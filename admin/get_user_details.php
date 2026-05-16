<?php
/**
 * Get User Details for Admin Panel
 * Returns user details as JSON for the view user modal
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log that the file is being accessed
error_log("get_user_details.php: File accessed at " . date('Y-m-d H:i:s'));

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff']);

// Check if user has permission to view user details
if (!hasRole(['administrator', 'administrative staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit();
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit();
}

$user_id = (int)$_GET['user_id'];

// Log the request for debugging
error_log("get_user_details.php: Request for user ID: " . $user_id);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch user details with all available fields (ASF schema)
    $sql = "SELECT 
                ua.id,
                ua.first_name,
                ua.last_name,
                ua.email,
                ua.username,
                ua.organization,
                ua.phone,
                ua.address,
                ua.province,
                ua.city,
                ua.postal_code,
                ua.user_role,
                ua.is_active,
                ua.is_verified,
                ua.email_verified_at,
                ua.created_at,
                ua.updated_at,
                ua.last_login_at,
                ua.profile_image
            FROM user_accounts ua 
            WHERE ua.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    
    error_log("get_user_details.php: SQL executed, rows found: " . $stmt->rowCount());
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("get_user_details.php: User data fetched: " . json_encode($user));
    
    // Format profile image path if it exists
    $profile_image_path = null;
    if (!empty($user['profile_image'])) {
        $profile_image_path = '../' . $user['profile_image'];
        // Check if file exists, if not, use default
        if (!file_exists(__DIR__ . '/' . $profile_image_path)) {
            $profile_image_path = '../bootstrap/assets/img/profile-img.jpg';
        }
    } else {
        $profile_image_path = '../bootstrap/assets/img/profile-img.jpg';
    }
    
    // Format the data for display (ASF schema)
    $formattedUser = [
        'id' => $user['id'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'email' => $user['email'] ?? '',
        'username' => $user['username'] ?? '',
        'organization' => $user['organization'] ?? 'N/A',
        'phone' => $user['phone'] ?? 'N/A',
        'address' => $user['address'] ?? 'N/A',
        'province' => $user['province'] ?? 'N/A',
        'city' => $user['city'] ?? 'N/A',
        'postal_code' => $user['postal_code'] ?? 'N/A',
        'user_role' => $user['user_role'] ?? 'Unknown',
        'is_active' => $user['is_active'] ? 'Active' : 'Inactive',
        'is_verified' => $user['is_verified'] ? 'Verified' : 'Not Verified',
        'email_verified_at' => $user['email_verified_at'] ? date('M d, Y H:i', strtotime($user['email_verified_at'])) : 'Never',
        'created_at' => $user['created_at'] ? date('M d, Y H:i', strtotime($user['created_at'])) : 'N/A',
        'updated_at' => $user['updated_at'] ? date('M d, Y H:i', strtotime($user['updated_at'])) : 'N/A',
        'last_login_at' => $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never',
        'profile_image' => $profile_image_path
    ];
    
    // Clean up empty values (except profile_image which should always have a path)
    foreach ($formattedUser as $key => $value) {
        if ($key !== 'profile_image' && ($value === '' || $value === null)) {
            $formattedUser[$key] = 'N/A';
        }
    }
    
    $database->closeConnection();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'user' => $formattedUser
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to fetch user details. Please try again.'
    ]);
}
?>
