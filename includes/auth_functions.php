<?php
/**
 * Authentication Functions for TaxEase
 * Handles user login, role-based redirects, and session management
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

/**
 * Authenticate user login and redirect based on role
 * 
 * @param string $email User's email address
 * @param string $password User's password
 * @return array Array with success status and redirect URL
 */
function authenticateUser($email, $password) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Prepare SQL statement to prevent SQL injection
        $sql = "SELECT id, username, email, password_hash, first_name, last_name, user_role, is_active, is_verified 
                FROM user_accounts 
                WHERE email = :email AND is_active = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if user is verified
            if (!$user['is_verified']) {
                return [
                    'success' => false,
                    'message' => 'Account not verified. Please check your email for verification link.',
                    'redirect' => null
                ];
            }
            
            // Get redirect URL based on user role
            $redirectUrl = getRedirectUrlByRole($user['user_role']);
            
            // Update last login timestamp
            updateLastLogin($conn, $user['id']);
            
            // Return success with user data and redirect URL
            return [
                'success' => true,
                'message' => 'Login successful',
                'redirect' => $redirectUrl,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'user_role' => $user['user_role'],
                    'is_verified' => $user['is_verified'] ?? 1
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid email or password',
                'redirect' => null
            ];
        }
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred during login. Please try again.',
            'redirect' => null
        ];
    } finally {
        if (isset($conn)) {
            $database->closeConnection();
        }
    }
}

/**
 * Get redirect URL based on user role
 * ASF Surveillance System: All users go to admin/index.php
 * Sidebar links are hidden/shown based on RBAC permissions
 * 
 * @param string $userRole User's role from database
 * @return string Redirect URL
 */
function getRedirectUrlByRole($userRole) {
    // All ASF Surveillance System users go to admin dashboard
    // Sidebar visibility is controlled by RBAC in admin/includes/sidebar.php
    return 'admin/index.php';
}

/**
 * Update user's last login timestamp
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return bool Success status
 */
function updateLastLogin($conn, $userId) {
    try {
        $sql = "UPDATE user_accounts SET last_login_at = CURRENT_TIMESTAMP WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
        return false;
    }
}

/**
 * Create user session and store in database
 * 
 * @param int $userId User ID
 * @param string $sessionToken Session token
 * @param string $ipAddress User's IP address
 * @param string $userAgent User's browser agent
 * @return bool Success status
 */
function createUserSession($userId, $sessionToken, $ipAddress = null, $userAgent = null) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Set session expiry to 24 hours
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':session_token', $sessionToken, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmt->bindParam(':expires_at', $expiresAt, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        $database->closeConnection();
        return $result;
        
    } catch (Exception $e) {
        error_log("Failed to create user session: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate user session token
 * 
 * @param string $sessionToken Session token to validate
 * @return array|false User data if valid, false if invalid
 */
function validateUserSession($sessionToken) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "SELECT us.session_token, us.expires_at, ua.id, ua.username, ua.email, ua.first_name, ua.last_name, ua.user_role
                FROM user_sessions us
                JOIN user_accounts ua ON us.user_id = ua.id
                WHERE us.session_token = :session_token 
                AND us.expires_at > CURRENT_TIMESTAMP
                AND ua.is_active = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':session_token', $sessionToken, PDO::PARAM_STR);
        $stmt->execute();
        
        $session = $stmt->fetch();
        
        $database->closeConnection();
        
        if ($session) {
            return [
                'id' => $session['id'],
                'username' => $session['username'],
                'email' => $session['email'],
                'first_name' => $session['first_name'],
                'last_name' => $session['last_name'],
                'user_role' => $session['user_role']
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Destroy user session
 * 
 * @param string $sessionToken Session token to destroy
 * @return bool Success status
 */
function destroyUserSession($sessionToken) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM user_sessions WHERE session_token = :session_token";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':session_token', $sessionToken, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        $database->closeConnection();
        return $result;
        
    } catch (Exception $e) {
        error_log("Failed to destroy user session: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean expired sessions from database
 * 
 * @return int Number of expired sessions removed
 */
function cleanExpiredSessions() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM user_sessions WHERE expires_at < CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $count = $stmt->rowCount();
        
        $database->closeConnection();
        return $count;
        
    } catch (Exception $e) {
        error_log("Failed to clean expired sessions: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user's IP address
 * 
 * @return string User's IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    return $ip;
}

/**
 * Get user's browser user agent
 * 
 * @return string User's browser agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Generate secure random session token
 * 
 * @param int $length Token length (default: 64)
 * @return string Secure random token
 */
function generateSessionToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if user has permission for specific action
 * 
 * @param string $userRole User's role
 * @param string $permission Permission name to check
 * @return bool True if user has permission
 */
function userHasPermission($userRole, $permission) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "SELECT COUNT(*) as count
                FROM role_permissions rp
                JOIN user_permissions up ON rp.permission_id = up.id
                WHERE rp.user_role = :user_role AND up.permission_name = :permission";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_role', $userRole, PDO::PARAM_STR);
        $stmt->bindParam(':permission', $permission, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        $database->closeConnection();
        
        return $result['count'] > 0;
        
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email to user
 * 
 * @param string $email User's email address
 * @return array Array with success status and message
 */
function sendPasswordResetEmail($email) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Check if email exists and user is active
        $sql = "SELECT id, email, first_name, last_name FROM user_accounts 
                WHERE email = :email AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if email exists or not for security
            return ['success' => true, 'message' => 'Reset link sent'];
        }
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        
        // Use database time to avoid timezone issues
        $timeSql = "SELECT DATE_ADD(NOW(), INTERVAL " . PASSWORD_RESET_EXPIRY_HOURS . " HOUR) as expires_at";
        $timeStmt = $conn->prepare($timeSql);
        $timeStmt->execute();
        $timeResult = $timeStmt->fetch();
        $expiresAt = $timeResult['expires_at'];
        
        // Log token generation for debugging
        error_log("Generated reset token: " . $resetToken);
        error_log("Token expires at: " . $expiresAt);
        error_log("Current PHP time: " . date('Y-m-d H:i:s'));
        error_log("Current database time: " . $conn->query("SELECT NOW()")->fetchColumn());
        
        // Store reset token in database
        $updateSql = "UPDATE user_accounts 
                      SET password_reset_token = :token, password_reset_expires_at = :expires 
                      WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bindParam(':token', $resetToken, PDO::PARAM_STR);
        $updateStmt->bindParam(':expires', $expiresAt, PDO::PARAM_STR);
        $updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to store reset token");
        }
        
        // Send email with reset link
        $resetLink = getAppUrl() . "/reset_password.php?token=" . $resetToken;
        error_log("Generated reset link: " . $resetLink);
        
        $to = $user['email'];
        $subject = PASSWORD_RESET_SUBJECT;
        
        // Use email template
        $variables = [
            'first_name' => $user['first_name'],
            'app_name' => APP_NAME,
            'reset_link' => $resetLink,
            'expiry_hours' => PASSWORD_RESET_EXPIRY_HOURS
        ];
        $message = getEmailTemplate(PASSWORD_RESET_TEMPLATE, $variables);
        
        // Send email using the fallback function
        if (sendEmailWithFallback($to, $subject, $message)) {
            $database->closeConnection();
            return ['success' => true, 'message' => 'Reset link sent'];
        } else {
            throw new Exception("Failed to send email");
        }
        
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        if (isset($database)) {
            $database->closeConnection();
        }
        return ['success' => false, 'message' => 'Failed to send reset email'];
    }
}

/**
 * Validate password reset token
 * 
 * @param string $token Reset token from email
 * @return array Array with success status and user email if valid
 */
function validatePasswordResetToken($token) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Log validation attempt
        error_log("Validating token: " . $token);
        error_log("Current timestamp: " . date('Y-m-d H:i:s'));
        
        // First, let's check what's in the database for this token
        $debugSql = "SELECT email, password_reset_expires_at, is_active FROM user_accounts 
                     WHERE password_reset_token = :token";
        $debugStmt = $conn->prepare($debugSql);
        $debugStmt->bindParam(':token', $token, PDO::PARAM_STR);
        $debugStmt->execute();
        $debugUser = $debugStmt->fetch();
        
        if ($debugUser) {
            error_log("Token found in database:");
            error_log("Email: " . $debugUser['email']);
            error_log("Expires at: " . $debugUser['password_reset_expires_at']);
            error_log("Is active: " . $debugUser['is_active']);
            error_log("Current time: " . date('Y-m-d H:i:s'));
            
            // Check if token is expired
            $expiresAt = new DateTime($debugUser['password_reset_expires_at']);
            $currentTime = new DateTime();
            $isExpired = $currentTime > $expiresAt;
            
            error_log("Token expired: " . ($isExpired ? 'Yes' : 'No'));
        } else {
            error_log("Token not found in database");
        }
        
        $sql = "SELECT email FROM user_accounts 
                WHERE password_reset_token = :token 
                AND password_reset_expires_at > NOW() 
                AND is_active = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        $database->closeConnection();
        
        if ($user) {
            error_log("Token validation successful for: " . $user['email']);
            return ['success' => true, 'email' => $user['email']];
        } else {
            error_log("Token validation failed - token invalid, expired, or user inactive");
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        if (isset($database)) {
            $database->closeConnection();
        }
        return ['success' => false, 'message' => 'Token validation failed'];
    }
}

/**
 * Reset user password using reset token
 * 
 * @param string $token Reset token from email
 * @param string $newPassword New password to set
 * @return array Array with success status and message
 */
function resetPassword($token, $newPassword) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Validate token first
        $tokenCheck = validatePasswordResetToken($token);
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        // Hash new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $sql = "UPDATE user_accounts 
                SET password_hash = :password_hash, 
                    password_reset_token = NULL, 
                    password_reset_expires_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE password_reset_token = :token";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $database->closeConnection();
            return ['success' => true, 'message' => 'Password reset successful'];
        } else {
            throw new Exception("Failed to update password");
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        if (isset($database)) {
            $database->closeConnection();
        }
        return ['success' => false, 'message' => 'Failed to reset password'];
    }
}
?>
