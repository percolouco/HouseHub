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

if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id, salary_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (isset($line['import_check'])) {
                $cat = $line['cat'];
                $is_credit = isset($line['is_credit']) ? (int)$line['is_credit'] : 0;
                
                // 🔥 NOUVEAU TRAITEMENT DU BUDGET_ITEM_ID
                $rawItemId = $line['budget_item_id'] ?? '';
                $budgetItemId = null;
                $salaryId = null;
                if (!empty($rawItemId)) {
                    if (strpos($rawItemId, 'SAL_') === 0) {
                        $salaryId = (int)str_replace('SAL_', '', $rawItemId);
                    } else {
                        $budgetItemId = (int)$rawItemId;
                    }
                }
                
                $holidayId = !empty($line['holiday_id']) ? (int)$line['holiday_id'] : null;
                $gestionMonthLine = !empty($line['gestion_month']) ? $line['gestion_month'] . '-01' : $viewMonthDate; 
                
                if ($is_credit && empty($cat)) continue;
                if (!$is_credit && empty($cat)) continue;

                $finalAmount = $is_credit ? abs($line['amount']) : -abs($line['amount']);
                $dateToSave = $line['date'];

                try {
                    $stmtExp->execute([$dateToSave, $gestionMonthLine, $cat, $line['label'], $finalAmount, $line['ref'], $budgetItemId, $holidayId, $salaryId]);
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


// ============================================================================
// E-Bis. LECTURE ET PRÉVISUALISATION DU FICHIER CSV
// ============================================================================
$csvData = [];
$showPreview = false;
$importError = null;

if (isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] == 0) {
        
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Fix fins de ligne Mac/iOS
        $fileContent = file_get_contents($file);
        $fileContent = preg_replace("/\r\n|\r|\n/", "\n", $fileContent);
        file_put_contents($file, $fileContent);

        $handle = fopen($file, "r");
        
        $stmtMap = $pdo->query("SELECT csv_mapping FROM pf_foyer_settings LIMIT 1");
        $csvMapRaw = $stmtMap->fetchColumn();
        $mapping = $csvMapRaw ? json_decode($csvMapRaw, true) : null;
        
        $delimiter  = $mapping['delimiter'] ?? ';';
        $colDate    = isset($mapping['col_date']) ? (int)$mapping['col_date'] : 0;
        $colLabel   = isset($mapping['col_label']) ? (int)$mapping['col_label'] : 1;
        $amountType = $mapping['amount_type'] ?? 'split';
        $colDebit   = isset($mapping['col_debit']) ? (int)$mapping['col_debit'] : 8;
        $colCredit  = isset($mapping['col_credit']) ? (int)$mapping['col_credit'] : 9;
        $colRef     = isset($mapping['col_ref']) ? (int)$mapping['col_ref'] : 3;
        $dateFormat = $mapping['date_format'] ?? 'd/m/Y';
        
        // 🔥 CHARGEMENT INTELLIGENT DES RÈGLES (Priorité au montant !)
        $rules = []; 
        try { 
            $stmtRules = $pdo->query("SELECT keyword, category, budget_item_id, amount FROM pf_import_rules ORDER BY amount DESC, keyword ASC");
            while ($r = $stmtRules->fetch(PDO::FETCH_ASSOC)) {
                $rules[] = [
                    'keyword' => $r['keyword'],
                    'cat' => $r['category'], 
                    'budget_item_id' => $r['budget_item_id'],
                    'amount' => $r['amount']
                ];
            }
        } catch(Exception $e){}
        
        $existingRefs = []; 
        try { $existingRefs = $pdo->query("SELECT import_ref FROM pf_expenses WHERE import_ref IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e){}
        
        fgetcsv($handle, 1000, $delimiter, "\"", "\\"); // Skip header
        
        while (($data = fgetcsv($handle, 1000, $delimiter, "\"", "\\")) !== FALSE) {
            if (empty(array_filter($data))) continue;

            $amount = 0; $isCredit = 0;
            if ($amountType === 'single') {
                $val = $data[$colDebit] ?? ''; 
                if (!empty(trim($val))) {
                    $floatVal = (float)str_replace([' ', ','], ['', '.'], $val);
                    $amount = abs($floatVal);
                    $isCredit = $floatVal > 0 ? 1 : 0;
                } else { continue; }
            } else {
                $rawDebit = $data[$colDebit] ?? ''; $rawCredit = $data[$colCredit] ?? ''; 
                if (!empty(trim($rawCredit))) { $amount = abs((float)str_replace([' ', ','], ['', '.'], $rawCredit)); $isCredit = 1; }
                elseif (!empty(trim($rawDebit))) { $amount = abs((float)str_replace([' ', ','], ['', '.'], $rawDebit)); }
                else { continue; }
            }

            $rawDate = $data[$colDate] ?? '';
            $dateSql = date('Y-m-d');
            if ($dateFormat === 'd/m/Y') {
                $dateParts = explode('/', $rawDate); 
                if (count($dateParts) >= 3) $dateSql = $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0];
            } else {
                $ts = strtotime($rawDate);
                if ($ts) $dateSql = date('Y-m-d', $ts);
            }

            $label = trim($data[$colLabel] ?? '') ?: trim($data[2] ?? '');
            $refCSV = trim($data[$colRef] ?? '');
            $uniqueKey = !empty($refCSV) ? "REF_".$refCSV : "HASH_".md5($dateSql.$label.number_format($amount, 2).$isCredit);
            $isDuplicate = in_array($uniqueKey, $existingRefs);
            
            // 🔥 MOTEUR DE MATCHING : Mot-clé + Montant strict
            $suggestedCat = '';
            $suggestedItemId = null;
            foreach ($rules as $ruleData) { 
                if (stripos($label, $ruleData['keyword']) !== false) { 
                    if (!empty($ruleData['amount']) && abs($amount) != abs((float)$ruleData['amount'])) {
                        continue; // Montant exigé par la règle mais non respecté
                    }
                    $suggestedCat = $ruleData['cat']; 
                    $suggestedItemId = $ruleData['budget_item_id'];
                    break; 
                } 
            }
            
            $csvData[] = ['date'=>$dateSql, 'label'=>$label, 'amount'=>$amount, 'cat'=>$suggestedCat, 'suggested_item_id'=>$suggestedItemId, 'ref'=>$uniqueKey, 'is_duplicate'=>$isDuplicate, 'is_credit'=>$isCredit];
        }
        fclose($handle);
        $showPreview = true;
    } else {
        $importError = "Échec d'upload (Code : " . $_FILES['csv_file']['error'] . ").";
        $showPreview = true;
    }
}


// ============================================================================
// 3. RECUPERATION DES DONNEES ET CALCULS (100% DYNAMIQUE)
// ============================================================================

// --- LECTURE DYNAMIQUE DES CATEGORIES ---
$stmtCats = $pdo->query("SELECT * FROM pf_budget_categories ORDER BY type DESC, sort_order ASC, label ASC");
$dbCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$categoriesConfig = [];
foreach ($dbCategories as $c) {
    $catType = ($c['type'] === 'Income') ? 'credit' : 'debit';
    $categoriesConfig[$c['code']] = [
        'type'        => $catType,
        'db_type'     => $c['type'],
        'label'       => ($c['icon'] ? $c['icon'] . ' ' : '') . $c['label'],
        'budget'      => 0, // Sera rempli par les règles
        'color'       => $c['color'] ?: '#64748b',
        'suggestions' => []
    ];
}

// Fallback "Autres" au cas où la BDD serait vide ou pour les dépenses non classées
if (!isset($categoriesConfig['AUTRES'])) {
    $categoriesConfig['AUTRES'] = [
        'type'=>'debit', 'db_type'=>'Expense', 'label'=>'📁 Autres / Divers', 
        'budget'=>0, 'color'=>'#94a3b8', 'suggestions'=>[]
    ];
}

// Peuplement dynamique des suggestions via les règles existantes
$stmtRules = $pdo->query("SELECT keyword, category FROM pf_import_rules");
while ($rule = $stmtRules->fetch(PDO::FETCH_ASSOC)) {
    if (isset($categoriesConfig[$rule['category']])) {
        $categoriesConfig[$rule['category']]['suggestions'][] = $rule['keyword'];
    }
}


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

$budget_income_prevu = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir_calc = 0; 
$fixedChargesList = []; $incomeList = []; $pending_charges = [];

// ============================================================================
// MAPPING DYNAMIQUE DES BUDGETS PRÉVISIONNELS (NOUVELLE LOGIQUE)
// ============================================================================
// On exclut "SAVINGS" pour s'aligner à 100% avec le recap.php
$stmt = $pdo->query("SELECT * FROM pf_budget_items WHERE category != 'SAVINGS' ORDER BY name ASC");
$estimatesList = []; // On prépare la liste pour retenir nos estimations multi-catégories

while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $absAmount = abs((float)$item['amount']); 
    
    // --- 🚀 INTERCEPTION DYNAMIQUE ---
    $calcDetails = "";
    if (isset($item['is_dynamic']) && $item['is_dynamic'] == 1 && !empty($item['dynamic_code'])) {
        require_once __DIR__ . '/../includes/DynamicBudgetCalculator.php';
        // Facturation à terme échu : on recule d'un mois par rapport au mois actif du suivi
        $prevDate = date('Y-m-d', strtotime('-1 month', strtotime($viewMonthDate)));
        $calcYear = date('Y', strtotime($prevDate));
        $calcMonth = date('m', strtotime($prevDate));

        $calc = new DynamicBudgetCalculator($pdo, $calcYear, $calcMonth);
        $estimateData = $calc->getEstimate($item['dynamic_code']);
        $absAmount = $estimateData['amount'];
        $calcDetails = $estimateData['details'];
    }
    // --------------------------------

    $amt = ($item['type'] === 'Annuel') ? $absAmount / 12 : $absAmount;
    $name = trim($item['name']);
    $catCode = $item['category']; 
    
    $isIncome = (strtoupper($catCode) === 'INCOME');
    
    if ($isIncome) {
        $incomeList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount];
        $total_income += $amt;
        $budget_income_prevu += $amt;
        
        if (!empty($catCode) && isset($categoriesConfig[$catCode])) {
            $categoriesConfig[$catCode]['budget'] += $amt;
        } else {
            $incomeCatKey = array_key_first(array_filter($categoriesConfig, fn($c) => $c['db_type'] === 'Income'));
            if ($incomeCatKey) $categoriesConfig[$incomeCatKey]['budget'] += $amt;
        }
    } else {
        $total_expenses_prevues += $amt;
        
        // 1. C'est une charge fixe (Non-variable)
        if ($item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
            $fixedChargesList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount]; 
            
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
                // On inclut les détails de l'interception dans le tooltip natif du suivi
                $tooltip = !empty($calcDetails) ? $calcDetails : 'Charge fixe en attente';
                $pending_charges[] = ['name' => $name, 'amount' => $absAmount, 'tooltip' => $tooltip];
            }
        }

        // 2. C'est une estimation (Variable)
        if ((int)$item['is_estimate'] === 1) {
            $estimatesList[] = [
                'name' => $name,
                'amount' => $amt,
                'categories' => !empty($catCode) ? array_map('trim', explode(',', $catCode)) : [],
                'calc_details' => $calcDetails // Transmission pour le second bloc
            ];
        }

        // 3. Attribution du budget aux jauges visuelles de la page
        if (!empty($catCode)) {
            $catCodesArray = array_map('trim', explode(',', $catCode));
            $firstCat = $catCodesArray[0];
            if (isset($categoriesConfig[$firstCat])) {
                $categoriesConfig[$firstCat]['budget'] += $amt;
            }
        }
    }
}

$stmtSalaries = $pdo->query("SELECT id, person, salary FROM pf_salary_config WHERE year = " . $viewY);
while ($sal = $stmtSalaries->fetch(PDO::FETCH_ASSOC)) {
    $incomeList[] = [
        'id' => 'SAL_' . $sal['id'], 
        'name' => 'Salaire ' . $sal['person'], 
        'amount' => (float)$sal['salary']
    ];
}

// L'enveloppe "Autres" prend tout le reste du budget non alloué
$categoriesConfig['AUTRES']['budget'] = max(0, $total_income - $total_expenses_prevues);

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);
$total_rentrees = 0;
$depenses_reelles = 0;

// VENTILATION DYNAMIQUE DES DÉPENSES
foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($categoriesConfig[$cat])) $cat = 'AUTRES';
    
    $val = (float)$exp['amount'];
    
    if ($categoriesConfig[$cat]['db_type'] === 'Income') { 
        $totals[$cat] += $val; 
    } else {
        if ($val > 0) $categoriesConfig[$cat]['budget'] += $val; // Remboursement
        else $totals[$cat] += abs($val);
    }
    
    $expensesByCategory[$cat][] = $exp;

    if ($val > 0) { 
        if ($categoriesConfig[$cat]['db_type'] === 'Income') { $total_rentrees += $val; }
        else { $depenses_reelles -= $val; } 
    } else {
        $depenses_reelles += abs($val);
    }
}

// CALCUL DES RESTES À VENIR BASÉ SUR LES ESTIMATIONS (MACRO)
foreach ($estimatesList as $est) {
    $spentForEstimate = 0;
    $detailsHover = [];
    
    // On additionne les dépenses de toutes les catégories liées à cette estimation
    foreach ($est['categories'] as $cCode) {
        if (!empty($cCode) && isset($totals[$cCode])) {
            $spentForEstimate += $totals[$cCode];
            // On prépare le détail pour la petite bulle d'info (hover)
            if ($totals[$cCode] > 0 && isset($categoriesConfig[$cCode])) {
                $detailsHover[] = strip_tags($categoriesConfig[$cCode]['label']) . " : " . number_format($totals[$cCode], 0) . "€";
            }
        }
    }
    
    $rem = max(0, $est['amount'] - $spentForEstimate);
    if ($rem > 0) {
        $reste_a_venir_calc += $rem;
        // On fusionne les détails du calcul dynamique (s'il y en a) avec les détails des dépenses réelles
        $baseTooltip = !empty($detailsHover) ? implode(' | ', $detailsHover) : 'Aucune dépense pour le moment';
        $tooltip = !empty($est['calc_details']) ? $est['calc_details'] . " | " . $baseTooltip : $baseTooltip;
        
        $pending_charges[] = [
            'name' => 'Reste ' . $est['name'], 
            'amount' => $rem,
            'tooltip' => $tooltip
        ];
    }
}

// F. Calculs des KPIs finaux
$rentrees_salaires_reels = 0;
foreach($categoriesConfig as $code => $conf) { if($conf['db_type'] === 'Income') $rentrees_salaires_reels += ($totals[$code] ?? 0); }

$rentrees_autres = $total_rentrees - $rentrees_salaires_reels;
$salaires_retenus = max($rentrees_salaires_reels, $budget_income_prevu);

$capacite_max_calc = $solde_initial + $salaires_retenus + $rentrees_autres;

$revenus_a_venir = max(0, $budget_income_prevu - $rentrees_salaires_reels);
$solde_theorique_calc = $snapshot['amount'] + $revenus_a_venir - $reste_a_venir_calc;

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

$monthNames = [
    1 => tr('month_01'), 2 => tr('month_02'), 3 => tr('month_03'), 4 => tr('month_04'),
    5 => tr('month_05'), 6 => tr('month_06'), 7 => tr('month_07'), 8 => tr('month_08'),
    9 => tr('month_09'), 10 => tr('month_10'), 11 => tr('month_11'), 12 => tr('month_12')
];
$monthName = $monthNames[(int)$viewM] . ' ' . $viewY;
?>

<div class="budget-view">

    <div class="bud-summary-card">
        
        <?php if ($isClosed): ?>
            <div class="bud-closed-banner">
                🔒 <?= tr('bud_closed_archived') ?>
            </div>
            <div style="height:20px;"></div>
        <?php endif; ?>

        <div class="bud-header-group">
            <div class="bud-header-title-wrapper">
                <h2 class="bud-header-title"><?= tr('bud_budget_of') ?> <?= $monthName ?></h2>
            </div>
            
            <div class="action-toolbar bud-toolbar">
                <?php if (!$isClosed): ?>
                <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="openSuiviModal('importCsvModal')">
                    📂 <?= tr('bud_import_csv') ?? 'Importer CSV' ?>
                </button>

                <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_close') ?>');">
                    <input type="hidden" name="action" value="close_month">
                    <input type="hidden" name="close_month_date" value="<?= $viewMonthDate ?>">
                    <input type="hidden" name="freeze_solde_actuel" value="<?= $solde_actuel ?>">
                    <input type="hidden" name="freeze_capacite_max" value="<?= $capacite_max ?>">
                    <input type="hidden" name="freeze_solde_theorique" value="<?= $solde_theorique ?>">
                    <input type="hidden" name="freeze_reste_a_venir" value="<?= $reste_a_venir ?>">
                    <button type="submit" class="pf-btn bud-btn-lock">🔒 <?= tr('bud_close_btn') ?></button>
                </form>
            <?php else: ?>
                <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_reopen') ?>');">
                    <input type="hidden" name="action" value="reopen_month">
                    <button type="submit" class="pf-btn btn-secondary pf-btn-small">🔓 <?= tr('bud_reopen_btn') ?></button>
                </form>
            <?php endif; ?>
            
            <div class="suivi-nav-group">
                <a href="<?= $prevLink ?>" class="suivi-btn-nav">◀</a>
                <a href="<?= $defaultLink ?>" class="suivi-btn-nav"><?= tr('bud_active_month_btn') ?></a>
                <a href="<?= $nextLink ?>" class="suivi-btn-nav">▶</a>
            </div>
        </div>
    </div>

    <div class="bud-kpi-grid">
        <div class="bud-kpi-card">
            <div class="bud-kpi-label"><?= tr('bud_bank_balance') ?> <?= $isClosed ? "(".tr('bud_frozen').")" : "(".tr('bud_current').")" ?></div>
            <div class="bud-kpi-value">
                <?= number_format($solde_actuel, 2, ',', ' ') ?> €
            </div>
            <?php if (!$isClosed): ?>
                <button onclick="openSuiviModal('snapshotModal')" class="bud-kpi-edit-btn">✏️ <?= tr('bud_update_btn') ?></button>
            <?php endif; ?>
        </div>

        <div class="bud-kpi-card">
            <div class="bud-kpi-label"><?= tr('bud_theoretical_balance') ?></div>
            <div class="bud-kpi-value <?= $solde_theorique < 0 ? 'bud-kpi-value--danger' : '' ?>">
                <?= number_format($solde_theorique, 2, ',', ' ') ?> €
            </div>
        </div>

        <div class="bud-kpi-card bud-kpi-card--danger">
            <div class="bud-kpi-label bud-kpi-label--danger">
                <span><?= tr('bud_upcoming_charges') ?></span>
                <button onclick="toggleDiv('pendingDetailsList')" class="bud-pending-toggle">👁️</button>
            </div>
            <div class="bud-kpi-value bud-kpi-value--danger">
                - <?= number_format($reste_a_venir, 2, ',', ' ') ?> €
            </div>
            <div id="pendingDetailsList" class="bud-pending-list" style="display:none;">
                <?php if (empty($pending_charges)): ?>
                    <div style="text-align:center; font-style:italic; opacity:0.8;"><?= tr('bud_all_paid') ?></div>
                <?php else: ?>
                    <?php foreach($pending_charges as $pc): ?>
                        <div class="bud-pending-item" <?= !empty($pc['tooltip']) ? 'title="'.htmlspecialchars($pc['tooltip']).'" style="cursor:help;"' : '' ?>>
                            <span><?= htmlspecialchars($pc['name']) ?></span>
                            <strong><?= number_format($pc['amount'], 0, ',', ' ') ?> €</strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <?php
        // --- 1. CALCULS DE L'ÉCHELLE DYNAMIQUE ---
        $buffer = max(abs($capacite_max), abs($solde_actuel), abs($solde_theorique)) * 0.05; 
        $minScale = min(0, $solde_theorique, $solde_actuel) - $buffer;
        $maxScale = max($capacite_max, $solde_actuel, 0) + $buffer;
        
        $range = $maxScale - $minScale;
        if ($range <= 0) $range = 1;

        $posZero = (($minScale * -1) / $range) * 100;
        $posActuel = (($solde_actuel - $minScale) / $range) * 100;
        $posTheorique = (($solde_theorique - $minScale) / $range) * 100;
        $posMax = (($capacite_max - $minScale) / $range) * 100;

        $widthActuel = (abs($solde_actuel) / $range) * 100;
        $leftActuel = $solde_actuel >= 0 ? $posZero : $posActuel;
        
        $widthDanger = 0; $leftDanger = $posZero;
        if ($solde_theorique < 0) {
            $widthDanger = (abs($solde_theorique) / $range) * 100;
            $leftDanger = $posTheorique; 
        }
        ?>

        <?php 
        // Jauge "Layered Glow" avec gestion dynamique des couleurs
        $colorTheo = $solde_theorique >= 0 ? 'var(--primary)' : 'var(--danger)'; 
        $bgTheoSoft = $solde_theorique >= 0 ? 'var(--bg-soft)' : 'var(--bg-danger-soft)';
        ?>
        <div class="bud-gauge-wrapper">
            <div class="bud-gauge-track">
                <!-- Zone Négative (Découvert) -->
                <?php if ($posZero > 0): ?>
                    <div class="bud-gauge-neg" style="width: <?= $posZero ?>%;"></div>
                <?php endif; ?>

                <!-- Remplissage Actuel -->
                <div class="bud-gauge-pos" style="left: <?= $leftActuel ?>%; width: <?= $widthActuel ?>%;">
                    <div class="bud-gauge-fill"></div>
                </div>

                <!-- Marqueur Zéro -->
                <div class="bud-gauge-marker-zero" style="left: <?= $posZero ?>%;"></div>

                <!-- Marqueur Théorique (Halo) -->
                <div class="bud-gauge-marker-theo" style="left: <?= $posTheorique ?>%; background: <?= $colorTheo ?>; box-shadow: 0 0 12px <?= $colorTheo ?>;"></div>
            </div>

            <div class="bud-gauge-labels">
                <div class="bud-label-theo" style="left: <?= $posTheorique ?>%; color: <?= $colorTheo ?>; background: <?= $bgTheoSoft ?>;">
                    <span class="lbl-desktop"><?= tr('bud_bar_theoretical') ?> : </span><?= number_format($solde_theorique, 0, ',', ' ') ?> €
                </div>
                <div class="bud-label-zero" style="left: <?= $posZero ?>%;">0 €</div>
                <div class="bud-label-actual" style="left: <?= $posActuel ?>%;">
                    <span class="lbl-desktop"><?= tr('bud_bar_actual') ?> : </span><?= number_format($solde_actuel, 0, ',', ' ') ?> €
                </div>
                <div class="bud-label-max" title="<?= sprintf(tr('bud_capacity_tooltip'), number_format($solde_initial, 0), number_format($salaires_retenus, 0), number_format($rentrees_autres, 0)) ?>">
                    <span class="lbl-desktop"><?= tr('bud_bar_max_cap') ?> : </span><?= number_format($capacite_max, 0, ',', ' ') ?> € ℹ️
                </div>
            </div>
        </div>
</div>


    <div class="bud-categories-grid">
    <?php foreach ($categoriesConfig as $key => $conf): ?>
    <div class="cat-card" <?= $isClosed ? 'style="opacity:0.8;"' : '' ?>>
        <div class="cat-card-header" style="background:<?= $conf['color'] ?>15; border-bottom:1px solid <?= $conf['color'] ?>30;">
            <div class="cat-card-title-group">
                <h3 style="margin:0; font-size:1rem; color:<?= $conf['color'] ?>;"><?= $conf['label'] ?></h3>
                <div class="cat-card-subtitle">
                    <?php $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']); echo $logic['text']; ?>
                </div>
            </div>
            <?php if (!$isClosed): ?>
                <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes(strip_tags($conf['label'])) ?>')">＋</button>
            <?php endif; ?>
        </div>

        <?php $barCol = ($conf['db_type'] === 'Income') ? 'var(--success)' : ($logic['isOver'] ? 'var(--danger)' : $conf['color']); ?>
        <div style="background:var(--bg-page); height:4px; width:100%;">
            <div style="width:<?= $logic['pct'] ?>%; background:<?= $barCol ?>; height:100%;"></div>
        </div>

        <div style="flex:1; max-height:300px; overflow-y:auto; padding:0;">
            <?php if (empty($expensesByCategory[$key])): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-style:italic; font-size:0.85rem;"><?= tr('bud_no_lines') ?></div>
            <?php else: ?>
                <table class="bud-expense-table">
                    <?php foreach ($expensesByCategory[$key] as $exp): ?>
                    <tr class="bud-expense-row">
                        <td class="bud-expense-date"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                        <td class="bud-expense-label">
                            <?= htmlspecialchars($exp['label']) ?>
                        </td>
                        <td class="bud-expense-amount" style="color:<?= $exp['amount'] > 0 ? 'var(--success)' : 'var(--text-main)' ?>;">
                            <?= $exp['amount'] > 0 ? '+' : '-' ?><?= number_format(abs($exp['amount']), 2) ?>
                        </td>
                        <?php if (!$isClosed): ?>
                            <td class="bud-expense-actions">
                                <button class="btn-icon-action edit" onclick='openEditModal(<?= json_encode($exp, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="<?= tr('edit') ?>">✏️</button>
                                <button class="btn-icon-action delete btn-safe-click" onclick="deleteExpense(<?= $exp['id'] ?>)" title="<?= tr('delete') ?>">🗑️</button>
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
                        <option value="<?= $key ?>"><?= strip_tags($conf['label']) ?></option>
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

            <div class="form-group" id="blockInputFrais" style="margin-bottom:15px; display:none;">
                <label class="pf-label">Lier à une Charge Fixe (Optionnel)</label>
                <select name="budget_item_id" id="fraisSelect" class="pf-input" disabled>
                <option value="">-- Aucune --</option>
                    <?php foreach ($fixedChargesList as $fc): ?><option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="blockInputIncome" style="margin-bottom:15px; display:none;">
                <label class="pf-label">Lier à un Revenu Prévu (Optionnel)</label>
                <select name="budget_item_id" id="incomeSelect" class="pf-input" disabled>
                    <option value="">-- Aucun --</option>
                    <?php foreach ($incomeList as $inc): ?>
                        <option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?></option>
                    <?php endforeach; ?>
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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; padding:0;">🏦 <?= tr('bud_update_balance') ?></h3>
            <button type="button" onclick="closeSuiviModal('snapshotModal')" style="background:none; border:none; font-size:1.8rem; cursor:pointer; color:#94a3b8; line-height:1;">&times;</button>
        </div>
        
        <div class="pf-modal-body">
            <form id="snapshotForm" method="POST">
                <input type="hidden" name="action" value="save_snapshot">
                <div class="pf-form-group">
                    <input type="date" name="snapshot_date" class="pf-input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="pf-form-group">
                    <input type="number" step="0.01" name="snapshot_amount" class="pf-input" placeholder="<?= tr('bud_balance_eur') ?>" required>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" onclick="closeSuiviModal('snapshotModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
            <button type="submit" form="snapshotForm" class="pf-btn"><?= tr('btn_save') ?></button>
        </div>
    </div>
</div>

<div id="importCsvModal" class="pf-modal <?= $showPreview ? 'open' : '' ?>">
    <div class="pf-modal-content <?= $showPreview ? 'modal-large' : '' ?>" style="display: flex; flex-direction: column; max-height: 90vh; padding: 24px; overflow: hidden;">
        
        <!-- HEADER FIXE -->
        <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-light); padding-bottom: 16px; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--text-main);">📄 <?= tr('bud_import_csv') ?></h3>
            <button type="button" onclick="closeSuiviModal('importCsvModal')" style="background: transparent; border: none; font-size: 2rem; line-height: 1; cursor: pointer; color: var(--text-muted); padding: 0 5px;">&times;</button>
        </div>

        <?php if (!empty($importError)): ?>
            <div class="pf-alert pf-alert--error pf-mb-md" style="flex-shrink: 0;"><?= htmlspecialchars($importError) ?></div>
        <?php endif; ?>

        <?php if (!$showPreview): ?>
            <!-- FORMULAIRE D'UPLOAD -->
            <form method="POST" enctype="multipart/form-data" action="?tab=suivi&m=<?= htmlspecialchars($viewM) ?>&y=<?= htmlspecialchars($viewY) ?>" style="display: flex; flex-direction: column; flex: 1;">
                <div class="import-dropzone" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px dashed var(--border-strong); border-radius: 12px; background: var(--bg-subtle);">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📄</div>
                    <label class="pf-label" style="font-size: 1.1rem;"><?= tr('bud_csv_file') ?></label>
                    <input type="file" name="csv_file" accept=".csv" class="pf-input" style="max-width: 300px; margin-top: 10px;" required>
                </div>
                <div style="flex-shrink: 0; margin-top: 20px; border-top: 1px solid var(--border-light); padding-top: 16px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeSuiviModal('importCsvModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                    <button type="submit" class="pf-btn"><?= tr('bud_preview') ?></button>
                </div>
            </form>
        <?php else: ?>
            <!-- FORMULAIRE DE MAPPING (SCROLLABLE AU CENTRE) -->
            <form method="POST" id="formMapping" action="?tab=suivi&m=<?= htmlspecialchars($viewM) ?>&y=<?= htmlspecialchars($viewY) ?>" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
                <input type="hidden" name="action" value="save_import">
                
                <!-- En-tête des actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; flex-shrink: 0; margin-bottom: 16px; background: var(--bg-subtle); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-light);">
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 500; color: var(--text-main);"><?= tr('bud_validate_import') ?></p>
                    
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <label class="pf-switch-wrap" style="font-size: 0.85rem; margin: 0;">
                            <?= tr('bud_hide_categorized') ?? 'Masquer classés' ?>
                            <div class="pf-switch">
                                <input type="checkbox" id="toggleCategorized" onchange="filterCategorizedRows(this.checked)">
                                <span class="pf-switch-slider"></span>
                            </div>
                        </label>
                        <span id="missingCount" class="badge-type" style="background: var(--bg-danger-soft); color: var(--danger); display: none; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem;"></span>
                    </div>
                </div>

                <!-- VUE DESKTOP : TABLEAU STRICT -->
                <div class="desktop-import-table" style="flex: 1; overflow-y: auto; min-height: 0; border: 1px solid var(--border-light); border-radius: 8px;">
                    <table class="pf-table bs-csv-table" style="table-layout: auto; width: 100%; margin: 0; border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center; padding: 12px 8px;"><input type="checkbox" onclick="toggleAll(this)" checked style="width: 16px; height: 16px; cursor: pointer;"></th>
                                <th style="width: 140px; padding: 12px 8px;"><?= tr('bud_assign_month') ?></th>
                                <th style="width: 80px; padding: 12px 8px;"><?= tr('bud_op_date') ?></th>
                                <th style="width: 25%; padding: 12px 8px;"><?= tr('bud_label') ?></th>
                                <th style="width: 100px; text-align: right; padding: 12px 8px;"><?= tr('bud_amount') ?></th>
                                <th><?= tr('bud_category_and_link') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvData as $idx => $row): 
                                $dup = $row['is_duplicate']; $isCrd = $row['is_credit']; $dis = $dup ? 'disabled' : ''; 
                                $hasCat = !empty($row['cat']);
                                $bgColClass = $dup ? 'is-duplicate' : (!$hasCat && !$isCrd ? 'bg-danger-soft' : '');
                            ?>
                            <tr class="import-row bs-csv-tr <?= $bgColClass ?>" data-categorized="<?= $hasCat ? '1' : '0' ?>" style="<?= $dup ? 'opacity:0.5;' : '' ?>">
                                <td style="text-align: center; padding: 12px 8px;">
                                    <input type="checkbox" class="line-checkbox" style="width: 16px; height: 16px; cursor: pointer;" name="lines[<?= $idx ?>][import_check]" value="1" <?= $dup?'':'checked' ?> <?= $dis ?> onchange="checkValidation()">
                                    <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= htmlspecialchars($row['label']) ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][ref]" value="<?= $row['ref'] ?>">
                                    <input type="hidden" class="is-credit-flag" name="lines[<?= $idx ?>][is_credit]" value="<?= $isCrd ?>">
                                </td>
                                <td>
                                    <input type="month" name="lines[<?= $idx ?>][gestion_month]" value="<?= substr($viewMonthDate, 0, 7) ?>" class="pf-input" style="height: 36px; padding: 6px;" <?= $dis ?>>
                                </td>
                                <td class="font-bold text-main">
                                    <?= date('d/m', strtotime($row['date'])) ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; line-height: 1.4; color: var(--text-main);" title="<?= htmlspecialchars($row['label']) ?>">
                                        <?= htmlspecialchars($row['label']) ?>
                                    </div>
                                    <?php if ($dup): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-style: italic; margin-top: 4px;"><?= tr('bud_import_duplicate_hint') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="font-bold <?= $isCrd ? 'text-success' : 'text-main' ?>" style="text-align: right; white-space: nowrap;">
                                    <?= $isCrd ? '+' : '-' ?> <?= number_format($row['amount'], 2) ?> €
                                </td>
                                <td style="padding: 12px 8px;">
                                    <div class="category-cell-wrapper">
                                        <div class="category-selects-row">
                                            <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="handleLineCatChange(this)" <?= $dis ?>>
                                                <option value="">-- <?= $isCrd ? tr('bud_ignore') : tr('bud_to_define') ?> --</option>
                                                <?php foreach ($categoriesConfig as $k => $c): ?>
                                                    <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= strip_tags($c['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-frais" onchange="checkValidation()" style="display:none;" disabled>
                                                <option value="">-- <?= tr('bud_link_fixed_charge') ?> --</option>
                                                <?php foreach ($fixedChargesList as $fc): ?>
                                                    <option value="<?= $fc['id'] ?>" <?= ($row['suggested_item_id'] == $fc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($fc['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-income" onchange="checkValidation()" style="display:none;" disabled>
                                                <option value="">-- <?= tr('bud_is_income') ?> --</option>
                                                <?php foreach ($incomeList as $inc): ?>
                                                    <option value="<?= $inc['id'] ?>" <?= ($row['suggested_item_id'] == $inc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($inc['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <label class="ignore-checkbox-wrapper">
                                            <input type="checkbox" onchange="toggleIgnoreRow(this)" <?= $dup?'checked disabled':'' ?>> 
                                            <?= tr('bud_ignore_this_line') ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VUE MOBILE : ACCORDÉON -->
                <div class="prop2-container mobile-import-accordion" style="flex: 1; overflow-y: auto; min-height: 0; margin-top: 10px;">
                    <?php foreach ($csvData as $idx => $row): 
                        $dup = $row['is_duplicate']; $isCrd = $row['is_credit']; $dis = $dup ? 'disabled' : ''; 
                        $hasCat = !empty($row['cat']);
                        $statusBg = ($hasCat || $dup || ($isCrd && empty($row['cat']))) ? 'var(--success)' : 'var(--danger)';
                        if ($dup) { $statusBg = 'var(--text-muted)'; }
                    ?>
                    <div class="prop2-item import-row <?= $dup ? 'is-duplicate' : '' ?>" data-categorized="<?= $hasCat ? '1' : '0' ?>">
                        <div class="prop2-summary" onclick="toggleMobileAccordion(this, event)">
                            <div class="prop2-summary-left">
                                <div class="prop2-date"><?= date('d/m/Y', strtotime($row['date'])) ?></div>
                                <div class="prop2-label"><?= htmlspecialchars($row['label']) ?></div>
                            </div>
                            <div class="amount-badge <?= $isCrd ? 'amount-income' : 'amount-expense' ?>">
                                <?= $isCrd ? '+' : '-' ?><?= number_format($row['amount'], 2) ?> €
                            </div>
                            <div class="prop2-status" style="background: <?= $statusBg ?>;"></div>
                        </div>
                        
                        <div class="prop2-details">
                            <input type="checkbox" class="line-checkbox hol-hidden" name="lines[<?= $idx ?>][import_check]" value="1" <?= $dup?'':'checked' ?> <?= $dis ?> onchange="checkValidation()">
                            <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                            <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= htmlspecialchars($row['label']) ?>">
                            <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                            <input type="hidden" name="lines[<?= $idx ?>][ref]" value="<?= $row['ref'] ?>">
                            <input type="hidden" class="is-credit-flag" name="lines[<?= $idx ?>][is_credit]" value="<?= $isCrd ?>">

                            <div class="pf-stack-sm">
                                <div>
                                    <label class="pf-label pf-label-sm"><?= tr('bud_assign_month') ?></label>
                                    <input type="month" name="lines[<?= $idx ?>][gestion_month]" value="<?= substr($viewMonthDate, 0, 7) ?>" class="pf-input" style="padding: 8px; font-size: 0.9rem; height: auto;" <?= $dis ?>>
                                </div>

                                <div>
                                    <label class="pf-label pf-label-sm"><?= tr('bud_category') ?></label>
                                    <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="handleLineCatChange(this)" <?= $dis ?> style="padding: 8px; font-size: 0.9rem; height: auto;">
                                        <option value="">-- <?= $isCrd ? tr('bud_ignore') : tr('bud_to_define') ?> --</option>
                                        <?php foreach ($categoriesConfig as $k => $c): ?>
                                            <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= strip_tags($c['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="display:none;" class="mobile-frais-block">
                                    <label class="pf-label pf-label-sm">Lier à une Charge Fixe</label>
                                    <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-frais" onchange="checkValidation()" style="padding: 8px; font-size: 0.9rem; height: auto;" disabled>
                                        <option value="">-- Aucune --</option>
                                        <?php foreach ($fixedChargesList as $fc): ?>
                                            <option value="<?= $fc['id'] ?>" <?= ($row['suggested_item_id'] == $fc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($fc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="display:none;" class="mobile-income-block">
                                    <label class="pf-label pf-label-sm"><?= tr('bud_is_income') ?></label>
                                    <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-income" onchange="checkValidation()" style="padding: 8px; font-size: 0.9rem; height: auto;" disabled>
                                        <option value="">-- Aucun --</option>
                                        <?php foreach ($incomeList as $inc): ?>
                                            <option value="<?= $inc['id'] ?>" <?= ($row['suggested_item_id'] == $inc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($inc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="margin-top: 5px; padding-top: 10px; border-top: 1px dashed var(--border-light);">
                                    <label class="pf-switch-wrap" style="color: var(--danger);">
                                        <input type="checkbox" onchange="toggleIgnoreRow(this)" style="width: 18px; height: 18px; accent-color: var(--danger);" <?= $dup?'checked disabled':'' ?>>
                                        <?= tr('bud_ignore') ?> cette ligne
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="flex-shrink: 0; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="?tab=suivi" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></a>
                    <button type="submit" id="btnImport" class="pf-btn" style="background: var(--success); color: #fff; border-color: var(--success);"><?= tr('btn_import') ?? 'Importer' ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if ($showPreview): ?>
    document.body.classList.add('no-scroll');
<?php endif; ?>

window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_confirm_delete': <?= json_encode(tr('bud_confirm_delete')) ?>,
    'bud_to_define_js': <?= json_encode(tr('bud_to_define_js')) ?>,
    'error_occured': <?= json_encode(tr('error_occured')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'btn_cancel': <?= json_encode(tr('btn_cancel')) ?>,
    'btn_delete': <?= json_encode(tr('btn_delete')) ?>,
};

// --- DICTIONNAIRE JS DYNAMIQUE ---
const catConfigs = <?= json_encode($categoriesConfig) ?>;

const activeViewMonth = '<?= substr($viewMonthDate, 0, 7) ?>';
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function openSuiviModal(id) { document.getElementById(id).classList.add('open'); document.body.classList.add('no-scroll'); }
function closeSuiviModal(id) { document.getElementById(id).classList.remove('open'); document.body.classList.remove('no-scroll');}

// ============================================================================
// GESTION DU FORMULAIRE MANUEL (Ajout / Édition)
// ============================================================================
function handleModalCatChange(select) {
    const catKey = select.value.toUpperCase(); 
    const conf = catConfigs[select.value] || {db_type: 'Expense', suggestions: []};
    
    document.getElementById('blockInputText').style.display = 'block';
    document.getElementById('blockInputFrais').style.display = 'none';
    document.getElementById('blockInputIncome').style.display = 'none';
    
    document.getElementById('modalLabelInput').required = true;
    document.getElementById('fraisSelect').required = false;
    document.getElementById('incomeSelect').required = false;
    document.getElementById('fraisSelect').disabled = true;
    document.getElementById('incomeSelect').disabled = true;

    if (conf.db_type === 'Income') { 
        const incSel = document.getElementById('incomeSelect');
        if (incSel.options.length > 1) { 
            document.getElementById('blockInputIncome').style.display = 'block'; 
            incSel.disabled = false; 
        }
    } 
    else if (catKey === 'FIXED') { 
        document.getElementById('blockInputFrais').style.display = 'block'; 
        document.getElementById('fraisSelect').disabled = false; 
        document.getElementById('fraisSelect').required = true; 
    }

    const list = document.getElementById('modalSuggestions'); list.innerHTML = ''; 
    if (conf.suggestions) { 
        conf.suggestions.forEach(i => { const op = document.createElement('option'); op.value = i; list.appendChild(op); }); 
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
    
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = catKey; 
    handleModalCatChange(catSelect);
    document.getElementById('modalIsCredit').value = (catConfigs[catKey]?.db_type === 'Income') ? "1" : "0";
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
    
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = e.category; 
    handleModalCatChange(catSelect);
    
    if (catConfigs[e.category]?.db_type === 'Income') document.getElementById('incomeSelect').value = e.budget_item_id;
    else document.getElementById('fraisSelect').value = e.budget_item_id;
}


// ============================================================================
// NOUVEAU : GESTION DYNAMIQUE DU CSV (RESPONSIVE + ACCORDEON)
// ============================================================================

function toggleResponsiveInputs() {
    const isMobile = window.innerWidth <= 768;
    
    document.querySelectorAll('.desktop-import-table input, .desktop-import-table select').forEach(el => {
        if (!el.classList.contains('originally-disabled')) el.disabled = isMobile;
    });
    
    document.querySelectorAll('.mobile-import-accordion input, .mobile-import-accordion select').forEach(el => {
        if (!el.classList.contains('originally-disabled')) el.disabled = !isMobile;
    });
    checkValidation();
}

function filterCategorizedRows(hide) {
    document.querySelectorAll('.import-row:not(.is-duplicate)').forEach(item => {
        if (hide && item.getAttribute('data-categorized') === '1') {
            item.style.display = 'none';
        } else {
            item.style.display = window.innerWidth <= 768 ? 'block' : 'table-row';
        }
    });
}

function toggleMobileAccordion(summaryEl, event) {
    if (['INPUT', 'LABEL', 'BUTTON'].includes(event.target.tagName)) return;
    const propItem = summaryEl.closest('.prop2-item');
    propItem.classList.toggle('open');
    // mobile : la ligne dépliée est ramenée dans la zone visible (la liste est petite sur un téléphone)
    if (propItem.classList.contains('open')) setTimeout(() => propItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 60);
}

function toggleIgnoreRow(checkbox) {
    const row = checkbox.closest('.import-row');
    const mainCheckbox = row.querySelector('.line-checkbox');
    
    if (checkbox.checked) {
        mainCheckbox.checked = false;
        row.classList.add('ignored');
        const statusDot = row.querySelector('.prop2-status');
        if (statusDot) statusDot.style.background = 'var(--text-muted)';
        if (window.innerWidth > 768) row.style.background = '';
    } else {
        mainCheckbox.checked = true;
        row.classList.remove('ignored');
        updateRowStatus(row.querySelector('.line-select'));
    }
    checkValidation();
}

function updateRowStatus(select) {
    const row = select.closest('.import-row');
    if (!row || row.classList.contains('ignored') || row.classList.contains('is-duplicate')) return;
    
    const isCredit = row.querySelector('.is-credit-flag').value === '1';
    const hasCat = select.value !== "";
    row.setAttribute('data-categorized', hasCat ? '1' : '0');
    
    const statusDot = row.querySelector('.prop2-status');
    if (statusDot) {
        statusDot.style.background = (hasCat || (isCredit && !hasCat)) ? 'var(--success)' : 'var(--danger)';
    }
    
    if (window.innerWidth > 768) {
        row.style.background = (hasCat || (isCredit && !hasCat)) ? '' : 'var(--bg-danger-soft)';
    }
    
    const toggleSwitch = document.getElementById('toggleCategorized');
    if (toggleSwitch) filterCategorizedRows(toggleSwitch.checked);
}

function handleLineCatChange(select, isInit = false) {
    const row = select.closest('.import-row');
    const fSel = row.querySelector('.select-frais'); 
    const iSel = row.querySelector('.select-income');
    const catKey = select.value.toUpperCase();
    const conf = catConfigs[select.value] || null;
    
    fSel.style.display = 'none'; iSel.style.display = 'none'; 
    
    const mFraisBlock = row.querySelector('.mobile-frais-block');
    const mIncomeBlock = row.querySelector('.mobile-income-block');
    if(mFraisBlock) mFraisBlock.style.display = 'none';
    if(mIncomeBlock) mIncomeBlock.style.display = 'none';

    if (!isInit) { fSel.value = ''; iSel.value = ''; }
    fSel.disabled = true; iSel.disabled = true;

    if (conf && conf.db_type === 'Income') { 
        if (iSel.options.length > 1) { 
            iSel.style.display = 'block'; iSel.disabled = false; 
            if(mIncomeBlock) mIncomeBlock.style.display = 'block';
        }
    } else if (catKey === 'FIXED') { 
        fSel.style.display = 'block'; fSel.disabled = false; 
        if(mFraisBlock) mFraisBlock.style.display = 'block';
    }
    
    if(!isInit) { updateRowStatus(select); checkValidation(); }
}

function toggleAll(src) {
    document.querySelectorAll('.desktop-import-table .line-checkbox:not([disabled])').forEach(c => {
        c.checked = src.checked;
        const row = c.closest('.import-row');
        if (!src.checked && row && !row.classList.contains('is-duplicate')) {
            row.style.background = ''; 
        }
    }); 
    checkValidation(); 
}

function checkValidation() {
    let miss = 0;
    const isMobile = window.innerWidth <= 768;
    const activeSelector = isMobile ? '.mobile-import-accordion' : '.desktop-import-table';
    
    document.querySelectorAll(`${activeSelector} .line-checkbox:checked`).forEach(cb => { 
        const row = cb.closest('.import-row'); 
        if (!row || row.classList.contains('ignored')) return;

        const isCrd = row.querySelector('.is-credit-flag').value === '1'; 
        const cat = row.querySelector('.line-select').value;
        const catKey = cat.toUpperCase();
        const fSel = row.querySelector('.select-frais');

        let v = true; 
        if (cat === "") { if (!isCrd) v = false; } 
        else if (catKey === 'FIXED' && fSel.value === "") { v = false; }
        
        if (!v) {
            miss++;
            if (!isMobile) row.style.background = 'var(--bg-danger-soft)';
        }
    });
    
    const btn = document.getElementById('btnImport'); 
    const msg = document.getElementById('missingCount');
    if (btn && msg) {
        if(miss > 0) { 
            btn.disabled = true; btn.style.opacity = 0.5; 
            msg.style.display = 'inline'; msg.innerText = miss + ' ' + (window.I18N['bud_to_define_js'] || 'à définir'); 
        } else { 
            btn.disabled = false; btn.style.opacity = 1; msg.style.display = 'none'; 
        }
    }
}

// L'écouteur demandé, préservé :
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('pf-modal')) {
        e.target.classList.remove('open', 'is-active');
        e.target.style.display = ''; 
        document.body.classList.remove('no-scroll');
    }
});

window.addEventListener('resize', () => {
    if (document.getElementById('formMapping')) {
        toggleResponsiveInputs();
        const toggleSwitch = document.getElementById('toggleCategorized');
        filterCategorizedRows(toggleSwitch ? toggleSwitch.checked : false);
    }
});

if (document.getElementById('formMapping')) {
    document.querySelectorAll('.select-frais, .select-income').forEach(el => {
        if (el.disabled) el.classList.add('originally-disabled');
    });
    document.querySelectorAll('.line-select').forEach(s => handleLineCatChange(s, true));
    toggleResponsiveInputs();
}

// ============================================================================
// SAUVEGARDE ET SUPPRESSION (ASYNCHRONE)
// ============================================================================
async function deleteExpense(id) {
    const confirmed = await pachaConfirm("Suppression", window.I18N['bud_confirm_delete']);
    if (!confirmed) return;

    const formData = new FormData();
    formData.append("action", "delete_expense");
    formData.append("id", id);

    try {
        await pachaFetch("modules/budget/includes/api/manage-item.php", { method: "POST", body: formData });
        showToast(window.I18N['bud_toast_deleted'] || "Supprimé"); 
        setTimeout(() => window.location.reload(), 800);
    } catch (err) {
        showToast(window.I18N['error_occured'] || "Erreur", "error");
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const formExpense = document.querySelector('#manualExpenseModal form');
    if (formExpense) {
        formExpense.addEventListener('submit', async (e) => {
            e.preventDefault(); 
            const submitBtn = formExpense.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerText = '⏳ ...';

                const formData = new FormData(formExpense);
                formData.set('action', 'save_expense_manual'); 

                const actionUrl = formExpense.getAttribute('action') || 'modules/budget/includes/api/manage-item.php';

                const result = await pachaFetch(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (result.success) {
                    closeSuiviModal('manualExpenseModal');
                    formExpense.reset();
                    window.location.reload(); 
                } else {
                    showToast((window.I18N['error_occured'] || 'Erreur') + ' : ' + (result.error || 'Inconnue'), 'error');
                }
            } catch (error) {
                console.error("Erreur réseau :", error);
                showToast(window.I18N['bud_err_tech'] || "Erreur critique réseau.", 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalBtnText;
            }
        });
    }
});
</script>