<?php
// 1. Gestion des propriétaires à afficher
$requestedOwner = $_GET['owner'] ?? 'Nens'; 

// Si l'onglet est "Nens", on affiche Pol et Pep. Sinon, on affiche juste la personne demandée.
$ownersToDisplay = ($requestedOwner === 'Nens') ? ['Pol', 'Pep'] : [$requestedOwner];
?>

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
                // On détermine la classe CSS basée sur le nom (pol ou pep)
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
            <button onclick="openSavingsModal('add', '<?= $currentOwner ?>')" class="pf-btn">
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
<table class="pf-table savings-table nens-table theme-<?= strtolower($currentOwner) ?>" style="margin-top:0; box-shadow:none; border-radius:16px;">            <thead>
                <tr>
                    <th class="sticky-col" style="background:#f8fafc;">Poste / Mois</th>
                    <?php foreach ($months as $month): ?>
                        <th>
                            <div class="month-header-container">
                                <span class="month-name"><?= date('M Y', strtotime($month)) ?></span>
                                <div class="month-actions">
                                    <button class="btn-icon-small" title="Modifier"
                                            onclick='editMonth("<?= $month ?>", "<?= $currentOwner ?>", <?= json_encode($data[$month] ?? []) ?>, <?= json_encode($allCategories) ?>)'>
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
    <?php endforeach; ?> </div>

<div id="savingsModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 500px;">
        <h3 id="savingsModalTitle" class="pf-modal-title">Saisir le mois</h3>
        
        <form action="/modules/budget/includes/api/save-savings.php" method="POST" id="savingsForm">
            <input type="hidden" name="owner" id="sav_owner">
            
            <input type="hidden" name="redirect_tab" value="<?= $requestedOwner ?>"> 

            <div class="form-group">
                <label class="pf-label">Mois concerné</label>
                <input type="date" name="month_date" id="sav_date" required class="pf-input">
            </div>

            <div class="form-group">
                <label class="pf-label">Total (€)</label>
                <input type="number" step="0.01" name="values[TOTAL_BANQUE]" id="sav_total" required class="pf-input" style="font-weight:bold; color:#2563eb;">
            </div>

            <div class="separator" style="margin: 20px 0; border-bottom: 1px solid #eee;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h4 style="margin:0; font-size:0.9rem; color:#64748b;">Ventilation</h4>
                <button type="button" class="btn-icon" onclick="addNewLine()" title="Ajouter une ligne" style="background:#e2e8f0;">＋</button>
            </div>

            <div id="linesContainer" style="max-height: 300px; overflow-y: auto; padding-right:5px; display:flex; flex-direction:column; gap:8px;"></div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('savingsModal').style.display='none'" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>