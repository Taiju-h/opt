<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../inc.db.php';

// ---------------------------------------------------------
// 🛠️ 関数: 自動ログイン(認証用)Cookieの設定 (30日間)
// ---------------------------------------------------------
function set_auth_cookie($user) {
    $salt = 'hayashiroku_secret_salt_2026'; 
    $signature = hash_hmac('sha256', $user['id'] . $user['password_hash'], $salt);
    $cookie_value = $user['id'] . ':' . $signature;
    setcookie('admin_remember', $cookie_value, time() + (86400 * 30), "/", "", false, true);
}

// ---------------------------------------------------------
// 📧 関数: メールアドレス記憶用Cookie (ログアウトしても消さない)
// ---------------------------------------------------------
function set_email_cookie($email) {
    setcookie('saved_email', $email, time() + (86400 * 30), "/");
}

// ---------------------------------------------------------
// 🚪 ログアウト処理
// ---------------------------------------------------------
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('admin_remember', '', time() - 3600, "/"); 
    header("Location: login.php");
    exit;
}

// 初期値（Cookieからメールだけ復元）
$default_email = isset($_COOKIE['saved_email']) ? $_COOKIE['saved_email'] : '';

// ---------------------------------------------------------
// 🛡️ IP制限 & POST処理
// ---------------------------------------------------------
try {
    $stmt = $pdo->query("SELECT config_value FROM system_configs WHERE config_key = 'emergency_key'");
    $EMERGENCY_KEY = $stmt->fetchColumn() ?: 'hayashiroku2026'; 
} catch (Exception $e) { $EMERGENCY_KEY = 'hayashiroku2026'; }

$current_ip = $_SERVER['REMOTE_ADDR'];
try { $pdo->exec("DELETE FROM allowed_ips WHERE expires_at IS NOT NULL AND expires_at < NOW()"); } catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT count(*) FROM allowed_ips WHERE ip_address = ?");
$stmt->execute([$current_ip]);
$is_allowed_ip = $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. 緊急IP追加
    if (isset($_POST['action']) && $_POST['action'] === 'add_ip') {
        if ($_POST['emergency_key'] === $EMERGENCY_KEY) {
            try {
                $stmt = $pdo->prepare("INSERT INTO allowed_ips (ip_address, description, expires_at) VALUES (?, ?, NOW() + INTERVAL 24 HOUR)");
                $stmt->execute([$current_ip, '緊急アクセス: ' . date('m/d H:i')]);
                header("Location: login.php"); exit;
            } catch (PDOException $e) { $message = ['type' => 'error', 'text' => 'エラー: ' . $e->getMessage()]; }
        } else { $message = ['type' => 'error', 'text' => '緊急キーが違います。']; }
    }
    // B. ログイン処理
    elseif (isset($_POST['action']) && $_POST['action'] === 'login') {
        if (!$is_allowed_ip) die('Access Denied');

        $email = $_POST['email'];
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember']);

        // DBからユーザー情報を取得
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        $login_success = false;

        if ($admin) {
            // ★大改修：パスワードの二段構えチェック＆自動修復
            
            // ① 新しく追加された「暗号化(ハッシュ)パスワード」での照合
            if (password_verify($password, $admin['password_hash'])) {
                $login_success = true;
            } 
            // ② 昔からある「素のパスワード(平文)」での照合
            elseif ($password === $admin['password_hash']) {
                $login_success = true;
                
                // 【自動修復機能】素のパスワードで入れた場合、安全な暗号に変換してDBを上書きする！
                $new_secure_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                $update_stmt->execute([$new_secure_hash, $admin['id']]);
                
                // Cookie生成などのために、変数の中身も新しい暗号に書き換えておく
                $admin['password_hash'] = $new_secure_hash;
            }
        }

        // ログイン成功時の処理
        if ($login_success) { 
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];
            
            // Cookie保存
            set_email_cookie($email);
            if ($remember_me) {
                set_auth_cookie($admin);
            }

            // リダイレクト前にセッションを確実に保存
            session_write_close();

            header("Location: index.php");
            exit;
        } else {
            $message = ['type' => 'error', 'text' => 'IDまたはパスワードが違います。'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン | 林六システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; --danger: #ef4444; --bg: #f1f5f9; }
        body { font-family: "Hiragino Sans", sans-serif; background: var(--bg); color: #334155; margin: 0; }
        .center-screen { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .auth-card { background: white; width: 100%; max-width: 400px; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); text-align: center; }
        input[type="email"], input[type="password"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 1rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-success { background: #10b981; color: white; display:block; text-decoration:none; padding:15px; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: left; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: var(--danger); }
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #64748b; }
        .checkbox-group { display: flex; align-items: center; justify-content: flex-start; margin-bottom: 25px; font-size: 0.95rem; }
        .checkbox-group input { width: auto; margin-right: 10px; transform: scale(1.3); }
        .ip-blocked { border: 2px solid var(--danger); }
        
        /* ログイン済み時のスタイル */
        .logged-in-box { padding: 20px; border: 2px solid #10b981; border-radius: 8px; background: #ecfdf5; color: #065f46; }
    </style>
</head>
<body>

<?php if (!$is_allowed_ip): ?>
    <div class="center-screen">
        <div class="auth-card ip-blocked">
            <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--danger); margin-bottom: 20px;"></i>
            <h1 style="color: var(--danger); margin:0;">Access Denied</h1>
            <p>IP: <?= htmlspecialchars($current_ip) ?></p>
            <?php if (isset($message)) echo "<div class='alert alert-{$message['type']}'>{$message['text']}</div>"; ?>
            <details>
                <summary style="cursor:pointer; color:#2563eb; margin-top:15px;">緊急アクセスキー</summary>
                <form method="POST">
                    <input type="hidden" name="action" value="add_ip">
                    <input type="password" name="emergency_key" placeholder="管理者キー" required>
                    <button type="submit" class="btn btn-danger">許可リストに追加</button>
                </form>
            </details>
        </div>
    </div>
<?php else: ?>
    <div class="center-screen">
        <div class="auth-card">
            <h1 style="margin-bottom: 10px;">林六 受発注管理</h1>
            
            <?php if (isset($message)) echo "<div class='alert alert-{$message['type']}'>{$message['text']}</div>"; ?>

            <?php if (isset($_SESSION['admin_id'])): ?>
                <div class="logged-in-box">
                    <h3><i class="fas fa-check-circle"></i> ログイン済み</h3>
                    <p>有効なセッションがあります。</p>
                    <a href="index.php" class="btn btn-success" style="display:block; text-decoration:none; padding:12px;">
                        管理ダッシュボードへ移動
                    </a>
                    <br>
                    <a href="login.php?logout=true" style="color:#ef4444; font-size:0.9rem;">
                        [一度ログアウトする]
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="login.php" id="login_form" autocomplete="on">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label class="form-label" for="edge_id">メールアドレス</label>
                        <input type="email" name="email" id="edge_id" 
                               placeholder="example@hayashiroku.co.jp" 
                               required autocomplete="username" 
                               value="<?= htmlspecialchars($default_email) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edge_pw">パスワード</label>
                        <input type="password" name="password" id="edge_pw" 
                               placeholder="••••••••" 
                               required autocomplete="current-password">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="remember" id="rem_check" value="1" checked>
                        <label for="rem_check" style="cursor:pointer;">次回から自動的にログインする</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">ログイン</button>
                </form>
            <?php endif; ?>
            
            <p style="margin-top:20px; font-size:0.8rem; color:#ccc;">IP: <?= htmlspecialchars($current_ip) ?></p>
        </div>
    </div>
<?php endif; ?>

</body>
</html>