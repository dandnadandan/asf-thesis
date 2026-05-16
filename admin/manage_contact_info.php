<?php
/**
 * Manage Contact Information - Admin Page
 * Allows administrators to update the contact information displayed on the landing page
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
$pageTitle = 'Manage Contact Information';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact_info'])) {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $facebook_url = trim($_POST['facebook_url']);
    $twitter_url = trim($_POST['twitter_url']);
    $linkedin_url = trim($_POST['linkedin_url']);
    $instagram_url = trim($_POST['instagram_url']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Check if record exists
            $check_query = "SELECT id FROM contact_information WHERE is_active = 1 LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute();
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing record
                $query = "UPDATE contact_information SET 
                          email = :email, 
                          phone = :phone, 
                          address = :address, 
                          facebook_url = :facebook_url, 
                          twitter_url = :twitter_url, 
                          linkedin_url = :linkedin_url, 
                          instagram_url = :instagram_url 
                          WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $existing['id']);
            } else {
                // Insert new record
                $query = "INSERT INTO contact_information 
                          (email, phone, address, facebook_url, twitter_url, linkedin_url, instagram_url, is_active) 
                          VALUES (:email, :phone, :address, :facebook_url, :twitter_url, :linkedin_url, :instagram_url, 1)";
                $stmt = $conn->prepare($query);
            }
            
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':facebook_url', $facebook_url);
            $stmt->bindParam(':twitter_url', $twitter_url);
            $stmt->bindParam(':linkedin_url', $linkedin_url);
            $stmt->bindParam(':instagram_url', $instagram_url);
            
            if ($stmt->execute()) {
                $success_message = "Contact information updated successfully!";
            } else {
                $error_message = "Failed to update contact information";
            }
        } catch (Exception $e) {
            $error_message = "Error updating contact information: " . $e->getMessage();
        }
    }
}

// Load current contact information
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "SELECT * FROM contact_information WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set default values if no record exists
    if (!$contact_info) {
        $contact_info = [
            'email' => '',
            'phone' => '',
            'address' => '',
            'facebook_url' => '',
            'twitter_url' => '',
            'linkedin_url' => '',
            'instagram_url' => ''
        ];
    }
} catch (Exception $e) {
    $error_message = "Error loading contact information: " . $e->getMessage();
    $contact_info = [
        'email' => '',
        'phone' => '',
        'address' => '',
        'facebook_url' => '',
        'twitter_url' => '',
        'linkedin_url' => '',
        'instagram_url' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .preview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .preview-card h3 {
            margin-bottom: 20px;
        }
        .preview-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .preview-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .social-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .social-badge {
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-gear"></i> Manage Contact Information</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Manage Contact Information</li>
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

            <div class="row">
                <!-- Preview Section -->
                <div class="col-lg-4">
                    <div class="preview-card">
                        <h3><i class="bi bi-eye"></i> Current Information</h3>
                        
                        <div class="preview-item">
                            <div class="preview-icon">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <div>
                                <small style="opacity: 0.8;">Email</small><br>
                                <strong><?php echo htmlspecialchars($contact_info['email']) ?: 'Not set'; ?></strong>
                            </div>
                        </div>
                        
                        <div class="preview-item">
                            <div class="preview-icon">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <div>
                                <small style="opacity: 0.8;">Phone</small><br>
                                <strong><?php echo htmlspecialchars($contact_info['phone']) ?: 'Not set'; ?></strong>
                            </div>
                        </div>
                        
                        <div class="preview-item">
                            <div class="preview-icon">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <div>
                                <small style="opacity: 0.8;">Address</small><br>
                                <strong><?php echo nl2br(htmlspecialchars($contact_info['address'])) ?: 'Not set'; ?></strong>
                            </div>
                        </div>
                        
                        <div class="preview-item">
                            <div class="preview-icon">
                                <i class="bi bi-share"></i>
                            </div>
                            <div>
                                <small style="opacity: 0.8;">Social Media</small><br>
                                <div class="social-preview mt-2">
                                    <?php if (!empty($contact_info['facebook_url'])): ?>
                                        <span class="social-badge"><i class="bi bi-facebook"></i> Facebook</span>
                                    <?php endif; ?>
                                    <?php if (!empty($contact_info['twitter_url'])): ?>
                                        <span class="social-badge"><i class="bi bi-twitter"></i> Twitter</span>
                                    <?php endif; ?>
                                    <?php if (!empty($contact_info['linkedin_url'])): ?>
                                        <span class="social-badge"><i class="bi bi-linkedin"></i> LinkedIn</span>
                                    <?php endif; ?>
                                    <?php if (!empty($contact_info['instagram_url'])): ?>
                                        <span class="social-badge"><i class="bi bi-instagram"></i> Instagram</span>
                                    <?php endif; ?>
                                    <?php if (empty($contact_info['facebook_url']) && empty($contact_info['twitter_url']) && 
                                              empty($contact_info['linkedin_url']) && empty($contact_info['instagram_url'])): ?>
                                        <span style="opacity: 0.6;">No social links</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="col-lg-8">
                    <div class="form-section">
                        <h3 class="mb-4"><i class="bi bi-pencil-square"></i> Update Contact Information</h3>
                        
                        <form method="POST">
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($contact_info['email']); ?>" required>
                                    <small class="text-muted">This will appear on the landing page</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($contact_info['phone']); ?>" required>
                                    <small class="text-muted">e.g., 09123456789</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($contact_info['address']); ?></textarea>
                                <small class="text-muted">Full business address</small>
                            </div>

                            <!-- Social Media Links -->
                            <h5 class="mb-3 mt-4"><i class="bi bi-share"></i> Social Media Links (Optional)</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook URL</label>
                                    <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars($contact_info['facebook_url']); ?>" placeholder="https://facebook.com/yourpage">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-twitter text-info"></i> Twitter URL</label>
                                    <input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars($contact_info['twitter_url']); ?>" placeholder="https://twitter.com/yourhandle">
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-linkedin text-primary"></i> LinkedIn URL</label>
                                    <input type="url" name="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($contact_info['linkedin_url']); ?>" placeholder="https://linkedin.com/in/yourprofile">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram URL</label>
                                    <input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars($contact_info['instagram_url']); ?>" placeholder="https://instagram.com/yourhandle">
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="update_contact_info" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                                <a href="view_contact_inquiries.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-envelope"></i> View Inquiries
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Info Box -->
                    <div class="alert alert-info mt-4">
                        <h5><i class="bi bi-info-circle"></i> Important Information</h5>
                        <ul class="mb-0">
                            <li>Changes will appear <strong>immediately</strong> on the landing page (index.php)</li>
                            <li>Email and phone fields are required</li>
                            <li>Social media links are optional</li>
                            <li>Leave social media fields empty to hide them from the landing page</li>
                            <li>Make sure URLs include <code>https://</code> or <code>http://</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

