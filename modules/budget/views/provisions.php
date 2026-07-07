<?php
// modules/budget/views/provisions.php

// --- RÉCUPÉRATION DES DÉPENSES PRÉVUES (MULTI-TENANT PDO) ---
$stmt = $pdo->query("SELECT id, title, amount, expected_date FROM pf_expected_expenses WHERE is_paid = 0 ORDER BY expected_date ASC");
$provisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- RÉCUPÉRATION DES APPORTS ET DETTES (CLEARING) ---
$currentYear = date('Y');

// 1. Apports de base (Eco Family) des parents configurés pour l'année
$stmtConfig = $pdo->prepare("SELECT person, eco_family FROM pf_salary_config WHERE year = ?");
$stmtConfig->execute([$currentYear]);
$configs = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Dettes non résolues par personne (issues des Avances & Tricount)
$stmtDebts = $pdo->query("SELECT payer, SUM(amount) as total_debt FROM pf_advances WHERE is_resolved = 0 GROUP BY payer");
$debts = $stmtDebts->fetchAll(PDO::FETCH_KEY_PAIR);

function formatDate($dateString) {
    if (!$dateString) return '';
    return date('d/m/Y', strtotime($dateString));
}
?>

<div class="budget-view">
    <div class="view-header">
        <h2><?= tr('budget_provisions_title') ?></h2>
        
        <button class="pf-btn pf-btn-primary" onclick="openOptimizeModal()">
            ✨ <?= tr('btn_optimize_cashflow') ?>
        </button>
    </div>

    <div class="pf-card">
        <h3 class="pf-card-h2"><?= tr('add_new_provision') ?></h3>
        
        <form id="form-add-provision" data-action="/modules/budget/includes/api/add-provision.php">
            <div class="provisions-form-grid">
                
                <div class="pf-form-group">
                    <label class="pf-label" for="prov-title"><?= tr('provision_label') ?></label>
                    <input type="text" id="prov-title" name="title" class="pf-input" placeholder="<?= tr('provision_placeholder_wood') ?>" required>
                </div>
                
                <div class="pf-form-group">
                    <label class="pf-label" for="prov-amount"><?= tr('amount') ?> (€)</label>
                    <input type="number" id="prov-amount" name="amount" class="pf-input no-spinners input-amount-highlight" step="0.01" min="0.01" required>
                </div>
                
                <div class="pf-form-group">
                    <label class="pf-label" for="prov-date"><?= tr('expected_date') ?></label>
                    <input type="date" id="prov-date" name="expected_date" class="pf-input" required>
                </div>
                
                <button type="submit" class="pf-btn pf-btn-secondary">
                    <?= tr('btn_add') ?>
                </button>
            </div>
        </form>
    </div>

    <div class="budget-table-card table-responsive">
        <?php if (empty($provisions)): ?>
            <div class="optimization-hint" style="padding: 30px;">
                <p><?= tr('no_provisions') ?></p>
            </div>
        <?php else: ?>
            <table class="pf-table savings-table provisions-table">
                <thead>
                    <tr>
                        <th><?= tr('provision_label') ?></th>
                        <th style="width: 150px;"><?= tr('amount') ?></th>
                        <th style="width: 150px;"><?= tr('expected_date') ?></th>
                        <th style="width: 100px;"><?= tr('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($provisions as $p): ?>
                    <tr>
                        <td class="prov-title-cell">
                            <?= htmlspecialchars($p['title']) ?>
                        </td>
                        <td class="prov-amount-cell">
                            <?= number_format($p['amount'], 2, ',', ' ') ?> €
                        </td>
                        <td class="prov-date-cell">
                            <?= formatDate($p['expected_date']) ?>
                        </td>
                        <td class="text-right" style="padding-right: 15px;">
                            <button class="btn-icon-action delete btn-safe-click" title="<?= tr('btn_delete') ?>" onclick="deleteProvision(<?= $p['id'] ?>)">
                                🗑️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="optimizeModal" class="pf-modal">
    <div class="pf-modal-content provisions-modal-content">
        
        <div class="pf-modal-header">
            <h3 class="pf-modal-title">🧠 <?= tr('optimization_assistant_title') ?></h3>
            <button type="button" class="pf-modal-close" onclick="closeOptimizeModal()">&times;</button>
        </div>
        
        <form id="form-optimize" data-action="/modules/budget/includes/api/optimize-cashflow.php">
            <div id="dynamic-savings-inputs">
                <?php if (empty($configs)): ?>
                    <p class="text-danger font-bold pf-muted-tiny"><?= tr('budget_opti_no_config') ?></p>
                    <input type="number" step="0.01" min="0" name="savings[Global]" class="pf-input no-spinners" required>
                <?php else: ?>
                    <?php foreach ($configs as $person => $ecoBase): 
                        $debt = $debts[$person] ?? 0;
                    ?>
                    <div class="pf-form-group provision-person-card">
                        <label class="pf-label provision-person-header">
                            <strong class="text-main"><?= htmlspecialchars($person) ?></strong>
                            <span class="pf-muted-note">
                                <?php if ($debt > 0): ?>
                                    <span class="text-danger font-bold">(Dette en attente : <?= (float)$debt ?> €)</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <div class="provision-input-group">
                            <input type="number" step="0.01" min="0" name="savings[<?= htmlspecialchars($person) ?>]" class="pf-input no-spinners input-amount-success" value="<?= (float)$ecoBase ?>" required>
                            <span class="currency-symbol">€</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="submit" class="pf-btn pf-btn-primary btn-block">
                <?= tr('calculate') ?>
            </button>
        </form>

        <hr class="pf-divider">

        <div id="optimization-results">
            <p class="optimization-hint">
                Vérifiez vos apports théoriques ci-dessus pour calculer la stratégie de répartition (Clearing automatique si aucune dépense).
            </p>
        </div>
    </div>
</div>

<script>
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'btn_delete': <?= json_encode(tr('btn_delete')) ?>,
    'error_generic': <?= json_encode(tr('error_generic')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'bud_err_server': <?= json_encode(tr('bud_err_server')) ?>
};

// --- 2. GESTION DU FORMULAIRE D'AJOUT ---
const formAdd = document.getElementById('form-add-provision');
if (formAdd) {
    formAdd.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = '⏳';
        submitBtn.disabled = true;

        try {
            const result = await pachaFetch(this.getAttribute('data-action'), {
                method: 'POST',
                body: new FormData(this)
            });
            
            if (result.success) {
                window.location.reload();
            } else {
                alert(result.message || window.I18N['error_generic']);
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            alert(window.I18N['bud_err_tech']);
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    });
}

// --- 3. GESTION DE LA SUPPRESSION ---
async function deleteProvision(id) {
    if (!confirm(window.I18N['btn_delete'] + ' ?')) return;
    try {
        const result = await pachaFetch('/modules/budget/includes/api/delete-provision.php', { 
            method: "POST", 
            body: new URLSearchParams({ id: id }) 
        });
        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message);
        }
    } catch(err) {
        alert(window.I18N['bud_err_tech']);
    }
}

// --- 4. CONTROL DE LA MODALE D'OPTIMISATION ---
function openOptimizeModal() {
    document.getElementById('optimization-results').innerHTML = `
        <p class="optimization-hint">
            Vérifiez vos apports théoriques ci-dessus pour calculer la stratégie de répartition (Clearing automatique si aucune dépense).
        </p>`;
    document.getElementById('optimizeModal').classList.add('open');
    document.body.classList.add('no-scroll');
}

function closeOptimizeModal() {
    document.getElementById('optimizeModal').classList.remove('open');
    document.body.classList.remove('no-scroll');
}

window.onclick = function(event) {
    const modal = document.getElementById('optimizeModal');
    if (event.target == modal) {
        closeOptimizeModal();
    }
}

// --- 5. EXECUTION ET SUBMIT DE L'ALGORITHME (formOptimize) ---
const formOptimize = document.getElementById('form-optimize');
if (formOptimize) {
    formOptimize.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const resultsContainer = document.getElementById('optimization-results');
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        resultsContainer.innerHTML = '<div class="optimization-hint">⏳ Analyse des quinzaines bancaires...</div>';

        try {
            const result = await pachaFetch(this.getAttribute('data-action'), {
                method: 'POST',
                body: new FormData(this)
            });

            if (result.success) {
                resultsContainer.innerHTML = result.html;
            } else {
                resultsContainer.innerHTML = `<p class="text-danger font-bold text-center">${result.message}</p>`;
            }
        } catch (error) {
            console.error("Erreur d'optimisation :", error);
            resultsContainer.innerHTML = `<p class="text-danger font-bold text-center">${window.I18N['bud_err_tech']}</p>`;
        } finally {
            submitBtn.disabled = false;
        }
    });
}
</script>