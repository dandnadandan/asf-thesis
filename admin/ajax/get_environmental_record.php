<?php
/**
 * Get Environmental Record Details - AJAX Handler
 * Returns detailed information about a specific environmental data record
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

// Get record ID
$recordId = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;

if ($recordId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT ed.*, ua.first_name, ua.last_name 
                           FROM environmental_data ed
                           LEFT JOIN user_accounts ua ON ed.recorded_by = ua.id
                           WHERE ed.id = ?");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching environmental record: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching record details'
    ]);
}
