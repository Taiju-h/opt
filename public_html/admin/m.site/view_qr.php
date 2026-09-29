<?php
// admin/m.site/view_qr.php
// 呼び出し元: admin/m.site.php ($site, $qr_url は定義済み)
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>QRバーコード発行 | <?= htmlspecialchars($site['name']) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --primary: #3498db; --dark: #2c3e50; --bg: #f4f7f9; }
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        
        .container { max-width: 1200px; margin: 0 auto; display: flex; gap: 30px; align-items: flex-start; justify-content: center; }
        
        .flyer { background: white; width: 210mm; min-height: 297mm; padding: 40px; box-sizing: border-box; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; }
        
        .header-bar { border-bottom: 5px solid var(--dark); padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .site-name { font-size: 2.2rem; font-weight: bold; color: var(--dark); margin: 0; }
        .doc-title { font-size: 1.2rem; color: #666; font-weight: bold; letter-spacing: 2px; }
        
        .qr-section { text-align: center; margin-top: 40px; }
        .qr-title { font-size: 1.8rem; font-weight: bold; color: var(--primary); margin-bottom: 10px; }
        .qr-desc { font-size: 1.1rem; color: #555; margin-bottom: 30px; }
        
        #qrcode { display: inline-block; padding: 20px; background: white; border: 4px solid var(--primary); border-radius: 15px; }
        
        .url-box { margin-top: 20px; font-size: 1.1rem; font-family: monospace; color: #555; background: #f8f9fa; padding: 10px; border-radius: 5px; display: inline-block; border: 1px solid #ddd; }
        
        .info-box { margin-top: 40px; background: #f8f9fa; padding: 25px; border-radius: 10px; border-left: 6px solid var(--primary); text-align: left; }
        
        /* ★修正：申し訳程度の「控えめ」な電話注文枠のスタイル */
        .phone-order-box { margin-top: 30px; border: 1px dashed #94a3b8; border-radius: 8px; padding: 15px; background: #f8fafc; text-align: center; }
        .phone-title { color: #475569; margin: 0 0 5px 0; font-size: 1rem; font-weight: bold; }
        .phone-number { font-size: 1.5rem; font-weight: bold; color: #334155; letter-spacing: 2px; margin: 5px 0; font-family: Arial, sans-serif; }
        .phone-desc { margin: 0; font-size: 0.85rem; color: #64748b; }
        .phone-sub { margin: 5px 0 0 0; font-size: 0.8rem; color: #94a3b8; }

        @media print {
            body { background: none; padding: 0; }
            .container { box-shadow: none; display: block; }
            .flyer { box-shadow: none; width: 100%; min-height: auto; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom:20px;">
    <button onclick="window.print()" style="background:#27ae60; color:white; padding:12px 25px; border:none; border-radius:30px; font-size:1.1rem; font-weight:bold; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);"><i class="fas fa-print"></i> チラシを印刷する</button>
    <a href="m.site.php" style="margin-left:15px; color:#555; text-decoration:none; font-weight:bold;">一覧へ戻る</a>
</div>

<div class="container">
    <div class="flyer">
        <div class="header-bar">
            <div>
                <h1 class="site-name" id="p_title"><?= htmlspecialchars($site['name']) ?></h1>
                <div style="font-size:1.2rem; color:#666; margin-top:10px;">専用 資材発注システム</div>
            </div>
            <div class="doc-title">林六株式会社</div>
        </div>

        <div class="qr-section">
            <div class="qr-title"><i class="fas fa-mobile-alt"></i> スマホで簡単・24時間発注</div>
            <div class="qr-desc">下記のQRコードをスマートフォンのカメラで読み取って、<br>専用の発注ページにアクセスしてください。</div>
            
            <div id="qrcode"></div>
            
            <br>
            <div class="url-box"><?= htmlspecialchars($qr_url) ?></div>

            <div style="font-weight:bold; color:#555; margin-top:15px;">↑ カメラで読み取ってください ↑</div>

            <div class="info-box">
                <h3 style="margin-top:0; background:#333; color:white; display:inline-block; padding:5px 10px; border-radius:4px;">使い方</h3>
                <ol style="margin:10px 0; padding-left:20px; line-height:1.8; font-size:11pt;">
                    <li>スマートフォンのカメラでQRバーコードを読み取る</li>
                    <li>または、上記のURLをブラウザに入力する</li>
                    <li>必要な数量と希望日時を選択して「注文を確定する」ボタンを押す</li>
                </ol>
            </div>
            
            <div class="phone-order-box">
                <h3 class="phone-title"><i class="fas fa-info-circle"></i> QRコードを読み込めない方へ</h3>
                <p class="phone-desc"><?= nl2br(htmlspecialchars($site['phone_order_text'] ?? 'お電話でのご注文も承っております。')) ?></p>
                
                <div class="phone-number"><?= htmlspecialchars($site['phone_order_number'] ?? '03-3256-4937') ?></div>
                
                <p class="phone-sub">受付時間: 平日 8:00〜17:00 （担当: <?= htmlspecialchars($site['internal_staff_name'] ?? '林六株式会社') ?>）</p>
            </div>
            
            <div style="margin-top:40px; border:3px solid #e67e22; color:#e67e22; font-weight:bold; padding:15px 30px; display:inline-block; transform:rotate(-3deg); font-size:1.2rem; border-radius:8px;">
                💧 現場への掲示は防水ラミネート加工を推奨します
            </div>
        </div>
    </div>
</div>

<script>
    // QRコード生成 (サイズ調整: 200x200)
    const qrObj = new QRCode(document.getElementById("qrcode"), { 
        text: "<?= htmlspecialchars($qr_url, ENT_QUOTES, 'UTF-8') ?>",
        width: 200, 
        height: 200, 
        correctLevel: QRCode.CorrectLevel.H 
    });
</script>
</body>
</html>