<?php
/**
 * Content Management for ASF Surveillance System
 * Allows administrators to manage homepage content dynamically
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can manage content
requireRole(['administrator'], '../unauthorized.php');

// Additional RBAC check
if (!canManageContent()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Content Management';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'save_content') {
            $content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
            $content_type = trim($_POST['content_type']);
            $title = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $badge_text = trim($_POST['badge_text'] ?? '');
            $icon_class = trim($_POST['icon_class'] ?? '');
            $image_path = trim($_POST['image_path'] ?? '');
            $content_order = isset($_POST['content_order']) ? (int)$_POST['content_order'] : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($content_id > 0) {
                // Update existing content
                $stmt = $pdo->prepare("UPDATE homepage_content SET 
                    content_type = :content_type,
                    title = :title,
                    subtitle = :subtitle,
                    description = :description,
                    badge_text = :badge_text,
                    icon_class = :icon_class,
                    image_path = :image_path,
                    content_order = :content_order,
                    is_active = :is_active,
                    updated_by = :updated_by,
                    updated_at = NOW()
                    WHERE id = :content_id");
                
                $stmt->bindParam(':content_id', $content_id, PDO::PARAM_INT);
                $stmt->bindParam(':content_type', $content_type);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':subtitle', $subtitle);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':badge_text', $badge_text);
                $stmt->bindParam(':icon_class', $icon_class);
                $stmt->bindParam(':image_path', $image_path);
                $stmt->bindParam(':content_order', $content_order, PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':updated_by', $currentUser['id'], PDO::PARAM_INT);
                
                $stmt->execute();
                $success_message = "Content updated successfully!";
            } else {
                // Insert new content
                $stmt = $pdo->prepare("INSERT INTO homepage_content 
                    (content_type, title, subtitle, description, badge_text, icon_class, image_path, content_order, is_active, created_by, updated_by)
                    VALUES (:content_type, :title, :subtitle, :description, :badge_text, :icon_class, :image_path, :content_order, :is_active, :created_by, :updated_by)");
                
                $stmt->bindParam(':content_type', $content_type);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':subtitle', $subtitle);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':badge_text', $badge_text);
                $stmt->bindParam(':icon_class', $icon_class);
                $stmt->bindParam(':image_path', $image_path);
                $stmt->bindParam(':content_order', $content_order, PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':created_by', $currentUser['id'], PDO::PARAM_INT);
                $stmt->bindParam(':updated_by', $currentUser['id'], PDO::PARAM_INT);
                
                $stmt->execute();
                $success_message = "Content added successfully!";
            }
        } elseif ($_POST['action'] === 'delete_content') {
            $content_id = (int)$_POST['content_id'];
            
            $stmt = $pdo->prepare("DELETE FROM homepage_content WHERE id = :content_id");
            $stmt->bindParam(':content_id', $content_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $success_message = "Content deleted successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch all content
try {
    $stmt = $pdo->query("SELECT * FROM homepage_content ORDER BY content_type, content_order, id");
    $all_content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group content by type
    $content_by_type = [
        'page_header' => [],
        'carousel_slide' => [],
        'feature_card' => [],
        'about_section' => []
    ];
    
    foreach ($all_content as $content) {
        $content_by_type[$content['content_type']][] = $content;
    }
} catch (Exception $e) {
    $error_message = "Error loading content: " . $e->getMessage();
    $content_by_type = [
        'page_header' => [],
        'carousel_slide' => [],
        'feature_card' => [],
        'about_section' => []
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .content-section {
      margin-bottom: 2rem;
      padding: 1.5rem;
      background: #f8f9fa;
      border-radius: 10px;
    }
    
    .content-item {
      background: white;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
      border: 1px solid #dee2e6;
      transition: all 0.3s ease;
    }
    
    .content-item:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-color: #0d6efd;
    }
    
    .content-item-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 1rem;
    }
    
    .content-item-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-right: 10px;
    }
    
    .badge-carousel { background: #dc3545; color: white; }
    .badge-feature { background: #0d6efd; color: white; }
    .badge-header { background: #28a745; color: white; }
    .badge-about { background: #ffc107; color: #000; }
    
    .form-section {
      background: white;
      padding: 1.5rem;
      border-radius: 10px;
      margin-bottom: 2rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Content Management</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Content Management</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      
      <?php if ($success_message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>
      
      <?php if ($error_message): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>
      
      <div class="row">
        
        <!-- Add/Edit Form -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Add/Edit Content</h5>
              
              <form method="POST" id="contentForm">
                <input type="hidden" name="action" value="save_content">
                <input type="hidden" name="content_id" id="content_id" value="">
                
                <div class="mb-3">
                  <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                  <select class="form-select" id="content_type" name="content_type" required>
                    <option value="">Select Type</option>
                    <option value="page_header">Page Header</option>
                    <option value="carousel_slide">Carousel Slide</option>
                    <option value="feature_card">Feature Card</option>
                    <option value="about_section">About Section</option>
                  </select>
                </div>
                
                <div class="mb-3">
                  <label for="title" class="form-label">Title</label>
                  <input type="text" class="form-control" id="title" name="title">
                </div>
                
                <div class="mb-3">
                  <label for="subtitle" class="form-label">Subtitle</label>
                  <input type="text" class="form-control" id="subtitle" name="subtitle">
                </div>
                
                <div class="mb-3">
                  <label for="description" class="form-label">Description</label>
                  <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                </div>
                
                <div class="mb-3" id="badge_text_group">
                  <label for="badge_text" class="form-label">Badge Text</label>
                  <input type="text" class="form-control" id="badge_text" name="badge_text" placeholder="e.g., WOAH Listed Disease">
                </div>
                
                <div class="mb-3" id="icon_class_group">
                  <label for="icon_class" class="form-label">Icon Class (Bootstrap Icons)</label>
                  <input type="text" class="form-control" id="icon_class" name="icon_class" placeholder="e.g., bi-cpu-fill">
                  <small class="text-muted">Visit <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a> for available icons</small>
                </div>
                
                <div class="mb-3" id="image_path_group">
                  <label for="image_path" class="form-label">Image Path</label>
                  <input type="text" class="form-control" id="image_path" name="image_path" placeholder="e.g., uploads/contents/pigs_1.png">
                </div>
                
                <div class="mb-3">
                  <label for="content_order" class="form-label">Display Order</label>
                  <input type="number" class="form-control" id="content_order" name="content_order" value="0" min="0">
                </div>
                
                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                    <label class="form-check-label" for="is_active">
                      Active (visible on homepage)
                    </label>
                  </div>
                </div>
                
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Content
                  </button>
                  <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Content List -->
        <div class="col-lg-8">
          
          <!-- Page Header Content -->
          <div class="content-section">
            <h5 class="mb-3">
              <i class="bi bi-heading me-2"></i>Page Header
              <button class="btn btn-sm btn-primary float-end" onclick="addNewContent('page_header')">
                <i class="bi bi-plus-circle me-1"></i>Add New
              </button>
            </h5>
            
            <?php foreach ($content_by_type['page_header'] as $content): ?>
            <div class="content-item">
              <div class="content-item-header">
                <div>
                  <span class="content-item-badge badge-header">Header</span>
                  <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                  <?php if (!$content['is_active']): ?>
                    <span class="badge bg-secondary ms-2">Inactive</span>
                  <?php endif; ?>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-primary" onclick="editContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
              <p class="text-muted mb-1"><strong>Subtitle:</strong> <?php echo htmlspecialchars($content['subtitle']); ?></p>
              <small class="text-muted">Order: <?php echo $content['content_order']; ?></small>
            </div>
            <?php endforeach; ?>
          </div>
          
          <!-- Carousel Slides -->
          <div class="content-section">
            <h5 class="mb-3">
              <i class="bi bi-images me-2"></i>Carousel Slides
              <button class="btn btn-sm btn-primary float-end" onclick="addNewContent('carousel_slide')">
                <i class="bi bi-plus-circle me-1"></i>Add New
              </button>
            </h5>
            
            <?php foreach ($content_by_type['carousel_slide'] as $content): ?>
            <div class="content-item">
              <div class="content-item-header">
                <div>
                  <span class="content-item-badge badge-carousel">Carousel</span>
                  <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                  <?php if ($content['badge_text']): ?>
                    <span class="badge bg-danger ms-2"><?php echo htmlspecialchars($content['badge_text']); ?></span>
                  <?php endif; ?>
                  <?php if (!$content['is_active']): ?>
                    <span class="badge bg-secondary ms-2">Inactive</span>
                  <?php endif; ?>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-primary" onclick="editContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
              <p class="text-muted mb-1"><?php echo htmlspecialchars(substr($content['description'], 0, 150)) . '...'; ?></p>
              <small class="text-muted">Image: <?php echo htmlspecialchars($content['image_path']); ?> | Order: <?php echo $content['content_order']; ?></small>
            </div>
            <?php endforeach; ?>
          </div>
          
          <!-- Feature Cards -->
          <div class="content-section">
            <h5 class="mb-3">
              <i class="bi bi-grid-3x3-gap me-2"></i>Feature Cards
              <button class="btn btn-sm btn-primary float-end" onclick="addNewContent('feature_card')">
                <i class="bi bi-plus-circle me-1"></i>Add New
              </button>
            </h5>
            
            <?php foreach ($content_by_type['feature_card'] as $content): ?>
            <div class="content-item">
              <div class="content-item-header">
                <div>
                  <span class="content-item-badge badge-feature">Feature</span>
                  <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                  <?php if ($content['icon_class']): ?>
                    <i class="<?php echo htmlspecialchars($content['icon_class']); ?> ms-2"></i>
                  <?php endif; ?>
                  <?php if (!$content['is_active']): ?>
                    <span class="badge bg-secondary ms-2">Inactive</span>
                  <?php endif; ?>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-primary" onclick="editContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
              <p class="text-muted mb-1"><?php echo htmlspecialchars($content['description']); ?></p>
              <small class="text-muted">Order: <?php echo $content['content_order']; ?></small>
            </div>
            <?php endforeach; ?>
          </div>
          
          <!-- About Section -->
          <div class="content-section">
            <h5 class="mb-3">
              <i class="bi bi-info-circle me-2"></i>About Section
              <button class="btn btn-sm btn-primary float-end" onclick="addNewContent('about_section')">
                <i class="bi bi-plus-circle me-1"></i>Add New
              </button>
            </h5>
            
            <?php foreach ($content_by_type['about_section'] as $content): ?>
            <div class="content-item">
              <div class="content-item-header">
                <div>
                  <span class="content-item-badge badge-about">About</span>
                  <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                  <?php if (!$content['is_active']): ?>
                    <span class="badge bg-secondary ms-2">Inactive</span>
                  <?php endif; ?>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-primary" onclick="editContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteContent(<?php echo $content['id']; ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
              <p class="text-muted mb-1"><?php echo htmlspecialchars(substr($content['description'], 0, 200)) . '...'; ?></p>
              <small class="text-muted">Order: <?php echo $content['content_order']; ?></small>
            </div>
            <?php endforeach; ?>
          </div>
          
        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <script>
    const contentData = <?php echo json_encode($all_content); ?>;
    
    function addNewContent(contentType) {
      resetForm();
      document.getElementById('content_type').value = contentType;
      toggleFieldsByType(contentType);
      document.getElementById('contentForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    function editContent(contentId) {
      const content = contentData.find(c => c.id == contentId);
      if (!content) return;
      
      document.getElementById('content_id').value = content.id;
      document.getElementById('content_type').value = content.content_type;
      document.getElementById('title').value = content.title || '';
      document.getElementById('subtitle').value = content.subtitle || '';
      document.getElementById('description').value = content.description || '';
      document.getElementById('badge_text').value = content.badge_text || '';
      document.getElementById('icon_class').value = content.icon_class || '';
      document.getElementById('image_path').value = content.image_path || '';
      document.getElementById('content_order').value = content.content_order || 0;
      document.getElementById('is_active').checked = content.is_active == 1;
      
      toggleFieldsByType(content.content_type);
      document.getElementById('contentForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    function deleteContent(contentId) {
      if (!confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
        return;
      }
      
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="action" value="delete_content">
        <input type="hidden" name="content_id" value="${contentId}">
      `;
      document.body.appendChild(form);
      form.submit();
    }
    
    function resetForm() {
      document.getElementById('contentForm').reset();
      document.getElementById('content_id').value = '';
      document.getElementById('content_order').value = '0';
      document.getElementById('is_active').checked = true;
    }
    
    function toggleFieldsByType(contentType) {
      const badgeGroup = document.getElementById('badge_text_group');
      const iconGroup = document.getElementById('icon_class_group');
      const imageGroup = document.getElementById('image_path_group');
      
      // Show/hide fields based on content type
      if (contentType === 'carousel_slide') {
        badgeGroup.style.display = 'block';
        iconGroup.style.display = 'none';
        imageGroup.style.display = 'block';
      } else if (contentType === 'feature_card') {
        badgeGroup.style.display = 'none';
        iconGroup.style.display = 'block';
        imageGroup.style.display = 'none';
      } else if (contentType === 'page_header') {
        badgeGroup.style.display = 'none';
        iconGroup.style.display = 'none';
        imageGroup.style.display = 'none';
      } else if (contentType === 'about_section') {
        badgeGroup.style.display = 'none';
        iconGroup.style.display = 'none';
        imageGroup.style.display = 'none';
      } else {
        badgeGroup.style.display = 'block';
        iconGroup.style.display = 'block';
        imageGroup.style.display = 'block';
      }
    }
    
    // Toggle fields when content type changes
    document.getElementById('content_type').addEventListener('change', function() {
      toggleFieldsByType(this.value);
    });
  </script>

</body>

</html>
