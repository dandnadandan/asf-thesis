<?php
/**
 * Admin Dashboard Header Section
 * Contains the fixed top header with navigation elements
 */

// Ensure currentUser is available
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}

// Get user's profile picture from database
$user_profile_image = '../bootstrap/assets/img/profile-img.jpg'; // Default image
if (isset($currentUser['id'])) {
    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../../config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
        }
        
        $stmt = $pdo->prepare("SELECT profile_image FROM user_accounts WHERE id = :user_id");
        $stmt->bindParam(':user_id', $currentUser['id'], PDO::PARAM_INT);
        $stmt->execute();
        $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($profile_data && !empty($profile_data['profile_image']) && file_exists(__DIR__ . '/../../' . $profile_data['profile_image'])) {
            $user_profile_image = '../' . $profile_data['profile_image'];
        }
    } catch (Exception $e) {
        // Keep default image if any error occurs
        error_log("Error loading profile image: " . $e->getMessage());
    }
}
?>

<!-- ======= Header ======= -->
<header id="header" class="header fixed-top d-flex align-items-center">

<div class="d-flex align-items-center">
        <i class="bi bi-list toggle-sidebar-btn me-2" style="padding-left: 5px;"></i>
        <a href="../index.php" class="logo d-flex align-items-center">
          <img src="../uploads/asf_logo.png" alt="ASF Surveillance Logo" class="d-lg-none" style="height: 60px; margin-right: 15px; object-fit: contain;">
          <img src="../uploads/asf_logo.png" alt="ASF Surveillance Logo" class="d-none d-lg-block" style="height: 60px; margin-right: 15px; object-fit: contain;">
          <div class="d-none d-lg-block" style="font-size: 1.6rem; color:rgb(77, 106, 130); white-space: nowrap; font-weight: bold;">CALABARZON</div>
        </a>
    </div><!-- End Logo -->

  <nav class="header-nav ms-auto">
    <ul class="d-flex align-items-center">

      <li class="nav-item dropdown pe-3">

        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
          <img src="<?php echo htmlspecialchars($user_profile_image); ?>" alt="Profile" class="rounded-circle">
          <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo htmlspecialchars($currentUser['name']); ?></span>
        </a><!-- End Profile Iamge Icon -->

        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
          <li class="dropdown-header">
            <h6><?php echo htmlspecialchars($currentUser['name']); ?></h6>
            <span><?php echo htmlspecialchars($currentUser['role']); ?></span>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="admin-profile.php">
              <i class="bi bi-person"></i>
              <span>My Profile</span>
            </a>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="../logout.php">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sign Out</span>
            </a>
          </li>

        </ul><!-- End Profile Dropdown Items -->
      </li><!-- End Profile Nav -->

    </ul>
  </nav><!-- End Icons Navigation -->

</header><!-- End Header -->
