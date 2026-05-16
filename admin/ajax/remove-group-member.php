<?php
/**
 * Remove Group Member AJAX Handler - Admin
 * Removes a member from a group (admin only)
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
    $userId = (int)($input['user_id'] ?? 0);
    
    if ($groupId == 0 || $userId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }
    
    // Verify current user is admin
    $stmt = $pdo->prepare("SELECT is_admin FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    $result = $stmt->fetch();
    if (!$result || !$result['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Only group admins can remove members']);
        exit();
    }
    
    // Can't remove yourself
    if ($userId == $currentUser['id']) {
        echo json_encode(['success' => false, 'error' => 'Cannot remove yourself. Use Leave Group instead.']);
        exit();
    }
    
    // Remove member
    $stmt = $pdo->prepare("DELETE FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $userId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

