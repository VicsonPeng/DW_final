<?php
/**
 * ============================================
 * Velocity Auction Pro - 即時競標室
 * product.php
 * ============================================
 * 商品詳情頁面，包含：
 * - Chart.js 價格走勢圖
 * - 即時倒數計時
 * - 出價表單與自動代標
 * - 公開留言板
 * - 私訊賣家功能
 */

$pageTitle = '商品詳情';
require_once __DIR__ . '/functions.php';

$productId = (int)($_GET['id'] ?? 0);
$product = getProduct($productId);

// 檢查商品是否存在
if (!$product) {
    header('Location: index.php?error=product_not_found');
    exit;
}

// 檢查私人商品權限
if (!canViewPrivateProduct($product, getCurrentUserId())) {
    header('Location: index.php?error=access_denied');
    exit;
}

// 更新瀏覽次數
$pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$productId]);

// 取得出價歷史
$bidHistory = getBidHistory($productId);
$highestBid = getHighestBid($productId);

// 計算最低出價金額
$minBid = $product['current_price'] + $product['min_increment'];

// 取得賣家評價
$sellerRating = getSellerRating($product['seller_id']);

$pageTitle = $product['title'];
require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container">
        <div class="product-detail-layout">
            <!-- 左側：商品圖片與資訊 -->
            <div class="product-info-section">
                <!-- 商品圖片 -->
                <div class="product-image-large">
                    <img src="<?php echo h($product['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800'); ?>" 
                         alt="<?php echo h($product['title']); ?>">
                    <div class="product-badges-large">
                        <?php 
                        $badgeClass = $product['auction_type'] === 'auction' ? 'badge-auction' : 
                                     ($product['auction_type'] === 'private' ? 'badge-private' : 'badge-fixed');
                        $badgeText = $product['auction_type'] === 'auction' ? '競標中' : 
                                    ($product['auction_type'] === 'private' ? '專屬商品' : '直購');
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                    </div>
                </div>

                <!-- 商品描述 -->
                <div class="product-description-box">
                    <h2 class="section-title">📋 商品描述</h2>
                    <div class="description-content">
                        <?php echo nl2br(h($product['description'] ?: '賣家尚未提供商品描述。')); ?>
                    </div>
                </div>

                <!-- 價格走勢圖（僅競標商品） -->
                <?php if ($product['auction_type'] === 'auction' && count($bidHistory) > 0): ?>
                <div class="chart-container">
                    <h3 class="chart-title">📈 價格走勢</h3>
                    <canvas id="priceChart" height="200"></canvas>
                </div>
                <?php endif; ?>

                <!-- 留言板 -->
                <div class="comments-section">
                    <h2 class="section-title">💬 問與答 (<?php echo count($bidHistory); ?>)</h2>
                    
                    <div class="comment-list" id="comment-list">
                        <!-- 動態載入 -->
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                    <form class="comment-form" onsubmit="submitComment(event)">
                        <input type="text" id="comment-input" placeholder="輸入您的問題..." required>
                        <button type="submit" class="btn btn-primary">發送</button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted mt-2">請先<a href="#" onclick="showLoginModal(); return false;">登入</a>後留言</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 右側：競標面板 -->
            <div class="bid-section">
                <!-- 商品標題與賣家 -->
                <div class="product-header-box">
                    <span class="product-category-tag"><?php echo h($product['category'] ?: 'general'); ?></span>
                    <h1 class="product-title-large"><?php echo h($product['title']); ?></h1>
                    
                    <a href="profile.php?id=<?php echo $product['seller_id']; ?>" class="seller-info-box">
                        <div class="seller-avatar-large">
                            <?php echo strtoupper(substr($product['seller_name'], 0, 1)); ?>
                        </div>
                        <div class="seller-details">
                            <span class="seller-name"><?php echo h($product['seller_name']); ?></span>
                            <span class="seller-rating">
                                ⭐ <?php echo $sellerRating['average_rating']; ?> 
                                (<?php echo $sellerRating['total_reviews']; ?> 則評價)
                            </span>
                        </div>
                        <?php if (isLoggedIn() && getCurrentUserId() !== $product['seller_id']): ?>
                        <button class="btn btn-sm btn-outline" onclick="event.preventDefault(); openChat(<?php echo $product['seller_id']; ?>)">
                            私訊賣家
                        </button>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- 競標面板 -->
                <div class="bid-panel">
                    <!-- 當前價格 -->
                    <div class="current-price-display">
                        <div class="price-label">
                            <?php echo $product['auction_type'] === 'auction' ? '當前最高價' : '售價'; ?>
                        </div>
                        <div class="price-value" id="current-price">
                            $<?php echo number_format($product['current_price'], 2); ?>
                        </div>
                        <?php if ($highestBid && $product['auction_type'] === 'auction'): ?>
                        <div class="highest-bidder">
                            目前最高出價者：<strong id="highest-bidder"><?php echo h($highestBid['bidder_name']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 倒數計時 -->
                    <?php if ($product['status'] === 'active'): ?>
                    <div class="countdown-display" id="countdown-container">
                        <div class="countdown-label">⏱️ 剩餘時間</div>
                        <div class="countdown-timer" id="countdown-timer" data-end="<?php echo $product['end_time']; ?>">
                            --:--:--
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="countdown-display ended">
                        <div class="countdown-label">拍賣狀態</div>
                        <div class="countdown-timer text-danger">已結束</div>
                    </div>
                    <?php endif; ?>

                    <!-- 出價表單 -->
                    <?php if ($product['status'] === 'active' && isLoggedIn() && getCurrentUserId() !== $product['seller_id']): ?>
                        <?php if ($product['auction_type'] === 'auction'): ?>
                        <!-- 競標模式 -->
                        <div class="bid-form-section">
                            <div class="form-group">
                                <label>出價金額 (最低 $<?php echo number_format($minBid, 2); ?>)</label>
                                <div class="bid-input-group">
                                    <input type="number" id="bid-amount" 
                                           min="<?php echo $minBid; ?>" 
                                           step="<?php echo $product['min_increment']; ?>"
                                           value="<?php echo $minBid; ?>"
                                           placeholder="輸入出價金額">
                                    <button class="btn btn-primary" onclick="placeBid()">
                                        🔥 出價
                                    </button>
                                </div>
                            </div>
                            
                            <!-- 自動代標設定 -->
                            <div class="auto-bid-section">
                                <div class="auto-bid-toggle">
                                    <label class="toggle-label">
                                        <input type="checkbox" id="auto-bid-toggle" onchange="toggleAutoBid()">
                                        <span class="toggle-text">🤖 啟用自動代標</span>
                                    </label>
                                </div>
                                <div class="auto-bid-form" id="auto-bid-form" style="display: none;">
                                    <div class="form-group">
                                        <label>自動出價上限</label>
                                        <div class="bid-input-group">
                                            <input type="number" id="auto-bid-max" 
                                                   min="<?php echo $minBid; ?>" 
                                                   placeholder="設定最高自動出價">
                                            <button class="btn btn-secondary" onclick="setAutoBid()">
                                                設定
                                            </button>
                                        </div>
                                        <p class="form-hint">系統將在他人出價時，自動幫您出價至此上限</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- 直購/專屬模式 -->
                        <div class="buy-now-section">
                            <button class="btn btn-primary btn-lg btn-block" onclick="buyNow()">
                                💰 立即購買 - $<?php echo number_format($product['current_price'], 2); ?>
                            </button>
                            <p class="form-hint text-center mt-2">點擊後將直接購買此商品</p>
                        </div>
                        <?php endif; ?>
                    <?php elseif (!isLoggedIn()): ?>
                    <div class="login-prompt">
                        <p>請先登入以進行競標或購買</p>
                        <button class="btn btn-primary btn-block" onclick="showLoginModal()">登入 / 註冊</button>
                    </div>
                    <?php elseif (getCurrentUserId() === $product['seller_id']): ?>
                    <div class="owner-notice">
                        <p>📦 這是您的商品</p>
                        <a href="dashboard.php" class="btn btn-secondary btn-block">前往管理</a>
                    </div>
                    <?php endif; ?>

                    <!-- 統計資訊 -->
                    <div class="bid-stats">
                        <div class="bid-stat">
                            <div class="bid-stat-label">出價次數</div>
                            <div class="bid-stat-value" id="bid-count"><?php echo $product['bid_count']; ?></div>
                        </div>
                        <div class="bid-stat">
                            <div class="bid-stat-label">瀏覽次數</div>
                            <div class="bid-stat-value"><?php echo $product['view_count']; ?></div>
                        </div>
                        <div class="bid-stat">
                            <div class="bid-stat-label">起標價</div>
                            <div class="bid-stat-value">$<?php echo number_format($product['starting_price'], 2); ?></div>
                        </div>
                        <div class="bid-stat">
                            <div class="bid-stat-label">最低加價</div>
                            <div class="bid-stat-value">$<?php echo number_format($product['min_increment'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- 出價歷史 -->
                <?php if ($product['auction_type'] === 'auction' && count($bidHistory) > 0): ?>
                <div class="bid-history-box">
                    <h3 class="section-title">📊 出價記錄</h3>
                    <div class="bid-history-list" id="bid-history">
                        <?php foreach (array_reverse(array_slice($bidHistory, -10)) as $bid): ?>
                        <div class="bid-history-item">
                            <div class="bid-user">
                                <span class="bid-avatar"><?php echo strtoupper(substr($bid['bidder_name'], 0, 1)); ?></span>
                                <span><?php echo h($bid['bidder_name']); ?></span>
                                <?php if ($bid['is_auto_bid']): ?>
                                <span class="auto-bid-tag">🤖 自動</span>
                                <?php endif; ?>
                            </div>
                            <div class="bid-amount">$<?php echo number_format($bid['amount'], 2); ?></div>
                            <div class="bid-time"><?php echo timeAgo($bid['created_at']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- 私訊模態框 -->
<div class="modal-overlay" id="chat-modal">
    <div class="modal" style="max-width: 500px;">
        <button class="modal-close" onclick="closeModal('chat-modal')">&times;</button>
        <h2 class="modal-title">💬 私訊賣家</h2>
        <form onsubmit="sendMessage(event)">
            <input type="hidden" id="message-receiver" value="">
            <div class="form-group">
                <label>訊息內容</label>
                <textarea id="message-content" rows="4" placeholder="請輸入您想詢問的內容..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">發送訊息</button>
        </form>
    </div>
</div>

<style>
/* 商品詳情頁面樣式 */
.product-detail-layout {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 32px;
}

.product-image-large {
    position: relative;
    border-radius: var(--border-radius);
    overflow: hidden;
    background: var(--bg-tertiary);
}

.product-image-large img {
    width: 100%;
    height: 400px;
    object-fit: cover;
}

.product-badges-large {
    position: absolute;
    top: 16px;
    left: 16px;
}

.product-description-box,
.comments-section,
.bid-history-box {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-top: 24px;
}

.description-content {
    color: var(--text-secondary);
    line-height: 1.8;
}

.product-header-box {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 24px;
}

.product-category-tag {
    display: inline-block;
    padding: 4px 12px;
    background: var(--bg-tertiary);
    border-radius: 20px;
    font-size: 11px;
    color: var(--accent-blue);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.product-title-large {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
    margin-bottom: 20px;
}

.seller-info-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    color: var(--text-primary);
}

.seller-avatar-large {
    width: 48px;
    height: 48px;
    background: var(--gradient-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 20px;
}

.seller-details {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.seller-name {
    font-weight: 600;
    font-size: 16px;
}

.seller-rating {
    font-size: 13px;
    color: var(--text-muted);
}

/* 倒數計時 */
.countdown-display {
    text-align: center;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    margin-bottom: 24px;
}

.countdown-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.countdown-timer {
    font-family: var(--font-display);
    font-size: 36px;
    font-weight: 700;
    color: var(--text-primary);
}

.countdown-timer.urgent {
    color: var(--accent-red);
    animation: countdown-pulse 0.5s infinite alternate;
}

.countdown-display.ended {
    background: rgba(239, 68, 68, 0.1);
}

/* 最高出價者 */
.highest-bidder {
    margin-top: 12px;
    font-size: 14px;
    color: var(--text-secondary);
}

.highest-bidder strong {
    color: var(--accent-gold);
}

/* 出價表單 */
.bid-form-section,
.buy-now-section {
    margin-bottom: 24px;
}

.auto-bid-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.auto-bid-toggle {
    margin-bottom: 12px;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.toggle-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent-gold);
}

.toggle-text {
    color: var(--text-secondary);
    font-weight: 500;
}

.auto-bid-form {
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
}

.login-prompt,
.owner-notice {
    text-align: center;
    padding: 24px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    color: var(--text-secondary);
}

/* 出價歷史 */
.bid-history-list {
    max-height: 300px;
    overflow-y: auto;
}

.bid-history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.bid-history-item:last-child {
    border-bottom: none;
}

.bid-user {
    display: flex;
    align-items: center;
    gap: 8px;
}

.bid-avatar {
    width: 28px;
    height: 28px;
    background: var(--gradient-purple);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
}

.bid-amount {
    font-family: var(--font-display);
    font-weight: 600;
    color: var(--accent-gold);
}

.bid-time {
    font-size: 12px;
    color: var(--text-muted);
}

.auto-bid-tag {
    font-size: 10px;
    padding: 2px 6px;
    background: rgba(139, 92, 246, 0.2);
    color: var(--accent-purple);
    border-radius: 4px;
}

/* 響應式 */
@media (max-width: 1024px) {
    .product-detail-layout {
        grid-template-columns: 1fr;
    }
    
    .bid-section {
        order: -1;
    }
}
</style>

<script>
// ============================================
// 即時競標室腳本
// ============================================

const productId = <?php echo $productId; ?>;
const isAuction = <?php echo $product['auction_type'] === 'auction' ? 'true' : 'false'; ?>;
const auctionEnded = <?php echo $product['status'] !== 'active' ? 'true' : 'false'; ?>;
let minBid = <?php echo $minBid; ?>;
let minIncrement = <?php echo $product['min_increment']; ?>;
let priceChart = null;

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化倒數計時
    if (!auctionEnded) {
        startCountdown();
    }
    
    // 初始化價格走勢圖
    if (isAuction) {
        initPriceChart();
    }
    
    // 載入留言
    loadComments();
    
    // 開始即時更新
    setInterval(updateProductStatus, 3000);
    setInterval(loadComments, 15000);
});

// 倒數計時
function startCountdown() {
    const timerEl = document.getElementById('countdown-timer');
    if (!timerEl) return;
    
    const endTime = new Date(timerEl.dataset.end).getTime();
    
    function update() {
        const now = Date.now();
        const diff = Math.max(0, Math.floor((endTime - now) / 1000));
        
        if (diff <= 0) {
            timerEl.innerHTML = '<span class="text-danger">已結束</span>';
            timerEl.classList.remove('urgent');
            return;
        }
        
        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;
        
        const h = String(hours).padStart(2, '0');
        const m = String(minutes).padStart(2, '0');
        const s = String(seconds).padStart(2, '0');
        
        timerEl.innerHTML = `${h}<span class="countdown-separator">:</span>${m}<span class="countdown-separator">:</span>${s}`;
        
        // 最後60秒變紅
        if (diff <= 60) {
            timerEl.classList.add('urgent');
        } else {
            timerEl.classList.remove('urgent');
        }
    }
    
    update();
    setInterval(update, 1000);
}

// 價格走勢圖
function initPriceChart() {
    const canvas = document.getElementById('priceChart');
    if (!canvas) return;
    
    fetch(`api.php?action=get_bid_history&product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.chart_data) {
                const ctx = canvas.getContext('2d');
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.chart_data.labels,
                        datasets: [{
                            label: '出價金額',
                            data: data.chart_data.data,
                            borderColor: '#f5a623',
                            backgroundColor: 'rgba(245, 166, 35, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#f5a623',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(255,255,255,0.05)' },
                                ticks: { color: '#64748b' }
                            },
                            y: {
                                grid: { color: 'rgba(255,255,255,0.05)' },
                                ticks: { 
                                    color: '#64748b',
                                    callback: value => '$' + value.toLocaleString()
                                }
                            }
                        }
                    }
                });
            }
        });
}

// 即時更新商品狀態
function updateProductStatus() {
    fetch(`api.php?action=get_product_status&product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // 更新價格
                const priceEl = document.getElementById('current-price');
                const oldPrice = parseFloat(priceEl.textContent.replace(/[$,]/g, ''));
                const newPrice = data.current_price;
                
                if (newPrice > oldPrice) {
                    priceEl.textContent = '$' + newPrice.toLocaleString(undefined, {minimumFractionDigits: 2});
                    priceEl.classList.add('price-flash');
                    setTimeout(() => priceEl.classList.remove('price-flash'), 500);
                    
                    // 更新最低出價
                    minBid = newPrice + minIncrement;
                    const bidInput = document.getElementById('bid-amount');
                    if (bidInput) {
                        bidInput.min = minBid;
                        if (parseFloat(bidInput.value) < minBid) {
                            bidInput.value = minBid;
                        }
                    }
                }
                
                // 更新最高出價者
                const bidderEl = document.getElementById('highest-bidder');
                if (bidderEl && data.highest_bidder) {
                    bidderEl.textContent = data.highest_bidder;
                }
                
                // 更新出價次數
                const countEl = document.getElementById('bid-count');
                if (countEl) {
                    countEl.textContent = data.bid_count;
                }
                
                // 更新倒數（處理延長）
                const timerEl = document.getElementById('countdown-timer');
                if (timerEl && data.end_time) {
                    timerEl.dataset.end = data.end_time;
                }
            }
        });
}

// 出價
function placeBid() {
    const amount = parseFloat(document.getElementById('bid-amount').value);
    
    if (isNaN(amount) || amount < minBid) {
        Swal.fire({
            icon: 'error',
            title: '出價金額不足',
            text: `最低出價金額為 $${minBid.toLocaleString()}`
        });
        return;
    }
    
    Swal.fire({
        title: '確認出價',
        html: `您即將出價 <strong>$${amount.toLocaleString()}</strong><br>此金額將從您的餘額中凍結`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確認出價',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'place_bid');
            formData.append('product_id', productId);
            formData.append('amount', amount);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '出價成功！',
                        text: '您目前是最高出價者',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    updateProductStatus();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '出價失敗',
                        text: data.message
                    });
                }
            });
        }
    });
}

// 切換自動代標
function toggleAutoBid() {
    const form = document.getElementById('auto-bid-form');
    const toggle = document.getElementById('auto-bid-toggle');
    form.style.display = toggle.checked ? 'block' : 'none';
}

// 設定自動代標
function setAutoBid() {
    const maxAmount = parseFloat(document.getElementById('auto-bid-max').value);
    
    if (isNaN(maxAmount) || maxAmount < minBid) {
        Swal.fire({
            icon: 'error',
            title: '金額不足',
            text: `自動出價上限至少需為 $${minBid.toLocaleString()}`
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'set_auto_bid');
    formData.append('product_id', productId);
    formData.append('max_amount', maxAmount);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '設定成功！',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '設定失敗',
                text: data.message
            });
        }
    });
}

// 直接購買
function buyNow() {
    Swal.fire({
        title: '確認購買',
        html: `您即將購買此商品<br>金額將從您的餘額中扣除`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確認購買',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'buy_now');
            formData.append('product_id', productId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '購買成功！',
                        text: '請前往會員中心填寫收貨資訊',
                        confirmButtonText: '前往'
                    }).then(() => {
                        location.href = 'checkout.php?order_id=' + data.order_id;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '購買失敗',
                        text: data.message
                    });
                }
            });
        }
    });
}

// 載入留言
function loadComments() {
    fetch(`api.php?action=get_comments&product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('comment-list');
            if (data.success && data.comments.length > 0) {
                container.innerHTML = data.comments.map(comment => `
                    <div class="comment-item">
                        <div class="comment-avatar">${comment.username.charAt(0).toUpperCase()}</div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author">${escapeHtml(comment.username)}</span>
                                <span class="comment-time">${timeAgo(comment.created_at)}</span>
                            </div>
                            <div class="comment-text">${escapeHtml(comment.content)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted text-center p-3">尚無留言</p>';
            }
        });
}

// 發送留言
function submitComment(e) {
    e.preventDefault();
    const input = document.getElementById('comment-input');
    const content = input.value.trim();
    
    if (!content) return;
    
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('product_id', productId);
    formData.append('content', content);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadComments();
        } else {
            Swal.fire({
                icon: 'error',
                title: '發送失敗',
                text: data.message
            });
        }
    });
}

// 開啟私訊
function openChat(sellerId) {
    document.getElementById('message-receiver').value = sellerId;
    document.getElementById('chat-modal').classList.add('active');
}

// 發送私訊
function sendMessage(e) {
    e.preventDefault();
    const receiverId = document.getElementById('message-receiver').value;
    const content = document.getElementById('message-content').value.trim();
    
    if (!content) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('receiver_id', receiverId);
    formData.append('product_id', productId);
    formData.append('content', content);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal('chat-modal');
            Swal.fire({
                icon: 'success',
                title: '訊息已發送',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '發送失敗',
                text: data.message
            });
        }
    });
}

// 工具函數
function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const diff = Math.floor((Date.now() - time) / 1000);
    
    if (diff < 60) return '剛剛';
    if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
    if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
    if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
    return new Date(datetime).toLocaleDateString();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
