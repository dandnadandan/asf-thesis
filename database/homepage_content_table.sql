-- =======================================================
-- Homepage Content Management Table
-- Stores dynamic content for the ASF Surveillance System homepage
-- =======================================================

USE asf_surveillance_db;

-- Homepage content sections
CREATE TABLE IF NOT EXISTS homepage_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('carousel_slide', 'feature_card', 'page_header', 'about_section') NOT NULL,
    title VARCHAR(255) NULL,
    subtitle VARCHAR(500) NULL,
    description TEXT NULL,
    badge_text VARCHAR(100) NULL,
    icon_class VARCHAR(100) NULL, -- Bootstrap icon class for features
    image_path VARCHAR(500) NULL, -- Path to image for carousel slides
    content_order INT DEFAULT 0, -- Order/sequence for display
    is_active BOOLEAN DEFAULT TRUE,
    external_link VARCHAR(500) NULL, -- Optional link URL
    link_text VARCHAR(100) NULL, -- Optional link text
    meta_data JSON NULL, -- Additional metadata in JSON format
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
    INDEX idx_content_type (content_type),
    INDEX idx_is_active (is_active),
    INDEX idx_content_order (content_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default carousel slides
INSERT INTO homepage_content (content_type, title, description, badge_text, image_path, content_order, is_active) VALUES
('carousel_slide', 'What is African Swine Fever?', 'African Swine Fever (ASF) is a highly contagious viral disease of domestic and wild pigs with a mortality rate that can reach 100%. While it does not affect human health, ASF has devastating effects on pig populations and the farming economy. The virus is highly resistant in the environment and can survive on clothes, boots, wheels, and various pork products.', 'WOAH Listed Disease', 'uploads/contents/pigs_1.png', 1, TRUE),
('carousel_slide', 'Global Threat to Food Security', 'ASF continues to spread worldwide, affecting multiple countries across Asia, the Caribbean, Europe, and the Pacific. Pork meat accounts for more than 35% of global meat intake, making this disease a serious threat to food security. The spread of ASF has devastated family-run pig farms, impacting livelihoods and reducing opportunities for healthcare and education in affected communities.', 'Global Concern', 'uploads/contents/pigs_2.png', 2, TRUE),
('carousel_slide', 'How ASF Spreads', 'ASF spreads through direct contact between infected pigs, ingestion of contaminated materials (food waste, feed, garbage), contact with contaminated fomites (clothing, footwear, vehicles), or bites from biological vectors (soft ticks). Human behaviors play an important role in spreading this disease across borders if adequate biosecurity measures are not implemented.', 'Disease Control', 'uploads/contents/pigs_3.png', 3, TRUE),
('carousel_slide', 'Clinical Signs of ASF', 'Acute forms are characterized by high fever, depression, anorexia, hemorrhages in the skin (redness on ears, abdomen, and legs), abortion in pregnant sows, cyanosis, vomiting, diarrhea, and death within 6-13 days. Mortality rates may be as high as 100%. Subacute and chronic forms show less intense clinical signs but can still result in 30-70% mortality. Laboratory confirmation is essential to differentiate ASF from classical swine fever.', 'Early Detection', 'uploads/contents/pigs_4.png', 4, TRUE),
('carousel_slide', 'Prevention & Control', 'Biosecurity is the most important and effective measure to prevent and control ASF. Rigorous implementation of biosecurity principles at farm level, increased vigilance at borders to prevent illegal movement of ASF-infected animals or commodities, and effective risk communication are essential. Despite its complexity, global control of ASF is possible with sustained effort and collaboration at national, regional, and international levels.', 'Biosecurity', 'uploads/contents/pigs_5.png', 5, TRUE)
ON DUPLICATE KEY UPDATE title=title;

-- Insert default page header
INSERT INTO homepage_content (content_type, title, subtitle, content_order, is_active) VALUES
('page_header', 'ASF Surveillance Dashboard', 'Real-time monitoring and predictive analysis for African Swine Fever in CALABARZON', 1, TRUE)
ON DUPLICATE KEY UPDATE title=title;

-- Insert default feature cards
INSERT INTO homepage_content (content_type, title, description, icon_class, content_order, is_active) VALUES
('feature_card', 'Predictive Modeling', 'Machine learning algorithms analyze historical data and environmental factors to forecast ASF trends and potential outbreaks.', 'bi-cpu-fill', 1, TRUE),
('feature_card', 'Real-Time Monitoring', 'Continuous surveillance of environmental parameters, outbreak reports, and meat movement patterns for early detection.', 'bi-speedometer2', 2, TRUE),
('feature_card', 'Data Management', 'Upload and manage environmental data, historical outbreaks, depopulation records, and meat movement logs efficiently.', 'bi-database-fill', 3, TRUE),
('feature_card', 'GIS Visualization', 'Interactive maps with multiple layers showing outbreaks, risk zones, depopulation events, and movement routes.', 'bi-geo-fill', 4, TRUE),
('feature_card', 'Alert & Notifications', 'Automated alerts for emerging outbreaks, high-risk zone identification, and critical surveillance updates via email.', 'bi-bell-fill', 5, TRUE),
('feature_card', 'Reporting Tools', 'Generate comprehensive reports (PDF, CSV, Excel) summarizing outbreak trends, risk analysis, and predictive insights.', 'bi-file-earmark-text-fill', 6, TRUE)
ON DUPLICATE KEY UPDATE title=title;

-- Insert default about section
INSERT INTO homepage_content (content_type, title, description, content_order, is_active) VALUES
('about_section', 'About ASF Surveillance System', 'The ASF Surveillance System for CALABARZON is a comprehensive GIS-based predictive monitoring platform designed to enhance early detection and effective management of African Swine Fever outbreaks. Developed in alignment with Bureau of Animal Industry (BAI), World Organisation for Animal Health (WOAH), and Food and Agriculture Organization (FAO) guidelines, this system integrates real-time data collection, machine learning algorithms, and interactive mapping to support proactive disease surveillance and outbreak response.', 1, TRUE)
ON DUPLICATE KEY UPDATE title=title;
