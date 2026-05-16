<?php
/**
 * Meat Movement Management for ASF Surveillance System
 * Manages meat movement records for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Meat Movement';

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
$meatTypeFilter = isset($_GET['meat_type']) && $_GET['meat_type'] !== '' ? trim($_GET['meat_type']) : '';
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "(mm.source_city LIKE ? OR mm.destination_city LIKE ?)";
    $params[] = "%{$cityFilter}%";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "(mm.source_province = ? OR mm.destination_province = ?)";
    $params[] = $provinceFilter;
    $params[] = $provinceFilter;
}

if (!empty($statusFilter)) {
    $conditions[] = "mm.status = ?";
    $params[] = $statusFilter;
}

if (!empty($meatTypeFilter)) {
    $conditions[] = "mm.meat_type = ?";
    $params[] = $meatTypeFilter;
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(mm.movement_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(mm.movement_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get meat movement records
$meatMovements = [];
try {
    $sql = "SELECT mm.*, 
                   ua.first_name, ua.last_name
            FROM meat_movement mm 
            LEFT JOIN user_accounts ua ON mm.recorded_by = ua.id 
            {$whereClause}
            ORDER BY mm.movement_date DESC, mm.created_at DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meatMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching meat movements: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_movements' => 0,
    'in_transit' => 0,
    'completed' => 0,
    'total_quantity_kg' => 0,
    'total_quantity_heads' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM meat_movement");
    $stats['total_movements'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as in_transit FROM meat_movement WHERE status = 'in_transit'");
    $stats['in_transit'] = $stmt->fetch()['in_transit'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM meat_movement WHERE status = 'completed'");
    $stats['completed'] = $stmt->fetch()['completed'] ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(quantity_kg) as total FROM meat_movement WHERE quantity_kg IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_quantity_kg'] = round($result['total'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT SUM(quantity_heads) as total FROM meat_movement WHERE quantity_heads IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_quantity_heads'] = $result['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM (
                            SELECT source_city as city FROM meat_movement WHERE source_city IS NOT NULL
                            UNION
                            SELECT destination_city as city FROM meat_movement WHERE destination_city IS NOT NULL
                         ) as cities ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get chart data - movement trends by month
$chartData = [
    'labels' => [],
    'counts' => [],
    'status_breakdown' => [],
    'meat_type_breakdown' => []
];

try {
    $chartWhereParts = [];
    $chartParams = [];
    
    if (!empty($cityFilter)) {
        $chartWhereParts[] = "(source_city LIKE ? OR destination_city LIKE ?)";
        $chartParams[] = "%{$cityFilter}%";
        $chartParams[] = "%{$cityFilter}%";
    }
    
    if (!empty($provinceFilter)) {
        $chartWhereParts[] = "(source_province = ? OR destination_province = ?)";
        $chartParams[] = $provinceFilter;
        $chartParams[] = $provinceFilter;
    }
    
    if (!empty($dateFrom)) {
        $chartWhereParts[] = "DATE(movement_date) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(movement_date) <= ?";
        $chartParams[] = $dateTo;
    }
    
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    $chartSql = "SELECT DATE_FORMAT(movement_date, '%Y-%m') as month, COUNT(*) as count
                 FROM meat_movement 
                 {$whereClause}
                 GROUP BY DATE_FORMAT(movement_date, '%Y-%m')
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
    $statusSql = "SELECT status, COUNT(*) as count FROM meat_movement {$whereClause} GROUP BY status";
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
    
    // Meat type breakdown
    $meatTypeSql = "SELECT meat_type, COUNT(*) as count FROM meat_movement {$whereClause} GROUP BY meat_type";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($meatTypeSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($meatTypeSql);
    }
    
    $meatTypeResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($meatTypeResults as $row) {
        $chartData['meat_type_breakdown'][$row['meat_type']] = intval($row['count']);
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}

function getStatusBadge($status) {
    $badges = [
        'in_transit' => 'warning',
        'completed' => 'success',
        'rejected' => 'danger',
        'quarantined' => 'info'
    ];
    return $badges[$status] ?? 'secondary';
}

function getMeatTypeBadge($meatType) {
    $badges = [
        'fresh' => 'success',
        'frozen' => 'info',
        'processed' => 'primary',
        'live_animal' => 'warning'
    ];
    return $badges[$meatType] ?? 'secondary';
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Meat Movement</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Meat Movement</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Movements</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                <i class="bi bi-truck"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_movements']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">In Transit</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-hourglass-split"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['in_transit']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Completed</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['completed']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Quantity</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                <i class="bi bi-box-seam"></i>
              </div>
              <div class="ps-3">
                <h6>
                  <?php if ($stats['total_quantity_kg'] > 0): ?>
                    <?php echo number_format($stats['total_quantity_kg'], 0); ?> kg
                  <?php elseif ($stats['total_quantity_heads'] > 0): ?>
                    <?php echo number_format($stats['total_quantity_heads']); ?> heads
                  <?php else: ?>
                    0
                  <?php endif; ?>
                </h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Movement Trends</h5>
            <?php if (empty($chartData['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-bar-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Chart Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="movementTrendChart" style="max-height: 300px;"></canvas>
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
            <h5 class="card-title">Meat Movement Records</h5>
            
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
                      <option value="in_transit" <?php echo $statusFilter === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                      <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                      <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                      <option value="quarantined" <?php echo $statusFilter === 'quarantined' ? 'selected' : ''; ?>>Quarantined</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="meatTypeFilter" class="form-label">Meat Type</label>
                    <select class="form-select form-select-sm" id="meatTypeFilter" name="meat_type">
                      <option value="">All Types</option>
                      <option value="fresh" <?php echo $meatTypeFilter === 'fresh' ? 'selected' : ''; ?>>Fresh</option>
                      <option value="frozen" <?php echo $meatTypeFilter === 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                      <option value="processed" <?php echo $meatTypeFilter === 'processed' ? 'selected' : ''; ?>>Processed</option>
                      <option value="live_animal" <?php echo $meatTypeFilter === 'live_animal' ? 'selected' : ''; ?>>Live Animal</option>
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
                    <a href="meat-movement.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($meatMovements)): ?>
              <div class="text-center py-5">
                <i class="bi bi-truck" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No meat movement records found</h5>
                <p class="text-muted">No movement records match the selected filters.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="meatMovementTable">
                  <thead>
                    <tr>
                      <th>Movement Code</th>
                      <th>Source</th>
                      <th>Destination</th>
                      <th>Movement Date</th>
                      <th>Meat Type</th>
                      <th>Quantity</th>
                      <th>Status</th>
                      <th>Recorded By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($meatMovements as $movement): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($movement['movement_code']); ?></strong></td>
                        <td>
                          <strong><?php echo htmlspecialchars($movement['source_location']); ?></strong><br>
                          <small class="text-muted">
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo htmlspecialchars($movement['source_city']); ?>, <?php echo htmlspecialchars($movement['source_province']); ?>
                          </small>
                        </td>
                        <td>
                          <strong><?php echo htmlspecialchars($movement['destination_location']); ?></strong><br>
                          <small class="text-muted">
                            <i class="bi bi-geo-alt-fill"></i> 
                            <?php echo htmlspecialchars($movement['destination_city']); ?>, <?php echo htmlspecialchars($movement['destination_province']); ?>
                          </small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($movement['movement_date'])); ?></td>
                        <td>
                          <span class="badge bg-<?php echo getMeatTypeBadge($movement['meat_type']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $movement['meat_type'])); ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($movement['quantity_kg'] !== null): ?>
                            <strong><?php echo number_format($movement['quantity_kg'], 2); ?> kg</strong>
                          <?php elseif ($movement['quantity_heads'] !== null): ?>
                            <strong><?php echo number_format($movement['quantity_heads']); ?> heads</strong>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getStatusBadge($movement['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $movement['status'])); ?>
                          </span>
                        </td>
                        <td>
                          <?php echo htmlspecialchars(($movement['first_name'] ?? '') . ' ' . ($movement['last_name'] ?? 'Unknown')); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewMovementDetails(<?php echo $movement['id']; ?>)">
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

<!-- Movement Details Modal -->
<div class="modal fade" id="movementDetailsModal" tabindex="-1" aria-labelledby="movementDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="movementDetailsModalLabel">Meat Movement Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="movementDetailsContent">
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
  const meatMovementTable = document.getElementById('meatMovementTable');
  if (meatMovementTable) {
    new simpleDatatables.DataTable(meatMovementTable, {
      "pageLength": 25,
      "order": [[3, "desc"]],
      "responsive": true
    });
  }
  
  // Initialize Charts
  const trendCtx = document.getElementById('movementTrendChart');
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
          label: 'Movements',
          data: trendData.counts,
          borderColor: 'rgb(13, 110, 253)',
          backgroundColor: 'rgba(13, 110, 253, 0.1)',
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
      'in_transit': 'rgb(255, 193, 7)',
      'completed': 'rgb(25, 135, 84)',
      'rejected': 'rgb(220, 53, 69)',
      'quarantined': 'rgb(13, 110, 253)'
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

function viewMovementDetails(movementId) {
  const modal = new bootstrap.Modal(document.getElementById('movementDetailsModal'));
  const content = document.getElementById('movementDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_meat_movement_details.php?movement_id=${movementId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const mm = data.movement;
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Source Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Location:</strong></td><td>${mm.source_location || 'N/A'}</td></tr>
                <tr><td><strong>Province:</strong></td><td>${mm.source_province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${mm.source_city || 'N/A'}</td></tr>
                <tr><td><strong>Coordinates:</strong></td><td>${mm.source_latitude ? parseFloat(mm.source_latitude).toFixed(6) : 'N/A'}, ${mm.source_longitude ? parseFloat(mm.source_longitude).toFixed(6) : 'N/A'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Destination Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Location:</strong></td><td>${mm.destination_location || 'N/A'}</td></tr>
                <tr><td><strong>Province:</strong></td><td>${mm.destination_province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${mm.destination_city || 'N/A'}</td></tr>
                <tr><td><strong>Coordinates:</strong></td><td>${mm.destination_latitude ? parseFloat(mm.destination_latitude).toFixed(6) : 'N/A'}, ${mm.destination_longitude ? parseFloat(mm.destination_longitude).toFixed(6) : 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Movement Details</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Movement Code:</strong></td><td>${mm.movement_code || 'N/A'}</td></tr>
                <tr><td><strong>Movement Date:</strong></td><td>${formatDate(mm.movement_date)}</td></tr>
                <tr><td><strong>Meat Type:</strong></td><td><span class="badge bg-${getMeatTypeColor(mm.meat_type)}">${mm.meat_type ? mm.meat_type.toUpperCase().replace('_', ' ') : 'N/A'}</span></td></tr>
                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getStatusColor(mm.status)}">${mm.status ? mm.status.toUpperCase().replace('_', ' ') : 'N/A'}</span></td></tr>
                <tr><td><strong>Quantity:</strong></td><td>${mm.quantity_kg ? parseFloat(mm.quantity_kg).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg' : (mm.quantity_heads ? parseInt(mm.quantity_heads).toLocaleString() + ' heads' : 'N/A')}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Transport Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Vehicle:</strong></td><td>${mm.transport_vehicle || 'N/A'}</td></tr>
                <tr><td><strong>Registration:</strong></td><td>${mm.transport_registration || 'N/A'}</td></tr>
                <tr><td><strong>Driver Name:</strong></td><td>${mm.driver_name || 'N/A'}</td></tr>
                <tr><td><strong>Driver License:</strong></td><td>${mm.driver_license || 'N/A'}</td></tr>
                <tr><td><strong>Health Certificate:</strong></td><td>${mm.health_certificate_number || 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Certificate Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Issuing Authority:</strong></td><td>${mm.certificate_issuing_authority || 'N/A'}</td></tr>
                <tr><td><strong>Issue Date:</strong></td><td>${mm.certificate_issue_date ? formatDate(mm.certificate_issue_date) : 'N/A'}</td></tr>
                <tr><td><strong>Expiry Date:</strong></td><td>${mm.certificate_expiry_date ? formatDate(mm.certificate_expiry_date) : 'N/A'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Record Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Recorded By:</strong></td><td>${mm.first_name || ''} ${mm.last_name || 'Unknown'}</td></tr>
                <tr><td><strong>Created At:</strong></td><td>${formatDateTime(mm.created_at)}</td></tr>
                <tr><td><strong>Updated At:</strong></td><td>${formatDateTime(mm.updated_at)}</td></tr>
              </table>
            </div>
          </div>
          ${mm.checkpoints_passed ? '<div class="mt-3"><h6 class="fw-bold">Checkpoints Passed</h6><p>' + escapeHtml(mm.checkpoints_passed) + '</p></div>' : ''}
          ${mm.notes ? '<div class="mt-3"><h6 class="fw-bold">Notes</h6><p>' + escapeHtml(mm.notes) + '</p></div>' : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load movement details</div>';
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
    'in_transit': 'warning',
    'completed': 'success',
    'rejected': 'danger',
    'quarantined': 'info'
  };
  return colors[status] || 'secondary';
}

function getMeatTypeColor(meatType) {
  const colors = {
    'fresh': 'success',
    'frozen': 'info',
    'processed': 'primary',
    'live_animal': 'warning'
  };
  return colors[meatType] || 'secondary';
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
