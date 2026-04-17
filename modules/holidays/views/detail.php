<?php
// modules/holidays/views/detail.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) { 
    echo "<div class='pf-section'><p>".tr('err_invalid_holiday_id')."</p></div>"; 
    exit; 
}

// Récupération des données du voyage
$stmt = $pdo->prepare("
    SELECT h.*, 
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)) as total_cost,
           ((SELECT COALESCE(SUM(ABS(amount)), 0) FROM pf_expenses WHERE holiday_id = h.id) + (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND is_paid = 1)) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h WHERE h.id = ?
");
$stmt->execute([$id]);
$holiday = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupération des items (étapes et frais généraux)
$stmtItems = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ? ORDER BY sort_order ASC, id ASC");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Favoris pour le géocodage
$stmtFav = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'holiday_favorites'");
$favorites = json_decode($stmtFav->fetchColumn() ?: '[]', true);

$steps = [];
$generalItems = [];

foreach ($items as $it) {
    if ($it['location_name'] !== null) {
        $orderKey = $it['sort_order']; 
        if (!isset($steps[$orderKey])) {
            $steps[$orderKey] = [
                'location_name' => $it['location_name'],
                'lat' => (float)$it['lat'],
                'lng' => (float)$it['lng'],
                'sort_order' => $it['sort_order'],
                'step_start_date' => $it['step_start_date'],
                'step_end_date' => $it['step_end_date'],
                'is_return' => (int)$it['is_return'], 
                'total_amount' => 0,
                'items' => []
            ];
        }
        $steps[$orderKey]['items'][] = $it;
        $steps[$orderKey]['total_amount'] += (float)$it['amount'];
    } else {
        $generalItems[] = $it;
    }
}
ksort($steps); 
$mapPoints = array_values($steps);

// Affichage de la date
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
    
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="?tab=list" class="pf-btn btn-secondary pf-btn-small" style="width: fit-content; text-decoration: none;"><?= tr('btn_back') ?></a>
            <div style="display: flex; align-items: center; gap: 15px;">
                <h1 style="margin: 0; font-size: 1.8rem;"><?= htmlspecialchars($holiday['title']) ?></h1>
                <span class="hol-badge-status"><?= tr('hdl_status_' . $holiday['status']) ?></span>
            </div>
        </div>

        <div>
            <script id="holidayDataJson" type="application/json">
                <?= json_encode(['main' => $holiday, 'items' => $generalItems], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
            </script>
            <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="editHoliday(JSON.parse(document.getElementById('holidayDataJson').textContent))">
                <?= tr('btn_edit_bases') ?>
            </button>
        </div>
    </div>

    <div class="hol-summary-card">
        <div class="hol-summary-grid">
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_label_period') ?></div>
                <div class="hol-summary-value"><?= $dateDisplay ?: tr('hdl_dates_to_define') ?></div>
            </div>
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_label_budget_food_extras') ?></div>
                <div class="hol-summary-value">🍔 <?= number_format($holiday['budget_food'], 0) ?> € | 🎁 <?= number_format($holiday['budget_extra'], 0) ?> €</div>
            </div>
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_total_budget') ?></div>
                <div class="hol-summary-value total"><?= number_format($cost, 0, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="hol-progress-bar">
            <div class="hol-progress-paid" style="width:<?= $pctPaid ?>%;" title="<?= tr('hdl_paid') ?>"></div>
            <div class="hol-progress-saved" style="width:<?= $pctSaved ?>%;" title="<?= tr('hdl_saved') ?>"></div>
        </div>
        <div class="hol-progress-labels">
            <span class="hol-label-paid">✓ <?= tr('hdl_paid') ?> : <?= number_format($paid, 0, ',', ' ') ?> €</span>
            <span class="hol-label-saved">💼 <?= tr('hdl_saved') ?> : <?= number_format($saved, 0, ',', ' ') ?> €</span>
            <span class="hol-label-left">⏳ <?= tr('hdl_left_to_pay') ?> : <?= number_format($leftToPay, 0, ',', ' ') ?> €</span>
        </div>
    </div>

    <div class="hol-layout-grid">
        <div class="hol-panel">
            <div class="hol-panel-header" style="flex-wrap: wrap; gap: 10px;">
                <div class="hol-panel-header-group">
                    <h3 style="margin:0;"><?= tr('hdl_map_itinerary') ?></h3>
                    <div class="hol-map-legend">
                        <span class="hol-legend-item" title="<?= tr('hdl_outbound') ?>"><span class="hol-legend-color aller"></span> <?= tr('hdl_outbound') ?></span>
                        <span class="hol-legend-item" title="<?= tr('hdl_return') ?>"><span class="hol-legend-color retour"></span> <?= tr('hdl_return') ?></span>
                    </div>
                </div>
                <button class="pf-btn pf-btn-small" onclick="openCheckpointModal('add')"><?= tr('hdl_btn_add_step') ?></button>
            </div>
            <div id="tripMap" style="flex:1; width:100%; background:#f1f5f9;"></div>
        </div>

        <div class="hol-panel">
            <div class="hol-panel-header">
                <h3 style="margin:0;"><?= tr('hdl_step_details') ?></h3>
            </div>
            
            <div class="hol-panel-body">
                <?php if (empty($steps)): ?>
                    <p style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:40px;"><?= tr('hdl_no_steps') ?></p>
                <?php else: ?>

                    <?php if (!empty($generalItems) || $holiday['budget_food'] > 0 || $holiday['budget_extra'] > 0): ?>
                        <div class="hol-checkpoint" style="border-left-color: #64748b; background: #f8fafc; margin-bottom: 20px;">
                            <div class="hol-cp-header" style="background: transparent;">
                                <div class="hol-cp-info-group">
                                    <span style="font-size:1.4rem;">🌍</span>
                                    <div class="hol-cp-title"><?= tr('hdl_general_costs') ?></div>
                                </div>
                                <?php 
                                    $generalTotal = $holiday['budget_food'] + $holiday['budget_extra'];
                                    foreach ($generalItems as $gi) { $generalTotal += $gi['amount']; }
                                ?>
                                <div class="hol-cp-actions-group">
                                    <div class="hol-cp-total"><?= number_format($generalTotal, 2, ',', ' ') ?> €</div>
                                    <button onclick='editHoliday(JSON.parse(document.getElementById("holidayDataJson").textContent))' class="btn-icon-small" title="<?= tr('btn_edit') ?>">⚙️</button>
                                </div>
                            </div>

                            <div class="hol-cp-body">
                                <?php if ($holiday['budget_food'] > 0): ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name" style="color:#64748b;">🍔 <?= tr('hdl_food_bev') ?></span>
                                        <span><strong class="hol-expense-amount"><?= number_format($holiday['budget_food'], 2, ',', ' ') ?> €</strong><span class="status-pending">⏳</span></span>
                                    </div></div>
                                <?php endif; ?>
                                <?php if ($holiday['budget_extra'] > 0): ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name" style="color:#64748b;">🎁 <?= tr('hdl_extras') ?></span>
                                        <span><strong class="hol-expense-amount"><?= number_format($holiday['budget_extra'], 2, ',', ' ') ?> €</strong><span class="status-pending">⏳</span></span>
                                    </div></div>
                                <?php endif; ?>

                                <?php foreach ($generalItems as $it): 
                                    $icon = match($it['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name"><?= $icon ?> <?= htmlspecialchars($it['name']) ?></span>
                                        <span>
                                            <strong class="hol-expense-amount"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                            <span class="<?= $it['is_paid'] ? 'status-paid' : 'status-pending' ?>"><?= $it['is_paid'] ? '✓' : '⏳' ?></span>
                                        </span>
                                    </div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($steps as $step): ?>
                        <div id="step-card-<?= $step['sort_order'] ?>" class="hol-checkpoint hol-checkpoint-draggable" draggable="true" data-location="<?= htmlspecialchars($step['location_name']) ?>">
                            <div class="hol-cp-header">
                                <div class="hol-cp-info-group">
                                    <span style="color:#94a3b8; font-size:1.1rem; cursor:grab; user-select:none;">☰</span>
                                    <div class="hol-cp-title" onclick="panMapTo(<?= $step['lat'] ?>, <?= $step['lng'] ?>)" title="<?= htmlspecialchars($step['location_name']) ?>">
                                        📍 <?= htmlspecialchars($step['location_name']) ?>
                                        <?php if (!empty($step['is_return'])): ?>
                                            <span style="background: #fff7ed; color: #ea580c; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 5px; border: 1px solid #ffedd5; vertical-align: middle;">🏁 <?= tr('hdl_return') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($step['step_start_date']) && !empty($step['step_end_date'])): ?>
                                            <div style="font-size:0.75rem; color:#64748b; font-weight:normal; margin-top:2px; display: flex; align-items: center; gap: 10px;">
                                                <span>
                                                    <?= tr('hdl_from') ?> <?= date('d/m', strtotime($step['step_start_date'])) ?> <?= tr('hdl_to') ?> <?= date('d/m', strtotime($step['step_end_date'])) ?>
                                                </span>
                                                <div class="hol-weather-info"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="hol-cp-actions-group">
                                    <div class="hol-cp-total"><?= number_format($step['total_amount'], 2, ',', ' ') ?> €</div>
                                    <button onclick='openPlanningModal(<?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small" title="<?= tr('hdl_view_planning') ?>">📅</button>
                                    <button onclick='openCheckpointModal("edit", <?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small" title="<?= tr('btn_edit') ?>">✏️</button>
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
                                        <div class="hol-expense-wrapper">
                                            <div class="hol-expense-main">
                                                <span class="hol-expense-name"><?= $icon ?> <?= htmlspecialchars($it['name']) ?></span>
                                                <span>
                                                    <strong class="hol-expense-amount"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                                    <span class="<?= $it['is_paid'] ? 'status-paid' : 'status-pending' ?>" title="<?= $it['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?>"><?= $it['is_paid'] ? '✓' : '⏳' ?></span>
                                                </span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                                
                                <?php if ($visibleItemsCount === 0): ?>
                                    <div class="hol-empty-step">📍 <?= tr('hdl_passing_point') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($holiday['notes'])): ?>
    <div class="hol-summary-card" style="margin-top: 24px; padding: 25px; border-left: 5px solid #f59e0b;">
        <h3 style="margin: 0 0 15px 0; font-size: 1.2rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            📝 <?= tr('hdl_label_notes') ?>
        </h3>
        <div style="font-size: 0.95rem; color: #334155; white-space: pre-wrap; line-height: 1.6;">
            <?= htmlspecialchars($holiday['notes']) ?>
        </div>
    </div>
    <?php endif; ?>

</div> 

<div id="checkpointModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="cpModalTitle" style="margin:0;">📍 <?= tr('hdl_btn_add_step') ?></h3>
            <button type="button" onclick="closeCheckpointModal()" class="pf-modal-close">&times;</button>
        </div>
        
        <div id="cpSearchBlock" style="margin-bottom:20px;">
            <?php if (!empty($favorites)): ?>
            <div style="margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php foreach($favorites as $fav): ?>
                    <button type="button" class="pf-btn btn-secondary" style="padding:4px 10px; font-size:0.8rem; border-radius:20px;" onclick="selectPlace(<?= $fav['lat'] ?>, <?= $fav['lng'] ?>, '<?= htmlspecialchars(addslashes($fav['name'])) ?>')">
                        ⭐ <?= htmlspecialchars($fav['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <label class="pf-label"><?= tr('hdl_search_location') ?></label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchPlaceInput" class="pf-input" placeholder="<?= tr('hdl_ph_search') ?>" onkeypress="if(event.key === 'Enter') { searchPlace(); return false; }">
                <button type="button" class="pf-btn btn-secondary" onclick="searchPlace()">🔍</button>
            </div>
            <div id="searchResults" style="margin-top:10px; max-height:200px; overflow-y:auto;"></div>
        </div>

        <form action="/modules/holidays/includes/api/save_checkpoint.php" method="POST" id="formCheckpoint" style="display:none; border-top:1px solid #e2e8f0; padding-top:20px;">
            <input type="hidden" name="holiday_id" value="<?= $id ?>">
            <input type="hidden" name="old_location_name" id="cp_old_name">
            <input type="hidden" name="lat" id="cp_lat">
            <input type="hidden" name="lng" id="cp_lng">
            <input type="hidden" name="old_sort_order" id="cp_old_sort_order">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('hdl_label_step_name') ?></label>
                <input type="text" name="location_name" id="cp_name" class="pf-input" style="font-weight:bold; color:var(--primary);" required>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px; background:#f8fafc; padding:12px; border-radius:8px;">
                <div class="form-group" style="flex:1;">
                    <label class="pf-label"><?= tr('hdl_label_arrival') ?></label>
                    <input type="date" name="step_start_date" id="cp_start_date" class="pf-input">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="pf-label"><?= tr('hdl_label_departure') ?></label>
                    <input type="date" name="step_end_date" id="cp_end_date" class="pf-input">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:flex; align-items:center; cursor:pointer; color:#ea580c; font-weight:600;">
                    <input type="checkbox" name="is_return" id="cp_is_return" value="1" style="margin-right:8px;">
                    🏁 <?= tr('hdl_return') ?>
                </label>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <label class="pf-label" style="margin:0;"><?= tr('hdl_planned_expenses') ?></label>
                <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="addCpExpenseLine()"><?= tr('hdl_btn_add_expense') ?></button>
            </div>

            <div id="cpExpensesContainer" style="margin-bottom:15px;"></div>

            <div style="margin-bottom: 20px;">
                <label style="display:flex; align-items:center; cursor:pointer; font-size:0.85rem; color:#475569;">
                    <input type="checkbox" name="save_favorite" value="1" style="margin-right:8px;">
                    ⭐ <?= tr('hdl_save_fav') ?>
                </label>
            </div>

            <div class="modal-footer" style="padding-top:15px; border-top:1px solid #e2e8f0;">
                <button type="button" onclick="deleteCheckpoint()" id="btnDeleteCp" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; display:none;"><?= tr('btn_delete') ?></button>
                <button type="button" onclick="closeCheckpointModal()" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="planningModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 550px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="planningModalTitle" style="margin:0; color:var(--primary);">📅 <?= tr('hdl_planning_title') ?></h3>
            <button type="button" onclick="closePlanningModal()" class="pf-modal-close">&times;</button>
        </div>
        <div id="planningContainer" style="width: 100%;"></div>
        <div class="modal-footer">
            <button type="button" onclick="closePlanningModal()" class="pf-btn btn-secondary"><?= tr('btn_close') ?></button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET VARIABLES ---
window.MAP_POINTS = <?= json_encode($mapPoints ?? []) ?>;
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";

window.I18N = {
    ...(window.I18N || {}),
    'hdl_js_search_loading': "<?= tr('hdl_js_search_loading') ?>",
    'hdl_js_no_result': "<?= tr('hdl_js_no_result') ?>",
    'hdl_js_confirm_del_trip': "<?= tr('hdl_js_confirm_del_trip') ?>",
    'hdl_js_confirm_del_step': "<?= tr('hdl_js_confirm_del_step') ?>",
    'hdl_js_step_label': "<?= tr('hdl_js_step_label') ?>",
    'hdl_js_ph_expense_name': "<?= tr('hdl_js_ph_expense_name') ?>",
    'hdl_js_delete_line': "<?= tr('btn_delete') ?>",
    'hdl_planning_title': "<?= tr('hdl_planning_title') ?>",
    'hdl_to_place': "<?= tr('hdl_to_place') ?>",
    'hdl_js_missing_dates_title': "<?= tr('hdl_js_missing_dates_title') ?>",
    'hdl_js_missing_dates_msg': "<?= tr('hdl_js_missing_dates_msg') ?>",
    'hdl_modal_title': "<?= tr('hdl_modal_title') ?>",
    'hdl_js_edit_step': "<?= tr('hdl_js_edit_step') ?>",
    'hdl_ph_notes': "<?= tr('hdl_ph_notes') ?>",
    'hdl_btn_add_step': "<?= tr('hdl_btn_add_step') ?>",
    'hdl_quick_edit_title': "<?= tr('hdl_quick_edit_title') ?>",
    
    // --- NOUVELLES CLÉS MÉTÉO ICI ---
    'weather_sunny': "<?= tr('weather_sunny') ?>",
    'weather_cloudy': "<?= tr('weather_cloudy') ?>",
    'weather_rainy': "<?= tr('weather_rainy') ?>",
    'weather_snowy': "<?= tr('weather_snowy') ?>",
    'weather_forecast': "<?= tr('weather_forecast') ?>",
    'weather_historical': "<?= tr('weather_historical') ?>"
};

// Fallback de sécurité pour s'assurer que les modales peuvent toujours se fermer
window.closeCheckpointModal = window.closeCheckpointModal || function() {
    const modal = document.getElementById('checkpointModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};

window.closePlanningModal = window.closePlanningModal || function() {
    const modal = document.getElementById('planningModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};
</script>

<script src="/modules/holidays/holidays.js"></script>