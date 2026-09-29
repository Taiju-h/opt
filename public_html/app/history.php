<?php
// app/history.php
session_start();
require_once __DIR__ . '/../inc.db.php';
$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM sites WHERE access_token = ? AND is_active = 1");
$stmt->execute([$token]);
$site = $stmt->fetch();
if (!$site) die("Access Denied");

$orders = fetchAll("SELECT o.*, v.name as v_name FROM orders o LEFT JOIN vehicle_masters v ON o.vehicle_id = v.id WHERE o.site_id = ? ORDER BY o.id DESC LIMIT 20", [$site['id']]);

function st_badge($st, $date) {
    if ($st === 'confirmed') return "<span style='background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-size:0.8rem;'>確定: $date</span>";
    return "<span style='background:#fef9c3; color:#854d0e; padding:2px 6px; border-radius:4px; font-size:0.8rem;'>依頼中</span>";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文履歴</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header"><h1>注文履歴</h1><a href="index.php?token=<?= $token ?>">&times; 閉じる</a></div>
    <?php require_once 'inc.header.php'; ?>
<div class="container">
        <?php foreach($orders as $o): ?>
        <div class="card" style="border-left: 5px solid <?= $o['is_urgent'] ? '#ef4444' : '#ccc' ?>;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-weight:bold;">#<?= $o['id'] ?> <?= $o['is_urgent'] ? '<span style="color:#ef4444;"><i class="fas fa-fire"></i> 最短</span>' : '' ?></span>
                <?= st_badge($o['status'], $o['confirmed_date']) ?>
            </div>
            <div style="font-size:0.9rem;">希望: <?= $o['requested_date'] ?> / <?= h($o['v_name']) ?></div>
            <div style="background:#f8fafc; padding:10px; border-radius:4px; margin-top:8px; font-size:0.85rem;">
                <?php $items = fetchAll("SELECT oi.*, p.name, p.unit FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?", [$o['id']]);
                foreach($items as $i) echo "<div>・" . h($i['name']) . ": " . (float)$i['quantity'] . h($i['unit']) . "</div>"; ?>
                <?php if($o['memo']): ?><div style="border-top:1px dashed #ccc; margin-top:8px; padding-top:8px; color:#64748b; white-space:pre-wrap;"><?= h($o['memo']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>