<?php
/**
 * API endpoint to get barangay locations for ASF Simulation
 * Returns all unique barangays from outbreak data with coordinates
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get unique barangays with their average coordinates from outbreaks
    $sql = "SELECT 
                province,
                city,
                barangay,
                AVG(latitude) as latitude,
                AVG(longitude) as longitude,
                COUNT(*) as outbreak_count
            FROM asf_outbreaks
            WHERE barangay IS NOT NULL 
                AND barangay != ''
                AND latitude IS NOT NULL 
                AND longitude IS NOT NULL
                AND latitude != 0 
                AND longitude != 0
            GROUP BY province, city, barangay
            ORDER BY province, city, barangay";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formattedLocations = [];
    foreach ($locations as $location) {
        $formattedLocations[] = [
            'province' => $location['province'],
            'city' => $location['city'],
            'barangay' => $location['barangay'],
            'latitude' => floatval($location['latitude']),
            'longitude' => floatval($location['longitude']),
            'outbreak_count' => intval($location['outbreak_count']),
            'display_name' => $location['barangay'] . ', ' . $location['city'] . ', ' . $location['province']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedLocations
    ]);
    
} catch (Exception $e) {
    error_log("Get Simulation Locations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching simulation locations',
        'error' => $e->getMessage()
    ]);
}
