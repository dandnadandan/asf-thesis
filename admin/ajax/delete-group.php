<?php
/**
 * Delete Group AJAX Handler - Admin
 * Permanently deletes a group and all its messages (admin only)
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
    $groupId = (int)($input['group_id'] ?? 0);
    
    if ($groupId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
        exit();
    }
    
    // Verify user is admin of this group
    $stmt = $pdo->prepare("SELECT is_admin FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    $result = $stmt->fetch();
    
    if (!$result || !$result['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Only group admins can delete the group']);
        exit();
    }
    
    // Soft delete the group (set is_active = FALSE)
    // This preserves messages for audit/history but hides the group
    $stmt = $pdo->prepare("UPDATE group_conversations SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$groupId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

