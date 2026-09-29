<?php
/**
 * Title: 🛠️ 注文情報の最強修正 (Admin Only)
 */
require_once __DIR__ . '/../inc.db.php';
if (!is_admin_logged_in()) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID Missing");

// --- 更新処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. 注文基本情報の更新
        $stmt = $pdo->prepare("
            UPDATE orders SET 
                requested_date = ?, 
                confirmed_date = ?, 
                status = ?, 
                vehicle_id = ?, 
                is_urgent = ?, 
                memo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['requested_date'],
            $_POST['confirmed_date'] ?: null,
            $_POST['status'],
            $_POST['vehicle_id'],
            isset($_POST['is_urgent']) ? 1 : 0,
            $_POST['memo'],
            $id
        ]);

        // 2. 商品数量の更新
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item_id => $qty) {
                $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?")
                    ->execute([$qty, $item_id, $id]);
            }
        }

        $pdo->commit();
        echo "<script>alert('すべての情報を更新しました。'); location.href='m.order.php';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// データ取得
$order = fetchOne("SELECT o.*, s.name as site_name FROM orders o JOIN sites s ON o.site_id = s.id WHERE o.id = ?", [$id]);
if (!$order) die("Order Not Found");

$items = fetchAll("SELECT oi.*, p.name as p_name, p.unit FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?", [$id]);
$vehicles = fetchAll("SELECT * FROM vehicle_masters WHERE is_active = 1 ORDER BY sort_order");

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>注文修正 #<?= $id ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; background: #f4f7f9; padding: 20px; color: #334155; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; margin-bottom: 20px; color: #1e293b; border-left: 5px solid #2563eb; padding-left: 15px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem; color: #64748b; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        .item-box { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-submit { background: #2563eb; color: white; border: none; padding: 15px; width: 100%; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 0.9rem; }
        .urgent-check { display: flex; align-items: center; gap: 10px; background: #fff1f2; padding: 10px; border-radius: 6px; border: 1px solid #fecdd3; }
        .urgent-check input { width: auto; }
    </style>
</head>
<body>
<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1>🛠️ 注文修正: #<?= $id ?> (<?= h($order['site_name']) ?>)</h1>
    
    <form method="POST">
        <div class="form-group urgent-check">
            <input type="checkbox" name="is_urgent" id="is_urgent" <?= $order['is_urgent'] ? 'checked' : '' ?>>
            <label for="is_urgent" style="margin:0; color:#e11d48;">🔥 最短手配フラグ（至急案件）</label>
        </div>

        <div class="grid">
            <div class="form-group">
                <label>状況 (ステータス)</label>
                <select name="status">
                    <option value="requested" <?= $order['status']=='requested'?'selected':'' ?>>⏳ 回答待ち (Requested)</option>
                    <option value="confirmed" <?= $order['status']=='confirmed'?'selected':'' ?>>✅ 確定済 (Confirmed)</option>
                    <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>❌ 取消済 (Cancelled)</option>
                </select>
            </div>
            <div class="form-group">
                <label>配送車両</label>
                <select name="vehicle_id">
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $order['vehicle_id']==$v['id']?'selected':'' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid">
            <div class="form-group">
                <label>現場希望日</label>
                <input type="date" name="requested_date" value="<?= $order['requested_date'] ?>" required>
            </div>
            <div class="form-group">
                <label>メーカー確定日 (空欄で未定)</label>
                <input type="date" name="confirmed_date" value="<?= $order['confirmed_date'] ?>">
            </div>
        </div>

        <h3>📦 発注商品・数量の修正</h3>
        <?php foreach($items as $it): ?>
        <div class="item-box">
            <label><?= h($it['p_name']) ?> (<?= h($it['unit']) ?>)</label>
            <input type="number" name="items[<?= $it['id'] ?>]" value="<?= (float)$it['quantity'] ?>" step="0.1" required>
        </div>
        <?php endforeach; ?>

        <div class="form-group">
            <label>備考 (現場・管理者メモ)</label>
            <textarea name="memo" rows="4"><?= h($order['memo']) ?></textarea>
        </div>

        <button type="submit" class="btn-submit">この内容で強制更新する</button>
        <a href="m.order.php" class="btn-back">修正せずに戻る</a>
    </form>
</div>
</body>
</html>