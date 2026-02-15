<?php
// modules/budget/views/recap.php

// 1. Récupération des Items du Budget
$stmt = $pdo->query("SELECT * FROM pf_budget_items ORDER BY category DESC, sort_order ASC, name ASC");
$items = $stmt->fetchAll();

// 2. Récupération des Dépenses Réelles du mois (Pour le matching auto)
$currentMonth = date('m');
$currentYear = date('Y');
$stmtExp = $pdo->prepare("SELECT label FROM pf_expenses WHERE MONTH(date_exp) = ? AND YEAR(date_exp) = ?");
$stmtExp->execute([$currentMonth, $currentYear]);
// On stocke tous les libellés réels dans un tableau simple
$realExpensesLabels = $stmtExp->fetchAll(PDO::FETCH_COLUMN);

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
                    <th>Montant</th>
                    <th>Type</th>
                    <th>Jour</th>
                    <th>État prélèvement</th>
                    <th>Régularisation</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    // --- CALCUL DES TOTAUX ---
                    $amountToAdd = ($item['type'] === 'Annuel') ? $item['amount'] / 12 : $item['amount'];
                    if ($item['category'] === 'expense') $totalDepenses += $amountToAdd;
                    else $totalRevenus += $amountToAdd;
                    
                    // --- LOGIQUE DE DETECTION AUTOMATIQUE ---
                    $isAutoChecked = false;
                    // Si des mots-clés sont définis pour cet item
                    if (!empty($item['mapping_keywords'])) {
                        // On explose la chaine "Netflix, Spotify" en tableau
                        $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                        
                        foreach ($keywords as $kw) {
                            if (empty($kw)) continue;
                            // On cherche ce mot clé dans TOUTES les dépenses réelles du mois
                            foreach ($realExpensesLabels as $realLabel) {
                                // stripos pour insensible à la casse
                                if (stripos($realLabel, $kw) !== false) {
                                    $isAutoChecked = true;
                                    break 2; // On sort des deux boucles si trouvé
                                }
                            }
                        }
                    }

                    // L'état final est : Soit coché manuellement (BDD), soit détecté auto
                    $isPaid = $item['is_checked'] || $isAutoChecked;

                    // Styles
                    $rowClass = ($item['category'] === 'income') ? 'row-income' : 'row-expense';
                    if ($item['is_estimate']) $rowClass .= ' row-estimate';
                ?>
                <tr class="<?= $rowClass ?>" style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:15px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?= $item['is_estimate'] ? ' <small style="color:#64748b;">(Est.)</small>' : '' ?>
                        <?php if(!empty($item['mapping_keywords'])): ?>
                            <span title="Mapping auto activé: <?= htmlspecialchars($item['mapping_keywords']) ?>" style="font-size:0.7rem; cursor:help;">🔗</span>
                        <?php endif; ?>
                    </td>
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:<?= $item['category']==='income'?'#10b981':'#1e293b' ?>;">
                        <?= number_format($item['amount'], 2, ',', ' ') ?> €
                        <?php if($item['type'] === 'Annuel'): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-weight:normal;">Soit <?= number_format($amountToAdd, 2, ',', ' ') ?>/mois</div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:15px;">
                        <span class="badge-type <?= strtolower($item['type']) ?>" style="background:#e2e8f0; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600; color:#475569;">
                            <?= $item['type'] ?>
                        </span>
                    </td>
                    <td style="padding:15px; color:#64748b;"><?= $item['payment_day'] ? $item['payment_day'] : '-' ?></td>
                    <td style="padding:15px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <?php if ($isAutoChecked): ?>
                                <input type="checkbox" checked disabled style="width:18px; height:18px; accent-color:#3b82f6; cursor:not-allowed;">
                                <span style="color:#3b82f6; font-weight:600; font-size:0.9rem;">Auto ✅</span>
                            <?php else: ?>
                                <input type="checkbox" 
                                       <?= $item['is_checked'] ? 'checked' : '' ?> 
                                       onclick="toggleItemCheck(<?= $item['id'] ?>, this.checked)"
                                       title="Marquer comme payé"
                                       style="width:18px; height:18px; cursor:pointer;">
                                <?= $item['is_checked'] ? ' <span style="color:#10b981; font-weight:500; font-size:0.9rem;">Payé</span>' : ' <span style="color:#f59e0b; font-weight:500; font-size:0.9rem;">Attente</span>' ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding:15px; color:#64748b; font-size:0.9rem;">
                        <em><?= htmlspecialchars($item['reg_month'] ?: '-') ?></em>
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
                    <td colspan="6" style="padding:15px; color:#10b981;"><strong>+ <?= number_format($totalRevenus, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td colspan="1" style="padding:15px;"><strong>Total Dépenses (Lissées)</strong></td>
                    <td colspan="6" style="padding:15px; color:#ef4444;"><strong>- <?= number_format($totalDepenses, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e2e8f0; background: white;">
                    <td colspan="1" style="padding:15px; font-size:1.1rem;"><strong>Équilibre théorique</strong></td>
                    <?php $balance = $totalRevenus - $totalDepenses; ?>
                    <td colspan="6" style="padding:15px; font-size: 1.3em;" class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong style="color:<?= $balance >= 0 ? '#10b981' : '#ef4444' ?>;"><?= number_format($balance, 2, ',', ' ') ?> € / mois</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="budget-note" style="margin-top:15px; font-size:0.85rem; color:#64748b;">
        <p>* Les lignes marquées <strong>Auto ✅</strong> ont été détectées automatiquement dans les dépenses réelles du mois.</p>
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
                <small style="color:#64748b; font-size:0.75rem;">Si une dépense du mois contient ce mot, la ligne passera en "Payé".</small>
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
// ==========================================
// SCRIPTS ISOLÉS POUR L'ONGLET RECAPITULATIF
// ==========================================

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
    // Remplissage du nouveau champ Keywords
    document.getElementById("item_keywords").value = data.mapping_keywords || ''; 
    
    document.getElementById("item_amount").value = data.amount;
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

function toggleItemCheck(id, isChecked) {
    const formData = new FormData();
    formData.append("action", "toggle-check");
    formData.append("id", id);
    formData.append("status", isChecked ? 1 : 0);

    fetch("/modules/budget/includes/api/manage-item.php", {
        method: "POST",
        body: formData,
    })
    .then((response) => {
        if (!response.ok) throw new Error("Erreur réseau");
        window.location.reload(); 
    })
    .catch((error) => {
        alert("Erreur lors de la mise à jour");
        console.error(error);
        window.location.reload();
    });
}
</script>