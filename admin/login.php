<?php
/**
 * Admin Login Redirect
 * Redirects admin login attempts to the main login page
 */

// Redirect to main login page
header("Location: ../login.php");
exit();
?>
