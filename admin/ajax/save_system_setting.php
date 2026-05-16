<?php
/**
 * Save System Setting Endpoint
 * Handles creation and updating of system settings
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

// Check if user has permission
if (!canManageSystemSettings()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$currentUser = getCurrentUser();
$settingId = isset($_POST['setting_id']) && $_POST['setting_id'] !== '' ? intval($_POST['setting_id']) : 0;
$isEdit = $settingId > 0;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Validate required fields
    $settingKey = isset($_POST['setting_key']) ? trim($_POST['setting_key']) : '';
    $settingValue = isset($_POST['setting_value']) ? trim($_POST['setting_value']) : '';
    $settingType = isset($_POST['setting_type']) ? trim($_POST['setting_type']) : 'string';
    
    if (empty($settingKey)) {
        echo json_encode(['success' => false, 'error' => 'Setting key is required']);
        exit();
    }
    
    // Validate setting key format (lowercase, numbers, underscores only)
    if (!preg_match('/^[a-z0-9_]+$/', $settingKey)) {
        echo json_encode(['success' => false, 'error' => 'Setting key must contain only lowercase letters, numbers, and underscores']);
        exit();
    }
    
    // Get other fields
    $category = isset($_POST['category']) && $_POST['category'] !== '' ? trim($_POST['category']) : null;
    $description = isset($_POST['description']) && $_POST['description'] !== '' ? trim($_POST['description']) : null;
    $isPublic = isset($_POST['is_public']) ? intval($_POST['is_public']) : 0;
    
    // Validate value based on type
    if ($settingType === 'json' && !empty($settingValue)) {
        json_decode($settingValue);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON format']);
            exit();
        }
    } elseif ($settingType === 'integer' && !empty($settingValue)) {
        if (!is_numeric($settingValue) || $settingValue != intval($settingValue)) {
            echo json_encode(['success' => false, 'error' => 'Invalid integer value']);
            exit();
        }
    } elseif ($settingType === 'decimal' && !empty($settingValue)) {
        if (!is_numeric($settingValue)) {
            echo json_encode(['success' => false, 'error' => 'Invalid decimal value']);
            exit();
        }
    } elseif ($settingType === 'boolean' && !empty($settingValue)) {
        $lowerValue = strtolower(trim($settingValue));
        if (!in_array($lowerValue, ['true', 'false', '1', '0', 'yes', 'no'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid boolean value. Use true, false, 1, 0, yes, or no']);
            exit();
        }
    }
    
    if ($isEdit) {
        // Update existing setting
        $sql = "UPDATE system_settings SET
                setting_value = ?, setting_type = ?, category = ?,
                description = ?, is_public = ?, updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $settingValue, $settingType, $category,
            $description, $isPublic, $currentUser['id'],
            $settingId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully',
            'setting_id' => $settingId
        ]);
    } else {
        // Check if setting key already exists
        $checkStmt = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $checkStmt->execute([$settingKey]);
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'Setting key already exists']);
            exit();
        }
        
        // Insert new setting
        $sql = "INSERT INTO system_settings 
                (setting_key, setting_value, setting_type, category, description, is_public, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $settingKey, $settingValue, $settingType, $category,
            $description, $isPublic, $currentUser['id']
        ]);
        
        $newSettingId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting created successfully',
            'setting_id' => $newSettingId
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error saving setting: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error saving setting: ' . $e->getMessage()
    ]);
}
?>
