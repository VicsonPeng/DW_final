<?php
/**
 * ============================================
 * Velocity Auction Pro - 賣家個人頁面
 * profile.php
 * ============================================
 * 顯示賣家資訊、平均評分、所有評價、活躍商品
 */

$pageTitle = '賣家檔案';
require_once __DIR__ . '/functions.php';

$sellerId = (int)($_GET['id'] ?? 0);

// 取得賣家資訊
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: index.php?error=user_not_found');
    exit;
}

// 取得評價統計
$sellerRating = getSellerRating($sellerId);
$achievement = calculateAchievement($seller['total_bid_amount']);

// 取得所有評價
$stmt = $pdo->prepare("
    SELECT r.*, u.username as reviewer_name, p.title as product_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.seller_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$sellerId]);
$reviews = $stmt->fetchAll();

// 取得賣家的活躍商品
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE seller_id = ? AND status = 'active' AND end_time > NOW()
    ORDER BY end_time ASC
");
$stmt->execute([$sellerId]);
$activeProducts = $stmt->fetchAll();

// 統計
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
$stmt->execute([$sellerId]);
$totalProducts = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ?");
$stmt->execute([$sellerId]);
$totalSales = $stmt->fetchColumn();

$pageTitle = $seller['username'] . ' 的賣家檔案';
require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container">
        <!-- 賣家檔案卡片 -->
        <div class="seller-profile-card">
            <div class="seller-profile-left">
                <div class="seller-avatar-xl">
                    <?php echo strtoupper(substr($seller['username'], 0, 1)); ?>
                </div>
                <div class="seller-info">
                    <h1 class="seller-name"><?php echo h($seller['username']); ?></h1>
                    <div class="seller-achievement" style="color: <?php echo $achievement['color']; ?>;">
                        <span><?php echo $achievement['icon']; ?></span>
                        <span><?php echo h($achievement['title']); ?></span>
                    </div>
                    <?php if ($seller['bio']): ?>
                    <p class="seller-bio"><?php echo nl2br(h($seller['bio'])); ?></p>
                    <?php endif; ?>
                    <p class="seller-join">加入於 <?php echo date('Y年m月d日', strtotime($seller['created_at'])); ?></p>
                    
                    <?php if (isLoggedIn() && getCurrentUserId() !== $sellerId): ?>
                    <a href="chat.php?user=<?php echo $sellerId; ?>" class="btn btn-outline mt-2">
                        💬 發送訊息
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="seller-profile-right">
                <!-- 評價摘要 -->
                <div class="rating-summary">
                    <div class="rating-big">
                        <span class="rating-number"><?php echo $sellerRating['average_rating']; ?></span>
                        <span class="rating-star">★</span>
                    </div>
                    <p class="rating-count"><?php echo $sellerRating['total_reviews']; ?> 則評價</p>
                    
                    <!-- 評分分佈 -->
                    <div class="rating-bars">
                        <?php for ($i = 5; $i >= 1; $i--): 
                            $count = $sellerRating[$i === 5 ? 'five_star' : 
                                                   ($i === 4 ? 'four_star' : 
                                                   ($i === 3 ? 'three_star' : 
                                                   ($i === 2 ? 'two_star' : 'one_star')))];
                            $percent = $sellerRating['total_reviews'] > 0 ? ($count / $sellerRating['total_reviews']) * 100 : 0;
                        ?>
                        <div class="rating-bar-row">
                            <span class="rating-bar-label"><?php echo $i; ?> ★</span>
                            <div class="rating-bar-track">
                                <div class="rating-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <span class="rating-bar-count"><?php echo $count; ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- 統計 -->
                <div class="seller-stats">
                    <div class="seller-stat">
                        <span class="stat-num"><?php echo $totalProducts; ?></span>
                        <span class="stat-label">商品</span>
                    </div>
                    <div class="seller-stat">
                        <span class="stat-num"><?php echo $totalSales; ?></span>
                        <span class="stat-label">成交</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-content-grid">
            <!-- 評價列表 -->
            <div class="reviews-section">
                <h2 class="section-title">⭐ 買家評價</h2>
                
                <?php if (count($reviews) > 0): ?>
                <div class="review-list">
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <span class="reviewer-avatar">
                                    <?php echo strtoupper(substr($review['reviewer_name'], 0, 1)); ?>
                                </span>
                                <div>
                                    <span class="reviewer-name"><?php echo h($review['reviewer_name']); ?></span>
                                    <div class="review-meta">
                                        <span class="review-product">購買了：<?php echo h($review['product_title']); ?></span>
                                        <span class="review-date"><?php echo timeAgo($review['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if ($review['comment']): ?>
                        <div class="review-content">
                            <?php echo nl2br(h($review['comment'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">⭐</div>
                    <h3 class="empty-title">尚無評價</h3>
                    <p class="empty-text">此賣家還沒有收到任何評價</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- 活躍商品 -->
            <div class="active-products-section">
                <h2 class="section-title">📦 正在販售</h2>
                
                <?php if (count($activeProducts) > 0): ?>
                <div class="product-list-mini">
                    <?php foreach ($activeProducts as $product): ?>
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="product-item-mini">
                        <img src="<?php echo h($product['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100'); ?>"
                             class="product-thumb">
                        <div class="product-item-info">
                            <h4><?php echo h($product['title']); ?></h4>
                            <p class="product-item-price"><?php echo formatMoney($product['current_price']); ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">目前沒有正在販售的商品</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
/* 賣家檔案頁面樣式 */
.seller-profile-card {
    display: flex;
    justify-content: space-between;
    gap: 40px;
    padding: 40px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    margin-bottom: 32px;
}

.seller-profile-left {
    display: flex;
    gap: 24px;
}

.seller-avatar-xl {
    width: 100px;
    height: 100px;
    background: var(--gradient-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 700;
    flex-shrink: 0;
}

.seller-name {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}

.seller-achievement {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 12px;
}

.seller-bio {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 12px;
}

.seller-join {
    font-size: 13px;
    color: var(--text-muted);
}

/* 評價摘要 */
.rating-summary {
    text-align: center;
    margin-bottom: 24px;
}

.rating-big {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.rating-number {
    font-family: var(--font-display);
    font-size: 48px;
    font-weight: 700;
    color: var(--accent-gold);
}

.rating-star {
    font-size: 32px;
    color: var(--accent-gold);
}

.rating-count {
    color: var(--text-muted);
    margin-top: 4px;
}

/* 評分分佈 */
.rating-bars {
    margin-top: 16px;
}

.rating-bar-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.rating-bar-label {
    width: 40px;
    font-size: 12px;
    color: var(--text-muted);
}

.rating-bar-track {
    flex: 1;
    height: 6px;
    background: var(--bg-tertiary);
    border-radius: 3px;
    overflow: hidden;
}

.rating-bar-fill {
    height: 100%;
    background: var(--gradient-gold);
    border-radius: 3px;
}

.rating-bar-count {
    width: 30px;
    font-size: 12px;
    color: var(--text-muted);
    text-align: right;
}

/* 賣家統計 */
.seller-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
}

.seller-stat {
    text-align: center;
}

.stat-num {
    display: block;
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

/* 內容網格 */
.profile-content-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
}

.reviews-section,
.active-products-section {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
}

/* 評價卡片 */
.review-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.review-card {
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.reviewer-info {
    display: flex;
    gap: 12px;
}

.reviewer-avatar {
    width: 40px;
    height: 40px;
    background: var(--gradient-purple);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.reviewer-name {
    font-weight: 600;
}

.review-meta {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.review-product {
    margin-right: 8px;
}

.review-rating .star {
    color: var(--text-muted);
    font-size: 14px;
}

.review-rating .star.active {
    color: var(--accent-gold);
}

.review-content {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* 商品列表迷你版 */
.product-list-mini {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.product-item-mini {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    color: var(--text-primary);
    transition: var(--transition-fast);
}

.product-item-mini:hover {
    background: var(--bg-hover);
}

.product-thumb {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-sm);
    object-fit: cover;
}

.product-item-info h4 {
    font-size: 14px;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-item-price {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 600;
    color: var(--accent-gold);
}

/* 響應式 */
@media (max-width: 1024px) {
    .profile-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .seller-profile-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 24px;
    }
    
    .seller-profile-left {
        flex-direction: column;
        align-items: center;
    }
    
    .review-header {
        flex-direction: column;
        gap: 12px;
    }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
