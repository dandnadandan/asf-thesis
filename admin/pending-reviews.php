<?php
/**
 * Admin Pending Reviews
 * Review and approve pending documents with email notifications to owners
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require admin role
requireRole(['admin', 'administrator'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'Pending Reviews';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Success/Error messages
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_document':
                $result = approveDocument($pdo, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
                break;
            case 'reject_document':
                $result = rejectDocument($pdo, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
                break;
            case 'request_revision':
                $result = requestDocumentRevision($pdo, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
                break;
            case 'mark_under_review':
                $result = markUnderReview($pdo, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Handle GET parameters for filtering
$priority_filter = $_GET['priority'] ?? 'all';
$service_type_filter = $_GET['service_type'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'oldest';

$serviceDocumentConfigs = [
    'tax_filing' => [
        'document_table' => 'tax_filing_documents',
        'service_table' => 'tax_filing_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'service_type_field' => 'service_type',
        'default_service_type' => 'tax_filing',
        'business_name_field' => 'business_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'review_notes',
        'reviewed_by_column' => 'reviewed_by',
        'reviewed_at_column' => 'reviewed_at',
        'uploaded_column' => 'uploaded_at',
        'pending_statuses' => ['pending', 'under_review'],
        'revision_status' => 'needs_revision',
        'under_review_status' => 'under_review',
    ],
    'financial_statements' => [
        'document_table' => 'financial_statements_documents',
        'service_table' => 'financial_statements_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'default_service_type' => 'financial_statements',
        'business_name_field' => 'business_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'review_notes',
        'reviewed_by_column' => 'reviewed_by',
        'reviewed_at_column' => 'reviewed_at',
        'uploaded_column' => 'uploaded_at',
        'pending_statuses' => ['pending', 'under_review'],
        'revision_status' => 'needs_revision',
        'under_review_status' => 'under_review',
    ],
    'accounting_bookkeeping' => [
        'document_table' => 'accounting_documents',
        'service_table' => 'accounting_bookkeeping_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'service_type_field' => 'service_type',
        'default_service_type' => 'accounting_bookkeeping',
        'business_name_field' => 'business_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'review_notes',
        'reviewed_by_column' => 'reviewed_by',
        'reviewed_at_column' => 'reviewed_at',
        'uploaded_column' => 'uploaded_at',
        'pending_statuses' => ['pending', 'under_review'],
        'revision_status' => 'needs_revision',
        'under_review_status' => 'under_review',
    ],
    'business_registration' => [
        'document_table' => 'business_registration_documents',
        'service_table' => 'business_registration_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'default_service_type' => 'business_registration',
        'business_name_field' => 'company_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'review_notes',
        'reviewed_by_column' => 'reviewed_by',
        'reviewed_at_column' => 'reviewed_at',
        'uploaded_column' => 'uploaded_at',
        'pending_statuses' => ['pending', 'under_review'],
        'revision_status' => 'needs_revision',
        'under_review_status' => 'under_review',
    ],
    'compliance_consulting' => [
        'document_table' => 'compliance_consulting_documents',
        'service_table' => 'compliance_consulting_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'default_service_type' => 'compliance_consulting',
        'business_name_field' => 'company_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'review_notes',
        'reviewed_by_column' => 'reviewed_by',
        'reviewed_at_column' => 'reviewed_at',
        'uploaded_column' => 'uploaded_at',
        'pending_statuses' => ['pending', 'under_review'],
        'revision_status' => 'needs_revision',
        'under_review_status' => 'under_review',
    ],
    'payroll_processing' => [
        'document_table' => 'payroll_processing_documents',
        'service_table' => 'payroll_processing_services',
        'document_alias' => 'doc',
        'service_alias' => 'svc',
        'default_service_type' => 'payroll_processing',
        'business_name_field' => 'company_name',
        'total_amount_field' => 'total_amount',
        'document_name_field' => 'document_name',
        'document_type_field' => 'document_type',
        'file_path_field' => 'file_path',
        'status_field' => 'status',
        'review_notes_column' => 'notes',
        'uploaded_column' => 'uploaded_date',
        'pending_statuses' => ['pending'],
        'revision_status' => null,
        'under_review_status' => null,
    ],
];

$serviceTypeOptions = [];
foreach ($serviceDocumentConfigs as $key => $config) {
    $serviceTypeOptions[$key] = ucwords(str_replace('_', ' ', $key));
}

if ($service_type_filter !== 'all' && !isset($serviceDocumentConfigs[$service_type_filter])) {
    $service_type_filter = 'all';
}

function getServiceDocumentConfig($serviceKey) {
    global $serviceDocumentConfigs;
    return $serviceDocumentConfigs[$serviceKey] ?? null;
}

function normalizeServiceType($rawType, $defaultType) {
    $raw = strtolower(trim((string)$rawType));
    if ($raw === '' || $raw === 'both') {
        $raw = $defaultType;
    }
    $normalized = str_replace([' ', '-', '/'], '_', $raw);
    return strtolower($normalized);
}

// Function to get all owner users
function getOwnerUsers($pdo) {
    $sql = "SELECT id, email, first_name, last_name 
            FROM user_accounts 
            WHERE user_role = 'owner' 
            AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get pending documents with filters
function getPendingDocuments($pdo, $priority_filter, $service_type_filter, $sort_by) {
    global $serviceDocumentConfigs;

    $documents = [];

    foreach ($serviceDocumentConfigs as $serviceKey => $config) {
        if ($service_type_filter !== 'all' && $service_type_filter !== $serviceKey) {
            continue;
        }

        try {
            $pdo->query("SELECT 1 FROM {$config['document_table']} LIMIT 1");
        } catch (Exception $e) {
            continue;
        }

        $docAlias = $config['document_alias'] ?? 'doc';
        $svcAlias = $config['service_alias'] ?? 'svc';

        $baseUploadedField = "{$docAlias}." . ($config['uploaded_column'] ?? 'uploaded_at');
        if (!empty($config['uploaded_fallback'])) {
            $baseUploadedField = "COALESCE({$baseUploadedField}, {$docAlias}.{$config['uploaded_fallback']})";
        }
        $uploadedExpr = "COALESCE({$baseUploadedField}, NOW())";
        $daysPendingExpr = "DATEDIFF(NOW(), {$uploadedExpr})";

        $serviceTypeExpr = isset($config['service_type_field'])
            ? "{$svcAlias}.{$config['service_type_field']}"
            : "'" . ($config['default_service_type'] ?? $serviceKey) . "'";

        $businessField = $config['business_name_field'] ?? null;
        $businessNameExpr = $businessField ? "COALESCE({$svcAlias}.{$businessField}, '')" : "''";

        $totalAmountField = $config['total_amount_field'] ?? null;
        $totalAmountExpr = $totalAmountField ? "COALESCE({$svcAlias}.{$totalAmountField}, 0)" : "0";

        $documentNameField = "{$docAlias}." . ($config['document_name_field'] ?? 'document_name');
        $documentTypeField = "{$docAlias}." . ($config['document_type_field'] ?? 'document_type');
        $filePathField = "{$docAlias}." . ($config['file_path_field'] ?? 'file_path');
        $statusField = "{$docAlias}." . ($config['status_field'] ?? 'status');

        $reviewNotesSelect = !empty($config['review_notes_column'])
            ? "{$docAlias}.{$config['review_notes_column']}"
            : "NULL";
        $reviewedBySelect = !empty($config['reviewed_by_column'])
            ? "{$docAlias}.{$config['reviewed_by_column']}"
            : "NULL";
        $reviewedAtSelect = !empty($config['reviewed_at_column'])
            ? "{$docAlias}.{$config['reviewed_at_column']}"
            : "NULL";

        $sql = "
            SELECT
                {$docAlias}.id,
                {$docAlias}.service_id,
                {$documentNameField} AS document_name,
                {$documentTypeField} AS document_type,
                {$filePathField} AS file_path,
                {$statusField} AS status,
                {$reviewNotesSelect} AS review_notes,
                {$reviewedBySelect} AS reviewed_by,
                {$reviewedAtSelect} AS reviewed_at,
                {$serviceTypeExpr} AS raw_service_type,
                {$businessNameExpr} AS business_name,
                {$totalAmountExpr} AS total_amount,
                ua.first_name AS first_name,
                ua.last_name AS last_name,
                ua.email AS email,
                ua.first_name AS client_first_name,
                ua.last_name AS client_last_name,
                {$uploadedExpr} AS uploaded_at,
                {$daysPendingExpr} AS days_pending
            FROM {$config['document_table']} {$docAlias}
            LEFT JOIN {$config['service_table']} {$svcAlias} ON {$docAlias}.service_id = {$svcAlias}.id
            LEFT JOIN user_accounts ua ON {$svcAlias}.user_id = ua.id
            WHERE 1=1
        ";

        $params = [];
        $pendingStatuses = $config['pending_statuses'] ?? ['pending'];
        if (!empty($pendingStatuses)) {
            $statusPlaceholders = [];
            foreach ($pendingStatuses as $idx => $statusValue) {
                $paramName = ":{$serviceKey}_status_{$idx}";
                $statusPlaceholders[] = $paramName;
                $params[$paramName] = $statusValue;
            }
            $sql .= " AND {$statusField} IN (" . implode(', ', $statusPlaceholders) . ")";
        }

        if ($priority_filter === 'urgent') {
            $sql .= " AND {$daysPendingExpr} > 7";
        } elseif ($priority_filter === 'critical') {
            $sql .= " AND {$daysPendingExpr} > 14";
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            continue;
        }

        foreach ($results as $row) {
            $normalizedType = normalizeServiceType($row['raw_service_type'] ?? '', $config['default_service_type'] ?? $serviceKey);
            $row['service_type'] = $normalizedType;
            $row['service_key'] = $serviceKey;
            $row['display_service_type'] = ucwords(str_replace('_', ' ', $normalizedType));
            $row['supports_under_review'] = !empty($config['under_review_status']);
            $row['supports_revision'] = !empty($config['revision_status']);
            if (!isset($row['days_pending']) || $row['days_pending'] === null) {
                $row['days_pending'] = 0;
            } else {
                $row['days_pending'] = (int)$row['days_pending'];
            }
            if (empty($row['uploaded_at'])) {
                $row['uploaded_at'] = date('Y-m-d H:i:s');
            }
            $row['total_amount'] = isset($row['total_amount']) ? (float)$row['total_amount'] : 0.0;
            unset($row['raw_service_type']);
            $documents[] = $row;
        }
    }

    usort($documents, function($a, $b) use ($sort_by) {
        switch ($sort_by) {
            case 'newest':
                return strtotime($b['uploaded_at']) <=> strtotime($a['uploaded_at']);
            case 'client':
                $nameA = strtolower(trim(($a['last_name'] ?? '') . ' ' . ($a['first_name'] ?? '')));
                $nameB = strtolower(trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? '')));
                return $nameA <=> $nameB;
            case 'oldest':
            default:
                return strtotime($a['uploaded_at']) <=> strtotime($b['uploaded_at']);
        }
    });

    return $documents;
}

function getDocumentDetailsById($pdo, $service_key, $document_id) {
    $config = getServiceDocumentConfig($service_key);
    if (!$config) {
        return null;
    }

    $docAlias = $config['document_alias'] ?? 'doc';
    $svcAlias = $config['service_alias'] ?? 'svc';

    $baseUploadedField = "{$docAlias}." . ($config['uploaded_column'] ?? 'uploaded_at');
    if (!empty($config['uploaded_fallback'])) {
        $baseUploadedField = "COALESCE({$baseUploadedField}, {$docAlias}.{$config['uploaded_fallback']})";
    }
    $uploadedExpr = "COALESCE({$baseUploadedField}, NOW())";
    $daysPendingExpr = "DATEDIFF(NOW(), {$uploadedExpr})";

    $serviceTypeExpr = isset($config['service_type_field'])
        ? "{$svcAlias}.{$config['service_type_field']}"
        : "'" . ($config['default_service_type'] ?? $service_key) . "'";

    $businessField = $config['business_name_field'] ?? null;
    $businessNameExpr = $businessField ? "COALESCE({$svcAlias}.{$businessField}, '')" : "''";

    $totalAmountField = $config['total_amount_field'] ?? null;
    $totalAmountExpr = $totalAmountField ? "COALESCE({$svcAlias}.{$totalAmountField}, 0)" : "0";

    $documentNameField = "{$docAlias}." . ($config['document_name_field'] ?? 'document_name');
    $documentTypeField = "{$docAlias}." . ($config['document_type_field'] ?? 'document_type');
    $filePathField = "{$docAlias}." . ($config['file_path_field'] ?? 'file_path');
    $statusField = "{$docAlias}." . ($config['status_field'] ?? 'status');

    $reviewNotesSelect = !empty($config['review_notes_column'])
        ? "{$docAlias}.{$config['review_notes_column']}"
        : "NULL";
    $reviewedBySelect = !empty($config['reviewed_by_column'])
        ? "{$docAlias}.{$config['reviewed_by_column']}"
        : "NULL";
    $reviewedAtSelect = !empty($config['reviewed_at_column'])
        ? "{$docAlias}.{$config['reviewed_at_column']}"
        : "NULL";

    $sql = "
        SELECT
            {$docAlias}.id,
            {$docAlias}.service_id,
            {$documentNameField} AS document_name,
            {$documentTypeField} AS document_type,
            {$filePathField} AS file_path,
            {$statusField} AS status,
            {$reviewNotesSelect} AS review_notes,
            {$reviewedBySelect} AS reviewed_by,
            {$reviewedAtSelect} AS reviewed_at,
            {$serviceTypeExpr} AS raw_service_type,
            {$businessNameExpr} AS business_name,
            {$totalAmountExpr} AS total_amount,
            ua.first_name AS client_first_name,
            ua.last_name AS client_last_name,
            ua.email AS client_email,
            {$uploadedExpr} AS uploaded_at,
            {$daysPendingExpr} AS days_pending
        FROM {$config['document_table']} {$docAlias}
        LEFT JOIN {$config['service_table']} {$svcAlias} ON {$docAlias}.service_id = {$svcAlias}.id
        LEFT JOIN user_accounts ua ON {$svcAlias}.user_id = ua.id
        WHERE {$docAlias}.id = :document_id
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['document_id' => $document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }

    if (!$document) {
        return null;
    }

    $normalizedType = normalizeServiceType($document['raw_service_type'] ?? '', $config['default_service_type'] ?? $service_key);
    $document['service_type'] = $normalizedType;
    $document['display_service_type'] = ucwords(str_replace('_', ' ', $normalizedType));
    $document['service_key'] = $service_key;
    $document['total_amount'] = isset($document['total_amount']) ? (float)$document['total_amount'] : 0.0;
    $document['days_pending'] = isset($document['days_pending']) ? (int)$document['days_pending'] : 0;
    unset($document['raw_service_type']);

    return $document;
}

// Function to send email notification
function sendEmailNotification($to_email, $to_name, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings from email_config.php
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // SSL options for compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, REPLY_TO_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to approve document
function approveDocument($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $notes = $data['notes'] ?? '';
    $service_key = $data['service_key'] ?? '';
    $notify_owner = isset($data['notify_owner']) && $data['notify_owner'] == '1';

    if (empty($service_key) || empty($document_id)) {
        return ['success' => false, 'message' => 'Missing document reference.'];
    }

    $config = getServiceDocumentConfig($service_key);
    if (!$config) {
        return ['success' => false, 'message' => 'Unsupported service type.'];
    }

    try {
        $document = getDocumentDetailsById($pdo, $service_key, $document_id);
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $statusColumn = $config['status_field'] ?? 'status';

        $sql = "UPDATE {$config['document_table']} SET {$statusColumn} = :status";
        $params = [
            'status' => 'approved',
            'document_id' => $document_id,
        ];

        if (!empty($config['review_notes_column'])) {
            $sql .= ", {$config['review_notes_column']} = :notes";
            $params['notes'] = trim($notes) !== '' ? $notes : null;
        }

        if (!empty($config['reviewed_by_column'])) {
            $sql .= ", {$config['reviewed_by_column']} = :admin_id";
            $params['admin_id'] = $currentUser['id'];
        }

        if (!empty($config['reviewed_at_column'])) {
            $sql .= ", {$config['reviewed_at_column']} = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE id = :document_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($notify_owner) {
            $owners = getOwnerUsers($pdo);
            $admin_name = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
            $serviceLabel = ucwords(str_replace('_', ' ', $document['service_type'] ?? $service_key));

            foreach ($owners as $owner) {
                $subject = "Document Approved - " . ($document['document_name'] ?? 'Document');
                $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
                            .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
                            .label { font-weight: bold; color: #555; }
                            .footer { background: #343a40; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; margin-top: 20px; }
                            .status-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>✓ Document Approved</h2>
                            </div>
                            <div class='content'>
                                <p>Hello {$owner['first_name']},</p>
                                <p>A document has been approved by <strong>{$admin_name}</strong>.</p>
                                
                                <div class='info-box'>
                                    <p><span class='label'>Service:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Document:</span> {$document['document_name']}</p>
                                    <p><span class='label'>Document Type:</span> {$document['document_type']}</p>
                                    <p><span class='label'>Status:</span> <span class='status-badge'>Approved</span></p>
                                    <p><span class='label'>Client:</span> {$document['client_first_name']} {$document['client_last_name']}</p>
                                    <p><span class='label'>Business:</span> {$document['business_name']}</p>
                                    <p><span class='label'>Service Type:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Reviewed:</span> " . date('F d, Y h:i A') . "</p>
                                </div>
                                
                                " . (!empty($notes) ? "<div class='info-box'><p><span class='label'>Review Notes:</span></p><p>{$notes}</p></div>" : "") . "
                                
                                <p>You can view the full document details in your dashboard.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " TaxEase. All rights reserved.</p>
                                <p><small>This is an automated notification. Please do not reply to this email.</small></p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                sendEmailNotification($owner['email'], $owner['first_name'] . ' ' . $owner['last_name'], $subject, $body);
            }
        }
        
        return ['success' => true, 'message' => 'Document approved successfully!' . ($notify_owner ? ' Owners have been notified via email.' : '')];

    } catch (Exception $e) {
        error_log("Error approving document: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while approving the document.'];
    }
}

// Function to reject document
function rejectDocument($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $rejection_reason = $data['rejection_reason'] ?? '';
    $service_key = $data['service_key'] ?? '';
    $notify_owner = isset($data['notify_owner']) && $data['notify_owner'] == '1';

    if (empty($service_key) || empty($document_id)) {
        return ['success' => false, 'message' => 'Missing document reference.'];
    }

    $config = getServiceDocumentConfig($service_key);
    if (!$config) {
        return ['success' => false, 'message' => 'Unsupported service type.'];
    }
    
    try {
        $document = getDocumentDetailsById($pdo, $service_key, $document_id);
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $statusColumn = $config['status_field'] ?? 'status';

        $sql = "UPDATE {$config['document_table']} SET {$statusColumn} = :status";
        $params = [
            'status' => 'rejected',
            'document_id' => $document_id,
        ];

        if (!empty($config['review_notes_column'])) {
            $sql .= ", {$config['review_notes_column']} = :notes";
            $params['notes'] = trim($rejection_reason) !== '' ? $rejection_reason : null;
        }

        if (!empty($config['reviewed_by_column'])) {
            $sql .= ", {$config['reviewed_by_column']} = :admin_id";
            $params['admin_id'] = $currentUser['id'];
        }

        if (!empty($config['reviewed_at_column'])) {
            $sql .= ", {$config['reviewed_at_column']} = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE id = :document_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($notify_owner) {
            $owners = getOwnerUsers($pdo);
            $admin_name = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
            $serviceLabel = ucwords(str_replace('_', ' ', $document['service_type'] ?? $service_key));

            foreach ($owners as $owner) {
                $subject = "Document Rejected - " . ($document['document_name'] ?? 'Document');
                $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
                            .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
                            .label { font-weight: bold; color: #555; }
                            .footer { background: #343a40; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; margin-top: 20px; }
                            .status-badge { background: #dc3545; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; }
                            .reason-box { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>✗ Document Rejected</h2>
                            </div>
                            <div class='content'>
                                <p>Hello {$owner['first_name']},</p>
                                <p>A document has been rejected by <strong>{$admin_name}</strong>.</p>
                                
                                <div class='info-box'>
                                    <p><span class='label'>Service:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Document:</span> {$document['document_name']}</p>
                                    <p><span class='label'>Document Type:</span> {$document['document_type']}</p>
                                    <p><span class='label'>Status:</span> <span class='status-badge'>Rejected</span></p>
                                    <p><span class='label'>Client:</span> {$document['client_first_name']} {$document['client_last_name']}</p>
                                    <p><span class='label'>Business:</span> {$document['business_name']}</p>
                                    <p><span class='label'>Service Type:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Reviewed:</span> " . date('F d, Y h:i A') . "</p>
                                </div>
                                
                                <div class='reason-box'>
                                    <p><span class='label'>Rejection Reason:</span></p>
                                    <p>{$rejection_reason}</p>
                                </div>
                                
                                <p>Please review this rejection in your dashboard.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " TaxEase. All rights reserved.</p>
                                <p><small>This is an automated notification. Please do not reply to this email.</small></p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                sendEmailNotification($owner['email'], $owner['first_name'] . ' ' . $owner['last_name'], $subject, $body);
            }
        }
        
        return ['success' => true, 'message' => 'Document rejected.' . ($notify_owner ? ' Owners have been notified via email.' : '')];
        
    } catch (Exception $e) {
        error_log("Error rejecting document: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while rejecting the document.'];
    }
}

// Function to request document revision
function requestDocumentRevision($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $revision_notes = $data['revision_notes'] ?? '';
    $service_key = $data['service_key'] ?? '';
    $notify_owner = isset($data['notify_owner']) && $data['notify_owner'] == '1';

    if (empty($service_key) || empty($document_id)) {
        return ['success' => false, 'message' => 'Missing document reference.'];
    }

    $config = getServiceDocumentConfig($service_key);
    if (!$config) {
        return ['success' => false, 'message' => 'Unsupported service type.'];
    }

    if (empty($config['revision_status'])) {
        return ['success' => false, 'message' => 'Document revisions are not supported for this service.'];
    }

    if (trim($revision_notes) === '') {
        return ['success' => false, 'message' => 'Revision notes are required.'];
    }
    
    try {
        $document = getDocumentDetailsById($pdo, $service_key, $document_id);
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $statusColumn = $config['status_field'] ?? 'status';

        $sql = "UPDATE {$config['document_table']} SET {$statusColumn} = :status";
        $params = [
            'status' => $config['revision_status'],
            'document_id' => $document_id,
        ];

        if (!empty($config['review_notes_column'])) {
            $sql .= ", {$config['review_notes_column']} = :notes";
            $params['notes'] = $revision_notes;
        }

        if (!empty($config['reviewed_by_column'])) {
            $sql .= ", {$config['reviewed_by_column']} = :admin_id";
            $params['admin_id'] = $currentUser['id'];
        }

        if (!empty($config['reviewed_at_column'])) {
            $sql .= ", {$config['reviewed_at_column']} = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE id = :document_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($notify_owner) {
            $owners = getOwnerUsers($pdo);
            $admin_name = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
            $serviceLabel = ucwords(str_replace('_', ' ', $document['service_type'] ?? $service_key));

            foreach ($owners as $owner) {
                $subject = "Document Revision Requested - " . ($document['document_name'] ?? 'Document');
                $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
                            .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
                            .label { font-weight: bold; color: #555; }
                            .footer { background: #343a40; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; margin-top: 20px; }
                            .status-badge { background: #17a2b8; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; }
                            .revision-box { background: #e7f3ff; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>↻ Revision Requested</h2>
                            </div>
                            <div class='content'>
                                <p>Hello {$owner['first_name']},</p>
                                <p>A document revision has been requested by <strong>{$admin_name}</strong>.</p>
                                
                                <div class='info-box'>
                                    <p><span class='label'>Service:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Document:</span> {$document['document_name']}</p>
                                    <p><span class='label'>Document Type:</span> {$document['document_type']}</p>
                                    <p><span class='label'>Status:</span> <span class='status-badge'>Needs Revision</span></p>
                                    <p><span class='label'>Client:</span> {$document['client_first_name']} {$document['client_last_name']}</p>
                                    <p><span class='label'>Business:</span> {$document['business_name']}</p>
                                    <p><span class='label'>Service Type:</span> {$serviceLabel}</p>
                                    <p><span class='label'>Reviewed:</span> " . date('F d, Y h:i A') . "</p>
                                </div>
                                
                                <div class='revision-box'>
                                    <p><span class='label'>Revision Notes:</span></p>
                                    <p>{$revision_notes}</p>
                                </div>
                                
                                <p>The client has been notified to submit a revised version.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " TaxEase. All rights reserved.</p>
                                <p><small>This is an automated notification. Please do not reply to this email.</small></p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                sendEmailNotification($owner['email'], $owner['first_name'] . ' ' . $owner['last_name'], $subject, $body);
            }
        }
        
        return ['success' => true, 'message' => 'Revision requested.' . ($notify_owner ? ' Owners have been notified via email.' : '')];
        
    } catch (Exception $e) {
        error_log("Error requesting revision: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while requesting revision.'];
    }
}

// Function to mark document as under review
function markUnderReview($pdo, $data) {
    $currentUser = getCurrentUser();
    $document_id = $data['document_id'];
    $service_key = $data['service_key'] ?? '';

    if (empty($service_key) || empty($document_id)) {
        return ['success' => false, 'message' => 'Missing document reference.'];
    }

    $config = getServiceDocumentConfig($service_key);
    if (!$config) {
        return ['success' => false, 'message' => 'Unsupported service type.'];
    }

    if (empty($config['under_review_status'])) {
        return ['success' => false, 'message' => 'Under review status is not supported for this service.'];
    }
    
    try {
        $document = getDocumentDetailsById($pdo, $service_key, $document_id);
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $statusColumn = $config['status_field'] ?? 'status';

        $sql = "UPDATE {$config['document_table']} SET {$statusColumn} = :status";
        $params = [
            'status' => $config['under_review_status'],
            'document_id' => $document_id,
        ];

        if (!empty($config['reviewed_by_column'])) {
            $sql .= ", {$config['reviewed_by_column']} = :admin_id";
            $params['admin_id'] = $currentUser['id'];
        }

        if (!empty($config['reviewed_at_column'])) {
            $sql .= ", {$config['reviewed_at_column']} = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE id = :document_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'Document marked as under review.'];
        
    } catch (Exception $e) {
        error_log("Error marking document under review: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while updating the document.'];
    }
}

// Get data
$pendingDocuments = getPendingDocuments($pdo, $priority_filter, $service_type_filter, $sort_by);

// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-warning';
        case 'under_review': return 'bg-primary';
        default: return 'bg-secondary';
    }
}

function getPriorityClass($days_pending) {
    if ($days_pending > 14) return 'table-danger';
    if ($days_pending > 7) return 'table-warning';
    return '';
}

function getPriorityBadge($days_pending) {
    if ($days_pending > 14) return '<span class="badge bg-danger">Critical</span>';
    if ($days_pending > 7) return '<span class="badge bg-warning">Urgent</span>';
    return '<span class="badge bg-info">Normal</span>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title><?php echo $pageTitle; ?> - TaxEase Admin</title>
  <style>
    .action-btn-group .btn {
      margin: 2px;
    }
    .priority-indicator {
      width: 4px;
      height: 100%;
      position: absolute;
      left: 0;
      top: 0;
    }
    .priority-critical { background: #dc3545; }
    .priority-urgent { background: #ffc107; }
    .priority-normal { background: #17a2b8; }
    tr { position: relative; }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Pending Reviews</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Document Management</li>
          <li class="breadcrumb-item active">Pending Reviews</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <!-- Success/Error Messages -->
    <?php if (!empty($message)): ?>
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Quick Stats and Actions -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Quick Statistics</h5>
              <div class="row">
                <div class="col-md-3">
                  <div class="text-center">
                    <h3 class="text-primary"><?php echo count($pendingDocuments); ?></h3>
                    <p class="text-muted mb-0">Total Pending</p>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h3 class="text-danger"><?php echo count(array_filter($pendingDocuments, function($d) { return $d['days_pending'] > 14; })); ?></h3>
                    <p class="text-muted mb-0">Critical (>14d)</p>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h3 class="text-warning"><?php echo count(array_filter($pendingDocuments, function($d) { return $d['days_pending'] > 7 && $d['days_pending'] <= 14; })); ?></h3>
                    <p class="text-muted mb-0">Urgent (>7d)</p>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h3 class="text-info"><?php echo count(array_filter($pendingDocuments, function($d) { return $d['days_pending'] <= 7; })); ?></h3>
                    <p class="text-muted mb-0">Normal (≤7d)</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <form method="GET" class="row g-3 align-items-end mt-2">
                <div class="col-md-3">
                  <label for="priority" class="form-label">Priority</label>
                  <select name="priority" id="priority" class="form-select">
                    <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority Levels</option>
                    <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical (>14 days)</option>
                    <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent (>7 days)</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="service_type" class="form-label">Service Type</label>
                  <select name="service_type" id="service_type" class="form-select">
                    <option value="all" <?php echo $service_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($serviceTypeOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $service_type_filter === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="sort_by" class="form-label">Sort By</label>
                  <select name="sort_by" id="sort_by" class="form-select">
                    <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="client" <?php echo $sort_by === 'client' ? 'selected' : ''; ?>>Client Name</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <button type="submit" class="btn btn-primary">Apply Filters</button>
                  <a href="pending-reviews.php" class="btn btn-secondary">Reset</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pending Documents Table -->
    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Pending Documents for Review</h5>
              
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th width="40">ID</th>
                      <th>Document</th>
                      <th>Client</th>
                      <th>Service</th>
                      <th>Status</th>
                      <th>Age</th>
                      <th>Priority</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($pendingDocuments)): ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No pending documents found
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($pendingDocuments as $doc): ?>
                    <tr class="<?php echo getPriorityClass($doc['days_pending']); ?>">
                      <td>
                        <div class="priority-indicator priority-<?php echo $doc['days_pending'] > 14 ? 'critical' : ($doc['days_pending'] > 7 ? 'urgent' : 'normal'); ?>"></div>
                        <?php echo $doc['id']; ?>
                      </td>
                      <td>
                        <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($doc['document_type']); ?></small>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($doc['business_name']); ?></small>
                      </td>
                      <td>
                        <small><?php echo htmlspecialchars($doc['display_service_type']); ?></small>
                      </td>
                      <td>
                        <span class="badge <?php echo getStatusBadgeClass($doc['status']); ?>">
                          <?php echo ucwords(str_replace('_', ' ', $doc['status'])); ?>
                        </span>
                      </td>
                      <td>
                        <strong><?php echo $doc['days_pending']; ?></strong> days
                        <br>
                        <small class="text-muted"><?php echo date('M d', strtotime($doc['uploaded_at'])); ?></small>
                      </td>
                      <td><?php echo getPriorityBadge($doc['days_pending']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <!-- View Document Modal -->
  <?php foreach ($pendingDocuments as $doc): ?>
  <div class="modal fade" id="viewModal<?php echo $doc['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Document Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6>Document Information</h6>
              <p><strong>Name:</strong> <?php echo htmlspecialchars($doc['document_name']); ?></p>
              <p><strong>Type:</strong> <?php echo htmlspecialchars($doc['document_type']); ?></p>
              <p><strong>Status:</strong> <span class="badge <?php echo getStatusBadgeClass($doc['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $doc['status'])); ?></span></p>
              <p><strong>Uploaded:</strong> <?php echo date('M d, Y h:i A', strtotime($doc['uploaded_at'])); ?></p>
              <p><strong>Days Pending:</strong> <?php echo getPriorityBadge($doc['days_pending']); ?> <?php echo $doc['days_pending']; ?> days</p>
            </div>
            <div class="col-md-6">
              <h6>Client Information</h6>
              <p><strong>Name:</strong> <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></p>
              <p><strong>Email:</strong> <?php echo htmlspecialchars($doc['email']); ?></p>
              <p><strong>Business:</strong> <?php echo htmlspecialchars($doc['business_name']); ?></p>
              <p><strong>Service Type:</strong> <?php echo htmlspecialchars($doc['display_service_type']); ?></p>
              <p><strong>Amount:</strong> ₱<?php echo number_format($doc['total_amount'], 2); ?></p>
            </div>
          </div>
          <hr>
          <?php if ($doc['file_path']): ?>
          <div class="text-center">
            <a href="../<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-primary">
              <i class="bi bi-eye me-2"></i>View Document
            </a>
            <a href="../<?php echo $doc['file_path']; ?>" download class="btn btn-secondary">
              <i class="bi bi-download me-2"></i>Download
            </a>
          </div>
          <?php else: ?>
          <p class="text-muted text-center">No file available</p>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Approve Modal -->
  <?php foreach ($pendingDocuments as $doc): ?>
  <div class="modal fade" id="approveModal<?php echo $doc['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Approve Document</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="approve_document">
          <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
          <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($doc['service_key']); ?>">
          <div class="modal-body">
            <div class="alert alert-info">
              <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
              <br><small>Client: <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></small>
              <br><small>Service: <?php echo htmlspecialchars($doc['display_service_type']); ?></small>
            </div>
            <div class="mb-3">
              <label for="notes<?php echo $doc['id']; ?>" class="form-label">Approval Notes (Optional)</label>
              <textarea name="notes" id="notes<?php echo $doc['id']; ?>" class="form-control" rows="3" placeholder="Add approval notes..."></textarea>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="notify_owner" value="1" id="notifyApprove<?php echo $doc['id']; ?>" checked>
              <label class="form-check-label" for="notifyApprove<?php echo $doc['id']; ?>">
                <i class="bi bi-envelope me-1"></i>Send email notification to owners
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-2"></i>Approve Document</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Reject Modal -->
  <?php foreach ($pendingDocuments as $doc): ?>
  <div class="modal fade" id="rejectModal<?php echo $doc['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Document</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="reject_document">
          <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
          <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($doc['service_key']); ?>">
          <div class="modal-body">
            <div class="alert alert-warning">
              <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
              <br><small>Client: <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></small>
              <br><small>Service: <?php echo htmlspecialchars($doc['display_service_type']); ?></small>
            </div>
            <div class="mb-3">
              <label for="rejection_reason<?php echo $doc['id']; ?>" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
              <textarea name="rejection_reason" id="rejection_reason<?php echo $doc['id']; ?>" class="form-control" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="notify_owner" value="1" id="notifyReject<?php echo $doc['id']; ?>" checked>
              <label class="form-check-label" for="notifyReject<?php echo $doc['id']; ?>">
                <i class="bi bi-envelope me-1"></i>Send email notification to owners
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-2"></i>Reject Document</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Request Revision Modal -->
  <?php foreach ($pendingDocuments as $doc): ?>
  <?php if (empty($doc['supports_revision'])) { continue; } ?>
  <div class="modal fade" id="revisionModal<?php echo $doc['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="bi bi-arrow-clockwise me-2"></i>Request Revision</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="request_revision">
          <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
          <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($doc['service_key']); ?>">
          <div class="modal-body">
            <div class="alert alert-info">
              <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
              <br><small>Client: <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></small>
              <br><small>Service: <?php echo htmlspecialchars($doc['display_service_type']); ?></small>
            </div>
            <div class="mb-3">
              <label for="revision_notes<?php echo $doc['id']; ?>" class="form-label">Revision Notes <span class="text-danger">*</span></label>
              <textarea name="revision_notes" id="revision_notes<?php echo $doc['id']; ?>" class="form-control" rows="3" placeholder="Specify what needs to be revised..." required></textarea>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="notify_owner" value="1" id="notifyRevision<?php echo $doc['id']; ?>" checked>
              <label class="form-check-label" for="notifyRevision<?php echo $doc['id']; ?>">
                <i class="bi bi-envelope me-1"></i>Send email notification to owners
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-clockwise me-2"></i>Request Revision</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Mark Under Review Script -->
  <script>
    function markUnderReview(documentId, serviceKey) {
      if (confirm('Mark this document as under review?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="mark_under_review">
          <input type="hidden" name="document_id" value="${documentId}">
          <input type="hidden" name="service_key" value="${serviceKey}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>

</body>

</html>

