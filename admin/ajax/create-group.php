<?php
/**
 * Create Group AJAX Handler - Admin
 * Creates a new group conversation
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $groupName = trim($input['group_name'] ?? '');
    $groupDescription = trim($input['group_description'] ?? '');
    $memberIds = $input['member_ids'] ?? [];
    
    if (empty($groupName)) {
        echo json_encode(['success' => false, 'error' => 'Group name is required']);
        exit();
    }
    
    if (empty($memberIds) || !is_array($memberIds)) {
        echo json_encode(['success' => false, 'error' => 'At least one member is required']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Create group
    $stmt = $pdo->prepare("INSERT INTO group_conversations (group_name, group_description, created_by) 
                          VALUES (?, ?, ?)");
    $stmt->execute([$groupName, $groupDescription, $currentUser['id']]);
    $groupId = $pdo->lastInsertId();
    
    // Add creator as admin
    $stmt = $pdo->prepare("INSERT INTO group_participants (group_id, user_id, is_admin) VALUES (?, ?, TRUE)");
    $stmt->execute([$groupId, $currentUser['id']]);
    
    // Add selected members
    $stmt = $pdo->prepare("INSERT INTO group_participants (group_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $memberId) {
        $memberId = (int)$memberId;
        if ($memberId > 0 && $memberId != $currentUser['id']) {
            $stmt->execute([$groupId, $memberId]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'group_id' => $groupId
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

