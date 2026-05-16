<?php
/**
 * AJAX endpoint to get details of a specific news article
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

$articleId = isset($_GET['article_id']) ? intval($_GET['article_id']) : 0;

if ($articleId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT na.*, 
            CONCAT(ua.first_name, ' ', ua.last_name) as author_name,
            ua.email as author_email
            FROM news_articles na
            LEFT JOIN user_accounts ua ON na.author_id = ua.id
            WHERE na.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        echo json_encode(['success' => false, 'error' => 'Article not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'article' => $article
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching article details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching article details: ' . $e->getMessage()
    ]);
}
?>
