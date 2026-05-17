<?php
/**
 * Risk Zones Management for ASF Surveillance System
 * Manages high-risk zones for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Risk Zones';

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
$riskLevelFilter = isset($_GET['risk_level']) && $_GET['risk_level'] !== '' ? trim($_GET['risk_level']) : '';
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Handle level filter from sidebar links
if (isset($_GET['level']) && !empty($_GET['level'])) {
    $riskLevelFilter = trim($_GET['level']);
}

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "rz.city LIKE ?";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "rz.province = ?";
    $params[] = $provinceFilter;
}

if (!empty($riskLevelFilter)) {
    $conditions[] = "rz.risk_level = ?";
    $params[] = $riskLevelFilter;
}

if (!empty($statusFilter)) {
    $conditions[] = "rz.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(rz.identified_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(rz.identified_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get risk zone records
$riskZones = [];
try {
    $sql = "SELECT rz.*, 
                   ua.first_name, ua.last_name
            FROM risk_zones rz 
            LEFT JOIN user_accounts ua ON rz.reviewed_by = ua.id 
            {$whereClause}
            ORDER BY rz.risk_score DESC, rz.identified_date DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $riskZones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching risk zones: " . $e->getMessage());
}

// Get statistics - ALL zone types
$stats = [
    'total_zones' => 0,
    'infected_zones' => 0,
    'buffer_zones' => 0,
    'surveillance_zones' => 0,
    'protected_zones' => 0,
    'free_zones' => 0,
    'critical_zones' => 0,
    'high_risk_zones' => 0,
    'medium_zones' => 0,
    'low_zones' => 0,
    'avg_risk_score' => 0,
    'avg_temperature' => 0,
    'active_zones' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM risk_zones");
    $stats['total_zones'] = $stmt->fetch()['total'] ?? 0;
    
    // Count by ASF zone type (from factors_contributing JSON)
    $stmt = $pdo->query("SELECT factors_contributing FROM risk_zones WHERE factors_contributing IS NOT NULL");
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($zones as $zone) {
        $factors = json_decode($zone['factors_contributing'], true);
        if ($factors && isset($factors['zone_type'])) {
            $zoneType = $factors['zone_type'];
            if ($zoneType === 'infected') $stats['infected_zones']++;
            elseif ($zoneType === 'buffer') $stats['buffer_zones']++;
            elseif ($zoneType === 'surveillance') $stats['surveillance_zones']++;
            elseif ($zoneType === 'protected') $stats['protected_zones']++;
            elseif ($zoneType === 'free') $stats['free_zones']++;
        }
    }
    
    // Also count by risk_level for backward compatibility
    $stmt = $pdo->query("SELECT COUNT(*) as critical FROM risk_zones WHERE risk_level = 'critical'");
    $stats['critical_zones'] = $stmt->fetch()['critical'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as high FROM risk_zones WHERE risk_level = 'high'");
    $stats['high_risk_zones'] = $stmt->fetch()['high'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as medium FROM risk_zones WHERE risk_level = 'medium'");
    $stats['medium_zones'] = $stmt->fetch()['medium'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as low FROM risk_zones WHERE risk_level = 'low'");
    $stats['low_zones'] = $stmt->fetch()['low'] ?? 0;
    
    $stmt = $pdo->query("SELECT AVG(risk_score) as avg FROM risk_zones WHERE risk_score IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_risk_score'] = round($result['avg'] ?? 0, 2);

    $stmt = $pdo->query("SELECT AVG(temperature) as avg_temp 
                     FROM risk_zones 
                     WHERE temperature IS NOT NULL");
    $result = $stmt->fetch();

    $stats['avg_temperature'] = round($result['avg_temp'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM risk_zones WHERE status = 'active'");
    $stats['active_zones'] = $stmt->fetch()['active'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM risk_zones WHERE city IS NOT NULL ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get chart data - risk zone distribution
$chartData = [
    'risk_level_breakdown' => [],
    'status_breakdown' => [],
    'risk_score_trends' => [
        'labels' => [],
        'avg_scores' => [],
        'min_scores' => [],
        'max_scores' => [],
        'zone_counts' => [],
        'critical_counts' => [],
        'high_counts' => [],
        'medium_counts' => [],
        'low_counts' => []
    ]
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
        $chartWhereParts[] = "DATE(identified_date) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(identified_date) <= ?";
        $chartParams[] = $dateTo;
    }
    
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    // Risk level breakdown
    $levelSql = "SELECT risk_level, COUNT(*) as count FROM risk_zones {$whereClause} GROUP BY risk_level";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($levelSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($levelSql);
    }
    
    $levelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($levelResults as $row) {
        $chartData['risk_level_breakdown'][$row['risk_level']] = intval($row['count']);
    }
    
    // Status breakdown
    $statusSql = "SELECT status, COUNT(*) as count FROM risk_zones {$whereClause} GROUP BY status";
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
    
    // Risk score trends by month - Enhanced with multiple metrics
    $trendSql = "SELECT 
                    DATE_FORMAT(identified_date, '%Y-%m') as month,
                    AVG(risk_score) as avg_score,
                    MIN(risk_score) as min_score,
                    MAX(risk_score) as max_score,
                    COUNT(*) as zone_count,
                    SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_count,
                    SUM(CASE WHEN risk_level = 'medium' THEN 1 ELSE 0 END) as medium_count,
                    SUM(CASE WHEN risk_level = 'low' THEN 1 ELSE 0 END) as low_count
                 FROM risk_zones 
                 {$whereClause}
                 WHERE risk_score IS NOT NULL
                 GROUP BY DATE_FORMAT(identified_date, '%Y-%m')
                 ORDER BY month ASC
                 LIMIT 24";
    
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($trendSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($trendSql);
    }
    
    $trendResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trendResults as $row) {
        $chartData['risk_score_trends']['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
        $chartData['risk_score_trends']['avg_scores'][] = round($row['avg_score'], 2);
        $chartData['risk_score_trends']['min_scores'][] = round($row['min_score'], 2);
        $chartData['risk_score_trends']['max_scores'][] = round($row['max_score'], 2);
        $chartData['risk_score_trends']['zone_counts'][] = intval($row['zone_count']);
        $chartData['risk_score_trends']['critical_counts'][] = intval($row['critical_count']);
        $chartData['risk_score_trends']['high_counts'][] = intval($row['high_count']);
        $chartData['risk_score_trends']['medium_counts'][] = intval($row['medium_count']);
        $chartData['risk_score_trends']['low_counts'][] = intval($row['low_count']);
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}

function getRiskLevelBadge($level) {
    $badges = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    return $badges[$level] ?? 'secondary';
}

function getStatusBadge($status) {
    $badges = [
        'active' => 'danger',
        'monitoring' => 'warning',
        'cleared' => 'success'
    ];
    return $badges[$status] ?? 'secondary';
}

function getRiskScoreColor($score) {
    if ($score === null) return 'secondary';
    if ($score >= 80) return 'dark'; // Critical
    if ($score >= 60) return 'danger'; // High
    if ($score >= 40) return 'warning'; // Medium
    return 'success'; // Low
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start">
    <div>
      <h1>Risk Zones</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Risk Zones</li>
        </ol>
      </nav>
    </div>
    <button type="button" class="btn btn-primary mt-1" data-bs-toggle="modal" data-bs-target="#calculateModal">
      <i class="bi bi-calculator me-1"></i> Calculate Risk Zones
    </button>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                <i class="bi bi-map"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Infected Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['infected_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Buffer Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #e91e63;">
                <i class="bi bi-exclamation-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['buffer_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Surveillance Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-eye"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['surveillance_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-5">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Protected Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #90ee90;">
                <i class="bi bi-shield-check"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['protected_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-5">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Free Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #228b22;">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['free_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Risk Score</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-graph-up-arrow"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_risk_score'], 1); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Temp</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #8b2226;">
                <i class="bi bi-thermometer"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_temperature']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-2 col-md-4">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Active Zones</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-check-circle-fill"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['active_zones']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">Risk Score Trends</h5>
              <?php if (!empty($chartData['risk_score_trends']['labels'])): ?>
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="switchTrendView('score')" id="btnViewScore">
                  <i class="bi bi-graph-up"></i> Scores
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="switchTrendView('count')" id="btnViewCount">
                  <i class="bi bi-bar-chart"></i> Zone Counts
                </button>
              </div>
              <?php endif; ?>
            </div>
            <?php if (empty($chartData['risk_score_trends']['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-bar-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Chart Data Available</h6>
                <p class="text-muted">Risk score trends will appear here once risk zones are calculated.</p>
              </div>
            <?php else: ?>
              <div id="scoreTrendChart">
                <canvas id="riskScoreTrendChart" style="max-height: 400px;"></canvas>
              </div>
              <div id="countTrendChart" style="display: none;">
                <canvas id="riskZoneCountChart" style="max-height: 400px;"></canvas>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Risk Level Distribution</h5>
            <?php if (empty($chartData['risk_level_breakdown'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-pie-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Data Available</h6>
              </div>
            <?php else: ?>
              <canvas id="riskLevelChart" style="max-height: 300px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Data Table -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Risk Zone Records</h5>
            
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
                    <label for="riskLevelFilter" class="form-label">Risk Level</label>
                    <select class="form-select form-select-sm" id="riskLevelFilter" name="risk_level">
                      <option value="">All Levels</option>
                      <option value="low" <?php echo $riskLevelFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                      <option value="medium" <?php echo $riskLevelFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                      <option value="high" <?php echo $riskLevelFilter === 'high' ? 'selected' : ''; ?>>High</option>
                      <option value="critical" <?php echo $riskLevelFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="statusFilter" name="status">
                      <option value="">All Status</option>
                      <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                      <option value="monitoring" <?php echo $statusFilter === 'monitoring' ? 'selected' : ''; ?>>Monitoring</option>
                      <option value="cleared" <?php echo $statusFilter === 'cleared' ? 'selected' : ''; ?>>Cleared</option>
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
                    <a href="risk-zones.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($riskZones)): ?>
              <div class="text-center py-5">
                <i class="bi bi-map" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No risk zones found</h5>
                <p class="text-muted">No risk zone records match the selected filters.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="riskZonesTable">
                  <thead>
                    <tr>
                      <th>Zone Code</th>
                      <th>Zone Name</th>
                      <th>Location</th>
                      <th>Risk Level</th>
                      <th>Risk Score</th>
                      <th>Status</th>
                      <th>Outbreaks</th>
                      <th>Depopulation</th>
                      <th>Identified Date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($riskZones as $zone): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($zone['zone_code']); ?></strong></td>
                        <td>
                          <strong><?php echo htmlspecialchars($zone['zone_name']); ?></strong>
                          <?php if ($zone['barangay']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($zone['barangay']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <i class="bi bi-geo-alt"></i> 
                          <?php echo htmlspecialchars($zone['city']); ?>, <?php echo htmlspecialchars($zone['province']); ?><br>
                          <small class="text-muted">
                            <?php echo number_format($zone['center_latitude'], 6); ?>, <?php echo number_format($zone['center_longitude'], 6); ?>
                          </small>
                          <?php if ($zone['radius_km']): ?>
                            <br><small class="text-info">Radius: <?php echo number_format($zone['radius_km'], 2); ?> km</small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getRiskLevelBadge($zone['risk_level']); ?>">
                            <?php echo ucfirst($zone['risk_level']); ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($zone['risk_score'] !== null): ?>
                            <span class="badge bg-<?php echo getRiskScoreColor($zone['risk_score']); ?>">
                              <?php echo number_format($zone['risk_score'], 1); ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getStatusBadge($zone['status']); ?>">
                            <?php echo ucfirst($zone['status']); ?>
                          </span>
                        </td>
                        <td>
                          <strong><?php echo number_format($zone['nearby_outbreaks_count']); ?></strong>
                          <?php if ($zone['last_outbreak_date']): ?>
                            <br><small class="text-muted">Last: <?php echo date('M d, Y', strtotime($zone['last_outbreak_date'])); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <strong><?php echo number_format($zone['depopulation_count']); ?></strong>
                        </td>
                        <td>
                          <?php echo date('M d, Y', strtotime($zone['identified_date'])); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewZoneDetails(<?php echo $zone['id']; ?>)">
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

<!-- Calculate Risk Zones Modal -->
<div class="modal fade" id="calculateModal" tabindex="-1" aria-labelledby="calculateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="calculateModalLabel"><i class="bi bi-calculator me-2"></i>Calculate Risk Zones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="calcForm">
          <div class="mb-3">
            <label for="calcLookbackDays" class="form-label">Lookback Period (days)</label>
            <input type="number" class="form-control" id="calcLookbackDays" value="180" min="30" max="730">
            <small class="text-muted">Analyze outbreaks from the last N days</small>
          </div>
          <div class="mb-3">
            <label for="calcReplaceExisting" class="form-label">Existing Zones</label>
            <select class="form-select" id="calcReplaceExisting">
              <option value="replace">Replace all existing zones</option>
              <option value="append">Append to existing zones</option>
              <option value="update">Update existing zones only</option>
            </select>
          </div>
        </div>
        <div id="calcProgress" style="display:none;">
          <div class="text-center py-3">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="mb-0">Calculating risk zones from outbreak data...</p>
          </div>
        </div>
        <div id="calcResult" style="display:none;"></div>
      </div>
      <div class="modal-footer" id="calcFooter">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="calcRunBtn" onclick="runCalculation()">
          <i class="bi bi-play-fill me-1"></i>Run Calculation
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Zone Details Modal -->
<div class="modal fade" id="zoneDetailsModal" tabindex="-1" aria-labelledby="zoneDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="zoneDetailsModalLabel">Risk Zone Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="zoneDetailsContent">
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
  const riskZonesTable = document.getElementById('riskZonesTable');
  if (riskZonesTable) {
    new simpleDatatables.DataTable(riskZonesTable, {
      "pageLength": 25,
      "order": [[4, "desc"]],
      "responsive": true
    });
  }
  
  // Initialize Risk Score Trends Chart
  let riskScoreTrendChart = null;
  let riskZoneCountChart = null;
  
  const trendCtx = document.getElementById('riskScoreTrendChart');
  if (trendCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['risk_score_trends']['labels']); ?>,
      avgScores: <?php echo json_encode($chartData['risk_score_trends']['avg_scores']); ?>,
      minScores: <?php echo json_encode($chartData['risk_score_trends']['min_scores'] ?? []); ?>,
      maxScores: <?php echo json_encode($chartData['risk_score_trends']['max_scores'] ?? []); ?>,
      zoneCounts: <?php echo json_encode($chartData['risk_score_trends']['zone_counts'] ?? []); ?>,
      criticalCounts: <?php echo json_encode($chartData['risk_score_trends']['critical_counts'] ?? []); ?>,
      highCounts: <?php echo json_encode($chartData['risk_score_trends']['high_counts'] ?? []); ?>,
      mediumCounts: <?php echo json_encode($chartData['risk_score_trends']['medium_counts'] ?? []); ?>,
      lowCounts: <?php echo json_encode($chartData['risk_score_trends']['low_counts'] ?? []); ?>
    };
    
    // Risk Score Chart
    riskScoreTrendChart = new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData.labels,
        datasets: [
          {
            label: 'Average Risk Score',
            data: trendData.avgScores,
            borderColor: 'rgb(220, 53, 69)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: 'rgb(220, 53, 69)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
          },
          {
            label: 'Maximum Score',
            data: trendData.maxScores.length > 0 ? trendData.maxScores : trendData.avgScores,
            borderColor: 'rgb(33, 37, 41)',
            backgroundColor: 'rgba(33, 37, 41, 0.05)',
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0.4,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: 'rgb(33, 37, 41)',
            pointBorderColor: '#fff',
            pointBorderWidth: 1
          },
          {
            label: 'Minimum Score',
            data: trendData.minScores.length > 0 ? trendData.minScores : trendData.avgScores,
            borderColor: 'rgb(25, 135, 84)',
            backgroundColor: 'rgba(25, 135, 84, 0.05)',
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0.4,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: 'rgb(25, 135, 84)',
            pointBorderColor: '#fff',
            pointBorderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: {
              usePointStyle: true,
              padding: 15,
              font: {
                size: 12,
                weight: '500'
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
              size: 14,
              weight: 'bold'
            },
            bodyFont: {
              size: 12
            },
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 1,
            displayColors: true,
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: true,
              color: 'rgba(0, 0, 0, 0.05)'
            },
            ticks: {
              maxRotation: 45,
              minRotation: 45,
              font: {
                size: 11
              }
            }
          },
          y: {
            beginAtZero: true,
            max: 100,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            },
            title: {
              display: true,
              text: 'Risk Score (0-100)',
              font: {
                size: 12,
                weight: 'bold'
              }
            },
            ticks: {
              font: {
                size: 11
              },
              callback: function(value) {
                return value.toFixed(0);
              }
            }
          }
        }
      }
    });
    
    // Risk Zone Count Chart (stacked area chart)
    const countCtx = document.getElementById('riskZoneCountChart');
    if (countCtx && trendData.zoneCounts.length > 0) {
      riskZoneCountChart = new Chart(countCtx, {
        type: 'line',
        data: {
          labels: trendData.labels,
          datasets: [
            {
              label: 'Critical Zones',
              data: trendData.criticalCounts,
              borderColor: 'rgb(33, 37, 41)',
              backgroundColor: 'rgba(33, 37, 41, 0.6)',
              borderWidth: 2,
              tension: 0.4,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            },
            {
              label: 'High-Risk Zones',
              data: trendData.highCounts,
              borderColor: 'rgb(220, 53, 69)',
              backgroundColor: 'rgba(220, 53, 69, 0.6)',
              borderWidth: 2,
              tension: 0.4,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            },
            {
              label: 'Medium Risk Zones',
              data: trendData.mediumCounts,
              borderColor: 'rgb(255, 193, 7)',
              backgroundColor: 'rgba(255, 193, 7, 0.6)',
              borderWidth: 2,
              tension: 0.4,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            },
            {
              label: 'Low Risk Zones',
              data: trendData.lowCounts,
              borderColor: 'rgb(25, 135, 84)',
              backgroundColor: 'rgba(25, 135, 84, 0.6)',
              borderWidth: 2,
              tension: 0.4,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: {
                usePointStyle: true,
                padding: 15,
                font: {
                  size: 12,
                  weight: '500'
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              padding: 12,
              titleFont: {
                size: 14,
                weight: 'bold'
              },
              bodyFont: {
                size: 12
              },
              borderColor: 'rgba(255, 255, 255, 0.1)',
              borderWidth: 1,
              displayColors: true
            }
          },
          scales: {
            x: {
              stacked: false,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                maxRotation: 45,
                minRotation: 45,
                font: {
                  size: 11
                }
              }
            },
            y: {
              beginAtZero: true,
              stacked: false,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              },
              title: {
                display: true,
                text: 'Number of Zones',
                font: {
                  size: 12,
                  weight: 'bold'
                }
              },
              ticks: {
                font: {
                  size: 11
                },
                stepSize: 1,
                precision: 0
              }
            }
          }
        }
      });
    }
  }
  
  // Switch between score and count views
  function switchTrendView(view) {
    const scoreChartDiv = document.getElementById('scoreTrendChart');
    const countChartDiv = document.getElementById('countTrendChart');
    const btnScore = document.getElementById('btnViewScore');
    const btnCount = document.getElementById('btnViewCount');
    
    if (view === 'score') {
      scoreChartDiv.style.display = 'block';
      countChartDiv.style.display = 'none';
      btnScore.classList.add('active');
      btnCount.classList.remove('active');
    } else {
      scoreChartDiv.style.display = 'none';
      countChartDiv.style.display = 'block';
      btnScore.classList.remove('active');
      btnCount.classList.add('active');
    }
  }
  
  const levelCtx = document.getElementById('riskLevelChart');
  if (levelCtx) {
    const levelData = <?php echo json_encode($chartData['risk_level_breakdown']); ?>;
    const colors = {
      'low': 'rgb(25, 135, 84)',
      'medium': 'rgb(255, 193, 7)',
      'high': 'rgb(220, 53, 69)',
      'critical': 'rgb(33, 37, 41)'
    };
    
    new Chart(levelCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(levelData).map(l => l.charAt(0).toUpperCase() + l.slice(1)),
        datasets: [{
          data: Object.values(levelData),
          backgroundColor: Object.keys(levelData).map(l => colors[l] || 'rgb(108, 117, 125)')
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

function viewZoneDetails(zoneId) {
  const modal = new bootstrap.Modal(document.getElementById('zoneDetailsModal'));
  const content = document.getElementById('zoneDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_risk_zone_details.php?zone_id=${zoneId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const zone = data.zone;
        const factors = zone.factors_contributing ? JSON.parse(zone.factors_contributing) : null;
        
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Zone Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Zone Code:</strong></td><td>${zone.zone_code || 'N/A'}</td></tr>
                <tr><td><strong>Zone Name:</strong></td><td>${zone.zone_name || 'N/A'}</td></tr>
                <tr><td><strong>Province:</strong></td><td>${zone.province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${zone.city || 'N/A'}</td></tr>
                <tr><td><strong>Barangay:</strong></td><td>${zone.barangay || 'N/A'}</td></tr>
                <tr><td><strong>Center Coordinates:</strong></td><td>${zone.center_latitude ? parseFloat(zone.center_latitude).toFixed(6) : 'N/A'}, ${zone.center_longitude ? parseFloat(zone.center_longitude).toFixed(6) : 'N/A'}</td></tr>
                <tr><td><strong>Radius:</strong></td><td>${zone.radius_km ? parseFloat(zone.radius_km).toFixed(2) + ' km' : 'N/A'}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Risk Assessment</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Risk Level:</strong></td><td><span class="badge bg-${getRiskLevelColor(zone.risk_level)}">${zone.risk_level ? zone.risk_level.toUpperCase() : 'N/A'}</span></td></tr>
                <tr><td><strong>Risk Score:</strong></td><td><span class="badge bg-${getRiskScoreColorJS(zone.risk_score)}">${zone.risk_score ? parseFloat(zone.risk_score).toFixed(1) : 'N/A'}</span></td></tr>
                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getStatusColor(zone.status)}">${zone.status ? zone.status.toUpperCase() : 'N/A'}</span></td></tr>
                <tr><td><strong>Identified Date:</strong></td><td>${formatDate(zone.identified_date)}</td></tr>
                <tr><td><strong>Reviewed By:</strong></td><td>${zone.first_name ? zone.first_name + ' ' + zone.last_name : 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Outbreak Statistics</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Nearby Outbreaks:</strong></td><td><strong>${zone.nearby_outbreaks_count ? parseInt(zone.nearby_outbreaks_count).toLocaleString() : '0'}</strong></td></tr>
                <tr><td><strong>Last Outbreak Date:</strong></td><td>${zone.last_outbreak_date ? formatDate(zone.last_outbreak_date) : 'N/A'}</td></tr>
                <tr><td><strong>Depopulation Count:</strong></td><td><strong>${zone.depopulation_count ? parseInt(zone.depopulation_count).toLocaleString() : '0'}</strong></td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Risk Scores</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Environmental Risk:</strong></td><td>${zone.environmental_risk_score ? parseFloat(zone.environmental_risk_score).toFixed(1) : 'N/A'}</td></tr>
                <tr><td><strong>Movement Risk:</strong></td><td>${zone.movement_risk_score ? parseFloat(zone.movement_risk_score).toFixed(1) : 'N/A'}</td></tr>
                <tr><td><strong>Population Density:</strong></td><td>${zone.population_density || 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          ${factors ? '<div class="row mt-3"><div class="col-md-12"><h6 class="fw-bold">Contributing Factors</h6><ul>' + Object.entries(factors).map(([key, value]) => '<li><strong>' + key.replace(/_/g, ' ').toUpperCase() + ':</strong> ' + value + '</li>').join('') + '</ul></div></div>' : ''}
          ${zone.notes ? '<div class="mt-3"><h6 class="fw-bold">Notes</h6><p>' + escapeHtml(zone.notes) + '</p></div>' : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load zone details</div>';
    });
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getRiskLevelColor(level) {
  const colors = {
    'low': 'success',
    'medium': 'warning',
    'high': 'danger',
    'critical': 'dark'
  };
  return colors[level] || 'secondary';
}

function getRiskScoreColorJS(score) {
  if (!score || score === null) return 'secondary';
  if (score >= 80) return 'dark'; // Critical
  if (score >= 60) return 'danger'; // High
  if (score >= 40) return 'warning'; // Medium
  return 'success'; // Low
}

function getStatusColor(status) {
  const colors = {
    'active': 'danger',
    'monitoring': 'warning',
    'cleared': 'success'
  };
  return colors[status] || 'secondary';
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function runCalculation() {
  const btn = document.getElementById('calcRunBtn');
  const form = document.getElementById('calcForm');
  const progress = document.getElementById('calcProgress');
  const result = document.getElementById('calcResult');
  const footer = document.getElementById('calcFooter');

  btn.disabled = true;
  form.style.display = 'none';
  progress.style.display = 'block';
  result.style.display = 'none';
  footer.style.display = 'none';

  const formData = new FormData();
  formData.append('lookbackDays', document.getElementById('calcLookbackDays').value);
  formData.append('replaceExisting', document.getElementById('calcReplaceExisting').value);
  formData.append('clusterRadius', '10');
  formData.append('minOutbreaksForZone', '1');

  fetch('ajax/calculate_risk_zones.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      progress.style.display = 'none';
      result.style.display = 'block';
      footer.style.display = 'flex';

      if (data.success) {
        result.innerHTML = `
          <div class="alert alert-success mb-0">
            <strong><i class="bi bi-check-circle me-1"></i>Done!</strong> ${data.message}<br>
            <small>Infected: ${data.infected_zones} &bull; Buffer: ${data.buffer_zones} &bull; Surveillance: ${data.surveillance_zones} &bull; Protected: ${data.protected_zones} &bull; Free: ${data.free_zones}</small>
          </div>`;
        footer.innerHTML = '<button class="btn btn-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Reload Page</button>';
      } else {
        result.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(data.message || 'Calculation failed.')}</div>`;
        btn.disabled = false;
        form.style.display = 'block';
        result.style.display = 'block';
      }
    })
    .catch(() => {
      progress.style.display = 'none';
      result.style.display = 'block';
      result.innerHTML = '<div class="alert alert-danger mb-0">Request failed. Please try again.</div>';
      btn.disabled = false;
      form.style.display = 'block';
      footer.style.display = 'flex';
    });
}

// Reset modal state when it's closed
document.addEventListener('DOMContentLoaded', function() {
  const calcModal = document.getElementById('calculateModal');
  if (calcModal) {
    calcModal.addEventListener('hidden.bs.modal', function() {
      document.getElementById('calcForm').style.display = 'block';
      document.getElementById('calcProgress').style.display = 'none';
      document.getElementById('calcResult').style.display = 'none';
      document.getElementById('calcFooter').style.display = 'flex';
      document.getElementById('calcFooter').innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="calcRunBtn" onclick="runCalculation()">
          <i class="bi bi-play-fill me-1"></i>Run Calculation
        </button>`;
    });
  }
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
