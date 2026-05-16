<?php
session_start();

// Include authentication functions and database
require_once 'includes/auth_functions.php';
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $redirectUrl = getRedirectUrlByRole($_SESSION['user_role']);
    header("Location: " . $redirectUrl);
    exit();
}

$error = '';
$success = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city_municipality = trim($_POST['city_municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    $country = trim($_POST['country'] ?? '');
    $user_role = 'client'; // Default role for signup
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email address is already registered.";
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $error = "Username is already taken.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate verification token
                    $verification_token = bin2hex(random_bytes(32));
                    
                                         // Insert new user
                     $sql = "INSERT INTO user_accounts (
                         first_name, last_name, email, username, password_hash, 
                         company_name, phone, address, region, province, 
                         city_municipality, barangay, country, user_role, is_active, 
                         is_verified, email_verified_at, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
                     
                     $stmt = $pdo->prepare($sql);
                     $stmt->execute([
                         $first_name, $last_name, $email, $username, $hashed_password,
                         $company_name, $phone, $address, $region, $province,
                         $city_municipality, $barangay, $country, $user_role, 0, 0
                     ]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Send verification email
                    if (sendVerificationEmail($email, $first_name, $verification_token)) {
                        $success = "Account created successfully! Please check your email to verify your account.";
                    } else {
                        // Account created but email failed - user can request resend
                        $success = "Account created successfully! However, verification email could not be sent. Please contact support.";
                    }
                    
                    $database->closeConnection();
                }
            }
        } catch (Exception $e) {
            error_log("Signup error: " . $e->getMessage());
            $error = "An error occurred during signup. Please try again.";
        }
    }
}

// Load email configuration
require_once 'config/email_config.php';

/**
 * Send verification email using PHPMailer
 */
function sendVerificationEmail($email, $first_name, $token) {
    try {
        // Include PHPMailer
        require 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Enable debug for troubleshooting (set to 0 in production)
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $first_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ASF Surveillance System - Verify Your Email';
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $verification_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?email=" . urlencode($email) . "&token=" . $token;
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>ASF Surveillance System</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>CALABARZON Region</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #333; margin-bottom: 20px;'>Welcome to ASF Surveillance System!</h2>
                
                <p style='color: #555; line-height: 1.6; margin-bottom: 20px;'>
                    Hi <strong>{$first_name}</strong>,<br><br>
                    Thank you for registering with the ASF Surveillance System! To get started, please verify your email address by clicking the button below.
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verification_url}' 
                       style='background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); 
                              color: white; 
                              padding: 15px 30px; 
                              text-decoration: none; 
                              border-radius: 8px; 
                              display: inline-block; 
                              font-weight: bold;
                              box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);'>
                        Verify Email Address
                    </a>
                </div>
                
                <p style='color: #666; font-size: 14px; line-height: 1.5;'>
                    If the button above doesn't work, you can copy and paste this link into your browser:<br>
                    <a href='{$verification_url}' style='color: #0d6efd;'>{$verification_url}</a>
                </p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='color: #666; font-size: 12px; margin: 0;'>
                        This verification link will expire in 24 hours. If you didn't create this account, please ignore this email.
                    </p>
                </div>
            </div>
        </div>";
        
        $mail->AltBody = "
        Welcome to ASF Surveillance System!
        
        Hi {$first_name},
        
        Thank you for registering with the ASF Surveillance System! To get started, please verify your email address by visiting this link:
        
        {$verification_url}
        
        If you didn't create this account, please ignore this email.
        
        Best regards,
        ASF Surveillance System Team
        CALABARZON Region";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Sign Up - ASF Surveillance System</title>
    <meta content="Create your ASF Surveillance System account" name="description">
    
    <!-- Favicons -->
    <link href="uploads/asf_logo.png" rel="icon">
    <link href="uploads/asf_logo.png" rel="apple-touch-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Philippine Address Selector using local JSON data -->
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(-45deg, #0a0e27, #1a1f3a, #2d3748, #1e3a8a);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating particles animation */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .particle:nth-child(1) { width: 80px; height: 80px; top: 20%; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 120px; height: 120px; top: 60%; left: 80%; animation-delay: 2s; }
        .particle:nth-child(3) { width: 60px; height: 60px; top: 80%; left: 20%; animation-delay: 4s; }
        .particle:nth-child(4) { width: 100px; height: 100px; top: 10%; left: 70%; animation-delay: 1s; }
        .particle:nth-child(5) { width: 40px; height: 40px; top: 40%; left: 90%; animation-delay: 3s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.3; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.6; }
        }
        
        /* Main container */
        .signup-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Logo section */
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInDown 1s ease-out;
        }
        
        .logo-section img {
            height: 60px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        
        .logo-section h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .logo-section p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-style: italic;
            margin: 0;
        }
        
        /* Signup form card */
        .signup-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 600px;
            width: 100%;
            animation: slideInUp 1s ease-out 0.3s both;
            position: relative;
            overflow: hidden;
        }
        
        .signup-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(30, 58, 138, 0.1), transparent);
            transition: left 0.6s ease;
        }
        
        .signup-card:hover::before {
            left: 100%;
        }
        
        .signup-card h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        /* Form groups */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: color 0.3s ease;
        }
        
        .form-group input:focus + .input-icon {
            color: #1e3a8a;
        }
        
        /* Two column layout for larger screens */
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Signup button */
        .signup-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
            margin-top: 20px;
        }
        
        .signup-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(30, 58, 138, 0.4);
            background: linear-gradient(135deg, #1e40af 0%, #0f172a 100%);
        }
        
        .signup-btn:active {
            transform: translateY(-1px);
        }
        
        .signup-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .signup-btn:hover::before {
            left: 100%;
        }
        
        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .login-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .login-link a:hover {
            color: #1e3a8a;
        }
        
        /* Back to home link */
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-home a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-home a:hover {
            color: #1e3a8a;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .signup-card {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .logo-section h1 {
                font-size: 2rem;
            }
            
            .logo-section p {
                font-size: 1rem;
            }
        }
        
        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .loading .signup-btn {
            background: #6c757d;
        }
        
        /* Error message styling */
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Success message styling */
        .success-message {
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid rgba(25, 135, 84, 0.3);
            color: #198754;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }
        
        /* Philippine Address Selector Custom Styling */
        #philippine-address-selector {
            margin-bottom: 20px;
        }
        
        #philippine-address-selector select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        #philippine-address-selector select:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            transform: translateY(-1px);
        }
        
        #philippine-address-selector select:disabled {
            background: rgba(248, 249, 250, 0.9);
            color: #6c757d;
            cursor: not-allowed;
        }
        
        #philippine-address-selector label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        #philippine-address-selector .form-group {
            margin-bottom: 15px;
        }
        
        #philippine-address-selector .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            #philippine-address-selector .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Animated background particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <div class="signup-container">
        <div class="signup-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="uploads/asf_logo.png" alt="ASF Surveillance Logo" style="object-fit: contain;">
                <h5>ASF Surveillance System</h5>
            </div>
            
            <h2>Create Your Account</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Signup Form -->
            <form method="POST" action="signup.php" id="signupForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               placeholder="Enter your first name">
                        <i class="bi bi-person input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               placeholder="Enter your last name">
                        <i class="bi bi-person input-icon"></i>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter your email">
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Choose a username">
                        <i class="bi bi-person-badge input-icon"></i>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Create a password" minlength="8">
                        <i class="bi bi-lock input-icon"></i>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                        <i class="bi bi-lock-fill input-icon"></i>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" 
                               value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                               placeholder="Enter company name (optional)">
                        <i class="bi bi-building input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                               placeholder="Enter phone number (optional)">
                        <i class="bi bi-telephone input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="philippine-address">Philippine Address</label>
                    <div id="philippine-address-selector"></div>
                    <input type="hidden" id="region" name="region" value="<?php echo htmlspecialchars($_POST['region'] ?? ''); ?>">
                    <input type="hidden" id="province" name="province" value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                    <input type="hidden" id="city_municipality" name="city_municipality" value="<?php echo htmlspecialchars($_POST['city_municipality'] ?? ''); ?>">
                    <input type="hidden" id="barangay" name="barangay" value="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
                    
                    <input type="hidden" id="country" name="country" value="Philippines">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" 
                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                           placeholder="Enter street address, building, house number (optional)">
                    <i class="bi bi-geo-alt input-icon"></i>
                </div>
                
                <button type="submit" class="signup-btn">
                    <span>Create Account</span>
                </button>
            </form>
            
            <div class="login-link">
                <a href="login.php">
                    <i class="bi bi-arrow-left"></i>
                    Already have an account? Sign in
                </a>
            </div>
            
            <div class="back-home">
                <a href="index.php">
                    <i class="bi bi-house"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <!-- jQuery (required for Philippine Address Selector) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Philippine Address Selector using local JSON data -->
    
    <script>
        // Form submission handling with loading state
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const form = this;
            const submitBtn = form.querySelector('.signup-btn');
            
            // Add loading state
            form.classList.add('loading');
            submitBtn.innerHTML = '<span>Creating Account...</span>';
            
            // Simulate loading delay (remove in production)
            setTimeout(() => {
                form.classList.remove('loading');
                submitBtn.innerHTML = '<span>Create Account</span>';
            }, 2000);
        });
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                strengthDiv.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                feedback = 'Weak password';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (strength <= 3) {
                feedback = 'Medium strength password';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                feedback = 'Strong password';
                strengthDiv.className = 'password-strength strength-strong';
            }
            
            strengthDiv.textContent = feedback;
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Input focus effects
        document.querySelectorAll('.form-group input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // Add some interactive particle effects
        document.addEventListener('mousemove', function(e) {
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                const speed = (index + 1) * 0.5;
                const x = (window.innerWidth - e.pageX * speed) / 100;
                const y = (window.innerHeight - e.pageY * speed) / 100;
                particle.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
        
        // Initialize Philippine Address Selector using local JSON data
        $(document).ready(function() {
            initializePhilippineAddressSelector();
        });
        
        // Philippine Address Selector using local JSON data
        function initializePhilippineAddressSelector() {
            const container = document.getElementById('philippine-address-selector');
            if (!container) return;
            
            container.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label for="region_select">Region *</label>
                        <select id="region_select" name="region_select" required>
                            <option value="">Select Region</option>
                        </select>
                        <i class="bi bi-geo-alt input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label for="province_select">Province *</label>
                        <select id="province_select" name="province_select" required disabled>
                            <option value="">Select Province</option>
                        </select>
                        <i class="bi bi-geo-alt input-icon"></i>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="city_select">City/Municipality *</label>
                        <select id="city_select" name="city_select" required disabled>
                            <option value="">Select City/Municipality</option>
                        </select>
                        <i class="bi bi-geo-alt input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label for="barangay_select">Barangay *</label>
                        <select id="barangay_select" name="barangay_select" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                        <i class="bi bi-geo-alt input-icon"></i>
                    </div>
                </div>
            `;
            
            // Load address data from local JSON file
            loadAddressData();
            
            // Add event listeners
            document.getElementById('region_select').addEventListener('change', function() {
                const regionId = this.value;
                if (regionId) {
                    loadProvinces(regionId);
                    // Reset dependent fields
                    resetSelect('city_select');
                    resetSelect('barangay_select');
                }
            });
            
            document.getElementById('province_select').addEventListener('change', function() {
                const provinceId = this.value;
                if (provinceId) {
                    loadCities(provinceId);
                    // Reset dependent fields
                    resetSelect('barangay_select');
                }
            });
            
            document.getElementById('city_select').addEventListener('change', function() {
                const cityId = this.value;
                if (cityId) {
                    loadBarangays(cityId);
                }
            });
            
            document.getElementById('barangay_select').addEventListener('change', function() {
                const barangay = this.options[this.selectedIndex].text;
                document.getElementById('barangay').value = barangay;
            });
        }
        
        // Global variable to store address data
        let addressData = null;
        
        // Load address data from local JSON file
        async function loadAddressData() {
            try {
                const response = await fetch('PH-Address-API-main/ph-location.json');
                addressData = await response.json();
                
                // Load regions
                loadRegions();
            } catch (error) {
                console.error('Error loading address data:', error);
                showApiError('Failed to load address data. Please refresh the page.');
            }
        }
        
        // Load regions from local data
        function loadRegions() {
            if (!addressData || !addressData.region) return;
            
            const regionSelect = document.getElementById('region_select');
            const regions = addressData.region.sort((a, b) => a.name.localeCompare(b.name));
            
            regions.forEach(region => {
                if (region && region.name) {
                    const option = document.createElement('option');
                    option.value = region.id;
                    option.textContent = region.name;
                    regionSelect.appendChild(option);
                }
            });
        }
        
        // Load provinces based on selected region
        function loadProvinces(regionId) {
            if (!addressData || !addressData.province) return;
            
            const provinceSelect = document.getElementById('province_select');
            resetSelect('province_select');
            provinceSelect.disabled = false;
            
            // Find provinces that belong to the selected region
            const provinces = addressData.province
                .filter(item => item.region_code === regionId)
                .sort((a, b) => a.name.localeCompare(b.name));
            
            provinces.forEach(province => {
                if (province && province.name) {
                    const option = document.createElement('option');
                    option.value = province.id;
                    option.textContent = province.name;
                    provinceSelect.appendChild(option);
                }
            });
            
            // Update hidden field with region name
            const selectedRegion = addressData.region.find(r => r.id === regionId);
            if (selectedRegion) {
                document.getElementById('region').value = selectedRegion.name;
            }
        }
        
        // Load cities based on selected province
        function loadCities(provinceId) {
            if (!addressData || !addressData.city) return;
            
            const citySelect = document.getElementById('city_select');
            resetSelect('city_select');
            citySelect.disabled = false;
            
            // Find cities that belong to the selected province
            const cities = addressData.city
                .filter(item => item.province_code === provinceId)
                .sort((a, b) => a.name.localeCompare(b.name));
            
            cities.forEach(city => {
                if (city && city.name) {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name;
                    citySelect.appendChild(option);
                }
            });
            
            // Update hidden field with province name
            const selectedProvince = addressData.province.find(p => p.id === provinceId);
            if (selectedProvince) {
                document.getElementById('province').value = selectedProvince.name;
            }
        }
        
        // Load barangays based on selected city
        function loadBarangays(cityId) {
            if (!addressData || !addressData.barangay) return;
            
            const barangaySelect = document.getElementById('barangay_select');
            resetSelect('barangay_select');
            barangaySelect.disabled = false;
            
            // Find barangays that belong to the selected city
            const barangays = addressData.barangay
                .filter(item => item.city_code === cityId)
                .sort((a, b) => a.name.localeCompare(b.name));
            
            barangays.forEach(barangay => {
                if (barangay && barangay.name && barangay.name.trim() !== '') {
                    const option = document.createElement('option');
                    option.value = barangay.id;
                    option.textContent = barangay.name;
                    barangaySelect.appendChild(option);
                }
            });
            
            // Update hidden field with city name
            const selectedCity = addressData.city.find(c => c.id === cityId);
            if (selectedCity) {
                document.getElementById('city_municipality').value = selectedCity.name;
            }
        }
        
        // Reset select element
        function resetSelect(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = `<option value="">Select ${selectId.replace('_select', '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>`;
            select.disabled = true;
        }
        
        // Show API error message
        function showApiError(message) {
            const container = document.getElementById('philippine-address-selector');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            container.appendChild(errorDiv);
            
            // Remove error after 5 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }
    </script>
</body>
</html>
