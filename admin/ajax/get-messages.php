<?php
/**
 * Get Messages AJAX Handler - Admin Side
 * Retrieves messages for a conversation between admin and any user
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = getCurrentUser();

// Validate admin roles
if (!in_array($currentUser['role'], ['administrator', 'administrative staff', 'owner'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $conversationId = $_GET['conversation_id'] ?? '';
    $lastMessageId = (int)($_GET['last_message_id'] ?? 0);
    
    if (empty($conversationId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
        exit();
    }
    
    // Get messages
    $sql = "SELECT m.*, 
                   ms.status,
                   ua.first_name, ua.last_name
            FROM messages m
            LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
            LEFT JOIN user_accounts ua ON m.sender_id = ua.id
            WHERE m.conversation_id = ? 
              AND m.id > ?
              AND m.is_deleted = FALSE
            ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser['id'], $conversationId, $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments for each message
    foreach ($messages as &$message) {
        $stmt = $pdo->prepare("SELECT * FROM message_attachments WHERE message_id = ?");
        $stmt->execute([$message['id']]);
        $message['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark message as read if it's from the other person
        if ($message['sender_id'] != $currentUser['id']) {
            $stmt = $pdo->prepare("UPDATE message_status 
                                  SET status = 'read', status_timestamp = NOW() 
                                  WHERE message_id = ? AND user_id = ?");
            $stmt->execute([$message['id'], $currentUser['id']]);
        }
    }
    
    // Update last read time for conversation
    $stmt = $pdo->prepare("UPDATE conversation_participants 
                          SET last_read_at = NOW() 
                          WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $currentUser['id']]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

