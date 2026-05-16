<?php
/**
 * Admin Dashboard for ASF Surveillance System
 * Accessible by administrators
 * Aligned with ASF database schema
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// All logged-in users can access the admin dashboard
// Sidebar links are controlled by RBAC permissions in admin/includes/sidebar.php

$currentUser = getCurrentUser();
$pageTitle = 'Admin Dashboard';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * Get dynamic system statistics for ASF Surveillance
 */
function getSystemStatistics($pdo) {
    try {
        $stats = [
            'total_users' => 0,
            'total_field_staff' => 0,
            'total_analysts' => 0,
            'total_outbreaks' => 0,
            'active_outbreaks' => 0,
            'resolved_outbreaks' => 0,
            'total_depopulation_events' => 0,
            'total_pigs_depopulated' => 0,
            'total_meat_movements' => 0,
            'total_risk_zones' => 0,
            'high_risk_zones' => 0,
            'total_environmental_records' => 0,
            'total_alerts' => 0,
            'active_alerts' => 0,
            'total_uploads' => 0,
            'pending_uploads' => 0,
            'total_reports' => 0,
            'total_news_articles' => 0
        ];
        
        // Count users by role
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_accounts");
        $stats['total_users'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_accounts WHERE user_role = 'field_staff'");
        $stats['total_field_staff'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_accounts WHERE user_role = 'analyst'");
        $stats['total_analysts'] = $stmt->fetch()['total'];
        
        // Outbreak statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM asf_outbreaks");
        $stats['total_outbreaks'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM asf_outbreaks WHERE status IN ('suspected', 'confirmed', 'contained')");
        $stats['active_outbreaks'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM asf_outbreaks WHERE status = 'resolved'");
        $stats['resolved_outbreaks'] = $stmt->fetch()['total'];
        
        // Depopulation statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM depopulation_events");
        $stats['total_depopulation_events'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT SUM(head_count) as total FROM depopulation_events");
        $result = $stmt->fetch();
        $stats['total_pigs_depopulated'] = $result['total'] ?: 0;
        
        // Meat movement statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM meat_movement");
        $stats['total_meat_movements'] = $stmt->fetch()['total'];
        
        // Risk zone statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM risk_zones");
        $stats['total_risk_zones'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM risk_zones WHERE risk_level IN ('high', 'critical') AND status IN ('active', 'monitoring')");
        $stats['high_risk_zones'] = $stmt->fetch()['total'];
        
        // Environmental data statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM environmental_data");
        $stats['total_environmental_records'] = $stmt->fetch()['total'];
        
        // Alert statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_alerts");
        $stats['total_alerts'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_alerts WHERE status = 'active'");
        $stats['active_alerts'] = $stmt->fetch()['total'];
        
        // Data upload statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM data_uploads");
        $stats['total_uploads'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM data_uploads WHERE status IN ('pending', 'processing')");
        $stats['pending_uploads'] = $stmt->fetch()['total'];
        
        // Report statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM generated_reports");
        $stats['total_reports'] = $stmt->fetch()['total'];
        
        // News articles statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM news_articles");
        $stats['total_news_articles'] = $stmt->fetch()['total'];
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error fetching system statistics: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_field_staff' => 0,
            'total_analysts' => 0,
            'total_outbreaks' => 0,
            'active_outbreaks' => 0,
            'resolved_outbreaks' => 0,
            'total_depopulation_events' => 0,
            'total_pigs_depopulated' => 0,
            'total_meat_movements' => 0,
            'total_risk_zones' => 0,
            'high_risk_zones' => 0,
            'total_environmental_records' => 0,
            'total_alerts' => 0,
            'active_alerts' => 0,
            'total_uploads' => 0,
            'pending_uploads' => 0,
            'total_reports' => 0,
            'total_news_articles' => 0
        ];
    }
}

/**
 * Get outbreak status breakdown
 */
function getOutbreakStatusBreakdown($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM asf_outbreaks
            GROUP BY status
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $breakdown = [
            'suspected' => 0,
            'confirmed' => 0,
            'contained' => 0,
            'resolved' => 0,
            'false_alarm' => 0
        ];
        
        foreach ($results as $result) {
            $status = $result['status'];
            if (isset($breakdown[$status])) {
                $breakdown[$status] = $result['count'];
            }
        }
        
        return $breakdown;
    } catch (Exception $e) {
        error_log("Error fetching outbreak status breakdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Get monthly outbreak trends
 */
function getMonthlyOutbreakTrends($pdo, $months = 6) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(outbreak_date, '%Y-%m') as month,
                DATE_FORMAT(outbreak_date, '%b %Y') as month_label,
                COUNT(*) as count
            FROM asf_outbreaks
            WHERE outbreak_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(outbreak_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$months]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $results;
    } catch (Exception $e) {
        error_log("Error fetching monthly outbreak trends: " . $e->getMessage());
        return [];
    }
}

/**
 * Get risk zone distribution
 */
function getRiskZoneDistribution($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                risk_level,
                COUNT(*) as count
            FROM risk_zones
            WHERE status IN ('active', 'monitoring')
            GROUP BY risk_level
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $distribution = [];
        foreach ($results as $result) {
            $distribution[] = [
                'risk_level' => $result['risk_level'],
                'count' => $result['count']
            ];
        }
        
        return $distribution;
    } catch (Exception $e) {
        error_log("Error fetching risk zone distribution: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent outbreaks
 */
function getRecentOutbreaks($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
            FROM asf_outbreaks o
            LEFT JOIN user_accounts u ON o.reported_by = u.id
            ORDER BY o.reported_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching recent outbreaks: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent activities
 */
function getRecentActivities($pdo, $limit = 10) {
    try {
        $activities = [];
        
        // Get recent outbreaks
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    o.id, o.outbreak_code, o.location_name, o.status, o.reported_date,
                    CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
                FROM asf_outbreaks o
                LEFT JOIN user_accounts u ON o.reported_by = u.id
                ORDER BY o.reported_date DESC
                LIMIT 5
            ");
            $stmt->execute();
            $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($outbreaks as $outbreak) {
                $activities[] = [
                    'type' => 'outbreak',
                    'description' => 'Outbreak: ' . htmlspecialchars($outbreak['location_name']) . ' (' . ucfirst(str_replace('_', ' ', $outbreak['status'])) . ')',
                    'user' => $outbreak['reported_by_name'] ?: 'Unknown User',
                    'timestamp' => $outbreak['reported_date'],
                    'icon' => 'exclamation-triangle',
                    'color' => getOutbreakStatusColor($outbreak['status'])
                ];
            }
        } catch (Exception $e) {
            // Table doesn't exist yet
        }
        
        // Get recent depopulation events
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    d.id, d.event_code, d.location_name, d.event_date, d.head_count,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM depopulation_events d
                LEFT JOIN user_accounts u ON d.created_by = u.id
                ORDER BY d.event_date DESC
                LIMIT 5
            ");
            $stmt->execute();
            $depopulations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($depopulations as $depop) {
                $activities[] = [
                    'type' => 'depopulation',
                    'description' => 'Depopulation: ' . htmlspecialchars($depop['location_name']) . ' (' . number_format($depop['head_count']) . ' heads)',
                    'user' => $depop['created_by_name'] ?: 'Unknown User',
                    'timestamp' => $depop['event_date'],
                    'icon' => 'file-earmark-medical',
                    'color' => 'danger'
                ];
            }
        } catch (Exception $e) {
            // Table doesn't exist yet
        }
        
        // Get recent data uploads
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    du.id, du.upload_code, du.upload_type, du.status, du.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM data_uploads du
                LEFT JOIN user_accounts u ON du.uploaded_by = u.id
                ORDER BY du.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($uploads as $upload) {
                $activities[] = [
                    'type' => 'upload',
                    'description' => 'Data Upload: ' . ucwords(str_replace('_', ' ', $upload['upload_type'])) . ' (' . ucfirst(str_replace('_', ' ', $upload['status'])) . ')',
                    'user' => $upload['uploaded_by_name'] ?: 'Unknown User',
                    'timestamp' => $upload['created_at'],
                    'icon' => 'upload',
                    'color' => 'info'
                ];
            }
        } catch (Exception $e) {
            // Table doesn't exist yet
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
    } catch (Exception $e) {
        error_log("Error fetching recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get CALABARZON active ASF cases breakdown
 */
function getCalabarzonActiveCases($pdo) {
    try {
        $calabarzonProvinces = ['Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon'];
        $cases = [];
        $summary = [
            'provinces' => 0,
            'cities' => 0,
            'barangays' => 0
        ];
        
        // Get active outbreaks (suspected, confirmed, contained)
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                province,
                city,
                barangay
            FROM asf_outbreaks
            WHERE status IN ('suspected', 'confirmed', 'contained')
            AND province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND barangay IS NOT NULL
            AND barangay != ''
            ORDER BY province, city, barangay
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $provinceSet = [];
        $citySet = [];
        $barangaySet = [];
        $grouped = [];
        
        foreach ($results as $row) {
            $province = $row['province'];
            $city = $row['city'];
            $barangay = $row['barangay'];
            
            // Normalize province name
            if ($province === 'CALABARZON') {
                continue; // Skip generic CALABARZON entries
            }
            
            $provinceSet[$province] = true;
            
            if (!isset($grouped[$province])) {
                $grouped[$province] = [];
            }
            if (!isset($grouped[$province][$city])) {
                $grouped[$province][$city] = [];
                $citySet[$province . '|' . $city] = true;
            }
            
            $barangayKey = $province . '|' . $city . '|' . $barangay;
            if (!isset($barangaySet[$barangayKey])) {
                $grouped[$province][$city][] = $barangay;
                $barangaySet[$barangayKey] = true;
            }
        }
        
        // Format data for display
        foreach ($grouped as $province => $cities) {
            foreach ($cities as $city => $barangays) {
                $cases[] = [
                    'province' => $province,
                    'city' => $city,
                    'barangay_count' => count($barangays)
                ];
            }
        }
        
        $summary['provinces'] = count($provinceSet);
        $summary['cities'] = count($citySet);
        $summary['barangays'] = count($barangaySet);
        
        return [
            'cases' => $cases,
            'summary' => $summary
        ];
    } catch (Exception $e) {
        error_log("Error fetching CALABARZON active cases: " . $e->getMessage());
        return [
            'cases' => [],
            'summary' => ['provinces' => 0, 'cities' => 0, 'barangays' => 0]
        ];
    }
}

/**
 * Get CALABARZON monthly ASF positive barangays
 */
function getCalabarzonMonthlyPositiveBarangays($pdo, $months = 24) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(outbreak_date, '%Y-%m') as month,
                DATE_FORMAT(outbreak_date, '%b %Y') as month_label,
                COUNT(DISTINCT CONCAT(province, '|', city, '|', IFNULL(barangay, ''))) as barangay_count
            FROM asf_outbreaks
            WHERE status IN ('suspected', 'confirmed', 'contained', 'resolved')
            AND province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND outbreak_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            AND barangay IS NOT NULL
            AND barangay != ''
            GROUP BY DATE_FORMAT(outbreak_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching CALABARZON monthly positive barangays: " . $e->getMessage());
        return [];
    }
}

/**
 * Get CALABARZON zone recovery status
 */
function getCalabarzonZoneRecoveryStatus($pdo) {
    try {
        // Map risk levels to zone types
        // High/Critical = Infected Zone, Medium = Buffer Zone, Low = Surveillance Zone
        $stmt = $pdo->query("
            SELECT 
                risk_level,
                COUNT(*) as count
            FROM risk_zones
            WHERE province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND status IN ('active', 'monitoring')
            GROUP BY risk_level
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $zones = [
            'infected' => 0,  // high + critical
            'buffer' => 0,    // medium
            'surveillance' => 0  // low
        ];
        
        foreach ($results as $row) {
            $level = $row['risk_level'];
            $count = $row['count'];
            
            if (in_array($level, ['high', 'critical'])) {
                $zones['infected'] += $count;
            } elseif ($level === 'medium') {
                $zones['buffer'] += $count;
            } elseif ($level === 'low') {
                $zones['surveillance'] += $count;
            }
        }
        
        $total = array_sum($zones);
        if ($total > 0) {
            return [
                'infected' => round(($zones['infected'] / $total) * 100, 1),
                'buffer' => round(($zones['buffer'] / $total) * 100, 1),
                'surveillance' => round(($zones['surveillance'] / $total) * 100, 1),
                'total' => $total
            ];
        }
        
        return ['infected' => 0, 'buffer' => 0, 'surveillance' => 0, 'total' => 0];
    } catch (Exception $e) {
        error_log("Error fetching CALABARZON zone recovery status: " . $e->getMessage());
        return ['infected' => 0, 'buffer' => 0, 'surveillance' => 0, 'total' => 0];
    }
}

/**
 * Get CALABARZON areas recovered data
 */
function getCalabarzonAreasRecovered($pdo) {
    try {
        // Areas recovered from high/critical to medium (red to pink)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM risk_zones
            WHERE province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND status = 'cleared'
            AND risk_level IN ('medium', 'low')
        ");
        $stmt->execute();
        $redToPink = $stmt->fetch()['count'];
        
        // Areas recovered from medium to low (pink to yellow)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM risk_zones
            WHERE province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND status = 'cleared'
            AND risk_level = 'low'
        ");
        $stmt->execute();
        $pinkToYellow = $stmt->fetch()['count'];
        
        return [
            'red_to_pink' => $redToPink,
            'pink_to_yellow' => $pinkToYellow
        ];
    } catch (Exception $e) {
        error_log("Error fetching CALABARZON areas recovered: " . $e->getMessage());
        return ['red_to_pink' => 0, 'pink_to_yellow' => 0];
    }
}

/**
 * Get cumulative affected areas for CALABARZON
 */
function getCalabarzonCumulativeAffected($pdo) {
    try {
        // Get cumulative counts from 2019 to present
        $stmt = $pdo->query("
            SELECT 
                COUNT(DISTINCT province) as provinces,
                COUNT(DISTINCT city) as cities,
                COUNT(DISTINCT CONCAT(province, '|', city, '|', IFNULL(barangay, ''))) as barangays
            FROM asf_outbreaks
            WHERE province IN ('Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon', 'CALABARZON')
            AND outbreak_date >= '2019-01-01'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'provinces' => $result['provinces'] ?: 0,
            'cities' => $result['cities'] ?: 0,
            'barangays' => $result['barangays'] ?: 0
        ];
    } catch (Exception $e) {
        error_log("Error fetching CALABARZON cumulative affected: " . $e->getMessage());
        return ['provinces' => 0, 'cities' => 0, 'barangays' => 0];
    }
}

/**
 * Get monthly comparison stats
 */
function getMonthlyComparison($pdo) {
    try {
        $stats = [];
        
        // This month outbreaks
        $stmt = $pdo->query("
            SELECT COUNT(*) as total FROM asf_outbreaks 
            WHERE MONTH(outbreak_date) = MONTH(CURDATE()) 
            AND YEAR(outbreak_date) = YEAR(CURDATE())
        ");
        $stats['outbreaks_this_month'] = $stmt->fetch()['total'];
        
        // Last month outbreaks
        $stmt = $pdo->query("
            SELECT COUNT(*) as total FROM asf_outbreaks 
            WHERE MONTH(outbreak_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
            AND YEAR(outbreak_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ");
        $stats['outbreaks_last_month'] = $stmt->fetch()['total'];
        
        // Calculate growth
        if ($stats['outbreaks_last_month'] > 0) {
            $stats['outbreak_growth'] = round((($stats['outbreaks_this_month'] - $stats['outbreaks_last_month']) / $stats['outbreaks_last_month']) * 100, 1);
        } else {
            $stats['outbreak_growth'] = 0;
        }
        
        // This month depopulations
        $stmt = $pdo->query("
            SELECT SUM(head_count) as total FROM depopulation_events 
            WHERE MONTH(event_date) = MONTH(CURDATE()) 
            AND YEAR(event_date) = YEAR(CURDATE())
        ");
        $result = $stmt->fetch();
        $stats['depopulated_this_month'] = $result['total'] ?: 0;
        
        // Last month depopulations
        $stmt = $pdo->query("
            SELECT SUM(head_count) as total FROM depopulation_events 
            WHERE MONTH(event_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND YEAR(event_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ");
        $result = $stmt->fetch();
        $stats['depopulated_last_month'] = $result['total'] ?: 0;
        
        if ($stats['depopulated_last_month'] > 0) {
            $stats['depopulation_growth'] = round((($stats['depopulated_this_month'] - $stats['depopulated_last_month']) / $stats['depopulated_last_month']) * 100, 1);
        } else {
            $stats['depopulation_growth'] = 0;
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error fetching monthly comparison: " . $e->getMessage());
        return [
            'outbreaks_this_month' => 0,
            'outbreaks_last_month' => 0,
            'outbreak_growth' => 0,
            'depopulated_this_month' => 0,
            'depopulated_last_month' => 0,
            'depopulation_growth' => 0
        ];
    }
}

/**
 * Helper function for outbreak status color classes
 */
function getOutbreakStatusColor($status) {
    $colors = [
        'suspected' => 'warning',
        'confirmed' => 'danger',
        'contained' => 'info',
        'resolved' => 'success',
        'false_alarm' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

/**
 * Time ago helper function
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('M d, Y', $time);
}

$stats = getSystemStatistics($pdo);
$outbreak_status_breakdown = getOutbreakStatusBreakdown($pdo);
$outbreak_trends = getMonthlyOutbreakTrends($pdo);
$risk_zone_distribution = getRiskZoneDistribution($pdo);
$recent_activities = getRecentActivities($pdo, 10);
$monthly_comparison = getMonthlyComparison($pdo);

// CALABARZON-specific data
$calabarzon_active_cases = getCalabarzonActiveCases($pdo);
$calabarzon_monthly_barangays = getCalabarzonMonthlyPositiveBarangays($pdo, 24);
$calabarzon_zone_recovery = getCalabarzonZoneRecoveryStatus($pdo);
$calabarzon_areas_recovered = getCalabarzonAreasRecovered($pdo);
$calabarzon_cumulative = getCalabarzonCumulativeAffected($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .dashboard-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 20px;
    }
    
    .stat-card {
      border-left: 4px solid #4154f1;
      transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .chart-card {
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border: 1px solid #e9ecef;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Admin Dashboard</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Admin Dashboard</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      
      <!-- Dashboard Header -->
      <div class="dashboard-header">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h2 class="mb-1">Welcome back, <?php echo isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'Admin'; ?>!</h2>
            <p class="mb-1"><i class="bi bi-shield-shaded"></i> System Administrator</p>
            <p class="mb-0"><i class="bi bi-calendar-check"></i> <?php echo date('l, F d, Y'); ?></p>
          </div>
          <div class="col-md-4 text-md-end">
            <span class="badge bg-light text-dark" style="font-size: 1.1rem; padding: 10px 20px;">
              <i class="bi bi-graph-up"></i> ASF Surveillance Analytics
            </span> 
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="card stat-card">
            <div class="card-body">
              <h5 class="card-title">Total Outbreaks</h5>
              <div class="d-flex align-items-center">
                <div class="ps-3">
                  <h3 class="text-danger"><?php echo number_format($stats['total_outbreaks']); ?></h3>
                  <span class="text-muted small"><?php echo number_format($stats['active_outbreaks']); ?> active</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card">
            <div class="card-body">
              <h5 class="card-title">High-Risk Zones</h5>
              <div class="d-flex align-items-center">
                <div class="ps-3">
                  <h3 class="text-warning"><?php echo number_format($stats['high_risk_zones']); ?></h3>
                  <span class="text-muted small"><?php echo number_format($stats['total_risk_zones']); ?> total zones</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card">
            <div class="card-body">
              <h5 class="card-title">Pigs Depopulated</h5>
              <div class="d-flex align-items-center">
                <div class="ps-3">
                  <h3 class="text-info"><?php echo number_format($stats['total_pigs_depopulated']); ?></h3>
                  <span class="text-muted small"><?php echo number_format($stats['total_depopulation_events']); ?> events</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <div class="card stat-card">
            <div class="card-body">
              <h5 class="card-title">Active Alerts</h5>
              <div class="d-flex align-items-center">
                <div class="ps-3">
                  <h3 class="text-danger"><?php echo number_format($stats['active_alerts']); ?></h3>
                  <span class="text-muted small"><?php echo number_format($stats['total_alerts']); ?> total alerts</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CALABARZON Visualization Reports -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card chart-card">
            <div class="card-body">
              <div class="row">
                <!-- Active Cases Table -->
                <div class="col-lg-4">
                  <h5 class="card-title mb-3" style="color: #8B0000; font-weight: bold;">
                    NUMBER OF ACTIVE ASF CASES
                  </h5>
                  <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-bordered">
                      <thead style="background-color: #8B0000; color: white;">
                        <tr>
                          <th colspan="3" class="text-center">
                            <small>
                              <?php echo $calabarzon_active_cases['summary']['provinces']; ?> Provinces, 
                              <?php echo $calabarzon_active_cases['summary']['cities']; ?> Cities/Municipalities, 
                              <?php echo $calabarzon_active_cases['summary']['barangays']; ?> Barangays
                            </small>
                          </th>
                        </tr>
                        <tr>
                          <th>Province</th>
                          <th>City/Municipality</th>
                          <th class="text-center">Barangays</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($calabarzon_active_cases['cases'])): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted">No active cases</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($calabarzon_active_cases['cases'] as $case): ?>
                            <tr>
                              <td><?php echo htmlspecialchars($case['province']); ?></td>
                              <td><?php echo htmlspecialchars($case['city']); ?></td>
                              <td class="text-center"><?php echo $case['barangay_count']; ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Monthly Positive Barangays Chart -->
                <div class="col-lg-5">
                  <h5 class="card-title mb-3">
                    Number of ASF Positive Barangays as of <?php echo date('d F Y'); ?>
                  </h5>
                  <canvas id="calabarzonMonthlyBarangaysChart" style="max-height: 350px;"></canvas>
                </div>

                <!-- Zone Recovery and Areas Recovered -->
                <div class="col-lg-3">
                  <!-- Zone Recovery Status -->
                  <h5 class="card-title mb-3" style="font-size: 0.9rem;">
                    Status of Zone Recovery as of <?php echo date('d F Y'); ?>
                  </h5>
                  <?php if ($calabarzon_zone_recovery['total'] > 0): ?>
                    <div class="mb-4">
                      <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <small>CALABARZON</small>
                        </div>
                        <div class="progress" style="height: 25px;">
                          <div class="progress-bar bg-danger" role="progressbar" 
                               style="width: <?php echo $calabarzon_zone_recovery['infected']; ?>%" 
                               title="Infected Zone: <?php echo $calabarzon_zone_recovery['infected']; ?>%">
                            <small><?php echo $calabarzon_zone_recovery['infected']; ?>%</small>
                          </div>
                          <div class="progress-bar" style="background-color: #ff69b4; width: <?php echo $calabarzon_zone_recovery['buffer']; ?>%" 
                               title="Buffer Zone: <?php echo $calabarzon_zone_recovery['buffer']; ?>%">
                            <small><?php echo $calabarzon_zone_recovery['buffer']; ?>%</small>
                          </div>
                          <div class="progress-bar bg-warning" role="progressbar" 
                               style="width: <?php echo $calabarzon_zone_recovery['surveillance']; ?>%" 
                               title="Surveillance Zone: <?php echo $calabarzon_zone_recovery['surveillance']; ?>%">
                            <small><?php echo $calabarzon_zone_recovery['surveillance']; ?>%</small>
                          </div>
                        </div>
                        <div class="mt-1">
                          <small>
                            <span class="badge bg-danger me-1">Infected</span>
                            <span class="badge" style="background-color: #ff69b4;">Buffer</span>
                            <span class="badge bg-warning">Surveillance</span>
                          </small>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <p class="text-muted small">No zone data available</p>
                  <?php endif; ?>

                  <!-- Areas Recovered -->
                  <div class="mt-3">
                    <h6 class="card-title mb-2" style="font-size: 0.85rem; background-color: #ffe4e1; padding: 8px; border-radius: 5px;">
                      AREAS RECOVERED FROM RED TO PINK
                    </h6>
                    <p class="text-center mb-3" style="font-size: 1.5rem; font-weight: bold; color: #8B0000;">
                      <?php echo number_format($calabarzon_areas_recovered['red_to_pink']); ?>
                    </p>
                  </div>
                  <div class="mt-2">
                    <h6 class="card-title mb-2" style="font-size: 0.85rem; background-color: #fffacd; padding: 8px; border-radius: 5px;">
                      AREAS RECOVERED FROM PINK TO YELLOW
                    </h6>
                    <p class="text-center mb-0" style="font-size: 1.5rem; font-weight: bold; color: #daa520;">
                      <?php echo number_format($calabarzon_areas_recovered['pink_to_yellow']); ?>
                    </p>
                  </div>
                </div>
              </div>

              <!-- Cumulative Affected Areas -->
              <div class="row mt-3">
                <div class="col-12">
                  <div class="card" style="background-color: #e3f2fd; border: 1px solid #90caf9;">
                    <div class="card-body py-2">
                      <h6 class="card-title mb-2" style="color: #1976d2; font-weight: bold;">
                        CUMULATIVE NUMBER OF AFFECTED AREAS FROM 2019-PRESENT
                      </h6>
                      <div class="row text-center">
                        <div class="col-md-4">
                          <strong style="font-size: 1.2rem;"><?php echo $calabarzon_cumulative['provinces']; ?></strong>
                          <br><small class="text-muted">Provinces</small>
                        </div>
                        <div class="col-md-4">
                          <strong style="font-size: 1.2rem;"><?php echo number_format($calabarzon_cumulative['cities']); ?></strong>
                          <br><small class="text-muted">Cities/Municipalities</small>
                        </div>
                        <div class="col-md-4">
                          <strong style="font-size: 1.2rem;"><?php echo number_format($calabarzon_cumulative['barangays']); ?></strong>
                          <br><small class="text-muted">Barangays</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- End CALABARZON Visualization Reports -->

      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-8">
          <div class="row">

            <!-- Outbreak Status Chart -->
            <div class="col-12">
              <div class="card chart-card">
                <div class="card-body">
                  <h5 class="card-title">
                    Outbreak Status Overview
                    <i class="bi bi-circle-fill text-success" style="font-size: 8px; margin-left: 5px;" title="Live data from database"></i>
                  </h5>
                  <?php if (empty($outbreak_status_breakdown) || array_sum($outbreak_status_breakdown) == 0): ?>
                    <div class="text-center py-5">
                      <i class="bi bi-pie-chart" style="font-size: 3rem; color: #ddd;"></i>
                      <p class="text-muted mt-3">No outbreak data available yet</p>
                      <small class="text-muted">Status distribution will appear when outbreaks are recorded</small>
                    </div>
                  <?php else: ?>
                    <canvas id="outbreakStatusChart" style="max-height: 300px;"></canvas>
                  <?php endif; ?>
                </div>
              </div>
            </div><!-- End Outbreak Status Chart -->

            <!-- Outbreak Trend Chart -->
            <div class="col-12">
              <div class="card chart-card">
                <div class="card-body">
                  <h5 class="card-title">
                    Outbreak Trend 
                    <span>| Last 6 Months</span>
                    <i class="bi bi-circle-fill text-success" style="font-size: 8px; margin-left: 5px;" title="Live data from database"></i>
                  </h5>
                  <?php if (empty($outbreak_trends)): ?>
                    <div class="text-center py-5">
                      <i class="bi bi-graph-up" style="font-size: 3rem; color: #ddd;"></i>
                      <p class="text-muted mt-3">No outbreak data for the last 6 months</p>
                      <small class="text-muted">Outbreak trends will appear here when outbreaks are reported</small>
                    </div>
                  <?php else: ?>
                    <canvas id="outbreakTrendChart" style="max-height: 300px;"></canvas>
                  <?php endif; ?>
                </div>
              </div>
            </div><!-- End Outbreak Trend Chart -->

          </div>
        </div><!-- End Left side columns -->

        <!-- Right side columns -->
        <div class="col-lg-4">

          <!-- Risk Zone Distribution Chart -->
          <div class="card chart-card">
            <div class="card-body">
              <h5 class="card-title">
                Risk Zone Distribution
                <i class="bi bi-circle-fill text-success" style="font-size: 8px; margin-left: 5px;" title="Live data from database"></i>
              </h5>
              <?php if (empty($risk_zone_distribution)): ?>
                <div class="text-center py-4">
                  <i class="bi bi-diagram-3" style="font-size: 2.5rem; color: #ddd;"></i>
                  <p class="text-muted mt-3">No risk zone data</p>
                  <small class="text-muted">Risk zones will appear here</small>
                </div>
              <?php else: ?>
                <canvas id="riskZoneChart" style="max-height: 250px;"></canvas>
              <?php endif; ?>
            </div>
          </div><!-- End Risk Zone Distribution -->

          <!-- Monthly Growth Comparison -->
          <div class="card chart-card">
            <div class="card-body">
              <h5 class="card-title">
                Monthly Growth
                <i class="bi bi-circle-fill text-success" style="font-size: 8px; margin-left: 5px;" title="Live data"></i>
              </h5>
              
              <div class="mb-3">
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Outbreaks This Month:</span>
                    <strong class="text-primary"><?php echo number_format($monthly_comparison['outbreaks_this_month']); ?></strong>
                  </div>
                  <?php if ($monthly_comparison['outbreak_growth'] != 0): ?>
                  <div class="progress" style="height: 6px;">
                    <div class="progress-bar <?php echo $monthly_comparison['outbreak_growth'] > 0 ? 'bg-danger' : 'bg-success'; ?>" 
                         style="width: <?php echo min(abs($monthly_comparison['outbreak_growth']), 100); ?>%"></div>
                  </div>
                  <small class="<?php echo $monthly_comparison['outbreak_growth'] > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo $monthly_comparison['outbreak_growth'] > 0 ? '↑' : '↓'; ?> 
                    <?php echo abs($monthly_comparison['outbreak_growth']); ?>% vs last month
                  </small>
                  <?php endif; ?>
                </div>
                
                <div class="mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Depopulated This Month:</span>
                    <strong class="text-info"><?php echo number_format($monthly_comparison['depopulated_this_month']); ?></strong>
                  </div>
                  <?php if ($monthly_comparison['depopulation_growth'] != 0): ?>
                  <div class="progress" style="height: 6px;">
                    <div class="progress-bar <?php echo $monthly_comparison['depopulation_growth'] > 0 ? 'bg-danger' : 'bg-success'; ?>" 
                         style="width: <?php echo min(abs($monthly_comparison['depopulation_growth']), 100); ?>%"></div>
                  </div>
                  <small class="<?php echo $monthly_comparison['depopulation_growth'] > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo $monthly_comparison['depopulation_growth'] > 0 ? '↑' : '↓'; ?> 
                    <?php echo abs($monthly_comparison['depopulation_growth']); ?>% vs last month
                  </small>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div><!-- End Monthly Growth -->

          <!-- System Summary -->
          <div class="card chart-card">
            <div class="card-body">
              <h5 class="card-title">System Summary</h5>
              
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Total Users:</span>
                  <strong class="text-primary"><?php echo number_format($stats['total_users']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Field Staff:</span>
                  <strong class="text-info"><?php echo number_format($stats['total_field_staff']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Meat Movements:</span>
                  <strong class="text-secondary"><?php echo number_format($stats['total_meat_movements']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Environmental Records:</span>
                  <strong class="text-info"><?php echo number_format($stats['total_environmental_records']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Data Uploads:</span>
                  <strong class="text-primary"><?php echo number_format($stats['total_uploads']); ?></strong>
                </div>
                <?php if ($stats['pending_uploads'] > 0): ?>
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Pending Uploads:</span>
                  <a href="data-uploads.php?status=pending" class="text-warning fw-bold">
                    <?php echo number_format($stats['pending_uploads']); ?> <i class="bi bi-arrow-right"></i>
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div><!-- End System Summary -->

          <!-- Quick Actions -->
          <div class="card chart-card">
            <div class="card-body">
              <h5 class="card-title">Quick Actions</h5>
              <div class="d-grid gap-2">
                <a href="outbreaks.php" class="btn btn-danger">
                  <i class="bi bi-exclamation-triangle me-2"></i>View Outbreaks
                </a>
                <a href="risk-zones.php" class="btn btn-warning">
                  <i class="bi bi-map me-2"></i>Risk Zones
                </a>
                <a href="depopulation-events.php" class="btn btn-info">
                  <i class="bi bi-file-earmark-medical me-2"></i>Depopulation Events
                </a>
                <a href="data-uploads.php" class="btn btn-primary">
                  <i class="bi bi-upload me-2"></i>Data Uploads
                </a>
                <a href="system-alerts.php" class="btn btn-danger">
                  <i class="bi bi-bell me-2"></i>System Alerts
                  <?php if ($stats['active_alerts'] > 0): ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $stats['active_alerts']; ?></span>
                  <?php endif; ?>
                </a>
              </div>
            </div>
          </div><!-- End Quick Actions -->

        </div><!-- End Right side columns -->

      </div>

      <!-- Bottom Row -->
      <div class="row mt-4">

        <!-- Recent Activities -->
        <div class="col-lg-12">
          <div class="card chart-card">
            <div class="card-body">
              <h5 class="card-title">
                Recent Activities
                <i class="bi bi-circle-fill text-success" style="font-size: 8px; margin-left: 5px;" title="Live data"></i>
              </h5>
              
              <?php if (empty($recent_activities)): ?>
                <div class="text-center py-4">
                  <i class="bi bi-activity" style="font-size: 2.5rem; color: #ddd;"></i>
                  <p class="text-muted mt-3">No recent activity</p>
                </div>
              <?php else: ?>
                <div class="activity-feed" style="max-height: 400px; overflow-y: auto;">
                  <div class="row">
                  <?php foreach ($recent_activities as $activity): ?>
                      <div class="col-md-6 mb-3">
                        <div class="d-flex pb-3 border-bottom">
                          <div class="flex-shrink-0">
                            <div class="rounded-circle bg-<?php echo $activity['color']; ?> bg-opacity-10 p-2" style="width: 40px; height: 40px; display: flex; align-items-center; justify-content: center;">
                              <i class="bi bi-<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>"></i>
                            </div>
                          </div>
                          <div class="flex-grow-1 ms-3">
                            <p class="mb-0"><?php echo $activity['description']; ?></p>
                            <small class="text-muted">
                              <i class="bi bi-clock"></i> <?php echo timeAgo($activity['timestamp']); ?>
                            </small>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div><!-- End Recent Activities -->

      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <script>
    // Prepare data for charts
    const outbreakStatusData = <?php echo json_encode($outbreak_status_breakdown); ?>;
    const outbreakTrendData = <?php echo json_encode($outbreak_trends); ?>;
    const riskZoneData = <?php echo json_encode($risk_zone_distribution); ?>;
    const calabarzonMonthlyBarangaysData = <?php echo json_encode($calabarzon_monthly_barangays); ?>;

    // CALABARZON Monthly Positive Barangays Chart
    <?php if (!empty($calabarzon_monthly_barangays)): ?>
    const calabarzonMonthlyBarangaysCtx = document.getElementById('calabarzonMonthlyBarangaysChart');
    if (calabarzonMonthlyBarangaysCtx) {
      new Chart(calabarzonMonthlyBarangaysCtx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: calabarzonMonthlyBarangaysData.map(item => item.month_label),
          datasets: [{
            label: 'ASF Positive Barangays',
            data: calabarzonMonthlyBarangaysData.map(item => parseInt(item.barangay_count)),
            backgroundColor: 'rgba(220, 53, 69, 0.7)',
            borderColor: '#dc3545',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return 'Barangays: ' + context.parsed.y;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 10
              },
              title: {
                display: true,
                text: 'No. of Barangays'
              }
            },
            x: {
              ticks: {
                maxRotation: 45,
                minRotation: 45,
                font: {
                  size: 9
                }
              }
            }
          }
        }
      });
    }
    <?php endif; ?>

    // Outbreak Status Chart (Doughnut) - Only render if we have data
    <?php if (!empty($outbreak_status_breakdown) && array_sum($outbreak_status_breakdown) > 0): ?>
    const outbreakStatusCtx = document.getElementById('outbreakStatusChart').getContext('2d');
    new Chart(outbreakStatusCtx, {
      type: 'doughnut',
      data: {
        labels: ['Suspected', 'Confirmed', 'Contained', 'Resolved', 'False Alarm'],
        datasets: [{
          data: [
            outbreakStatusData.suspected || 0,
            outbreakStatusData.confirmed || 0,
            outbreakStatusData.contained || 0,
            outbreakStatusData.resolved || 0,
            outbreakStatusData.false_alarm || 0
          ],
          backgroundColor: [
            '#ffc107', // Suspected (yellow)
            '#dc3545', // Confirmed (red)
            '#17a2b8', // Contained (cyan)
            '#28a745', // Resolved (green)
            '#6c757d'  // False Alarm (gray)
          ],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 15,
              font: { size: 12 }
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return context.label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
    <?php endif; ?>

    // Outbreak Trend Chart (Line) - Only render if we have data
    <?php if (!empty($outbreak_trends)): ?>
    const outbreakTrendCtx = document.getElementById('outbreakTrendChart').getContext('2d');
    new Chart(outbreakTrendCtx, {
      type: 'line',
      data: {
        labels: outbreakTrendData.map(item => item.month_label),
        datasets: [{
          label: 'Outbreaks',
          data: outbreakTrendData.map(item => item.count),
          borderColor: '#dc3545',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          tension: 0.4,
          fill: true,
          borderWidth: 3,
          pointBackgroundColor: '#dc3545',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            callbacks: {
              label: function(context) {
                return 'Outbreaks: ' + context.parsed.y;
              }
            }
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
    <?php endif; ?>

    // Risk Zone Distribution Chart (Pie) - Only render if we have data
    <?php if (!empty($risk_zone_distribution)): ?>
    const riskZoneCtx = document.getElementById('riskZoneChart').getContext('2d');
    new Chart(riskZoneCtx, {
      type: 'pie',
      data: {
        labels: riskZoneData.map(item => {
          return item.risk_level.charAt(0).toUpperCase() + item.risk_level.slice(1);
        }),
        datasets: [{
          data: riskZoneData.map(item => item.count),
          backgroundColor: [
            '#28a745', // Low (green)
            '#ffc107', // Medium (yellow)
            '#fd7e14', // High (orange)
            '#dc3545'  // Critical (red)
          ],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 10,
              font: { size: 11 },
              boxWidth: 12
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return context.label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
    <?php endif; ?>
  </script>

</body>

</html>
