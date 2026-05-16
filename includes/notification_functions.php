<?php
/**
 * Notification System Functions
 * Handles all notification-related operations
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/database.php';
}

/**
 * Create a new notification
 * 
 * @param int $userId User ID to receive notification
 * @param string $userRole User role (admin, employee, owner, client)
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param array $options Additional options (related_id, related_type, link, priority)
 * @return bool Success status
 */
function createNotification($userId, $userRole, $type, $title, $message, $options = []) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "INSERT INTO notifications 
                (user_id, user_role, notification_type, title, message, related_id, related_type, link, priority) 
                VALUES (:user_id, :user_role, :notification_type, :title, :message, :related_id, :related_type, :link, :priority)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':user_role' => $userRole,
            ':notification_type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':related_id' => $options['related_id'] ?? null,
            ':related_type' => $options['related_type'] ?? null,
            ':link' => $options['link'] ?? null,
            ':priority' => $options['priority'] ?? 'normal'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param int $userId User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($userId) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = :user_id 
                AND is_read = 0 
                AND is_archived = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    } catch (PDOException $e) {
        error_log("Error getting unread notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get notifications for a user
 * 
 * @param int $userId User ID
 * @param array $filters Filters (is_read, limit, offset)
 * @return array Array of notifications
 */
function getUserNotifications($userId, $filters = []) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                AND is_archived = 0";
        
        // Add read filter if specified
        if (isset($filters['is_read'])) {
            $sql .= " AND is_read = :is_read";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        // Add limit and offset
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        
        if (isset($filters['is_read'])) {
            $stmt->bindValue(':is_read', $filters['is_read'], PDO::PARAM_INT);
        }
        
        if (isset($filters['limit'])) {
            $stmt->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
        }
        
        if (isset($filters['offset'])) {
            $stmt->bindValue(':offset', $filters['offset'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * 
 * @param int|string $notificationId Notification ID (can be numeric or dynamic string)
 * @param int $userId User ID (for security)
 * @return bool Success status
 */
function markNotificationAsRead($notificationId, $userId) {
    // Check if this is a dynamic notification
    if (is_string($notificationId) && strpos($notificationId, 'dynamic_') === 0) {
        // Store dynamic notification read status in session
        if (!isset($_SESSION['read_dynamic_notifications'])) {
            $_SESSION['read_dynamic_notifications'] = [];
        }
        if (!in_array($notificationId, $_SESSION['read_dynamic_notifications'])) {
            $_SESSION['read_dynamic_notifications'][] = $notificationId;
        }
        return true;
    }
    
    // Handle regular database notifications
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = :id 
                AND user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $userId User ID
 * @param string $userRole User role (optional, will detect if not provided)
 * @return bool Success status
 */
function markAllNotificationsAsRead($userId, $userRole = null) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        // Mark all database notifications as read
        $sql = "UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = :user_id 
                AND is_read = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        // Mark all dynamic notifications as read in session
        if (!isset($_SESSION['read_dynamic_notifications'])) {
            $_SESSION['read_dynamic_notifications'] = [];
        }
        
        // Determine user role if not provided
        if ($userRole === null && isset($_SESSION['role'])) {
            $userRole = strtolower($_SESSION['role']);
        }
        
        // Get all dynamic notifications based on role and mark them as read
        $allDynamicNotifications = [];
        
        try {
            if ($userRole === 'owner') {
                require_once __DIR__ . '/owner_notification_generator.php';
                $allDynamicNotifications = generateOwnerNotifications($userId);
            } elseif (in_array($userRole, ['administrator', 'administrative staff'])) {
                require_once __DIR__ . '/admin_notification_generator.php';
                $allDynamicNotifications = generateAdminNotifications($userId);
            } elseif ($userRole === 'client') {
                require_once __DIR__ . '/client_notification_generator.php';
                $allDynamicNotifications = generateClientNotifications($userId);
            } elseif (in_array($userRole, ['senior account executive', 'junior account executive', 'account supervisor'])) {
                require_once __DIR__ . '/employee_notification_generator.php';
                $allDynamicNotifications = generateEmployeeNotifications($userId, $userRole);
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
        } catch (Exception $e) {
            // Log error but continue - at least database notifications were marked
            error_log("Error marking dynamic notifications as read: " . $e->getMessage());
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Archive a notification
 * 
 * @param int|string $notificationId Notification ID (can be numeric or dynamic string)
 * @param int $userId User ID (for security)
 * @return bool Success status
 */
function archiveNotification($notificationId, $userId) {
    // Check if this is a dynamic notification
    if (is_string($notificationId) && strpos($notificationId, 'dynamic_') === 0) {
        // Store dynamic notification archived status in session
        if (!isset($_SESSION['archived_dynamic_notifications'])) {
            $_SESSION['archived_dynamic_notifications'] = [];
        }
        if (!in_array($notificationId, $_SESSION['archived_dynamic_notifications'])) {
            $_SESSION['archived_dynamic_notifications'][] = $notificationId;
        }
        return true;
    }
    
    // Handle regular database notifications
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "UPDATE notifications 
                SET is_archived = 1 
                WHERE id = :id 
                AND user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error archiving notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a notification
 * 
 * @param int|string $notificationId Notification ID (can be numeric or dynamic string)
 * @param int $userId User ID (for security)
 * @return bool Success status
 */
function deleteNotification($notificationId, $userId) {
    // Check if this is a dynamic notification (treat delete same as archive)
    if (is_string($notificationId) && strpos($notificationId, 'dynamic_') === 0) {
        // Store dynamic notification archived status in session
        if (!isset($_SESSION['archived_dynamic_notifications'])) {
            $_SESSION['archived_dynamic_notifications'] = [];
        }
        if (!in_array($notificationId, $_SESSION['archived_dynamic_notifications'])) {
            $_SESSION['archived_dynamic_notifications'][] = $notificationId;
        }
        return true;
    }
    
    // Handle regular database notifications
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM notifications 
                WHERE id = :id 
                AND user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification by ID
 * 
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security)
 * @return array|null Notification data
 */
function getNotificationById($notificationId, $userId) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "SELECT * FROM notifications 
                WHERE id = :id 
                AND user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting notification by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get recent notifications (for dropdown display)
 * 
 * @param int $userId User ID
 * @param int $limit Number of notifications to retrieve
 * @return array Array of recent notifications
 */
function getRecentNotifications($userId, $limit = 5) {
    return getUserNotifications($userId, ['limit' => $limit]);
}

/**
 * Create notification for multiple users
 * 
 * @param array $userIds Array of user IDs
 * @param string $userRole User role
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param array $options Additional options
 * @return bool Success status
 */
function createBulkNotification($userIds, $userRole, $type, $title, $message, $options = []) {
    $success = true;
    
    foreach ($userIds as $userId) {
        if (!createNotification($userId, $userRole, $type, $title, $message, $options)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Get notification statistics for a user
 * 
 * @param int $userId User ID
 * @return array Statistics array
 */
function getNotificationStats($userId) {
    global $database;
    
    try {
        $conn = $database->getConnection();
        
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
                SUM(CASE WHEN priority = 'urgent' AND is_read = 0 THEN 1 ELSE 0 END) as urgent_unread
                FROM notifications 
                WHERE user_id = :user_id 
                AND is_archived = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'read' => 0,
            'urgent_unread' => 0
        ];
    }
}

/**
 * Format time ago (helper function)
 * 
 * @param string $datetime Datetime string
 * @return string Formatted time ago
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        if ($difference < 60) {
            return 'Just now';
        } elseif ($difference < 3600) {
            $minutes = floor($difference / 60);
            return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}

/**
 * Get notification icon class based on type
 * 
 * @param string $type Notification type
 * @return string Bootstrap icon class
 */
if (!function_exists('getNotificationIcon')) {
    function getNotificationIcon($type) {
        $icons = [
            'document' => 'bi-file-earmark',
            'service' => 'bi-briefcase',
            'payment' => 'bi-credit-card',
            'message' => 'bi-chat-dots',
            'task' => 'bi-list-check',
            'system' => 'bi-gear',
            'user' => 'bi-person',
            'alert' => 'bi-exclamation-circle',
            // ASF-specific types
            'outbreak' => 'bi-exclamation-triangle-fill',
            'depopulation' => 'bi-piggy-bank',
            'risk_zone' => 'bi-map',
            'data_upload' => 'bi-cloud-upload',
            'news' => 'bi-newspaper',
            'report' => 'bi-file-earmark-text',
            'environmental' => 'bi-thermometer-half',
            'meat_movement' => 'bi-truck',
            'predictive' => 'bi-graph-up'
        ];
        
        return $icons[$type] ?? 'bi-bell';
    }
}

/**
 * Get notification color class based on priority
 * 
 * @param string $priority Notification priority
 * @return string Color class
 */
if (!function_exists('getNotificationPriorityClass')) {
    function getNotificationPriorityClass($priority) {
        $classes = [
            'low' => 'text-secondary',
            'normal' => 'text-primary',
            'high' => 'text-warning',
            'urgent' => 'text-danger'
        ];
        
        return $classes[$priority] ?? 'text-primary';
    }
}
?>

