<?php
/**
 * Data Uploads Management for ASF Surveillance System
 * Allows CSV file uploads for environmental data, outbreaks, depopulation events, and meat movement
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Data Uploads';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get upload history (exclude risk zone calculations - they're not file uploads)
$uploadHistory = [];
try {
    $stmt = $pdo->prepare("SELECT du.*, ua.first_name, ua.last_name 
                          FROM data_uploads du 
                          LEFT JOIN user_accounts ua ON du.uploaded_by = ua.id 
                          WHERE du.upload_type IN ('environmental', 'outbreaks', 'depopulation', 'meat_movement', 'combined')
                          ORDER BY du.created_at DESC 
                          LIMIT 50");
    $stmt->execute();
    $uploadHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching upload history: " . $e->getMessage());
}

// Get upload statistics
$uploadStats = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'failed' => 0,
    'partially_completed' => 0
];

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM data_uploads WHERE upload_type IN ('environmental', 'outbreaks', 'depopulation', 'meat_movement', 'combined') GROUP BY status");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $uploadStats['total'] += $row['count'];
        $uploadStats[$row['status']] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching upload statistics: " . $e->getMessage());
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Data Uploads</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Data Uploads</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      <div class="col-lg-12">
        
        <!-- Upload Statistics Cards -->
        <div class="row mb-4">
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Total Uploads</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['total']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Completed</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                    <i class="bi bi-check-circle"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['completed']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Pending</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                    <i class="bi bi-clock-history"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['pending']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Failed</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                    <i class="bi bi-x-circle"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['failed']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Processing</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                    <i class="bi bi-hourglass-split"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['processing']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-xl-2 col-md-4">
            <div class="card info-card">
              <div class="card-body">
                <h5 class="card-title">Partial</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                  </div>
                  <div class="ps-3">
                    <h6><?php echo number_format($uploadStats['partially_completed']); ?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload Form Card -->
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between">
                  <h5 class="card-title">Upload CSV File</h5>
                  <button type="button" class="btn btn-info my-3" onclick="downloadFile()">Download Template</button> 
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="uploadType" class="form-label">Upload Type <span class="text-danger">*</span></label>
                  <select class="form-select" id="uploadType" name="uploadType" required>
                    <option value="">-- Select Upload Type --</option>
                    <option value="environmental">Environmental Data</option>
                    <option value="outbreaks">ASF Outbreaks</option>
                    <option value="depopulation">Depopulation Events</option>
                    <option value="meat_movement">Meat Movement</option>
                  </select>
                  <small class="text-muted">Select the type of data you are uploading</small>
                </div>
                
                <div class="col-md-6">
                  <label for="csvFile" class="form-label">CSV File <span class="text-danger">*</span></label>
                  <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv,.xlsx,.xls" required>
                  <small class="text-muted">Accepted formats: CSV, XLSX, XLS (Max: 10MB)</small>
                </div>
              </div>
              
              <div class="row mb-3">
                <div class="col-md-12">
                  <label for="notes" class="form-label">Notes (Optional)</label>
                  <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this upload..."></textarea>
                </div>
              </div>
              
              <div class="mb-3">
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  <strong>CSV Format Guidelines:</strong>
                  <ul class="mb-0 mt-2">
                    <li>First row must contain column headers</li>
                    <li>Ensure data matches the selected upload type format</li>
                    <li>Required fields must not be empty</li>
                    <li>Dates should be in YYYY-MM-DD format</li>
                    <li>Coordinates (latitude/longitude) must be valid decimal numbers</li>
                  </ul>
                </div>
              </div>
              
              <div class="text-center">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-upload me-2"></i>Upload File
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('uploadForm').reset();">
                  <i class="bi bi-x-circle me-2"></i>Clear Form
                </button>
              </div>
            </form>
            
            <!-- Upload Progress -->
            <div id="uploadProgress" class="mt-4" style="display: none;">
              <div class="progress" style="height: 25px;">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
              </div>
              <div id="uploadStatus" class="mt-2 text-center"></div>
            </div>
          </div>
        </div>

        <!-- Upload History Card -->
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Upload History</h5>
            
            <?php if (empty($uploadHistory)): ?>
              <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No uploads yet</h5>
                <p class="text-muted">Upload your first CSV file to get started</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="uploadsTable">
                  <thead>
                    <tr>
                      <th>Upload Code</th>
                      <th>Type</th>
                      <th>File Name</th>
                      <th>Records</th>
                      <th>Status</th>
                      <th>Uploaded By</th>
                      <th>Date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($uploadHistory as $upload): ?>
                      <tr>
                        <td><code><?php echo htmlspecialchars($upload['upload_code']); ?></code></td>
                        <td>
                          <span class="badge bg-info">
                            <?php echo ucfirst(str_replace('_', ' ', $upload['upload_type'])); ?>
                          </span>
                        </td>
                        <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                        <td>
                          <span class="badge bg-success"><?php echo number_format($upload['successful_records']); ?> success</span>
                          <?php if ($upload['failed_records'] > 0): ?>
                            <span class="badge bg-danger"><?php echo number_format($upload['failed_records']); ?> failed</span>
                          <?php endif; ?>
                          <small class="text-muted d-block">Total: <?php echo number_format($upload['total_records']); ?></small>
                        </td>
                        <td>
                          <?php
                          $statusColors = [
                            'pending' => 'warning',
                            'processing' => 'info',
                            'completed' => 'success',
                            'failed' => 'danger',
                            'partially_completed' => 'warning'
                          ];
                          $statusColor = $statusColors[$upload['status']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $statusColor; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $upload['status'])); ?>
                          </span>
                        </td>
                        <td><?php echo htmlspecialchars($upload['first_name'] . ' ' . $upload['last_name']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($upload['created_at'])); ?></td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewUploadDetails(<?php echo $upload['id']; ?>)">
                            <i class="bi bi-eye"></i>
                          </button>
                          <?php if ($upload['status'] == 'completed' || $upload['status'] == 'partially_completed'): ?>
                            <a href="../uploads/data/<?php echo htmlspecialchars($upload['file_name']); ?>" class="btn btn-sm btn-outline-secondary" download>
                              <i class="bi bi-download"></i>
                            </a>
                          <?php endif; ?>
                          <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteUpload(<?php echo $upload['id']; ?>, '<?php echo htmlspecialchars(addslashes($upload['file_name'])); ?>')">
                            <i class="bi bi-trash"></i>
                          </button>
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
  </section>

</main><!-- End #main -->

<!-- Upload Details Modal -->
<div class="modal fade" id="uploadDetailsModal" tabindex="-1" aria-labelledby="uploadDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadDetailsModalLabel">Upload Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="uploadDetailsContent">
        <div class="text-center">
          <i class="bi bi-arrow-clockwise spin"></i> Loading...
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Upload Confirmation Modal -->
<div class="modal fade" id="deleteUploadModal" tabindex="-1" aria-labelledby="deleteUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteUploadModalLabel">Delete Upload</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this upload?</p>
        <p class="fw-bold" id="deleteUploadFileName"></p>
        <div class="alert alert-danger small mb-0">
          <strong>This will permanently delete:</strong>
          <ul class="mb-0 mt-1">
            <li>The uploaded file</li>
            <li>All data records imported from this file</li>
            <li>Any linked documents or attachments</li>
            <li>All calculated risk zones and predictive model data</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>

  
function downloadFile() {
    const uploadType = document.getElementById("uploadType").value;

    let filePath = "";
    let fileName = "";

    switch(uploadType) {
        case "environmental":
            filePath = "DATA TEMPLATE.xlsx";
            fileName = "DATA TEMPLATE.xlsx";
            break;

        case "outbreaks":
            filePath = "DATA ASF - ASF Outbreak.xlsx";
            fileName = "DATA ASF - ASF Outbreak.xlsx";
            break;

        case "depopulation":
            filePath = "DATA ASF - Depopulation.xlsx";
            fileName = "DATA ASF - Depopulation.xlsx";
            break;

        case "meat_movement":
            filePath = "DATA ASF - Meat Movement.xlsx";
            fileName = "DATA ASF - Meat Movement.xlsx";
            break;

        default:
            alert("Please select an upload type first.");
            return;
    }

    // Create temporary download link
    const link = document.createElement("a");
    link.href = filePath;
    link.download = fileName;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}  

document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const uploadsTable = document.getElementById('uploadsTable');
  if (uploadsTable) {
    new simpleDatatables.DataTable(uploadsTable, {
      "pageLength": 25,
      "order": [[6, "desc"]],
      "responsive": true
    });
  }

  // Handle form submission
  document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const uploadType = document.getElementById('uploadType').value;
    const fileInput = document.getElementById('csvFile');
    
    if (!uploadType) {
      alert('Please select an upload type');
      return;
    }
    
    if (!fileInput.files || !fileInput.files[0]) {
      alert('Please select a file to upload');
      return;
    }
    
    // Validate file size (10MB)
    if (fileInput.files[0].size > 10 * 1024 * 1024) {
      alert('File size must be less than 10MB');
      return;
    }
    
    // Show progress
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressBar').textContent = '0%';
    document.getElementById('uploadStatus').innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading...';
    
    // Disable form
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading...';
    
    // Upload file
    fetch('ajax/upload_csv.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('progressBar').textContent = '100%';
        document.getElementById('progressBar').classList.remove('progress-bar-animated');
        document.getElementById('progressBar').classList.add('bg-success');
        document.getElementById('uploadStatus').innerHTML = 
          '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>' + data.message + '</div>';
        
        // Reset form
        document.getElementById('uploadForm').reset();
        
        // Reload page after 2 seconds
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        document.getElementById('progressBar').classList.add('bg-danger');
        document.getElementById('uploadStatus').innerHTML = 
          '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>' + data.message + '</div>';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Upload File';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('progressBar').classList.add('bg-danger');
      document.getElementById('uploadStatus').innerHTML = 
        '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>An error occurred during upload. Please try again.</div>';
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Upload File';
    });
  });
});

function viewUploadDetails(uploadId) {
  const modal = new bootstrap.Modal(document.getElementById('uploadDetailsModal'));
  const content = document.getElementById('uploadDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_upload_details.php?upload_id=${uploadId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const upload = data.upload;
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <p><strong>Upload Code:</strong><br><code>${upload.upload_code}</code></p>
              <p><strong>Upload Type:</strong><br><span class="badge bg-info">${upload.upload_type.replace('_', ' ')}</span></p>
              <p><strong>File Name:</strong><br>${upload.file_name}</p>
              <p><strong>File Size:</strong><br>${formatFileSize(upload.file_size)}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Status:</strong><br><span class="badge bg-${getStatusColor(upload.status)}">${upload.status.replace('_', ' ')}</span></p>
              <p><strong>Total Records:</strong><br>${upload.total_records}</p>
              <p><strong>Successful:</strong><br><span class="badge bg-success">${upload.successful_records}</span></p>
              <p><strong>Failed:</strong><br><span class="badge bg-danger">${upload.failed_records}</span></p>
            </div>
          </div>
          ${upload.notes ? `<p><strong>Notes:</strong><br>${upload.notes}</p>` : ''}
          ${upload.validation_errors ? `<div class="alert alert-warning"><strong>Validation Errors:</strong><br><pre>${JSON.stringify(JSON.parse(upload.validation_errors), null, 2)}</pre></div>` : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load upload details</div>';
    });
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function getStatusColor(status) {
  const colors = {
    'pending': 'warning',
    'processing': 'info',
    'completed': 'success',
    'failed': 'danger',
    'partially_completed': 'warning'
  };
  return colors[status] || 'secondary';
}

let pendingDeleteId = null;

function confirmDeleteUpload(uploadId, fileName) {
  pendingDeleteId = uploadId;
  document.getElementById('deleteUploadFileName').textContent = fileName;
  const modal = new bootstrap.Modal(document.getElementById('deleteUploadModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!pendingDeleteId) return;

    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    const formData = new FormData();
    formData.append('upload_id', pendingDeleteId);

    fetch('ajax/delete_upload.php', { method: 'POST', body: formData })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          window.location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to delete upload'));
          btn.disabled = false;
          btn.textContent = 'Delete';
        }
      })
      .catch(() => {
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Delete';
      });
  });
});
</script>

<style>
.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
