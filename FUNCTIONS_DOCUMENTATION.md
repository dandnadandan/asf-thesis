# ASF Surveillance System - Functions Documentation

This document provides a comprehensive discussion of all functions in the ASF Surveillance System, organized by functional category.

---

## 1. Authentication Functions (`includes/auth_functions.php`)

### `authenticateUser($email, $password)`
This function handles user authentication by verifying email and password credentials against the database. It checks if the user account is active and verified before allowing login, and uses PHP's `password_verify()` function to securely compare the provided password with the stored hash. Upon successful authentication, it updates the user's last login timestamp and returns user data along with a role-based redirect URL. The function returns an array with success status, user information, and appropriate error messages for failed authentication attempts.

### `getRedirectUrlByRole($userRole)`
This function determines the appropriate redirect URL after login based on the user's role. In the ASF Surveillance System, all users are redirected to the admin dashboard (`admin/index.php`), with sidebar visibility controlled by role-based access control (RBAC) permissions. This centralized approach simplifies navigation while maintaining security through permission checks rather than separate dashboards.

### `updateLastLogin($conn, $userId)`
This function updates the user's last login timestamp in the database to track user activity and session history. It uses a prepared statement to safely update the `last_login_at` field with the current timestamp, which helps administrators monitor user engagement and identify inactive accounts.

### `createUserSession($userId, $sessionToken, $ipAddress, $userAgent)`
This function creates a persistent database session for "Remember Me" functionality, storing the session token, user ID, IP address, and user agent in the `user_sessions` table. The session is set to expire after 24 hours, providing a balance between user convenience and security. This allows users to remain logged in across browser sessions while maintaining security through token-based validation.

### `validateUserSession($sessionToken)`
This function validates a session token by checking if it exists in the database and hasn't expired, ensuring the user's session is still valid. It joins the `user_sessions` table with `user_accounts` to verify the user is still active, returning user data if the session is valid or false if it's expired or invalid. This provides secure session management for the "Remember Me" feature.

### `destroyUserSession($sessionToken)`
This function removes a user session from the database by deleting the session token record, effectively logging out the user from all devices when they explicitly log out. It's called during the logout process to ensure persistent sessions are properly terminated and cannot be reused.

### `cleanExpiredSessions()`
This function removes expired session records from the database to maintain database cleanliness and prevent accumulation of stale session data. It deletes all sessions where the expiration time has passed, returning the count of removed sessions for monitoring purposes.

### `getUserIP()`
This function retrieves the user's IP address by checking various HTTP headers in order of priority: `HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`, and finally `REMOTE_ADDR`. This approach handles different network configurations including proxies and load balancers, ensuring accurate IP tracking for security and audit purposes.

### `getUserAgent()`
This function retrieves the user's browser user agent string from the `HTTP_USER_AGENT` server variable, which identifies the browser and device being used. This information is stored with sessions for security auditing and helps identify suspicious login patterns.

### `generateSessionToken($length = 64)`
This function generates a cryptographically secure random session token using PHP's `random_bytes()` function, which is then converted to hexadecimal format. The default length of 64 characters provides strong security against token guessing attacks, and the function uses cryptographically secure random number generation to ensure unpredictability.

### `userHasPermission($userRole, $permission)`
This function checks if a user role has a specific permission by querying the `role_permissions` and `user_permissions` tables. It returns true if the role has the requested permission, enabling fine-grained access control throughout the application. This supports the role-based access control (RBAC) system used in the ASF Surveillance System.

### `sendPasswordResetEmail($email)`
This function handles the password reset process by generating a secure reset token and sending it to the user via email. It creates a time-limited token (default 1 hour expiry) and stores it in the database along with the expiration time, then sends an email with a reset link using the configured email system. The function maintains security by not revealing whether an email exists in the system if the user is not found.

### `validatePasswordResetToken($token)`
This function validates a password reset token by checking if it exists in the database, hasn't expired, and belongs to an active user account. It uses database time (`NOW()`) to compare against the expiration timestamp, avoiding timezone issues. The function includes extensive logging for debugging token validation issues and returns the user's email if the token is valid.

### `resetPassword($token, $newPassword)`
This function resets a user's password using a valid reset token, first validating the token and then hashing the new password using PHP's `password_hash()` with the default algorithm (bcrypt). After successfully updating the password, it clears the reset token and expiration fields to prevent token reuse. This completes the password reset workflow initiated by `sendPasswordResetEmail()`.

---

## 2. Session Management Functions (`includes/session_manager.php`)

### `startSecureSession()`
This function initializes a secure PHP session with security-focused configuration settings, including HTTP-only cookies, secure cookies (when HTTPS is available), strict mode, and SameSite cookie attributes. It also implements session ID regeneration every 30 minutes to prevent session fixation attacks, enhancing the overall security of user sessions.

### `isLoggedIn()`
This function checks if a user is currently logged in by verifying that both `user_id` and `user_role` are present in the session. It's a simple but essential function used throughout the application to determine authentication status before allowing access to protected resources.

### `hasRole($roles)`
This function checks if the current user has one of the specified roles, accepting either a single role string or an array of roles for flexible permission checking. It performs case-insensitive comparison to handle role name variations, making it robust for role-based access control throughout the application.

### `isAdministrator()`
This function is a convenience wrapper that checks if the current user has the administrator role, providing a simple way to verify administrative privileges. It's used extensively throughout the application to control access to administrative functions and sensitive operations.

### `isASFAdministrator()`
This function specifically checks for ASF Surveillance System administrator privileges, which have exclusive access to user management, system alerts, content management, news, and system settings. It's part of the role-based access control system that restricts certain administrative functions to administrators only.

### `isFieldStaff()`
This function checks if the current user has the field_staff role, which allows users to input outbreak reports and field data. Field staff members have permissions to create and update outbreak records, environmental data, and other field observations in the ASF surveillance system.

### `isAnalyst()`
This function verifies if the user has the analyst role, which grants access to view data, generate reports, and access predictive models. Analysts can analyze outbreak patterns, risk zones, and environmental data but typically cannot modify core data entries.

### `isViewer()`
This function checks if the user has the viewer role, which provides read-only access to dashboards and reports. Viewers can monitor the system's status and view published information but cannot create or modify any data in the system.

### `isSupervisor()`, `isVeterinarian()`, `isInspector()`, `isDataEntry()`
These functions check for specific professional roles within the ASF surveillance system, each with distinct permissions and responsibilities. They support the hierarchical role structure that allows different levels of access and responsibility for managing ASF surveillance data and operations.

### `canManageUsers()`, `canManageSystemAlerts()`, `canManageContent()`, `canManageNews()`, `canManageSystemSettings()`, `canAccessAdminProfile()`
These functions check for specific administrative permissions, all of which are currently restricted to administrators only in the ASF Surveillance System. They provide granular permission checking for different administrative functions, allowing for future expansion of permissions to other roles if needed.

### `requireLogin()`
This function enforces authentication by redirecting unauthenticated users to the login page, automatically detecting the correct path based on whether the current script is in a subdirectory. It's used at the beginning of protected pages to ensure only logged-in users can access them.

### `requireRole($roles, $redirectUrl)`
This function enforces role-based access control by first checking if the user is logged in, then verifying they have one of the required roles. If either check fails, it redirects to the specified URL (default: `unauthorized.php`), providing a security layer for role-restricted pages and functions.

### `logout($redirectUrl)`
This function performs a complete logout by destroying the database session (if "Remember Me" was used), clearing all session variables, deleting the session cookie, and destroying the PHP session. It handles path detection to ensure proper redirection regardless of the current directory structure, providing a clean logout experience.

### `getCurrentUser()`
This function retrieves the current logged-in user's information from the session, returning an array with user ID, email, role, name, username, and verification status. It returns null if no user is logged in, making it safe to call without prior authentication checks.

### `getCurrentUserRole()`, `getCurrentUserId()`
These functions provide quick access to the current user's role and ID from the session, returning null if not logged in. They're convenient helpers for role checking and user identification throughout the application.

### `validateDatabaseSession()`
This function validates a persistent database session token stored in the session, refreshing the session data with current user information from the database if valid. It's called during session initialization to ensure "Remember Me" sessions remain synchronized with the database and user account status.

### `cleanupExpiredSessions()`
This function periodically cleans up expired database sessions, but only runs on approximately 1% of requests to avoid performance impact. It uses a random check combined with session tracking to ensure cleanup happens regularly without affecting every page load.

### `handleSessionTimeout($timeoutMinutes)`
This function implements automatic session timeout by tracking the last activity time and logging out users who have been inactive for the specified duration (default 30 minutes). It updates the last activity timestamp on each call, ensuring active users remain logged in while inactive sessions are automatically terminated for security.

### `initSessionSecurity()`
This function initializes all session security features by calling `startSecureSession()`, `handleSessionTimeout()`, and `cleanupExpiredSessions()`, and validates database sessions if applicable. It's automatically called when the session_manager.php file is included, ensuring consistent security initialization across all pages.

---

## 3. User Management Functions (`classes/User.php`)

### `__construct()`
The User class constructor initializes a database connection by creating a new Database instance and storing the connection. This ensures that all User methods have access to the database without requiring external connection management.

### `create()`
This method creates a new user account by inserting user information into the `user_accounts` table, including username, email, password hash, name, role, and location details. It uses prepared statements to prevent SQL injection and returns true on success, setting the object's ID property to the newly created user's ID.

### `readById($id)`, `readByUsername($username)`, `readByEmail($email)`
These methods retrieve user information from the database by ID, username, or email respectively, loading the data into the User object's properties. They only return active users (`is_active = 1`) and use the private `assignProperties()` method to populate object properties from the database row.

### `update()`
This method updates user account information including username, email, name, company, phone, address, and profile image. It uses prepared statements to safely update only the specified fields, maintaining data integrity while allowing partial updates.

### `updatePassword($new_password_hash)`
This method updates a user's password hash in the database, allowing password changes without affecting other user data. It expects a pre-hashed password, ensuring that password hashing logic remains centralized and consistent.

### `updateLastLogin()`
This method updates the user's last login timestamp to the current time, tracking user activity for administrative and security purposes. It's called automatically during the login process to maintain accurate login history.

### `delete()`
This method performs a soft delete by setting the `is_active` flag to 0 rather than physically removing the record from the database. This preserves data integrity and allows for potential account recovery or audit trails.

### `getAllUsers($limit, $offset)`
This method retrieves a paginated list of all users from the database, returning essential user information without sensitive data like passwords. It's designed for administrative user management interfaces, supporting pagination through limit and offset parameters.

### `getUsersByRole($role, $limit)`
This method retrieves users filtered by a specific role, returning only active users matching the specified role. This is useful for role-based user listings and administrative functions that need to work with specific user groups.

### `hasPermission($permission_name)`
This method checks if the user's role has a specific permission by querying the role_permissions and user_permissions tables. It returns true if the permission exists for the user's role, supporting fine-grained access control within the User class.

### `getPermissions()`
This method retrieves all permissions associated with the user's role, returning an array of permission names and descriptions. This is useful for displaying available permissions or checking what actions a user can perform.

### `verifyPassword($password)`
This method verifies a plain-text password against the stored password hash using PHP's `password_verify()` function. It provides secure password checking without exposing the hash or requiring external password verification logic.

### `hashPassword($password)`
This static method hashes a password using PHP's `password_hash()` with the default algorithm (bcrypt), providing a centralized password hashing function. It's static so it can be called without instantiating a User object, useful during user creation.

### `usernameExists($username, $exclude_id)`, `emailExists($email, $exclude_id)`
These methods check if a username or email already exists in the database, optionally excluding a specific user ID (useful when updating existing users). They return true if the username/email is taken, helping prevent duplicate accounts during registration or profile updates.

### `assignProperties($row)`
This private method populates the User object's properties from a database row, handling optional fields with null coalescing operators. It ensures consistent property assignment across all read operations and handles variations in database schema.

### `getFullName()`
This method returns the user's full name by concatenating first name and last name, providing a convenient way to display user names throughout the application.

### `isAdministrator()`, `isAccountSupervisor()`, `isSeniorAccountExecutive()`, `isJuniorAccountExecutive()`, `isAdministrativeStaff()`, `isClient()`
These methods check if the user belongs to specific roles or role hierarchies, using inclusive checks that consider role hierarchies (e.g., administrators have all privileges). They support the role-based access control system by providing convenient role checking methods.

### `hasManagementAccess()`, `hasExecutiveAccess()`
These methods check for hierarchical access levels, where management access includes administrators and supervisors, and executive access includes all account executives and above. They support the organizational hierarchy within the system.

---

## 4. Database Functions (`config/database.php`)

### `Database::getConnection()`
This method establishes a PDO connection to the MySQL database using the configured credentials, setting important attributes like error mode, fetch mode, and prepared statement emulation. It sets the MySQL timezone to Asia/Manila (UTC+8) to ensure consistent datetime handling, and throws exceptions on connection failure for proper error handling.

### `Database::closeConnection()`
This method closes the database connection by setting the connection property to null, allowing PHP's garbage collector to clean up the PDO object. While PDO connections are automatically closed when the script ends, explicitly closing connections is good practice for resource management.

### `Database::testConnection()`
This method tests the database connection by attempting to establish a connection and immediately closing it, returning true if successful or false on failure. It's useful for health checks and connection validation during system initialization or troubleshooting.

---

## 5. Email Functions (`config/email_config.php`)

### `getEmailTemplate($template, $variables)`
This function replaces placeholder variables in email templates with actual values, using a simple string replacement mechanism with curly brace delimiters (e.g., `{first_name}`). It supports dynamic email content generation for password resets, notifications, and other system emails, making email templates reusable and maintainable.

### `getAppUrl()`
This function retrieves the application URL, first checking if `APP_URL` is defined as a constant, and if not, dynamically generating it from the current server environment. It handles both HTTP and HTTPS protocols and constructs the full URL including host and path, with error logging for debugging URL generation issues.

### `sendEmailWithFallback($to, $subject, $message, $fromEmail, $fromName)`
This function sends emails using PHPMailer with SMTP configuration when available, falling back to PHP's native `mail()` function if SMTP is disabled. It configures PHPMailer with Gmail SMTP settings, including TLS encryption and proper headers, and handles errors gracefully with logging. The fallback mechanism ensures email functionality even in development environments without SMTP access.

---

## 6. Notification Functions (`includes/notification_functions.php`)

### `createNotification($userId, $userRole, $type, $title, $message, $options)`
This function creates a new notification record in the database for a specific user, storing notification type, title, message, and optional metadata like related IDs, links, and priority levels. It supports various notification types including ASF-specific types like outbreaks, depopulation events, risk zones, and data uploads, enabling comprehensive notification coverage across the system.

### `getUnreadNotificationCount($userId)`
This function retrieves the count of unread, non-archived notifications for a user, providing a quick way to display notification badges or counts in the user interface. It's optimized for performance with a simple COUNT query, making it suitable for frequent polling or display updates.

### `getUserNotifications($userId, $filters)`
This function retrieves notifications for a user with optional filtering by read status, limit, and offset for pagination. It returns all non-archived notifications ordered by creation date (newest first), supporting flexible notification display requirements throughout the application.

### `markNotificationAsRead($notificationId, $userId)`
This function marks a notification as read, handling both database-stored notifications and dynamic notifications (stored in session). For dynamic notifications (prefixed with "dynamic_"), it stores the read status in the session, while database notifications are updated directly. This dual approach supports both persistent and temporary notification systems.

### `markAllNotificationsAsRead($userId, $userRole)`
This function marks all notifications as read for a user, including both database notifications and dynamic notifications generated based on the user's role. It calls role-specific notification generators to identify all dynamic notifications and marks them as read in the session, providing a comprehensive "mark all as read" functionality.

### `archiveNotification($notificationId, $userId)`
This function archives a notification by setting the `is_archived` flag to 1, or for dynamic notifications, storing the archived status in the session. Archiving allows users to hide notifications without deleting them, maintaining a notification history while keeping the active notification list clean.

### `deleteNotification($notificationId, $userId)`
This function permanently deletes a notification from the database, or archives dynamic notifications (since they can't be truly deleted). It includes user ID verification to ensure users can only delete their own notifications, maintaining security and data integrity.

### `getNotificationById($notificationId, $userId)`
This function retrieves a specific notification by ID, verifying that it belongs to the specified user for security. It returns the notification data as an associative array or null if not found, supporting detailed notification views and operations.

### `getRecentNotifications($userId, $limit)`
This function is a convenience wrapper that retrieves the most recent notifications for a user, defaulting to 5 notifications. It's optimized for dropdown displays and notification previews where only a few recent items are needed.

### `createBulkNotification($userIds, $userRole, $type, $title, $message, $options)`
This function creates the same notification for multiple users by iterating through an array of user IDs and calling `createNotification()` for each. It's useful for system-wide announcements, alerts, or notifications that need to be sent to multiple users simultaneously, returning true only if all notifications are created successfully.

### `getNotificationStats($userId)`
This function retrieves notification statistics for a user, including total notifications, unread count, read count, and urgent unread count. It provides aggregated data useful for dashboard displays and notification management interfaces, giving users insight into their notification status.

### `timeAgo($datetime)`
This helper function formats a datetime as a relative time string (e.g., "2 hours ago", "3 days ago") for human-readable display. It handles various time ranges from seconds to years, providing appropriate formatting for each range, and is commonly used in notification displays and activity feeds.

### `getNotificationIcon($type)`
This function returns a Bootstrap icon class name based on the notification type, supporting both generic types (document, service, payment) and ASF-specific types (outbreak, depopulation, risk_zone). It provides visual consistency across notification displays by mapping notification types to appropriate icons.

### `getNotificationPriorityClass($priority)`
This function returns a CSS color class based on notification priority (low, normal, high, urgent), enabling visual priority indication through color coding. It supports the notification priority system by providing appropriate styling classes for different priority levels.

---

## 7. Date/Time Helper Functions (`includes/date_helper.php`)

### `formatDate($date)`
This function formats a date for display in a human-readable format (e.g., "Oct 28, 2025"), handling invalid dates and empty values gracefully by returning "N/A". It supports both date strings and timestamps, automatically detecting the input type and converting appropriately.

### `formatTime($datetime)`
This function formats a time for display in 12-hour format with AM/PM (e.g., "02:30 PM"), handling invalid times and empty values. It extracts just the time portion from datetime values, useful for displaying times independently of dates.

### `formatDateTime($datetime)`
This function formats a full datetime for display combining date and time (e.g., "Oct 28, 2025 02:30 PM"). It provides a standard format for datetime display throughout the application, ensuring consistency in how dates and times are presented to users.

### `formatDateTimeWithDay($datetime)`
This function formats a datetime including the day name (e.g., "Tuesday, Oct 28, 2025 02:30 PM"), providing more context for date displays. It's useful for detailed views where the day of the week adds helpful information.

### `formatDateForDB($date)`, `formatDateTimeForDB($datetime)`
These functions format dates and datetimes for database storage in standard SQL formats (YYYY-MM-DD and YYYY-MM-DD HH:MM:SS). They ensure consistent date formatting when storing data in the database, preventing format-related errors and maintaining data integrity.

### `getRelativeTime($datetime)`
This function converts a datetime to a relative time string (e.g., "2 hours ago", "3 days ago") with appropriate formatting for different time ranges. It provides human-readable time differences that are more intuitive than absolute timestamps, especially for recent events.

### `getCurrentDateTime($format)`
This function returns the current datetime in the specified format, defaulting to the standard database datetime format. It uses the Asia/Manila timezone set at the application level, ensuring consistent timezone handling across the system.

### `isToday($date)`, `isThisWeek($date)`
These functions check if a date falls within today or this week respectively, useful for conditional formatting and filtering. They support relative date comparisons that are common in user interfaces, such as highlighting today's events or grouping weekly activities.

### `formatTableDate($date)`, `formatTableDateTime($datetime)`
These functions provide compact date formatting optimized for table displays, with `formatTableDateTime()` showing relative times for today and abbreviated formats for this week. They balance readability with space efficiency, important for data-dense table views.

### `formatNotificationTime($datetime)`
This function formats datetime for notifications, preferring relative time for recent notifications (within 24 hours) and full datetime for older ones. This approach provides the most relevant time information based on recency, improving notification readability.

### `formatDetailedDateTime($datetime)`
This function provides the most comprehensive datetime format including the day name, suitable for detailed views where complete context is important. It uses `formatDateTimeWithDay()` to provide maximum information for important datetime displays.

### `isValidDate($date, $format)`
This function validates that a date string matches the expected format using PHP's DateTime class, returning true if valid and false otherwise. It's useful for form validation and data integrity checks before processing or storing dates.

---

## 8. ASF-Specific Notification Generator Functions (`includes/asf_admin_notification_generator.php`)

### `applyDynamicNotificationStatus($notifications)`
This function applies read and archived status to dynamic notifications by checking session-stored status arrays, allowing dynamic notifications to maintain state across page loads. It filters out archived notifications and marks read status appropriately, ensuring dynamic notifications behave like database notifications from the user's perspective.

### `generateASFAdminNotifications($userId)`
This is the main function that generates all ASF-specific notifications for administrators by calling various notification generator functions for different ASF data types. It combines notifications from outbreaks, depopulation events, risk zones, data uploads, news articles, system alerts, reports, environmental data, meat movements, predictive models, and user management, providing comprehensive notification coverage for administrators.

### `getNewOutbreakNotifications($pdo)`, `getOutbreakStatusChangeNotifications($pdo)`
These functions generate notifications for new ASF outbreaks and status changes in existing outbreaks, helping administrators stay informed about disease surveillance activities. They query the `asf_outbreaks` table for recent entries and status changes, creating dynamic notifications that alert administrators to critical disease monitoring events.

### `getNewDepopulationNotifications($pdo)`
This function generates notifications for new depopulation events, which are critical disease control measures in ASF management. It identifies recent depopulation records and creates notifications with relevant details, ensuring administrators are aware of disease control actions being taken.

### `getNewRiskZoneNotifications($pdo)`, `getRiskZoneStatusChangeNotifications($pdo)`, `getCriticalRiskZoneNotifications($pdo)`
These functions generate notifications related to risk zones, including new risk zone identifications, status changes, and critical risk situations. They help administrators monitor areas of concern and track changes in risk assessments, which are crucial for effective ASF surveillance and response.

### `getDataUploadNotifications($pdo)`
This function generates notifications for new data uploads, alerting administrators when new surveillance data is added to the system. It tracks uploads that require review or processing, ensuring data quality and timely processing of surveillance information.

### `getNewsArticleNotifications($pdo)`
This function generates notifications for newly published news articles, keeping administrators informed about public communications and announcements. It helps coordinate communication efforts and ensures administrators are aware of published content.

### `getSystemAlertNotifications($pdo)`
This function generates notifications for system-generated alerts, which may include critical system issues, data anomalies, or important system events. It ensures administrators are promptly notified of system-level concerns that require attention.

### `getReportGenerationNotifications($pdo)`
This function generates notifications when new reports are generated, alerting administrators to newly available analytical reports. It helps track report generation activities and ensures administrators are aware of new analytical outputs.

### `getEnvironmentalDataNotifications($pdo)`
This function generates notifications for significant environmental data entries, which are important for ASF surveillance as environmental factors can influence disease spread. It alerts administrators to new environmental monitoring data that may be relevant for risk assessment.

### `getMeatMovementNotifications($pdo)`
This function generates notifications for meat movement records, which are critical for tracking potential disease transmission through animal product transportation. It helps administrators monitor movement patterns that could contribute to ASF spread.

### `getPredictiveModelNotifications($pdo)`
This function generates notifications for predictive model results, alerting administrators to new risk predictions or model outputs. It helps administrators stay informed about analytical insights that could inform surveillance and response strategies.

### `getUserManagementNotifications($pdo)`
This function generates notifications related to user management activities, such as new user registrations or account changes. It helps administrators monitor user account activities and maintain oversight of system access.

### `getASFAdminDynamicNotificationStats($userId)`
This function calculates statistics for ASF admin dynamic notifications, providing counts of total, unread, and urgent notifications. It supports notification management interfaces by providing aggregated statistics for administrators to understand their notification load.

---

## 9. Notification Trigger Functions (`includes/asf_notification_triggers.php`)

### `triggerNewOutbreakNotification($pdo, $outbreakId, $outbreakData)`
This function creates notifications when a new ASF outbreak is reported, alerting relevant users to the new disease event. It extracts key information from the outbreak data and creates appropriate notifications for administrators and other stakeholders who need to be informed about new outbreaks.

### `triggerOutbreakStatusChangeNotification($pdo, $outbreakId, $oldStatus, $newStatus, $outbreakData)`
This function creates notifications when an outbreak's status changes (e.g., from suspected to confirmed), keeping stakeholders informed about the progression of disease events. It compares old and new statuses to generate meaningful notification messages about status transitions.

### `triggerNewDepopulationNotification($pdo, $depopulationId, $depopulationData)`
This function creates notifications for new depopulation events, which are critical disease control measures. It alerts administrators and relevant staff when depopulation actions are recorded, ensuring awareness of disease control activities.

### `triggerNewRiskZoneNotification($pdo, $zoneId, $zoneData)`
This function creates notifications when new risk zones are identified, alerting administrators to areas requiring increased surveillance or monitoring. It helps ensure that new risk assessments are promptly communicated to relevant personnel.

### `triggerRiskZoneStatusChangeNotification($pdo, $zoneId, $oldStatus, $newStatus, $zoneData)`
This function creates notifications when a risk zone's status changes, keeping administrators informed about evolving risk assessments. It helps track changes in risk zone classifications that may require different response strategies.

### `triggerDataUploadNotification($pdo, $uploadId, $uploadData)`
This function creates notifications when new data is uploaded to the system, alerting administrators to new surveillance data that may require review or processing. It ensures timely awareness of new data entries that could impact surveillance activities.

### `triggerNewsArticlePublishedNotification($pdo, $articleId, $articleData)`
This function creates notifications when news articles are published, alerting administrators to new public communications. It helps coordinate communication efforts and ensures administrators are aware of published content.

### `triggerSystemAlertNotification($pdo, $alertId, $alertData)`
This function creates notifications for system-generated alerts, which may include critical system issues or important events. It ensures that system-level concerns are promptly communicated to administrators.

### `triggerReportGeneratedNotification($pdo, $reportId, $reportData, $generatedByUserId)`
This function creates notifications when reports are generated, alerting relevant users to newly available analytical reports. It helps track report generation activities and ensures stakeholders are aware of new analytical outputs.

### `triggerNewUserNotification($pdo, $userId, $userData)`
This function creates notifications when new users register or are created, alerting administrators to new account activities. It helps maintain oversight of system access and user management activities.

### `triggerMeatMovementNotification($pdo, $movementId, $movementData)`
This function creates notifications for meat movement records, which are critical for tracking potential disease transmission. It alerts administrators to movement patterns that could be relevant for disease surveillance and control.

### `triggerPredictiveModelNotification($pdo, $modelId, $modelData)`
This function creates notifications for predictive model results, alerting administrators to new risk predictions or analytical insights. It helps ensure that important analytical outputs are promptly communicated to decision-makers.

---

## Summary

The ASF Surveillance System includes over 100 functions organized into logical categories:

- **Authentication & Security**: User authentication, session management, password reset, and security functions
- **User Management**: User CRUD operations, role checking, and permission management
- **Database**: Connection management and database operations
- **Email**: Email template processing and sending functionality
- **Notifications**: Comprehensive notification system supporting both database and dynamic notifications
- **Date/Time**: Extensive date and time formatting and manipulation utilities
- **ASF-Specific**: Specialized functions for ASF surveillance data, notifications, and triggers

All functions follow consistent error handling patterns, use prepared statements for database operations, and include appropriate logging for debugging and audit purposes. The system is designed with security, maintainability, and extensibility in mind.
