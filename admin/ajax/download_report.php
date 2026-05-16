<?php
/**
 * Download Report Endpoint
 * Handles downloading of previously generated reports
 * Note: This is a placeholder - in a real implementation, reports would be stored
 * and this would retrieve them. For now, it regenerates the report.
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    die('Not authenticated');
}

$currentUser = getCurrentUser();

// Check if user has permission to download reports
$canGenerateReports = in_array($currentUser['role'], ['administrator', 'supervisor', 'analyst']);
if (!$canGenerateReports) {
    http_response_code(403);
    die('Unauthorized');
}

// Get report ID
$reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reportId <= 0) {
    http_response_code(400);
    die('Invalid report ID');
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if reports_history table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'reports_history'");
    if ($checkTable->rowCount() == 0) {
        http_response_code(404);
        die('Report not found');
    }
    
    // Get report details
    $sql = "SELECT * FROM reports_history WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        die('Report not found');
    }
    
    // Increment download count
    $updateSql = "UPDATE reports_history SET download_count = download_count + 1 WHERE id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([$reportId]);
    
    // Regenerate report (since we're not storing files)
    // Include the generate_report.php logic here
    $reportType = $report['report_type'];
    $format = $report['format'];
    $dateFrom = $report['date_from'];
    $dateTo = $report['date_to'];
    
    // Redirect to generate_report.php with same parameters
    $url = "generate_report.php?type=" . urlencode($reportType) . "&format=" . urlencode($format);
    if ($dateFrom) $url .= "&date_from=" . urlencode($dateFrom);
    if ($dateTo) $url .= "&date_to=" . urlencode($dateTo);
    
    header("Location: $url");
    exit();
    
} catch (Exception $e) {
    error_log("Error downloading report: " . $e->getMessage());
    http_response_code(500);
    die('Error downloading report');
}
?>
