<?php
/**
 * AJAX endpoint to delete a news article
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

if ($articleId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if article exists
    $checkStmt = $pdo->prepare("SELECT id, featured_image FROM news_articles WHERE id = ?");
    $checkStmt->execute([$articleId]);
    $article = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        echo json_encode(['success' => false, 'error' => 'Article not found']);
        exit();
    }
    
    // Delete featured image file if exists
    if (!empty($article['featured_image']) && file_exists('../../' . $article['featured_image'])) {
        @unlink('../../' . $article['featured_image']);
    }
    
    // Delete article
    $deleteStmt = $pdo->prepare("DELETE FROM news_articles WHERE id = ?");
    $deleteStmt->execute([$articleId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Article deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting article: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error deleting article: ' . $e->getMessage()
    ]);
}
?>
