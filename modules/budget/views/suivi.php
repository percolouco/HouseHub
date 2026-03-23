<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. GESTION DES ACTIONS ET DE LA NAVIGATION
// ============================================================================

$currentMonth = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m');
$currentYear = isset($_GET['y']) ? (int)$_GET['y'] : date('Y');
$currentMonthKey = $currentMonth . '-' . $currentYear;

$prevM = (int)$currentMonth - 1; $prevY = $currentYear;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = (int)$currentMonth + 1; $nextY = $currentYear;
if ($nextM > 12) { $nextM = 1; $nextY++; }

$prevLink = "?tab=suivi&m=$prevM&y=$prevY";
$nextLink = "?tab=suivi&m=$nextM&y=$nextY";
$todayLink = "?tab=suivi";

// ============================================================================
// CONFIGURATION DU CYCLE (Dates personnalisées)
// ============================================================================
$stmtConfig = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_config' AND reference_id = ?");
$stmtConfig->execute([$currentMonthKey]);
$configJson = $stmtConfig->fetchColumn();
$monthConfig = $configJson ? json_decode($configJson, true) : null;

$customStartDate = $monthConfig['start_date'] ?? "$currentYear-$currentMonth-01";
$customStartBalance = $monthConfig['start_balance'] ?? 0;

$nextMonthKey = str_pad($nextM, 2, '0', STR_PAD_LEFT) . '-' . $nextY;
$stmtNextConfig = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_config' AND reference_id = ?");
$stmtNextConfig->execute([$nextMonthKey]);
$nextConfigJson = $stmtNextConfig->fetchColumn();
$nextConfig = $nextConfigJson ? json_decode($nextConfigJson, true) : null;

if ($nextConfig && !empty($nextConfig['start_date'])) {
    $customEndDate = date('Y-m-d', strtotime($nextConfig['start_date'] . ' -1 day'));
} else {
    $customEndDate = date('Y-m-t', strtotime("$currentYear-$currentMonth-01"));
}

// A. AJOUT CATÉGORIE TEMPORAIRE MANUELLE
if (isset($_POST['action']) && $_POST['action'] === 'add_temp_cat') {
    $name = trim($_POST['cat_name']);
    $budget = floatval($_POST['cat_budget']); 
    $type = $_POST['cat_type'] === 'credit' ? 'credit' : 'debit';
    if ($type === 'debit') $budget = -$budget;

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO pf_monthly_categories (month_year, name, type, budget) VALUES (?, ?, ?, ?)");
        $stmt->execute([$currentMonthKey, $name, $type, $budget]);
        header("Location: ?tab=suivi&m=$currentMonth&y=$currentYear"); exit;
    }
}

// B. SUPPRESSION CATÉGORIE TEMPORAIRE
if (isset($_GET['del_cat'])) {
    $pdo->prepare("DELETE FROM pf_monthly_categories WHERE id = ?")->execute([(int)$_GET['del_cat']]);
    header("Location: ?tab=suivi&m=$currentMonth&y=$currentYear"); exit;
}

// C. SAUVEGARDE SNAPSHOT BANCAIRE
if (isset($_POST['action']) && $_POST['action'] === 'save_snapshot') {
    $date = $_POST['snapshot_date'];
    $amount = floatval($_POST['snapshot_amount']);
    $pdo->query("DELETE FROM pf_bank_snapshots"); 
    $pdo->prepare("INSERT INTO pf_bank_snapshots (snapshot_date, amount) VALUES (?, ?)")->execute([$date, $amount]);
    header("Location: ?tab=suivi&m=$currentMonth&y=$currentYear"); exit;
}

// D. SAUVEGARDE IMPORT CSV
if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $viewMonth = $_POST['view_month'] ?? $currentMonth;
    $viewYear = $_POST['view_year'] ?? $currentYear;
    
    $tempCatMapping = [];
    if (!empty($_POST['new_temp_cats'])) {
        $stmtTemp = $pdo->prepare("INSERT INTO pf_monthly_categories (month_year, name, type, budget) VALUES (?, ?, ?, 0)");
        foreach ($_POST['new_temp_cats'] as $tempKey => $catData) {
            $stmtTemp->execute([$currentMonthKey, $catData['name'], $catData['type']]);
            $tempCatMapping[$tempKey] = $pdo->lastInsertId();
        }
    }

    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (isset($line['import_check'])) {
                $cat = $line['cat'];
                $is_credit = isset($line['is_credit']) ? (int)$line['is_credit'] : 0;
                $budgetItemId = !empty($line['budget_item_id']) ? (int)$line['budget_item_id'] : null;
                $holidayId = !empty($line['holiday_id']) ? (int)$line['holiday_id'] : null;
                
                if ($is_credit && empty($cat)) continue;
                if (!$is_credit && empty($cat)) continue;

                if (strpos($cat, 'NEW_TEMP_') === 0 && isset($tempCatMapping[$cat])) {
                    $cat = 'TEMP_' . $tempCatMapping[$cat];
                }

                $finalAmount = $is_credit ? abs($line['amount']) : -abs($line['amount']);
                
                $dateToSave = $line['date'];
                if (!empty($line['force_current'])) {
                    $day = date('d', strtotime($dateToSave));
                    if (checkdate((int)$viewMonth, (int)$day, (int)$viewYear)) {
                        $dateToSave = "$viewYear-$viewMonth-$day";
                    } else {
                        $dateToSave = date('Y-m-t', strtotime("$viewYear-$viewMonth-01"));
                    }
                }

                try {
                    $stmtExp->execute([$dateToSave, $cat, $line['label'], $finalAmount, $line['ref'], $budgetItemId, $holidayId]);
                    $stmtRule->execute([$line['label'], $cat]);
                    $count++;
                } catch (Exception $e) { continue; }
            }
        }
    }
    header("Location: ?tab=suivi&m=$viewMonth&y=$viewYear&msg=imported_$count"); exit;
}

// E. AJOUT OU MODIFICATION DÉPENSE (MANUELLE)
if (isset($_POST['action']) && $_POST['action'] === 'save_expense_manual') {
    $id = !empty($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;
    $cat = $_POST['category']; 
    $amount = floatval($_POST['amount']); 
    $date = $_POST['date'];
    $label = trim($_POST['label']);
    $budgetItemId = null;
    $holidayId = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

    if ($cat === 'School' && !empty($_POST['label_select'])) $label = trim($_POST['label_select']);
    elseif (($cat === 'Frais' || $cat === 'Income') && !empty($_POST['budget_item_id'])) $budgetItemId = (int)$_POST['budget_item_id'];

    if ($label && $amount > 0) {
        $is_credit = isset($_POST['is_credit']) ? (int)$_POST['is_credit'] : 0;
        $finalAmount = $is_credit ? abs($amount) : -abs($amount);
        
        if ($id) {
            $pdo->prepare("UPDATE pf_expenses SET date_exp=?, category=?, label=?, amount=?, budget_item_id=?, holiday_id=? WHERE id=?")
                ->execute([$date, $cat, $label, $finalAmount, $budgetItemId, $holidayId, $id]);
        } else {
            $uniqueRef = "MANUAL_" . uniqid();
            $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$date, $cat, $label, $finalAmount, $uniqueRef, $budgetItemId, $holidayId]);
        }
        header("Location: ?tab=suivi&m=$currentMonth&y=$currentYear"); exit;
    }
}

// F. SUPPRESSION DÉPENSE
if (isset($_GET['delete_expense'])) {
    $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([(int)$_GET['delete_expense']]);
    header("Location: ?tab=suivi&m=$currentMonth&y=$currentYear"); exit;
}

// G. RECUPERATION VACANCES
$activeHolidays = $pdo->query("SELECT id, title FROM pf_holidays WHERE status IN ('draft', 'planned', 'booked') ORDER BY start_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// 2. CALCUL DES BUDGETS & CHARGES FIXES
// ============================================================================

$budget_fmcg = 0; $budget_school = 0; $budget_essence = 0; $budget_frais = 0; $budget_income_prevu = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir = 0; 
$today_day = (int)date('j'); 
$fixedChargesList = [];
$incomeList = []; 
$pending_charges = []; // Stockage détaillé des charges à venir

$stmtIds = $pdo->prepare("SELECT DISTINCT budget_item_id FROM pf_expenses WHERE date_exp >= ? AND date_exp <= ? AND budget_item_id IS NOT NULL");
$stmtIds->execute([$customStartDate, $customEndDate]);
$paidItemIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

$stmtLabels = $pdo->prepare("SELECT label FROM pf_expenses WHERE date_exp >= ? AND date_exp <= ? AND amount < 0");
$stmtLabels->execute([$customStartDate, $customEndDate]);
$realExpensesLabels = $stmtLabels->fetchAll(PDO::FETCH_COLUMN);

// Snapshot Bancaire
$snapshot = ['date' => date('Y-m-d'), 'amount' => 0];
try {
    $snapStmt = $pdo->query("SELECT * FROM pf_bank_snapshots ORDER BY id DESC LIMIT 1");
    if ($s = $snapStmt->fetch(PDO::FETCH_ASSOC)) {
        $snapshot = ['date' => $s['snapshot_date'], 'amount' => (float)$s['amount']];
    }
} catch (Exception $e) {}

$solde_actuel = $snapshot['amount'];

// Lecture Budget Prévisionnel
$stmt = $pdo->query("SELECT id, name, amount, type, category, is_estimate, payment_day, is_checked, mapping_keywords FROM pf_budget_items ORDER BY name ASC");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rawAmount = (float)$item['amount'];
    $absAmount = abs($rawAmount); 
    $amt = ($item['type'] === 'Annuel') ? $absAmount / 12 : $absAmount;
    $name = trim($item['name']);
    $pDay = (int)$item['payment_day'];
    $isChecked = (int)$item['is_checked'];
    
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
            if ($isChecked === 1) $isPaid = true;
            elseif (in_array($item['id'], $paidItemIds)) $isPaid = true;
            elseif (!empty($item['mapping_keywords'])) {
                $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                foreach ($keywords as $kw) {
                    if (empty($kw)) continue;
                    foreach ($realExpensesLabels as $realLabel) {
                        if (stripos($realLabel, $kw) !== false) {
                            $isPaid = true;
                            break 2;
                        }
                    }
                }
            }
            if (!$isPaid) {
                $reste_a_venir += $absAmount;
                $pending_charges[] = ['name' => $name, 'amount' => $absAmount];
            }
        }

        if ($name === 'Estimacio F&B & beauty') $budget_fmcg = $amt;
        elseif ($name === 'Estimacio escola') $budget_school = $amt;
        elseif ($name === 'Estimation gasolina') $budget_essence = $amt;
        elseif ((int)$item['is_estimate'] === 0 && $item['type'] === 'Mensuel' && $item['category'] === 'expense') {
            $budget_frais += $absAmount;
        }
    }
}

$tempCats = []; $total_temp_budget = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM pf_monthly_categories WHERE month_year = ?");
    $stmt->execute([$currentMonthKey]);
    $tempCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($tempCats as $tc) {
        if ($tc['type'] === 'debit') $total_temp_budget += abs($tc['budget']);
    }
} catch (Exception $e) {}

$budget_autres = $total_income - ($total_expenses_prevues + $total_temp_budget);
if ($budget_autres < 0) $budget_autres = 0;

$categoriesConfig = [
    'Income' => ['type'=>'credit', 'label'=>'Revenus', 'budget'=>$budget_income_prevu, 'color'=>'#10b981', 'suggestions'=>[]],
    'FMCG' => ['type'=>'debit', 'label'=>'Courses (FMCG)', 'budget'=>$budget_fmcg, 'color'=>'#3b82f6', 'suggestions'=>['Action', 'Carrefour', 'Lidl']],
    'Essence' => ['type'=>'debit', 'label'=>'Essence', 'budget'=>$budget_essence, 'color'=>'#f59e0b', 'suggestions'=>['Audi', 'Polo']],
    'School' => ['type'=>'debit', 'label'=>'École / Garde', 'budget'=>$budget_school, 'color'=>'#10b981', 'suggestions'=>[]],
    'Frais' => ['type'=>'debit', 'label'=>'Charges Fixes', 'budget'=>$budget_frais, 'color'=>'#ef4444', 'suggestions'=>[]],
];

$tempColors = ['#ec4899', '#06b6d4', '#84cc16', '#d946ef', '#f97316'];
$colorIdx = 0;
foreach ($tempCats as $tc) {
    $catKey = 'TEMP_' . $tc['id'];
    $categoriesConfig[$catKey] = [
        'type' => $tc['type'], 'label' => $tc['name'], 'budget' => abs($tc['budget']), 'color' => $tempColors[$colorIdx++ % count($tempColors)], 'suggestions' => [], 'is_temp' => true, 'id' => $tc['id']
    ];
}
$categoriesConfig['Autres'] = ['type'=>'debit', 'label'=>'Autres / Imprévus', 'budget'=>$budget_autres, 'color'=>'#64748b', 'suggestions'=>['Restaurant', 'Cadeau']];
$categoriesConfig['LivretA'] = ['type'=>'debit', 'label'=>'Epargne', 'budget'=>0, 'color'=>'#8b5cf6', 'suggestions'=>['Virement']];

// ============================================================================
// 4. DONNÉES RÉELLES (Sur la période du cycle)
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

$stmt = $pdo->prepare("SELECT * FROM pf_expenses WHERE date_exp >= ? AND date_exp <= ? ORDER BY date_exp DESC");
$stmt->execute([$customStartDate, $customEndDate]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);

// --- PREPARATION SOLDE THEORIQUE SUR LE CYCLE ---
$solde_initial = $customStartBalance; 
$total_rentrees = 0;
$depenses_reelles = 0;

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($totals[$cat])) $cat = 'Autres';
    
    $val = (float)$exp['amount'];
    
    if ($cat === 'Income') { 
        $totals[$cat] += $val; 
    } else {
        if ($val > 0) $categoriesConfig[$cat]['budget'] += $val;
        else $totals[$cat] += abs($val);
    }
    $expensesByCategory[$cat][] = $exp;

    if ($val > 0) { 
        if ($cat === 'Income' || $cat !== 'Frais') {
            $total_rentrees += $val; 
        } else {
            $depenses_reelles -= $val; 
        }
    } else {
        $depenses_reelles += abs($val);
    }
}

$rentrees_salaires_reels = $totals['Income'];
$rentrees_autres = $total_rentrees - $rentrees_salaires_reels;
$capacite_max = $solde_initial + max($rentrees_salaires_reels, $budget_income_prevu) + $rentrees_autres;

// Ajout du reste estimé pour l'École dans les charges à venir
$ecole_depense = isset($totals['School']) ? $totals['School'] : 0;
$reste_ecole = max(0, $budget_school - $ecole_depense);
if ($reste_ecole > 0) {
    $reste_a_venir += $reste_ecole;
    $pending_charges[] = ['name' => 'École / Garde (Reste estimé)', 'amount' => $reste_ecole];
}

$solde_theorique = ($solde_initial + $total_rentrees) - $depenses_reelles - $reste_a_venir;

$solde_net = max(0, $solde_actuel - $reste_a_venir);
$charges_visibles = min($solde_actuel, $reste_a_venir); 

$max_scale = max($solde_actuel, $solde_theorique, $capacite_max, 1) * 1.1; 

$pct_net = min(100, max(0, ($solde_net / $max_scale) * 100));
$pct_charges = min(100 - $pct_net, max(0, ($charges_visibles / $max_scale) * 100));
$pct_actuel = min(100, max(0, ($solde_actuel / $max_scale) * 100));
$pct_theorique = min(100, max(0, ($solde_theorique / $max_scale) * 100));

function getDisplayLogic($spent, $bg, $type) {
    if ($type === 'credit') {
        $pct = ($bg > 0) ? min(100, ($spent / $bg) * 100) : ($spent > 0 ? 100 : 0); 
        $isOver = false;
        if ($bg > 0) { $text = number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €'; } 
        else { $text = number_format(ceil($spent), 0, ',', ' ') . ' €'; }
    } else {
        $pct = ($bg > 0) ? min(100, ($spent / $bg) * 100) : ($spent > 0 ? 100 : 0);
        $isOver = ($spent > $bg && $bg > 0);
        if ($bg > 0) { $text = number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €'; } 
        else { $text = number_format(ceil($spent), 0, ',', ' ') . ' €'; }
    }
    return ['pct' => $pct, 'isOver' => $isOver, 'text' => $text];
}

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$monthName = $moisFr[(int)$currentMonth] . ' ' . $currentYear;
$cycleDisplay = date('d/m', strtotime($customStartDate)) . ' au ' . date('d/m', strtotime($customEndDate));
?>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:24px; border:1px solid #e2e8f0;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0; font-size:1.3rem; color:#0f172a; text-transform:capitalize;">Suivi : <?= $monthName ?></h2>
                <span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:600; border:1px solid #bae6fd;">
                    Cycle du <?= $cycleDisplay ?>
                </span>
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="document.getElementById('configMonthModal').style.display='flex'" class="pf-btn btn-secondary" style="padding:6px 12px; height:auto; width:auto; font-size:0.85rem; border:1px solid #cbd5e1; color:#475569;">⚙️ Cycle</button>
                <div class="suivi-nav-group">
                    <a href="<?= $prevLink ?>" class="suivi-btn-nav">◀</a>
                    <a href="<?= $todayLink ?>" class="suivi-btn-nav">Auj.</a>
                    <a href="<?= $nextLink ?>" class="suivi-btn-nav">▶</a>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
            <div style="padding:15px; background:#f8fafc; border-radius:12px; position:relative; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;">Solde bancaire <span style="font-size:0.75rem;">(au <?= date('d/m', strtotime($snapshot['date'])) ?>)</span></div>
                <div style="font-size:1.4rem; font-weight:700; color:#0f172a;">
                    <?= number_format($solde_actuel, 2, ',', ' ') ?> €
                </div>
                <button onclick="openSuiviModal('snapshotModal')" style="position:absolute; top:12px; right:12px; background:white; border:1px solid #cbd5e1; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:0.8rem; color:#475569; transition:0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">✏️ MàJ</button>
            </div>

            <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;">Solde théorique <span style="font-size:0.75rem;">(Fin de cycle)</span></div>
                <div style="font-size:1.4rem; font-weight:700; color:<?= $solde_theorique < 0 ? '#ef4444' : '#334155' ?>;" title="Solde Initial + Rentrées - Sorties - Charges à venir">
                    <?= number_format($solde_theorique, 2, ',', ' ') ?> €
                </div>
            </div>

            <div style="padding:15px; background:#fef2f2; border-radius:12px; border:1px solid #fecaca; position: relative;">
                <div style="font-size:0.85rem; color:#991b1b; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center;">
                    <span>Charges à venir</span>
                    <button onclick="toggleDiv('pendingDetailsList')" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; filter: grayscale(1); opacity: 0.7; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7" title="Voir le détail">👁️</button>
                </div>
                <div style="font-size:1.4rem; font-weight:700; color:#b91c1c;">
                    - <?= number_format($reste_a_venir, 2, ',', ' ') ?> €
                </div>
                
                <div id="pendingDetailsList" style="display:none; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #fca5a5; font-size: 0.8rem; color: #7f1d1d;">
                    <?php if (empty($pending_charges)): ?>
                        <div style="text-align:center; font-style:italic; opacity:0.8;">Tout est payé ! 🎉</div>
                    <?php else: ?>
                        <?php foreach($pending_charges as $pc): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;"><?= htmlspecialchars($pc['name']) ?></span>
                                <strong><?= number_format($pc['amount'], 0, ',', ' ') ?> €</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; padding: 30px 10px 45px 10px; position:relative;">
            <div style="position: absolute; top: 0px; left: 10px; font-size: 0.8rem; color: #64748b; font-weight: 700;">0 €</div>
            <div style="position: absolute; top: 0px; right: 10px; font-size: 0.8rem; color: #64748b; font-weight: 700; text-align:right;">
                Capacité Max : <?= number_format($capacite_max, 0, ',', ' ') ?> €
            </div>

            <div style="position: absolute; top: 12px; left: <?= $pct_actuel ?>%; transform: translateX(-50%); text-align: center; z-index: 10;">
                <div style="color: #334155; font-size: 0.75rem; font-weight: 800; white-space: nowrap; margin-bottom: 2px;">Actuel</div>
                <div style="width: 3px; height: 18px; background: #334155; margin: 0 auto; border-radius: 2px;"></div>
            </div>

            <div style="height: 24px; background: #e2e8f0; border-radius: 12px; display: flex; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <div style="width: <?= $pct_net ?>%; background: linear-gradient(90deg, #3b82f6, #0ea5e9); transition: width 0.5s;"></div>
                <div style="width: <?= $pct_charges ?>%; background: repeating-linear-gradient(45deg, #f59e0b, #f59e0b 8px, #fbbf24 8px, #fbbf24 16px); transition: width 0.5s;" title="Provisions pour charges à venir"></div>
            </div>

            <div style="position: absolute; top: 54px; left: <?= $pct_theorique ?>%; transform: translateX(-50%); text-align: center; z-index: 10;">
                <div style="width: 3px; height: 18px; background: #8b5cf6; margin: 0 auto 2px auto; border-radius: 2px;"></div>
                <div style="color: #8b5cf6; font-size: 0.75rem; font-weight: 800; white-space: nowrap;">Théorique</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#64748b; font-weight:600; padding: 15px 10px 0 10px; border-top: 1px solid #f1f5f9;">
            <span style="display:flex; align-items:center; gap:6px;"><span style="width:12px; height:12px; border-radius:3px; background:#3b82f6;"></span> Dispo Net : <strong style="color:#0f172a;"><?= number_format($solde_net, 0, ',', ' ') ?> €</strong></span>
            <span style="display:flex; align-items:center; gap:6px;"><span style="width:12px; height:12px; border-radius:3px; background:repeating-linear-gradient(45deg, #f59e0b, #f59e0b 4px, #fbbf24 4px, #fbbf24 8px);"></span> Provisions : <strong style="color:#0f172a;"><?= number_format($reste_a_venir, 0, ',', ' ') ?> €</strong></span>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <button class="pf-btn btn-secondary" onclick="toggleDiv('importCsvForm')">📂 Importer CSV</button>
        <button class="pf-btn btn-secondary" onclick="toggleDiv('addTempCatForm')" style="border-style:dashed;">＋ Créer catégorie temporaire</button>
    </div>

    <div id="addTempCatForm" style="display:none; background:#f8fafc; padding:15px; border-radius:12px; border:1px dashed #cbd5e1; margin-bottom:20px;">
        <form method="POST" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="action" value="add_temp_cat">
            <div style="flex:1; min-width:180px;">
                <label class="pf-label">Nom</label>
                <input type="text" name="cat_name" class="pf-input" required>
            </div>
            <div style="width:120px;">
                <label class="pf-label">Budget de base</label>
                <input type="number" name="cat_budget" class="pf-input" step="1" value="0" required>
            </div>
            <div style="width:150px;">
                <label class="pf-label">Type</label>
                <select name="cat_type" class="pf-input">
                    <option value="debit">Débit (Budget)</option>
                    <option value="credit">Crédit (Réserve)</option>
                </select>
            </div>
            <button type="submit" class="pf-btn" style="width:auto;">Créer</button>
        </form>
    </div>

    <div id="importCsvForm" style="display:<?= $showPreview ? 'block' : 'none' ?>; background:#f8fafc; padding:20px; border-radius:12px; margin-bottom:20px;">
        <?php if (!$showPreview): ?>
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <div style="flex:1; min-width:250px;">
                    <label class="pf-label">Fichier CSV (Point-virgule)</label>
                    <input type="file" name="csv_file" accept=".csv" class="pf-input">
                </div>
                <button type="submit" class="pf-btn" style="width:auto;">Prévisualiser</button>
            </form>
        <?php else: ?>
            <form method="POST" id="formMapping">
                <input type="hidden" name="action" value="save_import">
                <input type="hidden" name="view_month" value="<?= $currentMonth ?>">
                <input type="hidden" name="view_year" value="<?= $currentYear ?>">
                <div id="dynamicNewCats"></div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <h3 style="margin:0;">Valider l'importation</h3>
                    </div>
                    <div>
                        <span id="missingCount" style="color:#ef4444; background:#fee2e2; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.85rem; display:none; margin-right:10px;"></span>
                        <button type="button" class="btn-icon-small" onclick="openSuiviModal('newCatModal')" title="Créer une catégorie" style="display:inline-flex; vertical-align:middle; width:auto; padding:4px 10px;">➕ Catégorie</button>
                    </div>
                </div>

                <div style="max-height:350px; overflow-y:auto; background:white; border:1px solid #eee; border-radius:8px;">
                    <table class="pf-table" style="margin:0;">
                        <thead style="position:sticky; top:0; z-index:10;">
                            <tr><th><input type="checkbox" onclick="toggleAll(this)" checked></th><th>Date</th><th>Libellé</th><th>Montant</th><th>Catégorie</th></tr>
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
                                <td style="white-space:nowrap; vertical-align: top; padding-top: 12px;">
                                    <div style="font-weight:600; color:#1e293b; line-height:1;"><?= date('d/m/Y', strtotime($row['date'])) ?></div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['label']) ?>
                                    <?php if($dup): ?><small style="color:#ef4444; font-weight:bold; margin-left:5px;">(déjà importé)</small><?php endif; ?>
                                </td>
                                <td style="font-weight:bold; color:<?= $isCrd ? '#10b981' : '#1e293b' ?>; white-space:nowrap;">
                                    <?= $isCrd ? '+' : '-' ?> <?= number_format($row['amount'],2) ?> €
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="handleLineCatChange(this)" <?= $dis ?> style="flex:1;">
                                            <option value="">-- <?= $isCrd ? 'Ignorer' : 'À définir' ?> --</option>
                                            <?php foreach ($categoriesConfig as $k => $c): ?>
                                                <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= $c['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input budget-item-select select-frais" onchange="checkValidation()" style="display:none; flex:1; border-color:#ef4444; background:#fef2f2;" disabled>
                                            <option value="">-- Quelle Charge ? --</option>
                                            <?php foreach ($fixedChargesList as $fc): ?>
                                                <option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?> (<?= number_format($fc['amount'],0) ?>€)</option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input budget-item-select select-income" onchange="checkValidation()" style="display:none; flex:1; border-color:#10b981; background:#f0fdf4;" disabled>
                                            <option value="">-- Quel Revenu ? --</option>
                                            <?php foreach ($incomeList as $inc): ?>
                                                <option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?> (<?= number_format($inc['amount'],0) ?>€)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select name="lines[<?= $idx ?>][holiday_id]" class="pf-input select-holiday" style="display:none; flex:1; border-color:#8b5cf6; background:#f5f3ff;" <?= $dis ?>>
                                            <option value="">-- Voyage (Optionnel) --</option>
                                            <?php foreach ($activeHolidays as $hol): ?>
                                                <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px; display:flex; justify-content:flex-end; gap:10px;">
                    <a href="?tab=suivi" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</a>
                    <button type="submit" id="btnImport" class="pf-btn" style="width:auto; margin:0;">Importer</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="categories-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
        <?php foreach ($categoriesConfig as $key => $conf): ?>
        <div class="cat-card">
            <div style="background:<?= $conf['color'] ?>15; padding:15px; border-bottom:1px solid <?= $conf['color'] ?>30; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0; font-size:1rem; color:<?= $conf['color'] ?>;">
                        <?= $conf['label'] ?>
                        <?php if (isset($conf['is_temp'])): ?>
                            <a href="?tab=suivi&del_cat=<?= $conf['id'] ?>&m=<?= $currentMonth ?>&y=<?= $currentYear ?>" onclick="return confirm('Supprimer cette catégorie ?')" style="color:#ef4444; text-decoration:none; font-size:0.8rem; margin-left:5px;">&times;</a>
                        <?php endif; ?>
                    </h3>
                    <div style="font-size:0.85rem; color:#64748b; font-weight:600; margin-top:2px;">
                        <?php $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']); echo $logic['text']; ?>
                    </div>
                </div>
                <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes($conf['label']) ?>')">＋</button>
            </div>

            <?php $barCol = ($key === 'Income') ? '#10b981' : ($logic['isOver'] ? '#ef4444' : $conf['color']); ?>
            <div style="background:#f1f5f9; height:4px; width:100%;">
                <div style="width:<?= $logic['pct'] ?>%; background:<?= $barCol ?>; height:100%;"></div>
            </div>

            <div style="flex:1; max-height:300px; overflow-y:auto; padding:0;">
                <?php if (empty($expensesByCategory[$key])): ?>
                    <div style="padding:20px; text-align:center; color:#cbd5e1; font-style:italic; font-size:0.85rem;">Aucune ligne.</div>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <?php foreach ($expensesByCategory[$key] as $exp): ?>
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:10px 15px; color:#94a3b8;"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                            <td style="padding:10px 5px; font-weight:500;">
                                <?= htmlspecialchars($exp['label']) ?>
                                <?php if(!empty($exp['budget_item_id'])): ?>
                                    <span title="Lié à une charge/revenu" style="font-size:0.7rem; cursor:help;">🔗</span>
                                <?php endif; ?>
                            </td>
                            <?php if($exp['amount'] > 0): ?>
                                <td style="padding:10px 15px; text-align:right; font-weight:600; color:#10b981;">+<?= number_format($exp['amount'], 2) ?></td>
                            <?php else: ?>
                                <td style="padding:10px 15px; text-align:right; font-weight:600; color:#1e293b;">-<?= number_format(abs($exp['amount']), 2) ?></td>
                            <?php endif; ?>
                            <td style="width:60px; padding-right:10px; text-align:right; white-space:nowrap;">
                                <button onclick='openEditModal(<?= json_encode($exp) ?>)' 
                                        style="background:none; border:none; padding:0; cursor:pointer; font-size:1rem; margin-right:8px; color:#64748b; line-height:1; vertical-align:middle;">
                                    ✏️
                                </button>
                                
                                <a href="?tab=suivi&delete_expense=<?= $exp['id'] ?>&m=<?= $currentMonth ?>&y=<?= $currentYear ?>" 
                                   onclick="return confirm('Supprimer ?')" 
                                   style="color:#ef4444; text-decoration:none; font-size:1.4rem; line-height:1; vertical-align:middle;">
                                    &times;
                                </a>
                            </td>
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
            <h3 style="margin:0;" id="modalTitle">Nouvelle transaction</h3>
            <button type="button" onclick="closeSuiviModal('manualExpenseModal')" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_expense_manual">
            <input type="hidden" name="expense_id" id="modalExpenseId">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Catégorie</label>
                <select name="category" id="modalCatSelect" class="pf-input" onchange="handleModalCatChange(this)">
                    <?php foreach($categoriesConfig as $key => $conf): ?>
                        <option value="<?= $key ?>"><?= $conf['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Type de mouvement</label>
                <select name="is_credit" id="modalIsCredit" class="pf-input" style="font-weight:bold;">
                    <option value="0">Dépense ( - sur le compte )</option>
                    <option value="1">Revenu / Remboursement ( + sur le compte )</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Date</label>
                <input type="date" name="date" id="modalDate" class="pf-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group" id="blockInputText" style="margin-bottom:15px;">
                <label class="pf-label">Titre / Magasin</label>
                <input type="text" name="label" class="pf-input" id="modalLabelInput" list="modalSuggestions" autocomplete="off">
                <datalist id="modalSuggestions"></datalist>
            </div>

            <div class="form-group" id="blockInputSelect" style="margin-bottom:15px; display:none;">
                <label class="pf-label">Bénéficiaire</label>
                <select name="label_select" id="schoolSelect" class="pf-input">
                    <option value="Ecole Pol">Ecole Pol</option>
                    <option value="Carole">Carole</option>
                </select>
            </div>

            <div class="form-group" id="blockInputFrais" style="margin-bottom:15px; display:none;">
                <label class="pf-label" style="color:#ef4444;">Choisir la charge fixe</label>
                <select name="budget_item_id" id="fraisSelect" class="pf-input" style="border-color:#ef4444; background:#fef2f2;" disabled>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($fixedChargesList as $fc): ?>
                        <option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?> (<?= number_format($fc['amount'],2) ?>€)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="blockInputIncome" style="margin-bottom:15px; display:none;">
                <label class="pf-label" style="color:#10b981;">Choisir le revenu</label>
                <select name="budget_item_id" id="incomeSelect" class="pf-input" style="border-color:#10b981; background:#f0fdf4;" disabled>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($incomeList as $inc): ?>
                        <option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?> (<?= number_format($inc['amount'],2) ?>€)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="blockHoliday" style="margin-bottom:15px; display:none;">
                <label class="pf-label" style="color:#8b5cf6;">🌴 Associer à un voyage (Optionnel)</label>
                <select name="holiday_id" id="modalHolidayId" class="pf-input" style="border-color:#8b5cf6; background:#f5f3ff;">
                    <option value="">-- Ne pas associer --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Montant (€)</label>
                <input type="number" step="0.01" name="amount" id="modalAmount" class="pf-input" placeholder="0.00" required>
            </div>

            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div id="snapshotModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:350px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Mettre à jour le solde</h3>
            <button type="button" onclick="closeSuiviModal('snapshotModal')" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_snapshot">
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Date du relevé</label>
                <input type="date" name="snapshot_date" class="pf-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Solde actuel (€)</label>
                <input type="number" step="0.01" name="snapshot_amount" class="pf-input" placeholder="0.00" required>
            </div>
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeSuiviModal('snapshotModal')" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div id="configMonthModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:350px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Configuration du Cycle</h3>
            <button type="button" onclick="closeSuiviModal('configMonthModal')" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <div style="font-size:0.85rem; color:#475569; margin-bottom:20px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
            Le cycle démarre généralement à la réception de votre paie principale. Le solde initial que vous déclarez deviendra votre Capacité Max de départ pour ce mois.
        </div>

        <div class="form-group" style="margin-bottom:15px;">
            <label class="pf-label">Date de début du cycle</label>
            <input type="date" id="conf_start_date" class="pf-input" value="<?= $customStartDate ?>" required>
        </div>
        
        <div class="form-group" style="margin-bottom:15px;">
            <label class="pf-label">Solde de départ (€)</label>
            <input type="number" step="0.01" id="conf_start_balance" class="pf-input" value="<?= $customStartBalance ?>" placeholder="-50.00" required>
            <small style="color:#94a3b8;">Solde bancaire exact avant toute nouvelle rentrée d'argent de ce mois.</small>
        </div>

        <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeSuiviModal('configMonthModal')" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
            <button type="button" onclick="saveMonthConfig()" class="pf-btn" style="width:auto; margin:0;">Enregistrer</button>
        </div>
    </div>
</div>

<div id="newCatModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:350px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Nouvelle catégorie</h3>
            <button type="button" onclick="closeSuiviModal('newCatModal')" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        <div class="form-group" style="margin-bottom:15px;">
            <label class="pf-label">Nom (ex: Vacances)</label>
            <input type="text" id="newCatName" class="pf-input">
        </div>
        <div class="form-group" style="margin-bottom:15px;">
            <label class="pf-label">Type</label>
            <select id="newCatType" class="pf-input">
                <option value="debit">Débit (Dépense standard)</option>
                <option value="credit">Crédit (Réserve d'argent)</option>
            </select>
        </div>
        <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeSuiviModal('newCatModal')" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
            <button type="button" onclick="confirmNewCat()" class="pf-btn" style="width:auto; margin:0;">Créer</button>
        </div>
    </div>
</div>

<script>
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function openSuiviModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeSuiviModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(event) { if (event.target.classList.contains('pf-modal')) event.target.style.display = 'none'; }

function saveMonthConfig() {
    const formData = new FormData();
    formData.append('action', 'save_note');
    formData.append('note_type', 'month_config');
    formData.append('reference_id', '<?= $currentMonthKey ?>');
    
    const configData = {
        start_date: document.getElementById('conf_start_date').value,
        start_balance: parseFloat(document.getElementById('conf_start_balance').value) || 0
    };
    
    formData.append('content', JSON.stringify(configData));

    fetch('/modules/budget/includes/api/save-budget.php', {
        method: 'POST',
        body: formData
    })
    .then(() => window.location.reload())
    .catch(e => alert("Erreur lors de la sauvegarde du cycle."));
}

const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;

function handleModalCatChange(select) {
    const catKey = select.value;
    const catText = select.options[select.selectedIndex].text.toLowerCase();
    
    const blockText = document.getElementById('blockInputText');
    const blockSelect = document.getElementById('blockInputSelect');
    const blockFrais = document.getElementById('blockInputFrais');
    const blockIncome = document.getElementById('blockInputIncome');
    const blockHoliday = document.getElementById('blockHoliday');
    
    const inputLabel = document.getElementById('modalLabelInput');
    const selectFrais = document.getElementById('fraisSelect');
    const selectIncome = document.getElementById('incomeSelect');
    const selectHoliday = document.getElementById('modalHolidayId');

    blockText.style.display = 'none';
    blockSelect.style.display = 'none';
    blockFrais.style.display = 'none';
    blockIncome.style.display = 'none';
    blockHoliday.style.display = 'none';
    
    inputLabel.required = false;
    selectFrais.required = false;
    selectIncome.required = false;

    selectFrais.disabled = true;
    selectIncome.disabled = true;

    if (catKey === 'LivretA' || catText.includes('vacance')) {
        blockHoliday.style.display = 'block';
    } else {
        selectHoliday.value = '';
    }

    if (catKey === 'School') {
        blockSelect.style.display = 'block';
    } 
    else if (catKey === 'Frais') {
        blockText.style.display = 'block'; 
        blockFrais.style.display = 'block'; 
        selectFrais.required = true;
        selectFrais.disabled = false;
    }
    else if (catKey === 'Income') {
        blockText.style.display = 'block'; 
        blockIncome.style.display = 'block';
        selectIncome.required = true;
        selectIncome.disabled = false;
    }
    else {
        blockText.style.display = 'block';
        inputLabel.required = true;
        
        const list = document.getElementById('modalSuggestions');
        list.innerHTML = ''; 
        if (suggestions[catKey]) {
            suggestions[catKey].forEach(i => { 
                const op = document.createElement('option'); 
                op.value = i; 
                list.appendChild(op); 
            });
        }
    }
}

function openAddModal(catKey, catLabel) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "Ajouter : " + catLabel;
    
    document.getElementById('modalExpenseId').value = ""; 
    document.getElementById('modalDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalLabelInput').value = "";
    document.getElementById('modalAmount').value = "";
    document.getElementById('modalHolidayId').value = "";
    
    const catSelect = document.getElementById('modalCatSelect');
    catSelect.value = catKey;
    handleModalCatChange(catSelect);
    
    document.getElementById('modalIsCredit').value = (catKey === 'Income') ? "1" : "0";
    
    setTimeout(() => document.getElementById('modalLabelInput').focus(), 100);
}

function openEditModal(expenseData) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "Modifier la transaction";
    
    document.getElementById('modalExpenseId').value = expenseData.id;
    document.getElementById('modalDate').value = expenseData.date_exp;
    document.getElementById('modalLabelInput').value = expenseData.label;
    
    const rawAmount = parseFloat(expenseData.amount);
    document.getElementById('modalIsCredit').value = rawAmount > 0 ? "1" : "0";
    document.getElementById('modalAmount').value = Math.abs(rawAmount);
    document.getElementById('modalHolidayId').value = expenseData.holiday_id || ""; 
    
    const catSelect = document.getElementById('modalCatSelect');
    catSelect.value = expenseData.category;
    handleModalCatChange(catSelect);

    if (expenseData.category === 'Frais') {
        document.getElementById('fraisSelect').value = expenseData.budget_item_id;
    } else if (expenseData.category === 'Income') {
        document.getElementById('incomeSelect').value = expenseData.budget_item_id;
    }
}

let newCatIndex = 0;
function confirmNewCat() {
    const name = document.getElementById('newCatName').value.trim();
    const type = document.getElementById('newCatType').value;
    if(name !== "") {
        const key = 'NEW_TEMP_' + newCatIndex++;
        document.getElementById('formMapping').insertAdjacentHTML('beforeend', `<input type="hidden" name="new_temp_cats[${key}][name]" value="${name}"><input type="hidden" name="new_temp_cats[${key}][type]" value="${type}">`);
        document.querySelectorAll('.line-select').forEach(sel => {
            const opt = document.createElement('option');
            opt.value = key; opt.text = "🏷️ " + name + (type === 'credit' ? ' (Réserve)' : '');
            sel.add(opt, sel.options[1]); 
        });
        closeSuiviModal('newCatModal');
        checkValidation();
    }
}

function handleLineCatChange(select) {
    const row = select.closest('tr');
    const fraisSelect = row.querySelector('.select-frais');
    const incomeSelect = row.querySelector('.select-income');
    const holidaySelect = row.querySelector('.select-holiday');
    
    const catKey = select.value;
    const catText = select.options[select.selectedIndex].text.toLowerCase();

    fraisSelect.style.display = 'none';
    incomeSelect.style.display = 'none';
    holidaySelect.style.display = 'none';
    
    fraisSelect.value = '';
    incomeSelect.value = '';

    fraisSelect.disabled = true;
    incomeSelect.disabled = true;

    if (catKey === 'LivretA' || catText.includes('vacance')) {
        holidaySelect.style.display = 'block';
    } else {
        holidaySelect.value = '';
    }

    if (catKey === 'Frais') {
        fraisSelect.style.display = 'block';
        fraisSelect.disabled = false;
    } 
    else if (catKey === 'Income') {
        incomeSelect.style.display = 'block';
        incomeSelect.disabled = false;
    }
    
    checkValidation();
}

function toggleAll(src) { document.querySelectorAll('.line-checkbox:not([disabled])').forEach(c => c.checked = src.checked); checkValidation(); }

function checkValidation() {
    const cbs = document.querySelectorAll('.line-checkbox:checked');
    let miss = 0;
    cbs.forEach(cb => { 
        const row = cb.closest('tr');
        const isCredit = row.querySelector('.is-credit-flag').value === '1';
        const mainCat = row.querySelector('.line-select').value;
        const fraisSelect = row.querySelector('.select-frais');
        const incomeSelect = row.querySelector('.select-income');
        
        let rowValid = true;

        if (mainCat === "") {
            if(!isCredit) rowValid = false; 
        } 
        else if (mainCat === 'Frais') {
            if (fraisSelect.value === "") rowValid = false;
        }
        else if (mainCat === 'Income') {
            if (incomeSelect.value === "") rowValid = false;
        }

        if (!rowValid) {
            miss++;
            row.style.background = '#fff1f2';
        } else {
            row.style.background = '';
        }
    });
    
    const btn = document.getElementById('btnImport');
    const msg = document.getElementById('missingCount');
    if(miss>0) { 
        btn.disabled = true; btn.style.opacity=0.5; btn.style.cursor='not-allowed';
        msg.style.display='inline'; msg.innerText=miss+' à définir';
    } else {
        btn.disabled = false; btn.style.opacity=1; btn.style.cursor='pointer';
        msg.style.display='none';
    }
}

if(document.getElementById('formMapping')) {
    document.querySelectorAll('.line-select').forEach(s => handleLineCatChange(s));
    checkValidation();
}
</script>