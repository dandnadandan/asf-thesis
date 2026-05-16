<?php
/**
 * News & Announcements Management for ASF Surveillance System
 * Only administrators can access this page
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can manage news and announcements
requireRole(['administrator'], '../unauthorized.php');

// Additional RBAC check
if (!canManageNews()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'News & Announcements';

// Database connection for statistics
$database = new Database();
try {
    $pdo = $database->getConnection();
    
    // Get statistics
    $stats = [
        'total_articles' => 0,
        'published' => 0,
        'draft' => 0,
        'archived' => 0,
        'total_views' => 0
    ];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news_articles");
    $stats['total_articles'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news_articles WHERE status = 'published'");
    $stats['published'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news_articles WHERE status = 'draft'");
    $stats['draft'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news_articles WHERE status = 'archived'");
    $stats['archived'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(views_count) as total FROM news_articles");
    $result = $stmt->fetch();
    $stats['total_views'] = $result['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats = [
        'total_articles' => 0,
        'published' => 0,
        'draft' => 0,
        'archived' => 0,
        'total_views' => 0
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .featured-image-preview {
      max-width: 300px;
      max-height: 200px;
      border-radius: 8px;
      margin-top: 10px;
      border: 1px solid #dee2e6;
    }
    
    .article-excerpt {
      max-width: 400px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .status-badge {
      font-size: 0.75rem;
      padding: 0.35rem 0.65rem;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>News & Announcements</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">News & Announcements</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        
        <!-- Statistics Cards -->
        <div class="col-xl-3 col-md-6">
          <div class="card info-card">
            <div class="card-body">
              <h5 class="card-title">Total Articles</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                  <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['total_articles']); ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card info-card">
            <div class="card-body">
              <h5 class="card-title">Published</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                  <i class="bi bi-check-circle"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['published']); ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card info-card">
            <div class="card-body">
              <h5 class="card-title">Drafts</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                  <i class="bi bi-pencil-square"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['draft']); ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card info-card">
            <div class="card-body">
              <h5 class="card-title">Total Views</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                  <i class="bi bi-eye"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['total_views']); ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Articles Table -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Articles</h5>
                <button type="button" class="btn btn-primary" onclick="showAddModal()">
                  <i class="bi bi-plus-circle me-2"></i>Add New Article
                </button>
              </div>
              
              <!-- Filters -->
              <div class="row mb-3">
                <div class="col-md-3">
                  <select class="form-select form-select-sm" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="news">News</option>
                    <option value="announcement">Announcement</option>
                    <option value="guideline">Guideline</option>
                    <option value="update">Update</option>
                    <option value="alert">Alert</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <select class="form-select form-select-sm" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search articles...">
                </div>
              </div>
              
              <div class="table-responsive">
                <table class="table table-hover" id="articlesTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Title</th>
                      <th>Category</th>
                      <th>Status</th>
                      <th>Author</th>
                      <th>Views</th>
                      <th>Published</th>
                      <th>Created</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="articlesTableBody">
                    <tr>
                      <td colspan="9" class="text-center">
                        <i class="bi bi-arrow-clockwise spin"></i> Loading articles...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

  </main><!-- End #main -->

  <!-- Add/Edit Article Modal -->
  <div class="modal fade" id="articleModal" tabindex="-1" aria-labelledby="articleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="articleModalLabel">Add New Article</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="articleForm">
            <input type="hidden" id="articleId" name="article_id">
            
            <div class="row mb-3">
              <div class="col-md-8">
                <label for="articleTitle" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="articleTitle" name="title" required>
              </div>
              <div class="col-md-4">
                <label for="articleCategory" class="form-label">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="articleCategory" name="category" required>
                  <option value="news">News</option>
                  <option value="announcement">Announcement</option>
                  <option value="guideline">Guideline</option>
                  <option value="update">Update</option>
                  <option value="alert">Alert</option>
                </select>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="articleStatus" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="articleStatus" name="status" required>
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="articleExcerpt" class="form-label">Excerpt</label>
                <textarea class="form-control" id="articleExcerpt" name="excerpt" rows="2" placeholder="Brief summary of the article..."></textarea>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="articleContent" class="form-label">Content <span class="text-danger">*</span></label>
              <textarea class="form-control tinymce-editor" id="articleContent" name="content" rows="15"></textarea>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="featuredImageInput" class="form-label">Featured Image</label>
                <input type="file" class="form-control" id="featuredImageInput" accept="image/*">
                <input type="hidden" id="featuredImage" name="featured_image">
                <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF, WEBP</small>
                <div id="featuredImagePreview" class="mt-2"></div>
              </div>
              <div class="col-md-6">
                <button type="button" class="btn btn-sm btn-outline-primary mt-4" onclick="uploadFeaturedImage()">
                  <i class="bi bi-upload me-1"></i>Upload Image
                </button>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="metaKeywords" class="form-label">Meta Keywords</label>
                <input type="text" class="form-control" id="metaKeywords" name="meta_keywords" placeholder="keyword1, keyword2, keyword3">
              </div>
              <div class="col-md-6">
                <label for="metaDescription" class="form-label">Meta Description</label>
                <textarea class="form-control" id="metaDescription" name="meta_description" rows="2" placeholder="SEO description..."></textarea>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="saveArticleBtn" onclick="saveArticle(event)">
            <i class="bi bi-save me-2"></i>Save Article
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
    let articlesDataTable = null;
    let currentFilter = {
      category: '',
      status: '',
      search: ''
    };
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      loadArticles();
      
      // Initialize TinyMCE
      if (typeof tinymce !== 'undefined') {
        tinymce.init({
          selector: 'textarea.tinymce-editor',
          plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
          menubar: 'file edit view insert format tools table help',
          toolbar: "undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | align numlist bullist | link image | table media | lineheight outdent indent | forecolor backcolor removeformat | charmap emoticons | code fullscreen preview | save print | pagebreak anchor codesample",
          height: 400,
          image_advtab: true,
          autosave_ask_before_unload: true,
          autosave_interval: '30s',
          content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }'
        });
      }
      
      // Filter event listeners
      document.getElementById('categoryFilter').addEventListener('change', function() {
        currentFilter.category = this.value;
        loadArticles();
      });
      
      document.getElementById('statusFilter').addEventListener('change', function() {
        currentFilter.status = this.value;
        loadArticles();
      });
      
      document.getElementById('searchInput').addEventListener('input', debounce(function() {
        currentFilter.search = this.value;
        loadArticles();
      }, 500));
      
      // Reset form when modal is hidden
      const articleModal = document.getElementById('articleModal');
      articleModal.addEventListener('hidden.bs.modal', function() {
        resetForm();
      });
    });
    
    // Load articles from API
    function loadArticles() {
      const tbody = document.getElementById('articlesTableBody');
      tbody.innerHTML = '<tr><td colspan="9" class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading articles...</td></tr>';
      
      let url = 'ajax/get_news_articles.php';
      const params = new URLSearchParams();
      if (currentFilter.category) params.append('category', currentFilter.category);
      if (currentFilter.status) params.append('status', currentFilter.status);
      if (currentFilter.search) params.append('search', currentFilter.search);
      if (params.toString()) url += '?' + params.toString();
      
      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayArticles(data.articles);
          } else {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.error || 'Failed to load articles'}</td></tr>`;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Failed to load articles. Please try again.</td></tr>';
        });
    }
    
    // Display articles in table
    function displayArticles(articles) {
      const tbody = document.getElementById('articlesTableBody');
      
      if (articles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No articles found</td></tr>';
        return;
      }
      
      let html = '';
      articles.forEach(article => {
        const statusBadge = getStatusBadge(article.status);
        const categoryBadge = getCategoryBadge(article.category);
        const publishedDate = article.published_at ? new Date(article.published_at).toLocaleDateString() : '-';
        const createdDate = article.created_at ? new Date(article.created_at).toLocaleDateString() : '-';
        
        html += `
          <tr>
            <td>${article.id}</td>
            <td>
              <strong>${escapeHtml(article.title)}</strong>
              ${article.featured_image ? '<i class="bi bi-image ms-1 text-muted" title="Has featured image"></i>' : ''}
            </td>
            <td>${categoryBadge}</td>
            <td>${statusBadge}</td>
            <td>${escapeHtml(article.author_name || 'Unknown')}</td>
            <td>${article.views_count || 0}</td>
            <td>${publishedDate}</td>
            <td>${createdDate}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="editArticle(${article.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-success" onclick="toggleStatus(${article.id}, '${article.status}')" title="Toggle Status">
                  <i class="bi bi-${article.status === 'published' ? 'eye-slash' : 'eye'}"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="deleteArticle(${article.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      });
      
      tbody.innerHTML = html;
      
      // Initialize DataTable if not already done
      if (!articlesDataTable) {
        const table = document.getElementById('articlesTable');
        if (table) {
          articlesDataTable = new simpleDatatables.DataTable(table, {
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true
          });
        }
      } else {
        articlesDataTable.refresh();
      }
    }
    
    // Show add modal
    function showAddModal() {
      document.getElementById('articleModalLabel').textContent = 'Add New Article';
      document.getElementById('articleId').value = '';
      resetForm();
      const modal = new bootstrap.Modal(document.getElementById('articleModal'));
      modal.show();
    }
    
    // Edit article
    function editArticle(articleId) {
      fetch(`ajax/get_news_article_details.php?article_id=${articleId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const article = data.article;
            document.getElementById('articleModalLabel').textContent = 'Edit Article';
            document.getElementById('articleId').value = article.id;
            document.getElementById('articleTitle').value = article.title || '';
            document.getElementById('articleCategory').value = article.category || 'news';
            document.getElementById('articleStatus').value = article.status || 'draft';
            document.getElementById('articleExcerpt').value = article.excerpt || '';
            document.getElementById('featuredImage').value = article.featured_image || '';
            document.getElementById('metaKeywords').value = article.meta_keywords || '';
            document.getElementById('metaDescription').value = article.meta_description || '';
            
            // Set TinyMCE content
            if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
              tinymce.get('articleContent').setContent(article.content || '');
            } else {
              document.getElementById('articleContent').value = article.content || '';
            }
            
            // Show featured image preview
            if (article.featured_image) {
              document.getElementById('featuredImagePreview').innerHTML = `
                <img src="../${article.featured_image}" class="featured-image-preview" alt="Featured Image">
              `;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('articleModal'));
            modal.show();
          } else {
            alert('Error: ' + (data.error || 'Failed to load article details'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to load article details. Please try again.');
        });
    }
    
    // Save article
    function saveArticle(event) {
      // Prevent default if event is provided
      if (event) {
        event.preventDefault();
      }
      
      // Get TinyMCE content first (before validation)
      let content = '';
      if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
        content = tinymce.get('articleContent').getContent();
        // Sync content back to textarea to avoid validation issues
        document.getElementById('articleContent').value = content;
      } else {
        content = document.getElementById('articleContent').value;
      }
      
      // Validate required fields manually (TinyMCE hides textarea, so we can't use form.checkValidity)
      const title = document.getElementById('articleTitle').value.trim();
      if (!title) {
        alert('Please enter article title');
        document.getElementById('articleTitle').focus();
        return;
      }
      
      if (!content || content.trim() === '' || content.trim() === '<p></p>' || content.trim() === '<p><br></p>') {
        alert('Please enter article content');
        if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
          tinymce.get('articleContent').focus();
        } else {
          document.getElementById('articleContent').focus();
        }
        return;
      }
      
      // Validate other required fields
      const category = document.getElementById('articleCategory').value;
      const status = document.getElementById('articleStatus').value;
      
      if (!category) {
        alert('Please select a category');
        document.getElementById('articleCategory').focus();
        return;
      }
      
      if (!status) {
        alert('Please select a status');
        document.getElementById('articleStatus').focus();
        return;
      }
      
      // Get featured image value
      const featuredImageValue = document.getElementById('featuredImage').value || '';
      console.log('Featured image path:', featuredImageValue); // Debug log
      
      const formData = new FormData();
      formData.append('article_id', document.getElementById('articleId').value);
      formData.append('title', document.getElementById('articleTitle').value);
      formData.append('category', document.getElementById('articleCategory').value);
      formData.append('status', document.getElementById('articleStatus').value);
      formData.append('excerpt', document.getElementById('articleExcerpt').value);
      formData.append('content', content);
      formData.append('featured_image', featuredImageValue);
      formData.append('meta_keywords', document.getElementById('metaKeywords').value);
      formData.append('meta_description', document.getElementById('metaDescription').value);
      
      // Show loading state - get button by ID instead of event.target
      const saveBtn = document.querySelector('#articleModal .btn-primary');
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Saving...';
      
      let successShown = false; // Track if success alert was shown
      
      fetch('ajax/save_news_article.php', {
        method: 'POST',
        body: formData
      })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          // Restore button state first
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
          }
          
          if (data.success) {
            // Mark that success was shown
            successShown = true;
            
            // Show success message
            alert(data.message || 'Article saved successfully!');
            
            // Close modal and reload articles
            try {
              const modal = bootstrap.Modal.getInstance(document.getElementById('articleModal'));
              if (modal) {
                modal.hide();
              }
              
              // Reload articles list after a short delay to ensure modal is closed
              setTimeout(() => {
                loadArticles();
              }, 300);
            } catch (e) {
              console.error('Error closing modal or reloading articles:', e);
              // Still reload articles even if modal close fails
              loadArticles();
            }
          } else {
            alert('Error: ' + (data.error || 'Failed to save article'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Only show error alert if success wasn't already shown
          if (!successShown) {
            alert('Failed to save article. Please try again.');
            if (saveBtn) {
              saveBtn.disabled = false;
              saveBtn.innerHTML = originalText;
            }
          }
        });
    }
    
    // Delete article
    function deleteArticle(articleId) {
      if (!confirm('Are you sure you want to delete this article? This action cannot be undone.')) {
        return;
      }
      
      const formData = new FormData();
      formData.append('article_id', articleId);
      
      fetch('ajax/delete_news_article.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message || 'Article deleted successfully!');
            loadArticles();
          } else {
            alert('Error: ' + (data.error || 'Failed to delete article'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to delete article. Please try again.');
        });
    }
    
    // Toggle status
    function toggleStatus(articleId, currentStatus) {
      let newStatus = 'published';
      if (currentStatus === 'published') {
        newStatus = 'draft';
      } else if (currentStatus === 'draft') {
        newStatus = 'published';
      }
      
      const formData = new FormData();
      formData.append('article_id', articleId);
      formData.append('status', newStatus);
      
      fetch('ajax/toggle_news_article_status.php', {
        method: 'POST',
        body: formData
      })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text();
        })
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.success) {
              loadArticles();
            } else {
              alert('Error: ' + (data.error || 'Failed to update status'));
            }
          } catch (e) {
            console.error('JSON parse error:', e, 'Response:', text);
            alert('Failed to update status. Please try again.');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to update status. Please try again.');
        });
    }
    
    // Upload featured image
    function uploadFeaturedImage() {
      const fileInput = document.getElementById('featuredImageInput');
      const file = fileInput.files[0];
      
      if (!file) {
        alert('Please select an image file');
        return;
      }
      
      const formData = new FormData();
      formData.append('image', file);
      
      // Show loading state
      const previewDiv = document.getElementById('featuredImagePreview');
      previewDiv.innerHTML = '<div class="text-muted"><i class="bi bi-arrow-clockwise spin"></i> Uploading...</div>';
      
      fetch('ajax/upload_news_image.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById('featuredImage').value = data.file_path;
            previewDiv.innerHTML = `<img src="../${data.file_path}" class="featured-image-preview" alt="Featured Image">`;
            alert('Image uploaded successfully!');
          } else {
            previewDiv.innerHTML = '';
            alert('Error: ' + (data.error || 'Failed to upload image'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          previewDiv.innerHTML = '';
          alert('Failed to upload image. Please try again.');
        });
    }
    
    // Reset form
    function resetForm() {
      document.getElementById('articleForm').reset();
      document.getElementById('articleId').value = '';
      document.getElementById('featuredImage').value = '';
      document.getElementById('featuredImagePreview').innerHTML = '';
      document.getElementById('featuredImageInput').value = '';
      
      // Reset TinyMCE
      if (typeof tinymce !== 'undefined' && tinymce.get('articleContent')) {
        tinymce.get('articleContent').setContent('');
      }
    }
    
    // Helper functions
    function getStatusBadge(status) {
      const badges = {
        'published': '<span class="badge bg-success status-badge">Published</span>',
        'draft': '<span class="badge bg-warning status-badge">Draft</span>',
        'archived': '<span class="badge bg-secondary status-badge">Archived</span>'
      };
      return badges[status] || '<span class="badge bg-secondary status-badge">' + status + '</span>';
    }
    
    function getCategoryBadge(category) {
      const badges = {
        'news': '<span class="badge bg-primary">News</span>',
        'announcement': '<span class="badge bg-info">Announcement</span>',
        'guideline': '<span class="badge bg-success">Guideline</span>',
        'update': '<span class="badge bg-warning">Update</span>',
        'alert': '<span class="badge bg-danger">Alert</span>'
      };
      return badges[category] || '<span class="badge bg-secondary">' + category + '</span>';
    }
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
  </script>

</body>

</html>
