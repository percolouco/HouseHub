<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. RÉCUPÉRATION DES BUDGETS PRÉVISIONNELS (Dynamique)
// ============================================================================

// Initialisation
$budget_fmcg = 0;
$budget_school = 0;
$budget_essence = 0;
$budget_frais = 0; // Charges fixes (Dénominateur calculé selon tes critères)

$total_income = 0;
$total_expenses = 0; // Sert au calcul du reste à vivre

// On récupère les colonnes nécessaires, y compris is_estimate
$stmt = $pdo->query("SELECT name, amount, type, category, is_estimate FROM pf_budget_items");
$budgetItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($budgetItems as $item) {
    $rawAmount = (float)$item['amount'];
    $amt = $rawAmount; // Montant lissé pour les totaux globaux
    
    $name = trim($item['name']);
    $type = $item['type']; // 'Mensuel' ou 'Annuel'
    $cat = $item['category']; // 'expense' ou 'income'
    $is_est = (int)$item['is_estimate']; // 0 ou 1

    // Gestion du lissage pour le calcul des totaux globaux (Reste à vivre)
    if ($type === 'Annuel') {
        $amt = $rawAmount / 12;
    }

    // 1. Calcul des totaux globaux (Entrées / Sorties)
    if ($cat === 'income') {
        $total_income += $amt;
    } else {
        $total_expenses += $amt;
    }

    // 2. Mappage des ESTIMATIONS spécifiques (FMCG, School, Essence)
    // Ces lignes ont généralement is_estimate = 1 dans ta base
    if ($name === 'Estimacio F&B & beauty') {
        $budget_fmcg = $amt;
    }
    elseif ($name === 'Estimacio escola') {
        $budget_school = $amt;
    }
    elseif ($name === 'Estimation gasolina') {
        $budget_essence = $amt;
    }

    // 3. CALCUL DU BUDGET CHARGES FIXES (Selon tes critères stricts)
    // - Pas une estimation
    // - Type Mensuel
    // - Catégorie Dépense
    if ($is_est === 0 && $type === 'Mensuel' && $cat === 'expense') {
        $budget_frais += $rawAmount;
    }
}

// Calcul du "Reste à vivre" pour la catégorie Autres
// Formule : Revenus Totaux - Toutes les dépenses lissées
$budget_autres = $total_income - $total_expenses;
if ($budget_autres < 0) $budget_autres = 0;


// ============================================================================
// 2. CONFIGURATION DES CATÉGORIES
// ============================================================================

$categoriesConfig = [
    'FMCG' => [
        'label' => 'Courses (FMCG)',
        'budget' => $budget_fmcg, 
        'color' => '#3b82f6', // Bleu
        'suggestions' => ['Action', 'Boucher', 'Boulangerie', 'Carrefour', 'Grand Frais', 'Lidl', 'Zooplus', 'Pharmacie', 'Decathlon']
    ],
    'Essence' => [
        'label' => 'Essence',
        'budget' => $budget_essence,
        'color' => '#f59e0b', // Orange
        'suggestions' => ['Audi', 'Polo']
    ],
    'School' => [
        'label' => 'École / Garde',
        'budget' => $budget_school, 
        'color' => '#10b981', // Vert
        'suggestions' => ['Pep', 'Pol']
    ],
    'Frais' => [
        'label' => 'Charges / Frais Fixes',
        'budget' => $budget_frais, // Calculé ci-dessus (Somme des fixes mensuels)
        'color' => '#ef4444', // Rouge
        'suggestions' => ['Prêt', 'Assurance', 'Banque', 'Netflix', 'Spotify', 'Verisure', 'Internet', 'Mobile', 'Eau', 'Elec', 'Cantine']
    ],
    'Autres' => [
        'label' => 'Autres / Imprévus',
        'budget' => $budget_autres, // Reste à vivre
        'color' => '#64748b', // Gris
        'suggestions' => ['Restaurant', 'Cadeau', 'Maison', 'Sortie', 'Santé non remboursée']
    ],
    'LivretA' => [
        'label' => 'Epargne / Livret A',
        'budget' => 0, 
        'color' => '#8b5cf6', // Violet
        'suggestions' => ['Virement mensuel']
    ]
];


// ============================================================================
// 3. LOGIQUE D'IMPORTATION CSV (Avec Mémoire)
// ============================================================================

$csvData = [];
$showPreview = false;

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // Chargement de la mémoire
    $rules = [];
    try {
        $rulesStmt = $pdo->query("SELECT keyword, category FROM pf_import_rules");
        $rules = $rulesStmt->fetchAll(PDO::FETCH_KEY_PAIR); 
    } catch (Exception $e) { /* Ignore */ }

    // Sauter l'en-tête
    fgetcsv($handle, 1000, ";"); 

    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        // Mapping (Date;Libelle;...;Debit)
        $rawDebit = $data[8] ?? ''; 
        
        if (!empty($rawDebit)) {
            $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawDebit)));
            
            $dateParts = explode('/', $data[0]); 
            if (count($dateParts) == 3) {
                $dateSql = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                $dateSql = date('Y-m-d');
            }

            $label = trim($data[1]);
            if(empty($label)) $label = trim($data[2]);

            // Intelligence
            $suggestedCat = '';
            foreach ($rules as $keyword => $cat) {
                if (stripos($label, $keyword) !== false) {
                    $suggestedCat = $cat;
                    break; 
                }
            }

            $csvData[] = [
                'date' => $dateSql,
                'label' => $label,
                'amount' => $amount,
                'cat' => $suggestedCat
            ];
        }
    }
    fclose($handle);
    $showPreview = true;
}


// ============================================================================
// 4. TRAITEMENT DES ACTIONS (Sauvegardes)
// ============================================================================

// A. SAUVEGARDE IMPORT CSV
if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount) VALUES (?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (!empty($line['cat']) && isset($line['import_check'])) {
                $stmtExp->execute([$line['date'], $line['cat'], $line['label'], $line['amount']]);
                $stmtRule->execute([$line['label'], $line['cat']]);
                $count++;
            }
        }
    }
    header("Location: ?tab=suivi&msg=imported_$count");
    exit;
}

// B. AJOUT MANUEL
if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $cat = $_POST['category'];
    $label = trim($_POST['label']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];

    if ($label && $amount > 0 && isset($categoriesConfig[$cat])) {
        $stmt = $pdo->prepare("INSERT INTO pf_expenses (date_exp, category, label, amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$date, $cat, $label, $amount]);
        header("Location: ?tab=suivi");
        exit;
    }
}

// C. SUPPRESSION
if (isset($_GET['delete_expense'])) {
    $id = (int)$_GET['delete_expense'];
    $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([$id]);
    header("Location: ?tab=suivi");
    exit;
}


// ============================================================================
// 5. RÉCUPÉRATION DES DONNÉES D'AFFICHAGE DU MOIS
// ============================================================================

$currentMonth = date('m');
$currentYear = date('Y');

$stmt = $pdo->prepare("
    SELECT * FROM pf_expenses 
    WHERE MONTH(date_exp) = ? AND YEAR(date_exp) = ? 
    ORDER BY date_exp DESC, id DESC
");
$stmt->execute([$currentMonth, $currentYear]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux par catégorie
$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (isset($totals[$cat])) {
        $totals[$cat] += $exp['amount'];
        $expensesByCategory[$cat][] = $exp;
    }
}

$globalSpent = array_sum($totals);
$globalBudget = array_sum(array_column($categoriesConfig, 'budget'));
?>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 style="margin:0;">Suivi Mensuel : <?= date('F Y') ?></h2>
            <div style="text-align:right;">
                <span style="display:block; font-size:0.9rem; color:#64748b;">Total Dépensé</span>
                <strong style="font-size:1.4rem; color:#1e293b;"><?= number_format($globalSpent, 2, ',', ' ') ?> €</strong>
                <span style="font-size:0.85rem; color:#94a3b8;"> / <?= number_format($globalBudget, 0, ',', ' ') ?> € Prévu</span>
            </div>
        </div>

        <div class="progress-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
            <?php foreach ($categoriesConfig as $key => $conf): 
                $spent = $totals[$key];
                $budget = $conf['budget'];
                
                if ($budget > 0) {
                    $percent = ($spent / $budget) * 100;
                } else {
                    $percent = ($spent > 0) ? 100 : 0; 
                }
                
                $barColor = ($spent > $budget && $budget > 0) ? '#ef4444' : $conf['color'];
                $cssPercent = min(100, $percent);
            ?>
            <div class="progress-card">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:5px;">
                    <span style="font-weight:600; color:<?= $conf['color'] ?>"><?= $conf['label'] ?></span>
                    <span><?= number_format($spent, 0, ',', ' ') ?> / <?= number_format($budget, 0, ',', ' ') ?></span>
                </div>
                <div style="background:#f1f5f9; height:8px; border-radius:4px; overflow:hidden;">
                    <div style="width:<?= $cssPercent ?>%; background:<?= $barColor ?>; height:100%; transition: width 0.5s;"></div>
                </div>
                <?php if ($spent > $budget && $budget > 0): ?>
                    <div style="font-size:0.75rem; color:#ef4444; text-align:right; margin-top:2px;">
                        +<?= number_format($spent - $budget, 0, ',', ' ') ?> €
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <button class="pf-btn" onclick="toggleDiv('addExpenseForm')">＋ Saisie Manuelle</button>
        <button class="pf-btn btn-secondary" onclick="toggleDiv('importCsvForm')">📂 Importer CSV</button>
    </div>

    <div id="addExpenseForm" style="display:none; margin-top:15px; margin-bottom:30px; background:white; padding:20px; border-radius:16px; border:1px solid #e2e8f0; animation: fadeIn 0.3s;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_expense">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; align-items:end;">
                <div><label class="pf-label">Date</label><input type="date" name="date" class="pf-input" value="<?= date('Y-m-d') ?>" required></div>
                <div>
                    <label class="pf-label">Catégorie</label>
                    <select name="category" class="pf-input" id="catSelector" onchange="updateDatalist()" required>
                        <?php foreach ($categoriesConfig as $key => $conf): ?><option value="<?= $key ?>"><?= $conf['label'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="pf-label">Titre</label>
                    <input type="text" name="label" class="pf-input" list="dynamic-suggestions" placeholder="Ex: Action..." required autocomplete="off">
                    <datalist id="dynamic-suggestions"></datalist>
                </div>
                <div><label class="pf-label">Montant (€)</label><input type="number" step="0.01" name="amount" class="pf-input" placeholder="0.00" required></div>
                <button type="submit" class="pf-btn">Valider</button>
            </div>
        </form>
    </div>

    <div id="importCsvForm" style="display:<?= $showPreview ? 'block' : 'none' ?>; margin-bottom:30px; background:white; padding:20px; border-radius:16px; border:1px solid #e2e8f0; animation: fadeIn 0.3s;">
        <?php if (!$showPreview): ?>
            <form method="POST" enctype="multipart/form-data">
                <h3 style="margin-top:0; font-size:1.1rem;">Importer un relevé bancaire (CSV)</h3>
                <p style="font-size:0.9rem; color:#64748b; margin-bottom:15px;">Le fichier doit utiliser des points-virgules (;) comme séparateur.</p>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="file" name="csv_file" accept=".csv" required class="pf-input" style="flex:1;">
                    <button type="submit" class="pf-btn">Prévisualiser</button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_import">
                <h3 style="margin-top:0;">Valider l'importation</h3>
                <p style="font-size:0.9rem; color:#64748b;">Vérifiez les catégories. L'application apprendra de vos choix.</p>
                <div style="max-height:400px; overflow-y:auto; border:1px solid #eee; border-radius:8px; margin-bottom:15px;">
                    <table class="pf-table" style="box-shadow:none; margin:0;">
                        <thead style="position:sticky; top:0; z-index:10;">
                            <tr>
                                <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)" checked></th>
                                <th>Date</th>
                                <th>Libellé</th>
                                <th>Montant</th>
                                <th>Catégorie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvData as $idx => $row): ?>
                            <tr style="<?= empty($row['cat']) ? 'background:#fff1f2;' : '' ?>">
                                <td>
                                    <input type="checkbox" name="lines[<?= $idx ?>][import_check]" value="1" checked>
                                    <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= $row['label'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                                </td>
                                <td><?= date('d/m', strtotime($row['date'])) ?></td>
                                <td style="font-weight:500; font-size:0.9rem;"><?= htmlspecialchars($row['label']) ?></td>
                                <td style="font-weight:bold;">-<?= number_format($row['amount'], 2) ?> €</td>
                                <td>
                                    <select name="lines[<?= $idx ?>][cat]" class="pf-input" style="padding:4px; font-size:0.9rem;">
                                        <option value="" <?= empty($row['cat'])?'selected':'' ?>>-- À définir --</option>
                                        <?php foreach ($categoriesConfig as $k => $c): ?>
                                            <option value="<?= $k ?>" <?= ($row['cat'] === $k) ? 'selected' : '' ?>><?= $c['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px; text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                    <a href="?tab=suivi" class="pf-btn btn-secondary">Annuler</a>
                    <button type="submit" class="pf-btn">Importer la sélection</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="categories-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap:24px;">
        <?php foreach ($categoriesConfig as $key => $conf): ?>
        <div class="cat-card" style="background:white; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden;">
            <div style="background:<?= $conf['color'] ?>15; padding:15px; border-bottom:1px solid <?= $conf['color'] ?>30; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1rem; color:<?= $conf['color'] ?>;"><?= $conf['label'] ?></h3>
                <span style="font-weight:bold; color:<?= $conf['color'] ?>;"><?= number_format($totals[$key], 2, ',', ' ') ?> €</span>
            </div>
            <div style="max-height:300px; overflow-y:auto;">
                <?php if (empty($expensesByCategory[$key])): ?>
                    <p style="padding:20px; text-align:center; color:#94a3b8; font-style:italic;">Aucune dépense.</p>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                        <?php foreach ($expensesByCategory[$key] as $exp): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px; color:#64748b; font-size:0.8rem; width:50px;"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                            <td style="padding:10px; font-weight:500;"><?= htmlspecialchars($exp['label']) ?></td>
                            <td style="padding:10px; text-align:right; font-weight:600;">- <?= number_format($exp['amount'], 2) ?> €</td>
                            <td style="width:30px; text-align:center;"><a href="?tab=suivi&delete_expense=<?= $exp['id'] ?>" onclick="return confirm('Supprimer ?')" style="color:#ef4444; text-decoration:none;">&times;</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function toggleAll(src) { document.querySelectorAll('input[name*="[import_check]"]').forEach(c => c.checked = src.checked); }
const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;
function updateDatalist() {
    const cat = document.getElementById('catSelector').value;
    const list = document.getElementById('dynamic-suggestions');
    const input = document.querySelector('input[name="label"]');
    list.innerHTML = ''; input.value = ''; 
    if (suggestions[cat]) suggestions[cat].forEach(i => { const op = document.createElement('option'); op.value = i; list.appendChild(op); });
}
document.addEventListener('DOMContentLoaded', updateDatalist);
</script>