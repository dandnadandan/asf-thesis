<?php
/**
 * Get Available Members for Group Creation - Admin
 * Returns list of employees and admins who can be added to groups
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = getCurrentUser();

// Validate admin role
if (!in_array($currentUser['role'], ['owner', 'administrator'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $groupId = (int)($_GET['group_id'] ?? 0);
    
    // If group_id provided, exclude current members
    if ($groupId > 0) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, user_role
                              FROM user_accounts
                              WHERE user_role IN ('senior account executive', 'junior account executive', 'owner', 'administrator')
                                AND id != ?
                                AND id NOT IN (SELECT user_id FROM group_participants WHERE group_id = ?)
                              ORDER BY 
                                CASE user_role
                                  WHEN 'owner' THEN 1
                                  WHEN 'administrator' THEN 2
                                  WHEN 'senior account executive' THEN 3
                                  WHEN 'junior account executive' THEN 4
                                END,
                                first_name, last_name");
        $stmt->execute([$currentUser['id'], $groupId]);
    } else {
        // Get all employees and admins except current user (for creating new group)
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, user_role
                              FROM user_accounts
                              WHERE user_role IN ('senior account executive', 'junior account executive', 'owner', 'administrator')
                                AND id != ?
                              ORDER BY 
                                CASE user_role
                                  WHEN 'owner' THEN 1
                                  WHEN 'administrator' THEN 2
                                  WHEN 'senior account executive' THEN 3
                                  WHEN 'junior account executive' THEN 4
                                END,
                                first_name, last_name");
        $stmt->execute([$currentUser['id']]);
    }
    
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

