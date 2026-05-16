<?php
/**
 * AJAX endpoint to toggle article status (draft/published/archived)
 */

header('Content-Type: application/json');

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if session is valid
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Require administrator role
if (!canManageNews()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$articleId = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($articleId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit();
}

if (!in_array($newStatus, ['draft', 'published', 'archived'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Set published_at if status is published
    $publishedAt = null;
    if ($newStatus === 'published') {
        $checkStmt = $pdo->prepare("SELECT published_at FROM news_articles WHERE id = ?");
        $checkStmt->execute([$articleId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['published_at']) {
            $publishedAt = $existing['published_at'];
        } else {
            $publishedAt = date('Y-m-d H:i:s');
        }
    }
    
    $sql = "UPDATE news_articles SET 
            status = ?, published_at = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$newStatus, $publishedAt, $articleId]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Article status updated successfully',
            'status' => $newStatus
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No changes made'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error updating article status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
