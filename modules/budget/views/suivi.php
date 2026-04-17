<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. GESTION DU MOIS ACTIF ET DE LA NAVIGATION
// ============================================================================

$stmtActive = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'active_gestion_month' LIMIT 1");
$defaultActiveMonth = $stmtActive->fetchColumn();
if (!$defaultActiveMonth) {
    $defaultActiveMonth = date('Y-m-01');
}

$viewM = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($defaultActiveMonth));
$viewY = isset($_GET['y']) ? (int)$_GET['y'] : date('Y', strtotime($defaultActiveMonth));
$viewMonthDate = "$viewY-$viewM-01";

$prevDate = date('Y-m-01', strtotime('-1 month', strtotime($viewMonthDate)));
$nextDate = date('Y-m-01', strtotime('+1 month', strtotime($viewMonthDate)));

$prevLink = "?tab=suivi&m=" . date('m', strtotime($prevDate)) . "&y=" . date('Y', strtotime($prevDate));
$nextLink = "?tab=suivi&m=" . date('m', strtotime($nextDate)) . "&y=" . date('Y', strtotime($nextDate));
$defaultLink = "?tab=suivi";

// ============================================================================
// 2. GESTION DES ACTIONS POST (Clôture, Ajouts, CSV)
// ============================================================================

if (isset($_POST['action']) && $_POST['action'] === 'close_month') {
    $monthToClose = $_POST['close_month_date'];
    $nextMonthToOpen = date('Y-m-01', strtotime('+1 month', strtotime($monthToClose)));
    
    $frozenData = [
        'is_closed' => true,
        'solde_actuel' => (float)$_POST['freeze_solde_actuel'],
        'capacite_max' => (float)$_POST['freeze_capacite_max'],
        'solde_theorique' => (float)$_POST['freeze_solde_theorique'],
        'reste_a_venir' => (float)$_POST['freeze_reste_a_venir'],
        'closed_at' => date('Y-m-d H:i:s')
    ];
    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('month_closure', ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$monthToClose, json_encode($frozenData)]);

    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('active_gestion_month', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$nextMonthToOpen]);

    echo "<script>window.location.href='?tab=suivi&m=" . date('m', strtotime($nextMonthToOpen)) . "&y=" . date('Y', strtotime($nextMonthToOpen)) . "';</script>";
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'reopen_month') {
    $pdo->prepare("DELETE FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?")->execute([$viewMonthDate]);
    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('active_gestion_month', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$viewMonthDate]);
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>";
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_expense_manual') {
    $id = !empty($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;
    $cat = $_POST['category']; 
    $amount = floatval($_POST['amount']); 
    $date = $_POST['date'];
    $gestionMonth = $_POST['gestion_month']; 
    $label = trim($_POST['label']);
    $budgetItemId = null;
    $holidayId = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

    if ($cat === 'School' && !empty($_POST['label_select'])) $label = trim($_POST['label_select']);
    elseif (($cat === 'Frais' || $cat === 'Income') && !empty($_POST['budget_item_id'])) $budgetItemId = (int)$_POST['budget_item_id'];

    if ($label && $amount > 0) {
        $is_credit = isset($_POST['is_credit']) ? (int)$_POST['is_credit'] : 0;
        $finalAmount = $is_credit ? abs($amount) : -abs($amount);
        
        if ($id) {
            $pdo->prepare("UPDATE pf_expenses SET date_exp=?, gestion_month=?, category=?, label=?, amount=?, budget_item_id=?, holiday_id=? WHERE id=?")
                ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $budgetItemId, $holidayId, $id]);
        } else {
            $uniqueRef = "MANUAL_" . uniqid();
            $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $uniqueRef, $budgetItemId, $holidayId]);
        }
        
        echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>"; 
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (isset($line['import_check'])) {
                $cat = $line['cat'];
                $is_credit = isset($line['is_credit']) ? (int)$line['is_credit'] : 0;
                $budgetItemId = !empty($line['budget_item_id']) ? (int)$line['budget_item_id'] : null;
                $holidayId = !empty($line['holiday_id']) ? (int)$line['holiday_id'] : null;
                $gestionMonthLine = !empty($line['gestion_month']) ? $line['gestion_month'] . '-01' : $viewMonthDate; 
                
                if ($is_credit && empty($cat)) continue;
                if (!$is_credit && empty($cat)) continue;

                $finalAmount = $is_credit ? abs($line['amount']) : -abs($line['amount']);
                $dateToSave = $line['date'];

                try {
                    $stmtExp->execute([$dateToSave, $gestionMonthLine, $cat, $line['label'], $finalAmount, $line['ref'], $budgetItemId, $holidayId]);
                    $stmtRule->execute([$line['label'], $cat]);
                    $count++;
                } catch (Exception $e) { continue; }
            }
        }
    }
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY&msg=imported_$count';</script>"; 
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_snapshot') {
    $pdo->query("DELETE FROM pf_bank_snapshots"); 
    $pdo->prepare("INSERT INTO pf_bank_snapshots (snapshot_date, amount) VALUES (?, ?)")->execute([$_POST['snapshot_date'], floatval($_POST['snapshot_amount'])]);
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>"; 
    exit;
}

if (isset($_GET['delete_expense'])) {
    $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([(int)$_GET['delete_expense']]);
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>"; 
    exit;
}

// ============================================================================
// E-Bis. LECTURE ET PRÉVISUALISATION DU FICHIER CSV
// ============================================================================
$csvData = [];
$showPreview = false;

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $rules = []; try { $rules = $pdo->query("SELECT keyword, category FROM pf_import_rules")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
    $existingRefs = []; try { $existingRefs = $pdo->query("SELECT import_ref FROM pf_expenses WHERE import_ref IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e){}
    fgetcsv($handle, 1000, ";", "\"", "\\"); 
    
    while (($data = fgetcsv($handle, 1000, ";", "\"", "\\")) !== FALSE) {
        $rawDebit = $data[8] ?? ''; $rawCredit = $data[9] ?? ''; 
        $amount = 0; $isCredit = 0;
        if (!empty(trim($rawCredit))) { $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawCredit))); $isCredit = 1; }
        elseif (!empty(trim($rawDebit))) { $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawDebit))); }
        else continue; 

        $dateParts = explode('/', $data[0]); 
        $dateSql = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : date('Y-m-d');
        $label = trim($data[1]) ?: trim($data[2]);
        $refCSV = trim($data[3]);
        $uniqueKey = !empty($refCSV) ? "REF_".$refCSV : "HASH_".md5($dateSql.$label.number_format($amount, 2).$isCredit);
        $isDuplicate = in_array($uniqueKey, $existingRefs);
        
        $suggestedCat = '';
        foreach ($rules as $kw => $c) { if (stripos($label, $kw) !== false) { $suggestedCat = $c; break; } }
        
        $csvData[] = ['date'=>$dateSql, 'label'=>$label, 'amount'=>$amount, 'cat'=>$suggestedCat, 'ref'=>$uniqueKey, 'is_duplicate'=>$isDuplicate, 'is_credit'=>$isCredit];
    }
    fclose($handle);
    $showPreview = true;
}

// ============================================================================
// 3. RECUPERATION DES DONNEES ET CALCULS
// ============================================================================

$stmtCheckClose = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?");
$stmtCheckClose->execute([$viewMonthDate]);
$closureJson = $stmtCheckClose->fetchColumn();
$monthState = $closureJson ? json_decode($closureJson, true) : null;
$isClosed = ($monthState && isset($monthState['is_closed']) && $monthState['is_closed'] === true);

$snapshot = ['date' => date('Y-m-d'), 'amount' => 0];
$snapStmt = $pdo->query("SELECT * FROM pf_bank_snapshots ORDER BY id DESC LIMIT 1");
if ($s = $snapStmt->fetch(PDO::FETCH_ASSOC)) {
    $snapshot = ['date' => $s['snapshot_date'], 'amount' => (float)$s['amount']];
}

$stmtPrevClose = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?");
$stmtPrevClose->execute([$prevDate]);
$prevClosureJson = $stmtPrevClose->fetchColumn();
$prevMonthState = $prevClosureJson ? json_decode($prevClosureJson, true) : null;

$solde_initial = ($prevMonthState && isset($prevMonthState['solde_actuel'])) ? (float)$prevMonthState['solde_actuel'] : 0; 
if ($solde_initial === 0 && !$prevMonthState) {
    $solde_initial = $snapshot['amount'];
}

$stmt = $pdo->prepare("SELECT * FROM pf_expenses WHERE gestion_month = ? ORDER BY date_exp DESC");
$stmt->execute([$viewMonthDate]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paidItemIds = array_column(array_filter($allExpenses, fn($e) => !empty($e['budget_item_id'])), 'budget_item_id');
$realExpensesLabels = array_column(array_filter($allExpenses, fn($e) => $e['amount'] < 0), 'label');

$budget_fmcg = 0; $budget_school = 0; $budget_essence = 0; $budget_frais = 0; $budget_income_prevu = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir_calc = 0; 
$fixedChargesList = []; $incomeList = []; $pending_charges = [];

$stmt = $pdo->query("SELECT id, name, amount, type, category, is_estimate, payment_day, mapping_keywords FROM pf_budget_items ORDER BY name ASC");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $absAmount = abs((float)$item['amount']); 
    $amt = ($item['type'] === 'Annuel') ? $absAmount / 12 : $absAmount;
    $name = trim($item['name']);
    
    if ($item['category'] === 'expense' && $item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
        $fixedChargesList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount]; 
    }
    if ($item['category'] === 'income') {
        $incomeList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount];
        $total_income += $amt;
        $budget_income_prevu += $amt; 
    } else {
        $total_expenses_prevues += $amt;
        if ($item['category'] === 'expense' && $item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
            $isPaid = false;
            if (in_array($item['id'], $paidItemIds)) $isPaid = true;
            elseif (!empty($item['mapping_keywords'])) {
                $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                foreach ($keywords as $kw) {
                    if (empty($kw)) continue;
                    foreach ($realExpensesLabels as $realLabel) {
                        if (stripos($realLabel, $kw) !== false) { $isPaid = true; break 2; }
                    }
                }
            }
            if (!$isPaid) {
                $reste_a_venir_calc += $absAmount;
                $pending_charges[] = ['name' => $name, 'amount' => $absAmount];
            }
        }
        if ($name === 'Estimacio F&B & beauty') $budget_fmcg = $amt;
        elseif ($name === 'Estimacio escola') $budget_school = $amt;
        elseif ($name === 'Estimation gasolina') $budget_essence = $amt;
        elseif ((int)$item['is_estimate'] === 0 && $item['type'] === 'Mensuel' && $item['category'] === 'expense') { $budget_frais += $absAmount; }
    }
}

$budget_autres = max(0, $total_income - $total_expenses_prevues);

// TRADUCTION DES CATÉGORIES
$categoriesConfig = [
    'Income'  => ['type'=>'credit', 'label'=>tr('cat_income'), 'budget'=>$budget_income_prevu, 'color'=>'#10b981', 'suggestions'=>[]],
    'FMCG'    => ['type'=>'debit',  'label'=>tr('cat_fmcg'),   'budget'=>$budget_fmcg, 'color'=>'#3b82f6', 'suggestions'=>['Action', 'Carrefour', 'Lidl']],
    'Essence' => ['type'=>'debit',  'label'=>tr('cat_fuel'),   'budget'=>$budget_essence, 'color'=>'#f59e0b', 'suggestions'=>['Audi', 'Polo']],
    'School'  => ['type'=>'debit',  'label'=>tr('cat_school'), 'budget'=>$budget_school, 'color'=>'#10b981', 'suggestions'=>[]],
    'Frais'   => ['type'=>'debit',  'label'=>tr('cat_fixed'),  'budget'=>$budget_frais, 'color'=>'#ef4444', 'suggestions'=>[]],
    'Autres'  => ['type'=>'debit',  'label'=>tr('cat_others'), 'budget'=>$budget_autres, 'color'=>'#64748b', 'suggestions'=>['Restaurant', 'Cadeau']],
    'LivretA' => ['type'=>'debit',  'label'=>tr('cat_savings'),'budget'=>0, 'color'=>'#8b5cf6', 'suggestions'=>['Virement']]
];

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);
$total_rentrees = 0;
$depenses_reelles = 0;

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($totals[$cat])) $cat = 'Autres';
    $val = (float)$exp['amount'];
    
    if ($cat === 'Income') { $totals[$cat] += $val; } 
    else {
        if ($val > 0) $categoriesConfig[$cat]['budget'] += $val;
        else $totals[$cat] += abs($val);
    }
    $expensesByCategory[$cat][] = $exp;

    if ($val > 0) { 
        if ($cat === 'Income' || $cat !== 'Frais') $total_rentrees += $val; 
        else $depenses_reelles -= $val; 
    } else {
        $depenses_reelles += abs($val);
    }
}

// Reste à venir dynamiques
$ecole_depense = isset($totals['School']) ? $totals['School'] : 0;
$reste_ecole = max(0, $budget_school - $ecole_depense);
if ($reste_ecole > 0) {
    $reste_a_venir_calc += $reste_ecole;
    $pending_charges[] = ['name' => tr('bud_rem_school'), 'amount' => $reste_ecole];
}

$fmcg_depense = isset($totals['FMCG']) ? $totals['FMCG'] : 0;
$reste_fmcg = max(0, $budget_fmcg - $fmcg_depense);
if ($reste_fmcg > 0) {
    $reste_a_venir_calc += $reste_fmcg;
    $pending_charges[] = ['name' => tr('bud_rem_fmcg'), 'amount' => $reste_fmcg];
}

$essence_depense = isset($totals['Essence']) ? $totals['Essence'] : 0;
$reste_essence = max(0, $budget_essence - $essence_depense);
if ($reste_essence > 0) {
    $reste_a_venir_calc += $reste_essence;
    $pending_charges[] = ['name' => tr('bud_rem_fuel'), 'amount' => $reste_essence];
}

// F. Calculs des KPIs finaux
$rentrees_salaires_reels = $totals['Income'] ?? 0;
$rentrees_autres = $total_rentrees - $rentrees_salaires_reels;
$salaires_retenus = max($rentrees_salaires_reels, $budget_income_prevu);

$capacite_max_calc = $solde_initial + $salaires_retenus + $rentrees_autres;
$solde_theorique_calc = ($solde_initial + $total_rentrees) - $depenses_reelles - $reste_a_venir_calc;

if ($isClosed) {
    $solde_actuel = $monthState['solde_actuel'];
    $capacite_max = $monthState['capacite_max'];
    $solde_theorique = $monthState['solde_theorique'];
    $reste_a_venir = $monthState['reste_a_venir'];
} else {
    $solde_actuel = $snapshot['amount'];
    $capacite_max = $capacite_max_calc;
    $solde_theorique = $solde_theorique_calc;
    $reste_a_venir = $reste_a_venir_calc;
}

$solde_net = max(0, $solde_actuel - $reste_a_venir);
$charges_visibles = min($solde_actuel, $reste_a_venir); 
$max_scale = max($solde_actuel, $solde_theorique, $capacite_max, 1) * 1.1; 

$pct_net = min(100, max(0, ($solde_net / $max_scale) * 100));
$pct_charges = min(100 - $pct_net, max(0, ($charges_visibles / $max_scale) * 100));
$pct_actuel = min(100, max(0, ($solde_actuel / $max_scale) * 100));
$pct_theorique = min(100, max(0, ($solde_theorique / $max_scale) * 100));

function getDisplayLogic($spent, $bg, $type) {
    $pct = ($bg > 0) ? min(100, ($spent / $bg) * 100) : ($spent > 0 ? 100 : 0);
    $isOver = ($type === 'debit' && $spent > $bg && $bg > 0);
    $text = ($bg > 0) ? number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €' : number_format(ceil($spent), 0, ',', ' ') . ' €';
    return ['pct' => $pct, 'isOver' => $isOver, 'text' => $text];
}

// Noms des mois traduits
$monthNames = [
    1 => tr('month_01'), 2 => tr('month_02'), 3 => tr('month_03'), 4 => tr('month_04'),
    5 => tr('month_05'), 6 => tr('month_06'), 7 => tr('month_07'), 8 => tr('month_08'),
    9 => tr('month_09'), 10 => tr('month_10'), 11 => tr('month_11'), 12 => tr('month_12')
];
$monthName = $monthNames[(int)$viewM] . ' ' . $viewY;
?>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:24px; border:1px solid #e2e8f0; position:relative;">
        
        <?php if ($isClosed): ?>
            <div style="position:absolute; top:0; left:0; right:0; background:#f1f5f9; padding:5px; text-align:center; font-size:0.8rem; font-weight:bold; color:#64748b; border-radius:16px 16px 0 0; border-bottom:1px solid #e2e8f0;">
                🔒 <?= tr('bud_closed_archived') ?>
            </div>
            <div style="height:20px;"></div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0; font-size:1.3rem; color:#0f172a; text-transform:capitalize;"><?= tr('bud_budget_of') ?> <?= $monthName ?></h2>
            </div>
            
            <div style="display:flex; gap:10px;">
                <?php if (!$isClosed): ?>
                    <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_close') ?>');">
                        <input type="hidden" name="action" value="close_month">
                        <input type="hidden" name="close_month_date" value="<?= $viewMonthDate ?>">
                        <input type="hidden" name="freeze_solde_actuel" value="<?= $solde_actuel ?>">
                        <input type="hidden" name="freeze_capacite_max" value="<?= $capacite_max ?>">
                        <input type="hidden" name="freeze_solde_theorique" value="<?= $solde_theorique ?>">
                        <input type="hidden" name="freeze_reste_a_venir" value="<?= $reste_a_venir ?>">
                        <button type="submit" class="pf-btn" style="background:linear-gradient(135deg, #10b981, #059669); padding:6px 12px; height:auto; width:auto; font-size:0.85rem;">🔒 <?= tr('bud_close_btn') ?></button>
                    </form>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_reopen') ?>');">
                        <input type="hidden" name="action" value="reopen_month">
                        <button type="submit" class="pf-btn btn-secondary" style="padding:6px 12px; height:auto; width:auto; font-size:0.85rem;">🔓 <?= tr('bud_reopen_btn') ?></button>
                    </form>
                <?php endif; ?>

                <div class="suivi-nav-group">
                    <a href="<?= $prevLink ?>" class="suivi-btn-nav">◀</a>
                    <a href="<?= $defaultLink ?>" class="suivi-btn-nav"><?= tr('bud_active_month_btn') ?></a>
                    <a href="<?= $nextLink ?>" class="suivi-btn-nav">▶</a>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
            <div style="padding:15px; background:#f8fafc; border-radius:12px; position:relative; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;"><?= tr('bud_bank_balance') ?> <?= $isClosed ? "(".tr('bud_frozen').")" : "(".tr('bud_current').")" ?></div>
                <div style="font-size:1.4rem; font-weight:700; color:#0f172a;">
                    <?= number_format($solde_actuel, 2, ',', ' ') ?> €
                </div>
                <?php if (!$isClosed): ?>
                    <button onclick="openSuiviModal('snapshotModal')" style="position:absolute; top:12px; right:12px; background:white; border:1px solid #cbd5e1; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:0.8rem; color:#475569; transition:0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">✏️ <?= tr('bud_update_btn') ?></button>
                <?php endif; ?>
            </div>

            <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;"><?= tr('bud_theoretical_balance') ?></div>
                <div style="font-size:1.4rem; font-weight:700; color:<?= $solde_theorique < 0 ? '#ef4444' : '#334155' ?>;">
                    <?= number_format($solde_theorique, 2, ',', ' ') ?> €
                </div>
            </div>

            <div style="padding:15px; background:#fef2f2; border-radius:12px; border:1px solid #fecaca; position: relative;">
                <div style="font-size:0.85rem; color:#991b1b; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center;">
                    <span><?= tr('bud_upcoming_charges') ?></span>
                    <button onclick="toggleDiv('pendingDetailsList')" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; filter: grayscale(1); opacity: 0.7; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">👁️</button>
                </div>
                <div style="font-size:1.4rem; font-weight:700; color:#b91c1c;">
                    - <?= number_format($reste_a_venir, 2, ',', ' ') ?> €
                </div>
                <div id="pendingDetailsList" style="display:none; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #fca5a5; font-size: 0.8rem; color: #7f1d1d;">
                    <?php if (empty($pending_charges)): ?>
                        <div style="text-align:center; font-style:italic; opacity:0.8;"><?= tr('bud_all_paid') ?></div>
                    <?php else: ?>
                        <?php foreach($pending_charges as $pc): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span><?= htmlspecialchars($pc['name']) ?></span>
                                <strong><?= number_format($pc['amount'], 0, ',', ' ') ?> €</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; padding: 30px 10px 45px 10px; position:relative;">
            <div style="position: absolute; top: 0px; left: 10px; font-size: 0.8rem; color: #64748b; font-weight: 700;">0 €</div>
            
            <div style="position: absolute; top: 0px; right: 10px; font-size: 0.8rem; color: #64748b; font-weight: 700; text-align:right; cursor:help;" 
                 title="<?= sprintf(tr('bud_capacity_tooltip'), number_format($solde_initial, 0), number_format($salaires_retenus, 0), number_format($rentrees_autres, 0)) ?>">
                <?= tr('bud_max_capacity') ?> : <?= number_format($capacite_max, 0, ',', ' ') ?> € ℹ️
            </div>

            <div style="position: absolute; top: 12px; left: <?= $pct_actuel ?>%; transform: translateX(-50%); text-align: center; z-index: 10;">
                <div style="color: #334155; font-size: 0.75rem; font-weight: 800; white-space: nowrap; margin-bottom: 2px;"><?= tr('bud_actual') ?></div>
                <div style="width: 3px; height: 18px; background: #334155; margin: 0 auto; border-radius: 2px;"></div>
            </div>
            <div style="height: 24px; background: #e2e8f0; border-radius: 12px; display: flex; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <div style="width: <?= $pct_net ?>%; background: linear-gradient(90deg, #3b82f6, #0ea5e9);"></div>
                <div style="width: <?= $pct_charges ?>%; background: repeating-linear-gradient(45deg, #f59e0b, #f59e0b 8px, #fbbf24 8px, #fbbf24 16px);"></div>
            </div>
            <div style="position: absolute; top: 54px; left: <?= $pct_theorique ?>%; transform: translateX(-50%); text-align: center; z-index: 10;">
                <div style="width: 3px; height: 18px; background: #8b5cf6; margin: 0 auto 2px auto; border-radius: 2px;"></div>
                <div style="color: #8b5cf6; font-size: 0.75rem; font-weight: 800; white-space: nowrap;"><?= tr('bud_theoretical') ?></div>
            </div>
        </div>
    </div>

    <?php if (!$isClosed): ?>
        <div style="display:flex; gap:10px; margin-bottom:20px;">
            <button class="pf-btn btn-secondary" onclick="toggleDiv('importCsvForm')">📂 <?= tr('bud_import_csv') ?></button>
        </div>

        <div id="importCsvForm" style="display:<?= $showPreview ? 'block' : 'none' ?>; background:#f8fafc; padding:20px; border-radius:12px; margin-bottom:20px;">
            <?php if (!$showPreview): ?>
                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <label class="pf-label"><?= tr('bud_csv_file') ?></label>
                        <input type="file" name="csv_file" accept=".csv" class="pf-input">
                    </div>
                    <button type="submit" class="pf-btn" style="width:auto;"><?= tr('bud_preview') ?></button>
                </form>
            <?php else: ?>
                <form method="POST" id="formMapping">
                    <input type="hidden" name="action" value="save_import">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                        <h3 style="margin:0;"><?= tr('bud_validate_import') ?></h3>
                        <span id="missingCount" style="color:#ef4444; background:#fee2e2; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.85rem; display:none;"></span>
                    </div>

                    <div style="max-height:350px; overflow-y:auto; background:white; border:1px solid #eee; border-radius:8px;">
                        <table class="pf-table" style="margin:0;">
                            <thead style="position:sticky; top:0; z-index:10;">
                                <tr><th><input type="checkbox" onclick="toggleAll(this)" checked></th><th><?= tr('bud_assign_month') ?></th><th><?= tr('bud_op_date') ?></th><th><?= tr('bud_label') ?></th><th><?= tr('bud_amount') ?></th><th><?= tr('bud_category') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($csvData as $idx => $row): 
                                    $dup = $row['is_duplicate']; $isCrd = $row['is_credit']; $dis = $dup?'disabled':''; 
                                    $bgCol = $dup ? 'opacity:0.5' : (empty($row['cat']) && !$isCrd ? 'background:#fff1f2' : '');
                                ?>
                                <tr style="<?= $bgCol ?>">
                                    <td>
                                        <input type="checkbox" class="line-checkbox" name="lines[<?= $idx ?>][import_check]" value="1" <?= $dup?'':'checked' ?> <?= $dis ?> onchange="checkValidation()">
                                        <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                                        <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= $row['label'] ?>">
                                        <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                                        <input type="hidden" name="lines[<?= $idx ?>][ref]" value="<?= $row['ref'] ?>">
                                        <input type="hidden" class="is-credit-flag" name="lines[<?= $idx ?>][is_credit]" value="<?= $isCrd ?>">
                                    </td>
                                    <td>
                                        <input type="month" name="lines[<?= $idx ?>][gestion_month]" value="<?= substr($viewMonthDate, 0, 7) ?>" class="pf-input" style="padding:4px;" <?= $dis ?>>
                                    </td>
                                    <td style="white-space:nowrap; font-weight:600;"><?= date('d/m', strtotime($row['date'])) ?></td>
                                    <td><?= htmlspecialchars($row['label']) ?></td>
                                    <td style="font-weight:bold; color:<?= $isCrd ? '#10b981' : '#1e293b' ?>; white-space:nowrap;"><?= $isCrd ? '+' : '-' ?> <?= number_format($row['amount'],2) ?> €</td>
                                    <td>
                                        <div style="display:flex; gap:5px;">
                                            <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="handleLineCatChange(this)" <?= $dis ?> style="flex:1;">
                                                <option value="">-- <?= $isCrd ? tr('bud_ignore') : tr('bud_to_define') ?> --</option>
                                                <?php foreach ($categoriesConfig as $k => $c): ?>
                                                    <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= $c['label'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-frais" onchange="checkValidation()" style="display:none; flex:1;" disabled>
                                                <option value="">-- <?= tr('bud_is_charge') ?> --</option>
                                                <?php foreach ($fixedChargesList as $fc): ?><option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option><?php endforeach; ?>
                                            </select>
                                            <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-income" onchange="checkValidation()" style="display:none; flex:1;" disabled>
                                                <option value="">-- <?= tr('bud_is_income') ?> --</option>
                                                <?php foreach ($incomeList as $inc): ?><option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:15px; display:flex; justify-content:flex-end; gap:10px;">
                        <a href="?tab=suivi" class="pf-btn btn-secondary" style="width:auto;"><?= tr('btn_cancel') ?></a>
                        <button type="submit" id="btnImport" class="pf-btn" style="width:auto;"><?= tr('btn_import') ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="categories-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
        <?php foreach ($categoriesConfig as $key => $conf): ?>
        <div class="cat-card" <?= $isClosed ? 'style="opacity:0.8;"' : '' ?>>
            <div style="background:<?= $conf['color'] ?>15; padding:15px; border-bottom:1px solid <?= $conf['color'] ?>30; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0; font-size:1rem; color:<?= $conf['color'] ?>;"><?= $conf['label'] ?></h3>
                    <div style="font-size:0.85rem; color:#64748b; font-weight:600; margin-top:2px;">
                        <?php $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']); echo $logic['text']; ?>
                    </div>
                </div>
                <?php if (!$isClosed): ?>
                    <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes($conf['label']) ?>')">＋</button>
                <?php endif; ?>
            </div>

            <?php $barCol = ($key === 'Income') ? '#10b981' : ($logic['isOver'] ? '#ef4444' : $conf['color']); ?>
            <div style="background:#f1f5f9; height:4px; width:100%;">
                <div style="width:<?= $logic['pct'] ?>%; background:<?= $barCol ?>; height:100%;"></div>
            </div>

            <div style="flex:1; max-height:300px; overflow-y:auto; padding:0;">
                <?php if (empty($expensesByCategory[$key])): ?>
                    <div style="padding:20px; text-align:center; color:#cbd5e1; font-style:italic; font-size:0.85rem;"><?= tr('bud_no_lines') ?></div>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <?php foreach ($expensesByCategory[$key] as $exp): ?>
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:10px 15px; color:#94a3b8;"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                            <td style="padding:10px 5px; font-weight:500;">
                                <?= htmlspecialchars($exp['label']) ?>
                            </td>
                            <td style="padding:10px 15px; text-align:right; font-weight:600; color:<?= $exp['amount'] > 0 ? '#10b981' : '#1e293b' ?>;">
                                <?= $exp['amount'] > 0 ? '+' : '-' ?><?= number_format(abs($exp['amount']), 2) ?>
                            </td>
                            <?php if (!$isClosed): ?>
                                <td style="width:60px; padding-right:10px; text-align:right; white-space:nowrap;">
                                    <button onclick='openEditModal(<?= json_encode($exp, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:none; border:none; cursor:pointer; font-size:1rem; margin-right:8px;">✏️</button>
                                    <a href="?tab=suivi&delete_expense=<?= $exp['id'] ?>" onclick="return confirm('<?= tr('bud_confirm_delete') ?>')" style="color:#ef4444; text-decoration:none; font-size:1.4rem;">&times;</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="manualExpenseModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTitle"><?= tr('bud_new_transaction') ?></h3>
            <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_expense_manual">
            <input type="hidden" name="expense_id" id="modalExpenseId">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_assign_to_month') ?> :</label>
                <input type="month" name="gestion_month" id="modalGestionMonth" class="pf-input" style="font-weight:bold; color:#2563eb;" required>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_real_date') ?></label>
                <input type="date" name="date" id="modalDate" class="pf-input" required>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_category') ?></label>
                <select name="category" id="modalCatSelect" class="pf-input" onchange="handleModalCatChange(this)">
                    <?php foreach($categoriesConfig as $key => $conf): ?>
                        <option value="<?= $key ?>"><?= $conf['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_type') ?></label>
                <select name="is_credit" id="modalIsCredit" class="pf-input">
                    <option value="0"><?= tr('bud_type_expense') ?> (-)</option>
                    <option value="1"><?= tr('bud_type_income') ?> (+)</option>
                </select>
            </div>
            
            <div class="form-group" id="blockInputText" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_label') ?></label>
                <input type="text" name="label" class="pf-input" id="modalLabelInput" list="modalSuggestions" autocomplete="off">
                <datalist id="modalSuggestions"></datalist>
            </div>

            <div class="form-group" id="blockInputSelect" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_beneficiary') ?></label>
                <select name="label_select" id="schoolSelect" class="pf-input">
                    <option value="Ecole Pol">Ecole Pol</option>
                    <option value="Carole">Carole</option>
                </select>
            </div>

            <div class="form-group" id="blockInputFrais" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_fixed_charge') ?></label>
                <select name="budget_item_id" id="fraisSelect" class="pf-input" disabled>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($fixedChargesList as $fc): ?><option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="blockInputIncome" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_expected_income') ?></label>
                <select name="budget_item_id" id="incomeSelect" class="pf-input" disabled>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($incomeList as $inc): ?><option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_amount_eur') ?></label>
                <input type="number" step="0.01" name="amount" id="modalAmount" class="pf-input" placeholder="0.00" required>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="snapshotModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:350px;">
        <h3 style="margin-top:0;"><?= tr('bud_update_balance') ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="save_snapshot">
            <input type="date" name="snapshot_date" class="pf-input" value="<?= date('Y-m-d') ?>" style="margin-bottom:10px;" required>
            <input type="number" step="0.01" name="snapshot_amount" class="pf-input" placeholder="<?= tr('bud_balance_eur') ?>" style="margin-bottom:15px;" required>
            <div class="modal-footer">
                <button type="button" onclick="closeSuiviModal('snapshotModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const activeViewMonth = '<?= substr($viewMonthDate, 0, 7) ?>';
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function openSuiviModal(id) { document.getElementById(id).style.display = 'flex'; document.body.classList.add('no-scroll'); }
function closeSuiviModal(id) { document.getElementById(id).style.display = 'none'; document.body.classList.remove('no-scroll');}

const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;

function handleModalCatChange(select) {
    const catKey = select.value;
    
    document.getElementById('blockInputText').style.display = 'none';
    document.getElementById('blockInputSelect').style.display = 'none';
    document.getElementById('blockInputFrais').style.display = 'none';
    document.getElementById('blockInputIncome').style.display = 'none';
    
    document.getElementById('modalLabelInput').required = false;
    document.getElementById('fraisSelect').required = false;
    document.getElementById('incomeSelect').required = false;
    document.getElementById('fraisSelect').disabled = true;
    document.getElementById('incomeSelect').disabled = true;

    if (catKey === 'School') { document.getElementById('blockInputSelect').style.display = 'block'; } 
    else if (catKey === 'Frais') { document.getElementById('blockInputText').style.display = 'block'; document.getElementById('blockInputFrais').style.display = 'block'; document.getElementById('fraisSelect').required = true; document.getElementById('fraisSelect').disabled = false; }
    else if (catKey === 'Income') { document.getElementById('blockInputText').style.display = 'block'; document.getElementById('blockInputIncome').style.display = 'block'; document.getElementById('incomeSelect').required = true; document.getElementById('incomeSelect').disabled = false; }
    else {
        document.getElementById('blockInputText').style.display = 'block'; document.getElementById('modalLabelInput').required = true;
        const list = document.getElementById('modalSuggestions'); list.innerHTML = ''; 
        if (suggestions[catKey]) { suggestions[catKey].forEach(i => { const op = document.createElement('option'); op.value = i; list.appendChild(op); }); }
    }
}

function openAddModal(catKey, catLabel) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "<?= tr('bud_add_title') ?> : " + catLabel;
    document.getElementById('modalExpenseId').value = ""; 
    document.getElementById('modalDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalGestionMonth').value = activeViewMonth; 
    document.getElementById('modalLabelInput').value = "";
    document.getElementById('modalAmount').value = "";
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = catKey; handleModalCatChange(catSelect);
    document.getElementById('modalIsCredit').value = (catKey === 'Income') ? "1" : "0";
}

function openEditModal(e) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "<?= tr('bud_edit_title') ?>";
    document.getElementById('modalExpenseId').value = e.id;
    document.getElementById('modalDate').value = e.date_exp;
    document.getElementById('modalGestionMonth').value = e.gestion_month ? e.gestion_month.substring(0, 7) : activeViewMonth;
    document.getElementById('modalLabelInput').value = e.label;
    document.getElementById('modalIsCredit').value = parseFloat(e.amount) > 0 ? "1" : "0";
    document.getElementById('modalAmount').value = Math.abs(parseFloat(e.amount));
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = e.category; handleModalCatChange(catSelect);
    if (e.category === 'Frais') document.getElementById('fraisSelect').value = e.budget_item_id;
    else if (e.category === 'Income') document.getElementById('incomeSelect').value = e.budget_item_id;
}

function handleLineCatChange(select) {
    const row = select.closest('tr');
    const fSel = row.querySelector('.select-frais'); const iSel = row.querySelector('.select-income');
    fSel.style.display = 'none'; iSel.style.display = 'none'; fSel.value = ''; iSel.value = ''; fSel.disabled = true; iSel.disabled = true;
    if (select.value === 'Frais') { fSel.style.display = 'block'; fSel.disabled = false; } 
    else if (select.value === 'Income') { iSel.style.display = 'block'; iSel.disabled = false; }
    checkValidation();
}

function toggleAll(src) { document.querySelectorAll('.line-checkbox:not([disabled])').forEach(c => c.checked = src.checked); checkValidation(); }

function checkValidation() {
    let miss = 0;
    document.querySelectorAll('.line-checkbox:checked').forEach(cb => { 
        const row = cb.closest('tr'); const isCrd = row.querySelector('.is-credit-flag').value === '1'; const cat = row.querySelector('.line-select').value;
        let v = true; if (cat==="") { if(!isCrd) v = false; } else if (cat==='Frais' && row.querySelector('.select-frais').value==="") v=false; else if (cat==='Income' && row.querySelector('.select-income').value==="") v=false;
        if (!v) { miss++; row.style.background = '#fff1f2'; } else row.style.background = '';
    });
    const btn = document.getElementById('btnImport'); const msg = document.getElementById('missingCount');
    if(miss>0) { btn.disabled = true; btn.style.opacity=0.5; msg.style.display='inline'; msg.innerText = miss + ' <?= tr('bud_to_define_js') ?>'; } 
    else { btn.disabled = false; btn.style.opacity=1; msg.style.display='none'; }
}

if(document.getElementById('formMapping')) { document.querySelectorAll('.line-select').forEach(s => handleLineCatChange(s)); checkValidation(); }
</script>