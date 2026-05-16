<?php
/**
 * Admin User Roles Management for TaxEase
 * Shows list of all users by role, excluding clients
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    // Redirect to login with timeout parameter
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'User Roles Management';

// Initialize variables for filtering
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$params = [];

if (!empty($roleFilter)) {
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'Active') {
        $params[] = 1;
    } elseif ($statusFilter === 'Inactive') {
        $params[] = 0;
    }
}

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
    
    // Build SQL query based on available columns, excluding clients
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
                    ua.last_login_at as last_login,
                    ua.employee_id
                FROM user_accounts ua
                WHERE ua.user_role != 'client'";
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
                    NULL as last_login,
                    ua.employee_id
                FROM user_accounts ua
                WHERE ua.user_role != 'client'";
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
                    ua.last_login_at as last_login,
                    ua.employee_id
                FROM user_accounts ua
                WHERE ua.user_role != 'client'";
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
                    NULL as last_login,
                    ua.employee_id
                FROM user_accounts ua
                WHERE ua.user_role != 'client'";
    }
    
    // Add WHERE clauses for filters
    if (!empty($roleFilter)) {
        $sql .= " AND ua.user_role = ?";
    }
    
    if (!empty($statusFilter)) {
        if ($statusFilter === 'Active' || $statusFilter === 'Inactive') {
            $sql .= " AND ua.is_active = ?";
        }
    }
    
    // Add ORDER BY
    $sql .= " ORDER BY ua.user_role ASC, ua.id DESC";
    
    error_log("Final SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $database->closeConnection();
    
    error_log("Successfully fetched " . count($users) . " non-client users from database");
} catch (Exception $e) {
    // Log detailed error information
    error_log("Database error in user_roles.php: " . $e->getMessage());
    error_log("SQL Query: " . (isset($sql) ? $sql : 'SQL query not defined due to early error'));
    error_log("Parameters: " . print_r($params, true));
    
    $users = [];
    $errorMessage = "Unable to fetch users at this time. Please try again later.";
    
    // Show more detailed error in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $errorMessage .= " Error: " . $e->getMessage();
    }
}

// Get unique roles and statuses for filter dropdowns (excluding clients)
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Debug: Check if we can query the table
    $testQuery = $pdo->query("SELECT COUNT(*) as total FROM user_accounts WHERE user_role != 'client'");
    $totalUsers = $testQuery->fetch()['total'];
    error_log("Total non-client users in database: " . $totalUsers);
    
    $roleStmt = $pdo->query("SELECT DISTINCT user_role FROM user_accounts WHERE user_role != 'client' AND user_role IS NOT NULL ORDER BY user_role");
    $availableRoles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available roles (excluding clients): " . implode(', ', $availableRoles));
    
    // Get status options from is_active column
    $statusStmt = $pdo->query("SELECT DISTINCT is_active FROM user_accounts WHERE user_role != 'client' AND is_active IS NOT NULL ORDER BY is_active");
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
    $availableRoles = ['Employee', 'Administrative Staff', 'Account Supervisor', 'Administrator'];
    $availableStatuses = ['Active', 'Inactive'];
}

// Group users by role for better organization
$usersByRole = [];
foreach ($users as $user) {
    $role = $user['role'];
    if (!isset($usersByRole[$role])) {
        $usersByRole[$role] = [];
    }
    $usersByRole[$role][] = $user;
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
    
    .role-section {
      margin-bottom: 2rem;
      border: 1px solid #dee2e6;
      border-radius: 0.5rem;
      overflow: hidden;
    }
    
    .role-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1rem;
      margin: 0;
    }
    
    .role-count {
      background: rgba(255, 255, 255, 0.2);
      padding: 0.25rem 0.75rem;
      border-radius: 1rem;
      font-size: 0.875rem;
      margin-left: 1rem;
    }
    
    .role-content {
      padding: 1rem;
    }
    
    .role-stats {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    
    .stat-card {
      background: #f8f9fa;
      padding: 0.75rem;
      border-radius: 0.375rem;
      text-align: center;
      min-width: 120px;
    }
    
    .stat-number {
      font-size: 1.5rem;
      font-weight: bold;
      color: #495057;
    }
    
    .stat-label {
      font-size: 0.875rem;
      color: #6c757d;
      margin-top: 0.25rem;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>User Roles Management</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">User Roles Management</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-12">

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Staff & Administrative Users</h5>
              
              <!-- Error Message -->
              <?php if (isset($errorMessage)): ?>
                <div class="error-message">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  <?php echo htmlspecialchars($errorMessage); ?>
                </div>
              <?php endif; ?>
              
              <!-- Filters -->
              <div class="d-flex justify-content-end mb-3">
                <form method="GET" class="d-flex gap-2">
                  <select class="form-select form-select-sm" style="width: auto;" name="role" id="roleFilter">
                    <option value="">All Roles</option>
                    <?php foreach ($availableRoles as $role): ?>
                      <option value="<?php echo htmlspecialchars($role); ?>" 
                              <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role); ?>
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
                    <a href="user_roles.php" class="btn btn-outline-danger btn-sm">
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                  <?php endif; ?>
                </form>
              </div>

              <!-- Users by Role -->
              <?php if (empty($usersByRole)): ?>
                <div class="no-users">
                  <i class="bi bi-people" style="font-size: 3rem; color: #dee2e6;"></i>
                  <h5 class="mt-3">No staff users found</h5>
                  <p class="text-muted">
                    <?php if (!empty($roleFilter) || !empty($statusFilter)): ?>
                      No users match the selected filters. Try adjusting your search criteria.
                    <?php else: ?>
                      There are no staff or administrative users in the system yet.
                    <?php endif; ?>
                  </p>
                </div>
              <?php else: ?>
                <?php foreach ($usersByRole as $role => $roleUsers): ?>
                  <div class="role-section">
                    <h6 class="role-header">
                      <?php echo htmlspecialchars($role); ?>
                      <span class="role-count"><?php echo count($roleUsers); ?> user(s)</span>
                    </h6>
                    
                    <div class="role-content">
                      <!-- Role Statistics -->
                      <div class="role-stats">
                        <?php
                        $activeCount = 0;
                        $inactiveCount = 0;
                        $withEmployeeId = 0;
                        
                        foreach ($roleUsers as $user) {
                            if ($user['status'] === 'Active') $activeCount++;
                            if ($user['status'] === 'Inactive') $inactiveCount++;
                            if (!empty($user['employee_id'])) $withEmployeeId++;
                        }
                        ?>
                        <div class="stat-card">
                          <div class="stat-number"><?php echo $activeCount; ?></div>
                          <div class="stat-label">Active</div>
                        </div>
                        <div class="stat-card">
                          <div class="stat-number"><?php echo $inactiveCount; ?></div>
                          <div class="stat-label">Inactive</div>
                        </div>
                        <div class="stat-card">
                          <div class="stat-number"><?php echo $withEmployeeId; ?></div>
                          <div class="stat-label">With Employee ID</div>
                        </div>
                      </div>
                      
                      <!-- Users Table for this Role -->
                      <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                          <thead>
                            <tr>
                              <th scope="col">#</th>
                              <th scope="col">Name</th>
                              <th scope="col">Email</th>
                              <th scope="col">Status</th>
                              <th scope="col">Employee ID</th>
                              <th scope="col">Created Date</th>
                              <th scope="col">Last Login</th>
                              <th scope="col">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($roleUsers as $user): ?>
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
                                <span class="badge bg-<?php echo getStatusBadgeColor($user['status']); ?>">
                                  <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                              </td>
                              <td>
                                <?php 
                                if (!empty($user['employee_id'])) {
                                    echo '<span class="badge bg-info">' . htmlspecialchars($user['employee_id']) . '</span>';
                                } else {
                                    echo '<span class="text-muted">Not set</span>';
                                }
                                ?>
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
                                  <button type="button" class="btn btn-sm btn-outline-danger" 
                                          onclick="deleteUser(<?php echo $user['id']; ?>)">
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </div>
                              </td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
                
                <!-- Results Summary -->
                <div class="mt-3 text-muted small">
                  Showing <?php echo count($users); ?> staff user(s) across <?php echo count($usersByRole); ?> role(s)
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

  <!-- Edit User Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserModalLabel">Change User Role</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editUserForm">
            <input type="hidden" id="editUserId" name="user_id">
            <div class="mb-3">
              <label for="editUserName" class="form-label">User Name</label>
              <input type="text" class="form-control" id="editUserName" readonly>
            </div>
            <div class="mb-3">
              <label for="editRole" class="form-label">User Role</label>
              <select class="form-select" id="editRole" name="user_role" required>
                <option value="">Select Role</option>
                <option value="" id="currentRoleOption" disabled style="display: none;">Loading...</option>
                <option value="Administrator">Administrator</option>
                <option value="Account Supervisor">Account Supervisor</option>
                <option value="Senior Account Executive">Senior Account Executive</option>
                <option value="Junior Account Executive">Junior Account Executive</option>
                <option value="Administrative Staff">Administrative Staff</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="saveUserChanges()">Change Role</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
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
      const content = `
        <div class="row">
          <!-- Profile Section -->
          <div class="col-md-4 text-center mb-4">
            <div class="profile-section">
              <div class="avatar-lg mx-auto mb-3">
                ${user.profile_image && 
                  typeof user.profile_image === 'string' && 
                  user.profile_image.trim() !== '' && 
                  user.profile_image !== 'null' && 
                  user.profile_image !== 'undefined' &&
                  !user.profile_image.toLowerCase().includes('no image') &&
                  !user.profile_image.toLowerCase().includes('noimage') &&
                  !user.profile_image.toLowerCase().includes('placeholder') &&
                  !user.profile_image.toLowerCase().includes('default') ? 
                  `<img src="${user.profile_image}" alt="Profile" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">` :
                  `<img src="../images/employees/noimage.png" alt="No Profile Image" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">`
                }
              </div>
              <h4 class="mb-2">${user.full_name}</h4>
              <span class="badge bg-${getStatusBadgeColor(user.is_active)} mb-2">${user.is_active}</span>
              <span class="badge bg-${getRoleBadgeColor(user.user_role)}">${user.user_role}</span>
            </div>
          </div>
          
          <!-- User Information -->
          <div class="col-md-8">
            <div class="row">
              <!-- Basic Information -->
              <div class="col-md-6">
                <h6 class="text-primary mb-3">Basic Information</h6>
                <table class="table table-sm">
                  <tr><td><strong>ID:</strong></td><td>#${user.id}</td></tr>
                  <tr><td><strong>Username:</strong></td><td>${user.username}</td></tr>
                  <tr><td><strong>Email:</strong></td><td><a href="mailto:${user.email}">${user.email}</a></td></tr>
                  <tr><td><strong>First Name:</strong></td><td>${user.first_name}</td></tr>
                  <tr><td><strong>Last Name:</strong></td><td>${user.last_name}</td></tr>
                  <tr><td><strong>Company:</strong></td><td>${user.company_name}</td></tr>
                  <tr><td><strong>Phone:</strong></td><td>${user.phone}</td></tr>
                </table>
              </div>
              
              <!-- Address Information -->
              <div class="col-md-6">
                <h6 class="text-primary mb-3">Address Information</h6>
                <table class="table table-sm">
                  <tr><td><strong>Address:</strong></td><td>${user.address}</td></tr>
                  <tr><td><strong>City:</strong></td><td>${user.city}</td></tr>
                  <tr><td><strong>State:</strong></td><td>${user.state}</td></tr>
                  <tr><td><strong>Postal Code:</strong></td><td>${user.postal_code}</td></tr>
                  <tr><td><strong>Country:</strong></td><td>${user.country}</td></tr>
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
                      <div class="h5 mb-1">${user.employee_id || 'Not set'}</div>
                      <small class="text-muted">Employee ID</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">${user.is_active}</div>
                      <small class="text-muted">Account Status</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">${user.user_role}</div>
                      <small class="text-muted">User Role</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h5 mb-1">#${user.id}</div>
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
                      <div class="h6 mb-1">${user.created_at}</div>
                      <small class="text-muted">Created</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.updated_at}</div>
                      <small class="text-muted">Last Updated</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.last_login_at}</div>
                      <small class="text-muted">Last Login</small>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center p-3 border rounded">
                      <div class="h6 mb-1">${user.email_verified_at}</div>
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
      // Show loading state
      const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
      editModal.show();
      
      // Fetch user details to populate the form
      fetch(`get_user_details.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const user = data.user;
            populateEditForm(user);
          } else {
            alert('Error loading user details: ' + data.error);
            editModal.hide();
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to load user details. Please try again.');
          editModal.hide();
        });
    }

    function populateEditForm(user) {
      // Populate form fields with user data
      document.getElementById('editUserId').value = user.id;
      document.getElementById('editUserName').value = user.full_name || `${user.first_name} ${user.last_name}`;
      
      // Get the role select element
      const roleSelect = document.getElementById('editRole');
      const currentRoleOption = document.getElementById('currentRoleOption');
      
      // Clear existing options except the first "Select Role" option
      while (roleSelect.children.length > 1) {
        roleSelect.removeChild(roleSelect.lastChild);
      }
      
      // Add current role as first option (highlighted)
      if (user.user_role && user.user_role !== 'client') {
        const currentOption = document.createElement('option');
        currentOption.value = user.user_role;
        currentOption.textContent = `${user.user_role} (Current)`;
        currentOption.style.fontWeight = 'bold';
        currentOption.style.backgroundColor = '#e3f2fd';
        roleSelect.appendChild(currentOption);
      }
      
             // Add other available roles (excluding current role, employee, and client)
       const availableRoles = ['Administrator', 'Account Supervisor', 'Senior Account Executive', 'Junior Account Executive', 'Administrative Staff'];
      availableRoles.forEach(role => {
        if (role !== user.user_role) {
          const option = document.createElement('option');
          option.value = role;
          option.textContent = role;
          roleSelect.appendChild(option);
        }
      });
      
      // Set the current role as selected
      roleSelect.value = user.user_role || '';
    }

    function saveUserChanges() {
      // Get form data
      const formData = new FormData(document.getElementById('editUserForm'));
      
      // Validate required fields
      if (!formData.get('user_role')) {
        alert('Please select a user role.');
        return;
      }

      // Show loading state
      const saveBtn = document.querySelector('#editUserModal .btn-primary');
      const originalText = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Changing...';
      saveBtn.disabled = true;

      // Send update request
      fetch('update_user_role.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('User role changed successfully!');
          // Close modal and refresh page to show updated data
          const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
          editModal.hide();
          location.reload();
        } else {
          alert('Error changing user role: ' + data.error);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to change user role. Please try again.');
      })
      .finally(() => {
        // Reset button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
      });
    }

    function deleteUser(userId) {
      if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        alert('Delete user with ID: ' + userId);
        // In a real application, this would make an AJAX call to delete the user
      }
    }

    // Helper functions for badge colors (JavaScript versions)
    function getStatusBadgeColor(status) {
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
      switch (role.toLowerCase()) {
        case 'administrator':
          return 'danger';
        case 'administrative staff':
          return 'warning';
        case 'account supervisor':
          return 'info';
        case 'employee':
          return 'primary';
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
        case 'administrative staff':
            return 'warning';
        case 'account supervisor':
            return 'info';
        case 'employee':
            return 'primary';
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
