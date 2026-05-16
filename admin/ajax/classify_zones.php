<?php
/**
 * Classify Risk Zones into ASF Zoning Categories
 * Converts risk zones into official ASF zoning categories:
 * - Infected Zone (Red)
 * - Buffer Zone (Pink)
 * - Surveillance Zone (Yellow)
 * - Protected Zone (Light Green)
 * - Free Zone (Dark Green)
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$dateFrom = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? trim($_POST['date_from']) : null;
$dateTo = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? trim($_POST['date_to']) : null;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get risk zones within date range
    $conditions = [];
    $params = [];
    
    if ($dateFrom) {
        $conditions[] = "identified_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $conditions[] = "identified_date <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    $sql = "SELECT * FROM risk_zones {$whereClause} ORDER BY risk_score DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $riskZones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Classify zones based on risk level and outbreak data
    $zones = [
        'infected' => [],
        'buffer' => [],
        'surveillance' => [],
        'protected' => [],
        'free' => []
    ];
    
    foreach ($riskZones as $zone) {
        $zoneType = classifyZone($zone, $pdo);
        $zones[$zoneType][] = [
            'id' => $zone['id'],
            'zone_code' => $zone['zone_code'],
            'zone_name' => $zone['zone_name'],
            'province' => $zone['province'],
            'city' => $zone['city'],
            'center_latitude' => floatval($zone['center_latitude']),
            'center_longitude' => floatval($zone['center_longitude']),
            'radius_km' => floatval($zone['radius_km']),
            'risk_level' => $zone['risk_level'],
            'risk_score' => floatval($zone['risk_score']),
            'nearby_outbreaks_count' => intval($zone['nearby_outbreaks_count']),
            'last_outbreak_date' => $zone['last_outbreak_date'],
            'identified_date' => $zone['identified_date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'zones' => $zones,
        'total_zones' => count($riskZones),
        'date_range' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error classifying zones: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to classify zones: ' . $e->getMessage()
    ]);
}

/**
 * Classify a risk zone into ASF zoning category
 */
function classifyZone($zone, $pdo) {
    $riskLevel = $zone['risk_level'];
    $riskScore = floatval($zone['risk_score']);
    $outbreakCount = intval($zone['nearby_outbreaks_count']);
    $lastOutbreakDate = $zone['last_outbreak_date'];
    
    // Check for recent outbreaks within the zone
    $recentOutbreaks = 0;
    if ($lastOutbreakDate) {
        $daysSince = (time() - strtotime($lastOutbreakDate)) / 86400;
        if ($daysSince <= 30) {
            $recentOutbreaks = $outbreakCount;
        }
    }
    
    // Classification logic based on ASF zoning standards
    // Infected Zone: Critical risk with recent outbreaks
    if ($riskLevel === 'critical' && $recentOutbreaks > 0) {
        return 'infected';
    }
    
    // Buffer Zone: High risk adjacent to infected zones or high outbreak count
    if ($riskLevel === 'high' || ($riskLevel === 'critical' && $recentOutbreaks == 0)) {
        return 'buffer';
    }
    
    // Surveillance Zone: Medium risk with some outbreak history
    if ($riskLevel === 'medium' || ($riskScore >= 40 && $outbreakCount > 0)) {
        return 'surveillance';
    }
    
    // Protected Zone: Low-medium risk, no recent outbreaks
    if ($riskLevel === 'low' && $riskScore >= 20 && $outbreakCount == 0) {
        return 'protected';
    }
    
    // Free Zone: Low risk, no outbreaks
    return 'free';
}
