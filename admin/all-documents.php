<?php
/**
 * Admin All Documents
 * Comprehensive document monitoring and management system for TaxEase Admin
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require admin role
requireRole(['admin', 'administrator'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'All Documents';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_document':
                approveDocument($pdo, $_POST);
                break;
            case 'reject_document':
                rejectDocument($pdo, $_POST);
                break;
            case 'request_revision':
                requestDocumentRevision($pdo, $_POST);
                break;
        }
    }
}

// Handle GET parameters for filtering
$status_filter = $_GET['status'] ?? 'all';
$service_type_filter = $_GET['service_type'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

$serviceTypeOptions = [
    'tax_filing' => 'Tax Filing',
    'accounting_bookkeeping' => 'Accounting & Bookkeeping',
    'financial_statements' => 'Financial Statements',
    'business_registration' => 'Business Registration',
    'compliance_consulting' => 'Compliance Consulting',
    'payroll_processing' => 'Payroll Processing'
];

// Function to get all documents with filters
function getDocuments($pdo, $status_filter, $service_type_filter, $date_filter, $search) {
    $serviceConfigs = [
        'tax_filing' => [
            'document_table' => 'tax_filing_documents',
            'service_table' => 'tax_filing_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'tax_filing'",
            'business_name_expr' => 'svc.business_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_at',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.review_notes',
            'reviewed_by_expr' => 'doc.reviewed_by',
            'reviewed_at_expr' => 'doc.reviewed_at',
            'description_expr' => 'doc.description'
        ],
        'financial_statements' => [
            'document_table' => 'financial_statements_documents',
            'service_table' => 'financial_statements_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'financial_statements'",
            'business_name_expr' => 'svc.business_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_at',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.review_notes',
            'reviewed_by_expr' => 'doc.reviewed_by',
            'reviewed_at_expr' => 'doc.reviewed_at',
            'description_expr' => 'doc.description'
        ],
        'accounting_bookkeeping' => [
            'document_table' => 'accounting_documents',
            'service_table' => 'accounting_bookkeeping_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'accounting_bookkeeping'",
            'business_name_expr' => 'svc.business_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_at',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.review_notes',
            'reviewed_by_expr' => 'doc.reviewed_by',
            'reviewed_at_expr' => 'doc.reviewed_at',
            'description_expr' => 'doc.description'
        ],
        'business_registration' => [
            'document_table' => 'business_registration_documents',
            'service_table' => 'business_registration_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'business_registration'",
            'business_name_expr' => 'svc.company_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_at',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.review_notes',
            'reviewed_by_expr' => 'doc.reviewed_by',
            'reviewed_at_expr' => 'doc.reviewed_at',
            'description_expr' => 'doc.description'
        ],
        'compliance_consulting' => [
            'document_table' => 'compliance_consulting_documents',
            'service_table' => 'compliance_consulting_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'compliance_consulting'",
            'business_name_expr' => 'svc.company_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_at',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.review_notes',
            'reviewed_by_expr' => 'doc.reviewed_by',
            'reviewed_at_expr' => 'doc.reviewed_at',
            'description_expr' => 'doc.description'
        ],
        'payroll_processing' => [
            'document_table' => 'payroll_processing_documents',
            'service_table' => 'payroll_processing_services',
            'document_alias' => 'doc',
            'service_alias' => 'svc',
            'service_type_expr' => "'payroll_processing'",
            'business_name_expr' => 'svc.company_name',
            'total_amount_expr' => 'svc.total_amount',
            'uploaded_column' => 'doc.uploaded_date',
            'status_column' => 'doc.status',
            'review_notes_expr' => 'doc.notes',
            'reviewed_by_expr' => 'NULL',
            'reviewed_at_expr' => 'NULL',
            'description_expr' => 'doc.notes'
        ],
    ];

    $documents = [];

    foreach ($serviceConfigs as $serviceKey => $config) {
        if ($service_type_filter !== 'all' && $service_type_filter !== $serviceKey) {
            continue;
        }

        $docAlias = $config['document_alias'];
        $svcAlias = $config['service_alias'];

        $select = "
            SELECT 
                {$docAlias}.id,
                {$docAlias}.service_id,
                {$config['service_type_expr']} AS service_type,
                {$config['business_name_expr']} AS business_name,
                {$config['total_amount_expr']} AS total_amount,
                {$docAlias}.document_name,
                {$docAlias}.document_type,
                {$docAlias}.file_path,
                " . ($config['file_size_expr'] ?? "{$docAlias}.file_size") . " AS file_size,
                {$config['status_column']} AS status,
                " . ($config['review_notes_expr'] ?? 'NULL') . " AS review_notes,
                " . ($config['reviewed_by_expr'] ?? 'NULL') . " AS reviewed_by,
                " . ($config['reviewed_at_expr'] ?? 'NULL') . " AS reviewed_at,
                " . ($config['description_expr'] ?? 'NULL') . " AS description,
                {$config['uploaded_column']} AS uploaded_at,
                ua.first_name,
                ua.last_name,
                ua.email,
                '{$serviceKey}' AS source_service
            FROM {$config['document_table']} {$docAlias}
            LEFT JOIN {$config['service_table']} {$svcAlias} ON {$docAlias}.service_id = {$svcAlias}.id
            LEFT JOIN user_accounts ua ON {$svcAlias}.user_id = ua.id
            WHERE 1=1
        ";

        $params = [];

        if ($status_filter !== 'all') {
            $select .= " AND {$config['status_column']} = :status";
            $params['status'] = $status_filter;
        }

        if ($date_filter !== 'all') {
            switch ($date_filter) {
                case 'today':
                    $select .= " AND DATE({$config['uploaded_column']}) = CURDATE()";
                    break;
                case 'week':
                    $select .= " AND {$config['uploaded_column']} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $select .= " AND {$config['uploaded_column']} >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }

        if (!empty($search)) {
            $select .= " AND (
                {$docAlias}.document_name LIKE :search1 OR
                {$docAlias}.document_type LIKE :search2 OR
                {$config['business_name_expr']} LIKE :search3 OR
                ua.first_name LIKE :search4 OR
                ua.last_name LIKE :search5 OR
                ua.email LIKE :search6
            )";
            $searchTerm = "%$search%";
            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
            $params['search4'] = $searchTerm;
            $params['search5'] = $searchTerm;
            $params['search6'] = $searchTerm;
        }

        $select .= " ORDER BY {$config['uploaded_column']} DESC";

        $stmt = $pdo->prepare($select);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results)) {
            $documents = array_merge($documents, $results);
        }
    }

    usort($documents, function($a, $b) {
        $timeA = isset($a['uploaded_at']) ? strtotime($a['uploaded_at']) : 0;
        $timeB = isset($b['uploaded_at']) ? strtotime($b['uploaded_at']) : 0;
        return $timeB <=> $timeA;
    });

    return $documents;
}

// Function to approve document
function approveDocument($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $notes = $data['notes'] ?? '';
    
    $sql = "UPDATE tax_filing_documents SET 
            status = 'approved', 
            reviewed_by = :admin_id, 
            reviewed_at = CURRENT_TIMESTAMP,
            review_notes = :notes
            WHERE id = :document_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'document_id' => $document_id,
        'admin_id' => $currentUser['id'],
        'notes' => $notes
    ]);
    
    return $result;
}

// Function to reject document
function rejectDocument($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $rejection_reason = $data['rejection_reason'] ?? '';
    
    $sql = "UPDATE tax_filing_documents SET 
            status = 'rejected', 
            reviewed_by = :admin_id, 
            reviewed_at = CURRENT_TIMESTAMP,
            review_notes = :notes
            WHERE id = :document_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'document_id' => $document_id,
        'admin_id' => $currentUser['id'],
        'notes' => $rejection_reason
    ]);
    
    return $result;
}

// Function to request document revision
function requestDocumentRevision($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $revision_notes = $data['revision_notes'] ?? '';
    
    $sql = "UPDATE tax_filing_documents SET 
            status = 'needs_revision', 
            reviewed_by = :admin_id, 
            reviewed_at = CURRENT_TIMESTAMP,
            review_notes = :notes
            WHERE id = :document_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'document_id' => $document_id,
        'admin_id' => $currentUser['id'],
        'notes' => $revision_notes
    ]);
    
    return $result;
}

// Get data
$documents = getDocuments($pdo, $status_filter, $service_type_filter, $date_filter, $search);

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-warning';
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'revision_requested': return 'bg-info';
        case 'under_review': return 'bg-primary';
        case 'needs_revision': return 'bg-info';
        default: return 'bg-secondary';
    }
}

// Function to get service type badge class
function getServiceTypeBadgeClass($service_type) {
    switch ($service_type) {
        case 'tax_filing': return 'bg-primary';
        case 'accounting_bookkeeping': return 'bg-info';
        case 'financial_statements': return 'bg-success';
        case 'business_registration': return 'bg-warning';
        case 'compliance_consulting': return 'bg-danger';
        case 'payroll_processing': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title><?php echo $pageTitle; ?> - TaxEase Admin</title>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>All Documents</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Document Management</li>
          <li class="breadcrumb-item active">All Documents</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        
        <!-- Statistics Cards -->
        <div class="col-lg-12">
          <div class="row">
            <div class="col-lg-3">
              <div class="card info-card">
                <div class="card-body">
                  <h5 class="card-title">Total Documents</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?php echo count($documents); ?></h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-3">
              <div class="card info-card">
                <div class="card-body">
                  <h5 class="card-title">Pending Review</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-clock"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?php echo count(array_filter($documents, function($d) { return $d['status'] === 'pending'; })); ?></h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-3">
              <div class="card info-card">
                <div class="card-body">
                  <h5 class="card-title">Approved</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?php echo count(array_filter($documents, function($d) { return $d['status'] === 'approved'; })); ?></h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-3">
              <div class="card info-card">
                <div class="card-body">
                  <h5 class="card-title">Rejected</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?php echo count(array_filter($documents, function($d) { return $d['status'] === 'rejected'; })); ?></h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters and Search -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <form method="GET" class="row g-3">
                <div class="col-md-3">
                  <label for="status" class="form-label">Status</label>
                  <select name="status" id="status" class="form-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="needs_revision" <?php echo $status_filter === 'needs_revision' ? 'selected' : ''; ?>>Needs Revision</option>
                    <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="service_type" class="form-label">Service Type</label>
                  <select name="service_type" id="service_type" class="form-select">
                    <option value="all" <?php echo $service_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($serviceTypeOptions as $value => $label): ?>
                      <option value="<?php echo $value; ?>" <?php echo $service_type_filter === $value ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="date" class="form-label">Date Range</label>
                  <select name="date" id="date" class="form-select">
                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="search" class="form-label">Search</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search documents, clients, or status..." value="<?php echo htmlspecialchars($search); ?>">
                  </div>
                  <small class="text-muted">Real-time search active</small>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">Filter</button>
                  <a href="all-documents.php" class="btn btn-secondary">Clear Filters</a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Documents Table -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Document Management</h5>
              <div id="searchResults" class="text-muted mb-2" style="display: none;">
                <small>Showing <span id="resultCount">0</span> document(s)</small>
              </div>
              
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Document</th>
                      <th>Client</th>
                      <th>Service Type</th>
                      <th>Status</th>
                      <th>Uploaded</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($documents)): ?>
                    <tr>
                      <td colspan="7" class="text-center">No documents found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($documents as $document): ?>
                    <?php $allowActions = ($document['source_service'] === 'tax_filing'); ?>
                    <tr data-document-id="<?php echo $document['id']; ?>">
                      <td><?php echo $document['id']; ?></td>
                      <td>
                        <div>
                          <strong><?php echo htmlspecialchars($document['document_name']); ?></strong>
                          <br>
                          <small class="text-muted"><?php echo htmlspecialchars($document['document_type']); ?></small>
                        </div>
                      </td>
                      <td>
                        <div>
                          <strong><?php echo htmlspecialchars($document['first_name'] . ' ' . $document['last_name']); ?></strong>
                          <br>
                          <small class="text-muted"><?php echo htmlspecialchars($document['email']); ?></small>
                        </div>
                      </td>
                      <td>
                        <span class="badge <?php echo getServiceTypeBadgeClass($document['service_type']); ?>">
                          <?php echo ucwords(str_replace('_', ' ', $document['service_type'])); ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge <?php echo getStatusBadgeClass($document['status']); ?>">
                          <?php echo ucwords(str_replace('_', ' ', $document['status'])); ?>
                        </span>
                      </td>
                      <td><?php echo date('M d, Y H:i', strtotime($document['uploaded_at'])); ?></td>
                      <td>
                        <div class="btn-group" role="group">
                          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $document['id']; ?>">
                            <i class="bi bi-eye"></i>
                          </button>
                          <?php if ($allowActions && ($document['status'] === 'pending' || $document['status'] === 'under_review')): ?>
                          <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $document['id']; ?>">
                            <i class="bi bi-check"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $document['id']; ?>">
                            <i class="bi bi-x"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#revisionModal<?php echo $document['id']; ?>">
                            <i class="bi bi-arrow-clockwise"></i>
                          </button>
                          <?php elseif (!$allowActions): ?>
                          <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Actions available for tax filing documents only">
                            <i class="bi bi-lock"></i>
                          </button>
                          <?php endif; ?>
                        </div>
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

  <?php include 'includes/footer.php'; ?>

  <!-- Real-time Search Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('search');
      const tableRows = document.querySelectorAll('tbody tr[data-document-id]');
      const searchResults = document.getElementById('searchResults');
      const resultCount = document.getElementById('resultCount');
      
      // Real-time search function
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        let visibleCount = 0;
        
        tableRows.forEach(function(row) {
          const documentName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
          const clientName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
          const serviceType = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
          const status = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
          
          const matchFound = documentName.includes(searchTerm) || 
                            clientName.includes(searchTerm) || 
                            serviceType.includes(searchTerm) || 
                            status.includes(searchTerm);
          
          if (matchFound) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });
        
        // Update result count
        if (searchTerm !== '') {
          searchResults.style.display = 'block';
          resultCount.textContent = visibleCount;
        } else {
          searchResults.style.display = 'none';
        }
        
        // Show/hide "no results" message
        const noResultsRow = document.querySelector('tbody tr td[colspan="7"]');
        if (noResultsRow && noResultsRow.parentElement) {
          const parent = noResultsRow.parentElement;
          if (visibleCount === 0 && searchTerm !== '') {
            parent.style.display = '';
            noResultsRow.textContent = 'No documents match your search';
          } else if (tableRows.length === 0) {
            parent.style.display = '';
            noResultsRow.textContent = 'No documents found';
          } else {
            parent.style.display = 'none';
          }
        }
      });
      
      // Clear search button functionality
      const clearBtn = document.querySelector('a[href="all-documents.php"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', function() {
          searchInput.value = '';
          searchResults.style.display = 'none';
          tableRows.forEach(function(row) {
            row.style.display = '';
          });
        });
      }
    });
  </script>

  <!-- View Document Modal -->
  <?php foreach ($documents as $document): ?>
  <div class="modal fade" id="viewModal<?php echo $document['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Document Details - <?php echo htmlspecialchars($document['document_name']); ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6>Document Information</h6>
              <p><strong>Name:</strong> <?php echo htmlspecialchars($document['document_name']); ?></p>
              <p><strong>Type:</strong> <?php echo htmlspecialchars($document['document_type']); ?></p>
              <p><strong>Size:</strong> <?php echo htmlspecialchars($document['file_size']); ?> bytes</p>
              <p><strong>Uploaded:</strong> <?php echo date('M d, Y H:i', strtotime($document['uploaded_at'])); ?></p>
            </div>
            <div class="col-md-6">
              <h6>Client Information</h6>
              <p><strong>Name:</strong> <?php echo htmlspecialchars($document['first_name'] . ' ' . $document['last_name']); ?></p>
              <p><strong>Email:</strong> <?php echo htmlspecialchars($document['email']); ?></p>
              <p><strong>Business:</strong> <?php echo htmlspecialchars($document['business_name']); ?></p>
              <p><strong>Service Amount:</strong> ₱<?php echo number_format($document['total_amount'], 2); ?></p>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-12">
              <h6>Document Preview</h6>
              <?php if ($document['file_path']): ?>
              <div class="text-center">
                <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="btn btn-primary">
                  <i class="bi bi-eye me-2"></i>View Document
                </a>
                <a href="../<?php echo $document['file_path']; ?>" download class="btn btn-secondary">
                  <i class="bi bi-download me-2"></i>Download
                </a>
              </div>
              <?php else: ?>
              <p class="text-muted">No document file available</p>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($document['review_notes'])): ?>
          <hr>
          <div class="row">
            <div class="col-12">
              <h6>Review Notes</h6>
              <p><?php echo nl2br(htmlspecialchars($document['review_notes'])); ?></p>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Approve Document Modal -->
  <?php foreach ($documents as $document): ?>
  <?php if (($document['source_service'] ?? 'tax_filing') === 'tax_filing' && ($document['status'] === 'pending' || $document['status'] === 'under_review')): ?>
  <div class="modal fade" id="approveModal<?php echo $document['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Approve Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="approve_document">
          <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
          <div class="modal-body">
            <p>Are you sure you want to approve this document?</p>
            <div class="mb-3">
              <label for="notes<?php echo $document['id']; ?>" class="form-label">Approval Notes (Optional)</label>
              <textarea name="notes" id="notes<?php echo $document['id']; ?>" class="form-control" rows="3" placeholder="Add any notes about this approval..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Approve Document</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>

  <!-- Reject Document Modal -->
  <?php foreach ($documents as $document): ?>
  <?php if (($document['source_service'] ?? 'tax_filing') === 'tax_filing' && ($document['status'] === 'pending' || $document['status'] === 'under_review')): ?>
  <div class="modal fade" id="rejectModal<?php echo $document['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Reject Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="reject_document">
          <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
          <div class="modal-body">
            <p>Are you sure you want to reject this document?</p>
            <div class="mb-3">
              <label for="rejection_reason<?php echo $document['id']; ?>" class="form-label">Rejection Reason</label>
              <textarea name="rejection_reason" id="rejection_reason<?php echo $document['id']; ?>" class="form-control" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Reject Document</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>

  <!-- Request Revision Modal -->
  <?php foreach ($documents as $document): ?>
  <?php if (($document['source_service'] ?? 'tax_filing') === 'tax_filing' && ($document['status'] === 'pending' || $document['status'] === 'under_review')): ?>
  <div class="modal fade" id="revisionModal<?php echo $document['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Request Document Revision</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="request_revision">
          <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
          <div class="modal-body">
            <p>Request the client to revise this document?</p>
            <div class="mb-3">
              <label for="revision_notes<?php echo $document['id']; ?>" class="form-label">Revision Notes</label>
              <textarea name="revision_notes" id="revision_notes<?php echo $document['id']; ?>" class="form-control" rows="3" placeholder="Please specify what needs to be revised..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-info">Request Revision</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>

</body>

</html>

