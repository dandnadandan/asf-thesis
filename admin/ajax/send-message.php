<?php
/**
 * Send Message AJAX Handler - Admin Side
 * Handles sending messages from admin to any user
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = getCurrentUser();

// Validate admin roles
if (!in_array($currentUser['role'], ['administrator', 'administrative staff', 'owner'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $conversationId = $_POST['conversation_id'] ?? '';
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $messageText = trim($_POST['message_text'] ?? '');
    
    if (empty($conversationId) || $receiverId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }
    
    // Admin can message any user - no assignment check needed
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert message
    $messageType = (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) ? 'file' : 'text';
    
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text, message_type) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$conversationId, $currentUser['id'], $receiverId, $messageText, $messageType]);
    $messageId = $pdo->lastInsertId();
    
    // Insert message status for sender
    $stmt = $pdo->prepare("INSERT INTO message_status (message_id, user_id, status) VALUES (?, ?, 'sent')");
    $stmt->execute([$messageId, $currentUser['id']]);
    
    // Insert message status for receiver
    $stmt = $pdo->prepare("INSERT INTO message_status (message_id, user_id, status) VALUES (?, ?, 'sent')");
    $stmt->execute([$messageId, $receiverId]);
    
    // Handle file uploads
    $uploadedFiles = [];
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $uploadDir = '../../uploads/chat_attachments/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileCount = count($_FILES['files']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = basename($_FILES['files']['name'][$i]);
                $fileSize = $_FILES['files']['size'][$i];
                $tmpName = $_FILES['files']['tmp_name'][$i];
                $mimeType = mime_content_type($tmpName);
                
                // Skip files larger than 10MB
                if ($fileSize > 10 * 1024 * 1024) {
                    continue;
                }
                
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $uniqueName = uniqid() . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $uniqueName;
                $relativePath = 'uploads/chat_attachments/' . $uniqueName;
                
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    $fileType = $mimeType;
                    
                    $stmt = $pdo->prepare("INSERT INTO message_attachments 
                                         (message_id, file_name, file_path, file_type, file_size, mime_type) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$messageId, $originalName, $relativePath, $fileType, $fileSize, $mimeType]);
                    
                    $uploadedFiles[] = [
                        'id' => $pdo->lastInsertId(),
                        'file_name' => $originalName,
                        'file_path' => $relativePath,
                        'file_type' => $fileType,
                        'file_size' => $fileSize
                    ];
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'attachments' => $uploadedFiles
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

