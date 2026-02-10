<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. GESTION DES ACTIONS (POST/GET)
// ============================================================================

$currentMonthKey = date('m-Y');

// A. AJOUT CATÉGORIE TEMPORAIRE
if (isset($_POST['action']) && $_POST['action'] === 'add_temp_cat') {
    $name = trim($_POST['cat_name']);
    $budget = floatval($_POST['cat_budget']);
    if ($name && $budget >= 0) {
        $stmt = $pdo->prepare("INSERT INTO pf_monthly_categories (month_year, name, budget) VALUES (?, ?, ?)");
        $stmt->execute([$currentMonthKey, $name, $budget]);
        header("Location: ?tab=suivi"); exit;
    }
}

// B. SUPPRESSION CATÉGORIE TEMPORAIRE
if (isset($_GET['del_cat'])) {
    $id = (int)$_GET['del_cat'];
    $pdo->prepare("DELETE FROM pf_monthly_categories WHERE id = ?")->execute([$id]);
    header("Location: ?tab=suivi"); exit;
}

// C. SAUVEGARDE IMPORT CSV
if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref) VALUES (?, ?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (!empty($line['cat']) && isset($line['import_check'])) {
                try {
                    $stmtExp->execute([$line['date'], $line['cat'], $line['label'], $line['amount'], $line['ref']]);
                    $stmtRule->execute([$line['label'], $line['cat']]);
                    $count++;
                } catch (Exception $e) { continue; }
            }
        }
    }
    header("Location: ?tab=suivi&msg=imported_$count"); exit;
}

// D. AJOUT DÉPENSE MANUELLE
if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $cat = $_POST['category']; 
    $amount = floatval($_POST['amount']); 
    $date = $_POST['date'];
    
    // Logique pour le label : Soit liste fermée (School), soit texte libre
    if ($cat === 'School' && !empty($_POST['label_select'])) {
        $label = trim($_POST['label_select']);
    } else {
        $label = trim($_POST['label']);
    }

    if ($label && $amount > 0) {
        $uniqueRef = "MANUAL_" . uniqid();
        $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount, import_ref) VALUES (?, ?, ?, ?, ?)")
            ->execute([$date, $cat, $label, $amount, $uniqueRef]);
        header("Location: ?tab=suivi"); exit;
    }
}

// E. SUPPRESSION DÉPENSE
if (isset($_GET['delete_expense'])) {
    $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([(int)$_GET['delete_expense']]);
    header("Location: ?tab=suivi"); exit;
}

// ============================================================================
// 2. CALCUL DES BUDGETS & INDICATEURS
// ============================================================================

$budget_fmcg = 0; $budget_school = 0; $budget_essence = 0; $budget_frais = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir = 0; // Somme des frais futurs
$today_day = (int)date('j'); // Jour du mois (1 à 31)

// 2.1 Récupération Budget Fixe
$stmt = $pdo->query("SELECT name, amount, type, category, is_estimate, payment_day FROM pf_budget_items");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rawAmount = (float)$item['amount'];
    $amt = ($item['type'] === 'Annuel') ? $rawAmount / 12 : $rawAmount;
    $name = trim($item['name']);
    $pDay = (int)$item['payment_day'];
    
    if ($item['category'] === 'income') {
        $total_income += $amt;
    } else {
        $total_expenses_prevues += $amt;
        
        // --- CALCUL DU "RESTE À VENIR" ---
        // Conditions : Expense + Mensuel + (Jour > Aujourd'hui OU C'est l'école)
        if ($item['category'] === 'expense' && $item['type'] === 'Mensuel') {
            // Si c'est l'école, on l'ajoute toujours (selon ta demande)
            if ($name === 'Estimacio escola') {
                $reste_a_venir += $rawAmount;
            }
            // Sinon, si c'est une autre dépense avec une date future
            elseif ($pDay > $today_day) {
                $reste_a_venir += $rawAmount;
            }
        }

        // --- MAPPING CATÉGORIES ---
        if ($name === 'Estimacio F&B & beauty') $budget_fmcg = $amt;
        elseif ($name === 'Estimacio escola') $budget_school = $amt;
        elseif ($name === 'Estimation gasolina') $budget_essence = $amt;
        elseif ((int)$item['is_estimate'] === 0 && $item['type'] === 'Mensuel' && $item['category'] === 'expense') {
            $budget_frais += $rawAmount;
        }
    }
}

// 2.2 Récupération Catégories Temporaires
$tempCats = [];
$total_temp_budget = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM pf_monthly_categories WHERE month_year = ?");
    $stmt->execute([$currentMonthKey]);
    $tempCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($tempCats as $tc) $total_temp_budget += $tc['budget'];
} catch (Exception $e) {}

// 2.3 Calcul Reste à vivre
$budget_autres = $total_income - ($total_expenses_prevues + $total_temp_budget);
if ($budget_autres < 0) $budget_autres = 0;

// ============================================================================
// 3. CONFIGURATION CATÉGORIES
// ============================================================================

$categoriesConfig = [
    'FMCG' => ['label' => 'Courses (FMCG)', 'budget' => $budget_fmcg, 'color' => '#3b82f6', 'suggestions' => ['Action', 'Carrefour', 'Lidl']],
    'Essence' => ['label' => 'Essence', 'budget' => $budget_essence, 'color' => '#f59e0b', 'suggestions' => ['Audi', 'Polo']],
    'School' => ['label' => 'École / Garde', 'budget' => $budget_school, 'color' => '#10b981', 'suggestions' => []], // Liste fermée gérée en JS
    'Frais' => ['label' => 'Charges Fixes', 'budget' => $budget_frais, 'color' => '#ef4444', 'suggestions' => ['Netflix', 'Assurance', 'Prêt']],
];

// Couleurs temporaires
$tempColors = ['#ec4899', '#06b6d4', '#84cc16', '#d946ef', '#f97316'];
$colorIdx = 0;

foreach ($tempCats as $tc) {
    $catKey = 'TEMP_' . $tc['id'];
    $categoriesConfig[$catKey] = [
        'label' => $tc['name'],
        'budget' => $tc['budget'],
        'color' => $tempColors[$colorIdx % count($tempColors)],
        'suggestions' => [],
        'is_temp' => true,
        'id' => $tc['id']
    ];
    $colorIdx++;
}

$categoriesConfig['Autres'] = ['label' => 'Autres / Imprévus', 'budget' => $budget_autres, 'color' => '#64748b', 'suggestions' => ['Restaurant', 'Cadeau']];
$categoriesConfig['LivretA'] = ['label' => 'Epargne', 'budget' => 0, 'color' => '#8b5cf6', 'suggestions' => ['Virement']];

// ============================================================================
// 4. DONNÉES & IMPORT
// ============================================================================

// CSV PREVIEW
$csvData = [];
$showPreview = false;
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $rules = []; try { $rules = $pdo->query("SELECT keyword, category FROM pf_import_rules")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
    $existingRefs = []; try { $existingRefs = $pdo->query("SELECT import_ref FROM pf_expenses WHERE import_ref IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e){}
    fgetcsv($handle, 1000, ";"); 
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        $rawDebit = $data[8] ?? ''; 
        if (!empty($rawDebit)) {
            $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawDebit)));
            $dateParts = explode('/', $data[0]); 
            $dateSql = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : date('Y-m-d');
            $label = trim($data[1]) ?: trim($data[2]);
            $refCSV = trim($data[3]);
            $uniqueKey = !empty($refCSV) ? "REF_".$refCSV : "HASH_".md5($dateSql.$label.number_format($amount, 2));
            $isDuplicate = in_array($uniqueKey, $existingRefs);
            $suggestedCat = '';
            foreach ($rules as $kw => $c) { if (stripos($label, $kw) !== false) { $suggestedCat = $c; break; } }
            $csvData[] = ['date'=>$dateSql, 'label'=>$label, 'amount'=>$amount, 'cat'=>$suggestedCat, 'ref'=>$uniqueKey, 'is_duplicate'=>$isDuplicate];
        }
    }
    fclose($handle);
    $showPreview = true;
}

// DÉPENSES RÉELLES
$currentMonth = date('m'); $currentYear = date('Y');
$stmt = $pdo->prepare("SELECT * FROM pf_expenses WHERE MONTH(date_exp) = ? AND YEAR(date_exp) = ? ORDER BY date_exp DESC");
$stmt->execute([$currentMonth, $currentYear]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($totals[$cat])) $cat = 'Autres';
    $totals[$cat] += $exp['amount'];
    $expensesByCategory[$cat][] = $exp;
}

$globalSpent = array_sum($totals);
$globalBudget = array_sum(array_column($categoriesConfig, 'budget'));
?>

<style>
    .cat-card {
        background: white; border-radius: 16px; border: 1px solid #e2e8f0;
        display: flex; flex-direction: column; overflow: hidden; position: relative;
    }
    .btn-add-item {
        background: rgba(255, 255, 255, 0.6); color: inherit; border: 1px solid rgba(0, 0, 0, 0.1);
        width: 28px; height: 28px; border-radius: 50%; font-size: 18px; line-height: 1; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .btn-add-item:hover {
        background: white; transform: rotate(90deg) scale(1.1);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15); border-color: currentColor;
    }
    .pf-modal {
        display: none; position: fixed; inset: 0; z-index: 9999;
        background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
        align-items: center; justify-content: center;
    }
    .pf-modal-content {
        background: white; width: 95%; border-radius: 20px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); padding: 30px; position: relative;
    }
    .progress-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;
    }
    
    /* Animation pour le reste à venir */
    .fade-pulse { animation: pulseText 2s infinite; }
    @keyframes pulseText {
        0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; }
    }
</style>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h2 style="margin:0;">Suivi : <?= date('F Y') ?></h2>
                <div style="margin-top:4px; font-size:0.9rem; color:#64748b;">
                    Charges à venir ce mois : <strong class="fade-pulse" style="color:#f59e0b;"><?= number_format($reste_a_venir, 0, ',', ' ') ?> €</strong>
                </div>
            </div>
            <div style="text-align:right;">
                <strong style="font-size:1.4rem; color:#1e293b;"><?= number_format(ceil($globalSpent), 0, ',', ' ') ?> €</strong>
                <span style="font-size:0.85rem; color:#94a3b8;"> / <?= number_format(ceil($globalBudget), 0, ',', ' ') ?> €</span>
            </div>
        </div>

        <div class="progress-grid">
            <?php foreach ($categoriesConfig as $key => $conf): 
                $spent = $totals[$key]; $bg = $conf['budget'];
                $pct = ($bg > 0) ? min(100, ($spent/$bg)*100) : ($spent>0?100:0);
                $col = ($spent > $bg && $bg > 0) ? '#ef4444' : $conf['color'];
            ?>
            <div class="progress-card">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:5px;">
                    <span style="font-weight:600; color:<?= $conf['color'] ?>"><?= $conf['label'] ?></span>
                    <span><?= number_format(ceil($spent), 0, ',', ' ') ?> / <?= number_format(ceil($bg), 0, ',', ' ') ?> €</span>
                </div>
                <div style="background:#f1f5f9; height:8px; border-radius:4px; overflow:hidden;">
                    <div style="width:<?= $pct ?>%; background:<?= $col ?>; height:100%;"></div>
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
            <div style="flex:1; min-width:200px;">
                <label class="pf-label">Nom</label>
                <input type="text" name="cat_name" class="pf-input" required>
            </div>
            <div style="width:120px;">
                <label class="pf-label">Budget (€)</label>
                <input type="number" name="cat_budget" class="pf-input" step="1" required>
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
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <h3>Valider l'importation</h3>
                    <span id="missingCount" style="color:red; font-weight:bold; display:none;"></span>
                </div>
                <div style="max-height:300px; overflow-y:auto; background:white; border:1px solid #eee;">
                    <table class="pf-table" style="margin:0;">
                        <thead><tr><th><input type="checkbox" onclick="toggleAll(this)" checked></th><th>Libellé</th><th>Montant</th><th>Catégorie</th></tr></thead>
                        <tbody>
                            <?php foreach ($csvData as $idx => $row): 
                                $dup = $row['is_duplicate']; $dis = $dup?'disabled':''; ?>
                            <tr style="<?= $dup?'opacity:0.5':(empty($row['cat'])?'background:#fff1f2':'') ?>">
                                <td><input type="checkbox" class="line-checkbox" name="lines[<?= $idx ?>][import_check]" value="1" <?= $dup?'':'checked' ?> <?= $dis ?> onchange="checkValidation()">
                                    <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= $row['label'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][ref]" value="<?= $row['ref'] ?>">
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['label']) ?>
                                    <?php if($dup): ?><span style="color:#ef4444; font-size:0.85em; font-weight:bold; margin-left:5px;">(déjà importé)</span><?php endif; ?>
                                </td>
                                <td><?= number_format($row['amount'],2) ?> €</td>
                                <td>
                                    <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="checkValidation()" <?= $dis ?>>
                                        <option value="">-- ? --</option>
                                        <?php foreach ($categoriesConfig as $k => $c): ?>
                                            <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= $c['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:10px; text-align:right;">
                    <a href="?tab=suivi" class="pf-btn btn-secondary">Annuler</a>
                    <button type="submit" id="btnImport" class="pf-btn" style="width:auto;">Importer</button>
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
                        <?= number_format(ceil($totals[$key]), 0, ',', ' ') ?> / <?= number_format(ceil($conf['budget']), 0, ',', ' ') ?> €
                    </div>
                </div>
                <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes($conf['label']) ?>')">＋</button>
            </div>

            <?php 
                $bg = $conf['budget']; 
                $pct = ($bg > 0) ? min(100, ($totals[$key]/$bg)*100) : ($totals[$key]>0?100:0);
                $barCol = ($totals[$key] > $bg && $bg > 0) ? '#ef4444' : $conf['color'];
            ?>
            <div style="background:#f1f5f9; height:4px; width:100%;">
                <div style="width:<?= $pct ?>%; background:<?= $barCol ?>; height:100%;"></div>
            </div>

            <div style="flex:1; max-height:300px; overflow-y:auto; padding:0;">
                <?php if (empty($expensesByCategory[$key])): ?>
                    <div style="padding:20px; text-align:center; color:#cbd5e1; font-style:italic; font-size:0.85rem;">Aucune dépense</div>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <?php foreach ($expensesByCategory[$key] as $exp): ?>
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:10px 15px; color:#94a3b8;"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                            <td style="padding:10px 5px; font-weight:500;"><?= htmlspecialchars($exp['label']) ?></td>
                            <td style="padding:10px 15px; text-align:right;">-<?= number_format($exp['amount'], 2) ?></td>
                            <td style="width:20px; padding-right:10px;"><a href="?tab=suivi&delete_expense=<?= $exp['id'] ?>" onclick="return confirm('x ?')" style="color:#ef4444; text-decoration:none;">&times;</a></td>
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
            <button type="button" onclick="closeModal()" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
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
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Montant (€)</label>
                <input type="number" step="0.01" name="amount" class="pf-input" placeholder="0.00" required>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="button" onclick="closeModal()" class="pf-btn btn-secondary" style="margin-right:10px;">Annuler</button>
                <button type="submit" class="pf-btn">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<script>
// UI Utils
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }

// MODALE LOGIQUE
const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;

function openAddModal(catKey, catLabel) {
    const modal = document.getElementById('manualExpenseModal');
    if(modal) {
        modal.style.display = 'flex';
        document.getElementById('modalTitle').innerText = "Dépense : " + catLabel;
        document.getElementById('modalCatInput').value = catKey;
        
        // GESTION DU CHAMP TITRE (Input vs Select pour School)
        const blockText = document.getElementById('blockInputText');
        const blockSelect = document.getElementById('blockInputSelect');
        const inputLabel = document.getElementById('modalLabelInput');

        if (catKey === 'School') {
            // Mode Select Fermé
            blockText.style.display = 'none';
            blockSelect.style.display = 'block';
            inputLabel.required = false; // On désactive le required du text
        } else {
            // Mode Texte Libre
            blockText.style.display = 'block';
            blockSelect.style.display = 'none';
            inputLabel.required = true;
            
            // Chargement suggestions
            const list = document.getElementById('modalSuggestions');
            list.innerHTML = '';
            inputLabel.value = '';
            if (suggestions[catKey]) {
                suggestions[catKey].forEach(item => {
                    const op = document.createElement('option');
                    op.value = item;
                    list.appendChild(op);
                });
            }
            setTimeout(() => inputLabel.focus(), 100);
        }
    }
}

function closeModal() {
    const modal = document.getElementById('manualExpenseModal');
    if(modal) modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('manualExpenseModal');
    if (event.target == modal) { closeModal(); }
}

// IMPORT CSV
function toggleAll(src) { document.querySelectorAll('.line-checkbox:not([disabled])').forEach(c => c.checked = src.checked); checkValidation(); }
function checkValidation() {
    const cbs = document.querySelectorAll('.line-checkbox:checked');
    let miss = 0;
    cbs.forEach(cb => { if(cb.closest('tr').querySelector('.line-select').value === "") miss++; });
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
if(document.getElementById('formMapping')) checkValidation();
</script>