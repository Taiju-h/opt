<?php
/**
 * Title: 取引先・担当者管理
 * Icon: fas fa-user-tie
 * Color: orange
 * Desc: 会社HP・ドメイン登録、メアド重複防止、リアルタイム検索機能付き。
 */
ini_set('session.gc_maxlifetime', 43200);
session_set_cookie_params(43200);

session_start();
require_once __DIR__ . '/../inc.db.php';

// 権限チェック
if (!isset($_SESSION['admin_role'])) { header("Location: index.php"); exit; }

$message = '';

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. 会社追加
    if (isset($_POST['action']) && $_POST['action'] === 'add_company') {
        try {
            $domain = str_replace('@', '', $_POST['email_domain']);
            $stmt = $pdo->prepare("INSERT INTO companies (name, address, website, email_domain) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['address'], $_POST['website'], $domain]);
            $message = "<div class='alert success'>会社「" . htmlspecialchars($_POST['name']) . "」を登録しました。</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "<div class='alert error'>エラー: その会社名と住所の組み合わせは既に登録されています。</div>";
            } else {
                $message = "<div class='alert error'>エラー: " . $e->getMessage() . "</div>";
            }
        }
    }

    // B. スタッフ追加
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_staff') {
        try {
            $stmt = $pdo->prepare("INSERT INTO staffs (company_id, name, email, position) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['company_id'], $_POST['name'], $_POST['email'], $_POST['position']]);
            $message = "<div class='alert success'>担当者「" . htmlspecialchars($_POST['name']) . "」を登録しました。</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "<div class='alert error'><strong>登録エラー:</strong><br>メールアドレス「" . htmlspecialchars($_POST['email']) . "」は既に登録されています。</div>";
            } else {
                $message = "<div class='alert error'>エラー: " . $e->getMessage() . "</div>";
            }
        }
    }

    // C. 削除
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
        $stmt = $pdo->prepare("UPDATE staffs SET is_active = 0 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = "<div class='alert success'>担当者を削除しました。</div>";
    }
}

// --- データ取得 ---
$companies = [];
$staffs = [];

try {
    $companies = $pdo->query("SELECT * FROM companies WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    
    // JSフィルタ用全件取得
    $staffs = $pdo->query("
        SELECT s.*, c.name as company_name, c.address as company_address
        FROM staffs s 
        JOIN companies c ON s.company_id = c.id 
        WHERE s.is_active = 1 
        ORDER BY c.id ASC, s.id ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $message = "<div class='alert error'>DBエラー: " . $e->getMessage() . "</div>";
}

$json_staffs = json_encode($staffs);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>取引先・担当者管理 | 林六システム</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏗️</text></svg>">
 <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #f97316; --bg: #f8fafc; --text: #334155; }
        body { font-family: "Hiragino Sans", sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        
        /* レイアウト調整: 左4 : 右6 */
        .grid-layout { display: grid; grid-template-columns: 4fr 6fr; gap: 30px; align-items: start; }
        .card { background: #fff7ed; padding: 20px; border-radius: 8px; border: 1px solid #fed7aa; }
        
        input, select { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }
        .btn-company { background: #ea580c; color: white; }
        .btn-staff { background: #2563eb; color: white; }
        
        /* スクロールリスト共通設定 */
        .scroll-list { list-style: none; padding: 0; margin: 0; max-height: 600px; overflow-y: auto; background: #fff; border-radius: 6px; border: 1px solid #e2e8f0; }
        .company-item { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; }
        .company-item:hover { background: #ffedd5; color: #c2410c; }
        .company-item.active { background: #fed7aa; font-weight: bold; color: #c2410c; border-left: 4px solid #ea580c; }

        .c-meta { display: block; font-size: 0.75rem; color: #9a3412; margin-top: 2px; }
        .c-domain { background:#e0f2fe; color:#0284c7; padding:1px 4px; border-radius:3px; margin-left:5px; font-size:0.75rem; }

        .flash-bg { animation: flash 1s; }
        @keyframes flash { 0% { background-color: #bfdbfe; } 100% { background-color: #eff6ff; } }
        
        /* 担当者テーブルのスタイル */
        .staff-table-container { margin-top: 20px; max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        
        /* ヘッダー固定 */
        th { background: #f1f5f9; color: #475569; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #cbd5e1; }
        
        .company-badge { background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        
        .domain-hint { font-size: 0.8rem; color: #2563eb; margin-top: -8px; margin-bottom: 10px; display: none; }
        .domain-hint.visible { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .filter-status { font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; display: none; margin-bottom: 10px; }
        .filter-status.active { display: inline-block; }
    </style>
</head>
<body>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <h1>👔 取引先・担当者マスタ</h1>
    <?= $message ?>
    <p style="font-size:0.9rem; color:#666; margin-bottom:20px;">
        左のリストから会社を選ぶと、右のフォームにセットされ、その下の担当者一覧が絞り込まれます。<br>
        <code>@</code> を入力するとドメインが補完されます。
    </p>

    <div class="grid-layout">
        
        <div>
            <div class="card" style="margin-bottom:20px;">
                <h2><i class="fas fa-building"></i> STEP 1: 会社登録</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_company">
                    <input type="text" name="name" placeholder="会社名 (例: 鹿島建設)" required>
                    <input type="text" name="address" placeholder="住所 (任意)">
                    <input type="text" name="website" placeholder="HP URL (任意)">
                    <div style="display:flex; align-items:center; gap:5px;">
                        <span style="font-weight:bold; color:#666;">@</span>
                        <input type="text" name="email_domain" placeholder="ドメイン (例: raito.co.jp)" style="margin-bottom:10px;">
                    </div>
                    <button type="submit" class="btn btn-company">会社を追加</button>
                </form>
            </div>
            
            <h3 style="border-bottom: 2px solid #fed7aa; padding-bottom:5px; margin-bottom:0;">登録済み会社リスト</h3>
            <ul class="company-list scroll-list">
                <?php foreach($companies as $c): ?>
                <li class="company-item" 
                    id="comp_<?= $c['id'] ?>"
                    onclick="selectCompany(<?= $c['id'] ?>, '<?= htmlspecialchars($c['email_domain'] ?? '') ?>', this)">
                    <div>
                        <span class="c-name" style="font-weight:bold;"><?= htmlspecialchars($c['name']) ?></span>
                        <?php if(!empty($c['email_domain'])): ?>
                            <span class="c-domain">@<?= htmlspecialchars($c['email_domain']) ?></span>
                        <?php endif; ?>
                        <?php if(!empty($c['website'])): ?>
                            <a href="<?= htmlspecialchars($c['website']) ?>" target="_blank" class="c-meta" onclick="event.stopPropagation();">
                                <i class="fas fa-external-link-alt"></i> HP
                            </a>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-arrow-circle-right" style="color:#cbd5e1;"></i>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div id="staffArea">
            <div class="card" id="staffCard" style="background:#eff6ff; border-color:#bfdbfe; margin-bottom: 20px;">
                <h2 style="color:#1d4ed8;"><i class="fas fa-user-plus"></i> STEP 2: 担当者登録</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <label style="font-size:0.9rem; font-weight:bold; color:#1e40af;">所属会社</label>
                    <select name="company_id" id="companySelect" required onchange="onCompanySelectChange()">
                        <option value="" data-domain="">左のリストから選択...</option>
                        <?php foreach($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" data-domain="<?= htmlspecialchars($c['email_domain'] ?? '') ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label style="font-size:0.9rem; font-weight:bold; color:#1e40af;">氏名</label>
                            <input type="text" name="name" id="staffName" placeholder="氏名" required oninput="filterStaffTable()">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:0.9rem; font-weight:bold; color:#1e40af;">役職</label>
                            <input type="text" name="position" placeholder="(任意)">
                        </div>
                    </div>
                    
                    <label style="font-size:0.9rem; font-weight:bold; color:#1e40af;">メールアドレス</label>
                    <input type="email" name="email" id="staffEmail" placeholder="メールアドレス" required oninput="checkAtMark(this)" autocomplete="off">
                    <div id="domainHint" class="domain-hint">
                        <i class="fas fa-magic"></i> <code>@</code> 入力で <strong><span id="hintText"></span></strong> を補完
                    </div>

                    <button type="submit" class="btn btn-staff">担当者を追加</button>
                </form>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <h3 style="margin:0;">📋 担当者一覧</h3>
                <span id="filterStatus" class="filter-status">
                    <i class="fas fa-filter"></i> <span id="filterText"></span>
                </span>
            </div>

            <div class="staff-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>所属会社</th>
                            <th>氏名 / 役職</th>
                            <th>メールアドレス</th>
                            <th style="width:50px;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    const allStaffs = <?= $json_staffs ?>;
    let currentCompanyId = '';
    let currentDomain = '';

    renderStaffTable();

    function selectCompany(id, domain, element) {
        document.querySelectorAll('.company-item').forEach(el => el.classList.remove('active'));
        if(element) element.classList.add('active');

        const select = document.getElementById('companySelect');
        select.value = id;
        currentDomain = domain;
        currentCompanyId = id;

        renderStaffTable();
        highlightCard();
        updateDomainHint();
        
        const nameInput = document.getElementById('staffName');
        nameInput.value = '';
        nameInput.focus();
    }

    function onCompanySelectChange() {
        const select = document.getElementById('companySelect');
        const selectedOption = select.options[select.selectedIndex];
        
        currentCompanyId = select.value;
        currentDomain = selectedOption.getAttribute('data-domain');
        
        document.querySelectorAll('.company-item').forEach(el => el.classList.remove('active'));
        const listItem = document.getElementById('comp_' + currentCompanyId);
        if(listItem) listItem.classList.add('active');

        renderStaffTable();
        updateDomainHint();
    }

    function filterStaffTable() {
        const nameInput = document.getElementById('staffName').value;
        renderStaffTable(nameInput);
    }

    function renderStaffTable(nameFilter = '') {
        const tbody = document.getElementById('staffTableBody');
        const statusSpan = document.getElementById('filterStatus');
        const statusText = document.getElementById('filterText');
        tbody.innerHTML = '';

        let filtered = allStaffs.filter(staff => {
            if (currentCompanyId && staff.company_id != currentCompanyId) return false;
            if (nameFilter && !staff.name.includes(nameFilter)) return false;
            return true;
        });

        if (currentCompanyId || nameFilter) {
            statusSpan.classList.add('active');
            let text = [];
            if (currentCompanyId) text.push('会社絞込');
            if (nameFilter) text.push(`検索:${nameFilter}`);
            statusText.innerText = text.join(' + ');
        } else {
            statusSpan.classList.remove('active');
        }

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">該当なし</td></tr>';
            return;
        }

        filtered.forEach(staff => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="company-badge">${escapeHtml(staff.company_name)}</span></td>
                <td><strong>${escapeHtml(staff.name)}</strong><br><span style="font-size:0.75rem; color:#666;">${escapeHtml(staff.position || '')}</span></td>
                <td>${escapeHtml(staff.email)}</td>
                <td>
                     <form method="POST" onsubmit="return confirm('削除しますか？')">
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="id" value="${staff.id}">
                        <button style="color:red; background:none; border:none; cursor:pointer;"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        if(!str) return '';
        return str.replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function updateDomainHint() {
        const hintDiv = document.getElementById('domainHint');
        const hintText = document.getElementById('hintText');
        if (currentDomain) {
            hintText.innerText = '@' + currentDomain;
            hintDiv.classList.add('visible');
        } else {
            hintDiv.classList.remove('visible');
        }
    }

    function checkAtMark(input) {
        if (currentDomain && input.value.endsWith('@')) {
            input.value = input.value + currentDomain;
        }
    }

    function highlightCard() {
        const card = document.getElementById('staffCard');
        card.classList.remove('flash-bg');
        void card.offsetWidth;
        card.classList.add('flash-bg');
    }
</script>

<?php require_once 'inc.footer.php'; ?>
</body>
</html>