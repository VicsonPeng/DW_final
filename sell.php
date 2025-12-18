<?php
/**
 * ============================================
 * Velocity Auction Pro - 商品上架頁面
 * sell.php
 * ============================================
 * 支援三種拍賣模式：
 * - Auction: 競標模式
 * - Fixed: 直購模式
 * - Private: 專屬買家模式
 */

$pageTitle = '上架商品';
require_once __DIR__ . '/functions.php';

// 需要登入
requireLogin('index.php');

$currentUser = getCurrentUser();

// 取得所有用戶（用於專屬買家選擇）
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username");
$stmt->execute([getCurrentUserId()]);
$allUsers = $stmt->fetchAll();

require_once __DIR__ . '/navbar.php';
?>

<main class="main-content">
    <div class="container-sm">
        <div class="page-header text-center">
            <h1 class="page-title">📤 上架新商品</h1>
            <p class="page-subtitle">選擇拍賣方式，開始您的銷售之旅</p>
        </div>

        <div class="sell-form-container">
            <!-- 拍賣類型選擇 -->
            <div class="auction-type-selector">
                <div class="type-option active" data-type="auction" onclick="selectAuctionType('auction')">
                    <div class="type-icon">🔥</div>
                    <div class="type-info">
                        <h3>競標拍賣</h3>
                        <p>價高者得，支援自動延長與代標</p>
                    </div>
                </div>
                <div class="type-option" data-type="fixed" onclick="selectAuctionType('fixed')">
                    <div class="type-icon">💰</div>
                    <div class="type-info">
                        <h3>直接購買</h3>
                        <p>固定價格，買家可直接購買</p>
                    </div>
                </div>
                <div class="type-option" data-type="private" onclick="selectAuctionType('private')">
                    <div class="type-icon">🔒</div>
                    <div class="type-info">
                        <h3>專屬販售</h3>
                        <p>僅指定買家可見並購買</p>
                    </div>
                </div>
            </div>

            <!-- 上架表單 -->
            <form id="sell-form" class="sell-form" onsubmit="submitProduct(event)">
                <input type="hidden" id="auction-type" name="auction_type" value="auction">

                <!-- 基本資訊 -->
                <div class="form-section">
                    <h3 class="form-section-title">📦 商品資訊</h3>
                    
                    <div class="form-group">
                        <label>商品標題 <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               placeholder="請輸入商品標題（至少5個字元）" minlength="5" maxlength="200">
                    </div>

                    <div class="form-group">
                        <label>商品描述</label>
                        <textarea id="description" name="description" rows="5"
                                  placeholder="詳細描述您的商品，包括規格、狀態、特色等..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>商品分類</label>
                            <select id="category" name="category">
                                <option value="general">一般商品</option>
                                <option value="electronics">電子產品</option>
                                <option value="art">藝術收藏</option>
                                <option value="antique">古董珍品</option>
                                <option value="fashion">時尚精品</option>
                                <option value="exclusive">限量專屬</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>商品圖片網址</label>
                            <input type="url" id="image_url" name="image_url" 
                                   placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                </div>

                <!-- 價格設定 -->
                <div class="form-section">
                    <h3 class="form-section-title">💵 價格設定</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label id="price-label">起標價格 <span class="required">*</span></label>
                            <div class="input-with-prefix">
                                <span class="input-prefix">$</span>
                                <input type="number" id="starting_price" name="starting_price" 
                                       required min="1" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="form-group" id="increment-group">
                            <label>最低加價金額</label>
                            <div class="input-with-prefix">
                                <span class="input-prefix">$</span>
                                <input type="number" id="min_increment" name="min_increment" 
                                       min="1" step="0.01" value="10" placeholder="10.00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 時間設定 -->
                <div class="form-section" id="duration-section">
                    <h3 class="form-section-title">⏱️ 拍賣時長</h3>
                    
                    <div class="duration-options">
                        <label class="duration-option">
                            <input type="radio" name="duration" value="1">
                            <div class="duration-card">
                                <span class="duration-value">1</span>
                                <span class="duration-unit">小時</span>
                            </div>
                        </label>
                        <label class="duration-option">
                            <input type="radio" name="duration" value="6">
                            <div class="duration-card">
                                <span class="duration-value">6</span>
                                <span class="duration-unit">小時</span>
                            </div>
                        </label>
                        <label class="duration-option">
                            <input type="radio" name="duration" value="24" checked>
                            <div class="duration-card">
                                <span class="duration-value">24</span>
                                <span class="duration-unit">小時</span>
                            </div>
                        </label>
                        <label class="duration-option">
                            <input type="radio" name="duration" value="72">
                            <div class="duration-card">
                                <span class="duration-value">3</span>
                                <span class="duration-unit">天</span>
                            </div>
                        </label>
                        <label class="duration-option">
                            <input type="radio" name="duration" value="168">
                            <div class="duration-card">
                                <span class="duration-value">7</span>
                                <span class="duration-unit">天</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 專屬買家選擇 -->
                <div class="form-section" id="private-section" style="display: none;">
                    <h3 class="form-section-title">🔒 指定買家</h3>
                    
                    <div class="form-group">
                        <label>選擇買家 <span class="required">*</span></label>
                        <select id="allowed_buyer_id" name="allowed_buyer_id">
                            <option value="">-- 請選擇買家 --</option>
                            <?php foreach ($allUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo h($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">只有指定的買家可以看到並購買此商品</p>
                    </div>
                </div>

                <!-- 提交按鈕 -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="history.back()">取消</button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span>🚀</span> 立即上架
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<style>
/* 上架頁面樣式 */
.sell-form-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    padding: 32px;
    margin-top: 32px;
}

/* 拍賣類型選擇器 */
.auction-type-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.type-option {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--bg-tertiary);
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition-fast);
}

.type-option:hover {
    border-color: var(--accent-gold);
}

.type-option.active {
    border-color: var(--accent-gold);
    background: rgba(245, 166, 35, 0.1);
}

.type-icon {
    font-size: 32px;
}

.type-info h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.type-info p {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

/* 表單區塊 */
.form-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
}

.required {
    color: var(--accent-red);
}

/* 輸入框帶前綴 */
.input-with-prefix {
    display: flex;
    align-items: center;
}

.input-prefix {
    padding: 14px 16px;
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-right: none;
    border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm);
    color: var(--text-muted);
    font-weight: 600;
}

.input-with-prefix input {
    border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
}

/* 時長選擇器 */
.duration-options {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}

.duration-option {
    cursor: pointer;
}

.duration-option input {
    display: none;
}

.duration-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px 16px;
    background: var(--bg-tertiary);
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    transition: var(--transition-fast);
}

.duration-option:hover .duration-card {
    border-color: var(--accent-gold);
}

.duration-option input:checked + .duration-card {
    border-color: var(--accent-gold);
    background: rgba(245, 166, 35, 0.1);
}

.duration-value {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

.duration-unit {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* 表單操作 */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

/* 響應式 */
@media (max-width: 768px) {
    .auction-type-selector {
        grid-template-columns: 1fr;
    }
    
    .duration-options {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .type-option {
        padding: 16px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<script>
// ============================================
// 上架頁面腳本
// ============================================

let currentType = 'auction';

// 選擇拍賣類型
function selectAuctionType(type) {
    currentType = type;
    document.getElementById('auction-type').value = type;
    
    // 更新選中狀態
    document.querySelectorAll('.type-option').forEach(el => {
        el.classList.remove('active');
    });
    document.querySelector(`.type-option[data-type="${type}"]`).classList.add('active');
    
    // 根據類型調整表單
    const incrementGroup = document.getElementById('increment-group');
    const durationSection = document.getElementById('duration-section');
    const privateSection = document.getElementById('private-section');
    const priceLabel = document.getElementById('price-label');
    
    switch (type) {
        case 'auction':
            incrementGroup.style.display = 'block';
            durationSection.style.display = 'block';
            privateSection.style.display = 'none';
            priceLabel.textContent = '起標價格 ';
            document.getElementById('allowed_buyer_id').removeAttribute('required');
            break;
            
        case 'fixed':
            incrementGroup.style.display = 'none';
            durationSection.style.display = 'block';
            privateSection.style.display = 'none';
            priceLabel.textContent = '售價 ';
            document.getElementById('allowed_buyer_id').removeAttribute('required');
            break;
            
        case 'private':
            incrementGroup.style.display = 'none';
            durationSection.style.display = 'block';
            privateSection.style.display = 'block';
            priceLabel.textContent = '售價 ';
            document.getElementById('allowed_buyer_id').setAttribute('required', 'required');
            break;
    }
    
    // 添加必須標記
    priceLabel.innerHTML += '<span class="required">*</span>';
}

// 提交商品
function submitProduct(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'create_product');
    
    // 取得時長
    const durationInput = document.querySelector('input[name="duration"]:checked');
    formData.append('duration', durationInput ? durationInput.value : 24);
    
    // 驗證專屬買家
    if (currentType === 'private') {
        const buyerId = document.getElementById('allowed_buyer_id').value;
        if (!buyerId) {
            Swal.fire({
                icon: 'error',
                title: '請選擇買家',
                text: '專屬販售必須指定買家'
            });
            return;
        }
    }
    
    // 顯示確認
    Swal.fire({
        title: '確認上架',
        text: '確定要上架此商品嗎？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '確定上架',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            // 顯示載入中
            Swal.fire({
                title: '處理中...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '上架成功！',
                        text: '您的商品已成功上架',
                        confirmButtonText: '前往查看'
                    }).then(() => {
                        location.href = 'product.php?id=' + data.product_id;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '上架失敗',
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
    });
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    selectAuctionType('auction');
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
