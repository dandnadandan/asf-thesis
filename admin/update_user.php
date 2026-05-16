<?php
/**
 * Update User for TaxEase Admin
 * Handles AJAX requests to update user information
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

$userId = (int)$_POST['user_id'];

// Validate required fields
$requiredFields = ['first_name', 'last_name', 'email', 'user_role', 'is_active'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit();
    }
}

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
    
    // Prepare update data
    $updateData = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'user_role' => trim($_POST['user_role']),
        'is_active' => (int)$_POST['is_active'],
        'employee_id' => isset($_POST['employee_id']) ? trim($_POST['employee_id']) : null,
        'company_name' => isset($_POST['company_name']) ? trim($_POST['company_name']) : null,
        'phone' => isset($_POST['phone']) ? trim($_POST['phone']) : null,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Validate email format
    if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }
    
    // Check if email is already taken by another user
    $emailCheckStmt = $pdo->query("SELECT id FROM user_accounts WHERE email = ? AND id != ?");
    $emailCheckStmt->execute([$updateData['email'], $userId]);
    if ($emailCheckStmt->rowCount() > 0) {
        throw new Exception("Email address is already in use by another user");
    }
    
    // Build UPDATE query
    $sql = "UPDATE user_accounts SET 
                first_name = ?,
                last_name = ?,
                email = ?,
                user_role = ?,
                is_active = ?,
                employee_id = ?,
                company_name = ?,
                phone = ?,
                updated_at = ?
            WHERE id = ? AND user_role != 'client'";
    
    $params = [
        $updateData['first_name'],
        $updateData['last_name'],
        $updateData['email'],
        $updateData['user_role'],
        $updateData['is_active'],
        $updateData['employee_id'],
        $updateData['company_name'],
        $updateData['phone'],
        $updateData['updated_at'],
        $userId
    ];
    
    $updateStmt = $pdo->prepare($sql);
    $result = $updateStmt->execute($params);
    
    if ($result) {
        // Log the update
        error_log("User updated successfully: ID=$userId, Role={$updateData['user_role']}, Status={$updateData['is_active']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully',
            'user_id' => $userId
        ]);
    } else {
        throw new Exception("Failed to update user");
    }
    
    $database->closeConnection();
    
} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to update user: ' . $e->getMessage()
    ]);
}
?>
