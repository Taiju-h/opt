<?php
// admin/logout.php
session_start();

// セッションを破壊（ログイン状態を解除）
$_SESSION = array();
session_destroy();

// 自動ログイン用のCookieも削除
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, "/");
}

// ログイン画面へリダイレクト
header("Location: login.php");
exit;