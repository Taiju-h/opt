<?php
// admin/index.php
// 管理画面ダッシュボード (徹底堅牢化版)

// --- デバッグ用：エラーを画面に強制表示 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 必要なファイルの読み込み
$db_path = __DIR__ . '/../inc.db.php';

if (!file_exists($db_path)) die("Error: inc.db.php not found at " . $db_path);
 
require_once $db_path;

if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

// --- メニュー動的読み込みロジック ---
$menu_items = [];
$files = glob(__DIR__ . '/m.*.php');

if ($files) {
    foreach ($files as $filepath) {
        $filename = basename($filepath);
        if ($filename === 'm.common.php') continue; // 共通部品などは除外

        // ファイルを読み込む (安全のため最大4KB)
        $source = @file_get_contents($filepath, false, null, 0, 4096);
        if (!$source) continue;

        // "Title:" がないファイルはメニューに出さない
        if (stripos($source, 'Title:') === false) continue;

        // 各項目を抽出
        $title = $filename;
        $icon  = 'fas fa-file';
        $color = 'dark';
        $desc  = '';
        $sort  = 0;

        // 正規表現で一行ずつ解析 (より確実に)
        if (preg_match('/Title:\s*(.*)$/m', $source, $m)) $title = trim($m[1]);
        if (preg_match('/Icon:\s*(.*)$/m', $source, $m))  $icon  = trim($m[1]);
        if (preg_match('/Color:\s*(.*)$/m', $source, $m)) $color = trim($m[1]);
        if (preg_match('/Desc:\s*(.*)$/m', $source, $m))  $desc  = trim($m[1]);
        if (preg_match('/Sort:\s*(-?\d+)/m', $source, $m)) $sort  = (int)$m[1];

        $menu_items[] = [
            'url'   => $filename,
            'title' => $title,
            'icon'  => $icon,
            'color' => $color,
            'desc'  => $desc,
            'sort'  => $sort
        ];
    }
}

// ソート: Sortの値が大きい順 (PHP 5.6系でも動く書き方)
usort($menu_items, function($a, $b) {
    if ($a['sort'] == $b['sort']) return 0;
    return ($a['sort'] > $b['sort']) ? -1 : 1;
});
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理ダッシュボード</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚙️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f4f7f9; margin: 0; padding: 20px; color: #333; width:1000px;margin:auto;}
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #2c3e50; font-size: 1.5rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: flex-start; gap: 15px; text-decoration: none; color: inherit; border-left: 5px solid #ccc; transition: 0.2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-icon { font-size: 1.8rem; width: 40px; text-align: center; margin-top: 5px; }
        .card-content h3 { margin: 0 0 5px 0; font-size: 1.1rem; color: #2c3e50; }
        .card-content p { margin: 0; font-size: 0.85rem; color: #7f8c8d; line-height: 1.4; }
        
        /* カラーバリエーション */
        .border-blue   { border-left-color: #3498db; } .icon-blue   { color: #3498db; }
        .border-green  { border-left-color: #27ae60; } .icon-green  { color: #27ae60; }
        .border-orange { border-left-color: #f39c12; } .icon-orange { color: #f39c12; }
        .border-red    { border-left-color: #e74c3c; } .icon-red    { color: #e74c3c; }
        .border-dark   { border-left-color: #34495e; } .icon-dark   { color: #34495e; }
    </style>
</head>
<body>
<div class="header">
    <h1>管理ダッシュボード</h1>
    <a href="logout.php" style="color:#c0392b; text-decoration:none; font-weight:bold;"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
</div>
<div class="dashboard-grid">
    <?php foreach($menu_items as $m): ?>
    <a href="<?= $m['url'] ?>" class="card border-<?= $m['color'] ?>">
        <div class="card-icon icon-<?= $m['color'] ?>"><i class="<?= $m['icon'] ?>"></i></div>
        <div class="card-content">
            <h3><?= htmlspecialchars($m['title']) ?></h3>
            <p><?= htmlspecialchars($m['desc']) ?></p>
        </div>
    </a>
    <?php endforeach; ?>
</div>
</body>
</html>