<?php
/**
 * Delete User - AJAX Handler
 * Deletes user account from database
 */

header('Content-Type: application/json');

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role - only administrators can delete users
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
if (empty($input['userId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Prevent deletion of user ID 1 (default administrator)
if ($input['userId'] == 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Cannot delete the default administrator account (User ID 1)']);
    exit();
}

$userId = $input['userId'];
$currentUserId = getCurrentUser()['id'];

// Prevent self-deletion
if ($userId == $currentUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, user_role FROM user_accounts WHERE id = ?");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Optional: Prevent deletion of owner accounts
    if (strtolower($user['user_role']) === 'owner') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Owner accounts cannot be deleted']);
        exit();
    }
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM user_accounts WHERE id = ?");
    $success = $stmt->execute([$userId]);
    
    if ($success && $stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully!'
        ]);
    } else {
        throw new Exception('Failed to delete user');
    }
    
    $database->closeConnection();
    
} catch (Exception $e) {
    error_log("Error deleting user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the user. Please try again.'
    ]);
}
?>

