<?php
/**
 * Database Configuration for ASF Surveillance System
 * Database connection settings and configuration
 */

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Include date helper functions
require_once __DIR__ . '/../includes/date_helper.php';

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'asf_surveillance_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Database connection class
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $conn;
    
    // Get database connection
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Set MySQL timezone to Philippines
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch(PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
        
        return $this->conn;
    }
    
    // Close database connection
    public function closeConnection() {
        $this->conn = null;
    }
    
    // Test database connection
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $this->closeConnection();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Create database connection instance
$database = new Database();

// Test connection on include
if (!defined('DB_CONNECTION_TESTED')) {
    define('DB_CONNECTION_TESTED', true);
    
    // Only test connection in development environment
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        if (!$database->testConnection()) {
            error_log("ASF Surveillance System: Database connection test failed");
        }
    }
}
?>
