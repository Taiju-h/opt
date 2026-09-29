<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= $mode==='new'?'新規登録':'設定変更' ?> | 現場管理</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        body { font-family: sans-serif; background: #f4f7f9; padding: 20px; color: #334155; }
        .container { max-width: 950px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { border-bottom: 2px solid #2980b9; padding-bottom: 10px; margin-bottom: 25px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; }
        label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; }
        input[type=text], input[type=email], input[type=number], select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-save { background: #2980b9; }
        .btn-cancel { background: #95a5a6; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        th { background: #f1f5f9; color: #475569; font-size: 0.9rem; }
    </style>
</head>
<body>
<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1><?= $mode==='new'?'🏗️ 新規現場登録':'⚙️ 現場設定変更' ?></h1>
    
    <form method="POST">
        <label>現場名（プロジェクト名）</label>
        <input type="text" name="site_name" value="<?= htmlspecialchars($site['name']??'') ?>" required placeholder="例：虎ノ門3丁目工事">
        
        <div class="form-row" style="margin-top:20px;">
            <div class="form-group">
                <label style="color:#2980b9;">👤 客先担当者 (m.client登録者)</label>
                <select name="client_person_id" id="mgr_sel" class="select2" style="width:100%;">
                    <option value="">未選択</option>
                    <?php foreach($all_clients as $c): ?>
                        <option value="<?= $c['id'] ?>" data-email="<?= $c['email'] ?>" <?= ($site['client_person_id']??'')==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['company_name'] . ' - ' . $c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label style="color:#27ae60;">🏢 弊社担当者 (林六スタッフ)</label>
                <select name="internal_staff_id" id="staff_sel" class="select2" style="width:100%;">
                    <option value="">未選択</option>
                    <?php foreach($internal_staffs as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($site['internal_staff_id']??'')==$s['id']?'selected':'' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>通知先メールアドレス (お客様)</label>
                <input type="email" name="manager_email" id="mgr_email" value="<?= htmlspecialchars($site['manager_email']??'') ?>" readonly style="background:#f8fafc; color:#64748b;" placeholder="担当者を選ぶと自動入力されます">
            </div>
            <div class="form-group">
                <label>CCメールアドレス (社内共有など)</label>
                <input type="email" name="cc_email" value="<?= htmlspecialchars($site['cc_email']??'') ?>" placeholder="例：sales@hayashiroku.co.jp">
            </div>
        </div>

        <label>現場固有のリードタイム (通常納期)</label>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px;">
            <input type="number" name="lead_time_days" value="<?= htmlspecialchars($site['lead_time_days'] ?? '') ?>" min="0" placeholder="空欄なら標準設定" style="width: 150px;">
            <span style="font-size: 0.85rem; color: #64748b;">営業日</span>
        </div>

        <h3 style="border-left: 4px solid #e11d48; padding-left: 10px; margin-top:30px;">📞 QRコード読取不可時のご案内（チラシ用）</h3>
        <div class="form-row" style="background:#fff1f2; padding:15px; border-radius:8px; border:1px solid #fda4af;">
            <div class="form-group" style="flex:2;">
                <label style="color:#e11d48;">案内テキスト</label>
                <input type="text" name="phone_order_text" value="<?= htmlspecialchars($site['phone_order_text'] ?? 'お電話でのご注文も承っております。') ?>" placeholder="例：お電話でのご注文も承っております。">
            </div>
            <div class="form-group" style="flex:1;">
                <label style="color:#e11d48;">電話番号</label>
                <input type="text" name="phone_order_number" value="<?= htmlspecialchars($site['phone_order_number'] ?? '03-3256-4937') ?>" placeholder="例：03-3256-4937">
            </div>
        </div>

        <h3 style="border-left: 4px solid #27ae60; padding-left: 10px; margin-top:30px;">取扱商品・単価設定</h3>
        <table>
            <thead><tr><th style="width:50px;">有効</th><th>商品名</th><th style="width:120px;">現場単価</th><th style="width:120px;">初期数量</th><th style="width:120px;">固有納期</th></tr></thead>
            <tbody>
                <?php foreach($products as $p): 
                    $sp = $s_prods[$p['id']] ?? null;
                    $checked = $sp ? 'checked' : ($mode==='new' && $p['sort_order']<20 ? 'checked':'');
                    $price = $sp ? $sp['site_price'] : $p['default_price'];
                    $qty = $sp ? $sp['default_quantity'] : ($p['is_main']?500:0);
                    $lt = $sp ? $sp['lead_time_days'] : '';
                ?>
                <tr>
                    <td style="text-align:center;"><input type="checkbox" name="products[]" value="<?= $p['id'] ?>" <?= $checked ?>></td>
                    <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                    <td><input type="number" name="price_<?= $p['id'] ?>" value="<?= $price ?>" step="0.1"></td>
                    <td><input type="number" name="qty_display_<?= $p['id'] ?>" value="<?= $qty ?>"></td>
                    <td><input type="number" name="lead_time_<?= $p['id'] ?>" value="<?= $lt ?>" placeholder="標準"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:40px; border-top: 2px solid #eee; padding-top: 20px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-save"><i class="fas fa-check-circle"></i> 設定を保存する</button>
            <a href="m.site.php" class="btn btn-cancel">キャンセル</a>
        </div>
    </form>
</div>

<script>
$(function(){
    $('.select2').select2({ placeholder: "選択または検索", allowClear: true });
    $('#mgr_sel').on('select2:select', function(e){
        $('#mgr_email').val($(this).find(':selected').data('email'));
    });
});
</script>
</body>
</html>