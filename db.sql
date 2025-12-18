-- ============================================
-- Velocity Auction Pro - Database Schema
-- 即時競標與電商平台資料庫結構
-- ============================================

-- 建立資料庫（如果不存在）
CREATE DATABASE IF NOT EXISTS velocity_auction_pro 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE velocity_auction_pro;

-- ============================================
-- 1. 用戶表 (users)
-- 儲存用戶帳號、錢包餘額、挖礦獲得金額、成就追蹤
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    -- 錢包系統
    balance DECIMAL(15, 2) DEFAULT 10000.00 COMMENT '可用餘額(新用戶預設10000)',
    frozen_balance DECIMAL(15, 2) DEFAULT 0.00 COMMENT '凍結金額(出價中)',
    
    -- 挖礦與成就
    mined_amount DECIMAL(15, 2) DEFAULT 0.00 COMMENT '累積挖礦獲得金額',
    total_bid_amount DECIMAL(15, 2) DEFAULT 0.00 COMMENT '累積出價金額(用於成就計算)',
    
    -- 個人資料
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    
    -- 時間戳
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. 商品表 (products)
-- 支援三種拍賣模式：auction(競標)、fixed(直購)、private(專屬)
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    
    -- 商品基本資訊
    title VARCHAR(200) NOT NULL,
    description TEXT,
    image_url VARCHAR(500) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    
    -- 拍賣類型與價格
    auction_type ENUM('auction', 'fixed', 'private') NOT NULL DEFAULT 'auction',
    starting_price DECIMAL(15, 2) NOT NULL COMMENT '起標價/直購價',
    current_price DECIMAL(15, 2) NOT NULL COMMENT '當前最高價',
    min_increment DECIMAL(15, 2) DEFAULT 10.00 COMMENT '最低加價金額',
    
    -- 庫存數量 (直購/專屬商品用)
    stock INT DEFAULT 1 COMMENT '庫存數量',
    
    -- 專屬買家(private模式用)
    allowed_buyer_id INT DEFAULT NULL COMMENT '私人拍賣指定買家ID',
    
    -- 時間設定
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NOT NULL COMMENT '結束時間(可因Soft Close延長)',
    original_end_time TIMESTAMP NOT NULL COMMENT '原始結束時間',
    
    -- 狀態 (新增 sold_out 狀態)
    status ENUM('active', 'ended', 'sold', 'sold_out', 'cancelled') DEFAULT 'active',
    winner_id INT DEFAULT NULL COMMENT '得標者ID',
    
    -- 統計
    view_count INT DEFAULT 0,
    bid_count INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (allowed_buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_seller (seller_id),
    INDEX idx_status (status),
    INDEX idx_auction_type (auction_type),
    INDEX idx_end_time (end_time),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. 出價記錄表 (bids)
-- 記錄所有出價歷史，用於圖表與走勢分析
-- ============================================
CREATE TABLE IF NOT EXISTS bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    bidder_id INT NOT NULL,
    
    amount DECIMAL(15, 2) NOT NULL COMMENT '出價金額',
    is_auto_bid TINYINT(1) DEFAULT 0 COMMENT '是否為自動代標',
    
    -- 狀態
    status ENUM('active', 'outbid', 'won', 'refunded') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_product (product_id),
    INDEX idx_bidder (bidder_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. 自動代標設定表 (auto_bids)
-- 儲存用戶的自動出價上限設定
-- ============================================
CREATE TABLE IF NOT EXISTS auto_bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    bidder_id INT NOT NULL,
    
    max_amount DECIMAL(15, 2) NOT NULL COMMENT '自動出價上限',
    current_frozen DECIMAL(15, 2) DEFAULT 0.00 COMMENT '當前凍結金額',
    
    is_active TINYINT(1) DEFAULT 1,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_product_bidder (product_id, bidder_id),
    INDEX idx_product (product_id),
    INDEX idx_bidder (bidder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. 商品留言表 (comments) - 公開問與答
-- ============================================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL COMMENT '回覆的留言ID',
    
    content TEXT NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    
    INDEX idx_product (product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. 私訊表 (messages) - 一對一聊天
-- ============================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    product_id INT DEFAULT NULL COMMENT '關聯商品(可選)',
    
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. 訂單表 (orders) - 已完成交易
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    
    -- 金額資訊
    final_price DECIMAL(15, 2) NOT NULL,
    platform_fee DECIMAL(15, 2) DEFAULT 0.00 COMMENT '平台手續費',
    seller_received DECIMAL(15, 2) NOT NULL COMMENT '賣家實際收到金額',
    
    -- 物流資訊
    shipping_name VARCHAR(100) DEFAULT NULL,
    shipping_phone VARCHAR(20) DEFAULT NULL,
    shipping_address TEXT DEFAULT NULL,
    shipping_status ENUM('pending', 'shipped', 'delivered', 'completed') DEFAULT 'pending',
    tracking_number VARCHAR(100) DEFAULT NULL,
    
    -- 訂單狀態
    status ENUM('pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'refunded') DEFAULT 'paid',
    
    -- 評價狀態
    is_reviewed TINYINT(1) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. 評價表 (reviews) - 1-5星評價
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    reviewer_id INT NOT NULL COMMENT '評價者(買家)',
    seller_id INT NOT NULL COMMENT '被評價者(賣家)',
    product_id INT NOT NULL,
    
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5星評分',
    comment TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_order_review (order_id),
    INDEX idx_seller (seller_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. 系統動態表 (activities) - 用於跑馬燈
-- ============================================
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('bid', 'sale', 'new_listing') NOT NULL,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    
    message VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. 購物車表 (cart)
-- ============================================
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. 關注表 (follows)
-- ============================================
CREATE TABLE IF NOT EXISTS follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    seller_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 插入測試用戶資料
-- ============================================
INSERT INTO users (username, email, password_hash, balance) VALUES
('demo_user', 'demo@example.com', '$2y$10$LM5whHYx3zZDk0T3rN70bOc3SJITZiSYuHIV2EZ1a7xoh4ubYZ4Im', 50000.00),
('seller_alice', 'alice@example.com', '$2y$10$LM5whHYx3zZDk0T3rN70bOc3SJITZiSYuHIV2EZ1a7xoh4ubYZ4Im', 100000.00),
('bidder_bob', 'bob@example.com', '$2y$10$LM5whHYx3zZDk0T3rN70bOc3SJITZiSYuHIV2EZ1a7xoh4ubYZ4Im', 75000.00);
-- 測試密碼皆為: 123

-- ============================================
-- 插入測試商品資料
-- ============================================
INSERT INTO products (seller_id, title, description, image_url, auction_type, starting_price, current_price, min_increment, end_time, original_end_time, category, stock) VALUES
(2, '稀有古董懷錶 - 1920年代瑞士製', '這是一款極為罕見的1920年代瑞士製古董懷錶，保存狀態極佳，機芯運作正常。附原廠盒子與證書。', 'https://images.unsplash.com/photo-1509048191080-d2984bad6ae5?w=400', 'auction', 5000.00, 5000.00, 100.00, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'antique', 1),
(2, '限量版藝術畫作 - 城市夜景', '知名藝術家親筆簽名限量版印刷畫作，編號 23/100，含精美木質框。', 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?w=400', 'auction', 3000.00, 3000.00, 50.00, DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'art', 1),
(2, 'MacBook Pro 16吋 M3 Max', '全新未拆封 MacBook Pro 16吋，M3 Max晶片，36GB RAM，1TB SSD。提供完整保固。', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=400', 'fixed', 89000.00, 89000.00, 0.00, DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'electronics', 5);

-- 設定專屬商品的指定買家
UPDATE products SET allowed_buyer_id = 1 WHERE id = 4;

SELECT 'Database setup completed successfully!' AS status;