<?php
/**
 * AJAX endpoint to save (add/update) a news article
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

$currentUser = getCurrentUser();
$articleId = isset($_POST['article_id']) && $_POST['article_id'] !== '' ? intval($_POST['article_id']) : 0;
$isEdit = $articleId > 0;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Validate required fields
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'news';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'draft';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit();
    }
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Content is required']);
        exit();
    }
    
    // Generate slug from title
    function generateSlug($title, $pdo, $excludeId = 0) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure slug is unique
        $originalSlug = $slug;
        $counter = 1;
        while (true) {
            $sql = "SELECT id FROM news_articles WHERE slug = ?";
            $params = [$slug];
            if ($excludeId > 0) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() == 0) {
                break;
            }
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
    
    $slug = generateSlug($title, $pdo, $articleId);
    
    // Get other fields
    $excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
    $featuredImage = isset($_POST['featured_image']) ? trim($_POST['featured_image']) : null;
    $metaKeywords = isset($_POST['meta_keywords']) ? trim($_POST['meta_keywords']) : null;
    $metaDescription = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : null;
    
    // Set published_at if status is published
    $publishedAt = null;
    if ($status === 'published') {
        if ($isEdit) {
            // Check if already published
            $checkStmt = $pdo->prepare("SELECT published_at FROM news_articles WHERE id = ?");
            $checkStmt->execute([$articleId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $publishedAt = $existing && $existing['published_at'] ? $existing['published_at'] : date('Y-m-d H:i:s');
        } else {
            $publishedAt = date('Y-m-d H:i:s');
        }
    }
    
    if ($isEdit) {
        // Update existing article
        $sql = "UPDATE news_articles SET
                title = ?, slug = ?, excerpt = ?, content = ?, 
                featured_image = ?, category = ?, status = ?, 
                published_at = ?, meta_keywords = ?, meta_description = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $slug, $excerpt, $content,
            $featuredImage, $category, $status,
            $publishedAt, $metaKeywords, $metaDescription,
            $articleId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Article updated successfully',
            'article_id' => $articleId
        ]);
    } else {
        // Insert new article
        $sql = "INSERT INTO news_articles 
                (title, slug, excerpt, content, featured_image, category, status, 
                 published_at, author_id, meta_keywords, meta_description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $slug, $excerpt, $content,
            $featuredImage, $category, $status,
            $publishedAt, $currentUser['id'],
            $metaKeywords, $metaDescription
        ]);
        
        $newArticleId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Article created successfully',
            'article_id' => $newArticleId
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error saving article: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error saving article: ' . $e->getMessage()
    ]);
}
?>
