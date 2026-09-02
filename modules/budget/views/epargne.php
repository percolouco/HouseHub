<?php
// modules/budget/views/epargne.php

// --- 🧠 CONFIGURATION AGNOSTIQUE DES MEMBRES (MULTI-TENANT) ---
$stmtPeople = $pdo->query("SELECT name, role, color FROM pf_people ORDER BY id ASC");
$familyParents = [];
$familyKids = [];
$personColors = [];

while ($row = $stmtPeople->fetch(PDO::FETCH_ASSOC)) {
    $role = strtolower(trim($row['role'] ?? ''));
    $personColors[$row['name']] = $row['color'] ?? '#3b82f6';
    
    if ($role === 'parent') {
        $familyParents[] = $row['name'];
    } elseif ($role === 'nounou' || $role === 'helper') {
        continue;
    } else {
        $familyKids[] = $row['name'];
    }
}

if (empty($familyParents)) $familyParents = ['Parent 1', 'Parent 2'];

$requestedOwner = $_GET['owner'] ?? (!empty($familyKids) ? 'KIDS' : ($familyParents[0] ?? 'KIDS')); 
$ownersToDisplay = ($requestedOwner === 'KIDS') ? $familyKids : [$requestedOwner];

// --- RÉCUPÉRATION CONFIGURATION DES MOIS ---
$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

function getMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="budget-view">
    <div class="view-header">
        <div class="owner-tabs">
            <?php if (!empty($familyKids)): ?>
            <a href="?tab=epargne&owner=KIDS" class="owner-tab <?= $requestedOwner === 'KIDS' ? 'active' : '' ?>">
                <?= tr('budget_tab_kids') ?>
            </a>
            <?php endif; ?>

            <?php foreach ($familyParents as $p): ?>
                <a href="?tab=epargne&owner=<?= urlencode($p) ?>" class="owner-tab <?= $requestedOwner === $p ? 'active' : '' ?>">
                    <?= htmlspecialchars($p) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ($ownersToDisplay as $index => $currentOwner): 
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

        $safeOwnerCls = htmlspecialchars(str_replace(' ', '_', $currentOwner), ENT_QUOTES);
        $ownerColor = $personColors[$currentOwner] ?? '#1e293b';
    ?>

    <div class="epargne-header-bar <?= ($requestedOwner === 'KIDS' && $index > 0) ? 'pf-mt-40' : '' ?>">        
        <div class="pf-flex-1">
            <?php if ($requestedOwner === 'KIDS'): ?>
                <h3 class="nens-title" style="--owner-color: <?= htmlspecialchars($ownerColor) ?>;">
                    <?= htmlspecialchars($currentOwner) ?>
                </h3>
            <?php endif; ?>
        </div>

        <div class="pf-flex-gap-12-center">
            <?php if (!empty($months)): ?>
                <button onclick="duplicateLastMonth('<?= $months[0] ?>', '<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>')" class="pf-btn btn-secondary">
                    🔁 <?= tr('bud_sav_add_one_month') ?>
                </button>
                <button onclick="promptNewSavingsLine('<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>', '<?= $months[0] ?>')" class="pf-btn">
                    ＋ <?= tr('bud_sav_new_line') ?>
                </button>
            <?php else: ?>
                <button onclick="openCustomSavingsModal('<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>')" class="pf-btn">
                    ＋ <?= tr('bud_sav_add_month') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive pf-card-epargne">
        <?php if (empty($months)): ?>
            <div class="pf-empty-dashed">
                <p><?= sprintf(tr('bud_sav_no_data'), htmlspecialchars($currentOwner)) ?></p>
            </div>
        <?php else: ?>
            <div class="epargne-grid-table" style="--cols: <?= count($months) ?>; --owner-color: <?= htmlspecialchars($ownerColor) ?>;">
                
                <div class="eg-header-row">
                    <div class="eg-cell eg-sticky"><?= tr('bud_sav_post_month') ?></div>
                    <?php foreach ($months as $month): ?>
                        <div class="eg-cell">
                            <div class="month-header-container">
                                <div class="month-header-title">
                                    <span class="month-name text-inherit"><?= getMonthName($month) ?></span>
                                    <?php 
                                    if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                        $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                        echo "<span class='month-cycle-start'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</span>";
                                    }
                                    ?>
                                </div>
                                <div class="month-actions">
                                    <button class="btn-icon-small btn-safe-click" title="<?= tr('bud_sav_edit_modal') ?>"
                                            data-json="<?= htmlspecialchars(json_encode($data[$month] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                            onclick='editCustomSavingsMonth("<?= $month ?>", "<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>", JSON.parse(this.getAttribute("data-json")))'>
                                        ✏️
                                    </button>
                                    <button class="btn-icon-small btn-safe-click btn-danger-soft" title="<?= tr('bud_sav_delete_month') ?>"
                                            onclick="deleteEntireMonth('<?= $month ?>', '<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>')">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="eg-row eg-total-row">
                    <div class="eg-cell eg-sticky"><strong><?= tr('bud_sav_total_bank') ?></strong></div>
                    <?php foreach ($months as $month): 
                        $val = $data[$month]['TOTAL_BANQUE'] ?? 0;
                    ?>
                        <div class="eg-cell">
                            <div class="eg-cell-content">
                                <input type="number" step="0.01" 
                                       class="prev-input eg-input eg-input-total total-input-<?= $safeOwnerCls ?>-<?= $month ?>" 
                                       value="<?= $val != 0 ? round($val) : '' ?>" 
                                       placeholder="0"
                                       onchange="updateEpargneCell('<?= $month ?>', 'TOTAL_BANQUE', '<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>', this)">
                                <span class="eg-currency-symbol">€</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($allCategories as $cat): ?>
                <div class="eg-row">
                    <div class="eg-cell eg-sticky"><?= htmlspecialchars($cat) ?></div>
                    <?php foreach ($months as $month): 
                        $amount = $data[$month][$cat] ?? 0; 
                    ?>
                        <div class="eg-cell">
                            <input type="number" step="0.01" 
                                   class="prev-input eg-input cat-input-<?= $safeOwnerCls ?>-<?= $month ?>" 
                                   value="<?= $amount != 0 ? round($amount) : '' ?>" 
                                   placeholder="-"
                                   onchange="updateEpargneCell('<?= $month ?>', '<?= htmlspecialchars($cat, ENT_QUOTES) ?>', '<?= htmlspecialchars($currentOwner, ENT_QUOTES) ?>', this)">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <div class="eg-row eg-extres-row">
                    <div class="eg-cell eg-sticky"><strong><?= tr('bud_sav_extra') ?></strong></div>
                    <?php foreach ($months as $month): 
                        $total = $data[$month]['TOTAL_BANQUE'] ?? 0;
                        $sum = 0;
                        foreach ($allCategories as $cat) $sum += ($data[$month][$cat] ?? 0);
                        $extra = $total - $sum;
                    ?>
                        <div class="eg-cell font-bold sum-target <?= $extra >= 0 ? 'text-success' : 'text-danger' ?>" id="extra_<?= $safeOwnerCls ?>_<?= $month ?>">
                            <?= number_format($extra, 0, ',', ' ') ?> €
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?> 
</div>

<div id="savingsModal" class="pf-modal">
    <div class="pf-modal-content modal-epargne">
        <div class="pf-modal-header">
            <h3 id="savingsModalTitle" class="pf-modal-title"><?= tr('bud_sav_modal_title_add') ?></h3>
            <button type="button" onclick="document.getElementById('savingsModal').classList.remove('open'); document.body.classList.remove('no-scroll');" class="pf-modal-close">&times;</button>
        </div>
        
        <form action="/modules/budget/includes/api/save-savings.php" method="POST" id="savingsForm">
            <input type="hidden" name="owner" id="sav_owner">
            <input type="hidden" name="redirect_tab" id="redirect_tab" value="<?= htmlspecialchars($requestedOwner) ?>"> 
            <input type="hidden" name="month_date" id="sav_date_hidden">

            <div class="pf-form-row">
                <div class="pf-form-group pf-flex-1 pf-m-0">
                    <label class="pf-label"><?= tr('bud_sav_month_concerned') ?></label>
                    <input type="month" id="sav_month" required class="pf-input">
                </div>

                <div class="pf-form-group pf-flex-1 pf-m-0">
                    <label class="pf-label"><?= tr('bud_sav_total_bank_eur') ?></label>
                    <input type="number" step="0.01" name="values[TOTAL_BANQUE]" id="sav_total" required class="pf-input no-spinners pf-input-total">
                </div>
            </div>

            <hr class="pf-divider">
            
            <div class="ventilation-header">
                <div>
                    <h4 class="pf-m-0 text-main"><?= tr('bud_sav_ventilation') ?></h4>
                    <span class="pf-muted-note"><?= tr('bud_sav_adj_help') ?></span>
                </div>
                <button type="button" class="pf-btn btn-secondary pf-btn-sm" onclick="addCustomEpargneLine()">＋ <?= tr('bud_sav_add_line') ?></button>
            </div>

            <div class="ventilation-cols">
                <div class="vl-col-name"><?= tr('bud_category') ?></div>
                <div class="vl-col-base"><?= tr('bud_sav_current') ?></div>
                <div class="vl-col-adj"><?= tr('bud_sav_adjust') ?></div>
                <div class="vl-col-final"><?= tr('bud_sav_new') ?></div>
                <div class="vl-col-btn"></div>
            </div>

            <div id="linesContainer" class="ventilation-list"></div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('savingsModal').classList.remove('open'); document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
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
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_sav_modal_title_add': <?= json_encode(tr('bud_sav_modal_title_add')) ?>,
    'bud_sav_modal_title_edit': <?= json_encode(tr('bud_sav_modal_title_edit')) ?>,
    'bud_sav_ph_name': <?= json_encode(tr('bud_sav_ph_name')) ?>,
    'bud_sav_confirm_delete_month': <?= json_encode(tr('bud_sav_confirm_delete_month')) ?>,
    'bud_sav_prompt_duplicate': <?= json_encode(tr('bud_sav_prompt_duplicate')) ?>,
    'bud_sav_prompt_new_line': <?= json_encode(tr('bud_sav_prompt_new_line')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'bud_err_server': <?= json_encode(tr('bud_err_server')) ?>,
    'bud_err_network_dup': <?= json_encode(tr('bud_err_network_dup')) ?>,
    'bud_sav_saving': <?= json_encode(tr('bud_sav_saving')) ?>,
    'bud_err_delete': <?= json_encode(tr('bud_err_delete')) ?>,
    'btn_delete': <?= json_encode(tr('btn_delete')) ?>
};

const systemCurrency = (typeof window.CONFIG !== 'undefined' && window.CONFIG.CURRENCY) ? window.CONFIG.CURRENCY : '€';
const cycleConfigs = <?= json_encode($cycleConfigs ?? []) ?>;

async function promptNewSavingsLine(owner, latestMonth) {
    const catName = prompt(window.I18N['bud_sav_prompt_new_line']);
    if (!catName || catName.trim() === "") return;

    const formData = new FormData();
    formData.append('action', 'update_single_entry');
    formData.append('month_date', latestMonth);
    formData.append('category', catName.trim());
    formData.append('owner', owner);
    formData.append('amount', 0);
    formData.append('force_insert', '1'); // 🛡️ Empêche la suppression auto par l'API
    formData.append('ajax', '1');

    try {
        const result = await pachaFetch('/modules/budget/includes/api/save-savings.php', {
            method: 'POST',
            body: formData
        });

        if (result.success) {
            window.location.reload();
        } else {
            alert(window.I18N['bud_err_tech'] + " : " + (result.error || "Erreur"));
        }
    } catch (err) {
        alert(window.I18N['bud_err_tech']);
    }
}

async function updateEpargneCell(month, category, owner, inputEl) {
    const val = parseFloat(inputEl.value) || 0;
    const formData = new FormData();
    formData.append('action', 'update_single_entry');
    formData.append('month_date', month);
    formData.append('category', category);
    formData.append('owner', owner);
    formData.append('amount', val);
    formData.append('ajax', '1');

    try {
        const result = await pachaFetch('/modules/budget/includes/api/save-savings.php', { method: 'POST', body: formData });
        if (!result.success) {
            alert(window.I18N['bud_err_tech'] + " : " + (result.error || "Erreur serveur"));
            return;
        }
    } catch (err) {
        alert(window.I18N['bud_err_tech']);
        return;
    }

    const safeOwnerClass = owner.replace(/\s+/g, '_');
    const totalInput = document.querySelector(`.total-input-${CSS.escape(safeOwnerClass)}-${month}`);
    const totalVal = parseFloat(totalInput ? totalInput.value : 0) || 0;

    let sumCats = 0;
    document.querySelectorAll(`.cat-input-${CSS.escape(safeOwnerClass)}-${month}`).forEach(inp => {
        sumCats += parseFloat(inp.value) || 0;
    });

    const extra = totalVal - sumCats;
    const extraCell = document.getElementById(`extra_${safeOwnerClass}_${month}`);

    if (extraCell) {
        extraCell.innerText = Math.round(extra).toLocaleString(window.appLang) + ' €';
        if (extra >= 0) {
            extraCell.classList.remove('text-danger');
            extraCell.classList.add('text-success');
        } else {
            extraCell.classList.remove('text-success');
            extraCell.classList.add('text-danger');
        }
    }
    
    if(isSumModeActive) updateSumResult();
}

function addCustomEpargneLine(catName = '', amount = '') {
    const container = document.getElementById('linesContainer');
    const baseAmount = (amount !== '' && amount !== null) ? parseFloat(amount).toFixed(2) : '0.00';
    const inputName = catName ? `values[${catName}]` : '';

    const html = `
        <div class="ventilation-line">
            <div class="vl-col-name">
                <input type="text" class="pf-input cat-name-input pf-input-sm" value="${catName}" placeholder="${window.I18N['bud_sav_ph_name'] || 'Catégorie'}" oninput="updateCustomFieldName(this)" required>
            </div>
            <div class="vl-col-base">
                <input type="number" step="0.01" class="pf-input base-amount no-spinners pf-input-sm bg-white" value="${baseAmount}" oninput="recalculateCustomLine(this)">
            </div>
            <div class="vl-col-adj">
                <input type="number" step="0.01" class="pf-input adjustment-amount no-spinners pf-input-sm vl-input-adj" placeholder="+ / -" oninput="recalculateCustomLine(this)">
            </div>
            <div class="vl-col-final">
                <input type="number" step="0.01" name="${inputName}" class="pf-input final-amount no-spinners pf-input-sm vl-input-final" value="${baseAmount}" readonly>
            </div>
            <button type="button" class="btn-remove-line" onclick="this.parentElement.remove()" title="${window.I18N['btn_delete']}">&times;</button>
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
    document.getElementById('savingsModalTitle').innerText = (window.I18N['bud_sav_modal_title_edit'] || 'Editer') + " " + monthName + " (" + owner + ")";
    
    document.getElementById('sav_total').value = rowData['TOTAL_BANQUE'] || '';

    const container = document.getElementById('linesContainer');
    container.innerHTML = '';

    for (const [cat, val] of Object.entries(rowData)) {
        if (cat !== 'TOTAL_BANQUE') addCustomEpargneLine(cat, val);
    }
    
    if (container.children.length === 0) addCustomEpargneLine();

    document.getElementById('savingsModal').classList.add('open');
    document.body.classList.add('no-scroll');
}

function openCustomSavingsModal(owner) {
    document.getElementById('sav_owner').value = owner;
    document.getElementById('sav_month').value = '';
    document.getElementById('sav_total').value = '';
    
    document.getElementById('savingsModalTitle').innerText = (window.I18N['bud_sav_modal_title_add'] || 'Ajouter') + " (" + owner + ")";
    
    const container = document.getElementById('linesContainer');
    container.innerHTML = '';
    addCustomEpargneLine(); 
    
    document.getElementById('savingsModal').classList.add('open');
    document.body.classList.add('no-scroll');
}

const savingsForm = document.getElementById('savingsForm');
if (savingsForm) {
    savingsForm.addEventListener('submit', async function(e) {
        e.preventDefault(); 
        
        const ym = document.getElementById('sav_month').value;
        if(ym) {
            document.getElementById('sav_date_hidden').value = ym + '-01';
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = window.I18N['bud_sav_saving'] || 'Sauvegarde...';
        submitBtn.disabled = true;

        const formData = new FormData(this);
        formData.append('ajax', '1');

        try {
            const result = await pachaFetch('/modules/budget/includes/api/save-savings.php', { method: 'POST', body: formData });
            if (result.success) {
                window.location.reload(); 
            } else {
                alert((window.I18N['bud_err_server'] || 'Erreur serveur : ') + (result.error || "Inconnue"));
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            alert(window.I18N['bud_err_tech']);
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    });
}

async function deleteEntireMonth(monthDate, owner) {
    const rawMsg = window.I18N['bud_sav_confirm_delete_month'];
    const msg = rawMsg.replace('%m', monthDate).replace('%o', owner);
    if (!confirm(msg)) return;
    
    const formData = new FormData();
    formData.append("action", "delete_month_global"); 
    formData.append("month_date", monthDate);
    formData.append("owner", owner);
    formData.append("ajax", "1");
    
    try {
        const result = await pachaFetch('/modules/budget/includes/api/save-savings.php', { method: "POST", body: formData });
        if (result.success) window.location.reload();
        else alert(result.error || window.I18N['bud_err_delete']);
    } catch(err) {
        alert(window.I18N['bud_err_delete']);
    }
}

async function duplicateLastMonth(lastMonthDate, owner) {
    let dateObj = new Date(lastMonthDate);
    dateObj.setMonth(dateObj.getMonth() + 1);
    let year = dateObj.getFullYear();
    let month = String(dateObj.getMonth() + 1).padStart(2, '0');
    let nextMonthStr = `${year}-${month}-01`;

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    let defaultTotal = "";
    if (cycleConfigs[nextMonthStr] && cycleConfigs[nextMonthStr].start_balance !== undefined) {
        defaultTotal = cycleConfigs[nextMonthStr].start_balance;
    }

    const rawMsg = window.I18N['bud_sav_prompt_duplicate'];
    const message = rawMsg.replace('%s', formatMonth(lastMonthDate)).replace(/%t[12]/g, formatMonth(nextMonthStr));

    let newTotal = prompt(message, defaultTotal);

    if (newTotal !== null && newTotal.trim() !== "") {
        const formData = new FormData();
        formData.append("action", "duplicate_month");
        formData.append("source_date", lastMonthDate);
        formData.append("target_date", nextMonthStr);
        formData.append("new_total", newTotal);
        formData.append("owner", owner);
        formData.append("ajax", "1");

        try {
            const result = await pachaFetch('/modules/budget/includes/api/save-savings.php', { method: "POST", body: formData });
            if (result.success) {
                window.location.reload();
            } else {
                alert((window.I18N['bud_err_server'] || 'Erreur serveur : ') + (result.error || "Inconnue"));
            }
        } catch(err) {
            alert(window.I18N['bud_err_network_dup']);
        }
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
    
    document.getElementById('sumResultValue').innerText = Math.round(total).toLocaleString(window.appLang) + ' ' + systemCurrency;
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