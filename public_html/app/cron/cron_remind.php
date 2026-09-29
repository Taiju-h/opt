<?php
// /var/www/order.optjet.com/public_html/app/cron/cron_remind.php
// 毎日実行されるリマインダープログラム

// 2つ上の階層にあるDB・メール設定を読み込む
require_once __DIR__ . '/../../inc.db.php';
require_once __DIR__ . '/../../inc.mail.php';

// テキスト読み込み関数
function load_mail_template($filepath) {
    if (!file_exists($filepath)) return ['件名エラー', "テンプレートが見つかりません:\n" . basename($filepath)];
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $subject = trim(array_shift($lines));
    $body = trim(implode("\n", $lines));
    return [$subject, $body];
}

// アドレス洗浄
function clean_email($email) {
    $email = mb_convert_kana($email, "rnas", "UTF-8"); 
    return strtolower(trim(str_replace([' ', '　'], '', $email)));
}

// 実行ログを記録する関数
function write_cron_log($msg) {
    $timestamp = date('Y-m-d H:i:s');
    // エラーログだけでなく、Cron実行時の出力としても返す
    error_log("[CRON_REMIND] {$timestamp} | {$msg}");
    echo "[{$timestamp}] {$msg}\n";
}

write_cron_log("START: リマインダー処理を開始します。");

// BCCリスト（林六の管理者）を取得
$configs = $pdo->query("SELECT config_key, config_value FROM system_configs")->fetchAll(PDO::FETCH_KEY_PAIR);
$bcc_string = $configs['global_bcc_list'] ?? '';
$bcc_list = array_filter(array_map('trim', explode(',', $bcc_string)));

if (empty($bcc_list)) {
    write_cron_log("ERROR: 管理者の送信先(BCC)が設定されていません。終了します。");
    exit;
}

// 回答待ち(requested)のオーダーを取得
$unanswered = fetchAll("
    SELECT o.*, s.name as site_name 
    FROM orders o 
    JOIN sites s ON o.site_id = s.id 
    WHERE o.status = 'requested'
    ORDER BY o.requested_date ASC
");

if (count($unanswered) > 0) {
    // リストの生成
    $list_text = "";
    foreach ($unanswered as $o) {
        $urgent_mark = $o['is_urgent'] ? " 【🔥最短手配】" : "";
        $list_text .= "■ 注文 #{$o['id']} ({$o['site_name']})\n";
        $list_text .= "　発注日時: {$o['created_at']}\n";
        $list_text .= "　希望納期: {$o['requested_date']}{$urgent_mark}\n\n";
    }
    
    // テンプレート読み込み (同じapp内のmailフォルダを指定)
    list($tpl_sub, $tpl_body) = load_mail_template(__DIR__ . '/../mail/cron_remind_notice.txt');
    
    // 置換
    $subject = $tpl_sub;
    $body = str_replace('{{UNANSWERED_LIST}}', trim($list_text), $tpl_body);
    
    // 送信
    $sent_count = 0;
    foreach ($bcc_list as $raw_to) {
        $to = clean_email($raw_to);
        if($to) {
            send_one_mail($to, $subject, $body);
            $sent_count++;
        }
    }
    write_cron_log("SUCCESS: 未回答 " . count($unanswered) . " 件。管理者 {$sent_count} 名にメールを送信しました。");
} else {
    write_cron_log("INFO: 未回答のオーダーはありませんでした。");
}