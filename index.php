<?php
/**
 * ============================================
 * Velocity Auction Pro - 商城大廳
 * index.php
 * ============================================
 * 顯示所有活動商品，支援即時更新與分類篩選
 */

$pageTitle = '商城大廳';
require_once __DIR__ . '/navbar.php';

// 取得分類
$categories = [
    'all' => '全部商品',
    'electronics' => '電子產品',
    'art' => '藝術收藏',
    'antique' => '古董珍品',
    'fashion' => '時尚精品',
    'exclusive' => '限量專屬'
];
?>

<main class="main-content">
    <div class="container">
        <!-- 頁面標題 -->
        <div class="page-header">
            <h1 class="page-title">🏛️ 競標大廳</h1>
            <p class="page-subtitle">即時競標，價高者得。探索稀有珍品，開啟你的收藏之旅。</p>
        </div>

        <!-- 篩選器 -->
        <div class="filter-bar">
            <div class="filter-tabs">
                <button class="filter-tab active" data-type="all">
                    <span>📦</span> 全部
                </button>
                <button class="filter-tab" data-type="auction">
                    <span>🔥</span> 競標中
                </button>
                <button class="filter-tab" data-type="fixed">
                    <span>💰</span> 直購
                </button>
            </div>
            
            <div class="filter-categories">
                <select id="category-filter" class="category-select">
                    <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>"><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- 商品列表 -->
        <div class="product-grid" id="product-list">
            <!-- 動態載入 -->
            <div class="loading">
                <div class="loading-spinner"></div>
            </div>
        </div>

        <!-- 載入更多 -->
        <div class="load-more-wrapper" id="load-more-wrapper" style="display: none;">
            <button class="btn btn-secondary" id="load-more-btn" onclick="loadMoreProducts()">
                載入更多商品
            </button>
        </div>
    </div>
</main>

<style>
/* 篩選器樣式 */
.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 32px;
    padding: 16px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}

.filter-tabs {
    display: flex;
    gap: 8px;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-sm);
    color: var(--text-secondary);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition-fast);
}

.filter-tab:hover {
    border-color: var(--accent-gold);
    color: var(--accent-gold);
}

.filter-tab.active {
    background: var(--gradient-gold);
    border-color: transparent;
    color: #000;
}

.category-select {
    min-width: 180px;
}

.load-more-wrapper {
    text-align: center;
    margin-top: 40px;
}

/* 商品卡片增強 */
.product-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition-normal);
    cursor: pointer;
}

.product-card:hover {
    transform: translateY(-6px);
    border-color: var(--accent-gold);
    box-shadow: var(--shadow-glow);
}

.product-image-wrapper {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition-normal);
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.product-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.product-countdown-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
}

.product-body {
    padding: 20px;
}

.product-category {
    font-size: 11px;
    color: var(--accent-blue);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.product-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

.product-seller {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    color: var(--text-muted);
}

.seller-avatar-small {
    width: 24px;
    height: 24px;
    background: var(--gradient-purple);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: white;
}

.product-footer {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.product-price-info {
    flex: 1;
}

.product-price-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-price {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 700;
    color: var(--accent-gold);
}

.product-bids {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.product-action-btn {
    padding: 10px 20px;
    font-size: 13px;
}

/* 響應式 */
@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<script>
// ============================================
// 商城大廳腳本
// ============================================

let currentType = 'all';
let currentCategory = 'all';
let currentOffset = 0;
const limit = 12;
let loading = false;

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    
    // 綁定篩選事件
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentType = this.dataset.type;
            currentOffset = 0;
            loadProducts();
        });
    });
    
    document.getElementById('category-filter').addEventListener('change', function() {
        currentCategory = this.value;
        currentOffset = 0;
        loadProducts();
    });
    
    // 每 10 秒更新一次
    setInterval(updateProducts, 10000);
});

// 載入商品
function loadProducts(append = false) {
    if (loading) return;
    loading = true;
    
    const container = document.getElementById('product-list');
    
    if (!append) {
        container.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
    }
    
    let url = `api.php?action=get_products&type=${currentType}&limit=${limit}&offset=${currentOffset}`;
    if (currentCategory !== 'all') {
        url += `&category=${currentCategory}`;
    }
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            loading = false;
            
            if (!append) {
                container.innerHTML = '';
            }
            
            if (data.success && data.products.length > 0) {
                data.products.forEach(product => {
                    container.innerHTML += renderProductCard(product);
                });
                
                // 顯示載入更多按鈕
                if (data.products.length === limit) {
                    document.getElementById('load-more-wrapper').style.display = 'block';
                } else {
                    document.getElementById('load-more-wrapper').style.display = 'none';
                }
            } else if (!append) {
                container.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <div class="empty-icon">📦</div>
                        <h3 class="empty-title">暫無商品</h3>
                        <p class="empty-text">目前沒有符合條件的商品</p>
                        <a href="sell.php" class="btn btn-primary">上架第一個商品</a>
                    </div>
                `;
            }
        })
        .catch(err => {
            loading = false;
            container.innerHTML = `
                <div class="empty-state" style="grid-column: 1/-1;">
                    <div class="empty-icon">⚠️</div>
                    <h3 class="empty-title">載入失敗</h3>
                    <p class="empty-text">請稍後再試</p>
                </div>
            `;
        });
}

// 載入更多
function loadMoreProducts() {
    currentOffset += limit;
    loadProducts(true);
}

// 即時更新（不重整整個列表）
function updateProducts() {
    // 只更新價格和倒數
    document.querySelectorAll('.product-card[data-product-id]').forEach(card => {
        const productId = card.dataset.productId;
        
        fetch(`api.php?action=get_product_status&product_id=${productId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // 更新價格
                    const priceEl = card.querySelector('.product-price');
                    const oldPrice = parseFloat(priceEl.textContent.replace(/[$,]/g, ''));
                    const newPrice = data.current_price;
                    
                    if (newPrice > oldPrice) {
                        priceEl.textContent = '$' + newPrice.toLocaleString(undefined, {minimumFractionDigits: 2});
                        priceEl.classList.add('price-flash');
                        setTimeout(() => priceEl.classList.remove('price-flash'), 500);
                    }
                    
                    // 更新出價數
                    const bidsEl = card.querySelector('.product-bids');
                    if (bidsEl) {
                        bidsEl.textContent = `${data.bid_count} 次出價`;
                    }
                    
                    // 更新倒數
                    const countdownEl = card.querySelector('.countdown');
                    if (countdownEl && data.countdown) {
                        if (data.countdown.ended) {
                            countdownEl.innerHTML = '<span class="text-danger">已結束</span>';
                        } else {
                            const h = String(data.countdown.hours).padStart(2, '0');
                            const m = String(data.countdown.minutes).padStart(2, '0');
                            const s = String(data.countdown.seconds).padStart(2, '0');
                            countdownEl.innerHTML = `${h}<span class="countdown-separator">:</span>${m}<span class="countdown-separator">:</span>${s}`;
                            
                            // 最後 60 秒變紅
                            if (data.countdown.total_seconds <= 60) {
                                countdownEl.classList.add('urgent');
                            } else {
                                countdownEl.classList.remove('urgent');
                            }
                        }
                    }
                }
            });
    });
}

// 渲染商品卡片
function renderProductCard(product) {
    const isAuction = product.auction_type === 'auction';
    const isPrivate = product.auction_type === 'private';
    
    const badgeClass = isAuction ? 'badge-auction' : (isPrivate ? 'badge-private' : 'badge-fixed');
    const badgeText = isAuction ? '競標中' : (isPrivate ? '專屬' : '直購');
    
    const countdown = product.countdown;
    let countdownHtml = '';
    
    if (countdown.ended) {
        countdownHtml = '<span class="text-danger">已結束</span>';
    } else {
        const h = String(countdown.hours).padStart(2, '0');
        const m = String(countdown.minutes).padStart(2, '0');
        const s = String(countdown.seconds).padStart(2, '0');
        const urgentClass = countdown.total_seconds <= 60 ? 'urgent' : '';
        countdownHtml = `<span class="countdown ${urgentClass}">${h}<span class="countdown-separator">:</span>${m}<span class="countdown-separator">:</span>${s}</span>`;
    }
    
    const imageUrl = product.image_url || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400';
    const sellerInitial = product.seller_name ? product.seller_name.charAt(0).toUpperCase() : '?';
    
    const priceLabel = isAuction ? '當前價格' : '售價';
    const actionText = isAuction ? '立即出價' : '立即購買';
    
    return `
        <div class="product-card" data-product-id="${product.id}" onclick="location.href='product.php?id=${product.id}'">
            <div class="product-image-wrapper">
                <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(product.title)}" class="product-image">
                <div class="product-badges">
                    <span class="badge ${badgeClass}">${badgeText}</span>
                </div>
                ${isAuction ? `
                <div class="product-countdown-overlay">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 12px;">⏱️ 剩餘時間</span>
                        ${countdownHtml}
                    </div>
                </div>
                ` : ''}
            </div>
            <div class="product-body">
                <div class="product-category">${escapeHtml(product.category || 'general')}</div>
                <h3 class="product-title">${escapeHtml(product.title)}</h3>
                <div class="product-seller">
                    <span class="seller-avatar-small">${sellerInitial}</span>
                    <span>${escapeHtml(product.seller_name)}</span>
                </div>
                <div class="product-footer">
                    <div class="product-price-info">
                        <div class="product-price-label">${priceLabel}</div>
                        <div class="product-price">$${parseFloat(product.current_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                        ${isAuction ? `<div class="product-bids">${product.bid_count || 0} 次出價</div>` : ''}
                    </div>
                    <button class="btn btn-primary product-action-btn" onclick="event.stopPropagation(); location.href='product.php?id=${product.id}'">
                        ${actionText}
                    </button>
                </div>
            </div>
        </div>
    `;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
