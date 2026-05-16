<?php
/**
 * API Endpoint: Get Notification Count
 * Returns the unread notification count for the current user
 * Can be used for real-time updates via AJAX
 */

// Start session
session_start();

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated',
        'count' => 0
    ]);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get unread notification count
    $unreadCount = getUnreadNotificationCount($userId);
    
    // Get recent notifications (optional)
    $includeRecent = isset($_GET['include_recent']) && $_GET['include_recent'] === 'true';
    
    $response = [
        'success' => true,
        'count' => $unreadCount,
        'formatted_count' => $unreadCount > 99 ? '99+' : $unreadCount,
        'has_notifications' => $unreadCount > 0
    ];
    
    // Include recent notifications if requested
    if ($includeRecent) {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $recentNotifications = getRecentNotifications($userId, $limit);
        
        // Format notifications for display
        $formatted = array_map(function($notification) {
            return [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => substr($notification['message'], 0, 100) . (strlen($notification['message']) > 100 ? '...' : ''),
                'type' => $notification['notification_type'],
                'priority' => $notification['priority'],
                'is_read' => $notification['is_read'],
                'time_ago' => timeAgo($notification['created_at']),
                'link' => $notification['link'],
                'icon' => getNotificationIcon($notification['notification_type'])
            ];
        }, $recentNotifications);
        
        $response['recent_notifications'] = $formatted;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'count' => 0
    ]);
    error_log('Notification count API error: ' . $e->getMessage());
}
?>

