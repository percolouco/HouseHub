<?php
// modules/budget/views/recap.php

// 1. Récupération des Items du Budget
$stmt = $pdo->query("SELECT * FROM pf_budget_items ORDER BY category DESC, sort_order ASC, name ASC");
$items = $stmt->fetchAll();

// 2. Récupération des Dépenses Réelles du mois (Pour valider les états)
$currentMonth = date('m');
$currentYear = date('Y');

$stmtExp = $pdo->prepare("SELECT amount, label, category, budget_item_id FROM pf_expenses WHERE MONTH(date_exp) = ? AND YEAR(date_exp) = ?");
$stmtExp->execute([$currentMonth, $currentYear]);
$allExpenses = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

$totalDepenses = 0;
$totalRevenus = 0;
?>

<div class="budget-view">
    <div class="view-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Récapitulatif Mensuel</h2>
        <button onclick="openRecapModal('add')" class="pf-btn">＋ Ajouter un frais / revenu</button>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0; overflow:hidden;">
        <table class="pf-table" style="margin:0; box-shadow:none;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th>Nom</th>
                    <th>Montant Prévu</th>
                    <th>Type</th>
                    <th>Jour</th>
                    <th>État du mois (<?= date('M') ?>)</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    // --- 1. CALCUL DES TOTAUX PRÉVUS ---
                    $targetAbs = abs((float)$item['amount']); // On utilise l'absolu pour l'affichage
                    $amountToAdd = ($item['type'] === 'Annuel') ? $targetAbs / 12 : $targetAbs;
                    
                    if ($item['category'] === 'expense') $totalDepenses += $amountToAdd;
                    else $totalRevenus += $amountToAdd;
                    
                    // --- 2. CALCUL DU RÉEL (SOMME DES LIGNES ASSOCIÉES) ---
                    $realSum = 0;
                    $hasMatchingExpense = false;

                    foreach ($allExpenses as $exp) {
                        $match = false;
                        
                        if (!empty($exp['budget_item_id']) && (int)$exp['budget_item_id'] === (int)$item['id']) {
                            $match = true;
                        } 
                        elseif ($exp['category'] === 'School' && trim($item['name']) === 'Estimacio escola') {
                            $match = true;
                        }
                        elseif (empty($exp['budget_item_id']) && !empty($item['mapping_keywords'])) {
                            $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                            foreach ($keywords as $kw) {
                                if (!empty($kw) && stripos($exp['label'], $kw) !== false) {
                                    $match = true; 
                                    break;
                                }
                            }
                        }

                        if ($match) {
                            $realSum += (float)$exp['amount'];
                            $hasMatchingExpense = true;
                        }
                    }

                    // On convertit le résultat réel en positif pour simplifier la comparaison visuelle UI
                    $realAbs = abs($realSum);

                    // --- 3. LOGIQUE D'ÉTAT ---
                    $isAutoChecked = false;
                    if ($hasMatchingExpense && ($realAbs >= ($targetAbs - 0.10))) {
                        $isAutoChecked = true;
                    }

                    // Styles
                    $rowClass = ($item['category'] === 'income') ? 'row-income' : 'row-expense';
                    if ($item['is_estimate']) $rowClass .= ' row-estimate';
                ?>
                <tr class="<?= $rowClass ?>" style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:15px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?= $item['is_estimate'] ? ' <small style="color:#64748b;">(Est.)</small>' : '' ?>
                        
                        <?php if(!empty($item['mapping_keywords'])): ?>
                            <span title="<?= htmlspecialchars($item['mapping_keywords']) ?>" style="font-size:0.7rem; cursor:help;">🔗</span>
                        <?php endif; ?>
                        
                        <?php if(!empty($item['reg_month'])): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-style:italic; margin-top:2px;">
                                📅 Régul. prévue en <?= htmlspecialchars($item['reg_month']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:<?= $item['category']==='income'?'#10b981':'#1e293b' ?>;">
                        <?= number_format($targetAbs, 2, ',', ' ') ?> €
                        
                        <?php if ($hasMatchingExpense): ?>
                            <?php 
                            // Le "Gap" est la différence visuelle. 
                            // S'il y a plus de $realAbs que prévu, c'est un dépassement ou un bonus.
                            $gap = $realAbs - $targetAbs; 
                            
                            if ($gap > 0.05): ?>
                                <div style="font-size:0.75rem; color:#ef4444; font-weight:bold;">
                                    <?= $item['category'] === 'income' ? 'Bonus :' : 'Dépassement :' ?> +<?= number_format($gap, 2, ',', ' ') ?> €
                                </div>
                            <?php elseif ($gap < -0.05): ?>
                                <div style="font-size:0.75rem; color:#f59e0b; font-weight:normal;">
                                    Reste : <?= number_format(abs($gap), 2, ',', ' ') ?> €
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.75rem; color:#10b981; font-weight:normal;">
                                    Montant exact ✓
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['type'] === 'Annuel'): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-weight:normal;">Soit <?= number_format($amountToAdd, 2, ',', ' ') ?>/mois</div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px;">
                        <span class="badge-type <?= strtolower($item['type']) ?>" style="background:#e2e8f0; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600; color:#475569;">
                            <?= $item['type'] ?>
                        </span>
                    </td>
                    <td style="padding:15px; color:#64748b; font-weight:bold;"><?= $item['payment_day'] ? $item['payment_day'] : '-' ?></td>
                    
                    <td style="padding:15px;">
                        <?php if ($isAutoChecked): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #bbf7d0;">
                                <span>✓</span> Validé
                            </div>
                        <?php elseif($hasMatchingExpense): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #fde68a;">
                                <span>⏳</span> Partiel
                            </div>
                        <?php else: ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #e2e8f0;">
                                <span>○</span> En attente
                            </div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px; text-align:right;">
                        <div class="action-buttons" style="display:flex; gap:5px; justify-content:flex-end;">
                            <button class="btn-icon" onclick='editRecapItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)' title="Modifier" style="background:none; border:none; cursor:pointer; font-size:1.1rem; filter:grayscale(1); transition:0.2s;">✏️</button>
                            <button class="btn-icon" onclick="deleteRecapItem(<?= $item['id'] ?>)" title="Supprimer" style="background:none; border:none; cursor:pointer; font-size:1.1rem; filter:grayscale(1); transition:0.2s;">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8fafc;">
                <tr>
                    <td colspan="1" style="padding:15px;"><strong>Total Revenus (Lissés)</strong></td>
                    <td colspan="5" style="padding:15px; color:#10b981;"><strong>+ <?= number_format($totalRevenus, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td colspan="1" style="padding:15px;"><strong>Total Dépenses (Lissées)</strong></td>
                    <td colspan="5" style="padding:15px; color:#ef4444;"><strong>- <?= number_format($totalDepenses, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e2e8f0; background: white;">
                    <td colspan="1" style="padding:15px; font-size:1.1rem;"><strong>Équilibre théorique</strong></td>
                    <?php $balance = $totalRevenus - $totalDepenses; ?>
                    <td colspan="5" style="padding:15px; font-size: 1.3em;" class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong style="color:<?= $balance >= 0 ? '#10b981' : '#ef4444' ?>;"><?= number_format($balance, 2, ',', ' ') ?> € / mois</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="budget-note" style="margin-top:15px; font-size:0.85rem; color:#64748b;">
        <p>* L'état de paiement se met à jour automatiquement en fonction des opérations importées dans l'onglet "Suivi Mensuel".</p>
    </div>
</div>

<div id="budgetRecapModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:500px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px; position:relative;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="recapModalTitle" style="margin:0; font-size:1.2rem; color:#1e293b;">Ajouter un élément</h3>
            <button type="button" onclick="closeRecapModal()" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="/modules/budget/includes/api/manage-item.php" method="POST" id="recapForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="item_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Nom</label>
                <input type="text" name="name" id="item_name" required class="pf-input" placeholder="Ex: Loyer, Salaire..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Mots-clés (Détection Auto)</label>
                <input type="text" name="mapping_keywords" id="item_keywords" class="pf-input" placeholder="Ex: NETFLIX, PRIME VIDEO (séparés par virgule)" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f0f9ff; border-color:#bae6fd;">
                <small style="color:#64748b; font-size:0.75rem;">Si une dépense du mois contient ce mot, la ligne passera en "Validé".</small>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Montant (€)</label>
                    <input type="number" step="0.01" name="amount" id="item_amount" required class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Jour (1-31)</label>
                    <input type="number" min="1" max="31" name="payment_day" id="item_day" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Catégorie</label>
                    <select name="category" id="item_category" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="expense">Dépense (Frais)</option>
                        <option value="income">Revenu (Salaire)</option>
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

            <div style="display:flex; gap:15px; margin-bottom:25px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Type de montant</label>
                    <select name="is_estimate" id="item_is_estimate" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="0">Fixe (Facture)</option>
                        <option value="1">Variable (Estimation)</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;">Régularisation</label>
                    <select name="reg_month" id="item_reg_month" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="">Aucune</option>
                        <?php 
                        $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                        foreach($mois as $m) echo "<option value='$m'>$m</option>";
                        ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRecapModal()" class="pf-btn btn-secondary" style="width:auto; margin:0; background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0; background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
window.onclick = function(event) {
    const modal = document.getElementById('budgetRecapModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

function openRecapModal(mode) {
    if (mode === "add") {
        document.getElementById("recapModalTitle").innerText = "Ajouter un élément";
        document.getElementById("item_id").value = "";
        document.getElementById("recapForm").reset();
    }
    document.getElementById("budgetRecapModal").style.display = "flex";
}

function closeRecapModal() {
    document.getElementById("budgetRecapModal").style.display = "none";
}

function editRecapItem(item) {
    const data = typeof item === "string" ? JSON.parse(item) : item;

    document.getElementById("recapModalTitle").innerText = "Modifier : " + data.name;
    document.getElementById("item_id").value = data.id;
    document.getElementById("item_name").value = data.name;
    document.getElementById("item_keywords").value = data.mapping_keywords || ''; 
    // On affiche toujours la valeur en positif dans le formulaire
    document.getElementById("item_amount").value = Math.abs(data.amount);
    document.getElementById("item_category").value = data.category;
    document.getElementById("item_type").value = data.type;
    document.getElementById("item_day").value = data.payment_day;
    document.getElementById("item_reg_month").value = data.reg_month || "";
    document.getElementById("item_is_estimate").value = data.is_estimate;

    document.getElementById("budgetRecapModal").style.display = "flex";
}

function deleteRecapItem(id) {
    if (confirm("Voulez-vous vraiment supprimer cet élément ?")) {
        const formData = new FormData();
        formData.append("action", "delete");
        formData.append("id", id);

        fetch("/modules/budget/includes/api/manage-item.php", {
            method: "POST",
            body: formData,
        }).then(() => {
            window.location.reload(); 
        }).catch(err => {
            alert("Erreur lors de la suppression.");
            console.error(err);
        });
    }
}
</script>