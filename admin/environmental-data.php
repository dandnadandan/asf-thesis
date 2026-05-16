<?php
/**
 * Environmental Data Management for ASF Surveillance System
 * Manages environmental parameters (temperature, humidity, rainfall, etc.) for CALABARZON
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Environmental Data';

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
$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim($_GET['date_to']) : '';

// Build query with filters
$conditions = [];
$params = [];

if (!empty($cityFilter)) {
    $conditions[] = "ed.city LIKE ?";
    $params[] = "%{$cityFilter}%";
}

if (!empty($provinceFilter)) {
    $conditions[] = "ed.province = ?";
    $params[] = $provinceFilter;
}

if (!empty($dateFrom)) {
    $conditions[] = "DATE(ed.recorded_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $conditions[] = "DATE(ed.recorded_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get environmental data records
$environmentalData = [];
try {
    $sql = "SELECT ed.*, ua.first_name, ua.last_name 
            FROM environmental_data ed 
            LEFT JOIN user_accounts ua ON ed.recorded_by = ua.id 
            {$whereClause}
            ORDER BY ed.recorded_at DESC 
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $environmentalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching environmental data: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_records' => 0,
    'avg_temperature' => 0,
    'avg_humidity' => 0,
    'total_rainfall' => 0,
    'cities_count' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM environmental_data");
    $stats['total_records'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT AVG(temperature) as avg_temp FROM environmental_data WHERE temperature IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_temperature'] = round($result['avg_temp'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT AVG(humidity) as avg_hum FROM environmental_data WHERE humidity IS NOT NULL");
    $result = $stmt->fetch();
    $stats['avg_humidity'] = round($result['avg_hum'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT SUM(rainfall) as total_rain FROM environmental_data WHERE rainfall IS NOT NULL");
    $result = $stmt->fetch();
    $stats['total_rainfall'] = round($result['total_rain'] ?? 0, 2);
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT city) as cities FROM environmental_data");
    $result = $stmt->fetch();
    $stats['cities_count'] = $result['cities'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get unique cities for filter dropdown
$cities = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT city FROM environmental_data WHERE city IS NOT NULL ORDER BY city");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Get data for charts (apply same filters as table)
$chartData = [
    'labels' => [],
    'temperature' => [],
    'humidity' => [],
    'rainfall' => [],
    'wind_speed' => [],
    'atmospheric_pressure' => []
];

// Build chart query - use same filters as table but simpler approach
// Use query() for simple queries, prepare() for parameterized ones
try {
    // Build WHERE conditions for chart
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
        $chartWhereParts[] = "DATE(recorded_at) >= ?";
        $chartParams[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $chartWhereParts[] = "DATE(recorded_at) <= ?";
        $chartParams[] = $dateTo;
    }
    
    // If no date filter, don't restrict by date - show all available data
    $whereClause = !empty($chartWhereParts) ? 'WHERE ' . implode(' AND ', $chartWhereParts) : '';
    
    // Build and execute query
    $chartSql = "SELECT DATE(recorded_at) as date, 
                        AVG(temperature) as avg_temp,
                        AVG(humidity) as avg_hum,
                        SUM(rainfall) as total_rain,
                        AVG(wind_speed) as avg_wind,
                        AVG(atmospheric_pressure) as avg_pressure
                 FROM environmental_data 
                 {$whereClause}
                 GROUP BY DATE(recorded_at)
                 ORDER BY date ASC
                 LIMIT 100";
    
    if (!empty($chartParams)) {
        $stmt = $pdo->prepare($chartSql);
        $stmt->execute($chartParams);
    } else {
        $stmt = $pdo->query($chartSql);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get min and max dates for subtitle
    $chartDateRange = '';
    if (!empty($results)) {
        $firstDate = $results[0]['date'];
        $lastDate = $results[count($results) - 1]['date'];
        $chartDateRange = date('M d, Y', strtotime($firstDate)) . ' - ' . date('M d, Y', strtotime($lastDate));
    }
    
    foreach ($results as $row) {
        $chartData['labels'][] = date('M d', strtotime($row['date']));
        $chartData['temperature'][] = $row['avg_temp'] !== null ? round($row['avg_temp'], 2) : null;
        $chartData['humidity'][] = $row['avg_hum'] !== null ? round($row['avg_hum'], 2) : null;
        $chartData['rainfall'][] = $row['total_rain'] !== null ? round($row['total_rain'], 2) : null;
        $chartData['wind_speed'][] = $row['avg_wind'] !== null ? round($row['avg_wind'], 2) : null;
        $chartData['atmospheric_pressure'][] = $row['avg_pressure'] !== null ? round($row['avg_pressure'], 2) : null;
    }
} catch (Exception $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Environmental Data</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Environmental Data</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Statistics Cards -->
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Records</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                <i class="bi bi-database"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_records']); ?></h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Temperature</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                <i class="bi bi-thermometer-half"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_temperature'], 1); ?>°C</h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Avg Humidity</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                <i class="bi bi-moisture"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['avg_humidity'], 1); ?>%</h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="card info-card">
          <div class="card-body">
            <h5 class="card-title">Total Rainfall</h5>
            <div class="d-flex align-items-center">
              <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary">
                <i class="bi bi-cloud-rain"></i>
              </div>
              <div class="ps-3">
                <h6><?php echo number_format($stats['total_rainfall'], 2); ?> mm</h6>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Charts -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="card-title mb-1">Environmental Trends</h5>
                <?php if (isset($chartDateRange) && !empty($chartDateRange)): ?>
                  <small class="text-muted">
                    <i class="bi bi-calendar-range me-1"></i>
                    <?php echo htmlspecialchars($chartDateRange); ?>
                    <?php if (!empty($cityFilter)): ?>
                      | <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($cityFilter); ?>
                    <?php endif; ?>
                  </small>
                <?php else: ?>
                  <small class="text-muted">Last 30 Days</small>
                <?php endif; ?>
              </div>
              <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleChartType()">
                  <i class="bi bi-arrow-left-right me-1"></i>Toggle View
                </button>
              </div>
            </div>
            <?php if (empty($chartData['labels'])): ?>
              <div class="text-center py-5">
                <i class="bi bi-bar-chart" style="font-size: 3rem; color: #dee2e6;"></i>
                <h6 class="mt-3">No Chart Data Available</h6>
                <p class="text-muted">
                  <?php if (!empty($cityFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                    No data found for the selected filters. Try adjusting your search criteria.
                  <?php else: ?>
                    No environmental data available for the last 30 days. Upload data via the Data Uploads page.
                  <?php endif; ?>
                </p>
              </div>
            <?php else: ?>
              <canvas id="environmentalChart" style="max-height: 350px;"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Data Table -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Environmental Data Records</h5>
            
            <!-- Filters -->
            <div class="row mb-3">
              <div class="col-md-12">
                <form method="GET" class="row g-3">
                  <div class="col-md-3">
                    <label for="provinceFilter" class="form-label">Province</label>
                    <select class="form-select form-select-sm" id="provinceFilter" name="province">
                      <option value="">All Provinces</option>
                      <option value="CALABARZON" <?php echo $provinceFilter === 'CALABARZON' ? 'selected' : ''; ?>>CALABARZON</option>
                    </select>
                  </div>
                  <div class="col-md-3">
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
                    <a href="environmental-data.php" class="btn btn-secondary btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>
            
            <?php if (empty($environmentalData)): ?>
              <div class="text-center py-5">
                <i class="bi bi-thermometer-half" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3">No environmental data found</h5>
                <p class="text-muted">
                  <?php if (!empty($cityFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                    No records match the selected filters. Try adjusting your search criteria.
                  <?php else: ?>
                    There are no environmental data records yet. Upload data via the Data Uploads page.
                  <?php endif; ?>
                </p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover" id="environmentalTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Location</th>
                      <th>City</th>
                      <th>Barangay</th>
                      <th>Temperature</th>
                      <th>Humidity</th>
                      <th>Rainfall</th>
                      <th>Wind Speed</th>
                      <th>Recorded At</th>
                      <th>Recorded By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($environmentalData as $record): ?>
                      <tr>
                        <td><?php echo $record['id']; ?></td>
                        <td>
                          <strong><?php echo htmlspecialchars($record['location_name']); ?></strong><br>
                          <small class="text-muted">
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo number_format($record['latitude'], 6); ?>, <?php echo number_format($record['longitude'], 6); ?>
                          </small>
                        </td>
                        <td><?php echo htmlspecialchars($record['city']); ?></td>
                        <td><?php echo htmlspecialchars($record['barangay'] ?? 'N/A'); ?></td>
                        <td>
                          <?php if ($record['temperature'] !== null): ?>
                            <span class="badge bg-danger"><?php echo number_format($record['temperature'], 1); ?>°C</span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($record['humidity'] !== null): ?>
                            <span class="badge bg-info"><?php echo number_format($record['humidity'], 1); ?>%</span>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($record['rainfall'] !== null && $record['rainfall'] > 0): ?>
                            <span class="badge bg-primary"><?php echo number_format($record['rainfall'], 2); ?> mm</span>
                          <?php else: ?>
                            <span class="text-muted">0 mm</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($record['wind_speed'] !== null): ?>
                            <small><?php echo number_format($record['wind_speed'], 1); ?> km/h</small>
                            <?php if ($record['wind_direction']): ?>
                              <span class="badge bg-secondary"><?php echo htmlspecialchars($record['wind_direction']); ?></span>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <small><?php echo date('M d, Y', strtotime($record['recorded_at'])); ?></small><br>
                          <small class="text-muted"><?php echo date('H:i:s', strtotime($record['recorded_at'])); ?></small>
                        </td>
                        <td>
                          <?php echo htmlspecialchars(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? 'Unknown')); ?>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewRecordDetails(<?php echo $record['id']; ?>)">
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

<!-- Record Details Modal -->
<div class="modal fade" id="recordDetailsModal" tabindex="-1" aria-labelledby="recordDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="recordDetailsModalLabel">Environmental Data Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="recordDetailsContent">
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
  const environmentalTable = document.getElementById('environmentalTable');
  if (environmentalTable) {
    new simpleDatatables.DataTable(environmentalTable, {
      "pageLength": 25,
      "order": [[8, "desc"]],
      "responsive": true
    });
  }
  
  // Initialize Environmental Chart
  let environmentalChart = null;
  let chartType = 'combined'; // 'combined', 'temperature', 'precipitation'
  
  const ctx = document.getElementById('environmentalChart');
  if (ctx) {
    const chartData = {
      labels: <?php echo json_encode($chartData['labels']); ?>,
      temperature: <?php echo json_encode($chartData['temperature']); ?>,
      humidity: <?php echo json_encode($chartData['humidity']); ?>,
      rainfall: <?php echo json_encode($chartData['rainfall']); ?>,
      wind_speed: <?php echo json_encode($chartData['wind_speed']); ?>,
      atmospheric_pressure: <?php echo json_encode($chartData['atmospheric_pressure']); ?>
    };
    
    function createChart(type) {
      if (environmentalChart) {
        environmentalChart.destroy();
      }
      
      let datasets = [];
      let scales = {};
      
      if (type === 'combined' || type === 'temperature') {
        datasets.push({
          label: 'Temperature (°C)',
          data: chartData.temperature,
          borderColor: 'rgb(220, 53, 69)',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y'
        });
        
        datasets.push({
          label: 'Humidity (%)',
          data: chartData.humidity,
          borderColor: 'rgb(13, 110, 253)',
          backgroundColor: 'rgba(13, 110, 253, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        });
        
        scales.y = {
          type: 'linear',
          display: true,
          position: 'left',
          title: {
            display: true,
            text: 'Temperature (°C)'
          },
          grid: {
            drawOnChartArea: type === 'combined'
          }
        };
        
        scales.y1 = {
          type: 'linear',
          display: true,
          position: 'right',
          title: {
            display: true,
            text: 'Humidity (%)'
          },
          grid: {
            drawOnChartArea: false
          }
        };
      }
      
      if (type === 'combined' || type === 'precipitation') {
        datasets.push({
          label: 'Rainfall (mm)',
          data: chartData.rainfall,
          borderColor: 'rgb(25, 135, 84)',
          backgroundColor: 'rgba(25, 135, 84, 0.3)',
          tension: 0.4,
          type: 'bar',
          yAxisID: type === 'combined' ? 'y2' : 'y',
          order: type === 'combined' ? 2 : 0
        });
        
        if (type === 'combined') {
          scales.y2 = {
            type: 'linear',
            display: false,
            position: 'right'
          };
        } else {
          scales.y = {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Rainfall (mm)'
            }
          };
        }
      }
      
      if (type === 'wind') {
        datasets.push({
          label: 'Wind Speed (km/h)',
          data: chartData.wind_speed,
          borderColor: 'rgb(255, 193, 7)',
          backgroundColor: 'rgba(255, 193, 7, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y'
        });
        
        datasets.push({
          label: 'Atmospheric Pressure (hPa)',
          data: chartData.atmospheric_pressure,
          borderColor: 'rgb(108, 117, 125)',
          backgroundColor: 'rgba(108, 117, 125, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        });
        
        scales.y = {
          type: 'linear',
          display: true,
          position: 'left',
          title: {
            display: true,
            text: 'Wind Speed (km/h)'
          }
        };
        
        scales.y1 = {
          type: 'linear',
          display: true,
          position: 'right',
          title: {
            display: true,
            text: 'Pressure (hPa)'
          },
          grid: {
            drawOnChartArea: false
          }
        };
      }
      
      environmentalChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: chartData.labels,
          datasets: datasets
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
              position: 'top',
              onClick: function(e, legendItem) {
                const index = legendItem.datasetIndex;
                const meta = environmentalChart.getDatasetMeta(index);
                meta.hidden = meta.hidden === null ? !environmentalChart.data.datasets[index].hidden : null;
                environmentalChart.update();
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.parsed.y !== null) {
                    label += context.parsed.y.toFixed(2);
                  }
                  return label;
                }
              }
            }
          },
          scales: scales
        }
      });
    }
    
    // Initialize with combined view
    createChart('combined');
    
    // Store function globally for toggle
    window.toggleChartType = function() {
      const types = ['combined', 'temperature', 'precipitation', 'wind'];
      const currentIndex = types.indexOf(chartType);
      chartType = types[(currentIndex + 1) % types.length];
      createChart(chartType);
    };
  }
});

function viewRecordDetails(recordId) {
  const modal = new bootstrap.Modal(document.getElementById('recordDetailsModal'));
  const content = document.getElementById('recordDetailsContent');
  
  content.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading...</div>';
  modal.show();
  
  fetch(`ajax/get_environmental_record.php?record_id=${recordId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const record = data.record;
        content.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6 class="fw-bold">Location Information</h6>
              <table class="table table-sm table-borderless">
                <tr>
                  <td><strong>Location Name:</strong></td>
                  <td>${record.location_name || 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Province:</strong></td>
                  <td>${record.province || 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>City:</strong></td>
                  <td>${record.city || 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Barangay:</strong></td>
                  <td>${record.barangay || 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Coordinates:</strong></td>
                  <td>${record.latitude ? parseFloat(record.latitude).toFixed(6) : 'N/A'}, ${record.longitude ? parseFloat(record.longitude).toFixed(6) : 'N/A'}</td>
                </tr>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="fw-bold">Environmental Parameters</h6>
              <table class="table table-sm table-borderless">
                <tr>
                  <td><strong>Temperature:</strong></td>
                  <td>${record.temperature !== null ? parseFloat(record.temperature).toFixed(2) + '°C' : 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Humidity:</strong></td>
                  <td>${record.humidity !== null ? parseFloat(record.humidity).toFixed(2) + '%' : 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Rainfall:</strong></td>
                  <td>${record.rainfall !== null ? parseFloat(record.rainfall).toFixed(2) + ' mm' : 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Wind Speed:</strong></td>
                  <td>${record.wind_speed !== null ? parseFloat(record.wind_speed).toFixed(2) + ' km/h' : 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Wind Direction:</strong></td>
                  <td>${record.wind_direction || 'N/A'}</td>
                </tr>
                <tr>
                  <td><strong>Atmospheric Pressure:</strong></td>
                  <td>${record.atmospheric_pressure !== null ? parseFloat(record.atmospheric_pressure).toFixed(2) + ' hPa' : 'N/A'}</td>
                </tr>
              </table>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-12">
              <h6 class="fw-bold">Record Information</h6>
              <table class="table table-sm table-borderless">
                <tr>
                  <td><strong>Recorded At:</strong></td>
                  <td>${formatDateTime(record.recorded_at)}</td>
                </tr>
                <tr>
                  <td><strong>Recorded By:</strong></td>
                  <td>${record.first_name || ''} ${record.last_name || 'Unknown'}</td>
                </tr>
                <tr>
                  <td><strong>Created At:</strong></td>
                  <td>${formatDateTime(record.created_at)}</td>
                </tr>
              </table>
            </div>
          </div>
        `;
      } else {
        content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<div class="alert alert-danger">Failed to load record details</div>';
    });
}

function formatDateTime(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
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
