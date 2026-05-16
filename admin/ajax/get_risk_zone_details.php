<?php
/**
 * Get Risk Zone Details - AJAX Handler
 * Returns detailed information about a specific risk zone
 */

header('Content-Type: application/json');

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get zone ID
$zoneId = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : 0;

if ($zoneId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid zone ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT rz.*, 
                   ua.first_name, ua.last_name
            FROM risk_zones rz 
            LEFT JOIN user_accounts ua ON rz.reviewed_by = ua.id 
            WHERE rz.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$zoneId]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$zone) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Risk zone not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'zone' => $zone
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching risk zone details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch risk zone details'
    ]);
}
