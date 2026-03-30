<?php
// modules/holidays/views/detail.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) { echo "<div class='pf-section'><p>Erreur.</p></div>"; exit; }

$stmt = $pdo->prepare("
    SELECT h.*, 
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)) as total_cost,
           (SELECT COALESCE(SUM(ABS(amount)), 0) FROM pf_expenses WHERE holiday_id = h.id) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h WHERE h.id = ?
");
$stmt->execute([$id]);
$holiday = $stmt->fetch(PDO::FETCH_ASSOC);

// IMPORTANT : Le tri se fait maintenant sur "sort_order" pour que le glisser-déposer fonctionne
$stmtItems = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ? ORDER BY sort_order ASC, id ASC");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Récupération des favoris géographiques
$stmtFav = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'holiday_favorites'");
$favorites = json_decode($stmtFav->fetchColumn() ?: '[]', true);

// GROUPEMENT PAR LIEU (Pour la carte et l'affichage)
$steps = [];
$generalItems = [];

foreach ($items as $it) {
    if (!empty($it['location_name'])) {
        $loc = $it['location_name'];
        if (!isset($steps[$loc])) {
            $steps[$loc] = [
                'location_name' => $loc,
                'lat' => (float)$it['lat'],
                'lng' => (float)$it['lng'],
                'total_amount' => 0,
                'items' => []
            ];
        }
        $steps[$loc]['items'][] = $it;
        $steps[$loc]['total_amount'] += (float)$it['amount'];
    } else {
        $generalItems[] = $it;
    }
}
$mapPoints = array_values($steps);

// Calculs d'affichage (Dates & Finances)
$dateDisplay = htmlspecialchars($holiday['period_hint'] ?? '');
if (empty($dateDisplay) && $holiday['start_date']) {
    $dateDisplay = date('d/m/Y', strtotime($holiday['start_date']));
    if ($holiday['end_date']) $dateDisplay .= ' → ' . date('d/m/Y', strtotime($holiday['end_date']));
}

$cost = (float)$holiday['total_cost'];
$paid = (float)$holiday['total_paid'];
$saved = (float)$holiday['total_saved'];
$leftToPay = max(0, $cost - $paid);
$pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
$pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="pf-holidays-detail">
    
    <div class="hol-detail-header">
        <div class="hol-detail-title-group">
            <a href="?tab=list" class="pf-btn btn-secondary" style="padding:6px 12px; height:auto; width:auto; text-decoration:none;">◀ Retour</a>
            <h1><?= htmlspecialchars($holiday['title']) ?></h1>
            <span class="hol-badge-status"><?= strtoupper($holiday['status']) ?></span>
        </div>
        <button onclick='editHoliday(<?= htmlspecialchars(json_encode(['main' => $holiday, 'items' => $generalItems]), ENT_QUOTES, 'UTF-8') ?>)' class="pf-btn btn-secondary" style="width:auto; margin:0;">⚙️ Modifier les bases</button>
    </div>

    <div class="hol-summary-card">
        <div class="hol-summary-grid">
            <div class="hol-summary-item">
                <div class="hol-summary-label">Période</div>
                <div class="hol-summary-value"><?= $dateDisplay ?: 'À définir' ?></div>
            </div>
            <div class="hol-summary-item">
                <div class="hol-summary-label">Budget Food & Extras</div>
                <div class="hol-summary-value">🍔 <?= number_format($holiday['budget_food'], 0) ?> € | 🎁 <?= number_format($holiday['budget_extra'], 0) ?> €</div>
            </div>
            <div class="hol-summary-item" style="text-align:right;">
                <div class="hol-summary-label">Coût Total Estimé</div>
                <div class="hol-summary-value total"><?= number_format($cost, 0, ',', ' ') ?> €</div>
            </div>
        </div>

        <div style="width:100%; height:12px; background:#e2e8f0; border-radius:6px; margin-bottom:10px; display:flex; overflow:hidden;">
            <div style="width:<?= $pctPaid ?>%; background:#10b981;" title="Payé"></div>
            <div style="width:<?= $pctSaved ?>%; background:#3b82f6;" title="Financé (Provision)"></div>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
            <span style="color:#10b981; font-weight:600;">✓ Payé : <?= number_format($paid, 0, ',', ' ') ?> €</span>
            <span style="color:#3b82f6; font-weight:600;">💼 Provisionné : <?= number_format($saved, 0, ',', ' ') ?> €</span>
            <span style="color:#ef4444; font-weight:700;">⏳ Reste à payer : <?= number_format($leftToPay, 0, ',', ' ') ?> €</span>
        </div>
    </div>

    <div class="hol-layout-grid">
        
        <div class="hol-panel">
            <div class="hol-panel-header">
                <h3>🗺️ Itinéraire & Checkpoints</h3>
                <button class="pf-btn" onclick="openCheckpointModal('add')" style="padding:6px 12px; height:auto; width:auto; font-size:0.85rem; margin:0;">📍 Placer une étape</button>
            </div>
            <div id="tripMap" style="flex:1; width:100%; background:#f1f5f9;"></div>
        </div>

        <div class="hol-panel">
            <div class="hol-panel-header">
                <h3>📝 Détail des étapes</h3>
            </div>
            
            <div class="hol-panel-body">
                <?php if (empty($steps)): ?>
                    <p style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:40px;">Aucune étape planifiée.</p>
                <?php else: ?>
                    <?php foreach ($steps as $step): ?>
                        <div class="hol-checkpoint hol-checkpoint-draggable" draggable="true" data-location="<?= htmlspecialchars($step['location_name']) ?>">
                            <div class="hol-cp-header">
                                
                                <div class="hol-cp-info-group">
                                    <span style="color:#94a3b8; font-size:1.1rem; cursor:grab; user-select:none;">☰</span>
                                    <div class="hol-cp-title" onclick="panMapTo(<?= $step['lat'] ?>, <?= $step['lng'] ?>)" title="<?= htmlspecialchars($step['location_name']) ?>">
                                        📍 <?= htmlspecialchars($step['location_name']) ?>
                                    </div>
                                </div>

                                <div class="hol-cp-actions-group">
                                    <div class="hol-cp-total"><?= number_format($step['total_amount'], 2, ',', ' ') ?> €</div>
                                    <button onclick='openCheckpointModal("edit", <?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small" title="Modifier">✏️</button>
                                </div>

                            </div>

                            <div class="hol-cp-body">
                                <?php 
                                    $visibleItemsCount = 0;
                                    foreach ($step['items'] as $it): 
                                        if ($it['name'] === 'PF_TECHNICAL_POINT') continue; 
                                        $visibleItemsCount++;
                                        $icon = match($it['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                ?>
                                        <div class="hol-expense-line">
                                            <span style="color:#475569;"><?= $icon ?> <?= htmlspecialchars($it['name']) ?></span>
                                            <span>
                                                <strong style="color:var(--text-main);"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                                <span style="margin-left:5px; color:<?= $it['is_paid'] ? '#10b981' : '#f59e0b' ?>;"><?= $it['is_paid'] ? '✓' : '⏳' ?></span>
                                            </span>
                                        </div>
                                <?php endforeach; ?>
                                
                                <?php if ($visibleItemsCount === 0): ?>
                                    <div style="font-size:0.8rem; color:var(--text-muted); font-style:italic; padding: 5px 0;">
                                        📍 Point de passage (Aucune dépense)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($holiday['notes'])): ?>
            <div class="hol-notes-panel">
                <h4 style="margin:0 0 5px 0; font-size:0.85rem; color:#d97706;">Notes du voyage :</h4>
                <p style="margin:0; font-size:0.85rem; color:#92400e; white-space:pre-wrap;"><?= htmlspecialchars($holiday['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="checkpointModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="cpModalTitle" style="margin:0;">📍 Placer une étape</h3>
            <button type="button" onclick="document.getElementById('checkpointModal').style.display='none'" style="border:none; background:none; font-size:1.8rem; cursor:pointer;">&times;</button>
        </div>
        
        <div id="cpSearchBlock" style="margin-bottom:20px;">
            <?php if (!empty($favorites)): ?>
            <div style="margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php foreach($favorites as $fav): ?>
                    <button type="button" class="pf-btn btn-secondary" style="padding:4px 10px; font-size:0.8rem; height:auto; width:auto; border-radius:20px; background:#f0f9ff; color:#0369a1; border-color:#bae6fd;" onclick="selectPlace(<?= $fav['lat'] ?>, <?= $fav['lng'] ?>, '<?= htmlspecialchars(addslashes($fav['name'])) ?>')">
                        ⭐ <?= htmlspecialchars($fav['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <label class="pf-label">Rechercher un lieu géographique</label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchPlaceInput" class="pf-input" placeholder="Ex: Paris, Ibis Barcelone..." onkeypress="if(event.key === 'Enter') { searchPlace(); return false; }">
                <button type="button" class="pf-btn btn-secondary" onclick="searchPlace()" style="width:auto; margin:0;">🔍</button>
            </div>
            <div id="searchResults" style="margin-top:10px; max-height:200px; overflow-y:auto; display:flex; flex-direction:column; gap:5px;"></div>
        </div>

        <form action="/modules/holidays/includes/api/save_checkpoint.php" method="POST" id="formCheckpoint" style="display:none; border-top:1px solid #e2e8f0; padding-top:20px;">
            <input type="hidden" name="holiday_id" value="<?= $id ?>">
            <input type="hidden" name="old_location_name" id="cp_old_name">
            <input type="hidden" name="lat" id="cp_lat">
            <input type="hidden" name="lng" id="cp_lng">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label">Nom de l'étape (Ce qui s'affichera sur la carte)</label>
                <input type="text" name="location_name" id="cp_name" class="pf-input" style="font-weight:bold; color:var(--primary);" required>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <label class="pf-label" style="margin:0;">Dépenses prévues à cette étape</label>
                <button type="button" class="pf-btn btn-secondary" onclick="addCpExpenseLine()" style="padding:4px 8px; font-size:0.8rem; height:auto; width:auto; margin:0;">+ Ajouter une dépense</button>
            </div>

            <div id="cpExpensesContainer" style="margin-bottom:15px; display:flex; flex-direction:column; gap:10px; max-height:300px; overflow-y:auto;">
                </div>

            <div style="margin-bottom: 20px; padding-left: 5px;">
                <label style="display:flex; align-items:center; cursor:pointer; font-size:0.85rem; color:#475569;">
                    <input type="checkbox" name="save_favorite" value="1" style="margin-right:8px; cursor:pointer; width:16px; height:16px;">
                    ⭐ Sauvegarder cette adresse dans mes favoris rapides
                </label>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e2e8f0; padding-top:15px;">
                <div>
                    <button type="button" onclick="deleteCheckpoint()" id="btnDeleteCp" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; display:none; width:auto; margin:0;">🗑️ Supprimer l'étape</button>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="document.getElementById('checkpointModal').style.display='none'" class="pf-btn btn-secondary" style="width:auto; margin:0;">Annuler</button>
                    <button type="submit" class="pf-btn" style="width:auto; margin:0;">Enregistrer l'étape</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
    const MAP_POINTS = <?= json_encode($mapPoints) ?>;
</script>