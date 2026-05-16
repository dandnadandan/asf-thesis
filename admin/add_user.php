<?php
/**
 * Add New User - AJAX Handler
 * Creates new user account in database and sends verification email
 */

header('Content-Type: application/json');

require_once '../includes/session_manager.php';
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role - only administrators can add users
if (!canManageUsers()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only administrators can manage users']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['firstName', 'lastName', 'email', 'role', 'status', 'password', 'confirmPassword'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
}

// Validate email format
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate passwords match
if ($input['password'] !== $input['confirmPassword']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

// Validate password length
if (strlen($input['password']) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    
    // Generate username from email if not provided
    $username = strtolower(explode('@', $input['email'])[0]);
    
    // Check if username exists, if so, append number
    $base_username = $username;
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() == 0) {
            break;
        }
        $username = $base_username . $counter;
        $counter++;
    }
    
    // Hash password
    $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Determine is_active value (set to 0 initially, will be activated upon email verification)
    $is_active = 0;
    $is_verified = 0; // User needs to verify email
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    
    // Insert user
    $query = "INSERT INTO user_accounts 
              (first_name, last_name, email, username, password_hash, user_role, is_active, is_verified, email_verified_at, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
    
    $stmt = $pdo->prepare($query);
    $success = $stmt->execute([
        $input['firstName'],
        $input['lastName'],
        $input['email'],
        $username,
        $hashed_password,
        $input['role'],
        $is_active,
        $is_verified
    ]);
    
    if ($success) {
        $user_id = $pdo->lastInsertId();
        
        // Send verification email
        $emailSent = sendVerificationEmail($input['email'], $input['firstName'], $verification_token);
        
        if ($emailSent) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully! A verification email has been sent to ' . $input['email'],
                'user_id' => $user_id,
                'username' => $username,
                'email_sent' => true
            ]);
        } else {
            // User created but email failed
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully! However, verification email could not be sent. User will need to contact support.',
                'user_id' => $user_id,
                'username' => $username,
                'email_sent' => false
            ]);
        }
    } else {
        throw new Exception('Failed to insert user');
    }
    
} catch (Exception $e) {
    error_log("Error creating user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while creating the user. Please try again.'
    ]);
}

/**
 * Send verification email using PHPMailer
 */
function sendVerificationEmail($email, $first_name, $token) {
    try {
        // Include PHPMailer
        require '../vendor/autoload.php';
        
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
        $mail->Debugoutput = 'error_log';
        
        // SSL Options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $first_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, REPLY_TO_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ASF Surveillance System - Verify Your Email';
        
        // Build verification URL (go up one level from admin directory)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $verification_url = $protocol . "://" . $host . "/verify_email.php?email=" . urlencode($email) . "&token=" . $token;
        
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
                    An account has been created for you on the ASF Surveillance System by our administrator. To activate your account and get started, please verify your email address by clicking the button below.
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
                    <a href='{$verification_url}' style='color: #0d6efd; word-break: break-all;'>{$verification_url}</a>
                </p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='color: #666; font-size: 12px; margin: 0;'>
                        This verification link will expire in 24 hours. If you didn't expect this email, please contact support.
                    </p>
                </div>
            </div>
        </div>";
        
        $mail->AltBody = "
        Welcome to ASF Surveillance System!
        
        Hi {$first_name},
        
        An account has been created for you on the ASF Surveillance System. To activate your account, please verify your email address by visiting this link:
        
        {$verification_url}
        
        If you didn't expect this email, please contact support.
        
        Best regards,
        ASF Surveillance System Team
        CALABARZON Region";
        
        $mail->send();
        error_log("Verification email sent successfully to: " . $email);
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

