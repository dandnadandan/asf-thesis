<?php
/**
 * Save System Alert Endpoint
 * Handles creation and updating of system alerts
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

$currentUser = getCurrentUser();
$alertId = isset($_POST['alert_id']) && $_POST['alert_id'] !== '' ? intval($_POST['alert_id']) : 0;
$isEdit = $alertId > 0;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Validate required fields
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $alertType = isset($_POST['alert_type']) ? trim($_POST['alert_type']) : 'system';
    $severity = isset($_POST['severity']) ? trim($_POST['severity']) : 'medium';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit();
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is required']);
        exit();
    }
    
    // Get other fields
    $locationProvince = isset($_POST['location_province']) && $_POST['location_province'] !== '' ? trim($_POST['location_province']) : null;
    $locationCity = isset($_POST['location_city']) && $_POST['location_city'] !== '' ? trim($_POST['location_city']) : null;
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    $relatedResourceType = isset($_POST['related_resource_type']) && $_POST['related_resource_type'] !== '' ? trim($_POST['related_resource_type']) : null;
    $relatedResourceId = isset($_POST['related_resource_id']) && $_POST['related_resource_id'] !== '' ? intval($_POST['related_resource_id']) : null;
    
    // Generate alert code if new alert
    if (!$isEdit) {
        $alertCode = 'ALERT' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        $counter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM system_alerts WHERE alert_code = ?");
            $checkStmt->execute([$alertCode]);
            if ($checkStmt->rowCount() == 0) {
                break;
            }
            $alertCode = 'ALERT' . date('Ymd') . str_pad(rand(1, 9999) + $counter, 4, '0', STR_PAD_LEFT);
            $counter++;
        }
    }
    
    if ($isEdit) {
        // Update existing alert
        $sql = "UPDATE system_alerts SET
                title = ?, message = ?, alert_type = ?, severity = ?, 
                status = ?, location_province = ?, location_city = ?,
                latitude = ?, longitude = ?, related_resource_type = ?,
                related_resource_id = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $message, $alertType, $severity,
            $status, $locationProvince, $locationCity,
            $latitude, $longitude, $relatedResourceType,
            $relatedResourceId, $alertId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Alert updated successfully',
            'alert_id' => $alertId
        ]);
    } else {
        // Insert new alert
        $sql = "INSERT INTO system_alerts 
                (alert_code, title, message, alert_type, severity, status,
                 location_province, location_city, latitude, longitude,
                 related_resource_type, related_resource_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $alertCode, $title, $message, $alertType, $severity, $status,
            $locationProvince, $locationCity, $latitude, $longitude,
            $relatedResourceType, $relatedResourceId, $currentUser['id']
        ]);
        
        $newAlertId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Alert created successfully',
            'alert_id' => $newAlertId
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error saving alert: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error saving alert: ' . $e->getMessage()
    ]);
}
?>
