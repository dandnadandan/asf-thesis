<?php
/**
 * CSV Upload Handler for ASF Surveillance System
 * Processes CSV file uploads and imports data into appropriate tables
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

// Check if file was uploaded
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$uploadType = $_POST['uploadType'] ?? '';
$notes = $_POST['notes'] ?? '';
$file = $_FILES['csvFile'];

// Validate upload type
$validTypes = ['environmental', 'outbreaks', 'depopulation', 'meat_movement'];
if (!in_array($uploadType, $validTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload type']);
    exit();
}

// Validate file type
$allowedExtensions = ['csv', 'xlsx', 'xls'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only CSV, XLSX, and XLS files are allowed']);
    exit();
}

// Validate file size (10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $userId = $_SESSION['user_id'];
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../../uploads/data/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique upload code
    $uploadCode = 'UPL' . date('YmdHis') . rand(1000, 9999);
    
    // Generate unique filename
    $fileName = $uploadCode . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Parse CSV file
    $data = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new Exception('Failed to open uploaded file');
    }
    
    // Read CSV headers
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        throw new Exception('Failed to read CSV headers');
    }
    
    // Normalize headers (trim and lowercase)
    $headers = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);
    
    // Read data rows
    $rowNum = 1; // Start from 1 (headers are row 0)
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count($row) !== count($headers)) {
            continue; // Skip rows with incorrect column count
        }
        $data[] = array_combine($headers, $row);
    }
    fclose($handle);
    
    $totalRecords = count($data);
    $successfulRecords = 0;
    $failedRecords = 0;
    $validationErrors = [];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert upload record
    $stmt = $pdo->prepare("INSERT INTO data_uploads 
                          (upload_code, upload_type, file_name, file_path, file_size, file_type, 
                           total_records, successful_records, failed_records, status, uploaded_by, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'processing', ?, ?)");
    $stmt->execute([
        $uploadCode,
        $uploadType,
        $file['name'],
        'uploads/data/' . $fileName,
        $file['size'],
        $fileExtension,
        $totalRecords,
        0,
        0,
        $userId,
        $notes
    ]);
    $uploadId = $pdo->lastInsertId();
    
    // Process data based on upload type
    foreach ($data as $index => $row) {
        try {
            switch ($uploadType) {
                case 'environmental':
                    $success = processEnvironmentalData($pdo, $row, $userId, $index + 1);
                    break;
                case 'outbreaks':
                    $success = processOutbreakData($pdo, $row, $userId, $index + 1);
                    break;
                case 'depopulation':
                    $success = processDepopulationData($pdo, $row, $userId, $index + 1);
                    break;
                case 'meat_movement':
                    $success = processMeatMovementData($pdo, $row, $userId, $index + 1);
                    break;
                default:
                    $success = false;
            }
            
            if ($success) {
                $successfulRecords++;
            } else {
                $failedRecords++;
                $validationErrors[] = [
                    'row' => $index + 2, // +2 because index starts at 0 and we skip header row
                    'error' => 'Failed to process row'
                ];
            }
        } catch (Exception $e) {
            $failedRecords++;
            $validationErrors[] = [
                'row' => $index + 2,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Determine final status
    $finalStatus = 'completed';
    if ($failedRecords > 0 && $successfulRecords > 0) {
        $finalStatus = 'partially_completed';
    } elseif ($failedRecords > 0) {
        $finalStatus = 'failed';
    }
    
    // Update upload record
    $stmt = $pdo->prepare("UPDATE data_uploads 
                          SET successful_records = ?, 
                              failed_records = ?, 
                              validation_errors = ?,
                              status = ?,
                              processed_at = NOW(),
                              completed_at = NOW()
                          WHERE id = ?");
    $stmt->execute([
        $successfulRecords,
        $failedRecords,
        json_encode($validationErrors),
        $finalStatus,
        $uploadId
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Upload completed. {$successfulRecords} records imported successfully" . ($failedRecords > 0 ? ", {$failedRecords} failed" : ""),
        'upload_id' => $uploadId,
        'total_records' => $totalRecords,
        'successful_records' => $successfulRecords,
        'failed_records' => $failedRecords
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("CSV Upload Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload: ' . $e->getMessage()
    ]);
}

/**
 * Process environmental data row
 */
function processEnvironmentalData($pdo, $row, $userId, $rowNum) {
    // Required fields validation
    $requiredFields = ['location_name', 'latitude', 'longitude', 'city', 'recorded_at'];
    foreach ($requiredFields as $field) {
        if (empty($row[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Validate coordinates
    $latitude = floatval($row['latitude']);
    $longitude = floatval($row['longitude']);
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception("Invalid coordinates");
    }
    
    // Validate province (must be CALABARZON)
    $province = !empty($row['province']) ? trim($row['province']) : 'CALABARZON';
    if (strtoupper($province) !== 'CALABARZON') {
        $province = 'CALABARZON'; // Force CALABARZON
    }
    
    // Parse recorded_at
    $recordedAt = !empty($row['recorded_at']) ? $row['recorded_at'] : date('Y-m-d H:i:s');
    try {
        $recordedAt = date('Y-m-d H:i:s', strtotime($recordedAt));
    } catch (Exception $e) {
        $recordedAt = date('Y-m-d H:i:s');
    }
    
    $stmt = $pdo->prepare("INSERT INTO environmental_data 
                          (location_name, latitude, longitude, province, city, barangay,
                           temperature, humidity, rainfall, wind_speed, wind_direction,
                           atmospheric_pressure, recorded_by, recorded_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        trim($row['location_name']),
        $latitude,
        $longitude,
        $province,
        trim($row['city']),
        !empty($row['barangay']) ? trim($row['barangay']) : null,
        !empty($row['temperature']) ? floatval($row['temperature']) : null,
        !empty($row['humidity']) ? floatval($row['humidity']) : null,
        !empty($row['rainfall']) ? floatval($row['rainfall']) : null,
        !empty($row['wind_speed']) ? floatval($row['wind_speed']) : null,
        !empty($row['wind_direction']) ? trim($row['wind_direction']) : null,
        !empty($row['atmospheric_pressure']) ? floatval($row['atmospheric_pressure']) : null,
        $userId,
        $recordedAt
    ]);
}

/**
 * Process outbreak data row
 */
function processOutbreakData($pdo, $row, $userId, $rowNum) {
    // Generate unique outbreak code
    $outbreakCode = 'OB' . date('Ymd') . rand(1000, 9999) . $rowNum;
    
    // Required fields
    if (empty($row['location_name']) || empty($row['latitude']) || empty($row['longitude']) || 
        empty($row['city']) || empty($row['outbreak_date'])) {
        throw new Exception("Missing required fields");
    }
    
    $latitude = floatval($row['latitude']);
    $longitude = floatval($row['longitude']);
    $province = !empty($row['province']) ? trim($row['province']) : 'CALABARZON';
    if (strtoupper($province) !== 'CALABARZON') {
        $province = 'CALABARZON';
    }
    
    $outbreakDate = date('Y-m-d', strtotime($row['outbreak_date']));
    $reportedDate = !empty($row['reported_date']) ? date('Y-m-d H:i:s', strtotime($row['reported_date'])) : date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO asf_outbreaks 
                          (outbreak_code, location_name, latitude, longitude, province, city, barangay,
                           farm_name, farm_type, outbreak_date, reported_date, status,
                           total_pigs_affected, total_pigs_mortality, total_pigs_depopulated,
                           severity_level, reported_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $outbreakCode,
        trim($row['location_name']),
        $latitude,
        $longitude,
        $province,
        trim($row['city']),
        !empty($row['barangay']) ? trim($row['barangay']) : null,
        !empty($row['farm_name']) ? trim($row['farm_name']) : null,
        !empty($row['farm_type']) ? trim($row['farm_type']) : null,
        $outbreakDate,
        $reportedDate,
        !empty($row['status']) ? trim($row['status']) : 'suspected',
        !empty($row['total_pigs_affected']) ? intval($row['total_pigs_affected']) : 0,
        !empty($row['total_pigs_mortality']) ? intval($row['total_pigs_mortality']) : 0,
        !empty($row['total_pigs_depopulated']) ? intval($row['total_pigs_depopulated']) : 0,
        !empty($row['severity_level']) ? trim($row['severity_level']) : null,
        $userId
    ]);
}

/**
 * Process depopulation data row
 */
function processDepopulationData($pdo, $row, $userId, $rowNum) {
    $eventCode = 'DEP' . date('Ymd') . rand(1000, 9999) . $rowNum;
    
    if (empty($row['location_name']) || empty($row['latitude']) || empty($row['longitude']) || 
        empty($row['city']) || empty($row['event_date'])) {
        throw new Exception("Missing required fields");
    }
    
    $latitude = floatval($row['latitude']);
    $longitude = floatval($row['longitude']);
    $province = !empty($row['province']) ? trim($row['province']) : 'CALABARZON';
    if (strtoupper($province) !== 'CALABARZON') {
        $province = 'CALABARZON';
    }
    
    $eventDate = date('Y-m-d', strtotime($row['event_date']));
    
    $stmt = $pdo->prepare("INSERT INTO depopulation_events 
                          (event_code, location_name, latitude, longitude, province, city, barangay,
                           farm_name, event_date, head_count, depopulation_method, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $eventCode,
        trim($row['location_name']),
        $latitude,
        $longitude,
        $province,
        trim($row['city']),
        !empty($row['barangay']) ? trim($row['barangay']) : null,
        !empty($row['farm_name']) ? trim($row['farm_name']) : null,
        $eventDate,
        !empty($row['head_count']) ? intval($row['head_count']) : 0,
        !empty($row['depopulation_method']) ? trim($row['depopulation_method']) : 'culling',
        $userId
    ]);
}

/**
 * Process meat movement data row
 */
function processMeatMovementData($pdo, $row, $userId, $rowNum) {
    $movementCode = 'MM' . date('Ymd') . rand(1000, 9999) . $rowNum;
    
    if (empty($row['source_location']) || empty($row['source_city']) || 
        empty($row['destination_location']) || empty($row['destination_city']) || 
        empty($row['movement_date'])) {
        throw new Exception("Missing required fields");
    }
    
    $sourceProvince = !empty($row['source_province']) ? trim($row['source_province']) : 'CALABARZON';
    $destProvince = !empty($row['destination_province']) ? trim($row['destination_province']) : 'CALABARZON';
    if (strtoupper($sourceProvince) !== 'CALABARZON') {
        $sourceProvince = 'CALABARZON';
    }
    if (strtoupper($destProvince) !== 'CALABARZON') {
        $destProvince = 'CALABARZON';
    }
    
    $movementDate = date('Y-m-d', strtotime($row['movement_date']));
    
    $stmt = $pdo->prepare("INSERT INTO meat_movement 
                          (movement_code, source_location, source_latitude, source_longitude,
                           source_province, source_city, destination_location,
                           destination_latitude, destination_longitude, destination_province,
                           destination_city, movement_date, meat_type, quantity_kg,
                           quantity_heads, recorded_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $movementCode,
        trim($row['source_location']),
        !empty($row['source_latitude']) ? floatval($row['source_latitude']) : null,
        !empty($row['source_longitude']) ? floatval($row['source_longitude']) : null,
        $sourceProvince,
        trim($row['source_city']),
        trim($row['destination_location']),
        !empty($row['destination_latitude']) ? floatval($row['destination_latitude']) : null,
        !empty($row['destination_longitude']) ? floatval($row['destination_longitude']) : null,
        $destProvince,
        trim($row['destination_city']),
        $movementDate,
        !empty($row['meat_type']) ? trim($row['meat_type']) : 'fresh',
        !empty($row['quantity_kg']) ? floatval($row['quantity_kg']) : null,
        !empty($row['quantity_heads']) ? intval($row['quantity_heads']) : null,
        $userId
    ]);
}
