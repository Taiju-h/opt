<?php
/**
 * Title: ユーザー・権限管理
 * Icon: fas fa-users-cog
 * Color: red
 * Desc: 管理者の招待・編集・削除。権限（Role）の設定。
 * Roles: super_admin
 * * Sort: -1
 * 
 */
 // admin/m.users.php
session_start();
require_once __DIR__ . '/../inc.db.php'; // DB接続

// 1. 権限チェック (Super Admin以外は追い出す)
// ※セッションに role が入っている前提
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo "<script>alert('【権限エラー】この画面は特権管理者のみアクセス可能です。'); location.href = 'index.php';</script>"; exit;
    //   die("Access Denied: この画面は特権管理者のみアクセス可能です。");
}

$message = '';

// 2. アクション処理 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. 招待メール送信 (新規登録) ---
    if ($_POST['action'] === 'invite') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        
        // パスワード自動生成 (8文字)
        $raw_password = substr(bin2hex(random_bytes(8)), 0, 8);
        $password_hash = password_hash($raw_password, PASSWORD_DEFAULT); // 本番はこれ推奨
        // ※デモ用にあえて平文保存する場合は $password_hash = $raw_password;

        try {
            // DB登録
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password_hash, $role]);

            // メール送信ロジック (mail関数)
            $subject = "【林六システム】アカウント招待のお知らせ";
            $body = "{$name} 様\n\n林六 受発注管理システムへの招待が届きました。\n以下の情報でログインしてください。\n\nURL: https://hayashiroku.daishokagaku.com/admin/\nID: {$email}\nPass: {$raw_password}\n\n※ログイン後にパスワードを変更してください。";
            $headers = "From: system@hayashiroku.daishokagaku.com";

            // ★サーバーでメール設定が有効ならコメントアウトを外す
            // mail($email, $subject, $body, $headers);

            // デモ用に画面にパスワードを表示
            $message = "<div class='alert success'>ユーザーを追加しました。<br><strong>自動生成パスワード: {$raw_password}</strong><br>(本来はこの内容がメールで飛びます)</div>";
            
        } catch (PDOException $e) {
            $message = "<div class='alert error'>エラー: " . $e->getMessage() . "</div>";
        }
    }

    // --- B. 編集 (更新) ---
    elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $new_pass = $_POST['password'];

        try {
            if (!empty($new_pass)) {
                // パスワード変更あり
                $hash = password_hash($new_pass, PASSWORD_DEFAULT); // 本番推奨
                // $hash = $new_pass; // デモ用
                $stmt = $pdo->prepare("UPDATE admins SET name=?, email=?, role=?, password_hash=? WHERE id=?");
                $stmt->execute([$name, $email, $role, $hash, $id]);
            } else {
                // パスワード変更なし
                $stmt = $pdo->prepare("UPDATE admins SET name=?, email=?, role=? WHERE id=?");
                $stmt->execute([$name, $email, $role, $id]);
            }
            $message = "<div class='alert success'>ユーザー情報を更新しました。</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert error'>更新エラー: " . $e->getMessage() . "</div>";
        }
    }

    // --- C. 削除 ---
    elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        if ($id == $_SESSION['admin_id']) {
            $message = "<div class='alert error'>自分自身のアカウントは削除できません。</div>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $message = "<div class='alert success'>ユーザーを削除しました。</div>";
        }
    }
}

// 3. ユーザー一覧取得
$users = $pdo->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー管理 | 林六システム</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; --bg: #f8fafc; --text: #334155; }
        body { font-family: "Hiragino Sans", sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .badge-super { background: #e0e7ff; color: #3730a3; }
        .badge-sales { background: #dcfce7; color: #166534; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { font-size: 0.85rem; padding: 5px 10px; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }

        /* モーダル */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 400px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>👥 管理ユーザー一覧</h1>
        <button class="btn btn-primary" onclick="openModal('invite')">
            <i class="fas fa-paper-plane"></i> 新規招待メール送信
        </button>
    </div>

    <?= $message ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名前</th>
                <th>メールアドレス</th>
                <th>権限</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php if($u['role'] == 'super_admin'): ?>
                        <span class="badge badge-super">Super Admin</span>
                    <?php else: ?>
                        <span class="badge badge-sales">Sales (林六)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-edit" 
                            onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                        <i class="fas fa-edit"></i> 編集
                    </button>
                    
                    <?php if($u['id'] != $_SESSION['admin_id']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button class="btn btn-sm btn-delete"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top:20px;">
        <a href="index.php" style="color:#64748b; text-decoration:none;">&laquo; ダッシュボードに戻る</a>
    </div>
</div>

<div id="modal-invite" class="modal">
    <div class="modal-content">
        <h2>📩 新規メンバー招待</h2>
        <form method="POST">
            <input type="hidden" name="action" value="invite">
            <div class="form-group">
                <label>名前</label>
                <input type="text" name="name" required placeholder="例：林六 太郎">
            </div>
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" required placeholder="taro@hayashiroku...">
            </div>
            <div class="form-group">
                <label>権限ロール</label>
                <select name="role">
                    <option value="sales">Sales (一般・林六社員)</option>
                    <option value="super_admin">Super Admin (管理者)</option>
                </select>
            </div>
            <div style="text-align:right;">
                <button type="button" class="btn" style="background:#ccc;" onclick="closeModal('invite')">キャンセル</button>
                <button type="submit" class="btn btn-primary">招待メール送信</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit" class="modal">
    <div class="modal-content">
        <h2>✏️ ユーザー編集</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            
            <div class="form-group">
                <label>名前</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="edit-email" required>
            </div>
            <div class="form-group">
                <label>権限ロール</label>
                <select name="role" id="edit-role">
                    <option value="sales">Sales (一般・林六社員)</option>
                    <option value="super_admin">Super Admin (管理者)</option>
                </select>
            </div>
            <div class="form-group">
                <label>パスワード変更 (空欄なら変更なし)</label>
                <input type="password" name="password" placeholder="新しいパスワード">
            </div>
            
            <div style="text-align:right;">
                <button type="button" class="btn" style="background:#ccc;" onclick="closeModal('edit')">キャンセル</button>
                <button type="submit" class="btn btn-edit">更新保存</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(type) {
        document.getElementById('modal-' + type).classList.add('active');
    }
    function closeModal(type) {
        document.getElementById('modal-' + type).classList.remove('active');
    }

    function openEdit(user) {
        document.getElementById('edit-id').value = user.id;
        document.getElementById('edit-name').value = user.name;
        document.getElementById('edit-email').value = user.email;
        document.getElementById('edit-role').value = user.role;
        openModal('edit');
    }
</script>
<?php require_once 'inc.footer.php'; ?>
</body>
</html>