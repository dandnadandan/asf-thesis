<?php
/**
 * Admin Dashboard Sidebar Section
 * Contains the collapsible sidebar navigation for ASF Surveillance System
 */

// Ensure session manager functions are available
if (!function_exists('canManageUsers')) {
    require_once __DIR__ . '/../../includes/session_manager.php';
}
?>

<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">

  <ul class="sidebar-nav" id="sidebar-nav">

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? '' : 'collapsed'; ?>" href="index.php">
        <i class="bi bi-grid"></i>
        <span>Dashboard</span>
      </a>
    </li><!-- End Dashboard Nav -->

    <?php if (canManageUsers()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? '' : 'collapsed'; ?>" href="users.php">
        <i class="bi bi-people"></i><span>User Management</span>
      </a>
    </li><!-- End Users Nav -->
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#outbreaks-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-exclamation-triangle"></i><span>Outbreak Management</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="outbreaks-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
        <li>
          <a href="outbreaks.php">
            <i class="bi bi-circle"></i><span>All Outbreaks</span>
          </a>
        </li>
        <li>
          <a href="outbreaks.php?status=active">
            <i class="bi bi-circle"></i><span>Active Outbreaks</span>
          </a>
        </li>
        <li>
          <a href="outbreaks.php?status=confirmed">
            <i class="bi bi-circle"></i><span>Confirmed Outbreaks</span>
          </a>
        </li>
      </ul>
    </li><!-- End Outbreaks Nav -->

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'depopulation-events.php' ? '' : 'collapsed'; ?>" href="depopulation-events.php">
        <i class="bi bi-file-earmark-medical"></i>
        <span>Depopulation Events</span>
      </a>
    </li><!-- End Depopulation Events Nav -->

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'meat-movement.php' ? '' : 'collapsed'; ?>" href="meat-movement.php">
        <i class="bi bi-truck"></i>
        <span>Meat Movement</span>
      </a>
    </li><!-- End Meat Movement Nav -->

    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#risk-zones-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-map"></i><span>Risk Zones</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="risk-zones-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
        <li>
          <a href="risk-zones.php">
            <i class="bi bi-circle"></i><span>All Risk Zones</span>
          </a>
        </li>
        <li>
          <a href="risk-zones.php?level=high">
            <i class="bi bi-circle"></i><span>High-Risk Zones</span>
          </a>
        </li>
        <li>
          <a href="risk-zones.php?level=critical">
            <i class="bi bi-circle"></i><span>Critical Zones</span>
          </a>
        </li>
        <li>
          <a href="calculate-risk-zones.php">
            <i class="bi bi-calculator"></i><span>Calculate Risk Zones</span>
          </a>
        </li>
      </ul>
    </li><!-- End Risk Zones Nav -->

    <!-- <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'environmental-data.php' ? '' : 'collapsed'; ?>" href="environmental-data.php">
        <i class="bi bi-thermometer-half"></i>
        <span>Environmental Data</span>
      </a>
    </li>End Environmental Data Nav -->

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'predictive-models.php' ? '' : 'collapsed'; ?>" href="predictive-models.php">
        <i class="bi bi-graph-up"></i>
        <span>Predictive Analysis</span>
      </a>
    </li><!-- End Predictive Analysis Nav -->

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'data-uploads.php' ? '' : 'collapsed'; ?>" href="data-uploads.php">
        <i class="bi bi-upload"></i>
        <span>Data Uploads</span>
      </a>
    </li><!-- End Data Uploads Nav -->

    <?php if (canManageSystemAlerts()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-alerts.php' ? '' : 'collapsed'; ?>" href="system-alerts.php">
        <i class="bi bi-bell"></i>
        <span>System Alerts</span>
      </a>
    </li><!-- End System Alerts Nav -->
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? '' : 'collapsed'; ?>" href="reports.php">
        <i class="bi bi-bar-chart"></i>
        <span>Reports</span>
      </a>
    </li><!-- End Reports Nav -->

    <?php if (canManageContent()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'content-management.php' ? '' : 'collapsed'; ?>" href="content-management.php">
        <i class="bi bi-file-earmark-text"></i>
        <span>Content Management</span>
      </a>
    </li><!-- End Content Management Nav -->
    <?php endif; ?>

    <?php if (canManageNews()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'news-articles.php' ? '' : 'collapsed'; ?>" href="news-articles.php">
        <i class="bi bi-newspaper"></i>
        <span>News & Announcements</span>
      </a>
    </li><!-- End News Articles Nav -->
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? '' : 'collapsed'; ?>" href="notifications.php">
        <i class="bi bi-bell"></i>
        <span>Notifications</span>
        <?php 
        // Get unread notification count
        if (isset($_SESSION['user_id'])) {
            try {
                if (!isset($pdo)) {
                    require_once __DIR__ . '/../../config/database.php';
                    $database = new Database();
                    $pdo = $database->getConnection();
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch();
                $unreadCount = $result['count'] ?? 0;
                
                if ($unreadCount > 0) {
                    echo '<span class="badge bg-danger badge-number">' . ($unreadCount > 99 ? '99+' : $unreadCount) . '</span>';
                }
            } catch (Exception $e) {
                // Silent fail if notifications table doesn't exist yet
            }
        }
        ?>
      </a>
    </li><!-- End Notifications Nav -->

    <li class="nav-heading">Account</li>

    <?php if (canAccessAdminProfile()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin-profile.php' ? '' : 'collapsed'; ?>" href="admin-profile.php">
        <i class="bi bi-person"></i>
        <span>Admin Profile</span>
      </a>
    </li><!-- End Profile Page Nav -->
    <?php endif; ?>

    <?php if (canManageSystemSettings()): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-settings.php' ? '' : 'collapsed'; ?>" href="system-settings.php">
        <i class="bi bi-gear"></i>
        <span>System Settings</span>
      </a>
    </li><!-- End System Settings Nav -->
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link collapsed" href="../index.php">
        <i class="bi bi-house"></i>
        <span>Back to Home</span>
      </a>
    </li><!-- End Home Page Nav -->

  </ul>

</aside><!-- End Sidebar-->
