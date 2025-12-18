<?php
/**
 * ============================================
 * Velocity Auction Pro - 會員中心
 * dashboard.php
 * ============================================
 * 包含：錢包、挖礦小遊戲、訂單紀錄、我的商品
 */

$pageTitle = '會員中心';
require_once __DIR__ . '/functions.php';

// 需要登入
requireLogin('index.php');

$currentUser = getCurrentUser();
$achievement = calculateAchievement($currentUser['total_bid_amount']);

// 取得用戶的商品
$stmt = $pdo->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC");
$stmt->execute([getCurrentUserId()]);
$myProducts = $stmt->fetchAll();

// 取得用戶的出價紀錄
$stmt = $pdo->prepare("
    SELECT b.*, p.title as product_title, p.status as product_status, p.image_url
    FROM bids b
    JOIN products p ON b.product_id = p.id
    WHERE b.bidder_id = ?
    ORDER BY b.created_at DESC
    LIMIT 20
");
$stmt->execute([getCurrentUserId()]);
$myBids = $stmt->fetchAll();

// 取得訂單（買家）
$stmt = $pdo->prepare("
    SELECT o.*, p.title as product_title, p.image_url, u.username as seller_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.seller_id = u.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([getCurrentUserId()]);
$myOrders = $stmt->fetchAll();

// 取得銷售訂單（賣家）
$stmt = $pdo->prepare("
    SELECT o.*, p.title as product_title, p.image_url, u.username as buyer_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([getCurrentUserId()]);
$mySales = $stmt->fetchAll();

require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container">
        <!-- 用戶資訊卡片 -->
        <div class="user-profile-card">
            <div class="profile-left">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo h($currentUser['username']); ?></h1>
                    <div class="profile-achievement" style="color: <?php echo $achievement['color']; ?>;">
                        <span><?php echo $achievement['icon']; ?></span>
                        <span><?php echo h($achievement['title']); ?></span>
                        <span class="achievement-level">Lv.<?php echo $achievement['level']; ?></span>
                    </div>
                    <p class="profile-join">會員自 <?php echo date('Y/m/d', strtotime($currentUser['created_at'])); ?></p>
                </div>
            </div>
            <div class="profile-right">
                <div class="wallet-display">
                    <div class="wallet-item">
                        <span class="wallet-label">可用餘額</span>
                        <span class="wallet-amount text-gold"><?php echo formatMoney($currentUser['balance']); ?></span>
                    </div>
                    <div class="wallet-item">
                        <span class="wallet-label">凍結金額</span>
                        <span class="wallet-amount"><?php echo formatMoney($currentUser['frozen_balance']); ?></span>
                    </div>
                    <div class="wallet-item">
                        <span class="wallet-label">累積挖礦</span>
                        <span class="wallet-amount text-success"><?php echo formatMoney($currentUser['mined_amount']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 標籤頁 -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('wallet')">💰 錢包</button>
            <button class="tab" onclick="switchTab('mining')">⛏️ 挖礦</button>
            <button class="tab" onclick="switchTab('products')">📦 我的商品</button>
            <button class="tab" onclick="switchTab('bids')">🔥 出價紀錄</button>
            <button class="tab" onclick="switchTab('orders')">📋 訂單</button>
            <button class="tab" onclick="switchTab('follows')">❤️ 關注</button>
        </div>

        <!-- 錢包 -->
        <div class="tab-content active" id="tab-wallet">
            <div class="grid-2">
                <div class="stat-card">
                    <div class="stat-icon">💵</div>
                    <div class="stat-info">
                        <span class="stat-label">總資產</span>
                        <span class="stat-value"><?php echo formatMoney($currentUser['balance'] + $currentUser['frozen_balance']); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <span class="stat-label">累積出價</span>
                        <span class="stat-value"><?php echo formatMoney($currentUser['total_bid_amount']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- 測試儲值 -->
            <div class="deposit-section mt-4">
                <h3>💳 測試儲值</h3>
                <form onsubmit="handleDeposit(event)" class="deposit-form">
                    <div class="form-row">
                        <div class="input-with-prefix">
                            <span class="input-prefix">$</span>
                            <input type="number" id="deposit-amount" name="amount" 
                                   min="1" max="1000000" step="0.01" placeholder="輸入金額" required>
                        </div>
                        <button type="submit" class="btn btn-primary">儲值</button>
                    </div>
                    <p class="form-hint">此為測試功能，可直接增加帳戶餘額</p>
                </form>
            </div>
            
            <div class="achievement-progress mt-4">
                <h3>🏆 成就進度</h3>
                <div class="progress-info">
                    <span>目前: <?php echo $achievement['icon']; ?> <?php echo h($achievement['title']); ?></span>
                    <?php
                    $nextAchievements = [
                        ['min' => 10000, 'title' => '活躍競標者'],
                        ['min' => 50000, 'title' => '收藏家'],
                        ['min' => 200000, 'title' => '資深藏家'],
                        ['min' => 500000, 'title' => '鑽石會員'],
                        ['min' => 1000000, 'title' => '鯨魚大戶'],
                        ['min' => 5000000, 'title' => '傳奇收藏家'],
                    ];
                    $nextLevel = null;
                    foreach ($nextAchievements as $na) {
                        if ($currentUser['total_bid_amount'] < $na['min']) {
                            $nextLevel = $na;
                            break;
                        }
                    }
                    if ($nextLevel):
                        $progress = ($currentUser['total_bid_amount'] / $nextLevel['min']) * 100;
                    ?>
                    <span>下一級: <?php echo h($nextLevel['title']); ?> (<?php echo formatMoney($nextLevel['min']); ?>)</span>
                    <?php endif; ?>
                </div>
                <?php if ($nextLevel): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, $progress); ?>%;"></div>
                </div>
                <?php else: ?>
                <p class="text-gold mt-2">🎉 您已達成最高成就！</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 挖礦 -->
        <div class="tab-content" id="tab-mining">
            <div class="mining-intro">
                <h2>⛏️ 資金挖礦</h2>
                <p>使用我們的虛擬挖礦系統，獲得免費的虛擬資金！每次挖礦可獲得 $10 - $500 的隨機獎勵。</p>
            </div>
            
            <div class="mining-terminal" id="mining-terminal">
                <div class="terminal-header">
                    <span class="terminal-dot red"></span>
                    <span class="terminal-dot yellow"></span>
                    <span class="terminal-dot green"></span>
                </div>
                <div class="terminal-output" id="terminal-output">
                    <div>Velocity Mining System v1.0.0</div>
                    <div>Ready to mine...</div>
                    <div><span class="terminal-cursor"></span></div>
                </div>
            </div>
            
            <div class="mining-controls mt-3">
                <button class="btn btn-primary btn-lg" id="mine-btn" onclick="startMining()">
                    ⛏️ 開始挖礦
                </button>
            </div>
            
            <div class="mining-stats mt-4">
                <h3>📊 挖礦統計</h3>
                <div class="grid-3">
                    <div class="stat-mini">
                        <span class="stat-mini-label">累積獎勵</span>
                        <span class="stat-mini-value" id="total-mined"><?php echo formatMoney($currentUser['mined_amount']); ?></span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-mini-label">本次獎勵</span>
                        <span class="stat-mini-value text-success" id="last-reward">$0.00</span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-mini-label">當前餘額</span>
                        <span class="stat-mini-value text-gold" id="current-balance"><?php echo formatMoney($currentUser['balance']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 我的商品 -->
        <div class="tab-content" id="tab-products">
            <?php if (count($myProducts) > 0): ?>
            <div class="product-grid">
                <?php foreach ($myProducts as $product): ?>
                <div class="product-card-mini product-card-editable">
                    <div class="card-image-container" onclick="location.href='product.php?id=<?php echo $product['id']; ?>'">
                        <img src="<?php echo h($product['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300'); ?>" 
                             class="product-card-image">
                        <?php
                        $statusBadge = match($product['status']) {
                            'active' => ['badge-auction', '進行中'],
                            'sold' => ['badge-sold', '已售出'],
                            'sold_out' => ['badge-ended', '已售完'],
                            'cancelled' => ['badge-ended', '已下架'],
                            default => ['badge-ended', '已結束']
                        };
                        ?>
                        <span class="badge product-status-badge <?php echo $statusBadge[0]; ?>">
                            <?php echo $statusBadge[1]; ?>
                        </span>
                    </div>
                    <div class="product-card-body">
                        <h4><?php echo h($product['title']); ?></h4>
                        <p class="product-card-price"><?php echo formatMoney($product['current_price']); ?></p>
                        <div class="product-meta">
                            <?php if ($product['auction_type'] !== 'auction'): ?>
                            <span class="stock-label">庫存: <?php echo $product['stock'] ?? 1; ?></span>
                            <?php else: ?>
                            <span class="bid-label"><?php echo $product['bid_count']; ?> 次出價</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-actions">
                            <button class="btn btn-sm btn-outline" onclick="event.stopPropagation(); showEditProductModal(<?php echo htmlspecialchars(json_encode([
                                'id' => $product['id'],
                                'title' => $product['title'],
                                'description' => $product['description'] ?? '',
                                'current_price' => $product['current_price'],
                                'stock' => $product['stock'] ?? 1,
                                'auction_type' => $product['auction_type'],
                                'bid_count' => $product['bid_count'],
                                'status' => $product['status']
                            ]), ENT_QUOTES, 'UTF-8'); ?>)">✏️ 編輯</button>
                            <?php if ($product['status'] === 'active' && ($product['auction_type'] !== 'auction' || $product['bid_count'] == 0)): ?>
                            <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteProduct(<?php echo $product['id']; ?>)">🗑️ 下架</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3 class="empty-title">尚無商品</h3>
                <p class="empty-text">您還沒有上架任何商品</p>
                <a href="sell.php" class="btn btn-primary">立即上架</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- 出價紀錄 -->
        <div class="tab-content" id="tab-bids">
            <?php if (count($myBids) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>出價金額</th>
                            <th>狀態</th>
                            <th>時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myBids as $bid): ?>
                        <tr onclick="location.href='product.php?id=<?php echo $bid['product_id']; ?>'" style="cursor: pointer;">
                            <td>
                                <div class="flex gap-2" style="align-items: center;">
                                    <img src="<?php echo h($bid['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=50'); ?>" 
                                         style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                                    <span><?php echo h($bid['product_title']); ?></span>
                                </div>
                            </td>
                            <td class="text-gold"><?php echo formatMoney($bid['amount']); ?></td>
                            <td>
                                <?php
                                $statusClass = match($bid['status']) {
                                    'active' => 'badge-auction',
                                    'won' => 'badge-sold',
                                    'outbid' => 'badge-ended',
                                    default => ''
                                };
                                $statusText = match($bid['status']) {
                                    'active' => '領先中',
                                    'won' => '得標',
                                    'outbid' => '已被超車',
                                    'refunded' => '已退款',
                                    default => $bid['status']
                                };
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                            <td class="text-muted"><?php echo timeAgo($bid['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔥</div>
                <h3 class="empty-title">尚無出價紀錄</h3>
                <p class="empty-text">快去競標您喜歡的商品吧！</p>
                <a href="index.php" class="btn btn-primary">探索商品</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- 訂單 -->
        <div class="tab-content" id="tab-orders">
            <div class="order-tabs">
                <button class="order-tab active" onclick="switchOrderTab('buy')">我購買的</button>
                <button class="order-tab" onclick="switchOrderTab('sell')">我賣出的</button>
            </div>
            
            <!-- 購買訂單 -->
            <div class="order-content active" id="orders-buy">
                <?php if (count($myOrders) > 0): ?>
                <div class="order-list">
                    <?php foreach ($myOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">訂單 #<?php echo $order['id']; ?></span>
                            <span class="badge <?php echo $order['status'] === 'completed' ? 'badge-sold' : 'badge-auction'; ?>">
                                <?php 
                                echo match($order['status']) {
                                    'paid' => '已付款',
                                    'shipped' => '已發貨',
                                    'completed' => '已完成',
                                    default => $order['status']
                                };
                                ?>
                            </span>
                        </div>
                        <div class="order-body">
                            <img src="<?php echo h($order['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100'); ?>" 
                                 class="order-image">
                            <div class="order-info">
                                <h4><?php echo h($order['product_title']); ?></h4>
                                <p>賣家：<?php echo h($order['seller_name']); ?></p>
                                <p class="order-price"><?php echo formatMoney($order['final_price']); ?></p>
                            </div>
                            <div class="order-actions">
                                <?php if (!$order['shipping_name']): ?>
                                <a href="checkout.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">填寫收貨資訊</a>
                                <?php elseif ($order['status'] === 'shipped'): ?>
                                <button class="btn btn-success btn-sm" onclick="confirmReceived(<?php echo $order['id']; ?>)">📦 確認收貨</button>
                                <?php elseif ($order['status'] === 'completed' && !$order['is_reviewed']): ?>
                                <button class="btn btn-secondary btn-sm" onclick="showReviewModal(<?php echo $order['id']; ?>)">⭐ 撰寫評價</button>
                                <?php elseif ($order['status'] === 'completed' && $order['is_reviewed']): ?>
                                <span class="text-success">✅ 已評價</span>
                                <?php elseif ($order['status'] === 'paid' && $order['shipping_name']): ?>
                                <span class="text-muted">等待賣家發貨</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3 class="empty-title">尚無訂單</h3>
                    <p class="empty-text">您還沒有購買任何商品</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 銷售訂單 -->
            <div class="order-content" id="orders-sell">
                <?php if (count($mySales) > 0): ?>
                <div class="order-list">
                    <?php foreach ($mySales as $sale): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">訂單 #<?php echo $sale['id']; ?></span>
                            <span class="badge <?php echo $sale['status'] === 'completed' ? 'badge-sold' : 'badge-auction'; ?>">
                                <?php 
                                echo match($sale['status']) {
                                    'paid' => '待發貨',
                                    'shipped' => '已發貨',
                                    'completed' => '已完成',
                                    default => $sale['status']
                                };
                                ?>
                            </span>
                        </div>
                        <div class="order-body">
                            <img src="<?php echo h($sale['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100'); ?>" 
                                 class="order-image">
                            <div class="order-info">
                                <h4><?php echo h($sale['product_title']); ?></h4>
                                <p>買家：<?php echo h($sale['buyer_name']); ?></p>
                                <p class="order-price text-success">+<?php echo formatMoney($sale['seller_received']); ?></p>
                            </div>
                            <div class="order-actions">
                                <?php if ($sale['shipping_name'] && $sale['status'] === 'paid'): ?>
                                <div class="shipping-info">
                                    <small>收件人：<?php echo h($sale['shipping_name']); ?></small><br>
                                    <small><?php echo h($sale['shipping_phone']); ?></small><br>
                                    <small><?php echo h($sale['shipping_address']); ?></small>
                                </div>
                                <button class="btn btn-primary btn-sm mt-2" onclick="shipOrder(<?php echo $sale['id']; ?>)">📤 標記已發貨</button>
                                <?php elseif ($sale['status'] === 'paid' && !$sale['shipping_name']): ?>
                                <span class="text-muted">等待買家填寫收貨資訊</span>
                                <?php elseif ($sale['status'] === 'shipped'): ?>
                                <span class="text-info">📦 已發貨，等待買家確認收貨</span>
                                <?php elseif ($sale['status'] === 'completed'): ?>
                                <span class="text-success">✅ 訂單已完成</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">💰</div>
                    <h3 class="empty-title">尚無銷售</h3>
                    <p class="empty-text">您還沒有賣出任何商品</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 關注 -->
        <div class="tab-content" id="tab-follows">
            <div class="order-tabs">
                <button class="order-tab active" onclick="switchFollowTab('following')">我關注的</button>
                <button class="order-tab" onclick="switchFollowTab('followers')">關注我的</button>
            </div>
            
            <!-- 我關注的 -->
            <div class="follow-content active" id="follows-following">
                <div class="follow-list" id="following-list">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
            
            <!-- 關注我的 -->
            <div class="follow-content" id="follows-followers">
                <div class="follow-list" id="followers-list">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- 評價模態框 -->
<div class="modal-overlay" id="review-modal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('review-modal')">&times;</button>
        <h2 class="modal-title">⭐ 撰寫評價</h2>
        <form onsubmit="submitReview(event)">
            <input type="hidden" id="review-order-id" value="">
            <div class="form-group">
                <label>評分</label>
                <div class="rating-stars" id="rating-stars">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                <input type="hidden" id="rating-value" value="5">
            </div>
            <div class="form-group">
                <label>評論</label>
                <textarea id="review-comment" rows="4" placeholder="分享您的購買體驗..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">提交評價</button>
        </form>
    </div>
</div>

<!-- 商品編輯模態框 -->
<div class="modal-overlay" id="edit-product-modal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('edit-product-modal')">&times;</button>
        <h2 class="modal-title">✏️ 編輯商品</h2>
        <form onsubmit="submitProductEdit(event)">
            <input type="hidden" id="edit-product-id" value="">
            <input type="hidden" id="edit-product-type" value="">
            <div class="form-group">
                <label>商品標題 <span class="required">*</span></label>
                <input type="text" id="edit-product-title" required minlength="5" maxlength="200">
            </div>
            <div class="form-group">
                <label>商品描述</label>
                <textarea id="edit-product-description" rows="4"></textarea>
            </div>
            <div class="form-group" id="edit-price-group">
                <label>價格 <span class="required">*</span></label>
                <div class="input-with-prefix">
                    <span class="input-prefix">$</span>
                    <input type="number" id="edit-product-price" min="1" step="0.01" required>
                </div>
                <p class="form-hint" id="price-warning" style="color: var(--accent-red); display: none;">
                    ⚠️ 競標商品已有出價，無法修改價格
                </p>
            </div>
            <div class="form-group" id="edit-stock-group">
                <label>庫存數量</label>
                <input type="number" id="edit-product-stock" min="0" value="1">
                <p class="form-hint">直購/專屬商品的庫存數量</p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">儲存變更</button>
        </form>
    </div>
</div>

<style>
/* 會員中心樣式 */
.user-profile-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 32px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    margin-bottom: 32px;
}

.profile-left {
    display: flex;
    align-items: center;
    gap: 24px;
}

.profile-avatar-large {
    width: 80px;
    height: 80px;
    background: var(--gradient-gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    color: #000;
}

.profile-name {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.profile-achievement {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
}

.achievement-level {
    font-size: 12px;
    opacity: 0.7;
}

.profile-join {
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 8px;
}

.wallet-display {
    display: flex;
    gap: 32px;
}

.wallet-item {
    text-align: center;
}

.wallet-label {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.wallet-amount {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 700;
}

/* 統計卡片 */
.stat-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}

.stat-icon {
    font-size: 40px;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
}

.stat-value {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--accent-gold);
}

/* 成就進度 */
.achievement-progress {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
}

.achievement-progress h3 {
    margin-bottom: 16px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.progress-bar {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--gradient-gold);
    border-radius: 4px;
    transition: width 0.5s ease;
}

/* 挖礦統計 */
.mining-intro {
    text-align: center;
    margin-bottom: 24px;
}

.mining-intro h2 {
    font-size: 24px;
    margin-bottom: 8px;
}

.mining-intro p {
    color: var(--text-secondary);
}

.mining-controls {
    text-align: center;
}

.mining-stats {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
}

.mining-stats h3 {
    margin-bottom: 16px;
}

.stat-mini {
    text-align: center;
}

.stat-mini-label {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.stat-mini-value {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 600;
}

/* 商品卡片迷你版 */
.product-card-mini {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    cursor: pointer;
    transition: var(--transition-fast);
}

.product-card-mini:hover {
    transform: translateY(-4px);
    border-color: var(--accent-gold);
}

.product-card-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.product-card-body {
    padding: 16px;
}

.product-card-body h4 {
    font-size: 15px;
    margin: 8px 0;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-card-price {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 600;
    color: var(--accent-gold);
}

/* 商品卡片編輯樣式 */
.product-card-editable {
    cursor: default;
}

.product-card-editable .card-image-container {
    position: relative;
    cursor: pointer;
}

.product-status-badge {
    position: absolute;
    top: 8px;
    left: 8px;
}

.product-meta {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.product-meta .stock-label {
    color: var(--accent-green);
}

.product-card-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.product-card-actions .btn {
    flex: 1;
    font-size: 12px;
    padding: 6px 10px;
}

.btn-danger {
    background: var(--accent-red);
    border-color: var(--accent-red);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

/* 訂單標籤頁 */
.order-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
}

.order-tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-sm);
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition-fast);
}

.order-tab:hover,
.order-tab.active {
    background: var(--bg-card);
    border-color: var(--accent-gold);
    color: var(--accent-gold);
}

.order-content {
    display: none;
}

.order-content.active {
    display: block;
}

/* 訂單卡片 */
.order-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
}

.order-id {
    font-weight: 600;
    font-size: 14px;
}

.order-body {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
}

.order-image {
    width: 80px;
    height: 80px;
    border-radius: var(--border-radius-sm);
    object-fit: cover;
}

.order-info {
    flex: 1;
}

.order-info h4 {
    font-size: 16px;
    margin-bottom: 4px;
}

.order-info p {
    font-size: 13px;
    color: var(--text-muted);
    margin: 2px 0;
}

.order-price {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 600;
    color: var(--accent-gold);
    margin-top: 8px !important;
}

.shipping-info {
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.6;
}

/* 響應式 */
@media (max-width: 768px) {
    .user-profile-card {
        flex-direction: column;
        gap: 24px;
        text-align: center;
    }
    
    .profile-left {
        flex-direction: column;
    }
    
    .wallet-display {
        flex-wrap: wrap;
        justify-content: center;
        gap: 16px;
    }
    
    .tabs {
        flex-wrap: wrap;
    }
    
    .order-body {
        flex-wrap: wrap;
    }
}

/* 關注列表 */
.follow-content {
    display: none;
}

.follow-content.active {
    display: block;
}

.follow-list {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    min-height: 200px;
}

.follow-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.follow-item:last-child {
    border-bottom: none;
}

.follow-user {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-primary);
}

.follow-avatar {
    width: 40px;
    height: 40px;
    background: var(--gradient-purple);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}

.follow-name {
    font-weight: 500;
}

.follow-time {
    font-size: 12px;
    color: var(--text-muted);
}
</style>

<script>
// ============================================
// 會員中心腳本
// ============================================

// 時間工具函數
function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const diff = Math.floor((Date.now() - time) / 1000);
    
    if (diff < 60) return '剛剛';
    if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
    if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
    if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
    return new Date(datetime).toLocaleDateString();
}
// ============================================

// 標籤頁切換
function switchTab(tabName) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

// 訂單標籤切換
function switchOrderTab(type) {
    document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.order-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('orders-' + type).classList.add('active');
}

// 挖礦動畫
let isMining = false;

function startMining() {
    if (isMining) return;
    isMining = true;
    
    const btn = document.getElementById('mine-btn');
    const output = document.getElementById('terminal-output');
    btn.disabled = true;
    btn.textContent = '挖礦中...';
    
    // 清空終端
    output.innerHTML = '';
    
    // 模擬挖礦過程
    const lines = [
        '> Initializing mining protocol...',
        '> Connecting to blockchain network...',
        '> Scanning for available blocks...',
        '> Block found! Starting hash computation...',
        '> Computing hash: 0x' + Math.random().toString(16).substr(2, 8) + '...',
        '> Verifying proof of work...',
        '> Block validated successfully!',
        '> Mining complete! Calculating rewards...'
    ];
    
    let lineIndex = 0;
    
    function addLine() {
        if (lineIndex < lines.length) {
            const div = document.createElement('div');
            div.className = 'terminal-line';
            div.style.animationDelay = (lineIndex * 0.1) + 's';
            div.textContent = lines[lineIndex];
            output.appendChild(div);
            lineIndex++;
            setTimeout(addLine, 300 + Math.random() * 200);
        } else {
            // 發送挖礦請求
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mine'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'terminal-line';
                    successDiv.style.color = '#00ff00';
                    successDiv.innerHTML = `> <strong>SUCCESS!</strong> You earned $${data.amount.toFixed(2)}!`;
                    output.appendChild(successDiv);
                    
                    // 更新統計
                    document.getElementById('last-reward').textContent = '$' + data.amount.toFixed(2);
                    
                    // 更新餘額
                    fetch('api.php?action=get_activities')
                        .then(() => location.reload());
                } else {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'terminal-line';
                    errorDiv.style.color = '#ff5555';
                    errorDiv.textContent = '> ERROR: ' + data.message;
                    output.appendChild(errorDiv);
                }
                
                btn.disabled = false;
                btn.textContent = '⛏️ 開始挖礦';
                isMining = false;
            });
        }
    }
    
    addLine();
}

// 評價星星互動
document.querySelectorAll('#rating-stars .star').forEach(star => {
    star.addEventListener('click', function() {
        const rating = this.dataset.rating;
        document.getElementById('rating-value').value = rating;
        
        document.querySelectorAll('#rating-stars .star').forEach((s, i) => {
            s.classList.toggle('active', i < rating);
        });
    });
    
    star.addEventListener('mouseenter', function() {
        const rating = this.dataset.rating;
        document.querySelectorAll('#rating-stars .star').forEach((s, i) => {
            s.classList.toggle('active', i < rating);
        });
    });
});

document.getElementById('rating-stars').addEventListener('mouseleave', function() {
    const rating = document.getElementById('rating-value').value;
    document.querySelectorAll('#rating-stars .star').forEach((s, i) => {
        s.classList.toggle('active', i < rating);
    });
});

// 顯示評價模態框
function showReviewModal(orderId) {
    document.getElementById('review-order-id').value = orderId;
    document.getElementById('review-modal').classList.add('active');
    
    // 預設5星
    document.getElementById('rating-value').value = 5;
    document.querySelectorAll('#rating-stars .star').forEach(s => s.classList.add('active'));
}

// 提交評價
function submitReview(e) {
    e.preventDefault();
    
    const orderId = document.getElementById('review-order-id').value;
    const rating = document.getElementById('rating-value').value;
    const comment = document.getElementById('review-comment').value;
    
    const formData = new FormData();
    formData.append('action', 'add_review');
    formData.append('order_id', orderId);
    formData.append('rating', rating);
    formData.append('comment', comment);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '評價成功！',
                text: '感謝您的評價',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '評價失敗',
                text: data.message
            });
        }
    });
}

// 標記已發貨
function shipOrder(orderId) {
    Swal.fire({
        title: '確認發貨',
        text: '確定要標記此訂單為已發貨嗎？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確認發貨',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'ship_order');
            formData.append('order_id', orderId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '已標記發貨！',
                        text: '買家已收到發貨通知',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '操作失敗',
                        text: data.message
                    });
                }
            });
        }
    });
}

// 確認收貨
function confirmReceived(orderId) {
    Swal.fire({
        title: '確認收貨',
        text: '確定已收到商品嗎？確認後訂單將完成',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確認收貨',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'confirm_received');
            formData.append('order_id', orderId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '訂單已完成！',
                        text: '您現在可以為此訂單撰寫評價',
                        confirmButtonText: '前往評價'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '操作失敗',
                        text: data.message
                    });
                }
            });
        }
    });
}

// 初始化評分星星
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#rating-stars .star').forEach(s => s.classList.add('active'));
});

// 處理儲值
function handleDeposit(e) {
    e.preventDefault();
    
    const amount = document.getElementById('deposit-amount').value;
    const formData = new FormData();
    formData.append('action', 'test_deposit');
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
                title: '儲值成功！',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '儲值失敗',
                text: data.message
            });
        }
    });
}

// 切換關注子標籤
function switchFollowTab(type) {
    document.querySelectorAll('.follow-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#tab-follows .order-tab').forEach(el => el.classList.remove('active'));
    
    document.getElementById('follows-' + type).classList.add('active');
    event.target.classList.add('active');
    
    if (type === 'following') {
        loadFollowing();
    } else {
        loadFollowers();
    }
}

// 載入我關注的
function loadFollowing() {
    fetch('api.php?action=get_following')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('following-list');
            if (data.success && data.following.length > 0) {
                container.innerHTML = data.following.map(user => `
                    <div class="follow-item">
                        <a href="profile.php?id=${user.seller_id}" class="follow-user">
                            <div class="follow-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <span class="follow-name">${escapeHtml(user.username)}</span>
                        </a>
                        <button class="btn btn-sm btn-outline" onclick="unfollowUser(${user.seller_id})">取消關注</button>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">❤️</div>
                        <h3 class="empty-title">尚未關注任何賣家</h3>
                        <p class="empty-text">去商品頁面關注您喜歡的賣家吧！</p>
                    </div>
                `;
            }
        });
}

// 載入關注我的
function loadFollowers() {
    fetch('api.php?action=get_followers')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('followers-list');
            if (data.success && data.followers.length > 0) {
                container.innerHTML = data.followers.map(user => `
                    <div class="follow-item">
                        <a href="profile.php?id=${user.follower_id}" class="follow-user">
                            <div class="follow-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <span class="follow-name">${escapeHtml(user.username)}</span>
                        </a>
                        <span class="follow-time">${timeAgo(user.created_at)}</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3 class="empty-title">尚無人關注您</h3>
                        <p class="empty-text">上架商品並積極互動來吸引關注！</p>
                    </div>
                `;
            }
        });
}

// 取消關注
function unfollowUser(sellerId) {
    const formData = new FormData();
    formData.append('action', 'unfollow_seller');
    formData.append('seller_id', sellerId);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadFollowing(); // 重新載入
        }
    });
}

// 初始化關注標籤
document.addEventListener('DOMContentLoaded', function() {
    // 如果 hash 是 follows，載入資料
    if (window.location.hash === '#follows') {
        switchTab('follows');
        loadFollowing();
    }
    
    // 綁定關注標籤點擊事件，使其在首次點擊時載入資料
    const followsTab = document.querySelector('.tab[onclick*="follows"]');
    if (followsTab) {
        followsTab.addEventListener('click', function() {
            // 延遲載入以確保 tab 切換完成
            setTimeout(loadFollowing, 100);
        });
    }
});

// 顯示商品編輯模態框
function showEditProductModal(product) {
    document.getElementById('edit-product-id').value = product.id;
    document.getElementById('edit-product-type').value = product.auction_type;
    document.getElementById('edit-product-title').value = product.title;
    document.getElementById('edit-product-description').value = product.description;
    document.getElementById('edit-product-price').value = product.current_price;
    document.getElementById('edit-product-stock').value = product.stock;
    
    // 競標商品有出價時停用價格編輯
    const priceInput = document.getElementById('edit-product-price');
    const priceWarning = document.getElementById('price-warning');
    
    if (product.auction_type === 'auction' && product.bid_count > 0) {
        priceInput.disabled = true;
        priceWarning.style.display = 'block';
    } else {
        priceInput.disabled = false;
        priceWarning.style.display = 'none';
    }
    
    // 非直購商品隱藏庫存欄位
    const stockGroup = document.getElementById('edit-stock-group');
    if (product.auction_type === 'auction') {
        stockGroup.style.display = 'none';
    } else {
        stockGroup.style.display = 'block';
    }
    
    document.getElementById('edit-product-modal').classList.add('active');
}

// 提交商品編輯
function submitProductEdit(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'update_product');
    formData.append('product_id', document.getElementById('edit-product-id').value);
    formData.append('title', document.getElementById('edit-product-title').value);
    formData.append('description', document.getElementById('edit-product-description').value);
    formData.append('price', document.getElementById('edit-product-price').value);
    formData.append('stock', document.getElementById('edit-product-stock').value);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal('edit-product-modal');
            Swal.fire({
                icon: 'success',
                title: '更新成功！',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '更新失敗',
                text: data.message
            });
        }
    });
}

// 下架商品
function deleteProduct(productId) {
    Swal.fire({
        title: '確定要下架此商品？',
        text: '下架後商品將不再顯示在市場中',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '確定下架',
        cancelButtonText: '取消',
        confirmButtonColor: '#ef4444'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_product');
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
                        title: '商品已下架',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '下架失敗',
                        text: data.message
                    });
                }
            });
        }
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
