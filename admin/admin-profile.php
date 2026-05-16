<?php
/**
 * Admin Profile Page for ASF Surveillance System
 * Allows administrators to view and edit their profile
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require administrator role - only administrators can access admin profile
requireRole(['administrator'], '../unauthorized.php');

// Additional RBAC check
if (!canAccessAdminProfile()) {
    header("Location: ../unauthorized.php");
    exit();
}

// Require administrator role
requireRole(['administrator', 'administrative staff', 'owner'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'My Profile';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize variables
$success_message = '';
$error_message = '';
$user_data = null;

// Fetch user data from database
try {
    $stmt = $pdo->prepare("SELECT * FROM user_accounts WHERE id = :user_id");
    $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    $error_message = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Update basic information
    if ($_POST['action'] === 'update_profile') {
        try {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $organization = trim($_POST['organization'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $province = trim($_POST['province'] ?? 'CALABARZON');
            $postal_code = trim($_POST['postal_code'] ?? '');
            
            // Validate inputs
            if (empty($first_name) || empty($last_name) || empty($email)) {
                throw new Exception("First name, last name, and email are required");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE email = :email AND id != :user_id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Email is already taken by another user");
            }
            
            // Check if username is already taken by another user
            if (!empty($username)) {
                $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE username = :username AND id != :user_id");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Username is already taken by another user");
                }
            }
            
            // Update user data
            $stmt = $pdo->prepare("UPDATE user_accounts SET 
                first_name = :first_name,
                last_name = :last_name,
                username = :username,
                email = :email,
                phone = :phone,
                organization = :organization,
                address = :address,
                city = :city,
                province = :province,
                postal_code = :postal_code,
                updated_at = NOW()
                WHERE id = :user_id");
            
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':organization', $organization);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':province', $province);
            $stmt->bindParam(':postal_code', $postal_code);
            $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Update session data
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['username'] = $username;
                
                $success_message = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM user_accounts WHERE id = :user_id");
                $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
                $stmt->execute();
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentUser = getCurrentUser();
            } else {
                throw new Exception("Failed to update profile");
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    // Change password
    if ($_POST['action'] === 'change_password') {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All password fields are required");
            }
            
            // Verify current password
            if (!password_verify($current_password, $user_data['password_hash'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Validate new password
            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            
            if ($current_password === $new_password) {
                throw new Exception("New password must be different from current password");
            }
            
            // Hash new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $pdo->prepare("UPDATE user_accounts SET 
                password_hash = :password_hash,
                updated_at = NOW()
                WHERE id = :user_id");
            
            $stmt->bindParam(':password_hash', $new_password_hash);
            $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                throw new Exception("Failed to change password");
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    // Upload profile picture
    if ($_POST['action'] === 'upload_picture') {
        try {
            if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception("Please select a file to upload");
            }
            
            $file = $_FILES['profile_picture'];
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file");
            }
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size must not exceed 5MB");
            }
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, and GIF images are allowed");
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $currentUser['id'] . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($user_data['profile_image']) && file_exists('../' . $user_data['profile_image'])) {
                    unlink('../' . $user_data['profile_image']);
                }
                
                // Update database
                $profile_image_path = 'uploads/profiles/' . $filename;
                $stmt = $pdo->prepare("UPDATE user_accounts SET 
                    profile_image = :profile_image,
                    updated_at = NOW()
                    WHERE id = :user_id");
                
                $stmt->bindParam(':profile_image', $profile_image_path);
                $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success_message = "Profile picture updated successfully!";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM user_accounts WHERE id = :user_id");
                    $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    // Delete uploaded file if database update fails
                    unlink($upload_path);
                    throw new Exception("Failed to update profile picture in database");
                }
            } else {
                throw new Exception("Failed to move uploaded file");
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .profile-card .card-body {
      padding: 30px;
    }
    
    .profile-picture-container {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .profile-picture {
      width: 160px;
      height: 120px;
      border-radius: 100%;
      object-fit: cover;
      border: 5px solid #f0f0f0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .profile-info-row {
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .profile-info-label {
      font-weight: 600;
      color: #666;
      margin-bottom: 5px;
    }
    
    .profile-info-value {
      color: #333;
      font-size: 15px;
    }
    
    .badge-status {
      font-size: 12px;
      padding: 5px 10px;
    }
    
    .nav-tabs-bordered .nav-link {
      border: 2px solid transparent;
      border-bottom: 0;
      font-weight: 500;
    }
    
    .nav-tabs-bordered .nav-link.active {
      background: #fff;
      border-color: #dee2e6 #dee2e6 #fff;
      color: #012970;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>My Profile</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Profile</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
      
      <?php if ($success_message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>
      
      <?php if ($error_message): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>
      
      <div class="row">
        
        <!-- Left Side - Profile Overview -->
        <div class="col-xl-4">
          
          <div class="card profile-card">
            <div class="card-body pt-4">
              
              <div class="profile-picture-container">
                <?php 
                $profile_image = !empty($user_data['profile_image']) && file_exists('../' . $user_data['profile_image']) 
                    ? '../' . $user_data['profile_image'] 
                    : '../bootstrap/assets/img/profile-img.jpg';
                ?>
                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile-picture">
              </div>
              
              <h3 class="text-center"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h3>
              <div class="text-center mb-3">
                <span class="badge bg-primary"><?php echo htmlspecialchars(ucwords($user_data['user_role'])); ?></span>
                <?php if ($user_data['is_verified']): ?>
                <span class="badge bg-success">Verified</span>
                <?php else: ?>
                <span class="badge bg-warning">Not Verified</span>
                <?php endif; ?>
                <?php if ($user_data['is_active']): ?>
                <span class="badge bg-info">Active</span>
                <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </div>
              
              <hr>
              
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-person"></i> Username
                </div>
                <div class="profile-info-value">
                  <?php echo htmlspecialchars($user_data['username'] ?: 'Not set'); ?>
                </div>
              </div>
              
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-envelope"></i> Email
                </div>
                <div class="profile-info-value">
                  <?php echo htmlspecialchars($user_data['email']); ?>
                </div>
              </div>
              
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-phone"></i> Phone
                </div>
                <div class="profile-info-value">
                  <?php echo htmlspecialchars($user_data['phone'] ?: 'Not set'); ?>
                </div>
              </div>
              
              <?php if (!empty($user_data['organization'])): ?>
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-building"></i> Organization
                </div>
                <div class="profile-info-value">
                  <?php echo htmlspecialchars($user_data['organization']); ?>
                </div>
              </div>
              <?php endif; ?>
              
              <?php if (!empty($user_data['address']) || !empty($user_data['city']) || !empty($user_data['province'])): ?>
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-geo-alt"></i> Address
                </div>
                <div class="profile-info-value">
                  <?php 
                  $address_parts = array_filter([
                    $user_data['address'],
                    $user_data['city'],
                    $user_data['province'],
                    $user_data['postal_code']
                  ]);
                  echo htmlspecialchars(implode(', ', $address_parts) ?: 'Not set');
                  ?>
                </div>
              </div>
              <?php endif; ?>
              
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-calendar"></i> Member Since
                </div>
                <div class="profile-info-value">
                  <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>
                </div>
              </div>
              
              <?php if ($user_data['last_login_at']): ?>
              <div class="profile-info-row">
                <div class="profile-info-label">
                  <i class="bi bi-clock"></i> Last Login
                </div>
                <div class="profile-info-value">
                  <?php echo date('F j, Y g:i A', strtotime($user_data['last_login_at'])); ?>
                </div>
              </div>
              <?php endif; ?>
              
            </div>
          </div>
          
        </div>
        
        <!-- Right Side - Edit Profile -->
        <div class="col-xl-8">
          
          <div class="card">
            <div class="card-body pt-3">
              
              <!-- Bordered Tabs -->
              <ul class="nav nav-tabs nav-tabs-bordered">
                
                <li class="nav-item">
                  <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview">Overview</button>
                </li>
                
                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit">Edit Profile</button>
                </li>
                
                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password">Change Password</button>
                </li>
                
                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-picture">Profile Picture</button>
                </li>
                
              </ul>
              
              <div class="tab-content pt-4">
                
                <!-- Overview Tab -->
                <div class="tab-pane fade show active profile-overview" id="profile-overview">
                  
                  <h5 class="card-title">Profile Details</h5>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Full Name</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Username</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['username'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Email</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['email']); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Phone</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['phone'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Organization</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['organization'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Address</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['address'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">City</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['city'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Province</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['province'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Postal Code</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars($user_data['postal_code'] ?: 'Not set'); ?></div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-lg-3 col-md-4 label fw-bold">Role</div>
                    <div class="col-lg-9 col-md-8"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user_data['user_role']))); ?></div>
                  </div>
                  
                </div><!-- End Overview Tab -->
                
                <!-- Edit Profile Tab -->
                <div class="tab-pane fade profile-edit" id="profile-edit">
                  
                  <h5 class="card-title">Edit Profile</h5>
                  
                  <form method="POST" action="admin-profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row mb-3">
                      <label for="first_name" class="col-md-4 col-lg-3 col-form-label">First Name <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="last_name" class="col-md-4 col-lg-3 col-form-label">Last Name <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="username" class="col-md-4 col-lg-3 col-form-label">Username</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>">
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="email" class="col-md-4 col-lg-3 col-form-label">Email <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="phone" class="col-md-4 col-lg-3 col-form-label">Phone</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="organization" class="col-md-4 col-lg-3 col-form-label">Organization</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="organization" name="organization" value="<?php echo htmlspecialchars($user_data['organization'] ?? ''); ?>">
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="address" class="col-md-4 col-lg-3 col-form-label">Address</label>
                      <div class="col-md-8 col-lg-9">
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="city" class="col-md-4 col-lg-3 col-form-label">City</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>">
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="province" class="col-md-4 col-lg-3 col-form-label">Province</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($user_data['province'] ?? 'CALABARZON'); ?>">
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="postal_code" class="col-md-4 col-lg-3 col-form-label">Postal Code</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user_data['postal_code'] ?? ''); ?>">
                      </div>
                    </div>
                    
                    <div class="text-center">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
                      </button>
                    </div>
                  </form>
                  
                </div><!-- End Edit Profile Tab -->
                
                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="profile-change-password">
                  
                  <h5 class="card-title">Change Password</h5>
                  
                  <form method="POST" action="admin-profile.php">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="row mb-3">
                      <label for="current_password" class="col-md-4 col-lg-3 col-form-label">Current Password <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="new_password" class="col-md-4 col-lg-3 col-form-label">New Password <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Password must be at least 8 characters long</small>
                      </div>
                    </div>
                    
                    <div class="row mb-3">
                      <label for="confirm_password" class="col-md-4 col-lg-3 col-form-label">Confirm New Password <span class="text-danger">*</span></label>
                      <div class="col-md-8 col-lg-9">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                      </div>
                    </div>
                    
                    <div class="text-center">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key"></i> Change Password
                      </button>
                    </div>
                  </form>
                  
                </div><!-- End Change Password Tab -->
                
                <!-- Profile Picture Tab -->
                <div class="tab-pane fade" id="profile-picture">
                  
                  <h5 class="card-title">Profile Picture</h5>
                  
                  <div class="row mb-4">
                    <div class="col-12 text-center">
                      <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 250px; height: 250px; border-radius: 50%; object-fit: cover; border: 5px solid #f0f0f0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    </div>
                  </div>
                  
                  <form method="POST" action="admin-profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_picture">
                    
                    <div class="row mb-3">
                      <label for="profile_picture" class="col-md-4 col-lg-3 col-form-label">Choose New Picture</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                        <small class="text-muted">Allowed formats: JPG, PNG, GIF. Maximum size: 5MB</small>
                      </div>
                    </div>
                    
                    <div class="text-center">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload Picture
                      </button>
                    </div>
                  </form>
                  
                </div><!-- End Profile Picture Tab -->
                
              </div><!-- End Bordered Tabs -->
              
            </div>
          </div>
          
        </div>
        
      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

</body>

</html>

