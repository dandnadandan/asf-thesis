<?php
/**
 * Logout Page for TaxEase
 * Handles user logout and session cleanup
 */

require_once 'includes/session_manager.php';

// Logout user and redirect to login page
logout('login.php?logout=1');
?>
