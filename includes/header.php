<?php
// Header section for ASF Surveillance System
$_publicCurrentUser = null;
$_publicProfileImage = 'uploads/profile-img.jpg';

if (isLoggedIn()) {
    $_publicCurrentUser = getCurrentUser();
    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
        }
        $stmt = $pdo->prepare("SELECT profile_image FROM user_accounts WHERE id = :id");
        $stmt->execute([':id' => $_publicCurrentUser['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['profile_image']) && file_exists(__DIR__ . '/../' . $row['profile_image'])) {
            $_publicProfileImage = $row['profile_image'];
        }
    } catch (Exception $e) {}
}
?>
  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center">
        <i class="bi bi-list toggle-sidebar-btn me-2" style="padding-left: 5px;"></i>
        <a href="index.php" class="logo d-flex align-items-center">
          <img src="uploads/asf_logo.png" alt="ASF Surveillance Logo" class="d-lg-none" style="height: 60px; margin-right: 15px; object-fit: contain;">
          <img src="uploads/asf_logo.png" alt="ASF Surveillance Logo" class="d-none d-lg-block" style="height: 60px; margin-right: 15px; object-fit: contain;">
          <div class="d-none d-lg-block" style="font-size: 1.6rem; color:rgb(77, 106, 130); white-space: nowrap; font-weight: bold;">CALABARZON</div>
        </a>
    </div><!-- End Logo -->


   
<?php
/*
 <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item dropdown">

          <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-bell"></i>
            <span class="badge bg-primary badge-number">4</span>
          </a><!-- End Notification Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow notifications">
            <li class="dropdown-header">
              You have 4 new notifications
              <a href="#"><span class="badge rounded-pill bg-primary p-2 ms-2">View all</span></a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="notification-item">
              <i class="bi bi-exclamation-circle text-warning"></i>
              <div>
                <h4>Lorem Ipsum</h4>
                <p>Quae dolorem earum veritatis oditseno</p>
                <p>30 min. ago</p>
              </div>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="notification-item">
              <i class="bi bi-x-circle text-danger"></i>
              <div>
                <h4>Atque rerum nesciunt</h4>
                <p>Quae dolorem earum veritatis oditseno</p>
                <p>1 hr. ago</p>
              </div>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="notification-item">
              <i class="bi bi-check-circle text-success"></i>
              <div>
                <h4>Sit rerum fuga</h4>
                <p>Quae dolorem earum veritatis oditseno</p>
                <p>2 hrs. ago</p>
              </div>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="notification-item">
              <i class="bi bi-info-circle text-primary"></i>
              <div>
                <h4>Dicta reprehenderit</h4>
                <p>Quae dolorem earum veritatis oditseno</p>
                <p>4 hrs. ago</p>
              </div>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>
            <li class="dropdown-footer">
              <a href="#">Show all notifications</a>
            </li>

          </ul><!-- End Notification Dropdown Items -->

        </li><!-- End Notification Nav -->

        <li class="nav-item dropdown">

          <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-chat-left-text"></i>
            <span class="badge bg-success badge-number">3</span>
          </a><!-- End Messages Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow messages">
            <li class="dropdown-header">
              You have 3 new messages
              <a href="#"><span class="badge rounded-pill bg-primary p-2 ms-2">View all</span></a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="message-item">
              <a href="#">
                <img src="assets/img/messages-1.jpg" alt="" class="rounded-circle">
                <div>
                  <h4>Maria Hudson</h4>
                  <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                  <p>4 hrs. ago</p>
                </div>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="message-item">
              <a href="#">
                <img src="assets/img/messages-2.jpg" alt="" class="rounded-circle">
                <div>
                  <h4>Anna Nelson</h4>
                  <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                  <p>6 hrs. ago</p>
                </div>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="message-item">
              <a href="#">
                <img src="assets/img/messages-3.jpg" alt="" class="rounded-circle">
                <div>
                  <h4>David Muldon</h4>
                  <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                  <p>8 hrs. ago</p>
                </div>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="dropdown-footer">
              <a href="#">Show all messages</a>
            </li>

          </ul><!-- End Messages Dropdown Items -->

        </li><!-- End Messages Nav -->

        <li class="nav-item dropdown pe-3">

          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="assets/img/profile-img.jpg" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2">K. Anderson</span>
          </a><!-- End Profile Iamge Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6>Kevin Anderson</h6>
              <span>Web Designer</span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="users-profile.php">
                <i class="bi bi-person"></i>
                <span>My Profile</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="users-profile.php">
                <i class="bi bi-gear"></i>
                <span>Account Settings</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="pages-faq.php">
                <i class="bi bi-question-circle"></i>
                <span>Need Help?</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="#">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign Out</span>
              </a>
            </li>

          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->

      </ul>
    </nav><!-- End Icons Navigation -->
*/
?>

<!-- login / profile section -->
        <div class="ms-auto">
          <?php if ($_publicCurrentUser): ?>
            <nav class="header-nav">
              <ul class="d-flex align-items-center mb-0">
                <li class="nav-item dropdown pe-3">
                  <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <span class="dropdown-toggle"><?php echo htmlspecialchars($_publicCurrentUser['name']); ?></span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                      <h6><?php echo htmlspecialchars($_publicCurrentUser['name']); ?></h6>
                      <span><?php echo htmlspecialchars($_publicCurrentUser['role']); ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if ($_publicCurrentUser['role'] === 'administrator'): ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center" href="admin/index.php">
                        <i class="bi bi-speedometer2 me-2"></i><span>Admin Dashboard</span>
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i><span>Sign Out</span>
                      </a>
                    </li>
                  </ul>
                </li>
              </ul>
            </nav>
          <?php else: ?>
            <div class="login-section">
              <a href="login.php" class="btn btn-primary login-btn">
                <i class="bi bi-person-circle me-2"></i>
                <span class="btn-text">Login</span>
                <div class="btn-overlay"></div>
              </a>
            </div>
          <?php endif; ?>
        </div>
  </header><!-- End Header -->
