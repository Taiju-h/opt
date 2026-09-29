<?php
/**
 * Title: 現場管理 (QR・一覧)
 * Icon: fas fa-list-ul
 * Color: blue
 * Desc: 現場の一覧表示。ここから詳細設定(物流・商品)や、QRバーコードチラシ発行へ移動します。
 * Roles: super_admin, sales
 * Sort: 5000
 */

session_start();
require_once __DIR__ . '/../inc.db.php';

// 権限チェック
if (!isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit;
}

// =========================================================
// 🔄 モード判定 & データ保存処理 (Logic)
// =========================================================
$mode = $_GET['mode'] ?? 'list';
$site_id = $_GET['id'] ?? null;
$sys_msg = '';

require_once __DIR__ . '/m.site/post_save.php';

// =========================================================
// 🖥️ 画面表示 (View)
// =========================================================

// ■■■ A. 見積書モード ■■■
if ($mode === 'quote' && $site_id) {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch();
    if (!$site) die("Site not found");

    $client_company_name = "";
    if (!empty($site['client_person_id'])) {
        $stmt = $pdo->prepare("
            SELECT c.name 
            FROM staffs s
            JOIN companies c ON s.company_id = c.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$site['client_person_id']]);
        $client_company_name = $stmt->fetchColumn();
    }

    $sql = "
        SELECT p.*, sp.site_price, sp.default_quantity 
        FROM site_products sp
        JOIN products p ON sp.product_id = p.id
        WHERE sp.site_id = ? AND sp.is_active = 1
        ORDER BY p.sort_order ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$site_id]);
    $products = $stmt->fetchAll();

    require_once __DIR__ . '/m.site/quote.php';
    exit;
}

// ■■■ B. QRチラシ画面 ■■■
if ($mode === 'qr' && $site_id) {
    // ★修正：林六の担当者名（internal_staff_name）も一緒に取得する
    $stmt = $pdo->prepare("
        SELECT s.*, a.name as internal_staff_name 
        FROM sites s 
        LEFT JOIN admins a ON s.internal_staff_id = a.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch();
    if (!$site) die("Site not found");

    if (!preg_match('/様$/', $site['name'])) {
        $site['name'] .= ' 様';
    }

    $protocol = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
    $domain = $_SERVER['HTTP_HOST'];
    $qr_url = $protocol . $domain . '/app/' . $site['access_token'];

    require_once __DIR__ . '/m.site/view_qr.php';
    exit;
}

// ■■■ C. 新規・編集画面 ■■■
if ($mode === 'new' || $mode === 'edit') {
    $site = [];
    $s_cons = [];
    $s_vehs = [];
    $s_prods = [];
    
    if ($mode === 'edit' && $site_id) {
        $site = $pdo->query("SELECT * FROM sites WHERE id = $site_id")->fetch();
        $s_cons = $pdo->query("SELECT * FROM site_constraints WHERE site_id = $site_id")->fetch() ?: [];
        $s_vehs = $pdo->query("SELECT vehicle_id FROM site_allowed_vehicles WHERE site_id = $site_id")->fetchAll(PDO::FETCH_COLUMN);
        $tmp = $pdo->query("SELECT * FROM site_products WHERE site_id = $site_id")->fetchAll();
        foreach($tmp as $t) $s_prods[$t['product_id']] = $t;
    }

    $products = $pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    $vehicles = $pdo->query("SELECT * FROM vehicle_masters WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    
    $all_clients = $pdo->query("SELECT s.*, c.name as company_name FROM staffs s JOIN companies c ON s.company_id=c.id WHERE s.is_active=1 ORDER BY c.id")->fetchAll();
    $companies = $pdo->query("SELECT id, name, email_domain FROM companies WHERE is_active=1 ORDER BY name")->fetchAll();
    $internal_staffs = $pdo->query("SELECT id, name FROM admins ORDER BY id")->fetchAll();

    require_once __DIR__ . '/m.site/view_form.php';
    exit;
}

// ■■■ D. 一覧画面 (デフォルト) ■■■
$sites = $pdo->query("SELECT * FROM sites WHERE is_active=1 ORDER BY id DESC")->fetchAll();
require_once __DIR__ . '/m.site/view_list.php';
?>