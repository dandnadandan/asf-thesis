<?php
/**
 * API endpoint to get outbreak spread statistics for simulation
 * Analyzes existing outbreak data to determine spread patterns
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get all outbreaks with their dates and locations
    $sql = "SELECT 
                id,
                province,
                city,
                barangay,
                latitude,
                longitude,
                outbreak_date,
                reported_date,
                status,
                total_pigs_affected,
                severity_level
            FROM asf_outbreaks
            WHERE latitude IS NOT NULL 
                AND longitude IS NOT NULL
                AND latitude != 0 
                AND longitude != 0
                AND outbreak_date IS NOT NULL
            ORDER BY outbreak_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate spread statistics
    $spreadData = [
        'total_outbreaks' => count($outbreaks),
        'avg_daily_spread' => 0,
        'avg_distance_between_outbreaks' => 0,
        'spread_pattern' => []
    ];
    
    if (count($outbreaks) > 1) {
        // Calculate average days between outbreaks
        $dateDiffs = [];
        $distances = [];
        
        for ($i = 1; $i < count($outbreaks); $i++) {
            $prevDate = new DateTime($outbreaks[$i-1]['outbreak_date']);
            $currDate = new DateTime($outbreaks[$i]['outbreak_date']);
            $diff = $prevDate->diff($currDate);
            $days = $diff->days;
            if ($days > 0) {
                $dateDiffs[] = $days;
            }
            
            // Calculate distance between outbreaks (Haversine formula)
            $lat1 = deg2rad(floatval($outbreaks[$i-1]['latitude']));
            $lon1 = deg2rad(floatval($outbreaks[$i-1]['longitude']));
            $lat2 = deg2rad(floatval($outbreaks[$i]['latitude']));
            $lon2 = deg2rad(floatval($outbreaks[$i]['longitude']));
            
            $dlat = $lat2 - $lat1;
            $dlon = $lon2 - $lon1;
            
            $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = 6371 * $c; // Distance in km (Earth radius = 6371 km)
            
            if ($distance > 0 && $distance < 100) { // Filter out unrealistic distances
                $distances[] = $distance;
            }
        }
        
        if (count($dateDiffs) > 0) {
            $spreadData['avg_daily_spread'] = array_sum($dateDiffs) / count($dateDiffs);
        }
        
        if (count($distances) > 0) {
            $spreadData['avg_distance_between_outbreaks'] = array_sum($distances) / count($distances);
        }
    }
    
    // Group outbreaks by time periods to understand spread patterns
    $timePatterns = [];
    foreach ($outbreaks as $outbreak) {
        $date = new DateTime($outbreak['outbreak_date']);
        $week = $date->format('Y-W'); // Year-Week
        
        if (!isset($timePatterns[$week])) {
            $timePatterns[$week] = [];
        }
        
        $timePatterns[$week][] = [
            'latitude' => floatval($outbreak['latitude']),
            'longitude' => floatval($outbreak['longitude']),
            'city' => $outbreak['city'],
            'barangay' => $outbreak['barangay'],
            'outbreak_date' => $outbreak['outbreak_date']
        ];
    }
    
    $spreadData['spread_pattern'] = $timePatterns;
    
    echo json_encode([
        'success' => true,
        'data' => $spreadData
    ]);
    
} catch (Exception $e) {
    error_log("Get Simulation Spread Data Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching simulation spread data',
        'error' => $e->getMessage()
    ]);
}
