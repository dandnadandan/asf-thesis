<?php
/**
 * News & Announcements Page for ASF Surveillance System
 * Displays list of all published news articles and individual article details
 */

require_once 'config/database.php';

// Get query parameters
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9; // Articles per page
$offset = ($page - 1) * $per_page;

$pageTitle = 'News & Announcements';
$article = null;
$news_articles = [];
$total_articles = 0;
$categories = ['news', 'announcement', 'guideline', 'update', 'alert'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!empty($slug)) {
        // Single article view - fetch article by slug
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as author_name,
                   u.email as author_email
            FROM news_articles n
            LEFT JOIN user_accounts u ON n.author_id = u.id
            WHERE n.slug = ?
            AND n.status = 'published'
            AND (n.published_at IS NULL OR n.published_at <= NOW())
        ");
        $stmt->execute([$slug]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($article) {
            // Update view count
            $stmt = $pdo->prepare("UPDATE news_articles SET views_count = views_count + 1 WHERE id = ?");
            $stmt->execute([$article['id']]);
            $article['views_count'] = ($article['views_count'] ?? 0) + 1;
            
            $pageTitle = htmlspecialchars($article['title']);
            
            // Fetch related articles (same category, exclude current)
            $stmt = $pdo->prepare("
                SELECT n.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM news_articles n
                LEFT JOIN user_accounts u ON n.author_id = u.id
                WHERE n.category = ?
                AND n.id != ?
                AND n.status = 'published'
                AND (n.published_at IS NULL OR n.published_at <= NOW())
                ORDER BY n.published_at DESC, n.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$article['category'], $article['id']]);
            $related_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Article not found
            http_response_code(404);
        }
    } else {
        // List view - fetch all published articles
        $where_conditions = ["n.status = 'published'", "(n.published_at IS NULL OR n.published_at <= NOW())"];
        $params = [];
        
        if (!empty($category) && in_array($category, $categories)) {
            $where_conditions[] = "n.category = ?";
            $params[] = $category;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        // Get total count for pagination
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM news_articles n
            $where_clause
        ");
        $stmt->execute($params);
        $total_articles = $stmt->fetch()['total'] ?? 0;
        $total_pages = ceil($total_articles / $per_page);
        
        // Fetch articles with pagination
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as author_name
            FROM news_articles n
            LEFT JOIN user_accounts u ON n.author_id = u.id
            $where_clause
            ORDER BY n.published_at DESC, n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $per_page;
        $params[] = $offset;
        $stmt->execute($params);
        $news_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get category counts for filter
        $stmt = $pdo->query("
            SELECT category, COUNT(*) as count
            FROM news_articles
            WHERE status = 'published'
            AND (published_at IS NULL OR published_at <= NOW())
            GROUP BY category
        ");
        $category_counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $category_counts[$row['category']] = $row['count'];
        }
    }
    
    $database->closeConnection();
} catch (Exception $e) {
    error_log("Error loading news articles: " . $e->getMessage());
    if (!empty($slug)) {
        http_response_code(500);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    /* News Page Styles */
    .news-page-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 40px 20px;
      border-radius: 15px;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .news-page-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    
    .category-filter {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 30px;
      justify-content: center;
    }
    
    .category-btn {
      padding: 8px 20px;
      border: 2px solid #dee2e6;
      background: white;
      border-radius: 25px;
      text-decoration: none;
      color: #495057;
      font-weight: 500;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .category-btn:hover,
    .category-btn.active {
      border-color: #0d6efd;
      background: #0d6efd;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
    }
    
    .news-card {
      background: #fff;
      border-radius: 15px;
      padding: 0;
      height: 100%;
      transition: all 0.3s ease;
      border: 1px solid #e9ecef;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }
    
    .news-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      border-color: #0d6efd;
    }
    
    .news-card .news-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    
    .news-card .news-body {
      padding: 20px;
    }
    
    .news-card .news-category {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    
    .news-card .news-category.news {
      background-color: #e3f2fd;
      color: #1976d2;
    }
    
    .news-card .news-category.announcement {
      background-color: #fff3e0;
      color: #f57c00;
    }
    
    .news-card .news-category.alert {
      background-color: #ffebee;
      color: #c62828;
    }
    
    .news-card .news-category.guideline {
      background-color: #e8f5e9;
      color: #388e3c;
    }
    
    .news-card .news-category.update {
      background-color: #f3e5f5;
      color: #7b1fa2;
    }
    
    .news-card .news-title {
      color: #2c3e50;
      font-weight: 700;
      font-size: 1.2rem;
      margin-bottom: 10px;
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .news-card .news-title a {
      text-decoration: none;
      color: inherit;
      transition: color 0.3s ease;
    }
    
    .news-card:hover .news-title a {
      color: #0d6efd;
    }
    
    .news-card .news-excerpt {
      color: #6c757d;
      font-size: 0.95rem;
      line-height: 1.6;
      margin-bottom: 15px;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .news-card .news-meta {
      color: #adb5bd;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 15px;
      border-top: 1px solid #e9ecef;
      padding-top: 12px;
    }
    
    .news-card .news-meta i {
      margin-right: 5px;
    }
    
    /* Article Detail Styles */
    .article-header {
      margin-bottom: 30px;
    }
    
    .article-header .article-category {
      display: inline-block;
      padding: 6px 16px;
      border-radius: 25px;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 15px;
    }
    
    .article-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 20px;
      line-height: 1.3;
    }
    
    .article-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      color: #6c757d;
      font-size: 0.95rem;
      padding-bottom: 20px;
      border-bottom: 2px solid #e9ecef;
      margin-bottom: 30px;
    }
    
    .article-meta span {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .article-image {
      width: 100%;
      max-height: 500px;
      object-fit: cover;
      border-radius: 15px;
      margin-bottom: 30px;
    }
    
    .article-content {
      font-size: 1.1rem;
      line-height: 1.8;
      color: #495057;
      margin-bottom: 40px;
    }
    
    .article-content h2 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #2c3e50;
      margin-top: 30px;
      margin-bottom: 15px;
    }
    
    .article-content h3 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #2c3e50;
      margin-top: 25px;
      margin-bottom: 12px;
    }
    
    .article-content h4 {
      font-size: 1.3rem;
      font-weight: 600;
      color: #2c3e50;
      margin-top: 20px;
      margin-bottom: 10px;
    }
    
    .article-content ul, .article-content ol {
      margin-bottom: 20px;
      padding-left: 30px;
    }
    
    .article-content li {
      margin-bottom: 10px;
    }
    
    .article-content table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }
    
    .article-content table th,
    .article-content table td {
      padding: 12px;
      text-align: left;
      border: 1px solid #dee2e6;
    }
    
    .article-content table th {
      background-color: #f8f9fa;
      font-weight: 600;
    }
    
    .pagination {
      justify-content: center;
      margin-top: 40px;
    }
    
    .pagination .page-link {
      color: #0d6efd;
      border-color: #dee2e6;
    }
    
    .pagination .page-item.active .page-link {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }
    
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #0d6efd;
      text-decoration: none;
      margin-bottom: 20px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .back-link:hover {
      color: #0b5ed7;
      transform: translateX(-5px);
    }
    
    .related-articles-section {
      margin-top: 50px;
      padding-top: 40px;
      border-top: 2px solid #e9ecef;
    }
    
    @media (max-width: 768px) {
      .news-page-header h1 {
        font-size: 2rem;
      }
      
      .article-header h1 {
        font-size: 1.8rem;
      }
      
      .category-filter {
        justify-content: flex-start;
      }
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">
    <section class="section">
      <div class="container-fluid">
        
        <?php if (!empty($slug) && $article): ?>
          <!-- Single Article View -->
          <div class="row">
            <div class="col-lg-8 mx-auto">
              <a href="news.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to All News
              </a>
              
              <div class="article-header">
                <span class="article-category <?php echo htmlspecialchars($article['category']); ?>">
                  <?php echo ucfirst(htmlspecialchars($article['category'])); ?>
                </span>
                
                <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                
                <div class="article-meta">
                  <?php if (!empty($article['published_at'])): ?>
                    <span>
                      <i class="bi bi-calendar3"></i>
                      <?php echo date('F d, Y', strtotime($article['published_at'])); ?>
                    </span>
                  <?php else: ?>
                    <span>
                      <i class="bi bi-calendar3"></i>
                      <?php echo date('F d, Y', strtotime($article['created_at'])); ?>
                    </span>
                  <?php endif; ?>
                  
                  <?php if (!empty($article['author_name'])): ?>
                    <span>
                      <i class="bi bi-person"></i>
                      <?php echo htmlspecialchars($article['author_name']); ?>
                    </span>
                  <?php endif; ?>
                  
                  <span>
                    <i class="bi bi-eye"></i>
                    <?php echo number_format($article['views_count'] ?? 0); ?> views
                  </span>
                </div>
              </div>
              
              <?php if (!empty($article['featured_image'])): ?>
                <img src="<?php echo htmlspecialchars($article['featured_image']); ?>" 
                     alt="<?php echo htmlspecialchars($article['title']); ?>" 
                     class="article-image"
                     onerror="this.style.display='none';">
              <?php endif; ?>
              
              <div class="article-content">
                <?php echo $article['content']; ?>
              </div>
              
              <?php if (!empty($related_articles)): ?>
                <div class="related-articles-section">
                  <h3 class="mb-4">Related Articles</h3>
                  <div class="row">
                    <?php foreach ($related_articles as $related): ?>
                      <div class="col-md-4 mb-4">
                        <div class="news-card">
                          <?php if (!empty($related['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                 class="news-image"
                                 onerror="this.src='uploads/contents/pigs_1.png';">
                          <?php else: ?>
                            <img src="uploads/contents/pigs_1.png" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                 class="news-image">
                          <?php endif; ?>
                          
                          <div class="news-body">
                            <span class="news-category <?php echo htmlspecialchars($related['category']); ?>">
                              <?php echo ucfirst(htmlspecialchars($related['category'])); ?>
                            </span>
                            
                            <h5 class="news-title">
                              <a href="news.php?slug=<?php echo htmlspecialchars($related['slug']); ?>">
                                <?php echo htmlspecialchars($related['title']); ?>
                              </a>
                            </h5>
                            
                            <?php if (!empty($related['excerpt'])): ?>
                              <p class="news-excerpt"><?php echo htmlspecialchars($related['excerpt']); ?></p>
                            <?php endif; ?>
                            
                            <div class="news-meta">
                              <span>
                                <i class="bi bi-calendar3"></i>
                                <?php echo date('M d, Y', strtotime($related['published_at'] ?? $related['created_at'])); ?>
                              </span>
                              
                              <span>
                                <i class="bi bi-eye"></i>
                                <?php echo number_format($related['views_count'] ?? 0); ?>
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
        <?php elseif (!empty($slug) && !$article): ?>
          <!-- Article Not Found -->
          <div class="row">
            <div class="col-lg-8 mx-auto text-center py-5">
              <i class="bi bi-exclamation-triangle" style="font-size: 4rem; color: #ffc107;"></i>
              <h2 class="mt-4">Article Not Found</h2>
              <p class="text-muted">The article you're looking for doesn't exist or is no longer available.</p>
              <a href="news.php" class="btn btn-primary mt-3">
                <i class="bi bi-arrow-left me-2"></i>Back to News
              </a>
            </div>
          </div>
          
        <?php else: ?>
          <!-- News List View -->
          <div class="news-page-header">
            <h1><i class="bi bi-newspaper me-2"></i>News & Announcements</h1>
            <p>Stay informed with the latest updates, announcements, and news about ASF surveillance in CALABARZON</p>
          </div>
          
          <!-- Category Filter -->
          <div class="category-filter">
            <a href="news.php" class="category-btn <?php echo empty($category) ? 'active' : ''; ?>">
              <i class="bi bi-grid"></i> All
              <?php if (isset($category_counts) && !empty($category_counts)): ?>
                <span class="badge bg-secondary"><?php echo array_sum($category_counts); ?></span>
              <?php endif; ?>
            </a>
            
            <?php foreach ($categories as $cat): ?>
              <?php if (isset($category_counts[$cat]) && $category_counts[$cat] > 0): ?>
                <a href="news.php?category=<?php echo urlencode($cat); ?>" 
                   class="category-btn <?php echo $category === $cat ? 'active' : ''; ?>">
                  <i class="bi bi-tag"></i> <?php echo ucfirst($cat); ?>
                  <span class="badge bg-secondary"><?php echo $category_counts[$cat]; ?></span>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          
          <?php if (empty($news_articles)): ?>
            <!-- No Articles -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-body text-center py-5">
                    <i class="bi bi-newspaper" style="font-size: 4rem; color: #ddd;"></i>
                    <h3 class="mt-4">No Articles Found</h3>
                    <p class="text-muted">
                      <?php if (!empty($category)): ?>
                        No <?php echo htmlspecialchars($category); ?> articles available at this time.
                      <?php else: ?>
                        No news articles available at this time.
                      <?php endif; ?>
                    </p>
                    <?php if (!empty($category)): ?>
                      <a href="news.php" class="btn btn-primary mt-3">
                        View All News
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- Articles Grid -->
            <div class="row">
              <?php foreach ($news_articles as $article): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="news-card">
                    <?php if (!empty($article['featured_image'])): ?>
                      <img src="<?php echo htmlspecialchars($article['featured_image']); ?>" 
                           alt="<?php echo htmlspecialchars($article['title']); ?>" 
                           class="news-image"
                           onerror="this.src='uploads/contents/pigs_1.png';">
                    <?php else: ?>
                      <img src="uploads/contents/pigs_1.png" 
                           alt="<?php echo htmlspecialchars($article['title']); ?>" 
                           class="news-image">
                    <?php endif; ?>
                    
                    <div class="news-body">
                      <span class="news-category <?php echo htmlspecialchars($article['category']); ?>">
                        <?php echo ucfirst(htmlspecialchars($article['category'])); ?>
                      </span>
                      
                      <h5 class="news-title">
                        <a href="news.php?slug=<?php echo htmlspecialchars($article['slug']); ?>">
                          <?php echo htmlspecialchars($article['title']); ?>
                        </a>
                      </h5>
                      
                      <?php if (!empty($article['excerpt'])): ?>
                        <p class="news-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                      <?php elseif (!empty($article['content'])): ?>
                        <p class="news-excerpt">
                          <?php 
                            $excerpt = strip_tags($article['content']);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 150) . (mb_strlen($excerpt) > 150 ? '...' : ''));
                          ?>
                        </p>
                      <?php endif; ?>
                      
                      <div class="news-meta">
                        <?php if (!empty($article['published_at'])): ?>
                          <span>
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('M d, Y', strtotime($article['published_at'])); ?>
                          </span>
                        <?php else: ?>
                          <span>
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('M d, Y', strtotime($article['created_at'])); ?>
                          </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($article['author_name'])): ?>
                          <span>
                            <i class="bi bi-person"></i>
                            <?php echo htmlspecialchars($article['author_name']); ?>
                          </span>
                        <?php endif; ?>
                        
                        <span>
                          <i class="bi bi-eye"></i>
                          <?php echo number_format($article['views_count'] ?? 0); ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
              <nav aria-label="News pagination">
                <ul class="pagination">
                  <?php if ($page > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="news.php?page=<?php echo $page - 1; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                      </a>
                    </li>
                  <?php endif; ?>
                  
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);
                  
                  if ($start_page > 1): ?>
                    <li class="page-item"><a class="page-link" href="news.php?page=1<?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?>">1</a></li>
                    <?php if ($start_page > 2): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="news.php?page=<?php echo $i; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?>">
                        <?php echo $i; ?>
                      </a>
                    </li>
                  <?php endfor; ?>
                  
                  <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="news.php?page=<?php echo $total_pages; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?>"><?php echo $total_pages; ?></a></li>
                  <?php endif; ?>
                  
                  <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                      <a class="page-link" href="news.php?page=<?php echo $page + 1; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?>">
                        Next <i class="bi bi-chevron-right"></i>
                      </a>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
        
      </div>
    </section>
  </main>

  <?php include 'includes/footer.php'; ?>

</body>

</html>
