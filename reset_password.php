<?php
session_start();

// Include authentication functions
require_once 'includes/auth_functions.php';

$message = '';
$messageType = '';
$tokenValid = false;
$email = '';

// Check if token is provided and valid
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $tokenCheck = validatePasswordResetToken($token);
    
    if ($tokenCheck['success']) {
        $tokenValid = true;
        $email = $tokenCheck['email'];
    } else {
        $message = "Invalid or expired reset token. Please request a new password reset.";
        $messageType = 'error';
    }
} else {
    $message = "No reset token provided.";
    $messageType = 'error';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $message = "Please enter both password fields.";
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $messageType = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = 'error';
    } else {
        // Reset the password
        $result = resetPassword($token, $password);
        
        if ($result['success']) {
            $message = "Password has been successfully reset. You can now log in with your new password.";
            $messageType = 'success';
            $tokenValid = false; // Hide the form after successful reset
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Reset Password - ASF Surveillance System</title>
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
        .reset-container {
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
        
        /* Reset password form card */
        .reset-card {
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
        
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(13, 110, 253, 0.1), transparent);
            transition: left 0.6s ease;
        }
        
        .reset-card:hover::before {
            left: 100%;
        }
        
        .reset-card h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .reset-card p {
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
            cursor: pointer;
            pointer-events: auto;
        }
        
        .form-group input:focus + .input-icon {
            color: #0d6efd;
        }
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 10px;
            font-size: 0.8rem;
        }
        
        .strength-bar {
            height: 4px;
            background: #e1e5e9;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { width: 25%; background: #dc3545; }
        .strength-fair { width: 50%; background: #ffc107; }
        .strength-good { width: 75%; background: #17a2b8; }
        .strength-strong { width: 100%; background: #28a745; }
        
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
            .reset-card {
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
    
    <div class="reset-container">
        <div class="reset-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="uploads/asf_logo.png" alt="ASF Surveillance Logo" style="object-fit: contain; height: 60px;">
                <h5 style="color: #333; margin-top: 10px; font-weight: 600;">ASF Surveillance System</h5>
                <p style="color: #666; font-size: 0.9rem; margin: 5px 0 0 0; font-style: normal;">CALABARZON Region</p>
            </div>
            
            <h2>Reset Password</h2>
            <p style="text-align: center; color: #666; font-size: 0.95rem; margin-bottom: 25px;">Enter your new password to complete the reset process</p>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tokenValid): ?>
                <p>Enter your new password below.</p>
                
                <!-- Reset Password Form -->
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" id="resetForm">
                    <div class="form-group" style="position: relative;">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter new password" minlength="8" style="padding-right: 45px;">
                        <i class="bi bi-eye-slash input-icon" id="passwordToggle"></i>
                    </div>
                    
                    <div class="password-strength">
                        <span id="strengthText">Password strength</span>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="position: relative;">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" minlength="8" style="padding-right: 45px;">
                        <i class="bi bi-eye-slash input-icon" id="confirmPasswordToggle"></i>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <span>Reset Password</span>
                    </button>
                </form>
            <?php endif; ?>
            
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
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const feedback = [];
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
            
            const strengthText = document.getElementById('strengthText');
            const strengthFill = document.getElementById('strengthFill');
            
            strengthFill.className = 'strength-fill';
            
            if (strength <= 2) {
                strengthText.textContent = 'Weak';
                strengthFill.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthText.textContent = 'Fair';
                strengthFill.classList.add('strength-fair');
            } else if (strength === 4) {
                strengthText.textContent = 'Good';
                strengthFill.classList.add('strength-good');
            } else {
                strengthText.textContent = 'Strong';
                strengthFill.classList.add('strength-strong');
            }
        }
        
        // Password visibility toggle
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });
            }
            
            // Password visibility toggles
            document.getElementById('passwordToggle').addEventListener('click', function() {
                togglePasswordVisibility('password', 'passwordToggle');
            });
            
            document.getElementById('confirmPasswordToggle').addEventListener('click', function() {
                togglePasswordVisibility('confirm_password', 'confirmPasswordToggle');
            });
            
            // Form submission handling with loading state
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    const form = this;
                    const submitBtn = form.querySelector('.submit-btn');
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    // Validate passwords match
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match. Please try again.');
                        return false;
                    }
                    
                    // Validate password length
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long.');
                        return false;
                    }
                    
                    // Add loading state
                    form.classList.add('loading');
                    submitBtn.innerHTML = '<span><i class="bi bi-hourglass-split me-2"></i>Resetting...</span>';
                    submitBtn.disabled = true;
                });
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
    </script>
</body>
</html>
