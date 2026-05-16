<?php
/**
 * Admin Messages Hub
 * Shows all conversations and allows admins to chat with any user
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require admin/owner roles
requireRole(['owner', 'administrator', 'administrative staff'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'Messages';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get conversation ID
function getConversationId($userId1, $userId2) {
    $ids = [$userId1, $userId2];
    sort($ids);
    return 'conv_' . $ids[0] . '_' . $ids[1];
}

// Get all users with message stats (excluding current admin)
function getAllUserConversations($pdo, $adminId) {
    $sql = "SELECT 
                ua.id,
                ua.first_name,
                ua.last_name,
                ua.email,
                ua.user_role,
                ua.phone,
                ua.is_verified
            FROM user_accounts ua
            WHERE ua.id != ?
            ORDER BY ua.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get message stats for each user
    foreach ($users as &$user) {
        $conversationId = getConversationId($adminId, $user['id']);
        
        // Get last message
        $stmt = $pdo->prepare("SELECT m.*, ua.first_name, ua.last_name 
                              FROM messages m
                              LEFT JOIN user_accounts ua ON m.sender_id = ua.id
                              WHERE m.conversation_id = ? 
                                AND m.is_deleted = FALSE
                              ORDER BY m.created_at DESC 
                              LIMIT 1");
        $stmt->execute([$conversationId]);
        $user['last_message'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) 
                              FROM messages m
                              LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                              WHERE m.conversation_id = ? 
                                AND m.sender_id != ?
                                AND (ms.status IS NULL OR ms.status != 'read')
                                AND m.is_deleted = FALSE");
        $stmt->execute([$adminId, $conversationId, $adminId]);
        $user['unread_count'] = $stmt->fetchColumn();
        
        // Get total message count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages 
                              WHERE conversation_id = ? AND is_deleted = FALSE");
        $stmt->execute([$conversationId]);
        $user['total_messages'] = $stmt->fetchColumn();
        
        // Get service count for clients
        if ($user['user_role'] === 'client') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tax_filing_services WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $user['service_count'] = $stmt->fetchColumn();
        } else {
            $user['service_count'] = 0;
        }
    }
    
    return $users;
}

// Helper function for time ago
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d', $time);
    }
}

// Get search and role filter parameters
$searchQuery = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';

$conversations = getAllUserConversations($pdo, $currentUser['id']);

// Filter by search if provided
if (!empty($searchQuery)) {
    $conversations = array_filter($conversations, function($conv) use ($searchQuery) {
        $name = strtolower($conv['first_name'] . ' ' . $conv['last_name']);
        $email = strtolower($conv['email']);
        $role = strtolower($conv['user_role']);
        $search = strtolower($searchQuery);
        return strpos($name, $search) !== false || 
               strpos($email, $search) !== false || 
               strpos($role, $search) !== false;
    });
}

// Filter by role if provided
if ($roleFilter !== 'all') {
    $conversations = array_filter($conversations, function($conv) use ($roleFilter) {
        return $conv['user_role'] === $roleFilter;
    });
}

// Sort by last message time and unread status
usort($conversations, function($a, $b) {
    // Unread messages first
    if ($a['unread_count'] != $b['unread_count']) {
        return $b['unread_count'] - $a['unread_count'];
    }
    // Then by last message time
    $timeA = $a['last_message'] ? strtotime($a['last_message']['created_at']) : 0;
    $timeB = $b['last_message'] ? strtotime($b['last_message']['created_at']) : 0;
    return $timeB - $timeA;
});

// Calculate totals
$totalUnread = array_sum(array_column($conversations, 'unread_count'));
$totalConversations = count($conversations);
$activeConversations = count(array_filter($conversations, function($c) { return $c['total_messages'] > 0; }));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title><?php echo $pageTitle; ?> - TaxEase Admin</title>
  <style>
    .conversation-item {
      padding: 15px;
      border-bottom: 1px solid #dee2e6;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
      display: block;
      color: inherit;
    }
    
    .conversation-item:hover {
      background: #f8f9fa;
      color: inherit;
    }
    
    .conversation-item.unread {
      background: #e7f3ff;
    }
    
    .conversation-item.unread:hover {
      background: #d0e9ff;
    }
    
    .conversation-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    
    .conversation-avatar.client {
      background: linear-gradient(135deg, #2eca6a 0%, #20c997 100%);
    }
    
    .conversation-avatar.admin {
      background: linear-gradient(135deg, #ff771d 0%, #dc3545 100%);
    }
    
    .conversation-avatar.employee {
      background: linear-gradient(135deg, #4154f1 0%, #6366f1 100%);
    }
    
    .conversation-details {
      flex: 1;
      min-width: 0;
    }
    
    .conversation-name {
      font-weight: 600;
      margin-bottom: 4px;
    }
    
    .conversation-item.unread .conversation-name {
      font-weight: 700;
      color: #0d6efd;
    }
    
    .conversation-preview {
      font-size: 0.875rem;
      color: #6c757d;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .conversation-item.unread .conversation-preview {
      font-weight: 600;
      color: #212529;
    }
    
    .conversation-meta {
      text-align: right;
      min-width: 80px;
      flex-shrink: 0;
    }
    
    .conversation-time {
      font-size: 0.75rem;
      color: #6c757d;
      margin-bottom: 4px;
    }
    
    .unread-badge {
      background: #0d6efd;
      color: white;
      border-radius: 12px;
      padding: 2px 8px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }
    
    .empty-state i {
      font-size: 4rem;
      color: #dee2e6;
      margin-bottom: 20px;
    }
    
    .search-bar {
      border-bottom: 2px solid #dee2e6;
      padding: 15px;
      background: white;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .messages-list {
      max-height: calc(100vh - 350px);
      overflow-y: auto;
    }
    
    .stats-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      margin-bottom: 20px;
    }
    
    .stats-card .card-body {
      padding: 20px;
    }
    
    .stat-item {
      text-align: center;
    }
    
    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 0.875rem;
      opacity: 0.9;
    }
    
    .role-badge {
      font-size: 0.7rem;
      padding: 2px 6px;
      border-radius: 4px;
    }
    
    .role-client { background: #d1f4e0; color: #0f5132; }
    .role-admin { background: #f8d7da; color: #842029; }
    .role-employee { background: #cfe2ff; color: #084298; }
    .role-other { background: #e2e3e5; color: #41464b; }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Messages</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Messages</li>
        </ol>
      </nav>
    </div>

    <section class="section">
      <div class="row">
        
        <!-- Statistics Card -->
        <div class="col-lg-12">
          <div class="card stats-card">
            <div class="card-body">
              <div class="row">
                <div class="col-md-3">
                  <div class="stat-item">
                    <div class="stat-number"><?php echo $totalConversations; ?></div>
                    <div class="stat-label">Total Users</div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-item">
                    <div class="stat-number"><?php echo $activeConversations; ?></div>
                    <div class="stat-label">Active Conversations</div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-item">
                    <div class="stat-number"><?php echo $totalUnread; ?></div>
                    <div class="stat-label">Unread Messages</div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="stat-item">
                    <div class="stat-number"><?php echo array_sum(array_column($conversations, 'total_messages')); ?></div>
                    <div class="stat-label">Total Messages</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Conversations List -->
        <div class="col-lg-12">
          <div class="card">
            <div class="search-bar">
              <form method="GET" class="row g-2">
                <div class="col-md-5">
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input 
                      type="text" 
                      name="search" 
                      class="form-control" 
                      placeholder="Search by name, email, or role..." 
                      value="<?php echo htmlspecialchars($searchQuery); ?>">
                  </div>
                </div>
                <div class="col-md-3">
                  <select name="role" class="form-select">
                    <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="client" <?php echo $roleFilter === 'client' ? 'selected' : ''; ?>>Clients</option>
                    <option value="senior account executive" <?php echo $roleFilter === 'senior account executive' ? 'selected' : ''; ?>>Senior Account Executives</option>
                    <option value="junior account executive" <?php echo $roleFilter === 'junior account executive' ? 'selected' : ''; ?>>Junior Account Executives</option>
                    <option value="administrator" <?php echo $roleFilter === 'administrator' ? 'selected' : ''; ?>>Administrators</option>
                    <option value="administrative staff" <?php echo $roleFilter === 'administrative staff' ? 'selected' : ''; ?>>Administrative Staff</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <div class="btn-group w-100" role="group">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($searchQuery) || $roleFilter !== 'all'): ?>
                    <a href="messages.php" class="btn btn-secondary">
                      <i class="bi bi-x-circle"></i> Clear
                    </a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>
            
            <div class="messages-list">
              <?php if (empty($conversations)): ?>
              <div class="empty-state">
                <i class="bi bi-search"></i>
                <h4>No Users Found</h4>
                <p class="text-muted">Try a different search term or clear filters</p>
                <?php if (!empty($searchQuery) || $roleFilter !== 'all'): ?>
                <a href="messages.php" class="btn btn-primary mt-3">View All Users</a>
                <?php endif; ?>
              </div>
              <?php else: ?>
              
              <?php foreach ($conversations as $conversation): ?>
              <?php 
              // Determine avatar class based on role
              $avatarClass = '';
              if ($conversation['user_role'] === 'client') {
                  $avatarClass = 'client';
              } elseif (in_array($conversation['user_role'], ['administrator', 'administrative staff', 'owner'])) {
                  $avatarClass = 'admin';
              } elseif (in_array($conversation['user_role'], ['senior account executive', 'junior account executive'])) {
                  $avatarClass = 'employee';
              }
              
              // Determine role badge class
              $roleBadgeClass = '';
              if ($conversation['user_role'] === 'client') {
                  $roleBadgeClass = 'role-client';
              } elseif (in_array($conversation['user_role'], ['administrator', 'administrative staff', 'owner'])) {
                  $roleBadgeClass = 'role-admin';
              } elseif (in_array($conversation['user_role'], ['senior account executive', 'junior account executive'])) {
                  $roleBadgeClass = 'role-employee';
              } else {
                  $roleBadgeClass = 'role-other';
              }
              ?>
              <a href="admin-user-messages.php?user_id=<?php echo $conversation['id']; ?>" 
                 class="conversation-item <?php echo $conversation['unread_count'] > 0 ? 'unread' : ''; ?>">
                <div class="d-flex gap-3 align-items-center">
                  
                  <!-- Avatar -->
                  <div class="conversation-avatar <?php echo $avatarClass; ?>">
                    <?php echo strtoupper(substr($conversation['first_name'], 0, 1) . substr($conversation['last_name'], 0, 1)); ?>
                  </div>
                  
                  <!-- Details -->
                  <div class="conversation-details">
                    <div class="conversation-name">
                      <?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?>
                      <span class="role-badge <?php echo $roleBadgeClass; ?> ms-2">
                        <?php echo ucwords($conversation['user_role']); ?>
                      </span>
                      <?php if (!$conversation['is_verified']): ?>
                      <i class="bi bi-exclamation-circle text-warning ms-1" title="Not verified"></i>
                      <?php endif; ?>
                    </div>
                    
                    <?php if ($conversation['last_message']): ?>
                    <div class="conversation-preview">
                      <?php if ($conversation['last_message']['sender_id'] == $currentUser['id']): ?>
                        <i class="bi bi-check-all me-1"></i> You: 
                      <?php else: ?>
                        <?php echo htmlspecialchars($conversation['last_message']['first_name']); ?>: 
                      <?php endif; ?>
                      <?php 
                      if (!empty($conversation['last_message']['message_text'])) {
                          echo htmlspecialchars(substr($conversation['last_message']['message_text'], 0, 50));
                          if (strlen($conversation['last_message']['message_text']) > 50) echo '...';
                      } else {
                          echo '<i class="bi bi-paperclip"></i> Attachment';
                      }
                      ?>
                    </div>
                    <?php else: ?>
                    <div class="conversation-preview text-muted">
                      <i class="bi bi-info-circle"></i> No messages yet - Start a conversation
                    </div>
                    <?php endif; ?>
                    
                    <small class="text-muted">
                      <i class="bi bi-envelope"></i> <?php echo $conversation['total_messages']; ?> message(s)
                      <?php if ($conversation['user_role'] === 'client' && $conversation['service_count'] > 0): ?>
                      | <i class="bi bi-briefcase"></i> <?php echo $conversation['service_count']; ?> service(s)
                      <?php endif; ?>
                    </small>
                  </div>
                  
                  <!-- Meta -->
                  <div class="conversation-meta">
                    <?php if ($conversation['last_message']): ?>
                    <div class="conversation-time">
                      <?php echo timeAgo($conversation['last_message']['created_at']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conversation['unread_count'] > 0): ?>
                    <div class="unread-badge">
                      <?php echo $conversation['unread_count']; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                  
                </div>
              </a>
              <?php endforeach; ?>
              
              <?php endif; ?>
            </div>
          </div>
        </div>
        
      </div>
    </section>

  </main>

  <?php include 'includes/footer.php'; ?>

  <script>
  // Auto-refresh unread counts every 30 seconds
  setInterval(function() {
    location.reload();
  }, 30000);
  
  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      document.querySelector('input[name="search"]').focus();
    }
  });
  </script>

</body>

</html>
