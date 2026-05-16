<?php
/**
 * Admin User Messages - Individual Chat Interface
 * Allows admins to chat with any user
 */

require_once '../includes/session_manager.php';
require_once '../config/database.php';

// Check if session is still valid
if (!isLoggedIn()) {
    header("Location: ../login.php?timeout=1");
    exit();
}

// Require admin roles
requireRole(['owner', 'administrator', 'administrative staff'], '../unauthorized.php');

$currentUser = getCurrentUser();
$pageTitle = 'User Messages';

// Database connection
$database = new Database();
try {
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user ID from URL
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get user details
function getUserInfo($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, user_role, is_verified 
                          FROM user_accounts WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Generate conversation ID
function getConversationId($userId1, $userId2) {
    $ids = [$userId1, $userId2];
    sort($ids);
    return 'conv_' . $ids[0] . '_' . $ids[1];
}

// Get all user conversations for sidebar
function getAllUserConversations($pdo, $adminId) {
    $sql = "SELECT DISTINCT 
                ua.id,
                ua.first_name,
                ua.last_name,
                ua.email,
                ua.user_role
            FROM user_accounts ua
            WHERE ua.id != ?
            ORDER BY ua.first_name, ua.last_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get last message and unread count for each user
    foreach ($users as &$user) {
        $conversationId = getConversationId($adminId, $user['id']);
        
        // Get last message
        $stmt = $pdo->prepare("SELECT message_text, created_at, sender_id 
                              FROM messages 
                              WHERE conversation_id = ? AND is_deleted = FALSE
                              ORDER BY created_at DESC 
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
    }
    
    return $users;
}

// Get all user conversations for sidebar
$allUsers = getAllUserConversations($pdo, $currentUser['id']);

// If no user_id provided, redirect to messages list
if ($userId == 0) {
    header("Location: messages.php");
    exit();
}

$userInfo = getUserInfo($pdo, $userId);
if (!$userInfo) {
    header("Location: messages.php");
    exit();
}

$conversationId = getConversationId($currentUser['id'], $userId);

// Initialize conversation participants if not exists
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
    $stmt->execute([$conversationId, $currentUser['id']]);
    $stmt->execute([$conversationId, $userId]);
} catch (Exception $e) {
    // Table might not exist, continue anyway
}

// Update last read time for current user
try {
    $stmt = $pdo->prepare("UPDATE conversation_participants SET last_read_at = NOW() 
                          WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $currentUser['id']]);
} catch (Exception $e) {
    // Continue if table doesn't exist
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Chat with <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?> - ASF Surveillance</title>
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
    }
    
    .message.sent {
      justify-content: flex-end;
    }
    
    .message.received {
      justify-content: flex-start;
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
    
    .message-time {
      font-size: 0.75rem;
      opacity: 0.7;
      margin-top: 4px;
    }
    
    .message-status {
      font-size: 0.7rem;
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
    
    .seen-indicator {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.7rem;
      margin-top: 4px;
      color: #4CAF50;
    }
    
    .seen-avatar {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 8px;
      font-weight: bold;
    }
    
    .message.sent .seen-indicator {
      justify-content: flex-end;
    }
    
    .users-sidebar {
      border-right: 2px solid #dee2e6;
      height: calc(100vh - 200px);
      overflow-y: auto;
      background: #f8f9fa;
    }
    
    .user-item {
      padding: 12px 15px;
      border-bottom: 1px solid #dee2e6;
      cursor: pointer;
      transition: background 0.2s;
      background: white;
    }
    
    .user-item:hover {
      background: #e9ecef;
    }
    
    .user-item.active {
      background: #e7f3ff;
      border-left: 3px solid #0d6efd;
    }
    
    .user-avatar-small {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 1rem;
      flex-shrink: 0;
    }
    
    .user-info {
      flex: 1;
      min-width: 0;
    }
    
    .user-name-small {
      font-weight: 600;
      font-size: 0.9rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .user-preview {
      font-size: 0.75rem;
      color: #6c757d;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .user-item.active .user-name-small {
      color: #0d6efd;
    }
    
    .unread-badge-small {
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
    
    .file-attachment {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      margin-top: 8px;
    }
    
    .message.received .file-attachment {
      background: #f8f9fa;
    }
    
    .file-icon {
      font-size: 24px;
    }
    
    .file-info {
      flex: 1;
    }
    
    .file-name {
      font-weight: 500;
      font-size: 0.9rem;
    }
    
    .file-size {
      font-size: 0.75rem;
      opacity: 0.7;
    }
    
    .user-info-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      margin-bottom: 15px;
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Chat with <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?></h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="messages.php">Messages</a></li>
          <li class="breadcrumb-item active">Chat</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        
        <!-- Users Sidebar -->
        <div class="col-lg-3">
          <div class="card">
            <div class="sidebar-header">
              <h6 class="mb-2"><i class="bi bi-people-fill"></i> All Users</h6>
              <small class="text-muted mb-2 d-block"><?php echo count($allUsers); ?> users</small>
              <div class="input-group input-group-sm">
                <span class="input-group-text">
                  <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       id="userSearchInput" 
                       class="form-control" 
                       placeholder="Search users..."
                       autocomplete="off">
              </div>
            </div>
            <div class="users-sidebar" id="usersSidebar">
              <?php if (empty($allUsers)): ?>
              <div class="text-center py-4">
                <i class="bi bi-inbox" style="font-size: 2rem; color: #dee2e6;"></i>
                <p class="text-muted small mt-2">No users yet</p>
              </div>
              <?php else: ?>
              <?php foreach ($allUsers as $user): ?>
              <div class="user-item <?php echo $user['id'] == $userId ? 'active' : ''; ?>" 
                   data-user-name="<?php echo strtolower($user['first_name'] . ' ' . $user['last_name']); ?>"
                   data-user-role="<?php echo strtolower($user['user_role']); ?>"
                   onclick="window.location.href='admin-user-messages.php?user_id=<?php echo $user['id']; ?>'">
                <div class="d-flex gap-2 align-items-center">
                  <div class="user-avatar-small">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                  </div>
                  <div class="user-info">
                    <div class="user-name-small">
                      <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                    <div class="user-preview" style="color: #0d6efd; font-size: 0.7rem;">
                      <i class="bi bi-person-badge"></i> <?php echo ucwords($user['user_role']); ?>
                    </div>
                    <?php if ($user['last_message']): ?>
                    <div class="user-preview">
                      <?php 
                      if ($user['last_message']['sender_id'] == $currentUser['id']) {
                          echo 'You: ';
                      }
                      echo htmlspecialchars(substr($user['last_message']['message_text'] ?? 'Attachment', 0, 30));
                      ?>
                    </div>
                    <?php else: ?>
                    <div class="user-preview">No messages yet</div>
                    <?php endif; ?>
                  </div>
                  <?php if ($user['unread_count'] > 0): ?>
                  <div class="unread-badge-small">
                    <?php echo $user['unread_count']; ?>
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

          <!-- User Info Card -->
          <div class="card user-info-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h5 class="mb-1 text-white">
                    <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?>
                    <?php if (!$userInfo['is_verified']): ?>
                    <i class="bi bi-exclamation-circle" title="Not verified"></i>
                    <?php endif; ?>
                  </h5>
                  <small style="opacity: 0.9;">
                    <i class="bi bi-person-badge"></i> <?php echo ucwords($userInfo['user_role']); ?>
                    <?php if ($userInfo['email']): ?>
                    | <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($userInfo['email']); ?>
                    <?php endif; ?>
                    <?php if ($userInfo['phone']): ?>
                    | <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($userInfo['phone']); ?>
                    <?php endif; ?>
                  </small>
                </div>
                <div>
                  <a href="messages.php" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Messages
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <div class="chat-container">
                <div class="messages-container" id="messagesContainer">
                  <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                    <p>Loading messages...</p>
                  </div>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                  <span><?php echo htmlspecialchars($userInfo['first_name']); ?> is typing</span>
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

        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <?php include 'includes/footer.php'; ?>

  <script>
  const currentUserId = <?php echo $currentUser['id']; ?>;
  const userId = <?php echo $userId; ?>;
  const conversationId = '<?php echo $conversationId; ?>';
  let selectedFiles = [];
  let lastMessageId = 0;
  let typingTimeout;
  let isTyping = false;

  // Initialize
  document.addEventListener('DOMContentLoaded', function() {
    loadMessages();
    startPolling();
    setupMessageInput();
  });

  // Setup message input handlers
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

  // Handle typing indicator
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

  // Update typing status
  function updateTypingStatus(typing) {
    fetch('ajax/update-typing-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conversation_id: conversationId,
        is_typing: typing
      })
    }).catch(err => console.log('Typing status update failed (table may not exist)'));
  }

  // Handle file selection
  function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    selectedFiles = [...selectedFiles, ...files];
    displayAttachmentPreview();
  }

  // Display attachment preview
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
        icon.className = 'file-icon text-center pt-3';
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

  // Remove attachment
  function removeAttachment(index) {
    selectedFiles.splice(index, 1);
    displayAttachmentPreview();
  }

  // Send message
  async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message && selectedFiles.length === 0) return;
    
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = true;
    
    const formData = new FormData();
    formData.append('conversation_id', conversationId);
    formData.append('receiver_id', userId);
    formData.append('message_text', message);
    
    selectedFiles.forEach((file, index) => {
      formData.append('files[]', file);
    });
    
    try {
      const response = await fetch('ajax/send-message.php', {
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

  // Load messages
  async function loadMessages() {
    try {
      const response = await fetch(`ajax/get-messages.php?conversation_id=${conversationId}&last_message_id=${lastMessageId}`);
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

  // Display messages
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
      
      const bubble = document.createElement('div');
      bubble.className = 'message-bubble';
      
      if (message.message_text) {
        const text = document.createElement('div');
        text.textContent = message.message_text;
        bubble.appendChild(text);
      }
      
      // Add attachments
      if (message.attachments && message.attachments.length > 0) {
        message.attachments.forEach(attachment => {
          const fileDiv = document.createElement('div');
          fileDiv.className = 'file-attachment';
          
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
            fileDiv.innerHTML = `
              <i class="bi bi-file-earmark file-icon"></i>
              <div class="file-info">
                <div class="file-name">${attachment.file_name}</div>
                <div class="file-size">${formatFileSize(attachment.file_size)}</div>
              </div>
              <a href="../${attachment.file_path}" download class="btn btn-sm btn-link">
                <i class="bi bi-download"></i>
              </a>
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
        
        // Add seen indicator with avatar if message is read
        if (message.status === 'read') {
          const seenIndicator = document.createElement('div');
          seenIndicator.className = 'seen-indicator';
          const userName = '<?php echo addslashes($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?>';
          const userInitial = '<?php echo substr($userInfo['first_name'], 0, 1); ?>';
          seenIndicator.innerHTML = `
            <div class="seen-avatar">${userInitial}</div>
            <span>Seen by ${userName}</span>
          `;
          bubble.appendChild(seenIndicator);
        }
      }
      
      messageDiv.appendChild(bubble);
      container.appendChild(messageDiv);
    });
    
    container.scrollTop = container.scrollHeight;
  }

  // Get status icon
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

  // Format time
  function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 86400000) { // Less than 24 hours
      return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    } else if (diff < 604800000) { // Less than 7 days
      return date.toLocaleDateString('en-US', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
    } else {
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
  }

  // Format file size
  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  // Check typing status
  async function checkTypingStatus() {
    try {
      const response = await fetch(`ajax/check-typing-status.php?conversation_id=${conversationId}&user_id=${userId}`);
      const data = await response.json();
      
      const indicator = document.getElementById('typingIndicator');
      if (data.is_typing) {
        indicator.classList.add('active');
      } else {
        indicator.classList.remove('active');
      }
    } catch (error) {
      // Silent fail if endpoint doesn't exist
    }
  }

  // Start polling for new messages and typing status
  function startPolling() {
    setInterval(loadMessages, 3000); // Check for new messages every 3 seconds
    setInterval(checkTypingStatus, 2000); // Check typing status every 2 seconds
  }

  // User search functionality
  document.getElementById('userSearchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase().trim();
    const userItems = document.querySelectorAll('.user-item');
    const sidebar = document.getElementById('usersSidebar');
    let visibleCount = 0;
    
    userItems.forEach(item => {
      const userName = item.getAttribute('data-user-name') || '';
      const userRole = item.getAttribute('data-user-role') || '';
      
      if (userName.includes(searchTerm) || userRole.includes(searchTerm)) {
        item.style.display = '';
        visibleCount++;
      } else {
        item.style.display = 'none';
      }
    });
    
    // Show "no results" message if needed
    let noResultsDiv = document.getElementById('noSearchResults');
    if (visibleCount === 0 && searchTerm !== '') {
      if (!noResultsDiv) {
        noResultsDiv = document.createElement('div');
        noResultsDiv.id = 'noSearchResults';
        noResultsDiv.className = 'text-center py-4';
        noResultsDiv.innerHTML = `
          <i class="bi bi-search" style="font-size: 2rem; color: #dee2e6;"></i>
          <p class="text-muted small mt-2">No users found</p>
        `;
        sidebar.appendChild(noResultsDiv);
      }
      noResultsDiv.style.display = 'block';
    } else if (noResultsDiv) {
      noResultsDiv.style.display = 'none';
    }
  });

  // Clear search on escape key
  document.getElementById('userSearchInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      this.value = '';
      this.dispatchEvent(new Event('input'));
      this.blur();
    }
  });
  </script>

</body>

</html>

