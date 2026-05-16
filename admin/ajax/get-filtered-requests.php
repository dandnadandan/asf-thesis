<?php
/**
 * AJAX endpoint to get filtered tax filing requests
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
    
    // Get filter parameters
    $status_filter = $_GET['status'] ?? 'all';
    $type_filter = $_GET['type'] ?? 'all';
    $priority_filter = $_GET['priority'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT tfs.*, ua.first_name, ua.last_name, ua.email,
                   COUNT(DISTINCT tfd.id) as document_count,
                   SUM(CASE WHEN tfp.status = 'approved' THEN tfp.amount ELSE 0 END) as total_paid
            FROM tax_filing_services tfs
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            LEFT JOIN tax_filing_documents tfd ON tfs.id = tfd.service_id
            LEFT JOIN tax_filing_payments tfp ON tfs.id = tfp.service_id
            WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $sql .= " AND tfs.service_status = :status";
        $params['status'] = $status_filter;
    }
    
    if ($type_filter !== 'all') {
        $sql .= " AND tfs.service_type = :type";
        $params['type'] = $type_filter;
    }
    
    if ($priority_filter !== 'all') {
        $sql .= " AND tfs.payment_status = :priority";
        $params['priority'] = $priority_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (tfs.business_name LIKE :search OR tfs.notes LIKE :search OR 
                      ua.first_name LIKE :search OR ua.last_name LIKE :search OR 
                      ua.email LIKE :search OR tfs.tin LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    $sql .= " GROUP BY tfs.id ORDER BY tfs.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
    
} catch (Exception $e) {
    error_log("Error getting filtered requests: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving requests'
    ]);
}
?>
