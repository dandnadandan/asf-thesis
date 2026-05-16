<?php
/**
 * Add Group Members AJAX Handler - Admin
 * Adds new members to an existing group
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
    $memberIds = $input['member_ids'] ?? [];
    
    if ($groupId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
        exit();
    }
    
    if (empty($memberIds) || !is_array($memberIds)) {
        echo json_encode(['success' => false, 'error' => 'No members selected']);
        exit();
    }
    
    // Verify current user is admin of this group
    $stmt = $pdo->prepare("SELECT is_admin FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    $result = $stmt->fetch();
    if (!$result || !$result['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Only group admins can add members']);
        exit();
    }
    
    // Add members
    $stmt = $pdo->prepare("INSERT IGNORE INTO group_participants (group_id, user_id) VALUES (?, ?)");
    $addedCount = 0;
    
    foreach ($memberIds as $memberId) {
        $memberId = (int)$memberId;
        if ($memberId > 0) {
            $stmt->execute([$groupId, $memberId]);
            if ($stmt->rowCount() > 0) {
                $addedCount++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'added_count' => $addedCount
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

