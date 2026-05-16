# ASF Surveillance System Database

This document describes the database schema for the ASF (African Swine Fever) Surveillance System for CALABARZON.

## Database Name
`asf_surveillance_db`

## Installation

1. **Import the SQL file:**
   ```bash
   mysql -u root -p < database/asf_database.sql
   ```
   
   Or using phpMyAdmin:
   - Login to phpMyAdmin
   - Click "Import"
   - Select `database/asf_database.sql`
   - Click "Go"

2. **Update database configuration:**
   - Edit `config/database.php`
   - Verify database name is set to `asf_surveillance_db`
   - Update credentials if needed

## Database Structure

### User Management Tables
- **user_accounts** - User authentication and profile information
- **user_sessions** - Active user sessions for "Remember Me" functionality
- **user_activity_logs** - Audit trail of user actions

### Core Data Tables
- **environmental_data** - Environmental parameters (temperature, humidity, rainfall, etc.)
- **asf_outbreaks** - Historical and active ASF outbreak records
- **outbreak_documents** - Supporting documents and images for outbreaks
- **depopulation_events** - Depopulation/culling event records
- **meat_movement** - Meat movement and transportation tracking

### Risk Analysis Tables
- **risk_zones** - High-risk zone identification and monitoring
- **predictive_models** - Machine learning model predictions and results

### System Tables
- **data_uploads** - File upload tracking and validation
- **system_alerts** - System-generated alerts and notifications
- **user_notifications** - User-specific notifications
- **news_articles** - Content management for news and announcements
- **generated_reports** - Report generation tracking
- **system_settings** - System configuration settings

## User Roles

The system supports the following user roles:
- **administrator** - Full system access and control
- **field_staff** - Can input outbreak reports and data
- **analyst** - Can view data, generate reports, and access predictions
- **viewer** - Read-only access to dashboards and reports

## Default Administrator Account

After installation, a default administrator account is created:
- **Username:** admin
- **Email:** admin@asf-surveillance.ph
- **Password:** admin123 (⚠️ **MUST BE CHANGED IMMEDIATELY**)

## Key Features

### Geographic Data
All location-based tables include:
- Latitude/Longitude coordinates for GIS mapping
- Province, City, and Barangay fields for CALABARZON region
- Indexed for efficient geographic queries

### Data Validation
- Foreign key constraints ensure data integrity
- Status enums for consistent state management
- Timestamps for audit trails

### Security
- Password hashing using bcrypt
- Two-factor authentication support
- Activity logging for accountability
- Session management with token-based authentication

## Views

### vw_active_outbreaks
Aggregated view of active outbreaks with related counts and user information.

### vw_high_risk_zones
Summary view of high-risk zones with recent outbreak counts.

## Maintenance

### Backup
```bash
mysqldump -u root -p asf_surveillance_db > asf_backup_$(date +%Y%m%d).sql
```

### Restore
```bash
mysql -u root -p asf_surveillance_db < asf_backup_YYYYMMDD.sql
```

## Notes

- All timestamps use Asia/Manila timezone (UTC+8)
- Character set is UTF8MB4 for full Unicode support
- All tables use InnoDB engine for transaction support
- Geographic coordinates use DECIMAL(10,8) for latitude and DECIMAL(11,8) for longitude

## Support

For database-related issues or questions, refer to the main project documentation or contact the development team.
