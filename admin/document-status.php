<?php
/**
 * Admin Document Status Monitoring
 * Comprehensive dashboard for monitoring document statuses, analytics, and metrics
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
$pageTitle = 'Document Status Monitor';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$date_range = $_GET['date_range'] ?? '30';
$service_filter = $_GET['service'] ?? 'all';

// Function to get document statistics
function getDocumentStats($pdo, $date_range = '30') {
    $sql = "SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'needs_revision' THEN 1 ELSE 0 END) as needs_revision,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review
            FROM tax_filing_documents
            WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $date_range]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get status breakdown by service type
function getStatusByServiceType($pdo, $date_range = '30') {
    $sql = "SELECT 
                tfs.service_type,
                tfd.status,
                COUNT(*) as count
            FROM tax_filing_documents tfd
            LEFT JOIN tax_filing_services tfs ON tfd.service_id = tfs.id
            WHERE tfd.uploaded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY tfs.service_type, tfd.status
            ORDER BY tfs.service_type, tfd.status";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $date_range]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get document aging (pending documents older than X days)
function getDocumentAging($pdo) {
    $sql = "SELECT 
                tfd.id,
                tfd.document_name,
                tfd.status,
                tfd.uploaded_at,
                DATEDIFF(NOW(), tfd.uploaded_at) as days_pending,
                tfs.service_type,
                tfs.business_name,
                ua.first_name,
                ua.last_name,
                ua.email
            FROM tax_filing_documents tfd
            LEFT JOIN tax_filing_services tfs ON tfd.service_id = tfs.id
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            WHERE tfd.status IN ('pending', 'under_review', 'needs_revision')
            ORDER BY days_pending DESC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent document activity
function getRecentActivity($pdo, $limit = 15) {
    $sql = "SELECT 
                tfd.id,
                tfd.document_name,
                tfd.status,
                tfd.uploaded_at,
                tfd.reviewed_at,
                tfs.service_type,
                tfs.business_name,
                ua.first_name,
                ua.last_name,
                reviewer.first_name as reviewer_first_name,
                reviewer.last_name as reviewer_last_name
            FROM tax_filing_documents tfd
            LEFT JOIN tax_filing_services tfs ON tfd.service_id = tfs.id
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            LEFT JOIN user_accounts reviewer ON tfd.reviewed_by = reviewer.id
            ORDER BY COALESCE(tfd.reviewed_at, tfd.uploaded_at) DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get client document summary
function getClientDocumentSummary($pdo, $date_range = '30') {
    $sql = "SELECT 
                ua.id as user_id,
                ua.first_name,
                ua.last_name,
                ua.email,
                COUNT(tfd.id) as total_documents,
                SUM(CASE WHEN tfd.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN tfd.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN tfd.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                MAX(tfd.uploaded_at) as last_upload
            FROM tax_filing_documents tfd
            LEFT JOIN tax_filing_services tfs ON tfd.service_id = tfs.id
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            WHERE tfd.uploaded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY ua.id, ua.first_name, ua.last_name, ua.email
            HAVING total_documents > 0
            ORDER BY total_documents DESC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $date_range]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get daily document trends
function getDailyTrends($pdo, $days = 14) {
    $sql = "SELECT 
                DATE(uploaded_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM tax_filing_documents
            WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(uploaded_at)
            ORDER BY date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get average processing time
function getAverageProcessingTime($pdo, $date_range = '30') {
    $sql = "SELECT 
                AVG(TIMESTAMPDIFF(HOUR, uploaded_at, reviewed_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, uploaded_at, reviewed_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, uploaded_at, reviewed_at)) as max_hours,
                COUNT(*) as reviewed_count
            FROM tax_filing_documents
            WHERE reviewed_at IS NOT NULL
            AND uploaded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND status IN ('approved', 'rejected')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $date_range]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get urgent documents (pending > 7 days)
function getUrgentDocuments($pdo) {
    $sql = "SELECT COUNT(*) as urgent_count
            FROM tax_filing_documents
            WHERE status IN ('pending', 'under_review')
            AND DATEDIFF(NOW(), uploaded_at) > 7";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all data
$stats = getDocumentStats($pdo, $date_range);
$statusByService = getStatusByServiceType($pdo, $date_range);
$documentAging = getDocumentAging($pdo);
$recentActivity = getRecentActivity($pdo, 15);
$clientSummary = getClientDocumentSummary($pdo, $date_range);
$dailyTrends = getDailyTrends($pdo, 14);
$processingTime = getAverageProcessingTime($pdo, $date_range);
$urgentDocs = getUrgentDocuments($pdo);

// Calculate percentages
$total = $stats['total_documents'] ?: 1;
$pendingPercent = round(($stats['pending'] / $total) * 100, 1);
$approvedPercent = round(($stats['approved'] / $total) * 100, 1);
$rejectedPercent = round(($stats['rejected'] / $total) * 100, 1);

// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-warning';
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'needs_revision': return 'bg-info';
        case 'under_review': return 'bg-primary';
        default: return 'bg-secondary';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return 'bi-clock-history';
        case 'approved': return 'bi-check-circle';
        case 'rejected': return 'bi-x-circle';
        case 'needs_revision': return 'bi-arrow-clockwise';
        case 'under_review': return 'bi-eye';
        default: return 'bi-question-circle';
    }
}

function getActivityIcon($status) {
    switch ($status) {
        case 'approved': return 'bi-check-circle-fill text-success';
        case 'rejected': return 'bi-x-circle-fill text-danger';
        case 'needs_revision': return 'bi-arrow-clockwise text-info';
        case 'under_review': return 'bi-eye-fill text-primary';
        default: return 'bi-upload text-warning';
    }
}

function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title><?php echo $pageTitle; ?> - TaxEase Admin</title>
  <style>
    .stat-card {
      transition: transform 0.2s;
    }
    .stat-card:hover {
      transform: translateY(-5px);
    }
    .progress-thin {
      height: 8px;
    }
    .activity-timeline {
      position: relative;
      padding-left: 30px;
    }
    .activity-timeline::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #dee2e6;
    }
    .activity-item {
      position: relative;
      margin-bottom: 20px;
    }
    .activity-icon {
      position: absolute;
      left: -22px;
      background: white;
      padding: 2px;
    }
    .aging-badge {
      font-weight: bold;
    }
    .urgent {
      background-color: #fff3cd;
      border-left: 4px solid #ffc107;
    }
    .critical {
      background-color: #f8d7da;
      border-left: 4px solid #dc3545;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Document Status Monitor</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Document Management</li>
          <li class="breadcrumb-item active">Status Monitor</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <!-- Filters -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <form method="GET" class="row g-3 align-items-end mt-2">
                <div class="col-md-3">
                  <label for="date_range" class="form-label">Time Period</label>
                  <select name="date_range" id="date_range" class="form-select">
                    <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="14" <?php echo $date_range == '14' ? 'selected' : ''; ?>>Last 14 Days</option>
                    <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="60" <?php echo $date_range == '60' ? 'selected' : ''; ?>>Last 60 Days</option>
                    <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <button type="submit" class="btn btn-primary">Apply Filter</button>
                  <a href="document-status.php" class="btn btn-secondary">Reset</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Urgent Alert -->
    <?php if ($urgentDocs['urgent_count'] > 0): ?>
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Urgent Action Required!</strong> You have <?php echo $urgentDocs['urgent_count']; ?> document(s) pending for more than 7 days.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Overview Statistics -->
    <section class="section">
      <div class="row">
        
        <!-- Total Documents -->
        <div class="col-xxl-3 col-md-6">
          <div class="card info-card stat-card">
            <div class="card-body">
              <h5 class="card-title">Total Documents <span>| Last <?php echo $date_range; ?> Days</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['total_documents']); ?></h6>
                  <span class="text-muted small pt-2">All statuses</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Documents -->
        <div class="col-xxl-3 col-md-6">
          <div class="card info-card stat-card">
            <div class="card-body">
              <h5 class="card-title">Pending Review <span>| <?php echo $pendingPercent; ?>%</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #fff4e6; color: #ffc107;">
                  <i class="bi bi-clock-history"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['pending']); ?></h6>
                  <span class="text-warning small pt-2">Needs attention</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Approved Documents -->
        <div class="col-xxl-3 col-md-6">
          <div class="card info-card stat-card">
            <div class="card-body">
              <h5 class="card-title">Approved <span>| <?php echo $approvedPercent; ?>%</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #e7f5e9; color: #28a745;">
                  <i class="bi bi-check-circle"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['approved']); ?></h6>
                  <span class="text-success small pt-2">Completed</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Rejected Documents -->
        <div class="col-xxl-3 col-md-6">
          <div class="card info-card stat-card">
            <div class="card-body">
              <h5 class="card-title">Rejected <span>| <?php echo $rejectedPercent; ?>%</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background: #fce8e8; color: #dc3545;">
                  <i class="bi bi-x-circle"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo number_format($stats['rejected']); ?></h6>
                  <span class="text-danger small pt-2">Not approved</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Additional Metrics -->
    <section class="section">
      <div class="row">
        
        <!-- Under Review -->
        <div class="col-xxl-3 col-md-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Under Review</h5>
              <div class="d-flex align-items-center">
                <div class="ps-2">
                  <h4 class="text-primary"><?php echo number_format($stats['under_review']); ?></h4>
                  <span class="text-muted small">Being processed</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Needs Revision -->
        <div class="col-xxl-3 col-md-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Needs Revision</h5>
              <div class="d-flex align-items-center">
                <div class="ps-2">
                  <h4 class="text-info"><?php echo number_format($stats['needs_revision']); ?></h4>
                  <span class="text-muted small">Awaiting client</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Avg Processing Time -->
        <div class="col-xxl-3 col-md-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Avg. Processing Time</h5>
              <div class="d-flex align-items-center">
                <div class="ps-2">
                  <h4><?php echo $processingTime['avg_hours'] ? round($processingTime['avg_hours'], 1) : '0'; ?> hrs</h4>
                  <span class="text-muted small">From upload to review</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Urgent Count -->
        <div class="col-xxl-3 col-md-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Urgent (>7 days)</h5>
              <div class="d-flex align-items-center">
                <div class="ps-2">
                  <h4 class="text-warning"><?php echo number_format($urgentDocs['urgent_count']); ?></h4>
                  <span class="text-muted small">Requires immediate action</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Status Overview Progress Bars -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Document Status Distribution</h5>
              
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Pending</span>
                  <span class="fw-bold"><?php echo $stats['pending']; ?> (<?php echo $pendingPercent; ?>%)</span>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $pendingPercent; ?>%" aria-valuenow="<?php echo $pendingPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Approved</span>
                  <span class="fw-bold"><?php echo $stats['approved']; ?> (<?php echo $approvedPercent; ?>%)</span>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $approvedPercent; ?>%" aria-valuenow="<?php echo $approvedPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Rejected</span>
                  <span class="fw-bold"><?php echo $stats['rejected']; ?> (<?php echo $rejectedPercent; ?>%)</span>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $rejectedPercent; ?>%" aria-valuenow="<?php echo $rejectedPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Document Aging and Recent Activity -->
    <section class="section">
      <div class="row">
        
        <!-- Document Aging -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Document Aging Analysis <span>| Pending Documents</span></h5>
              
              <div class="list-group">
                <?php if (empty($documentAging)): ?>
                  <div class="list-group-item text-center text-muted">
                    <i class="bi bi-check-circle me-2"></i>No pending documents
                  </div>
                <?php else: ?>
                  <?php foreach ($documentAging as $doc): ?>
                    <?php 
                      $agingClass = '';
                      if ($doc['days_pending'] > 14) {
                        $agingClass = 'critical';
                        $badgeClass = 'danger';
                      } elseif ($doc['days_pending'] > 7) {
                        $agingClass = 'urgent';
                        $badgeClass = 'warning';
                      } else {
                        $badgeClass = 'info';
                      }
                    ?>
                    <div class="list-group-item <?php echo $agingClass; ?>">
                      <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                          <h6 class="mb-1"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                          <p class="mb-1 small text-muted">
                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                            <span class="mx-2">|</span>
                            <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($doc['business_name']); ?>
                          </p>
                          <small class="text-muted">
                            <i class="bi bi-calendar3 me-1"></i>Uploaded: <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                          </small>
                        </div>
                        <div class="text-end">
                          <span class="badge bg-<?php echo $badgeClass; ?> aging-badge">
                            <?php echo $doc['days_pending']; ?> days
                          </span>
                          <br>
                          <small><span class="badge <?php echo getStatusBadgeClass($doc['status']); ?> mt-1">
                            <?php echo ucwords(str_replace('_', ' ', $doc['status'])); ?>
                          </span></small>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              
              <?php if (!empty($documentAging)): ?>
              <div class="text-center mt-3">
                <a href="all-documents.php?status=pending" class="btn btn-sm btn-outline-primary">View All Pending</a>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Recent Activity <span>| Latest Updates</span></h5>
              
              <div class="activity-timeline">
                <?php if (empty($recentActivity)): ?>
                  <p class="text-muted text-center">No recent activity</p>
                <?php else: ?>
                  <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                      <i class="activity-icon bi <?php echo getActivityIcon($activity['status']); ?>"></i>
                      <div>
                        <h6 class="mb-1">
                          <?php echo htmlspecialchars($activity['document_name']); ?>
                          <span class="badge <?php echo getStatusBadgeClass($activity['status']); ?> ms-2">
                            <?php echo ucwords(str_replace('_', ' ', $activity['status'])); ?>
                          </span>
                        </h6>
                        <p class="mb-0 small text-muted">
                          Client: <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                          <?php if ($activity['business_name']): ?>
                            | <?php echo htmlspecialchars($activity['business_name']); ?>
                          <?php endif; ?>
                        </p>
                        <?php if ($activity['reviewer_first_name']): ?>
                          <p class="mb-0 small text-muted">
                            Reviewed by: <?php echo htmlspecialchars($activity['reviewer_first_name'] . ' ' . $activity['reviewer_last_name']); ?>
                          </p>
                        <?php endif; ?>
                        <small class="text-muted">
                          <?php echo timeAgo($activity['reviewed_at'] ?: $activity['uploaded_at']); ?>
                        </small>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              
              <div class="text-center mt-3">
                <a href="all-documents.php" class="btn btn-sm btn-outline-primary">View All Documents</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Client Summary -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Top Clients by Document Volume <span>| Last <?php echo $date_range; ?> Days</span></h5>
              
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Client</th>
                      <th>Email</th>
                      <th class="text-center">Total</th>
                      <th class="text-center">Pending</th>
                      <th class="text-center">Approved</th>
                      <th class="text-center">Rejected</th>
                      <th>Last Upload</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($clientSummary)): ?>
                      <tr>
                        <td colspan="7" class="text-center text-muted">No client data available</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($clientSummary as $client): ?>
                        <tr>
                          <td><strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong></td>
                          <td><?php echo htmlspecialchars($client['email']); ?></td>
                          <td class="text-center"><span class="badge bg-primary"><?php echo $client['total_documents']; ?></span></td>
                          <td class="text-center">
                            <?php if ($client['pending'] > 0): ?>
                              <span class="badge bg-warning"><?php echo $client['pending']; ?></span>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <?php if ($client['approved'] > 0): ?>
                              <span class="badge bg-success"><?php echo $client['approved']; ?></span>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <?php if ($client['rejected'] > 0): ?>
                              <span class="badge bg-danger"><?php echo $client['rejected']; ?></span>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td><small><?php echo date('M d, Y', strtotime($client['last_upload'])); ?></small></td>
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

    <!-- Processing Time Details -->
    <?php if ($processingTime['reviewed_count'] > 0): ?>
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Processing Time Metrics <span>| Last <?php echo $date_range; ?> Days</span></h5>
              
              <div class="row">
                <div class="col-md-3">
                  <div class="text-center p-3">
                    <h6 class="text-muted">Average Time</h6>
                    <h3 class="text-primary"><?php echo round($processingTime['avg_hours'], 1); ?> hrs</h3>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center p-3">
                    <h6 class="text-muted">Fastest Review</h6>
                    <h3 class="text-success"><?php echo round($processingTime['min_hours'], 1); ?> hrs</h3>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center p-3">
                    <h6 class="text-muted">Slowest Review</h6>
                    <h3 class="text-danger"><?php echo round($processingTime['max_hours'], 1); ?> hrs</h3>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center p-3">
                    <h6 class="text-muted">Documents Reviewed</h6>
                    <h3><?php echo $processingTime['reviewed_count']; ?></h3>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <!-- Auto-refresh script -->
  <script>
    // Auto-refresh every 5 minutes
    setTimeout(function() {
      location.reload();
    }, 300000);

    // Show last updated time
    document.addEventListener('DOMContentLoaded', function() {
      const now = new Date();
      const timeString = now.toLocaleTimeString();
      console.log('Dashboard last updated:', timeString);
    });
  </script>

</body>

</html>

