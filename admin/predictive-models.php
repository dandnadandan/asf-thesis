<!DOCTYPE html>
<?php
/**
 * Predictive Models Management for ASF Surveillance System
 * Manages predictive model results and analysis for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Predictive Models';

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
$modelNameFilter = isset($_GET['model_name']) && $_GET['model_name'] !== '' ? trim($_GET['model_name']) : '';
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "pm.location_city LIKE ?";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "pm.location_province = ?";
    $params[] = $provinceFilter;
}

if (!empty($riskLevelFilter)) {
    $conditions[] = "pm.predicted_risk_level = ?";
    $params[] = $riskLevelFilter;
}

if (!empty($modelNameFilter)) {
    $conditions[] = "pm.model_name LIKE ?";
    $params[] = "%{$modelNameFilter}%";
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(pm.prediction_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(pm.prediction_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get predictive model records
$predictions = [];
try {
    $sql = "SELECT pm.*, 
                   ua.first_name, ua.last_name
            FROM predictive_models pm 
            LEFT JOIN user_accounts ua ON pm.created_by = ua.id 
            {$whereClause}
            ORDER BY pm.prediction_date DESC, pm.predicted_risk_score DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching predictions: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_predictions' => 0,
    'critical_predictions' => 0,
    'high_risk_predictions' => 0,
    'avg_risk_score' => 0,
    'avg_confidence' => 0,
    'unique_models' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM predictive_models");
    $stats['total_predictions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as critical FROM predictive_models WHERE predicted_risk_level = 'critical'");
    $stats['critical_predictions'] = $stmt->fetch()['critical'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as high FROM predictive_models WHERE predicted_risk_level = 'high'");
    $stats['high_risk_predictions'] = $stmt->fetch()['high'] ?? 0;
    
    $stmt = $pdo->query("SELECT AVG(predicted_risk_score) as avg FROM predictive_models WHERE predicted_risk_score IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_risk_score'] = round($result['avg'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT AVG(confidence_level) as avg FROM predictive_models WHERE confidence_level IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_confidence'] = round($result['avg'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT model_name) as unique_models FROM predictive_models");
    $stats['unique_models'] = $stmt->fetch()['unique_models'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT location_city FROM predictive_models WHERE location_city IS NOT NULL ORDER BY location_city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get unique model names for filter dropdown
$modelNames = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT model_name FROM predictive_models WHERE model_name IS NOT NULL ORDER BY model_name");
    $modelNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching model names: " . $e->getMessage());
}

// Get chart data - prediction trends
$chartData = [
    'risk_level_breakdown' => [],
    'model_performance' => [],
    'risk_score_trends' => [
        'labels' => [],
        'avg_scores' => [],
        'min_scores' => [],
        'max_scores' => [],
        'avg_probability' => [],
        'avg_confidence' => [],
        'prediction_counts' => [],
        'critical_counts' => [],
        'high_counts' => [],
        'medium_counts' => [],
        'low_counts' => [],
        'actual_scores' => []
    ]
];

try {
    $chartWhereParts = [];
    $chartParams = [];
    
    if (!empty($cityFilter)) {
        $chartWhereParts[] = "location_city LIKE ?";
        $chartParams[] = "%{$cityFilter}%";
    }
    
    if (!empty($provinceFilter)) {
        $chartWhereParts[] = "location_province = ?";
        $chartParams[] = $provinceFilter;
    }
    
    if (!empty($dateFrom)) {
        $chartWhereParts[] = "DATE(prediction_date) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(prediction_date) <= ?";
        $chartParams[] = $dateTo;
    }
    
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    // Risk level breakdown
    $levelSql = "SELECT predicted_risk_level, COUNT(*) as count FROM predictive_models {$whereClause} GROUP BY predicted_risk_level";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($levelSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($levelSql);
    }
    
    $levelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($levelResults as $row) {
        $chartData['risk_level_breakdown'][$row['predicted_risk_level']] = intval($row['count']);
    }
    
    // Model performance (by model name)
    $modelSql = "SELECT model_name, COUNT(*) as count, AVG(predicted_risk_score) as avg_score, AVG(confidence_level) as avg_confidence 
                 FROM predictive_models {$whereClause} 
                 GROUP BY model_name 
                 ORDER BY count DESC 
                 LIMIT 10";
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($modelSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($modelSql);
    }
    
    $modelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($modelResults as $row) {
        $chartData['model_performance'][$row['model_name']] = [
            'count' => intval($row['count']),
            'avg_score' => round($row['avg_score'], 2),
            'avg_confidence' => round($row['avg_confidence'], 2)
        ];
    }
    
    // Enhanced predictive trends by month
    $trendWhereParts = $chartWhereParts;
    $trendParams = $chartParams;
    
    // Add predicted_risk_score IS NOT NULL condition
    $trendWhereParts[] = "predicted_risk_score IS NOT NULL";
    
    $trendWhereClause = !empty($trendWhereParts) ? 'WHERE ' . implode(' AND ', $trendWhereParts) : '';
    
    $trendSql = "SELECT 
                    DATE_FORMAT(prediction_date, '%Y-%m') as month,
                    AVG(predicted_risk_score) as avg_score,
                    MIN(predicted_risk_score) as min_score,
                    MAX(predicted_risk_score) as max_score,
                    AVG(probability_outbreak) as avg_probability,
                    AVG(confidence_level) as avg_confidence,
                    COUNT(*) as prediction_count,
                    SUM(CASE WHEN predicted_risk_level = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN predicted_risk_level = 'high' THEN 1 ELSE 0 END) as high_count,
                    SUM(CASE WHEN predicted_risk_level = 'medium' THEN 1 ELSE 0 END) as medium_count,
                    SUM(CASE WHEN predicted_risk_level = 'low' THEN 1 ELSE 0 END) as low_count
                 FROM predictive_models 
                 {$trendWhereClause}
                 GROUP BY DATE_FORMAT(prediction_date, '%Y-%m')
                 ORDER BY month ASC
                 LIMIT 24";
    
    if (!empty($trendParams)) {
        $stmt = $pdo->prepare($trendSql);
        $stmt->execute($trendParams);
    } else {
        $stmt = $pdo->query($trendSql);
    }
    
    $trendResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trendResults as $row) {
        $chartData['risk_score_trends']['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
        $chartData['risk_score_trends']['avg_scores'][] = round($row['avg_score'], 2);
        $chartData['risk_score_trends']['min_scores'][] = round($row['min_score'], 2);
        $chartData['risk_score_trends']['max_scores'][] = round($row['max_score'], 2);
        $chartData['risk_score_trends']['avg_probability'][] = round($row['avg_probability'] ?? 0, 2);
        $chartData['risk_score_trends']['avg_confidence'][] = round($row['avg_confidence'] ?? 0, 2);
        $chartData['risk_score_trends']['prediction_counts'][] = intval($row['prediction_count']);
        $chartData['risk_score_trends']['critical_counts'][] = intval($row['critical_count']);
        $chartData['risk_score_trends']['high_counts'][] = intval($row['high_count']);
        $chartData['risk_score_trends']['medium_counts'][] = intval($row['medium_count']);
        $chartData['risk_score_trends']['low_counts'][] = intval($row['low_count']);
    }
    
    // Get actual risk zones trends for comparison (last 24 months)
    try {
        $actualTrendSql = "SELECT 
                              DATE_FORMAT(identified_date, '%Y-%m') as month,
                              AVG(risk_score) as avg_score
                           FROM risk_zones
                           WHERE risk_score IS NOT NULL
                           GROUP BY DATE_FORMAT(identified_date, '%Y-%m')
                           ORDER BY month ASC
                           LIMIT 24";
        $actualStmt = $pdo->query($actualTrendSql);
        $actualResults = $actualStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $actualTrends = [];
        foreach ($actualResults as $row) {
            $month = date('M Y', strtotime($row['month'] . '-01'));
            $actualTrends[$month] = round($row['avg_score'], 2);
        }
        
        // Match actual trends with predicted trends by month label
        $chartData['risk_score_trends']['actual_scores'] = [];
        foreach ($chartData['risk_score_trends']['labels'] as $label) {
            $chartData['risk_score_trends']['actual_scores'][] = $actualTrends[$label] ?? null;
        }
    } catch (Exception $e) {
        error_log("Error fetching actual risk zone trends: " . $e->getMessage());
        $chartData['risk_score_trends']['actual_scores'] = [];
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}

// Generate Summary Report
function generateSummary($pdo, $stats, $chartData, $filters = []) {

    try {

        $summary = [];

        $criticalPercent =
            $stats['total_predictions'] > 0
            ? round(
                ($stats['critical_predictions'] / $stats['total_predictions']) * 100,
                1
            )
            : 0;

        $highPercent =
            $stats['total_predictions'] > 0
            ? round(
                ($stats['high_risk_predictions'] / $stats['total_predictions']) * 100,
                1
            )
            : 0;

        if ($stats['total_predictions'] > 0) {

            $summary[] =
                "The predictive models generated " .
                number_format($stats['total_predictions']) .
                " predictions.";

            $summary[] =
                number_format($criticalPercent, 1) .
                "% are critical risk and " .
                number_format($highPercent, 1) .
                "% are high-risk zones.";

        } else {

            $summary[] =
                "No prediction data available.";

        }

        return implode(' ', $summary);

    } catch (Exception $e) {

        error_log($e->getMessage());

        return "Failed to generate summary.";
    }
}


// -----------------------------------
// GENERATE CSV + TABLE DATA
// ----------------------------------- 

// -----------------------------------
// BADGE COLORS
// -----------------------------------

function getRiskLevelBadge($level) {

    $badges = [

        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'

    ];

    return $badges[$level] ?? 'secondary';
}

function getRiskScoreColor($score) {

    if ($score === null) return 'secondary';

    if ($score >= 80) return 'dark';

    if ($score >= 60) return 'danger';

    if ($score >= 40) return 'warning';

    return 'success';
}

function getConfidenceColor($confidence) {

    if ($confidence === null) return 'secondary';

    if ($confidence >= 80) return 'success';

    if ($confidence >= 60) return 'info';

    if ($confidence >= 40) return 'warning';

    return 'danger';
}


// -----------------------------------
// RUN FUNCTIONS
// -----------------------------------

$summary = generateSummary(
    $pdo,
    $stats,
    $chartData
);

$predictionData = [
    'cities' => []
];

$predictionsPath = __DIR__ . '/../ml/predictions.json';

if (file_exists($predictionsPath)) {

    $json = file_get_contents($predictionsPath);

    $predictions1 = json_decode($json, true);

    if ($predictions1) {

        $predictionData['cities'] = $predictions1;
    }
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<head>
  <link
  rel="stylesheet"
  href="https://unpkg.com/leaflet/dist/leaflet.css"
/>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<main id="main" class="main">
  <div class="pagetitle">
    <h1>Predictive Models</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Predictive Models</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Predictions</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                <i class="bi bi-graph-up"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_predictions']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Critical Predictions</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-dark">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['critical_predictions']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Risk Score</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                <i class="bi bi-speedometer2"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_risk_score'], 1); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Confidence</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_confidence'], 1); ?>%</h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Summary Report -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                  <h5 class="card-title mb-1">
                  <i class="bi bi-file-text me-2"></i>Analysis Summary
                </h5>
                <small class="text-muted">Narrative analysis of predictive model data</small>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="regenerateSummary()">
                <i class="bi bi-arrow-clockwise me-1"></i>Regenerate
              </button>
            </div>
            <div class="summary-content">
              <div class="p-3 bg-light rounded">
                <p id="summaryText" class="mb-0" style="line-height: 1.8; text-align: justify;">
                  <?php echo htmlspecialchars($summary); ?>
                </p>
              </div>
            </div>
            <button type="button" class="btn btn-danger mb-3" onclick="downloadPredictionPDF()">
                Download Prediction Report PDF
            </button>
          <button type="button" onclick="toggleRiskTable()" class="btn btn-primary mb-3">
              Show/Hide Risk Table
          </button>
            <div id="riskTableContainer" class="card mt-4" style="display: none;">
            <div class="card-header">
                <h4>Prediction Results</h4>
            </div>

            <div class="card-body">

                <table class="table table-bordered table-striped" id="predictionTable">

                    <thead>
                        <tr>
                            <th>City</th>
                            <th>Barangay</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Zone Type</th>
                        </tr>
                    </thead>

                    <tbody id="predictionTableBody">
                    </tbody>

                </table>

            </div>
        </div>
          </div>
        </div>
      </div>
      
      <!-- Predictive Trends Charts -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">
                <i class="bi bi-graph-up-arrow me-2"></i>Predictive Trends Visualization
              </h5>
              <?php if (!empty($chartData['risk_score_trends']['labels'])): ?>
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="switchPredictiveView('scores')" id="btnViewScores">
                  <i class="bi bi-speedometer2"></i> Risk Scores
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="switchPredictiveView('probability')" id="btnViewProbability">
                  <i class="bi bi-pie-chart"></i> Probability
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="switchPredictiveView('counts')" id="btnViewCounts">
                  <i class="bi bi-bar-chart"></i> Prediction Counts
                </button>
              </div>
              <?php endif; ?>
            </div>
            <?php if (empty($chartData['risk_score_trends']['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-graph-up" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Predictive Trends Data Available</h6>
                <p class="text-muted">Predictive trends will appear here once predictive model data is available.</p>
              </div>
            <?php else: ?>
              <!-- Risk Scores View -->
              <div id="scoresTrendChart">
                <canvas id="riskScoreTrendChart" style="max-height: 450px;"></canvas>
              </div>
              <!-- Probability View -->
              <div id="probabilityTrendChart" style="display: none;">
                <canvas id="probabilityTrendChartCanvas" style="max-height: 450px;"></canvas>
              </div>
              <!-- Prediction Counts View -->
              <div id="countsTrendChart" style="display: none;">
                <canvas id="predictionCountsChart" style="height: 450px; width: 100%;"></canvas>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
    
      
      <!-- Data Table -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Prediction Records</h5>
            
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
                    <label for="modelNameFilter" class="form-label">Model Name</label>
                    <select class="form-select form-select-sm" id="modelNameFilter" name="model_name">
                      <option value="">All Models</option>
                      <?php foreach ($modelNames as $modelName): ?>
                        <option value="<?php echo htmlspecialchars($modelName); ?>" <?php echo $modelNameFilter === $modelName ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($modelName); ?>
                        </option>
                      <?php endforeach; ?>
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
                    <a href="predictive-models.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($predictions)): ?>
              <div class="text-center py-5">
                <i class="bi bi-graph-up" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No predictions found</h5>
                <p class="text-muted">No predictive model records match the selected filters.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="predictionsTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Model</th>
                      <th>Location</th>
                      <th>Prediction Date</th>
                      <th>Risk Level</th>
                      <th>Risk Score</th>
                      <th>Confidence</th>
                      <th>Probability</th>
                      <th>Created By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($predictions as $prediction): ?>
                      <tr>
                        <td><?php echo $prediction['id']; ?></td>
                        <td>
                          <strong><?php echo htmlspecialchars($prediction['model_name']); ?></strong><br>
                          <small class="text-muted">v<?php echo htmlspecialchars($prediction['model_version']); ?> - <?php echo htmlspecialchars($prediction['model_type']); ?></small>
                        </td>
                        <td>
                          <?php if ($prediction['location_city']): ?>
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo htmlspecialchars($prediction['location_city']); ?>
                            <?php if ($prediction['location_province']): ?>
                              , <?php echo htmlspecialchars($prediction['location_province']); ?>
                            <?php endif; ?>
                            <?php if ($prediction['latitude'] && $prediction['longitude']): ?>
                              <br><small class="text-muted"><?php echo number_format($prediction['latitude'], 6); ?>, <?php echo number_format($prediction['longitude'], 6); ?></small>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($prediction['prediction_date'])); ?></td>
                        <td>
                          <span class="badge bg-<?php echo getRiskLevelBadge($prediction['predicted_risk_level']); ?>">
                            <?php echo ucfirst($prediction['predicted_risk_level']); ?>
                          </span>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getRiskScoreColor($prediction['predicted_risk_score']); ?>">
                            <?php echo number_format($prediction['predicted_risk_score'], 1); ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($prediction['confidence_level'] !== null): ?>
                            <span class="badge bg-<?php echo getConfidenceColor($prediction['confidence_level']); ?>">
                              <?php echo number_format($prediction['confidence_level'], 1); ?>%
                            </span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($prediction['probability_outbreak'] !== null): ?>
                            <strong><?php echo number_format($prediction['probability_outbreak'], 1); ?>%</strong>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php echo htmlspecialchars(($prediction['first_name'] ?? '') . ' ' . ($prediction['last_name'] ?? 'Unknown')); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewPredictionDetails(<?php echo $prediction['id']; ?>)">
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

<!-- Prediction Details Modal -->
<div class="modal fade" id="predictionDetailsModal" tabindex="-1" aria-labelledby="predictionDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="predictionDetailsModalLabel">Prediction Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="predictionDetailsContent">
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
<!-- <div id="pdfMap" style="width:1000px; height:700px;"></div> -->
<div id="exportMap"
     style="
        position: absolute;
        top: -99999px;
        left: -99999px;

        width: 1200px;
        height: 700px;

        z-index: -1; ">

    <div id="pdfMap"
         style="width:1000px; height:700px;">
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet-image/leaflet-image.js"></script>
<script>
let sumsum;
async function createExportMap() {

    // remove old instance if exists
    if (window.exportLeafletMap) {
        window.exportLeafletMap.remove();
    }

    // create clean export map
    const exportMap = L.map("exportMap", {
        preferCanvas: true,
        zoomControl: false,
        attributionControl: false
    });

    // match visible map view
    exportMap.setView(
        asfMap.getCenter(),
        asfMap.getZoom()
    );

    // copy tile layer
    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            crossOrigin: true
        }
    ).addTo(exportMap);

    // -----------------------------------
    // COPY POLYGONS
    // -----------------------------------

    asfMap.eachLayer(layer => {

    // -----------------------------------
    // GEOJSON / FEATURE GROUP
    // -----------------------------------

    if (layer instanceof L.GeoJSON || layer instanceof L.FeatureGroup) {

        layer.eachLayer(subLayer => {

            // polygons / polylines
            if (subLayer.toGeoJSON && subLayer.options) {

                const geojson =
                    subLayer.toGeoJSON();

                // exact style clone
                const style = {
                    color:
                        subLayer.options.color || "#3388ff",

                    weight:
                        subLayer.options.weight || 3,

                    opacity:
                        subLayer.options.opacity ?? 1,

                    fillColor:
                        subLayer.options.fillColor ||
                        subLayer.options.color ||
                        "#3388ff",

                    fillOpacity:
                        subLayer.options.fillOpacity ?? 0.5
                };

                L.geoJSON(geojson, {
                    style: style,
                    renderer: L.canvas()
                }).addTo(exportMap);
            }
        });
    }
});

    // wait for rendering
    await new Promise(r => setTimeout(r, 1500));

    return exportMap;
}

async function captureExportMap() {

    const exportMap = await createExportMap();

    const element =
        document.getElementById("exportMap");

    const canvas = await html2canvas(element, {
        useCORS: true,
        allowTaint: true,
        scale: 2,
        backgroundColor: "#ffffff"
    });

    return canvas;
}


async function downloadPredictionPDF() {

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF("p", "mm", "a4");

   
      const canvas = await captureExportMap();

      const mapImage = canvas.toDataURL("image/png");
    // -----------------------------------
    // 3. TITLE
    // -----------------------------------
    pdf.setFontSize(20);
    pdf.text("ASF Prediction Report", 10, 15);

    // -----------------------------------
    // 4. SUMMARY
    // -----------------------------------
    pdf.setFontSize(11);

    const wrappedSummary = pdf.splitTextToSize(sumsum || "", 180);
    pdf.text(wrappedSummary, 10, 30);

    // -----------------------------------
    // 5. MAP IMAGE
    // -----------------------------------
    pdf.addImage(mapImage, "PNG", 10, 75, 190, 90);

    // -----------------------------------
    // 6. TABLE
    // -----------------------------------
    const tableRows = [];

    document.querySelectorAll("#predictionTable tbody tr").forEach(row => {
        const cols = row.querySelectorAll("td");

        tableRows.push([
            cols[0]?.innerText || "",
            cols[1]?.innerText || "",
            cols[2]?.innerText || "",
            cols[3]?.innerText || "",
            cols[4]?.innerText || ""
        ]);
    });

    pdf.autoTable({
        startY: 190,
        head: [[
            "City",
            "Barangay",
            "Latitude",
            "Longitude",
            "Zone Type"
        ]],
        body: tableRows,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [220, 53, 69] }
    });

    // -----------------------------------
    // 7. DOWNLOAD
    // -----------------------------------
    pdf.save("ASF_Prediction_Report.pdf");
}


  const predictionData =
    <?= json_encode($predictionData) ?>;

console.log(predictionData);

    let pdfMap;
    let mapLayers = {
      cartogram: null  // Cartogram layer for risk zones
    };
    let zonePolygons = {}; // Store polygons by zone type for filtering
    let calabarzonGeoJSON = null; // Store GeoJSON data

      initializeMap();
    
    function initializeMap() {
      // CALABARZON center coordinates (approximately Batangas City area)
      const calabarzonCenter = [14.0, 121.0];
      
      // CALABARZON region boundaries (restrict map to CALABARZON only)
      // Northern: 14.6°N, Southern: 13.3°N, Eastern: 122.0°E, Western: 120.3°E
      const calabarzonBounds = [
        [13.3, 120.3], // Southwest corner
        [14.6, 122.0]  // Northeast corner
      ];
      
      // Initialize map with bounds restriction
      asfMap = L.map('pdfMap', {
        maxBounds: calabarzonBounds,
        maxBoundsViscosity: 1.0, // Prevent panning outside bounds
        minZoom: 8,
        maxZoom: 18
      }).setView(calabarzonCenter, 9);
      
      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18,
        crossOrigin: true
      }).addTo(asfMap);
      
      // Set map bounds to CALABARZON region
      asfMap.setMaxBounds(calabarzonBounds);
      
      // Initialize cartogram layer for animated circles
      mapLayers.cartogram = L.layerGroup().addTo(asfMap);
      
      // Set default date range (last 90 days)
      
      // Load GeoJSON boundaries first, then load the map data from API
      fetch('../assets/data/calabarzon-municipalities.geojson')
        .then(response => response.json())
        .then(data => {
          calabarzonGeoJSON = data;
          loadMapData();
        })
        .catch(err => {
          console.error('Error loading GeoJSON boundaries:', err);
          loadMapData(); // Fallback
        });
    }

    /**
     * Load map markers and data from API
     */
    function loadMapData() {
      // Load map data for animated circles (date filter handled in loadMapDataForCartogram)
      loadMapDataForCartogram();
    }
    
    /**
     * Apply date filter to map
     */
    function applyDateFilter() {
      loadMapData();
    }
    
    /**
     * Clear date filter
     */
    function clearDateFilter() {
      const today = new Date();
      const ninetyDaysAgo = new Date(today);
      ninetyDaysAgo.setDate(today.getDate() - 90);
      document.getElementById('mapDateFrom').value = ninetyDaysAgo.toISOString().split('T')[0];
      document.getElementById('mapDateTo').value = today.toISOString().split('T')[0];
      loadMapData();
    }
    
    /**
     * Toggle cartogram layer visibility for specific zone type
     */
    function toggleZoneLayer(zoneType, event) {
      const checkboxId = `zone${zoneType.charAt(0).toUpperCase() + zoneType.slice(1)}`;
      const checkbox = document.getElementById(checkboxId);
      
      if (checkbox) {
        checkbox.checked = !checkbox.checked;
        
        // Update toggle visual state - find the parent layer-toggle element
        if (event && event.currentTarget) {
          const toggle = event.currentTarget;
          if (checkbox.checked) {
            toggle.classList.add('active');
          } else {
            toggle.classList.remove('active');
          }
        } else {
          // Fallback: find the toggle element manually
          const toggle = checkbox.closest('.layer-toggle');
          if (toggle) {
            if (checkbox.checked) {
              toggle.classList.add('active');
            } else {
              toggle.classList.remove('active');
            }
          }
        }
        
        // Apply filter to show/hide zones
        applyZoneFilter();
      }
    }
    
    /**
     * Load map data for animated circles cartogram (now Polygon based)
     */
    function loadMapDataForCartogram() {

    fetch('../ml/predictions.json')

    .then(response => response.json())

    .then(predictions => {

        console.log("PREDICTIONS:", predictions);

        // Convert predictions.json
        // into SAME FORMAT your polygon system expects

        const formattedData = {
            cities: predictions.map(p => ({

                city: p.city,
                barangay: p.barangay,
                latitude: p.latitude,
                longitude: p.longitude,

                // REQUIRED
                zone_type: p.zone_type,

                // OPTIONAL FALLBACKS
                outbreak_date: p.outbreak_date || 'Prediction',
                location_name: p.city,
                province: 'CALABARZON',

                total_outbreaks: 1,
                total_pigs_affected: 0,
                severity_level: 'Predicted'

            }))
        };

        console.log("FORMATTED:", formattedData);

        updateCartogramWithPolygons(formattedData);
        loadPredictionTable(predictions);

    })

    .catch(error => {

        console.error(
            'Error loading predictions:',
            error
        );

    });

}

function loadPredictionTable(data) {

    const tbody = document.getElementById("predictionTableBody");

    tbody.innerHTML = "";

    data.forEach(row => {

        const tr = document.createElement("tr");

        let badge = "";

        switch(row.zone_type) {

            case "infected":
                badge = `<span class="badge bg-danger">Infected</span>`;
                break;

            case "buffer":
                badge = `<span class="badge bg-pink text-dark">Buffer</span>`;
                break;

            case "surveillance":
                badge = `<span class="badge bg-warning text-dark">Surveillance</span>`;
                break;

            case "protected":
                badge = `<span class="badge bg-info text-dark">Protected</span>`;
                break;

            default:
                badge = `<span class="badge bg-success">Free</span>`;
        }

        tr.innerHTML = `
            <td>${row.city || ""}</td>
            <td>${row.barangay || ""}</td>
            <td>${row.latitude}</td>
            <td>${row.longitude}</td>
            <td>${badge}</td>
        `;

        tbody.appendChild(tr);
    });
}
     /**
     * Update cartogram map using GeoJSON polygons instead of circles
     */
    function updateCartogramWithPolygons(data) {
      // Clear existing layer
      mapLayers.cartogram.clearLayers();
      zonePolygons = {};
      console.log("GeoJSON loaded:", calabarzonGeoJSON);
      console.log("Cities:", data.cities);
      
      // Zone colors matching ASF zoning standards
      const zoneColors = {
        infected: '#dc3545',      // Red
        buffer: '#ff69b4',        // Pink (Hot Pink)
        surveillance: '#ffc107',   // Yellow
        protected: '#f5f5dc',     // Light Cream (Beige)
        free: '#228b22'            // Green
      };
      
      if (calabarzonGeoJSON && data.cities && Array.isArray(data.cities)) {
        // Allowed provinces in CALABARZON
        const calabarzonProvinces = ['CAVITE', 'LAGUNA', 'BATANGAS', 'RIZAL', 'QUEZON'];
          
        // Create lookup dictionary for API data by city name
        const outbreakLookup = {};
        data.cities.forEach(outbreak => {
            const prov = (outbreak.province || '').trim().toUpperCase();
            
            // Exclude data if it is explicitly outside Cavite, Laguna, Batangas, Rizal, Quezon
            if (prov && prov !== 'CALABARZON' && !calabarzonProvinces.some(p => prov.includes(p))) {
                return; // Skip data from outside CALABARZON
            }
            
            const matchName = (outbreak.location_name || outbreak.barangay || outbreak.city || '').replace('CITY OF ', '').replace(' CITY', '').trim().toUpperCase();
            if (matchName) outbreakLookup[matchName] = outbreak;
            
            // Allow exact match fallback
            if (outbreak.city) outbreakLookup[outbreak.city.trim().toUpperCase()] = outbreak;
        });
        
        // Add GeoJSON layer
        const geoLayer = L.geoJSON(calabarzonGeoJSON, {
          filter: function(feature) {
              const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
              
              if (!feature.geometry || !feature.geometry.coordinates) return false;
              
              // Extract approximate geographical coordinate to verify CALABARZON boundaries
              let coords = feature.geometry.coordinates;
              while (coords.length > 0 && Array.isArray(coords[0])) {
                  coords = coords[0];
              }
              if (coords.length < 2) return true;
              
              const lng = coords[0];
              const lat = coords[1];
              
              // 1. Generic CALABARZON strict bounding box (Lat 13.1 to 15.2, Lng 120.3 to 123.0)
              if (lat < 13.1 || lat > 15.2 || lng < 120.3 || lng > 123.0) return false;
              
              // 2. Explicitly exclude identically-named municipalities located in other provinces that slip into the rough bounding box
              if (geoName === 'SAN JOSE' && (lat > 14.5 || lat < 13.5 || lng < 120.8)) return false; // Batangas only
              if (geoName === 'SAN ANTONIO' && (lat > 14.5 || lng < 121.0)) return false; // Quezon only
              if (geoName === 'ROSARIO' && (lat > 14.6 || lat < 13.6)) return false; // Batangas & Cavite only
              if (geoName === 'SAN NICOLAS' && lat > 14.5) return false; // Batangas only
              if (geoName === 'SAN PASCUAL' && (lat > 14.0 || lat < 13.5)) return false; // Batangas only
              if (geoName === 'PLARIDEL' && lng < 121.5) return false; // Quezon only (drops Bulacan Plaridel)
              if (geoName === 'MABINI' && (lat > 14.0 || lat < 13.5)) return false; // Batangas only
              if (geoName === 'RIZAL' && (lat > 14.5 || lat < 13.8 || lng < 121.0)) return false; // Laguna only

              // 3. Drop specific anomalies spotted outside of bounds (NCR, Marinduque, Bataan)
              if (geoName === 'QUEZON CITY') return false; // Dropping NCR
              if (geoName === 'MANILA' || geoName === 'CITY OF MANILA') return false; // Dropping NCR
              if (geoName === 'BUENAVISTA' && lat < 13.6) return false; // Dropping Marinduque Buenavista
              if (geoName === 'SANTA CRUZ' && lat < 13.8) return false; // Dropping Marinduque Santa Cruz
              if (geoName === 'MORONG' && lng < 120.6) return false; // Dropping Bataan Morong

              // 4. Drop identically-named municipalities in Pampanga, Bulacan, and Tarlac
              if (geoName === 'SAN LUIS' && lat > 14.5) return false; // Pampanga San Luis
              if (geoName === 'SANTO TOMAS' && lat > 14.5) return false; // Pampanga Santo Tomas
              if (geoName === 'SANTA MARIA' && lat > 14.5) return false; // Bulacan Santa Maria
              if (geoName === 'VICTORIA' && lat > 14.5) return false; // Tarlac Victoria

              return true; // Polygon is validated as exclusively inside Region IV-A
          },
          style: function(feature) {
            const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
            const outbreakInfo = outbreakLookup[geoName];
            
            const zoneType = outbreakInfo ? (outbreakInfo.zone_type || 'free') : 'free';
            console.log("MATCHING:", geoName, outbreakLookup[geoName]);
            return {
              fillColor: zoneColors[zoneType] || zoneColors.free,
              weight: 2,
              opacity: 0.9,
              color: '#ffffff',
              fillOpacity: zoneType === 'protected' ? 0.7 : 0.65
            };
          },
          onEachFeature: function(feature, layer) {
            const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
            const outbreakInfo = outbreakLookup[geoName];
            
            const zoneType = outbreakInfo ? (outbreakInfo.zone_type || 'free') : 'free';
            
            const zoneTypeName = zoneType.charAt(0).toUpperCase() + zoneType.slice(1) + ' Zone';
            const statusLabels = {
              'infected': 'Confirmed',
              'buffer': 'Contained',
              'surveillance': 'Suspected',
              'protected': 'Resolved',
              'free': 'False Alarm'
            };
            const statusLabel = statusLabels[zoneType] || 'Unknown';
            
            // Group feature layer into the appropriate zone type for checkbox filtering
            if (!zonePolygons[zoneType]) zonePolygons[zoneType] = [];
            zonePolygons[zoneType].push(layer);
            
            // Define popup tooltips based on outbreak info
            const displayName = feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || geoName;
            let popupContent = `<strong>${zoneTypeName}</strong><br><strong>${displayName}</strong><br>`;
            
            if (outbreakInfo) {
              popupContent += `Status: ${statusLabel}<br>
              ${outbreakInfo.total_outbreaks > 1 ? `Total Outbreaks: ${outbreakInfo.total_outbreaks}<br>` : `Outbreak Code: ${outbreakInfo.outbreak_code || 'N/A'}<br>`}
              Date: ${outbreakInfo.outbreak_date || 'N/A'}<br>
              ${outbreakInfo.total_pigs_affected > 0 ? `Affected: ${outbreakInfo.total_pigs_affected}<br>` : ''}
              ${outbreakInfo.severity_level ? `Severity: ${outbreakInfo.severity_level}<br>` : ''}`;
            } else {
              popupContent += `Status: No active outbreaks recorded (Free/Clean)`;
            }
            
            layer.bindPopup(popupContent);
            
            // Hover styling interaction for polygons
            layer.on({
                mouseover: function(e) {
                    const l = e.target;
                    l.setStyle({
                        weight: 4,
                        color: '#666',
                        fillOpacity: 0.9
                    });
                    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                        l.bringToFront();
                    }
                },
                mouseout: function(e) {
                    geoLayer.resetStyle(e.target);
                }
            });
          }
        });
        
        // Append all grouped features explicitly to allow toggling logic
        geoLayer.addTo(mapLayers.cartogram);
        // applyZoneFilter();
        
      } else {
        console.warn('GeoJSON boundaries or cities data is not available.', data);
      }
    }
    

    /**
     * GIS Map Simulation Variables and Functions
     */
    

function toggleRiskTable() {

      const table = document.getElementById("riskTableContainer");
      const btn = document.getElementById("toggleBtn");

      if (table.style.display === "none") {

          table.style.display = "block";
          btn.innerText = "Hide Risk Table";

      } else {

          table.style.display = "none";
          btn.innerText = "Show Risk Table";
      }
  }

document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const predictionsTable = document.getElementById('predictionsTable');
  if (predictionsTable) {
    new simpleDatatables.DataTable(predictionsTable, {
      "pageLength": 25,
      "order": [[3, "desc"]],
      "responsive": true
    });
  }
  // Initialize Charts
  const trendCtx = document.getElementById('riskScoreTrendChart');
  if (trendCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['risk_score_trends']['labels']); ?>,
      avgScores: <?php echo json_encode($chartData['risk_score_trends']['avg_scores']); ?>
    };
    
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData.labels,
        datasets: [{
          label: 'Average Risk Score',
          data: trendData.avgScores,
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
            display: true,
            position: 'top'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: 'Risk Score'
            }
          }
        }
      }
    });
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
  
  // Initialize Probability Chart
  const probabilityCtx = document.getElementById('probabilityTrendChartCanvas');
  if (probabilityCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['risk_score_trends']['labels']); ?>,
      avgProbability: <?php echo json_encode($chartData['risk_score_trends']['avg_probability']); ?>,
      avgConfidence: <?php echo json_encode($chartData['risk_score_trends']['avg_confidence']); ?>
    };
    
    new Chart(probabilityCtx, {
      type: 'line',
      data: {
        labels: trendData.labels,
        datasets: [
          {
            label: 'Average Outbreak Probability (%)',
            data: trendData.avgProbability,
            borderColor: 'rgb(220, 53, 69)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y'
          },
          {
            label: 'Average Confidence Level (%)',
            data: trendData.avgConfidence,
            borderColor: 'rgb(13, 110, 253)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
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
            position: 'top'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '%';
              }
            }
          }
        },
        scales: {
          x: {
            display: true,
            title: {
              display: true,
              text: 'Month'
            },
            grid: {
              display: false
            }
          },
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Probability (%)'
            },
            min: 0,
            max: 100,
            ticks: {
              callback: function(value) {
                return value + '%';
              }
            }
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Confidence (%)'
            },
            min: 0,
            max: 100,
            ticks: {
              callback: function(value) {
                return value + '%';
              }
            },
            grid: {
              drawOnChartArea: false
            }
          }
        }
      }
    });
  }
  
  // Initialize Prediction Counts Chart
  const countsCtx = document.getElementById('predictionCountsChart');
  if (countsCtx) {
    const trendData = {
      labels: <?php echo json_encode($chartData['risk_score_trends']['labels']); ?>,
      criticalCounts: <?php echo json_encode($chartData['risk_score_trends']['critical_counts']); ?>,
      highCounts: <?php echo json_encode($chartData['risk_score_trends']['high_counts']); ?>,
      mediumCounts: <?php echo json_encode($chartData['risk_score_trends']['medium_counts']); ?>,
      lowCounts: <?php echo json_encode($chartData['risk_score_trends']['low_counts']); ?>
    };
    
    new Chart(countsCtx, {
      type: 'bar',
      data: {
        labels: trendData.labels,
        datasets: [
          {
            label: 'Critical',
            data: trendData.criticalCounts,
            backgroundColor: 'rgb(33, 37, 41)',
            borderColor: 'rgb(33, 37, 41)',
            borderWidth: 1
          },
          {
            label: 'High',
            data: trendData.highCounts,
            backgroundColor: 'rgb(220, 53, 69)',
            borderColor: 'rgb(220, 53, 69)',
            borderWidth: 1
          },
          {
            label: 'Medium',
            data: trendData.mediumCounts,
            backgroundColor: 'rgb(255, 193, 7)',
            borderColor: 'rgb(255, 193, 7)',
            borderWidth: 1
          },
          {
            label: 'Low',
            data: trendData.lowCounts,
            backgroundColor: 'rgb(25, 135, 84)',
            borderColor: 'rgb(25, 135, 84)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: true,
            position: 'top'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y + ' predictions';
              }
            }
          }
        },
        scales: {
          x: {
            display: true,
            title: {
              display: true,
              text: 'Month'
            },
            grid: {
              display: false
            },
            stacked: false
          },
          y: {
            display: true,
            title: {
              display: true,
              text: 'Number of Predictions'
            },
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            stacked: false
          }
        }
      }
    });
  }
});

// Switch between predictive trend views
function switchPredictiveView(view) {
  const scoresDiv = document.getElementById('scoresTrendChart');
  const probabilityDiv = document.getElementById('probabilityTrendChart');
  const countsDiv = document.getElementById('countsTrendChart');
  
  const btnScores = document.getElementById('btnViewScores');
  const btnProbability = document.getElementById('btnViewProbability');
  const btnCounts = document.getElementById('btnViewCounts');
  
  // Hide all charts
  scoresDiv.style.display = 'none';
  probabilityDiv.style.display = 'none';
  countsDiv.style.display = 'none';
  
  // Remove active class from all buttons
  [btnScores, btnProbability, btnCounts].forEach(btn => {
    if (btn) btn.classList.remove('active');
  });
  
  // Show selected chart and activate button
  switch(view) {
    case 'scores':
      scoresDiv.style.display = 'block';
      if (btnScores) btnScores.classList.add('active');
      break;
    case 'probability':
      probabilityDiv.style.display = 'block';
      if (btnProbability) btnProbability.classList.add('active');
      break;
    case 'counts':
      countsDiv.style.display = 'block';
      if (btnCounts) btnCounts.classList.add('active');
      break;
  }
}

function regenerateSummary() {
  const content = document.querySelector('.summary-content');
  const button = event.target.closest('button');
  const originalText = button.innerHTML;
  
  button.disabled = true;
  button.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Generating...';
  content.innerHTML = '<div class="text-center p-3"><i class="bi bi-arrow-clockwise spin"></i> Generating summary...</div>';
  
  // Get current filter parameters from URL
  const urlParams = new URLSearchParams(window.location.search);
  
  fetch(`ajax/generate_ai_summary.php?${urlParams.toString()}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        sumsum = escapeHtml(data.summary);
        content.innerHTML = `
          <div class="p-3 bg-light rounded">
            <p class="mb-0" style="line-height: 1.8; text-align: justify;">
              ${escapeHtml(data.summary)}
            </p>
          </div>
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
      button.disabled = false;
      button.innerHTML = originalText;
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to generate summary. Please try again.</div>';
      button.disabled = false;
      button.innerHTML = originalText;
    });
}

function viewPredictionDetails(predictionId) {
  const modal = new bootstrap.Modal(document.getElementById('predictionDetailsModal'));
  const content = document.getElementById('predictionDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_predictive_model_details.php?prediction_id=${predictionId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const pm = data.prediction;
        const inputFeatures = pm.input_features ? JSON.parse(pm.input_features) : null;
        const modelOutput = pm.model_output ? JSON.parse(pm.model_output) : null;
        const accuracyMetrics = pm.accuracy_metrics ? JSON.parse(pm.accuracy_metrics) : null;
        
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Model Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Model Name:</strong></td><td>${pm.model_name || 'N/A'}</td></tr>
                <tr><td><strong>Model Version:</strong></td><td>${pm.model_version || 'N/A'}</td></tr>
                <tr><td><strong>Model Type:</strong></td><td>${pm.model_type || 'N/A'}</td></tr>
                <tr><td><strong>Prediction Date:</strong></td><td>${formatDate(pm.prediction_date)}</td></tr>
                <tr><td><strong>Created By:</strong></td><td>${pm.first_name || ''} ${pm.last_name || 'Unknown'}</td></tr>
                <tr><td><strong>Created At:</strong></td><td>${formatDateTime(pm.created_at)}</td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Location Information</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Province:</strong></td><td>${pm.location_province || 'N/A'}</td></tr>
                <tr><td><strong>City:</strong></td><td>${pm.location_city || 'N/A'}</td></tr>
                <tr><td><strong>Coordinates:</strong></td><td>${pm.latitude ? parseFloat(pm.latitude).toFixed(6) : 'N/A'}, ${pm.longitude ? parseFloat(pm.longitude).toFixed(6) : 'N/A'}</td></tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6 class="fw-bold">Prediction Results</h6>
              <table class="table table-sm table-borderless">
                <tr><td><strong>Predicted Risk Level:</strong></td><td><span class="badge bg-${getRiskLevelColor(pm.predicted_risk_level)}">${pm.predicted_risk_level ? pm.predicted_risk_level.toUpperCase() : 'N/A'}</span></td></tr>
                <tr><td><strong>Predicted Risk Score:</strong></td><td><span class="badge bg-${getRiskScoreColorJS(pm.predicted_risk_score)}">${pm.predicted_risk_score ? parseFloat(pm.predicted_risk_score).toFixed(1) : 'N/A'}</span></td></tr>
                <tr><td><strong>Probability of Outbreak:</strong></td><td><strong>${pm.probability_outbreak ? parseFloat(pm.probability_outbreak).toFixed(1) + '%' : 'N/A'}</strong></td></tr>
                <tr><td><strong>Confidence Level:</strong></td><td><span class="badge bg-${getConfidenceColorJS(pm.confidence_level)}">${pm.confidence_level ? parseFloat(pm.confidence_level).toFixed(1) + '%' : 'N/A'}</span></td></tr>
              </table>
            </div>
            <div class="col-md-6">
              ${accuracyMetrics ? `
                <h6 class="fw-bold">Model Accuracy Metrics</h6>
                <table class="table table-sm table-borderless">
                  ${Object.entries(accuracyMetrics).map(([key, value]) => 
                    `<tr><td><strong>${key.replace(/_/g, ' ').toUpperCase()}:</strong></td><td>${typeof value === 'number' ? parseFloat(value).toFixed(2) : value}</td></tr>`
                  ).join('')}
                </table>
              ` : ''}
            </div>
          </div>
          ${inputFeatures ? `
            <div class="row mt-3">
              <div class="col-md-12">
                <h6 class="fw-bold">Input Features</h6>
                <div class="p-2 bg-light rounded">
                  <pre style="margin: 0; font-size: 0.85rem; white-space: pre-wrap;">${JSON.stringify(inputFeatures, null, 2)}</pre>
                </div>
              </div>
            </div>
          ` : ''}
          ${modelOutput ? `
            <div class="row mt-3">
              <div class="col-md-12">
                <h6 class="fw-bold">Model Output</h6>
                <div class="p-2 bg-light rounded">
                  <pre style="margin: 0; font-size: 0.85rem; white-space: pre-wrap;">${JSON.stringify(modelOutput, null, 2)}</pre>
                </div>
              </div>
            </div>
          ` : ''}
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load prediction details</div>';
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
  if (score >= 80) return 'dark';
  if (score >= 60) return 'danger';
  if (score >= 40) return 'warning';
  return 'success';
}

function getConfidenceColorJS(confidence) {
  if (!confidence || confidence === null) return 'secondary';
  if (confidence >= 80) return 'success';
  if (confidence >= 60) return 'info';
  if (confidence >= 40) return 'warning';
  return 'danger';
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

.summary-content {
  min-height: 80px;
}
</style>
