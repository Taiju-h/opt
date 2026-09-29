<?php
/**
 * Title: 🚚 発注・配送状況管理
 * Icon: fas fa-shipping-fast
 * Color: blue
 * Desc: 現場からの発注・メーカーの納期回答をリアルタイム管理。
 * Sort: 200
 */
require_once __DIR__ . '/../inc.db.php';
require_once __DIR__ . '/../inc.mail.php';

if (!is_admin_logged_in()) { header("Location: login.php"); exit; }

// ===============================================
// 🔍 検索パラメータの取得とクレンジング
// ===============================================
$raw_keyword    = $_GET['s_keyword'] ?? '';
$clean_keyword  = trim(str_replace(['　', ' '], '', $raw_keyword)); // スペース除去
$s_client_id    = $_GET['s_client_id']    ?? ''; // ★追加: 客先担当者ID
$s_staff_id     = $_GET['s_staff_id']     ?? ''; // 林六側の担当者ID
$s_status       = $_GET['s_status']       ?? '';
$s_product_id   = $_GET['s_product_id']   ?? '';
$sort           = $_GET['sort']           ?? 'id';
$order          = $_GET['order']          ?? 'DESC';

$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';

// ===============================================
// 💾 アクション処理 (削除 / 承認)
// ===============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo "<script>alert('注文 #{$id} を削除しました。'); location.href='m.order.php';</script>";
        } catch (Exception $e) { $pdo->rollBack(); }
    }
    
    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();
            $order_data = fetchOne("SELECT o.*, s.name as site_name, s.manager_name, s.manager_email, s.cc_email FROM orders o JOIN sites s ON o.site_id = s.id WHERE o.id = ?", [$id]);
            $items = fetchAll("SELECT oi.*, p.name as p_name, p.unit FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?", [$id]);
            
            if ($order_data && $order_data['status'] === 'adjusted') {
                $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?")->execute([$id]);
                
                $items_text = "";
                foreach($items as $it) { 
                    $items_text .= "・" . $it['p_name'] . " : " . (float)$it['quantity'] . " " . $it['unit'] . "\n"; 
                    if(!empty($it['package_details'])) $items_text .= "　└ 内訳: " . $it['package_details'] . "\n";
                }
                $tags = ['{{ORDER_ID}}'=>$order_data['id'], '{{SITE_NAME}}'=>$order_data['site_name'], '{{CONFIRMED_DATE}}'=>$order_data['confirmed_date'], '{{CONFIRMED_TIME}}'=>($order_data['confirmed_time'] === '指定なし' ? '' : $order_data['confirmed_time']), '{{REPLY_MEMO}}'=>($order_data['maker_reply_memo'] ?: "特になし"), '{{ITEMS}}'=>$items_text];
                
                $tpl_file = __DIR__ . '/../app/mail/order_confirmed_notice.txt';
                if(file_exists($tpl_file)){
                    $lines = file($tpl_file, FILE_IGNORE_NEW_LINES);
                    $sub_tpl = array_shift($lines);
                    $body_tpl = implode("\n", $lines);
                    $subject = str_replace(array_keys($tags), array_values($tags), $sub_tpl);
                    $body    = str_replace(array_keys($tags), array_values($tags), $body_tpl);
                    $recipients = array_filter(array_unique(array_merge([$order_data['manager_email']], explode(',', $order_data['cc_email']))));
                    foreach ($recipients as $raw_recp) {
                        $recp = mb_convert_kana(trim($raw_recp), "rnas", "UTF-8");
                        if (!empty($recp)) send_one_mail($recp, $subject, $body);
                    }
                }
                $pdo->commit();
                echo "<script>alert('現場へ確定通知を送信しました！'); location.href='m.order.php';</script>";
            } else { $pdo->rollBack(); }
        } catch (Exception $e) { $pdo->rollBack(); }
    }
}

// ===============================================
// 📊 動的SQLの構築
// ===============================================
$where_clauses = ["1=1"];
$params = [];

// 1. キーワード検索 (現場名 OR 会社名 OR 客先担当名 OR 弊社担当名)
if ($clean_keyword !== '') {
    $where_clauses[] = "(
        REPLACE(REPLACE(s.name, ' ', ''), '　', '') LIKE ? 
        OR REPLACE(REPLACE(client_co.name, ' ', ''), '　', '') LIKE ?
        OR REPLACE(REPLACE(client_st.name, ' ', ''), '　', '') LIKE ?
        OR REPLACE(REPLACE(internal_st.name, ' ', ''), '　', '') LIKE ?
    )";
    $val = "%{$clean_keyword}%";
    array_push($params, $val, $val, $val, $val);
}

// ★追加：2. 客先担当プルダウン (m.client登録者)
if ($s_client_id !== '') {
    $where_clauses[] = "s.client_person_id = ?";
    $params[] = $s_client_id;
}

// 3. 弊社担当プルダウン (admins)
if ($s_staff_id !== '') {
    $where_clauses[] = "s.internal_staff_id = ?";
    $params[] = $s_staff_id;
}

// 4. 状況フィルタ
if ($s_status !== '') {
    $where_clauses[] = "o.status = ?";
    $params[] = $s_status;
}

// 5. 商品フィルタ
if ($s_product_id !== '') {
    $where_clauses[] = "o.id IN (SELECT order_id FROM order_items WHERE product_id = ?)";
    $params[] = $s_product_id;
}

$where_sql = implode(" AND ", $where_clauses);
$allowed_sort = ['id' => 'o.id', 'site' => 's.name', 'req_date' => 'o.requested_date', 'status' => 'o.status', 'order_date' => 'o.created_at'];
$sort_col = $allowed_sort[$sort] ?? 'o.id';

// SQL: 客先はstaffs/companies、弊社はadminsから取得
$sql = "
    SELECT o.*, 
           s.name as site_name, 
           client_co.name as client_company_name,
           client_st.name as customer_name,
           internal_st.name as internal_staff_name,
           v.name as v_name,
           (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_qty
    FROM orders o 
    JOIN sites s ON o.site_id = s.id 
    LEFT JOIN staffs client_st ON s.client_person_id = client_st.id
    LEFT JOIN companies client_co ON client_st.company_id = client_co.id
    LEFT JOIN admins internal_st ON s.internal_staff_id = internal_st.id
    LEFT JOIN vehicle_masters v ON o.vehicle_id = v.id
    WHERE {$where_sql}
    ORDER BY {$sort_col} {$order}
    LIMIT 200
";

$orders = fetchAll($sql, $params);

// ===============================================
// 🎯 フィルタ用マスタデータの取得
// ===============================================

// ★追加：客先(m.client)のリスト
$filter_clients = $pdo->query("
    SELECT st.id, st.name, c.name as company_name 
    FROM staffs st 
    JOIN companies c ON st.company_id = c.id 
    WHERE st.is_active=1 
    ORDER BY c.id, st.id
")->fetchAll();

// 弊社担当(admins)のリスト
$filter_staffs = $pdo->query("SELECT id, name FROM admins ORDER BY name")->fetchAll();

function sort_url($col, $current_sort, $current_order) {
    $params = $_GET;
    $params['sort'] = $col;
    $params['order'] = ($col === $current_sort && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($params);
}
function sort_icon($col, $current_sort, $current_order) {
    if ($col !== $current_sort) return '<i class="fas fa-sort" style="color:#cbd5e1; margin-left:5px;"></i>';
    return ($current_order === 'ASC') ? '<i class="fas fa-sort-up" style="margin-left:5px;"></i>' : '<i class="fas fa-sort-down" style="margin-left:5px;"></i>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>発注状況一覧 | 林六システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; background: #f4f7f9; padding: 20px; color: #334155; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-bar { background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .search-group { display: flex; flex-direction: column; gap: 5px; }
        .search-group label { font-size: 0.75rem; font-weight: bold; color: #64748b; }
        .search-bar select, .search-bar input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; min-width: 200px; }
        .btn-search { background: #1e293b; color: white; border: none; padding: 9px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-clear { background: #94a3b8; color: white; text-decoration: none; padding: 9px 15px; border-radius: 6px; font-size: 0.85rem; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 0.8rem; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        th a { text-decoration: none; color: inherit; display: flex; align-items: center; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 0.85rem; }
        .urgent-row { background: #fff5f5; }
        .badge-urgent { background: #e11d48; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; display: inline-block; margin-top: 4px; }
        .item-list { font-size: 0.8rem; color: #334155; margin-top: 8px; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .reply-memo { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 8px; border-radius: 6px; font-size: 0.75rem; margin-top: 8px; line-height: 1.4; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.75rem; font-weight: bold; cursor: pointer; border: none; transition: 0.2s; display: inline-block; text-align: center; }
        .btn-edit { background: #2563eb; color: white; }
        .btn-approve { background: #10b981; color: white; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; padding: 8px 12px; }
    </style>
</head>
<body>
<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1><i class="fas fa-shipping-fast"></i> 発注・配送状況 一覧</h1>

    <form method="GET" class="search-bar">
        <div class="search-group" style="flex: 1.5; min-width: 250px;">
            <label>キーワード（現場・会社・担当）</label>
            <input type="text" name="s_keyword" value="<?= htmlspecialchars($raw_keyword) ?>" placeholder="例: ライト工業, 田中, 虎ノ門...">
        </div>

        <div class="search-group">
            <label>客先で絞り込み</label>
            <select name="s_client_id">
                <option value="">すべて</option>
                <?php foreach($filter_clients as $fc): ?>
                    <option value="<?= $fc['id'] ?>" <?= $s_client_id == $fc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fc['company_name'] . ' - ' . $fc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-group">
            <label>弊社担当で絞り込み</label>
            <select name="s_staff_id">
                <option value="">すべて</option>
                <?php foreach($filter_staffs as $fs): ?>
                    <option value="<?= $fs['id'] ?>" <?= $s_staff_id == $fs['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fs['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-group">
            <label>状況</label>
            <select name="s_status" style="min-width: 150px;">
                <option value="">すべて</option>
                <option value="requested" <?= $s_status === 'requested' ? 'selected' : '' ?>>⏳ 回答待ち</option>
                <option value="adjusted" <?= $s_status === 'adjusted' ? 'selected' : '' ?>>📞 要調整</option>
                <option value="confirmed" <?= $s_status === 'confirmed' ? 'selected' : '' ?>>✅ 確定済み</option>
            </select>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn-search">🔍 検索</button>
            <a href="m.order.php" class="btn-clear">クリア</a>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width:60px;"><a href="<?= sort_url('id', $sort, $order) ?>">ID <?= sort_icon('id', $sort, $order) ?></a></th>
                <th><a href="<?= sort_url('site', $sort, $order) ?>">現場・お客様情報 <?= sort_icon('site', $sort, $order) ?></a></th>
                <th><a href="<?= sort_url('req_date', $sort, $order) ?>">希望 / 確定日時 <?= sort_icon('req_date', $sort, $order) ?></a></th>
                <th>数量・車両</th>
                <th><a href="<?= sort_url('status', $sort, $order) ?>">状況 <?= sort_icon('status', $sort, $order) ?></a></th>
                <th style="width:120px; background: #eef2ff;">弊社担当</th>
                <th style="width:80px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($orders as $o): 
                $items = fetchAll("SELECT p.name, oi.quantity, oi.package_details, p.unit FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?", [$o['id']]); 
            ?>
            <tr class="<?= $o['is_urgent'] ? 'urgent-row' : '' ?>">
                <td>
                    #<?= $o['id'] ?><br>
                    <?php if($o['is_urgent']): ?><span class="badge-urgent">最短手配</span><?php endif; ?>
                </td>
                <td>
                    <b style="font-size:1.05rem; color:#1e293b;"><?= htmlspecialchars($o['site_name']) ?></b>
                    
                    <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">
                        <i class="far fa-building"></i> <?= htmlspecialchars($o['client_company_name'] ?: '会社名未設定') ?> - <?= htmlspecialchars($o['customer_name'] ?: '担当未設定') ?> 様
                    </div>
                    
                    <?php if(!empty($o['memo'])): ?><div style="font-size:0.75rem; color:#64748b; margin-top:6px; font-style:italic;">📝 <?= nl2br(htmlspecialchars($o['memo'])) ?></div><?php endif; ?>
                    
                    <div class="item-list">
                        <?php foreach($items as $it): ?>
                            <div style="font-weight:bold; color:#2563eb;">・<?= htmlspecialchars($it['name']) ?> : <?= (float)$it['quantity'] ?><?= htmlspecialchars($it['unit']) ?></div>
                            <?php if(!empty($it['package_details'])): ?>
                                <div style="font-size:0.7rem; color:#64748b; margin-left:12px;">└ <?= htmlspecialchars($it['package_details']) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td>
                    <div style="font-size:0.75rem; color:#94a3b8; margin-bottom:4px;">注: <?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></div>
                    <div style="font-size:0.85rem; color:#64748b;">希: <?= $o['requested_date'] ?> <?= htmlspecialchars($o['requested_time'] ?? '') ?></div>
                    <div style="font-weight:bold; color:#166534; font-size:0.95rem; margin-top:4px;">確: <?= $o['confirmed_date'] ?: '---' ?> <?= ($o['confirmed_time'] && $o['confirmed_time'] !== '指定なし') ? htmlspecialchars($o['confirmed_time']) : '' ?></div>
                    <?php if(!empty($o['maker_reply_memo'])): ?>
                        <div class="reply-memo"><i class="fas fa-comment-alt"></i> <b>メーカー回答:</b><br><?= nl2br(htmlspecialchars($o['maker_reply_memo'])) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:bold; font-size:1rem; color:#2563eb;"><?= number_format($o['total_qty'], 1) ?>t</div>
                    <div style="font-size:0.75rem; color:#64748b;"><i class="fas fa-truck"></i> <?= htmlspecialchars($o['v_name'] ?: '指定なし') ?></div>
                    <?php if(!empty($o['vehicle_memo'])): ?><div style="font-size:0.7rem; color:#e11d48; font-weight:bold; margin-top:4px; background:#ffe4e6; padding:2px 4px; border-radius:3px;">⚠️ <?= htmlspecialchars($o['vehicle_memo']) ?></div><?php endif; ?>
                </td>
                <td>
                    <?php if($o['status']==='confirmed'): ?>
                        <span style="color:#10b981; font-weight:bold;"><i class="fas fa-check"></i> 確定</span>
                    <?php elseif($o['status']==='adjusted'): ?>
                        <span style="color:#e11d48; font-weight:bold; background:#ffe4e6; padding:4px 8px; border-radius:4px; display:inline-block; text-align:center;"><i class="fas fa-phone-alt"></i> 要調整<br><small style="font-size:0.65rem;">(確認待)</small></span>
                    <?php elseif($o['status']==='cancelled'): ?>
                        <span style="color:#94a3b8;">❌ 取消</span>
                    <?php else: ?>
                        <span style="color:#f59e0b; font-weight:bold;">⏳ 回答待</span>
                    <?php endif; ?>
                </td>
                
                <td style="font-weight:bold; color:#2563eb; vertical-align: middle;">
                    <?= htmlspecialchars($o['internal_staff_name'] ?: '未設定') ?>
                </td>
                
                <td style="vertical-align: middle;">
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <a href="order_edit.php?id=<?= $o['id'] ?>" class="btn btn-edit">修正</a>
                        <?php if($o['status']==='adjusted'): ?>
                            <form method="POST" onsubmit="return confirm('確定通知メールを送信しますか？');" style="margin:0;">
                                <input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button type="submit" class="btn btn-approve"><i class="fas fa-paper-plane"></i> 確定</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once 'inc.footer.php'; ?>
</body>
</html>