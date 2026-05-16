<?php
/**
 * Check Group Typing Status AJAX Handler - Admin
 * Returns list of users currently typing in a group
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
    
    if ($groupId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
        exit();
    }
    
    // Get users typing (excluding current user, within last 5 seconds)
    $stmt = $pdo->prepare("SELECT ua.first_name, ua.last_name
                          FROM group_typing_indicators gti
                          JOIN user_accounts ua ON gti.user_id = ua.id
                          WHERE gti.group_id = ? 
                            AND gti.user_id != ?
                            AND gti.is_typing = TRUE 
                            AND gti.last_typed_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
    $stmt->execute([$groupId, $currentUser['id']]);
    $typingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up old typing indicators
    $stmt = $pdo->prepare("DELETE FROM group_typing_indicators 
                          WHERE last_typed_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'typing_users' => $typingUsers
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

