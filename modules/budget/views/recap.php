<?php
// modules/budget/views/recap.php

// 1. Récupération des Catégories dynamiques
$stmtCats = $pdo->query("SELECT code, label, icon, type FROM pf_budget_categories ORDER BY type DESC, label ASC");
$dbCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupération des Salaires/Mensualités configurés (Revenus automatiques)
$currentYear = isset($_GET['y']) ? (int)$_GET['y'] : date('Y');
$stmtSalaries = $pdo->prepare("SELECT person, mensualite FROM pf_salary_config WHERE year = ?");
$stmtSalaries->execute([$currentYear]);
$salaries = $stmtSalaries->fetchAll(PDO::FETCH_ASSOC);

// 3. Récupération des Items du Budget (Charges Fixes et Estimations)
$stmt = $pdo->query("SELECT * FROM pf_budget_items WHERE category != 'SAVINGS' ORDER BY is_estimate ASC, sort_order ASC, name ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Gestion du mois actif
$stmtActive = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'active_gestion_month' LIMIT 1");
$defaultActiveMonth = $stmtActive->fetchColumn() ?: date('Y-m-01');
$currentMonth = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($defaultActiveMonth));
$viewMonthDate = "$currentYear-$currentMonth-01";

// 5. Récupération optimisée et unifiée du Réel
// On récupère tout en une seule requête pour éviter les décalages entre ID et Catégorie
$stmtReal = $pdo->prepare("
    SELECT budget_item_id, category, SUM(amount) as total_real 
    FROM pf_expenses 
    WHERE gestion_month = ? 
    GROUP BY budget_item_id, category
");
$stmtReal->execute([$viewMonthDate]);
$allExpenses = $stmtReal->fetchAll(PDO::FETCH_ASSOC);

// On initialise les tableaux de correspondance
$realTotalsById = []; // Pour les lignes liées par ID
$realTotalsByCat = []; // Pour les lignes orphelines (NULL) liées par Catégorie

foreach ($allExpenses as $row) {
    if (!empty($row['budget_item_id'])) {
        $realTotalsById[$row['budget_item_id']] = (float)$row['total_real'];
    } else {
        $realTotalsByCat[$row['category']] = (float)$row['total_real'];
    }
}

// Récupération des détails pour le mapping par mots-clés (si besoin de précision)
$stmtLabels = $pdo->prepare("SELECT label, amount, category FROM pf_expenses WHERE gestion_month = ? AND budget_item_id IS NULL");
$stmtLabels->execute([$viewMonthDate]);
$unlinkedExpenses = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

// Gestion des libellés et totaux
$currentMonthName = tr('month_' . str_pad((int)$currentMonth, 2, '0', STR_PAD_LEFT)) . ' ' . $currentYear;
$totalDepenses = 0;
$totalRevenus = 0;
?>

<div class="budget-view">
    <div class="view-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;"><?= tr('bud_recap_monthly_title') ?></h2>
        <button onclick="openRecapModal('add')" class="pf-btn">＋ <?= tr('bud_recap_btn_add') ?></button>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0; overflow:hidden;">
        <table class="pf-table" style="margin:0; box-shadow:none;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th><?= tr('bud_label_name') ?></th>
                    <th><?= tr('bud_planned_amount') ?></th>
                    <th><?= tr('bud_type') ?></th>
                    <th><?= tr('bud_day') ?></th>
                    <th><?= tr('bud_month_state') ?> (<?= $currentMonthName ?>)</th>
                    <th style="text-align:right;"><?= tr('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salaries as $salary): 
                    $mensualite = (float)$salary['mensualite'];
                    $totalRevenus += $mensualite;
                ?>
                <tr class="row-income" style="border-bottom:1px solid var(--border-light); background:#f0fdf4;">
                    <td style="padding:15px;">
                        <strong>Apport <?= htmlspecialchars($salary['person']) ?></strong>
                        <span title="Géré automatiquement depuis les paramètres des Salaires" style="font-size:0.8rem; cursor:help;">⚙️</span>
                    </td>
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:#10b981;">
                        + <?= number_format($mensualite, 2, ',', ' ') ?> €
                    </td>
                    <td style="padding:15px;">
                        <span class="badge-type" style="background:#dcfce7; color:#16a34a; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600;">Fixe (Auto)</span>
                    </td>
                    <td style="padding:15px; color:#64748b; font-weight:bold;">-</td>
                    <td style="padding:15px;">
                        <div style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #e2e8f0;">
                            Auto
                        </div>
                    </td>
                    <td style="padding:15px; text-align:right;">
                        <small style="color:#94a3b8;">Via Paramètres</small>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php 
foreach ($items as $item): 
    // 1. Calculs de base
    $targetAbs = abs((float)$item['amount']); 
    $amountToAdd = ($item['type'] === 'Annuel') ? $targetAbs / 12 : $targetAbs;
    $totalDepenses += $amountToAdd;
    
    // 2. RÉINITIALISATION STRICTE
    $realSum = 0;
    $hasMatchingExpense = false;

    // A. Correspondance par ID direct (Le lien dur)
    if (isset($realTotalsById[$item['id']])) {
        $realSum = (float)$realTotalsById[$item['id']];
        $hasMatchingExpense = true;
    } 
    // B. Correspondance par mots-clés bancaires (Le lien intelligent pour les orphelines)
    elseif (!empty($item['mapping_keywords'])) {
        $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
        foreach ($unlinkedExpenses as $uexp) {
            foreach ($keywords as $kw) {
                if (!empty($kw) && stripos($uexp['label'], $kw) !== false) {
                    $realSum += (float)$uexp['amount'];
                    $hasMatchingExpense = true;
                    break; // On a trouvé un mot clé sur cette dépense, on passe à l'orpheline suivante
                }
            }
        }
    }

    // 3. Finalisation des variables d'affichage
    $realAbs = abs($realSum);
    $isAutoChecked = ($hasMatchingExpense && ($realAbs >= ($targetAbs - 0.10)));
    $rowClass = 'row-expense' . ($item['is_estimate'] ? ' row-estimate' : '');
?>
                <tr class="<?= $rowClass ?>" style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:15px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?= $item['is_estimate'] ? ' <small style="color:#64748b;">(Variable)</small>' : '' ?>
                        <?php if(!empty($item['mapping_keywords'])): ?>
                            <span title="Reconnaissance bancaire : <?= htmlspecialchars($item['mapping_keywords']) ?>" style="font-size:0.7rem; cursor:help;">🔗</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:#1e293b;">
                        - <?= number_format($targetAbs, 2, ',', ' ') ?> €
                        
                        <?php if ($hasMatchingExpense): ?>
                            <?php $gap = $realAbs - $targetAbs; 
                            if ($gap > 0.05): ?>
                                <div style="font-size:0.75rem; color:#ef4444; font-weight:bold;">Dépassé : +<?= number_format($gap, 2, ',', ' ') ?> €</div>
                            <?php elseif ($gap < -0.05): ?>
                                <div style="font-size:0.75rem; color:#f59e0b;">Reste : <?= number_format(abs($gap), 2, ',', ' ') ?> €</div>
                            <?php else: ?>
                                <div style="font-size:0.75rem; color:#10b981;">Atteint ✓</div>
                            <?php endif; ?>
                        <?php elseif ($item['type'] === 'Annuel'): ?>
                            <div style="font-size:0.75rem; color:#94a3b8;"><?= number_format($amountToAdd, 2, ',', ' ') ?>/mois</div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px;">
                        <span class="badge-type" style="background:#e2e8f0; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600; color:#475569;">
                            <?= htmlspecialchars($item['type']) ?>
                        </span>
                    </td>
                    <td style="padding:15px; color:#64748b; font-weight:bold;"><?= $item['payment_day'] ?: '-' ?></td>
                    
                    <td style="padding:15px;">
                        <?php if ($isAutoChecked): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #bbf7d0;"><span>✓</span> Validé</div>
                        <?php elseif($hasMatchingExpense): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #fde68a;"><span>⏳</span> Partiel</div>
                        <?php else: ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #e2e8f0;"><span>○</span> En attente</div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px; text-align:right;">
                        <div class="action-buttons" style="display:flex; gap:5px; justify-content:flex-end;">
                            <button class="btn-icon-action edit" onclick='editRecapItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)' title="Modifier">✏️</button>
                            <button class="btn-icon-action delete" onclick="deleteRecapItem(<?= $item['id'] ?>)" title="Supprimer">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8fafc;">
                <tr>
                    <td colspan="1" style="padding:15px;"><strong>Total Revenus Lissés</strong></td>
                    <td colspan="5" style="padding:15px; color:#10b981;"><strong>+ <?= number_format($totalRevenus, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td colspan="1" style="padding:15px;"><strong>Total Dépenses & Estimations</strong></td>
                    <td colspan="5" style="padding:15px; color:#ef4444;"><strong>- <?= number_format($totalDepenses, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e2e8f0; background: white;">
                    <td colspan="1" style="padding:15px; font-size:1.1rem;"><strong>Reste à Vivre (Équilibre)</strong></td>
                    <?php $balance = $totalRevenus - $totalDepenses; ?>
                    <td colspan="5" style="padding:15px; font-size: 1.3em;">
                        <strong style="color:<?= $balance >= 0 ? '#10b981' : '#ef4444' ?>;"><?= number_format($balance, 2, ',', ' ') ?> € / mois</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div id="budgetRecapModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:500px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px; position:relative;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="recapModalTitle" style="margin:0; font-size:1.2rem; color:#1e293b;">Ajouter une ligne</h3>
            <button type="button" onclick="closeRecapModal()" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="modules/budget/includes/api/manage-item.php" method="POST" id="recapForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="item_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Nom de la ligne</label>
                <input type="text" name="name" id="item_name" required class="pf-input" placeholder="ex: Assurance Auto, Estimation Courses..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Montant Prévu (€)</label>
                    <input type="number" step="0.01" name="amount" id="item_amount" required class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Type de Dépense</label>
                    <select name="is_estimate" id="item_is_estimate" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="0">Fixe (Facture, Prélèvement)</option>
                        <option value="1">Variable (Estimation, Enveloppe)</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Catégorie Cible (La jauge)</label>
                    <select name="category" id="item_category" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;" required>
                        <option value="" disabled selected>-- Choisir --</option>
                        <?php foreach ($dbCategories as $c): ?>
                            <?php if(strtolower($c['type']) === 'expense'): // On n'affiche que les catégories de dépenses ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>">
                                <?= htmlspecialchars($c['icon'] . ' ' . $c['label']) ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Fréquence</label>
                    <select name="type" id="item_type" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="Mensuel">Mensuel</option>
                        <option value="Annuel">Annuel</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Mots-clés (Reconnaissance Bancaire)</label>
                <input type="text" name="mapping_keywords" id="item_keywords" class="pf-input" placeholder="ex: NETFLIX, DOMOFINANCE..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f0f9ff; border-color:#bae6fd;">
                <small style="color:#64748b; font-size:0.75rem;">Optionnel. Permet au système de lier automatiquement un import CSV à cette ligne.</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top: 25px;">
                <button type="button" onclick="closeRecapModal()" class="pf-btn btn-secondary" style="width:auto; margin:0; background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0; background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRecapModal(mode) {
    if (mode === "add") {
        document.getElementById("recapModalTitle").innerText = 'Ajouter une ligne';
        document.getElementById("item_id").value = "";
        document.getElementById("recapForm").reset();
    }
    document.getElementById("budgetRecapModal").style.display = "flex";
    document.body.classList.add('no-scroll');
}

function closeRecapModal() {
    document.getElementById("budgetRecapModal").style.display = "none";
    document.body.classList.remove('no-scroll');
}

window.onclick = function(event) {
    if (event.target == document.getElementById('budgetRecapModal')) closeRecapModal();
}

function editRecapItem(item) {
    const data = typeof item === "string" ? JSON.parse(item) : item;
    document.getElementById("recapModalTitle").innerText = "Editer : " + data.name;
    document.getElementById("item_id").value = data.id;
    document.getElementById("item_name").value = data.name;
    document.getElementById("item_keywords").value = data.mapping_keywords || ''; 
    document.getElementById("item_amount").value = Math.abs(data.amount);
    document.getElementById("item_category").value = data.category;
    document.getElementById("item_type").value = data.type;
    document.getElementById("item_is_estimate").value = data.is_estimate;

    document.getElementById("budgetRecapModal").style.display = "flex";
    document.body.classList.add('no-scroll');
}

document.getElementById('recapForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerText = '⏳ ...';

    const formData = new FormData(this);
    formData.append('ajax', '1');
    const url = this.getAttribute('action');

    try {
        const res = await fetch(url.startsWith('/') ? url.substring(1) : url, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) window.location.reload();
        else alert("Erreur : " + data.error);
    } catch (err) {
        alert("Erreur technique lors de la sauvegarde.");
    } finally {
        btn.disabled = false; btn.innerText = 'Enregistrer';
    }
});

async function deleteRecapItem(id) {
    if (!confirm("Confirmer la suppression ?")) return;
    const formData = new FormData();
    formData.append("action", "delete"); formData.append("id", id); formData.append("ajax", "1");
    try {
        const res = await fetch("modules/budget/includes/api/manage-item.php", { method: "POST", body: formData });
        const data = await res.json();
        if (data.success !== false) window.location.reload();
        else alert("Erreur : " + data.error);
    } catch (err) {
        window.location.reload(); 
    }
}
</script>