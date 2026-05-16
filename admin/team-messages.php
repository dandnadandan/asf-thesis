<?php
/**
 * Admin Team Messages - Group Chat for Admin
 * Supports group messaging between admin and employees
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require admin/owner roles
requireRole(['owner', 'administrator'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'Team Messages';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get group ID from URL
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Get all groups user is part of
function getUserGroups($pdo, $userId) {
    $sql = "SELECT gc.*, 
                   COUNT(DISTINCT gp.user_id) as member_count,
                   MAX(gm.created_at) as last_message_time
            FROM group_conversations gc
            JOIN group_participants gp ON gc.id = gp.group_id
            LEFT JOIN group_messages gm ON gc.id = gm.group_id
            WHERE gc.id IN (SELECT group_id FROM group_participants WHERE user_id = ?)
              AND gc.is_active = TRUE
            GROUP BY gc.id
            ORDER BY last_message_time DESC, gc.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get last message and unread count for each group
    foreach ($groups as &$group) {
        // Get last message
        $stmt = $pdo->prepare("SELECT gm.*, ua.first_name, ua.last_name 
                              FROM group_messages gm
                              LEFT JOIN user_accounts ua ON gm.sender_id = ua.id
                              WHERE gm.group_id = ? AND gm.is_deleted = FALSE
                              ORDER BY gm.created_at DESC 
                              LIMIT 1");
        $stmt->execute([$group['id']]);
        $group['last_message'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) 
                              FROM group_messages gm
                              LEFT JOIN group_message_status gms ON gm.id = gms.message_id AND gms.user_id = ?
                              WHERE gm.group_id = ? 
                                AND gm.sender_id != ?
                                AND (gms.status IS NULL OR gms.status != 'read')
                                AND gm.is_deleted = FALSE");
        $stmt->execute([$userId, $group['id'], $userId]);
        $group['unread_count'] = $stmt->fetchColumn();
        
        // Get participant names
        $stmt = $pdo->prepare("SELECT ua.first_name, ua.last_name 
                              FROM user_accounts ua
                              JOIN group_participants gp ON ua.id = gp.user_id
                              WHERE gp.group_id = ?
                              LIMIT 3");
        $stmt->execute([$group['id']]);
        $group['participants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $groups;
}

// Get group details
function getGroupInfo($pdo, $groupId, $userId) {
    // Check if user is member
    $stmt = $pdo->prepare("SELECT * FROM group_participants WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $userId]);
    if (!$stmt->fetch()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT gc.*, 
                                  ua.first_name as creator_first_name,
                                  ua.last_name as creator_last_name
                           FROM group_conversations gc
                           LEFT JOIN user_accounts ua ON gc.created_by = ua.id
                           WHERE gc.id = ? AND gc.is_active = TRUE");
    $stmt->execute([$groupId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get group participants
function getGroupParticipants($pdo, $groupId) {
    $stmt = $pdo->prepare("SELECT ua.*, gp.is_admin, gp.joined_at
                          FROM user_accounts ua
                          JOIN group_participants gp ON ua.id = gp.user_id
                          WHERE gp.group_id = ?
                          ORDER BY gp.is_admin DESC, ua.first_name");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all groups
$allGroups = getUserGroups($pdo, $currentUser['id']);

// If no group_id and have groups, use first one
if ($groupId == 0 && !empty($allGroups)) {
    $groupId = $allGroups[0]['id'];
}

// Get current group info and participants
$groupInfo = null;
$groupParticipants = [];
if ($groupId > 0) {
    $groupInfo = getGroupInfo($pdo, $groupId, $currentUser['id']);
    if ($groupInfo) {
        $groupParticipants = getGroupParticipants($pdo, $groupId);
        
        // Update last read time
        $stmt = $pdo->prepare("UPDATE group_participants SET last_read_at = NOW() 
                              WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $currentUser['id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .chat-container {
      height: calc(100vh - 300px);
      min-height: 500px;
      display: flex;
      flex-direction: column;
    }
    
    .messages-container {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    
    .message {
      margin-bottom: 15px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    
    .message.sent {
      justify-content: flex-end;
    }
    
    .message.received {
      justify-content: flex-start;
    }
    
    .sender-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 0.8rem;
      flex-shrink: 0;
    }
    
    .message-bubble {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: 18px;
      position: relative;
      word-wrap: break-word;
    }
    
    .message.sent .message-bubble {
      background: #0d6efd;
      color: white;
      border-bottom-right-radius: 4px;
    }
    
    .message.received .message-bubble {
      background: white;
      color: #212529;
      border: 1px solid #dee2e6;
      border-bottom-left-radius: 4px;
    }
    
    .sender-name {
      font-size: 0.75rem;
      font-weight: 600;
      margin-bottom: 2px;
      opacity: 0.9;
    }
    
    .message-time {
      font-size: 0.7rem;
      opacity: 0.7;
      margin-top: 4px;
    }
    
    .message-status {
      font-size: 0.65rem;
      margin-top: 2px;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .message.sent .message-status {
      color: #e3f2fd;
      justify-content: flex-end;
    }
    
    .typing-indicator {
      display: none;
      padding: 10px 20px;
      font-style: italic;
      color: #6c757d;
      align-items: center;
      gap: 8px;
    }
    
    .typing-indicator.active {
      display: flex;
    }
    
    .typing-dots {
      display: inline-flex;
      gap: 4px;
      align-items: center;
    }
    
    .typing-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #6c757d;
      animation: typing-bounce 1.4s infinite;
    }
    
    .typing-dot:nth-child(1) { animation-delay: 0s; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    
    @keyframes typing-bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-10px); }
    }
    
    /* Sidebar Styles */
    .groups-sidebar {
      border-right: 2px solid #dee2e6;
      height: calc(100vh - 200px);
      overflow-y: auto;
      background: #f8f9fa;
    }
    
    .group-item {
      padding: 12px 15px;
      border-bottom: 1px solid #dee2e6;
      cursor: pointer;
      transition: background 0.2s;
      background: white;
    }
    
    .group-item:hover {
      background: #e9ecef;
    }
    
    .group-item.active {
      background: #e7f3ff;
      border-left: 3px solid #0d6efd;
    }
    
    .group-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 1rem;
      flex-shrink: 0;
    }
    
    .group-info {
      flex: 1;
      min-width: 0;
    }
    
    .group-name {
      font-weight: 600;
      font-size: 0.9rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .group-preview {
      font-size: 0.75rem;
      color: #6c757d;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .group-item.active .group-name {
      color: #0d6efd;
    }
    
    .unread-badge {
      background: #0d6efd;
      color: white;
      border-radius: 10px;
      padding: 2px 6px;
      font-size: 0.7rem;
      font-weight: 600;
      min-width: 18px;
      text-align: center;
    }
    
    .sidebar-header {
      padding: 15px;
      background: white;
      border-bottom: 2px solid #dee2e6;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .participants-badge {
      font-size: 0.7rem;
      color: #6c757d;
    }
    
    .message-input-container {
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 10px;
      background: white;
    }
    
    .message-input {
      border: none;
      outline: none;
      resize: none;
      width: 100%;
      max-height: 120px;
    }
    
    .attachment-preview {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    
    .attachment-item {
      position: relative;
      width: 80px;
      height: 80px;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      overflow: hidden;
    }
    
    .attachment-item img,
    .attachment-item video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .attachment-item .remove-attachment {
      position: absolute;
      top: 2px;
      right: 2px;
      background: rgba(220, 53, 69, 0.9);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 12px;
    }
    
    .participant-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: #e9ecef;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8rem;
      margin: 2px;
    }
    
    .participant-chip.admin {
      background: #d1ecf1;
      color: #0c5460;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Team Messages</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Team Messages</li>
        </ol>
      </nav>
    </div>

    <section class="section">
      <div class="row">
        
        <!-- Groups Sidebar -->
        <div class="col-lg-3">
          <div class="card">
            <div class="sidebar-header">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0"><i class="bi bi-people-fill"></i> Groups</h6>
                  <small class="text-muted"><?php echo count($allGroups); ?> conversations</small>
                </div>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                  <i class="bi bi-plus-circle"></i>
                </button>
              </div>
            </div>
            <div class="groups-sidebar">
              <?php if (empty($allGroups)): ?>
              <div class="text-center py-4">
                <i class="bi bi-chat-square-dots" style="font-size: 2rem; color: #dee2e6;"></i>
                <p class="text-muted small mt-2">No groups yet</p>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                  Create Group
                </button>
              </div>
              <?php else: ?>
              <?php foreach ($allGroups as $group): ?>
              <div class="group-item <?php echo $group['id'] == $groupId ? 'active' : ''; ?>" 
                   onclick="window.location.href='team-messages.php?group_id=<?php echo $group['id']; ?>'">
                <div class="d-flex gap-2 align-items-center">
                  <div class="group-avatar">
                    <i class="bi bi-people-fill"></i>
                  </div>
                  <div class="group-info">
                    <div class="group-name">
                      <?php echo htmlspecialchars($group['group_name']); ?>
                    </div>
                    <?php if ($group['last_message']): ?>
                    <div class="group-preview">
                      <?php 
                      if ($group['last_message']['sender_id'] == $currentUser['id']) {
                          echo 'You: ';
                      } else {
                          echo htmlspecialchars($group['last_message']['first_name']) . ': ';
                      }
                      echo htmlspecialchars(substr($group['last_message']['message_text'] ?? 'Attachment', 0, 25));
                      ?>
                    </div>
                    <?php else: ?>
                    <div class="group-preview">No messages yet</div>
                    <?php endif; ?>
                    <div class="participants-badge">
                      <i class="bi bi-person"></i> <?php echo $group['member_count']; ?> members
                    </div>
                  </div>
                  <?php if ($group['unread_count'] > 0): ?>
                  <div class="unread-badge">
                    <?php echo $group['unread_count']; ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <!-- Chat Area -->
        <div class="col-lg-9">
          <?php if ($groupInfo): ?>
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                <div>
                  <h5 class="mb-0">
                    <i class="bi bi-people-fill text-success"></i>
                    <?php echo htmlspecialchars($groupInfo['group_name']); ?>
                  </h5>
                  <small class="text-muted">
                    <?php echo count($groupParticipants); ?> members
                    <?php if ($groupInfo['group_description']): ?>
                    • <?php echo htmlspecialchars($groupInfo['group_description']); ?>
                    <?php endif; ?>
                  </small>
                  <div class="mt-1">
                    <?php foreach ($groupParticipants as $participant): ?>
                    <span class="participant-chip <?php echo $participant['is_admin'] ? 'admin' : ''; ?>">
                      <?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?>
                      <?php if ($participant['is_admin']): ?>
                      <i class="bi bi-star-fill" style="font-size: 0.6rem;"></i>
                      <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#groupInfoModal">
                    <i class="bi bi-info-circle"></i> Info
                  </button>
                </div>
              </div>

              <div class="chat-container">
                <div class="messages-container" id="messagesContainer">
                  <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                    <p>Loading messages...</p>
                  </div>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                  <span id="typingNames"></span>
                  <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                  </div>
                </div>

                <div class="message-input-container">
                  <div class="attachment-preview" id="attachmentPreview"></div>
                  
                  <div class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                      <textarea 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="Type your message..."
                        rows="1"></textarea>
                    </div>
                    <div class="d-flex gap-1">
                      <button class="btn btn-outline-secondary" onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-paperclip"></i>
                      </button>
                      <button class="btn btn-primary" id="sendButton" onclick="sendMessage()">
                        <i class="bi bi-send"></i>
                      </button>
                    </div>
                  </div>
                  
                  <input 
                    type="file" 
                    id="fileInput" 
                    multiple 
                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx" 
                    style="display: none;"
                    onchange="handleFileSelect(event)">
                </div>
              </div>

            </div>
          </div>
          <?php else: ?>
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="bi bi-chat-square-dots" style="font-size: 4rem; color: #dee2e6;"></i>
              <h4 class="mt-3">Select a group or create one</h4>
              <p class="text-muted">Choose a group from the sidebar to start messaging</p>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="bi bi-plus-circle"></i> Create New Group
              </button>
            </div>
          </div>
          <?php endif; ?>
        </div>
        
      </div>
    </section>

  </main>

  <!-- Create Group Modal -->
  <div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Group</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="createGroupForm">
            <div class="mb-3">
              <label class="form-label">Group Name *</label>
              <input type="text" class="form-control" id="groupName" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description (Optional)</label>
              <textarea class="form-control" id="groupDescription" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Add Members *</label>
              <input 
                type="text" 
                class="form-control mb-2" 
                id="memberSearch" 
                placeholder="🔍 Search members by name or role...">
              <div id="membersList" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px;"></div>
              <small class="text-muted mt-2 d-block">
                <span id="selectedCount">0</span> member(s) selected
                <span id="selectedNames" class="d-block mt-1" style="font-size: 0.85rem;"></span>
              </small>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="createGroup()">Create Group</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Group Info Modal -->
  <div class="modal fade" id="groupInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-info-circle"></i> Group Information
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if ($groupInfo): ?>
          
          <!-- Group Details -->
          <div class="mb-4">
            <h6 class="text-muted mb-2">Group Details</h6>
            <div class="card bg-light">
              <div class="card-body">
                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($groupInfo['group_name']); ?></p>
                <?php if ($groupInfo['group_description']): ?>
                <p class="mb-1"><strong>Description:</strong> <?php echo htmlspecialchars($groupInfo['group_description']); ?></p>
                <?php endif; ?>
                <p class="mb-0">
                  <strong>Created by:</strong> 
                  <?php echo htmlspecialchars($groupInfo['creator_first_name'] . ' ' . $groupInfo['creator_last_name']); ?>
                  <small class="text-muted">on <?php echo date('M d, Y', strtotime($groupInfo['created_at'])); ?></small>
                </p>
              </div>
            </div>
          </div>

          <!-- Members List -->
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="text-muted mb-0">Members (<?php echo count($groupParticipants); ?>)</h6>
              <?php 
              // Check if current user is admin
              $isGroupAdmin = false;
              foreach ($groupParticipants as $p) {
                  if ($p['id'] == $currentUser['id'] && $p['is_admin']) {
                      $isGroupAdmin = true;
                      break;
                  }
              }
              if ($isGroupAdmin): 
              ?>
              <button class="btn btn-sm btn-primary" onclick="openAddMemberModal()">
                <i class="bi bi-person-plus"></i> Add Member
              </button>
              <?php endif; ?>
            </div>
            <div class="list-group" id="membersList">
              <?php foreach ($groupParticipants as $participant): ?>
              <div class="list-group-item" id="member-<?php echo $participant['id']; ?>">
                <div class="d-flex align-items-center gap-2">
                  <div class="sender-avatar">
                    <?php echo strtoupper(substr($participant['first_name'], 0, 1)); ?>
                  </div>
                  <div class="flex-grow-1">
                    <strong><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></strong>
                    <?php if ($participant['id'] == $currentUser['id']): ?>
                    <span class="badge bg-success">You</span>
                    <?php endif; ?>
                    <br>
                    <small class="text-muted"><?php echo ucwords($participant['user_role']); ?></small>
                  </div>
                  <?php if ($participant['is_admin']): ?>
                  <span class="badge bg-primary">Admin</span>
                  <?php endif; ?>
                  <?php if ($isGroupAdmin && $participant['id'] != $currentUser['id']): ?>
                  <button class="btn btn-sm btn-outline-danger" onclick="removeMember(<?php echo $participant['id']; ?>, '<?php echo addslashes($participant['first_name'] . ' ' . $participant['last_name']); ?>')">
                    <i class="bi bi-person-dash"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Group Actions -->
          <div class="mb-3">
            <h6 class="text-muted mb-2">Group Actions</h6>
            <div class="d-grid gap-2">
              <?php if ($isGroupAdmin): ?>
              <button class="btn btn-outline-danger" onclick="confirmDeleteGroup()">
                <i class="bi bi-trash"></i> Delete Group
              </button>
              <?php else: ?>
              <button class="btn btn-outline-warning" onclick="confirmLeaveGroup()">
                <i class="bi bi-box-arrow-right"></i> Leave Group
              </button>
              <?php endif; ?>
            </div>
          </div>
          
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Member Modal -->
  <div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Members to Group</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <input 
              type="text" 
              class="form-control mb-2" 
              id="addMemberSearch" 
              placeholder="🔍 Search members to add...">
            <div id="addMembersList" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px;"></div>
            <small class="text-muted mt-2 d-block">
              <span id="addSelectedCount">0</span> member(s) selected
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="addMembersToGroup()">Add Selected</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
  const currentUserId = <?php echo $currentUser['id']; ?>;
  
  // Store all members globally for search
  let allMembers = [];
  let selectedMemberIds = new Set(); // Track selected member IDs
  
  // Load available members when page loads (for create group modal)
  document.addEventListener('DOMContentLoaded', function() {
    loadAvailableMembers();
    <?php if ($groupInfo): ?>
    // Initialize group chat if group is selected
    loadMessages();
    startPolling();
    setupMessageInput();
    <?php endif; ?>
  });

  // Load available members for create group (always available)
  async function loadAvailableMembers() {
    try {
      const response = await fetch('ajax/get-available-members.php');
      const data = await response.json();
      if (data.success) {
        allMembers = data.members;
        displayMembers(allMembers);
        setupMemberSearch();
      }
    } catch (error) {
      console.error('Error loading members:', error);
    }
  }

  // Display members in the list (preserving checked state)
  function displayMembers(members) {
    const container = document.getElementById('membersList');
    container.innerHTML = '';
    
    if (members.length === 0) {
      container.innerHTML = '<div class="text-muted text-center py-3">No members found</div>';
      updateSelectedCount();
      return;
    }
    
    members.forEach(member => {
      const div = document.createElement('div');
      div.className = 'form-check';
      
      // Check if this member was previously selected
      const isChecked = selectedMemberIds.has(member.id);
      
      div.innerHTML = `
        <input class="form-check-input member-checkbox" 
               type="checkbox" 
               value="${member.id}" 
               id="member${member.id}" 
               ${isChecked ? 'checked' : ''}
               onchange="toggleMemberSelection(${member.id})">
        <label class="form-check-label" for="member${member.id}">
          <strong>${member.first_name} ${member.last_name}</strong>
          <small class="text-muted d-block">${member.user_role}</small>
        </label>
      `;
      container.appendChild(div);
    });
    
    updateSelectedCount();
  }

  // Toggle member selection (add/remove from Set)
  function toggleMemberSelection(memberId) {
    if (selectedMemberIds.has(memberId)) {
      selectedMemberIds.delete(memberId);
    } else {
      selectedMemberIds.add(memberId);
    }
    updateSelectedCount();
  }

  // Setup search functionality
  function setupMemberSearch() {
    const searchInput = document.getElementById('memberSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase().trim();
      
      if (searchTerm === '') {
        displayMembers(allMembers);
      } else {
        const filtered = allMembers.filter(member => {
          const fullName = `${member.first_name} ${member.last_name}`.toLowerCase();
          const role = member.user_role.toLowerCase();
          const email = member.email.toLowerCase();
          
          return fullName.includes(searchTerm) || 
                 role.includes(searchTerm) || 
                 email.includes(searchTerm);
        });
        displayMembers(filtered);
      }
    });
  }

  // Update selected count (based on Set, not visible checkboxes)
  function updateSelectedCount() {
    const count = selectedMemberIds.size;
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
      countElement.textContent = count;
      countElement.style.fontWeight = count > 0 ? 'bold' : 'normal';
      countElement.style.color = count > 0 ? '#0d6efd' : '#6c757d';
    }
    
    // Show selected member names
    const namesElement = document.getElementById('selectedNames');
    if (namesElement) {
      if (count > 0) {
        const names = getSelectedMemberNames();
        if (names.length <= 3) {
          namesElement.textContent = '✓ ' + names.join(', ');
        } else {
          namesElement.textContent = `✓ ${names.slice(0, 3).join(', ')} and ${names.length - 3} more`;
        }
        namesElement.style.color = '#0d6efd';
      } else {
        namesElement.textContent = '';
      }
    }
  }
  
  // Get selected member names for display
  function getSelectedMemberNames() {
    const names = [];
    allMembers.forEach(member => {
      if (selectedMemberIds.has(member.id)) {
        names.push(`${member.first_name} ${member.last_name}`);
      }
    });
    return names;
  }

  // Clear search when modal opens
  document.addEventListener('DOMContentLoaded', function() {
    const createModal = document.getElementById('createGroupModal');
    if (createModal) {
      createModal.addEventListener('show.bs.modal', function() {
        const searchInput = document.getElementById('memberSearch');
        if (searchInput) {
          searchInput.value = '';
          displayMembers(allMembers);
        }
        updateSelectedCount();
      });
      
      // Clear form when modal closes
      createModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('groupName').value = '';
        document.getElementById('groupDescription').value = '';
        selectedMemberIds.clear(); // Clear the Set
        displayMembers(allMembers); // Refresh display
      });
    }
  });

  // Create group function (always available)
  async function createGroup() {
    const name = document.getElementById('groupName').value.trim();
    const description = document.getElementById('groupDescription').value.trim();
    const memberIds = Array.from(selectedMemberIds); // Use Set instead of checkboxes
    
    if (!name) {
      alert('Please enter a group name');
      return;
    }
    
    if (memberIds.length === 0) {
      alert('Please select at least one member');
      return;
    }
    
    try {
      const response = await fetch('ajax/create-group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          group_name: name,
          group_description: description,
          member_ids: memberIds
        })
      });
      
      const data = await response.json();
      if (data.success) {
        window.location.href = 'team-messages.php?group_id=' + data.group_id;
      } else {
        alert('Failed to create group: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to create group');
    }
  }

  // Group management functions
  let addMemberIds = new Set();
  
  async function openAddMemberModal() {
    addMemberIds.clear();
    try {
      const response = await fetch(`ajax/get-available-members.php?group_id=${groupId}`);
      const data = await response.json();
      if (data.success) {
        displayAddMembers(data.members);
        setupAddMemberSearch(data.members);
        bootstrap.Modal.getInstance(document.getElementById('groupInfoModal')).hide();
        new bootstrap.Modal(document.getElementById('addMemberModal')).show();
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to load members');
    }
  }

  function displayAddMembers(members) {
    const container = document.getElementById('addMembersList');
    container.innerHTML = '';
    if (members.length === 0) {
      container.innerHTML = '<div class="text-muted text-center py-3">All team members are already in this group</div>';
      return;
    }
    members.forEach(member => {
      const div = document.createElement('div');
      div.className = 'form-check';
      const isChecked = addMemberIds.has(member.id);
      div.innerHTML = `
        <input class="form-check-input" type="checkbox" value="${member.id}" id="addMember${member.id}" ${isChecked ? 'checked' : ''} onchange="toggleAddMember(${member.id})">
        <label class="form-check-label" for="addMember${member.id}">
          <strong>${member.first_name} ${member.last_name}</strong>
          <small class="text-muted d-block">${member.user_role}</small>
        </label>
      `;
      container.appendChild(div);
    });
    updateAddSelectedCount();
  }

  function setupAddMemberSearch(members) {
    const searchInput = document.getElementById('addMemberSearch');
    if (!searchInput) return;
    searchInput.value = '';
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase().trim();
      const filtered = searchTerm === '' ? members : members.filter(m => {
        const fullName = `${m.first_name} ${m.last_name}`.toLowerCase();
        const role = m.user_role.toLowerCase();
        return fullName.includes(searchTerm) || role.includes(searchTerm);
      });
      displayAddMembers(filtered);
    });
  }

  function toggleAddMember(memberId) {
    if (addMemberIds.has(memberId)) {
      addMemberIds.delete(memberId);
    } else {
      addMemberIds.add(memberId);
    }
    updateAddSelectedCount();
  }

  function updateAddSelectedCount() {
    const count = addMemberIds.size;
    const countElement = document.getElementById('addSelectedCount');
    if (countElement) {
      countElement.textContent = count;
      countElement.style.fontWeight = count > 0 ? 'bold' : 'normal';
      countElement.style.color = count > 0 ? '#0d6efd' : '#6c757d';
    }
  }

  async function addMembersToGroup() {
    const memberIds = Array.from(addMemberIds);
    if (memberIds.length === 0) {
      alert('Please select at least one member to add');
      return;
    }
    try {
      const response = await fetch('ajax/add-group-members.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          group_id: groupId,
          member_ids: memberIds
        })
      });
      const data = await response.json();
      if (data.success) {
        location.reload();
      } else {
        alert('Failed to add members: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to add members');
    }
  }

  async function removeMember(memberId, memberName) {
    if (!confirm(`Remove ${memberName} from this group?`)) return;
    try {
      const response = await fetch('ajax/remove-group-member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: groupId, user_id: memberId })
      });
      const data = await response.json();
      if (data.success) {
        const memberElement = document.getElementById(`member-${memberId}`);
        if (memberElement) memberElement.remove();
        alert(`${memberName} has been removed from the group`);
      } else {
        alert('Failed to remove member: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to remove member');
    }
  }

  async function confirmLeaveGroup() {
    if (!confirm('Are you sure you want to leave this group?')) return;
    try {
      const response = await fetch('ajax/leave-group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: groupId })
      });
      const data = await response.json();
      if (data.success) {
        window.location.href = 'team-messages.php';
      } else {
        alert('Failed to leave group: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to leave group');
    }
  }

  async function confirmDeleteGroup() {
    if (!confirm('⚠️ WARNING: This will permanently delete the group and all its messages for everyone. Are you sure?')) return;
    if (!confirm('This is your last chance. Delete the group permanently?')) return;
    try {
      const response = await fetch('ajax/delete-group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: groupId })
      });
      const data = await response.json();
      if (data.success) {
        alert('Group has been deleted');
        window.location.href = 'team-messages.php';
      } else {
        alert('Failed to delete group: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to delete group');
    }
  }
  </script>

  <?php if ($groupInfo): ?>
  <script>
  const groupId = <?php echo $groupId; ?>;
  const participants = <?php echo json_encode($groupParticipants); ?>;
  let selectedFiles = [];
  let lastMessageId = 0;
  let typingTimeout;
  let isTyping = false;

  function setupMessageInput() {
    const input = document.getElementById('messageInput');
    input.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      handleTyping();
    });
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  function handleTyping() {
    if (!isTyping) {
      isTyping = true;
      updateTypingStatus(true);
    }
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
      isTyping = false;
      updateTypingStatus(false);
    }, 3000);
  }

  function updateTypingStatus(typing) {
    fetch('ajax/update-group-typing.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        group_id: groupId,
        is_typing: typing
      })
    });
  }

  function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    selectedFiles = [...selectedFiles, ...files];
    displayAttachmentPreview();
  }

  function displayAttachmentPreview() {
    const preview = document.getElementById('attachmentPreview');
    preview.innerHTML = '';
    selectedFiles.forEach((file, index) => {
      const item = document.createElement('div');
      item.className = 'attachment-item';
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        item.appendChild(img);
      } else if (file.type.startsWith('video/')) {
        const video = document.createElement('video');
        video.src = URL.createObjectURL(file);
        item.appendChild(video);
      } else {
        const icon = document.createElement('div');
        icon.className = 'text-center pt-3';
        icon.innerHTML = '<i class="bi bi-file-earmark"></i>';
        item.appendChild(icon);
      }
      const removeBtn = document.createElement('button');
      removeBtn.className = 'remove-attachment';
      removeBtn.innerHTML = '×';
      removeBtn.onclick = () => removeAttachment(index);
      item.appendChild(removeBtn);
      preview.appendChild(item);
    });
  }

  function removeAttachment(index) {
    selectedFiles.splice(index, 1);
    displayAttachmentPreview();
  }

  async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message && selectedFiles.length === 0) return;
    
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = true;
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('message_text', message);
    selectedFiles.forEach((file) => {
      formData.append('files[]', file);
    });
    
    try {
      const response = await fetch('ajax/send-group-message.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      if (data.success) {
        input.value = '';
        input.style.height = 'auto';
        selectedFiles = [];
        displayAttachmentPreview();
        loadMessages();
      } else {
        alert('Failed to send message: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Failed to send message');
    } finally {
      sendButton.disabled = false;
    }
  }

  async function loadMessages() {
    try {
      const response = await fetch(`ajax/get-group-messages.php?group_id=${groupId}&last_message_id=${lastMessageId}`);
      const data = await response.json();
      if (data.success) {
        if (data.messages.length > 0 || lastMessageId === 0) {
          displayMessages(data.messages);
          if (data.messages.length > 0) {
            lastMessageId = Math.max(...data.messages.map(m => m.id));
          }
        }
      }
    } catch (error) {
      console.error('Error loading messages:', error);
    }
  }

  function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    if (lastMessageId === 0) {
      container.innerHTML = '';
    }
    if (messages.length === 0 && lastMessageId === 0) {
      container.innerHTML = `
        <div class="text-center text-muted py-4">
          <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
          <p>No messages yet. Start the conversation!</p>
        </div>
      `;
      return;
    }
    
    messages.forEach(message => {
      const messageDiv = document.createElement('div');
      messageDiv.className = `message ${message.sender_id == currentUserId ? 'sent' : 'received'}`;
      
      // Add avatar for received messages
      if (message.sender_id != currentUserId) {
        const avatar = document.createElement('div');
        avatar.className = 'sender-avatar';
        avatar.textContent = message.first_name ? message.first_name.charAt(0).toUpperCase() : '?';
        messageDiv.appendChild(avatar);
      }
      
      const bubble = document.createElement('div');
      bubble.className = 'message-bubble';
      
      // Add sender name for received messages
      if (message.sender_id != currentUserId && message.first_name) {
        const senderName = document.createElement('div');
        senderName.className = 'sender-name';
        senderName.textContent = message.first_name + ' ' + message.last_name;
        bubble.appendChild(senderName);
      }
      
      if (message.message_text) {
        const text = document.createElement('div');
        text.textContent = message.message_text;
        bubble.appendChild(text);
      }
      
      // Add attachments
      if (message.attachments && message.attachments.length > 0) {
        message.attachments.forEach(attachment => {
          if (attachment.file_type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = '../' + attachment.file_path;
            img.style.maxWidth = '200px';
            img.style.borderRadius = '8px';
            img.style.cursor = 'pointer';
            img.onclick = () => window.open('../' + attachment.file_path, '_blank');
            bubble.appendChild(img);
          } else if (attachment.file_type.startsWith('video/')) {
            const video = document.createElement('video');
            video.src = '../' + attachment.file_path;
            video.controls = true;
            video.style.maxWidth = '200px';
            video.style.borderRadius = '8px';
            bubble.appendChild(video);
          } else {
            const fileDiv = document.createElement('div');
            fileDiv.innerHTML = `
              <i class="bi bi-file-earmark"></i>
              <a href="../${attachment.file_path}" download>${attachment.file_name}</a>
            `;
            bubble.appendChild(fileDiv);
          }
        });
      }
      
      const time = document.createElement('div');
      time.className = 'message-time';
      time.textContent = formatTime(message.created_at);
      bubble.appendChild(time);
      
      if (message.sender_id == currentUserId) {
        const status = document.createElement('div');
        status.className = 'message-status';
        status.innerHTML = getStatusIcon(message.status);
        bubble.appendChild(status);
      }
      
      messageDiv.appendChild(bubble);
      container.appendChild(messageDiv);
    });
    
    container.scrollTop = container.scrollHeight;
  }

  function getStatusIcon(status) {
    switch(status) {
      case 'read':
        return '<i class="bi bi-check-all" style="color: #4CAF50;"></i> Read';
      case 'delivered':
        return '<i class="bi bi-check-all"></i> Delivered';
      default:
        return '<i class="bi bi-check"></i> Sent';
    }
  }

  function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    if (diff < 86400000) {
      return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    } else if (diff < 604800000) {
      return date.toLocaleDateString('en-US', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
    } else {
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
  }

  async function checkTypingStatus() {
    try {
      const response = await fetch(`ajax/check-group-typing.php?group_id=${groupId}`);
      const data = await response.json();
      const indicator = document.getElementById('typingIndicator');
      const typingNames = document.getElementById('typingNames');
      
      if (data.typing_users && data.typing_users.length > 0) {
        const names = data.typing_users.map(u => u.first_name).join(', ');
        typingNames.textContent = names + (data.typing_users.length > 1 ? ' are' : ' is') + ' typing';
        indicator.classList.add('active');
      } else {
        indicator.classList.remove('active');
      }
    } catch (error) {
      console.error('Error checking typing status:', error);
    }
  }

  function startPolling() {
    setInterval(loadMessages, 3000);
    setInterval(checkTypingStatus, 2000);
  }
  </script>
  <?php endif; ?>

</body>

</html>

