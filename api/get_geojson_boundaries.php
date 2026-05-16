<?php
/**
 * API endpoint to get GeoJSON boundaries for CALABARZON cities
 * Returns simplified GeoJSON boundaries for choropleth mapping
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get date range parameters
    $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : null;
    
    // Get aggregated zone data by city/province (same logic as get_map_data.php)
    $zoneConditions = [];
    $zoneParams = [];
    
    if ($dateFrom) {
        $zoneConditions[] = "identified_date >= ?";
        $zoneParams[] = $dateFrom;
    }
    
    if ($dateTo) {
        $zoneConditions[] = "identified_date <= ?";
        $zoneParams[] = $dateTo;
    }
    
    $zoneWhere = !empty($zoneConditions) ? 'WHERE ' . implode(' AND ', $zoneConditions) : '';
    
    // Get individual zones for classification
    $zoneSqlDetail = "SELECT * FROM risk_zones {$zoneWhere} ORDER BY risk_score DESC";
    $stmtDetail = $pdo->prepare($zoneSqlDetail);
    $stmtDetail->execute($zoneParams);
    $riskZones = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
    
    // Aggregate zones by city/municipality
    $cityData = [];
    $zonePriority = ['infected' => 5, 'buffer' => 4, 'surveillance' => 3, 'protected' => 2, 'free' => 1];
    
    foreach ($riskZones as $zone) {
        $key = $zone['province'] . '|' . $zone['city'];
        
        // Get zone type from factors_contributing JSON
        $zoneType = 'free';
        if ($zone['factors_contributing']) {
            $factors = is_string($zone['factors_contributing']) 
                ? json_decode($zone['factors_contributing'], true) 
                : $zone['factors_contributing'];
            $zoneType = $factors['zone_type'] ?? 'free';
        }
        
        if (!isset($cityData[$key])) {
            $cityData[$key] = [
                'province' => $zone['province'],
                'city' => $zone['city'],
                'latitude' => floatval($zone['center_latitude']),
                'longitude' => floatval($zone['center_longitude']),
                'zones' => [],
                'risk_scores' => [],
                'total_zones' => 0,
                'total_outbreaks' => 0,
                'max_score' => 0
            ];
        }
        
        $cityData[$key]['zones'][] = [
            'type' => $zoneType,
            'score' => floatval($zone['risk_score'] ?? 0)
        ];
        $cityData[$key]['risk_scores'][] = floatval($zone['risk_score'] ?? 0);
        $cityData[$key]['total_zones']++;
        $cityData[$key]['total_outbreaks'] += intval($zone['nearby_outbreaks_count'] ?? 0);
        
        $riskScore = floatval($zone['risk_score'] ?? 0);
        if ($riskScore > $cityData[$key]['max_score']) {
            $cityData[$key]['max_score'] = $riskScore;
            $cityData[$key]['latitude'] = floatval($zone['center_latitude']);
            $cityData[$key]['longitude'] = floatval($zone['center_longitude']);
        }
    }
    
    // Calculate average/dominant zone type for each city
    foreach ($cityData as $key => &$data) {
        // Calculate average risk score
        $avgRiskScore = count($data['risk_scores']) > 0 
            ? array_sum($data['risk_scores']) / count($data['risk_scores']) 
            : 0;
        $data['avg_score'] = $avgRiskScore;
        
        // Determine dominant zone type (highest priority)
        $dominantType = 'free';
        $maxPriority = 0;
        
        // Count zone types
        $typeCounts = array_count_values(array_column($data['zones'], 'type'));
        
        // Find highest priority zone type that exists
        foreach ($zonePriority as $type => $priority) {
            if (isset($typeCounts[$type]) && $priority > $maxPriority) {
                $maxPriority = $priority;
                $dominantType = $type;
            }
        }
        
        $data['zone_type'] = $dominantType;
    }
    unset($data);
    
    
    // Create GeoJSON FeatureCollection
    $features = [];
    foreach ($cityData as $key => $data) {
        // Create a bounding box polygon around the city center
        // This is a simplified approach - in production, use actual GeoJSON boundaries
        $size = 0.1; // degrees (~10km)
        $lat = $data['latitude'];
        $lon = $data['longitude'];
        
        $coordinates = [[
            [$lon - $size, $lat - $size], // SW
            [$lon + $size, $lat - $size], // SE
            [$lon + $size, $lat + $size], // NE
            [$lon - $size, $lat + $size], // NW
            [$lon - $size, $lat - $size]  // Close polygon
        ]];
        
        $features[] = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => $coordinates
            ],
            'properties' => [
                'city' => $data['city'],
                'province' => $data['province'],
                'zone_type' => $data['zone_type'],
                'risk_score' => $data['max_score'],
                'avg_risk_score' => round($data['avg_score'], 2),
                'zone_count' => $data['total_zones'],
                'total_outbreaks' => $data['total_outbreaks']
            ]
        ];
    }
    
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];
    
    echo json_encode($geojson);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'type' => 'FeatureCollection',
        'features' => [],
        'error' => $e->getMessage()
    ]);
}
?>
