<?php
/**
 * ============================================
 * Velocity Auction Pro - 私訊聊天室
 * chat.php
 * ============================================
 * 一對一私訊系統，支援即時更新
 */

$pageTitle = '私訊';
require_once __DIR__ . '/functions.php';

// 需要登入
requireLogin('index.php');

$currentUser = getCurrentUser();
$selectedUserId = (int)($_GET['user'] ?? 0);

// 如果有指定用戶，取得用戶資訊
$selectedUser = null;
if ($selectedUserId > 0 && $selectedUserId !== getCurrentUserId()) {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$selectedUserId]);
    $selectedUser = $stmt->fetch();
}

require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">💬 私人訊息</h1>
        </div>

        <div class="chat-container">
            <!-- 對話列表 -->
            <div class="conversation-list" id="conversation-list">
                <div class="conversation-header">
                    <h3>對話</h3>
                </div>
                <div class="conversation-items" id="conversation-items">
                    <!-- 動態載入 -->
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>

            <!-- 聊天面板 -->
            <div class="chat-panel" id="chat-panel">
                <?php if ($selectedUser): ?>
                <!-- 有選中的對話 -->
                <div class="chat-header">
                    <div class="conversation-avatar">
                        <?php echo strtoupper(substr($selectedUser['username'], 0, 1)); ?>
                    </div>
                    <div class="chat-user-info">
                        <span class="chat-user-name"><?php echo h($selectedUser['username']); ?></span>
                        <a href="profile.php?id=<?php echo $selectedUser['id']; ?>" class="chat-user-link">查看檔案</a>
                    </div>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <!-- 動態載入 -->
                </div>
                <form class="chat-input" onsubmit="sendChatMessage(event)">
                    <input type="hidden" id="receiver-id" value="<?php echo $selectedUser['id']; ?>">
                    <input type="text" id="message-input" placeholder="輸入訊息..." autocomplete="off">
                    <button type="submit" class="btn btn-primary">發送</button>
                </form>
                <?php else: ?>
                <!-- 未選中對話 -->
                <div class="chat-empty">
                    <div class="empty-icon">💬</div>
                    <h3>選擇一個對話</h3>
                    <p>從左側選擇一個對話開始聊天</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
/* 聊天室樣式 */
.chat-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 24px;
    height: calc(100vh - var(--ticker-height) - var(--navbar-height) - 180px);
    min-height: 500px;
}

.conversation-list {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.conversation-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.conversation-header h3 {
    font-size: 16px;
    font-weight: 600;
}

.conversation-items {
    flex: 1;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: var(--transition-fast);
}

.conversation-item:hover {
    background: var(--bg-hover);
}

.conversation-item.active {
    background: var(--bg-tertiary);
    border-left: 3px solid var(--accent-gold);
}

.conversation-avatar {
    width: 48px;
    height: 48px;
    background: var(--gradient-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
    flex-shrink: 0;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 600;
    margin-bottom: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-time {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 400;
}

.conversation-preview {
    font-size: 13px;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-dot {
    width: 10px;
    height: 10px;
    background: var(--accent-gold);
    border-radius: 50%;
    flex-shrink: 0;
}

/* 聊天面板 */
.chat-panel {
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.chat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-tertiary);
}

.chat-user-info {
    display: flex;
    flex-direction: column;
}

.chat-user-name {
    font-weight: 600;
    font-size: 16px;
}

.chat-user-link {
    font-size: 12px;
    color: var(--text-muted);
}

.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.message-bubble {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: var(--border-radius);
    line-height: 1.5;
    word-wrap: break-word;
}

.message-bubble.sent {
    align-self: flex-end;
    background: var(--gradient-gold);
    color: #000;
    border-bottom-right-radius: 4px;
}

.message-bubble.received {
    align-self: flex-start;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}

.message-time {
    font-size: 10px;
    opacity: 0.7;
    margin-top: 4px;
    text-align: right;
}

.message-bubble.received .message-time {
    text-align: left;
    color: var(--text-muted);
}

.chat-input {
    display: flex;
    gap: 12px;
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
    background: var(--bg-tertiary);
}

.chat-input input {
    flex: 1;
}

.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.chat-empty .empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.chat-empty h3 {
    color: var(--text-secondary);
    margin-bottom: 8px;
}

/* 空對話列表 */
.no-conversations {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

/* 響應式 */
@media (max-width: 768px) {
    .chat-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .conversation-list {
        max-height: 200px;
    }
    
    .chat-panel {
        min-height: 400px;
    }
}
</style>

<script>
// ============================================
// 聊天室腳本
// ============================================

const currentUserId = <?php echo getCurrentUserId(); ?>;
const selectedUserId = <?php echo $selectedUserId ?: 'null'; ?>;
let lastMessageId = 0;

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
    
    if (selectedUserId) {
        loadMessages();
        // 每 3 秒更新訊息
        setInterval(loadMessages, 3000);
    }
    
    // 每 10 秒更新對話列表
    setInterval(loadConversations, 10000);
});

// 載入對話列表
function loadConversations() {
    fetch('api.php?action=get_conversations')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('conversation-items');
            
            if (data.success && data.conversations.length > 0) {
                container.innerHTML = data.conversations.map(conv => `
                    <div class="conversation-item ${conv.other_user_id == selectedUserId ? 'active' : ''}" 
                         onclick="selectConversation(${conv.other_user_id})">
                        <div class="conversation-avatar">
                            ${conv.other_username.charAt(0).toUpperCase()}
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">
                                <span>${escapeHtml(conv.other_username)}</span>
                                <span class="conversation-time">${formatTime(conv.last_message_time)}</span>
                            </div>
                        </div>
                        ${conv.unread_count > 0 ? '<span class="unread-dot"></span>' : ''}
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="no-conversations">
                        <p>尚無對話</p>
                    </div>
                `;
            }
        });
}

// 選擇對話
function selectConversation(userId) {
    window.location.href = 'chat.php?user=' + userId;
}

// 載入訊息
function loadMessages() {
    if (!selectedUserId) return;
    
    fetch(`api.php?action=get_messages&user_id=${selectedUserId}&last_id=${lastMessageId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('chat-messages');
                const shouldScroll = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
                
                data.messages.forEach(msg => {
                    const isSent = msg.sender_id == currentUserId;
                    const div = document.createElement('div');
                    div.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
                    div.innerHTML = `
                        ${escapeHtml(msg.content)}
                        <div class="message-time">${formatTime(msg.created_at)}</div>
                    `;
                    container.appendChild(div);
                    
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
                
                if (shouldScroll) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        });
}

// 發送訊息
function sendChatMessage(e) {
    e.preventDefault();
    
    const input = document.getElementById('message-input');
    const content = input.value.trim();
    const receiverId = document.getElementById('receiver-id').value;
    
    if (!content) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('receiver_id', receiverId);
    formData.append('content', content);
    
    // 立即顯示訊息
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = 'message-bubble sent';
    div.innerHTML = `
        ${escapeHtml(content)}
        <div class="message-time">剛剛</div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    
    input.value = '';
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            Swal.fire({
                icon: 'error',
                title: '發送失敗',
                text: data.message
            });
            div.remove();
        }
    });
}

// 工具函數
function formatTime(datetime) {
    const date = new Date(datetime);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return '剛剛';
    if (diff < 3600) return Math.floor(diff / 60) + '分鐘前';
    if (diff < 86400) return date.getHours() + ':' + String(date.getMinutes()).padStart(2, '0');
    
    return (date.getMonth() + 1) + '/' + date.getDate();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
