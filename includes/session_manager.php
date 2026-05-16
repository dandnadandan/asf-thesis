<?php
/**
 * Session Management for ASF Surveillance System
 * Handles session validation, logout, and security
 */

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/date_helper.php';

/**
 * Start secure session with proper configuration
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters only before starting session
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
    }
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if user is logged in and has valid session
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has one of the specified roles
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array(strtolower($_SESSION['user_role']), array_map('strtolower', $roles));
}

/**
 * Check if user is administrator
 * 
 * @return bool True if user is administrator
 */
function isAdministrator() {
    return hasRole(['administrator']);
}

/**
 * Check if user is administrator (ASF Surveillance System)
 * Only administrators can access: user management, system alerts, content management, news, system settings
 * 
 * @return bool True if user is administrator
 */
function isASFAdministrator() {
    return hasRole(['administrator']);
}

/**
 * Check if user is field staff
 * 
 * @return bool True if user is field staff
 */
function isFieldStaff() {
    return hasRole(['field_staff']);
}

/**
 * Check if user is analyst
 * 
 * @return bool True if user is analyst
 */
function isAnalyst() {
    return hasRole(['analyst']);
}

/**
 * Check if user is viewer
 * 
 * @return bool True if user is viewer
 */
function isViewer() {
    return hasRole(['viewer']);
}

/**
 * Check if user is supervisor
 * 
 * @return bool True if user is supervisor
 */
function isSupervisor() {
    return hasRole(['supervisor']);
}

/**
 * Check if user is veterinarian
 * 
 * @return bool True if user is veterinarian
 */
function isVeterinarian() {
    return hasRole(['veterinarian']);
}

/**
 * Check if user is inspector
 * 
 * @return bool True if user is inspector
 */
function isInspector() {
    return hasRole(['inspector']);
}

/**
 * Check if user is data entry
 * 
 * @return bool True if user is data entry
 */
function isDataEntry() {
    return hasRole(['data_entry']);
}

/**
 * Check if user can access user management (administrator only)
 * 
 * @return bool True if user can access user management
 */
function canManageUsers() {
    return isASFAdministrator();
}

/**
 * Check if user can access system alerts (administrator only)
 * 
 * @return bool True if user can access system alerts
 */
function canManageSystemAlerts() {
    return isASFAdministrator();
}

/**
 * Check if user can access content management (administrator only)
 * 
 * @return bool True if user can access content management
 */
function canManageContent() {
    return isASFAdministrator();
}

/**
 * Check if user can access news and announcements (administrator only)
 * 
 * @return bool True if user can access news and announcements
 */
function canManageNews() {
    return isASFAdministrator();
}

/**
 * Check if user can access system settings (administrator only)
 * 
 * @return bool True if user can access system settings
 */
function canManageSystemSettings() {
    return isASFAdministrator();
}

/**
 * Check if user can access admin profile (administrator only)
 * 
 * @return bool True if user can access admin profile
 */
function canAccessAdminProfile() {
    return isASFAdministrator();
}

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Determine the correct path to login.php based on current directory
        $currentDir = dirname($_SERVER['PHP_SELF']);
        
        // Check if we're in any subdirectory (admin, client, employees, owner)
        if (strpos($currentDir, '/admin') !== false || 
            strpos($currentDir, '/client') !== false || 
            strpos($currentDir, '/employees') !== false || 
            strpos($currentDir, '/owner') !== false) {
            // If we're in a subdirectory, go up one level to reach root
            header("Location: ../login.php");
        } else {
            // If we're in root directory, use relative path
            header("Location: login.php");
        }
        exit();
    }
}

/**
 * Require user to have specific role, redirect to unauthorized if not
 * 
 * @param string|array $roles Required role(s)
 * @param string $redirectUrl URL to redirect if unauthorized
 */
function requireRole($roles, $redirectUrl = 'unauthorized.php') {
    requireLogin();
    
    if (!hasRole($roles)) {
        header("Location: " . $redirectUrl);
        exit();
    }
}

/**
 * Logout user and destroy session
 * 
 * @param string $redirectUrl URL to redirect after logout
 */
function logout($redirectUrl = 'login.php') {
    // Destroy database session if exists
    if (isset($_SESSION['session_token'])) {
        destroyUserSession($_SESSION['session_token']);
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // If redirectUrl is relative and we're in a subdirectory, adjust the path
    if (strpos($redirectUrl, 'http') !== 0 && strpos($redirectUrl, '//') !== 0) {
        $currentDir = dirname($_SERVER['PHP_SELF']);
        
        // Check if we're in any subdirectory (admin, client, employees, owner)
        if ((strpos($currentDir, '/admin') !== false || 
             strpos($currentDir, '/client') !== false || 
             strpos($currentDir, '/employees') !== false || 
             strpos($currentDir, '/owner') !== false) && 
            strpos($redirectUrl, '../') !== 0) {
            // If we're in a subdirectory and the redirect URL doesn't already go up, add it
            $redirectUrl = '../' . $redirectUrl;
        }
    }
    
    // Redirect to specified URL
    header("Location: " . $redirectUrl);
    exit();
}

/**
 * Get current user's information
 * 
 * @return array|null User information or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'is_verified' => $_SESSION['is_verified'] ?? false
    ];
}

/**
 * Get current user's role
 * 
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user's ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Validate session token from database
 * 
 * @return bool True if session is valid
 */
function validateDatabaseSession() {
    if (!isset($_SESSION['session_token'])) {
        return false;
    }
    
    $userData = validateUserSession($_SESSION['session_token']);
    if ($userData) {
        // Update session with fresh user data
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_role'] = $userData['user_role'];
        $_SESSION['user_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
        $_SESSION['username'] = $userData['username'];
        return true;
    }
    
    return false;
}

/**
 * Clean up expired sessions periodically
 */
function cleanupExpiredSessions() {
    // Only clean up every 100 requests to avoid performance impact
    if (!isset($_SESSION['last_cleanup']) || rand(1, 100) === 1) {
        cleanExpiredSessions();
        $_SESSION['last_cleanup'] = time();
    }
}

/**
 * Set session timeout and handle automatic logout
 * 
 * @param int $timeoutMinutes Session timeout in minutes (default: 30)
 */
function handleSessionTimeout($timeoutMinutes = 30) {
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > $timeoutMinutes * 60)) {
        // Determine the correct path to login.php based on current directory
        $currentDir = dirname($_SERVER['PHP_SELF']);
        
        // Check if we're in any subdirectory (admin, client, employees, owner)
        if (strpos($currentDir, '/admin') !== false || 
            strpos($currentDir, '/client') !== false || 
            strpos($currentDir, '/employees') !== false || 
            strpos($currentDir, '/owner') !== false) {
            // If we're in a subdirectory, go up one level to reach root
            logout('../login.php?timeout=1');
        } else {
            // If we're in root directory, use relative path
            logout('login.php?timeout=1');
        }
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Initialize session security
 */
function initSessionSecurity() {
    startSecureSession();
    handleSessionTimeout();
    cleanupExpiredSessions();
    
    // Validate database session if remember me is active
    if (isset($_SESSION['session_token'])) {
        validateDatabaseSession();
    }
}

// Initialize session security when file is included
initSessionSecurity();
?>
