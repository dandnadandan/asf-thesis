<?php
/**
 * Leave Group AJAX Handler - Admin
 * Allows an admin to leave a group
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
    
    // Check if user is member
    $stmt = $pdo->prepare("SELECT is_admin FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    $result = $stmt->fetch();
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'You are not a member of this group']);
        exit();
    }
    
    // Check if they're the last admin
    if ($result['is_admin']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_participants WHERE group_id = ? AND is_admin = TRUE");
        $stmt->execute([$groupId]);
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount <= 1) {
            // Check if there are other members
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_participants WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $memberCount = $stmt->fetchColumn();
            
            if ($memberCount > 1) {
                echo json_encode(['success' => false, 'error' => 'You are the only admin. Please promote another member or delete the group.']);
                exit();
            }
        }
    }
    
    // Remove user from group
    $stmt = $pdo->prepare("DELETE FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

