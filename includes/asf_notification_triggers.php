<?php
/**
 * ASF Notification Triggers
 * Functions to trigger notifications when changes are made in admin modules
 */

require_once __DIR__ . '/notification_functions.php';

/**
 * Trigger notification for new outbreak
 */
function triggerNewOutbreakNotification($pdo, $outbreakId, $outbreakData) {
    try {
        // Get all administrators
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'outbreak',
                'New Outbreak Reported',
                "New {$outbreakData['status']} outbreak reported in {$outbreakData['city']} ({$outbreakData['location_name']}).",
                [
                    'related_id' => $outbreakId,
                    'related_type' => 'outbreak',
                    'link' => 'admin/outbreaks.php?view=' . $outbreakId,
                    'priority' => ($outbreakData['severity_level'] === 'critical' || $outbreakData['severity_level'] === 'high') ? 'urgent' : 'high'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering new outbreak notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for outbreak status change
 */
function triggerOutbreakStatusChangeNotification($pdo, $outbreakId, $oldStatus, $newStatus, $outbreakData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $statusLabels = [
            'confirmed' => 'Confirmed',
            'contained' => 'Contained',
            'resolved' => 'Resolved'
        ];
        $statusLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'outbreak',
                'Outbreak Status Changed',
                "Outbreak in {$outbreakData['city']} ({$outbreakData['location_name']}) status changed from {$oldStatus} to {$statusLabel}.",
                [
                    'related_id' => $outbreakId,
                    'related_type' => 'outbreak',
                    'link' => 'admin/outbreaks.php?view=' . $outbreakId,
                    'priority' => ($newStatus === 'confirmed') ? 'urgent' : 'normal'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering outbreak status change notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for new depopulation event
 */
function triggerNewDepopulationNotification($pdo, $depopulationId, $depopulationData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'depopulation',
                'New Depopulation Event',
                "New depopulation event recorded in {$depopulationData['city']} ({$depopulationData['location_name']}) with {$depopulationData['head_count']} heads.",
                [
                    'related_id' => $depopulationId,
                    'related_type' => 'depopulation',
                    'link' => 'admin/depopulation-events.php?view=' . $depopulationId,
                    'priority' => 'high'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering new depopulation notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for new risk zone
 */
function triggerNewRiskZoneNotification($pdo, $zoneId, $zoneData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $priority = ($zoneData['risk_level'] === 'critical' || $zoneData['risk_level'] === 'high') ? 'urgent' : 'normal';
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'risk_zone',
                'New Risk Zone Identified',
                "New {$zoneData['risk_level']} risk zone identified in {$zoneData['city']} ({$zoneData['zone_name']}). Risk score: {$zoneData['risk_score']}.",
                [
                    'related_id' => $zoneId,
                    'related_type' => 'risk_zone',
                    'link' => 'admin/risk-zones.php?view=' . $zoneId,
                    'priority' => $priority
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering new risk zone notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for risk zone status change
 */
function triggerRiskZoneStatusChangeNotification($pdo, $zoneId, $oldStatus, $newStatus, $zoneData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $statusLabel = ($newStatus === 'cleared') ? 'Cleared' : 'Activated';
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'risk_zone',
                'Risk Zone Status Changed',
                "Risk zone in {$zoneData['city']} ({$zoneData['zone_name']}) status changed to {$statusLabel}.",
                [
                    'related_id' => $zoneId,
                    'related_type' => 'risk_zone',
                    'link' => 'admin/risk-zones.php?view=' . $zoneId,
                    'priority' => ($newStatus === 'cleared') ? 'normal' : 'high'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering risk zone status change notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for data upload
 */
function triggerDataUploadNotification($pdo, $uploadId, $uploadData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $statusLabels = [
            'pending' => 'Pending Review',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed'
        ];
        $statusLabel = $statusLabels[$uploadData['status']] ?? ucfirst($uploadData['status']);
        $priority = ($uploadData['status'] === 'failed') ? 'high' : 'normal';
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'data_upload',
                'Data Upload: ' . $statusLabel,
                "{$uploadData['upload_type']} data file '{$uploadData['file_name']}' status: {$statusLabel}. Records: {$uploadData['record_count']}.",
                [
                    'related_id' => $uploadId,
                    'related_type' => 'data_upload',
                    'link' => 'admin/data-uploads.php?view=' . $uploadId,
                    'priority' => $priority
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering data upload notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for news article published
 */
function triggerNewsArticlePublishedNotification($pdo, $articleId, $articleData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'news',
                'News Article Published',
                "News article '{$articleData['title']}' has been published.",
                [
                    'related_id' => $articleId,
                    'related_type' => 'news_article',
                    'link' => 'admin/news-articles.php?view=' . $articleId,
                    'priority' => 'normal'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering news article published notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for system alert
 */
function triggerSystemAlertNotification($pdo, $alertId, $alertData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $priority = ($alertData['severity'] === 'critical' || $alertData['severity'] === 'high') ? 'urgent' : 'normal';
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'system',
                'System Alert: ' . $alertData['title'],
                $alertData['message'],
                [
                    'related_id' => $alertId,
                    'related_type' => 'system_alert',
                    'link' => 'admin/system-alerts.php?view=' . $alertId,
                    'priority' => $priority
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering system alert notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for report generated
 */
function triggerReportGeneratedNotification($pdo, $reportId, $reportData, $generatedByUserId) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1 AND id != ?");
        $stmt->execute([$generatedByUserId]);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'report',
                'Report Generated',
                "{$reportData['report_type']} report has been generated. Status: {$reportData['status']}.",
                [
                    'related_id' => $reportId,
                    'related_type' => 'report',
                    'link' => 'admin/reports.php?view=' . $reportId,
                    'priority' => 'normal'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering report generated notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for new user registration
 */
function triggerNewUserNotification($pdo, $userId, $userData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'user',
                'New User Registered',
                "New {$userData['user_role']} account created: {$userData['first_name']} {$userData['last_name']} ({$userData['email']}).",
                [
                    'related_id' => $userId,
                    'related_type' => 'user',
                    'link' => 'admin/users.php?view=' . $userId,
                    'priority' => 'normal'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering new user notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for meat movement
 */
function triggerMeatMovementNotification($pdo, $movementId, $movementData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'meat_movement',
                'New Meat Movement Recorded',
                "Meat movement from {$movementData['origin_city']} to {$movementData['destination_city']} ({$movementData['quantity_kg']} kg) recorded.",
                [
                    'related_id' => $movementId,
                    'related_type' => 'meat_movement',
                    'link' => 'admin/meat-movement.php?view=' . $movementId,
                    'priority' => 'normal'
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering meat movement notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for predictive model run
 */
function triggerPredictiveModelNotification($pdo, $modelId, $modelData) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_role = 'administrator' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $priority = ($modelData['high_risk_areas_count'] > 10) ? 'high' : 'normal';
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'administrator',
                'predictive',
                'New Predictive Model Run',
                "New predictive model '{$modelData['model_name']}' run completed. {$modelData['high_risk_areas_count']} high-risk areas identified.",
                [
                    'related_id' => $modelId,
                    'related_type' => 'predictive_model',
                    'link' => 'admin/predictive-models.php?view=' . $modelId,
                    'priority' => $priority
                ]
            );
        }
        return true;
    } catch (Exception $e) {
        error_log("Error triggering predictive model notification: " . $e->getMessage());
        return false;
    }
}
