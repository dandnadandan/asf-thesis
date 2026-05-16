<?php
/**
 * Get Outbreak Details - AJAX Handler
 * Returns detailed information about a specific outbreak
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

// Get outbreak ID
$outbreakId = isset($_GET['outbreak_id']) ? intval($_GET['outbreak_id']) : 0;

if ($outbreakId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid outbreak ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT ob.*, 
                   ua_reported.first_name as reported_first_name, ua_reported.last_name as reported_last_name,
                   ua_confirmed.first_name as confirmed_first_name, ua_confirmed.last_name as confirmed_last_name
            FROM asf_outbreaks ob 
            LEFT JOIN user_accounts ua_reported ON ob.reported_by = ua_reported.id 
            LEFT JOIN user_accounts ua_confirmed ON ob.confirmed_by = ua_confirmed.id 
            WHERE ob.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outbreakId]);
    $outbreak = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$outbreak) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Outbreak not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'outbreak' => $outbreak
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching outbreak details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch outbreak details'
    ]);
}
