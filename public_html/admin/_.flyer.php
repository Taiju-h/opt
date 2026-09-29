<?php
/**
 * Title: チラシ作成
 * Icon: fas fa-print
 * Color: orange
 * Desc: 現場配布用のQRコード付きチラシを作成・印刷します。
 * * Hidden: true
 * Sort: -1
 */

session_start();
require_once __DIR__ . '/../inc.db.php';

// 権限チェック
if (!isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit;
}

// 1. システム設定（ベースURLなど）を取得
$configs = [];
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM system_configs");
    $db_configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // デフォルト値
    $defaults = [
        'site_name' => '林六システム',
        'site_url' => 'https://www.hayashiroku.co.jp',
        'order_app_url' => 'https://order.optjet.com/app/',
        'email_contact' => 'info@hayashiroku.co.jp'
    ];
    $configs = array_merge($defaults, $db_configs);
} catch (Exception $e) {
    $configs = $defaults;
}

// 2. 現場IDが指定されている場合、その現場情報を取得
// ★修正: sites テーブルを参照し、site_id パラメータも受け入れる
$site_data = null;
$target_qr_url = $configs['order_app_url']; // デフォルトは汎用URL
$default_subtitle = "現場名を入力してください";

// m.site.php からは site_id で来る可能性があるため両方チェック
$target_id = $_GET['id'] ?? $_GET['site_id'] ?? null;

if ($target_id) {
    // ★テーブル名を sites に変更
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$target_id]);
    $site = $stmt->fetch();

    if ($site) {
        $site_data = $site;
        // ★ここがポイント: 現場名と専用URLをセット
        $default_subtitle = $site['name'] . " 様";
        
        // 末尾にスラッシュがあるか確認して結合
        $base_url = rtrim($configs['order_app_url'], '/');
        $target_qr_url = $base_url . '/' . $site['public_id'];
    }
}

// QRコードの選択肢
$qr_options = [];
// 現場IDがあるなら、その専用URLを最優先にする
if ($site_data) {
    $qr_options[$target_qr_url] = '★この現場専用の発注URL (' . $site_data['name'] . ')';
}
// その他の選択肢
$qr_options[$configs['order_app_url']] = '現場オーダーアプリ (汎用トップ)';
$qr_options[$configs['site_url']] = '公式サイト (一般用)';
$qr_options['mailto:' . $configs['email_contact']] = 'お問い合わせメール作成';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チラシ作成 | 林六システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        :root { --primary: #3498db; --dark: #2c3e50; --bg: #f4f7f9; --danger: #e74c3c; }
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", sans-serif; background: var(--bg); margin: 0; color: #333; padding: 20px; }
        
        .container { max-width: 1200px; margin: 0 auto; display: flex; gap: 30px; align-items: flex-start; }
        
        /* 設定パネル (左側) */
        .settings-panel { flex: 1; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: sticky; top: 20px; }
        
        h1 { margin: 0 0 20px 0; font-size: 1.4rem; color: var(--dark); border-bottom: 2px solid var(--primary); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 0.9rem; color: #555; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        
        .btn { display: block; width: 100%; padding: 12px; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; font-size: 1rem; transition: 0.2s; text-align: center; text-decoration: none; margin-bottom: 10px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-back { background: #95a5a6; color: white; }
        
        /* プレビューエリア (右側・背景グレー) */
        .preview-area { flex: 1.5; background: #555; padding: 40px; border-radius: 12px; display: flex; justify-content: center; min-height: 800px; }
        
        /* チラシ用紙 (A4サイズ) */
        .flyer-paper { 
            background: white; width: 210mm; min-height: 297mm; padding: 20mm; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); box-sizing: border-box; text-align: center; position: relative;
        }

        .flyer-title { font-size: 28pt; margin-bottom: 10px; color: var(--dark); font-weight: bold; line-height: 1.2; }
        .flyer-subtitle { font-size: 18pt; border-bottom: 3px solid #333; display: inline-block; padding: 0 20px 5px; margin-bottom: 30px; }
        .flyer-msg { font-size: 16pt; margin: 20px 0; font-weight: bold; color: #e74c3c; }
        
        .qr-container { width: 200px; height: 200px; border: 4px solid #333; margin: 20px auto; padding: 10px; background: #fff; display: flex; align-items: center; justify-content: center; }
        .qr-caption { font-size: 10pt; font-weight: bold; margin-bottom: 30px; }
        
        .info-box { text-align: left; padding: 20px; background: #f8f9fa; border: 2px solid #ddd; border-radius: 10px; margin-top: 20px; }
        .info-box h3 { margin-top: 0; background: #333; color: white; padding: 5px 10px; display: inline-block; font-size: 12pt; }
        .info-box pre { font-family: inherit; white-space: pre-wrap; margin: 10px 0 0; line-height: 1.6; font-size: 11pt; }

        .laminate-mark { border: 3px solid red; color: red; font-weight: bold; padding: 10px 20px; display: inline-block; margin-top: 40px; font-size: 14pt; transform: rotate(-5deg); background: rgba(255, 255, 255, 0.9); }

        /* 印刷設定 (重要) */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .settings-panel, .btn, .header-nav { display: none !important; }
            .container { display: block; margin: 0; width: 100%; max-width: none; }
            .preview-area { background: white; padding: 0; border-radius: 0; min-height: auto; }
            .flyer-paper { box-shadow: none; margin: 0; width: 100%; height: 100%; page-break-after: always; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <div class="settings-panel">
        <h1>🖨️ チラシ作成</h1>
        
        <div class="form-group">
            <label>QRコードの飛び先</label>
            <select id="qr_url" onchange="updateFlyer()">
                <?php foreach ($qr_options as $url => $label): ?>
                    <option value="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
                <option value="custom">自由入力</option>
            </select>
            <input type="text" id="qr_custom_input" placeholder="https://..." style="display:none; margin-top:5px;" onkeyup="updateFlyer()">
        </div>

        <div class="form-group">
            <label>メインタイトル</label>
            <input type="text" id="title_input" value="資材発注用 QRコード" onkeyup="updateFlyer()">
        </div>

        <div class="form-group">
            <label>現場名 / サブタイトル</label>
            <input type="text" id="subtitle_input" value="<?= htmlspecialchars($default_subtitle) ?>" onkeyup="updateFlyer()">
        </div>

        <div class="form-group">
            <label>キャッチコピー</label>
            <input type="text" id="msg_input" value="スマホで読み取るだけで発注完了！" onkeyup="updateFlyer()">
        </div>

        <div class="form-group">
            <label>説明文 (使い方など)</label>
            <textarea id="desc_input" rows="5" onkeyup="updateFlyer()">
■ 発注方法
1. カメラでQRコードを読み取る
2. 必要な商品と数量を選択
3. 「発注確定」ボタンを押す

※在庫が少なくなったら早めの発注をお願いします。</textarea>
        </div>

        <div class="form-group">
            <label style="cursor:pointer; display:flex; align-items:center;">
                <input type="checkbox" id="laminate_check" checked onchange="updateFlyer()" style="width:auto; margin-right:10px;">
                ラミネート加工推奨マークを表示
            </label>
        </div>
        
        <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">

        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> 印刷 / PDF保存
        </button>
        <a href="<?= isset($target_id) ? 'm.site.php' : 'index.php' ?>" class="btn btn-back">戻る</a>
    </div>

    <div class="preview-area">
        <div class="flyer-paper">
            <div class="flyer-title" id="preview_title"></div>
            <div class="flyer-subtitle" id="preview_subtitle"></div>
            <div class="flyer-msg" id="preview_msg"></div>
            
            <div class="qr-container" id="qrcode"></div>
            <div class="qr-caption">↑ カメラで読み取ってください ↑</div>
            
            <div class="info-box">
                <h3>使い方・注意事項</h3>
                <pre id="preview_desc"></pre>
            </div>
            
            <div style="margin-top:20px; font-size:0.9rem; color:#555;">
                お問い合わせ: <?= htmlspecialchars($configs['email_contact']) ?>
            </div>

            <div id="laminate_mark" class="laminate-mark">
                <div>💧 防水ラミネート加工推奨</div>
                <div style="font-size:0.7rem; font-weight:normal; margin-top:5px;">
                    屋外掲示の際は劣化防止のため加工をお願いします
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const qrContainer = document.getElementById("qrcode");
    let qrcodeObj = new QRCode(qrContainer, {
        width: 180,
        height: 180,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    function updateFlyer() {
        // テキスト反映
        document.getElementById('preview_title').innerText = document.getElementById('title_input').value;
        document.getElementById('preview_subtitle').innerText = document.getElementById('subtitle_input').value;
        document.getElementById('preview_msg').innerText = document.getElementById('msg_input').value;
        document.getElementById('preview_desc').innerText = document.getElementById('desc_input').value;

        // ラミネート表示切替
        document.getElementById('laminate_mark').style.display = document.getElementById('laminate_check').checked ? 'inline-block' : 'none';

        // QR生成
        const qrSelect = document.getElementById('qr_url');
        const customInput = document.getElementById('qr_custom_input');
        let qrData = qrSelect.value;

        if (qrData === 'custom') {
            customInput.style.display = 'block';
            qrData = customInput.value;
        } else {
            customInput.style.display = 'none';
        }

        qrcodeObj.clear();
        if (qrData) {
            qrcodeObj.makeCode(qrData);
        }
    }

    // ロード時に実行
    window.addEventListener('load', function() {
        setTimeout(updateFlyer, 300);
    });
</script>

 
</body>
</html>