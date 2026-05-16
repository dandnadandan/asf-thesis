<?php
/**
 * AJAX handler for getting request details
 * Supports multiple service types: business, tax, payroll, etc.
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], '../unauthorized.php');

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

if (empty($type) || empty($id)) {
    http_response_code(400);
    die('Invalid parameters');
}

$database = new Database();
$pdo = $database->getConnection();

switch ($type) {
    case 'payroll':
        getPayrollRequestDetails($pdo, $id);
        break;
    case 'business':
        getBusinessRequestDetails($pdo, $id);
        break;
    case 'tax':
        getTaxRequestDetails($pdo, $id);
        break;
    default:
        http_response_code(400);
        die('Invalid service type');
}

function getPayrollRequestDetails($pdo, $service_id) {
    // Get service details
    $sql = "SELECT pps.*, ua.first_name, ua.last_name, ua.email, ua.phone
            FROM payroll_processing_services pps
            LEFT JOIN user_accounts ua ON pps.user_id = ua.id
            WHERE pps.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo '<div class="alert alert-danger">Request not found.</div>';
        return;
    }
    
    // Get payments
    $payments = getRequestPayments($pdo, $service_id, 'payroll');
    
    // Get documents
    $documents = getRequestDocuments($pdo, $service_id, 'payroll');
    
    // Get comments
    $comments = getRequestComments($pdo, $service_id, 'payroll');
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h6>Service Information</h6>';
    echo '<table class="table table-sm">';
    echo '<tr><td><strong>Company:</strong></td><td>' . htmlspecialchars($service['company_name']) . '</td></tr>';
    echo '<tr><td><strong>Client:</strong></td><td>' . htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) . '</td></tr>';
    echo '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($service['email']) . '</td></tr>';
    echo '<tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($service['phone']) . '</td></tr>';
    echo '<tr><td><strong>TIN:</strong></td><td>' . htmlspecialchars($service['tin']) . '</td></tr>';
    echo '<tr><td><strong>Payroll Period:</strong></td><td>' . ucfirst($service['payroll_period']) . '</td></tr>';
    echo '<tr><td><strong>Number of Employees:</strong></td><td>' . $service['number_of_employees'] . '</td></tr>';
    echo '<tr><td><strong>Total Amount:</strong></td><td>₱' . number_format($service['total_amount'], 2) . '</td></tr>';
    echo '<tr><td><strong>Status:</strong></td><td><span class="badge bg-info">' . ucfirst($service['service_status']) . '</span></td></tr>';
    echo '<tr><td><strong>Payment Status:</strong></td><td><span class="badge bg-warning">' . ucfirst($service['payment_status']) . '</span></td></tr>';
    echo '<tr><td><strong>Created:</strong></td><td>' . date('M d, Y H:i', strtotime($service['created_at'])) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<h6>Payment History</h6>';
    if (empty($payments)) {
        echo '<p class="text-muted">No payments found.</p>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        foreach ($payments as $payment) {
            $status_class = $payment['status'] === 'approved' ? 'bg-success' : ($payment['status'] === 'rejected' ? 'bg-danger' : 'bg-warning');
            echo '<tr>';
            echo '<td>' . date('M d, Y', strtotime($payment['payment_date'])) . '</td>';
            echo '<td>₱' . number_format($payment['amount'], 2) . '</td>';
            echo '<td>' . ucfirst($payment['payment_method']) . '</td>';
            echo '<td><span class="badge ' . $status_class . '">' . ucfirst($payment['status']) . '</span></td>';
            echo '<td>';
            if ($payment['status'] === 'pending') {
                echo '<button class="btn btn-sm btn-success me-1" onclick="approvePayment(' . $payment['id'] . ')">Approve</button>';
                echo '<button class="btn btn-sm btn-danger" onclick="rejectPayment(' . $payment['id'] . ')">Reject</button>';
            } else {
                echo '<button class="btn btn-sm btn-outline-primary" onclick="reviewPayment(' . $payment['id'] . ')">Review</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Documents section
    if (!empty($documents)) {
        echo '<div class="row mt-3">';
        echo '<div class="col-12">';
        echo '<h6>Documents</h6>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Type</th><th>Name</th><th>Status</th><th>Uploaded</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        foreach ($documents as $doc) {
            $status_class = $doc['status'] === 'approved' ? 'bg-success' : ($doc['status'] === 'rejected' ? 'bg-danger' : 'bg-warning');
            echo '<tr>';
            echo '<td>' . ucfirst($doc['document_type']) . '</td>';
            echo '<td>' . htmlspecialchars($doc['document_name']) . '</td>';
            echo '<td><span class="badge ' . $status_class . '">' . ucfirst($doc['status']) . '</span></td>';
            // Use uploaded_date for payroll documents, created_at for others
            $date_field = isset($doc['uploaded_date']) ? $doc['uploaded_date'] : $doc['created_at'];
            echo '<td>' . date('M d, Y', strtotime($date_field)) . '</td>';
            echo '<td>';
            if ($doc['file_path']) {
                echo '<a href="../' . htmlspecialchars($doc['file_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary">View</a>';
            } else {
                echo '<span class="text-muted">No file</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

function getBusinessRequestDetails($pdo, $service_id) {
    // Similar implementation for business registration
    // This would be implemented based on the business registration structure
    echo '<div class="alert alert-info">Business registration details not implemented yet.</div>';
}

function getTaxRequestDetails($pdo, $service_id) {
    // Similar implementation for tax filing
    // This would be implemented based on the tax filing structure
    echo '<div class="alert alert-info">Tax filing details not implemented yet.</div>';
}

function getRequestPayments($pdo, $service_id, $type) {
    $table = $type . '_processing_payments';
    $sql = "SELECT * FROM $table WHERE service_id = :service_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['service_id' => $service_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRequestDocuments($pdo, $service_id, $type) {
    $table = $type . '_processing_documents';
    // Use uploaded_date for payroll, created_at for others
    $date_column = ($type === 'payroll') ? 'uploaded_date' : 'created_at';
    $sql = "SELECT * FROM $table WHERE service_id = :service_id ORDER BY $date_column DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['service_id' => $service_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRequestComments($pdo, $service_id, $type) {
    $table = $type . '_processing_comments';
    $sql = "SELECT ppc.*, ua.first_name, ua.last_name 
            FROM $table ppc 
            LEFT JOIN user_accounts ua ON ppc.admin_id = ua.id 
            WHERE ppc.service_id = :service_id 
            ORDER BY ppc.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['service_id' => $service_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
