<?php
/**
 * System Settings Management for ASF Surveillance System
 * Only administrators can access this page
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can manage system settings
if (!canManageSystemSettings()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'System Settings';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : '';
$searchFilter = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($categoryFilter)) {
    $conditions[] = "category = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchFilter)) {
    $conditions[] = "(setting_key LIKE ? OR setting_value LIKE ? OR description LIKE ?)";
    $searchTerm = "%{$searchFilter}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get system settings
$systemSettings = [];
try {
    $sql = "SELECT ss.*, CONCAT(ua.first_name, ' ', ua.last_name) as updated_by_name
            FROM system_settings ss
            LEFT JOIN user_accounts ua ON ss.updated_by = ua.id
            {$whereClause}
            ORDER BY ss.category, ss.setting_key
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $systemSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching system settings: " . $e->getMessage());
}

// Get unique categories for filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM system_settings WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_settings' => 0,
    'public_settings' => 0,
    'categories_count' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_settings");
    $stats['total_settings'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as public FROM system_settings WHERE is_public = 1");
    $stats['public_settings'] = $stmt->fetch()['public'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category) as categories FROM system_settings WHERE category IS NOT NULL AND category != ''");
    $stats['categories_count'] = $stmt->fetch()['categories'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>System Settings</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">System Settings</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-4 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Settings</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                <i class="bi bi-gear"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_settings']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-4 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Public Settings</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-globe"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['public_settings']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-4 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Categories</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                <i class="bi bi-folder"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['categories_count']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Main Content -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">System Settings Management</h5>
            </div>
            
            <!-- Filters -->
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="categoryFilter" class="form-label">Category</label>
                <select class="form-select" id="categoryFilter" onchange="applyFilters()">
                  <option value="">All Categories</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars(ucfirst($category)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="col-md-8">
                <label for="searchInput" class="form-label">Search</label>
                <input type="text" class="form-control" id="searchInput" placeholder="Search settings..." value="<?php echo htmlspecialchars($searchFilter); ?>" onkeyup="debounce(applyFilters, 500)()">
              </div>
            </div>
            
            <!-- Settings Table -->
            <div class="table-responsive">
              <table class="table table-striped table-hover" id="settingsTable">
                <thead>
                  <tr>
                    <th>Setting Key</th>
                    <th>Value</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Public</th>
                    <th>Updated By</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="settingsTableBody">
                  <?php if (empty($systemSettings)): ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted">No system settings found. Create a new setting to get started.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($systemSettings as $setting): ?>
                      <tr>
                        <td>
                          <strong><?php echo htmlspecialchars($setting['setting_key']); ?></strong>
                          <?php if (!empty($setting['description'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($setting['description']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php 
                          $value = $setting['setting_value'];
                          if ($setting['setting_type'] === 'json') {
                            $value = json_encode(json_decode($value), JSON_PRETTY_PRINT);
                          }
                          echo htmlspecialchars(strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value); 
                          ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo ucfirst($setting['setting_type']); ?></span></td>
                        <td><?php echo $setting['category'] ? htmlspecialchars(ucfirst($setting['category'])) : 'N/A'; ?></td>
                        <td>
                          <?php if ($setting['is_public']): ?>
                            <span class="badge bg-success">Yes</span>
                          <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($setting['updated_by_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $setting['updated_at'] ? date('M d, Y H:i', strtotime($setting['updated_at'])) : 'N/A'; ?></td>
                        <td>
                          <button class="btn btn-sm btn-primary" onclick="editSetting(<?php echo $setting['id']; ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      
    </div>
  </section>
</main><!-- End #main -->

<!-- Add/Edit Setting Modal -->
<div class="modal fade" id="settingModal" tabindex="-1" aria-labelledby="settingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="settingModalLabel">Add New Setting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="settingForm">
        <div class="modal-body">
          <input type="hidden" id="settingId" name="setting_id" value="">
          
          <div class="mb-3">
            <label for="settingKey" class="form-label">Setting Key <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="settingKey" name="setting_key" required pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores allowed">
            <small class="text-muted">Use lowercase letters, numbers, and underscores only (e.g., site_name, max_upload_size)</small>
          </div>
          
          <div class="mb-3">
            <label for="settingValue" class="form-label">Setting Value <span class="text-danger">*</span></label>
            <textarea class="form-control" id="settingValue" name="setting_value" rows="3" required></textarea>
            <small class="text-muted">For JSON type, enter valid JSON format</small>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label for="settingType" class="form-label">Setting Type <span class="text-danger">*</span></label>
                <select class="form-select" id="settingType" name="setting_type" required>
                  <option value="string" selected>String</option>
                  <option value="integer">Integer</option>
                  <option value="boolean">Boolean</option>
                  <option value="json">JSON</option>
                  <option value="decimal">Decimal</option>
                </select>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="mb-3">
                <label for="settingCategory" class="form-label">Category</label>
                <input type="text" class="form-control" id="settingCategory" name="category" placeholder="e.g., general, email, security">
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="mb-3">
                <label for="isPublic" class="form-label">Public Setting</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" id="isPublic" name="is_public">
                  <label class="form-check-label" for="isPublic">Make this setting publicly accessible</label>
                </div>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="settingDescription" class="form-label">Description</label>
            <textarea class="form-control" id="settingDescription" name="description" rows="2" placeholder="Brief description of what this setting does..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Setting</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .info-card {
    margin-bottom: 1.5rem;
  }
  
  #settingsTable {
    font-size: 0.9rem;
  }
  
  #settingsTable th {
    font-weight: 600;
    white-space: nowrap;
  }
  
  .table-responsive {
    max-height: 600px;
    overflow-y: auto;
  }
</style>

<script>
  // Debounce function
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
  
  // Apply filters
  function applyFilters() {
    const category = document.getElementById('categoryFilter').value;
    const search = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams();
    if (category) params.append('category', category);
    if (search) params.append('search', search);
    
    window.location.href = 'system-settings.php' + (params.toString() ? '?' + params.toString() : '');
  }
  
  // Edit setting
  function editSetting(settingId) {
    fetch(`ajax/get_system_setting_details.php?setting_id=${settingId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const setting = data.setting;
          document.getElementById('settingModalLabel').textContent = 'Edit Setting';
          document.getElementById('settingId').value = setting.id;
          document.getElementById('settingKey').value = setting.setting_key || '';
          document.getElementById('settingKey').readOnly = true; // Key cannot be changed
          document.getElementById('settingValue').value = setting.setting_value || '';
          document.getElementById('settingType').value = setting.setting_type || 'string';
          document.getElementById('settingCategory').value = setting.category || '';
          document.getElementById('isPublic').checked = setting.is_public == 1;
          document.getElementById('settingDescription').value = setting.description || '';
          
          const modal = new bootstrap.Modal(document.getElementById('settingModal'));
          modal.show();
        } else {
          alert('Error: ' + (data.error || 'Failed to load setting details'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to load setting details. Please try again.');
      });
  }
  
  // Save setting
  document.getElementById('settingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveSetting();
  });
  
  function saveSetting() {
    const formData = new FormData(document.getElementById('settingForm'));
    
    // Convert checkbox to proper value
    formData.append('is_public', document.getElementById('isPublic').checked ? '1' : '0');
    
    // Validate JSON if type is json
    const settingType = document.getElementById('settingType').value;
    const settingValue = document.getElementById('settingValue').value;
    
    if (settingType === 'json' && settingValue.trim() !== '') {
      try {
        JSON.parse(settingValue);
      } catch (e) {
        alert('Invalid JSON format. Please check your JSON syntax.');
        return;
      }
    }
    
    // Validate integer if type is integer
    if (settingType === 'integer' && settingValue.trim() !== '') {
      if (!Number.isInteger(Number(settingValue)) || settingValue !== String(Number(settingValue))) {
        alert('Invalid integer value. Please enter a valid integer.');
        return;
      }
    }
    
    // Validate decimal if type is decimal
    if (settingType === 'decimal' && settingValue.trim() !== '') {
      if (isNaN(parseFloat(settingValue))) {
        alert('Invalid decimal value. Please enter a valid number.');
        return;
      }
    }
    
    // Validate boolean if type is boolean
    if (settingType === 'boolean' && settingValue.trim() !== '') {
      const lowerValue = settingValue.toLowerCase().trim();
      if (!['true', 'false', '1', '0', 'yes', 'no'].includes(lowerValue)) {
        alert('Invalid boolean value. Please enter true, false, 1, 0, yes, or no.');
        return;
      }
    }
    
    const saveBtn = document.querySelector('#settingModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Saving...';
    
    fetch('ajax/save_system_setting.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Setting saved successfully!');
          const modal = bootstrap.Modal.getInstance(document.getElementById('settingModal'));
          modal.hide();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to save setting'));
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalText;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to save setting. Please try again.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      });
  }
  
  // Reset form when modal is hidden
  document.getElementById('settingModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('settingForm').reset();
    document.getElementById('settingId').value = '';
  });
  
  // Spin animation
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    .spin {
      animation: spin 1s linear infinite;
    }
  `;
  document.head.appendChild(style);
</script>

<?php
include 'includes/footer.php';
?>
