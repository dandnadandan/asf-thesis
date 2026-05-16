-- =======================================================
-- ASF Surveillance System Database Schema
-- CALABARZON African Swine Fever Monitoring
-- =======================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS asf_surveillance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asf_surveillance_db;

-- =======================================================
-- USER MANAGEMENT TABLES
-- =======================================================

-- User accounts table (extends existing user system)
CREATE TABLE IF NOT EXISTS user_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    user_role ENUM('administrator', 'field_staff', 'analyst', 'viewer') NOT NULL DEFAULT 'viewer',
    organization VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(50) NULL,
    province VARCHAR(50) NULL DEFAULT 'CALABARZON',
    postal_code VARCHAR(20) NULL,
    profile_image VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires_at TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_user_role (user_role),
    INDEX idx_is_active (is_active),
    INDEX idx_province (province)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions for authentication
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity logging
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ENVIRONMENTAL DATA TABLES
-- =======================================================

-- Environmental parameters (temperature, humidity, etc.)
CREATE TABLE IF NOT EXISTS environmental_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    province VARCHAR(50) NOT NULL DEFAULT 'CALABARZON',
    city VARCHAR(50) NOT NULL,
    barangay VARCHAR(100) NULL,
    temperature DECIMAL(5, 2) NULL,
    humidity DECIMAL(5, 2) NULL,
    rainfall DECIMAL(8, 2) NULL,
    wind_speed DECIMAL(5, 2) NULL,
    wind_direction VARCHAR(10) NULL,
    atmospheric_pressure DECIMAL(7, 2) NULL,
    other_parameters JSON NULL,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recorded_by) REFERENCES user_accounts(id),
    INDEX idx_location (latitude, longitude),
    INDEX idx_province (province),
    INDEX idx_city (city),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ASF OUTBREAK RECORDS
-- =======================================================

-- ASF outbreak events
CREATE TABLE IF NOT EXISTS asf_outbreaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outbreak_code VARCHAR(50) UNIQUE NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    province VARCHAR(50) NOT NULL DEFAULT 'CALABARZON',
    city VARCHAR(50) NOT NULL,
    barangay VARCHAR(100) NULL,
    farm_name VARCHAR(100) NULL,
    farm_type ENUM('commercial', 'backyard', 'semi-commercial', 'wild_boar') NULL,
    outbreak_date DATE NOT NULL,
    reported_date TIMESTAMP NOT NULL,
    confirmed_date TIMESTAMP NULL,
    status ENUM('suspected', 'confirmed', 'contained', 'resolved', 'false_alarm') NOT NULL DEFAULT 'suspected',
    total_pigs_affected INT DEFAULT 0,
    total_pigs_mortality INT DEFAULT 0,
    total_pigs_depopulated INT DEFAULT 0,
    severity_level ENUM('low', 'medium', 'high', 'critical') NULL,
    source_of_infection TEXT NULL,
    clinical_signs TEXT NULL,
    laboratory_confirmed BOOLEAN DEFAULT FALSE,
    lab_confirmation_date TIMESTAMP NULL,
    containment_measures TEXT NULL,
    notes TEXT NULL,
    reported_by INT NOT NULL,
    confirmed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reported_by) REFERENCES user_accounts(id),
    FOREIGN KEY (confirmed_by) REFERENCES user_accounts(id),
    INDEX idx_outbreak_code (outbreak_code),
    INDEX idx_location (latitude, longitude),
    INDEX idx_province (province),
    INDEX idx_status (status),
    INDEX idx_outbreak_date (outbreak_date),
    INDEX idx_reported_date (reported_date),
    INDEX idx_severity (severity_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbreak images and documents
CREATE TABLE IF NOT EXISTS outbreak_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outbreak_id INT NOT NULL,
    document_type ENUM('image', 'report', 'lab_result', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL,
    mime_type VARCHAR(100) NULL,
    description TEXT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (outbreak_id) REFERENCES asf_outbreaks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES user_accounts(id),
    INDEX idx_outbreak_id (outbreak_id),
    INDEX idx_document_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- DEPOPULATION EVENTS
-- =======================================================

-- Depopulation events
CREATE TABLE IF NOT EXISTS depopulation_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_code VARCHAR(50) UNIQUE NOT NULL,
    outbreak_id INT NULL,
    location_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    province VARCHAR(50) NOT NULL DEFAULT 'CALABARZON',
    city VARCHAR(50) NOT NULL,
    barangay VARCHAR(100) NULL,
    farm_name VARCHAR(100) NULL,
    event_date DATE NOT NULL,
    head_count INT NOT NULL DEFAULT 0,
    age_category VARCHAR(50) NULL,
    depopulation_method ENUM('culling', 'humane_euthanasia', 'other') NOT NULL,
    disposal_method VARCHAR(100) NULL,
    compensation_amount DECIMAL(12, 2) NULL,
    compensation_status ENUM('pending', 'approved', 'paid', 'denied') NULL,
    supervised_by INT NULL,
    conducted_by TEXT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (outbreak_id) REFERENCES asf_outbreaks(id) ON DELETE SET NULL,
    FOREIGN KEY (supervised_by) REFERENCES user_accounts(id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(id),
    INDEX idx_event_code (event_code),
    INDEX idx_outbreak_id (outbreak_id),
    INDEX idx_location (latitude, longitude),
    INDEX idx_event_date (event_date),
    INDEX idx_province (province)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- MEAT MOVEMENT TRACKING
-- =======================================================

-- Meat movement records
CREATE TABLE IF NOT EXISTS meat_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movement_code VARCHAR(50) UNIQUE NOT NULL,
    source_location VARCHAR(100) NOT NULL,
    source_latitude DECIMAL(10, 8) NULL,
    source_longitude DECIMAL(11, 8) NULL,
    source_province VARCHAR(50) NOT NULL,
    source_city VARCHAR(50) NOT NULL,
    destination_location VARCHAR(100) NOT NULL,
    destination_latitude DECIMAL(10, 8) NULL,
    destination_longitude DECIMAL(11, 8) NULL,
    destination_province VARCHAR(50) NOT NULL,
    destination_city VARCHAR(50) NOT NULL,
    movement_date DATE NOT NULL,
    meat_type ENUM('fresh', 'frozen', 'processed', 'live_animal') NOT NULL,
    quantity_kg DECIMAL(10, 2) NULL,
    quantity_heads INT NULL,
    transport_vehicle VARCHAR(100) NULL,
    transport_registration VARCHAR(50) NULL,
    driver_name VARCHAR(100) NULL,
    driver_license VARCHAR(50) NULL,
    health_certificate_number VARCHAR(100) NULL,
    certificate_issuing_authority VARCHAR(100) NULL,
    certificate_issue_date DATE NULL,
    certificate_expiry_date DATE NULL,
    status ENUM('in_transit', 'completed', 'rejected', 'quarantined') NOT NULL DEFAULT 'in_transit',
    checkpoints_passed TEXT NULL,
    notes TEXT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recorded_by) REFERENCES user_accounts(id),
    INDEX idx_movement_code (movement_code),
    INDEX idx_source_province (source_province),
    INDEX idx_destination_province (destination_province),
    INDEX idx_movement_date (movement_date),
    INDEX idx_status (status),
    INDEX idx_meat_type (meat_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- RISK ZONES AND PREDICTIVE ANALYSIS
-- =======================================================

-- High-risk zones
CREATE TABLE IF NOT EXISTS risk_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_code VARCHAR(50) UNIQUE NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    province VARCHAR(50) NOT NULL DEFAULT 'CALABARZON',
    city VARCHAR(50) NOT NULL,
    barangay VARCHAR(100) NULL,
    center_latitude DECIMAL(10, 8) NOT NULL,
    center_longitude DECIMAL(11, 8) NOT NULL,
    radius_km DECIMAL(8, 2) DEFAULT 5.00,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    risk_score DECIMAL(5, 2) NULL,
    factors_contributing JSON NULL,
    nearby_outbreaks_count INT DEFAULT 0,
    last_outbreak_date DATE NULL,
    depopulation_count INT DEFAULT 0,
    environmental_risk_score DECIMAL(5, 2) NULL,
    movement_risk_score DECIMAL(5, 2) NULL,
    population_density VARCHAR(50) NULL,
    status ENUM('active', 'monitoring', 'cleared') NOT NULL DEFAULT 'monitoring',
    identified_date DATE NOT NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reviewed_by) REFERENCES user_accounts(id),
    INDEX idx_zone_code (zone_code),
    INDEX idx_province (province),
    INDEX idx_risk_level (risk_level),
    INDEX idx_location (center_latitude, center_longitude),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Predictive model results
CREATE TABLE IF NOT EXISTS predictive_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    model_type VARCHAR(50) NOT NULL,
    prediction_date DATE NOT NULL,
    location_province VARCHAR(50) NULL,
    location_city VARCHAR(50) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    predicted_risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    predicted_risk_score DECIMAL(5, 2) NOT NULL,
    probability_outbreak DECIMAL(5, 2) NULL,
    confidence_level DECIMAL(5, 2) NULL,
    input_features JSON NULL,
    model_output JSON NULL,
    accuracy_metrics JSON NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES user_accounts(id),
    INDEX idx_model_name (model_name),
    INDEX idx_prediction_date (prediction_date),
    INDEX idx_predicted_risk_level (predicted_risk_level),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- DATA UPLOAD MANAGEMENT
-- =======================================================

-- Data upload records
CREATE TABLE IF NOT EXISTS data_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_code VARCHAR(50) UNIQUE NOT NULL,
    upload_type ENUM('environmental', 'outbreaks', 'depopulation', 'meat_movement', 'combined') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    total_records INT DEFAULT 0,
    successful_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    validation_errors JSON NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'partially_completed') NOT NULL DEFAULT 'pending',
    uploaded_by INT NOT NULL,
    processed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (uploaded_by) REFERENCES user_accounts(id),
    INDEX idx_upload_code (upload_code),
    INDEX idx_upload_type (upload_type),
    INDEX idx_status (status),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ALERTS AND NOTIFICATIONS
-- =======================================================

-- System alerts
CREATE TABLE IF NOT EXISTS system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_code VARCHAR(50) UNIQUE NOT NULL,
    alert_type ENUM('outbreak', 'high_risk', 'depopulation', 'meat_movement', 'predictive', 'system') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_resource_type VARCHAR(50) NULL,
    related_resource_id INT NULL,
    location_province VARCHAR(50) NULL,
    location_city VARCHAR(50) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    status ENUM('active', 'acknowledged', 'resolved', 'dismissed') NOT NULL DEFAULT 'active',
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (acknowledged_by) REFERENCES user_accounts(id),
    FOREIGN KEY (resolved_by) REFERENCES user_accounts(id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(id),
    INDEX idx_alert_code (alert_code),
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User notifications
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_id INT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    action_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_id) REFERENCES system_alerts(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- CONTENT MANAGEMENT
-- =======================================================

-- News articles and announcements
CREATE TABLE IF NOT EXISTS news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(500) NULL,
    category ENUM('news', 'announcement', 'guideline', 'update', 'alert') NOT NULL DEFAULT 'news',
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    author_id INT NOT NULL,
    views_count INT DEFAULT 0,
    meta_keywords TEXT NULL,
    meta_description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (author_id) REFERENCES user_accounts(id),
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_author_id (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- REPORTS
-- =======================================================

-- Generated reports
CREATE TABLE IF NOT EXISTS generated_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(50) UNIQUE NOT NULL,
    report_type ENUM('outbreak_summary', 'risk_analysis', 'predictive_insights', 'data_export', 'custom') NOT NULL,
    report_title VARCHAR(255) NOT NULL,
    report_format ENUM('pdf', 'csv', 'excel', 'html') NOT NULL,
    file_path VARCHAR(500) NULL,
    file_size INT NULL,
    parameters JSON NULL,
    status ENUM('pending', 'generating', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    generated_by INT NOT NULL,
    generated_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (generated_by) REFERENCES user_accounts(id),
    INDEX idx_report_code (report_code),
    INDEX idx_report_type (report_type),
    INDEX idx_status (status),
    INDEX idx_generated_by (generated_by),
    INDEX idx_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SYSTEM SETTINGS
-- =======================================================

-- System configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'decimal') NOT NULL DEFAULT 'string',
    category VARCHAR(50) NULL,
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES user_accounts(id),
    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SAMPLE DATA (OPTIONAL)
-- =======================================================

-- Insert default administrator (password: admin123 - should be changed immediately)
-- Password hash for 'admin123' using password_hash()
INSERT INTO user_accounts (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    user_role, 
    organization,
    is_active,
    is_verified,
    province
) VALUES (
    'admin',
    'admin@asf-surveillance.ph',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System',
    'Administrator',
    'administrator',
    'ASF Surveillance System - CALABARZON',
    1,
    1,
    'CALABARZON'
) ON DUPLICATE KEY UPDATE username=username;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('system_name', 'ASF Surveillance System', 'string', 'general', 'Name of the surveillance system', TRUE),
('system_region', 'CALABARZON', 'string', 'general', 'Target region for surveillance', TRUE),
('alert_email_enabled', 'true', 'boolean', 'notifications', 'Enable email alerts', FALSE),
('predictive_model_enabled', 'true', 'boolean', 'features', 'Enable predictive modeling', TRUE),
('data_retention_days', '365', 'integer', 'data', 'Number of days to retain data', FALSE),
('high_risk_threshold', '0.7', 'decimal', 'risk', 'Threshold score for high-risk zones', FALSE)
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- =======================================================
-- VIEWS FOR COMMON QUERIES
-- =======================================================

-- Active outbreaks view
DROP VIEW IF EXISTS vw_active_outbreaks;
CREATE VIEW vw_active_outbreaks AS
SELECT 
    o.*,
    CONCAT(u1.first_name, ' ', u1.last_name) AS reported_by_name,
    CONCAT(u2.first_name, ' ', u2.last_name) AS confirmed_by_name,
    COUNT(DISTINCT d.id) AS depopulation_count,
    COUNT(DISTINCT od.id) AS document_count
FROM asf_outbreaks o
LEFT JOIN user_accounts u1 ON o.reported_by = u1.id
LEFT JOIN user_accounts u2 ON o.confirmed_by = u2.id
LEFT JOIN depopulation_events d ON d.outbreak_id = o.id
LEFT JOIN outbreak_documents od ON od.outbreak_id = o.id
WHERE o.status IN ('suspected', 'confirmed', 'contained')
GROUP BY o.id;

-- High-risk zones summary view
DROP VIEW IF EXISTS vw_high_risk_zones;
CREATE VIEW vw_high_risk_zones AS
SELECT 
    r.*,
    CONCAT(u.first_name, ' ', u.last_name) AS reviewed_by_name,
    (SELECT COUNT(*) FROM asf_outbreaks o 
     WHERE (6371 * acos(
         cos(radians(r.center_latitude)) * 
         cos(radians(o.latitude)) * 
         cos(radians(o.longitude) - radians(r.center_longitude)) + 
         sin(radians(r.center_latitude)) * 
         sin(radians(o.latitude))
     )) <= r.radius_km
     AND o.outbreak_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ) AS recent_outbreaks_count
FROM risk_zones r
LEFT JOIN user_accounts u ON r.reviewed_by = u.id
WHERE r.status IN ('active', 'monitoring')
AND r.risk_level IN ('high', 'critical');

-- =======================================================
-- END OF SCHEMA
-- =======================================================
