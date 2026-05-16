<?php
/**
 * Update User Role for TaxEase Admin
 * Handles AJAX requests to update only user role
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], '../unauthorized.php');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if user ID is provided
if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Check if user role is provided
if (!isset($_POST['user_role']) || empty(trim($_POST['user_role']))) {
    http_response_code(400);
    echo json_encode(['error' => 'User role is required']);
    exit();
}

$userId = (int)$_POST['user_id'];
$newRole = trim($_POST['user_role']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if user exists and is not a client
    $checkStmt = $pdo->prepare("SELECT id, user_role FROM user_accounts WHERE id = ? AND user_role != 'client'");
    $checkStmt->execute([$userId]);
    
    if ($checkStmt->rowCount() == 0) {
        throw new Exception("User not found or cannot edit client users");
    }
    
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $oldRole = $user['user_role'];
    
    // Don't allow changing to client role
    if ($newRole === 'client') {
        throw new Exception("Cannot change user role to 'client'");
    }
    
    // Don't allow changing if role is the same
    if ($oldRole === $newRole) {
        throw new Exception("User already has this role");
    }
    
    // Update only the user role
    $sql = "UPDATE user_accounts SET 
                user_role = ?,
                updated_at = ?
            WHERE id = ? AND user_role != 'client'";
    
    $params = [
        $newRole,
        date('Y-m-d H:i:s'),
        $userId
    ];
    
    $updateStmt = $pdo->prepare($sql);
    $result = $updateStmt->execute($params);
    
    if ($result) {
        // Log the role change
        error_log("User role changed successfully: ID=$userId, Old Role=$oldRole, New Role=$newRole");
        
        echo json_encode([
            'success' => true,
            'message' => 'User role changed successfully',
            'user_id' => $userId,
            'old_role' => $oldRole,
            'new_role' => $newRole
        ]);
    } else {
        throw new Exception("Failed to update user role");
    }
    
    $database->closeConnection();
    
} catch (Exception $e) {
    error_log("Error changing user role: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to change user role: ' . $e->getMessage()
    ]);
}
?>
