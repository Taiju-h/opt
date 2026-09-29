<?php
// admin/m.site/post_save.php

if (!function_exists('generateAccessToken')) {
    function generateAccessToken() {
        return bin2hex(random_bytes(4));
    }
}

if ($mode === 'list' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete') {
    try {
        $stmt = $pdo->prepare("UPDATE sites SET is_active = 0 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $sys_msg = "<div class='alert success'>現場を削除しました。</div>";
    } catch (Exception $e) { $sys_msg = "<div class='alert error'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>"; }
}

if (($mode === 'new' || $mode === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $name = $_POST['site_name'];
        $manager_email = $_POST['manager_email'];
        $cc_email = $_POST['cc_email'];
        $lead_time_days = ($_POST['lead_time_days'] !== '') ? (int)$_POST['lead_time_days'] : null;
        
        $client_person_id = !empty($_POST['client_person_id']) ? (int)$_POST['client_person_id'] : null;
        $internal_staff_id = !empty($_POST['internal_staff_id']) ? (int)$_POST['internal_staff_id'] : null;

        // ★追加：電話注文用のテキストと番号
        $phone_order_text = $_POST['phone_order_text'] ?? 'お電話でのご注文も承っております。';
        $phone_order_number = $_POST['phone_order_number'] ?? '03-3256-4937';

        // 客先の担当者名をstaffsから取得
        $stmt = $pdo->prepare("SELECT name FROM staffs WHERE id = ?");
        $stmt->execute([$client_person_id]);
        $manager_name = $stmt->fetchColumn() ?: '未設定';

        if ($mode === 'new') {
            $token = generateAccessToken(); 
            // ★ phone_order_text と phone_order_number を保存
            $stmt = $pdo->prepare("INSERT INTO sites (name, access_token, client_person_id, internal_staff_id, manager_name, manager_email, cc_email, lead_time_days, phone_order_text, phone_order_number, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$name, $token, $client_person_id, $internal_staff_id, $manager_name, $manager_email, $cc_email, $lead_time_days, $phone_order_text, $phone_order_number]);
            $site_id = $pdo->lastInsertId();
        } else {
            // ★ phone_order_text と phone_order_number を更新
            $stmt = $pdo->prepare("UPDATE sites SET name=?, client_person_id=?, internal_staff_id=?, manager_name=?, manager_email=?, cc_email=?, lead_time_days=?, phone_order_text=?, phone_order_number=? WHERE id=?");
            $stmt->execute([$name, $client_person_id, $internal_staff_id, $manager_name, $manager_email, $cc_email, $lead_time_days, $phone_order_text, $phone_order_number, $site_id]);
            
            $pdo->prepare("DELETE FROM site_allowed_vehicles WHERE site_id=?")->execute([$site_id]);
            $pdo->prepare("DELETE FROM site_products WHERE site_id=?")->execute([$site_id]);
        }

        if (!empty($_POST['vehicles'])) {
            $stmt = $pdo->prepare("INSERT INTO site_allowed_vehicles (site_id, vehicle_id) VALUES (?, ?)");
            foreach ($_POST['vehicles'] as $vid) $stmt->execute([$site_id, $vid]);
        }

        if (!empty($_POST['products'])) {
            $stmt = $pdo->prepare("INSERT INTO site_products (site_id, product_id, site_price, default_quantity, lead_time_days, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            foreach ($_POST['products'] as $pid) {
                $price = $_POST["price_{$pid}"] ?? 0;
                $qty = $_POST["qty_display_{$pid}"] ?? 0;
                $lt = ($_POST["lead_time_{$pid}"] !== '') ? (int)$_POST["lead_time_{$pid}"] : null;
                $stmt->execute([$site_id, $pid, $price, $qty, $lt]);
            }
        }

        $pdo->commit();
        header("Location: m.site.php?saved=1");
        exit;
    } catch (Exception $e) { $pdo->rollBack(); die("Save Error: " . $e->getMessage()); }
}