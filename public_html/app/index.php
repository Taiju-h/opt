<?php
// app/index.php
session_start();
require_once __DIR__ . '/../inc.db.php';
require_once __DIR__ . '/../inc.mail.php';

function load_mail_template($filepath) {
    if (!file_exists($filepath)) return ['件名エラー', "テンプレートファイルが見つかりません:\n" . basename($filepath)];
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $subject = trim(array_shift($lines));
    $body = trim(implode("\n", $lines));
    return [$subject, $body];
}

function write_app_log($action, $site_name, $details, $status = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[Daisho_Order] {$timestamp} | {$status} | {$action} | Site:{$site_name} | {$details}");
}

function clean_email($email) {
    $email = mb_convert_kana($email, "rnas", "UTF-8"); 
    return strtolower(trim(str_replace([' ', '　'], '', $email)));
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
if (empty($token)) { write_app_log('Access', 'Unknown', 'Token Missing', 'ERROR'); die("Token Missing"); }

$site = fetchOne("
    SELECT s.*, a.email as internal_email, a.name as internal_name 
    FROM sites s 
    LEFT JOIN admins a ON s.internal_staff_id = a.id 
    WHERE s.access_token = ? AND s.is_active = 1
", [$token]);

if (!$site) { write_app_log('Access', 'Unknown', "Invalid Token: {$token}", 'ERROR'); die("Invalid Site"); }

$configs = $pdo->query("SELECT config_key, config_value FROM system_configs")->fetchAll(PDO::FETCH_KEY_PAIR);
$holidays = $pdo->query("SELECT date FROM public_holidays")->fetchAll(PDO::FETCH_COLUMN);

$default_vendor_email = $configs['vendor_email'] ?? '';    
$bcc_string   = $configs['global_bcc_list'] ?? ''; 

$sys_lead_days = (int)($configs['lead_time_days'] ?? 4); 
$site_lead_days = $site['lead_time_days'] !== null ? (int)$site['lead_time_days'] : $sys_lead_days;

$products = fetchAll("SELECT p.name, p.unit, p.series_name, p.vendor_email, p.vendor_name, p.lead_time_days as master_lead, sp.* FROM site_products sp JOIN products p ON sp.product_id = p.id WHERE sp.site_id = ? AND sp.is_active = 1 ORDER BY p.sort_order", [$site['id']]);
$vehicles = fetchAll("SELECT v.* FROM vehicle_masters v JOIN site_allowed_vehicles sav ON v.id = sav.vehicle_id WHERE sav.site_id = ? AND v.is_active = 1 ORDER BY v.sort_order", [$site['id']]);
$ng_times = fetchAll("SELECT start_time, end_time FROM site_ng_times WHERE site_id = ? ORDER BY start_time", [$site['id']]);

$pkgs_raw = fetchAll("SELECT * FROM product_packages ORDER BY product_id, sort_order ASC");
$packages = [];
foreach($pkgs_raw as $pkg) { $packages[$pkg['product_id']][] = $pkg; }

$current_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?token=" . $token;

$date_map = [];
$std_date_obj = new DateTime(); 
$date_map[0] = $std_date_obj->format('Y-m-d');
for ($i = 1; $i <= 60; $i++) {
    while(true) {
        $std_date_obj->modify('+1 day');
        $w = (int)$std_date_obj->format('w');
        $ymd = $std_date_obj->format('Y-m-d');
        if ($w !== 0 && $w !== 6 && !in_array($ymd, $holidays)) { $date_map[$i] = $ymd; break; }
    }
}
$standard_lead_date = $date_map[$site_lead_days] ?? $date_map[60];
$absolute_min_date = date('Y-m-d', strtotime('+1 day'));

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'order') {
    
    $order_batches = [];
    $all_items_db = []; 
    $log_items_str = ""; 

    foreach ($products as $p) {
        $pid = $p['product_id'];
        $total_kg = 0;
        $pkg_str_arr = [];

        if (isset($_POST['pkg_qty'][$pid]) && is_array($_POST['pkg_qty'][$pid])) {
            foreach ($_POST['pkg_qty'][$pid] as $pkg_id => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    $cap = (float)$_POST['pkg_cap'][$pid][$pkg_id];
                    $p_name = $_POST['pkg_name'][$pid][$pkg_id];
                    $total_kg += ($cap * $qty);
                    $pkg_str_arr[] = "{$p_name}×{$qty}";
                }
            }
        }
        $custom_kg = (float)($_POST['custom_kg'][$pid] ?? 0);
        if ($custom_kg > 0) {
            $total_kg += $custom_kg;
            $pkg_str_arr[] = "バラ指定({$custom_kg}kg)";
        }

        if ($total_kg > 0) {
            $qty_ton = $total_kg / 1000;
            $target_email = !empty($p['vendor_email']) ? $p['vendor_email'] : $default_vendor_email;
            $target_name  = !empty($p['vendor_name'])  ? $p['vendor_name']  : 'お取引先';

            if (empty($target_email)) { $target_email = 'UNKNOWN'; $target_name = '宛先未設定'; }

            $pkg_details = implode(", ", $pkg_str_arr);

            $order_batches[$target_email]['name'] = $target_name;
            $order_batches[$target_email]['items'][] = [
                'name' => $p['name'],
                'series' => $p['series_name'],
                'kg' => $total_kg,
                'ton' => $qty_ton,
                'pkg_details' => $pkg_details
            ];

            $all_items_db[] = ['id' => $pid, 'qty' => $qty_ton, 'pkg_details' => $pkg_details];
            $log_items_str .= "[{$p['name']}:{$total_kg}kg] ";
        }
    }

    // ★大改修：日付が空なら強制的に最短手配フラグを立て、時間は「指定なし」にする
    $is_urgent = (isset($_POST['urgent_order']) && $_POST['urgent_order'] == '1') ? 1 : 0;
    if (empty($_POST['date_1'])) {
        $is_urgent = 1;
    }
    
    $req_date = $is_urgent ? date('Y-m-d', strtotime('+1 day')) : $_POST['date_1'];
    $req_time = $is_urgent ? '指定なし' : ($_POST['time_1'] ?? '指定なし');
    
    $log_base = "Req:{$req_date}({$req_time}), Items:{$log_items_str}";

    if (!empty($all_items_db)) {
        write_app_log('Order Attempt', $site['name'], $log_base, 'START');

        try {
            $pdo->beginTransaction();
            
            $ng_memo = "";
            if (!empty($_POST['ng_ignored'])) {
                $ng_str = implode(", ", $_POST['ng_ignored']);
                $ng_memo = "\n【特例受入】NG時間解除: {$ng_str}";
            }
            $memo_text = ($_POST['memo'] ?? '');
            $urgent_flag = ($is_urgent ? "【🔥最短手配希望】\n" : "");
            $db_memo = $urgent_flag . $memo_text . $ng_memo;
            $vehicle_memo = trim($_POST['vehicle_memo'] ?? '');
            $maker_token = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("INSERT INTO orders (site_id, vehicle_id, vehicle_memo, status, is_urgent, requested_date, requested_time, maker_token, memo, created_at) VALUES (?, ?, ?, 'requested', ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$site['id'], $_POST['vehicle'], $vehicle_memo, $is_urgent, $req_date, $req_time, $maker_token, $db_memo]);
            $oid = $pdo->lastInsertId();

            $stmt_i = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, package_details) VALUES (?, ?, ?, ?)");
            foreach ($all_items_db as $it) {
                $stmt_i->execute([$oid, $it['id'], $it['qty'], $it['pkg_details']]);
            }
            $pdo->commit();

            // --- メール送信処理 ---
            list($tpl_vendor_subject, $tpl_vendor_body) = load_mail_template(__DIR__ . '/mail/order_comp_maker.txt');
            list($tpl_internal_subject, $tpl_internal_body) = load_mail_template(__DIR__ . '/mail/order_comp_internal.txt');

            $display_memo = "";
            if ($vehicle_memo) $display_memo .= "【車両特記事項】\n{$vehicle_memo}\n\n";
            $display_memo .= "【現場からの備考】\n" . ($memo_text ?: "なし") . $ng_memo;

            $common_tags = [
                '{{ORDER_ID}}'     => $oid,
                '{{SITE_NAME}}'    => $site['name'],
                '{{MANAGER_NAME}}' => $site['manager_name'] ?: 'ご担当者',
                '{{REQ_DATE}}'     => $req_date,
                '{{REQ_TIME}}'     => $req_time,
                '{{MEMO}}'         => $display_memo,
                '{{REPLY_URL}}'    => "https://order.optjet.com/maker/reply.php?t=" . $maker_token
            ];

            $sent_log = []; 

            // --- 1. メーカー向け送信 ---
            foreach ($order_batches as $target_email => $batch) {
                if ($target_email === 'UNKNOWN') continue; 

                $items_text = "";
                foreach ($batch['items'] as $item) {
                    $items_text .= "　・{$item['name']} : " . number_format($item['kg']) . " kg (" . (float)$item['ton'] . "t)\n";
                    if ($item['pkg_details']) $items_text .= "　　└ 内訳: {$item['pkg_details']}\n";
                }
                
                $tags = $common_tags;
                $tags['{{VENDOR_NAME}}'] = $batch['name'];
                $tags['{{ITEMS}}']       = $items_text;

                $subject = str_replace(array_keys($tags), array_values($tags), $tpl_vendor_subject);
                $body    = str_replace(array_keys($tags), array_values($tags), $tpl_vendor_body);

                if (send_one_mail(clean_email($target_email), $subject, $body)) {
                    $sent_log[] = "{$batch['name']} ({$target_email})";
                }
            }

            // --- 2. 社内・現場向け控えメール ---
            $all_items_text = "";
            foreach ($order_batches as $target_email => $batch) {
                $target_disp = ($target_email === 'UNKNOWN') ? "★宛先未設定" : $batch['name'];
                $all_items_text .= "▼ 発注先: {$target_disp}\n";
                foreach ($batch['items'] as $item) {
                    $all_items_text .= "　・{$item['name']}: " . number_format($item['kg']) . " kg\n";
                    if ($item['pkg_details']) $all_items_text .= "　　└ 内訳: {$item['pkg_details']}\n";
                }
                $all_items_text .= "\n";
            }

            $site_recipients = [];
            if ($site['manager_email']) $site_recipients[] = $site['manager_email'];
            if ($site['cc_email']) { foreach(explode(',', $site['cc_email']) as $c) $site_recipients[] = trim($c); }
            
            $admin_recipients = [];
            if (!empty($site['internal_email'])) {
                $admin_recipients[] = $site['internal_email'];
            }
            if ($bcc_string) { foreach(explode(',', $bcc_string) as $b) $admin_recipients[] = trim($b); }

            $tags_int = $common_tags;
            $tags_int['{{ITEMS_DETAIL}}'] = $all_items_text;

            $sent_history = [];

            // 現場向け
            $tags_site = $tags_int;
            $tags_site['{{ADMIN_URL_SECTION}}'] = ""; 
            $sub_site  = str_replace(array_keys($tags_site), array_values($tags_site), $tpl_internal_subject);
            $body_site = str_replace(array_keys($tags_site), array_values($tags_site), $tpl_internal_body);

            foreach ($site_recipients as $raw_addr) {
                $addr = clean_email($raw_addr);
                if (!empty($addr) && !in_array($addr, $sent_history)) {
                    send_one_mail($addr, $sub_site, $body_site);
                    $sent_history[] = $addr;
                }
            }

            // 社内向け
            $admin_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . "/admin/m.order.php";
            $internal_name = $site['internal_name'] ?: '社内担当';

            $tags_admin = $tags_int;
            $tags_admin['{{ADMIN_URL_SECTION}}'] = "\n■ 林六様 管理画面（発注状況の確認）\n" . $admin_url . "\n\n※このメールは本案件の担当者（{$internal_name}様）およびシステム管理者へ自動送信されています。";
            
            $sub_admin  = str_replace(array_keys($tags_admin), array_values($tags_admin), $tpl_internal_subject);
            $body_admin = str_replace(array_keys($tags_admin), array_values($tags_admin), $tpl_internal_body);

            foreach ($admin_recipients as $raw_addr) {
                $addr = clean_email($raw_addr);
                if (!empty($addr) && !in_array($addr, $sent_history)) {
                    send_one_mail($addr, "[社内共有] " . $sub_admin, $body_admin);
                    $sent_history[] = $addr;
                }
            }

            $sent_vendors_str = implode(", ", $sent_log);
            write_app_log('Order Success', $site['name'], "OID:{$oid}, SentTo:[{$sent_vendors_str}], {$log_base}", 'SUCCESS');

            $message = "
            <div class='success-modal-overlay'>
                <div class='success-modal'>
                    <div class='success-icon'><i class='fas fa-check'></i></div>
                    <h2>発注完了</h2>
                    <p class='order-id'>Order #{$oid}</p>
                    <p style='color:#e11d48; font-weight:bold; margin-top:10px; font-size:0.9rem;'>
                        ※納期はまだ確定していません。<br>配送日時は期待に添えない場合があります。
                    </p>
                    <a href='index.php?token={$token}' class='btn-main' style='margin-top:20px;'>続けて発注する</a>
                    <a href='history.php?token={$token}' class='btn-sub'>履歴を確認する</a>
                </div>
            </div>";
        } catch (Exception $e) { 
            if($pdo->inTransaction()) $pdo->rollBack(); 
            $err = $e->getMessage();
            write_app_log('Order Failed', $site['name'], "Error:{$err}, {$log_base}", 'ERROR');
            $message = "<script>alert('Error: ".addslashes($err)."');</script>"; 
        }
    } else {
        write_app_log('Order Empty', $site['name'], "No items selected", 'WARNING');
    }
}

function renderTimeOptions() {
    echo '<option value="指定なし">時間指定なし</option><option value="午前">午前</option><option value="午後">午後</option>';
    for ($i = 8; $i <= 17; $i++) { 
        $t1 = sprintf('%02d:00', $i); 
        echo "<option value=\"$t1\">$t1</option>"; 
        if ($i != 17) {
            $t2 = sprintf('%02d:30', $i); 
            echo "<option value=\"$t2\">$t2</option>"; 
        }
    }
}

$stat = ['total_qty' => 0];
try {
    $stmt_stat = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity), 0) as total_qty FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.site_id = ? AND o.status != 'cancelled'");
    $stmt_stat->execute([$site['id']]);
    $res = $stmt_stat->fetch();
    if ($res) { $stat['total_qty'] = $res['total_qty']; }
} catch (Exception $e) {}

require_once __DIR__ . '/view.php';