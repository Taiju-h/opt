<?php
// セッションからログインユーザー名を取得。
// ※ 'admin_name' の部分は、ログイン処理（login.php）で設定しているキー名に合わせてください。
// 設定されていなければデフォルトで 'Haneda Taiju' を表示します。
$login_name = $_SESSION['admin_name'] ?? 'Haneda Taiju';
?>


<style>
  /* 根本的な原因であるbodyの余白を完全に封じ込める */
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden; /* 横スクロールの発生を物理的に防ぐ */
    width: 100%;
  }

  .custom-header {
    position: sticky;
    top: 0;
    left: 0;
    width: 100%; /* vwではなく%に。htmlのマージンが0ならこれで端まで行く */
    height: 45px; /* 高さを固定して安定させる */
    background: #333;
    color: #fff;
    padding: 0 20px;
    margin: 0 0 40px 0 !important;
    z-index: 9999;
    box-sizing: border-box;
    
    /* 突き抜け防止のコア設定 */
    display: flex;
    justify-content: space-between; /* 左右に振り分ける */
    align-items: center;
  }

  /* リンクのスタイル */
  .custom-header a {
    color: white;
    text-decoration: none;
    font-weight: bold;
    white-space: nowrap; /* 折り返し防止 */
  }

  /* ログイン名のスタイル */
  .user-info {
    font-size: 0.8em;
    opacity: 0.8;
    white-space: nowrap; /* 突き抜け・改行防止 */
  }

  /* コンテンツがヘッダーに食い込まないように */
  .container {
    margin-top: 0 !important;
  }
</style>


<header class="custom-header">
    <a href="/dashboard" style="color: white; text-decoration: none; font-weight: bold;">   <a href="/admin/" style="color: white; text-decoration: none; font-weight: bold;">
        🏠 林六 受発注管理システム - ダッシュボード
    </a>
    <span style="margin-left: auto; font-size: 0.8em;">Login: <?= htmlspecialchars($login_name, ENT_QUOTES, 'UTF-8') ?></span>
</header>