<?php
/**
 * Generate Report Endpoint
 * Generates reports in CSV, PDF, or Excel format
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

$currentUser = getCurrentUser();

// Check if user has permission to generate reports
$canGenerateReports = in_array($currentUser['role'], ['administrator', 'supervisor', 'analyst']);
if (!$canGenerateReports) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get parameters
$reportType = isset($_GET['type']) ? trim($_GET['type']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

// Validate parameters
if (empty($reportType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Report type is required']);
    exit();
}

if (!in_array($format, ['csv', 'pdf', 'excel'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid format']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Generate report based on type and format
    switch ($format) {
        case 'csv':
            generateCSVReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser);
            break;
        case 'pdf':
            generatePDFReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser);
            break;
        case 'excel':
            generateExcelReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error generating report: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error generating report: ' . $e->getMessage()]);
    exit();
}

/**
 * Generate CSV Report
 */
function generateCSVReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser) {
    // Fetch data based on report type
    $data = fetchReportData($pdo, $reportType, $dateFrom, $dateTo);
    
    // Set headers for CSV download
    $filename = generateFilename($reportType, 'csv', $dateFrom, $dateTo);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    if (!empty($data['headers'])) {
        fputcsv($output, $data['headers']);
    }
    
    // Write data rows
    if (!empty($data['rows'])) {
        foreach ($data['rows'] as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    
    // Log report generation
    logReportGeneration($pdo, $reportType, 'csv', $dateFrom, $dateTo, $currentUser['id']);
    
    exit();
}

/**
 * Generate PDF Report (simplified - returns CSV for now)
 */
function generatePDFReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser) {
    // For now, generate CSV as PDF generation requires additional libraries
    // In production, you would use libraries like TCPDF, FPDF, or DomPDF
    generateCSVReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser);
}

/**
 * Generate Excel Report (simplified - returns CSV for now)
 */
function generateExcelReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser) {
    // For now, generate CSV as Excel generation requires PhpSpreadsheet library
    // In production, you would use PhpSpreadsheet library
    generateCSVReport($pdo, $reportType, $dateFrom, $dateTo, $currentUser);
}

/**
 * Get date column name for report type
 */
function getDateColumnForType($reportType) {
    $dateColumns = [
        'outbreaks' => 'outbreak_date',
        'depopulation' => 'event_date',
        'meat_movement' => 'movement_date',
        'environmental' => 'recorded_at',
        'risk_zones' => 'created_at',
        'predictive_models' => 'prediction_date',
        'news_articles' => 'created_at',
        'summary' => 'created_at'
    ];
    return $dateColumns[$reportType] ?? 'created_at';
}

/**
 * Fetch report data based on type
 */
function fetchReportData($pdo, $reportType, $dateFrom, $dateTo) {
    $headers = [];
    $rows = [];
    
    // Build date condition based on report type
    $dateColumn = getDateColumnForType($reportType);
    $dateCondition = '';
    $params = [];
    if ($dateFrom) {
        $dateCondition .= " AND DATE($dateColumn) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $dateCondition .= " AND DATE($dateColumn) <= ?";
        $params[] = $dateTo;
    }
    
    switch ($reportType) {
        case 'outbreaks':
            $headers = ['ID', 'Outbreak Date', 'Location', 'City', 'Province', 'Latitude', 'Longitude', 'Affected Animals', 'Status', 'Created At'];
            $sql = "SELECT id, outbreak_date, location_name, city, province, latitude, longitude, total_pigs_affected, status, created_at 
                    FROM asf_outbreaks 
                    WHERE 1=1 $dateCondition 
                    ORDER BY outbreak_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['outbreak_date'],
                    $row['location_name'],
                    $row['city'],
                    $row['province'],
                    $row['latitude'],
                    $row['longitude'],
                    $row['total_pigs_affected'],
                    $row['status'],
                    $row['created_at']
                ];
            }
            break;
            
        case 'depopulation':
            $headers = ['ID', 'Event Date', 'Location', 'City', 'Province', 'Animals Depopulated', 'Method', 'Compensation Status', 'Amount', 'Created At'];
            $sql = "SELECT id, event_date, location_name, city, province, head_count, depopulation_method, compensation_status, compensation_amount, created_at 
                    FROM depopulation_events 
                    WHERE 1=1 $dateCondition 
                    ORDER BY event_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['event_date'],
                    $row['location_name'],
                    $row['city'],
                    $row['province'],
                    $row['head_count'],
                    $row['depopulation_method'],
                    $row['compensation_status'] ?? 'N/A',
                    $row['compensation_amount'] ?? 'N/A',
                    $row['created_at']
                ];
            }
            break;
            
        case 'meat_movement':
            $headers = ['ID', 'Movement Date', 'Origin', 'Destination', 'Origin City', 'Destination City', 'Meat Type', 'Quantity (kg)', 'Health Certificate', 'Status', 'Created At'];
            $sql = "SELECT id, movement_date, source_location, destination_location, source_city, destination_city, meat_type, quantity_kg, health_certificate_number, status, created_at 
                    FROM meat_movement 
                    WHERE 1=1 $dateCondition 
                    ORDER BY movement_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['movement_date'],
                    $row['source_location'],
                    $row['destination_location'],
                    $row['source_city'],
                    $row['destination_city'],
                    $row['meat_type'],
                    $row['quantity_kg'],
                    $row['health_certificate_number'] ?? 'N/A',
                    $row['status'] ?? 'N/A',
                    $row['created_at']
                ];
            }
            break;
            
        case 'environmental':
            $headers = ['ID', 'Date', 'Location', 'City', 'Province', 'Temperature (°C)', 'Humidity (%)', 'Rainfall (mm)', 'Wind Speed (km/h)', 'Created At'];
            $sql = "SELECT id, recorded_at, location_name, city, province, temperature, humidity, rainfall, wind_speed, created_at 
                    FROM environmental_data 
                    WHERE 1=1 $dateCondition 
                    ORDER BY recorded_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['recorded_at'],
                    $row['location_name'],
                    $row['city'],
                    $row['province'],
                    $row['temperature'],
                    $row['humidity'],
                    $row['rainfall'],
                    $row['wind_speed'] ?? 'N/A',
                    $row['created_at']
                ];
            }
            break;
            
        case 'risk_zones':
            $headers = ['ID', 'Zone Code', 'City', 'Province', 'Risk Level', 'Risk Score', 'Radius (km)', 'Created At'];
            $sql = "SELECT id, zone_code, city, province, risk_level, risk_score, radius_km, created_at 
                    FROM risk_zones 
                    WHERE 1=1 $dateCondition 
                    ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['zone_code'],
                    $row['city'],
                    $row['province'],
                    $row['risk_level'],
                    $row['risk_score'],
                    $row['radius_km'],
                    $row['created_at']
                ];
            }
            break;
            
        case 'predictive_models':
            $headers = ['ID', 'Model Date', 'Province', 'City', 'Predicted Risk Level', 'Predicted Risk Score', 'Probability', 'Created At'];
            $sql = "SELECT id, prediction_date, location_province, location_city, predicted_risk_level, predicted_risk_score, probability_outbreak, created_at 
                    FROM predictive_models 
                    WHERE 1=1 $dateCondition 
                    ORDER BY prediction_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['prediction_date'],
                    $row['location_province'] ?? 'N/A',
                    $row['location_city'] ?? 'N/A',
                    $row['predicted_risk_level'],
                    $row['predicted_risk_score'],
                    $row['probability_outbreak'] ?? 'N/A',
                    $row['created_at']
                ];
            }
            break;
            
        case 'news_articles':
            $headers = ['ID', 'Title', 'Category', 'Status', 'Published At', 'Author', 'Views', 'Created At'];
            $sql = "SELECT na.id, na.title, na.category, na.status, na.published_at, CONCAT(ua.first_name, ' ', ua.last_name) as author, na.views_count, na.created_at 
                    FROM news_articles na
                    LEFT JOIN user_accounts ua ON na.author_id = ua.id
                    WHERE 1=1 $dateCondition 
                    ORDER BY na.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    $row['id'],
                    $row['title'],
                    $row['category'],
                    $row['status'],
                    $row['published_at'] ?? 'N/A',
                    $row['author'] ?? 'N/A',
                    $row['views_count'],
                    $row['created_at']
                ];
            }
            break;
            
        case 'summary':
            // Summary report with counts from each table
            $headers = ['Data Type', 'Total Records', 'Date Range'];
            $summaryData = [];
            
            // Get counts for each type
            $types = ['outbreaks', 'depopulation', 'meat_movement', 'environmental', 'risk_zones', 'predictive_models', 'news_articles'];
            foreach ($types as $type) {
                $count = getCountForType($pdo, $type, $dateFrom, $dateTo);
                $summaryData[] = [
                    formatTypeName($type),
                    $count,
                    ($dateFrom && $dateTo) ? "$dateFrom to $dateTo" : 'All dates'
                ];
            }
            $rows = $summaryData;
            break;
    }
    
    return ['headers' => $headers, 'rows' => $rows];
}

/**
 * Get count for a specific type
 */
function getCountForType($pdo, $type, $dateFrom, $dateTo) {
    $dateColumn = getDateColumnForType($type);
    $dateCondition = '';
    $params = [];
    if ($dateFrom) {
        $dateCondition .= " AND DATE($dateColumn) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $dateCondition .= " AND DATE($dateColumn) <= ?";
        $params[] = $dateTo;
    }
    
    $tables = [
        'outbreaks' => 'asf_outbreaks',
        'depopulation' => 'depopulation_events',
        'meat_movement' => 'meat_movement',
        'environmental' => 'environmental_data',
        'risk_zones' => 'risk_zones',
        'predictive_models' => 'predictive_models',
        'news_articles' => 'news_articles'
    ];
    
    $table = $tables[$type] ?? null;
    if (!$table) return 0;
    
    $sql = "SELECT COUNT(*) as count FROM $table WHERE 1=1 $dateCondition";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

/**
 * Format type name for display
 */
function formatTypeName($type) {
    $names = [
        'outbreaks' => 'ASF Outbreaks',
        'depopulation' => 'Depopulation Events',
        'meat_movement' => 'Meat Movement',
        'environmental' => 'Environmental Data',
        'risk_zones' => 'Risk Zones',
        'predictive_models' => 'Predictive Models',
        'news_articles' => 'News & Announcements'
    ];
    return $names[$type] ?? $type;
}

/**
 * Generate filename
 */
function generateFilename($reportType, $format, $dateFrom, $dateTo) {
    $typeName = str_replace('_', '-', $reportType);
    $dateStr = '';
    if ($dateFrom && $dateTo) {
        $dateStr = '_' . $dateFrom . '_to_' . $dateTo;
    } elseif ($dateFrom) {
        $dateStr = '_from_' . $dateFrom;
    } elseif ($dateTo) {
        $dateStr = '_to_' . $dateTo;
    }
    return "asf_report_{$typeName}{$dateStr}." . $format;
}

/**
 * Log report generation
 */
function logReportGeneration($pdo, $reportType, $format, $dateFrom, $dateTo, $userId) {
    try {
        // Check if reports_history table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'reports_history'");
        if ($checkTable->rowCount() > 0) {
            $sql = "INSERT INTO reports_history (report_type, format, date_from, date_to, generated_by, parameters) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = json_encode([
                'type' => $reportType,
                'format' => $format,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reportType, $format, $dateFrom, $dateTo, $userId, $params]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break report generation if logging fails
        error_log("Error logging report generation: " . $e->getMessage());
    }
}
?>
