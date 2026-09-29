<?php
$adminSecret = 'your-admin-secret-for-user-registration';
$password = 'Pass001';

// --- STEP 1: もし ID が判明していれば削除 ---
// taiju という名前で作成した際の ID（おそらく 1 または最新）を消去します。
// IDが不明な場合は、一旦 1〜20 くらいを掃除しても良いですが、
// まずは taijuh を新規登録してみて、エラーが出るなら削除を試しましょう。

$data = [
    "username" => "taijuh", // taijuh に変更
    "password" => $password,
    "email"    => "taiju.h@gmail.com" // 正しいアドレスに変更
];

$ch = curl_init("https://scan.bigmagic.net/api/api/v1/auth/register");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-admin-secret: ' . $adminSecret
]);

$res = curl_exec($ch);
echo "Registering taijuh... Result: " . $res;
curl_close($ch);
?>