<?php
/**
 * Update Typing Status AJAX Handler - Admin Side
 * Updates the typing indicator for admin users
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? '';
    $isTyping = (bool)($input['is_typing'] ?? false);
    
    if (empty($conversationId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
        exit();
    }
    
    if ($isTyping) {
        $stmt = $pdo->prepare("INSERT INTO typing_indicators (conversation_id, user_id, is_typing, last_typed_at) 
                              VALUES (?, ?, TRUE, NOW())
                              ON DUPLICATE KEY UPDATE is_typing = TRUE, last_typed_at = NOW()");
        $stmt->execute([$conversationId, $currentUser['id']]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM typing_indicators WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$conversationId, $currentUser['id']]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

