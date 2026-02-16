<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. GESTION DES ACTIONS (POST/GET)
// ============================================================================

$currentMonthKey = date('m-Y');

// A. AJOUT CATÉGORIE TEMPORAIRE MANUELLE
if (isset($_POST['action']) && $_POST['action'] === 'add_temp_cat') {
    $name = trim($_POST['cat_name']);
    $budget = floatval($_POST['cat_budget']);
    $type = $_POST['cat_type'] === 'credit' ? 'credit' : 'debit';
    
    if ($name && $budget >= 0) {
        $stmt = $pdo->prepare("INSERT INTO pf_monthly_categories (month_year, name, type, budget) VALUES (?, ?, ?, ?)");
        $stmt->execute([$currentMonthKey, $name, $type, $budget]);
        header("Location: ?tab=suivi"); exit;
    }
}

// B. SUPPRESSION CATÉGORIE TEMPORAIRE
if (isset($_GET['del_cat'])) {
    $pdo->prepare("DELETE FROM pf_monthly_categories WHERE id = ?")->execute([(int)$_GET['del_cat']]);
    header("Location: ?tab=suivi"); exit;
}

// C. SAUVEGARDE SNAPSHOT BANCAIRE
if (isset($_POST['action']) && $_POST['action'] === 'save_snapshot') {
    $date = $_POST['snapshot_date'];
    $amount = floatval($_POST['snapshot_amount']);
    $pdo->query("DELETE FROM pf_bank_snapshots"); 
    $pdo->prepare("INSERT INTO pf_bank_snapshots (snapshot_date, amount) VALUES (?, ?)")->execute([$date, $amount]);
    header("Location: ?tab=suivi"); exit;
}

// D. SAUVEGARDE IMPORT CSV (AVEC BUDGET_ITEM_ID)
if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    
    // 1. Catégories temporaires
    $tempCatMapping = [];
    if (!empty($_POST['new_temp_cats'])) {
        $stmtTemp = $pdo->prepare("INSERT INTO pf_monthly_categories (month_year, name, type, budget) VALUES (?, ?, ?, 0)");
        foreach ($_POST['new_temp_cats'] as $tempKey => $catData) {
            $stmtTemp->execute([$currentMonthKey, $catData['name'], $catData['type']]);
            $tempCatMapping[$tempKey] = $pdo->lastInsertId();
        }
    }

    // 2. Insertion avec budget_item_id
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref, budget_item_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (isset($line['import_check'])) {
                $cat = $line['cat'];
                $is_credit = isset($line['is_credit']) ? (int)$line['is_credit'] : 0;
                // Récupération ID (Charge fixe OU Revenu)
                $budgetItemId = !empty($line['budget_item_id']) ? (int)$line['budget_item_id'] : null;
                
                if ($is_credit && empty($cat)) continue;
                if (!$is_credit && empty($cat)) continue;

                if (strpos($cat, 'NEW_TEMP_') === 0 && isset($tempCatMapping[$cat])) {
                    $cat = 'TEMP_' . $tempCatMapping[$cat];
                }

                $finalAmount = $is_credit ? -abs($line['amount']) : abs($line['amount']);

                try {
                    $stmtExp->execute([$line['date'], $cat, $line['label'], $finalAmount, $line['ref'], $budgetItemId]);
                    $stmtRule->execute([$line['label'], $cat]);
                    $count++;
                } catch (Exception $e) { continue; }
            }
        }
    }
    header("Location: ?tab=suivi&msg=imported_$count"); exit;
}

// E. AJOUT DÉPENSE MANUELLE (AVEC BUDGET_ITEM_ID)
if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $cat = $_POST['category']; 
    $amount = floatval($_POST['amount']); 
    $date = $_POST['date'];
    
    $label = trim($_POST['label']);
    $budgetItemId = null;

    if ($cat === 'School' && !empty($_POST['label_select'])) {
        $label = trim($_POST['label_select']);
    } 
    // GESTION ID POUR FRAIS ET REVENUS
    elseif (($cat === 'Frais' || $cat === 'Income') && !empty($_POST['budget_item_id'])) {
        $budgetItemId = (int)$_POST['budget_item_id'];
    }

    if ($label && $amount > 0) {
        $uniqueRef = "MANUAL_" . uniqid();
        // Si c'est un revenu (Income), on s'assure que le montant est enregistré en négatif (Crédit) dans pf_expenses
        // Sauf si l'utilisateur a déjà mis un moins, mais on part du principe qu'il saisit un montant positif
        if ($cat === 'Income') {
            $amount = -abs($amount);
        }

        $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref, budget_item_id) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$date, $cat, $label, $amount, $uniqueRef, $budgetItemId]);
        header("Location: ?tab=suivi"); exit;
    }
}

// F. SUPPRESSION DÉPENSE
if (isset($_GET['delete_expense'])) {
    $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([(int)$_GET['delete_expense']]);
    header("Location: ?tab=suivi"); exit;
}

// ============================================================================
// 2. CALCUL DES BUDGETS & LISTES DÉROULANTES
// ============================================================================

$budget_fmcg = 0; $budget_school = 0; $budget_essence = 0; $budget_frais = 0; $budget_income_prevu = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir = 0; 
$today_day = (int)date('j'); 

// Listes pour les selecteurs
$fixedChargesList = [];
$incomeList = []; // NOUVEAU : Liste des revenus

// Snapshot
$snapshot = ['date' => date('Y-m-d'), 'amount' => 0];
$solde_theorique = 0;
try {
    $snapStmt = $pdo->query("SELECT * FROM pf_bank_snapshots ORDER BY id DESC LIMIT 1");
    if ($s = $snapStmt->fetch(PDO::FETCH_ASSOC)) {
        $snapshot = ['date' => $s['snapshot_date'], 'amount' => (float)$s['amount']];
    }
} catch (Exception $e) {}
$solde_theorique = $snapshot['amount'];
if (!empty($snapshot['date'])) {
    try {
        $stmtCalc = $pdo->prepare("SELECT SUM(amount) as total_diff FROM pf_expenses WHERE date_exp > ?");
        $stmtCalc->execute([$snapshot['date']]);
        $resDiff = $stmtCalc->fetch(PDO::FETCH_ASSOC);
        if ($resDiff && $resDiff['total_diff'] !== null) $solde_theorique -= (float)$resDiff['total_diff'];
    } catch (Exception $e) {}
}

// Lecture Budget - Ajout de 'is_checked' à la requête
$stmt = $pdo->query("SELECT id, name, amount, type, category, is_estimate, payment_day, is_checked FROM pf_budget_items ORDER BY name ASC");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rawAmount = (float)$item['amount'];
    $amt = ($item['type'] === 'Annuel') ? $rawAmount / 12 : $rawAmount;
    $name = trim($item['name']);
    $pDay = (int)$item['payment_day'];
    $isChecked = (int)$item['is_checked'];
    
    // Remplissage liste Charges Fixes
    if ($item['category'] === 'expense' && $item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
        $fixedChargesList[] = $item;
    }
    // Remplissage liste Revenus
    if ($item['category'] === 'income') {
        $incomeList[] = $item;
        $total_income += $amt;
        // On peut vouloir suivre le budget des revenus aussi
        $budget_income_prevu += $amt; 
    } else {
        $total_expenses_prevues += $amt;
        
        // Calcul Reste à venir
        if ($item['category'] === 'expense' && $item['type'] === 'Mensuel') {
            if ($isChecked === 0 && (int)$item['is_estimate'] === 0) {
                $reste_a_venir += $rawAmount;
            }
        }

        if ($name === 'Estimacio F&B & beauty') $budget_fmcg = $amt;
        elseif ($name === 'Estimacio escola') $budget_school = $amt;
        elseif ($name === 'Estimation gasolina') $budget_essence = $amt;
        elseif ((int)$item['is_estimate'] === 0 && $item['type'] === 'Mensuel' && $item['category'] === 'expense') {
            $budget_frais += $rawAmount;
        }
    }
}

// Catégories Temporaires
$tempCats = []; $total_temp_budget = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM pf_monthly_categories WHERE month_year = ?");
    $stmt->execute([$currentMonthKey]);
    $tempCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($tempCats as $tc) if ($tc['type'] === 'debit') $total_temp_budget += $tc['budget'];
} catch (Exception $e) {}

$budget_autres = $total_income - ($total_expenses_prevues + $total_temp_budget);
if ($budget_autres < 0) $budget_autres = 0;

// ============================================================================
// 3. CONFIGURATION CATÉGORIES
// ============================================================================

$categoriesConfig = [
    // NOUVEAU : Catégorie Revenus
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
        'type' => $tc['type'], 'label' => $tc['name'], 'budget' => $tc['budget'], 'color' => $tempColors[$colorIdx++ % count($tempColors)], 'suggestions' => [], 'is_temp' => true, 'id' => $tc['id']
    ];
}
$categoriesConfig['Autres'] = ['type'=>'debit', 'label'=>'Autres / Imprévus', 'budget'=>$budget_autres, 'color'=>'#64748b', 'suggestions'=>['Restaurant', 'Cadeau']];
$categoriesConfig['LivretA'] = ['type'=>'debit', 'label'=>'Epargne', 'budget'=>0, 'color'=>'#8b5cf6', 'suggestions'=>['Virement']];

// ============================================================================
// 4. DONNÉES RÉELLES & IMPORT
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

// DÉPENSES EN BDD
$currentMonth = date('m'); $currentYear = date('Y');
$stmt = $pdo->prepare("SELECT * FROM pf_expenses WHERE MONTH(date_exp) = ? AND YEAR(date_exp) = ? ORDER BY date_exp DESC");
$stmt->execute([$currentMonth, $currentYear]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($totals[$cat])) $cat = 'Autres';
    
    // Si la dépense est un crédit (montant < 0), pour Income on l'ajoute au total "reçu"
    // Pour les autres catégories (réserves), ça augmente le budget
    if ($exp['amount'] < 0) {
        if ($cat === 'Income') {
            $totals[$cat] += abs($exp['amount']); // On somme les revenus
        } else {
            $categoriesConfig[$cat]['budget'] += abs($exp['amount']); // Réserve
        }
    } else {
        $totals[$cat] += $exp['amount'];
    }
    
    $expensesByCategory[$cat][] = $exp;
}

$globalSpent = array_sum($totals);
$globalBudget = array_sum(array_column($categoriesConfig, 'budget'));

function getDisplayLogic($spent, $bg, $type) {
    if ($type === 'credit') {
        // Pour Income et Réserves
        // Income : 2200 / 4000 (Reçu / Attendu)
        // Réserve : 50 / 400 (Restant / Initial) -> Cas particulier géré plus haut si besoin
        // Ici on simplifie :
        $remaining = $bg - $spent;
        $pct = ($bg > 0) ? max(0, min(100, ($spent / $bg) * 100)) : 0; // % Reçu
        $isOver = false;
        $text = number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €';
    } else {
        $pct = ($bg > 0) ? min(100, ($spent / $bg) * 100) : ($spent > 0 ? 100 : 0);
        $isOver = ($spent > $bg && $bg > 0);
        $text = number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €';
    }
    return ['pct' => $pct, 'isOver' => $isOver, 'text' => $text];
}
?>

<style>
    .cat-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; position: relative; }
    .btn-add-item { background: rgba(255, 255, 255, 0.6); color: inherit; border: 1px solid rgba(0, 0, 0, 0.1); width: 28px; height: 28px; border-radius: 50%; font-size: 18px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .btn-add-item:hover { background: white; transform: rotate(90deg) scale(1.1); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15); border-color: currentColor; }
    .pf-modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
    .pf-modal-content { background: white; width: 95%; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); padding: 30px; position: relative; }
    .progress-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
    .fade-pulse { animation: pulseText 2s infinite; }
    @keyframes pulseText { 0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; } }
</style>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h2 style="margin:0;">Suivi : <?= date('F Y') ?></h2>
                <div style="margin-top:4px; font-size:0.9rem; color:#64748b;">
                    Charges à venir : <strong class="fade-pulse" style="color:#f59e0b;"><?= number_format(ceil($reste_a_venir), 0, ',', ' ') ?> €</strong>
                </div>
                
                <div style="margin-top:4px; font-size:0.9rem; color:#64748b; display:flex; align-items:center; gap:5px;">
                    Solde au <?= date('d/m', strtotime($snapshot['date'])) ?> : 
                    <strong style="color:#1e293b;"><?= number_format($snapshot['amount'], 2, ',', ' ') ?> €</strong>
                    <button onclick="openSuiviModal('snapshotModal')" style="background:none; border:none; cursor:pointer; font-size:0.9rem; padding:0; filter:grayscale(1);">✏️</button>
                </div>

                <div style="margin-top:4px; font-size:0.9rem; color:#64748b; display:flex; align-items:center; gap:5px;">
                    Solde théorique au <?= date('d/m') ?> : 
                    <strong style="color:#3b82f6;"><?= number_format($solde_theorique, 2, ',', ' ') ?> €</strong>
                </div>
            </div>
            <div style="text-align:right;">
                <span style="font-size:0.85rem; color:#94a3b8;">Total Mouvements</span>
            </div>
        </div>

        <div class="progress-grid">
            <?php foreach ($categoriesConfig as $key => $conf): 
                $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']);
                // Couleur barre : Vert pour Income, Rouge si dépassement Debit
                $barCol = ($key === 'Income') ? '#10b981' : ($logic['isOver'] ? '#ef4444' : $conf['color']);
            ?>
            <div class="progress-card">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:5px;">
                    <span style="font-weight:600; color:<?= $conf['color'] ?>"><?= $conf['label'] ?></span>
                    <span><?= $logic['text'] ?></span>
                </div>
                <div style="background:#f1f5f9; height:8px; border-radius:4px; overflow:hidden;">
                    <div style="width:<?= $logic['pct'] ?>%; background:<?= $barCol ?>; height:100%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
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
                <div id="dynamicNewCats"></div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="margin:0;">Valider l'importation</h3>
                    <div>
                        <span id="missingCount" style="color:#ef4444; background:#fee2e2; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.85rem; display:none; margin-right:10px;"></span>
                        <button type="button" class="btn-icon-small" onclick="openSuiviModal('newCatModal')" title="Créer une catégorie" style="display:inline-flex; vertical-align:middle; width:auto; padding:4px 10px;">➕ Catégorie</button>
                    </div>
                </div>

                <div style="max-height:350px; overflow-y:auto; background:white; border:1px solid #eee; border-radius:8px;">
                    <table class="pf-table" style="margin:0;">
                        <thead style="position:sticky; top:0; z-index:10;">
                            <tr><th><input type="checkbox" onclick="toggleAll(this)" checked></th><th>Libellé</th><th>Montant</th><th>Catégorie</th></tr>
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
                                    <?= htmlspecialchars($row['label']) ?>
                                    <?php if($dup): ?><small style="color:#ef4444; font-weight:bold; margin-left:5px;">(déjà importé)</small><?php endif; ?>
                                </td>
                                <td style="font-weight:bold; color:<?= $isCrd ? '#10b981' : '#1e293b' ?>;">
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
                                        
                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input budget-item-select select-frais" style="display:none; flex:1; border-color:#ef4444; background:#fef2f2;">
                                            <option value="">-- Quelle Charge ? --</option>
                                            <?php foreach ($fixedChargesList as $fc): ?>
                                                <option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?> (<?= number_format($fc['amount'],0) ?>€)</option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input budget-item-select select-income" style="display:none; flex:1; border-color:#10b981; background:#f0fdf4;">
                                            <option value="">-- Quel Revenu ? --</option>
                                            <?php foreach ($incomeList as $inc): ?>
                                                <option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?> (<?= number_format($inc['amount'],0) ?>€)</option>
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
                            <a href="?tab=suivi&del_cat=<?= $conf['id'] ?>" onclick="return confirm('Supprimer cette catégorie ?')" style="color:#ef4444; text-decoration:none; font-size:0.8rem; margin-left:5px;">&times;</a>
                        <?php endif; ?>
                    </h3>
                    <div style="font-size:0.85rem; color:#64748b; font-weight:600; margin-top:2px;">
                        <?php $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']); echo $logic['text']; ?>
                    </div>
                </div>
                <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes($conf['label']) ?>')">＋</button>
            </div>

            <?php $barCol = $logic['isOver'] ? '#ef4444' : $conf['color']; ?>
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
                                    <span title="Lié à une charge fixe" style="font-size:0.7rem; cursor:help;">🔗</span>
                                <?php endif; ?>
                            </td>
                            <?php if($exp['amount'] < 0): ?>
                                <td style="padding:10px 15px; text-align:right; font-weight:600; color:#10b981;">+<?= number_format(abs($exp['amount']), 2) ?></td>
                            <?php else: ?>
                                <td style="padding:10px 15px; text-align:right; font-weight:600; color:#1e293b;">-<?= number_format($exp['amount'], 2) ?></td>
                            <?php endif; ?>
                            <td style="width:20px; padding-right:10px;"><a href="?tab=suivi&delete_expense=<?= $exp['id'] ?>" onclick="return confirm('x ?')" style="color:#ef4444; text-decoration:none; font-size:1.2rem;">&times;</a></td>
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
            <h3 style="margin:0;" id="modalTitle">Nouvelle dépense</h3>
            <button type="button" onclick="closeSuiviModal('manualExpenseModal')" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_expense">
            <input type="hidden" name="category" id="modalCatInput">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Date</label>
                <input type="date" name="date" class="pf-input" value="<?= date('Y-m-d') ?>" required>
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
                <select name="budget_item_id" id="fraisSelect" class="pf-input" style="border-color:#ef4444; background:#fef2f2;">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($fixedChargesList as $fc): ?>
                        <option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?> (<?= number_format($fc['amount'],2) ?>€)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="blockInputIncome" style="margin-bottom:15px; display:none;">
                <label class="pf-label" style="color:#10b981;">Choisir le revenu</label>
                <select name="budget_item_id" id="incomeSelect" class="pf-input" style="border-color:#10b981; background:#f0fdf4;">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($incomeList as $inc): ?>
                        <option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?> (<?= number_format($inc['amount'],2) ?>€)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Montant (€)</label>
                <input type="number" step="0.01" name="amount" class="pf-input" placeholder="0.00" required>
            </div>

            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;">Ajouter</button>
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
// --- UI ---
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function openSuiviModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeSuiviModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(event) { if (event.target.classList.contains('pf-modal')) event.target.style.display = 'none'; }

// --- SAISIE MANUELLE ---
const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;
function openAddModal(catKey, catLabel) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "Dépense : " + catLabel;
    document.getElementById('modalCatInput').value = catKey;
    
    const blockText = document.getElementById('blockInputText');
    const blockSelect = document.getElementById('blockInputSelect');
    const blockFrais = document.getElementById('blockInputFrais');
    const blockIncome = document.getElementById('blockInputIncome');
    
    const inputLabel = document.getElementById('modalLabelInput');
    const selectFrais = document.getElementById('fraisSelect');
    const selectIncome = document.getElementById('incomeSelect');

    // Reset visibility
    blockText.style.display = 'none';
    blockSelect.style.display = 'none';
    blockFrais.style.display = 'none';
    blockIncome.style.display = 'none';
    
    inputLabel.required = false;
    selectFrais.required = false;
    selectIncome.required = false;

    if (catKey === 'School') {
        blockSelect.style.display = 'block';
    } 
    else if (catKey === 'Frais') {
        blockText.style.display = 'block'; 
        blockFrais.style.display = 'block'; 
        selectFrais.required = true;
    }
    else if (catKey === 'Income') {
        blockText.style.display = 'block'; 
        blockIncome.style.display = 'block';
        selectIncome.required = true;
    }
    else {
        blockText.style.display = 'block';
        inputLabel.required = true;
        
        const list = document.getElementById('modalSuggestions');
        list.innerHTML = ''; inputLabel.value = '';
        if (suggestions[catKey]) suggestions[catKey].forEach(i => { const op = document.createElement('option'); op.value = i; list.appendChild(op); });
        setTimeout(() => inputLabel.focus(), 100);
    }
}

// --- IMPORT CSV ---
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
    
    // Reset
    fraisSelect.style.display = 'none';
    incomeSelect.style.display = 'none';
    fraisSelect.value = '';
    incomeSelect.value = '';

    if (select.value === 'Frais') {
        fraisSelect.style.display = 'block';
    } 
    else if (select.value === 'Income') {
        incomeSelect.style.display = 'block';
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
            row.style.background = isCredit ? '' : '#fff1f2';
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