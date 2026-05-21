<?php
// modules/budget/views/budget_prev.php

// 1. Chargement dynamique des parents
$stmtPeople = $pdo->query("SELECT id, name, user_id, role, color FROM pf_people WHERE role = 'parent' ORDER BY id ASC");
$budgetParents = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

$p1_name = $budgetParents[0]['name'] ?? 'Parent 1';
$p2_name = $budgetParents[1]['name'] ?? 'Parent 2';
$currentYear = date('Y');

// Cartographie dynamique
$parentMapping = [];
foreach ($budgetParents as $index => $parent) {
    $num = $index + 1;
    $parentMapping[] = [
        'id'        => (int)$parent['id'],
        'name'      => $parent['name'],
        'db_field'  => 'amount_p' . $num,
        'css'       => 'p' . $num,
        'color'     => $parent['color'] ?? (($num === 1) ? '#0891b2' : '#f59e0b')
    ];
}

// 2. Récupération Config Salaires
$salaryConfig = [];
$stmt = $pdo->prepare("SELECT * FROM pf_salary_config WHERE year = ?");
$stmt->execute([$currentYear]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $salaryConfig[$row['person']] = $row;
}

// Sécurité profils de salaire vides
foreach ($parentMapping as $map) {
    if (!isset($salaryConfig[$map['name']])) {
        $salaryConfig[$map['name']] = ['salary'=>0, 'mensualite'=>0, 'frais_func'=>0, 'eco_perso'=>0, 'eco_family'=>0];
    }
}

// 3. Récupération Catégories
$cats = $pdo->query("SELECT * FROM pf_alloc_categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 4. Gestion du mois FOCUS et Navigation
$focusDate = isset($_GET['focus_date']) ? $_GET['focus_date'] : date('Y-m-01');
$focusTs = strtotime($focusDate);

$months = [];
for ($i = 0; $i < 6; $i++) {
    $months[] = date('Y-m-01', strtotime("-$i months", $focusTs));
}

$prevMonthLink = date('Y-m-01', strtotime("-1 month", $focusTs));
$nextMonthLink = date('Y-m-01', strtotime("+1 month", $focusTs));

// Récupération des Cycles configurés dans pf_notes
$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// 5. Récupération Valeurs Répartition
$inQuery = implode(',', array_fill(0, count($months), '?'));
$stmt = $pdo->prepare("SELECT * FROM pf_alloc_values WHERE month_date IN ($inQuery)");
$stmt->execute($months);

$allocs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allocs[$row['month_date']][$row['cat_id']] = $row;
}

// 6. Récupération de l'ID de la catégorie système
$sysCatId = null;
foreach ($cats as $key => $c) {
    if ($c['name'] === 'SYSTEM_VALIDATION') {
        $sysCatId = $c['id'];
        unset($cats[$key]); 
        break;
    }
}

// Statuts de validation
$focusDate = $months[0];
$isValidated = [];
if ($sysCatId && isset($allocs[$focusDate][$sysCatId])) {
    $row = $allocs[$focusDate][$sysCatId];
    foreach ($parentMapping as $map) {
        $isValidated[$map['css']] = ($row[$map['db_field']] == 1);
    }
} else {
    foreach ($parentMapping as $map) {
        $isValidated[$map['css']] = false;
    }
}

$stmtNote = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'budget_prev' AND reference_id = ?");
$stmtNote->execute([$focusDate]);
$currentNote = $stmtNote->fetchColumn();

$activeHolidays = $pdo->query("SELECT id, title FROM pf_holidays WHERE status IN ('draft', 'planned', 'booked') ORDER BY start_date ASC")->fetchAll(PDO::FETCH_ASSOC);

function getTranslatedMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="prev-container" style="--p1-main: <?= $parentMapping[0]['color'] ?>; --p2-main: <?= $parentMapping[1]['color'] ?>;">

    <div>
        <div class="prev-section-header">
            <h2><?= tr('bud_prev_incomes') ?> <?= $currentYear ?></h2>
        </div>
        
        <table class="prev-salary-table">
            <thead>
                <tr>
                    <th><?= tr('bud_prev_person') ?></th>
                    <th><?= tr('bud_prev_salary') ?></th>
                    <th><?= tr('bud_prev_monthly_payment') ?><br><small>(<?= tr('bud_prev_joint_account') ?>)</small></th>
                    <th><?= tr('bud_prev_func_expenses') ?></th>
                    <th><?= tr('bud_prev_perso_savings') ?></th>
                    <th class="th-family-savings"><?= tr('bud_prev_family_savings') ?><br><small>(<?= tr('bud_prev_available') ?>)</small></th>
                    <th><?= tr('bud_prev_perso_remaining') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($parentMapping as $map): 
                    $d = $salaryConfig[$map['name']]; 
                    $restant = $d['salary'] - ($d['mensualite'] + $d['frais_func'] + $d['eco_perso'] + $d['eco_family']);
                ?>
                <tr data-person="<?= htmlspecialchars($map['name']) ?>">
                    <td class="col-p<?= $map['css'] ?>">
                        <?= htmlspecialchars($map['name']) ?>
                    </td>
                    <td><input type="number" class="prev-input" data-field="salary" value="<?= round($d['salary']) ?>" onchange="updateSalary('<?= htmlspecialchars($map['name']) ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="mensualite" value="<?= round($d['mensualite']) ?>" onchange="updateSalary('<?= htmlspecialchars($map['name']) ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="frais_func" value="<?= round($d['frais_func']) ?>" onchange="updateSalary('<?= htmlspecialchars($map['name']) ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="eco_perso" value="<?= round($d['eco_perso']) ?>" onchange="updateSalary('<?= htmlspecialchars($map['name']) ?>', this)"></td>
                    <td class="td-family-savings">
                        <input type="number" class="prev-input bold-blue" id="eco_family_<?= $map['css'] ?>" data-field="eco_family" value="<?= round($d['eco_family']) ?>" onchange="updateSalary('<?= htmlspecialchars($map['name']) ?>', this)">
                    </td>
                    <td id="restant_<?= $map['css'] ?>" class="sum-target font-bold">
                        <?= number_format($restant, 0, ',', ' ') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <div class="prev-section-header">
            <div class="prev-header-left">
                <h2><?= tr('bud_prev_budget_alloc') ?></h2>
                <div class="nav-group">
                    <a href="?tab=budget_prev&focus_date=<?= $prevMonthLink ?>" class="btn-nav">◀</a>
                    <a href="?tab=budget_prev&focus_date=<?= date('Y-m-01') ?>" class="btn-nav"><?= tr('bud_prev_today') ?></a>
                    <a href="?tab=budget_prev&focus_date=<?= $nextMonthLink ?>" class="btn-nav">▶</a>
                </div>
            </div>
            <div class="prev-header-actions">
                <button type="button" class="pf-btn btn-secondary" onclick="duplicateMonth()">
                    🔁 <?= tr('bud_sav_add_one_month') ?>
                </button>
                <button type="button" class="pf-btn" onclick="document.getElementById('addCatModal').style.display='flex'; document.body.classList.add('no-scroll');">
                    ＋ <?= tr('bud_prev_new_line') ?>
                </button>
            </div>
        </div>

        <div class="prev-timeline-wrapper">
            <table class="prev-alloc-table">
                <thead>
                    <tr>
                        <th class="col-sticky header-cell"></th>
                        <?php foreach ($months as $month): 
                            $isCurrent = ($month == date('Y-m-01'));
                            $cls = $isCurrent ? 'current' : '';
                            $colspan = count($parentMapping) + 1;
                        ?>
                            <th colspan="<?= $colspan ?>" class="th-month <?= $cls ?>">
                                <span><?= getTranslatedMonthName($month) ?></span>
                                <?php 
                                if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                    $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                    echo "<div class='cycle-start-label'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</div>";
                                }
                                ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="col-sticky header-cell"></th>
                        <?php foreach ($months as $month): ?>
                            <th class="th-sub">Global</th>
                            <?php foreach ($parentMapping as $map): ?>
                                <th class="th-sub txt-<?= $map['css'] ?>"><?= htmlspecialchars($map['name']) ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="col-sticky">Total</td>
                        <?php foreach ($months as $m): ?>
                            <td class="txt-global sum-target" id="total_global_<?= $m ?>">0</td>
                            <?php foreach ($parentMapping as $map): ?>
                                <td class="txt-<?= $map['css'] ?> sum-target" id="total_<?= $map['css'] ?>_<?= $m ?>">0</td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>

                    <tr class="row-restant">
                        <td class="col-sticky"><?= tr('bud_prev_remaining') ?> (Eco Family)</td>
                        <?php foreach ($months as $m): ?>
                            <td class="td-separator">-</td>
                            <?php foreach ($parentMapping as $map): ?>
                                <td class="val-ok sum-target" id="restant_alloc_<?= $map['css'] ?>_<?= $m ?>">0</td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($cats as $cat): 
                        $isIndicative = (strpos($cat['name'], 'Eco P') === 0);
                        $rowClass = $isIndicative ? 'row-indicative' : '';
                        $inputClass = $isIndicative ? 'ignore-calc' : ''; 
                        
                        $catDisplayName = $cat['name'];
                        if ($catDisplayName === 'Eco P1') $catDisplayName = 'Eco ' . $p1_name;
                        if ($catDisplayName === 'Eco P2') $catDisplayName = 'Eco ' . $p2_name;
                    ?>
                <tr class="<?= $rowClass ?>">
                    <td class="col-sticky">
                        <div class="cat-name-label">
                            <?= htmlspecialchars($catDisplayName) ?> 
                            <?php if(!empty($cat['holiday_id'])) echo " 🌴"; ?>
                            <?php if($isIndicative): ?><span>Info</span><?php endif; ?>
                        </div>
                        <div class="cat-target-label">
                            <?= htmlspecialchars($cat['target']) ?>
                        </div>
                        
                        <div class="row-actions">
                            <button type="button" class="btn-icon-action edit" title="<?= tr('edit') ?>" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-target="<?= htmlspecialchars($cat['target']) ?>" data-holiday="<?= $cat['holiday_id'] ?? '' ?>" onclick="openEditModal(this)">✎</button>
                            <button type="button" onclick="deleteCategory(<?= $cat['id'] ?>)" class="btn-icon-action delete" title="<?= tr('delete') ?>">🗑️</button>
                        </div>
                    </td>
                    <?php foreach ($months as $m): 
                        $val = $allocs[$m][$cat['id']] ?? [];
                    ?>
                        <td class="txt-global sum-target" id="g_<?= $m ?>_<?= $cat['id'] ?>">0</td>
                        
                        <?php foreach ($parentMapping as $map): 
                            $dbField = $map['db_field'];
                            $cellValue = isset($val[$dbField]) ? $val[$dbField] : 0;
                        ?>
                        <td>
                            <input type="number" step="1" class="prev-input txt-<?= $map['css'] ?> inp-<?= $map['css'] ?>-<?= $m ?> <?= $inputClass ?>" 
                                   value="<?= $cellValue == 0 ? '' : round($cellValue) ?>" 
                                   placeholder="-"
                                   data-target="<?= htmlspecialchars($cat['target']) ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, '<?= $dbField ?>', this)">
                        </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="budget-notes-section">
        <div class="notes-header">
            <h3>📝 <?= tr('bud_prev_notes_for') ?> <span><?= getTranslatedMonthName($focusDate) ?></span></h3>
            <span id="note-save-indicator" class="note-save-indicator">✓ <?= tr('bud_prev_saved') ?></span>
        </div>
        
        <textarea id="monthNoteArea" class="pf-input" rows="3" placeholder="<?= tr('bud_prev_notes_ph') ?>"><?= htmlspecialchars((string)$currentNote) ?></textarea>
        
        <div class="notes-footer">
            <button type="button" class="pf-btn" onclick="saveGenericNote('budget_prev', '<?= $focusDate ?>', document.getElementById('monthNoteArea').value)"><?= tr('bud_prev_save_note') ?></button>
        </div>
    </div>

    <?php
    $focusMonth = $months[0]; 
    $targetsOrder = ['vers commune', 'vers L.Pol', 'vers L.Pep', 'vers L.Perso'];
    $allTargets = $targetsOrder;
    foreach($cats as $c) {
        $t = trim($c['target']);
        if(!empty($t) && !in_array($t, $allTargets)) { $allTargets[] = $t; }
    }
    $allTargets = array_unique($allTargets);
    ?>

    <div class="recap-wrapper">
        <div class="recap-card">
            <div class="recap-header">
                <?= tr('bud_prev_transfers_to_make') ?> - <span><?= getTranslatedMonthName($focusMonth) ?></span>
            </div>
            
            <table class="recap-table">
                <thead>
                    <tr>
                        <th><?= tr('bud_prev_destination') ?></th>
                        <?php foreach ($parentMapping as $map): ?>
                        <th class="col-<?= $map['css'] ?>">
                            <div class="recap-th-content">
                                <span><?= htmlspecialchars($map['name']) ?></span>
                                <?php if($isValidated[$map['css']]): ?>
                                    <div class="validation-badge">✓ <?= tr('bud_prev_done') ?></div>
                                <?php else: ?>
                                    <button onclick="validateTransfers('<?= $map['css'] ?>', '<?= $focusMonth ?>')" class="pf-btn btn-small"><?= tr('bud_prev_validate') ?></button>
                                <?php endif; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                        <th class="col-global">Global</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allTargets as $target): $tId = md5($target); ?>
                    <tr id="row_summary_<?= $tId ?>">
                        <td><?= htmlspecialchars($target) ?></td>
                        <?php foreach ($parentMapping as $map): ?>
                            <td class="col-<?= $map['css'] ?> sum-target" id="sum_<?= $map['css'] ?>_<?= $tId ?>">0 €</td>
                        <?php endforeach; ?>
                        <td class="col-global sum-target" id="sum_global_<?= $tId ?>">0 €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="row-grand-total">
                        <td><?= tr('bud_prev_grand_total') ?></td>
                        <?php foreach ($parentMapping as $map): ?>
                            <td class="col-<?= $map['css'] ?> sum-target" id="grand_total_<?= $map['css'] ?>">0 €</td>
                        <?php endforeach; ?>
                        <td class="col-global sum-target" id="grand_total_global">0 €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div id="addCatModal" class="pf-modal">
    <div class="pf-modal-content">
        <div class="prev-header-left">
            <h3 class="pf-modal-title"><?= tr('bud_prev_new_line_title') ?></h3>
            <button onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group">
                <label class="pf-label"><?= tr('bud_prev_label_name') ?></label>
                <input type="text" name="name" class="pf-input" required>
            </div>
            <div class="form-group">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" class="pf-input" required>
                    <option value="" disabled selected>-- <?= tr('bud_prev_choose') ?> --</option>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group">
                <label class="pf-label">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('bud_add_title') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="editCatModal" class="pf-modal">
    <div class="pf-modal-content">
        <div class="prev-header-left">
            <h3 class="pf-modal-title"><?= tr('bud_prev_edit_line_title') ?></h3>
            <button onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            <div class="form-group">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="edit_cat_name" class="pf-input" required>
            </div>
            <div class="form-group">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" id="edit_cat_target" class="pf-input" required>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group">
                <label class="pf-label">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" id="edit_cat_holiday" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<button id="fabSumMode" class="pf-fab-sum" onclick="toggleSumMode()">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="8" y1="12" x2="16" y2="12"></line><line x1="12" y1="8" x2="12" y2="16"></line></svg>
</button>

<div id="sumResultBar" class="pf-sum-bar">
    <span class="pf-sum-label"><?= tr('bud_sav_selection') ?></span>
    <span id="sumResultValue" class="pf-sum-value">0 €</span>
    <button onclick="toggleSumMode()" class="pf-sum-close">&times;</button>
</div>

<script>
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_prev_label_name': <?= json_encode(tr('bud_prev_label_name')) ?>,
    'bud_prev_err_no_history': <?= json_encode(tr('bud_prev_err_no_history')) ?>,
    'bud_prev_confirm_copy': <?= json_encode(tr('bud_prev_confirm_copy')) ?>,
    'bud_prev_confirm_transfers': <?= json_encode(tr('bud_prev_confirm_transfers')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>
};

window.CONFIG = window.CONFIG || {};
window.CONFIG.parentMapping = <?= json_encode($parentMapping) ?>;

const currentYear = <?= $currentYear ?>;
const months = <?= json_encode($months) ?>;

function openEditModal(btn) {
    document.getElementById('edit_cat_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_cat_name').value = btn.getAttribute('data-name');
    document.getElementById('edit_cat_target').value = btn.getAttribute('data-target');
    document.getElementById('edit_cat_holiday').value = btn.getAttribute('data-holiday'); 
    document.getElementById('editCatModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

function updateSalary(person, input) {
    const row = input.closest('tr');
    const salary = parseFloat(row.querySelector('[data-field="salary"]').value) || 0;
    const mens = parseFloat(row.querySelector('[data-field="mensualite"]').value) || 0;
    const frais = parseFloat(row.querySelector('[data-field="frais_func"]').value) || 0;
    const ecoP = parseFloat(row.querySelector('[data-field="eco_perso"]').value) || 0;
    const ecoF = parseFloat(row.querySelector('[data-field="eco_family"]').value) || 0;

    const restant = salary - (mens + frais + ecoP + ecoF);
    
    const parentMap = window.CONFIG.parentMapping.find(m => m.name === person);
    if(parentMap) {
        document.getElementById('restant_' + parentMap.css).innerText = Math.round(restant).toLocaleString(window.appLang) + ' €';
    }

    saveData('update_salary_config', { year: currentYear, person: person, field: input.dataset.field, value: input.value });
    recalcAllAllocations();
}

function updateAlloc(month, catId, personField, input) {
    saveData('update_allocation', { month_date: month, cat_id: catId, person: personField, value: input.value || 0 });
    recalcAllAllocations();
}

function duplicateMonth() {
    const targetDateStr = months[0];
    const sourceDateStr = months[1];
    const parentMap = window.CONFIG.parentMapping;

    if (!sourceDateStr) {
        alert(window.I18N['bud_prev_err_no_history']);
        return;
    }

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(sourceDateStr); 
    const targetName = formatMonth(targetDateStr); 
    const message = window.I18N['bud_prev_confirm_copy'].replace('%s', sourceName).replace('%t', targetName);

    if(!confirm(message)) return;
    
    const firstCss = parentMap[0].css;
    document.querySelectorAll('.inp-' + firstCss + '-' + sourceDateStr).forEach(sourceInput => {
        const catIdMatch = sourceInput.getAttribute('onchange').match(/, (\d+),/);
        if(!catIdMatch) return;
        const catId = catIdMatch[1];
        const row = sourceInput.closest('tr');

        parentMap.forEach(map => {
            const sInp = row.querySelector('.inp-' + map.css + '-' + sourceDateStr);
            const tInp = row.querySelector('.inp-' + map.css + '-' + targetDateStr);
            if(sInp && tInp) { 
                tInp.value = sInp.value; 
                updateAlloc(targetDateStr, catId, map.db_field, tInp); 
            }
        });
    });
}

function recalcAllAllocations() {
    const parentMap = window.CONFIG.parentMapping;

    months.forEach(m => {
        let sums = {};
        parentMap.forEach(map => sums[map.css] = 0);

        const firstCss = parentMap[0].css;
        document.querySelectorAll('.inp-' + firstCss + '-' + m).forEach(inp => {
            const row = inp.closest('tr');
            let globalSum = 0;

            parentMap.forEach(map => {
                const pInp = row.querySelector('.inp-' + map.css + '-' + m);
                const val = pInp ? (parseFloat(pInp.value) || 0) : 0;
                if (pInp && !pInp.classList.contains('ignore-calc')) {
                    sums[map.css] += val;
                }
                globalSum += val;
            });
            
            const onchangeStr = inp.getAttribute('onchange');
            const matches = onchangeStr.match(/, (\d+),/);
            if(matches && matches[1]) {
                const gCell = document.getElementById('g_' + m + '_' + matches[1]);
                if(gCell) gCell.innerText = globalSum > 0 ? Math.round(globalSum) : '-';
            }
        });

        let totalGlobal = 0;
        parentMap.forEach(map => {
            const sumEl = document.getElementById('total_' + map.css + '_' + m);
            if(sumEl) sumEl.innerText = Math.round(sums[map.css]) + ' €';
            
            totalGlobal += sums[map.css];

            const budget = parseFloat(document.getElementById('eco_family_' + map.css).value) || 0;
            const rest = budget - sums[map.css];

            const elRest = document.getElementById('restant_alloc_' + map.css + '_' + m);
            if (elRest) {
                elRest.innerText = Math.round(rest) + ' €';
                elRest.className = 'val-' + (rest >= 0 ? 'ok' : 'ko') + ' sum-target'; 
            }
        });

        const globEl = document.getElementById('total_global_' + m);
        if(globEl) globEl.innerText = Math.round(totalGlobal) + ' €';
    });
    
    updateSummaryTable();
    if(isSumModeActive) updateSumResult(); 
}

function updateSummaryTable() {
    const focusMonth = months[0];
    const parentMap = window.CONFIG.parentMapping;
    let grandTotals = {};
    let dataByTarget = {};

    parentMap.forEach(map => {
        grandTotals[map.css] = 0;
        dataByTarget[map.css] = {};
        
        document.querySelectorAll('.inp-' + map.css + '-' + focusMonth).forEach(inp => {
            const target = inp.getAttribute('data-target');
            if(target) {
                if(!dataByTarget[map.css][target]) dataByTarget[map.css][target] = 0;
                dataByTarget[map.css][target] += (parseFloat(inp.value) || 0);
            }
        });
    });

    const tbody = document.querySelector('.recap-table tbody');
    if(tbody) {
        Array.from(tbody.rows).forEach(row => {
            const targetName = row.cells[0].innerText.trim(); 
            let globalSum = 0;

            parentMap.forEach((map, idx) => {
                const pSum = dataByTarget[map.css][targetName] || 0;
                row.cells[idx + 1].innerText = Math.round(pSum).toLocaleString(window.appLang) + ' ' + window.CONFIG.CURRENCY;
                grandTotals[map.css] += pSum;
                globalSum += pSum;
            });

            row.cells[parentMap.length + 1].innerText = Math.round(globalSum).toLocaleString(window.appLang) + ' ' + window.CONFIG.CURRENCY;
        });
    }

    let totalGrandGlobal = 0;
    parentMap.forEach(map => {
        const grandEl = document.getElementById('grand_total_' + map.css);
        if(grandEl) grandEl.innerText = Math.round(grandTotals[map.css]).toLocaleString(window.appLang) + ' ' + window.CONFIG.CURRENCY;
        totalGrandGlobal += grandTotals[map.css];
    });
    
    const globGrandEl = document.getElementById('grand_total_global');
    if(globGrandEl) globGrandEl.innerText = Math.round(totalGrandGlobal).toLocaleString(window.appLang) + ' ' + window.CONFIG.CURRENCY;
}

function saveData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) formData.append(key, data[key]);
    fetch('modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
}

function validateTransfers(personCss, month) {
    const msg = window.I18N['bud_prev_confirm_transfers'].replace('%p', personCss).replace('%m', month);
    if (!confirm(msg)) return;

    const parentMap = window.CONFIG.parentMapping.find(m => m.css === personCss);
    const dbPersonName = parentMap ? parentMap.name : personCss;

    const formData = new FormData();
    formData.append('action', 'validate_transfers');
    formData.append('person', dbPersonName);
    formData.append('month_date', month);
    
    fetch('modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) window.location.reload();
        else alert("Erreur: " + data.error);
    })
    .catch(e => alert(window.I18N['bud_err_tech']));
}

function saveGenericNote(noteType, refId, content) {
    const formData = new FormData();
    formData.append('action', 'save_note');
    formData.append('note_type', noteType);    
    formData.append('reference_id', refId);    
    formData.append('content', content);       

    fetch('modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData })
    .then(async r => {
        const text = await r.text();
        if (!r.ok) throw new Error(`Erreur HTTP ${r.status}`);
        return JSON.parse(text);
    })
    .then(data => {
        if(data.success) {
            const indicator = document.getElementById('note-save-indicator');
            if(indicator) {
                indicator.style.opacity = '1';
                setTimeout(() => indicator.style.opacity = '0', 2000);
            }
        } else {
            alert("Erreur: " + data.error);
        }
    })
    .catch(e => alert(e.message));
}

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
    return parseFloat(text.replace(',', '.').replace(/[^\d.-]/g, '')) || 0;
}

function updateSumResult() {
    let total = 0;
    selectedElementsForSum.forEach(el => {
        total += (el.tagName === 'INPUT') ? (parseFloat(el.value) || 0) : extractNumberFromText(el.innerText);
    });
    document.getElementById('sumResultValue').innerText = Math.round(total).toLocaleString(window.appLang) + ' ' + window.CONFIG.CURRENCY;
}

async function deleteCategory(id) {
    if (!confirm(window.I18N['bud_prev_confirm_del_line'])) return;
    const formData = new FormData();
    formData.append('action', 'delete_category');
    formData.append('id', id);
    formData.append('ajax', '1'); 
    try {
        const result = await pachaFetch('modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
        if (result.success) window.location.reload(); 
    } catch(e) { console.error(e); }
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

document.addEventListener('DOMContentLoaded', recalcAllAllocations);

document.querySelectorAll('#addCatModal form, #editCatModal form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        try {
            submitBtn.disabled = true;
            submitBtn.innerText = '⏳ ...';
            const formData = new FormData(form);
            formData.append('ajax', '1');
            const actionUrl = form.getAttribute('action'); 
            const result = await pachaFetch(actionUrl, { method: 'POST', body: formData });
            if (result.success) {
                form.closest('.pf-modal').style.display = 'none';
                document.body.classList.remove('no-scroll');
                window.location.reload(); 
            } else {
                alert((window.I18N['bud_err_tech'] || 'Erreur') + " : " + (result.error || "Inconnue"));
            }
        } catch (error) {
            alert("Une erreur technique est survenue.");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
});
</script>