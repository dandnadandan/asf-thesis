# Hosting Views Setup Guide

## Problem
When exporting views from MySQL, they often include a `DEFINER` clause that references a specific user (e.g., `DEFINER='username'@'hostname'`). Shared hosting environments typically don't allow you to set DEFINER to arbitrary users, causing errors like:

```
Access denied; you need (at least one of) the SET USER privilege(s) for this operation
```

## Solution
Use the hosting-compatible SQL file (`views_hosting_fix.sql`) which creates views without the DEFINER clause.

## Steps to Deploy Views on Hosting

### Method 1: Using phpMyAdmin (Recommended)

1. **Log in to phpMyAdmin** on your hosting control panel

2. **Select your database** from the left sidebar

3. **Click on the "SQL" tab** at the top

4. **Copy the entire contents** of `database/views_hosting_fix.sql`

5. **Paste into the SQL query box**

6. **Click "Go"** to execute

7. **Verify the views were created:**
   - Check the left sidebar - you should see `vw_active_outbreaks` and `vw_high_risk_zones` listed under "Views"
   - Run: `SELECT * FROM vw_active_outbreaks LIMIT 1;` to test

### Method 2: Using MySQL Command Line

1. **Upload** the `views_hosting_fix.sql` file to your server

2. **Connect via SSH** (if available) or use your hosting's MySQL command line

3. **Run:**
   ```bash
   mysql -u your_username -p your_database_name < views_hosting_fix.sql
   ```

### Method 3: Using MySQL Client (HeidiSQL, MySQL Workbench, etc.)

1. **Connect** to your hosting database

2. **Open** the `views_hosting_fix.sql` file

3. **Execute** the script

## What This File Does

The `views_hosting_fix.sql` file:
- Drops any existing views (to avoid conflicts)
- Creates views **without DEFINER clauses** (hosting-compatible)
- Uses standard SQL that works on all MySQL/MariaDB versions

## Views Included

### 1. `vw_active_outbreaks`
Aggregated view of active outbreaks with:
- Outbreak details
- Reporter and confirmer names
- Depopulation event counts
- Document counts

### 2. `vw_high_risk_zones`
Summary view of high-risk zones with:
- Zone details
- Reviewer names
- Recent outbreak counts (within 90 days)

## Troubleshooting

### Error: "View already exists"
**Solution:** The script includes `DROP VIEW IF EXISTS`, so run the entire script again. The DROP statement will remove the old view first.

### Error: "Table doesn't exist"
**Solution:** Make sure you've already created all the required tables:
- `asf_outbreaks`
- `user_accounts`
- `depopulation_events`
- `outbreak_documents`
- `risk_zones`

### Error: "Access denied"
**Solution:** Make sure you're using a database user account with:
- CREATE VIEW privileges
- SELECT privileges on all related tables
- DROP VIEW privileges (to remove old views)

## Notes

- Views are read-only by default
- Views will automatically reflect changes to underlying tables
- If you modify table structures, you may need to recreate the views
- Keep a backup of this SQL file for future deployments
