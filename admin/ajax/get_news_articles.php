<?php
/**
 * AJAX endpoint to get list of news articles
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

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get filter parameters
    $categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : '';
    $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
    $searchQuery = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : '';
    
    // Build WHERE clause
    $conditions = [];
    $params = [];
    
    if (!empty($categoryFilter)) {
        $conditions[] = "na.category = ?";
        $params[] = $categoryFilter;
    }
    
    if (!empty($statusFilter)) {
        $conditions[] = "na.status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($searchQuery)) {
        $conditions[] = "(na.title LIKE ? OR na.excerpt LIKE ? OR na.content LIKE ?)";
        $searchParam = "%{$searchQuery}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get articles with author info
    $sql = "SELECT na.*, 
            CONCAT(ua.first_name, ' ', ua.last_name) as author_name,
            ua.email as author_email
            FROM news_articles na
            LEFT JOIN user_accounts ua ON na.author_id = ua.id
            {$whereClause}
            ORDER BY na.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'articles' => $articles
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching news articles: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching news articles: ' . $e->getMessage()
    ]);
}
?>
