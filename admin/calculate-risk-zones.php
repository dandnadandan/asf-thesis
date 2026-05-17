<?php
/**
 * Risk Zone Calculation for ASF Surveillance System
 * Analyzes outbreaks, depopulation events, environmental data, and meat movement
 * to automatically calculate and generate risk zones
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Calculate Risk Zones';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get existing risk zones count for display
$existingZones = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM risk_zones");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $existingZones = $result['count'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching risk zones count: " . $e->getMessage());
}

// Get data source statistics
$dataStats = ['outbreaks' => 0];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM asf_outbreaks");
    $dataStats['outbreaks'] = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching data statistics: " . $e->getMessage());
}

// Get recent calculation history
$calculationHistory = [];
try {
    $sql = "SELECT * FROM data_uploads 
            WHERE upload_type = 'risk_zone_calculation' 
            ORDER BY created_at DESC 
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $calculationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not have this type, that's okay
}

include 'includes/head.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Calculate Risk Zones</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="risk-zones.php">Risk Zones</a></li>
        <li class="breadcrumb-item active">Calculate Risk Zones</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      
      <!-- Data Source Statistics -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Data Source Statistics</h5>
            <p class="text-muted">Risk zones are calculated exclusively from ASF outbreak records.</p>
            <div class="row">
              <div class="col-md-3">
                <div class="card bg-primary text-white mb-3">
                  <div class="card-body">
                    <h6 class="card-title">ASF Outbreaks</h6>
                    <h3 class="mb-0"><?php echo number_format($dataStats['outbreaks']); ?></h3>
                    <small>Records</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Calculation Parameters -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
              <h5 class="card-title">Risk Zone Calculation Parameters</h5>
            
            <div class="alert alert-info">
              <i class="bi bi-info-circle me-2"></i>
              <strong>How it works:</strong> The system analyzes ASF outbreak records by city/municipality and classifies each city as an Infected, Buffer, Surveillance, Protected, or Free zone based on outbreak count, recency, and severity.
            </div>
            
            <form id="calculateRiskZonesForm">
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="clusterRadius" class="form-label">Cluster Radius (km)</label>
                  <input type="number" class="form-control" id="clusterRadius" name="clusterRadius" value="10" min="1" max="50" step="0.5">
                  <small class="text-muted">Maximum distance to group nearby outbreaks (default: 10km)</small>
                </div>
                <div class="col-md-6">
                  <label for="minOutbreaksForZone" class="form-label">Minimum Outbreaks per Zone</label>
                  <input type="number" class="form-control" id="minOutbreaksForZone" name="minOutbreaksForZone" value="2" min="1" max="10">
                  <small class="text-muted">Minimum number of outbreaks required to create a zone (default: 2)</small>
                </div>
              </div>
              
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="lookbackDays" class="form-label">Lookback Period (days)</label>
                  <input type="number" class="form-control" id="lookbackDays" name="lookbackDays" value="180" min="30" max="730">
                  <small class="text-muted">Analyze data from the last N days (default: 180 days, max: 730 days)</small>
                </div>
                <div class="col-md-6">
                  <label for="replaceExisting" class="form-label">Existing Zones</label>
                  <select class="form-select" id="replaceExisting" name="replaceExisting">
                    <option value="append">Append to existing zones</option>
                    <option value="replace">Replace all existing zones</option>
                    <option value="update">Update existing zones only</option>
                  </select>
                  <small class="text-muted">How to handle existing risk zones</small>
                </div>
              </div>
              
              <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Note:</strong> Calculation may take several minutes depending on the amount of data. Existing risk zones: <strong><?php echo number_format($existingZones); ?></strong>
              </div>
              
              <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg" id="calculateBtn">
                  <i class="bi bi-calculator me-2"></i>Calculate Risk Zones
                </button>
                <a href="risk-zones.php" class="btn btn-secondary btn-lg">
                  <i class="bi bi-arrow-left me-2"></i>Back to Risk Zones
                </a>
              </div>
            </form>
            
            <!-- Calculation Progress -->
            <div id="calculationProgress" class="mt-4" style="display: none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-title">Calculation Progress</h6>
                  <div class="progress mb-3" style="height: 30px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                  </div>
                  <div id="progressStatus" class="text-center">
                    <i class="bi bi-arrow-clockwise spin"></i> Initializing calculation...
                  </div>
                  <div id="progressDetails" class="mt-3">
                    <!-- Progress details will be shown here -->
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Calculation Results -->
            <div id="calculationResults" class="mt-4" style="display: none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-title">Calculation Results</h6>
                  <div id="resultsContent">
                    <!-- Results will be shown here -->
                  </div>
                  <div class="text-center mt-3">
                    <a href="risk-zones.php" class="btn btn-primary">
                      <i class="bi bi-eye me-2"></i>View Risk Zones
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Information Panel -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Calculation Algorithm</h5>
            
            <div class="list-group">
              <div class="list-group-item">
                <h6 class="mb-1"><i class="bi bi-1-circle text-primary"></i> Geographic Clustering</h6>
                <p class="mb-0 small">Groups nearby outbreaks within the specified cluster radius to identify risk areas.</p>
              </div>
              <div class="list-group-item">
                <h6 class="mb-1"><i class="bi bi-2-circle text-primary"></i> Risk Score Calculation</h6>
                <p class="mb-0 small">Calculates risk scores (0–100) based on outbreak count (60 pts), recency of last outbreak (25 pts), and average severity level (15 pts).</p>
              </div>
              <div class="list-group-item">
                <h6 class="mb-1"><i class="bi bi-3-circle text-primary"></i> Risk Level Classification</h6>
                <p class="mb-0 small">Classifies zones as:
                  <br><span class="badge bg-success">Low</span> (0-39),
                  <span class="badge bg-warning">Medium</span> (40-59),
                  <span class="badge bg-danger">High</span> (60-79),
                  <span class="badge bg-dark">Critical</span> (80-100)
                </p>
              </div>
              <div class="list-group-item">
                <h6 class="mb-1"><i class="bi bi-4-circle text-primary"></i> Zone Generation</h6>
                <p class="mb-0 small">Creates risk zones with geographic boundaries, risk scores, and contributing factors.</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Recent Calculations -->
        <?php if (!empty($calculationHistory)): ?>
        <div class="card mt-3">
          <div class="card-body">
            <h5 class="card-title">Recent Calculations</h5>
            <div class="list-group list-group-flush">
              <?php foreach ($calculationHistory as $calc): ?>
              <div class="list-group-item">
                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($calc['created_at'])); ?></small>
                <br>
                <strong><?php echo number_format($calc['successful_records']); ?> zones created</strong>
                <?php if ($calc['failed_records'] > 0): ?>
                  <span class="text-danger">(<?php echo number_format($calc['failed_records']); ?> failed)</span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      
    </div>
  </section>

</main><!-- End #main -->

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('calculateRiskZonesForm');
  const calculateBtn = document.getElementById('calculateBtn');
  const progressDiv = document.getElementById('calculationProgress');
  const resultsDiv = document.getElementById('calculationResults');
  const progressBar = document.getElementById('progressBar');
  const progressStatus = document.getElementById('progressStatus');
  const progressDetails = document.getElementById('progressDetails');
  const resultsContent = document.getElementById('resultsContent');
  
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Disable form
    calculateBtn.disabled = true;
    calculateBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Calculating...';
    
    // Show progress
    progressDiv.style.display = 'block';
    resultsDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressStatus.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Initializing calculation...';
    progressDetails.innerHTML = '';
    
    // Get form data
    const formData = new FormData(form);
    formData.append('action', 'calculate');
    
    // Start calculation
    fetch('ajax/calculate_risk_zones.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Show results
        progressDiv.style.display = 'none';
        resultsDiv.style.display = 'block';
        
        resultsContent.innerHTML = `
          <div class="alert alert-success">
            <h5><i class="bi bi-check-circle me-2"></i>Calculation Completed Successfully!</h5>
            <hr>
                  <div class="row">
              <div class="col-md-6">
                <p><strong>Total Zones Created:</strong> <span class="badge bg-success">${data.total_zones}</span></p>
                <p><strong>Infected Zones:</strong> <span class="badge bg-danger">${data.infected_zones || 0}</span></p>
                <p><strong>Buffer Zones:</strong> <span class="badge" style="background-color: #e91e63;">${data.buffer_zones || 0}</span></p>
                <p><strong>Surveillance Zones:</strong> <span class="badge bg-warning">${data.surveillance_zones || 0}</span></p>
              </div>
              <div class="col-md-6">
                <p><strong>Protected Zones:</strong> <span class="badge" style="background-color: #90ee90; color: #000;">${data.protected_zones || 0}</span></p>
                <p><strong>Free Zones:</strong> <span class="badge" style="background-color: #228b22;">${data.free_zones || 0}</span></p>
                <p><strong>Processing Time:</strong> ${data.processing_time || 'N/A'}</p>
              </div>
            </div>
            ${data.message ? `<p class="mt-3">${data.message}</p>` : ''}
          </div>
        `;
        
        // Reload page after 3 seconds to refresh data
        setTimeout(() => {
          window.location.href = 'risk-zones.php';
        }, 3000);
      } else {
        // Show error
        progressDiv.style.display = 'none';
        resultsDiv.style.display = 'block';
        const errorMessage = data.message || 'An error occurred during calculation. Please try again.';
        const escapeHtml = (text) => {
          const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
          };
          return text.replace(/[&<>"']/g, m => map[m]);
        };
        let errorHtml = `
          <div class="alert alert-danger">
            <h5><i class="bi bi-exclamation-triangle me-2"></i>Calculation Failed</h5>
            <p>${escapeHtml(errorMessage)}</p>
          </div>
        `;
        
        // Add helpful suggestions for common errors
        if (errorMessage.includes('No outbreak data found')) {
          errorHtml += `
            <div class="alert alert-info mt-3">
              <h6><i class="bi bi-info-circle me-2"></i>Next Steps:</h6>
              <ol>
                <li>Go to <a href="data-uploads.php" class="alert-link">Data Uploads</a> page</li>
                <li>Upload ASF outbreak data CSV file</li>
                <li>Try calculating risk zones again</li>
              </ol>
            </div>
          `;
        } else if (errorMessage.includes('No outbreaks found within the specified lookback period')) {
          errorHtml += `
            <div class="alert alert-warning mt-3">
              <h6><i class="bi bi-exclamation-circle me-2"></i>Suggestions:</h6>
              <ul class="mb-0">
                <li>Try increasing the lookback period (e.g., 180 or 365 days)</li>
                <li>Check that your outbreak dates are recent enough</li>
                <li>Ensure outbreaks have valid latitude/longitude coordinates</li>
              </ul>
            </div>
          `;
        }
        
        resultsContent.innerHTML = errorHtml;
      }
      
      calculateBtn.disabled = false;
      calculateBtn.innerHTML = '<i class="bi bi-calculator me-2"></i>Calculate Risk Zones';
    })
    .catch(error => {
      console.error('Error:', error);
      progressDiv.style.display = 'none';
      resultsDiv.style.display = 'block';
      resultsContent.innerHTML = `
        <div class="alert alert-danger">
          <h5><i class="bi bi-exclamation-triangle me-2"></i>Error</h5>
          <p>Failed to calculate risk zones. Please try again.</p>
        </div>
      `;
      calculateBtn.disabled = false;
      calculateBtn.innerHTML = '<i class="bi bi-calculator me-2"></i>Calculate Risk Zones';
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
