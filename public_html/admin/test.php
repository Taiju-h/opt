<?php
// test_mail.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$to = "taiju.h@gmail.com"; // ★Taijuさんのメールアドレス
$subject = "Server Mail Test";
$body = "このメールが届けば、サーバーのメール機能自体は生きています。\n送信時刻: " . date('Y-m-d H:i:s');
$headers = "From: system@order.optjet.com"; // ★サーバーのドメインに合わせる

echo "<h1>メール送信テスト</h1>";
if (mail($to, $subject, $body, $headers)) {
    echo "<h2 style='color:green;'>✅ 送信成功しました！</h2><p>迷惑メールフォルダも確認してください。</p>";
} else {
    echo "<h2 style='color:red;'>❌ 送信失敗です。</h2><p>サーバー側のメール設定（Sendmail/Postfix）が動いていません。</p>";
}