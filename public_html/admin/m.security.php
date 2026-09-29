<?php
/**
 * Title: セキュリティ・IP管理
 * Icon: fas fa-user-shield
 * Color: red
 * Desc: 緊急キー変更、および許可IPの固定化・削除。
 * * * Sort: -50
 */
session_start();
require_once __DIR__ . '/../inc.db.php';

// 権限チェック
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    die("Access Denied: 特権管理者のみアクセス可能です。");
}

$message = '';
$my_ip = $_SERVER['REMOTE_ADDR'];

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 緊急キー変更
    if ($_POST['action'] === 'update_key') {
        $new_key = trim($_POST['new_key']);
        if (strlen($new_key) < 8) {
            $message = "<div class='alert error'>エラー: 緊急キーは8文字以上にしてください。</div>";
        } else {
            $stmt = $pdo->prepare("UPDATE system_configs SET config_value = ? WHERE config_key = 'emergency_key'");
            $stmt->execute([$new_key]);
            $message = "<div class='alert success'>緊急アクセスキーを変更しました。</div>";
        }
    }

    // IP手動追加
    elseif ($_POST['action'] === 'add_ip') {
        $ip = $_POST['ip'];
        $desc = $_POST['description'];
        try {
            // 手動追加はデフォルトで無期限(NULL)
            $stmt = $pdo->prepare("INSERT INTO allowed_ips (ip_address, description, expires_at) VALUES (?, ?, NULL)");
            $stmt->execute([$ip, $desc]);
            $message = "<div class='alert success'>IPアドレスを追加しました。</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert error'>エラー: " . $e->getMessage() . "</div>";
        }
    }

    // IP固定化 (24時間制限解除)
    elseif ($_POST['action'] === 'fix_ip') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("UPDATE allowed_ips SET expires_at = NULL, description = CONCAT(description, ' (固定済)') WHERE id = ?");
        $stmt->execute([$id]);
        $message = "<div class='alert success'>IPアドレスを無期限(永久)リストに昇格させました。</div>";
    }

    // IP削除
    elseif ($_POST['action'] === 'delete_ip') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM allowed_ips WHERE id = ?");
        $stmt->execute([$id]);
        $message = "<div class='alert success'>IPアドレスを削除しました。</div>";
    }
}

// データ取得 (キー & IPリスト)
$stmt = $pdo->query("SELECT config_value FROM system_configs WHERE config_key = 'emergency_key'");
$current_key = $stmt->fetchColumn() ?: '未設定';

$allowed_ips = $pdo->query("SELECT * FROM allowed_ips ORDER BY expires_at DESC, id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>セキュリティ管理 | 林六システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #ef4444; --bg: #f8fafc; --text: #334155; }
        body { font-family: "Hiragino Sans", sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px; }
        h2 { font-size: 1.1rem; margin-top: 30px; color: var(--accent); border-left: 4px solid var(--accent); padding-left: 10px; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }

        .form-box { background: #f1f5f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
        button { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-update { background: var(--accent); color: white; }
        .btn-add { background: #2563eb; color: white; }
        .btn-del { background: #64748b; color: white; padding: 5px 10px; font-size: 0.8rem; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f8fafc; }
        .badge-me { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: bold; }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>🛡️ セキュリティ・IP管理</h1>
        <a href="index.php" style="color:#64748b; text-decoration:none;">&laquo; ダッシュボード</a>
    </div>

    <?= $message ?>

    <h2>🔑 緊急アクセスキー設定</h2>
    <div class="form-box">
        <p style="margin-top:0; font-size:0.9rem;">
            IP制限でアクセスできなくなった際に、裏口から入るためのパスワードです。<br>
            現在のキー: <strong><?= htmlspecialchars($current_key) ?></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="update_key">
            <input type="text" name="new_key" placeholder="新しいキー (8文字以上)" required minlength="8" style="width:300px;">
            <button type="submit" class="btn-update">変更保存</button>
        </form>
    </div>

    <h2>🌐 許可IPアドレス一覧</h2>
    <div class="form-box">
        <p style="margin-top:0; font-weight:bold;">新規IP追加</p>
        <form method="POST" style="display:flex; gap:10px;">
            <input type="hidden" name="action" value="add_ip">
            <input type="text" name="ip" placeholder="例: 203.0.113.5" required style="width:200px;">
            <input type="text" name="description" placeholder="説明 (例: 本社固定IP)" required style="width:250px;">
            <button type="submit" class="btn-add">追加</button>
        </form>
        <p style="font-size:0.8rem; color:#666;">あなたの現在のIP: <?= $my_ip ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>IPアドレス</th>
                <th>ステータス / 有効期限</th>
                <th>説明</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allowed_ips as $ip): ?>
            <tr style="<?= $ip['ip_address'] === $my_ip ? 'background:#f0fdf4;' : '' ?>">
                <td>
                    <?= htmlspecialchars($ip['ip_address']) ?>
                    <?php if($ip['ip_address'] === $my_ip): ?>
                        <span class="badge-me">YOU</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($ip['expires_at']): ?>
                        <span style="color:#ef4444; font-weight:bold;">
                            <i class="fas fa-hourglass-half"></i> あと 
                            <?php 
                                $diff = strtotime($ip['expires_at']) - time();
                                echo ($diff > 0) ? round($diff / 3600) . '時間' : '期限切れ';
                            ?>
                        </span>
                        <div style="font-size:0.75rem; color:#666;">(<?= $ip['expires_at'] ?> まで)</div>
                    <?php else: ?>
                        <span style="color:#166534; font-weight:bold;"><i class="fas fa-check-circle"></i> 無期限 (固定)</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($ip['description']) ?></td>
                <td>
                    <div style="display:flex; gap:5px;">
                        <?php if ($ip['expires_at']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="fix_ip">
                            <input type="hidden" name="id" value="<?= $ip['id'] ?>">
                            <button class="btn-add" style="padding:5px 10px; font-size:0.8rem;"><i class="fas fa-thumbtack"></i> 固定化</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('削除しますか？');">
                            <input type="hidden" name="action" value="delete_ip">
                            <input type="hidden" name="id" value="<?= $ip['id'] ?>">
                            <button class="btn-del"><i class="fas fa-trash"></i> 削除</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table><?php require_once 'inc.footer.php'; ?>
</div>

</body>
</html>