<?php
/**
 * AJAX endpoint to get tax filing request statistics
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
    
    $sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN service_status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN service_status = 'under_review' THEN 1 ELSE 0 END) as in_progress_requests,
                SUM(CASE WHEN service_status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
                SUM(CASE WHEN service_status = 'rejected' THEN 1 ELSE 0 END) as cancelled_requests,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as urgent_requests,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as high_priority_requests
            FROM tax_filing_services";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'statistics' => $statistics
    ]);
    
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving statistics'
    ]);
}
?>
