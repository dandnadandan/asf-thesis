<?php
/**
 * Get Group Messages AJAX Handler - Admin
 * Retrieves messages for a group conversation
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
    $lastMessageId = (int)($_GET['last_message_id'] ?? 0);
    
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
    
    // Get messages
    $sql = "SELECT gm.*, 
                   gms.status,
                   ua.first_name, ua.last_name
            FROM group_messages gm
            LEFT JOIN group_message_status gms ON gm.id = gms.message_id AND gms.user_id = ?
            LEFT JOIN user_accounts ua ON gm.sender_id = ua.id
            WHERE gm.group_id = ? 
              AND gm.id > ?
              AND gm.is_deleted = FALSE
            ORDER BY gm.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser['id'], $groupId, $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments for each message
    foreach ($messages as &$message) {
        $stmt = $pdo->prepare("SELECT * FROM group_message_attachments WHERE message_id = ?");
        $stmt->execute([$message['id']]);
        $message['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark message as read if from someone else
        if ($message['sender_id'] != $currentUser['id']) {
            $stmt = $pdo->prepare("UPDATE group_message_status 
                                  SET status = 'read', status_timestamp = NOW() 
                                  WHERE message_id = ? AND user_id = ?");
            $stmt->execute([$message['id'], $currentUser['id']]);
        }
    }
    
    // Update last read time
    $stmt = $pdo->prepare("UPDATE group_participants 
                          SET last_read_at = NOW() 
                          WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

