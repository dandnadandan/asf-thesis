<?php
/**
 * Admin Users Management for ASF Surveillance System
 * Shows list of all users with management capabilities
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    // Redirect to login with timeout parameter
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can manage users
requireRole(['administrator'], '../unauthorized.php');

// Additional RBAC check
if (!canManageUsers()) {
    header("Location: ../unauthorized.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'User Management';

// Initialize variables for filtering
$roleFilter = isset($_GET['role']) && $_GET['role'] !== '' ? trim($_GET['role']) : '';
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$params = [];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Debug: Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_accounts'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception("Table 'user_accounts' does not exist");
    }
    
    // Debug: Check table structure
    $structure = $pdo->query("DESCRIBE user_accounts");
    $columns = $structure->fetchAll(PDO::FETCH_COLUMN);
    error_log("User accounts table columns: " . implode(', ', $columns));
    
    // Check if required columns exist
    $hasCreatedAt = in_array('created_at', $columns);
    $hasLastLogin = in_array('last_login_at', $columns);
    
    // Build SQL query based on available columns
    if ($hasCreatedAt && $hasLastLogin) {
        $sql = "SELECT 
                    ua.id,
                    CONCAT(ua.first_name, ' ', ua.last_name) as name,
                    ua.email,
                    ua.user_role as role,
                    CASE 
                        WHEN ua.is_active = 1 THEN 'Active'
                        WHEN ua.is_active = 0 THEN 'Inactive'
                        ELSE 'Unknown'
                    END as status,
                    ua.created_at as created_date,
                    ua.last_login_at as last_login
                FROM user_accounts ua
                WHERE 1=1";
    } elseif ($hasCreatedAt) {
        $sql = "SELECT 
                    ua.id,
                    CONCAT(ua.first_name, ' ', ua.last_name) as name,
                    ua.email,
                    ua.user_role as role,
                    CASE 
                        WHEN ua.is_active = 1 THEN 'Active'
                        WHEN ua.is_active = 0 THEN 'Inactive'
                        ELSE 'Unknown'
                    END as status,
                    ua.created_at as created_date,
                    NULL as last_login
                FROM user_accounts ua
                WHERE 1=1";
    } elseif ($hasLastLogin) {
        $sql = "SELECT 
                    ua.id,
                    CONCAT(ua.first_name, ' ', ua.last_name) as name,
                    ua.email,
                    ua.user_role as role,
                    CASE 
                        WHEN ua.is_active = 1 THEN 'Active'
                        WHEN ua.is_active = 0 THEN 'Inactive'
                        ELSE 'Unknown'
                    END as status,
                    NULL as created_date,
                    ua.last_login_at as last_login
                FROM user_accounts ua
                WHERE 1=1";
    } else {
        $sql = "SELECT 
                    ua.id,
                    CONCAT(ua.first_name, ' ', ua.last_name) as name,
                    ua.email,
                    ua.user_role as role,
                    CASE 
                        WHEN ua.is_active = 1 THEN 'Active'
                        WHEN ua.is_active = 0 THEN 'Inactive'
                        ELSE 'Unknown'
                    END as status,
                    NULL as created_date,
                    NULL as last_login
                FROM user_accounts ua
                WHERE 1=1";
    }
    
    $conditions = [];
    $params = [];
    
    // Only add role filter if a specific role is selected (not "All Roles")
    if (!empty($roleFilter)) {
        $conditions[] = "LOWER(ua.user_role) = LOWER(?)";
        $params[] = $roleFilter;
    }
    
    // Only add status filter if a specific status is selected (not "All Status")
    if (!empty($statusFilter)) {
        if ($statusFilter === 'Active') {
            $conditions[] = "ua.is_active = ?";
            $params[] = 1;
        } elseif ($statusFilter === 'Inactive') {
            $conditions[] = "ua.is_active = ?";
            $params[] = 0;
        }
    }
    
    if (!empty($conditions)) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }
    
    // Add ORDER BY
    $sql .= " ORDER BY ua.id DESC";
    
    error_log("Final SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $database->closeConnection();
    
    error_log("Successfully fetched " . count($users) . " users from database");
} catch (Exception $e) {
    // Log detailed error information
    error_log("Database error in users.php: " . $e->getMessage());
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $users = [];
    $errorMessage = "Unable to fetch users at this time. Please try again later.";
    
    // Show more detailed error in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $errorMessage .= " Error: " . $e->getMessage();
    }
}

// Get unique roles and statuses for filter dropdowns
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Debug: Check if we can query the table
    $testQuery = $pdo->query("SELECT COUNT(*) as total FROM user_accounts");
    $totalUsers = $testQuery->fetch()['total'];
    error_log("Total users in database: " . $totalUsers);
    
    // Define all available ASF roles in preferred order
    $availableRoles = ['administrator', 'supervisor', 'veterinarian', 'inspector', 'analyst', 'field_staff', 'data_entry', 'viewer'];
    
    error_log("Available roles: " . implode(', ', $availableRoles));
    
    // Get status options from is_active column
    $statusStmt = $pdo->query("SELECT DISTINCT is_active FROM user_accounts WHERE is_active IS NOT NULL ORDER BY is_active");
    $statusValues = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    $availableStatuses = [];
    foreach ($statusValues as $status) {
        if ($status == 1) {
            $availableStatuses[] = 'Active';
        } elseif ($status == 0) {
            $availableStatuses[] = 'Inactive';
        }
    }
    error_log("Available statuses: " . implode(', ', $availableStatuses));
    
    $database->closeConnection();
} catch (Exception $e) {
    error_log("Database error fetching filter options: " . $e->getMessage());
    // Define all available ASF roles (always include all roles)
    $availableRoles = ['administrator', 'supervisor', 'veterinarian', 'inspector', 'analyst', 'field_staff', 'data_entry', 'viewer'];
    $availableStatuses = ['Active', 'Inactive'];
}

/**
 * Format role name for display (convert snake_case to Title Case)
 * 
 * @param string $role Role name from database
 * @return string Formatted role name for display
 */
function formatRoleName($role) {
    // Convert snake_case to Title Case
    return ucwords(str_replace('_', ' ', $role));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .avatar-sm {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 16px;
    }
    
    .avatar-title {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .table th {
      background-color: #f8f9fa;
      border-top: none;
      font-weight: 600;
      color: #495057;
    }
    
    .btn-group .btn {
      margin-right: 2px;
    }
    
    .btn-group .btn:last-child {
      margin-right: 0;
    }
    
    .badge {
      font-size: 0.75rem;
      padding: 0.375rem 0.75rem;
    }
    
    .form-select-sm {
      min-width: 120px;
    }
    
    .error-message {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
      padding: 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .no-users {
      text-align: center;
      padding: 2rem;
      color: #6c757d;
    }
    
    .spin {
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    
    .avatar-lg {
      width: 100px;
      height: 100px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 2.5rem;
    }
    
    .profile-section {
      padding: 1rem;
      background-color: #f8f9fa;
      border-radius: 0.5rem;
    }
    
    .modal-xl {
      max-width: 90%;
    }
    
    .table-sm td {
      padding: 0.5rem 0.75rem;
      border: none;
      border-bottom: 1px solid #dee2e6;
    }
    
    .table-sm tr:last-child td {
      border-bottom: none;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>User Management</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">User Management</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-12">

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">All Users</h5>
              
              <!-- Error Message -->
              <?php if (isset($errorMessage)): ?>
                <div class="error-message">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  <?php echo htmlspecialchars($errorMessage); ?>
                </div>
              <?php endif; ?>
              
              <!-- Add User Button -->
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New User
                  </button>
                </div>
                <div class="d-flex gap-2">
                  <form method="GET" class="d-flex gap-2">
                    <select class="form-select form-select-sm" style="width: auto;" name="role" id="roleFilter">
                      <option value="">All Roles</option>
                      <?php foreach ($availableRoles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role); ?>" 
                                <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars(formatRoleName($role)); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" style="width: auto;" name="status" id="statusFilter">
                      <option value="">All Status</option>
                      <?php foreach ($availableStatuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" 
                                <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($status); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <?php if (!empty($roleFilter) || !empty($statusFilter)): ?>
                      <a href="users.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Clear
                      </a>
                    <?php endif; ?>
                  </form>
                </div>
              </div>

              <!-- Users Table -->
              <?php if (empty($users)): ?>
                <div class="no-users">
                  <i class="bi bi-people" style="font-size: 3rem; color: #dee2e6;"></i>
                  <h5 class="mt-3">No users found</h5>
                  <p class="text-muted">
                    <?php if (!empty($roleFilter) || !empty($statusFilter)): ?>
                      No users match the selected filters. Try adjusting your search criteria.
                    <?php else: ?>
                      There are no users in the system yet.
                    <?php endif; ?>
                  </p>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-striped table-hover" id="usersTable">
                    <thead>
                      <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created Date</th>
                        <th scope="col">Last Login</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($users as $user): ?>
                      <tr>
                        <th scope="row"><?php echo htmlspecialchars($user['id']); ?></th>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="avatar-sm me-3">
                              <div class="avatar-title bg-primary rounded-circle">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                              </div>
                            </div>
                            <div>
                              <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-primary">
                            <?php echo htmlspecialchars($user['email']); ?>
                          </a>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getRoleBadgeColor($user['role']); ?>">
                            <?php echo htmlspecialchars(formatRoleName($user['role'])); ?>
                          </span>
                        </td>
                        <td>
                          <span class="badge bg-<?php echo getStatusBadgeColor($user['status']); ?>">
                            <?php echo htmlspecialchars($user['status']); ?>
                          </span>
                        </td>
                        <td>
                          <?php 
                          if ($user['created_date']) {
                            echo date('M d, Y', strtotime($user['created_date']));
                          } else {
                            echo '<span class="text-muted">N/A</span>';
                          }
                          ?>
                        </td>
                        <td>
                          <?php 
                          if ($user['last_login']) {
                            echo date('M d, Y H:i', strtotime($user['last_login']));
                          } else {
                            echo '<span class="text-muted">Never</span>';
                          }
                          ?>
                        </td>
                        <td>
                          <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="viewUser(<?php echo $user['id']; ?>)">
                              <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                    onclick="editUser(<?php echo $user['id']; ?>)">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['id'] != 1): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteUser(<?php echo $user['id']; ?>)">
                              <i class="bi bi-trash"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    disabled
                                    title="Default administrator cannot be deleted">
                              <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                
                <!-- Results Summary -->
                <div class="mt-3 text-muted small">
                  Showing <?php echo count($users); ?> user(s)
                  <?php if (!empty($roleFilter) || !empty($statusFilter)): ?>
                    matching your filters
                  <?php endif; ?>
                </div>
              <?php endif; ?>

            </div>
          </div>

        </div>
      </div>
    </section>

  </main><!-- End #main -->
  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="addUserForm">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="firstName" class="form-label">First Name</label>
                  <input type="text" class="form-control" id="firstName" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="lastName" class="form-label">Last Name</label>
                  <input type="text" class="form-control" id="lastName" required>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" required>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                  <select class="form-select" id="role" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($availableRoles as $role): ?>
                      <option value="<?php echo htmlspecialchars($role); ?>">
                        <?php echo htmlspecialchars(formatRoleName($role)); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Select the user's role in the system</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="status" class="form-label">Status</label>
                  <select class="form-select" id="status" required>
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" required>
            </div>
            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="confirmPassword" required>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="saveUser()">Save User</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View User Modal -->
  <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="userDetailsContent">
            <!-- User details will be loaded here -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
    // Initialize DataTable
    document.addEventListener('DOMContentLoaded', function() {
      const usersTable = document.getElementById('usersTable');
      if (usersTable) {
        new simpleDatatables.DataTable(usersTable, {
          "pageLength": 25,
          "order": [[0, "desc"]],
          "responsive": true,
          "language": {
            "search": "Search users:",
            "lengthMenu": "Show _MENU_ users per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ users"
          }
        });
      }
    });

    // User action functions
    function viewUser(userId) {
      // Show loading state
      const userDetailsContent = document.getElementById('userDetailsContent');
      userDetailsContent.innerHTML = '<div class="text-center"><i class="bi bi-arrow-clockwise spin"></i> Loading user details...</div>';
      
      // Show modal
      const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
      viewModal.show();
      
      // Fetch user details via AJAX
      fetch(`get_user_details.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const user = data.user;
            displayUserDetails(user);
          } else {
            userDetailsContent.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          userDetailsContent.innerHTML = '<div class="alert alert-danger">Failed to load user details. Please try again.</div>';
        });
    }

    function displayUserDetails(user) {
      // Debug: Log the user data and image path
      console.log('User data:', user);
      console.log('Profile image path:', user.profile_image);
      console.log('No image path: ../images/employees/noimage.png');
      
      const content = `
        <div class="row">
          <!-- Profile Section -->
          <div class="col-md-4 text-center mb-4">
            <div class="profile-section">
                             <div class="avatar-lg mx-auto mb-3">
                 <img src="${user.profile_image || '../bootstrap/assets/img/profile-img.jpg'}" alt="Profile" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;" onerror="this.src='../bootstrap/assets/img/profile-img.jpg'">
               </div>
              <h4 class="mb-2">${user.full_name || user.first_name + ' ' + user.last_name || 'N/A'}</h4>
              <span class="badge bg-${getStatusBadgeColor(user.is_active || 'Unknown')} mb-2">${user.is_active || 'Unknown'}</span>
              <span class="badge bg-${getRoleBadgeColor(user.user_role || 'Unknown')}">${user.user_role || 'Unknown'}</span>
            </div>
          </div>
          
          <!-- User Information -->
          <div class="col-md-8">
            <div class="row">
              <!-- Basic Information -->
              <div class="col-md-6">
                <h6 class="text-primary mb-3">Basic Information</h6>
                <table class="table table-sm">
                  <tr><td><strong>ID:</strong></td><td>#${user.id || 'N/A'}</td></tr>
                  <tr><td><strong>Username:</strong></td><td>${user.username || 'N/A'}</td></tr>
                  <tr><td><strong>Email:</strong></td><td><a href="mailto:${user.email || ''}">${user.email || 'N/A'}</a></td></tr>
                  <tr><td><strong>First Name:</strong></td><td>${user.first_name || 'N/A'}</td></tr>
                  <tr><td><strong>Last Name:</strong></td><td>${user.last_name || 'N/A'}</td></tr>
                  <tr><td><strong>Organization:</strong></td><td>${user.organization || 'N/A'}</td></tr>
                  <tr><td><strong>Phone:</strong></td><td>${user.phone || 'N/A'}</td></tr>
                </table>
              </div>
              
              <!-- Address Information -->
              <div class="col-md-6">
                <h6 class="text-primary mb-3">Address Information</h6>
                <table class="table table-sm">
                  <tr><td><strong>Address:</strong></td><td>${user.address || 'N/A'}</td></tr>
                  <tr><td><strong>City:</strong></td><td>${user.city || 'N/A'}</td></tr>
                  <tr><td><strong>Province:</strong></td><td>${user.province || 'N/A'}</td></tr>
                  <tr><td><strong>Postal Code:</strong></td><td>${user.postal_code || 'N/A'}</td></tr>
                </table>
              </div>
            </div>
            
            <!-- Account Status -->
            <div class="row mt-3">
              <div class="col-12">
                <h6 class="text-primary mb-3">Account Status</h6>
                <div class="row">
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">${user.is_verified || 'Not Verified'}</div>
                      <small class="text-muted">Email Verification Status</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">${user.is_active || 'Unknown'}</div>
                      <small class="text-muted">Account Status</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">${user.user_role || 'Unknown'}</div>
                      <small class="text-muted">User Role</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">#${user.id || 'N/A'}</div>
                      <small class="text-muted">User ID</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Timestamps -->
            <div class="row mt-3">
              <div class="col-12">
                <h6 class="text-primary mb-3">Timestamps</h6>
                <div class="row">
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.created_at || 'N/A'}</div>
                      <small class="text-muted">Created</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.updated_at || 'N/A'}</div>
                      <small class="text-muted">Last Updated</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.last_login_at || 'N/A'}</div>
                      <small class="text-muted">Last Login</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.email_verified_at || 'N/A'}</div>
                      <small class="text-muted">Email Verified At</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      
      document.getElementById('userDetailsContent').innerHTML = content;
    }

    function editUser(userId) {
      // Fetch user details
      fetch(`get_user_details.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const user = data.user;
            showEditModal(user);
          } else {
            alert('Error loading user details: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to load user details. Please try again.');
        });
    }

    function showEditModal(user) {
      // Change modal title
      document.getElementById('addUserModalLabel').textContent = 'Edit User';
      
      // Populate form fields
      document.getElementById('firstName').value = user.first_name || '';
      document.getElementById('lastName').value = user.last_name || '';
      document.getElementById('email').value = user.email || '';
      document.getElementById('role').value = user.user_role || '';
      document.getElementById('status').value = user.is_active === 'Active' ? 'Active' : 'Inactive';
      
      // Hide password fields
      document.getElementById('password').parentElement.style.display = 'none';
      document.getElementById('confirmPassword').parentElement.style.display = 'none';
      document.getElementById('password').removeAttribute('required');
      document.getElementById('confirmPassword').removeAttribute('required');
      
      // Prevent role change for user ID 1 (default administrator)
      const roleSelect = document.getElementById('role');
      const roleLabel = roleSelect.parentElement.querySelector('label');
      if (user.id == 1) {
        roleSelect.disabled = true;
        roleSelect.title = 'The default administrator role cannot be changed';
        if (roleLabel) {
          const helpText = roleSelect.parentElement.querySelector('small');
          if (helpText) {
            helpText.textContent = 'The default administrator (User ID 1) role cannot be changed';
            helpText.className = 'text-warning';
          }
        }
      } else {
        roleSelect.disabled = false;
        roleSelect.removeAttribute('title');
        const helpText = roleSelect.parentElement.querySelector('small');
        if (helpText) {
          helpText.textContent = 'Select the user\'s role in the system';
          helpText.className = 'text-muted';
        }
      }
      
      // Store user ID in form
      document.getElementById('addUserForm').dataset.userId = user.id;
      document.getElementById('addUserForm').dataset.mode = 'edit';
      
      // Change button text
      const saveBtn = document.querySelector('#addUserModal .btn-primary');
      saveBtn.textContent = 'Update User';
      saveBtn.onclick = updateUser;
      
      // Show modal
      const editModal = new bootstrap.Modal(document.getElementById('addUserModal'));
      editModal.show();
    }

    function updateUser() {
      const form = document.getElementById('addUserForm');
      const userId = form.dataset.userId;
      
      // Get form values
      var firstName = document.getElementById('firstName').value.trim();
      var lastName = document.getElementById('lastName').value.trim();
      var email = document.getElementById('email').value.trim();
      var role = document.getElementById('role').value;
      var status = document.getElementById('status').value;

      // Basic validation
      if (!firstName || !lastName || !email || !role || !status) {
        alert('Please fill in all required fields.');
        return;
      }

      // Disable update button and show loading
      const updateBtn = event.target;
      const originalText = updateBtn.innerHTML;
      updateBtn.disabled = true;
      updateBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';

      // Prepare data
      const userData = {
        userId: userId,
        firstName: firstName,
        lastName: lastName,
        email: email,
        role: role,
        status: status
      };

      // Make AJAX call to update user
      fetch('edit_user.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✓ User updated successfully!');
          
          // Close modal and reset form
          const editModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
          editModal.hide();
          resetAddUserModal();
          
          // Reload page to show updated user
          setTimeout(() => {
            window.location.reload();
          }, 500);
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the user. Please try again.');
      })
      .finally(() => {
        // Re-enable update button
        updateBtn.disabled = false;
        updateBtn.innerHTML = originalText;
      });
    }

    function deleteUser(userId) {
      // Prevent deletion of user ID 1 (default administrator)
      if (userId == 1) {
        alert('Error: Cannot delete the default administrator account (User ID 1)');
        return;
      }
      
      if (confirm('Are you sure you want to delete this user?\n\nThis action cannot be undone!')) {
        // Make AJAX call to delete user
        fetch('delete_user.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ userId: userId })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('✓ User deleted successfully!');
            // Reload page to update the list
            window.location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while deleting the user. Please try again.');
        });
      }
    }

    function resetAddUserModal() {
      // Reset modal title
      document.getElementById('addUserModalLabel').textContent = 'Add New User';
      
      // Reset form
      document.getElementById('addUserForm').reset();
      delete document.getElementById('addUserForm').dataset.userId;
      delete document.getElementById('addUserForm').dataset.mode;
      
      // Show password fields
      document.getElementById('password').parentElement.style.display = 'block';
      document.getElementById('confirmPassword').parentElement.style.display = 'block';
      document.getElementById('password').setAttribute('required', 'required');
      document.getElementById('confirmPassword').setAttribute('required', 'required');
      
      // Reset button
      const saveBtn = document.querySelector('#addUserModal .btn-primary');
      saveBtn.textContent = 'Save User';
      saveBtn.onclick = saveUser;
    }

    // Reset modal when it's closed
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
      if (document.getElementById('addUserForm').dataset.mode === 'edit') {
        resetAddUserModal();
      }
    });

    function saveUser() {
      // Get form values
      var firstName = document.getElementById('firstName').value.trim();
      var lastName = document.getElementById('lastName').value.trim();
      var email = document.getElementById('email').value.trim();
      var role = document.getElementById('role').value;
      var status = document.getElementById('status').value;
      var password = document.getElementById('password').value;
      var confirmPassword = document.getElementById('confirmPassword').value;

      // Basic validation
      if (!firstName || !lastName || !email || !role || !status || !password) {
        alert('Please fill in all required fields.');
        return;
      }

      if (password !== confirmPassword) {
        alert('Passwords do not match.');
        return;
      }

      if (password.length < 6) {
        alert('Password must be at least 6 characters long.');
        return;
      }

      // Disable save button and show loading
      const saveBtn = event.target;
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';

      // Prepare data
      const userData = {
        firstName: firstName,
        lastName: lastName,
        email: email,
        role: role,
        status: status,
        password: password,
        confirmPassword: confirmPassword
      };

      // Make AJAX call to save user
      fetch('add_user.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Display success message with email status
          let message = '✓ User created successfully!\n\n';
          message += 'Username: ' + data.username + '\n';
          message += 'User ID: #' + data.user_id + '\n\n';
          
          if (data.email_sent) {
            message += '📧 A verification email has been sent to the user.\n';
            message += 'The user must verify their email before they can log in.';
          } else {
            message += '⚠️ Note: Verification email could not be sent.\n';
            message += 'The user will need to contact support to activate their account.';
          }
          
          alert(message);
          
          // Close modal and reset form
          const addModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
          addModal.hide();
          document.getElementById('addUserForm').reset();
          
          // Reload page to show new user
          setTimeout(() => {
            window.location.reload();
          }, 500);
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the user. Please try again.');
      })
      .finally(() => {
        // Re-enable save button
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      });
    }

    // Helper functions for badge colors (JavaScript versions)
    function getStatusBadgeColor(status) {
      if (!status || typeof status !== 'string') {
        return 'secondary';
      }
      
      switch (status.toLowerCase()) {
        case 'active':
          return 'success';
        case 'inactive':
          return 'danger';
        default:
          return 'secondary';
      }
    }

    function getRoleBadgeColor(role) {
      if (!role || typeof role !== 'string') {
        return 'secondary';
      }
      
      switch (role.toLowerCase()) {
        case 'administrator':
          return 'danger';
        case 'supervisor':
          return 'warning';
        case 'veterinarian':
          return 'success';
        case 'inspector':
          return 'info';
        case 'analyst':
          return 'primary';
        case 'field_staff':
          return 'info';
        case 'data_entry':
          return 'secondary';
        case 'viewer':
          return 'secondary';
        default:
          return 'secondary';
      }
    }
  </script>

</body>

</html>

<?php
// Helper functions for badge colors (PHP versions for server-side rendering)
function getRoleBadgeColor($role) {
    switch (strtolower($role)) {
        case 'administrator':
            return 'danger';
        case 'supervisor':
            return 'warning';
        case 'veterinarian':
            return 'success';
        case 'inspector':
            return 'info';
        case 'analyst':
            return 'primary';
        case 'field_staff':
            return 'info';
        case 'data_entry':
            return 'secondary';
        case 'viewer':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getStatusBadgeColor($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>
