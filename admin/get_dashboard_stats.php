<?php
/**
 * Get Dashboard Statistics - AJAX Handler
 * Returns statistics based on selected time period
 */

header('Content-Type: application/json');

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role
if (!hasRole(['administrator', 'administrative staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Get time period filter
$period = $_GET['period'] ?? 'month';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Build date conditions based on period
switch ($period) {
    case 'today':
        $date_condition = "DATE(created_at) = CURDATE()";
        $payment_date_condition = "DATE(payment_date) = CURDATE()";
        $update_date_condition = "DATE(updated_at) = CURDATE()";
        $period_label = "Today";
        break;
    case 'month':
        $date_condition = "MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        $payment_date_condition = "MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
        $update_date_condition = "MONTH(updated_at) = MONTH(CURRENT_DATE()) AND YEAR(updated_at) = YEAR(CURRENT_DATE())";
        $period_label = "This Month";
        break;
    case 'year':
        $date_condition = "YEAR(created_at) = YEAR(CURRENT_DATE())";
        $payment_date_condition = "YEAR(payment_date) = YEAR(CURRENT_DATE())";
        $update_date_condition = "YEAR(updated_at) = YEAR(CURRENT_DATE())";
        $period_label = "This Year";
        break;
    default:
        $date_condition = "MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        $payment_date_condition = "MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
        $update_date_condition = "MONTH(updated_at) = MONTH(CURRENT_DATE()) AND YEAR(updated_at) = YEAR(CURRENT_DATE())";
        $period_label = "This Month";
}

try {
    $stats = [];
    
    // Users in period
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_accounts WHERE user_role = 'client' AND $date_condition");
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Services in period
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tax_filing_services WHERE $date_condition");
    $stats['services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Revenue in period
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM tax_filing_payments WHERE status = 'paid' AND $payment_date_condition");
    $stats['revenue'] = number_format($stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);
    
    // Pending services (always current, not period-based)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tax_filing_services WHERE service_status IN ('pending', 'documents_pending', 'under_review')");
    $stats['pending_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active users (last 30 days, always)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_accounts WHERE user_role = 'client' AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Completed services in period
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tax_filing_services WHERE service_status = 'completed' AND $update_date_condition");
    $stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contact inquiries stats (if table exists)
    $stats['total_inquiries'] = 0;
    $stats['new_inquiries'] = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_inquiries WHERE $date_condition");
        $stats['total_inquiries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_inquiries WHERE status = 'new' AND $date_condition");
        $stats['new_inquiries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        // Table doesn't exist yet, ignore
    }
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'period_label' => $period_label,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving statistics: ' . $e->getMessage()
    ]);
}
?>

