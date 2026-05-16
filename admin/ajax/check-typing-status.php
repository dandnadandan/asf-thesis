<?php
/**
 * Check Typing Status AJAX Handler - Admin Side
 * Checks if a user is currently typing
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
    $userId = (int)($_GET['user_id'] ?? 0);
    
    if (empty($conversationId) || $userId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }
    
    // Check if user is typing (within last 5 seconds)
    $stmt = $pdo->prepare("SELECT is_typing 
                          FROM typing_indicators 
                          WHERE conversation_id = ? 
                            AND user_id = ? 
                            AND is_typing = TRUE 
                            AND last_typed_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
    $stmt->execute([$conversationId, $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Clean up old typing indicators
    $stmt = $pdo->prepare("DELETE FROM typing_indicators 
                          WHERE last_typed_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'is_typing' => $result ? true : false
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

