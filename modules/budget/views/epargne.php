<?php
// modules/budget/views/epargne.php

$requestedOwner = $_GET['owner'] ?? 'Nens'; 
$ownersToDisplay = ($requestedOwner === 'Nens') ? ['Pol', 'Pep'] : [$requestedOwner];

$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// Récupération sécurisée du nom des mois (utilise les clés globales existantes)
function getMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="budget-view">
    <div class="view-header">
        <div class="owner-tabs">
            <a href="?tab=epargne&owner=Alex" class="owner-tab <?= $requestedOwner === 'Alex' ? 'active' : '' ?>">Alex</a>
            <a href="?tab=epargne&owner=Laia" class="owner-tab <?= $requestedOwner === 'Laia' ? 'active' : '' ?>">Laia</a>
            <a href="?tab=epargne&owner=Nens" class="owner-tab <?= $requestedOwner === 'Nens' ? 'active' : '' ?>">Nens 👶</a>
        </div>
    </div>

    <?php foreach ($ownersToDisplay as $currentOwner): 
        $stmt = $pdo->prepare("SELECT month_date, category, amount FROM pf_savings WHERE owner = ? ORDER BY month_date DESC");
        $stmt->execute([$currentOwner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $months = [];
        $allCategories = [];

        foreach ($rows as $row) {
            $m = $row['month_date'];
            $cat = $row['category'];
            $val = $row['amount'];
            $data[$m][$cat] = $val;
            if (!in_array($m, $months)) $months[] = $m;
            if ($cat !== 'TOTAL_BANQUE' && !in_array($cat, $allCategories)) $allCategories[] = $cat;
        }
        $months = array_slice($months, 0, 7); 
        sort($allCategories);

        // Définition de la classe couleur selon le propriétaire
        $ownerTextClass = '';
        if ($currentOwner === 'Alex') $ownerTextClass = 'txt-alex';
        elseif ($currentOwner === 'Laia') $ownerTextClass = 'txt-laia';
        else $ownerTextClass = 'txt-global'; // Pour Pol et Pep
    ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: <?= ($requestedOwner === 'Nens' && $currentOwner !== 'Pol') ? '40px' : '0' ?>;">        
        <div style="flex-grow: 1;">
            <?php if ($requestedOwner === 'Nens'): 
                $themeClass = 'theme-' . strtolower($currentOwner);
            ?>
                <h3 class="nens-title <?= $themeClass ?>" style="margin:0; font-size:1.2rem;">
                    <?= $currentOwner ?>
                </h3>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (!empty($months)): ?>
                <button onclick="duplicateLastMonth('<?= $months[0] ?>', '<?= $currentOwner ?>')" class="pf-btn btn-secondary">
                    🔁 <?= tr('bud_sav_add_one_month') ?>
                </button>
            <?php endif; ?>
            <button onclick="openCustomSavingsModal('<?= $currentOwner ?>')" class="pf-btn">
                ＋ <?= tr('bud_sav_add_month') ?>
            </button>
        </div>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0;">
        <?php if (empty($months)): ?>
            <div style="padding: 30px; text-align: center; color: #64748b;">
                <p><?= sprintf(tr('bud_sav_no_data'), htmlspecialchars($currentOwner)) ?></p>
            </div>
        <?php else: ?>
            <table class="pf-table savings-table nens-table theme-<?= strtolower($currentOwner) ?>" style="margin-top:0; box-shadow:none; border-radius:16px;">            
                <thead>
                    <tr>
                        <th class="sticky-col" style="background:#f8fafc;"><?= tr('bud_sav_post_month') ?></th>
                        <?php foreach ($months as $month): ?>
                            <th>
                                <div class="month-header-container" style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                    <div style="display:flex; flex-direction:column; text-align:center;">
                                        <span class="month-name" style="text-transform:capitalize;"><?= getMonthName($month) ?></span>
                                        <?php 
                                        if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                            $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                            echo "<span style='font-size:0.75rem; font-weight:normal; color:#64748b;'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</span>";
                                        }
                                        ?>
                                    </div>
                                    <div class="month-actions" style="justify-content: center; width: 100%;">
                                        <button class="btn-icon-small" title="<?= tr('bud_sav_edit_modal') ?>"
                                                data-json="<?= htmlspecialchars(json_encode($data[$month] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick='editCustomSavingsMonth("<?= $month ?>", "<?= $currentOwner ?>", JSON.parse(this.getAttribute("data-json")))'>
                                            ✏️
                                        </button>
                                        <button class="btn-icon-small" title="<?= tr('bud_sav_delete_month') ?>"
                                                onclick="deleteEntireMonth('<?= $month ?>', '<?= $currentOwner ?>')"
                                                style="color: #ef4444; border-color: #fca5a5; background: #fef2f2;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="sticky-col"><strong><?= tr('bud_sav_total_bank') ?></strong></td>
                        <?php foreach ($months as $month): 
                            $val = $data[$month]['TOTAL_BANQUE'] ?? 0;
                        ?>
                            <td class="text-center" style="padding:4px;">
                                <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                    <input type="number" step="0.01" 
                                           class="prev-input total-input-<?= $currentOwner ?>-<?= $month ?>" 
                                           style="width: 70px; font-weight:bold; color:#2563eb;"
                                           value="<?= $val != 0 ? round($val) : '' ?>" 
                                           placeholder="0"
                                           onchange="updateEpargneCell('<?= $month ?>', 'TOTAL_BANQUE', '<?= $currentOwner ?>', this)">
                                    <span style="color:#2563eb; font-weight:bold; font-size:0.9rem;">€</span>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($allCategories as $cat): ?>
                    <tr>
                        <td class="sticky-col"><?= htmlspecialchars($cat) ?></td>
                        <?php foreach ($months as $month): 
                            $amount = $data[$month][$cat] ?? 0; 
                        ?>
                            <td class="text-center" style="padding:4px;">
                                <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                    <input type="number" step="0.01" 
                                           class="prev-input <?= $ownerTextClass ?> cat-input-<?= $currentOwner ?>-<?= $month ?>" 
                                           style="width: 70px;"
                                           value="<?= $amount != 0 ? round($amount) : '' ?>" 
                                           placeholder="-"
                                           onchange="updateEpargneCell('<?= $month ?>', '<?= htmlspecialchars($cat, ENT_QUOTES) ?>', '<?= $currentOwner ?>', this)">
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="row-extres">
                        <td class="sticky-col"><strong><?= tr('bud_sav_extra') ?></strong></td>
                        <?php foreach ($months as $month): 
                            $total = $data[$month]['TOTAL_BANQUE'] ?? 0;
                            $sum = 0;
                            foreach ($allCategories as $cat) $sum += ($data[$month][$cat] ?? 0);
                            $extra = $total - $sum;
                        ?>
                            <td class="text-center font-bold sum-target" id="extra_<?= $currentOwner ?>_<?= $month ?>" style="color: <?= $extra >= 0 ? '#10b981' : '#ef4444' ?>; padding:12px;">
                                <?= number_format($extra, 0, ',', ' ') ?> €
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?> 
</div>

<div id="savingsModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px; width: 95%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="savingsModalTitle" class="pf-modal-title" style="margin:0;"><?= tr('bud_sav_modal_title_add') ?></h3>
            <button type="button" onclick="document.getElementById('savingsModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="/modules/budget/includes/api/save-savings.php" method="POST" id="savingsForm">
            <input type="hidden" name="owner" id="sav_owner">
            <input type="hidden" name="redirect_tab" id="redirect_tab" value="<?= htmlspecialchars($requestedOwner) ?>"> 
            <input type="hidden" name="month_date" id="sav_date_hidden">

            <div style="display:flex; gap:15px; margin-bottom:20px;">
                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label"><?= tr('bud_sav_month_concerned') ?></label>
                    <input type="month" id="sav_month" required class="pf-input">
                </div>

                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label"><?= tr('bud_sav_total_bank_eur') ?></label>
                    <input type="number" step="0.01" name="values[TOTAL_BANQUE]" id="sav_total" required class="pf-input no-spinners" style="font-weight:bold; color:#2563eb;">
                </div>
            </div>

            <div class="separator" style="margin: 20px 0; border-bottom: 1px solid #e2e8f0;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h4 style="margin:0; font-size:1rem; color:#1e293b;"><?= tr('bud_sav_ventilation') ?></h4>
                    <span style="font-size:0.8rem; color:#64748b;"><?= tr('bud_sav_adj_help') ?></span>
                </div>
                <button type="button" class="pf-btn btn-secondary" onclick="addCustomEpargneLine()" style="padding:4px 10px; height:auto; width:auto; font-size:0.9rem;">＋ <?= tr('bud_sav_add_line') ?></button>
            </div>

            <div style="display:flex; gap:10px; padding:0 5px 5px 5px; font-size:0.8rem; color:#64748b; font-weight:600;">
                <div style="flex:2;"><?= tr('bud_category') ?></div>
                <div style="width:100px;"><?= tr('bud_sav_current') ?></div>
                <div style="width:90px;"><?= tr('bud_sav_adjust') ?></div>
                <div style="width:100px;"><?= tr('bud_sav_new') ?></div>
                <div style="width:28px;"></div>
            </div>

            <div id="linesContainer" style="max-height: 350px; overflow-y: auto; padding-right:5px; display:flex; flex-direction:column; gap:10px;">
                </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('savingsModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary" style="width:auto; margin:0;"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<button id="fabSumMode" class="pf-fab-sum" onclick="toggleSumMode()" title="<?= tr('bud_sav_sum_mode_title') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="8" y1="12" x2="16" y2="12"></line><line x1="12" y1="8" x2="12" y2="16"></line></svg>
</button>

<div id="sumResultBar" class="pf-sum-bar">
    <span class="pf-sum-label"><?= tr('bud_sav_selection') ?></span>
    <span id="sumResultValue" class="pf-sum-value">0 €</span>
    <button onclick="toggleSumMode()" class="pf-sum-close" title="<?= tr('btn_close') ?>">&times;</button>
</div>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_sav_modal_title_add': "<?= tr('bud_sav_modal_title_add') ?>",
    'bud_sav_modal_title_edit': "<?= tr('bud_sav_modal_title_edit') ?>",
    'bud_sav_ph_name': "<?= tr('bud_sav_ph_name') ?>",
    'bud_sav_confirm_delete_month': "<?= tr('bud_sav_confirm_delete_month') ?>",
    'bud_sav_prompt_duplicate': "<?= tr('bud_sav_prompt_duplicate') ?>",
    'bud_err_tech': "<?= tr('bud_err_tech') ?>",
    'bud_err_server': "<?= tr('bud_err_server') ?>",
    'bud_err_network_dup': "<?= tr('bud_err_network_dup') ?>",
    'bud_sav_saving': "<?= tr('bud_sav_saving') ?>",
    'bud_err_delete': "<?= tr('bud_err_delete') ?>"
};

// --- 2. GESTION DE L'ÉDITION INVISIBLE EN DIRECT ---
const cycleConfigs = <?= json_encode($cycleConfigs ?? []) ?>;

function updateEpargneCell(month, category, owner, inputEl) {
    const val = parseFloat(inputEl.value) || 0;
    const formData = new FormData();
    formData.append('action', 'update_single_entry');
    formData.append('month_date', month);
    formData.append('category', category);
    formData.append('owner', owner);
    formData.append('amount', val);

    fetch('/modules/budget/includes/api/save-savings.php', {
        method: 'POST',
        body: formData
    }).catch(err => alert(tr("bud_err_tech")));

    const totalInput = document.querySelector(`.total-input-${owner}-${month}`);
    const totalVal = parseFloat(totalInput ? totalInput.value : 0) || 0;

    let sumCats = 0;
    document.querySelectorAll(`.cat-input-${owner}-${month}`).forEach(inp => {
        sumCats += parseFloat(inp.value) || 0;
    });

    const extra = totalVal - sumCats;
    const extraCell = document.getElementById(`extra_${owner}_${month}`);

    if (extraCell) {
        extraCell.innerText = Math.round(extra).toLocaleString(window.appLang) + ' €';
        extraCell.style.color = extra >= 0 ? '#10b981' : '#ef4444';
    }
    
    if(isSumModeActive) updateSumResult();
}

function addCustomEpargneLine(catName = '', amount = '') {
    const container = document.getElementById('linesContainer');
    const baseAmount = (amount !== '' && amount !== null) ? parseFloat(amount).toFixed(2) : '0.00';
    const inputName = catName ? `values[${catName}]` : '';

    const html = `
        <div class="ventilation-line" style="display:flex; gap:10px; align-items:center; background:#f8fafc; padding:8px; border-radius:8px; border:1px solid #e2e8f0;">
            <div style="flex:2;">
                <input type="text" class="pf-input cat-name-input" value="${catName}" placeholder="${tr("bud_sav_ph_name")}" oninput="updateCustomFieldName(this)" style="padding:6px; font-size:0.9rem;" required>
            </div>
            <div style="width:100px;">
                <input type="number" step="0.01" class="pf-input base-amount no-spinners" value="${baseAmount}" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; background:#fff;">
            </div>
            <div style="width:90px;">
                <input type="number" step="0.01" class="pf-input adjustment-amount no-spinners" placeholder="+ / -" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; color:#f59e0b; font-weight:bold;">
            </div>
            <div style="width:100px;">
                <input type="number" step="0.01" name="${inputName}" class="pf-input final-amount no-spinners" value="${baseAmount}" style="padding:6px; font-size:0.9rem; font-weight:bold; background:#e0f2fe; border-color:#bae6fd; color:#0369a1;" readonly>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="width:28px; height:28px; border:none; background:#fee2e2; color:#ef4444; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-weight:bold;">&times;</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function updateCustomFieldName(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const finalInput = line.querySelector('.final-amount');
    const newName = inputElement.value.trim();
    if (newName) finalInput.name = `values[${newName}]`;
    else finalInput.name = ''; 
}

function recalculateCustomLine(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const baseInput = line.querySelector('.base-amount');
    const adjInput = line.querySelector('.adjustment-amount');
    const finalInput = line.querySelector('.final-amount');
    
    const base = parseFloat(baseInput.value) || 0;
    const adj = parseFloat(adjInput.value) || 0;
    
    finalInput.value = (base + adj).toFixed(2);
}

function editCustomSavingsMonth(monthDate, owner, rowData) {
    document.getElementById('sav_owner').value = owner;
    const ym = monthDate.substring(0, 7);
    document.getElementById('sav_month').value = ym;
    
    const dateObj = new Date(monthDate);
    const monthName = dateObj.toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
    document.getElementById('savingsModalTitle').innerText = tr("bud_sav_modal_title_edit") + " " + monthName + " (" + owner + ")";
    
    document.getElementById('sav_total').value = rowData['TOTAL_BANQUE'] || '';

    const container = document.getElementById('linesContainer');
    container.innerHTML = '';

    for (const [cat, val] of Object.entries(rowData)) {
        if (cat !== 'TOTAL_BANQUE') addCustomEpargneLine(cat, val);
    }
    
    if (container.children.length === 0) addCustomEpargneLine();

    document.getElementById('savingsModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

function openCustomSavingsModal(owner) {
    document.getElementById('sav_owner').value = owner;
    document.getElementById('sav_month').value = '';
    document.getElementById('sav_total').value = '';
    
    document.getElementById('savingsModalTitle').innerText = tr("bud_sav_modal_title_add") + " (" + owner + ")";
    
    const container = document.getElementById('linesContainer');
    container.innerHTML = '';
    addCustomEpargneLine(); 
    
    document.getElementById('savingsModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

window.onclick = function(event) {
    const modal = document.getElementById('savingsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
        document.body.classList.remove('no-scroll');
    }
}

const savingsForm = document.getElementById('savingsForm');
if (savingsForm) {
    savingsForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        const ym = document.getElementById('sav_month').value;
        if(ym) {
            document.getElementById('sav_date_hidden').value = ym + '-01';
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = tr('bud_sav_saving');
        submitBtn.disabled = true;

        const formData = new FormData(this);

        fetch(this.action, { method: 'POST', body: formData })
        .then(response => response.text()) 
        .then(text => { window.location.reload(); })
        .catch(error => {
            console.error("Erreur:", error);
            alert(tr("bud_err_tech"));
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        });
    });
}

function deleteEntireMonth(monthDate, owner) {
    const msg = tr('bud_sav_confirm_delete_month').replace('%m', monthDate).replace('%o', owner);
    if (!confirm(msg)) return;
    
    const formData = new FormData();
    formData.append("action", "delete_month_global"); 
    formData.append("month_date", monthDate);
    formData.append("owner", owner);
    fetch("/modules/budget/includes/api/save-savings.php", { method: "POST", body: formData })
    .then(() => window.location.reload())
    .catch(err => alert(tr('bud_err_delete')));
}

function duplicateLastMonth(lastMonthDate, owner) {
    let dateObj = new Date(lastMonthDate);
    dateObj.setMonth(dateObj.getMonth() + 1);
    let year = dateObj.getFullYear();
    let month = String(dateObj.getMonth() + 1).padStart(2, '0');
    let nextMonthStr = `${year}-${month}-01`;

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(lastMonthDate); 
    const targetName = formatMonth(nextMonthStr); 

    let defaultTotal = "";
    if (cycleConfigs[nextMonthStr] && cycleConfigs[nextMonthStr].start_balance !== undefined) {
        defaultTotal = cycleConfigs[nextMonthStr].start_balance;
    }

    const message = tr('bud_sav_prompt_duplicate')
        .replace('%s', sourceName)
        .replace('%t1', targetName)
        .replace('%t2', targetName);

    let newTotal = prompt(message, defaultTotal);

    if (newTotal !== null && newTotal.trim() !== "") {
        const formData = new FormData();
        formData.append("action", "duplicate_month");
        formData.append("source_date", lastMonthDate);
        formData.append("target_date", nextMonthStr);
        formData.append("new_total", newTotal);
        formData.append("owner", owner);

        fetch("/modules/budget/includes/api/save-savings.php", { method: "POST", body: formData })
        .then(async r => {
            const text = await r.text(); 
            try {
                const d = JSON.parse(text); 
                if (d.success) window.location.reload();
                else alert(tr('bud_err_server') + (d.error || "Inconnue"));
            } catch(e) {
                window.location.reload();
            }
        })
        .catch(err => {
            console.error(err);
            alert(tr("bud_err_network_dup"));
        });
    }
}

// --- CALCULATRICE ---
let isSumModeActive = false;
let selectedElementsForSum = new Set();

function toggleSumMode() {
    isSumModeActive = !isSumModeActive;
    const fab = document.getElementById('fabSumMode');
    const resultBar = document.getElementById('sumResultBar');
    
    if (isSumModeActive) {
        fab.classList.add('active');
        document.body.classList.add('sum-mode-active');
        resultBar.classList.add('visible');
        updateSumResult();
    } else {
        fab.classList.remove('active');
        document.body.classList.remove('sum-mode-active');
        resultBar.classList.remove('visible');
        
        selectedElementsForSum.forEach(el => el.classList.remove('sum-selected'));
        selectedElementsForSum.clear();
    }
}

function extractNumberFromText(text) {
    if (!text) return 0;
    const cleanText = text.replace(',', '.').replace(/[^\d.-]/g, '');
    return parseFloat(cleanText) || 0;
}

function updateSumResult() {
    let total = 0;
    selectedElementsForSum.forEach(el => {
        let val = 0;
        if (el.tagName === 'INPUT') {
            val = parseFloat(el.value) || 0;
        } else {
            val = extractNumberFromText(el.innerText);
        }
        total += val;
    });
    
    document.getElementById('sumResultValue').innerText = new Intl.NumberFormat(window.appLang, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(total);
}

document.addEventListener('click', function(e) {
    if (!isSumModeActive) return;

    const targetElement = e.target.closest('input[type="number"], .sum-target');
    
    if (targetElement) {
        e.preventDefault(); 
        
        if (selectedElementsForSum.has(targetElement)) {
            selectedElementsForSum.delete(targetElement);
            targetElement.classList.remove('sum-selected');
        } else {
            selectedElementsForSum.add(targetElement);
            targetElement.classList.add('sum-selected');
        }
        
        updateSumResult();
    }
}, true); 
</script>