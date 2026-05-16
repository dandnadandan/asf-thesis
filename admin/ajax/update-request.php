<?php
/**
 * AJAX endpoint for updating tax filing requests
 * Handles payment approval/rejection and other request updates
 */

require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], null);

$currentUser = getCurrentUser();

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_payment':
            $result = approvePaymentProof($pdo, $_POST);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Payment approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve payment']);
            }
            break;
            
        case 'reject_payment':
            $result = rejectPaymentProof($pdo, $_POST);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Payment rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject payment']);
            }
            break;
            
        case 'update_status':
            $result = updateRequestStatus($pdo, $_POST);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update request status']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// Function to approve payment proof
function approvePaymentProof($pdo, $data) {
    global $currentUser;
    $payment_id = $data['payment_id'];
    $notes = $data['notes'] ?? '';
    
    // Update payment status to paid
    $sql = "UPDATE tax_filing_payments SET status = 'paid', admin_notes = :notes, approved_by = :admin_id, approved_at = CURRENT_TIMESTAMP WHERE id = :payment_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'payment_id' => $payment_id,
        'notes' => $notes,
        'admin_id' => $currentUser['id']
    ]);
    
    if ($result) {
        // Update service payment status based on payment type
        // Try to get payment_type from payment table first, otherwise from service table
        $sql = "SELECT service_id, amount, payment_type FROM tax_filing_payments WHERE id = :payment_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['payment_id' => $payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            $payment_type = strtolower(trim($payment['payment_type'] ?? ''));
            
            // If payment_type is not in payment table, get it from service table
            if (empty($payment_type)) {
                $sql = "SELECT payment_type FROM tax_filing_services WHERE id = :service_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['service_id' => $payment['service_id']]);
                $service = $stmt->fetch(PDO::FETCH_ASSOC);
                $payment_type = strtolower(trim($service['payment_type'] ?? ''));
            }
            
            // If this is a full payment, set payment_status to 'paid' immediately
            if ($payment_type === 'full_payment') {
                $sql = "UPDATE tax_filing_services SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = :service_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['service_id' => $payment['service_id']]);
            } else {
                // For downpayment or final_payment, check if total approved payments equals total amount
                $sql = "SELECT SUM(amount) as total_approved FROM tax_filing_payments WHERE service_id = :service_id AND status = 'paid'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['service_id' => $payment['service_id']]);
                $total_approved = $stmt->fetch(PDO::FETCH_ASSOC)['total_approved'] ?? 0;
                
                $sql = "SELECT total_amount FROM tax_filing_services WHERE id = :service_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['service_id' => $payment['service_id']]);
                $total_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;
                
                // Use tolerance for floating point comparison
                if (abs($total_approved - $total_amount) < 0.01 || $total_approved >= $total_amount) {
                    // Update service payment status to paid
                    $sql = "UPDATE tax_filing_services SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = :service_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['service_id' => $payment['service_id']]);
                } else {
                    // Update to partial payment (for downpayment cases)
                    $sql = "UPDATE tax_filing_services SET payment_status = 'partial', updated_at = CURRENT_TIMESTAMP WHERE id = :service_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['service_id' => $payment['service_id']]);
                }
            }
        }
    }
    
    return $result;
}

// Function to reject payment proof
function rejectPaymentProof($pdo, $data) {
    global $currentUser;
    $payment_id = $data['payment_id'];
    $rejection_reason = $data['rejection_reason'] ?? '';
    
    // Update payment status to rejected
    $sql = "UPDATE tax_filing_payments SET status = 'rejected', admin_notes = :notes, approved_by = :admin_id, approved_at = CURRENT_TIMESTAMP WHERE id = :payment_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'payment_id' => $payment_id,
        'notes' => $rejection_reason,
        'admin_id' => $currentUser['id']
    ]);
    
    if ($result) {
        // Update service payment status back to pending
        $sql = "SELECT service_id FROM tax_filing_payments WHERE id = :payment_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['payment_id' => $payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            $sql = "UPDATE tax_filing_services SET payment_status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = :service_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['service_id' => $payment['service_id']]);
        }
    }
    
    return $result;
}

// Function to update request status
function updateRequestStatus($pdo, $data) {
    global $currentUser;
    $service_id = $data['request_id'];
    $new_status = $data['status'];
    
    // Update the service status
    $sql = "UPDATE tax_filing_services SET service_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'status' => $new_status,
        'id' => $service_id
    ]);
    
    // Log status change in history
    if ($result) {
        $sql = "INSERT INTO tax_filing_status_history (service_id, status, notes, changed_by) 
                VALUES (:service_id, :status, :notes, :changed_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'service_id' => $service_id,
            'status' => $new_status,
            'notes' => 'Status updated by admin',
            'changed_by' => $currentUser['id']
        ]);
    }
    
    return $result;
}
?>