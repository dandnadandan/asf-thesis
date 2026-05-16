<?php
/**
 * View Contact Inquiries - Admin Page
 * Allows administrators to view and manage contact form submissions
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff', 'owner'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'Contact Inquiries';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $inquiry_id = $_POST['inquiry_id'];
    $new_status = $_POST['status'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "UPDATE contact_inquiries SET status = :status WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $inquiry_id);
        $stmt->execute();
        
        $success_message = "Inquiry status updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Build query
    $query = "SELECT * FROM contact_inquiries WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics from contact_inquiries table
    $stats_query = "SELECT 
        COUNT(*) as total_inquiries,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_inquiries,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_inquiries,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_inquiries,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_inquiries
        FROM contact_inquiries";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute();
    $statistics = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading inquiries: " . $e->getMessage();
    $inquiries = [];
    $statistics = [
        'total_inquiries' => 0,
        'new_inquiries' => 0,
        'read_inquiries' => 0,
        'replied_inquiries' => 0,
        'archived_inquiries' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .inquiry-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .inquiry-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 12px;
        }
        .status-new { background: #0d6efd; color: white; }
        .status-read { background: #6c757d; color: white; }
        .status-replied { background: #28a745; color: white; }
        .status-archived { background: #dc3545; color: white; }
        .stats-card {
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card h3 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-envelope"></i> Contact Inquiries</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Contact Inquiries</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-x-circle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <?php if (isset($statistics)): ?>
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <h3><?php echo $statistics['total_inquiries'] ?? 0; ?></h3>
                        <p class="mb-0">Total Inquiries</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h3><?php echo $statistics['new_inquiries'] ?? 0; ?></h3>
                        <p class="mb-0">New Inquiries</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <h3><?php echo $statistics['read_inquiries'] ?? 0; ?></h3>
                        <p class="mb-0">Read</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <h3><?php echo $statistics['replied_inquiries'] ?? 0; ?></h3>
                        <p class="mb-0">Replied</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, subject..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inquiries List -->
            <?php if (!empty($inquiries)): ?>
                <?php foreach ($inquiries as $inquiry): ?>
                <div class="card inquiry-card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">
                                    <?php echo htmlspecialchars($inquiry['subject']); ?>
                                </h5>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($inquiry['name']); ?> 
                                    | <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($inquiry['email']); ?>
                                    | <i class="bi bi-clock"></i> <?php echo date('M d, Y h:i A', strtotime($inquiry['created_at'])); ?>
                                </p>
                            </div>
                            <span class="badge status-badge status-<?php echo $inquiry['status']; ?>">
                                <?php echo ucfirst($inquiry['status']); ?>
                            </span>
                        </div>
                        
                        <div class="card-text mb-3">
                            <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-globe"></i> <?php echo htmlspecialchars($inquiry['ip_address']); ?>
                            </small>
                            
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                    <option value="new" <?php echo $inquiry['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="read" <?php echo $inquiry['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                    <option value="replied" <?php echo $inquiry['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                    <option value="archived" <?php echo $inquiry['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check"></i> Update
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No inquiries found matching your criteria.
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

