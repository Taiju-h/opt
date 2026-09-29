<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>完全版発注フォーム | 虎ノ門3丁目</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f8fafc;
            --border: #cbd5e1;
            --text: #334155;
            --danger: #ef4444;
            --header-bg: #1e293b;
        }

        body {
            font-family: "Hiragino Sans", sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0 0 140px 0; /* フッター分空ける */
        }

        /* ヘッダー */
        .header {
            background: var(--header-bg);
            color: white;
            padding: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .site-name { font-weight: bold; font-size: 1.1rem; }
        .site-info { font-size: 0.8rem; opacity: 0.8; margin-top: 4px; }

        .container { padding: 16px; max-width: 600px; margin: 0 auto; }

        h2 {
            font-size: 0.95rem;
            color: #64748b;
            margin: 24px 0 8px 4px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* 1. 商品セクション（カード） */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 12px;
            border: 1px solid var(--border);
        }

        /* メイン商品（強調） */
        .main-item {
            border-left: 5px solid var(--primary);
            padding: 16px;
        }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .badge { background: var(--primary); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; }

        /* オプション商品（リスト） */
        .opt-row {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        .opt-row:last-child { border-bottom: none; }
        
        /* チェックボックス */
        .chk-box {
            width: 22px; height: 22px;
            margin-right: 12px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: white;
            transition: all 0.2s;
        }
        .opt-row.active .chk-box {
            background: var(--primary);
            border-color: var(--primary);
        }
        .opt-row.active { background: #eff6ff; }

        /* 入力エリア（スライド表示） */
        .qty-area {
            display: none;
            padding: 0 16px 16px 50px;
            background: #eff6ff;
            border-bottom: 1px solid #f1f5f9;
        }
        .opt-row.active + .qty-area { display: block; animation: slideDown 0.2s; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .input-group { display: flex; align-items: center; gap: 8px; }
        .num-input {
            width: 100%; padding: 12px;
            font-size: 1.2rem; text-align: right;
            border: 1px solid var(--border); border-radius: 6px;
            font-weight: bold;
        }

        /* 2. 配送・車両セクション（ここが復活！） */
        .delivery-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
        .form-label { display: block; font-size: 0.85rem; font-weight: bold; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 12px;
            font-size: 1rem;
            border: 1px solid var(--border); border-radius: 8px;
            background: white;
            appearance: none; /* iOS対策 */
        }

        /* 車両選択（チップ型） */
        .vehicle-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .v-radio { display: none; }
        .v-label {
            padding: 10px;
            text-align: center;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .v-radio:checked + .v-label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            font-weight: bold;
        }

        /* フッター */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: white;
            padding: 16px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            z-index: 90;
        }
        .total-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; }
        .total-label { font-size: 0.9rem; font-weight: bold; }
        .total-price { font-size: 1.4rem; font-weight: bold; color: var(--primary); }
        .submit-btn {
            width: 100%; padding: 16px;
            background: var(--primary); color: white;
            border: none; border-radius: 10px;
            font-size: 1.1rem; font-weight: bold;
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="site-name">虎ノ門3丁目工事</div>
        <div class="site-info">担当: 山田 太郎 | 林六担当: 佐藤</div>
    </header>

    <?php require_once 'inc.header.php'; ?>
<div class="container">

        <h2><i class="fas fa-box-open"></i> 商品選択</h2>
        
        <div class="card main-item">
            <div class="main-header">
                <span style="font-weight:bold; font-size:1.1rem;">OPフロー (標準)</span>
                <span class="badge">必須</span>
            </div>
            <div style="margin-bottom:10px; display:flex; gap:10px;">
                <label><input type="radio" name="op_type" checked> 標準</label>
                <label><input type="radio" name="op_type"> 粘性土用</label>
                <label><input type="radio" name="op_type"> 寒冷地用</label>
            </div>
            <div class="input-group">
                <input type="number" class="num-input" value="500" data-price="120" oninput="calc()">
                <span style="font-weight:bold;">kg</span>
            </div>
        </div>

        <div class="card">
            <div class="opt-row" onclick="toggle('opt1')" id="row-opt1">
                <div class="chk-box"><i class="fas fa-check"></i></div>
                <div style="flex:1;">
                    <div style="font-weight:bold;">ベントナイト20</div>
                    <div style="font-size:0.8rem; color:#64748b;">@1,500/袋</div>
                </div>
            </div>
            <div class="qty-area" id="area-opt1">
                <div class="input-group">
                    <input type="number" id="qty-opt1" class="num-input" placeholder="0" data-price="1500" oninput="calc()">
                    <span>袋</span>
                </div>
            </div>

            <div class="opt-row" onclick="toggle('opt2')" id="row-opt2">
                <div class="chk-box"><i class="fas fa-check"></i></div>
                <div style="flex:1;">
                    <div style="font-weight:bold;">遅延剤 (スロー)</div>
                    <div style="font-size:0.8rem; color:#64748b;">@200/kg</div>
                </div>
            </div>
            <div class="qty-area" id="area-opt2">
                <div class="input-group">
                    <input type="number" id="qty-opt2" class="num-input" placeholder="0" data-price="200" oninput="calc()">
                    <span>kg</span>
                </div>
            </div>
        </div>

        <h2><i class="fas fa-truck"></i> 配送・車両設定</h2>
        <div class="card" style="padding:16px;">
            
            <div class="delivery-grid">
                <div>
                    <label class="form-label">希望納期 <span style="color:red">*</span></label>
                    <input type="date" class="form-control" id="delDate">
                </div>
                <div>
                    <label class="form-label">時間帯</label>
                    <select class="form-control">
                        <option>指定なし</option>
                        <option>午前 (AM)</option>
                        <option>午後 (PM)</option>
                    </select>
                </div>
            </div>

            <label class="form-label">車両タイプ <span style="color:red">*</span></label>
            <div class="vehicle-list">
                <input type="radio" name="vehicle" id="v1" class="v-radio" checked>
                <label for="v1" class="v-label">平車 (4t)</label>

                <input type="radio" name="vehicle" id="v2" class="v-radio">
                <label for="v2" class="v-label">平車 (10t)</label>

                <input type="radio" name="vehicle" id="v3" class="v-radio">
                <label for="v3" class="v-label">ユニック車</label>

                <input type="radio" name="vehicle" id="v4" class="v-radio">
                <label for="v4" class="v-label">ローリー車</label>

                <input type="radio" name="vehicle" id="v5" class="v-radio">
                <label for="v5" class="v-label">路線便</label>

                <input type="radio" name="vehicle" id="v6" class="v-radio">
                <label for="v6" class="v-label">その他</label>
            </div>

            <div style="margin-top:12px;">
                <label class="form-label">備考</label>
                <textarea class="form-control" rows="2" placeholder="現場への進入指示など"></textarea>
            </div>
        </div>

    </div>

    <div class="footer">
        <div class="total-row">
            <span class="total-label">概算合計 (税抜)</span>
            <span class="total-price" id="totalDisplay">¥60,000</span>
        </div>
        <button class="submit-btn" onclick="alert('発注内容を送信します')">発注を確定する</button>
    </div>

    <script>
        // 開閉ロジック
        function toggle(id) {
            const row = document.getElementById('row-' + id);
            const area = document.getElementById('area-' + id);
            const input = document.getElementById('qty-' + id);

            // クラス切り替え
            const isActive = row.classList.toggle('active');
            
            if (isActive) {
                area.style.display = 'block';
                input.focus();
            } else {
                area.style.display = 'none';
                input.value = '';
            }
            calc();
        }

        // 計算ロジック
        function calc() {
            let total = 0;
            // 数値入力欄をすべて走査
            document.querySelectorAll('input[type="number"]').forEach(input => {
                // 親要素が表示されている（チェックされている）場合のみ計算
                // OPフローは常に表示
                if (input.offsetParent !== null && input.value) {
                    total += input.value * input.dataset.price;
                }
            });
            document.getElementById('totalDisplay').innerText = '¥' + total.toLocaleString();
        }

        // 初期ロード
        window.onload = function() {
            // 明後日の日付をセット
            const d = new Date();
            d.setDate(d.getDate() + 2);
            document.getElementById('delDate').valueAsDate = d;
            calc();
        };
    </script><?php require_once 'inc.footer.php'; ?>
</body>
</html>