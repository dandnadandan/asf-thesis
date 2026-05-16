<?php
/**
 * AJAX endpoint to get a single tax filing request
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], '');

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $request_id = $_GET['id'] ?? '';
    
    if (empty($request_id)) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        exit();
    }
    
    $sql = "SELECT tfs.*, ua.first_name, ua.last_name, ua.email,
                   COUNT(DISTINCT tfd.id) as document_count,
                   SUM(CASE WHEN tfp.status = 'approved' THEN tfp.amount ELSE 0 END) as total_paid
            FROM tax_filing_services tfs
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            LEFT JOIN tax_filing_documents tfd ON tfs.id = tfd.service_id
            LEFT JOIN tax_filing_payments tfp ON tfs.id = tfp.service_id
            WHERE tfs.id = :id
            GROUP BY tfs.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Request not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error getting request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving request'
    ]);
}
?>
