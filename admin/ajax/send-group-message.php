<?php
/**
 * Send Group Message AJAX Handler - Admin
 * Handles sending messages to group conversations
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = getCurrentUser();

// Validate admin role
if (!in_array($currentUser['role'], ['owner', 'administrator'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $groupId = (int)($_POST['group_id'] ?? 0);
    $messageText = trim($_POST['message_text'] ?? '');
    
    if ($groupId == 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
        exit();
    }
    
    // Verify user is member of group
    $stmt = $pdo->prepare("SELECT * FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $currentUser['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are not a member of this group']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert message
    $messageType = (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) ? 'file' : 'text';
    
    $stmt = $pdo->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([$groupId, $currentUser['id'], $messageText, $messageType]);
    $messageId = $pdo->lastInsertId();
    
    // Insert message status for all group members
    $stmt = $pdo->prepare("SELECT user_id FROM group_participants WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $statusStmt = $pdo->prepare("INSERT INTO group_message_status (message_id, user_id, status) VALUES (?, ?, ?)");
    foreach ($members as $memberId) {
        $status = ($memberId == $currentUser['id']) ? 'sent' : 'sent';
        $statusStmt->execute([$messageId, $memberId, $status]);
    }
    
    // Handle file uploads
    $uploadedFiles = [];
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $uploadDir = '../../uploads/group_attachments/';
        
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
                
                if ($fileSize > 10 * 1024 * 1024) {
                    continue;
                }
                
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $uniqueName = uniqid() . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $uniqueName;
                $relativePath = 'uploads/group_attachments/' . $uniqueName;
                
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    $fileType = $mimeType;
                    
                    $stmt = $pdo->prepare("INSERT INTO group_message_attachments 
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

