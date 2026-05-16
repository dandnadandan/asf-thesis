<?php
/**
 * Get Recent Reports Endpoint
 * Returns list of recently generated reports
 */

header('Content-Type: application/json');
require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if reports_history table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'reports_history'");
    if ($checkTable->rowCount() == 0) {
        echo json_encode(['success' => true, 'reports' => []]);
        exit();
    }
    
    // Get current user
    $currentUser = getCurrentUser();
    
    // Get recent reports (last 50, ordered by most recent)
    $sql = "SELECT rh.*, CONCAT(ua.first_name, ' ', ua.last_name) as generated_by_name
            FROM reports_history rh
            LEFT JOIN user_accounts ua ON rh.generated_by = ua.id
            ORDER BY rh.generated_at DESC
            LIMIT 50";
    
    $stmt = $pdo->query($sql);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format reports for response
    $formattedReports = array_map(function($report) {
        return [
            'id' => $report['id'],
            'report_type' => $report['report_type'],
            'format' => $report['format'],
            'date_from' => $report['date_from'],
            'date_to' => $report['date_to'],
            'generated_by' => $report['generated_by_name'] ?? 'Unknown',
            'generated_at' => $report['generated_at'],
            'download_count' => $report['download_count'] ?? 0
        ];
    }, $reports);
    
    echo json_encode(['success' => true, 'reports' => $formattedReports]);
    
} catch (Exception $e) {
    error_log("Error fetching recent reports: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error fetching reports']);
}
?>
