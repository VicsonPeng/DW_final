<?php
/**
 * ============================================
 * Velocity Auction Pro - API 核心處理器
 * api.php
 * ============================================
 * 處理所有 AJAX 請求：出價邏輯、自動代標、聊天、評論等
 * 使用事務處理確保資料一致性，防止 Race Condition
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/functions.php';

// 初始化 Session
initSession();

// 取得請求動作
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 回應函數
function jsonResponse(bool $success, string $message = '', array $data = []): void {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// ============================================
// 路由處理
// ============================================

switch ($action) {
    // ========== 用戶認證 ==========
    case 'register':
        handleRegister();
        break;
    
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    // ========== 競標相關 ==========
    case 'place_bid':
        handlePlaceBid();
        break;
    
    case 'set_auto_bid':
        handleSetAutoBid();
        break;
    
    case 'get_product_status':
        handleGetProductStatus();
        break;
    
    case 'get_bid_history':
        handleGetBidHistory();
        break;
    
    // ========== 商品相關 ==========
    case 'create_product':
        handleCreateProduct();
        break;
    
    case 'get_products':
        handleGetProducts();
        break;
    
    case 'buy_now':
        handleBuyNow();
        break;
    
    // ========== 評論與私訊 ==========
    case 'add_comment':
        handleAddComment();
        break;
    
    case 'get_comments':
        handleGetComments();
        break;
    
    case 'send_message':
        handleSendMessage();
        break;
    
    case 'get_messages':
        handleGetMessages();
        break;
    
    case 'get_conversations':
        handleGetConversations();
        break;
    
    // ========== 訂單與評價 ==========
    case 'checkout':
        handleCheckout();
        break;
    
    case 'add_review':
        handleAddReview();
        break;
    
    // ========== 挖礦 ==========
    case 'mine':
        handleMine();
        break;
    
    // ========== 跑馬燈 ==========
    case 'get_activities':
        handleGetActivities();
        break;
    
    // ========== 購物車 ==========
    case 'add_to_cart':
        handleAddToCart();
        break;
    
    case 'update_cart':
        handleUpdateCart();
        break;

    case 'remove_from_cart':
        handleRemoveFromCart();
        break;
    
    case 'get_cart':
        handleGetCart();
        break;
    
    case 'get_cart_count':
        handleGetCartCount();
        break;

    case 'ship_order':
        handleShipOrder();
        break;
    
    case 'confirm_received':
        handleConfirmReceived();
        break;
    
    case 'checkout_cart':
        handleCheckoutCart();
        break;
    
    // ========== 儲值 ==========
    case 'test_deposit':
        handleTestDeposit();
        break;
    
    // ========== 關注 ==========
    case 'follow_seller':
        handleFollowSeller();
        break;
    
    case 'unfollow_seller':
        handleUnfollowSeller();
        break;
    
    case 'get_follow_status':
        handleGetFollowStatus();
        break;
    
    case 'get_following':
        handleGetFollowing();
        break;
    
    case 'get_followers':
        handleGetFollowers();
        break;
    
    // ========== 未讀訊息 ==========
    case 'get_unread_count':
        handleGetUnreadCount();
        break;
    
    // ========== 商品編輯 ==========
    case 'update_product':
        handleUpdateProduct();
        break;
    
    case 'delete_product':
        handleDeleteProduct();
        break;
    
    default:
        jsonResponse(false, '未知的操作');
}

// ============================================
// 用戶認證處理
// ============================================

function handleRegister(): void {
    global $pdo;
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 驗證
    if (strlen($username) < 3 || strlen($username) > 50) {
        jsonResponse(false, '用戶名需為 3-50 個字元');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, '請輸入有效的電子郵件地址');
    }
    
    if (strlen($password) < 6) {
        jsonResponse(false, '密碼至少需要 6 個字元');
    }
    
    // 檢查重複
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        jsonResponse(false, '用戶名或電子郵件已被使用');
    }
    
    // 建立用戶
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)
    ");
    
    if ($stmt->execute([$username, $email, $passwordHash])) {
        $userId = $pdo->lastInsertId();
        loginUser($userId, $username);
        jsonResponse(true, '註冊成功！', ['user_id' => $userId]);
    } else {
        jsonResponse(false, '註冊失敗，請稍後再試');
    }
}

function handleLogin(): void {
    global $pdo;
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        loginUser($user['id'], $user['username']);
        jsonResponse(true, '登入成功！', [
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);
    } else {
        jsonResponse(false, '用戶名或密碼錯誤');
    }
}

function handleLogout(): void {
    logoutUser();
    jsonResponse(true, '已登出');
}

// ============================================
// 競標邏輯處理 (核心引擎)
// ============================================

/**
 * 處理出價請求
 * 使用 BEGIN TRANSACTION + SELECT FOR UPDATE 防止 Race Condition
 */
function handlePlaceBid(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $bidAmount = (float)($_POST['amount'] ?? 0);
    $userId = getCurrentUserId();
    
    if ($productId <= 0 || $bidAmount <= 0) {
        jsonResponse(false, '無效的出價參數');
    }
    
    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 鎖定商品列，防止 Race Condition
        $stmt = $pdo->prepare("
            SELECT p.*, u.balance 
            FROM products p
            JOIN users u ON u.id = ?
            WHERE p.id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$userId, $productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('商品不存在');
        }
        
        // 檢查拍賣類型
        if ($product['auction_type'] !== 'auction') {
            throw new Exception('此商品不支援競標');
        }
        
        // 檢查是否已結束
        if (strtotime($product['end_time']) <= time()) {
            throw new Exception('拍賣已結束');
        }
        
        // 檢查狀態
        if ($product['status'] !== 'active') {
            throw new Exception('此拍賣已不再進行中');
        }
        
        // 不能對自己的商品出價
        if ((int)$product['seller_id'] === $userId) {
            throw new Exception('不能對自己的商品出價');
        }
        
        // 計算最低出價金額
        $minBid = $product['current_price'] + $product['min_increment'];
        if ($bidAmount < $minBid) {
            throw new Exception("出價金額至少需為 $" . number_format($minBid, 2));
        }
        
        // 檢查餘額
        if ($product['balance'] < $bidAmount) {
            throw new Exception('餘額不足');
        }
        
        // 取得上一位最高出價者資訊
        $stmt = $pdo->prepare("
            SELECT bidder_id, amount FROM bids 
            WHERE product_id = ? AND status = 'active' 
            ORDER BY amount DESC LIMIT 1
        ");
        $stmt->execute([$productId]);
        $previousBid = $stmt->fetch();
        
        // 如果有上一位出價者，退還其凍結金額
        if ($previousBid && (int)$previousBid['bidder_id'] !== $userId) {
            // 將上一位出價者的出價標記為被超車
            $stmt = $pdo->prepare("
                UPDATE bids SET status = 'outbid' 
                WHERE product_id = ? AND bidder_id = ? AND status = 'active'
            ");
            $stmt->execute([$productId, $previousBid['bidder_id']]);
            
            // 退還上一位出價者的凍結金額
            $stmt = $pdo->prepare("
                UPDATE users 
                SET balance = balance + ?, frozen_balance = frozen_balance - ?
                WHERE id = ?
            ");
            $stmt->execute([$previousBid['amount'], $previousBid['amount'], $previousBid['bidder_id']]);
        }
        
        // 如果用戶之前有出價，先退還
        $stmt = $pdo->prepare("
            SELECT amount FROM bids 
            WHERE product_id = ? AND bidder_id = ? AND status = 'active'
        ");
        $stmt->execute([$productId, $userId]);
        $myPreviousBid = $stmt->fetch();
        
        if ($myPreviousBid) {
            // 退還之前的凍結金額
            $stmt = $pdo->prepare("
                UPDATE users SET balance = balance + ?, frozen_balance = frozen_balance - ?
                WHERE id = ?
            ");
            $stmt->execute([$myPreviousBid['amount'], $myPreviousBid['amount'], $userId]);
            
            // 更新舊出價狀態
            $stmt = $pdo->prepare("
                UPDATE bids SET status = 'outbid' 
                WHERE product_id = ? AND bidder_id = ? AND status = 'active'
            ");
            $stmt->execute([$productId, $userId]);
        }
        
        // 凍結新的出價金額
        $stmt = $pdo->prepare("
            UPDATE users SET balance = balance - ?, frozen_balance = frozen_balance + ?
            WHERE id = ? AND balance >= ?
        ");
        $stmt->execute([$bidAmount, $bidAmount, $userId, $bidAmount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('凍結資金失敗，請確認餘額');
        }
        
        // 新增出價記錄
        $stmt = $pdo->prepare("
            INSERT INTO bids (product_id, bidder_id, amount, is_auto_bid, status)
            VALUES (?, ?, ?, 0, 'active')
        ");
        $stmt->execute([$productId, $userId, $bidAmount]);
        
        // 更新商品當前價格與出價次數
        $stmt = $pdo->prepare("
            UPDATE products SET current_price = ?, bid_count = bid_count + 1
            WHERE id = ?
        ");
        $stmt->execute([$bidAmount, $productId]);
        
        // 防狙擊延長 (Soft Close): 若在結束前 60 秒內出價，延長 1 分鐘
        $endTime = strtotime($product['end_time']);
        $now = time();
        if (($endTime - $now) <= 60 && ($endTime - $now) > 0) {
            $newEndTime = date('Y-m-d H:i:s', $endTime + 60);
            $stmt = $pdo->prepare("UPDATE products SET end_time = ? WHERE id = ?");
            $stmt->execute([$newEndTime, $productId]);
        }
        
        // 更新用戶累積出價金額
        $stmt = $pdo->prepare("
            UPDATE users SET total_bid_amount = total_bid_amount + ? WHERE id = ?
        ");
        $stmt->execute([$bidAmount, $userId]);
        
        // 提交事務
        $pdo->commit();
        
        // 記錄活動（在事務外進行）
        $user = getCurrentUser();
        logActivity('bid', $userId, $productId, 
            $user['username'] . " 出價 $" . number_format($bidAmount, 2), $bidAmount);
        
        // 觸發自動代標檢查（在回應後處理）
        triggerAutoBid($productId, $userId, $bidAmount);
        
        jsonResponse(true, '出價成功！', [
            'new_price' => $bidAmount,
            'bid_count' => $product['bid_count'] + 1
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, $e->getMessage());
    }
}

/**
 * 觸發自動代標機器人
 * 檢查是否有其他用戶設定了更高的自動出價
 */
function triggerAutoBid(int $productId, int $currentBidderId, float $currentBidAmount): void {
    global $pdo;
    
    // 查找是否有其他用戶設定了更高的自動出價
    $stmt = $pdo->prepare("
        SELECT ab.*, u.balance, p.min_increment, p.end_time, p.status
        FROM auto_bids ab
        JOIN users u ON ab.bidder_id = u.id
        JOIN products p ON ab.product_id = p.id
        WHERE ab.product_id = ? 
          AND ab.bidder_id != ? 
          AND ab.is_active = 1 
          AND ab.max_amount > ?
          AND p.status = 'active'
          AND p.end_time > NOW()
        ORDER BY ab.max_amount DESC
        LIMIT 1
    ");
    $stmt->execute([$productId, $currentBidderId, $currentBidAmount]);
    $autoBid = $stmt->fetch();
    
    if (!$autoBid) {
        return; // 沒有符合條件的自動出價
    }
    
    // 計算自動出價金額
    $minIncrement = (float)$autoBid['min_increment'];
    $autoBidAmount = $currentBidAmount + $minIncrement;
    
    // 確保不超過最大限額
    if ($autoBidAmount > $autoBid['max_amount']) {
        $autoBidAmount = $autoBid['max_amount'];
    }
    
    // 如果自動出價金額仍然不夠高，則跳過
    if ($autoBidAmount <= $currentBidAmount) {
        return;
    }
    
    // 檢查用戶餘額
    if ($autoBid['balance'] < $autoBidAmount) {
        return;
    }
    
    // 執行自動出價
    executeAutoBid($productId, $autoBid['bidder_id'], $autoBidAmount, $currentBidderId, $currentBidAmount);
}

/**
 * 執行自動出價
 */
function executeAutoBid(int $productId, int $bidderId, float $bidAmount, int $previousBidderId, float $previousAmount): void {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 鎖定商品
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product || $product['status'] !== 'active' || strtotime($product['end_time']) <= time()) {
            $pdo->rollBack();
            return;
        }
        
        // 退還上一位出價者金額
        $stmt = $pdo->prepare("
            UPDATE bids SET status = 'outbid' 
            WHERE product_id = ? AND bidder_id = ? AND status = 'active'
        ");
        $stmt->execute([$productId, $previousBidderId]);
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET balance = balance + ?, frozen_balance = frozen_balance - ?
            WHERE id = ?
        ");
        $stmt->execute([$previousAmount, $previousAmount, $previousBidderId]);
        
        // 退還自動出價者之前的出價（如果有）
        $stmt = $pdo->prepare("
            SELECT amount FROM bids 
            WHERE product_id = ? AND bidder_id = ? AND status = 'active'
        ");
        $stmt->execute([$productId, $bidderId]);
        $myPreviousBid = $stmt->fetch();
        
        if ($myPreviousBid) {
            $stmt = $pdo->prepare("
                UPDATE users SET balance = balance + ?, frozen_balance = frozen_balance - ?
                WHERE id = ?
            ");
            $stmt->execute([$myPreviousBid['amount'], $myPreviousBid['amount'], $bidderId]);
            
            $stmt = $pdo->prepare("
                UPDATE bids SET status = 'outbid' 
                WHERE product_id = ? AND bidder_id = ? AND status = 'active'
            ");
            $stmt->execute([$productId, $bidderId]);
        }
        
        // 凍結新金額
        $stmt = $pdo->prepare("
            UPDATE users SET balance = balance - ?, frozen_balance = frozen_balance + ?
            WHERE id = ? AND balance >= ?
        ");
        $stmt->execute([$bidAmount, $bidAmount, $bidderId, $bidAmount]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return;
        }
        
        // 新增出價記錄（標記為自動出價）
        $stmt = $pdo->prepare("
            INSERT INTO bids (product_id, bidder_id, amount, is_auto_bid, status)
            VALUES (?, ?, ?, 1, 'active')
        ");
        $stmt->execute([$productId, $bidderId, $bidAmount]);
        
        // 更新商品價格
        $stmt = $pdo->prepare("
            UPDATE products SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?
        ");
        $stmt->execute([$bidAmount, $productId]);
        
        // 防狙擊延長
        $endTime = strtotime($product['end_time']);
        if ((time() - $endTime) >= -60 && (time() - $endTime) < 0) {
            $newEndTime = date('Y-m-d H:i:s', $endTime + 60);
            $stmt = $pdo->prepare("UPDATE products SET end_time = ? WHERE id = ?");
            $stmt->execute([$newEndTime, $productId]);
        }
        
        $pdo->commit();
        
        // 記錄活動
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$bidderId]);
        $user = $stmt->fetch();
        
        logActivity('bid', $bidderId, $productId, 
            $user['username'] . " 自動出價 $" . number_format($bidAmount, 2), $bidAmount);
        
        // 遞迴檢查是否還有更高的自動出價
        triggerAutoBid($productId, $bidderId, $bidAmount);
        
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

/**
 * 設定自動代標上限
 */
function handleSetAutoBid(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $maxAmount = (float)($_POST['max_amount'] ?? 0);
    $userId = getCurrentUserId();
    
    if ($productId <= 0) {
        jsonResponse(false, '無效的商品ID');
    }
    
    // 取得商品資訊
    $product = getProduct($productId);
    if (!$product) {
        jsonResponse(false, '商品不存在');
    }
    
    if ($product['auction_type'] !== 'auction') {
        jsonResponse(false, '此商品不支援自動出價');
    }
    
    if ($maxAmount < $product['current_price'] + $product['min_increment']) {
        jsonResponse(false, '自動出價上限必須高於當前價格加最低加價');
    }
    
    // 檢查餘額
    $user = getCurrentUser();
    if ($user['balance'] < $maxAmount) {
        jsonResponse(false, '餘額不足以設定此自動出價上限');
    }
    
    // 使用 UPSERT 更新或新增
    $stmt = $pdo->prepare("
        INSERT INTO auto_bids (product_id, bidder_id, max_amount, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE max_amount = ?, is_active = 1
    ");
    
    if ($stmt->execute([$productId, $userId, $maxAmount, $maxAmount])) {
        jsonResponse(true, '自動出價設定成功！上限為 $' . number_format($maxAmount, 2));
    } else {
        jsonResponse(false, '設定失敗');
    }
}

/**
 * 取得商品即時狀態
 */
function handleGetProductStatus(): void {
    $productId = (int)($_GET['product_id'] ?? 0);
    
    if ($productId <= 0) {
        jsonResponse(false, '無效的商品ID');
    }
    
    $product = getProduct($productId);
    if (!$product) {
        jsonResponse(false, '商品不存在');
    }
    
    $highestBid = getHighestBid($productId);
    $countdown = getCountdown($product['end_time']);
    
    jsonResponse(true, '', [
        'current_price' => (float)$product['current_price'],
        'bid_count' => (int)$product['bid_count'],
        'end_time' => $product['end_time'],
        'countdown' => $countdown,
        'highest_bidder' => $highestBid ? $highestBid['bidder_name'] : null,
        'status' => $product['status']
    ]);
}

/**
 * 取得出價歷史
 */
function handleGetBidHistory(): void {
    $productId = (int)($_GET['product_id'] ?? 0);
    
    if ($productId <= 0) {
        jsonResponse(false, '無效的商品ID');
    }
    
    $bids = getBidHistory($productId);
    
    // 格式化資料供 Chart.js 使用
    $chartData = [
        'labels' => [],
        'data' => []
    ];
    
    foreach ($bids as $bid) {
        $chartData['labels'][] = date('H:i', strtotime($bid['created_at']));
        $chartData['data'][] = (float)$bid['amount'];
    }
    
    jsonResponse(true, '', [
        'bids' => $bids,
        'chart_data' => $chartData
    ]);
}

// ============================================
// 商品處理
// ============================================

function handleCreateProduct(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $auctionType = $_POST['auction_type'] ?? 'auction';
    $startingPrice = (float)($_POST['starting_price'] ?? 0);
    $minIncrement = (float)($_POST['min_increment'] ?? 10);
    $duration = (int)($_POST['duration'] ?? 24); // 小時
    $category = $_POST['category'] ?? 'general';
    $allowedBuyerId = $_POST['allowed_buyer_id'] ?? null;
    $imageUrl = trim($_POST['image_url'] ?? '');
    $customEndTime = trim($_POST['end_time'] ?? '');
    $stock = max(1, (int)($_POST['stock'] ?? 1));
    
    // 處理圖片上傳
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            jsonResponse(false, '只支援 JPG, PNG, GIF, WEBP 圖片格式');
        }
        
        $filename = uniqid('product_') . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            $imageUrl = 'uploads/' . $filename;
        }
    }
    
    // 驗證
    if (strlen($title) < 5) {
        jsonResponse(false, '標題至少需要 5 個字元');
    }
    
    if (!in_array($auctionType, ['auction', 'fixed', 'private'])) {
        jsonResponse(false, '無效的拍賣類型');
    }
    
    if ($startingPrice < 1) {
        jsonResponse(false, '起標價至少需為 $1');
    }
    
    if ($auctionType === 'private' && empty($allowedBuyerId)) {
        jsonResponse(false, '專屬拍賣必須指定買家');
    }
    
    $userId = getCurrentUserId();
    
    // 計算結束時間
    if ($auctionType === 'auction') {
        // 競標：支持自訂結束時間
        if (!empty($customEndTime)) {
            $endTime = date('Y-m-d H:i:s', strtotime($customEndTime));
        } else {
            $endTime = date('Y-m-d H:i:s', strtotime("+$duration hours"));
        }
    } else {
        // 直購/專屬：設定為 10 年後（無期限）
        $endTime = date('Y-m-d H:i:s', strtotime("+10 years"));
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO products 
        (seller_id, title, description, image_url, auction_type, starting_price, 
         current_price, min_increment, allowed_buyer_id, end_time, original_end_time, category, stock)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $allowedBuyerIdValue = $auctionType === 'private' ? (int)$allowedBuyerId : null;
    
    if ($stmt->execute([
        $userId, $title, $description, $imageUrl, $auctionType, $startingPrice,
        $startingPrice, $minIncrement, $allowedBuyerIdValue, $endTime, $endTime, $category, $stock
    ])) {
        $productId = $pdo->lastInsertId();
        
        // 記錄活動
        $user = getCurrentUser();
        logActivity('new_listing', $userId, $productId, 
            $user['username'] . " 上架了新商品: $title", $startingPrice);
        
        // 通知關注者
        notifyFollowers($userId, $productId, $title);
        
        jsonResponse(true, '商品上架成功！', ['product_id' => $productId]);
    } else {
        jsonResponse(false, '上架失敗');
    }
}

function handleGetProducts(): void {
    global $pdo;
    
    $type = $_GET['type'] ?? 'all';
    $category = $_GET['category'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $userId = getCurrentUserId();
    
    $where = ["p.status = 'active'", "p.end_time > NOW()"];
    $params = [];
    
    // 類型篩選
    if ($type !== 'all' && in_array($type, ['auction', 'fixed'])) {
        $where[] = "p.auction_type = ?";
        $params[] = $type;
    }
    
    // 排除私人拍賣（除非是指定買家或賣家）
    if ($userId) {
        $where[] = "(p.auction_type != 'private' OR p.allowed_buyer_id = ? OR p.seller_id = ?)";
        $params[] = $userId;
        $params[] = $userId;
    } else {
        $where[] = "p.auction_type != 'private'";
    }
    
    // 分類篩選
    if (!empty($category)) {
        $where[] = "p.category = ?";
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $where);
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as seller_name
        FROM products p
        JOIN users u ON p.seller_id = u.id
        WHERE $whereClause
        ORDER BY p.end_time ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // 加入倒數時間
    foreach ($products as &$product) {
        $product['countdown'] = getCountdown($product['end_time']);
    }
    
    jsonResponse(true, '', ['products' => $products]);
}

function handleBuyNow(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $userId = getCurrentUserId();
    
    try {
        $pdo->beginTransaction();
        
        // 鎖定商品
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('商品不存在');
        }
        
        if ($product['auction_type'] !== 'fixed' && $product['auction_type'] !== 'private') {
            throw new Exception('此商品不支援直接購買');
        }
        
        if ($product['status'] !== 'active') {
            throw new Exception('此商品已售出或已下架');
        }
        
        if ((int)$product['seller_id'] === $userId) {
            throw new Exception('不能購買自己的商品');
        }
        
        // 私人拍賣檢查
        if ($product['auction_type'] === 'private' && (int)$product['allowed_buyer_id'] !== $userId) {
            throw new Exception('此為專屬商品，您無權購買');
        }
        
        $price = (float)$product['current_price'];
        
        // 檢查並扣除餘額
        $stmt = $pdo->prepare("
            UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?
        ");
        $stmt->execute([$price, $userId, $price]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('餘額不足');
        }
        
        // 更新商品狀態
        $stmt = $pdo->prepare("
            UPDATE products SET status = 'sold', winner_id = ? WHERE id = ?
        ");
        $stmt->execute([$userId, $productId]);
        
        // 計算費用
        $platformFee = $price * 0.05;
        $sellerReceived = $price - $platformFee;
        
        // 賣家收款
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$sellerReceived, $product['seller_id']]);
        
        // 建立訂單
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (product_id, buyer_id, seller_id, final_price, platform_fee, seller_received, status)
            VALUES (?, ?, ?, ?, ?, ?, 'paid')
        ");
        $stmt->execute([$productId, $userId, $product['seller_id'], $price, $platformFee, $sellerReceived]);
        
        $orderId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // 記錄活動
        $user = getCurrentUser();
        logActivity('sale', $userId, $productId, 
            $user['username'] . " 購買了 " . $product['title'], $price);
        
        jsonResponse(true, '購買成功！', ['order_id' => $orderId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, $e->getMessage());
    }
}

// ============================================
// 評論與私訊處理
// ============================================

function handleAddComment(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $parentId = $_POST['parent_id'] ?? null;
    $userId = getCurrentUserId();
    
    if (strlen($content) < 2 || strlen($content) > 1000) {
        jsonResponse(false, '留言需為 2-1000 個字元');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO comments (product_id, user_id, parent_id, content)
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$productId, $userId, $parentId ?: null, $content])) {
        jsonResponse(true, '留言成功');
    } else {
        jsonResponse(false, '留言失敗');
    }
}

function handleGetComments(): void {
    global $pdo;
    
    $productId = (int)($_GET['product_id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.product_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$productId]);
    
    jsonResponse(true, '', ['comments' => $stmt->fetchAll()]);
}

function handleSendMessage(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $productId = $_POST['product_id'] ?? null;
    $content = trim($_POST['content'] ?? '');
    $userId = getCurrentUserId();
    
    if ($receiverId === $userId) {
        jsonResponse(false, '不能發送訊息給自己');
    }
    
    if (strlen($content) < 1 || strlen($content) > 2000) {
        jsonResponse(false, '訊息需為 1-2000 個字元');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, product_id, content)
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$userId, $receiverId, $productId ?: null, $content])) {
        $messageId = $pdo->lastInsertId();
        jsonResponse(true, '訊息已發送', ['message_id' => (int)$messageId]);
    } else {
        jsonResponse(false, '發送失敗');
    }
}

function handleGetMessages(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $otherUserId = (int)($_GET['user_id'] ?? 0);
    $lastId = (int)($_GET['last_id'] ?? 0);
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               sender.username as sender_name,
               receiver.username as receiver_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
          AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $lastId]);
    
    $messages = $stmt->fetchAll();
    
    // 標記為已讀
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1 
            WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId, $otherUserId]);
    }
    
    jsonResponse(true, '', ['messages' => $messages]);
}

function handleGetConversations(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $userId = getCurrentUserId();
    
    // 取得所有對話對象
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.username as other_username,
            u.avatar as other_avatar,
            MAX(m.created_at) as last_message_time,
            SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM messages m
        JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_user_id, u.username, u.avatar
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    
    jsonResponse(true, '', ['conversations' => $stmt->fetchAll()]);
}

// ============================================
// 訂單與評價
// ============================================

function handleCheckout(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $shippingName = trim($_POST['shipping_name'] ?? '');
    $shippingPhone = trim($_POST['shipping_phone'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $userId = getCurrentUserId();
    
    // 驗證
    if (empty($shippingName) || empty($shippingPhone) || empty($shippingAddress)) {
        jsonResponse(false, '請填寫完整的收貨資訊');
    }
    
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET shipping_name = ?, shipping_phone = ?, shipping_address = ?, 
            shipping_status = 'pending', updated_at = NOW()
        WHERE id = ? AND buyer_id = ?
    ");
    
    if ($stmt->execute([$shippingName, $shippingPhone, $shippingAddress, $orderId, $userId]) 
        && $stmt->rowCount() > 0) {
        jsonResponse(true, '收貨資訊已更新');
    } else {
        jsonResponse(false, '更新失敗');
    }
}

function handleAddReview(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $userId = getCurrentUserId();
    
    if ($rating < 1 || $rating > 5) {
        jsonResponse(false, '評分需為 1-5 星');
    }
    
    // 取得訂單資訊
    $stmt = $pdo->prepare("
        SELECT * FROM orders WHERE id = ? AND buyer_id = ? AND is_reviewed = 0
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, '訂單不存在或已評價');
    }
    
    try {
        $pdo->beginTransaction();
        
        // 新增評價
        $stmt = $pdo->prepare("
            INSERT INTO reviews (order_id, reviewer_id, seller_id, product_id, rating, comment)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $userId, $order['seller_id'], $order['product_id'], $rating, $comment]);
        
        // 更新訂單評價狀態
        $stmt = $pdo->prepare("UPDATE orders SET is_reviewed = 1 WHERE id = ?");
        $stmt->execute([$orderId]);
        
        $pdo->commit();
        
        jsonResponse(true, '評價成功！感謝您的回饋');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, '評價失敗');
    }
}

// ============================================
// 訂單發貨與確認收貨
// ============================================

function handleShipOrder(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // 驗證訂單屬於此賣家
    $stmt = $pdo->prepare("
        SELECT o.*, p.title as product_title, u.username as buyer_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ? AND o.seller_id = ? AND o.status = 'paid'
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, '訂單不存在或無法發貨');
    }
    
    // 更新訂單狀態為已發貨
    $stmt = $pdo->prepare("
        UPDATE orders SET status = 'shipped', shipping_status = 'shipped', updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$orderId]);
    
    // 發送通知給買家
    $message = "📦 您的訂單已發貨！商品【{$order['product_title']}】已由賣家寄出，請留意收貨。";
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, product_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $order['buyer_id'], $message, $order['product_id']]);
    
    jsonResponse(true, '已標記發貨，買家已收到通知');
}

function handleConfirmReceived(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // 驗證訂單屬於此買家
    $stmt = $pdo->prepare("
        SELECT o.*, p.title as product_title, u.username as seller_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.seller_id = u.id
        WHERE o.id = ? AND o.buyer_id = ? AND o.status = 'shipped'
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, '訂單不存在或無法確認收貨');
    }
    
    // 更新訂單狀態為已完成
    $stmt = $pdo->prepare("
        UPDATE orders SET status = 'completed', shipping_status = 'completed', 
               completed_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$orderId]);
    
    // 發送通知給賣家
    $user = getCurrentUser();
    $message = "✅ 買家 {$user['username']} 已確認收到商品【{$order['product_title']}】，訂單已完成！";
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, product_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $order['seller_id'], $message, $order['product_id']]);
    
    jsonResponse(true, '訂單已完成');
}

// ============================================
// 挖礦處理
// ============================================

function handleMine(): void {
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $result = mineCoins(getCurrentUserId());
    jsonResponse($result['success'], $result['message'], [
        'amount' => $result['amount'] ?? 0
    ]);
}

// ============================================
// 跑馬燈動態
// ============================================

function handleGetActivities(): void {
    $limit = min((int)($_GET['limit'] ?? 10), 20);
    $activities = getLatestActivities($limit);
    
    jsonResponse(true, '', ['activities' => $activities]);
}

// ============================================
// 購物車處理
// ============================================

function handleAddToCart(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $userId = getCurrentUserId();
    
    // 驗證商品
    $product = getProduct($productId);
    if (!$product) {
        jsonResponse(false, '商品不存在');
    }
    
    if ($product['auction_type'] === 'auction') {
        jsonResponse(false, '競標商品不能加入購物車');
    }
    
    if ($product['status'] !== 'active') {
        jsonResponse(false, '商品已下架');
    }
    
    if ((int)$product['seller_id'] === $userId) {
        jsonResponse(false, '不能購買自己的商品');
    }
    
    // 使用 UPSERT
    $stmt = $pdo->prepare("
        INSERT INTO cart (user_id, product_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + ?
    ");
    
    if ($stmt->execute([$userId, $productId, $quantity, $quantity])) {
        jsonResponse(true, '已加入購物車');
    } else {
        jsonResponse(false, '操作失敗');
    }
}

function handleUpdateCart(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $userId = getCurrentUserId();
    
    if ($quantity < 1) {
        // 如果數量小於 1，移除商品
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        jsonResponse(true, '商品已移除');
    }
    
    // 檢查庫存
    $product = getProduct($productId);
    if (!$product) {
        jsonResponse(false, '商品不存在');
    }
    
    if ($quantity > $product['stock']) {
        jsonResponse(false, '庫存不足', ['stock' => $product['stock']]);
    }
    
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    if ($stmt->execute([$quantity, $userId, $productId])) {
        jsonResponse(true, '數量已更新');
    } else {
        jsonResponse(false, '更新失敗');
    }
}

function handleRemoveFromCart(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    
    jsonResponse(true, '已從購物車移除');
}

function handleGetCart(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("
        SELECT c.*, p.title, p.image_url, p.current_price, p.status, p.seller_id,
               u.username as seller_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN users u ON p.seller_id = u.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    jsonResponse(true, '', ['cart' => $stmt->fetchAll()]);
}

function handleGetCartCount(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(true, '', ['count' => 0]);
        return;
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    jsonResponse(true, '', ['count' => (int)($result['count'] ?? 0)]);
}

function handleCheckoutCart(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $userId = getCurrentUserId();
    
    // 取得購物車
    $stmt = $pdo->prepare("
        SELECT c.*, p.current_price, p.seller_id, p.status, p.title, p.stock, p.auction_type
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ? AND p.status = 'active'
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        jsonResponse(false, '購物車是空的');
    }
    
    // 檢查庫存
    foreach ($items as $item) {
        $stock = $item['stock'] ?? 999;
        if ($item['quantity'] > $stock) {
            jsonResponse(false, "商品「{$item['title']}」庫存不足");
        }
    }
    
    // 計算總金額
    $total = 0;
    foreach ($items as $item) {
        $total += $item['current_price'] * $item['quantity'];
    }
    
    // 檢查餘額
    $user = getCurrentUser();
    if ($user['balance'] < $total) {
        jsonResponse(false, '餘額不足，需要 $' . number_format($total, 2));
    }
    
    try {
        $pdo->beginTransaction();
        
        // 扣款
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
        $stmt->execute([$total, $userId, $total]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('扣款失敗');
        }
        
        $orderIds = [];
        
        foreach ($items as $item) {
            $price = $item['current_price'] * $item['quantity'];
            $platformFee = $price * 0.05;
            $sellerReceived = $price - $platformFee;
            
            // 賣家收款
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$sellerReceived, $item['seller_id']]);
            
            // 減少庫存
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("商品「{$item['title']}」庫存不足");
            }
            
            // 檢查是否售完 (直購商品) - 更新狀態為 sold_out
            $stmt = $pdo->prepare("UPDATE products SET status = 'sold_out' WHERE id = ? AND stock = 0 AND auction_type != 'auction'");
            $stmt->execute([$item['product_id']]);
            
            // 建立訂單
            $stmt = $pdo->prepare("
                INSERT INTO orders (product_id, buyer_id, seller_id, final_price, platform_fee, seller_received, status)
                VALUES (?, ?, ?, ?, ?, ?, 'paid')
            ");
            $stmt->execute([$item['product_id'], $userId, $item['seller_id'], $price, $platformFee, $sellerReceived]);
            $orderIds[] = $pdo->lastInsertId();
        }
        
        // 清空購物車
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        
        // 返回第一個訂單 ID 供跳轉
        jsonResponse(true, '結帳成功！共 ' . count($orderIds) . ' 筆訂單', [
            'order_ids' => $orderIds,
            'order_id' => $orderIds[0]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, $e->getMessage());
    }
}

// ============================================
// 儲值處理
// ============================================

function handleTestDeposit(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $amount = (float)($_POST['amount'] ?? 0);
    
    if ($amount <= 0 || $amount > 1000000) {
        jsonResponse(false, '金額需在 $1 - $1,000,000 之間');
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    if ($stmt->execute([$amount, $userId])) {
        // 取得新餘額
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newBalance = $stmt->fetchColumn();
        
        jsonResponse(true, '儲值成功！已增加 $' . number_format($amount, 2), [
            'new_balance' => (float)$newBalance
        ]);
    } else {
        jsonResponse(false, '儲值失敗');
    }
}

// ============================================
// 關注處理
// ============================================

function handleFollowSeller(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $sellerId = (int)($_POST['seller_id'] ?? 0);
    $userId = getCurrentUserId();
    
    if ($sellerId === $userId) {
        jsonResponse(false, '不能關注自己');
    }
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO follows (follower_id, seller_id) VALUES (?, ?)");
    if ($stmt->execute([$userId, $sellerId])) {
        jsonResponse(true, '關注成功');
    } else {
        jsonResponse(false, '關注失敗');
    }
}

function handleUnfollowSeller(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $sellerId = (int)($_POST['seller_id'] ?? 0);
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND seller_id = ?");
    $stmt->execute([$userId, $sellerId]);
    
    jsonResponse(true, '已取消關注');
}

function handleGetFollowStatus(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(true, '', ['following' => false, 'followers_count' => 0]);
        return;
    }
    
    $sellerId = (int)($_GET['seller_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // 是否關注
    $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND seller_id = ?");
    $stmt->execute([$userId, $sellerId]);
    $isFollowing = $stmt->rowCount() > 0;
    
    // 粉絲數
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE seller_id = ?");
    $stmt->execute([$sellerId]);
    $followersCount = $stmt->fetchColumn();
    
    jsonResponse(true, '', [
        'following' => $isFollowing,
        'followers_count' => (int)$followersCount
    ]);
}

function handleGetFollowing(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("
        SELECT f.seller_id, u.username, u.avatar, f.created_at
        FROM follows f
        JOIN users u ON f.seller_id = u.id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    jsonResponse(true, '', ['following' => $stmt->fetchAll()]);
}

function handleGetFollowers(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("
        SELECT f.follower_id, u.username, u.avatar, f.created_at
        FROM follows f
        JOIN users u ON f.follower_id = u.id
        WHERE f.seller_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    jsonResponse(true, '', ['followers' => $stmt->fetchAll()]);
}

// ============================================
// 未讀訊息處理
// ============================================

function handleGetUnreadCount(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(true, '', ['count' => 0]);
        return;
    }
    
    $userId = getCurrentUserId();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    jsonResponse(true, '', ['count' => (int)$count]);
}

// ============================================
// 商品編輯處理
// ============================================

function handleUpdateProduct(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // 驗證商品擁有者
    $product = getProduct($productId);
    if (!$product || (int)$product['seller_id'] !== $userId) {
        jsonResponse(false, '無權編輯此商品');
    }
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    
    if (strlen($title) < 5) {
        jsonResponse(false, '標題至少需要 5 個字元');
    }
    
    // 競標商品有出價時不能改價格
    if ($product['auction_type'] === 'auction' && $product['bid_count'] > 0) {
        // 不更新價格
        $stmt = $pdo->prepare("
            UPDATE products SET title = ?, description = ? WHERE id = ?
        ");
        $stmt->execute([$title, $description, $productId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE products SET title = ?, description = ?, starting_price = ?, current_price = ? WHERE id = ?
        ");
        $stmt->execute([$title, $description, $price, $price, $productId]);
    }
    
    jsonResponse(true, '更新成功');
}

function handleDeleteProduct(): void {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(false, '請先登入');
    }
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // 驗證商品擁有者
    $product = getProduct($productId);
    if (!$product || (int)$product['seller_id'] !== $userId) {
        jsonResponse(false, '無權刪除此商品');
    }
    
    // 競標商品有出價時不能刪除
    if ($product['auction_type'] === 'auction' && $product['bid_count'] > 0) {
        jsonResponse(false, '有人出價的商品無法下架');
    }
    
    $stmt = $pdo->prepare("UPDATE products SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$productId]);
    
    jsonResponse(true, '商品已下架');
}

// ============================================
// 關注者通知 (在商品上架後調用)
// ============================================

function notifyFollowers(int $sellerId, int $productId, string $productTitle): void {
    global $pdo;
    
    try {
        // 取得所有關注者
        $stmt = $pdo->prepare("SELECT follower_id FROM follows WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $followers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($followers)) {
            return;
        }
        
        // 取得賣家名稱
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$sellerId]);
        $sellerName = $stmt->fetchColumn();
        
        // 使用賣家自己發送通知（作為系統通知）
        $message = "🔔 您關注的賣家 {$sellerName} 上架了新商品：{$productTitle}";
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, content, product_id)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($followers as $followerId) {
            // 使用賣家 ID 作為發送者
            $stmt->execute([$sellerId, $followerId, $message, $productId]);
        }
    } catch (Exception $e) {
        // 忽略通知錯誤，不影響商品上架
        error_log("notifyFollowers error: " . $e->getMessage());
    }
}

