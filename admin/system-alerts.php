<?php
/**
 * System Alerts Management for ASF Surveillance System
 * Only administrators can access this page
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can manage system alerts
if (!canManageSystemAlerts()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'System Alerts';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$typeFilter = isset($_GET['type']) && $_GET['type'] !== '' ? trim($_GET['type']) : '';
$severityFilter = isset($_GET['severity']) && $_GET['severity'] !== '' ? trim($_GET['severity']) : '';
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($typeFilter)) {
    $conditions[] = "sa.alert_type = ?";
    $params[] = $typeFilter;
}

if (!empty($severityFilter)) {
    $conditions[] = "sa.severity = ?";
    $params[] = $severityFilter;
}

if (!empty($statusFilter)) {
    $conditions[] = "sa.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get system alerts
$systemAlerts = [];
try {
    $sql = "SELECT sa.*, CONCAT(ua.first_name, ' ', ua.last_name) as created_by_name
            FROM system_alerts sa
            LEFT JOIN user_accounts ua ON sa.created_by = ua.id
            {$whereClause}
            ORDER BY sa.created_at DESC
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $systemAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching system alerts: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_alerts' => 0,
    'active_alerts' => 0,
    'critical_alerts' => 0,
    'outbreak_alerts' => 0,
    'high_risk_alerts' => 0,
    'depopulation_alerts' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_alerts");
    $stats['total_alerts'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM system_alerts WHERE status = 'active'");
    $stats['active_alerts'] = $stmt->fetch()['active'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as critical FROM system_alerts WHERE severity = 'critical' AND status = 'active'");
    $stats['critical_alerts'] = $stmt->fetch()['critical'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as outbreak FROM system_alerts WHERE alert_type = 'outbreak'");
    $stats['outbreak_alerts'] = $stmt->fetch()['outbreak'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as high_risk FROM system_alerts WHERE alert_type = 'high_risk'");
    $stats['high_risk_alerts'] = $stmt->fetch()['high_risk'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as depopulation FROM system_alerts WHERE alert_type = 'depopulation'");
    $stats['depopulation_alerts'] = $stmt->fetch()['depopulation'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>System Alerts</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">System Alerts</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                <i class="bi bi-bell"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_alerts']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Active Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['active_alerts']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Critical Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['critical_alerts']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Outbreak Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['outbreak_alerts'] ?? 0); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">High Risk Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-exclamation-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['high_risk_alerts'] ?? 0); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Depopulation Alerts</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                <i class="bi bi-people"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['depopulation_alerts'] ?? 0); ?></h6>
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
              <h5 class="card-title mb-0">System Alerts Management</h5>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#alertModal" onclick="showAddModal()">
                <i class="bi bi-plus-circle me-2"></i>Add New Alert
              </button>
            </div>
            
            <!-- Filters -->
            <div class="row mb-3">
              <div class="col-md-3">
                <label for="typeFilter" class="form-label">Alert Type</label>
                <select class="form-select" id="typeFilter" onchange="applyFilters()">
                  <option value="">All Types</option>
                  <option value="outbreak" <?php echo $typeFilter === 'outbreak' ? 'selected' : ''; ?>>Outbreak</option>
                  <option value="high_risk" <?php echo $typeFilter === 'high_risk' ? 'selected' : ''; ?>>High Risk</option>
                  <option value="depopulation" <?php echo $typeFilter === 'depopulation' ? 'selected' : ''; ?>>Depopulation</option>
                  <option value="meat_movement" <?php echo $typeFilter === 'meat_movement' ? 'selected' : ''; ?>>Meat Movement</option>
                  <option value="predictive" <?php echo $typeFilter === 'predictive' ? 'selected' : ''; ?>>Predictive</option>
                  <option value="system" <?php echo $typeFilter === 'system' ? 'selected' : ''; ?>>System</option>
                </select>
              </div>
              
              <div class="col-md-3">
                <label for="severityFilter" class="form-label">Severity</label>
                <select class="form-select" id="severityFilter" onchange="applyFilters()">
                  <option value="">All Severities</option>
                  <option value="low" <?php echo $severityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                  <option value="medium" <?php echo $severityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                  <option value="high" <?php echo $severityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                  <option value="critical" <?php echo $severityFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                </select>
              </div>
              
              <div class="col-md-3">
                <label for="statusFilter" class="form-label">Status</label>
                <select class="form-select" id="statusFilter" onchange="applyFilters()">
                  <option value="">All Status</option>
                  <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                  <option value="acknowledged" <?php echo $statusFilter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                  <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                  <option value="dismissed" <?php echo $statusFilter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                </select>
              </div>
              
              <div class="col-md-3">
                <label for="searchInput" class="form-label">Search</label>
                <input type="text" class="form-control" id="searchInput" placeholder="Search alerts..." onkeyup="debounce(applyFilters, 500)()">
              </div>
            </div>
            
            <!-- Alerts Table -->
            <div class="table-responsive">
              <table class="table table-striped table-hover" id="alertsTable">
                <thead>
                  <tr>
                    <th>Alert Code</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="alertsTableBody">
                  <?php if (empty($systemAlerts)): ?>
                    <tr>
                      <td colspan="9" class="text-center text-muted">No system alerts found. Create a new alert to get started.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($systemAlerts as $alert): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($alert['alert_code']); ?></td>
                        <td><?php echo htmlspecialchars($alert['title']); ?></td>
                        <td><span class="badge <?php echo getTypeBadgeClass($alert['alert_type']); ?>"><?php echo formatAlertType($alert['alert_type']); ?></span></td>
                        <td><span class="badge <?php echo getSeverityBadgeClass($alert['severity']); ?>"><?php echo ucfirst($alert['severity']); ?></span></td>
                        <td><?php echo formatLocation($alert['location_province'], $alert['location_city']); ?></td>
                        <td>
                          <span class="badge <?php echo getStatusBadgeClass($alert['status']); ?>"><?php echo ucfirst($alert['status']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($alert['created_by_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $alert['created_at'] ? date('M d, Y H:i', strtotime($alert['created_at'])) : 'N/A'; ?></td>
                        <td>
                          <button class="btn btn-sm btn-primary" onclick="editAlert(<?php echo $alert['id']; ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                          </button>
                          <?php if ($alert['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?php echo $alert['id']; ?>, 'acknowledged')" title="Acknowledge">
                              <i class="bi bi-check"></i>
                            </button>
                          <?php elseif ($alert['status'] === 'acknowledged'): ?>
                            <button class="btn btn-sm btn-success" onclick="toggleStatus(<?php echo $alert['id']; ?>, 'resolved')" title="Resolve">
                              <i class="bi bi-check-circle"></i>
                            </button>
                          <?php endif; ?>
                          <button class="btn btn-sm btn-danger" onclick="deleteAlert(<?php echo $alert['id']; ?>)" title="Delete">
                            <i class="bi bi-trash"></i>
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

<!-- Add/Edit Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="alertModalLabel">Add New Alert</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="alertForm">
        <div class="modal-body">
          <input type="hidden" id="alertId" name="alert_id" value="">
          
          <div class="mb-3">
            <label for="alertTitle" class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="alertTitle" name="title" required>
          </div>
          
          <div class="mb-3">
            <label for="alertMessage" class="form-label">Message <span class="text-danger">*</span></label>
            <textarea class="form-control" id="alertMessage" name="message" rows="4" required></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="alertType" class="form-label">Alert Type <span class="text-danger">*</span></label>
                <select class="form-select" id="alertType" name="alert_type" required>
                  <option value="info">Info</option>
                  <option value="warning">Warning</option>
                  <option value="danger">Danger</option>
                  <option value="success">Success</option>
                </select>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="alertPriority" class="form-label">Priority <span class="text-danger">*</span></label>
                <select class="form-select" id="alertPriority" name="priority" required>
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="targetAudience" class="form-label">Target Audience <span class="text-danger">*</span></label>
            <select class="form-select" id="targetAudience" name="target_audience" required>
              <option value="all" selected>All Users</option>
              <option value="administrator">Administrator</option>
              <option value="supervisor">Supervisor</option>
              <option value="veterinarian">Veterinarian</option>
              <option value="inspector">Inspector</option>
              <option value="analyst">Analyst</option>
              <option value="field_staff">Field Staff</option>
              <option value="data_entry">Data Entry</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="startDate" class="form-label">Start Date</label>
                <input type="datetime-local" class="form-control" id="startDate" name="start_date">
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label for="endDate" class="form-label">End Date</label>
                <input type="datetime-local" class="form-control" id="endDate" name="end_date">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                <label class="form-check-label" for="isActive">Active</label>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="isDismissible" name="is_dismissible" checked>
                <label class="form-check-label" for="isDismissible">Dismissible</label>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="showOnDashboard" name="show_on_dashboard" checked>
                <label class="form-check-label" for="showOnDashboard">Show on Dashboard</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Alert</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .info-card {
    margin-bottom: 1.5rem;
  }
  
  #alertsTable {
    font-size: 0.9rem;
  }
  
  #alertsTable th {
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
    const type = document.getElementById('typeFilter').value;
    const priority = document.getElementById('priorityFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams();
    if (type) params.append('type', type);
    if (priority) params.append('priority', priority);
    if (status) params.append('status', status);
    if (search) params.append('search', search);
    
    window.location.href = 'system-alerts.php' + (params.toString() ? '?' + params.toString() : '');
  }
  
  // Show add modal
  function showAddModal() {
    document.getElementById('alertModalLabel').textContent = 'Add New Alert';
    document.getElementById('alertForm').reset();
    document.getElementById('alertId').value = '';
    document.getElementById('isActive').checked = true;
    document.getElementById('isDismissible').checked = true;
    document.getElementById('showOnDashboard').checked = true;
  }
  
  // Edit alert
  function editAlert(alertId) {
    fetch(`ajax/get_system_alert_details.php?alert_id=${alertId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const alert = data.alert;
          document.getElementById('alertModalLabel').textContent = 'Edit Alert';
          document.getElementById('alertId').value = alert.id;
          document.getElementById('alertTitle').value = alert.title || '';
          document.getElementById('alertMessage').value = alert.message || '';
          document.getElementById('alertType').value = alert.alert_type || 'info';
          document.getElementById('alertPriority').value = alert.priority || 'medium';
          document.getElementById('targetAudience').value = alert.target_audience || 'all';
          
          // Format dates for datetime-local input
          if (alert.start_date) {
            const startDate = new Date(alert.start_date);
            document.getElementById('startDate').value = startDate.toISOString().slice(0, 16);
          } else {
            document.getElementById('startDate').value = '';
          }
          
          if (alert.end_date) {
            const endDate = new Date(alert.end_date);
            document.getElementById('endDate').value = endDate.toISOString().slice(0, 16);
          } else {
            document.getElementById('endDate').value = '';
          }
          
          document.getElementById('isActive').checked = alert.is_active == 1;
          document.getElementById('isDismissible').checked = alert.is_dismissible == 1;
          document.getElementById('showOnDashboard').checked = alert.show_on_dashboard == 1;
          
          const modal = new bootstrap.Modal(document.getElementById('alertModal'));
          modal.show();
        } else {
          alert('Error: ' + (data.error || 'Failed to load alert details'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to load alert details. Please try again.');
      });
  }
  
  // Save alert
  document.getElementById('alertForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveAlert();
  });
  
  function saveAlert() {
    const formData = new FormData(document.getElementById('alertForm'));
    
    const saveBtn = document.querySelector('#alertModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Saving...';
    
    fetch('ajax/save_system_alert.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Alert saved successfully!');
          const modal = bootstrap.Modal.getInstance(document.getElementById('alertModal'));
          modal.hide();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to save alert'));
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalText;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to save alert. Please try again.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      });
  }
  
  // Delete alert
  function deleteAlert(alertId) {
    if (!confirm('Are you sure you want to delete this alert? This action cannot be undone.')) {
      return;
    }
    
    const formData = new FormData();
    formData.append('alert_id', alertId);
    
    fetch('ajax/delete_system_alert.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Alert deleted successfully!');
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to delete alert'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete alert. Please try again.');
      });
  }
  
  // Toggle status
  function toggleStatus(alertId, newStatus) {
    const action = newStatus === 'acknowledged' ? 'acknowledge' : newStatus === 'resolved' ? 'resolve' : 'update';
    
    if (!confirm(`Are you sure you want to ${action} this alert?`)) {
      return;
    }
    
    const formData = new FormData();
    formData.append('alert_id', alertId);
    formData.append('status', newStatus);
    
    fetch('ajax/toggle_system_alert_status.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to update alert status'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to update alert status. Please try again.');
      });
  }
  
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
// Helper functions
function getTypeBadgeClass($type) {
    $classes = [
        'outbreak' => 'bg-danger',
        'high_risk' => 'bg-warning',
        'depopulation' => 'bg-info',
        'meat_movement' => 'bg-primary',
        'predictive' => 'bg-purple',
        'system' => 'bg-secondary'
    ];
    return $classes[$type] ?? 'bg-secondary';
}

function getSeverityBadgeClass($severity) {
    $classes = [
        'low' => 'bg-secondary',
        'medium' => 'bg-info',
        'high' => 'bg-warning',
        'critical' => 'bg-danger'
    ];
    return $classes[$severity] ?? 'bg-secondary';
}

function getStatusBadgeClass($status) {
    $classes = [
        'active' => 'bg-success',
        'acknowledged' => 'bg-warning',
        'resolved' => 'bg-info',
        'dismissed' => 'bg-secondary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

function formatAlertType($type) {
    $types = [
        'outbreak' => 'Outbreak',
        'high_risk' => 'High Risk',
        'depopulation' => 'Depopulation',
        'meat_movement' => 'Meat Movement',
        'predictive' => 'Predictive',
        'system' => 'System'
    ];
    return $types[$type] ?? ucfirst($type);
}

function formatLocation($province, $city) {
    $parts = [];
    if (!empty($city)) {
        $parts[] = $city;
    }
    if (!empty($province)) {
        $parts[] = $province;
    }
    return !empty($parts) ? implode(', ', $parts) : 'N/A';
}
?>

<?php
include 'includes/footer.php';
?>
