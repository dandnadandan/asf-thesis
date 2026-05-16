<?php
/**
 * Admin Notifications Page - ASF Surveillance System
 * Displays all notifications for administrators
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';
require_once '../includes/notification_functions.php';
require_once '../includes/asf_admin_notification_generator.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role
if (!isASFAdministrator()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Notifications';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($_GET['action']) {
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (markNotificationAsRead($notificationId, $currentUser['id'])) {
                $response = ['success' => true, 'message' => 'Notification marked as read'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to mark notification as read'];
            }
            break;
            
        case 'mark_all_read':
            try {
                // Mark all database notifications as read
                $sql = "UPDATE notifications 
                        SET is_read = 1, read_at = NOW() 
                        WHERE user_id = :user_id 
                        AND is_read = 0";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':user_id' => $currentUser['id']]);
                
                // Mark all dynamic notifications as read
                $allDynamicNotifications = generateASFAdminNotifications($currentUser['id']);
                
                // Initialize session array if not exists
                if (!isset($_SESSION['read_dynamic_notifications'])) {
                    $_SESSION['read_dynamic_notifications'] = [];
                }
                
                // Mark all dynamic notifications as read
                foreach ($allDynamicNotifications as $notification) {
                    $notificationId = $notification['id'];
                    if (is_string($notificationId) && strpos($notificationId, 'dynamic_') === 0) {
                        if (!in_array($notificationId, $_SESSION['read_dynamic_notifications'])) {
                            $_SESSION['read_dynamic_notifications'][] = $notificationId;
                        }
                    }
                }
                
                $response = ['success' => true, 'message' => 'All notifications marked as read'];
            } catch (Exception $e) {
                error_log("Error marking all notifications as read: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Failed to mark all notifications as read'];
            }
            break;
            
        case 'delete':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (deleteNotification($notificationId, $currentUser['id'])) {
                $response = ['success' => true, 'message' => 'Notification deleted'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete notification'];
            }
            break;
            
        case 'archive':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (archiveNotification($notificationId, $currentUser['id'])) {
                $response = ['success' => true, 'message' => 'Notification archived'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to archive notification'];
            }
            break;
    }
    
    echo json_encode($response);
    exit();
}

// Get filter from request
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all'; // Filter by notification type

// Generate dynamic notifications from system activities
$allNotifications = generateASFAdminNotifications($currentUser['id']);

// Apply filters
$notifications = [];
foreach ($allNotifications as $notification) {
    // Filter by read status
    if ($filter === 'unread' && ($notification['is_read'] != 0 && $notification['is_read'] !== '0')) continue;
    if ($filter === 'read' && ($notification['is_read'] == 0 || $notification['is_read'] === '0')) continue;
    
    // Filter by type
    if ($type !== 'all' && $notification['notification_type'] !== $type) continue;
    
    $notifications[] = $notification;
}

// Get dynamic notification statistics
$stats = getASFAdminDynamicNotificationStats($currentUser['id']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  
  <style>
    .notification-card {
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
    }
    
    .notification-card:hover {
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      transform: translateX(5px);
    }
    
    .notification-card.unread {
      background-color: #f8f9ff;
      border-left-color: #4154f1;
    }
    
    .notification-card.priority-urgent {
      border-left-color: #dc3545;
    }
    
    .notification-card.priority-high {
      border-left-color: #ffc107;
    }
    
    .notification-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }
    
    .notification-time {
      font-size: 0.875rem;
      color: #6c757d;
    }
    
    .stats-card {
      border-left: 4px solid;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Notifications</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Notifications</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      
      <!-- Notification Statistics -->
      <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="card stats-card" style="border-left-color: #4154f1;">
            <div class="card-body">
              <h5 class="card-title">Total Notifications</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #f6f9ff; color: #4154f1;">
                  <i class="bi bi-bell"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo $stats['total']; ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="card stats-card" style="border-left-color: #ffc107;">
            <div class="card-body">
              <h5 class="card-title">Unread</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #fff4e5; color: #ffc107;">
                  <i class="bi bi-bell-fill"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo $stats['unread']; ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="card stats-card" style="border-left-color: #28a745;">
            <div class="card-body">
              <h5 class="card-title">Read</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #e8f5e9; color: #28a745;">
                  <i class="bi bi-check-circle"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo $stats['read']; ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="card stats-card" style="border-left-color: #dc3545;">
            <div class="card-body">
              <h5 class="card-title">Urgent</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #ffebee; color: #dc3545;">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="ps-3">
                  <h6><?php echo $stats['urgent_unread']; ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notifications List -->
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
                <h5 class="card-title mb-0">All Notifications</h5>
                <div class="d-flex gap-2 flex-wrap">
                  <!-- Filter by Status -->
                  <div class="btn-group" role="group">
                    <a href="?filter=all&type=<?php echo $type; ?>" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="?filter=unread&type=<?php echo $type; ?>" class="btn btn-sm <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary'; ?>">Unread</a>
                    <a href="?filter=read&type=<?php echo $type; ?>" class="btn btn-sm <?php echo $filter === 'read' ? 'btn-primary' : 'btn-outline-primary'; ?>">Read</a>
                  </div>
                  
                  <!-- Filter by Type -->
                  <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                      <?php 
                      $typeLabels = [
                        'all' => 'All Types',
                        'outbreak' => 'Outbreaks',
                        'depopulation' => 'Depopulation',
                        'risk_zone' => 'Risk Zones',
                        'data_upload' => 'Data Uploads',
                        'news' => 'News',
                        'system' => 'System Alerts',
                        'report' => 'Reports',
                        'environmental' => 'Environmental',
                        'meat_movement' => 'Meat Movement',
                        'predictive' => 'Predictive Models',
                        'user' => 'Users'
                      ];
                      echo $typeLabels[$type] ?? 'All Types';
                      ?>
                    </button>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=all">All Types</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=outbreak">Outbreaks</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=depopulation">Depopulation</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=risk_zone">Risk Zones</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=data_upload">Data Uploads</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=news">News</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=system">System Alerts</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=report">Reports</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=environmental">Environmental</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=meat_movement">Meat Movement</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=predictive">Predictive Models</a></li>
                      <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=user">Users</a></li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <?php if ($stats['unread'] > 0): ?>
              <div class="mb-3">
                <button type="button" class="btn btn-sm btn-success" id="markAllReadBtn">
                  <i class="bi bi-check-all"></i> Mark All as Read
                </button>
              </div>
              <?php endif; ?>
              
              <?php if (empty($notifications)): ?>
                <div class="alert alert-info text-center">
                  <i class="bi bi-info-circle me-2"></i>
                  <?php if ($filter === 'unread'): ?>
                    You have no unread notifications. All caught up!
                  <?php elseif ($type !== 'all'): ?>
                    No <?php echo ucfirst(str_replace('_', ' ', $type)); ?> notifications found.
                  <?php else: ?>
                    No notifications found. System is running smoothly!
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="notifications-list">
                  <?php foreach ($notifications as $notification): ?>
                    <?php 
                    $notificationId = $notification['id'];
                    $isDynamic = is_string($notificationId) && strpos($notificationId, 'dynamic_') === 0;
                    $isRead = ($notification['is_read'] == 0 || $notification['is_read'] === '0') ? false : true;
                    ?>
                    <div class="notification-card card mb-3 <?php echo !$isRead ? 'unread' : ''; ?> priority-<?php echo $notification['priority'] ?? 'normal'; ?>" data-notification-id="<?php echo htmlspecialchars($notificationId); ?>">
                      <div class="card-body">
                        <div class="d-flex">
                          <div class="notification-icon me-3 <?php echo getNotificationPriorityClass($notification['priority'] ?? 'normal'); ?>" style="background-color: <?php echo !$isRead ? '#f6f9ff' : '#f8f9fa'; ?>;">
                            <i class="<?php echo getNotificationIcon($notification['notification_type'] ?? 'system'); ?>"></i>
                          </div>
                          <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                              <h6 class="mb-0">
                                <?php echo htmlspecialchars($notification['title']); ?>
                                <?php if (!$isRead): ?>
                                  <span class="badge bg-primary">New</span>
                                <?php endif; ?>
                                <?php if (isset($notification['priority']) && $notification['priority'] === 'urgent'): ?>
                                  <span class="badge bg-danger">Urgent</span>
                                <?php elseif (isset($notification['priority']) && $notification['priority'] === 'high'): ?>
                                  <span class="badge bg-warning text-dark">High</span>
                                <?php endif; ?>
                                <?php if ($isDynamic): ?>
                                  <span class="badge bg-info">Live</span>
                                <?php endif; ?>
                              </h6>
                              <?php if (!$isDynamic): ?>
                              <div class="dropdown">
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                  <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                  <?php if (!$isRead): ?>
                                  <li><a class="dropdown-item mark-read-btn" href="#" data-id="<?php echo htmlspecialchars($notificationId); ?>"><i class="bi bi-check"></i> Mark as Read</a></li>
                                  <?php endif; ?>
                                  <li><a class="dropdown-item archive-btn" href="#" data-id="<?php echo htmlspecialchars($notificationId); ?>"><i class="bi bi-archive"></i> Archive</a></li>
                                  <li><hr class="dropdown-divider"></li>
                                  <li><a class="dropdown-item text-danger delete-btn" href="#" data-id="<?php echo htmlspecialchars($notificationId); ?>"><i class="bi bi-trash"></i> Delete</a></li>
                                </ul>
                              </div>
                              <?php endif; ?>
                            </div>
                            <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                              <small class="notification-time">
                                <i class="bi bi-clock"></i> <?php echo timeAgo($notification['created_at']); ?>
                              </small>
                              <?php if (!empty($notification['link'])): ?>
                              <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                 class="btn btn-sm btn-outline-primary view-details-btn" 
                                 data-notification-id="<?php echo htmlspecialchars($notificationId); ?>"
                                 data-is-read="<?php echo $isRead ? '1' : '0'; ?>"
                                 data-is-dynamic="<?php echo $isDynamic ? '1' : '0'; ?>">
                                <i class="bi bi-arrow-right"></i> View Details
                              </a>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              
            </div>
          </div>
        </div>
      </div>

    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Auto mark as read when View Details is clicked
      document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          const notificationId = this.dataset.notificationId;
          const isRead = this.dataset.isRead;
          const isDynamic = this.dataset.isDynamic;
          
          // Mark as read if it's unread (both dynamic and non-dynamic)
          if (isRead == '0') {
            e.preventDefault();
            const targetUrl = this.href;
            
            // Mark as read, then redirect
            fetch('?action=mark_read', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'notification_id=' + encodeURIComponent(notificationId)
            })
            .then(response => response.json())
            .then(data => {
              // Redirect regardless of success/failure
              window.location.href = targetUrl;
            })
            .catch(() => {
              // Redirect even if request fails
              window.location.href = targetUrl;
            });
          }
          // If already read, just follow the link normally
        });
      });
      
      // Mark as read
      document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const notificationId = this.dataset.id;
          markNotificationAsRead(notificationId);
        });
      });
      
      // Mark all as read
      const markAllBtn = document.getElementById('markAllReadBtn');
      if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
          if (confirm('Mark all notifications as read?')) {
            markAllNotificationsAsRead();
          }
        });
      }
      
      // Delete notification
      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const notificationId = this.dataset.id;
          if (confirm('Are you sure you want to delete this notification?')) {
            deleteNotification(notificationId);
          }
        });
      });
      
      // Archive notification
      document.querySelectorAll('.archive-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const notificationId = this.dataset.id;
          archiveNotification(notificationId);
        });
      });
      
      // Functions
      function markNotificationAsRead(id) {
        fetch('?action=mark_read', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'notification_id=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update sidebar notification count
            updateSidebarNotificationCount();
            location.reload();
          } else {
            alert(data.message);
          }
        });
      }
      
      function markAllNotificationsAsRead() {
        fetch('?action=mark_all_read', {
          method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update sidebar notification count
            updateSidebarNotificationCount();
            location.reload();
          } else {
            alert(data.message);
          }
        });
      }
      
      function updateSidebarNotificationCount() {
        // Update the sidebar notification badge to 0
        const notificationBadge = document.querySelector('.badge-number');
        if (notificationBadge) {
          notificationBadge.textContent = '0';
          notificationBadge.style.display = 'none';
        }
        
        // Update all notification badges
        document.querySelectorAll('.badge-number').forEach(badge => {
          badge.textContent = '0';
          badge.style.display = 'none';
        });
      }
      
      function deleteNotification(id) {
        fetch('?action=delete', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'notification_id=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert(data.message);
          }
        });
      }
      
      function archiveNotification(id) {
        fetch('?action=archive', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'notification_id=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert(data.message);
          }
        });
      }
    });
  </script>

</body>

</html>
