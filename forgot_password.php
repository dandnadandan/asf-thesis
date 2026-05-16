<?php
session_start();

// Include authentication functions
require_once 'includes/auth_functions.php';

$message = '';
$messageType = '';

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = 'error';
    } else {
        // Check if email exists and send reset link
        $result = sendPasswordResetEmail($email);
        
        if ($result['success']) {
            $message = "If an account with that email exists, we've sent a password reset link. Please check your email.";
            $messageType = 'success';
        } else {
            // Don't reveal if email exists or not for security
            $message = "If an account with that email exists, we've sent a password reset link. Please check your email.";
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Forgot Password - ASF Surveillance System</title>
    <meta content="Reset your ASF Surveillance System password" name="description">
    
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
            background: linear-gradient(-45deg, #0d1b2a, #1b263b, #415a77, #1e3a5f);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
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
        
        .particle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .particle:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-delay: 2s;
        }
        
        .particle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 80%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .particle:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 70%;
            animation-delay: 1s;
        }
        
        .particle:nth-child(5) {
            width: 40px;
            height: 40px;
            top: 40%;
            left: 90%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.6;
            }
        }
        
        /* Main container */
        .forgot-container {
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
        
        .logo-section h1,
        .logo-section h5 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
            margin-top: 10px;
        }
        
        .logo-section p {
            color: #666;
            font-size: 0.9rem;
            font-style: normal;
            margin: 5px 0 0 0;
        }
        
        /* Forgot password form card */
        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 450px;
            width: 100%;
            animation: slideInUp 1s ease-out 0.3s both;
            position: relative;
            overflow: hidden;
        }
        
        .forgot-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(13, 110, 253, 0.1), transparent);
            transition: left 0.6s ease;
        }
        
        .forgot-card:hover::before {
            left: 100%;
        }
        
        .forgot-card h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .forgot-card p {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        /* Form groups */
        .form-group {
            margin-bottom: 25px;
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
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
            transform: translateY(-2px);
        }
        
        .form-group .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: color 0.3s ease;
            pointer-events: none;
        }
        
        .form-group input:focus + .input-icon {
            color: #0d6efd;
        }
        
        /* Submit button */
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3);
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(13, 110, 253, 0.4);
            background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
        }
        
        .submit-btn:active {
            transform: translateY(-1px);
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .submit-btn:hover::before {
            left: 100%;
        }
        
        /* Back to login link */
        .back-to-login {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-to-login a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-to-login a:hover {
            color: #0d6efd;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .forgot-card {
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
        
        .loading .submit-btn {
            background: #6c757d;
        }
        
        /* Message styling */
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .message.success {
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid rgba(25, 135, 84, 0.3);
            color: #198754;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
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
    
    <div class="forgot-container">
        <div class="forgot-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="uploads/asf_logo.png" alt="ASF Surveillance Logo" style="object-fit: contain; height: 60px;">
                <h5 style="color: #333; margin-top: 10px; font-weight: 600;">ASF Surveillance System</h5>
                <p style="color: #666; font-size: 0.9rem; margin: 5px 0 0 0; font-style: normal;">CALABARZON Region</p>
            </div>
            
            <h2>Forgot Password?</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Forgot Password Form -->
            <form method="POST" action="forgot_password.php" id="forgotForm">
                <div class="form-group" style="position: relative;">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email address" style="padding-right: 45px;">
                    <i class="bi bi-envelope input-icon"></i>
                </div>
                
                <button type="submit" class="submit-btn">
                    <span>Send Reset Link</span>
                </button>
            </form>
            
            <div class="back-to-login">
                <a href="login.php">
                    <i class="bi bi-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form submission handling with loading state
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const form = this;
            const submitBtn = form.querySelector('.submit-btn');
            
            // Validate form before submission
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                e.preventDefault();
                return false;
            }
            
            // Add loading state
            form.classList.add('loading');
            submitBtn.innerHTML = '<span><i class="bi bi-hourglass-split me-2"></i>Sending...</span>';
            submitBtn.disabled = true;
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
    </script>
</body>
</html>
