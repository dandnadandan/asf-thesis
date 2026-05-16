-- =======================================================
-- DROP VIEWS ONLY - Run this first if views already exist
-- =======================================================
-- If you're getting "View already exists" errors,
-- run this SQL first to drop the existing views,
-- then run views_hosting_fix.sql to recreate them
-- =======================================================

-- Drop active outbreaks view
DROP VIEW IF EXISTS `vw_active_outbreaks`;

-- Drop high-risk zones view
DROP VIEW IF EXISTS `vw_high_risk_zones`;

-- Verification (optional)
-- After running this, check that views are dropped:
-- SHOW TABLES LIKE 'vw_%';
