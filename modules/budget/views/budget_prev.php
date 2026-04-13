<?php
// modules/budget/views/budget_prev.php

$currentYear = date('Y');

// 1. Récupération Config Salaires
$salaryConfig = [];
$stmt = $pdo->prepare("SELECT * FROM pf_salary_config WHERE year = ?");
$stmt->execute([$currentYear]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $salaryConfig[$row['person']] = $row;
}

foreach (['Alex', 'Laia'] as $p) {
    if (!isset($salaryConfig[$p])) {
        $salaryConfig[$p] = ['salary'=>0, 'mensualite'=>0, 'frais_func'=>0, 'eco_perso'=>0, 'eco_family'=>0];
    }
}

// 2. Récupération Catégories
$cats = $pdo->query("SELECT * FROM pf_alloc_categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Gestion du mois FOCUS et Navigation
$focusDate = isset($_GET['focus_date']) ? $_GET['focus_date'] : date('Y-m-01');
$focusTs = strtotime($focusDate);

$months = [];
for ($i = 0; $i < 6; $i++) {
    $months[] = date('Y-m-01', strtotime("-$i months", $focusTs));
}

$prevMonthLink = date('Y-m-01', strtotime("-1 month", $focusTs));
$nextMonthLink = date('Y-m-01', strtotime("+1 month", $focusTs));

// NOUVEAU : Récupération des Cycles configurés dans pf_notes
$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// 4. Récupération Valeurs Répartition
$inQuery = implode(',', array_fill(0, count($months), '?'));
$stmt = $pdo->prepare("SELECT * FROM pf_alloc_values WHERE month_date IN ($inQuery)");
$stmt->execute($months);

$allocs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allocs[$row['month_date']][$row['cat_id']] = $row;
}

// 5. Récupération de l'ID de la catégorie système
$sysCatId = null;
foreach ($cats as $key => $c) {
    if ($c['name'] === 'SYSTEM_VALIDATION') {
        $sysCatId = $c['id'];
        unset($cats[$key]); 
        break;
    }
}

// 6. Lecture des statuts de validation
$focusDate = $months[0];
$isValidatedAlex = false;
$isValidatedLaia = false;

if ($sysCatId && isset($allocs[$focusDate][$sysCatId])) {
    $row = $allocs[$focusDate][$sysCatId];
    $isValidatedAlex = ($row['amount_alex'] == 1);
    $isValidatedLaia = ($row['amount_laia'] == 1);
}

$stmtNote = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'budget_prev' AND reference_id = ?");
$stmtNote->execute([$focusDate]);
$currentNote = $stmtNote->fetchColumn();

// 7. Récupération des vacances actives
$activeHolidays = $pdo->query("SELECT id, title FROM pf_holidays WHERE status IN ('draft', 'planned', 'booked') ORDER BY start_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper function pour les mois
function getTranslatedMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="prev-container">

    <div>
        <div class="prev-section-header">
            <h2><?= tr('bud_prev_incomes') ?> <?= $currentYear ?></h2>
        </div>
        
        <table class="prev-salary-table">
            <thead>
                <tr>
                    <th><?= tr('bud_prev_person') ?></th>
                    <th><?= tr('bud_prev_salary') ?></th>
                    <th><?= tr('bud_prev_monthly_payment') ?><br><small style="font-weight:normal; text-transform:none;">(<?= tr('bud_prev_joint_account') ?>)</small></th>
                    <th><?= tr('bud_prev_func_expenses') ?></th>
                    <th><?= tr('bud_prev_perso_savings') ?></th>
                    <th style="background:#f0f9ff; color:#0284c7;"><?= tr('bud_prev_family_savings') ?><br><small style="font-weight:normal; text-transform:none;">(<?= tr('bud_prev_available') ?>)</small></th>
                    <th><?= tr('bud_prev_perso_remaining') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['Alex', 'Laia'] as $p): 
                    $d = $salaryConfig[$p]; 
                    $restant = $d['salary'] - ($d['mensualite'] + $d['frais_func'] + $d['eco_perso'] + $d['eco_family']);
                    $borderColor = ($p === 'Alex') ? '#0891b2' : '#f59e0b';
                ?>
                <tr data-person="<?= $p ?>">
                    <td style="text-align:left; font-weight:bold; color:var(--text-main); border-left:4px solid <?= $borderColor ?>;">
                        <?= $p ?>
                    </td>
                    <td><input type="number" class="prev-input" data-field="salary" value="<?= round($d['salary']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="mensualite" value="<?= round($d['mensualite']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="frais_func" value="<?= round($d['frais_func']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="eco_perso" value="<?= round($d['eco_perso']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td style="background:#f0f9ff;">
                        <input type="number" class="prev-input bold-blue" id="eco_family_<?= $p ?>" data-field="eco_family" value="<?= round($d['eco_family']) ?>" onchange="updateSalary('<?= $p ?>', this)">
                    </td>
                    <td id="restant_<?= $p ?>" style="font-weight:bold; color:var(--text-muted); text-align:center;" class="sum-target">
                        <?= number_format($restant, 0, ',', ' ') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <div class="prev-section-header">
            <div style="display:flex; gap:10px; align-items:center;">
                <h2><?= tr('bud_prev_budget_alloc') ?></h2>
                <div class="nav-group">
                    <a href="?tab=budget_prev&focus_date=<?= $prevMonthLink ?>" class="btn-nav">◀</a>
                    <a href="?tab=budget_prev&focus_date=<?= date('Y-m-01') ?>" class="btn-nav"><?= tr('bud_prev_today') ?></a>
                    <a href="?tab=budget_prev&focus_date=<?= $nextMonthLink ?>" class="btn-nav">▶</a>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="pf-btn btn-secondary" onclick="duplicateMonth()">
                    🔁 <?= tr('bud_sav_add_one_month') ?>
                </button>
                <button class="pf-btn" onclick="document.getElementById('addCatModal').style.display='flex'; document.body.classList.add('no-scroll');">
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
                        ?>
                            <th colspan="3" class="th-month <?= $cls ?>">
                                <span style="text-transform:capitalize;"><?= getTranslatedMonthName($month) ?></span>
                                <?php 
                                if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                    $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                    echo "<div style='font-size:0.75rem; font-weight:normal; color:#64748b; margin-top:2px;'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</div>";
                                }
                                ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="col-sticky header-cell"></th>
                        <?php foreach ($months as $month): ?>
                            <th class="th-sub">Global</th>
                            <th class="th-sub txt-alex">Alex</th>
                            <th class="th-sub txt-laia">Laia</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="col-sticky" style="text-align:right !important;">Total</td>
                        <?php foreach ($months as $m): ?>
                            <td class="txt-global sum-target" style="border-left:2px solid #e2e8f0;" id="total_global_<?= $m ?>">0</td>
                            <td class="txt-alex sum-target" id="total_alex_<?= $m ?>">0</td>
                            <td class="txt-laia sum-target" id="total_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <tr class="row-restant">
                        <td class="col-sticky" style="text-align:right !important;"><?= tr('bud_prev_remaining') ?> (Eco Family)</td>
                        <?php foreach ($months as $m): ?>
                            <td style="border-left:2px solid #cbd5e1; background:#e2e8f0;">-</td>
                            <td class="val-ok sum-target" id="restant_alloc_alex_<?= $m ?>">0</td>
                            <td class="val-ok sum-target" id="restant_alloc_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($cats as $cat): 
                    $isIndicative = ($cat['name'] === 'Eco Alex' || $cat['name'] === 'Eco Laia');
                    $rowClass = $isIndicative ? 'row-indicative' : '';
                    $inputClass = $isIndicative ? 'ignore-calc' : ''; 
                    $rowStyle = $isIndicative ? 'background:#f8fafc; color:#94a3b8;' : '';
                ?>
                <tr class="<?= $rowClass ?>" style="<?= $rowStyle ?>">
                    <td class="col-sticky" style="position:relative; <?= $isIndicative ? 'opacity:0.8;' : '' ?>">
                        <div style="font-weight:600; color:<?= $isIndicative ? '#64748b' : 'var(--text-main)' ?>;">
                            <?= htmlspecialchars($cat['name']) ?> 
                            <?php if(!empty($cat['holiday_id'])) echo " 🌴"; ?>
                            <?php if($isIndicative): ?><span style="font-size:0.7rem; border:1px solid #cbd5e1; border-radius:4px; padding:0 4px; margin-left:5px;">Info</span><?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-style:italic; text-align:right;">
                            <?= htmlspecialchars($cat['target']) ?>
                        </div>
                        
                        <div class="row-actions">
                            <button type="button" 
                                    class="btn-icon-action edit" 
                                    title="<?= tr('edit') ?>"
                                    data-id="<?= $cat['id'] ?>"
                                    data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                    data-target="<?= htmlspecialchars($cat['target']) ?>"
                                    data-holiday="<?= $cat['holiday_id'] ?? '' ?>"
                                    onclick="openEditModal(this)">
                                ✎
                            </button>

                            <a href="?tab=budget_prev&id=<?= $cat['id'] ?>&action=delete_category" 
                               onclick="return confirm('<?= tr('bud_prev_confirm_del_line') ?>')" 
                               class="btn-icon-action delete" title="<?= tr('delete') ?>">
                               &times;
                            </a>
                        </div>
                    </td>
                    <?php foreach ($months as $m): 
                        $val = $allocs[$m][$cat['id']] ?? ['amount_alex'=>0, 'amount_laia'=>0];
                    ?>
                        <td class="txt-global sum-target" style="border-left:2px solid #e2e8f0; <?= $isIndicative?'color:#94a3b8; font-weight:normal;':'' ?>" id="g_<?= $m ?>_<?= $cat['id'] ?>">0</td>
                        
                        <td>
                            <input type="number" step="1" class="prev-input txt-alex inp-alex-<?= $m ?> <?= $inputClass ?>" 
                                   style="<?= $isIndicative ? 'color:#64748b;' : '' ?>"
                                   value="<?= $val['amount_alex'] == 0 ? '' : round($val['amount_alex']) ?>" 
                                   placeholder="-"
                                   data-target="<?= htmlspecialchars($cat['target']) ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, 'amount_alex', this)">
                        </td>
                        
                        <td>
                            <input type="number" step="1" class="prev-input txt-laia inp-laia-<?= $m ?> <?= $inputClass ?>" 
                                   style="<?= $isIndicative ? 'color:#64748b;' : '' ?>"
                                   value="<?= $val['amount_laia'] == 0 ? '' : round($val['amount_laia']) ?>" 
                                   placeholder="-"
                                   data-target="<?= htmlspecialchars($cat['target']) ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, 'amount_laia', this)">
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin: 20px 0; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">
                📝 <?= tr('bud_prev_notes_for') ?> <span style="text-transform:capitalize;"><?= getTranslatedMonthName($focusDate) ?></span>
            </h3>
            <span id="note-save-indicator" style="font-size:0.85rem; color:#10b981; font-weight:bold; opacity:0; transition:opacity 0.3s;">
                ✓ <?= tr('bud_prev_saved') ?>
            </span>
        </div>
        
        <textarea 
            id="monthNoteArea" 
            class="pf-input" 
            rows="3" 
            placeholder="<?= tr('bud_prev_notes_ph') ?>" 
            style="width: 100%; resize: vertical; border-color: #cbd5e1; background: #f8fafc; margin-bottom: 10px;"
        ><?= htmlspecialchars((string)$currentNote) ?></textarea>
        
        <div style="text-align: right;">
            <button type="button" class="pf-btn" style="width: auto; display: inline-flex;" 
                    onclick="saveGenericNote('budget_prev', '<?= $focusDate ?>', document.getElementById('monthNoteArea').value)">
                <?= tr('bud_prev_save_note') ?>
            </button>
        </div>
    </div>

    <?php
    $focusMonth = $months[0]; 
    
    $targetsOrder = ['vers commune', 'vers L.Pol', 'vers L.Pep', 'vers L.Perso'];
    
    $allTargets = $targetsOrder;
    foreach($cats as $c) {
        $t = trim($c['target']);
        if(!empty($t) && !in_array($t, $allTargets)) {
            $allTargets[] = $t;
        }
    }
    $allTargets = array_unique($allTargets);

    $summaryData = [];
    foreach($allTargets as $t) $summaryData[$t] = ['Alex' => 0, 'Laia' => 0];

    foreach ($cats as $cat) {
        $t = trim($cat['target']);
        if (empty($t)) continue;

        $val = $allocs[$focusMonth][$cat['id']] ?? ['amount_alex'=>0, 'amount_laia'=>0];
        
        if (!isset($summaryData[$t])) {
            $summaryData[$t] = ['Alex' => 0, 'Laia' => 0];
        }

        $summaryData[$t]['Alex'] += $val['amount_alex'];
        $summaryData[$t]['Laia'] += $val['amount_laia'];
    }

    $grandTotalAlex = 0;
    $grandTotalLaia = 0;
    $grandTotalGlobal = 0;
    ?>

    <div class="recap-wrapper">
        <div class="recap-card">
            <div class="recap-header">
                <?= tr('bud_prev_transfers_to_make') ?> - <span style="text-transform:capitalize;"><?= getTranslatedMonthName($focusMonth) ?></span>
            </div>
            
            <table class="recap-table">
                <thead>
                    <tr>
                        <th style="text-align:left;"><?= tr('bud_prev_destination') ?></th>
                        
                        <th class="col-alex">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:5px;">
                                <span>ALEX</span>
                                <?php if($isValidatedAlex): ?>
                                    <div style="background:#10b981; color:white; padding:4px 8px; border-radius:4px; font-size:0.7rem; display:flex; align-items:center; gap:4px;">
                                        ✓ <?= tr('bud_prev_done') ?>
                                    </div>
                                <?php else: ?>
                                    <button onclick="validateTransfers('Alex', '<?= $focusMonth ?>')" class="pf-btn btn-small" style="background:white; color:#0891b2; border:1px solid #0891b2; font-size:0.7rem; padding:2px 8px; height:auto;">
                                        <?= tr('bud_prev_validate') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </th>

                        <th class="col-laia">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:5px;">
                                <span>LAIA</span>
                                <?php if($isValidatedLaia): ?>
                                    <div style="background:#10b981; color:white; padding:4px 8px; border-radius:4px; font-size:0.7rem; display:flex; align-items:center; gap:4px;">
                                        ✓ <?= tr('bud_prev_done') ?>
                                    </div>
                                <?php else: ?>
                                    <button onclick="validateTransfers('Laia', '<?= $focusMonth ?>')" class="pf-btn btn-small" style="background:white; color:#d97706; border:1px solid #d97706; font-size:0.7rem; padding:2px 8px; height:auto;">
                                        <?= tr('bud_prev_validate') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </th>
                        
                        <th class="col-global">Global</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allTargets as $target): 
                        $tId = md5($target); 
                    ?>
                    <tr id="row_summary_<?= $tId ?>">
                        <td><?= htmlspecialchars($target) ?></td>
                        <td class="col-alex sum-target" id="sum_alex_<?= $tId ?>">0 €</td>
                        <td class="col-laia sum-target" id="sum_laia_<?= $tId ?>">0 €</td>
                        <td class="col-global sum-target" id="sum_global_<?= $tId ?>">0 €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="row-grand-total">
                        <td><?= tr('bud_prev_grand_total') ?></td>
                        <td class="col-alex sum-target" id="grand_total_alex">0 €</td>
                        <td class="col-laia sum-target" id="grand_total_laia">0 €</td>
                        <td class="col-global sum-target" id="grand_total_global">0 €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div id="addCatModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:400px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;"><?= tr('bud_prev_new_line_title') ?></h3>
            <button onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_name') ?></label>
                <input type="text" name="name" class="pf-input" required>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" class="pf-input" required>
                    <option value="" disabled selected>-- <?= tr('bud_prev_choose') ?> --</option>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="pf-label" style="color:#8b5cf6;">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" class="pf-input" style="border-color:#8b5cf6; background:#f5f3ff;">
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
    <div class="pf-modal-content" style="max-width:400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;"><?= tr('bud_prev_edit_line_title') ?></h3>
            <button onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="edit_cat_name" class="pf-input" required>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" id="edit_cat_target" class="pf-input" required>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="pf-label" style="color:#8b5cf6;">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" id="edit_cat_holiday" class="pf-input" style="border-color:#8b5cf6; background:#f5f3ff;">
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

<style>
/* Bouton flottant */
.pf-fab-sum {
    position: fixed;
    right: 30px;
    bottom: 30px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background-color: #2563eb;
    color: white;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
    cursor: pointer;
    z-index: 9999;
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.pf-fab-sum:hover {
    transform: scale(1.1);
    background-color: #1d4ed8;
}

.pf-fab-sum.active {
    background-color: #10b981;
    transform: scale(1.1);
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.5);
}

/* Barre de résultat en bas */
.pf-sum-bar {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    opacity: 0;
    background: white;
    padding: 12px 24px;
    border-radius: 30px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 15px;
    z-index: 9998;
    border: 1px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.pf-sum-bar.visible {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

#sumResultValue {
    font-size: 1.2rem;
    color: #0f172a;
    font-weight: bold;
}

/* Effets sur le curseur quand le mode est actif */
body.sum-mode-active {
    cursor: cell !important;
}

body.sum-mode-active input, 
body.sum-mode-active .sum-target {
    cursor: cell !important;
}

/* Style des champs/cellules sélectionnés */
.sum-selected {
    outline: 2px dashed #10b981 !important;
    outline-offset: -2px;
    background-color: #ecfdf5 !important;
    color: #065f46 !important;
    transition: background-color 0.2s;
}
</style>

<button id="fabSumMode" class="pf-fab-sum" onclick="toggleSumMode()" title="<?= tr('bud_sav_sum_mode_title') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="8" y1="12" x2="16" y2="12"></line><line x1="12" y1="8" x2="12" y2="16"></line></svg>
</button>

<div id="sumResultBar" class="pf-sum-bar">
    <span class="pf-sum-label"><?= tr('bud_sav_selection') ?></span>
    <span id="sumResultValue" class="pf-sum-value">0 €</span>
    <button onclick="toggleSumMode()" class="pf-sum-close" title="<?= tr('btn_close') ?>">&times;</button>
</div>

<script>
    window.I18N = {
        ...window.I18N,
        'bud_prev_err_no_history': "<?= tr('bud_prev_err_no_history') ?>",
        'bud_prev_confirm_copy': "<?= tr('bud_prev_confirm_copy') ?>",
        'bud_prev_confirm_transfers': "<?= tr('bud_prev_confirm_transfers') ?>",
        'bud_err_tech': "<?= tr('bud_err_tech') ?>"
    };
    
    const currentLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
</script>

<script>
function openEditModal(btn) {
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const target = btn.getAttribute('data-target');
    const holiday = btn.getAttribute('data-holiday'); 

    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    document.getElementById('edit_cat_target').value = target;
    document.getElementById('edit_cat_holiday').value = holiday; 
    
    document.getElementById('editCatModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

const currentYear = <?= $currentYear ?>;
const months = <?= json_encode($months) ?>;

function updateSalary(person, input) {
    const row = input.closest('tr');
    const salary = parseFloat(row.querySelector('[data-field="salary"]').value) || 0;
    const mens = parseFloat(row.querySelector('[data-field="mensualite"]').value) || 0;
    const frais = parseFloat(row.querySelector('[data-field="frais_func"]').value) || 0;
    const ecoP = parseFloat(row.querySelector('[data-field="eco_perso"]').value) || 0;
    const ecoF = parseFloat(row.querySelector('[data-field="eco_family"]').value) || 0;

    const restant = salary - (mens + frais + ecoP + ecoF);
    document.getElementById('restant_' + person).innerText = Math.round(restant).toLocaleString(currentLang) + ' €';

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

    if (!sourceDateStr) {
        alert(tr("bud_prev_err_no_history"));
        return;
    }

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(currentLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(sourceDateStr); 
    const targetName = formatMonth(targetDateStr); 

    const message = tr("bud_prev_confirm_copy").replace('%s', sourceName).replace('%t', targetName);

    if(!confirm(message)) return;
    
    document.querySelectorAll('.inp-alex-' + sourceDateStr).forEach(sourceInput => {
        const catIdMatch = sourceInput.getAttribute('onchange').match(/, (\d+),/);
        if(!catIdMatch) return;
        const catId = catIdMatch[1];
        const row = sourceInput.closest('tr');

        const valAlex = sourceInput.value;
        const targetAlex = row.querySelector('.inp-alex-' + targetDateStr);
        if(targetAlex) { 
            targetAlex.value = valAlex; 
            updateAlloc(targetDateStr, catId, 'amount_alex', targetAlex); 
        }

        const valLaia = row.querySelector('.inp-laia-' + sourceDateStr).value;
        const targetLaia = row.querySelector('.inp-laia-' + targetDateStr);
        if(targetLaia) { 
            targetLaia.value = valLaia; 
            updateAlloc(targetDateStr, catId, 'amount_laia', targetLaia); 
        }
    });
}

function recalcAllAllocations() {
    const budgetAlex = parseFloat(document.getElementById('eco_family_Alex').value) || 0;
    const budgetLaia = parseFloat(document.getElementById('eco_family_Laia').value) || 0;

    months.forEach(m => {
        let sumAlex = 0;
        let sumLaia = 0;

        document.querySelectorAll('.inp-alex-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            if (!inp.classList.contains('ignore-calc')) sumAlex += val;
            
            const row = inp.closest('tr');
            const laiaVal = parseFloat(row.querySelector('.inp-laia-' + m).value) || 0;
            const globalSum = val + laiaVal;
            
            const onchangeStr = inp.getAttribute('onchange');
            const matches = onchangeStr.match(/, (\d+),/);
            if(matches && matches[1]) {
                const gCell = document.getElementById('g_' + m + '_' + matches[1]);
                if(gCell) gCell.innerText = globalSum > 0 ? Math.round(globalSum) : '-';
            }
        });

        document.querySelectorAll('.inp-laia-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            if (!inp.classList.contains('ignore-calc')) sumLaia += val;
        });

        document.getElementById('total_alex_' + m).innerText = Math.round(sumAlex) + ' €';
        document.getElementById('total_laia_' + m).innerText = Math.round(sumLaia) + ' €';
        document.getElementById('total_global_' + m).innerText = Math.round(sumAlex + sumLaia) + ' €';

        const restAlex = budgetAlex - sumAlex;
        const restLaia = budgetLaia - sumLaia;

        const elRestAlex = document.getElementById('restant_alloc_alex_' + m);
        const elRestLaia = document.getElementById('restant_alloc_laia_' + m);

        elRestAlex.innerText = Math.round(restAlex) + ' €';
        elRestAlex.className = 'val-' + (restAlex >= 0 ? 'ok' : 'ko') + ' sum-target'; 

        elRestLaia.innerText = Math.round(restLaia) + ' €';
        elRestLaia.className = 'val-' + (restLaia >= 0 ? 'ok' : 'ko') + ' sum-target';
    });
    updateSummaryTable();
    if(isSumModeActive) updateSumResult(); 
}

function updateSummaryTable() {
    const focusMonth = months[0];
    const sums = {};
    let grandTotalAlex = 0;
    let grandTotalLaia = 0;    
    const dataByTarget = {};

    document.querySelectorAll('.inp-alex-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].alex += (parseFloat(inp.value) || 0);
        }
    });

    document.querySelectorAll('.inp-laia-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].laia += (parseFloat(inp.value) || 0);
        }
    });

    const tbody = document.querySelector('.recap-table tbody');
    if(tbody) {
        Array.from(tbody.rows).forEach(row => {
            const targetName = row.cells[0].innerText.trim(); 
            const alexSum = dataByTarget[targetName] ? dataByTarget[targetName].alex : 0;
            const laiaSum = dataByTarget[targetName] ? dataByTarget[targetName].laia : 0;
            const globalSum = alexSum + laiaSum;

            row.cells[1].innerText = Math.round(alexSum).toLocaleString(currentLang) + ' €';
            row.cells[2].innerText = Math.round(laiaSum).toLocaleString(currentLang) + ' €';
            row.cells[3].innerText = Math.round(globalSum).toLocaleString(currentLang) + ' €';

            grandTotalAlex += alexSum;
            grandTotalLaia += laiaSum;
        });
    }

    document.getElementById('grand_total_alex').innerText = Math.round(grandTotalAlex).toLocaleString(currentLang) + ' €';
    document.getElementById('grand_total_laia').innerText = Math.round(grandTotalLaia).toLocaleString(currentLang) + ' €';
    document.getElementById('grand_total_global').innerText = Math.round(grandTotalAlex + grandTotalLaia).toLocaleString(currentLang) + ' €';
}

function saveData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) formData.append(key, data[key]);
    fetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
}

function validateTransfers(person, month) {
    const msg = tr("bud_prev_confirm_transfers").replace('%p', person).replace('%m', month);
    if (!confirm(msg)) return;

    const formData = new FormData();
    formData.append('action', 'validate_transfers');
    formData.append('person', person);
    formData.append('month_date', month);
    
    fetch('/modules/budget/includes/api/save-budget.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) window.location.reload();
        else alert("Erreur: " + data.error);
    })
    .catch(e => alert(tr("bud_err_tech")));
}

function saveGenericNote(noteType, refId, content) {
    const formData = new FormData();
    formData.append('action', 'save_note');
    formData.append('note_type', noteType);    
    formData.append('reference_id', refId);    
    formData.append('content', content);       

    fetch('/modules/budget/includes/api/save-budget.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        if (!r.ok) throw new Error(`Erreur HTTP ${r.status} : ${text}`);
        if (!text) throw new Error("Le serveur a renvoyé une réponse vide.");
        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error("Réponse inattendue (non JSON) : " + text);
        }
    })
    .then(data => {
        if(data.success) {
            const indicator = document.getElementById('note-save-indicator');
            if(indicator) {
                indicator.style.opacity = '1';
                setTimeout(() => indicator.style.opacity = '0', 2000);
            }
        } else {
            alert("Erreur lors de la sauvegarde : " + data.error);
        }
    })
    .catch(e => {
        console.error('Erreur technique:', e);
        alert(e.message);
    });
}

// LOGIQUE POUR LA CALCULATRICE RAPIDE
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
    
    document.getElementById('sumResultValue').innerText = new Intl.NumberFormat(currentLang, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(total);
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
</script>