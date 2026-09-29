<?php
require_once __DIR__ . '/../inc.db.php';
require_once __DIR__ . '/../inc.mail.php';

// テキスト読み込み関数
function load_mail_template($filepath) {
    if (!file_exists($filepath)) return ['件名エラー', "テンプレートが見つかりません:\n" . basename($filepath)];
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $subject = trim(array_shift($lines));
    $body = trim(implode("\n", $lines));
    return [$subject, $body];
}

function clean_email($email) {
    $email = mb_convert_kana($email, "rnas", "UTF-8"); 
    return strtolower(trim(str_replace([' ', '　'], '', $email)));
}

$token = $_GET['t'] ?? '';
if (!$token) die("Access Token Missing");

$order = fetchOne("SELECT o.*, s.name as site_name, s.manager_name, s.manager_email, s.cc_email FROM orders o JOIN sites s ON o.site_id = s.id WHERE o.maker_token = ?", [$token]);
if (!$order) die("注文データが見つかりません。");

$configs = $pdo->query("SELECT config_key, config_value FROM system_configs")->fetchAll(PDO::FETCH_KEY_PAIR);
$bcc_string = $configs['global_bcc_list'] ?? '';
$bcc_list = array_filter(array_map('trim', explode(',', $bcc_string)));

$items = fetchAll("SELECT oi.*, p.name as p_name, p.series_name, p.unit FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?", [$order['id']]);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'confirm') {
    $conf_date = $_POST['confirmed_date'] ?? '';
    $conf_time = $_POST['confirmed_time'] ?? '指定なし';
    $reply_memo = $_POST['maker_reply_memo'] ?? '';
    
    if($conf_date) {
        try {
            $pdo->beginTransaction();
            
            // ★判定：アラート（林六のみ）か、確定（現場へ）か
            $needs_adjustment = ($order['is_urgent'] == 1 || $conf_date !== $order['requested_date'] || !empty($reply_memo));
            
            // ★重要：要調整なら「adjusted(保留)」、問題なければ「confirmed(確定)」
            $new_status = $needs_adjustment ? 'adjusted' : 'confirmed';

            $pdo->prepare("UPDATE orders SET confirmed_date = ?, confirmed_time = ?, maker_reply_memo = ?, status = ? WHERE id = ?")
                ->execute([$conf_date, $conf_time, $reply_memo, $new_status, $order['id']]);
            $pdo->commit();

            $time_display = ($conf_time === '指定なし') ? '' : $conf_time;
            $items_text = "";
            foreach($items as $it) { $items_text .= "・" . $it['p_name'] . " : " . (float)$it['quantity'] . " " . $it['unit'] . "\n"; }

            $tags = [
                '{{ORDER_ID}}'       => $order['id'],
                '{{SITE_NAME}}'      => $order['site_name'],
                '{{REQ_DATE}}'       => $order['requested_date'],
                '{{URGENT_MARK}}'    => ($order['is_urgent'] ? "(最短希望)" : ""),
                '{{CONFIRMED_DATE}}' => $conf_date,
                '{{CONFIRMED_TIME}}' => $time_display,
                '{{REPLY_MEMO}}'     => ($reply_memo ?: "特になし"),
                '{{ITEMS}}'          => $items_text
            ];

            $sent_history = [];

            if ($needs_adjustment) {
                // 【ルートA：要調整】林六（BCC）のみに送信！
                list($tpl_sub, $tpl_body) = load_mail_template(__DIR__ . '/../app/mail/order_alert_notice.txt');
                $subject = str_replace(array_keys($tags), array_values($tags), $tpl_sub);
                $body    = str_replace(array_keys($tags), array_values($tags), $tpl_body);

                foreach ($bcc_list as $raw_bcc) {
                    $bcc = clean_email($raw_bcc);
                    if (!empty($bcc) && !in_array($bcc, $sent_history)) {
                        send_one_mail($bcc, $subject, $body);
                        $sent_history[] = $bcc;
                    }
                }
                $display_msg = "回答を送信しました。（内容確認のため、現場への通知は管理者の確認後となります）";

            } else {
                // 【ルートB：確定】現場監督 ＋ CC ＋ 林六（監視）の全員に送信！
                list($tpl_sub, $tpl_body) = load_mail_template(__DIR__ . '/../app/mail/order_confirmed_notice.txt');
                $subject = str_replace(array_keys($tags), array_values($tags), $tpl_sub);
                $body    = str_replace(array_keys($tags), array_values($tags), $tpl_body);

                // 現場(監督+CC)へ
                $internal_recipients = [];
                if ($order['manager_email']) $internal_recipients[] = $order['manager_email'];
                if ($order['cc_email']) { foreach(explode(',', $order['cc_email']) as $c) $internal_recipients[] = trim($c); }
                
                foreach ($internal_recipients as $raw_recp) {
                    $recp = clean_email($raw_recp);
                    if (!empty($recp) && !in_array($recp, $sent_history)) {
                        send_one_mail($recp, $subject, $body);
                        $sent_history[] = $recp;
                    }
                }
                // 林六(監視用)へ
                foreach ($bcc_list as $raw_bcc) {
                    $bcc = clean_email($raw_bcc);
                    if (!empty($bcc) && !in_array($bcc, $sent_history)) {
                        send_one_mail($bcc, "[監視用] " . $subject, $body);
                        $sent_history[] = $bcc;
                    }
                }
                $display_msg = "納期回答を送信しました。現場担当者へ自動で通知されました。";
            }

            $message = "
            <div class='success-overlay'>
                <div class='success-modal'>
                    <i class='fas fa-check-circle'></i>
                    <h2>回答完了</h2>
                    <p>配送日: <b>{$conf_date} {$time_display}</b></p>
                    <p style='font-size:0.9rem; color:#64748b; margin-top:10px;'>{$display_msg}</p>
                </div>
            </div>";
            
            $order['status'] = $new_status; // 画面表示用
            $order['confirmed_date'] = $conf_date;
            $order['confirmed_time'] = $conf_time;
            $order['maker_reply_memo'] = $reply_memo;

        } catch (Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>納期回答 | 大翔化学研究所</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../app/css/style.css">
    <link rel="stylesheet" href="./css/style.css">
    <style>
        body { background-color: #f1f5f9; color: #1e293b; }
        .maker-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .item-container { border-bottom: 2px solid #f1f5f9; padding: 20px 0; }
        .item-container:last-of-type { border: none; }
        .product-series { font-size: 0.85rem; color: #64748b; font-weight: bold; margin-bottom: 4px; }
        .product-name { font-size: 1.4rem; font-weight: 900; color: #0f172a; line-height: 1.3; margin-bottom: 12px; }
        .qty-badge { background: none!important; color:#1d4ed8 !important; margin:auto!important; display: block; width:60%; text-align: center!important; font-size:400%!important; font-weight: 900; }
        .qty-badge small { font-size: 1rem; opacity: 0.9; font-weight: bold; }
        .order-meta { background: #f8fafc; border-radius: 12px; padding: 15px; border: 1px solid #e2e8f0; margin-top: 20px; }
        .meta-label { font-size: 0.8rem; color: #64748b; margin-bottom: 4px; font-weight: bold; }
        .meta-val { font-size: 1.1rem; font-weight: 900; color: #1e293b; }
        .input-date { width: 100%; padding: 18px; border: 3px solid #cbd5e1; border-radius: 12px; font-size: 1.4rem; font-weight: 900; color: #1e293b; transition: 0.2s; text-align: center; box-sizing: border-box; }
        .input-date:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.15); }
        .input-memo { width: 100%; padding: 15px; border: 2px solid #cbd5e1; border-radius: 10px; font-size: 1rem; font-family: inherit; resize: vertical; margin-top: 10px; box-sizing: border-box; }
        .btn-confirm { background: #2563eb; color: white; width: 100%; padding: 20px; border-radius: 12px; font-weight: 900; font-size: 1.3rem; border: none; cursor: pointer; box-shadow: 0 6px 0 #1d4ed8; margin-top: 15px; }
        .btn-confirm:active { transform: translateY(6px); box-shadow: none; }
        .success-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(6px); }
        .success-modal { background:white; padding:40px; border-radius:24px; text-align:center; width: 90%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .success-modal i { font-size: 60px; color: #10b981; margin-bottom: 20px; }
    </style>
</head>
<body>

<?= $message ?>

<div class="header" style="background: #0f172a; padding: 25px 20px;">
    <div class="site-name">大翔化学研究所 | 発注回答システム</div>
    <div class="manager-name">現場: <?= h($order['site_name']) ?></div>
</div>

<div class="container" style="max-width: 600px; padding: 20px 15px;">
    
    <div class="maker-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <span style="font-weight:bold; color:#64748b;">注文番号 #<?= $order['id'] ?></span>
            <?php if($order['is_urgent']): ?>
                <span style="background:#e11d48; color:white; padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:bold; animation: blink 1s infinite;">🔥 最短手配希望</span>
            <?php endif; ?>
        </div>

        <h2 style="margin-bottom: 20px; font-size: 1.2rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><i class="fas fa-clipboard-check"></i> 発注内容の確認</h2>
        
        <?php foreach($items as $it): ?>
        <div class="item-container">
            <div class="product-series"><?= h($it['series_name']) ?></div>
            <div class="product-name"><?= h($it['p_name']) ?></div>
            <div class="qty-badge">
                <?= (float)$it['quantity'] ?> <small><?= h($it['unit']) ?></small>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="order-meta">
            <div class="meta-label"><i class="fas fa-truck"></i> 指定車両 / 特記事項</div>
            <div class="meta-val" style="color: #2563eb; margin-bottom: 10px;">
                <?php
                    // 簡易的に車両名を取得して表示(すでにあれば)
                    $v_name = $pdo->query("SELECT name FROM vehicle_masters WHERE id = ".(int)$order['vehicle_id'])->fetchColumn();
                    echo h($v_name ?: '指定なし');
                ?>
                <?php if($order['vehicle_memo']): ?>
                    <div style="font-size: 0.9rem; color: #e11d48; font-weight: bold; margin-top: 4px;">
                        ⚠️ <?= h($order['vehicle_memo']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="meta-label"><i class="far fa-calendar-alt"></i> 現場の第一希望日</div>
            <div class="meta-val"><?= $order['requested_date'] ?></div>
            <?php if($order['memo']): ?>
                <div class="meta-label" style="margin-top:15px;"><i class="far fa-comment-dots"></i> 現場からの備考</div>
                <div style="font-size:0.95rem; white-space: pre-wrap; color:#1e293b; line-height:1.5;"><?= h($order['memo']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($order['status'] === 'requested'): ?>
    <div class="maker-card" style="border-top: 6px solid #2563eb;">
        <h3 style="margin-bottom:15px;"><i class="fas fa-truck-loading"></i> 配送回答の入力</h3>
        <form method="POST">
            <input type="hidden" name="action" value="confirm">
            
            <label style="display:block; margin-bottom:8px; font-weight:bold; color:#475569;">確定した（あるいは希望する）配送日と時間</label>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="date" name="confirmed_date" class="input-date" value="<?= $order['requested_date'] ?>" required style="flex: 2;">
                <select name="confirmed_time" class="input-date" style="flex: 1.5; font-size: 1rem;">
                    <option value="指定なし">時間指定なし</option>
                    <option value="午前">午前</option>
                    <option value="午後">午後</option>
                    <?php for($i=8; $i<=17; $i++): $t=sprintf('%02d:00', $i); ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:bold; color:#475569;">連絡事項（数量不足、分納の相談など）</label>
                <textarea name="maker_reply_memo" class="input-memo" rows="3" placeholder="例：トン数不足のため2回に分けて配送します、等"></textarea>
            </div>

            <p style="font-size:0.85rem; color:#64748b; margin:15px 0; line-height:1.4;">
                ※送信後、この内容で納期が確定し、自動的に通知されます。<br>（希望日以外や連絡事項がある場合、林六担当者の確認が挟まります）
            </p>
            <button type="submit" class="btn-confirm">回答を送信する</button>
        </form>
    </div>
    <?php else: ?>
    <div class="maker-card" style="text-align:center; border-top: 6px solid #10b981;">
        <i class="fas fa-check-circle" style="font-size:50px; color:#10b981; margin-bottom:15px;"></i>
        <h2 style="margin:0; color:#065f46;">回答済み</h2>
        <p style="color:#64748b; margin-top:10px;">配送確定日時: <b style="font-size:1.4rem; color:#1e293b;"><?= $order['confirmed_date'] ?> <?= h($order['confirmed_time'] ?? '') ?></b></p>
        <?php if($order['maker_reply_memo']): ?>
            <div style="margin-top:15px; padding:10px; background:#f0fdf4; border-radius:8px; color:#166534; font-size:0.9rem; text-align:left;">
                <b>送信したメッセージ:</b><br><?= nl2br(h($order['maker_reply_memo'])) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<div style="text-align:center; padding:30px; color:#94a3b8; font-size:0.8rem;">
    注文管理システム
</div>
</body>
</html>