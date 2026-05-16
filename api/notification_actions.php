<?php
/**
 * API Endpoint: Notification Actions
 * Handles AJAX requests for notification operations
 * Actions: mark_read, mark_all_read, archive, delete
 */

// Start session
session_start();

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }
            
            $success = markNotificationAsRead($notificationId, $userId);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read',
                'new_count' => getUnreadNotificationCount($userId)
            ]);
            break;
            
        case 'mark_all_read':
            $success = markAllNotificationsAsRead($userId);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'All notifications marked as read' : 'Failed to mark all notifications as read',
                'new_count' => 0
            ]);
            break;
            
        case 'archive':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }
            
            $success = archiveNotification($notificationId, $userId);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification archived' : 'Failed to archive notification',
                'new_count' => getUnreadNotificationCount($userId)
            ]);
            break;
            
        case 'delete':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }
            
            $success = deleteNotification($notificationId, $userId);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification deleted' : 'Failed to delete notification',
                'new_count' => getUnreadNotificationCount($userId)
            ]);
            break;
            
        case 'get_notification':
            $notificationId = $_POST['notification_id'] ?? 0;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }
            
            $notification = getNotificationById($notificationId, $userId);
            if ($notification) {
                echo json_encode([
                    'success' => true,
                    'notification' => $notification
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Notification not found'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Notification action API error: ' . $e->getMessage());
}
?>

