<?php
/**
 * Get System Alert Details Endpoint
 * Returns details of a single system alert
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
if (!canManageSystemAlerts()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$alertId = isset($_GET['alert_id']) ? intval($_GET['alert_id']) : 0;

if ($alertId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid alert ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT * FROM system_alerts WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alert) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Alert not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'alert' => $alert]);
    
} catch (Exception $e) {
    error_log("Error fetching alert details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error fetching alert details']);
}
?>
