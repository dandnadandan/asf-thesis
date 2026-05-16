<?php
/**
 * AJAX endpoint to check for updates since last check
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], '');

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $since = $_GET['since'] ?? '0';
    $since_timestamp = date('Y-m-d H:i:s', $since);
    
    // Check if there are any updates since the given timestamp
    $sql = "SELECT COUNT(*) as update_count, MAX(updated_at) as latest_update
            FROM tax_filing_services 
            WHERE updated_at > :since";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['since' => $since_timestamp]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $hasUpdates = $result['update_count'] > 0;
    $timestamp = $result['latest_update'] ? strtotime($result['latest_update']) : time();
    
    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'updateCount' => $result['update_count'],
        'timestamp' => $timestamp
    ]);
    
} catch (Exception $e) {
    error_log("Error checking updates: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking for updates'
    ]);
}
?>
