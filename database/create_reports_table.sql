-- Create reports_history table for tracking generated reports
CREATE TABLE IF NOT EXISTS reports_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    format ENUM('csv', 'pdf', 'excel') NOT NULL DEFAULT 'csv',
    date_from DATE NULL,
    date_to DATE NULL,
    file_path VARCHAR(500) NULL,
    file_size INT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    download_count INT DEFAULT 0,
    parameters TEXT NULL,
    
    FOREIGN KEY (generated_by) REFERENCES user_accounts(id) ON DELETE CASCADE,
    INDEX idx_report_type (report_type),
    INDEX idx_generated_by (generated_by),
    INDEX idx_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
