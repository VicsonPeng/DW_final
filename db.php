<?php
/**
 * ============================================
 * Velocity Auction Pro - 資料庫連線配置
 * db.php
 * ============================================
 * 使用 PDO 進行資料庫連線，支援 UTF-8 編碼
 * 確保錯誤處理與安全性
 */

// 資料庫連線設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'velocity_auction_pro');
define('DB_USER', 'cvml');
define('DB_PASS', 'dwpcvml2025');
define('DB_CHARSET', 'utf8mb4');

/**
 * 建立 PDO 資料庫連線
 * @return PDO 資料庫連線物件
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            // 錯誤模式：拋出異常
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // 預設抓取模式：關聯陣列
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // 模擬預處理語句：關閉（使用真正的預處理）
            PDO::ATTR_EMULATE_PREPARES => false,
            // 持久連線
            PDO::ATTR_PERSISTENT => true
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // 生產環境中應記錄錯誤而非顯示
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => '資料庫連線失敗，請稍後再試'
            ]));
        }
    }
    
    return $pdo;
}

// 建立全域資料庫連線變數
$pdo = getDBConnection();
