<?php
/**
 * Get Depopulation Event Details - AJAX Handler
 * Returns detailed information about a specific depopulation event
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

// Get event ID
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT de.*, 
                   ua_created.first_name as created_first_name, ua_created.last_name as created_last_name,
                   ua_supervised.first_name as supervised_first_name, ua_supervised.last_name as supervised_last_name,
                   ob.outbreak_code
            FROM depopulation_events de 
            LEFT JOIN user_accounts ua_created ON de.created_by = ua_created.id 
            LEFT JOIN user_accounts ua_supervised ON de.supervised_by = ua_supervised.id 
            LEFT JOIN asf_outbreaks ob ON de.outbreak_id = ob.id
            WHERE de.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'event' => $event
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching depopulation event details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch event details'
    ]);
}
