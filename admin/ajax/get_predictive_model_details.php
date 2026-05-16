<?php
/**
 * Get Predictive Model Details - AJAX Handler
 * Returns detailed information about a specific predictive model prediction
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

// Get prediction ID
$predictionId = isset($_GET['prediction_id']) ? intval($_GET['prediction_id']) : 0;

if ($predictionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid prediction ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT pm.*, 
                   ua.first_name, ua.last_name
            FROM predictive_models pm 
            LEFT JOIN user_accounts ua ON pm.created_by = ua.id 
            WHERE pm.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$predictionId]);
    $prediction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prediction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Prediction not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'prediction' => $prediction
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching prediction details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch prediction details'
    ]);
}
