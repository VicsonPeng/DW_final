<?php
/**
 * ============================================
 * Velocity Auction Pro - 頁面底部
 * footer.php
 * ============================================
 */
?>

<footer class="site-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand">
                <span class="logo-icon">⚡</span>
                <span class="logo-text">Velocity</span>
                <span class="logo-sub">AUCTION PRO</span>
            </div>
            <div class="footer-links">
                <a href="#">關於我們</a>
                <a href="#">使用條款</a>
                <a href="#">隱私政策</a>
                <a href="#">幫助中心</a>
            </div>
            <div class="footer-copyright">
                © <?php echo date('Y'); ?> Velocity Auction Pro. All rights reserved.
            </div>
        </div>
    </div>
</footer>

<style>
.site-footer {
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
    padding: 40px 0;
    margin-top: 60px;
}

.footer-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    text-align: center;
}

.footer-brand {
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-links {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    justify-content: center;
}

.footer-links a {
    color: var(--text-secondary);
    font-size: 14px;
}

.footer-links a:hover {
    color: var(--accent-gold);
}

.footer-copyright {
    color: var(--text-muted);
    font-size: 13px;
}
</style>
