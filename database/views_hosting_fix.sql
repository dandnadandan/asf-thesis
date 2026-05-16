-- =======================================================
-- HOSTING-COMPATIBLE VIEW CREATION
-- =======================================================
-- This file creates database views without DEFINER clause
-- for deployment on shared hosting environments
-- 
-- Run this SQL in phpMyAdmin or your MySQL client
-- =======================================================

-- Active outbreaks view
-- Step 1: Drop existing view if it exists
DROP VIEW IF EXISTS `vw_active_outbreaks`;

-- Step 2: Create hosting-compatible view (NO DEFINER clause)
CREATE VIEW `vw_active_outbreaks` AS
SELECT 
    o.id,
    o.outbreak_code,
    o.location_name,
    o.latitude,
    o.longitude,
    o.province,
    o.city,
    o.barangay,
    o.farm_name,
    o.farm_type,
    o.outbreak_date,
    o.reported_date,
    o.confirmed_date,
    o.status,
    o.total_pigs_affected,
    o.total_pigs_mortality,
    o.total_pigs_depopulated,
    o.severity_level,
    o.source_of_infection,
    o.clinical_signs,
    o.laboratory_confirmed,
    o.lab_confirmation_date,
    o.containment_measures,
    o.notes,
    o.reported_by,
    o.confirmed_by,
    o.created_at,
    o.updated_at,
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
-- Step 1: Drop existing view if it exists
DROP VIEW IF EXISTS `vw_high_risk_zones`;

-- Step 2: Create hosting-compatible view (NO DEFINER clause)
CREATE VIEW `vw_high_risk_zones` AS
SELECT 
    r.id,
    r.zone_code,
    r.zone_name,
    r.province,
    r.city,
    r.barangay,
    r.center_latitude,
    r.center_longitude,
    r.radius_km,
    r.risk_level,
    r.risk_score,
    r.factors_contributing,
    r.nearby_outbreaks_count,
    r.last_outbreak_date,
    r.depopulation_count,
    r.environmental_risk_score,
    r.movement_risk_score,
    r.population_density,
    r.status,
    r.identified_date,
    r.reviewed_by,
    r.notes,
    r.created_at,
    r.updated_at,
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
-- VERIFICATION QUERIES (Optional - Remove these after testing)
-- =======================================================

-- Test the views work correctly
-- SELECT * FROM vw_active_outbreaks LIMIT 5;
-- SELECT * FROM vw_high_risk_zones LIMIT 5;
