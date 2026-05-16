<?php
/**
 * Update Group Typing Status AJAX Handler - Admin
 * Updates typing indicator for group conversations
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
    $isTyping = (bool)($input['is_typing'] ?? false);
    
    if ($groupId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
        exit();
    }
    
    // Verify user is member
    $stmt = $pdo->prepare("SELECT * FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are not a member of this group']);
        exit();
    }
    
    if ($isTyping) {
        $stmt = $pdo->prepare("INSERT INTO group_typing_indicators (group_id, user_id, is_typing, last_typed_at) 
                              VALUES (?, ?, TRUE, NOW())
                              ON DUPLICATE KEY UPDATE is_typing = TRUE, last_typed_at = NOW()");
        $stmt->execute([$groupId, $currentUser['id']]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM group_typing_indicators WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $currentUser['id']]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

