<?php
/**
 * Get Upload Details - AJAX Handler
 * Returns detailed information about a specific upload
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

// Get upload ID
$uploadId = isset($_GET['upload_id']) ? intval($_GET['upload_id']) : 0;

if ($uploadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM data_uploads WHERE id = ?");
    $stmt->execute([$uploadId]);
    $upload = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$upload) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Upload not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'upload' => $upload
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching upload details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching upload details'
    ]);
}
