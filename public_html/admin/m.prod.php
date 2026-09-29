<?php
/**
 * Title: 商品マスタ管理
 * Icon: fas fa-box
 * Color: green
 * Desc: 商品の登録・編集・並び順・単価・メーカー情報・荷姿(パッケージ)の管理。
 */

session_start();
require_once __DIR__ . '/../inc.db.php';

$message = '';
$system_error = '';

if (!isset($_SESSION['admin_role'])) {
    $system_error = "【セッションエラー】ログイン情報が見つかりません。<br>一度ログアウトして再ログインしてください。";
}

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($system_error)) {
    try {
        $pdo->beginTransaction();

        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $series = $_POST['series_name'];
            $name   = $_POST['name'];
            $unit   = $_POST['unit'];
            $price  = $_POST['default_price'];
            $v_name = $_POST['vendor_name'] ?? null;
            $v_mail = $_POST['vendor_email'] ?? null;
            $sort   = ($_POST['sort_order'] !== '') ? $_POST['sort_order'] : 100;
            $is_main = isset($_POST['is_main']) ? 1 : 0;
            $show_price = isset($_POST['show_price']) ? 1 : 0;

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO products (series_name, name, unit, default_price, show_price, sort_order, is_main, vendor_name, vendor_email, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$series, $name, $unit, $price, $show_price, $sort, $is_main, $v_name, $v_mail]);
                $product_id = $pdo->lastInsertId();
                $message = "<div class='alert success'>商品を登録しました。</div>";
            } else {
                $product_id = $_POST['id'];
                $stmt = $pdo->prepare("UPDATE products SET series_name=?, name=?, unit=?, default_price=?, show_price=?, sort_order=?, is_main=?, vendor_name=?, vendor_email=? WHERE id=?");
                $stmt->execute([$series, $name, $unit, $price, $show_price, $sort, $is_main, $v_name, $v_mail, $product_id]);
                
                // 既存の荷姿をリセット
                $pdo->prepare("DELETE FROM product_packages WHERE product_id = ?")->execute([$product_id]);
                $message = "<div class='alert success'>商品情報を更新しました。</div>";
            }

            // ★追加：荷姿(パッケージ)の保存処理
            if (!empty($_POST['package_names'])) {
                $stmt_pkg = $pdo->prepare("INSERT INTO product_packages (product_id, package_name, capacity_kg, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($_POST['package_names'] as $idx => $pkg_name) {
                    if (trim($pkg_name) !== '') {
                        $cap = (float)($_POST['capacity_kgs'][$idx] ?? 0);
                        $stmt_pkg->execute([$product_id, trim($pkg_name), $cap, $idx]);
                    }
                }
            }
        }
        elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $message = "<div class='alert success'>商品を削除しました。</div>";
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "<div class='alert error'><strong>保存エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$products = [];
$packages = [];
if (empty($system_error)) {
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        if ($stmt) { 
            $products = $stmt->fetchAll(); 
            // 荷姿データを全取得して配列に整理
            $pkg_stmt = $pdo->query("SELECT * FROM product_packages ORDER BY product_id, sort_order ASC");
            foreach ($pkg_stmt->fetchAll() as $pkg) {
                $packages[$pkg['product_id']][] = $pkg;
            }
        }
    } catch (PDOException $e) {
        $system_error = "【データベースエラー】<br>" . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>商品マスタ管理 | システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; --bg: #f8fafc; --text: #334155; }
        body { font-family: "Hiragino Sans", sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 0.85rem; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-del { background: #64748b; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #64748b; font-size: 0.9rem; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 650px; max-height: 90vh; overflow-y: auto; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
        input[type="text"], input[type="number"], input[type="email"], select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .section-title { font-weight: bold; color: #64748b; margin-top: 25px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 5px; margin-bottom: 15px; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 10px; background: #eff6ff; border-radius: 6px; }
        
        /* 荷姿タグのスタイル */
        .pkg-tag { font-size: 0.8rem; background: #eff6ff; color: #1e40af; display: inline-block; padding: 4px 8px; border-radius: 4px; border: 1px solid #bfdbfe; margin: 2px; }
        .pkg-tag small { color: #64748b; font-weight: bold; }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1>
        <span>📦 商品マスタ管理</span>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> 新規商品追加
        </button>
    </h1>
    
    <?= $message ?>

    <?php if (count($products) == 0): ?>
        <p style="text-align:center; padding:30px; color:#94a3b8;">登録されている商品がありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:60px; text-align:center;">順序</th>
                    <th>シリーズ / 商品名</th>
                    <th>荷姿・パッケージ</th>
                    <th>単位 / 単価</th>
                    <th>メーカー情報</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td style="text-align:center; font-family:monospace;"><?= $p['sort_order'] ?></td>
                    <td>
                        <div style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($p['series_name']) ?></div>
                        <b><?= htmlspecialchars($p['name']) ?></b>
                        <?php if(!empty($p['is_main'])): ?><span style="background:#2563eb; color:white; font-size:0.7rem; padding:2px 5px; border-radius:3px; margin-left:5px;">MAIN</span><?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $pkgs = $packages[$p['id']] ?? [];
                        if (empty($pkgs)): ?>
                            <span style="color:#94a3b8; font-size:0.8rem;">未設定</span>
                        <?php else: ?>
                            <?php foreach($pkgs as $pkg): ?>
                                <div class="pkg-tag">
                                    <?= htmlspecialchars($pkg['package_name']) ?> 
                                    <small>(<?= (float)$pkg['capacity_kg'] ?>kg)</small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:0.9rem;"><?= htmlspecialchars($p['unit']) ?></div>
                        <div>
                            ¥<?= number_format($p['default_price']) ?>
                            <?php if(isset($p['show_price']) && !$p['show_price']): ?>
                                <i class="fas fa-eye-slash" style="color:#ef4444; margin-left:5px;" title="価格非表示"></i>
                            <?php else: ?>
                                <i class="fas fa-eye" style="color:#10b981; margin-left:5px;" title="価格表示"></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="font-size:0.85rem; color:#64748b;">
                        <b><?= htmlspecialchars($p['vendor_name'] ?? '未設定') ?></b><br>
                        <?= htmlspecialchars($p['vendor_email'] ?? '-') ?>
                    </td>
                    <td>
                        <?php $p['packages'] = $pkgs; // JSに渡すために配列にセット ?>
                        <button class="btn btn-sm btn-edit" onclick='openEditModal(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('削除しますか？');">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-del"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle">商品登録</h2>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="productId">

            <div class="section-title">基本情報</div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="width:80px;"><label>順序</label><input type="number" name="sort_order" id="sortOrder"></div>
                <div class="form-group" style="flex:1;"><label>シリーズ</label><input type="text" name="series_name" id="seriesName" required></div>
            </div>
            <div class="form-group"><label>商品名</label><input type="text" name="name" id="productName" required></div>
            
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;"><label>単位 (見出し用)</label><input type="text" name="unit" id="unit" required placeholder="例: kg, 袋, 缶"></div>
                <div class="form-group" style="flex:1;"><label>標準単価</label><input type="number" name="default_price" id="price" required></div>
            </div>

            <div class="section-title">📦 荷姿・パッケージ設定</div>
            <p style="font-size:0.8rem; color:#64748b; margin-top:-10px; margin-bottom:10px;">
                ※「指定なし(バラ)」等、重量が決まっていない端数用には容量「0」を設定してください。
            </p>
            <div id="package_container">
                </div>
            <button type="button" class="btn btn-sm" onclick="addPackageRow()" style="background:#e2e8f0; color:#334155; margin-top:5px; border:1px solid #cbd5e1;">
                <i class="fas fa-plus"></i> 荷姿の選択肢を追加
            </button>

            <div class="section-title">発注先（メーカー）情報</div>
            <div class="form-group"><label>メーカー名</label><input type="text" name="vendor_name" id="vendorName" placeholder="例: 〇〇セメント株式会社"></div>
            <div class="form-group"><label>メーカーメール</label><input type="email" name="vendor_email" id="vendorEmail" placeholder="order@example.com"></div>

            <div class="section-title">オプション</div>
            <div class="checkbox-wrapper"><input type="checkbox" name="show_price" id="showPrice" value="1"><label for="showPrice">現場で単価を表示する</label></div>
            <div class="checkbox-wrapper"><input type="checkbox" name="is_main" id="isMain" value="1"><label for="isMain">メイン商品（識別用）</label></div>

            <div style="text-align:right; margin-top:30px;">
                <button type="button" class="btn" onclick="closeModal()" style="background:#ccc;">キャンセル</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">保存する</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('productModal');
    const pkgContainer = document.getElementById('package_container');

    // 荷姿の入力行を追加する関数
    function addPackageRow(name = '', cap = '') {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '10px';
        row.style.marginBottom = '10px';
        row.style.alignItems = 'center';
        row.innerHTML = `
            <div style="flex:2;">
                <input type="text" name="package_names[]" value="${name}" placeholder="荷姿 (例: 1tパック, 25kg袋)" required style="margin-bottom:0;">
            </div>
            <div style="flex:1;">
                <input type="number" name="capacity_kgs[]" value="${cap}" placeholder="容量kg (バラは0)" step="0.1" required style="margin-bottom:0;">
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.2rem; padding:0 5px;">
                <i class="fas fa-times-circle"></i>
            </button>
        `;
        pkgContainer.appendChild(row);
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = '新規商品追加';
        document.getElementById('formAction').value = 'add';
        document.getElementById('productId').value = '';
        document.getElementById('sortOrder').value = '100';
        document.getElementById('seriesName').value = '';
        document.getElementById('productName').value = '';
        document.getElementById('unit').value = 'kg';
        document.getElementById('price').value = '0';
        document.getElementById('vendorName').value = '';
        document.getElementById('vendorEmail').value = '';
        document.getElementById('showPrice').checked = true;
        document.getElementById('isMain').checked = false;
        
        // 荷姿枠をリセットして初期値をセット
        pkgContainer.innerHTML = '';
        addPackageRow('指定なし(バラ)', 0);
        
        modal.classList.add('active');
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = '商品編集';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('productId').value = data.id;
        document.getElementById('sortOrder').value = data.sort_order;
        document.getElementById('seriesName').value = data.series_name;
        document.getElementById('productName').value = data.name;
        document.getElementById('unit').value = data.unit;
        document.getElementById('price').value = data.default_price;
        document.getElementById('vendorName').value = data.vendor_name || '';
        document.getElementById('vendorEmail').value = data.vendor_email || '';
        document.getElementById('showPrice').checked = (data.show_price == 1);
        document.getElementById('isMain').checked = (data.is_main == 1);
        
        // 登録済みの荷姿を展開
        pkgContainer.innerHTML = '';
        if (data.packages && data.packages.length > 0) {
            data.packages.forEach(pkg => addPackageRow(pkg.package_name, pkg.capacity_kg));
        } else {
            addPackageRow('指定なし(バラ)', 0);
        }

        modal.classList.add('active');
    }

    function closeModal() { modal.classList.remove('active'); }
</script>
<?php require_once 'inc.footer.php'; ?>
</body>
</html>