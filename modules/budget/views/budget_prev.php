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

// Init si vide
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

// On affiche 6 mois (Timeline inversée)
$months = [];
for ($i = 0; $i < 6; $i++) {
    $months[] = date('Y-m-01', strtotime("-$i months", $focusTs));
}

// Liens navigation
$prevMonthLink = date('Y-m-01', strtotime("-1 month", $focusTs));
$nextMonthLink = date('Y-m-01', strtotime("+1 month", $focusTs));

// 4. Récupération Valeurs Répartition
$inQuery = implode(',', array_fill(0, count($months), '?'));
$stmt = $pdo->prepare("SELECT * FROM pf_alloc_values WHERE month_date IN ($inQuery)");
$stmt->execute($months);

$allocs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allocs[$row['month_date']][$row['cat_id']] = $row;
}
?>

<div class="prev-container">

    <div>
        <div class="prev-section-header">
            <h2>Revenus <?= $currentYear ?></h2>
        </div>
        
        <table class="prev-salary-table">
            <thead>
                <tr>
                    <th>Personne</th>
                    <th>Salaire</th>
                    <th>Mensualité<br><small style="font-weight:normal; text-transform:none;">(Cpt Commun)</small></th>
                    <th>Frais Func.</th>
                    <th>Eco Perso</th>
                    <th style="background:#f0f9ff; color:#0284c7;">Eco Family<br><small style="font-weight:normal; text-transform:none;">(Dispo)</small></th>
                    <th>Restant Perso</th>
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
                    <td id="restant_<?= $p ?>" style="font-weight:bold; color:var(--text-muted); text-align:center;">
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
                <h2>Répartition Budgétaire</h2>
                <div class="nav-group">
                    <a href="?tab=budget_prev&focus_date=<?= $prevMonthLink ?>" class="btn-nav">◀</a>
                    <a href="?tab=budget_prev&focus_date=<?= date('Y-m-01') ?>" class="btn-nav">Auj.</a>
                    <a href="?tab=budget_prev&focus_date=<?= $nextMonthLink ?>" class="btn-nav">▶</a>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="pf-btn btn-secondary" onclick="duplicateMonth()">
                    🔁 +1 Mois
                </button>
                
                <button class="pf-btn" onclick="document.getElementById('addCatModal').style.display='flex'">
                    ＋ Nouvelle Ligne
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
                                <?= date('F Y', strtotime($month)) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="col-sticky header-cell">
                            
                        </th>
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
                            <td class="txt-global" style="border-left:2px solid #e2e8f0;" id="total_global_<?= $m ?>">0</td>
                            <td class="txt-alex" id="total_alex_<?= $m ?>">0</td>
                            <td class="txt-laia" id="total_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <tr class="row-restant">
                        <td class="col-sticky" style="text-align:right !important;">Restant (Eco Family)</td>
                        <?php foreach ($months as $m): ?>
                            <td style="border-left:2px solid #cbd5e1; background:#e2e8f0;">-</td>
                            <td class="val-ok" id="restant_alloc_alex_<?= $m ?>">0</td>
                            <td class="val-ok" id="restant_alloc_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($cats as $cat): 
                    // Détection des lignes indicatives
                    $isIndicative = ($cat['name'] === 'Eco Alex' || $cat['name'] === 'Eco Laia');
                    $rowClass = $isIndicative ? 'row-indicative' : '';
                    $inputClass = $isIndicative ? 'ignore-calc' : ''; // Classe pour le JS
                    $rowStyle = $isIndicative ? 'background:#f8fafc; color:#94a3b8;' : '';
                ?>
                <tr class="<?= $rowClass ?>" style="<?= $rowStyle ?>">
                    <td class="col-sticky" style="position:relative; <?= $isIndicative ? 'opacity:0.8;' : '' ?>">
                        <div style="font-weight:600; color:<?= $isIndicative ? '#64748b' : 'var(--text-main)' ?>;">
                            <?= htmlspecialchars($cat['name']) ?> 
                            <?php if($isIndicative): ?><span style="font-size:0.7rem; border:1px solid #cbd5e1; border-radius:4px; padding:0 4px; margin-left:5px;">Info</span><?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-style:italic; text-align:right;">
                            <?= htmlspecialchars($cat['target']) ?>
                        </div>
                        
                        <div class="row-actions">
                            <button type="button" 
                                    class="btn-icon-action edit" 
                                    title="Modifier"
                                    data-id="<?= $cat['id'] ?>"
                                    data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                    data-target="<?= htmlspecialchars($cat['target']) ?>"
                                    onclick="openEditModal(this)">
                                ✎
                            </button>

                            <a href="?tab=budget_prev&id=<?= $cat['id'] ?>&action=delete_category" 
                               onclick="return confirm('Supprimer cette ligne et tout son historique ?')" 
                               class="btn-icon-action delete" title="Supprimer">
                               &times;
                            </a>
                        </div>
                    </td>
                    <?php foreach ($months as $m): 
                        $val = $allocs[$m][$cat['id']] ?? ['amount_alex'=>0, 'amount_laia'=>0];
                    ?>
                        <td class="txt-global" style="border-left:2px solid #e2e8f0; <?= $isIndicative?'color:#94a3b8; font-weight:normal;':'' ?>" id="g_<?= $m ?>_<?= $cat['id'] ?>">0</td>
                        
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
<?php
    $focusMonth = $months[0]; 
    
    // On initialise toutes les cibles possibles pour avoir les lignes prêtes
    // Note : On utilise md5() sur la cible pour créer des ID compatibles HTML (sans espaces/points)
    $targetsOrder = ['vers commune', 'vers L.Pol', 'vers L.Pep', 'vers L.Perso'];
    
    // On récupère toutes les cibles uniques utilisées dans les catégories + celles par défaut
    $allTargets = $targetsOrder;
    foreach($cats as $c) {
        if(!empty($c['target']) && !in_array($c['target'], $allTargets)) $allTargets[] = $c['target'];
    }
    $allTargets = array_unique($allTargets);
    ?>

    <div class="recap-wrapper">
        <div class="recap-card">
            <div class="recap-header">
                Virements à effectuer - <?= date('F Y', strtotime($focusMonth)) ?>
            </div>
            
            <table class="recap-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">Destination</th>
                        <th class="col-alex">Alex</th>
                        <th class="col-laia">Laia</th>
                        <th class="col-global">Global</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allTargets as $target): 
                        $tId = md5($target); // ID unique pour le JS
                    ?>
                    <tr id="row_summary_<?= $tId ?>">
                        <td><?= htmlspecialchars($target) ?></td>
                        <td class="col-alex" id="sum_alex_<?= $tId ?>">0 €</td>
                        <td class="col-laia" id="sum_laia_<?= $tId ?>">0 €</td>
                        <td class="col-global" id="sum_global_<?= $tId ?>">0 €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="row-grand-total">
                        <td>GRAND TOTAL</td>
                        <td class="col-alex" id="grand_total_alex">0 €</td>
                        <td class="col-laia" id="grand_total_laia">0 €</td>
                        <td class="col-global" id="grand_total_global">0 €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<div id="addCatModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:400px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;">Nouvelle Ligne de Budget</h3>
            <button onclick="document.getElementById('addCatModal').style.display='none'" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Nom (ex: Vacances)</label>
                <input type="text" name="name" class="pf-input" required>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="pf-label">Cible (Destination)</label>
                <select name="target" class="pf-input" required>
                    <option value="" disabled selected>-- Choisir --</option>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addCatModal').style.display='none'" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<div id="editCatModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;">Modifier la ligne</h3>
            <button onclick="document.getElementById('editCatModal').style.display='none'" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            
            <div class="form-group">
                <label class="pf-label">Nom</label>
                <input type="text" name="name" id="edit_cat_name" class="pf-input" required>
            </div>
            <div class="form-group">
                <label class="pf-label">Cible (Destination)</label>
                <select name="target" id="edit_cat_target" class="pf-input" required>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editCatModal').style.display='none'" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Fonction pour ouvrir la modale et pré-remplir les valeurs
function openEditModal(btn) {
    // 1. Récupération des données depuis le bouton
    // getAttribute est plus sûr pour éviter les bugs si des données sont manquantes
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const target = btn.getAttribute('data-target');

    // 2. Remplissage du formulaire
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    
    // Pour le SELECT, définir la .value sélectionne automatiquement la bonne option
    // Si la valeur actuelle en base n'existe pas dans la liste, ça sélectionnera la 1ère par défaut
    document.getElementById('edit_cat_target').value = target;
    
    // 3. Affichage
    document.getElementById('editCatModal').style.display = 'flex';
}
</script>
</div>

<script>
const currentYear = <?= $currentYear ?>;
const months = <?= json_encode($months) ?>;

// --- SALAIRES ---
function updateSalary(person, input) {
    const row = input.closest('tr');
    const salary = parseFloat(row.querySelector('[data-field="salary"]').value) || 0;
    const mens = parseFloat(row.querySelector('[data-field="mensualite"]').value) || 0;
    const frais = parseFloat(row.querySelector('[data-field="frais_func"]').value) || 0;
    const ecoP = parseFloat(row.querySelector('[data-field="eco_perso"]').value) || 0;
    const ecoF = parseFloat(row.querySelector('[data-field="eco_family"]').value) || 0;

    const restant = salary - (mens + frais + ecoP + ecoF);
    // Affichage sans décimale
    document.getElementById('restant_' + person).innerText = Math.round(restant).toLocaleString('fr-FR') + ' €';

    saveData('update_salary_config', { year: currentYear, person: person, field: input.dataset.field, value: input.value });
    recalcAllAllocations();
}

// --- ALLOCATIONS ---
function updateAlloc(month, catId, personField, input) {
    saveData('update_allocation', { month_date: month, cat_id: catId, person: personField, value: input.value || 0 });
    recalcAllAllocations();
}

// Fonction utilitaire : Retourne le mois en MAJUSCULES (ex: "FÉVRIER")
function getMonthName(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { month: 'long' }).toUpperCase();
}

function duplicateMonth() {
    // months[0] = Le mois affiché le plus à gauche (Cible)
    // months[1] = Le mois juste à droite (Source)
    const targetDateStr = months[0];
    const sourceDateStr = months[1];

    if (!sourceDateStr) {
        alert("Impossible : pas d'historique disponible pour copier.");
        return;
    }

    const targetName = getMonthName(targetDateStr); // ex: MARS
    const sourceName = getMonthName(sourceDateStr); // ex: FÉVRIER

    // On utilise des tirets ou étoiles pour attirer l'attention à défaut de gras
    const message = `Voulez-vous copier les valeurs de  ${sourceName}  vers  ${targetName}  ?\n\n` +
                    `Cela écrasera toutes les valeurs présentes sur ${targetName}.`;

    if(!confirm(message)) return;
    
    // Le reste du code de copie reste identique...
    document.querySelectorAll('.inp-alex-' + sourceDateStr).forEach(sourceInput => {
        const catIdMatch = sourceInput.getAttribute('onchange').match(/, (\d+),/);
        if(!catIdMatch) return;
        const catId = catIdMatch[1];
        const row = sourceInput.closest('tr');

        // Copie Alex
        const valAlex = sourceInput.value;
        const targetAlex = row.querySelector('.inp-alex-' + targetDateStr);
        if(targetAlex) { 
            targetAlex.value = valAlex; 
            updateAlloc(targetDateStr, catId, 'amount_alex', targetAlex); 
        }

        // Copie Laia
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

        // Boucle sur colonne Alex
        document.querySelectorAll('.inp-alex-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            
            // 1. On ajoute au TOTAL COLONNE seulement si ce n'est PAS une ligne indicative
            if (!inp.classList.contains('ignore-calc')) {
                sumAlex += val;
            }
            
            // 2. Calcul du GLOBAL LIGNE (On le fait toujours, même pour les indicatifs)
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

        // Boucle sur colonne Laia (Juste pour le total colonne)
        document.querySelectorAll('.inp-laia-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            if (!inp.classList.contains('ignore-calc')) {
                sumLaia += val;
            }
        });

        // Totaux
        document.getElementById('total_alex_' + m).innerText = Math.round(sumAlex) + ' €';
        document.getElementById('total_laia_' + m).innerText = Math.round(sumLaia) + ' €';
        document.getElementById('total_global_' + m).innerText = Math.round(sumAlex + sumLaia) + ' €';

        // Restants
        const restAlex = budgetAlex - sumAlex;
        const restLaia = budgetLaia - sumLaia;

        const elRestAlex = document.getElementById('restant_alloc_alex_' + m);
        const elRestLaia = document.getElementById('restant_alloc_laia_' + m);

        elRestAlex.innerText = Math.round(restAlex) + ' €';
        elRestAlex.className = 'val-' + (restAlex >= 0 ? 'ok' : 'ko');

        elRestLaia.innerText = Math.round(restLaia) + ' €';
        elRestLaia.className = 'val-' + (restLaia >= 0 ? 'ok' : 'ko');
    });
    updateSummaryTable();
}

function updateSummaryTable() {
    // 1. On identifie le mois focus (colonne de gauche)
    const focusMonth = months[0];
    
    // 2. Objet pour stocker les sommes par cible
    // Structure : { 'md5_target': { alex: 0, laia: 0 } }
    const sums = {};

    // 3. On parcourt les inputs ALEX du mois focus
    document.querySelectorAll('.inp-alex-' + focusMonth).forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        const target = inp.getAttribute('data-target'); // Récupéré de l'étape 1
        
        if (target) { }
    });
    
    // On reset les totaux
    let grandTotalAlex = 0;
    let grandTotalLaia = 0;    
    const dataByTarget = {};

    // Calcul Alex
    document.querySelectorAll('.inp-alex-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].alex += (parseFloat(inp.value) || 0);
        }
    });

    // Calcul Laia
    document.querySelectorAll('.inp-laia-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].laia += (parseFloat(inp.value) || 0);
        }
    });

    // Mise à jour du tableau
    // On parcourt toutes les lignes du tbody du récap
    const tbody = document.querySelector('.recap-table tbody');
    if(tbody) {
        Array.from(tbody.rows).forEach(row => {
            const targetName = row.cells[0].innerText.trim(); // "vers L.Pol"
            
            const alexSum = dataByTarget[targetName] ? dataByTarget[targetName].alex : 0;
            const laiaSum = dataByTarget[targetName] ? dataByTarget[targetName].laia : 0;
            const globalSum = alexSum + laiaSum;

            // Mise à jour des cellules (Index 1=Alex, 2=Laia, 3=Global)
            row.cells[1].innerText = Math.round(alexSum).toLocaleString('fr-FR') + ' €';
            row.cells[2].innerText = Math.round(laiaSum).toLocaleString('fr-FR') + ' €';
            row.cells[3].innerText = Math.round(globalSum).toLocaleString('fr-FR') + ' €';

            // Gestion visuelle : Masquer la ligne si tout est à 0 (Optionnel)
            // row.style.display = globalSum === 0 ? 'none' : ''; 

            grandTotalAlex += alexSum;
            grandTotalLaia += laiaSum;
        });
    }

    // Mise à jour du Footer Grand Total
    document.getElementById('grand_total_alex').innerText = Math.round(grandTotalAlex).toLocaleString('fr-FR') + ' €';
    document.getElementById('grand_total_laia').innerText = Math.round(grandTotalLaia).toLocaleString('fr-FR') + ' €';
    document.getElementById('grand_total_global').innerText = Math.round(grandTotalAlex + grandTotalLaia).toLocaleString('fr-FR') + ' €';
}

function saveData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) formData.append(key, data[key]);
    fetch('/modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
}

document.addEventListener('DOMContentLoaded', recalcAllAllocations);
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du clic sur mobile pour afficher les actions
    if (window.innerWidth <= 768) {
        
        // 1. On écoute les clics sur tout le document
        document.addEventListener('click', function(e) {
            
            // On récupère la cellule "sticky" (catégorie) si elle a été cliquée
            const clickedCell = e.target.closest('.col-sticky');
            
            // Si on a cliqué sur une cellule de catégorie...
            if (clickedCell) {
                const actionsDiv = clickedCell.querySelector('.row-actions');
                
                // Si on a cliqué DIRECTEMENT sur un bouton d'action (crayon/poubelle), on ne fait rien
                // (on laisse le bouton faire son travail : ouvrir la modale ou supprimer)
                if (e.target.closest('.btn-icon-action')) return;

                // Sinon, on Toggle (Affiche/Cache) le menu de CETTE ligne
                if (actionsDiv) {
                    // Est-ce qu'il est déjà ouvert ?
                    const isOpen = actionsDiv.classList.contains('show-actions');
                    
                    // D'abord, on ferme TOUS les autres menus ouverts pour ne pas en avoir partout
                    document.querySelectorAll('.row-actions.show-actions').forEach(el => {
                        el.classList.remove('show-actions');
                    });
                    
                    // Si c'était fermé, on l'ouvre
                    if (!isOpen) {
                        actionsDiv.classList.add('show-actions');
                    }
                }
            } 
            else {
                // Si on a cliqué AILLEURS (pas sur une catéogrie), on ferme tout
                document.querySelectorAll('.row-actions.show-actions').forEach(el => {
                    el.classList.remove('show-actions');
                });
            }
        });
    }
});
</script>