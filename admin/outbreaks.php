<?php
/**
 * ASF Outbreaks Management for ASF Surveillance System
 * Manages ASF outbreak records for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'ASF Outbreaks';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$cityFilter = isset($_GET['city']) && $_GET['city'] !== '' ? trim($_GET['city']) : '';
$provinceFilter = isset($_GET['province']) && $_GET['province'] !== '' ? trim($_GET['province']) : 'CALABARZON';
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "ob.city LIKE ?";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "ob.province = ?";
    $params[] = $provinceFilter;
}

if (!empty($statusFilter)) {
    $conditions[] = "ob.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(ob.outbreak_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(ob.outbreak_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get outbreak records
$outbreaks = [];
try {
    $sql = "SELECT ob.*, 
                   ua_reported.first_name as reported_first_name, ua_reported.last_name as reported_last_name,
                   ua_confirmed.first_name as confirmed_first_name, ua_confirmed.last_name as confirmed_last_name
            FROM asf_outbreaks ob 
            LEFT JOIN user_accounts ua_reported ON ob.reported_by = ua_reported.id 
            LEFT JOIN user_accounts ua_confirmed ON ob.confirmed_by = ua_confirmed.id 
            {$whereClause}
            ORDER BY ob.reported_date DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching outbreaks: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_outbreaks' => 0,
    'active_outbreaks' => 0,
    'confirmed_outbreaks' => 0,
    'total_pigs_affected' => 0,
    'total_pigs_depopulated' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM asf_outbreaks");
    $stats['total_outbreaks'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM asf_outbreaks WHERE status IN ('suspected', 'confirmed', 'contained')");
    $stats['active_outbreaks'] = $stmt->fetch()['active'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as confirmed FROM asf_outbreaks WHERE status = 'confirmed'");
    $stats['confirmed_outbreaks'] = $stmt->fetch()['confirmed'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(total_pigs_affected) as total FROM asf_outbreaks WHERE total_pigs_affected IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_pigs_affected'] = $result['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(total_pigs_depopulated) as total FROM asf_outbreaks WHERE total_pigs_depopulated IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_pigs_depopulated'] = $result['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM asf_outbreaks WHERE city IS NOT NULL ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get chart data - outbreak trends by month
$chartData = [
    'labels' => [],
    'counts' => [],
    'status_breakdown' => []
];

try {
    $chartWhereParts = [];
    $chartParams = [];
    
    if (!empty($cityFilter)) {
        $chartWhereParts[] = "city LIKE ?";
        $chartParams[] = "%{$cityFilter}%";
    }
    
    if (!empty($provinceFilter)) {
        $chartWhereParts[] = "province = ?";
        $chartParams[] = $provinceFilter;
    }
    
    if (!empty($dateFrom)) {
        $chartWhereParts[] = "DATE(outbreak_date) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(outbreak_date) <= ?";
        $chartParams[] = $dateTo;
    }
    
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    $chartSql = "SELECT DATE_FORMAT(outbreak_date, '%Y-%m') as month, COUNT(*) as count
                 FROM asf_outbreaks 
                 {$whereClause}
                 GROUP BY DATE_FORMAT(outbreak_date, '%Y-%m')
                 ORDER BY month ASC
                 LIMIT 24";
    
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($chartSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($chartSql);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $chartData['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
        $chartData['counts'][] = intval($row['count']);
    }
    
    // Status breakdown
    $statusSql = "SELECT status, COUNT(*) as count FROM asf_outbreaks {$whereClause} GROUP BY status";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($statusSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($statusSql);
    }
    
    $statusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusResults as $row) {
        $chartData['status_breakdown'][$row['status']] = intval($row['count']);
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}

function getStatusBadge($status) {
    $badges = [
        'suspected' => 'warning',
        'confirmed' => 'danger',
        'contained' => 'info',
        'resolved' => 'success',
        'false_alarm' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}

function getSeverityBadge($severity) {
    $badges = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    return $badges[$severity] ?? 'secondary';
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>ASF Outbreaks</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">ASF Outbreaks</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Outbreaks</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_outbreaks']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Active Outbreaks</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-exclamation-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['active_outbreaks']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Confirmed Outbreaks</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['confirmed_outbreaks']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Pigs Affected</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                <i class="bi bi-piggy-bank"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_pigs_affected']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Outbreak Trends</h5>
            <?php if (empty($chartData['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-bar-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Chart Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="outbreakTrendChart" style="max-height: 300px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Status Breakdown</h5>
            <?php if (empty($chartData['status_breakdown'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-pie-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="statusBreakdownChart" style="max-height: 300px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Data Table -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Outbreak Records</h5>
            
            <!-- Filters -->
            <div class="row mb-3">
              <div class="col-md-12">
                <form method="GET" class="row g-3">
                  <div class="col-md-2">
                    <label for="provinceFilter" class="form-label">Province</label>
                    <select class="form-select form-select-sm" id="provinceFilter" name="province">
                      <option value="">All Provinces</option>
                      <option value="CALABARZON" <?php echo $provinceFilter === 'CALABARZON' ? 'selected' : ''; ?>>CALABARZON</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="cityFilter" class="form-label">City</label>
                    <select class="form-select form-select-sm" id="cityFilter" name="city">
                      <option value="">All Cities</option>
                      <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $cityFilter === $city ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($city); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="statusFilter" name="status">
                      <option value="">All Status</option>
                      <option value="suspected" <?php echo $statusFilter === 'suspected' ? 'selected' : ''; ?>>Suspected</option>
                      <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                      <option value="contained" <?php echo $statusFilter === 'contained' ? 'selected' : ''; ?>>Contained</option>
                      <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                      <option value="false_alarm" <?php echo $statusFilter === 'false_alarm' ? 'selected' : ''; ?>>False Alarm</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="dateFrom" class="form-label">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="dateFrom" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                  </div>
                  <div class="col-md-2">
                    <label for="dateTo" class="form-label">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="dateTo" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                  </div>
                  <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2">
                      <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="outbreaks.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($outbreaks)): ?>
              <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No outbreaks found</h5>
                <p class="text-muted">No outbreak records match the selected filters.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="outbreaksTable">
                  <thead>
                    <tr>
                      <th>Outbreak Code</th>
                      <th>Location</th>
                      <th>City</th>
                      <th>Farm Type</th>
                      <th>Outbreak Date</th>
                      <th>Status</th>
                      <th>Severity</th>
                      <th>Pigs Affected</th>
                      <th>Reported By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($outbreaks as $outbreak): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($outbreak['outbreak_code']); ?></strong></td>
                        <td>
                          <strong><?php echo htmlspecialchars($outbreak['location_name']); ?></strong><br>
                          <?php if ($outbreak['farm_name']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($outbreak['farm_name']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($outbreak['city']); ?></td>
                        <td>
                          <?php if ($outbreak['farm_type']): ?>
                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $outbreak['farm_type'])); ?></span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($outbreak['outbreak_date'])); ?></td>
                        <td>
                          <span class="badge bg-<?php echo getStatusBadge($outbreak['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $outbreak['status'])); ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($outbreak['severity_level']): ?>
                            <span class="badge bg-<?php echo getSeverityBadge($outbreak['severity_level']); ?>">
                              <?php echo ucfirst($outbreak['severity_level']); ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <strong><?php echo number_format($outbreak['total_pigs_affected']); ?></strong>
                          <?php if ($outbreak['total_pigs_mortality'] > 0): ?>
                            <br><small class="text-danger">Mortality: <?php echo number_format($outbreak['total_pigs_mortality']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php echo htmlspecialchars(($outbreak['reported_first_name'] ?? '') . ' ' . ($outbreak['reported_last_name'] ?? 'Unknown')); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewOutbreakDetails(<?php echo $outbreak['id']; ?>)">
                            <i class="bi bi-eye"></i>
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

<!-- Outbreak Details Modal -->
<div class="modal fade" id="outbreakDetailsModal" tabindex="-1" aria-labelledby="outbreakDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="outbreakDetailsModalLabel">Outbreak Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="outbreakDetailsContent">
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

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const outbreaksTable = document.getElementById('outbreaksTable');
  if (outbreaksTable) {
    new simpleDatatables.DataTable(outbreaksTable, {
      "pageLength": 25,
      "order": [[4, "desc"]],
      "responsive": true
    });
  }
  
  // Initialize Charts
  const trendCtx = document.getElementById('outbreakTrendChart');
  if (trendCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['labels']); ?>,
      counts: <?php echo json_encode($chartData['counts']); ?>
    };
    
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData.labels,
        datasets: [{
          label: 'Outbreaks',
          data: trendData.counts,
          borderColor: 'rgb(220, 53, 69)',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });
  }
  
  const statusCtx = document.getElementById('statusBreakdownChart');
  if (statusCtx) {
    const statusData = <?php echo json_encode($chartData['status_breakdown']); ?>;
    const colors = {
      'suspected': 'rgb(255, 193, 7)',
      'confirmed': 'rgb(220, 53, 69)',
      'contained': 'rgb(13, 110, 253)',
      'resolved': 'rgb(25, 135, 84)',
      'false_alarm': 'rgb(108, 117, 125)'
    };
    
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(statusData).map(s => s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' ')),
        datasets: [{
          data: Object.values(statusData),
          backgroundColor: Object.keys(statusData).map(s => colors[s] || 'rgb(108, 117, 125)')
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }
});

function viewOutbreakDetails(outbreakId) {
  const modal = new bootstrap.Modal(document.getElementById('outbreakDetailsModal'));
  const content = document.getElementById('outbreakDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_outbreak_details.php?outbreak_id=${outbreakId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const ob = data.outbreak;
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Outbreak Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Outbreak Code:</strong></td><td>${ob.outbreak_code || 'N/A'}</td></tr>
                <tr><td><strong>Location:</strong></td><td>${ob.location_name || 'N/A'}</td></tr>
                <tr><td><strong>Farm Name:</strong></td><td>${ob.farm_name || 'N/A'}</td></tr>
                <tr><td><strong>Farm Type:</strong></td><td>${ob.farm_type ? ob.farm_type.replace('_', ' ').toUpperCase() : 'N/A'}</td></tr>
                <tr><td><strong>Province:</strong></td><td>${ob.province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${ob.city || 'N/A'}</td></tr>
                <tr><td><strong>Barangay:</strong></td><td>${ob.barangay || 'N/A'}</td></tr>
                <tr><td><strong>Coordinates:</strong></td><td>${ob.latitude ? parseFloat(ob.latitude).toFixed(6) : 'N/A'}, ${ob.longitude ? parseFloat(ob.longitude).toFixed(6) : 'N/A'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Outbreak Details</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Outbreak Date:</strong></td><td>${formatDate(ob.outbreak_date)}</td></tr>
                <tr><td><strong>Reported Date:</strong></td><td>${formatDateTime(ob.reported_date)}</td></tr>
                <tr><td><strong>Confirmed Date:</strong></td><td>${ob.confirmed_date ? formatDateTime(ob.confirmed_date) : 'N/A'}</td></tr>
                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getStatusColor(ob.status)}">${ob.status ? ob.status.toUpperCase().replace('_', ' ') : 'N/A'}</span></td></tr>
                <tr><td><strong>Severity:</strong></td><td>${ob.severity_level ? '<span class="badge bg-' + getSeverityColor(ob.severity_level) + '">' + ob.severity_level.toUpperCase() + '</span>' : 'N/A'}</td></tr>
                <tr><td><strong>Lab Confirmed:</strong></td><td>${ob.laboratory_confirmed ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Impact</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Pigs Affected:</strong></td><td>${ob.total_pigs_affected ? parseInt(ob.total_pigs_affected).toLocaleString() : '0'}</td></tr>
                <tr><td><strong>Pigs Mortality:</strong></td><td>${ob.total_pigs_mortality ? parseInt(ob.total_pigs_mortality).toLocaleString() : '0'}</td></tr>
                <tr><td><strong>Pigs Depopulated:</strong></td><td>${ob.total_pigs_depopulated ? parseInt(ob.total_pigs_depopulated).toLocaleString() : '0'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Additional Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Reported By:</strong></td><td>${ob.reported_first_name || ''} ${ob.reported_last_name || 'Unknown'}</td></tr>
                <tr><td><strong>Confirmed By:</strong></td><td>${ob.confirmed_first_name ? ob.confirmed_first_name + ' ' + ob.confirmed_last_name : 'N/A'}</td></tr>
                <tr><td><strong>Source of Infection:</strong></td><td>${ob.source_of_infection || 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          ${ob.notes ? '<div class="mt-3"><h6 class="fw-bold">Notes</h6><p>' + escapeHtml(ob.notes) + '</p></div>' : ''}
          ${ob.clinical_signs ? '<div class="mt-3"><h6 class="fw-bold">Clinical Signs</h6><p>' + escapeHtml(ob.clinical_signs) + '</p></div>' : ''}
          ${ob.containment_measures ? '<div class="mt-3"><h6 class="fw-bold">Containment Measures</h6><p>' + escapeHtml(ob.containment_measures) + '</p></div>' : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load outbreak details</div>';
    });
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getStatusColor(status) {
  const colors = {
    'suspected': 'warning',
    'confirmed': 'danger',
    'contained': 'info',
    'resolved': 'success',
    'false_alarm': 'secondary'
  };
  return colors[status] || 'secondary';
}

function getSeverityColor(severity) {
  const colors = {
    'low': 'success',
    'medium': 'warning',
    'high': 'danger',
    'critical': 'dark'
  };
  return colors[severity] || 'secondary';
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
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
