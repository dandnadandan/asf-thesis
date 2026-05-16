<?php
/**
 * Depopulation Events Management for ASF Surveillance System
 * Manages depopulation event records for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Depopulation Events';

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
$methodFilter = isset($_GET['method']) && $_GET['method'] !== '' ? trim($_GET['method']) : '';
$compensationFilter = isset($_GET['compensation_status']) && $_GET['compensation_status'] !== '' ? trim($_GET['compensation_status']) : '';
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "de.city LIKE ?";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "de.province = ?";
    $params[] = $provinceFilter;
}

if (!empty($methodFilter)) {
    $conditions[] = "de.depopulation_method = ?";
    $params[] = $methodFilter;
}

if (!empty($compensationFilter)) {
    $conditions[] = "de.compensation_status = ?";
    $params[] = $compensationFilter;
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(de.event_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(de.event_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get depopulation event records
$depopulationEvents = [];
try {
    $sql = "SELECT de.*, 
                   ua_created.first_name as created_first_name, ua_created.last_name as created_last_name,
                   ua_supervised.first_name as supervised_first_name, ua_supervised.last_name as supervised_last_name,
                   ob.outbreak_code
            FROM depopulation_events de 
            LEFT JOIN user_accounts ua_created ON de.created_by = ua_created.id 
            LEFT JOIN user_accounts ua_supervised ON de.supervised_by = ua_supervised.id 
            LEFT JOIN asf_outbreaks ob ON de.outbreak_id = ob.id
            {$whereClause}
            ORDER BY de.event_date DESC, de.created_at DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $depopulationEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching depopulation events: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_events' => 0,
    'total_head_count' => 0,
    'total_compensation' => 0,
    'avg_head_count' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM depopulation_events");
    $stats['total_events'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(head_count) as total FROM depopulation_events WHERE head_count IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_head_count'] = $result['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(compensation_amount) as total FROM depopulation_events WHERE compensation_amount IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_compensation'] = round($result['total'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT AVG(head_count) as avg FROM depopulation_events WHERE head_count IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_head_count'] = round($result['avg'] ?? 0, 0);
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM depopulation_events WHERE city IS NOT NULL ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get chart data - depopulation trends by month
$chartData = [
    'labels' => [],
    'counts' => [],
    'head_counts' => [],
    'method_breakdown' => [],
    'compensation_breakdown' => []
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
        $chartWhereParts[] = "DATE(event_date) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(event_date) <= ?";
        $chartParams[] = $dateTo;
    }
    
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    $chartSql = "SELECT DATE_FORMAT(event_date, '%Y-%m') as month, 
                        COUNT(*) as count,
                        SUM(head_count) as total_heads
                 FROM depopulation_events 
                 {$whereClause}
                 GROUP BY DATE_FORMAT(event_date, '%Y-%m')
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
        $chartData['head_counts'][] = intval($row['total_heads'] ?? 0);
    }
    
    // Method breakdown
    $methodSql = "SELECT depopulation_method, COUNT(*) as count FROM depopulation_events {$whereClause} GROUP BY depopulation_method";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($methodSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($methodSql);
    }
    
    $methodResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($methodResults as $row) {
        $chartData['method_breakdown'][$row['depopulation_method']] = intval($row['count']);
    }
    
    // Compensation status breakdown
    $compensationSql = "SELECT compensation_status, COUNT(*) as count FROM depopulation_events {$whereClause} WHERE compensation_status IS NOT NULL GROUP BY compensation_status";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($compensationSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($compensationSql);
    }
    
    $compensationResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($compensationResults as $row) {
        $chartData['compensation_breakdown'][$row['compensation_status']] = intval($row['count']);
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}

function getMethodBadge($method) {
    $badges = [
        'culling' => 'danger',
        'humane_euthanasia' => 'info',
        'other' => 'secondary'
    ];
    return $badges[$method] ?? 'secondary';
}

function getCompensationBadge($status) {
    $badges = [
        'pending' => 'warning',
        'approved' => 'info',
        'paid' => 'success',
        'denied' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Depopulation Events</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Depopulation Events</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Events</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-file-earmark-medical"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_events']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Head Count</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-piggy-bank"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_head_count']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Head Count</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                <i class="bi bi-graph-up"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_head_count']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Compensation</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-cash-stack"></i>
              </div>
              <div class="ps-3">
                <h6>₱<?php echo number_format($stats['total_compensation'], 2); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Depopulation Trends</h5>
            <?php if (empty($chartData['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-bar-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Chart Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="depopulationTrendChart" style="max-height: 300px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Method Breakdown</h5>
            <?php if (empty($chartData['method_breakdown'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-pie-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="methodBreakdownChart" style="max-height: 300px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Data Table -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Depopulation Event Records</h5>
            
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
                    <label for="methodFilter" class="form-label">Method</label>
                    <select class="form-select form-select-sm" id="methodFilter" name="method">
                      <option value="">All Methods</option>
                      <option value="culling" <?php echo $methodFilter === 'culling' ? 'selected' : ''; ?>>Culling</option>
                      <option value="humane_euthanasia" <?php echo $methodFilter === 'humane_euthanasia' ? 'selected' : ''; ?>>Humane Euthanasia</option>
                      <option value="other" <?php echo $methodFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="compensationFilter" class="form-label">Compensation</label>
                    <select class="form-select form-select-sm" id="compensationFilter" name="compensation_status">
                      <option value="">All Status</option>
                      <option value="pending" <?php echo $compensationFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                      <option value="approved" <?php echo $compensationFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                      <option value="paid" <?php echo $compensationFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                      <option value="denied" <?php echo $compensationFilter === 'denied' ? 'selected' : ''; ?>>Denied</option>
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
                  <div class="col-md-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2">
                      <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="depopulation-events.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($depopulationEvents)): ?>
              <div class="text-center py-5">
                <i class="bi bi-file-earmark-medical" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No depopulation events found</h5>
                <p class="text-muted">No depopulation event records match the selected filters.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="depopulationEventsTable">
                  <thead>
                    <tr>
                      <th>Event Code</th>
                      <th>Location</th>
                      <th>City</th>
                      <th>Event Date</th>
                      <th>Head Count</th>
                      <th>Method</th>
                      <th>Compensation</th>
                      <th>Created By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($depopulationEvents as $event): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($event['event_code']); ?></strong></td>
                        <td>
                          <strong><?php echo htmlspecialchars($event['location_name']); ?></strong><br>
                          <?php if ($event['farm_name']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($event['farm_name']); ?></small>
                          <?php endif; ?>
                          <?php if ($event['outbreak_code']): ?>
                            <br><small class="text-info"><i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($event['outbreak_code']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($event['city']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                        <td>
                          <strong><?php echo number_format($event['head_count']); ?></strong>
                          <?php if ($event['age_category']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($event['age_category']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getMethodBadge($event['depopulation_method']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $event['depopulation_method'])); ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($event['compensation_amount']): ?>
                            <strong>₱<?php echo number_format($event['compensation_amount'], 2); ?></strong><br>
                            <span class="badge bg-<?php echo getCompensationBadge($event['compensation_status']); ?>">
                              <?php echo ucfirst($event['compensation_status'] ?? 'N/A'); ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php echo htmlspecialchars(($event['created_first_name'] ?? '') . ' ' . ($event['created_last_name'] ?? 'Unknown')); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewEventDetails(<?php echo $event['id']; ?>)">
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

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventDetailsModalLabel">Depopulation Event Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="eventDetailsContent">
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
  const depopulationEventsTable = document.getElementById('depopulationEventsTable');
  if (depopulationEventsTable) {
    new simpleDatatables.DataTable(depopulationEventsTable, {
      "pageLength": 25,
      "order": [[3, "desc"]],
      "responsive": true
    });
  }
  
  // Initialize Charts
  const trendCtx = document.getElementById('depopulationTrendChart');
  if (trendCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['labels']); ?>,
      counts: <?php echo json_encode($chartData['counts']); ?>,
      headCounts: <?php echo json_encode($chartData['head_counts']); ?>
    };
    
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData.labels,
        datasets: [{
          label: 'Events',
          data: trendData.counts,
          borderColor: 'rgb(220, 53, 69)',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y'
        }, {
          label: 'Head Count',
          data: trendData.headCounts,
          borderColor: 'rgb(255, 193, 7)',
          backgroundColor: 'rgba(255, 193, 7, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            display: true,
            position: 'top'
          }
        },
        scales: {
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Events'
            },
            beginAtZero: true
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Head Count'
            },
            beginAtZero: true,
            grid: {
              drawOnChartArea: false,
            },
          }
        }
      }
    });
  }
  
  const methodCtx = document.getElementById('methodBreakdownChart');
  if (methodCtx) {
    const methodData = <?php echo json_encode($chartData['method_breakdown']); ?>;
    const colors = {
      'culling': 'rgb(220, 53, 69)',
      'humane_euthanasia': 'rgb(13, 110, 253)',
      'other': 'rgb(108, 117, 125)'
    };
    
    new Chart(methodCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(methodData).map(m => m.charAt(0).toUpperCase() + m.slice(1).replace('_', ' ')),
        datasets: [{
          data: Object.values(methodData),
          backgroundColor: Object.keys(methodData).map(m => colors[m] || 'rgb(108, 117, 125)')
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

function viewEventDetails(eventId) {
  const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
  const content = document.getElementById('eventDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_depopulation_details.php?event_id=${eventId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const ev = data.event;
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Event Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Event Code:</strong></td><td>${ev.event_code || 'N/A'}</td></tr>
                <tr><td><strong>Location:</strong></td><td>${ev.location_name || 'N/A'}</td></tr>
                <tr><td><strong>Farm Name:</strong></td><td>${ev.farm_name || 'N/A'}</td></tr>
                <tr><td><strong>Province:</strong></td><td>${ev.province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${ev.city || 'N/A'}</td></tr>
                <tr><td><strong>Barangay:</strong></td><td>${ev.barangay || 'N/A'}</td></tr>
                <tr><td><strong>Coordinates:</strong></td><td>${ev.latitude ? parseFloat(ev.latitude).toFixed(6) : 'N/A'}, ${ev.longitude ? parseFloat(ev.longitude).toFixed(6) : 'N/A'}</td></tr>
                ${ev.outbreak_code ? '<tr><td><strong>Related Outbreak:</strong></td><td><span class="badge bg-info">' + ev.outbreak_code + '</span></td></tr>' : ''}
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Depopulation Details</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Event Date:</strong></td><td>${formatDate(ev.event_date)}</td></tr>
                <tr><td><strong>Head Count:</strong></td><td><strong>${ev.head_count ? parseInt(ev.head_count).toLocaleString() : '0'}</strong></td></tr>
                <tr><td><strong>Age Category:</strong></td><td>${ev.age_category || 'N/A'}</td></tr>
                <tr><td><strong>Method:</strong></td><td><span class="badge bg-${getMethodColor(ev.depopulation_method)}">${ev.depopulation_method ? ev.depopulation_method.toUpperCase().replace('_', ' ') : 'N/A'}</span></td></tr>
                <tr><td><strong>Disposal Method:</strong></td><td>${ev.disposal_method || 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Compensation Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Amount:</strong></td><td>${ev.compensation_amount ? '₱' + parseFloat(ev.compensation_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'N/A'}</td></tr>
                <tr><td><strong>Status:</strong></td><td>${ev.compensation_status ? '<span class="badge bg-' + getCompensationColor(ev.compensation_status) + '">' + ev.compensation_status.toUpperCase() + '</span>' : 'N/A'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Personnel Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Created By:</strong></td><td>${ev.created_first_name || ''} ${ev.created_last_name || 'Unknown'}</td></tr>
                <tr><td><strong>Supervised By:</strong></td><td>${ev.supervised_first_name ? ev.supervised_first_name + ' ' + ev.supervised_last_name : 'N/A'}</td></tr>
                <tr><td><strong>Conducted By:</strong></td><td>${ev.conducted_by || 'N/A'}</td></tr>
                <tr><td><strong>Created At:</strong></td><td>${formatDateTime(ev.created_at)}</td></tr>
              </table>
            </div>
          </div>
          ${ev.notes ? '<div class="mt-3"><h6 class="fw-bold">Notes</h6><p>' + escapeHtml(ev.notes) + '</p></div>' : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load event details</div>';
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

function getMethodColor(method) {
  const colors = {
    'culling': 'danger',
    'humane_euthanasia': 'info',
    'other': 'secondary'
  };
  return colors[method] || 'secondary';
}

function getCompensationColor(status) {
  const colors = {
    'pending': 'warning',
    'approved': 'info',
    'paid': 'success',
    'denied': 'danger'
  };
  return colors[status] || 'secondary';
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
