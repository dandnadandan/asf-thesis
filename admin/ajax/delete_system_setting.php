<?php
/**
 * Delete System Setting Endpoint
 * Deletes a system setting
 */

header('Content-Type: application/json');
require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Check if user has permission
if (!canManageSystemSettings()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$settingId = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;

if ($settingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid setting ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if setting exists
    $checkStmt = $pdo->prepare("SELECT id FROM system_settings WHERE id = ?");
    $checkStmt->execute([$settingId]);
    if ($checkStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        exit();
    }
    
    // Delete setting
    $deleteStmt = $pdo->prepare("DELETE FROM system_settings WHERE id = ?");
    $deleteStmt->execute([$settingId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting setting: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error deleting setting: ' . $e->getMessage()
    ]);
}
?>
