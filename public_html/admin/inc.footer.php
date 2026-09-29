<?php
// admin/inc.footer.php
?>
    <style>
        .admin-footer {
            max-width: 1000px; margin: 0 auto;

            margin-top: 60px;
            padding: 20px 0;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #64748b;
            font-size: 0.9rem;
        }
        .footer-nav a {
            text-decoration: none;
            color: #475569;
            margin-left: 20px;
            font-weight: bold;
            transition: color 0.2s;
        }
        .footer-nav a:hover { color: #2563eb; }
        .footer-nav a.logout { color: #ef4444; }
        .footer-nav a.logout:hover { color: #b91c1c; }
    </style>

    <div class="admin-footer">
        <div>
            <i class="fas fa-server"></i> 林六 受発注管理システム v1.0
        </div>
        <div class="footer-nav">
            <a href="index.php">
                <i class="fas fa-th-large"></i> ダッシュボードへ戻る
            </a>
         </div>
    </div>

</body>
</html>