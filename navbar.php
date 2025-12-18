<?php
/**
 * ============================================
 * Velocity Auction Pro - 導航列與跑馬燈
 * navbar.php
 * ============================================
 */

require_once __DIR__ . '/functions.php';
initSession();

// 處理已結束的拍賣（建立訂單、通知得標者）
processEndedAuctions();

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?>Velocity Auction Pro</title>
    <meta name="description" content="Velocity Auction Pro - 旗艦級即時競標與電商平台">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Flatpickr CDN for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- 即時跑馬燈 -->
    <div class="ticker-wrapper">
        <div class="ticker-label">
            <span class="pulse-dot"></span>
            LIVE
        </div>
        <div class="ticker-content" id="ticker">
            <div class="ticker-items">
                <!-- 動態載入 -->
            </div>
        </div>
    </div>

    <!-- 導航列 -->
    <nav class="navbar">
        <div class="nav-container">
            <!-- Logo -->
            <a href="index.php" class="nav-logo">
                <span class="logo-icon">⚡</span>
                <span class="logo-text">Velocity</span>
                <span class="logo-sub">AUCTION PRO</span>
            </a>

            <!-- 主選單 -->
            <div class="nav-menu">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏠</span>
                    商城大廳
                </a>
                
                <?php if ($isLoggedIn): ?>
                <a href="sell.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📤</span>
                    上架商品
                </a>
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span>
                    會員中心
                </a>
                <a href="cart.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🛒</span>
                    購物車
                    <span class="cart-badge" id="cart-count" style="display: none;">0</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- 用戶區域 -->
            <div class="nav-user">
                <?php if ($isLoggedIn): ?>
                    <!-- 餘額顯示 -->
                    <div class="balance-display">
                        <span class="balance-label">可用餘額</span>
                        <span class="balance-amount" id="user-balance">
                            $<?php echo number_format($currentUser['balance'], 2); ?>
                        </span>
                    </div>
                    
                    <!-- 成就稱號 -->
                    <?php $achievement = calculateAchievement($currentUser['total_bid_amount']); ?>
                    <div class="achievement-badge" style="background: linear-gradient(135deg, <?php echo $achievement['color']; ?>22, <?php echo $achievement['color']; ?>44);">
                        <span class="achievement-icon"><?php echo $achievement['icon']; ?></span>
                        <span class="achievement-title"><?php echo h($achievement['title']); ?></span>
                    </div>
                    
                    <!-- 用戶選單 -->
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <span class="user-avatar">
                                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                            </span>
                            <span class="user-name"><?php echo h($currentUser['username']); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php?id=<?php echo $currentUser['id']; ?>">
                                <span>👤</span> 我的檔案
                            </a>
                            <a href="dashboard.php">
                                <span>⚙️</span> 會員中心
                            </a>
                            <a href="chat.php">
                                <span>💬</span> 私訊
                            </a>
                            <hr>
                            <a href="#" onclick="logout(); return false;" class="logout-link">
                                <span>🚪</span> 登出
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="btn btn-outline" onclick="showLoginModal()">登入</button>
                    <button class="btn btn-primary" onclick="showRegisterModal()">註冊</button>
                <?php endif; ?>
            </div>

            <!-- 行動端選單按鈕 -->
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- 行動端選單 -->
    <div class="mobile-menu" id="mobile-menu">
        <a href="index.php">🏠 商城大廳</a>
        <?php if ($isLoggedIn): ?>
        <a href="sell.php">📤 上架商品</a>
        <a href="dashboard.php">⚙️ 會員中心</a>
        <a href="chat.php">💬 訊息</a>
        <a href="#" onclick="logout(); return false;">🚪 登出</a>
        <?php else: ?>
        <a href="#" onclick="showLoginModal(); return false;">登入</a>
        <a href="#" onclick="showRegisterModal(); return false;">註冊</a>
        <?php endif; ?>
    </div>

    <!-- 登入模態框 -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal('login-modal')">&times;</button>
            <h2 class="modal-title">
                <span class="logo-icon">⚡</span>
                登入 Velocity
            </h2>
            <form id="login-form" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label>用戶名或電子郵件</label>
                    <input type="text" name="username" required placeholder="請輸入用戶名或電子郵件">
                </div>
                <div class="form-group">
                    <label>密碼</label>
                    <input type="password" name="password" required placeholder="請輸入密碼">
                </div>
                <button type="submit" class="btn btn-primary btn-block">登入</button>
            </form>
            <p class="modal-footer">
                還沒有帳號？ <a href="#" onclick="showRegisterModal(); return false;">立即註冊</a>
            </p>
        </div>
    </div>

    <!-- 註冊模態框 -->
    <div class="modal-overlay" id="register-modal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal('register-modal')">&times;</button>
            <h2 class="modal-title">
                <span class="logo-icon">⚡</span>
                註冊新帳號
            </h2>
            <form id="register-form" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label>用戶名</label>
                    <input type="text" name="username" required placeholder="3-50 個字元" minlength="3" maxlength="50">
                </div>
                <div class="form-group">
                    <label>電子郵件</label>
                    <input type="email" name="email" required placeholder="請輸入電子郵件">
                </div>
                <div class="form-group">
                    <label>密碼</label>
                    <input type="password" name="password" required placeholder="至少 6 個字元" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary btn-block">建立帳號</button>
            </form>
            <p class="modal-footer">
                已有帳號？ <a href="#" onclick="showLoginModal(); return false;">立即登入</a>
            </p>
        </div>
    </div>

    <script>
    // ============================================
    // 導航列與認證腳本
    // ============================================
    
    // 跑馬燈更新
    function updateTicker() {
        fetch('api.php?action=get_activities&limit=10')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.activities.length > 0) {
                    const tickerItems = document.querySelector('.ticker-items');
                    let html = '';
                    data.activities.forEach(activity => {
                        const icon = activity.type === 'bid' ? '🔥' : 
                                     activity.type === 'sale' ? '💰' : '🆕';
                        html += `
                            <span class="ticker-item">
                                <span class="ticker-icon">${icon}</span>
                                ${escapeHtml(activity.message)}
                                ${activity.amount ? `<span class="ticker-amount">$${parseFloat(activity.amount).toLocaleString()}</span>` : ''}
                            </span>
                        `;
                    });
                    // 複製一份以實現無縫循環
                    tickerItems.innerHTML = html + html;
                }
            })
            .catch(console.error);
    }
    
    // HTML 轉義
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // 模態框控制
    function showLoginModal() {
        closeModal('register-modal');
        document.getElementById('login-modal').classList.add('active');
    }
    
    function showRegisterModal() {
        closeModal('login-modal');
        document.getElementById('register-modal').classList.add('active');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    
    // 點擊外部關閉模態框
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // 登入處理
    function handleLogin(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', 'login');
        
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('login-modal');
                Swal.fire({
                    icon: 'success',
                    title: '登入成功！',
                    text: `歡迎回來，${data.username}`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '登入失敗',
                    text: data.message
                });
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: '網路錯誤，請稍後再試'
            });
        });
    }
    
    // 註冊處理
    function handleRegister(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', 'register');
        
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('register-modal');
                Swal.fire({
                    icon: 'success',
                    title: '註冊成功！',
                    text: '您的帳號已建立，將自動登入',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '註冊失敗',
                    text: data.message
                });
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: '網路錯誤，請稍後再試'
            });
        });
    }
    
    // 登出
    function logout() {
        Swal.fire({
            title: '確定要登出嗎？',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '登出',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=logout'
                })
                .then(() => {
                    location.href = 'index.php';
                });
            }
        });
    }
    
    // 行動端選單
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('active');
    }
    
    // 用戶下拉選單
    document.querySelectorAll('.user-dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            this.classList.toggle('active');
            e.stopPropagation();
        });
    });
    
    document.addEventListener('click', () => {
        document.querySelectorAll('.user-dropdown').forEach(d => d.classList.remove('active'));
    });
    
    // 初始化
    document.addEventListener('DOMContentLoaded', function() {
        updateTicker();
        // 每 30 秒更新一次跑馬燈
        setInterval(updateTicker, 30000);
    });
    </script>
