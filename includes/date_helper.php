<?php
/**
 * Date and Time Helper Functions for TaxEase
 * Provides consistent date/time formatting across the application
 * Timezone: Asia/Manila (Philippines - UTC+8)
 */

// Ensure timezone is set
date_default_timezone_set('Asia/Manila');

/**
 * Format date for display (e.g., "Oct 28, 2025")
 * 
 * @param string|int $date Date string or timestamp
 * @return string Formatted date
 */
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return 'Invalid Date';
    }
    
    return date('M d, Y', $timestamp);
}

/**
 * Format time for display (e.g., "02:30 PM")
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string Formatted time
 */
function formatTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid Time';
    }
    
    return date('h:i A', $timestamp);
}

/**
 * Format datetime for display (e.g., "Oct 28, 2025 02:30 PM")
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string Formatted datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid DateTime';
    }
    
    return date('M d, Y h:i A', $timestamp);
}

/**
 * Format datetime for display with day name (e.g., "Tuesday, Oct 28, 2025 02:30 PM")
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string Formatted datetime with day
 */
function formatDateTimeWithDay($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid DateTime';
    }
    
    return date('l, M d, Y h:i A', $timestamp);
}

/**
 * Format date for database storage (YYYY-MM-DD)
 * 
 * @param string|int $date Date string or timestamp
 * @return string|null Formatted date for database
 */
function formatDateForDB($date) {
    if (empty($date)) {
        return null;
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return null;
    }
    
    return date('Y-m-d', $timestamp);
}

/**
 * Format datetime for database storage (YYYY-MM-DD HH:MM:SS)
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string|null Formatted datetime for database
 */
function formatDateTimeForDB($datetime) {
    if (empty($datetime)) {
        return null;
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return null;
    }
    
    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Get relative time (e.g., "2 hours ago", "3 days ago")
 * 
 * @param string|int $datetime Datetime string or timestamp
 * @return string Relative time string
 */
function getRelativeTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid DateTime';
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Get current datetime in Philippines timezone
 * 
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Current datetime
 */
function getCurrentDateTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Check if date is today
 * 
 * @param string|int $date Date string or timestamp
 * @return bool True if date is today
 */
function isToday($date) {
    if (empty($date)) {
        return false;
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    return date('Y-m-d', $timestamp) === date('Y-m-d');
}

/**
 * Check if date is this week
 * 
 * @param string|int $date Date string or timestamp
 * @return bool True if date is this week
 */
function isThisWeek($date) {
    if (empty($date)) {
        return false;
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    $weekStart = strtotime('monday this week');
    $weekEnd = strtotime('sunday this week 23:59:59');
    
    return $timestamp >= $weekStart && $timestamp <= $weekEnd;
}

/**
 * Format date/time for specific contexts
 */

// For table displays (compact format)
function formatTableDate($date) {
    return formatDate($date);
}

// For table displays with time (compact format)
function formatTableDateTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid DateTime';
    }
    
    // If today, show relative time
    if (isToday($datetime)) {
        return 'Today ' . date('h:i A', $timestamp);
    }
    
    // If this week, show day name
    if (isThisWeek($datetime)) {
        return date('D h:i A', $timestamp); // "Mon 02:30 PM"
    }
    
    // Otherwise show full date and time
    return date('M d, Y h:i A', $timestamp);
}

// For notifications (relative time preferred)
function formatNotificationTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid DateTime';
    }
    
    $diff = time() - $timestamp;
    
    // Less than 24 hours - show relative time
    if ($diff < 86400) {
        return getRelativeTime($datetime);
    }
    
    // Otherwise show date and time
    return formatDateTime($datetime);
}

// For detailed views (full format)
function formatDetailedDateTime($datetime) {
    return formatDateTimeWithDay($datetime);
}

/**
 * Validate date format
 * 
 * @param string $date Date string
 * @param string $format Expected format (default: 'Y-m-d')
 * @return bool True if valid
 */
function isValidDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return false;
    }
    
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>

