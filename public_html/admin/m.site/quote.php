<?php
// admin/m.site/quote.php
// 見積書のレイアウトテンプレート

$current_date = date('Y年m月d日');

// ----------------------------------------------
// 宛名の構築 (会社名 + 現場名)
// ----------------------------------------------
// クライアント会社名があればセット、なければ空
$display_client_name = "";
if (!empty($client_company_name)) {
    $display_client_name .= "<div style='font-size:1.1em; margin-bottom:5px;'>" . htmlspecialchars($client_company_name) . "</div>";
}

// 現場名 (様や御中がなければ自動付与)
$site_name_safe = htmlspecialchars($site['name']);
$suffix = "";
if (!preg_match('/(様|御中)$/u', $site_name_safe)) {
    // 会社名があるなら現場名は「御中」より「様」や「現場」止めが自然だが、
    // ここでは安全策として、もし敬称がなければ「御中」をつける
    $suffix = " <span style='font-size:0.8em;'>御中</span>";
}

$display_client_name .= "<div style='font-size:1.2em; font-weight:bold; margin-left:1em;'>" . $site_name_safe . $suffix . "</div>";


// ----------------------------------------------
// 発行者情報 (林六)
// ----------------------------------------------
// 担当者はログインユーザー
$my_name = $_SESSION['admin_name'] ?? '担当者'; 

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>御見積書 | <?= htmlspecialchars($site['name']) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap');
        body { font-family: "Noto Serif JP", serif; background: #555; padding: 20px; color: #333; }
        
        .page { 
            background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 15mm 20mm; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.5); box-sizing: border-box; position: relative;
        }
        
        /* ヘッダー周り */
        .doc-title { text-align: center; font-size: 22pt; font-weight: bold; letter-spacing: 5px; margin-bottom: 40px; border-bottom: 2px double #333; padding-bottom: 10px; }
        
        .top-area { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        
        /* 宛名エリア (左側) */
        .client-area { width: 55%; border-bottom: 1px solid #333; padding-bottom: 5px; line-height: 1.4; }

        /* 自社情報エリア (右側) */
        .company-info { width: 40%; text-align: right; font-size: 9pt; line-height: 1.5; font-family: "Helvetica Neue", sans-serif; }
        .company-name { font-size: 13pt; font-weight: bold; margin-bottom: 5px; letter-spacing: 1px; }
        .info-row { margin-bottom: 2px; }
        .manager-row { margin-top: 10px; font-weight: bold; font-size: 10pt; }
        
        /* テーブル */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10.5pt; }
        th { background: #f0f0f0; border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold; }
        td { border: 1px solid #333; padding: 8px; vertical-align: middle; }
        .col-name { text-align: left; }
        .col-qty { text-align: right; width: 12%; }
        .col-unit { text-align: center; width: 8%; }
        .col-price { text-align: right; width: 15%; }
        .col-total { text-align: right; width: 18%; }

        .grand-total { margin-top: 20px; text-align: right; font-size: 16pt; font-weight: bold; border-bottom: 2px solid #333; display: inline-block; float: right; padding: 0 20px; }
        
        .remarks { clear: both; margin-top: 80px; border: 1px solid #333; padding: 15px; min-height: 100px; font-size: 10pt; border-radius: 4px; }

        /* ボタン */
        .no-print { position: fixed; top: 20px; right: 20px; z-index: 999; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; color: white; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .btn-print { background: #2563eb; }
        .btn-back { background: #64748b; }

        @media print {
            body { background: white; padding: 0; }
            .page { box-shadow: none; margin: 0; width: 100%; height: 100%; }
            .no-print { display: none; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> 印刷 / PDF保存</button>
    <a href="m.site.php?mode=list" class="btn btn-back">戻る</a>
</div>

<div class="page">
    <div class="doc-title">御 見 積 書</div>

    <div class="top-area">
        <div class="client-area">
            <?= $display_client_name ?>
        </div>

        <div class="company-info">
            <div class="info-row">発行日: <?= $current_date ?></div>
            <div style="margin-top: 5px;"></div>
            <div class="company-name">林六株式会社</div>
            <div class="info-row">〒542-0081</div>
            <div class="info-row">大阪市中央区南船場4-11-28</div>
            <div class="info-row">JPR心斎橋ウエスト 8階</div>
            <div class="info-row">TEL: 06-6262-3914</div>
            <div class="info-row">FAX: 06-6120-9525</div>
            
            <div class="manager-row">
                担当: <?= htmlspecialchars($my_name) ?>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 20px; font-size: 10pt;">
        下記のとおり御見積申し上げます。<br>
        <span style="font-size: 0.9em; color: #666;">有効期限: 発行より3ヶ月</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>品名 / 規格</th>
                <th>数量</th>
                <th>単位</th>
                <th>単価</th>
                <th>金額</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grand_total = 0;
            foreach ($products as $p): 
                $price = $p['site_price'] ?? $p['default_price'];
                $qty = $p['default_quantity'] ?? 0;
                
                // 数量0の商品はスキップする？（今回は表示する仕様と仮定）
                // if ($qty <= 0) continue; 

                $subtotal = $price * $qty;
                $grand_total += $subtotal;
            ?>
            <tr>
                <td class="col-name">
                    <?= htmlspecialchars($p['name']) ?>
                    <?php if($p['series_name']): ?>
                        <br><span style="font-size:0.85em; color:#666;">(<?= htmlspecialchars($p['series_name']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td class="col-qty"><?= number_format($qty) ?></td>
                <td class="col-unit"><?= htmlspecialchars($p['unit']) ?></td>
                <td class="col-price">@ <?= number_format($price) ?></td>
                <td class="col-total">¥ <?= number_format($subtotal) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php for($i=0; $i<max(0, 10 - count($products)); $i++): ?>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="grand-total">
        合計金額: ¥ <?= number_format($grand_total) ?> <span style="font-size:0.6em; font-weight:normal;">(税抜)</span>
    </div>

    <div class="remarks">
        <strong>【備考】</strong><br>
        <p style="margin:5px 0;">
            ※ 本見積書の金額には消費税は含まれておりません。<br>
            ※ 搬入条件：4t車進入可
        </p>
    </div>
</div>

</body>
</html>