<?php
/**
 * Title: システム設定・祝日
 * Icon: fas fa-cogs
 * Color: gray
 * Desc: 納期のリードタイム設定、通知メールのBCC設定、および祝日カレンダーの管理。
 * Roles: super_admin
 * * Sort: -100
 */

session_start();
require_once __DIR__ . '/../inc.db.php';

// 権限チェック (Super Adminのみ)
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    die("Access Denied");
}

$msg = '';

// ■ POST処理: 設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. 基本設定の保存
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        try {
            $pdo->beginTransaction();
            $configs = [
                'lead_time_days' => $_POST['lead_time_days'],
                'global_bcc_list' => $_POST['global_bcc_list'],
            ];
            $stmt = $pdo->prepare("INSERT INTO system_configs (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
            foreach ($configs as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            $pdo->commit();
            $msg = "<div class='alert success'>基本設定を保存しました。</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "<div class='alert error'>保存エラー: " . $e->getMessage() . "</div>";
        }
    }

    // B. 祝日の追加
    if (isset($_POST['action']) && $_POST['action'] === 'add_holiday') {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO public_holidays (date, name) VALUES (?, ?)");
            $stmt->execute([$_POST['date'], $_POST['name']]);
            $msg = "<div class='alert success'>祝日を追加しました。</div>";
        } catch (Exception $e) {
            $msg = "<div class='alert error'>エラー: " . $e->getMessage() . "</div>";
        }
    }

    // C. 祝日の削除
    if (isset($_POST['action']) && $_POST['action'] === 'del_holiday') {
        $stmt = $pdo->prepare("DELETE FROM public_holidays WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $msg = "<div class='alert success'>祝日を削除しました。</div>";
    }
}

// ■ データ取得
$configs = $pdo->query("SELECT config_key, config_value FROM system_configs")->fetchAll(PDO::FETCH_KEY_PAIR);
$holidays = $pdo->query("SELECT * FROM public_holidays ORDER BY date DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>システム設定 | 林六システム</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #f4f7f9; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h1 { grid-column: span 2; border-bottom: 2px solid #666; padding-bottom: 10px; margin-bottom: 10px; }
        h2 { margin-top: 0; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type=text], input[type=number], input[type=date] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .note { font-size: 0.85rem; color: #666; margin-top: 5px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: bold; }
        .btn-primary { background: #2c3e50; }
        .btn-add { background: #27ae60; }
        .btn-del { background: #e74c3c; padding: 5px 10px; font-size: 0.8rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
        
        .alert { grid-column: span 2; padding: 15px; background: #dcfce7; color: #166534; border-radius: 4px; margin-bottom: 10px; }
        .alert.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1>⚙️ システム全体設定</h1>
    
    <?= $msg ?>

    <div class="card">
        <h2><i class="fas fa-sliders-h"></i> 基本ルール設定</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_config">
            
            <div class="form-group">
                <label>納期リードタイム (営業日)</label>
                <input type="number" name="lead_time_days" value="<?= htmlspecialchars($configs['lead_time_days'] ?? '4') ?>" required>
                <div class="note">※土日・祝日を除いた日数です。<br>例: 4営業日後から選択可能</div>
            </div>

            <div class="form-group">
                <label>注文メール BCC送信先</label>
                <input type="text" name="global_bcc_list" value="<?= htmlspecialchars($configs['global_bcc_list'] ?? '') ?>" placeholder="admin@example.com, boss@example.com">
                <div class="note">※複数の場合はカンマ(,)区切り。</div>
            </div>

            <button type="submit" class="btn btn-primary">設定を保存</button>
        </form>
    </div>

    <div class="card">
        <h2><i class="far fa-calendar-alt"></i> 祝日カレンダー設定</h2>
        <p class="note">ここで登録した日は営業日カウントから除外されます。</p>
        
        <form method="POST" style="display:flex; gap:10px; margin-bottom:20px; align-items:flex-end;">
            <input type="hidden" name="action" value="add_holiday">
            <div style="flex:1;">
                <label>日付</label>
                <input type="date" name="date" required>
            </div>
            <div style="flex:1;">
                <label>名称 (任意)</label>
                <input type="text" name="name" placeholder="春分の日">
            </div>
            <button type="submit" class="btn btn-add">追加</button>
        </form>

        <div style="max-height: 400px; overflow-y: auto;">
            <table>
                <thead><tr><th>日付</th><th>名称</th><th>操作</th></tr></thead>
                <tbody>
                    <?php foreach($holidays as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['date']) ?></td>
                        <td><?= htmlspecialchars($h['name']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('削除しますか？')">
                                <input type="hidden" name="action" value="del_holiday">
                                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                <button class="btn btn-del">削除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="grid-column: span 2; text-align: center; margin-top: 20px;">
        <a href="index.php" style="color:#666; text-decoration:none;">&laquo; ダッシュボードへ戻る</a>
    </div>
</div>

</body>
</html>