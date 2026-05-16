-- =======================================================
-- Add Additional Roles to ASF Surveillance System
-- =======================================================

USE asf_surveillance_db;

-- Modify user_role ENUM to include additional roles
ALTER TABLE user_accounts 
MODIFY COLUMN user_role ENUM(
    'administrator', 
    'field_staff', 
    'analyst', 
    'viewer',
    'supervisor',
    'veterinarian',
    'inspector',
    'data_entry'
) NOT NULL DEFAULT 'viewer';

-- Note: The following roles are available:
-- - administrator: Full system access (user management, system alerts, content management, news, system settings)
-- - supervisor: Can oversee field staff and analysts
-- - veterinarian: Medical/health expertise for ASF analysis
-- - inspector: Can inspect and validate outbreak reports
-- - field_staff: Can create outbreak reports and upload data
-- - analyst: Can view data and generate reports
-- - data_entry: Can enter data but not view sensitive information
-- - viewer: Read-only access to public information
