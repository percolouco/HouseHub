<?php
// modules/budget/views/recap.php

// 1. Récupération des Items du Budget
$stmt = $pdo->query("SELECT * FROM pf_budget_items ORDER BY category DESC, sort_order ASC, name ASC");
$items = $stmt->fetchAll();

// 2. Déterminer quel est le mois de gestion "ouvert" par défaut
$stmtActive = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'active_gestion_month' LIMIT 1");
$defaultActiveMonth = $stmtActive->fetchColumn();
if (!$defaultActiveMonth) {
    $defaultActiveMonth = date('Y-m-01');
}

// 3. Assigner le mois et l'année en fonction du mois ouvert
$currentMonth = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($defaultActiveMonth));
$currentYear = isset($_GET['y']) ? (int)$_GET['y'] : date('Y', strtotime($defaultActiveMonth));
$viewMonthDate = "$currentYear-$currentMonth-01";

$sqlReal = "SELECT budget_item_id, SUM(amount) as total_real 
            FROM pf_expenses 
            WHERE gestion_month = ? AND budget_item_id IS NOT NULL 
            GROUP BY budget_item_id";
$stmtReal = $pdo->prepare($sqlReal);
$stmtReal->execute([$viewMonthDate]);
$realTotals = $stmtReal->fetchAll(PDO::FETCH_KEY_PAIR); // Retourne un tableau [id => total]

$sqlCatReal = "SELECT category, SUM(amount) as total_real 
               FROM pf_expenses 
               WHERE gestion_month = ? AND budget_item_id IS NULL 
               GROUP BY category";
$stmtCatReal = $pdo->prepare($sqlCatReal);
$stmtCatReal->execute([$viewMonthDate]);
$catTotals = $stmtCatReal->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtLabels = $pdo->prepare("SELECT label, amount FROM pf_expenses WHERE gestion_month = ? AND budget_item_id IS NULL");
$stmtLabels->execute([$viewMonthDate]);
$unlinkedExpenses = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$monthTranslationKey = 'month_' . str_pad((int)$currentMonth, 2, '0', STR_PAD_LEFT);
$currentMonthName = tr($monthTranslationKey) . ' ' . $currentYear;

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
                <?php foreach ($items as $item): 
                    // --- 1. CALCUL DES TOTAUX PRÉVUS ---
                    $targetAbs = abs((float)$item['amount']); 
                    $amountToAdd = ($item['type'] === 'Annuel') ? $targetAbs / 12 : $targetAbs;
                    
                    if ($item['category'] === 'income') $totalRevenus += $amountToAdd;
                    else $totalDepenses += $amountToAdd;
                    
                    // --- 2. CALCUL DU RÉEL (Logique optimisée) ---
                    $realSum = 0;
                    $hasMatchingExpense = false;

                    // A. Correspondance directe par ID
                    if (isset($realTotals[$item['id']])) {
                        $realSum = $realTotals[$item['id']];
                        $hasMatchingExpense = true;
                    } 
                    // B. Correspondance par catégorie système (École, Essence, FMCG)
                    else {
                        $catKey = null;
                        if (trim($item['name']) === 'Estimacio escola') $catKey = 'School';
                        elseif (trim($item['name']) === 'Estimation gasolina') $catKey = 'Essence';
                        elseif (trim($item['name']) === 'Estimacio F&B & beauty') $catKey = 'FMCG';

                        if ($catKey && isset($catTotals[$catKey])) {
                            $realSum = $catTotals[$catKey];
                            $hasMatchingExpense = true;
                        }
                        // C. Correspondance par mots-clés (seulement sur les dépenses non liées)
                        elseif (!empty($item['mapping_keywords'])) {
                            $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                            foreach ($unlinkedExpenses as $uexp) {
                                foreach ($keywords as $kw) {
                                    if (!empty($kw) && stripos($uexp['label'], $kw) !== false) {
                                        $realSum += (float)$uexp['amount'];
                                        $hasMatchingExpense = true;
                                        break; 
                                    }
                                }
                            }
                        }
                    }

                    $realAbs = abs($realSum);
                    $isAutoChecked = ($hasMatchingExpense && ($realAbs >= ($targetAbs - 0.10)));

                    $rowClass = ($item['category'] === 'income') ? 'row-income' : 'row-expense';
                    if ($item['is_estimate']) $rowClass .= ' row-estimate';
                ?>
                <tr class="<?= $rowClass ?>" style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:15px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?= $item['is_estimate'] ? ' <small style="color:#64748b;">('.tr('bud_est_short').')</small>' : '' ?>
                        
                        <?php if(!empty($item['mapping_keywords'])): ?>
                            <span title="<?= htmlspecialchars($item['mapping_keywords']) ?>" style="font-size:0.7rem; cursor:help;">🔗</span>
                        <?php endif; ?>
                        
                        <?php if(!empty($item['reg_month'])): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-style:italic; margin-top:2px;">
                                📅 <?= sprintf(tr('bud_reg_planned_in'), tr('month_'.str_pad(array_search($item['reg_month'], $moisFr), 2, '0', STR_PAD_LEFT))) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:<?= $item['category']==='income'?'#10b981':'#1e293b' ?>;">
                        <?= number_format($targetAbs, 2, ',', ' ') ?> €
                        
                        <?php if ($hasMatchingExpense): ?>
                            <?php 
                            // Le "Gap" est la différence visuelle. 
                            $gap = $realAbs - $targetAbs; 
                            
                            if ($gap > 0.05): ?>
                                <div style="font-size:0.75rem; color:#ef4444; font-weight:bold;">
                                    <?= $item['category'] === 'income' ? tr('bud_bonus') : tr('bud_overrun') ?> : +<?= number_format($gap, 2, ',', ' ') ?> €
                                </div>
                            <?php elseif ($gap < -0.05): ?>
                                <div style="font-size:0.75rem; color:#f59e0b; font-weight:normal;">
                                    <?= tr('bud_remaining') ?> : <?= number_format(abs($gap), 2, ',', ' ') ?> €
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.75rem; color:#10b981; font-weight:normal;">
                                    <?= tr('bud_exact_amount') ?> ✓
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['type'] === 'Annuel'): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-weight:normal;"><?= tr('bud_per_month_short') ?> <?= number_format($amountToAdd, 2, ',', ' ') ?>/<?= tr('bud_month_short') ?></div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px;">
                        <span class="badge-type <?= strtolower($item['type']) ?>" style="background:#e2e8f0; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600; color:#475569;">
                            <?= tr('bud_freq_'.strtolower($item['type'])) ?>
                        </span>
                    </td>
                    <td style="padding:15px; color:#64748b; font-weight:bold;"><?= $item['payment_day'] ? $item['payment_day'] : '-' ?></td>
                    
                    <td style="padding:15px;">
                        <?php if ($isAutoChecked): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #bbf7d0;">
                                <span>✓</span> <?= tr('bud_state_validated') ?>
                            </div>
                        <?php elseif($hasMatchingExpense): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #fde68a;">
                                <span>⏳</span> <?= tr('bud_state_partial') ?>
                            </div>
                        <?php else: ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #e2e8f0;">
                                <span>○</span> <?= tr('bud_state_waiting') ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px; text-align:right;">
                        <div class="action-buttons" style="display:flex; gap:5px; justify-content:flex-end;">
                            <button class="btn-icon-action edit" onclick='editRecapItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)' title="<?= tr('edit') ?>">✏️</button>
                            <button class="btn-icon-action delete" onclick="deleteRecapItem(<?= $item['id'] ?>)" title="<?= tr('delete') ?>">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8fafc;">
                <tr>
                    <td colspan="1" style="padding:15px;"><strong><?= tr('bud_total_income_smoothed') ?></strong></td>
                    <td colspan="5" style="padding:15px; color:#10b981;"><strong>+ <?= number_format($totalRevenus, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td colspan="1" style="padding:15px;"><strong><?= tr('bud_total_expenses_smoothed') ?></strong></td>
                    <td colspan="5" style="padding:15px; color:#ef4444;"><strong>- <?= number_format($totalDepenses, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e2e8f0; background: white;">
                    <td colspan="1" style="padding:15px; font-size:1.1rem;"><strong><?= tr('bud_theoretical_balance_recap') ?></strong></td>
                    <?php $balance = $totalRevenus - $totalDepenses; ?>
                    <td colspan="5" style="padding:15px; font-size: 1.3em;" class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong style="color:<?= $balance >= 0 ? '#10b981' : '#ef4444' ?>;"><?= number_format($balance, 2, ',', ' ') ?> € / <?= tr('bud_month_short') ?></strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="budget-note" style="margin-top:15px; font-size:0.85rem; color:#64748b;">
        <p>* <?= tr('bud_recap_footer_note') ?></p>
    </div>
</div>

<div id="budgetRecapModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:500px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px; position:relative;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="recapModalTitle" style="margin:0; font-size:1.2rem; color:#1e293b;"><?= tr('bud_recap_modal_add') ?></h3>
            <button type="button" onclick="closeRecapModal()" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="modules/budget/includes/api/manage-item.php" method="POST" id="recapForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="item_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="item_name" required class="pf-input" placeholder="<?= tr('bud_ph_item_name') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_keywords') ?></label>
                <input type="text" name="mapping_keywords" id="item_keywords" class="pf-input" placeholder="<?= tr('bud_ph_keywords') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f0f9ff; border-color:#bae6fd;">
                <small style="color:#64748b; font-size:0.75rem;"><?= tr('bud_help_keywords') ?></small>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_amount_eur') ?></label>
                    <input type="number" step="0.01" name="amount" id="item_amount" required class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_day') ?></label>
                    <input type="number" min="1" max="31" name="payment_day" id="item_day" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_category') ?></label>
                    <select name="category" id="item_category" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="expense"><?= tr('bud_cat_expense') ?></option>
                        <option value="income"><?= tr('bud_cat_income') ?></option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_frequency') ?></label>
                    <select name="type" id="item_type" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="Mensuel"><?= tr('bud_freq_mensuel') ?></option>
                        <option value="Annuel"><?= tr('bud_freq_annuel') ?></option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:25px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_amount_type') ?></label>
                    <select name="is_estimate" id="item_is_estimate" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="0"><?= tr('bud_type_fixed') ?></option>
                        <option value="1"><?= tr('bud_type_variable') ?></option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_regularization') ?></label>
                    <select name="reg_month" id="item_reg_month" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value=""><?= tr('bud_reg_none') ?></option>
                        <?php 
                        foreach($moisFr as $index => $m) {
                            if($index == 0) continue;
                            echo "<option value='$m'>" . tr('month_'.str_pad($index, 2, '0', STR_PAD_LEFT)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRecapModal()" class="pf-btn btn-secondary" style="width:auto; margin:0; background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0; background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_recap_modal_add': <?= json_encode(tr('bud_recap_modal_add')) ?>,
    'bud_recap_modal_edit': <?= json_encode(tr('bud_recap_modal_edit')) ?>,
    'bud_recap_confirm_delete': <?= json_encode(tr('bud_recap_confirm_delete')) ?>,
    'bud_err_delete': <?= json_encode(tr('bud_err_delete')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'bud_saving': <?= json_encode(tr('bud_sav_saving') ?? 'Sauvegarde...') ?>
};

function openRecapModal(mode) {
    if (mode === "add") {
        document.getElementById("recapModalTitle").innerText = window.I18N['bud_recap_modal_add'] || 'Ajouter';
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
    const modal = document.getElementById('budgetRecapModal');
    if (event.target == modal) {
        closeRecapModal();
    }
}

function editRecapItem(item) {
    const data = typeof item === "string" ? JSON.parse(item) : item;

    document.getElementById("recapModalTitle").innerText = (window.I18N['bud_recap_modal_edit'] || 'Editer') + " : " + data.name;
    document.getElementById("item_id").value = data.id;
    document.getElementById("item_name").value = data.name;
    document.getElementById("item_keywords").value = data.mapping_keywords || ''; 
    document.getElementById("item_amount").value = Math.abs(data.amount);
    document.getElementById("item_category").value = data.category;
    document.getElementById("item_type").value = data.type;
    document.getElementById("item_day").value = data.payment_day;
    document.getElementById("item_reg_month").value = data.reg_month || "";
    document.getElementById("item_is_estimate").value = data.is_estimate;

    document.getElementById("budgetRecapModal").style.display = "flex";
    document.body.classList.add('no-scroll');
}

// --- 2. INTERCEPTION ASYNCHRONE DU FORMULAIRE ---
const recapForm = document.getElementById('recapForm');
if (recapForm) {
    recapForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = window.I18N['bud_saving'] || '⏳ ...';
        submitBtn.disabled = true;

        const formData = new FormData(this);
        formData.append('ajax', '1');

        // 💡 Utilisation sécurisée de getAttribute
        const actionUrl = this.getAttribute('action'); 
        const finalUrl = actionUrl.startsWith('/') ? actionUrl.substring(1) : actionUrl;

        try {
            const response = await fetch(finalUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            // 💡 Lecture robuste (anti-Warnings PHP)
            const textResult = await response.text();
            try {
                const result = JSON.parse(textResult);
                if (result.success) {
                    closeRecapModal();
                    window.location.reload();
                } else {
                    alert((window.I18N['bud_err_tech'] || 'Erreur') + " : " + (result.error || "Inconnue"));
                }
            } catch (jsonError) {
                console.error("Réponse non-JSON :", textResult);
                alert("Le serveur a renvoyé une erreur PHP. Regarde la console (F12).");
            }
        } catch (error) {
            console.error("Erreur Fetch:", error);
            alert(window.I18N['bud_err_tech'] || 'Erreur technique');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
}

// --- 3. SUPPRESSION ASYNCHRONE ---
async function deleteRecapItem(id) {
    if (!confirm(window.I18N['bud_recap_confirm_delete'] || "Confirmer la suppression ?")) return;

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);
    formData.append("ajax", "1"); // Signale à l'API qu'on attend du JSON

    try {
        const response = await fetch("modules/budget/includes/api/manage-item.php", {
            method: "POST",
            body: formData,
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const textResult = await response.text();
        try {
            const result = JSON.parse(textResult);
            // On s'assure que si l'API ne renvoie pas success, on le signale
            if (result.success !== false) {
                window.location.reload(); 
            } else {
                alert((window.I18N['bud_err_delete'] || 'Erreur de suppression') + " : " + (result.error || ""));
            }
        } catch (jsonErr) {
            console.error("Réponse non-JSON lors de la suppression :", textResult);
            window.location.reload(); // Fallback si l'API redirige au lieu de répondre en JSON
        }
    } catch (err) {
        console.error("Erreur réseau Suppression:", err);
        alert(window.I18N['bud_err_delete'] || 'Erreur réseau');
    }
}
</script>