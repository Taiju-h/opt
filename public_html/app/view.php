<?php
// ★PHP側でパッケージ情報を容量順（大きい順）に並び替えておく（自動計算のため）
$js_packages = [];
foreach($products as $p) {
    $pid = $p['product_id'];
    $pkgs = $packages[$pid] ?? [];
    usort($pkgs, function($a, $b) { return $b['capacity_kg'] <=> $a['capacity_kg']; });
    $js_packages[$pid] = $pkgs;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>資材発注 | <?= h($site['name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* 横ブレ防止 */
        html, body { width: 100vw; max-width: 100%; overflow-x: hidden; margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        .container { width: 100%; max-width: 100%; overflow-x: hidden; padding: 15px; }
        
        .pkg-mode-selector { background: #f1f5f9; padding: 10px; border-radius: 6px; margin-bottom: 12px; display: flex; align-items: center; border: 1px solid #e2e8f0; }
        .mode-label { margin-right: 15px; font-size: 0.85rem; font-weight: bold; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: 0.2s; }
        .mode-label.active { color: #2563eb; }
        .packages-list { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; width: 100%; }
        
        .pkg-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; width: 100%; }
        .pkg-row:last-child { margin-bottom: 0; }
        .pkg-name { font-size: 0.95rem; font-weight: bold; color: #334155; margin-bottom: 2px; }
        .pkg-cap { font-size: 0.8rem; color: #64748b; }
        .pkg-calc { font-family: monospace; font-size: 0.9rem; color: #1e293b; flex: 1; min-width: 110px; display: flex; align-items: center; }
        
        .stepper { display: flex; align-items: center; border: 2px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: white; flex-shrink: 0; margin-left: auto; }
        .step-btn { background: #f1f5f9; border: none; width: 36px; height: 36px; font-size: 1.2rem; cursor: pointer; color: #3b82f6; font-weight: bold; transition: 0.1s; }
        .step-btn:active:not(:disabled) { background: #cbd5e1; }
        .stepper input { width: 40px; height: 36px; text-align: center; border: none; border-left: 2px solid #cbd5e1; border-right: 2px solid #cbd5e1; font-size: 1.1rem; font-weight: bold; color: #1e293b; padding: 0; }
        .stepper input:focus { outline: none; }
        .step-btn:disabled { opacity: 0.25; cursor: not-allowed; color: #94a3b8; }
        
        .mode-auto .stepper { border-color: #e2e8f0; }
        .mode-auto .stepper input { background: #f8fafc; color: #94a3b8; pointer-events: none; }
        
        .total-input-wrapper { background: white; padding: 15px; border-radius: 8px; border: 2px solid #94a3b8; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 100%; }
        .qty-controls { display: flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: wrap; }
        .qty-input { max-width: 90px; }
        .custom-badge { font-size: 1rem; font-weight: bold; color: #1e293b; background: #e2e8f0; padding: 6px 12px; border-radius: 6px; text-align: right; min-width: 80px; }
    </style>
</head>
<body>

<?= $message ?>

<div class="header">
    <div class="site-name"><?= h($site['name']) ?></div>
    <div class="manager-name">担当: <?= h($site['manager_name']) ?></div>
</div>

<?php require_once 'inc.header.php'; ?>
<div class="container">
    <div style="text-align:right; margin-bottom:15px;">
        <a href="history.php?token=<?= h($token) ?>" style="display:inline-block; background:#334155; color:white; padding:8px 15px; border-radius:20px; text-decoration:none; font-weight:bold; font-size:0.9rem;">
            <i class="fas fa-history"></i> 注文履歴 (<?= isset($stat['total_qty']) ? number_format($stat['total_qty']) : 0 ?> Qty)
        </a>
    </div>

    <form method="POST" id="orderForm">
        <input type="hidden" name="action" value="order">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        
        <div class="card-title" style="margin-bottom:10px;"><i class="fas fa-boxes"></i> 発注商品</div>
        
        <?php foreach($products as $p): 
            $pid = $p['product_id'];
            $raw_val = (float)$p['default_quantity'];
            $init_kg = ($raw_val > 0 && $raw_val < 100) ? $raw_val * 1000 : $raw_val;
        ?>
        <div class="product-item">
            <div class="product-name"><?= h($p['name']) ?></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; margin-bottom:10px;">
                <div class="product-meta"><?= h($p['series_name']) ?> / <?= h($p['unit']) ?></div>
                <div class="conv-tag" id="conv_<?= $pid ?>">0 t</div>
            </div>
            
            <div class="total-input-wrapper">
                <div class="qty-controls" style="margin-bottom:0;">
                    <button type="button" class="qty-btn btn-ton" onclick="changeTotal(<?= $pid ?>, -1000)">-1t</button>
                    <button type="button" class="qty-btn" onclick="changeTotal(<?= $pid ?>, -100)">-100k</button>
                    
                    <input type="number" name="qty[<?= $pid ?>]" id="total_qty_<?= $pid ?>" class="qty-input" value="<?= $init_kg ?>" step="10" onchange="handleTotalManualInput(<?= $pid ?>)" style="font-size:1.3rem; color:#1e293b;">
                    <span class="unit-label">kg</span>

                    <button type="button" class="qty-btn" onclick="changeTotal(<?= $pid ?>, 100)">+100k</button>
                    <button type="button" class="qty-btn btn-ton" onclick="changeTotal(<?= $pid ?>, 1000)">+1t</button>
                </div>
                <div style="text-align:center; font-size:0.8rem; color:#2563eb; margin-top:12px; font-weight:bold; letter-spacing:0.5px;">
                    <i class="fas fa-hand-pointer"></i> 枠内の数字をタップして直接入力できます
                </div>
            </div>
            
            <div class="pkg-mode-selector">
                <span style="font-size:0.8rem; color:#475569; margin-right:10px;"><i class="fas fa-box-open"></i> 荷姿配分:</span>
                <label class="mode-label active" id="lbl_auto_<?= $pid ?>">
                    <input type="radio" name="pkg_mode_sel[<?= $pid ?>]" value="auto" checked onchange="switchMode(<?= $pid ?>, 'auto')" style="display:none;">
                    <i class="fas fa-robot"></i> おまかせ
                </label>
                <label class="mode-label" id="lbl_manual_<?= $pid ?>">
                    <input type="radio" name="pkg_mode_sel[<?= $pid ?>]" value="manual" onchange="switchMode(<?= $pid ?>, 'manual')" style="display:none;">
                    <i class="fas fa-hand-pointer"></i> 小分けを指定
                </label>
            </div>

            <div class="packages-list mode-auto" id="pkg_area_<?= $pid ?>">
                <?php 
                $pkgs = $js_packages[$pid] ?? [];
                $pkg_index = 0;
                foreach($pkgs as $pkg): 
                    if($pkg['capacity_kg'] > 0):
                ?>
                    <div class="pkg-row">
                        <div style="flex: 1; min-width: 100px;">
                            <div class="pkg-name"><?= h($pkg['package_name']) ?></div>
                            <div class="pkg-cap">(<?= (float)$pkg['capacity_kg'] ?>kg/単位)</div>
                        </div>
                        
                        <div class="pkg-calc" id="calc_<?= $pid ?>_<?= $pkg['id'] ?>">
                            <span style="color:#cbd5e1;">-</span>
                        </div>

                        <div class="stepper">
                            <button type="button" class="step-btn" id="btn_minus_<?= $pid ?>_<?= $pkg['id'] ?>" onclick="stepPkg(<?= $pid ?>, <?= $pkg['id'] ?>, <?= $pkg_index ?>, -1)">-</button>
                            <input type="number" name="pkg_qty[<?= $pid ?>][<?= $pkg['id'] ?>]" id="pkg_<?= $pid ?>_<?= $pkg['id'] ?>" value="0" readonly>
                            <button type="button" class="step-btn" id="btn_plus_<?= $pid ?>_<?= $pkg['id'] ?>" onclick="stepPkg(<?= $pid ?>, <?= $pkg['id'] ?>, <?= $pkg_index ?>, 1)">+</button>
                            
                            <input type="hidden" name="pkg_cap[<?= $pid ?>][<?= $pkg['id'] ?>]" value="<?= $pkg['capacity_kg'] ?>">
                            <input type="hidden" name="pkg_name[<?= $pid ?>][<?= $pkg['id'] ?>]" value="<?= h($pkg['package_name']) ?>">
                        </div>
                    </div>
                <?php 
                    $pkg_index++;
                    endif; 
                endforeach; 
                ?>
                
                <div class="pkg-row" style="border-top:1px dashed #cbd5e1; padding-top:12px; margin-top:12px;">
                    <div style="flex: 1;">
                        <div class="pkg-name">指定なし <span class="pkg-cap">(端数・バラ)</span></div>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:5px; flex:1;">
                        <div class="custom-badge" id="custom_disp_<?= $pid ?>">0kg</div>
                        <input type="hidden" name="custom_kg[<?= $pid ?>]" id="custom_input_<?= $pid ?>" value="0">
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="card" style="margin-top:30px;">
            <div class="card-title" style="margin-bottom: 4px;"><i class="far fa-calendar-alt"></i> 配送希望日時</div>
            <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 12px 0; line-height: 1.4;">※納期はメーカーの配車状況等により、ご希望に添えない場合があります。</p>
            <div class="urgent-switch" style="margin-bottom: 12px;">
                <input type="checkbox" name="urgent_order" id="urgent_check" value="1">
                <label for="urgent_check">🔥 日時指定なし（最短手配）</label>
            </div>
            <div id="date_input_wrapper">
                <div id="urgent-alert" style="display:none; font-size: 0.85rem; color: #ea580c; font-weight: bold; margin-bottom: 10px; background: transparent; border: none; padding: 0;">
                    <i class="fas fa-exclamation-triangle"></i> 希望日が通常より早いため、ご期待に添えない可能性が高いです。
                </div>
                <div class="date-row">
                    <input type="date" name="date_1" id="date_picker_1" class="date-input" min="<?= $absolute_min_date ?>">
                    <select name="time_1" id="time_picker_1" class="time-select time-trigger"><?php renderTimeOptions(); ?></select>
                </div>
                <div style="text-align:right; font-size:0.8rem; color:#64748b; margin-top:5px;">通常納期: <span id="std_date_text"><?= $standard_lead_date ?> 以降</span></div>
            </div>

            <?php if(!empty($ng_times)): ?>
            <div class="ng-list-container">
                <div class="ng-list-title"><i class="fas fa-ban"></i> 現場の受入不可時間</div>
                <div style="font-size:0.8rem; color:#64748b; margin-bottom:10px;">※チェックを外すと、今回だけその時間の搬入が可能になります。</div>
                <?php foreach($ng_times as $idx => $ng): ?>
                <div class="ng-item" id="ng_row_<?= $idx ?>">
                    <input type="checkbox" class="ng-check" id="ng_check_<?= $idx ?>" data-start="<?= substr($ng['start_time'],0,5) ?>" data-end="<?= substr($ng['end_time'],0,5) ?>" checked>
                    <label for="ng_check_<?= $idx ?>" class="ng-label"><?= substr($ng['start_time'],0,5) ?> ～ <?= substr($ng['end_time'],0,5) ?></label>
                    <input type="hidden" name="ng_ignored[]" id="ng_val_<?= $idx ?>" value="" disabled>
                </div>
                <?php endforeach; ?>
                <div id="ng-warning-box" class="alert-box"><i class="fas fa-ban"></i> 指定時間は「受入不可時間」です。</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-truck"></i> 配送車両</div>
            <div class="form-group">
                <select name="vehicle" class="form-select" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
                    <?php foreach($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="vehicle_memo" placeholder="車両指定の理由・進入制限など (例: 進入路が狭いため4t車まで)" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 8px; font-size: 0.95rem; box-sizing: border-box;">
            </div>
        </div>

        <div class="card"><div class="card-title">備考</div><textarea name="memo" rows="3" placeholder="進入時の注意事項など"></textarea></div>
        
        <div class="footer-bar">
            <button type="submit" class="btn-main" onclick="return validateOrder()">注文を確定する</button>
        </div>
    </form>
</div>
<script>
    const packageData = <?= json_encode($js_packages) ?>;
    const dateMap = <?= json_encode($date_map) ?>;
    const baseLeadDays = <?= $site_lead_days ?>; 
    let standardLeadDate = "<?= $standard_lead_date ?>";
    
    const productLeadTimes = {
        <?php foreach($products as $p): 
            $pt = 'null';
            if (isset($p['lead_time_days']) && $p['lead_time_days'] !== '' && $p['lead_time_days'] !== null) {
                $pt = (int)$p['lead_time_days']; 
            } elseif (isset($p['master_lead']) && $p['master_lead'] !== '' && $p['master_lead'] !== null) {
                $pt = (int)$p['master_lead']; 
            }
        ?>
        "<?= $p['product_id'] ?>": <?= $pt ?>,
        <?php endforeach; ?>
    };

    const productNames = {
        <?php foreach($products as $p): ?>
        "<?= $p['product_id'] ?>": "<?= h($p['name']) ?>",
        <?php endforeach; ?>
    };

    function formatCombo(kg) {
        if (kg === 0) return "0kg";
        let str = kg.toLocaleString() + "kg";
        if (kg >= 1000) {
            let t = (kg / 1000).toFixed(2).replace(/\.?0+$/, '');
            str += ` <small style="color:#64748b;">(${t}t)</small>`;
        }
        return str;
    }

    function updateCalcDisplay(pid, pkgId, capacity, count) {
        let calcEl = document.getElementById(`calc_${pid}_${pkgId}`);
        if (calcEl) {
            if (count > 0) {
                let totalKg = capacity * count;
                calcEl.innerHTML = `${capacity.toLocaleString()}<small style="color:#94a3b8; margin:0 2px;">×</small>${count}<small style="color:#94a3b8; margin:0 2px;">=</small><span style="color:#e11d48; font-weight:bold; font-size:0.85rem;">${formatCombo(totalKg)}</span>`;
            } else {
                calcEl.innerHTML = `<span style="color:#cbd5e1;">-</span>`;
            }
        }
    }

    function changeTotal(pid, delta) {
        let input = document.getElementById('total_qty_' + pid);
        let val = parseFloat(input.value) || 0;
        val += delta;
        if (val < 0) val = 0;
        input.value = val;
        processTotalChange(pid, val);
    }

    function handleTotalManualInput(pid) {
        let input = document.getElementById('total_qty_' + pid);
        let val = parseFloat(input.value) || 0;
        if (val < 0) { val = 0; input.value = 0; }
        processTotalChange(pid, val);
    }

    function processTotalChange(pid, total) {
        updateTonBadge(pid, total);
        document.querySelector(`input[name="pkg_mode_sel[${pid}]"][value="auto"]`).checked = true;
        switchMode(pid, 'auto');
        recalcBreakdown(pid, -1);
        updateDynamicLeadTime();
    }

    function recalcBreakdown(pid, changedIndex) {
        let totalKg = parseFloat(document.getElementById('total_qty_' + pid).value) || 0;
        let pkgs = packageData[pid] || [];
        let usedKg = 0;
        
        for (let i = 0; i < pkgs.length; i++) {
            let pkg = pkgs[i];
            if (pkg.capacity_kg > 0) {
                let input = document.getElementById(`pkg_${pid}_${pkg.id}`);
                if (!input) continue;
                
                let maxAllowed = Math.floor((totalKg - usedKg) / pkg.capacity_kg);
                let val;
                
                if (i <= changedIndex) {
                    val = parseInt(input.value) || 0;
                    if (val > maxAllowed) val = maxAllowed;
                } else {
                    val = maxAllowed; 
                }
                
                input.value = val;
                usedKg += val * pkg.capacity_kg; 
                updateCalcDisplay(pid, pkg.id, pkg.capacity_kg, val);
                
                let btnMinus = document.getElementById(`btn_minus_${pid}_${pkg.id}`);
                let btnPlus = document.getElementById(`btn_plus_${pid}_${pkg.id}`);
                
                if (btnMinus) btnMinus.disabled = (val <= 0); 
                if (btnPlus) btnPlus.disabled = (val >= maxAllowed); 
            }
        }
        
        let remain = totalKg - usedKg;
        let customDisp = document.getElementById(`custom_disp_${pid}`);
        let customInput = document.getElementById(`custom_input_${pid}`);
        if(customDisp) customDisp.innerHTML = formatCombo(remain); 
        if(customInput) customInput.value = remain;
    }

    function stepPkg(pid, pkgId, pkgIndex, delta) {
        let area = document.getElementById('pkg_area_' + pid);
        
        if (area.classList.contains('mode-auto')) {
            document.querySelector(`input[name="pkg_mode_sel[${pid}]"][value="manual"]`).checked = true;
            switchMode(pid, 'manual');
        }
        
        let input = document.getElementById(`pkg_${pid}_${pkgId}`);
        if (!input) return;
        let val = parseInt(input.value) || 0;
        val += delta;
        if(val < 0) val = 0;
        input.value = val;
        
        recalcBreakdown(pid, pkgIndex);
    }

    function switchMode(pid, mode) {
        let area = document.getElementById('pkg_area_' + pid);
        let lblAuto = document.getElementById('lbl_auto_' + pid);
        let lblManual = document.getElementById('lbl_manual_' + pid);
        
        if (mode === 'auto') {
            area.classList.add('mode-auto');
            lblAuto.classList.add('active');
            lblManual.classList.remove('active');
            recalcBreakdown(pid, -1);
        } else {
            area.classList.remove('mode-auto');
            lblManual.classList.add('active');
            lblAuto.classList.remove('active');
        }
    }

    function updateTonBadge(pid, kg) {
        const tonBox = document.getElementById(`conv_${pid}`);
        if (tonBox) {
            const tons = (kg / 1000).toFixed(2); 
            tonBox.innerText = '≒ ' + tons + ' t';
            tonBox.style.background = kg > 0 ? "#3b82f6" : "#64748b";
        }
    }

    document.querySelectorAll('.qty-input').forEach(el => {
        let pid = el.id.replace('total_qty_', '');
        let total = parseFloat(el.value) || 0;
        processTotalChange(pid, total); 
    });

    const urgentCheck = document.getElementById('urgent_check');
    const dateWrapper = document.getElementById('date_input_wrapper');
    const urgentAlert = document.getElementById('urgent-alert');
    const ngWarning = document.getElementById('ng-warning-box');

    function updateDynamicLeadTime() {
        let maxDays = baseLeadDays;
        let bottleneckProductName = ""; 
        
        document.querySelectorAll('.qty-input').forEach(input => {
            let kg = parseFloat(input.value) || 0;
            if(kg > 0) {
                let pid = input.id.replace('total_qty_', '');
                let pt = productLeadTimes[pid];
                if(pt !== null && pt > maxDays) {
                    maxDays = pt;
                    bottleneckProductName = productNames[pid];
                }
            }
        });
        
        let newLeadDate = dateMap[maxDays] || dateMap[60];
        
        if (standardLeadDate !== newLeadDate || !window.isInitLeadTimeDone) {
            standardLeadDate = newLeadDate;
            window.isInitLeadTimeDone = true;

            let stdText = document.getElementById('std_date_text');
            if(stdText) {
                let msg = newLeadDate + " 以降";
                if (bottleneckProductName) {
                    msg += ` <span style="color:#e11d48; font-weight:bold;">(※${bottleneckProductName} の納期適用)</span>`;
                }
                stdText.innerHTML = msg;
            }

            let picker = document.getElementById('date_picker_1');
            if (picker && picker.value < newLeadDate && picker.value !== "") {
                picker.value = newLeadDate;
                picker.style.backgroundColor = "#ffe4e6";
                picker.style.transition = "background-color 0.5s";
                setTimeout(() => { picker.style.backgroundColor = ""; }, 800);
            }
            checkConstraints(); 
        }
    }

    function getActiveNgTimes() {
        const activeNg = [];
        document.querySelectorAll('.ng-check:checked').forEach(el => {
            activeNg.push({ start: el.dataset.start, end: el.dataset.end });
        });
        return activeNg;
    }

    function checkConstraints() {
        if(urgentCheck.checked) {
            if(urgentAlert) urgentAlert.style.display = 'none';
            if(ngWarning) ngWarning.style.display = 'none';
            return;
        }
        const d1 = document.getElementById('date_picker_1').value;
        if(urgentAlert) {
            urgentAlert.style.display = (d1 && d1 < standardLeadDate) ? 'block' : 'none';
        }
        let hasConflict = false;
        const currentNgTimes = getActiveNgTimes();
        const t = document.getElementById('time_picker_1').value;

        if(t && t !== '指定なし') {
            let ranges = (t==='午前')?[['08:00','12:00']]:(t==='午後')?[['13:00','17:00']]:[[t,t]];
            currentNgTimes.forEach(ng => {
                ranges.forEach(r => { if (r[0] < ng.end && r[1] > ng.start) hasConflict = true; });
            });
        }
        if(ngWarning) ngWarning.style.display = hasConflict ? 'block' : 'none';
    }

    document.querySelectorAll('.time-trigger, .date-input').forEach(el => { el.addEventListener('change', checkConstraints); });
    document.querySelectorAll('.ng-check').forEach(el => {
        el.addEventListener('change', function() {
            const row = this.closest('.ng-item');
            const hiddenInput = document.getElementById(this.id.replace('ng_check_', 'ng_val_'));
            if(this.checked) {
                row.classList.remove('inactive');
                hiddenInput.disabled = true;
            } else {
                row.classList.add('inactive');
                hiddenInput.value = this.dataset.start + '～' + this.dataset.end;
                hiddenInput.disabled = false;
            }
            checkConstraints();
        });
    });

    urgentCheck.addEventListener('change', function() {
        const picker = document.getElementById('date_picker_1');
        const timer = document.getElementById('time_picker_1');
        if(this.checked) {
            dateWrapper.style.opacity = '0.3';
            dateWrapper.style.pointerEvents = 'none';
            picker.disabled = true;
            timer.disabled = true;
            checkConstraints(); 
        } else {
            dateWrapper.style.opacity = '1';
            dateWrapper.style.pointerEvents = 'auto';
            picker.disabled = false;
            timer.disabled = false;
            checkConstraints();
        }
    });

    // ★修正：空欄なら自動で最短手配になるマジックを実装
    function validateOrder() {
        let total = 0;
        document.querySelectorAll('.qty-input').forEach(i => total += parseFloat(i.value) || 0);
        if (total <= 0) { alert('数量がすべて0kgです。資材の数を選択してください。'); return false; }
        
        const picker = document.getElementById('date_picker_1');
        const urgent = document.getElementById('urgent_check');
        
        // 日付が空欄なら、システムが勝手に「最短手配」のフラグを立ててあげる！
        if (!picker.value) {
            urgent.checked = true;
        }

        return confirm('この内容で注文を確定しますか？\n※納期はメーカーの配車状況等により、ご希望に添えない場合があります。');
    }
</script>
</body>
</html>