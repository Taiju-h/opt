<?php
// admin/m.site/view_list.php
// 現場一覧の表示パーツ

// 共通の敬称付与関数（表示時のみ）
if (!function_exists('add_sama')) {
    function add_sama($name) {
        if (empty($name)) return '未設定';
        if (preg_match('/(様|御中)$/u', $name)) return htmlspecialchars($name);
        return htmlspecialchars($name) . ' 様';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>現場一覧 | 林六システム</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; background: #f4f7f9; padding: 20px; color: #334155; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 20px; display:flex; justify-content:space-between; align-items: center; }
        
        .btn { padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; font-weight: bold; color: white; display:inline-flex; align-items:center; gap:5px; transition: 0.2s; border: none; cursor: pointer; }
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }
        
        .btn-new { background: #27ae60; }
        .btn-qr { background: #3498db; }
        .btn-edit { background: #f39c12; }
        .btn-quote { background: #6c5ce7; }
        .btn-del { background: #e74c3c; padding: 8px 12px; border-radius: 4px; color: white; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 0.85rem; text-transform: uppercase; }
        td { border-bottom: 1px solid #f1f5f9; padding: 15px 12px; vertical-align: middle; }
        tr:hover { background: #fafafa; }

        /* NG時間バッジ */
        .badge-ng { background: #fee2e2; color: #ef4444; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; border: 1px solid #fecaca; display: inline-flex; align-items: center; gap: 3px; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background: #dcfce7; color: #166534; border-left: 5px solid #22c55e; }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 5px solid #ef4444; }
    </style>
</head>
<body>
    
<?php require_once 'inc.header.php'; ?> 
<div class="container">
    <h1>
        <span>📋 現場一覧</span>
        <a href="m.site.php?mode=new" class="btn btn-new"><i class="fas fa-plus"></i> 新規現場登録</a>
    </h1>
    
    <?= $sys_msg ?? '' ?>

    <?php if(!$sites): ?>
        <p style="text-align:center; padding:50px; color: #94a3b8;">登録されている現場はありません。新規登録から始めてください。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>現場名（お客様名）</th>
                    <th>担当者</th>
                    <th style="width: 320px; text-align: right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($sites as $s): 
                    // この現場にNG時間が設定されているか判定 (簡易的に site_ng_times を引く)
                     $has_ng = "";//$pdo->query("SELECT count(*) FROM site_ng_times WHERE site_id = {$s['id']}")->fetchColumn();
                ?>
                <tr>
                    <td><span style="color:#94a3b8; font-family: monospace;"><?= $s['id'] ?></span></td>
                    <td>
                        <div style="font-weight:bold; font-size:1.05rem;">
                            <?= add_sama($s['name']) ?>
                            <?php if($has_ng): ?>
                                <span class="badge-ng" title="配送NG時間設定あり"><i class="fas fa-clock"></i> NG設定あり</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="color: #64748b;">
                            <i class="far fa-user-circle"></i> <?= add_sama($s['manager_name']) ?>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <a href="m.site.php?mode=quote&id=<?= $s['id'] ?>" class="btn btn-quote" title="見積書を作成">
                            <i class="fas fa-file-invoice-dollar"></i> 見積
                        </a>

                        <a href="m.site.php?mode=qr&id=<?= $s['id'] ?>" class="btn btn-qr" title="QRコード付きチラシを表示">
                            <i class="fas fa-qrcode"></i> QR
                        </a>
                        
                        <a href="m.site.php?mode=edit&id=<?= $s['id'] ?>" class="btn btn-edit" title="現場設定を修正">
                            <i class="fas fa-edit"></i> 修正
                        </a>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?= add_sama($s['name']) ?> を削除しますか？\n（この操作は取り消せません）')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-del" title="削除"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
<?php require_once 'inc.footer.php'; ?>
</div>
</body>
</html>