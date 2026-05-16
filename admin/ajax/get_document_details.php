<?php
/**
 * Get Outbreak Document Details - AJAX Handler
 * Returns detailed information about a specific outbreak document
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

// Get document ID
$documentId = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;

if ($documentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT od.*, 
                   ua.first_name, ua.last_name,
                   ob.outbreak_code, ob.location_name as outbreak_location, ob.city as outbreak_city
            FROM outbreak_documents od 
            LEFT JOIN user_accounts ua ON od.uploaded_by = ua.id 
            LEFT JOIN asf_outbreaks ob ON od.outbreak_id = ob.id
            WHERE od.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'document' => $document
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching document details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch document details'
    ]);
}
