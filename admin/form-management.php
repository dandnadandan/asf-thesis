<?php
/**
 * Form Management System for TaxEase Admin
 * Handles uploading, managing, and viewing forms
 */

require_once '../includes/session_manager.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator, administrative staff, owner, or account executive roles
requireRole(['owner', 'administrator', 'administrative staff', 'senior account executive', 'junior account executive'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'Form Management';

// Initialize variables
$success_message = '';
$error_message = '';
$validation_errors = [];

// Include database connection
require_once '../config/database.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_form':
                $validation_errors = validateFormUpload($_POST);
                
                if (empty($validation_errors)) {
                    if (uploadForm($_POST, $currentUser['id'])) {
                        $success_message = "Form uploaded successfully!";
                    } else {
                        $error_message = "Failed to upload form. Please try again.";
                    }
                }
                break;
                
            case 'update_form':
                $validation_errors = validateFormUpdate($_POST);
                
                if (empty($validation_errors)) {
                    if (updateForm($_POST)) {
                        $success_message = "Form updated successfully!";
                    } else {
                        $error_message = "Failed to update form. Please try again.";
                    }
                }
                break;
                
            case 'delete_form':
                if (deleteForm($_POST['form_id'])) {
                    $success_message = "Form deleted successfully!";
                } else {
                    $error_message = "Failed to delete form. Please try again.";
                }
                break;
        }
    }
}

/**
 * Validate form upload data
 */
function validateFormUpload($data) {
    $errors = [];
    
    if (empty($data['form_name'])) {
        $errors['form_name'] = "Form name is required.";
    }
    
    if (empty($data['form_type'])) {
        $errors['form_type'] = "Form type is required.";
    }
    
    if (!isset($_FILES['form_file']) || $_FILES['form_file']['error'] !== UPLOAD_ERR_OK) {
        $errors['form_file'] = "Please select a valid file to upload.";
    } else {
        $file = $_FILES['form_file'];
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowed_types)) {
            $errors['form_file'] = "Only PDF and Word documents are allowed.";
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $errors['form_file'] = "File size must be less than 10MB.";
        }
    }
    
    return $errors;
}

/**
 * Validate form update data
 */
function validateFormUpdate($data) {
    $errors = [];
    
    if (empty($data['form_name'])) {
        $errors['form_name'] = "Form name is required.";
    }
    
    if (empty($data['form_type'])) {
        $errors['form_type'] = "Form type is required.";
    }
    
    return $errors;
}

/**
 * Upload form file
 */
function uploadForm($data, $user_id) {
    try {
        if (!isset($_FILES['form_file']) || $_FILES['form_file']['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $file = $_FILES['form_file'];
        $upload_dir = '../forms/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename using the form name
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $form_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['form_name']);
        $filename = $form_name . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save form info to database
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("INSERT INTO form_management 
                (form_name, original_filename, file_path, file_size, mime_type, form_type, description, required_fields, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                trim($data['form_name']),
                $file['name'],
                'forms/' . $filename,
                $file['size'],
                $file['type'],
                $data['form_type'],
                trim($data['description'] ?? ''),
                json_encode(explode(',', $data['required_fields'] ?? '')),
                $user_id
            ]);
            
            $database->closeConnection();
            return $result;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Form upload error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update form information
 */
function updateForm($data) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("UPDATE form_management 
            SET form_name = ?, form_type = ?, description = ?, required_fields = ?, is_active = ?
            WHERE id = ?");
        
        $result = $stmt->execute([
            trim($data['form_name']),
            $data['form_type'],
            trim($data['description'] ?? ''),
            json_encode(explode(',', $data['required_fields'] ?? '')),
            isset($data['is_active']) ? 1 : 0,
            $data['form_id']
        ]);
        
        $database->closeConnection();
        return $result;
    } catch (Exception $e) {
        error_log("Form update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete form
 */
function deleteForm($form_id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get file path first
        $stmt = $pdo->prepare("SELECT file_path FROM form_management WHERE id = ?");
        $stmt->execute([$form_id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($form) {
            // Delete file from filesystem
            $file_path = '../' . $form['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM form_management WHERE id = ?");
            $result = $stmt->execute([$form_id]);
            
            $database->closeConnection();
            return $result;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Form delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all forms with optional search
 */
function getAllForms($search = '') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Basic query
        $sql = "SELECT fm.*, u.first_name, u.last_name 
                FROM form_management fm 
                LEFT JOIN user_accounts u ON fm.uploaded_by = u.id";
        
        // Add search condition if provided
        $search = trim($search);
        $searchTerm = "";
        if (!empty($search)) {
            $searchTerm = "%" . $search . "%";
            $sql .= " WHERE (fm.form_name LIKE ? 
                      OR fm.description LIKE ? 
                      OR fm.form_type LIKE ? 
                      OR fm.original_filename LIKE ?)";
        }
        
        $sql .= " ORDER BY fm.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        
        // Execute with parameters if search exists
        if (!empty($search)) {
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        } else {
            $stmt->execute();
        }
        
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $database->closeConnection();
        return $forms;
    } catch (Exception $e) {
        error_log("Get forms error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get form by ID
 */
function getFormById($form_id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM form_management WHERE id = ?");
        $stmt->execute([$form_id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $database->closeConnection();
        return $form;
    } catch (Exception $e) {
        error_log("Get form by ID error: " . $e->getMessage());
        return null;
    }
}

// Get search parameter and trim it
$search = trim($_GET['search'] ?? '');

$forms = getAllForms($search);
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'upload';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Form Management</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Form Management</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
          <div class="col-12">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="bi bi-check-circle me-1"></i>
              <?php echo htmlspecialchars($success_message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
          <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-octagon me-1"></i>
              <?php echo htmlspecialchars($error_message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="col-12">
          <ul class="nav nav-tabs" id="formTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $view_mode == 'upload' ? 'active' : ''; ?>" 
                      id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button" role="tab">
                <i class="bi bi-cloud-upload me-2"></i>Upload Forms
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $view_mode == 'all' ? 'active' : ''; ?>" 
                      id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">
                <i class="bi bi-list-ul me-2"></i>View All Forms
              </button>
            </li>
          </ul>
        </div>

        <!-- Upload Form Tab -->
        <div class="col-12">
          <div class="tab-content" id="formTabContent">
            <div class="tab-pane fade <?php echo $view_mode == 'upload' ? 'show active' : ''; ?>" id="upload" role="tabpanel">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Upload New Form</h5>
                  
                  <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_form">
                    
                    <div class="row mb-3">
                      <div class="col-md-6">
                        <label for="form_name" class="form-label">Form Name *</label>
                        <input type="text" class="form-control <?php echo isset($validation_errors['form_name']) ? 'is-invalid' : ''; ?>" 
                               id="form_name" name="form_name" required>
                        <?php if (isset($validation_errors['form_name'])): ?>
                          <div class="invalid-feedback"><?php echo htmlspecialchars($validation_errors['form_name']); ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="col-md-6">
                        <label for="form_type" class="form-label">Form Type *</label>
                        <select class="form-select <?php echo isset($validation_errors['form_type']) ? 'is-invalid' : ''; ?>" 
                                id="form_type" name="form_type" required>
                          <option value="">Select Form Type</option>
                          <option value="tax_form">Tax Form</option>
                          <option value="registration_form">Registration Form</option>
                          <option value="compliance_form">Compliance Form</option>
                          <option value="other">Other</option>
                        </select>
                        <?php if (isset($validation_errors['form_type'])): ?>
                          <div class="invalid-feedback"><?php echo htmlspecialchars($validation_errors['form_type']); ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <div class="mb-3">
                      <label for="description" class="form-label">Description</label>
                      <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                      <label for="required_fields" class="form-label">Required Fields (comma-separated)</label>
                      <input type="text" class="form-control" id="required_fields" name="required_fields" 
                             placeholder="e.g., business_name, tin, email, contact_number">
                      <div class="form-text">Enter field names separated by commas. These will be used for form validation.</div>
                    </div>
                    
                    <div class="mb-3">
                      <label for="form_file" class="form-label">Form File *</label>
                      <input type="file" class="form-control <?php echo isset($validation_errors['form_file']) ? 'is-invalid' : ''; ?>" 
                             id="form_file" name="form_file" accept=".pdf,.doc,.docx" required>
                      <?php if (isset($validation_errors['form_file'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($validation_errors['form_file']); ?></div>
                      <?php endif; ?>
                      <div class="form-text">Only PDF and Word documents are allowed. Maximum file size: 10MB.</div>
                    </div>
                    
                    <div class="alert alert-info">
                      <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Important Notes:</h6>
                      <ul class="mb-0">
                        <li>The uploaded file will be renamed to match the form name</li>
                        <li>Files are stored in the <code>../forms/</code> directory</li>
                        <li>Only PDF and Word documents are accepted</li>
                        <li>Maximum file size is 10MB</li>
                      </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-cloud-upload me-2"></i>Upload Form
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <!-- View Forms Tab -->
            <div class="tab-pane fade <?php echo $view_mode == 'all' ? 'show active' : ''; ?>" id="view" role="tabpanel">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">All Forms</h5>
                  
                  <!-- Search Bar -->
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <form method="GET" id="searchForm" action="form-management.php">
                        <input type="hidden" name="view" value="all">
                        <div class="input-group">
                          <input type="text" class="form-control" name="search" id="searchInput" 
                                 placeholder="Search by form name, type, or description..." 
                                 value="<?php echo htmlspecialchars($search); ?>"
                                 autocomplete="off">
                          <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> Search
                          </button>
                          <?php if (!empty($search)): ?>
                          <a href="form-management.php?view=all" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                          </a>
                          <?php endif; ?>
                        </div>
                      </form>
                    </div>
                    <div class="col-md-6 text-end">
                      <span class="text-muted">
                        <?php 
                        $total_forms = count($forms);
                        echo $total_forms . ' form' . ($total_forms != 1 ? 's' : '');
                        if (!empty($search)) {
                            echo ' found for "' . htmlspecialchars($search) . '"';
                        }
                        ?>
                      </span>
                    </div>
                  </div>
                  
                  <?php if (empty($forms)): ?>
                    <div class="text-center py-4">
                      <i class="bi bi-inbox display-1 text-muted"></i>
                          <?php if (!empty($search)): ?>
                        <p class="text-muted">No forms found matching "<?php echo htmlspecialchars($search); ?>"</p>
                        <a href="form-management.php?view=all" class="btn btn-primary">View All Forms</a>
                          <?php else: ?>
                        <p class="text-muted">No forms uploaded yet.</p>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-striped">
                        <thead>
                          <tr>
                            <th>Form Name</th>
                            <th>Type</th>
                            <th>File Size</th>
                            <th>Uploaded By</th>
                            <th>Created</th>
                            <th>Status</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($forms as $form): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($form['form_name']); ?></strong>
                                <?php if ($form['description']): ?>
                                  <br><small class="text-muted"><?php echo htmlspecialchars($form['description']); ?></small>
                                <?php endif; ?>
                              </td>
                              <td>
                                <span class="badge bg-primary">
                                  <?php echo ucwords(str_replace('_', ' ', $form['form_type'])); ?>
                                </span>
                              </td>
                              <td><?php echo formatFileSize($form['file_size']); ?></td>
                              <td><?php echo htmlspecialchars($form['first_name'] . ' ' . $form['last_name']); ?></td>
                              <td><?php echo date('M j, Y', strtotime($form['created_at'])); ?></td>
                              <td>
                                <?php if ($form['is_active']): ?>
                                  <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                  <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <div class="btn-group" role="group">
                                  <a href="../<?php echo $form['file_path']; ?>" target="_blank" 
                                     class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                  </a>
                                  <a href="../<?php echo $form['file_path']; ?>" download 
                                     class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download"></i> Download
                                  </a>
                                  <button class="btn btn-sm btn-outline-danger" 
                                          onclick="deleteForm(<?php echo $form['id']; ?>)">
                                    <i class="bi bi-trash"></i> Delete
                                  </button>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <!-- Edit Form Modal -->
  <div class="modal fade" id="editFormModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Form</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="editFormForm">
          <input type="hidden" name="action" value="update_form">
          <input type="hidden" name="form_id" id="edit_form_id">
          <div class="modal-body">
            <div class="mb-3">
              <label for="edit_form_name" class="form-label">Form Name *</label>
              <input type="text" class="form-control" id="edit_form_name" name="form_name" required>
            </div>
            <div class="mb-3">
              <label for="edit_form_type" class="form-label">Form Type *</label>
              <select class="form-select" id="edit_form_type" name="form_type" required>
                <option value="tax_form">Tax Form</option>
                <option value="registration_form">Registration Form</option>
                <option value="compliance_form">Compliance Form</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="edit_description" class="form-label">Description</label>
              <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label for="edit_required_fields" class="form-label">Required Fields (comma-separated)</label>
              <input type="text" class="form-control" id="edit_required_fields" name="required_fields">
            </div>
            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                <label class="form-check-label" for="edit_is_active">
                  Active
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Form</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteFormModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this form? This action cannot be undone.</p>
          <p class="text-danger"><strong>This will also delete the file from the server.</strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="delete_form">
            <input type="hidden" name="form_id" id="delete_form_id">
            <button type="submit" class="btn btn-danger">Delete Form</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function editForm(formId) {
      // This would typically fetch form data via AJAX
      // For now, we'll just show the modal
      document.getElementById('edit_form_id').value = formId;
      const modal = new bootstrap.Modal(document.getElementById('editFormModal'));
      modal.show();
    }

    function deleteForm(formId) {
      document.getElementById('delete_form_id').value = formId;
      const modal = new bootstrap.Modal(document.getElementById('deleteFormModal'));
      modal.show();
    }

    // Auto-format form name for filename
    document.getElementById('form_name').addEventListener('input', function(e) {
      const value = e.target.value;
      const formatted = value.replace(/[^a-zA-Z0-9\s_-]/g, '').replace(/\s+/g, '_');
      e.target.value = formatted;
    });
    
    // Search functionality enhancements
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      
      if (searchInput) {
        // Focus search input when pressing Ctrl+F or Cmd+F on View tab
        document.addEventListener('keydown', function(e) {
          // Check if on View All Forms tab
          const viewTab = document.getElementById('view');
          if (viewTab && viewTab.classList.contains('active')) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
              e.preventDefault();
              searchInput.focus();
              searchInput.select();
            }
          }
        });
        
        // Real-time search with debounce (disabled - use manual search button)
        // Auto-submit can interfere with manual searches, so we'll let users click the search button
        // Uncomment below if you want auto-search after typing stops
        /*
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(function() {
            // Auto-submit after 1000ms of no typing, only if 3+ characters
            if (e.target.value.trim().length >= 3) {
              document.getElementById('searchForm').submit();
            } else if (e.target.value.trim().length === 0) {
              // Clear search if input is empty
              window.location.href = '?view=all';
            }
          }, 1000);
        });
        */
        
        // Submit on Enter key
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('searchForm').submit();
          }
          // Clear search on Escape key
          if (e.key === 'Escape') {
            if (searchInput.value) {
              window.location.href = 'form-management.php?view=all';
            }
          }
        });
      }
      
      // Highlight search terms in table
      const searchTerm = searchInput ? searchInput.value.trim() : '';
      if (searchTerm) {
        highlightSearchTerms(searchTerm);
      }
    });
    
    // Function to highlight search terms
    function highlightSearchTerms(term) {
      if (!term || term.length < 2) return;
      
      const tableBody = document.querySelector('.table tbody');
      if (!tableBody) return;
      
      const cells = tableBody.querySelectorAll('td');
      const regex = new RegExp(`(${term})`, 'gi');
      
      cells.forEach(cell => {
        const originalText = cell.textContent;
        if (originalText.toLowerCase().includes(term.toLowerCase())) {
          const highlightedHTML = originalText.replace(regex, '<mark>$1</mark>');
          // Only update if it's not a cell with buttons
          if (!cell.querySelector('.btn-group')) {
            cell.innerHTML = highlightedHTML;
          }
        }
      });
    }
    
    // Show notification for search results
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
      const resultsCount = document.querySelectorAll('.table tbody tr').length;
      if (resultsCount === 0) {
        console.log('No results found');
      }
    }
  </script>

</body>

</html>

<?php
/**
 * Format file size helper function
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
