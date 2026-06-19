<?php
// modules/budget/views/budget_prev.php

// 1. Chargement dynamique des ADULTES de la famille
$stmtPeople = $pdo->query("SELECT id, name, user_id, role, color FROM pf_people WHERE role NOT IN ('enfant', 'nounou') AND is_active = 1 ORDER BY id ASC");
$budgetParents = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

// Sécurité : au cas où aucun adulte n'est trouvé, on évite un crash
if (empty($budgetParents)) {
    $budgetParents[] = ['id' => 0, 'name' => 'Utilisateur', 'color' => '#0891b2'];
}

$currentYear = date('Y');

// Cartographie dynamique (Supporte 1, 2 ou N adultes)
$parentMapping = [];
foreach ($budgetParents as $index => $parent) {
    $num = $index + 1;
    $parentMapping[] = [
        'id'        => (int)$parent['id'],
        'name'      => $parent['name'],
        'css'       => 'p' . $num,
        'color'     => $parent['color'] ?? (($num === 1) ? '#0891b2' : '#f59e0b')
    ];
}

// 1.5 Récupération des VRAIS comptes bancaires créés dans les paramètres
$stmtAccounts = $pdo->query("SELECT name FROM pf_bank_accounts ORDER BY is_default DESC, name ASC");
$bankAccounts = $stmtAccounts->fetchAll(PDO::FETCH_COLUMN);

// 2. Récupération Config Salaires
$salaryConfig = [];
$stmt = $pdo->prepare("SELECT * FROM pf_salary_config WHERE year = ?");
$stmt->execute([$currentYear]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $salaryConfig[$row['person']] = $row;
}

// Sécurité profils de salaire vides (Garantit l'affichage à 0 même si non configuré)
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

$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// 5. Récupération Valeurs Répartition Relationnelles
$inQuery = implode(',', array_fill(0, count($months), '?'));
$stmt = $pdo->prepare("SELECT * FROM pf_alloc_values WHERE month_date IN ($inQuery)");
$stmt->execute($months);

$allocs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allocs[$row['month_date']][$row['cat_id']][$row['person_id']] = (float)$row['amount'];
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
foreach ($parentMapping as $map) {
    $isValidated[$map['css']] = false;
    if ($sysCatId && isset($allocs[$focusDate][$sysCatId][$map['id']])) {
        $isValidated[$map['css']] = ($allocs[$focusDate][$sysCatId][$map['id']] == 1);
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

// --- 🧠 PREPARATION DATA TRICOUNT / AVANCES (Dynamique) ---
$stmtAdvancesList = $pdo->query("SELECT * FROM pf_advances WHERE is_resolved = 0 ORDER BY advance_date DESC");
$activeAdvances = $stmtAdvancesList->fetchAll(PDO::FETCH_ASSOC);

$advTotal = [];
$livretTotal = [];
$labelsCC = [];
$labelsLivret = [];

foreach ($parentMapping as $map) {
    $advTotal[$map['name']] = 0;
    $livretTotal[$map['name']] = 0;
    $labelsCC[$map['name']] = [];
    $labelsLivret[$map['name']] = [];
}

foreach ($activeAdvances as $adv) {
    $p = $adv['payer'];
    $amt = (float)$adv['amount'];
    $labelStr = htmlspecialchars($adv['description']) . ' (' . number_format($amt, 0, ',', ' ') . '€)';
    
    // Si un ancien nom traîne en base, on l'initialise
    if (!isset($advTotal[$p])) {
        $advTotal[$p] = 0; $livretTotal[$p] = 0;
        $labelsCC[$p] = []; $labelsLivret[$p] = [];
    }

    if ($adv['from_savings']) {
        $livretTotal[$p] += $amt;
        $labelsLivret[$p][] = $labelStr;
    } else {
        $advTotal[$p] += $amt;
        $labelsCC[$p][] = $labelStr;
    }
}

// Calcul de la dette croisée (Valable surtout si 2 parents)
$balanceDiff = 0;
$owedTo = '';
if (count($parentMapping) >= 2) {
    $p1 = $parentMapping[0]['name'];
    $p2 = $parentMapping[1]['name'];
    $balanceDiff = abs(($advTotal[$p1] ?? 0) - ($advTotal[$p2] ?? 0));
    if (($advTotal[$p1] ?? 0) > ($advTotal[$p2] ?? 0)) $owedTo = $p1;
    elseif (($advTotal[$p2] ?? 0) > ($advTotal[$p1] ?? 0)) $owedTo = $p2;
}

?>

<div class="prev-container" style="--p1-main: <?= $parentMapping[0]['color'] ?>; --p2-main: <?= $parentMapping[1]['color'] ?? '#f59e0b' ?>;">

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
                        
                        // Retrocompatibilité pour les anciens noms d'économies hardcodés
                        $catDisplayName = $cat['name'];
                        if (isset($parentMapping[0]) && $catDisplayName === 'Eco P1') $catDisplayName = 'Eco ' . $parentMapping[0]['name'];
                        if (isset($parentMapping[1]) && $catDisplayName === 'Eco P2') $catDisplayName = 'Eco ' . $parentMapping[1]['name'];
                    ?>
                <tr class="<?= $rowClass ?>">
                    <td class="col-sticky">
                        <div class="cat-name-label">
                            <?= htmlspecialchars($catDisplayName) ?>
                            <?php if(!empty($cat['holiday_id'])) echo " 🌴"; ?>
                            <?php if($isIndicative): ?><span>Info</span><?php endif; ?>
                        </div>
                        <div class="cat-target-label">
                            <?php if((float)$cat['target'] > 0) echo 'Obj: ' . round((float)$cat['target']) . '€ '; ?>
                            <?php if(!empty($cat['transfer_dest'])) echo '➔ ' . htmlspecialchars($cat['transfer_dest']); ?>
                        </div>

                        <div class="row-actions">
                            <button type="button" class="btn-icon-action edit" title="<?= tr('edit') ?>" 
                                    data-id="<?= $cat['id'] ?>" 
                                    data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                    data-target="<?= htmlspecialchars($cat['target'] ?? 0) ?>" 
                                    data-transfer-dest="<?= htmlspecialchars($cat['transfer_dest'] ?? '') ?>" 
                                    data-holiday="<?= $cat['holiday_id'] ?? '' ?>" 
                                    onclick="openEditModal(this)">✎</button>
                            <button type="button" onclick="deleteCategory(<?= $cat['id'] ?>)" class="btn-icon-action delete" title="<?= tr('delete') ?>">🗑️</button>
                        </div>
                    </td>
                    <?php foreach ($months as $m): ?>
                        <td class="txt-global sum-target" id="g_<?= $m ?>_<?= $cat['id'] ?>">0</td>

                        <?php foreach ($parentMapping as $map):
                            $cellValue = $allocs[$m][$cat['id']][$map['id']] ?? 0;
                        ?>
                        <td>
                            <input type="number" step="1" class="prev-input txt-<?= $map['css'] ?> inp-<?= $map['css'] ?>-<?= $m ?> <?= $inputClass ?>"
                                   value="<?= $cellValue == 0 ? '' : round($cellValue) ?>"
                                   placeholder="-"
                                   data-transfer-dest="<?= htmlspecialchars($cat['transfer_dest'] ?? '') ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, <?= $map['id'] ?>, this)">
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

    <?php if (count($parentMapping) > 1): ?>
    <div style="margin: 30px 0; background: var(--bg-panel); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border-light); box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 800;"><?= tr('bud_adv_title') ?></h3>
            <button type="button" class="pf-btn" onclick="document.getElementById('addAdvanceModal').classList.add('is-active')">
                ＋ <?= tr('bud_adv_add') ?>
            </button>
        </div>

        <div style="display: flex; gap: 20px; margin-bottom: 24px; flex-wrap: wrap;">
            <?php foreach ($parentMapping as $idx => $map): 
                $pName = $map['name'];
                $bgClass = ($idx === 0) ? 'rgba(8, 145, 178, 0.06)' : 'rgba(217, 119, 6, 0.06)';
                $bdClass = ($idx === 0) ? 'rgba(8, 145, 178, 0.2)' : 'rgba(217, 119, 6, 0.2)';
                $colorMain = ($idx === 0) ? '#0891b2' : '#d97706';
                $colorDark = ($idx === 0) ? '#164e63' : '#78350f';
            ?>
            <div style="flex: 1; min-width: 240px; background: <?= $bgClass ?>; border: 1px solid <?= $bdClass ?>; padding: 18px; border-radius: 12px;">
                <div style="font-size: 0.85rem; color: <?= $colorMain ?>; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;"><?= sprintf(tr('bud_adv_has_advanced'), htmlspecialchars($pName)) ?></div>
                <div style="margin-top: 10px;">
                    <div style="display: flex; align-items: baseline; gap: 6px;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: <?= $colorDark ?>; font-family: monospace;"><?= number_format($advTotal[$pName] ?? 0, 2, ',', ' ') ?> €</span>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500;"><?= tr('bud_adv_cc_label') ?></small>
                    <?php if (!empty($labelsCC[$pName])): ?>
                        <div style="font-size: 0.75rem; color: <?= $colorMain ?>; margin-top: 6px; line-height: 1.4; font-style: italic;">
                            <?= implode(', ', $labelsCC[$pName]) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if(($livretTotal[$pName] ?? 0) > 0): ?>
                <div style="margin-top: 14px; padding-top: 10px; border-top: 1px dashed <?= $bdClass ?>;">
                    <div style="font-size: 1.2rem; font-weight: 800; color: <?= $colorDark ?>; font-family: monospace;">+ <?= number_format($livretTotal[$pName], 2, ',', ' ') ?> €</div>
                    <small style="color: <?= $colorDark ?>; font-size: 0.75rem; font-weight: 600;"><?= tr('bud_adv_livret_label') ?></small>
                    <?php if (!empty($labelsLivret[$pName])): ?>
                        <div style="font-size: 0.75rem; color: <?= $colorDark ?>; margin-top: 4px; line-height: 1.4;">
                            <?= implode(', ', $labelsLivret[$pName]) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div style="flex: 1; min-width: 240px; background: var(--bg-page); border: 1px solid var(--border-light); padding: 18px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                    🏛️ <?= tr('bud_adv_cc_balance_title') ?>
                </div>
                <div style="font-size: 1.15rem; font-weight: 800;">
                    <?php if ($balanceDiff > 0.01): ?>
                        <?= sprintf(tr('bud_adv_owed_to'), number_format($balanceDiff, 2, ',', ' '), htmlspecialchars($owedTo)) ?>
                    <?php else: ?>
                        <span style="color: var(--success); font-weight: bold;">✓ <?= tr('bud_adv_balanced') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($activeAdvances)): ?>
            <div style="overflow-x:auto;">
                <table class="pf-table" style="margin: 0; box-shadow: none; border: 1px solid var(--border-light);">
                    <thead>
                        <tr>
                            <th><?= tr('date') ?></th>
                            <th><?= tr('bud_adv_payer') ?></th>
                            <th><?= tr('bud_label_name') ?></th>
                            <th><?= tr('bud_amount') ?></th>
                            <th style="text-align: right;"><?= tr('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeAdvances as $adv): 
                            // Attribution de la couleur selon l'utilisateur
                            $colorP = '#64748b'; // Defaut
                            foreach($parentMapping as $idx => $m) {
                                if($m['name'] === $adv['payer']) {
                                    $colorP = ($idx === 0) ? '#0891b2' : '#d97706';
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td style="color: var(--text-muted);"><?= date('d/m/Y', strtotime($adv['advance_date'])) ?></td>
                            <td style="font-weight: 700; color: <?= $colorP ?>;"><?= htmlspecialchars($adv['payer']) ?></td>
                            <td>
                                <?= htmlspecialchars($adv['description']) ?>
                                <?php if ($adv['from_savings']): ?>
                                    <span style="background: var(--bg-soft); color: var(--primary); font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: bold; border: 1px solid rgba(59, 130, 246, 0.2);"><?= tr('bud_adv_saved_badge') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; font-family: monospace;"><?= number_format($adv['amount'], 2, ',', ' ') ?> €</td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button class="btn-icon-action edit" title="<?= tr('edit') ?>"
                                        data-id="<?= $adv['id'] ?>"
                                        data-payer="<?= htmlspecialchars($adv['payer']) ?>"
                                        data-date="<?= $adv['advance_date'] ?>"
                                        data-desc="<?= htmlspecialchars($adv['description']) ?>"
                                        data-amount="<?= $adv['amount'] ?>"
                                        data-savings="<?= $adv['from_savings'] ?>"
                                        onclick="triggerEditAdvanceModal(this)">✏️</button>
                                <button class="btn-icon-action delete" title="<?= tr('delete') ?>" onclick="executeDeleteAdvance(<?= $adv['id'] ?>)">🗑️</button>
                                <button class="pf-btn" style="padding: 4px 10px; font-size: 0.8rem; border-radius: 6px; width:auto; height:auto;" onclick="executeResolveAdvance(<?= $adv['id'] ?>)">✓</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; // Fin du Tricount conditionnel ?>

    <?php
    $focusMonth = $months[0];
    
    // Génération dynamique des cibles basées UNIQUEMENT sur les comptes bancaires
    $targetsOrder = [];
    foreach ($bankAccounts as $accName) {
        $targetsOrder[] = 'vers ' . $accName;
    }
    
    // On ajoute quand même les cibles historiques enregistrées dans les catégories 
    // (pour ne pas casser l'affichage si on supprime un compte plus tard)
    $allTargets = $targetsOrder;
    foreach($cats as $c) {
        $t = trim($c['transfer_dest'] ?? '');
        if(!empty($t) && !in_array($t, $allTargets)) {
            $allTargets[] = $t;
        }
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

<div id="addCatModal" class="pf-modal" onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');">
    <div class="pf-modal-content" onclick="event.stopPropagation()">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 class="pf-modal-title" style="margin: 0; font-size: 1.25rem;"><?= tr('bud_prev_new_line_title') ?></h3>
            <button type="button" onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-modal-close" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color: var(--text-muted, #64748b);">×</button>
        </div>

        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_prev_label_name') ?></label>
                <input type="text" name="name" class="pf-input" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_prev_monthly_target') ?></label>
                <input type="number" step="1" name="target" class="pf-input" placeholder="<?= tr('bud_prev_target_ph') ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_prev_transfer_dest') ?></label>
                <select name="transfer_dest" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_none') ?> --</option>
                    <?php foreach ($bankAccounts as $accName): ?>
                        <option value="vers <?= htmlspecialchars($accName) ?>"><?= tr('bud_prev_transfer_to') ?> <?= htmlspecialchars($accName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('bud_add_title') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="editCatModal" class="pf-modal" onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');">
    <div class="pf-modal-content" onclick="event.stopPropagation()">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 class="pf-modal-title" style="margin: 0; font-size: 1.25rem;"><?= tr('bud_prev_edit_line_title') ?></h3>
            <button type="button" onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-modal-close" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color: var(--text-muted, #64748b);">×</button>
        </div>

        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="edit_cat_name" class="pf-input" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_prev_monthly_target') ?></label>
                <input type="number" step="1" name="target" id="edit_cat_target" class="pf-input" placeholder="<?= tr('bud_prev_target_ph') ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label"><?= tr('bud_prev_transfer_dest') ?></label>
                <select name="transfer_dest" id="edit_cat_transfer_dest" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_none') ?> --</option>
                    <?php foreach ($bankAccounts as $accName): ?>
                        <option value="vers <?= htmlspecialchars($accName) ?>"><?= tr('bud_prev_transfer_to') ?> <?= htmlspecialchars($accName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="pf-label">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" id="edit_cat_holiday" class="pf-input">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="addAdvanceModal" class="pf-modal">
    <div class="pf-modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; padding:0;">＋ <?= tr('bud_adv_add') ?></h3>
            <button type="button" onclick="closeSuiviModal('addAdvanceModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST" id="formAddAdvance" onsubmit="handleAdvanceSubmit(event, this)">
            <input type="hidden" name="action" value="save_advance">
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_adv_who_paid') ?></label>
                <select name="payer" class="pf-input" required>
                    <?php foreach ($parentMapping as $map): ?>
                        <option value="<?= htmlspecialchars($map['name']) ?>"><?= htmlspecialchars($map['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('date') ?></label>
                <input type="date" name="advance_date" class="pf-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="description" class="pf-input" placeholder="<?= tr('bud_adv_ph_desc') ?>" required autocomplete="off">
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_amount') ?></label>
                <input type="number" step="0.01" min="0.01" name="amount" class="pf-input" required>
            </div>
            <div class="pf-form-group" style="display:flex; align-items:center; gap:8px; margin-top:10px;">
                <input type="checkbox" name="from_savings" id="add_from_savings" value="1" class="pf-checkbox-lg">
                <label for="add_from_savings" style="margin:0; cursor:pointer; font-weight:600; color:var(--primary);"><?= tr('bud_adv_already_saved') ?></label>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeSuiviModal('addAdvanceModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="editAdvanceModal" class="pf-modal">
    <div class="pf-modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; padding:0;">✏️ <?= tr('bud_adv_edit') ?></h3>
            <button type="button" onclick="closeSuiviModal('editAdvanceModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST" id="formEditAdvance" onsubmit="handleAdvanceSubmit(event, this)">
            <input type="hidden" name="action" value="update_advance">
            <input type="hidden" name="id" id="edit_adv_id">
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_adv_who_paid') ?></label>
                <select name="payer" id="edit_adv_payer" class="pf-input" required>
                    <?php foreach ($parentMapping as $map): ?>
                        <option value="<?= htmlspecialchars($map['name']) ?>"><?= htmlspecialchars($map['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('date') ?></label>
                <input type="date" name="advance_date" id="edit_adv_date" class="pf-input" required>
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="description" id="edit_adv_desc" class="pf-input" required autocomplete="off">
            </div>
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('bud_amount') ?></label>
                <input type="number" step="0.01" min="0.01" name="amount" id="edit_adv_amount" class="pf-input" required>
            </div>
            <div class="pf-form-group" style="display:flex; align-items:center; gap:8px; margin-top:10px;">
                <input type="checkbox" name="from_savings" id="edit_adv_from_savings" value="1" class="pf-checkbox-lg">
                <label for="edit_adv_from_savings" style="margin:0; cursor:pointer; font-weight:600; color:var(--primary);"><?= tr('bud_adv_already_saved') ?></label>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeSuiviModal('editAdvanceModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
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
window.CONFIG = window.CONFIG || {};
window.CONFIG.parentMapping = <?= json_encode($parentMapping) ?>;
window.CONFIG.CURRENCY = '<?= defined('CURRENCY') ? CURRENCY : "€" ?>';

const currentYear = <?= $currentYear ?>;
const months = <?= json_encode($months) ?>;

/* --- LOGIQUE ALLOCATIONS & BUDGET --- */

function openEditModal(btn) {
    document.getElementById('edit_cat_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_cat_name').value = btn.getAttribute('data-name');
    document.getElementById('edit_cat_target').value = btn.getAttribute('data-target');
    document.getElementById('edit_cat_transfer_dest').value = btn.getAttribute('data-transfer-dest');
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

function updateAlloc(month, catId, personId, input) {
    saveData('update_allocation', { month_date: month, cat_id: catId, person_id: personId, value: input.value || 0 });
    recalcAllAllocations();
}

function duplicateMonth() {
    const targetDateStr = months[0];
    const sourceDateStr = months[1];
    const parentMap = window.CONFIG.parentMapping;

    if (!sourceDateStr) {
        alert(tr('bud_prev_err_no_history'));
        return;
    }

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(sourceDateStr);
    const targetName = formatMonth(targetDateStr);
    const message = tr('bud_prev_confirm_copy').replace('%s', sourceName).replace('%t', targetName);

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
                updateAlloc(targetDateStr, catId, map.id, tInp);
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
            const dest = inp.getAttribute('data-transfer-dest');
            if(dest) {
                if(!dataByTarget[map.css][dest]) dataByTarget[map.css][dest] = 0;
                dataByTarget[map.css][dest] += (parseFloat(inp.value) || 0);
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
    formData.append('ajax', '1');
    for (const key in data) formData.append(key, data[key]);
    
    fetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
}

async function validateTransfers(personCss, month) {
    const parentMap = window.CONFIG.parentMapping.find(m => m.css === personCss);
    if (!parentMap) return;

    const msg = tr('bud_prev_confirm_transfers').replace('%p', parentMap.name).replace('%m', month);
    if (!confirm(msg)) return;

    const formData = new FormData();
    formData.append('action', 'validate_transfers');
    formData.append('person_id', parentMap.id);
    formData.append('month_date', month);
    formData.append('ajax', '1'); 

    try {
        const result = await pachaFetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
        if(result.success) {
            window.location.reload();
        } else {
            alert(tr('bud_err_tech') + " : " + result.error);
        }
    } catch(e) {
        alert(tr('bud_err_tech'));
    }
}

async function saveGenericNote(noteType, refId, content) {
    const formData = new FormData();
    formData.append('action', 'save_note');
    formData.append('note_type', noteType);
    formData.append('reference_id', refId);
    formData.append('content', content);
    formData.append('ajax', '1');

    try {
        const data = await pachaFetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
        if(data.success) {
            const indicator = document.getElementById('note-save-indicator');
            if(indicator) {
                indicator.style.opacity = '1';
                setTimeout(() => indicator.style.opacity = '0', 2000);
            }
        } else {
            alert(tr('bud_err_tech') + " : " + data.error);
        }
    } catch(e) {
        alert(tr('bud_err_tech'));
    }
}

async function deleteCategory(id) {
    if (!confirm(tr('bud_prev_confirm_del_line'))) return;
    const formData = new FormData();
    formData.append('action', 'delete_category');
    formData.append('id', id);
    formData.append('ajax', '1');
    try {
        const result = await pachaFetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
        if (result.success) window.location.reload();
    } catch(e) { console.error(e); }
}

/* --- LOGIQUE TRICOUNT / AVANCES --- */

function closeSuiviModal(modalId) {
    document.getElementById(modalId).classList.remove('is-active');
}

function openSuiviModal(modalId) {
    document.getElementById(modalId).classList.add('is-active');
}

function triggerEditAdvanceModal(btn) {
    document.getElementById('edit_adv_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_adv_payer').value = btn.getAttribute('data-payer');
    document.getElementById('edit_adv_date').value = btn.getAttribute('data-date');
    document.getElementById('edit_adv_desc').value = btn.getAttribute('data-desc');
    document.getElementById('edit_adv_amount').value = btn.getAttribute('data-amount');
    document.getElementById('edit_adv_from_savings').checked = (btn.getAttribute('data-savings') === '1');
    openSuiviModal('editAdvanceModal');
}

async function handleAdvanceSubmit(event, form) {
    event.preventDefault();
    const btnSubmit = form.querySelector('button[type="submit"]');
    const oldText = btnSubmit.innerHTML;
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '...';

    const endpoint = form.getAttribute('action');
    const formData = new FormData(form);
    formData.append('ajax', '1');

    try {
        const result = await pachaFetch(endpoint, { method: 'POST', body: formData });
        
        if (result.success) {
            window.location.reload();
        } else {
            alert((tr('bud_err_tech') || "Erreur") + " : " + (result.error || "Opération échouée"));
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = oldText;
        }
    } catch (err) {
        console.error("AJAX Error:", err);
        alert(tr('bud_err_tech') || "Une erreur technique est survenue.");
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = oldText;
    }
}

async function executeResolveAdvance(id) {
    if (!confirm(tr('bud_adv_confirm_resolve'))) return;
    const fd = new FormData();
    fd.append('action', 'resolve_advance');
    fd.append('id', id);
    fd.append('ajax', '1');
    
    try {
        await pachaFetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: fd });
        window.location.reload();
    } catch(err) {
        console.error(err);
    }
}

async function executeDeleteAdvance(id) {
    if (!confirm(tr('bud_adv_confirm_delete'))) return;
    const fd = new FormData();
    fd.append('action', 'delete_advance');
    fd.append('id', id);
    fd.append('ajax', '1');
    
    try {
        await pachaFetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: fd });
        window.location.reload();
    } catch(err) {
        console.error(err);
    }
}

/* --- LOGIQUE MODE SOMME AUTOMATIQUE --- */

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

/* --- INITIALISATIONS & ECOUTEURS --- */

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
                alert((tr('bud_err_tech') || 'Erreur') + " : " + (result.error || "Inconnue"));
            }
        } catch (error) {
            alert(tr('bud_err_tech') || "Une erreur technique est survenue.");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
});
</script>