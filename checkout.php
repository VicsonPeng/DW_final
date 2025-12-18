<?php
/**
 * ============================================
 * Velocity Auction Pro - 結帳頁面
 * checkout.php
 * ============================================
 * 填寫收貨資訊、確認訂單
 */

$pageTitle = '結帳';
require_once __DIR__ . '/functions.php';

// 需要登入
requireLogin('index.php');

$orderId = (int)($_GET['order_id'] ?? 0);

// 取得訂單資訊
$stmt = $pdo->prepare("
    SELECT o.*, p.title as product_title, p.image_url, p.description,
           seller.username as seller_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users seller ON o.seller_id = seller.id
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->execute([$orderId, getCurrentUserId()]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php?error=order_not_found');
    exit;
}

require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container-sm">
        <div class="page-header text-center">
            <h1 class="page-title">📦 完成訂單</h1>
            <p class="page-subtitle">填寫收貨資訊以完成交易</p>
        </div>

        <div class="checkout-layout">
            <!-- 訂單摘要 -->
            <div class="order-summary-card">
                <h3 class="card-title">🛒 訂單摘要</h3>
                
                <div class="order-product">
                    <img src="<?php echo h($order['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=200'); ?>" 
                         class="order-product-image">
                    <div class="order-product-info">
                        <h4><?php echo h($order['product_title']); ?></h4>
                        <p>賣家：<?php echo h($order['seller_name']); ?></p>
                    </div>
                </div>

                <div class="order-price-breakdown">
                    <div class="price-row">
                        <span>商品金額</span>
                        <span><?php echo formatMoney($order['final_price']); ?></span>
                    </div>
                    <div class="price-row">
                        <span>運費</span>
                        <span class="text-success">免運費</span>
                    </div>
                    <hr>
                    <div class="price-row total">
                        <span>訂單總額</span>
                        <span class="text-gold"><?php echo formatMoney($order['final_price']); ?></span>
                    </div>
                </div>

                <div class="order-status-info">
                    <span class="status-icon">✅</span>
                    <span>已從您的餘額中扣除</span>
                </div>
            </div>

            <!-- 收貨資訊表單 -->
            <div class="shipping-form-card">
                <h3 class="card-title">📍 收貨資訊</h3>
                
                <?php if ($order['shipping_name']): ?>
                <!-- 已填寫收貨資訊 -->
                <div class="shipping-filled">
                    <div class="shipping-info-display">
                        <div class="info-row">
                            <span class="info-label">收件人</span>
                            <span class="info-value"><?php echo h($order['shipping_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">聯絡電話</span>
                            <span class="info-value"><?php echo h($order['shipping_phone']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">收貨地址</span>
                            <span class="info-value"><?php echo h($order['shipping_address']); ?></span>
                        </div>
                    </div>
                    
                    <div class="shipping-status">
                        <div class="status-badge <?php echo $order['shipping_status'] === 'shipped' ? 'status-success' : ''; ?>">
                            <?php 
                            echo match($order['shipping_status']) {
                                'pending' => '⏳ 等待發貨',
                                'shipped' => '🚚 已發貨',
                                'delivered' => '📬 已送達',
                                'completed' => '✅ 已完成',
                                default => $order['shipping_status']
                            };
                            ?>
                        </div>
                        <?php if ($order['tracking_number']): ?>
                        <p class="tracking-number">物流單號：<?php echo h($order['tracking_number']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <a href="dashboard.php" class="btn btn-secondary btn-block mt-3">返回會員中心</a>
                </div>
                <?php else: ?>
                <!-- 填寫收貨資訊 -->
                <form id="shipping-form" onsubmit="submitShipping(event)">
                    <div class="form-group">
                        <label>收件人姓名 <span class="required">*</span></label>
                        <input type="text" id="shipping_name" name="shipping_name" required 
                               placeholder="請輸入收件人姓名">
                    </div>

                    <div class="form-group">
                        <label>聯絡電話 <span class="required">*</span></label>
                        <input type="tel" id="shipping_phone" name="shipping_phone" required 
                               placeholder="請輸入聯絡電話">
                    </div>

                    <div class="form-group">
                        <label>收貨地址 <span class="required">*</span></label>
                        <textarea id="shipping_address" name="shipping_address" rows="3" required
                                  placeholder="請輸入完整收貨地址"></textarea>
                    </div>

                    <div class="form-hint mb-3">
                        ⚠️ 請確認收貨資訊正確，提交後將通知賣家發貨
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        ✅ 確認訂單
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
/* 結帳頁面樣式 */
.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    margin-top: 32px;
}

.order-summary-card,
.shipping-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 24px;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-color);
}

/* 訂單商品 */
.order-product {
    display: flex;
    gap: 16px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
}

.order-product-image {
    width: 100px;
    height: 100px;
    border-radius: var(--border-radius-sm);
    object-fit: cover;
}

.order-product-info h4 {
    font-size: 16px;
    margin-bottom: 8px;
}

.order-product-info p {
    font-size: 13px;
    color: var(--text-muted);
}

/* 價格明細 */
.order-price-breakdown {
    margin-bottom: 20px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--text-secondary);
}

.price-row.total {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.order-price-breakdown hr {
    border: none;
    border-top: 1px solid var(--border-color);
    margin: 16px 0;
}

/* 訂單狀態 */
.order-status-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: var(--border-radius-sm);
    color: var(--accent-green);
    font-size: 14px;
}

.status-icon {
    font-size: 18px;
}

/* 已填寫資訊 */
.shipping-filled {
    margin-top: 16px;
}

.shipping-info-display {
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    padding: 16px;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    margin-bottom: 12px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    width: 80px;
    color: var(--text-muted);
    font-size: 13px;
}

.info-value {
    flex: 1;
    color: var(--text-primary);
}

.shipping-status {
    text-align: center;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
}

.status-badge {
    display: inline-block;
    padding: 8px 20px;
    background: var(--bg-hover);
    border-radius: 20px;
    font-weight: 500;
}

.status-badge.status-success {
    background: rgba(16, 185, 129, 0.2);
    color: var(--accent-green);
}

.tracking-number {
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-muted);
}

/* 響應式 */
@media (max-width: 768px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .order-summary-card {
        order: 1;
    }
}
</style>

<script>
// ============================================
// 結帳頁面腳本
// ============================================

function submitShipping(e) {
    e.preventDefault();
    
    const name = document.getElementById('shipping_name').value.trim();
    const phone = document.getElementById('shipping_phone').value.trim();
    const address = document.getElementById('shipping_address').value.trim();
    
    if (!name || !phone || !address) {
        Swal.fire({
            icon: 'error',
            title: '資料不完整',
            text: '請填寫所有必填欄位'
        });
        return;
    }
    
    Swal.fire({
        title: '確認收貨資訊',
        html: `
            <p><strong>收件人：</strong>${escapeHtml(name)}</p>
            <p><strong>電話：</strong>${escapeHtml(phone)}</p>
            <p><strong>地址：</strong>${escapeHtml(address)}</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確認',
        cancelButtonText: '修改'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'checkout');
            formData.append('order_id', <?php echo $orderId; ?>);
            formData.append('shipping_name', name);
            formData.append('shipping_phone', phone);
            formData.append('shipping_address', address);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '訂單已確認！',
                        text: '賣家將盡快為您發貨',
                        confirmButtonText: '確定'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '提交失敗',
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
