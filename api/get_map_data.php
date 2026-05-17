    <?php
    /**
     * API endpoint to get GIS map data for ASF Surveillance System
     * Returns outbreak data from asf_outbreaks table mapped to zone types
     * Status mapping: confirmed->infected, contained->buffer, suspected->surveillance, resolved->protected, false_alarm->free
     * Locations are grouped by city and barangay, using average coordinates for each group
     */

    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/database.php';

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get date range parameters
        $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : null;
        $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : null;
        
        // Build WHERE clause for date filtering.
        // Use COALESCE so CSV-uploaded records (reported_date) and manual entries (outbreak_date) both match.
        $outbreakConditions = ["province = 'CALABARZON'"];
        $outbreakParams = [];

        if ($dateFrom) {
            $outbreakConditions[] = "COALESCE(outbreak_date, reported_date) >= ?";
            $outbreakParams[] = $dateFrom;
        }

        if ($dateTo) {
            $outbreakConditions[] = "COALESCE(outbreak_date, reported_date) <= ?";
            $outbreakParams[] = $dateTo;
        }

        $outbreakWhere = 'WHERE ' . implode(' AND ', $outbreakConditions);

        // Build a city → zone_type lookup from the calculated risk_zones table.
        $cityZoneMap = [];
        $rzStmt = $pdo->query("SELECT city, factors_contributing FROM risk_zones WHERE province = 'CALABARZON'");
        foreach ($rzStmt->fetchAll(PDO::FETCH_ASSOC) as $rz) {
            $factors = json_decode($rz['factors_contributing'], true);
            $zoneType = $factors['zone_type'] ?? null;
            if ($zoneType && !empty($rz['city'])) {
                $cityZoneMap[strtolower(trim($rz['city']))] = $zoneType;
            }
        }

        // Query outbreaks — simple, no JOIN
        $outbreakSql = "SELECT
                        id,
                        outbreak_code,
                        location_name,
                        province,
                        city,
                        barangay,
                        latitude,
                        longitude,
                        status,
                        COALESCE(outbreak_date, reported_date) AS outbreak_date,
                        reported_date,
                        total_pigs_affected,
                        total_pigs_mortality,
                        total_pigs_depopulated,
                        severity_level
                    FROM asf_outbreaks
                    {$outbreakWhere}
                    ORDER BY COALESCE(outbreak_date, reported_date) DESC";
        
        $stmt = $pdo->prepare($outbreakSql);
        $stmt->execute($outbreakParams);
        $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group outbreaks by city and barangay, then calculate average coordinates
        $locationGroups = [];
        $zonePriority = ['infected' => 0, 'buffer' => 1, 'surveillance' => 2, 'protected' => 3, 'free' => 4];
        $severityZoneMap = ['critical' => 'infected', 'high' => 'buffer', 'medium' => 'surveillance', 'low' => 'protected'];

        foreach ($outbreaks as $outbreak) {
            // Create unique key for city + barangay combination
            $locationKey = strtolower(trim($outbreak['province'] ?? 'CALABARZON') . '|' .
                                    trim($outbreak['city'] ?? '') . '|' .
                                    trim($outbreak['barangay'] ?? ''));

            // Resolve zone type: prefer calculated risk_zones, fall back to severity_level
            $cityKey = strtolower(trim($outbreak['city'] ?? ''));
            $resolvedZone = $cityZoneMap[$cityKey]
                ?? $severityZoneMap[strtolower($outbreak['severity_level'] ?? '')]
                ?? 'free';

            if (!isset($locationGroups[$locationKey])) {
                $locationGroups[$locationKey] = [
                    'province' => $outbreak['province'] ?? 'CALABARZON',
                    'city' => $outbreak['city'] ?? '',
                    'barangay' => $outbreak['barangay'] ?? null,
                    'zone_type' => $resolvedZone,
                    'priority' => $zonePriority[$resolvedZone] ?? 4,
                    'outbreaks' => [],
                    'coordinates' => []
                ];
            }

            $locationGroups[$locationKey]['outbreaks'][] = $outbreak;

            // Keep the highest-priority (most severe) zone type for the group
            $newPriority = $zonePriority[$resolvedZone] ?? 4;
            if ($newPriority < $locationGroups[$locationKey]['priority']) {
                $locationGroups[$locationKey]['zone_type'] = $resolvedZone;
                $locationGroups[$locationKey]['priority'] = $newPriority;
            }
            
            // Collect valid coordinates for averaging
            if (!empty($outbreak['latitude']) && !empty($outbreak['longitude']) &&
                is_numeric($outbreak['latitude']) && is_numeric($outbreak['longitude']) &&
                floatval($outbreak['latitude']) != 0 && floatval($outbreak['longitude']) != 0) {
                $locationGroups[$locationKey]['coordinates'][] = [
                    'latitude' => floatval($outbreak['latitude']),
                    'longitude' => floatval($outbreak['longitude'])
                ];
            }
        }
        
        // Calculate average coordinates for each city/barangay group
        $outbreakLocations = [];
        foreach ($locationGroups as $locationKey => $location) {
            if (empty($location['city'])) {
                continue; // Skip if no city
            }
            
            // Calculate average coordinates for this location group
            if (!empty($location['coordinates'])) {
                $avgLat = array_sum(array_column($location['coordinates'], 'latitude')) / count($location['coordinates']);
                $avgLon = array_sum(array_column($location['coordinates'], 'longitude')) / count($location['coordinates']);
                
                $outbreakLocations[] = [
                    'id' => $location['outbreaks'][0]['id'], // Use first outbreak ID
                    'outbreak_code' => $location['outbreaks'][0]['outbreak_code'],
                    'location_name' => $location['outbreaks'][0]['location_name'] ?? $location['city'],
                    'city' => $location['city'],
                    'province' => $location['province'],
                    'barangay' => $location['barangay'],
                    'latitude' => floatval($avgLat),
                    'longitude' => floatval($avgLon),
                    'zone_type' => $location['zone_type'],
                    'status' => $location['outbreaks'][0]['status'],
                    'outbreak_date' => $location['outbreaks'][0]['outbreak_date'],
                    'total_outbreaks' => count($location['outbreaks']),
                    'total_pigs_affected' => array_sum(array_column($location['outbreaks'], 'total_pigs_affected')),
                    'total_pigs_mortality' => array_sum(array_column($location['outbreaks'], 'total_pigs_mortality')),
                    'total_pigs_depopulated' => array_sum(array_column($location['outbreaks'], 'total_pigs_depopulated')),
                    'severity_level' => $location['outbreaks'][0]['severity_level']
                ];
            }
        }
        
        $today = date('Y-m-d');

    $isFutureRequest = false;

    if ($dateTo && $dateTo > $today) {
        $isFutureRequest = true;
    }
    if ($isFutureRequest) {

    // Reload latest historical outbreak data
    $stmt = $pdo->prepare("
        SELECT
            id,
            outbreak_code,
            location_name,
            province,
            city,
            barangay,
            latitude,
            longitude,
            status,
            COALESCE(outbreak_date, reported_date) AS outbreak_date,
            reported_date,
            total_pigs_affected,
            total_pigs_mortality,
            total_pigs_depopulated,
            severity_level
        FROM asf_outbreaks
        WHERE province = 'CALABARZON'
        ORDER BY COALESCE(outbreak_date, reported_date) DESC
    ");

    $stmt->execute();

    $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // RESET grouped data
    $locationGroups = [];
    $outbreakLocations = [];

    // REBUILD groups
    foreach ($outbreaks as $outbreak) {

        $locationKey = strtolower(
            trim($outbreak['province'] ?? 'CALABARZON') . '|' .
            trim($outbreak['city'] ?? '') . '|' .
            trim($outbreak['barangay'] ?? '')
        );

        $cityKey2 = strtolower(trim($outbreak['city'] ?? ''));
        $resolvedZone2 = $cityZoneMap[$cityKey2]
            ?? $severityZoneMap[strtolower($outbreak['severity_level'] ?? '')]
            ?? 'free';

        if (!isset($locationGroups[$locationKey])) {
            $locationGroups[$locationKey] = [
                'province' => $outbreak['province'] ?? 'CALABARZON',
                'city' => $outbreak['city'] ?? '',
                'barangay' => $outbreak['barangay'] ?? null,
                'zone_type' => $resolvedZone2,
                'priority' => $zonePriority[$resolvedZone2] ?? 4,
                'outbreaks' => [],
                'coordinates' => []
            ];
        }

        $locationGroups[$locationKey]['outbreaks'][] = $outbreak;

        if (
            !empty($outbreak['latitude']) &&
            !empty($outbreak['longitude'])
        ) {

            $locationGroups[$locationKey]['coordinates'][] = [
                'latitude' => floatval($outbreak['latitude']),
                'longitude' => floatval($outbreak['longitude'])
            ];
        }
    }

    // REBUILD outbreakLocations
        foreach ($locationGroups as $location) {

            if (empty($location['coordinates'])) {
                continue;
            }

            $avgLat = array_sum(array_column(
                $location['coordinates'],
                'latitude'
            )) / count($location['coordinates']);

            $avgLon = array_sum(array_column(
                $location['coordinates'],
                'longitude'
            )) / count($location['coordinates']);

            $outbreakLocations[] = [
                'city' => $location['city'],
                'barangay' => $location['barangay'],
                'latitude' => $avgLat,
                'longitude' => $avgLon,
                'zone_type' => $location['zone_type']
            ];
        }
    }

    if ($isFutureRequest) {

        // Build prediction input
        $predictionInput = [];

        foreach ($outbreakLocations as $location) {

            $predictionInput[] = [
                'city' => $location['city'],
                'barangay' => $location['barangay'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'future_date' => $dateTo
            ];
        }
        error_log(print_r($predictionInput, true));
        // Save JSON input
        file_put_contents(
            __DIR__ . '/../ml/input.json',
            json_encode($predictionInput)
        );

        // Run Python prediction
        $pythonScript = __DIR__ . '/../ml/predict.py';

        $python = "python";

        $command = "$python \"$pythonScript\" 2>&1";

        $output = shell_exec($command);

        error_log("PYTHON OUTPUT: " . $output);

        error_log($output);

        // Read prediction output
        $predictionsPath = __DIR__ . '/../ml/predictions.json';

        if (file_exists($predictionsPath)) {

            $predictions = json_decode(
                file_get_contents($predictionsPath),
                true
            );

            // Replace historical data with predictions
            $predictionMap = [];

            foreach ($predictions as $p) {

                $key = strtoupper($p['city'] . '|' . ($p['barangay'] ?? ''));

                $predictionMap[$key] = $p['zone_type'];
            }
            foreach ($outbreakLocations as &$location) {

                $key = strtoupper($location['city'] . '|' . ($location['barangay'] ?? ''));

                if (isset($predictionMap[$key])) {

                    $location['zone_type'] = $predictionMap[$key];
                }
            }
        }
    }


        // Return data in expected format
        echo json_encode([
            'success' => true,
            'data' => [
                'cities' => $outbreakLocations
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get Map Data Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching map data',
            'error' => $e->getMessage()
        ]);
    } catch (Error $e) {
        error_log("Get Map Data Fatal Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error fetching map data',
            'error' => $e->getMessage()
        ]);
    }
