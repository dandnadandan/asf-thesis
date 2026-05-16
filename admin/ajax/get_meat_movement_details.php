<?php
/**
 * Get Meat Movement Details - AJAX Handler
 * Returns detailed information about a specific meat movement record
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

// Get movement ID
$movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

if ($movementId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid movement ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT mm.*, 
                   ua.first_name, ua.last_name
            FROM meat_movement mm 
            LEFT JOIN user_accounts ua ON mm.recorded_by = ua.id 
            WHERE mm.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$movementId]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$movement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Movement record not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'movement' => $movement
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching meat movement details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch movement details'
    ]);
}
