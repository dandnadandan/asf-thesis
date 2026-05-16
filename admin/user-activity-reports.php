<?php
/**
 * User Activity Reports & Analytics
 * Comprehensive activity tracking system for all users and roles
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require owner, administrator or administrative staff role
requireRole(['owner', 'administrator', 'administrative staff'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'User Activity Reports';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle GET parameters for filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_role_filter = $_GET['user_role'] ?? 'all';
$activity_type_filter = $_GET['activity_type'] ?? 'all';
$user_search = $_GET['user_search'] ?? '';

// Function to get service request activity
function getServiceRequestActivity($pdo, $date_from, $date_to, $user_role_filter, $user_search) {
    $activities = [];
    
    // Tax Filing Services
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Tax Filing' as service_type,
                tfs.id as service_id,
                tfs.business_name,
                tfs.service_status,
                tfs.created_at,
                tfs.total_amount
            FROM tax_filing_services tfs
            LEFT JOIN user_accounts ua ON tfs.user_id = ua.id
            WHERE tfs.created_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if ($user_role_filter !== 'all') {
        $sql .= " AND ua.user_role = :role";
        $params['role'] = $user_role_filter;
    }
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY tfs.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Payroll Processing Services
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Payroll Processing' as service_type,
                pps.id as service_id,
                pps.company_name as business_name,
                pps.service_status,
                pps.created_at,
                pps.total_amount
            FROM payroll_processing_services pps
            LEFT JOIN user_accounts ua ON pps.user_id = ua.id
            WHERE pps.created_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if ($user_role_filter !== 'all') {
        $sql .= " AND ua.user_role = :role";
        $params['role'] = $user_role_filter;
    }
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY pps.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Accounting & Bookkeeping Services
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Accounting & Bookkeeping' as service_type,
                abs.id as service_id,
                abs.business_name,
                abs.service_status,
                abs.created_at,
                abs.total_amount
            FROM accounting_bookkeeping_services abs
            LEFT JOIN user_accounts ua ON abs.user_id = ua.id
            WHERE abs.created_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if ($user_role_filter !== 'all') {
        $sql .= " AND ua.user_role = :role";
        $params['role'] = $user_role_filter;
    }
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY abs.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Financial Statements Services
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Financial Statements' as service_type,
                fss.id as service_id,
                fss.business_name,
                fss.service_status,
                fss.created_at,
                fss.total_amount
            FROM financial_statements_services fss
            LEFT JOIN user_accounts ua ON fss.user_id = ua.id
            WHERE fss.created_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if ($user_role_filter !== 'all') {
        $sql .= " AND ua.user_role = :role";
        $params['role'] = $user_role_filter;
    }
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY fss.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Compliance Consulting Services
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Compliance Consulting' as service_type,
                ccs.id as service_id,
                ccs.company_name as business_name,
                ccs.service_status,
                ccs.created_at,
                ccs.total_amount
            FROM compliance_consulting_services ccs
            LEFT JOIN user_accounts ua ON ccs.user_id = ua.id
            WHERE ccs.created_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if ($user_role_filter !== 'all') {
        $sql .= " AND ua.user_role = :role";
        $params['role'] = $user_role_filter;
    }
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY ccs.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Sort by date
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $activities;
}

// Function to get document upload activity
function getDocumentUploadActivity($pdo, $date_from, $date_to, $user_role_filter, $user_search) {
    $documents = [];

    $documentSources = [
        [
            'activity_label'      => 'Tax Filing Document',
            'documents_table'     => 'tax_filing_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_at',
            'service_table'       => 'tax_filing_services',
            'service_alias'       => 's',
            'service_name_column' => 'business_name'
        ],
        [
            'activity_label'      => 'Accounting Document',
            'documents_table'     => 'accounting_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_at',
            'service_table'       => 'accounting_bookkeeping_services',
            'service_alias'       => 's',
            'service_name_column' => 'business_name'
        ],
        [
            'activity_label'      => 'Financial Statements Document',
            'documents_table'     => 'financial_statements_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_at',
            'service_table'       => 'financial_statements_services',
            'service_alias'       => 's',
            'service_name_column' => 'business_name'
        ],
        [
            'activity_label'      => 'Business Registration Document',
            'documents_table'     => 'business_registration_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_at',
            'service_table'       => 'business_registration_services',
            'service_alias'       => 's',
            'service_name_column' => 'company_name'
        ],
        [
            'activity_label'      => 'Compliance Consulting Document',
            'documents_table'     => 'compliance_consulting_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_at',
            'service_table'       => 'compliance_consulting_services',
            'service_alias'       => 's',
            'service_name_column' => 'company_name'
        ],
        [
            'activity_label'      => 'Payroll Processing Document',
            'documents_table'     => 'payroll_processing_documents',
            'documents_alias'     => 'd',
            'date_column'         => 'uploaded_date',
            'service_table'       => 'payroll_processing_services',
            'service_alias'       => 's',
            'service_name_column' => 'company_name'
        ],
    ];

    foreach ($documentSources as $source) {
        $sql = "SELECT 
                    ua.id as user_id,
                    ua.first_name,
                    ua.last_name,
                    ua.email,
                    ua.user_role,
                    :activity_label AS activity_type,
                    {$source['documents_alias']}.document_name,
                    {$source['documents_alias']}.document_type,
                    {$source['documents_alias']}.status,
                    {$source['documents_alias']}.{$source['date_column']} AS activity_date,
                    {$source['service_alias']}.{$source['service_name_column']} AS business_name
                FROM {$source['documents_table']} {$source['documents_alias']}
                LEFT JOIN {$source['service_table']} {$source['service_alias']} ON {$source['documents_alias']}.service_id = {$source['service_alias']}.id
                LEFT JOIN user_accounts ua ON {$source['service_alias']}.user_id = ua.id
                WHERE {$source['documents_alias']}.{$source['date_column']} BETWEEN :date_from AND :date_to";

        $params = [
            'activity_label' => $source['activity_label'],
            'date_from'      => $date_from,
            'date_to'        => $date_to . ' 23:59:59',
        ];

        if ($user_role_filter !== 'all') {
            $sql .= " AND ua.user_role = :role";
            $params['role'] = $user_role_filter;
        }

        if (!empty($user_search)) {
            $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
            $params['search1'] = '%' . $user_search . '%';
            $params['search2'] = '%' . $user_search . '%';
            $params['search3'] = '%' . $user_search . '%';
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $documents = array_merge($documents, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log("Document activity fetch error for {$source['documents_table']}: " . $e->getMessage());
            continue;
        }
    }

    usort($documents, function ($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });

    return $documents;
}

// Function to get user statistics
function getUserActivityStats($pdo, $date_from, $date_to) {
    // Total users by role
    $sql = "SELECT user_role, COUNT(*) as count 
            FROM user_accounts 
            WHERE created_at <= :date_to 
            GROUP BY user_role";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['date_to' => $date_to . ' 23:59:59']);
    $users_by_role = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // New registrations in period
    $sql = "SELECT COUNT(*) as count 
            FROM user_accounts 
            WHERE created_at BETWEEN :date_from AND :date_to";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59']);
    $new_registrations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active users (logged in during period)
    $active_users = 0;
    try {
        $sql = "SELECT COUNT(DISTINCT user_id) as count 
                FROM user_sessions 
                WHERE created_at BETWEEN :date_from AND :date_to";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59']);
        $active_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Exception $e) {
        $active_users = 0;
    }
    
    return [
        'users_by_role' => $users_by_role,
        'new_registrations' => $new_registrations,
        'active_users' => $active_users
    ];
}

// Function to get admin activity (status changes, assignments, etc.)
function getAdminActivity($pdo, $date_from, $date_to, $user_search) {
    $admin_activities = [];
    
    // Tax Filing Status History
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Status Change - Tax Filing' as activity_type,
                tfsh.status,
                tfsh.notes,
                tfsh.changed_at as activity_date,
                tfs.business_name
            FROM tax_filing_status_history tfsh
            LEFT JOIN user_accounts ua ON tfsh.changed_by = ua.id
            LEFT JOIN tax_filing_services tfs ON tfsh.service_id = tfs.id
            WHERE tfsh.changed_at BETWEEN :date_from AND :date_to";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY tfsh.changed_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $admin_activities = array_merge($admin_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Payment Approvals - Tax Filing
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Payment Approval - Tax Filing' as activity_type,
                tfp.amount,
                tfp.status,
                tfp.approved_at as activity_date,
                tfs.business_name
            FROM tax_filing_payments tfp
            LEFT JOIN user_accounts ua ON tfp.approved_by = ua.id
            LEFT JOIN tax_filing_services tfs ON tfp.service_id = tfs.id
            WHERE tfp.approved_at BETWEEN :date_from AND :date_to
            AND tfp.approved_by IS NOT NULL";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY tfp.approved_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $admin_activities = array_merge($admin_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        // Continue
    }
    
    // Payment Approvals - Payroll Processing
    $sql = "SELECT 
                ua.id as user_id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                'Payment Approval - Payroll' as activity_type,
                ppp.amount,
                ppp.status,
                ppp.approved_at as activity_date,
                pps.company_name as business_name
            FROM payroll_processing_payments ppp
            LEFT JOIN user_accounts ua ON ppp.approved_by = ua.id
            LEFT JOIN payroll_processing_services pps ON ppp.service_id = pps.id
            WHERE ppp.approved_at BETWEEN :date_from AND :date_to
            AND ppp.approved_by IS NOT NULL";
    
    $params = ['date_from' => $date_from, 'date_to' => $date_to . ' 23:59:59'];
    
    if (!empty($user_search)) {
        $sql .= " AND (ua.first_name LIKE :search1 OR ua.last_name LIKE :search2 OR ua.email LIKE :search3)";
        $params['search1'] = "%$user_search%";
        $params['search2'] = "%$user_search%";
        $params['search3'] = "%$user_search%";
    }
    
    $sql .= " ORDER BY ppp.approved_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $admin_activities = array_merge($admin_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        // Continue
    }
    
    // Sort by date
    usort($admin_activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    
    return $admin_activities;
}

// Function to get daily activity summary
function getDailyActivitySummary($pdo, $date_from, $date_to) {
    $daily_summary = [];
    
    $current_date = new DateTime($date_from);
    $end_date = new DateTime($date_to);
    
    while ($current_date <= $end_date) {
        $date = $current_date->format('Y-m-d');
        
        // Count logins (with error handling)
        $logins = 0;
        try {
            $sql = "SELECT COUNT(*) as count FROM user_sessions WHERE DATE(created_at) = :date";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['date' => $date]);
            $logins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $logins = 0;
        }
        
        // Count service requests
        $service_count = 0;
        $tables = [
            'tax_filing_services',
            'payroll_processing_services',
            'accounting_bookkeeping_services',
            'financial_statements_services',
            'compliance_consulting_services'
        ];
        
        foreach ($tables as $table) {
            try {
                $sql = "SELECT COUNT(*) as count FROM $table WHERE DATE(created_at) = :date";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['date' => $date]);
                $service_count += $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch (Exception $e) {
                continue;
            }
        }
        
        $daily_summary[] = [
            'date' => $date,
            'logins' => $logins,
            'service_requests' => $service_count
        ];
        
        $current_date->modify('+1 day');
    }
    
    return $daily_summary;
}

function paginateArray(array $items, string $pageParam, int $perPage = 10) {
    $totalItems = count($items);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $page = isset($_GET[$pageParam]) ? (int)$_GET[$pageParam] : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $paginatedItems = array_slice($items, $offset, $perPage);

    return [
        'data' => $paginatedItems,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $perPage,
    ];
}

function buildPaginationUrl(string $pageParam, int $page) {
    $params = $_GET;
    $params[$pageParam] = $page;
    return '?' . http_build_query($params);
}

// Function to get most active users
function getMostActiveUsers($pdo, $date_from, $date_to, $limit = 10) {
    $sql = "SELECT 
                ua.id, ua.first_name, ua.last_name, ua.email, ua.user_role,
                (SELECT COUNT(*) FROM tax_filing_services WHERE user_id = ua.id AND created_at BETWEEN :date_from1 AND :date_to1) +
                (SELECT COUNT(*) FROM payroll_processing_services WHERE user_id = ua.id AND created_at BETWEEN :date_from2 AND :date_to2) +
                (SELECT COUNT(*) FROM accounting_bookkeeping_services WHERE user_id = ua.id AND created_at BETWEEN :date_from3 AND :date_to3) +
                (SELECT COUNT(*) FROM financial_statements_services WHERE user_id = ua.id AND created_at BETWEEN :date_from4 AND :date_to4) +
                (SELECT COUNT(*) FROM compliance_consulting_services WHERE user_id = ua.id AND created_at BETWEEN :date_from5 AND :date_to5) 
                as service_count,
                NULL as last_activity
            FROM user_accounts ua
            GROUP BY ua.id
            HAVING service_count > 0
            ORDER BY service_count DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date_from1', $date_from);
    $stmt->bindValue(':date_to1', $date_to . ' 23:59:59');
    $stmt->bindValue(':date_from2', $date_from);
    $stmt->bindValue(':date_to2', $date_to . ' 23:59:59');
    $stmt->bindValue(':date_from3', $date_from);
    $stmt->bindValue(':date_to3', $date_to . ' 23:59:59');
    $stmt->bindValue(':date_from4', $date_from);
    $stmt->bindValue(':date_to4', $date_to . ' 23:59:59');
    $stmt->bindValue(':date_from5', $date_from);
    $stmt->bindValue(':date_to5', $date_to . ' 23:59:59');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get data based on activity type filter
$service_activity_full = [];
$document_activity_full = [];
$admin_activity_full = [];

$service_activity = [];
$document_activity = [];
$admin_activity = [];

$service_pagination = null;
$document_pagination = null;
$admin_pagination = null;

if ($activity_type_filter === 'all' || $activity_type_filter === 'services') {
    $service_activity_full = getServiceRequestActivity($pdo, $date_from, $date_to, $user_role_filter, $user_search);
    $service_pagination = paginateArray($service_activity_full, 'services_page', 10);
    $service_activity = $service_pagination['data'];
}

if ($activity_type_filter === 'all' || $activity_type_filter === 'documents') {
    $document_activity_full = getDocumentUploadActivity($pdo, $date_from, $date_to, $user_role_filter, $user_search);
    $document_pagination = paginateArray($document_activity_full, 'documents_page', 10);
    $document_activity = $document_pagination['data'];
}

if ($activity_type_filter === 'all' || $activity_type_filter === 'admin') {
    $admin_activity_full = getAdminActivity($pdo, $date_from, $date_to, $user_search);
    $admin_pagination = paginateArray($admin_activity_full, 'admin_page', 10);
    $admin_activity = $admin_pagination['data'];
}

$user_stats = getUserActivityStats($pdo, $date_from, $date_to);
$daily_activity = getDailyActivitySummary($pdo, $date_from, $date_to);
$most_active_users = getMostActiveUsers($pdo, $date_from, $date_to);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title><?php echo $pageTitle; ?> - TaxEase Admin</title>
  <style>
    .activity-card {
      transition: all 0.3s ease;
    }
    
    .activity-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .filter-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .activity-badge {
      min-width: 100px;
      text-align: center;
    }
    
    .timeline-item {
      padding: 15px;
      border-left: 3px solid #4154f1;
      margin-bottom: 15px;
      background: #f8f9fa;
      border-radius: 0 8px 8px 0;
    }
    
    .timeline-item:hover {
      background: #e9ecef;
    }
    
    .activity-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 10px;
    }
    
    .table-hover tbody tr:hover {
      background-color: #f5f5f5;
      cursor: pointer;
    }
    
    .stats-number {
      font-size: 2rem;
      font-weight: bold;
      color: #4154f1;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>User Activity Reports</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Reports & Analytics</li>
          <li class="breadcrumb-item active">User Activity</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        
        <!-- Filters Section -->
        <div class="col-lg-12">
          <div class="filter-section">
            <form method="GET" id="filterForm">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="date_from" class="form-label">Date From</label>
                  <input type="date" class="form-control" id="date_from" name="date_from" 
                         value="<?php echo htmlspecialchars($date_from); ?>" required>
                </div>
                <div class="col-md-3">
                  <label for="date_to" class="form-label">Date To</label>
                  <input type="date" class="form-control" id="date_to" name="date_to" 
                         value="<?php echo htmlspecialchars($date_to); ?>" required>
                </div>
                <div class="col-md-2">
                  <label for="user_role" class="form-label">User Role</label>
                  <select class="form-select" id="user_role" name="user_role">
                    <option value="all" <?php echo $user_role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="client" <?php echo $user_role_filter === 'client' ? 'selected' : ''; ?>>Clients</option>
                    <option value="administrator" <?php echo $user_role_filter === 'administrator' ? 'selected' : ''; ?>>Administrators</option>
                    <option value="administrative staff" <?php echo $user_role_filter === 'administrative staff' ? 'selected' : ''; ?>>Admin Staff</option>
                    <option value="senior account executive" <?php echo $user_role_filter === 'senior account executive' ? 'selected' : ''; ?>>Senior AE</option>
                    <option value="junior account executive" <?php echo $user_role_filter === 'junior account executive' ? 'selected' : ''; ?>>Junior AE</option>
                    <option value="owner" <?php echo $user_role_filter === 'owner' ? 'selected' : ''; ?>>Owner</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="activity_type" class="form-label">Activity Type</label>
                  <select class="form-select" id="activity_type" name="activity_type">
                    <option value="all" <?php echo $activity_type_filter === 'all' ? 'selected' : ''; ?>>All Activities</option>
                    <option value="services" <?php echo $activity_type_filter === 'services' ? 'selected' : ''; ?>>Service Requests</option>
                    <option value="documents" <?php echo $activity_type_filter === 'documents' ? 'selected' : ''; ?>>Document Uploads</option>
                    <option value="admin" <?php echo $activity_type_filter === 'admin' ? 'selected' : ''; ?>>Admin Actions</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="user_search" class="form-label">Search User</label>
                  <input type="text" class="form-control" id="user_search" name="user_search" 
                         placeholder="Name or email..." value="<?php echo htmlspecialchars($user_search); ?>">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Apply Filters
                  </button>
                  <a href="user-activity-reports.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                  </a>
                  <button type="button" class="btn btn-success" onclick="exportActivityToCSV()">
                    <i class="bi bi-file-earmark-excel"></i> Export to CSV
                  </button>
                  <button type="button" class="btn btn-info" onclick="printReport()">
                    <i class="bi bi-printer"></i> Print Report
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="col-lg-6 col-md-6">
          <div class="card activity-card">
            <div class="card-body">
              <h5 class="card-title">New Registrations</h5>
              <div class="d-flex align-items-center">
                <div class="activity-icon bg-info text-white">
                  <i class="bi bi-person-plus"></i>
                </div>
                <div class="ps-3">
                  <div class="stats-number"><?php echo number_format($user_stats['new_registrations']); ?></div>
                  <small class="text-muted">New users</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6 col-md-6">
          <div class="card activity-card">
            <div class="card-body">
              <h5 class="card-title">Service Requests</h5>
              <div class="d-flex align-items-center">
                <div class="activity-icon bg-warning text-white">
                  <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="ps-3">
                  <div class="stats-number"><?php echo number_format(count($service_activity_full)); ?></div>
                  <small class="text-muted">New requests</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Users by Role Distribution -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Users by Role</h5>
              <div id="userRoleChart" style="min-height: 350px;"></div>
              
              <script>
                document.addEventListener("DOMContentLoaded", () => {
                  new ApexCharts(document.querySelector("#userRoleChart"), {
                    series: <?php echo json_encode(array_column($user_stats['users_by_role'], 'count')); ?>,
                    chart: {
                      type: 'donut',
                      height: 350
                    },
                    labels: <?php echo json_encode(array_map(function($role) {
                        return ucwords($role['user_role']);
                    }, $user_stats['users_by_role'])); ?>,
                    colors: ['#4154f1', '#2eca6a', '#ff771d', '#bb0a1e', '#6f42c1', '#0dcaf0'],
                    legend: {
                      position: 'bottom'
                    },
                    responsive: [{
                      breakpoint: 480,
                      options: {
                        chart: {
                          width: 300
                        },
                        legend: {
                          position: 'bottom'
                        }
                      }
                    }]
                  }).render();
                });
              </script>
            </div>
          </div>
        </div>

        <!-- Daily Activity Chart -->
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Daily Activity Summary</h5>
              <div id="dailyActivityChart" style="min-height: 350px;"></div>
              
              <script>
                document.addEventListener("DOMContentLoaded", () => {
                  new ApexCharts(document.querySelector("#dailyActivityChart"), {
                    series: [{
                      name: 'Logins',
                      data: <?php echo json_encode(array_column($daily_activity, 'logins')); ?>
                    }, {
                      name: 'Service Requests',
                      data: <?php echo json_encode(array_column($daily_activity, 'service_requests')); ?>
                    }],
                    chart: {
                      type: 'area',
                      height: 350,
                      toolbar: {
                        show: true
                      }
                    },
                    dataLabels: {
                      enabled: false
                    },
                    stroke: {
                      curve: 'smooth',
                      width: 2
                    },
                    xaxis: {
                      categories: <?php echo json_encode(array_map(function($day) {
                          return date('M d', strtotime($day['date']));
                      }, $daily_activity)); ?>,
                      labels: {
                        rotate: -45
                      }
                    },
                    colors: ['#4154f1', '#2eca6a'],
                    fill: {
                      type: 'gradient',
                      gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.5,
                        opacityTo: 0.2,
                        stops: [0, 90, 100]
                      }
                    },
                    tooltip: {
                      shared: true,
                      intersect: false
                    }
                  }).render();
                });
              </script>
            </div>
          </div>
        </div>

        <!-- Most Active Users -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Most Active Users <span>| Top 10</span></h5>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Rank</th>
                      <th>User</th>
                      <th>Role</th>
                      <th>Service Requests</th>
                      <th>Last Activity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($most_active_users)): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted">No active users in selected period</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($most_active_users as $index => $user): ?>
                    <tr>
                      <td>
                        <?php if ($index == 0): ?>
                        <i class="bi bi-trophy-fill text-warning" style="font-size: 1.5rem;"></i>
                        <?php elseif ($index == 1): ?>
                        <i class="bi bi-trophy-fill text-secondary" style="font-size: 1.3rem;"></i>
                        <?php elseif ($index == 2): ?>
                        <i class="bi bi-trophy-fill" style="font-size: 1.1rem; color: #cd7f32;"></i>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                      </td>
                      <td>
                        <span class="badge bg-primary">
                          <?php echo ucwords($user['user_role']); ?>
                        </span>
                      </td>
                      <td><span class="badge bg-success"><?php echo number_format($user['service_count']); ?></span></td>
                      <td>
                        <?php if ($user['last_activity']): ?>
                        <small><?php echo date('M d, Y H:i', strtotime($user['last_activity'])); ?></small>
                        <?php else: ?>
                        <small class="text-muted">No recent activity</small>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($service_pagination && $service_pagination['total_pages'] > 1): ?>
              <nav class="mt-3">
                <ul class="pagination justify-content-center">
                  <?php for ($page = 1; $page <= $service_pagination['total_pages']; $page++): ?>
                  <li class="page-item <?php echo $page === $service_pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(buildPaginationUrl('services_page', $page)); ?>">
                      <?php echo $page; ?>
                    </a>
                  </li>
                  <?php endfor; ?>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Service Request Activity -->
        <?php if ($activity_type_filter === 'all' || $activity_type_filter === 'services'): ?>
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Service Request Activity <span>| <?php echo count($service_activity_full); ?> requests</span></h5>
              <div class="table-responsive">
                <table class="table table-striped table-hover" id="serviceActivityTable">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>User</th>
                      <th>Role</th>
                      <th>Service Type</th>
                      <th>Business/Company</th>
                      <th>Amount</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($service_activity)): ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted">No service activity found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($service_activity as $activity): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                      <td>
                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($activity['email']); ?></small>
                      </td>
                      <td>
                        <span class="badge bg-primary">
                          <?php echo ucwords($activity['user_role']); ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge bg-info">
                          <?php echo htmlspecialchars($activity['service_type']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($activity['business_name']); ?></td>
                      <td><strong>₱<?php echo number_format($activity['total_amount'], 2); ?></strong></td>
                      <td>
                        <?php 
                        $status_class = '';
                        switch($activity['service_status']) {
                            case 'completed': $status_class = 'bg-success'; break;
                            case 'active': $status_class = 'bg-info'; break;
                            case 'pending': $status_class = 'bg-warning'; break;
                            case 'cancelled': $status_class = 'bg-danger'; break;
                            default: $status_class = 'bg-secondary'; break;
                        }
                        ?>
                        <span class="badge <?php echo $status_class; ?>">
                          <?php echo ucwords(str_replace('_', ' ', $activity['service_status'])); ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($service_pagination && $service_pagination['total_pages'] > 1): ?>
              <nav class="mt-3">
                <ul class="pagination justify-content-center">
                  <?php for ($page = 1; $page <= $service_pagination['total_pages']; $page++): ?>
                  <li class="page-item <?php echo $page === $service_pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(buildPaginationUrl('services_page', $page)); ?>">
                      <?php echo $page; ?>
                    </a>
                  </li>
                  <?php endfor; ?>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Document Upload Activity -->
        <?php if ($activity_type_filter === 'all' || $activity_type_filter === 'documents'): ?>
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Document Upload Activity <span>| <?php echo count($document_activity_full); ?> uploads</span></h5>
              <div class="table-responsive">
                <table class="table table-striped table-hover" id="documentActivityTable">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>User</th>
                      <th>Role</th>
                      <th>Activity Type</th>
                      <th>Document Name</th>
                      <th>Document Type</th>
                      <th>Business</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($document_activity)): ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted">No document activity found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($document_activity as $activity): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?></td>
                      <td>
                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($activity['email']); ?></small>
                      </td>
                      <td>
                        <span class="badge bg-primary">
                          <?php echo ucwords($activity['user_role']); ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge bg-secondary">
                          <?php echo htmlspecialchars($activity['activity_type']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($activity['document_name']); ?></td>
                      <td><small><?php echo ucwords(str_replace('_', ' ', $activity['document_type'])); ?></small></td>
                      <td><?php echo htmlspecialchars($activity['business_name']); ?></td>
                      <td>
                        <?php 
                        $status_class = '';
                        switch($activity['status']) {
                            case 'approved': $status_class = 'bg-success'; break;
                            case 'pending': $status_class = 'bg-warning'; break;
                            case 'rejected': $status_class = 'bg-danger'; break;
                            case 'under_review': $status_class = 'bg-info'; break;
                            default: $status_class = 'bg-secondary'; break;
                        }
                        ?>
                        <span class="badge <?php echo $status_class; ?>">
                          <?php echo ucfirst($activity['status']); ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($document_pagination && $document_pagination['total_pages'] > 1): ?>
              <nav class="mt-3">
                <ul class="pagination justify-content-center">
                  <?php for ($page = 1; $page <= $document_pagination['total_pages']; $page++): ?>
                  <li class="page-item <?php echo $page === $document_pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(buildPaginationUrl('documents_page', $page)); ?>">
                      <?php echo $page; ?>
                    </a>
                  </li>
                  <?php endfor; ?>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Admin Activity -->
        <?php if ($activity_type_filter === 'all' || $activity_type_filter === 'admin'): ?>
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Admin Activity <span>| <?php echo count($admin_activity_full); ?> actions</span></h5>
              <div class="table-responsive">
                <table class="table table-striped table-hover" id="adminActivityTable">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Admin User</th>
                      <th>Role</th>
                      <th>Activity Type</th>
                      <th>Details</th>
                      <th>Business/Client</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($admin_activity)): ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted">No admin activity found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($admin_activity as $activity): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?></td>
                      <td>
                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($activity['email']); ?></small>
                      </td>
                      <td>
                        <span class="badge bg-danger">
                          <?php echo ucwords($activity['user_role']); ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge bg-warning">
                          <?php echo htmlspecialchars($activity['activity_type']); ?>
                        </span>
                      </td>
                      <td>
                        <?php if (isset($activity['status'])): ?>
                        <small>Status: <strong><?php echo ucfirst($activity['status']); ?></strong></small>
                        <?php endif; ?>
                        <?php if (isset($activity['amount'])): ?>
                        <br><small>Amount: <strong>₱<?php echo number_format($activity['amount'], 2); ?></strong></small>
                        <?php endif; ?>
                        <?php if (isset($activity['notes']) && $activity['notes']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($activity['notes'], 0, 50)); ?><?php echo strlen($activity['notes']) > 50 ? '...' : ''; ?></small>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($activity['business_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($admin_pagination && $admin_pagination['total_pages'] > 1): ?>
              <nav class="mt-3">
                <ul class="pagination justify-content-center">
                  <?php for ($page = 1; $page <= $admin_pagination['total_pages']; $page++): ?>
                  <li class="page-item <?php echo $page === $admin_pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(buildPaginationUrl('admin_page', $page)); ?>">
                      <?php echo $page; ?>
                    </a>
                  </li>
                  <?php endfor; ?>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Activity Timeline (Combined View) -->
        <?php if ($activity_type_filter === 'all'): ?>
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Recent Activity Timeline <span>| Last 20 Activities</span></h5>
              
              <?php 
              // Combine all activities
              $all_activities = [];
              
              foreach ($service_activity_full as $activity) {
                  $all_activities[] = [
                      'type' => 'Service Request',
                      'date' => $activity['created_at'],
                      'user' => $activity['first_name'] . ' ' . $activity['last_name'],
                      'role' => $activity['user_role'],
                      'description' => "Created " . $activity['service_type'] . " request for " . $activity['business_name'],
                      'icon' => 'bi-file-earmark-plus',
                      'color' => 'primary'
                  ];
              }
              
              foreach ($document_activity_full as $activity) {
                  $all_activities[] = [
                      'type' => 'Document Upload',
                      'date' => $activity['activity_date'],
                      'user' => $activity['first_name'] . ' ' . $activity['last_name'],
                      'role' => $activity['user_role'],
                      'description' => "Uploaded " . $activity['document_name'] . " (" . $activity['activity_type'] . ")",
                      'icon' => 'bi-cloud-upload',
                      'color' => 'success'
                  ];
              }
              
              foreach ($admin_activity_full as $activity) {
                  $all_activities[] = [
                      'type' => 'Admin Action',
                      'date' => $activity['activity_date'],
                      'user' => $activity['first_name'] . ' ' . $activity['last_name'],
                      'role' => $activity['user_role'],
                      'description' => $activity['activity_type'] . " - " . ($activity['business_name'] ?? 'N/A'),
                      'icon' => 'bi-shield-check',
                      'color' => 'danger'
                  ];
              }
              
              // Sort by date
              usort($all_activities, function($a, $b) {
                  return strtotime($b['date']) - strtotime($a['date']);
              });
              
              // Limit to 20
              $all_activities = array_slice($all_activities, 0, 20);
              ?>
              
              <div class="activity">
                <?php if (empty($all_activities)): ?>
                <div class="text-center py-4">
                  <i class="bi bi-inbox display-4 text-muted"></i>
                  <p class="text-muted">No activity found in selected period</p>
                </div>
                <?php else: ?>
                <?php foreach ($all_activities as $activity): ?>
                <div class="timeline-item">
                  <div class="d-flex align-items-start">
                    <div class="activity-icon bg-<?php echo $activity['color']; ?> text-white me-3">
                      <i class="<?php echo $activity['icon']; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 class="mb-1">
                            <span class="badge bg-<?php echo $activity['color']; ?> me-2"><?php echo $activity['type']; ?></span>
                            <strong><?php echo htmlspecialchars($activity['user']); ?></strong>
                            <span class="badge bg-secondary ms-2"><?php echo ucwords($activity['role']); ?></span>
                          </h6>
                          <p class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                        </div>
                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></small>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <script>
    // Export to CSV functionality
    function exportActivityToCSV() {
      let csvData = [];
      
      // Determine which table to export based on activity type
      const activityType = '<?php echo $activity_type_filter; ?>';
      let tableId = 'serviceActivityTable'; // Default
      
      if (activityType === 'documents') {
        tableId = 'documentActivityTable';
      } else if (activityType === 'admin') {
        tableId = 'adminActivityTable';
      }
      
      const table = document.getElementById(tableId);
      
      if (table) {
        // Get headers
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => {
          headers.push(th.textContent.trim());
        });
        csvData.push(headers.join(','));
        
        // Get data rows
        table.querySelectorAll('tbody tr').forEach(tr => {
          const row = [];
          tr.querySelectorAll('td').forEach(td => {
            let text = td.textContent.trim();
            text = text.replace(/\s+/g, ' ');
            if (text.includes(',') || text.includes('"')) {
              text = '"' + text.replace(/"/g, '""') + '"';
            }
            row.push(text);
          });
          if (row.length > 0 && row[0] !== 'No') {
            csvData.push(row.join(','));
          }
        });
      } else {
        // Export all activities from timeline
        csvData.push('Date,User,Role,Activity Type,Description');
        <?php foreach ($all_activities ?? [] as $activity): ?>
        csvData.push([
          '<?php echo date('Y-m-d H:i:s', strtotime($activity['date'])); ?>',
          '"<?php echo addslashes($activity['user']); ?>"',
          '"<?php echo addslashes($activity['role']); ?>"',
          '"<?php echo addslashes($activity['type']); ?>"',
          '"<?php echo addslashes($activity['description']); ?>"'
        ].join(','));
        <?php endforeach; ?>
      }
      
      // Create download
      const csvContent = csvData.join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.setAttribute('hidden', '');
      a.setAttribute('href', url);
      a.setAttribute('download', 'user_activity_report_<?php echo date('Y-m-d'); ?>.csv');
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      
      showNotification('Activity report exported successfully!', 'success');
    }
    
    // Print report
    function printReport() {
      window.print();
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
      notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
      notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 5000);
    }
    
    // Quick date range selectors
    document.addEventListener('DOMContentLoaded', function() {
      const dateFromInput = document.getElementById('date_from');
      const dateToInput = document.getElementById('date_to');
      
      window.setToday = function() {
        const today = new Date().toISOString().split('T')[0];
        dateFromInput.value = today;
        dateToInput.value = today;
      };
      
      window.setThisWeek = function() {
        const today = new Date();
        const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
        const lastDay = new Date();
        dateFromInput.value = firstDay.toISOString().split('T')[0];
        dateToInput.value = lastDay.toISOString().split('T')[0];
      };
      
      window.setThisMonth = function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date();
        dateFromInput.value = firstDay.toISOString().split('T')[0];
        dateToInput.value = lastDay.toISOString().split('T')[0];
      };
      
      window.setLastMonth = function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
        dateFromInput.value = firstDay.toISOString().split('T')[0];
        dateToInput.value = lastDay.toISOString().split('T')[0];
      };
      
      window.setThisYear = function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), 0, 1);
        const lastDay = new Date();
        dateFromInput.value = firstDay.toISOString().split('T')[0];
        dateToInput.value = lastDay.toISOString().split('T')[0];
      };
      
      // Add quick date buttons
      const filterForm = document.getElementById('filterForm');
      const firstRow = filterForm.querySelector('.row');
      const quickDateRow = document.createElement('div');
      quickDateRow.className = 'row g-3';
      quickDateRow.innerHTML = `
        <div class="col-12 mb-2">
          <small class="text-muted">Quick Select:</small>
          <div class="btn-group btn-group-sm ms-2" role="group">
            <button type="button" class="btn btn-outline-secondary" onclick="setToday()">Today</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setThisWeek()">This Week</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setThisMonth()">This Month</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setLastMonth()">Last Month</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setThisYear()">This Year</button>
          </div>
        </div>
      `;
      firstRow.parentNode.insertBefore(quickDateRow, firstRow);
    });
    
    // Print styles
    const style = document.createElement('style');
    style.textContent = `
      @media print {
        .sidebar, .header, .pagetitle nav, .filter-section, 
        .btn, .card .filter {
          display: none !important;
        }
        
        .main {
          margin-left: 0 !important;
        }
        
        .card {
          page-break-inside: avoid;
          box-shadow: none;
          border: 1px solid #ddd;
        }
        
        body {
          background: white;
        }
        
        .pagetitle h1 {
          font-size: 24px;
          margin-bottom: 20px;
        }
      }
    `;
    document.head.appendChild(style);
  </script>

</body>

</html>

