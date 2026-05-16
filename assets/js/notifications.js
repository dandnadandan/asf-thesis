/**
 * TaxEase Notification System - JavaScript
 * Handles real-time notification updates and interactions
 */

class NotificationManager {
    constructor(options = {}) {
        this.updateInterval = options.updateInterval || 30000; // 30 seconds default
        this.autoUpdate = options.autoUpdate || false;
        this.apiUrl = options.apiUrl || '../api/get_notification_count.php';
        this.badgeSelector = options.badgeSelector || '.badge-number';
        this.intervalId = null;
        
        if (this.autoUpdate) {
            this.startAutoUpdate();
        }
    }
    
    /**
     * Start automatic notification count updates
     */
    startAutoUpdate() {
        this.updateNotificationCount();
        this.intervalId = setInterval(() => {
            this.updateNotificationCount();
        }, this.updateInterval);
        
        console.log('Notification auto-update started');
    }
    
    /**
     * Stop automatic updates
     */
    stopAutoUpdate() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            console.log('Notification auto-update stopped');
        }
    }
    
    /**
     * Update notification count in badge
     */
    async updateNotificationCount() {
        try {
            const response = await fetch(this.apiUrl);
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.count, data.formatted_count);
                
                // Trigger custom event
                document.dispatchEvent(new CustomEvent('notificationCountUpdated', {
                    detail: data
                }));
            }
        } catch (error) {
            console.error('Error updating notification count:', error);
        }
    }
    
    /**
     * Update badge display
     */
    updateBadge(count, formattedCount) {
        const badges = document.querySelectorAll(this.badgeSelector);
        
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = formattedCount;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        });
    }
    
    /**
     * Get recent notifications
     */
    async getRecentNotifications(limit = 5) {
        try {
            const response = await fetch(`${this.apiUrl}?include_recent=true&limit=${limit}`);
            const data = await response.json();
            
            if (data.success) {
                return data.recent_notifications;
            }
            return [];
        } catch (error) {
            console.error('Error getting recent notifications:', error);
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);
            
            const response = await fetch('../api/notification_actions.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.new_count, data.new_count > 99 ? '99+' : data.new_count);
                
                // Trigger custom event
                document.dispatchEvent(new CustomEvent('notificationMarkedRead', {
                    detail: { notificationId, newCount: data.new_count }
                }));
            }
            
            return data;
        } catch (error) {
            console.error('Error marking notification as read:', error);
            return { success: false, message: 'An error occurred' };
        }
    }
    
    /**
     * Mark all notifications as read
     */
    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            
            const response = await fetch('../api/notification_actions.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(0, 0);
                
                // Trigger custom event
                document.dispatchEvent(new CustomEvent('allNotificationsMarkedRead', {
                    detail: data
                }));
            }
            
            return data;
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            return { success: false, message: 'An error occurred' };
        }
    }
    
    /**
     * Delete notification
     */
    async deleteNotification(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('notification_id', notificationId);
            
            const response = await fetch('../api/notification_actions.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.new_count, data.new_count > 99 ? '99+' : data.new_count);
                
                // Trigger custom event
                document.dispatchEvent(new CustomEvent('notificationDeleted', {
                    detail: { notificationId, newCount: data.new_count }
                }));
            }
            
            return data;
        } catch (error) {
            console.error('Error deleting notification:', error);
            return { success: false, message: 'An error occurred' };
        }
    }
    
    /**
     * Archive notification
     */
    async archiveNotification(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'archive');
            formData.append('notification_id', notificationId);
            
            const response = await fetch('../api/notification_actions.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.new_count, data.new_count > 99 ? '99+' : data.new_count);
                
                // Trigger custom event
                document.dispatchEvent(new CustomEvent('notificationArchived', {
                    detail: { notificationId, newCount: data.new_count }
                }));
            }
            
            return data;
        } catch (error) {
            console.error('Error archiving notification:', error);
            return { success: false, message: 'An error occurred' };
        }
    }
    
    /**
     * Show notification toast (if using toast library)
     */
    showToast(title, message, type = 'info') {
        // Implement based on your toast library
        // Example for Bootstrap Toast:
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// Initialize notification manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create global notification manager instance
    window.notificationManager = new NotificationManager({
        autoUpdate: false, // Set to true for automatic updates
        updateInterval: 30000 // Update every 30 seconds
    });
    
    // Example: Listen for notification count updates
    document.addEventListener('notificationCountUpdated', function(e) {
        console.log('Notification count updated:', e.detail.count);
    });
    
    // Example: Listen for notification marked as read
    document.addEventListener('notificationMarkedRead', function(e) {
        console.log('Notification marked as read:', e.detail.notificationId);
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}

