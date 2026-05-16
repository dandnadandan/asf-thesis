<?php
/**
 * Calculate Risk Zones - AJAX Handler
 * Analyzes outbreaks, depopulation events, environmental data, and meat movement
 * to automatically calculate and generate risk zones by city/municipality
 * Uses ASF zoning classification: infected, buffer, surveillance, protected, free
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

// Get parameters
$clusterRadius = isset($_POST['clusterRadius']) ? floatval($_POST['clusterRadius']) : 10.0;
$minOutbreaksForZone = isset($_POST['minOutbreaksForZone']) ? intval($_POST['minOutbreaksForZone']) : 2;
$lookbackDays = isset($_POST['lookbackDays']) ? intval($_POST['lookbackDays']) : 90;
$replaceExisting = isset($_POST['replaceExisting']) ? $_POST['replaceExisting'] : 'append';
$includeEnvironmental = isset($_POST['includeEnvironmental']) && $_POST['includeEnvironmental'] === 'on';
$includeMovement = isset($_POST['includeMovement']) && $_POST['includeMovement'] === 'on';

$startTime = microtime(true);
$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get cutoff date
    $cutoffDate = date('Y-m-d', strtotime("-{$lookbackDays} days"));
    
    // Step 1: Get ALL cities in CALABARZON from all data sources (union of all cities)
    $allCitiesSql = "SELECT DISTINCT city, province 
                     FROM (
                       SELECT DISTINCT city, province FROM asf_outbreaks WHERE province = 'CALABARZON' AND city IS NOT NULL AND city != ''
                       UNION
                       SELECT DISTINCT city, province FROM depopulation_events WHERE province = 'CALABARZON' AND city IS NOT NULL AND city != ''
                       UNION
                       SELECT DISTINCT city, province FROM environmental_data WHERE province = 'CALABARZON' AND city IS NOT NULL AND city != ''
                       UNION
                       SELECT DISTINCT source_city as city, source_province as province FROM meat_movement WHERE source_province = 'CALABARZON' AND source_city IS NOT NULL AND source_city != ''
                       UNION
                       SELECT DISTINCT destination_city as city, destination_province as province FROM meat_movement WHERE destination_province = 'CALABARZON' AND destination_city IS NOT NULL AND destination_city != ''
                     ) as all_cities
                     ORDER BY city";
    $stmt = $pdo->query($allCitiesSql);
    $allCities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allCities)) {
        throw new Exception('No cities found in CALABARZON. Please upload data for at least one city.');
    }
    
    // Initialize city data structure for ALL cities
    $cityOutbreaks = [];
    foreach ($allCities as $cityRow) {
        $city = $cityRow['city'];
        $province = $cityRow['province'] ?? 'CALABARZON';
        $key = $city . '|' . $province;
        
        $cityOutbreaks[$key] = [
            'province' => $province,
            'city' => $city,
            'outbreaks' => [],
            'center_lat' => 0,
            'center_lon' => 0,
            'total_outbreaks' => 0,
            'total_depopulation' => 0,
            'last_outbreak_date' => null
        ];
    }
    
    // Step 2: Get outbreaks within lookback period and group by city
    $outbreaksSql = "SELECT id, location_name, latitude, longitude, province, city, barangay,
                            outbreak_date, total_pigs_affected, total_pigs_mortality, 
                            total_pigs_depopulated, severity_level, status
                     FROM asf_outbreaks 
                     WHERE outbreak_date >= ? 
                     AND province = 'CALABARZON'
                     AND latitude IS NOT NULL 
                     AND longitude IS NOT NULL
                     ORDER BY city, outbreak_date DESC";
    $stmt = $pdo->prepare($outbreaksSql);
    $stmt->execute([$cutoffDate]);
    $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group outbreaks by city
    foreach ($outbreaks as $outbreak) {
        $city = $outbreak['city'] ?? '';
        $province = $outbreak['province'] ?? 'CALABARZON';
        $key = $city . '|' . $province;
        
        if (isset($cityOutbreaks[$key])) {
            $cityOutbreaks[$key]['outbreaks'][] = $outbreak;
        }
    }
    
    // Step 3: Calculate city-level statistics
    foreach ($cityOutbreaks as $key => &$cityData) {
        $cityData['total_outbreaks'] = count($cityData['outbreaks']);
        
        // Calculate center point (average of all outbreak locations)
        $sumLat = 0;
        $sumLon = 0;
        foreach ($cityData['outbreaks'] as $outbreak) {
            $sumLat += floatval($outbreak['latitude']);
            $sumLon += floatval($outbreak['longitude']);
        }
        $cityData['center_lat'] = $sumLat / $cityData['total_outbreaks'];
        $cityData['center_lon'] = $sumLon / $cityData['total_outbreaks'];
        
        // Get most recent outbreak date
        foreach ($cityData['outbreaks'] as $outbreak) {
            $obDate = $outbreak['outbreak_date'];
            if ($cityData['last_outbreak_date'] === null || $obDate > $cityData['last_outbreak_date']) {
                $cityData['last_outbreak_date'] = $obDate;
            }
        }
        
        // Get depopulation events for this city
        $depopSql = "SELECT COUNT(*) as count, SUM(head_count) as total_heads
                     FROM depopulation_events 
                     WHERE city = ? AND province = ?
                     AND event_date >= ?";
        $depopStmt = $pdo->prepare($depopSql);
        $depopStmt->execute([$cityData['city'], $cityData['province'], $cutoffDate]);
        $depopResult = $depopStmt->fetch(PDO::FETCH_ASSOC);
        $cityData['total_depopulation'] = intval($depopResult['count'] ?? 0);
    }
    unset($cityData);
    
    // Step 4: Calculate risk scores and classify zones by ASF zone type
    $zonesCreated = 0;
    $zonesByType = ['infected' => 0, 'buffer' => 0, 'surveillance' => 0, 'protected' => 0, 'free' => 0];
    
    // Handle existing zones
    if ($replaceExisting === 'replace') {
        $pdo->exec("DELETE FROM risk_zones");
    }
    
    foreach ($cityOutbreaks as $cityKey => $cityData) {
        // Calculate risk score for the city (simplified calculation)
        $riskScore = calculateCityRiskScore($cityData, $includeEnvironmental, $includeMovement, $pdo, $cutoffDate);
        
        // Classify city into ASF zone type
        $zoneType = classifyCityZone($cityData, $riskScore);
        $zonesByType[$zoneType]++;
        
        // Map zone type to risk_level for database compatibility
        $riskLevel = 'low';
        if ($zoneType === 'infected') {
            $riskLevel = 'critical';
        } elseif ($zoneType === 'buffer') {
            $riskLevel = 'high';
        } elseif ($zoneType === 'surveillance') {
            $riskLevel = 'medium';
        } elseif ($zoneType === 'protected' || $zoneType === 'free') {
            $riskLevel = 'low';
        }
        
        // Check if zone exists for this city
        if ($replaceExisting === 'update') {
            $checkSql = "SELECT id FROM risk_zones WHERE city = ? AND province = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$cityData['city'], $cityData['province']]);
            $existingZone = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingZone) {
                // Build factors contributing JSON
                $factors = [
                    'zone_type' => $zoneType,
                    'total_outbreaks' => $cityData['total_outbreaks'],
                    'depopulation_events' => $cityData['total_depopulation'],
                    'cluster_radius_km' => round($clusterRadius, 2)
                ];
                
                // Update existing zone
                $updateSql = "UPDATE risk_zones SET
                             risk_score = ?,
                             risk_level = ?,
                             nearby_outbreaks_count = ?,
                             last_outbreak_date = ?,
                             depopulation_count = ?,
                             factors_contributing = ?,
                             updated_at = NOW()
                             WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    round($riskScore, 2),
                    $riskLevel,
                    $cityData['total_outbreaks'],
                    $cityData['last_outbreak_date'],
                    $cityData['total_depopulation'],
                    json_encode($factors),
                    $existingZone['id']
                ]);
                continue;
            }
        }
        
        // Generate unique zone code
        // Use city abbreviation + date + sequence to ensure uniqueness
        $cityAbbr = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $cityData['city']), 0, 3));
        $baseCode = 'RZ' . date('Ymd') . $cityAbbr;
        $zoneCode = $baseCode;
        
        // Check if zone code exists and increment if needed
        $counter = 1;
        while (true) {
            $checkSql = "SELECT id FROM risk_zones WHERE zone_code = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$zoneCode]);
            if ($checkStmt->rowCount() == 0) {
                break; // Code is unique
            }
            $zoneCode = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
            if ($counter > 99) {
                // Fallback to timestamp-based code if too many duplicates
                $zoneCode = 'RZ' . date('YmdHis') . rand(100, 999);
                break;
            }
        }
        
        // Generate zone name based on city and zone type
        $cityName = $cityData['city'] ? $cityData['city'] : $cityData['province'];
        $zoneName = $cityName . ' - ' . ucfirst($zoneType) . ' Zone';
        
        // Calculate radius (average distance from center to outbreaks + 20%)
        $totalDistance = 0;
        foreach ($cityData['outbreaks'] as $outbreak) {
            $dist = calculateDistance(
                $cityData['center_lat'], $cityData['center_lon'],
                floatval($outbreak['latitude']), floatval($outbreak['longitude'])
            );
            $totalDistance += $dist;
        }
        $avgDistance = $cityData['total_outbreaks'] > 0 ? $totalDistance / $cityData['total_outbreaks'] : 5.0;
        $radiusKm = max(3.0, min(20.0, $avgDistance * 1.2)); // Min 3km, max 20km
        
        // Build factors contributing JSON
        $factors = [
            'zone_type' => $zoneType,
            'total_outbreaks' => $cityData['total_outbreaks'],
            'depopulation_events' => $cityData['total_depopulation'],
            'cluster_radius_km' => round($clusterRadius, 2)
        ];
        
        // Insert risk zone
        $insertSql = "INSERT INTO risk_zones 
                     (zone_code, zone_name, province, city, 
                      center_latitude, center_longitude, radius_km,
                      risk_level, risk_score, factors_contributing,
                      nearby_outbreaks_count, last_outbreak_date, depopulation_count,
                      status, identified_date, reviewed_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', CURDATE(), ?)";
        
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            $zoneCode,
            $zoneName,
            $cityData['province'],
            $cityData['city'],
            $cityData['center_lat'],
            $cityData['center_lon'],
            round($radiusKm, 2),
            $riskLevel,
            round($riskScore, 2),
            json_encode($factors),
            $cityData['total_outbreaks'],
            $cityData['last_outbreak_date'],
            $cityData['total_depopulation'],
            $userId
        ]);
        
        $zonesCreated++;
    }
    
    $pdo->commit();
    
    $endTime = microtime(true);
    $processingTime = round($endTime - $startTime, 2);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully calculated {$zonesCreated} risk zones by city/municipality",
        'total_zones' => $zonesCreated,
        'infected_zones' => $zonesByType['infected'],
        'buffer_zones' => $zonesByType['buffer'],
        'surveillance_zones' => $zonesByType['surveillance'],
        'protected_zones' => $zonesByType['protected'],
        'free_zones' => $zonesByType['free'],
        'processing_time' => $processingTime . ' seconds'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error calculating risk zones: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to calculate risk zones: ' . $e->getMessage()
    ]);
}

/**
 * Calculate distance between two points (Haversine formula)
 * Returns distance in kilometers
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

/**
 * Calculate risk score (0-100) for a city based on outbreak and depopulation data
 */
function calculateCityRiskScore($cityData, $includeEnvironmental, $includeMovement, $pdo, $cutoffDate) {
    $score = 0;
    
    // Outbreak factors (0-50 points)
    $outbreakCount = $cityData['total_outbreaks'];
    $outbreakScore = min(50, ($outbreakCount / 10) * 50); // Max 50 points for 10+ outbreaks
    $score += $outbreakScore;
    
    // Depopulation factors (0-20 points)
    $depopScore = min(20, ($cityData['total_depopulation'] / 5) * 20); // Max 20 points for 5+ events
    $score += $depopScore;
    
    // Recency factor (0-15 points)
    if ($cityData['last_outbreak_date']) {
        $daysSince = (time() - strtotime($cityData['last_outbreak_date'])) / 86400;
        if ($daysSince <= 7) {
            $recencyScore = 15; // Very recent
        } elseif ($daysSince <= 30) {
            $recencyScore = 10; // Recent
        } elseif ($daysSince <= 60) {
            $recencyScore = 5; // Somewhat recent
        } else {
            $recencyScore = 2; // Older
        }
        $score += $recencyScore;
    }
    
    // Environmental factors (0-10 points, if enabled)
    if ($includeEnvironmental) {
        $envSql = "SELECT AVG(temperature) as avg_temp, 
                          AVG(humidity) as avg_humidity,
                          AVG(rainfall) as avg_rainfall
                   FROM environmental_data 
                   WHERE city = ? AND province = ?
                   AND recorded_at >= ?";
        $envStmt = $pdo->prepare($envSql);
        $envStmt->execute([$cityData['city'], $cityData['province'], $cutoffDate]);
        $envData = $envStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($envData) {
            $avgTemp = $envData['avg_temp'] ?? 0;
            $avgHumidity = $envData['avg_humidity'] ?? 0;
            $envScore = 0;
            if ($avgTemp > 25 && $avgTemp < 35) {
                $envScore += 3; // Optimal temperature range
            }
            if ($avgHumidity > 60 && $avgHumidity < 90) {
                $envScore += 3; // Optimal humidity range
            }
            if ($envData['avg_rainfall'] > 100) {
                $envScore += 4; // High rainfall
            }
            $score += min(10, $envScore);
        }
    }
    
    // Movement factors (0-5 points, if enabled)
    if ($includeMovement) {
        $movementSql = "SELECT COUNT(*) as count
                       FROM meat_movement 
                       WHERE (source_city = ? AND source_province = ?)
                          OR (destination_city = ? AND destination_province = ?)
                       AND movement_date >= ?";
        $movementStmt = $pdo->prepare($movementSql);
        $movementStmt->execute([
            $cityData['city'], $cityData['province'],
            $cityData['city'], $cityData['province'],
            $cutoffDate
        ]);
        $movementResult = $movementStmt->fetch(PDO::FETCH_ASSOC);
        $movementCount = intval($movementResult['count'] ?? 0);
        $movementScore = min(5, ($movementCount / 20) * 5); // Max 5 points for 20+ movements
        $score += $movementScore;
    }
    
    // Ensure score is between 0 and 100
    return max(0, min(100, round($score, 2)));
}

/**
 * Classify a city/municipality into ASF zone type based on risk factors
 * Returns: 'infected', 'buffer', 'surveillance', 'protected', or 'free'
 */
function classifyCityZone($cityData, $riskScore) {
    $outbreakCount = $cityData['total_outbreaks'];
    $lastOutbreakDate = $cityData['last_outbreak_date'];
    $depopCount = $cityData['total_depopulation'];
    
    // Check for recent outbreaks (within 60 days)
    $daysSince = null;
    if ($lastOutbreakDate) {
        $daysSince = (time() - strtotime($lastOutbreakDate)) / 86400;
    }
    
    // Infected Zone: Has outbreaks within 60 days OR very high risk score (>=80) with outbreaks
    if ($outbreakCount > 0 && $lastOutbreakDate && $daysSince <= 60) {
        return 'infected';
    }
    if ($riskScore >= 80 && $outbreakCount > 0) {
        return 'infected';
    }
    
    // Buffer Zone: High risk score (60-79) with outbreaks OR high depopulation activity
    // Also consider high risk score even without recent outbreaks but with depopulation
    if ($riskScore >= 60 && $riskScore < 80 && $outbreakCount > 0) {
        return 'buffer';
    }
    if ($depopCount >= 5 && $outbreakCount > 0) {
        return 'buffer';
    }
    if ($riskScore >= 65 && $depopCount >= 3) {
        return 'buffer';
    }
    
    // Surveillance Zone: Medium risk score (40-59) OR moderate outbreak/depopulation activity
    // This should include cities with some activity but not critical
    if ($riskScore >= 40 && $riskScore < 60) {
        return 'surveillance';
    }
    if ($riskScore >= 30 && $outbreakCount > 0 && (!empty($daysSince) && $daysSince > 60)) {
        return 'surveillance';
    }
    if ($riskScore >= 25 && $depopCount > 0) {
        return 'surveillance';
    }
    if ($outbreakCount > 0 && (!empty($daysSince) && $daysSince > 60 && $daysSince <= 180)) {
        return 'surveillance';
    }
    
    // Protected Zone: Low risk score (15-39) with no recent outbreaks
    // Cities with low activity but still need monitoring
    if ($riskScore >= 15 && $riskScore < 40) {
        if ($outbreakCount == 0) {
            return 'protected';
        }
        if (!empty($daysSince) && $daysSince > 90) {
            return 'protected';
        }
        if ($depopCount > 0 && $depopCount < 3) {
            return 'protected';
        }
    }
    if ($riskScore >= 10 && $riskScore < 15 && $outbreakCount == 0 && $depopCount == 0) {
        return 'protected';
    }
    
    // Free Zone: Very low risk score (<15) and no outbreaks/depopulation (default fallback)
    // Cities with minimal to no ASF activity
    return 'free';
}
