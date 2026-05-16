<?php
/**
 * Generate Summary Report - AJAX Handler
 * Generates a narrative summary of predictive model data
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

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get filter parameters (same as main page)
    $cityFilter = isset($_GET['city']) && $_GET['city'] !== '' ? trim($_GET['city']) : '';
    $provinceFilter = isset($_GET['province']) && $_GET['province'] !== '' ? trim($_GET['province']) : 'CALABARZON';
    $riskLevelFilter = isset($_GET['risk_level']) && $_GET['risk_level'] !== '' ? trim($_GET['risk_level']) : '';
    $modelNameFilter = isset($_GET['model_name']) && $_GET['model_name'] !== '' ? trim($_GET['model_name']) : '';
    $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';
    
    // Build conditions for filtered statistics
    $conditions = [];
    $params = [];
    
    if (!empty($cityFilter)) {
        $conditions[] = "location_city LIKE ?";
        $params[] = "%{$cityFilter}%";
    }
    
    if (!empty($provinceFilter)) {
        $conditions[] = "location_province = ?";
        $params[] = $provinceFilter;
    }
    
    if (!empty($riskLevelFilter)) {
        $conditions[] = "predicted_risk_level = ?";
        $params[] = $riskLevelFilter;
    }
    
    if (!empty($modelNameFilter)) {
        $conditions[] = "model_name LIKE ?";
        $params[] = "%{$modelNameFilter}%";
    }
    
    if (!empty($dateFrom)) {
        $conditions[] = "DATE(prediction_date) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $conditions[] = "DATE(prediction_date) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get filtered statistics
    $stats = [
        'total_predictions' => 0,
        'critical_predictions' => 0,
        'high_risk_predictions' => 0,
        'avg_risk_score' => 0,
        'avg_confidence' => 0,
        'unique_models' => 0
    ];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM predictive_models {$whereClause}");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $stats['total_predictions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as critical FROM predictive_models {$whereClause} AND predicted_risk_level = 'critical'");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $stats['critical_predictions'] = $stmt->fetch()['critical'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as high FROM predictive_models {$whereClause} AND predicted_risk_level = 'high'");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $stats['high_risk_predictions'] = $stmt->fetch()['high'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT AVG(predicted_risk_score) as avg FROM predictive_models {$whereClause} AND predicted_risk_score IS NOT NULL");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $result = $stmt->fetch();
    $stats['avg_risk_score'] = round($result['avg'] ?? 0, 2);
    
    $stmt = $pdo->prepare("SELECT AVG(confidence_level) as avg FROM predictive_models {$whereClause} AND confidence_level IS NOT NULL");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $result = $stmt->fetch();
    $stats['avg_confidence'] = round($result['avg'] ?? 0, 2);
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT model_name) as unique_models FROM predictive_models {$whereClause}");
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $stats['unique_models'] = $stmt->fetch()['unique_models'] ?? 0;
    
    // Get chart data for summary
    $chartData = [
        'risk_level_breakdown' => [],
        'risk_score_trends' => [
            'labels' => [],
            'avg_scores' => []
        ]
    ];
    
    // Risk level breakdown
    $levelSql = "SELECT predicted_risk_level, COUNT(*) as count FROM predictive_models {$whereClause} GROUP BY predicted_risk_level";
    $stmt = $pdo->prepare($levelSql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($levelSql);
    }
    
    $levelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($levelResults as $row) {
        $chartData['risk_level_breakdown'][$row['predicted_risk_level']] = intval($row['count']);
    }
    
    // Risk score trends
    $trendConditions = $conditions;
    $trendParams = $params;
    
    // Add predicted_risk_score IS NOT NULL condition
    $trendConditions[] = "predicted_risk_score IS NOT NULL";
    
    $trendWhereClause = !empty($trendConditions) ? 'WHERE ' . implode(' AND ', $trendConditions) : '';
    
    $trendSql = "SELECT DATE_FORMAT(prediction_date, '%Y-%m') as month, AVG(predicted_risk_score) as avg_score
                 FROM predictive_models 
                 {$trendWhereClause}
                 GROUP BY DATE_FORMAT(prediction_date, '%Y-%m')
                 ORDER BY month ASC
                 LIMIT 24";
    
    $stmt = $pdo->prepare($trendSql);
    if (!empty($trendParams)) {
        $stmt->execute($trendParams);
    } else {
        $stmt = $pdo->query($trendSql);
    }
    
    $trendResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trendResults as $row) {
        $chartData['risk_score_trends']['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
        $chartData['risk_score_trends']['avg_scores'][] = round($row['avg_score'], 2);
    }
    
    // Generate Summary
    $summary = [];
    
    $criticalPercent = $stats['total_predictions'] > 0 ? round(($stats['critical_predictions'] / $stats['total_predictions']) * 100, 1) : 0;
    $highPercent = $stats['total_predictions'] > 0 ? round(($stats['high_risk_predictions'] / $stats['total_predictions']) * 100, 1) : 0;
    
    if ($stats['total_predictions'] > 0) {
        $summary[] = "The predictive models have generated " . number_format($stats['total_predictions']) . " risk predictions across " . ($cityFilter ? htmlspecialchars($cityFilter) : ($provinceFilter ? htmlspecialchars($provinceFilter) : "CALABARZON")) . ", with an average risk score of " . number_format($stats['avg_risk_score'], 1) . " and an average confidence level of " . number_format($stats['avg_confidence'], 1) . "%.";
        
        if ($criticalPercent > 0 || $highPercent > 0) {
            $summary[] = "Analysis reveals that " . number_format($criticalPercent, 1) . "% of predictions indicate critical risk levels, while " . number_format($highPercent, 1) . "% are classified as high-risk zones, requiring immediate attention and enhanced surveillance measures.";
        }
        
        if (!empty($chartData['risk_level_breakdown'])) {
            $total = array_sum($chartData['risk_level_breakdown']);
            $low = isset($chartData['risk_level_breakdown']['low']) ? $chartData['risk_level_breakdown']['low'] : 0;
            $medium = isset($chartData['risk_level_breakdown']['medium']) ? $chartData['risk_level_breakdown']['medium'] : 0;
            
            if ($low > 0 || $medium > 0) {
                $summary[] = "The distribution shows " . number_format($low) . " low-risk and " . number_format($medium) . " medium-risk predictions, indicating varying threat levels across different areas that warrant targeted intervention strategies.";
            }
        }
        
        if ($stats['unique_models'] > 0) {
            $summary[] = "With " . number_format($stats['unique_models']) . " distinct predictive models in use, the system leverages multiple analytical approaches to enhance prediction accuracy and provide comprehensive risk assessments for informed decision-making.";
        }
        
        if (!empty($chartData['risk_score_trends']['avg_scores'])) {
            $trendCount = count($chartData['risk_score_trends']['avg_scores']);
            if ($trendCount > 1) {
                $firstScore = $chartData['risk_score_trends']['avg_scores'][0];
                $lastScore = $chartData['risk_score_trends']['avg_scores'][$trendCount - 1];
                $trendDirection = $lastScore > $firstScore ? 'increasing' : ($lastScore < $firstScore ? 'decreasing' : 'stable');
                $summary[] = "Trend analysis over " . $trendCount . " time periods shows " . $trendDirection . " average risk scores, with scores ranging from " . number_format(min($chartData['risk_score_trends']['avg_scores']), 1) . " to " . number_format(max($chartData['risk_score_trends']['avg_scores']), 1) . ", suggesting " . ($trendDirection === 'increasing' ? 'escalating' : ($trendDirection === 'decreasing' ? 'improving' : 'consistent')) . " conditions that require " . ($trendDirection === 'increasing' ? 'urgent' : 'continued') . " monitoring and response protocols.";
            }
        }
    } else {
        $summary[] = "No predictive model data is currently available for the selected filters. The system is ready to process risk predictions once model results are generated and uploaded into the database.";
    }
    
    $fullSummary = implode(' ', $summary);
    
    echo json_encode([
        'success' => true,
        'summary' => $fullSummary
    ]);
    
} catch (Exception $e) {
    error_log("Error generating AI summary: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate summary'
    ]);
}
