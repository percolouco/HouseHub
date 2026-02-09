<?php
// 1. Récupération des données
$stmt = $pdo->query("SELECT * FROM pf_budget_items ORDER BY category DESC, sort_order ASC, name ASC");
$items = $stmt->fetchAll();

$totalDepensesMensuelles = 0;
$totalRevenusMensuels = 0;
?>

<div class="budget-view">
    <div class="view-header">
        <h2>Récapitulatif Mensuel</h2>
        <button onclick="openModal('add')" class="pf-btn">＋ Ajouter un frais / revenu</button>
    </div>

    <table class="pf-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Montant</th>
                <th>Type</th>
                <th>Jour</th>
                <th>État prélèvement</th>
                <th>Régularisation</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                // Calcul des totaux (Mensuel uniquement)
                if ($item['type'] === 'Mensuel') {
                    if ($item['category'] === 'expense') {
                        $totalDepensesMensuelles += $item['amount'];
                    } else {
                        $totalRevenusMensuels += $item['amount'];
                    }
                }
                
                $rowClass = ($item['category'] === 'income') ? 'row-income' : 'row-expense';
                if ($item['is_estimate']) $rowClass .= ' row-estimate';
            ?>
            <tr class="<?= $rowClass ?>">
                <td>
                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                    <?= $item['is_estimate'] ? ' <small>(Est.)</small>' : '' ?>
                </td>
                <td class="cell-amount">
                    <?= number_format($item['amount'], 2, ',', ' ') ?> €
                </td>
                <td>
                    <span class="badge-type <?= strtolower($item['type']) ?>">
                        <?= $item['type'] ?>
                    </span>
                </td>
                <td><?= $item['payment_day'] ? $item['payment_day'] : '-' ?></td>
                <td class="text-center">
                    <input type="checkbox" 
                           <?= $item['is_checked'] ? 'checked' : '' ?> 
                           onclick="toggleCheck(<?= $item['id'] ?>, this.checked)"
                           title="Marquer comme payé">
                    <?= $item['is_checked'] ? ' <span class="text-success">Payé</span>' : ' <span style="color:var(--warning)">Attente</span>' ?>
                </td>
                <td>
                    <em><?= htmlspecialchars($item['reg_month'] ?: '-') ?></em>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick='editItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)' title="Modifier">✏️</button>
                        <button class="btn-icon" onclick="deleteItem(<?= $item['id'] ?>)" title="Supprimer">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="1"><strong>Total Revenus Mensuels</strong></td>
                <td colspan="6" class="text-success"><strong>+ <?= number_format($totalRevenusMensuels, 2, ',', ' ') ?> €</strong></td>
            </tr>
            <tr>
                <td colspan="1"><strong>Total Dépenses Mensuelles</strong></td>
                <td colspan="6" class="text-danger"><strong>- <?= number_format($totalDepensesMensuelles, 2, ',', ' ') ?> €</strong></td>
            </tr>
            <tr style="border-top: 2px solid #e2e8f0; background: white;">
                <td colspan="1"><strong>Équilibre du compte</strong></td>
                <?php $balance = $totalRevenusMensuels - $totalDepensesMensuelles; ?>
                <td colspan="6" style="font-size: 1.3em;" class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                    <strong><?= number_format($balance, 2, ',', ' ') ?> € / mois</strong>
                </td>
            </tr>
        </tfoot>
    </table>
    
    <div class="budget-note">
        <p>* Note : Les frais de type "Annuel" sont affichés pour information mais ne sont pas inclus dans le calcul de l'équilibre mensuel.</p>
    </div>
</div>

<div id="budgetModal" class="pf-modal">
    <div class="pf-modal-content">
        <h3 id="modalTitle" class="pf-modal-title">Ajouter un élément</h3>
        
        <form action="/modules/budget/includes/api/manage-item.php" method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="item_id">
            
            <div class="form-group">
                <label class="pf-label">Nom</label>
                <input type="text" name="name" id="item_name" required class="pf-input" placeholder="Ex: Loyer, Salaire...">
            </div>
            
            <div class="form-row">
                <div>
                    <label class="pf-label">Montant (€)</label>
                    <input type="number" step="0.01" name="amount" id="item_amount" required class="pf-input">
                </div>
                <div>
                    <label class="pf-label">Jour (1-31)</label>
                    <input type="number" min="1" max="31" name="payment_day" id="item_day" class="pf-input">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label">Catégorie</label>
                    <select name="category" id="item_category" class="pf-input">
                        <option value="expense">Dépense (Frais)</option>
                        <option value="income">Revenu (Salaire)</option>
                    </select>
                </div>
                <div>
                    <label class="pf-label">Fréquence</label>
                    <select name="type" id="item_type" class="pf-input">
                        <option value="Mensuel">Mensuel</option>
                        <option value="Annuel">Annuel</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label">Type de montant</label>
                    <select name="is_estimate" id="item_is_estimate" class="pf-input">
                        <option value="0">Fixe (Facture)</option>
                        <option value="1">Variable (Estimation)</option>
                    </select>
                </div>
                <div>
                    <label class="pf-label">Régularisation</label>
                    <select name="reg_month" id="item_reg_month" class="pf-input">
                        <option value="">Aucune</option>
                        <?php 
                        $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                        foreach($mois as $m) echo "<option value='$m'>$m</option>";
                        ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>