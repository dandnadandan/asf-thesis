<?php
/**
 * Edit User - AJAX Handler
 * Updates user account information
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

// Require administrator role - only administrators can edit users
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

// Prevent changing role of user ID 1 (must remain administrator)
if ($input['userId'] == 1 && isset($input['role']) && strtolower($input['role']) !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Cannot change the role of the default administrator account (User ID 1). It must remain as Administrator.']);
    exit();
}

// Validate role value
$validRoles = ['administrator', 'supervisor', 'veterinarian', 'inspector', 'analyst', 'field_staff', 'data_entry', 'viewer'];
if (!in_array(strtolower($input['role']), array_map('strtolower', $validRoles))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role specified. Allowed roles: ' . implode(', ', $validRoles)]);
    exit();
}

$required_fields = ['firstName', 'lastName', 'email', 'role'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
}

// Validate email format
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $userId = (int)$input['userId'];
    $firstName = trim($input['firstName']);
    $lastName = trim($input['lastName']);
    $email = trim($input['email']);
    $role = trim($input['role']); // ENUM values must match exactly
    $status = $input['status'] ?? 'Active';
    
    // For user ID 1, always force role to be administrator
    if ($userId == 1) {
        $role = 'administrator';
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, user_role FROM user_accounts WHERE id = ?");
    $stmt->execute([$userId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Check if email is already used by another user
    $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already in use by another user']);
        exit();
    }
    
    // Determine is_active value
    $is_active = ($status === 'Active') ? 1 : 0;
    
    // Update user - basic info only (not password)
    $query = "UPDATE user_accounts 
              SET first_name = ?, 
                  last_name = ?, 
                  email = ?, 
                  user_role = ?, 
                  is_active = ?,
                  updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $pdo->prepare($query);
    
    error_log("Attempting to update user - ID: $userId, Role: $role, Email: $email");
    
    $success = $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $role,
        $is_active,
        $userId
    ]);
    
    $rowsAffected = $stmt->rowCount();
    error_log("User update executed. Rows affected: " . $rowsAffected);
    error_log("Update data - User ID: $userId, Role: $role, Status: $status, First Name: $firstName, Last Name: $lastName");
    
    if ($rowsAffected > 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully!',
            'user_id' => $userId,
            'role' => $role
        ]);
    } else {
        // Check if the data is actually different by querying the current user
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, user_role, is_active FROM user_accounts WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Current user data: " . print_r($currentUser, true));
        error_log("New user data - Role: $role, First Name: $firstName, Last Name: $lastName, Email: $email, Active: $is_active");
        
        // If data matches, that's fine
        if ($currentUser && 
            $currentUser['first_name'] === $firstName && 
            $currentUser['last_name'] === $lastName && 
            $currentUser['email'] === $email && 
            $currentUser['user_role'] === $role && 
            $currentUser['is_active'] == $is_active) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'User information updated (no changes detected)',
                'user_id' => $userId,
                'role' => $role
            ]);
        } else {
            // Data is different but no rows affected - this is an error
            throw new Exception("Update query executed but no rows were affected. This may indicate a database constraint issue.");
        }
    }
    
    $database->closeConnection();
    
} catch (PDOException $e) {
    error_log("PDO Error updating user: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    error_log("PDO Error Info: " . print_r($e->errorInfo, true));
    
    http_response_code(500);
    
    // Provide more specific error message for common issues
    $errorMessage = 'An error occurred while updating the user.';
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Data truncated') !== false || strpos($errorMsg, 'ENUM') !== false) {
        $errorMessage = 'Invalid role value. The role "' . htmlspecialchars($role) . '" is not a valid ENUM value. Please check that the database ENUM includes all required roles.';
    } elseif (strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMessage = 'Email address is already in use by another user.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

