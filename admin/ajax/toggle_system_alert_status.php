<?php
/**
 * Toggle System Alert Status Endpoint
 * Updates the status of a system alert
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$alertId = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($alertId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid alert ID']);
    exit();
}

if (!in_array($status, ['active', 'acknowledged', 'resolved', 'dismissed'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if alert exists
    $checkStmt = $pdo->prepare("SELECT id FROM system_alerts WHERE id = ?");
    $checkStmt->execute([$alertId]);
    if ($checkStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Alert not found']);
        exit();
    }
    
    // Update status
    $updateStmt = $pdo->prepare("UPDATE system_alerts SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$status, $alertId]);
    
    $statusLabels = [
        'active' => 'activated',
        'acknowledged' => 'acknowledged',
        'resolved' => 'resolved',
        'dismissed' => 'dismissed'
    ];
    
    $statusLabel = $statusLabels[$status] ?? $status;
    echo json_encode([
        'success' => true,
        'message' => "Alert {$statusLabel} successfully"
    ]);
    
} catch (Exception $e) {
    error_log("Error updating alert status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error updating alert status: ' . $e->getMessage()
    ]);
}
?>
