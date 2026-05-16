<?php
/**
 * Get System Setting Details Endpoint
 * Returns details of a single system setting
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

$settingId = isset($_GET['setting_id']) ? intval($_GET['setting_id']) : 0;

if ($settingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid setting ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT * FROM system_settings WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$settingId]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$setting) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'setting' => $setting]);
    
} catch (Exception $e) {
    error_log("Error fetching setting details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error fetching setting details']);
}
?>
