<?php
/**
 * Unauthorized Access Page for TaxEase
 * Shown when users don't have required permissions
 */

require_once 'includes/session_manager.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Unauthorized Access - TaxEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #dc2626, #ef4444, #f87171);
            min-height: 100vh;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .unauthorized-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            width: 100%;
        }
        .icon-large {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.8);
        }
        .btn-group {
            margin-top: 30px;
        }
        .btn-group .btn {
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="unauthorized-card">
                    <i class="bi bi-shield-exclamation icon-large"></i>
                    <h1 class="display-5 mb-3">Access Denied</h1>
                    <p class="lead mb-4">
                        Sorry, you don't have permission to access this page. 
                        Please contact your administrator if you believe this is an error.
                    </p>
                    
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-outline-light">
                            <i class="bi bi-house me-2"></i>Go Home
                        </a>
                        <a href="login.php" class="btn btn-light">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </a>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="mt-4">
                            <p class="text-muted">
                                Currently logged in as: <strong><?php echo htmlspecialchars(getCurrentUser()['name']); ?></strong><br>
                                Role: <strong><?php echo htmlspecialchars(getCurrentUser()['role']); ?></strong>
                            </p>
                            <a href="logout.php" class="btn btn-sm btn-outline-light">
                                <i class="bi bi-box-arrow-right me-1"></i>Logout
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
