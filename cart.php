<?php
/**
 * Velocity Auction Pro - 購物車
 * cart.php
 */

require_once __DIR__ . '/functions.php';
initSession();

if (!isLoggedIn()) {
    header('Location: index.php?error=login_required');
    exit;
}

$pageTitle = '購物車';
require_once __DIR__ . '/navbar.php';

$userId = getCurrentUserId();

// 取得購物車內容
$stmt = $pdo->prepare("
    SELECT c.*, p.title, p.image_url, p.current_price, p.status, p.seller_id,
            u.username as seller_name, p.stock, p.auction_type
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN users u ON p.seller_id = u.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

$totalAmount = 0;
$validItemsCount = 0;

foreach ($cartItems as $item) {
    if ($item['status'] === 'active') {
        $totalAmount += $item['current_price'] * $item['quantity'];
        $validItemsCount++;
    }
}
?>

<main class="main-content">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">🛒 購物車</h1>
        </div>

        <div class="cart-layout">
            <div class="cart-items">
                <?php if (count($cartItems) > 0): ?>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" id="cart-item-<?php echo $item['product_id']; ?>">
                        <div class="cart-item-image">
                            <img src="<?php echo h($item['image_url'] ?: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100'); ?>" alt="Product">
                        </div>
                        <div class="cart-item-info">
                            <div class="item-meta">
                                <span class="badge <?php echo $item['auction_type'] === 'fixed' ? 'badge-fixed' : 'badge-private'; ?>">
                                    <?php echo $item['auction_type'] === 'fixed' ? '直購' : '專屬'; ?>
                                </span>
                                <?php if ($item['status'] !== 'active'): ?>
                                    <span class="badge badge-auction">已下架</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="item-title">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                    <?php echo h($item['title']); ?>
                                </a>
                            </h3>
                            <p class="item-seller">賣家：<?php echo h($item['seller_name']); ?></p>
                            <p class="item-stock text-muted">庫存：<?php echo $item['stock']; ?></p>
                        </div>
                        <div class="cart-item-price">
                            <?php echo formatMoney($item['current_price']); ?>
                        </div>
                        <div class="cart-item-quantity">
                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">-</button>
                            <input type="number" class="qty-input" value="<?php echo $item['quantity']; ?>" readonly>
                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">+</button>
                        </div>
                        <div class="cart-item-subtotal">
                            <?php echo formatMoney($item['current_price'] * $item['quantity']); ?>
                        </div>
                        <button class="btn-remove" onclick="removeFromCart(<?php echo $item['product_id']; ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🛒</div>
                        <h3 class="empty-title">購物車是空的</h3>
                        <p class="empty-text">去逛逛喜歡的商品吧！</p>
                        <a href="index.php" class="btn btn-primary mt-3">前往商城</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($cartItems) > 0): ?>
            <div class="cart-summary">
                <div class="summary-card">
                    <h3>訂單摘要</h3>
                    <div class="summary-row">
                        <span>商品總數</span>
                        <span><?php echo $validItemsCount; ?> 件</span>
                    </div>
                    <div class="summary-row total">
                        <span>總金額</span>
                        <span><?php echo formatMoney($totalAmount); ?></span>
                    </div>
                    <button class="btn btn-primary btn-block btn-lg mt-3" onclick="checkout()" <?php echo $validItemsCount === 0 ? 'disabled' : ''; ?>>
                        前往結帳
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
}

.cart-items {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr 120px 120px 120px 40px;
    align-items: center;
    gap: 20px;
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-image img {
    width: 100px;
    height: 100px;
    border-radius: var(--border-radius-sm);
    object-fit: cover;
}

.item-meta {
    margin-bottom: 8px;
    display: flex;
    gap: 8px;
}

.item-title {
    font-size: 16px;
    margin-bottom: 4px;
}

.item-title a {
    color: var(--text-primary);
}

.item-title a:hover {
    color: var(--accent-gold);
}

.item-seller {
    font-size: 13px;
    color: var(--text-muted);
}

.cart-item-price {
    font-weight: 600;
}

.cart-item-quantity {
    display: flex;
    align-items: center;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-sm);
}

.qty-btn {
    width: 32px;
    height: 32px;
    background: var(--bg-tertiary);
    border: none;
    color: var(--text-primary);
    cursor: pointer;
    transition: var(--transition-fast);
}

.qty-btn:hover {
    background: var(--bg-hover);
}

.qty-input {
    width: 40px;
    height: 32px;
    border: none;
    background: transparent;
    text-align: center;
    color: var(--text-primary);
    -moz-appearance: textfield;
}

.cart-item-subtotal {
    font-weight: 700;
    color: var(--accent-gold);
    text-align: right;
}

.btn-remove {
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 18px;
}

.btn-remove:hover {
    color: var(--accent-red);
}

.summary-card {
    background: var(--bg-card);
    padding: 24px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    position: sticky;
    top: 100px;
}

.summary-card h3 {
    margin-bottom: 20px;
    font-size: 18px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--text-secondary);
}

.summary-row.total {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
}

@media (max-width: 1024px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        grid-template-rows: auto auto;
        gap: 12px;
    }
    
    .cart-item-image {
        grid-row: 1 / 3;
    }
    
    .cart-item-info {
        grid-column: 2;
    }
    
    .cart-item-price,
    .cart-item-quantity,
    .cart-item-subtotal,
    .btn-remove {
        grid-row: 2;
        grid-column: auto;
    }
    
    .cart-item-quantity {
        justify-self: start;
    }
    
    .cart-item-subtotal {
        margin-left: auto;
    }
}
</style>

<script>
function updateQuantity(productId, change) {
    const row = document.getElementById(`cart-item-${productId}`);
    const input = row.querySelector('.qty-input');
    const newQty = parseInt(input.value) + change;
    
    if (newQty < 1) {
        removeFromCart(productId);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_cart');
    formData.append('product_id', productId);
    formData.append('quantity', newQty);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: '更新失敗',
                text: data.message
            });
        }
    });
}

function removeFromCart(productId) {
    Swal.fire({
        title: '確認移除？',
        text: '確定要將此商品從購物車移除嗎？',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '移除',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('product_id', productId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    });
}

function checkout() {
    window.location.href = 'checkout.php?source=cart';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
