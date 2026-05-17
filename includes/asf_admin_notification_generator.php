<?php
/**
 * ASF Admin Notification Generator
 * Automatically generates notifications for administrators from ASF system activities
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_functions.php';

/**
 * Apply read/archived status from session to dynamic notifications
 * 
 * @param array $notifications Array of dynamic notifications
 * @return array Updated notifications with read/archived status
 */
function applyDynamicNotificationStatus($notifications) {
    $readIds = $_SESSION['read_dynamic_notifications'] ?? [];
    $archivedIds = $_SESSION['archived_dynamic_notifications'] ?? [];
    
    $filteredNotifications = [];
    
    foreach ($notifications as $notification) {
        $notificationId = $notification['id'];
        
        // Skip archived notifications
        if (in_array($notificationId, $archivedIds)) {
            continue;
        }
        
        // Mark as read if in session
        if (in_array($notificationId, $readIds)) {
            $notification['is_read'] = 1;
            $notification['read_at'] = date('Y-m-d H:i:s');
        }
        
        $filteredNotifications[] = $notification;
    }
    
    return $filteredNotifications;
}

/**
 * Generate all dynamic notifications for an ASF admin
 * 
 * @param int $userId Admin user ID
 * @return array Combined array of static and dynamic notifications
 */
function generateASFAdminNotifications($userId) {
    global $database, $pdo;
    
    if (!isset($pdo)) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
        } catch (Exception $e) {
            return [];
        }
    }
    
    $dynamicNotifications = [];
    
    // Get new outbreak notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getNewOutbreakNotifications($pdo));
    
    // Get outbreak status change notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getOutbreakStatusChangeNotifications($pdo));
    
    // Get new depopulation event notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getNewDepopulationNotifications($pdo));
    
    // Get new risk zone notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getNewRiskZoneNotifications($pdo));
    
    // Get risk zone status change notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getRiskZoneStatusChangeNotifications($pdo));
    
    // Get critical risk zone notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getCriticalRiskZoneNotifications($pdo));
    
    // Get data upload notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getDataUploadNotifications($pdo));
    
    // Get news article notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getNewsArticleNotifications($pdo));
    
    // Get system alert notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getSystemAlertNotifications($pdo));
    
    // Get report generation notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getReportGenerationNotifications($pdo));
    
    // Get environmental data notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getEnvironmentalDataNotifications($pdo));
    
    // Get meat movement notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getMeatMovementNotifications($pdo));
    
    // Get predictive model notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getPredictiveModelNotifications($pdo));
    
    // Get user management notifications
    $dynamicNotifications = array_merge($dynamicNotifications, getUserManagementNotifications($pdo));
    
    // Apply read/archived status from session to dynamic notifications
    $dynamicNotifications = applyDynamicNotificationStatus($dynamicNotifications);
    
    // Get static notifications from database
    $staticNotifications = getUserNotifications($userId);
    
    // Merge and sort by date
    $allNotifications = array_merge($staticNotifications, $dynamicNotifications);
    usort($allNotifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $allNotifications;
}

/**
 * Get new outbreak notifications
 */
function getNewOutbreakNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT o.id, o.outbreak_code, o.location_name, o.city, o.status, 
                       o.severity_level, o.outbreak_date, o.reported_date,
                       CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
                FROM asf_outbreaks o
                LEFT JOIN user_accounts u ON o.reported_by = u.id
                WHERE o.reported_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY o.reported_date DESC
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($outbreaks as $outbreak) {
            $severity = $outbreak['severity_level'] ?? 'medium';
            $priority = ($severity === 'critical' || $severity === 'high') ? 'urgent' : 'high';
            
            $notifications[] = [
                'id' => 'dynamic_new_outbreak_' . $outbreak['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'outbreak',
                'title' => "🚨 New Outbreak Reported",
                'message' => "New {$outbreak['status']} outbreak reported in {$outbreak['city']} ({$outbreak['location_name']}). Reported by {$outbreak['reported_by_name']}.",
                'related_id' => $outbreak['id'],
                'related_type' => 'outbreak',
                'link' => 'outbreaks.php?view=' . $outbreak['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => $priority,
                'created_at' => $outbreak['reported_date'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting new outbreak notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get outbreak status change notifications
 */
function getOutbreakStatusChangeNotifications($pdo) {
    $notifications = [];
    
    try {
        // Get outbreaks that changed status in the last 7 days
        $sql = "SELECT o.id, o.outbreak_code, o.location_name, o.city, o.status,
                       o.updated_at, o.confirmed_date,
                       CONCAT(u.first_name, ' ', u.last_name) as confirmed_by_name
                FROM asf_outbreaks o
                LEFT JOIN user_accounts u ON o.confirmed_by = u.id
                WHERE o.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND o.status IN ('confirmed', 'contained', 'resolved')
                ORDER BY o.updated_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $outbreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($outbreaks as $outbreak) {
            $statusLabels = [
                'confirmed' => 'Confirmed',
                'contained' => 'Contained',
                'resolved' => 'Resolved'
            ];
            $statusLabel = $statusLabels[$outbreak['status']] ?? ucfirst($outbreak['status']);
            
            $notifications[] = [
                'id' => 'dynamic_outbreak_status_' . $outbreak['id'] . '_' . $outbreak['status'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'outbreak',
                'title' => "📋 Outbreak Status Changed",
                'message' => "Outbreak in {$outbreak['city']} ({$outbreak['location_name']}) status changed to {$statusLabel}.",
                'related_id' => $outbreak['id'],
                'related_type' => 'outbreak',
                'link' => 'outbreaks.php?view=' . $outbreak['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => ($outbreak['status'] === 'confirmed') ? 'urgent' : 'normal',
                'created_at' => $outbreak['updated_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting outbreak status change notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get new depopulation event notifications
 */
function getNewDepopulationNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT d.id, d.event_code, d.location_name, d.city, d.head_count,
                       d.event_date, d.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM depopulation_events d
                LEFT JOIN user_accounts u ON d.created_by = u.id
                WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY d.created_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($events as $event) {
            $notifications[] = [
                'id' => 'dynamic_new_depopulation_' . $event['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'depopulation',
                'title' => "🐷 New Depopulation Event",
                'message' => "New depopulation event recorded in {$event['city']} ({$event['location_name']}) with {$event['head_count']} heads. Created by {$event['created_by_name']}.",
                'related_id' => $event['id'],
                'related_type' => 'depopulation',
                'link' => 'depopulation-events.php?view=' . $event['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'high',
                'created_at' => $event['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting new depopulation notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get new risk zone notifications
 */
function getNewRiskZoneNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT r.id, r.zone_code, r.zone_name, r.city, r.risk_level,
                       r.risk_score, r.identified_date, r.created_at
                FROM risk_zones r
                WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY r.created_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($zones as $zone) {
            $priority = ($zone['risk_level'] === 'critical' || $zone['risk_level'] === 'high') ? 'urgent' : 'normal';
            
            $notifications[] = [
                'id' => 'dynamic_new_risk_zone_' . $zone['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'risk_zone',
                'title' => "⚠️ New Risk Zone Identified",
                'message' => "New {$zone['risk_level']} risk zone identified in {$zone['city']} ({$zone['zone_name']}). Risk score: {$zone['risk_score']}.",
                'related_id' => $zone['id'],
                'related_type' => 'risk_zone',
                'link' => 'risk-zones.php?view=' . $zone['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => $priority,
                'created_at' => $zone['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting new risk zone notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get risk zone status change notifications
 */
function getRiskZoneStatusChangeNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT r.id, r.zone_code, r.zone_name, r.city, r.status,
                       r.risk_level, r.updated_at
                FROM risk_zones r
                WHERE r.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND r.status IN ('cleared', 'active')
                ORDER BY r.updated_at DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($zones as $zone) {
            $statusLabel = ($zone['status'] === 'cleared') ? 'Cleared' : 'Activated';
            
            $notifications[] = [
                'id' => 'dynamic_risk_zone_status_' . $zone['id'] . '_' . $zone['status'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'risk_zone',
                'title' => "📊 Risk Zone Status Changed",
                'message' => "Risk zone in {$zone['city']} ({$zone['zone_name']}) status changed to {$statusLabel}.",
                'related_id' => $zone['id'],
                'related_type' => 'risk_zone',
                'link' => 'risk-zones.php?view=' . $zone['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => ($zone['status'] === 'cleared') ? 'normal' : 'high',
                'created_at' => $zone['updated_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting risk zone status change notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get critical risk zone notifications
 */
function getCriticalRiskZoneNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT r.id, r.zone_code, r.zone_name, r.city, r.risk_level,
                       r.risk_score, r.updated_at
                FROM risk_zones r
                WHERE r.risk_level IN ('critical', 'high')
                AND r.status = 'active'
                AND r.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY r.risk_score DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($zones as $zone) {
            $notifications[] = [
                'id' => 'dynamic_critical_risk_zone_' . $zone['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'risk_zone',
                'title' => "🚨 Critical Risk Zone Active",
                'message' => "{$zone['risk_level']} risk zone in {$zone['city']} ({$zone['zone_name']}) requires immediate attention. Risk score: {$zone['risk_score']}.",
                'related_id' => $zone['id'],
                'related_type' => 'risk_zone',
                'link' => 'risk-zones.php?view=' . $zone['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'urgent',
                'created_at' => $zone['updated_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting critical risk zone notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get data upload notifications
 */
function getDataUploadNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT du.id, du.file_name, du.upload_type, du.status,
                       du.uploaded_at, du.record_count,
                       CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM data_uploads du
                LEFT JOIN user_accounts u ON du.uploaded_by = u.id
                WHERE du.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY du.uploaded_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($uploads as $upload) {
            $statusLabels = [
                'pending' => 'Pending Review',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed'
            ];
            $statusLabel = $statusLabels[$upload['status']] ?? ucfirst($upload['status']);
            
            $priority = ($upload['status'] === 'failed') ? 'high' : 'normal';
            
            $notifications[] = [
                'id' => 'dynamic_data_upload_' . $upload['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'data_upload',
                'title' => "📤 Data Upload: {$statusLabel}",
                'message' => "{$upload['upload_type']} data file '{$upload['file_name']}' uploaded by {$upload['uploaded_by_name']}. Status: {$statusLabel}. Records: {$upload['record_count']}.",
                'related_id' => $upload['id'],
                'related_type' => 'data_upload',
                'link' => 'data-uploads.php?view=' . $upload['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => $priority,
                'created_at' => $upload['uploaded_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting data upload notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get news article notifications
 */
function getNewsArticleNotifications($pdo) {
    $notifications = [];
    
    try {
        // Get newly published articles
        $sql = "SELECT n.id, n.title, n.status, n.published_at, n.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM news_articles n
                LEFT JOIN user_accounts u ON n.author_id = u.id
                WHERE (n.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       OR n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                AND n.status = 'published'
                ORDER BY COALESCE(n.published_at, n.created_at) DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($articles as $article) {
            $notifications[] = [
                'id' => 'dynamic_news_published_' . $article['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'news',
                'title' => "📰 News Article Published",
                'message' => "News article '{$article['title']}' has been published by {$article['author_name']}.",
                'related_id' => $article['id'],
                'related_type' => 'news_article',
                'link' => 'news-articles.php?view=' . $article['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'normal',
                'created_at' => $article['published_at'] ?? $article['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting news article notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get system alert notifications
 */
function getSystemAlertNotifications($pdo) {
    return [];
}

/**
 * Get report generation notifications
 */
function getReportGenerationNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT gr.id, gr.report_type, gr.status, gr.generated_at,
                       CONCAT(u.first_name, ' ', u.last_name) as generated_by_name
                FROM generated_reports gr
                LEFT JOIN user_accounts u ON gr.generated_by = u.id
                WHERE gr.generated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY gr.generated_at DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reports as $report) {
            $notifications[] = [
                'id' => 'dynamic_report_generated_' . $report['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'report',
                'title' => "📊 Report Generated",
                'message' => "{$report['report_type']} report has been generated by {$report['generated_by_name']}. Status: {$report['status']}.",
                'related_id' => $report['id'],
                'related_type' => 'report',
                'link' => 'reports.php?view=' . $report['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'normal',
                'created_at' => $report['generated_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting report generation notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get environmental data notifications (significant changes)
 */
function getEnvironmentalDataNotifications($pdo) {
    $notifications = [];
    
    try {
        // Get recent environmental data entries
        $sql = "SELECT ed.id, ed.location_name, ed.city, ed.recorded_date,
                       ed.temperature, ed.humidity, ed.recorded_at
                FROM environmental_data ed
                WHERE ed.recorded_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                ORDER BY ed.recorded_at DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $envData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Only create notifications for significant changes (example: very high temperature)
        foreach ($envData as $data) {
            if ($data['temperature'] && $data['temperature'] > 35) {
                $notifications[] = [
                    'id' => 'dynamic_env_data_' . $data['id'],
                    'user_id' => null,
                    'user_role' => 'administrator',
                    'notification_type' => 'environmental',
                    'title' => "🌡️ High Temperature Alert",
                    'message' => "High temperature ({$data['temperature']}°C) recorded in {$data['city']} ({$data['location_name']}).",
                    'related_id' => $data['id'],
                    'related_type' => 'environmental_data',
                    'link' => 'environmental-data.php?view=' . $data['id'],
                    'is_read' => 0,
                    'is_archived' => 0,
                    'priority' => 'normal',
                    'created_at' => $data['recorded_at'],
                    'read_at' => null
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Error getting environmental data notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get meat movement notifications
 */
function getMeatMovementNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT mm.id, mm.movement_code, mm.origin_city, mm.destination_city,
                       mm.quantity_kg, mm.movement_date, mm.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM meat_movement mm
                LEFT JOIN user_accounts u ON mm.created_by = u.id
                WHERE mm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY mm.created_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($movements as $movement) {
            $notifications[] = [
                'id' => 'dynamic_meat_movement_' . $movement['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'meat_movement',
                'title' => "🚚 New Meat Movement Recorded",
                'message' => "Meat movement from {$movement['origin_city']} to {$movement['destination_city']} ({$movement['quantity_kg']} kg) recorded by {$movement['created_by_name']}.",
                'related_id' => $movement['id'],
                'related_type' => 'meat_movement',
                'link' => 'meat-movement.php?view=' . $movement['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'normal',
                'created_at' => $movement['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting meat movement notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get predictive model notifications
 */
function getPredictiveModelNotifications($pdo) {
    $notifications = [];
    
    try {
        $sql = "SELECT pm.id, pm.model_name, pm.prediction_date, pm.created_at,
                       pm.high_risk_areas_count,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM predictive_models pm
                LEFT JOIN user_accounts u ON pm.created_by = u.id
                WHERE pm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY pm.created_at DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($models as $model) {
            $notifications[] = [
                'id' => 'dynamic_predictive_model_' . $model['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'predictive',
                'title' => "🔮 New Predictive Model Run",
                'message' => "New predictive model '{$model['model_name']}' run completed. {$model['high_risk_areas_count']} high-risk areas identified.",
                'related_id' => $model['id'],
                'related_type' => 'predictive_model',
                'link' => 'predictive-models.php?view=' . $model['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => ($model['high_risk_areas_count'] > 10) ? 'high' : 'normal',
                'created_at' => $model['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting predictive model notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Get user management notifications
 */
function getUserManagementNotifications($pdo) {
    $notifications = [];
    
    try {
        // Get new user registrations
        $sql = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       u.email, u.user_role, u.created_at
                FROM user_accounts u
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY u.created_at DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $notifications[] = [
                'id' => 'dynamic_new_user_' . $user['id'],
                'user_id' => null,
                'user_role' => 'administrator',
                'notification_type' => 'user',
                'title' => "👤 New User Registered",
                'message' => "New {$user['user_role']} account created: {$user['full_name']} ({$user['email']}).",
                'related_id' => $user['id'],
                'related_type' => 'user',
                'link' => 'users.php?view=' . $user['id'],
                'is_read' => 0,
                'is_archived' => 0,
                'priority' => 'normal',
                'created_at' => $user['created_at'],
                'read_at' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting user management notifications: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Calculate dynamic statistics for ASF admin
 */
function getASFAdminDynamicNotificationStats($userId) {
    $notifications = generateASFAdminNotifications($userId);
    
    $stats = [
        'total' => count($notifications),
        'unread' => 0,
        'read' => 0,
        'urgent_unread' => 0
    ];
    
    foreach ($notifications as $notification) {
        if ($notification['is_read'] == 0 || (isset($notification['is_read']) && $notification['is_read'] == 0)) {
            $stats['unread']++;
            if (isset($notification['priority']) && ($notification['priority'] === 'urgent' || $notification['priority'] === 'high')) {
                $stats['urgent_unread']++;
            }
        } else {
            $stats['read']++;
        }
    }
    
    return $stats;
}
