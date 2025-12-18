<?php
/**
 * ============================================
 * Velocity Auction Pro - 全域函數庫
 * functions.php
 * ============================================
 * 包含 Session管理、權限檢查、金流處理、成就計算等核心函數
 */

require_once __DIR__ . '/db.php';

// ============================================
// Session 管理
// ============================================

/**
 * 初始化 Session
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * 檢查用戶是否已登入
 * @return bool
 */
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 取得當前登入用戶ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * 取得當前用戶資訊
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch() ?: null;
}

/**
 * 設定用戶登入
 * @param int $userId
 * @param string $username
 */
function loginUser(int $userId, string $username): void {
    initSession();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    
    // 更新最後登入時間
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

/**
 * 登出用戶
 */
function logoutUser(): void {
    initSession();
    session_destroy();
}

// ============================================
// 權限檢查函數
// ============================================

/**
 * 檢查用戶是否為商品擁有者
 * @param int $productId
 * @param int|null $userId
 * @return bool
 */
function isProductOwner(int $productId, ?int $userId = null): bool {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    if ($userId === null) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT seller_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    return $product && (int)$product['seller_id'] === $userId;
}

/**
 * 檢查用戶是否可以查看私人商品
 * @param array $product
 * @param int|null $userId
 * @return bool
 */
function canViewPrivateProduct(array $product, ?int $userId = null): bool {
    if ($product['auction_type'] !== 'private') {
        return true;
    }
    
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    // 賣家可以看到自己的私人商品
    if ((int)$product['seller_id'] === $userId) {
        return true;
    }
    
    // 指定買家可以看到
    if ($product['allowed_buyer_id'] !== null && (int)$product['allowed_buyer_id'] === $userId) {
        return true;
    }
    
    return false;
}

/**
 * 要求登入，否則重導向
 * @param string $redirect 重導向URL
 */
function requireLogin(string $redirect = 'index.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect?error=login_required");
        exit;
    }
}

// ============================================
// 金流處理函數 (使用事務處理確保資料一致性)
// ============================================

/**
 * 凍結用戶餘額（出價時使用）
 * @param int $userId
 * @param float $amount
 * @return bool
 */
function freezeBalance(int $userId, float $amount): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET balance = balance - ?, 
            frozen_balance = frozen_balance + ? 
        WHERE id = ? AND balance >= ?
    ");
    
    return $stmt->execute([$amount, $amount, $userId, $amount]) && $stmt->rowCount() > 0;
}

/**
 * 解凍用戶餘額（被超車時退還）
 * @param int $userId
 * @param float $amount
 * @return bool
 */
function unfreezeBalance(int $userId, float $amount): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET balance = balance + ?, 
            frozen_balance = frozen_balance - ? 
        WHERE id = ? AND frozen_balance >= ?
    ");
    
    return $stmt->execute([$amount, $amount, $userId, $amount]) && $stmt->rowCount() > 0;
}

/**
 * 轉移資金（拍賣結束時，凍結金額轉給賣家）
 * @param int $buyerId
 * @param int $sellerId
 * @param float $amount
 * @param float $platformFee 平台手續費（預設5%）
 * @return array 包含 seller_received 的結果
 */
function transferBalance(int $buyerId, int $sellerId, float $amount, float $platformFee = 0.05): array {
    global $pdo;
    
    $fee = $amount * $platformFee;
    $sellerReceived = $amount - $fee;
    
    try {
        $pdo->beginTransaction();
        
        // 扣除買家凍結金額
        $stmt = $pdo->prepare("
            UPDATE users SET frozen_balance = frozen_balance - ? 
            WHERE id = ? AND frozen_balance >= ?
        ");
        $stmt->execute([$amount, $buyerId, $amount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("買家凍結金額不足");
        }
        
        // 賣家收到金額（扣除手續費）
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$sellerReceived, $sellerId]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'final_price' => $amount,
            'platform_fee' => $fee,
            'seller_received' => $sellerReceived
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 直接扣除餘額（直購用）
 * @param int $userId
 * @param float $amount
 * @return bool
 */
function deductBalance(int $userId, float $amount): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users SET balance = balance - ? 
        WHERE id = ? AND balance >= ?
    ");
    
    return $stmt->execute([$amount, $userId, $amount]) && $stmt->rowCount() > 0;
}

/**
 * 增加用戶餘額
 * @param int $userId
 * @param float $amount
 * @return bool
 */
function addBalance(int $userId, float $amount): bool {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    return $stmt->execute([$amount, $userId]);
}

/**
 * 挖礦獲得資金
 * @param int $userId
 * @return array
 */
function mineCoins(int $userId): array {
    global $pdo;
    
    // 隨機獲得 10-500 的虛擬資金
    $amount = rand(10, 500);
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET balance = balance + ?, 
            mined_amount = mined_amount + ? 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$amount, $amount, $userId])) {
        return [
            'success' => true,
            'amount' => $amount,
            'message' => "挖礦成功！獲得 $$amount"
        ];
    }
    
    return ['success' => false, 'message' => '挖礦失敗'];
}

// ============================================
// 成就稱號系統
// ============================================

/**
 * 根據累積出價金額計算成就稱號
 * @param float $totalBidAmount
 * @return array 包含 title 和 level
 */
function calculateAchievement(float $totalBidAmount): array {
    $achievements = [
        ['min' => 0, 'title' => '新手買家', 'level' => 1, 'icon' => '🌱', 'color' => '#95a5a6'],
        ['min' => 10000, 'title' => '活躍競標者', 'level' => 2, 'icon' => '⭐', 'color' => '#3498db'],
        ['min' => 50000, 'title' => '收藏家', 'level' => 3, 'icon' => '💎', 'color' => '#9b59b6'],
        ['min' => 200000, 'title' => '資深藏家', 'level' => 4, 'icon' => '👑', 'color' => '#f39c12'],
        ['min' => 500000, 'title' => '鑽石會員', 'level' => 5, 'icon' => '💠', 'color' => '#1abc9c'],
        ['min' => 1000000, 'title' => '鯨魚大戶', 'level' => 6, 'icon' => '🐋', 'color' => '#e74c3c'],
        ['min' => 5000000, 'title' => '傳奇收藏家', 'level' => 7, 'icon' => '🏆', 'color' => '#ffd700'],
    ];
    
    $result = $achievements[0];
    
    foreach ($achievements as $achievement) {
        if ($totalBidAmount >= $achievement['min']) {
            $result = $achievement;
        }
    }
    
    return $result;
}

/**
 * 更新用戶累積出價金額
 * @param int $userId
 * @param float $amount
 */
function updateTotalBidAmount(int $userId, float $amount): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users SET total_bid_amount = total_bid_amount + ? WHERE id = ?
    ");
    $stmt->execute([$amount, $userId]);
}

// ============================================
// 跑馬燈與動態
// ============================================

/**
 * 取得最新動態（用於跑馬燈）
 * @param int $limit
 * @return array
 */
function getLatestActivities(int $limit = 10): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, p.title as product_title
        FROM activities a
        JOIN users u ON a.user_id = u.id
        JOIN products p ON a.product_id = p.id
        ORDER BY a.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

/**
 * 記錄動態
 * @param string $type
 * @param int $userId
 * @param int $productId
 * @param string $message
 * @param float|null $amount
 */
function logActivity(string $type, int $userId, int $productId, string $message, ?float $amount = null): void {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO activities (type, user_id, product_id, message, amount) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$type, $userId, $productId, $message, $amount]);
}

// ============================================
// 安全性函數
// ============================================

/**
 * 清理並轉義輸出（防止XSS）
 * @param string|null $str
 * @return string
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化金額顯示
 * @param float $amount
 * @return string
 */
function formatMoney(float $amount): string {
    return '$' . number_format($amount, 2);
}

/**
 * 格式化時間為相對時間
 * @param string $datetime
 * @return string
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return '剛剛';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' 分鐘前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' 小時前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' 天前';
    } else {
        return date('Y-m-d H:i', $time);
    }
}

/**
 * 計算倒數時間
 * @param string $endTime
 * @return array
 */
function getCountdown(string $endTime): array {
    $end = strtotime($endTime);
    $now = time();
    $diff = $end - $now;
    
    if ($diff <= 0) {
        return [
            'ended' => true,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
            'total_seconds' => 0
        ];
    }
    
    return [
        'ended' => false,
        'hours' => floor($diff / 3600),
        'minutes' => floor(($diff % 3600) / 60),
        'seconds' => $diff % 60,
        'total_seconds' => $diff
    ];
}

/**
 * 產生 CSRF Token
 * @return string
 */
function generateCSRFToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證 CSRF Token
 * @param string $token
 * @return bool
 */
function validateCSRFToken(string $token): bool {
    initSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// 商品相關函數
// ============================================

/**
 * 取得商品詳細資訊
 * @param int $productId
 * @return array|null
 */
function getProduct(int $productId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as seller_name, u.avatar as seller_avatar
        FROM products p
        JOIN users u ON p.seller_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    
    return $stmt->fetch() ?: null;
}

/**
 * 取得商品的出價歷史（用於圖表）
 * @param int $productId
 * @return array
 */
function getBidHistory(int $productId): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT b.*, u.username as bidder_name
        FROM bids b
        JOIN users u ON b.bidder_id = u.id
        WHERE b.product_id = ?
        ORDER BY b.created_at ASC
    ");
    $stmt->execute([$productId]);
    
    return $stmt->fetchAll();
}

/**
 * 取得商品的最高出價
 * @param int $productId
 * @return array|null
 */
function getHighestBid(int $productId): ?array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT b.*, u.username as bidder_name
        FROM bids b
        JOIN users u ON b.bidder_id = u.id
        WHERE b.product_id = ? AND b.status = 'active'
        ORDER BY b.amount DESC
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    
    return $stmt->fetch() ?: null;
}

/**
 * 取得賣家評價統計
 * @param int $sellerId
 * @return array
 */
function getSellerRating(int $sellerId): array {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM reviews WHERE seller_id = ?
    ");
    $stmt->execute([$sellerId]);
    
    $result = $stmt->fetch();
    
    return [
        'total_reviews' => (int)$result['total_reviews'],
        'average_rating' => round((float)$result['average_rating'], 1) ?: 0,
        'five_star' => (int)$result['five_star'],
        'four_star' => (int)$result['four_star'],
        'three_star' => (int)$result['three_star'],
        'two_star' => (int)$result['two_star'],
        'one_star' => (int)$result['one_star']
    ];
}

// ============================================
// 競標結算處理
// ============================================

/**
 * 處理已結束的拍賣（建立訂單、通知得標者）
 * 應在頁面載入時呼叫
 */
function processEndedAuctions(): void {
    global $pdo;
    
    try {
        // 找出所有已過期但尚未處理的拍賣商品
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as seller_name
            FROM products p
            JOIN users u ON p.seller_id = u.id
            WHERE p.auction_type = 'auction' 
              AND p.status = 'active' 
              AND p.end_time < NOW()
              AND p.bid_count > 0
        ");
        $stmt->execute();
        $endedAuctions = $stmt->fetchAll();
        
        foreach ($endedAuctions as $auction) {
            processAuctionWinner($auction);
        }
        
        // 將無出價的過期拍賣標記為已結束
        $stmt = $pdo->prepare("
            UPDATE products 
            SET status = 'ended' 
            WHERE auction_type = 'auction' 
              AND status = 'active' 
              AND end_time < NOW() 
              AND bid_count = 0
        ");
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log("processEndedAuctions error: " . $e->getMessage());
    }
}

/**
 * 處理單一拍賣的得標者
 * @param array $auction
 */
function processAuctionWinner(array $auction): void {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 鎖定商品
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$auction['id']]);
        $product = $stmt->fetch();
        
        // 再次檢查狀態（避免重複處理）
        if (!$product || $product['status'] !== 'active') {
            $pdo->rollBack();
            return;
        }
        
        // 取得最高出價者
        $highestBid = getHighestBid($auction['id']);
        if (!$highestBid) {
            // 無出價，標記為結束
            $stmt = $pdo->prepare("UPDATE products SET status = 'ended' WHERE id = ?");
            $stmt->execute([$auction['id']]);
            $pdo->commit();
            return;
        }
        
        $winnerId = (int)$highestBid['bidder_id'];
        $finalPrice = (float)$highestBid['amount'];
        
        // 計算費用
        $platformFee = $finalPrice * 0.05;
        $sellerReceived = $finalPrice - $platformFee;
        
        // 更新商品狀態為已售出
        $stmt = $pdo->prepare("
            UPDATE products SET status = 'sold', winner_id = ? WHERE id = ?
        ");
        $stmt->execute([$winnerId, $auction['id']]);
        
        // 更新出價狀態為得標
        $stmt = $pdo->prepare("
            UPDATE bids SET status = 'won' 
            WHERE product_id = ? AND bidder_id = ? AND status = 'active'
        ");
        $stmt->execute([$auction['id'], $winnerId]);
        
        // 轉移資金（從凍結金額扣除到賣家）
        $stmt = $pdo->prepare("
            UPDATE users SET frozen_balance = frozen_balance - ? WHERE id = ?
        ");
        $stmt->execute([$finalPrice, $winnerId]);
        
        $stmt = $pdo->prepare("
            UPDATE users SET balance = balance + ? WHERE id = ?
        ");
        $stmt->execute([$sellerReceived, $auction['seller_id']]);
        
        // 建立訂單
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (product_id, buyer_id, seller_id, final_price, platform_fee, seller_received, status)
            VALUES (?, ?, ?, ?, ?, ?, 'paid')
        ");
        $stmt->execute([
            $auction['id'], $winnerId, $auction['seller_id'],
            $finalPrice, $platformFee, $sellerReceived
        ]);
        $orderId = $pdo->lastInsertId();
        
        // 發送得標通知給買家
        $message = "🎉 恭喜您得標！您成功標得商品【{$auction['title']}】，成交價格為 $" . number_format($finalPrice, 2) . "。請前往訂單頁面填寫收貨資訊。";
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, content, product_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$auction['seller_id'], $winnerId, $message, $auction['id']]);
        
        // 發送通知給賣家
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$winnerId]);
        $winnerName = $stmt->fetchColumn();
        
        $sellerMessage = "🔔 您的商品【{$auction['title']}】已由 {$winnerName} 得標，成交價格為 $" . number_format($finalPrice, 2) . "。等待買家填寫收貨資訊。";
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, content, product_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$winnerId, $auction['seller_id'], $sellerMessage, $auction['id']]);
        
        // 記錄活動
        logActivity('sale', $winnerId, $auction['id'], 
            "{$winnerName} 得標了 {$auction['title']}", $finalPrice);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("processAuctionWinner error: " . $e->getMessage());
    }
}

