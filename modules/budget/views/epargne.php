<?php
// modules/budget/views/epargne.php

// 1. Gestion des propriétaires à afficher
$requestedOwner = $_GET['owner'] ?? 'Nens'; 

// Si l'onglet est "Nens", on affiche Pol et Pep. Sinon, on affiche juste la personne demandée.
$ownersToDisplay = ($requestedOwner === 'Nens') ? ['Pol', 'Pep'] : [$requestedOwner];
?>

<style>
/* Cacher les flèches haut/bas des champs de type number */
input[type="number"].no-spinners::-webkit-inner-spin-button, 
input[type="number"].no-spinners::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
input[type="number"].no-spinners {
    -moz-appearance: textfield; /* Firefox */
}
</style>

<div class="budget-view">
    
    <div class="view-header">
        <div class="owner-tabs">
            <a href="?tab=epargne&owner=Alex" class="owner-tab <?= $requestedOwner === 'Alex' ? 'active' : '' ?>">Alex</a>
            <a href="?tab=epargne&owner=Laia" class="owner-tab <?= $requestedOwner === 'Laia' ? 'active' : '' ?>">Laia</a>
            <a href="?tab=epargne&owner=Nens" class="owner-tab <?= $requestedOwner === 'Nens' ? 'active' : '' ?>">Nens 👶</a>
        </div>
    </div>

    <?php foreach ($ownersToDisplay as $currentOwner): 
        // --- Récupération des données pour $currentOwner ---
        $stmt = $pdo->prepare("SELECT month_date, category, amount FROM pf_savings WHERE owner = ? ORDER BY month_date DESC");
        $stmt->execute([$currentOwner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $months = [];
        $allCategories = [];

        foreach ($rows as $row) {
            $m = $row['month_date'];
            $cat = $row['category'];
            $val = $row['amount'];
            $data[$m][$cat] = $val;
            if (!in_array($m, $months)) $months[] = $m;
            if ($cat !== 'TOTAL_BANQUE' && !in_array($cat, $allCategories)) $allCategories[] = $cat;
        }
        $months = array_slice($months, 0, 7); // 7 derniers mois
        sort($allCategories);
    ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: <?= ($requestedOwner === 'Nens' && $currentOwner !== 'Pol') ? '40px' : '0' ?>;">        
        <div style="flex-grow: 1;">
            <?php if ($requestedOwner === 'Nens'): 
                $themeClass = 'theme-' . strtolower($currentOwner);
            ?>
                <h3 class="nens-title <?= $themeClass ?>" style="margin:0; font-size:1.2rem;">
                    <?= $currentOwner ?>
                </h3>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (!empty($months)): ?>
                <button onclick="duplicateLastMonth('<?= $months[0] ?>', '<?= $currentOwner ?>')" class="pf-btn btn-secondary">
                    🔁 +1 Mois
                </button>
            <?php endif; ?>
            <button onclick="openCustomSavingsModal('<?= $currentOwner ?>')" class="pf-btn">
                ＋ Saisir un mois
            </button>
        </div>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0;">
        <?php if (empty($months)): ?>
            <div style="padding: 30px; text-align: center; color: #64748b;">
                <p>Aucune donnée pour <?= htmlspecialchars($currentOwner) ?>.</p>
            </div>
        <?php else: ?>
            <table class="pf-table savings-table nens-table theme-<?= strtolower($currentOwner) ?>" style="margin-top:0; box-shadow:none; border-radius:16px;">            
                <thead>
                    <tr>
                        <th class="sticky-col" style="background:#f8fafc;">Poste / Mois</th>
                        <?php foreach ($months as $month): ?>
                            <th>
                                <div class="month-header-container">
                                    <span class="month-name"><?= date('M Y', strtotime($month)) ?></span>
                                    <div class="month-actions">
                                        <button class="btn-icon-small" title="Modifier"
                                                data-json="<?= htmlspecialchars(json_encode($data[$month] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick='editCustomSavingsMonth("<?= $month ?>", "<?= $currentOwner ?>", JSON.parse(this.getAttribute("data-json")))'>
                                            ✏️
                                        </button>
                                        <button class="btn-icon-small" title="Supprimer"
                                                onclick="deleteEntireMonth('<?= $month ?>', '<?= $currentOwner ?>')"
                                                style="color: #ef4444; border-color: #fca5a5; background: #fef2f2;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="sticky-col"><strong>Total</strong></td>
                        <?php foreach ($months as $month): ?>
                            <td class="text-center font-bold" style="color: #2563eb;">
                                <?= number_format($data[$month]['TOTAL_BANQUE'] ?? 0, 0, ',', ' ') ?> €
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($allCategories as $cat): ?>
                    <tr>
                        <td class="sticky-col"><?= htmlspecialchars($cat) ?></td>
                        <?php foreach ($months as $month): $amount = $data[$month][$cat] ?? 0; ?>
                            <td class="text-center text-muted">
                                <?php if ($amount != 0): ?>
                                    <div class="cell-content">
                                        <span>- <?= number_format($amount, 0, ',', ' ') ?> €</span>
                                        <button class="btn-cell-delete" 
                                                onclick="deleteSavingsEntry('<?= $month ?>', '<?= htmlspecialchars($cat, ENT_QUOTES) ?>', '<?= $currentOwner ?>')">
                                            &times;
                                        </button>
                                    </div>
                                <?php else: ?> - <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="row-extres">
                        <td class="sticky-col"><strong>Extra</strong></td>
                        <?php foreach ($months as $month): 
                            $total = $data[$month]['TOTAL_BANQUE'] ?? 0;
                            $sum = 0;
                            foreach ($allCategories as $cat) $sum += ($data[$month][$cat] ?? 0);
                            $extra = $total - $sum;
                        ?>
                            <td class="text-center font-bold" style="color: <?= $extra >= 0 ? '#10b981' : '#ef4444' ?>">
                                <?= number_format($extra, 0, ',', ' ') ?> €
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?> 
</div>

<div id="savingsModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px; width: 95%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="savingsModalTitle" class="pf-modal-title" style="margin:0;">Saisir le mois</h3>
            <button type="button" onclick="document.getElementById('savingsModal').style.display='none'" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="/modules/budget/includes/api/save-savings.php" method="POST" id="savingsForm">
            <input type="hidden" name="owner" id="sav_owner">
            <input type="hidden" name="redirect_tab" id="redirect_tab" value="<?= htmlspecialchars($requestedOwner) ?>"> 

            <div style="display:flex; gap:15px; margin-bottom:20px;">
                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label">Mois concerné</label>
                    <input type="date" name="month_date" id="sav_date" required class="pf-input">
                </div>

                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label">Total en Banque (€)</label>
                    <input type="number" step="0.01" name="values[TOTAL_BANQUE]" id="sav_total" required class="pf-input no-spinners" style="font-weight:bold; color:#2563eb;">
                </div>
            </div>

            <div class="separator" style="margin: 20px 0; border-bottom: 1px solid #e2e8f0;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h4 style="margin:0; font-size:1rem; color:#1e293b;">Ventilation</h4>
                    <span style="font-size:0.8rem; color:#64748b;">Utilisez l'ajustement (+/-) pour recalculer automatiquement.</span>
                </div>
                <button type="button" class="pf-btn btn-secondary" onclick="addCustomEpargneLine()" style="padding:4px 10px; height:auto; width:auto; font-size:0.9rem;">＋ Ligne</button>
            </div>

            <div style="display:flex; gap:10px; padding:0 5px 5px 5px; font-size:0.8rem; color:#64748b; font-weight:600;">
                <div style="flex:2;">Catégorie</div>
                <div style="width:100px;">Actuel</div>
                <div style="width:90px;">Ajust (+/-)</div>
                <div style="width:100px;">Nouveau</div>
                <div style="width:28px;"></div>
            </div>

            <div id="linesContainer" style="max-height: 350px; overflow-y: auto; padding-right:5px; display:flex; flex-direction:column; gap:10px;">
                </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('savingsModal').style.display='none'" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// --- LOGIQUE MODALE EPARGNE ---

function addCustomEpargneLine(catName = '', amount = '') {
    const container = document.getElementById('linesContainer');
    // Si amount est vide, on met '0.00', sinon on formate
    const baseAmount = (amount !== '' && amount !== null) ? parseFloat(amount).toFixed(2) : '0.00';
    
    // Nom du champ input pour le serveur
    const inputName = catName ? `values[${catName}]` : '';

    const html = `
        <div class="ventilation-line" style="display:flex; gap:10px; align-items:center; background:#f8fafc; padding:8px; border-radius:8px; border:1px solid #e2e8f0;">
            <div style="flex:2;">
                <input type="text" class="pf-input cat-name-input" value="${catName}" placeholder="Nom (ex: Vacances)" oninput="updateCustomFieldName(this)" style="padding:6px; font-size:0.9rem;" required>
            </div>
            
            <div style="width:100px;">
                <input type="number" step="0.01" class="pf-input base-amount no-spinners" value="${baseAmount}" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; background:#fff;">
            </div>

            <div style="width:90px;">
                <input type="number" step="0.01" class="pf-input adjustment-amount no-spinners" placeholder="+ / -" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; color:#f59e0b; font-weight:bold;">
            </div>

            <div style="width:100px;">
                <input type="number" step="0.01" name="${inputName}" class="pf-input final-amount no-spinners" value="${baseAmount}" style="padding:6px; font-size:0.9rem; font-weight:bold; background:#e0f2fe; border-color:#bae6fd; color:#0369a1;" readonly>
            </div>
            
            <button type="button" onclick="this.parentElement.remove()" style="width:28px; height:28px; border:none; background:#fee2e2; color:#ef4444; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-weight:bold;">&times;</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function updateCustomFieldName(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const finalInput = line.querySelector('.final-amount');
    const newName = inputElement.value.trim();
    
    // Mise à jour dynamique de l'attribut name pour que PHP le reçoive correctement
    if (newName) {
        finalInput.name = `values[${newName}]`;
    } else {
        finalInput.name = ''; // Si vide, ne sera pas envoyé
    }
}

function recalculateCustomLine(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const baseInput = line.querySelector('.base-amount');
    const adjInput = line.querySelector('.adjustment-amount');
    const finalInput = line.querySelector('.final-amount');
    
    const base = parseFloat(baseInput.value) || 0;
    const adj = parseFloat(adjInput.value) || 0;
    
    finalInput.value = (base + adj).toFixed(2);
}

// Fonction d'ouverture pour ÉDITION
function editCustomSavingsMonth(monthDate, owner, rowData) {
    document.getElementById('sav_owner').value = owner;
    document.getElementById('sav_date').value = monthDate;
    
    // Formatage date pour le titre
    const dateObj = new Date(monthDate);
    const monthName = dateObj.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('savingsModalTitle').innerText = "Modifier : " + monthName + " (" + owner + ")";
    
    // Remplissage Total Banque
    document.getElementById('sav_total').value = rowData['TOTAL_BANQUE'] || '';

    // Remplissage des lignes
    const container = document.getElementById('linesContainer');
    container.innerHTML = '';

    // On parcourt les données JSON reçues
    for (const [cat, val] of Object.entries(rowData)) {
        if (cat !== 'TOTAL_BANQUE') {
            addCustomEpargneLine(cat, val);
        }
    }
    
    // Si aucune ligne de détail, on en ajoute une vide
    if (container.children.length === 0) {
        addCustomEpargneLine();
    }

    document.getElementById('savingsModal').style.display = 'flex';
}

// Fonction d'ouverture pour AJOUT (Nouveau mois)
function openCustomSavingsModal(owner) {
    document.getElementById('sav_owner').value = owner;
    document.getElementById('sav_date').value = '';
    document.getElementById('sav_total').value = '';
    
    document.getElementById('savingsModalTitle').innerText = "Saisir un mois (" + owner + ")";
    
    const container = document.getElementById('linesContainer');
    container.innerHTML = '';
    addCustomEpargneLine(); 
    
    document.getElementById('savingsModal').style.display = 'flex';
}

// Fermeture modale au clic extérieur
window.onclick = function(event) {
    const modal = document.getElementById('savingsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// --- SOUMISSION DU FORMULAIRE (AJAX) ---
const savingsForm = document.getElementById('savingsForm');
if (savingsForm) {
    savingsForm.addEventListener('submit', function(e) {
        e.preventDefault(); // On bloque le rechargement standard

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = "Enregistrement...";
        submitBtn.disabled = true;

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // On lit la réponse texte (parfois PHP renvoie du HTML ou vide)
        .then(text => {
            // Rechargement forcé pour voir les modifications
            window.location.reload(); 
        })
        .catch(error => {
            console.error("Erreur:", error);
            alert("Une erreur technique est survenue.");
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        });
    });
}

// ============================================================================
// ACTIONS DE SUPPRESSION / DUPLICATION
// ============================================================================

function deleteEntireMonth(monthDate, owner) {
    if (!confirm(`Supprimer TOUT le mois de ${monthDate} pour ${owner} ?`)) return;

    const formData = new FormData();
    formData.append("action", "delete_month_global"); // Action gérée par save-savings.php
    formData.append("month_date", monthDate);
    formData.append("owner", owner);

    fetch("/modules/budget/includes/api/save-savings.php", {
        method: "POST",
        body: formData,
    })
    .then(() => window.location.reload())
    .catch(err => alert("Erreur lors de la suppression."));
}

function deleteSavingsEntry(monthDate, category, owner) {
    if (!confirm(`Supprimer la ligne "${category}" ?`)) return;

    const formData = new FormData();
    formData.append("action", "delete_entry");
    formData.append("month_date", monthDate);
    formData.append("category", category);
    formData.append("owner", owner);

    fetch("/modules/budget/includes/api/save-savings.php", {
        method: "POST",
        body: formData,
    })
    .then(() => window.location.reload())
    .catch(err => alert("Erreur lors de la suppression de la ligne."));
}

function duplicateLastMonth(lastMonthDate, owner) {
    // Calcul du mois suivant
    let dateObj = new Date(lastMonthDate);
    dateObj.setMonth(dateObj.getMonth() + 1);
    // Astuce pour gérer les fuseaux horaires et garder YYYY-MM-01
    let year = dateObj.getFullYear();
    let month = String(dateObj.getMonth() + 1).padStart(2, '0');
    let nextMonthStr = `${year}-${month}-01`;

    let newTotal = prompt(
        `Dupliquer les données de ${lastMonthDate} vers ${nextMonthStr} ?\n\nNouveau TOTAL en banque (€) :`,
        ""
    );

    if (newTotal !== null && newTotal.trim() !== "") {
        const formData = new FormData();
        formData.append("action", "duplicate_month");
        formData.append("source_date", lastMonthDate);
        formData.append("target_date", nextMonthStr);
        formData.append("new_total", newTotal);
        formData.append("owner", owner);

        fetch("/modules/budget/includes/api/save-savings.php", {
            method: "POST",
            body: formData,
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert("Erreur serveur : " + (d.error || "Inconnue"));
        })
        .catch(err => {
            console.error(err);
            alert("Erreur réseau lors de la duplication.");
        });
    }
}
</script>