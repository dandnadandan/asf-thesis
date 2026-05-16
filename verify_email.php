<?php
session_start();

// Include database connection
require_once 'config/database.php';

$error = '';
$success = '';

// Check if email and token are provided
if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = trim($_GET['email']);
    $token = trim($_GET['token']);
    
    if (empty($email) || empty($token)) {
        $error = "Invalid verification link.";
    } else {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Check if user exists and is not already verified
            $stmt = $pdo->prepare("SELECT id, first_name, is_verified FROM user_accounts WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user['is_verified'] == 1) {
                    $success = "Your email is already verified. You can now log in to your account.";
                } else {
                    // For now, we'll just mark the user as verified since we don't have token storage
                    // In a production system, you might want to implement a more secure verification method
                    
                    // Update user to verified status
                    $updateStmt = $pdo->prepare("UPDATE user_accounts SET is_verified = 1, is_active = 1, email_verified_at = NOW() WHERE email = ?");
                    $updateStmt->execute([$email]);
                    
                    if ($updateStmt->rowCount() > 0) {
                        $success = "Email verified successfully! You can now log in to your account.";
                    } else {
                        $error = "Failed to verify email. Please try again or contact support.";
                    }
                }
            } else {
                $error = "User not found. Please check your verification link.";
            }
            
            $database->closeConnection();
            
        } catch (Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            $error = "An error occurred during verification. Please try again.";
        }
    }
} else {
    $error = "Invalid verification link.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Email Verification - ASF Surveillance System</title>
    <meta content="Verify your email address" name="description">
    
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
        
        /* Main container */
        .verification-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Verification card */
        .verification-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: slideInUp 1s ease-out;
        }
        
        .verification-card h1 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 2rem;
        }
        
        .verification-card p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        /* Message styling */
        .message {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: 500;
        }
        
        .success-message {
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid rgba(25, 135, 84, 0.3);
            color: #198754;
        }
        
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }
        
        /* Animations */
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .verification-card {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .verification-card h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-card">
            <h1>Email Verification</h1>
            
            <?php if ($error): ?>
                <div class="message error-message">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success-message">
                    <i class="bi bi-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="actions">
                <?php if ($success): ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login to Your Account
                    </a>
                <?php else: ?>
                    <a href="signup.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i>
                        Try Signing Up Again
                    </a>
                <?php endif; ?>
                
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-house"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
