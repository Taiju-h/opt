<?php
// admin/m.site/post_save.php

if (!function_exists('generateAccessToken')) {
    function generateAccessToken() {
        return bin2hex(random_bytes(4));
    }
}

// ---------------------------------------------------------
// 新規現場/現場編集画面からの「客先担当者をその場で追加」
// ---------------------------------------------------------
if (($mode === 'new' || $mode === 'edit')
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'add_client_staff') {

    header('Content-Type: application/json; charset=UTF-8');

    try {
        $company_id = (int)($_POST['company_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $position   = trim($_POST['position'] ?? '');

        if ($company_id <= 0 || $name === '' || $email === '') {
            throw new RuntimeException('会社・氏名・メールアドレスは必須です。');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('メールアドレスの形式が正しくありません。');
        }

        $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND is_active = 1");
        $stmt->execute([$company_id]);
        $company_name = $stmt->fetchColumn();
        if (!$company_name) {
            throw new RuntimeException('選択した会社が見つかりません。');
        }

        $stmt = $pdo->prepare("INSERT INTO staffs (company_id, name, email, position) VALUES (?, ?, ?, ?)");
        $stmt->execute([$company_id, $name, $email, $position]);
        $staff_id = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true,
            'staff' => [
                'id' => $staff_id,
                'name' => $name,
                'email' => $email,
                'position' => $position,
                'company_id' => $company_id,
                'company_name' => $company_name,
                'label' => $company_name . ' - ' . $name,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'このメールアドレスは既に登録されています。'
            : '担当者登録中にDBエラーが発生しました。';
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($mode === 'list' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete') {
    try {
        $stmt = $pdo->prepare("UPDATE sites SET is_active = 0 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $sys_msg = "<div class='alert success'>現場を削除しました。</div>";
    } catch (Exception $e) { $sys_msg = "<div class='alert error'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>"; }
}

if (($mode === 'new' || $mode === 'edit')
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['action'] ?? 'save_site') === 'save_site')) {
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
