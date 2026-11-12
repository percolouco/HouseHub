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
           v.name as vehicle_name,
           v.consumption as vehicle_consumption,
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)) as total_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND is_paid = 1) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND (expense_context = 'transit' OR (category = 'transport' AND (name LIKE '%Essence%' OR name LIKE '%Carburant%')))) as total_fuel,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND category = 'transport' AND (name LIKE '%Péage%' OR name LIKE '%Peage%')) as total_tolls
    FROM pf_holidays h 
    LEFT JOIN pf_vehicles v ON h.vehicle_id = v.id
    WHERE h.id = ?
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
                'step_type' => $it['step_type'] ?? 'stop', 
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
    // 💡 Format Jour/Mois uniquement
    $dateDisplay = date('d/m', strtotime($holiday['start_date']));
    if ($holiday['end_date']) $dateDisplay .= ' → ' . date('d/m', strtotime($holiday['end_date']));
}

$cost = (float)$holiday['total_cost'];
$paid = (float)$holiday['total_paid'];
$saved = (float)$holiday['total_saved'];
$leftToPay = max(0, $cost - $paid);
$pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
$pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;

// Répartition du budget par catégorie (prévu / déjà payé)
$budgetCats = [
    'accommodation' => ['icon' => '🏨', 'label' => tr('hol_cat_accommodation'), 'total' => 0.0, 'paid' => 0.0],
    'transport'     => ['icon' => '🚗', 'label' => tr('hol_cat_transport'),     'total' => 0.0, 'paid' => 0.0],
    'activity'      => ['icon' => '🎫', 'label' => tr('hol_cat_activity'),      'total' => 0.0, 'paid' => 0.0],
    'other'         => ['icon' => '🏷️', 'label' => tr('hol_cat_other'),         'total' => 0.0, 'paid' => 0.0],
];
foreach ($items as $it) {
    if ($it['name'] === 'PF_TECHNICAL_POINT') continue;
    $k = isset($budgetCats[$it['category']]) ? $it['category'] : 'other';
    $budgetCats[$k]['total'] += (float)$it['amount'];
    if ($it['is_paid']) $budgetCats[$k]['paid'] += (float)$it['amount'];
}
$budgetCats['food']  = ['icon' => '🍔', 'label' => tr('hol_cat_food'),   'total' => (float)$holiday['budget_food'],  'paid' => 0.0, 'envelope' => true];
$budgetCats['extra'] = ['icon' => '🎁', 'label' => tr('hol_cat_extra'), 'total' => (float)$holiday['budget_extra'], 'paid' => 0.0, 'envelope' => true];
$budgetCats = array_filter($budgetCats, fn($c) => $c['total'] > 0);
$tripDays = (!empty($holiday['start_date']) && !empty($holiday['end_date']))
    ? (int)((strtotime($holiday['end_date']) - strtotime($holiday['start_date'])) / 86400) + 1 : 0;
$catIcon = fn($c) => match($c) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };

// ── Suivi du voyage : compte à rebours, étape du jour, points à vérifier, résumé jour par jour ──
$today = date('Y-m-d');
$tStart = $holiday['start_date'] ?: null;
$tEnd   = $holiday['end_date'] ?: $tStart;
$countdown = null; $tripPhase = 'none';
if ($tStart) {
    if ($today < $tStart) {
        $tripPhase = 'before';
        $countdown = 'J-' . (int)round((strtotime($tStart) - strtotime($today)) / 86400);
    } elseif ($today <= $tEnd) {
        $tripPhase = 'during';
        $countdown = tr('hol_day') . ' ' . ((int)round((strtotime($today) - strtotime($tStart)) / 86400) + 1) . '/' . $tripDays;
    } else {
        $tripPhase = 'after';
        $countdown = tr('hol_trip_done');
    }
}
$fmtD = fn($d) => date('d/m', strtotime($d));
$stepList = array_values($steps);

$alerts = [];
if (!$tStart) {
    $alerts[] = ['warn', tr('hol_al_no_dates')];
}
if (!empty($stepList)) {
    $types = array_column($stepList, 'step_type');
    if (!in_array('origin', $types, true)) $alerts[] = ['info', tr('hol_al_no_origin')];
    if (count($stepList) > 1 && !in_array('destination', $types, true)) $alerts[] = ['info', tr('hol_al_no_destination')];
    $prev = null;
    foreach ($stepList as $st) {
        $name = $st['location_name'];
        $sd = $st['step_start_date']; $ed = $st['step_end_date'] ?: $sd;
        if (!$sd) { $alerts[] = ['warn', sprintf(tr('hol_al_step_no_date'), $name)]; $prev = null; continue; }
        if ($tStart && ($sd < $tStart || $ed > $tEnd)) {
            $alerts[] = ['warn', sprintf(tr('hol_al_step_outside'), $name, $fmtD($sd) . ($ed !== $sd ? ' → ' . $fmtD($ed) : ''), $fmtD($tStart) . ' → ' . $fmtD($tEnd))];
        }
        if ($prev) {
            if ($sd < $prev['end']) {
                $alerts[] = ['warn', sprintf(tr('hol_al_step_overlap'), $name, $fmtD($sd), $prev['name'], $fmtD($prev['end']))];
            } elseif ($sd > $prev['end']) {
                $gap = (int)round((strtotime($sd) - strtotime($prev['end'])) / 86400);
                $alerts[] = ['warn', sprintf(tr('hol_al_step_gap'), $gap, $prev['name'], $fmtD($prev['end']), $name, $fmtD($sd))];
            }
        }
        if ($ed > $sd) {
            $hasBed = false;
            foreach ($st['items'] as $it) { if ($it['category'] === 'accommodation' && $it['name'] !== 'PF_TECHNICAL_POINT') $hasBed = true; }
            if (!$hasBed) $alerts[] = ['info', sprintf(tr('hol_al_no_bed'), $name, (int)round((strtotime($ed) - strtotime($sd)) / 86400))];
        }
        $prev = ['name' => $name, 'end' => $ed];
    }
    // Fin du voyage après la dernière étape datée : des jours restent sans étape
    if ($prev && $tStart && $prev['end'] < $tEnd) {
        $alerts[] = ['warn', sprintf(tr('hol_al_tail_gap'), (int)round((strtotime($tEnd) - strtotime($prev['end'])) / 86400), $prev['name'], $fmtD($prev['end']), $fmtD($tEnd))];
    }
}
if ($tripPhase === 'before' && $leftToPay > 0 && (strtotime($tStart) - strtotime($today)) / 86400 <= 30) {
    $alerts[] = ['warn', sprintf(tr('hol_al_unpaid'), number_format($leftToPay, 0, ',', ' '))];
}

// Résumé jour par jour (lecture seule ; le placement heure par heure reste dans le Planning)
$dayRows = [];
if ($tStart && $tripDays > 0 && $tripDays <= 60) {
    for ($i = 0; $i < $tripDays; $i++) {
        $d = date('Y-m-d', strtotime("$tStart +$i day"));
        $here = []; $night = null; $acts = [];
        foreach ($stepList as $st) {
            $sd = $st['step_start_date']; $ed = $st['step_end_date'] ?: $sd;
            if (!$sd || $d < $sd || $d > $ed) continue;
            if (end($here) !== $st['location_name']) $here[] = $st['location_name']; // même lieu deux fois de suite : une seule mention
            if ($d < $ed) $night = $st['location_name'];
            foreach ($st['items'] as $it) {
                if ($it['name'] === 'PF_TECHNICAL_POINT' || $it['item_date'] !== $d) continue;
                $acts[] = ['time' => $it['item_time'] ? substr($it['item_time'], 0, 5) : '', 'name' => $it['expense_context'] === 'transit' ? tr('hol_day_drive') : $it['name'], 'icon' => $it['expense_context'] === 'transit' ? '🛣️' : $catIcon($it['category'])];
            }
        }
        usort($acts, fn($a, $b) => strcmp($a['time'], $b['time']));
        $dayRows[] = ['date' => $d, 'here' => $here, 'night' => $night, 'acts' => $acts];
    }
}

// Checklist du voyage (la table est créée à la première utilisation)
$checklist = ['todo' => [], 'pack' => []];
try {
    $stCk = $pdo->prepare("SELECT id, kind, label, is_done FROM pf_holidays_checklist WHERE holiday_id = ? ORDER BY is_done ASC, id ASC");
    $stCk->execute([$id]);
    foreach ($stCk->fetchAll(PDO::FETCH_ASSOC) as $ck) { $checklist[$ck['kind'] === 'pack' ? 'pack' : 'todo'][] = $ck; }
} catch (Throwable $e) {}

// Image de fond (API Pixabay ou fallback par défaut)
$bgImage = !empty($holiday['image_url']) ? htmlspecialchars($holiday['image_url']) : '/modules/home/assets/img/background.jpg';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="pf-holidays-detail">
    
    <!-- Scripts JSON invisibles -->
    <script id="holidayDataJson" type="application/json">
        <?= json_encode(['main' => $holiday, 'items' => $generalItems], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>

    <!-- 1. HERO HEADER IMMERSIF -->
    <div class="hol-hero-banner">
        <img src="<?= $bgImage ?>" class="hol-hero-bg-img" alt="" data-remote="<?= (empty($holiday['image_url']) || strpos($holiday['image_url'], '/modules/holidays/') !== 0) ? '1' : '0' ?>">
        <div class="hol-hero-overlay"></div>
        <div class="hol-hero-content">
            
            <div class="hol-hero-top">
                <a href="?tab=list" class="hol-hero-back-link">◀ <?= tr('btn_back') ?></a>
                <span class="hol-hero-badges">
                    <?php if ($countdown): ?><span class="hol-hero-status-badge hol-hero-countdown hol-phase-<?= $tripPhase ?>"><?= htmlspecialchars($countdown) ?></span><?php endif; ?>
                    <span class="hol-hero-status-badge"><?= tr('hdl_status_' . $holiday['status']) ?></span>
                </span>
            </div>
            
            <h1 class="hol-hero-title"><?= htmlspecialchars($holiday['title']) ?></h1>
            <div class="hol-hero-date-badge">
                🗓️ <?= $dateDisplay ?>
            </div>

            <!-- LES PILLS (Indicateurs Route & Quotidien) -->
            <div class="hol-hero-pills">
                
                <!-- PILL ROUTE (Masqué par défaut, géré par OSRM) -->
                <span id="block_total_distance" class="hol-pill-block hol-hidden">
                    <span class="hol-pill">
                        🛣️ <span id="global_total_distance">0</span> km (⏱️ <span id="global_total_duration">0h00</span>)
                    </span>
                </span>
                
                <!-- PILL PÉAGES -->
                <span class="hol-pill">
                    💳 <?= number_format($holiday['total_tolls'], 0) ?> €
                </span>

                <!-- PILL ESSENCE (Avec Edit + Oeil) -->
                <span class="hol-pill">
                    ⛽ <span id="global_fuel_cost"><?= number_format($holiday['total_fuel'], 0) ?></span> € 
                    <span onclick="updateFuelPrice()" class="hol-pill-action" title="Modifier le prix de l'essence">(<span id="display_fuel_price">1.85</span>€/L) ✏️</span>
                    <?php if (count($steps) > 1): ?>
                        <span onclick="openTransitModal()" class="hol-pill-action" title="<?= tr('hol_routes_see') ?>">👁️</span>
                    <?php endif; ?>
                </span>

                <!-- PILL BURGER (Food) -->
                <span class="hol-pill">
                    🍔 <?= number_format($holiday['budget_food'], 0) ?> €
                </span>

                <!-- PILL CADEAU (Extras) -->
                <span class="hol-pill">
                    🎁 <?= number_format($holiday['budget_extra'], 0) ?> €
                </span>
            </div>

            <!-- FINANCES GLOBALES -->
            <div class="hol-hero-finances">
                <div class="hol-finance-block">
                    <div class="hol-finance-label"><?= tr('hdl_total_budget') ?></div>
                    <div class="hol-finance-value"><?= number_format($cost, 0, ',', ' ') ?> €</div>
                </div>
                <div class="hol-finance-block hol-finance-right">
                    <div class="hol-finance-label"><?= tr('hdl_left_to_pay') ?></div>
                    <div class="hol-finance-value hol-finance-value-due"><?= number_format($leftToPay, 0, ',', ' ') ?> €</div>
                </div>
            </div>

            <!-- Barre de progression -->
            <div class="hol-progress-bar">
                <div class="hol-progress-paid" style="width: <?= $pctPaid ?>%;"></div>
                <div class="hol-progress-saved" style="width: <?= $pctSaved ?>%;"></div>
            </div>
            <div class="hol-hero-progress-labels">
                <span class="hol-label-paid-text">✓ <?= tr('hdl_paid') ?> : <?= number_format($paid, 0, ',', ' ') ?> €</span>
                <span class="hol-label-saved-text">💼 <?= tr('hdl_saved') ?> : <?= number_format($saved, 0, ',', ' ') ?> €</span>
            </div>
        </div>
    </div>

    <!-- 2. BARRE D'ACTIONS RAPIDES -->
    <div class="hol-quick-actions-bar">
        <button type="button" class="pf-btn btn-secondary pf-btn-small hol-quick-action-btn" onclick="toggleTripMap()">
            🗺️ Carte
        </button>
        <button type="button" class="pf-btn btn-secondary pf-btn-small hol-quick-action-btn" onclick="openGlobalPlanningModal()">
            📅 Planning
        </button>
        <button type="button" class="pf-btn btn-secondary pf-btn-small hol-quick-action-btn" onclick="generateTravelBook()">
            📖 PDF
        </button>
        <?php if ($tStart): ?>
        <a class="pf-btn btn-secondary pf-btn-small hol-quick-action-btn" href="/modules/holidays/includes/api/export_ics.php?id=<?= $id ?>" title="<?= tr('hol_ics_hint') ?>">
            📆 <?= tr('hol_ics') ?>
        </a>
        <?php endif; ?>
        <button type="button" class="pf-btn btn-secondary pf-btn-small hol-quick-action-btn" onclick="editHoliday(JSON.parse(document.getElementById('holidayDataJson').textContent))">
            ⚙️ Modifier
        </button>
    </div>

    <!-- POINTS À VÉRIFIER (cohérence du voyage) -->
    <div id="holAlerts" class="hol-alerts<?= empty($alerts) ? ' hol-hidden' : '' ?>">
        <div class="hol-alerts-title">🔎 <?= tr('hol_alerts_title') ?></div>
        <ul id="holAlertsList">
            <?php foreach ($alerts as [$lvl, $msg]): ?>
                <li class="hol-alert-<?= $lvl ?>"><?= htmlspecialchars($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- 3. LA CARTE (Masquée par défaut) -->
    <div id="tripMapWrapper" class="hol-map-wrapper hol-hidden">
        <div class="hol-panel-header hol-map-panel-header">
            <h3 class="hol-map-title">Itinéraire</h3>
            <div class="hol-map-legend">
                <span class="hol-legend-item"><span class="hol-legend-color aller"></span> Aller</span>
                <span class="hol-legend-item"><span class="hol-legend-color retour"></span> Retour</span>
            </div>
        </div>
        <!-- IMPORTANT : On garde l'ID tripMap pour que Leaflet (OSRM) fonctionne ! -->
        <div id="tripMap" class="hol-map-canvas"></div>
    </div>

    <!-- 4. TIMELINE (Carnet de route Pleine Largeur) -->
    <div class="hol-panel hol-panel-timeline">
        <div class="hol-panel-header hol-timeline-header">
            <h3><?= tr('hdl_step_details') ?></h3>
            <button class="pf-btn pf-btn-small" onclick="openCheckpointModal('add')">＋ <?= tr('hdl_btn_add_step') ?></button>
        </div>
        
        <div class="hol-panel-body">
            <?php if (empty($steps)): ?>
                <p class="hol-empty-steps-text"><?= tr('hdl_no_steps') ?></p>
            <?php else: ?>

                <?php foreach ($steps as $step): ?>
                    <?php $isCurrentStep = $tripPhase === 'during' && !empty($step['step_start_date']) && $today >= $step['step_start_date'] && $today <= ($step['step_end_date'] ?: $step['step_start_date']); ?>
                    <div id="step-card-<?= $step['sort_order'] ?>" class="hol-checkpoint hol-checkpoint-draggable<?= $isCurrentStep ? ' is-current' : '' ?>" draggable="true" data-sort="<?= (int)$step['sort_order'] ?>" data-location="<?= htmlspecialchars($step['location_name']) ?>">
                        <div class="hol-step-header">
                            
                            <div class="hol-step-row-top">
                                <?php
                                    $stepIcon = '📍';
                                    if ($step['step_type'] === 'origin') {
                                        $stepIcon = '🛫';
                                    } elseif ($step['step_type'] === 'destination') {
                                        $stepIcon = '🛬';
                                    }
                                    $isReturn = ($holiday['return_step_id'] !== null && $holiday['return_step_id'] == $step['sort_order']);
                                ?>
                                <div class="hol-step-icon hol-step-icon-<?= htmlspecialchars($step['step_type']) ?>">
                                    <?= $stepIcon ?>
                                </div>

                                <div class="hol-step-title" onclick="panMapTo(<?= $step['lat'] ?>, <?= $step['lng'] ?>)" title="<?= htmlspecialchars($step['location_name']) ?>">
                                    <?= htmlspecialchars($step['location_name']) ?>
                                </div>

                                <div class="hol-step-controls">
                                    <span class="desktop-only hol-drag-handle">⣿</span>
                                    <div class="mobile-only hol-step-arrows">
                                        <button type="button" onclick="moveStepMobile(this, -1)">▲</button>
                                        <button type="button" onclick="moveStepMobile(this, 1)">▼</button>
                                    </div>
                                </div>
                            </div>

                            <div class="hol-step-row-bottom">
                                <div class="hol-step-meta">
                                    <?php if ($isReturn): ?>
                                        <span class="hol-tag-return">🏁 Retour</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($step['step_start_date'])):
                                        $sd = $step['step_start_date']; $ed = $step['step_end_date'] ?: $sd;
                                        $nights = (int)((strtotime($ed) - strtotime($sd)) / 86400);
                                    ?>
                                        <span class="hol-step-date">
                                            <?= date('d/m', strtotime($sd)) ?><?php if ($ed !== $sd): ?> ➔ <?= date('d/m', strtotime($ed)) ?><?php endif; ?>
                                        </span>
                                        <?php if ($nights > 0): ?>
                                            <span class="hol-step-nights">🌙 <?= $nights ?> <?= $nights > 1 ? tr('hol_nights') : tr('hol_night') ?></span>
                                        <?php endif; ?>
                                        <div class="hol-weather-info"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="hol-step-actions">
                                    <span class="hol-step-price"><?= number_format($step['total_amount'], 2, ',', ' ') ?> €</span>
                                    
                                    <button onclick="openGpsModal(<?= $step['lat'] ?>, <?= $step['lng'] ?>)" 
                                       class="btn-icon-small hol-btn-action-icon" 
                                       title="Y aller avec le GPS">
                                        🧭
                                    </button>
                                    <button onclick="openDocsModal(<?= $step['sort_order'] ?>)" class="btn-icon-small hol-btn-action-icon" title="Documents & Billets">📎</button>

                                    <button onclick='openCheckpointModal("edit", <?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small hol-btn-action-icon" title="<?= tr('btn_edit') ?>">✏️</button>
                                </div>
                            </div>
                            
                        </div>

                        <div class="hol-cp-body">
                            <?php 
                                $visibleItemsCount = 0;
                                foreach ($step['items'] as $it): 
                                    if ($it['name'] === 'PF_TECHNICAL_POINT') continue; 
                                    $visibleItemsCount++;
                                    $icon = match($it['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                    
                                    // On vérifie si l'activité possède ses propres coordonnées GPS
                                    $hasCustomGps = (!empty($it['lat']) && !empty($it['lng']) && ((float)$it['lat'] !== (float)$step['lat'] || (float)$it['lng'] !== (float)$step['lng']));
                            ?>
                                    <div class="hol-expense-wrapper">
                                        <div class="hol-expense-main">
                                            <span class="hol-expense-name">
                                                <?= $icon ?> 
                                                <?php if ($hasCustomGps): ?>
                                                    <span onclick="openGpsModal(<?= $it['lat'] ?>, <?= $it['lng'] ?>)" class="hol-custom-gps-link" title="Y aller (GPS)">
                                                        <?= htmlspecialchars($it['name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($it['name']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <strong class="hol-expense-amount"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                                <button type="button" class="hol-paid-toggle <?= $it['is_paid'] ? 'status-paid' : 'status-pending' ?>" onclick="toggleItemPaid(<?= (int)$it['id'] ?>, this)" title="<?= $it['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?> — <?= tr('hol_paid_toggle_hint') ?>" aria-label="<?= $it['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?>"><?= $it['is_paid'] ? '✓' : '⏳' ?></button>
                                            </span>
                                        </div>
                                        <?php if (!empty($it['notes'])): ?>
                                            <div class="hol-expense-note">
                                                <?= htmlspecialchars($it['notes']) ?>
                                            </div>
                                        <?php endif; ?>
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

    <!-- RÉSUMÉ JOUR PAR JOUR (lecture seule, complète le Planning) -->
    <?php if (!empty($dayRows) && !empty($stepList)): ?>
    <details class="hol-panel hol-panel-days"<?= $tripPhase === 'during' ? ' open' : '' ?>>
        <summary class="hol-panel-header hol-timeline-header">
            <h3>🗓️ <?= tr('hol_days_title') ?></h3>
            <span class="hol-days-hint"><?= $tripDays ?> <?= tr('hol_days_unit') ?></span>
        </summary>
        <div class="hol-panel-body">
            <?php foreach ($dayRows as $i => $dr):
                $dk = strtolower(date('D', strtotime($dr['date'])));
            ?>
                <div class="hol-day-row<?= $dr['date'] === $today ? ' is-today' : '' ?>">
                    <div class="hol-day-rail">
                        <span class="hol-day-rail-name"><?= tr('day_' . $dk) ?></span>
                        <span class="hol-day-rail-num"><?= date('j', strtotime($dr['date'])) ?></span>
                        <span class="hol-day-rail-idx"><?= tr('hol_day_short') ?><?= $i + 1 ?></span>
                    </div>
                    <div class="hol-day-main">
                        <div class="hol-day-where">
                            <?php if (empty($dr['here'])): ?>
                                <span class="hol-day-empty"><?= tr('hol_day_nothing') ?></span>
                            <?php else: ?>
                                📍 <?= htmlspecialchars(implode(' ➔ ', $dr['here'])) ?>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($dr['acts'] as $a): ?>
                            <div class="hol-day-act"><?php if ($a['time']): ?><span class="hol-day-time"><?= $a['time'] ?></span><?php endif; ?><?= $a['icon'] ?> <?= htmlspecialchars($a['name']) ?></div>
                        <?php endforeach; ?>
                        <?php if ($dr['night']): ?>
                            <div class="hol-day-night">🌙 <?= tr('hol_day_night_at') ?> <?= htmlspecialchars($dr['night']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="hol-days-footer">
                <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="openGlobalPlanningModal()">📅 <?= tr('hol_days_open_planning') ?></button>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <!-- 5. RÉSERVATIONS & FRAIS GÉNÉRAUX (hors étape : vol, location, assurance…) -->
    <div class="hol-panel hol-panel-general">
        <div class="hol-panel-header hol-timeline-header">
            <h3>🌍 <?= tr('hol_general_title') ?></h3>
            <button class="pf-btn pf-btn-small" onclick="openGeneralItemModal()">＋ <?= tr('btn_add') ?></button>
        </div>
        <div class="hol-panel-body">
            <?php if (empty($generalItems)): ?>
                <p class="hol-empty-steps-text"><?= tr('hol_general_empty') ?></p>
            <?php else: foreach ($generalItems as $gi): ?>
                <div class="hol-expense-wrapper hol-general-row">
                    <div class="hol-expense-main">
                        <span class="hol-expense-name"><?= $catIcon($gi['category']) ?> <?= htmlspecialchars($gi['name']) ?></span>
                        <span class="hol-general-actions">
                            <strong class="hol-expense-amount"><?= number_format($gi['amount'], 2, ',', ' ') ?> €</strong>
                            <button type="button" class="hol-paid-toggle <?= $gi['is_paid'] ? 'status-paid' : 'status-pending' ?>" onclick="toggleItemPaid(<?= (int)$gi['id'] ?>, this)" title="<?= $gi['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?> — <?= tr('hol_paid_toggle_hint') ?>" aria-label="<?= $gi['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?>"><?= $gi['is_paid'] ? '✓' : '⏳' ?></button>
                            <button type="button" class="btn-icon-small hol-btn-action-icon" title="<?= tr('btn_edit') ?>" aria-label="<?= tr('btn_edit') ?>" onclick='openGeneralItemModal(<?= htmlspecialchars(json_encode(['id' => (int)$gi['id'], 'category' => $gi['category'], 'name' => $gi['name'], 'amount' => $gi['amount'], 'is_paid' => (int)$gi['is_paid'], 'notes' => $gi['notes']]), ENT_QUOTES, "UTF-8") ?>)'>✏️</button>
                        </span>
                    </div>
                    <?php if (!empty($gi['notes'])): ?>
                        <div class="hol-expense-note"><?= htmlspecialchars($gi['notes']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 6. BUDGET PAR CATÉGORIE -->
    <?php if (!empty($budgetCats)): ?>
    <div class="hol-panel hol-panel-budget">
        <div class="hol-panel-header hol-timeline-header">
            <h3>💰 <?= tr('hol_budget_title') ?></h3>
            <?php if ($tripDays > 0 && $cost > 0): ?>
                <span class="hol-budget-perday">≈ <?= number_format($cost / $tripDays, 0, ',', ' ') ?> € / <?= tr('hol_per_day') ?></span>
            <?php endif; ?>
        </div>
        <div class="hol-panel-body">
            <?php foreach ($budgetCats as $c):
                $share = $cost > 0 ? min(100, $c['total'] / $cost * 100) : 0;
                $paidPct = $c['total'] > 0 ? min(100, $c['paid'] / $c['total'] * 100) : 0;
            ?>
                <div class="hol-budget-row">
                    <div class="hol-budget-line">
                        <span class="hol-budget-label"><?= $c['icon'] ?> <?= htmlspecialchars($c['label']) ?></span>
                        <span class="hol-budget-amount"><?= number_format($c['total'], 0, ',', ' ') ?> € <small><?= round($share) ?> %</small></span>
                    </div>
                    <div class="hol-budget-bar" title="<?= empty($c['envelope']) ? tr('hdl_paid') . ' : ' . number_format($c['paid'], 0, ',', ' ') . ' €' : tr('hol_budget_envelope') ?>">
                        <div class="hol-budget-bar-total" style="width: <?= round($share, 1) ?>%;">
                            <div class="hol-budget-bar-paid" style="width: <?= round($paidPct, 1) ?>%;"></div>
                        </div>
                    </div>
                    <div class="hol-budget-sub">
                        <?php if (!empty($c['envelope'])): ?>
                            <?= tr('hol_budget_envelope') ?>
                        <?php else: ?>
                            ✓ <?= tr('hdl_paid') ?> <?= number_format($c['paid'], 0, ',', ' ') ?> € · ⏳ <?= tr('hdl_left_to_pay') ?> <?= number_format(max(0, $c['total'] - $c['paid']), 0, ',', ' ') ?> €
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 7. CHECKLIST : à faire avant le départ + bagages -->
    <div class="hol-panel hol-panel-checklist">
        <div class="hol-panel-header hol-timeline-header">
            <h3>✅ <?= tr('hol_check_title') ?></h3>
        </div>
        <div class="hol-panel-body hol-check-grid">
            <?php foreach (['todo' => ['🧾', tr('hol_check_todo'), tr('hol_check_todo_ph')], 'pack' => ['🧳', tr('hol_check_pack'), tr('hol_check_pack_ph')]] as $kind => [$kIcon, $kLabel, $kPh]):
                $doneCount = count(array_filter($checklist[$kind], fn($c) => $c['is_done']));
            ?>
            <section class="hol-check-col" data-kind="<?= $kind ?>">
                <div class="hol-check-head">
                    <span><?= $kIcon ?> <?= $kLabel ?></span>
                    <span class="hol-check-count"><span class="js-done"><?= $doneCount ?></span>/<span class="js-total"><?= count($checklist[$kind]) ?></span></span>
                </div>
                <ul class="hol-check-list">
                    <?php foreach ($checklist[$kind] as $ck): ?>
                        <li class="hol-check-item<?= $ck['is_done'] ? ' is-done' : '' ?>" data-id="<?= (int)$ck['id'] ?>">
                            <label><input type="checkbox" <?= $ck['is_done'] ? 'checked' : '' ?> onchange="holCheckToggle(this)"> <span><?= htmlspecialchars($ck['label']) ?></span></label>
                            <button type="button" class="hol-check-del" onclick="holCheckDelete(this)" title="<?= tr('btn_delete') ?>" aria-label="<?= tr('btn_delete') ?>">×</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form class="hol-check-add" onsubmit="holCheckAdd(event, '<?= $kind ?>')">
                    <input type="text" class="pf-input" maxlength="255" placeholder="<?= $kPh ?>" aria-label="<?= $kPh ?>">
                    <button type="submit" class="pf-btn pf-btn-small" aria-label="<?= tr('btn_add') ?>">＋</button>
                </form>
                <button type="button" class="hol-check-suggest<?= empty($checklist[$kind]) ? '' : ' hol-hidden' ?>" onclick="holCheckSuggest('<?= $kind ?>', this)">✨ <?= tr('hol_check_suggest') ?></button>
            </section>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="pf-card hol-notes-card">
        <h2 class="pf-card-title">📝 <?= tr('hdl_label_notes') ?></h2>
        <div class="pf-card-body">
            <textarea id="holidayGlobalNotes" class="pf-input hol-notes-textarea" rows="6" placeholder="<?= tr('hdl_ph_notes') ?>"><?= htmlspecialchars($holiday['notes'] ?? '') ?></textarea>
            <div class="hol-notes-actions">
                <button id="btnSaveHolidayNote" class="pf-btn" onclick="saveHolidayGlobalNote(<?= (int)$_GET['id'] ?>)">
                    💾 <?= tr('btn_save') ?>
                </button>
            </div>
        </div>
    </div>

</div> 


<div id="checkpointModal" class="pf-modal">
    <div class="pf-modal-content pf-modal-md">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="cpModalTitle" style="margin:0;">📍 <?= tr('hdl_btn_add_step') ?></h3>
            <button type="button" onclick="closeCheckpointModal()" class="pf-modal-close">&times;</button>
        </div>
        
        <!-- LES ONGLETS SONT ICI -->
        <div id="cpTabs" class="cp-tabs" style="display:none;">
            <button type="button" id="tabBtnInfo" onclick="switchCpTab('info')" class="cp-tab active"><?= htmlspecialchars(tr('hol_step_tab_info')) ?></button>
            <button type="button" id="tabBtnProg" onclick="switchCpTab('prog')" class="cp-tab">🎟️ Programme &amp; frais <span id="cpProgCount" class="cp-badge" style="display:none;">0</span></button>
        </div>

        <div id="cpSearchBlock" class="cp-search" style="margin-bottom:20px;">
            <p class="cp-hint"><?= htmlspecialchars(tr('hol_step_hint_place')) ?></p>
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
                <input type="text" id="searchPlaceInput" class="pf-input" autocomplete="off" placeholder="<?= tr('hdl_ph_search') ?>" onkeydown="if(event.key === 'Enter') { event.preventDefault(); searchPlace(); }">
                <button type="button" class="pf-btn btn-secondary" onclick="searchPlace()">🔍</button>
            </div>
            <div id="searchResults" style="margin-top:10px; max-height:200px; overflow-y:auto;"></div>
        </div>

        <!-- FORMULAIRE (Le contenu apparaîtra ici) -->
        <form action="/modules/holidays/includes/api/save_checkpoint.php" method="POST" id="formCheckpoint" style="display:none; border-top:1px solid #e2e8f0; padding-top:20px;">
            <input type="hidden" name="holiday_id" value="<?= $id ?>">
            <input type="hidden" name="old_location_name" id="cp_old_name">
            <input type="hidden" name="lat" id="cp_lat">
            <input type="hidden" name="lng" id="cp_lng">
            <input type="hidden" name="old_sort_order" id="cp_old_sort_order">
            
            <div class="cp-place-chip">
                <span class="cp-place-pin">📍</span>
                <span id="cpPlaceChipName" class="cp-place-name"></span>
                <button type="button" id="cpChangePlaceBtn" class="cp-link" onclick="cpChangePlace()">Changer de lieu</button>
            </div>

            <!-- CONTENU DE L'ONGLET INFO -->
            <div id="cpTabInfo">
                <div class="form-group" style="margin-bottom:15px;">
                    <label class="pf-label"><?= tr('hdl_label_step_name') ?></label>
                    <input type="text" name="location_name" id="cp_name" class="pf-input" style="font-weight:bold; color:var(--primary);" required>
                </div>

                <div class="form-group" style="margin-bottom:15px; background:#fff7ed; padding:10px; border-radius:8px; border:1px solid #ffedd5;">
                    <label class="pf-label" style="color:#ea580c;">📍 Type d'étape</label>
                    <select name="step_type" id="cp_step_type" class="pf-input" onchange="toggleStepDates(this.value)">
                        <option value="origin">DÉPART (Point de départ du voyage)</option>
                        <option value="stop">SÉJOUR (Étape classique avec arrivée et départ)</option>
                        <option value="destination">ARRIVÉE FINALE (Fin du voyage)</option>
                    </select>
                </div>

                <div class="form-group" id="cp_insert_group" style="margin-bottom:15px;">
                    <label class="pf-label"><?= htmlspecialchars(tr('hol_step_position')) ?></label>
                    <select name="insert_after" id="cp_insert_after" class="pf-input" onchange="const o=this.selectedOptions[0]; if(o && o.dataset.enddate){ document.getElementById('cp_start_date').value=o.dataset.enddate; document.getElementById('cp_end_date').value=o.dataset.enddate; }"></select>
                </div>

                <div style="display:flex; gap:15px; margin-bottom:15px; background:#f8fafc; padding:12px; border-radius:8px;">
                    <div class="form-group" id="grp_start_date" style="flex:1;">
                        <label class="pf-label" id="lbl_start_date"><?= tr('hdl_label_arrival') ?></label>
                        <input type="date" name="step_start_date" id="cp_start_date" class="pf-input">
                    </div>
                    <div class="form-group" id="grp_end_date" style="flex:1;">
                        <label class="pf-label" id="lbl_end_date"><?= tr('hdl_label_departure') ?></label>
                        <input type="date" name="step_end_date" id="cp_end_date" class="pf-input">
                    </div>
                </div>

                <div style="margin-bottom: 20px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                    <label style="display:flex; align-items:center; cursor:pointer; color:#ea580c; font-weight:600; font-size:0.9rem;">
                        <input type="checkbox" name="set_as_return" id="cp_set_as_return" value="1" style="margin-right:8px; width:16px; height:16px;">
                        🏁 Définir comme retour
                    </label>
                    <p style="margin: 4px 0 0 24px; font-size: 0.75rem; color: #64748b;">La route sera tracée en orange à partir d'ici.</p>
                </div>
            </div>

            <!-- CONTENU DE L'ONGLET PROGRAMME & FRAIS -->
            <div id="cpTabProg" class="hol-hidden" style="display: none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label class="pf-label" style="margin:0;"><?= tr('hdl_planned_expenses') ?></label>
                    <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="addCpExpenseLine()"><?= tr('hdl_btn_add_expense') ?></button>
                </div>

                <div id="cpExpensesContainer" style="margin-bottom:15px;"></div>
                <p id="cpExpensesEmpty" class="cp-hint" style="display:none;"><?= htmlspecialchars(tr('hol_step_expenses_empty')) ?></p>
            </div>

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

<div id="generalItemModal" class="pf-modal">
    <div class="pf-modal-content pf-modal-md">
        <div class="pf-modal-header">
            <h3 class="pf-modal-title" id="generalItemTitle">🌍 <?= tr('hol_general_title') ?></h3>
            <button type="button" onclick="closeGeneralItemModal()" class="pf-modal-close">&times;</button>
        </div>
        <form id="generalItemForm" onsubmit="saveGeneralItem(event)">
            <input type="hidden" id="gi_id" value="">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="pf-label" for="gi_name"><?= tr('hol_general_name') ?></label>
                <input type="text" id="gi_name" class="pf-input" maxlength="255" required placeholder="<?= tr('hol_general_ph') ?>">
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <div class="hol-flex-1">
                    <label class="pf-label" for="gi_category"><?= tr('hol_general_category') ?></label>
                    <select id="gi_category" class="pf-input">
                        <option value="transport">🚗 <?= tr('hol_cat_transport') ?></option>
                        <option value="accommodation">🏨 <?= tr('hol_cat_accommodation') ?></option>
                        <option value="activity">🎫 <?= tr('hol_cat_activity') ?></option>
                        <option value="other">🏷️ <?= tr('hol_cat_other') ?></option>
                    </select>
                </div>
                <div class="hol-flex-1">
                    <label class="pf-label" for="gi_amount"><?= tr('hol_general_amount') ?></label>
                    <input type="number" step="0.01" min="0" id="gi_amount" class="pf-input" placeholder="0.00" inputmode="decimal">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="pf-label" for="gi_notes"><?= tr('hdl_label_notes') ?></label>
                <input type="text" id="gi_notes" class="pf-input" placeholder="<?= tr('hol_general_notes_ph') ?>">
            </div>
            <label class="hol-general-paid"><input type="checkbox" id="gi_paid"> <?= tr('hdl_paid') ?></label>
            <div class="modal-footer">
                <button type="button" id="gi_delete" class="pf-btn btn-secondary hol-btn-delete" onclick="deleteGeneralItem()"><?= tr('btn_delete') ?></button>
                <button type="button" class="pf-btn btn-secondary" onclick="closeGeneralItemModal()"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" id="gi_submit"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="planningModal" data-keep-open class="pf-modal">
    <div class="pf-modal-content hol-modal-planning">
        <div class="pf-modal-header">
            <h3 id="planningModalTitle" class="pf-modal-title">📅 Planning Global</h3>
            <button type="button" onclick="closePlanningModal()" class="pf-modal-close">&times;</button>
        </div>
        
        <!-- Conteneur Injecté en JS -->
        <div id="planningContainer" class="hol-planning-container"></div>
        
        <div class="modal-footer">
            <button type="button" onclick="closePlanningModal()" class="pf-btn btn-secondary"><?= tr('btn_close') ?></button>
        </div>
    </div>
</div>

<div id="transitModal" class="pf-modal">
    <div class="pf-modal-content hol-modal-sm">
        <div class="pf-modal-header">
            <h3 class="pf-modal-title"><?= htmlspecialchars(tr('hol_routes_detail_title')) ?></h3>
            <button type="button" onclick="closeTransitModal()" class="pf-modal-close">&times;</button>
        </div>
        <div id="transitDetailsContainer" class="hol-transit-container">
            <p class="hol-empty-text">Calcul en cours...</p>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeTransitModal()" class="pf-btn btn-secondary"><?= tr('btn_close') ?></button>
        </div>
    </div>
</div>

<div id="gpsModal" class="pf-modal">
    <div class="pf-modal-content hol-modal-gps">
        <div class="pf-modal-header">
            <h3 class="pf-modal-title">🧭 Y aller avec...</h3>
            <button type="button" onclick="closeGpsModal()" class="pf-modal-close">×</button>
        </div>
        
        <div class="hol-gps-buttons">
            <button onclick="launchGpsApp('waze')" class="pf-btn btn-secondary hol-gps-btn">
                <img src="https://cdn.simpleicons.org/waze/05C8F0" alt="Waze" class="hol-gps-icon"> 
                Waze
            </button>
            
            <button onclick="launchGpsApp('gmaps')" class="pf-btn btn-secondary hol-gps-btn">
                <img src="https://cdn.simpleicons.org/googlemaps" alt="Google Maps" class="hol-gps-icon"> 
                Google Maps
            </button>
            
            <button onclick="launchGpsApp('amaps')" class="pf-btn btn-secondary hol-gps-btn">
                <img src="https://cdn.simpleicons.org/apple/000000" alt="Apple Maps" class="hol-gps-icon"> 
                Apple Maps
            </button>
        </div>
    </div>
</div>

<div id="docsModal" class="pf-modal">
    <div class="pf-modal-content hol-modal-docs">
        <div class="pf-modal-header">
            <h3 class="pf-modal-title">📎 Porte-documents</h3>
            <button type="button" onclick="closeDocsModal()" class="pf-modal-close">×</button>
        </div>
        
        <div class="hol-docs-dropzone">
            <p class="hol-docs-hint">Ajoutez vos billets, réservations ou PDFs pour cette étape.</p>
            
            <input type="file" id="docFileInput" class="hol-hidden" accept=".pdf,.png,.jpg,.jpeg" onchange="handleFileUpload(this)">
            
            <button type="button" class="pf-btn btn-primary hol-docs-select-btn" onclick="document.getElementById('docFileInput').click()">
                + Sélectionner un fichier
            </button>
            <div id="uploadStatus" class="hol-upload-status"></div>
        </div>

        <div id="docsListContainer" class="hol-docs-list">
            <p class="hol-empty-text">Aucun document pour cette étape.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<?php include __DIR__ . '/pdf_template.php'; ?>


<script>
    // --- 1. SÉCURISATION TRADUCTIONS ET VARIABLES ---
    window.MAP_POINTS = <?= json_encode($mapPoints ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";

    window.I18N = {
        ...(window.I18N || {}),
        'hdl_js_search_loading': <?= json_encode(tr('hdl_js_search_loading')) ?>,
        'hdl_js_no_result': <?= json_encode(tr('hdl_js_no_result')) ?>,
        'hdl_js_confirm_del_trip': <?= json_encode(tr('hdl_js_confirm_del_trip')) ?>,
        'hdl_js_confirm_del_step': <?= json_encode(tr('hdl_js_confirm_del_step')) ?>,
        'hdl_js_step_label': <?= json_encode(tr('hdl_js_step_label')) ?>,
        'hdl_js_ph_expense_name': <?= json_encode(tr('hdl_js_ph_expense_name')) ?>,
        'hdl_js_delete_line': <?= json_encode(tr('btn_delete')) ?>,
        'hdl_planning_title': <?= json_encode(tr('hdl_planning_title')) ?>,
        'hdl_to_place': <?= json_encode(tr('hdl_to_place')) ?>,
        'hdl_js_missing_dates_title': <?= json_encode(tr('hdl_js_missing_dates_title')) ?>,
        'hdl_js_missing_dates_msg': <?= json_encode(tr('hdl_js_missing_dates_msg')) ?>,
        'hdl_modal_title': <?= json_encode(tr('hdl_modal_title')) ?>,
        'hdl_js_edit_step': <?= json_encode(tr('hdl_js_edit_step')) ?>,
        'hdl_ph_notes': <?= json_encode(tr('hdl_ph_notes')) ?>,
        'hdl_btn_add_step': <?= json_encode(tr('hdl_btn_add_step')) ?>,
        'hdl_quick_edit_title': <?= json_encode(tr('hdl_quick_edit_title')) ?>,
        'hdl_paid': <?= json_encode(tr('hdl_paid')) ?>,
        'weather_sunny': <?= json_encode(tr('weather_sunny')) ?>,
        'weather_cloudy': <?= json_encode(tr('weather_cloudy')) ?>,
        'weather_rainy': <?= json_encode(tr('weather_rainy')) ?>,
        'weather_snowy': <?= json_encode(tr('weather_snowy')) ?>,
        'weather_forecast': <?= json_encode(tr('weather_forecast')) ?>,
        'weather_historical': <?= json_encode(tr('weather_historical')) ?>,
        'hol_step_opt_end': <?= json_encode(tr('hol_step_opt_end')) ?>,
        'hol_step_opt_after': <?= json_encode(tr('hol_step_opt_after')) ?>,
        'hol_step_toast_name': <?= json_encode(tr('hol_step_toast_name')) ?>,
        'hol_step_toast_dates': <?= json_encode(tr('hol_step_toast_dates')) ?>,
        'hol_step_toast_place': <?= json_encode(tr('hol_step_toast_place')) ?>,
        'hol_general_confirm_del': <?= json_encode(tr('hol_general_confirm_del')) ?>,
        'hdl_to_pay': <?= json_encode(tr('hdl_to_pay')) ?>,
        'btn_delete': <?= json_encode(tr('btn_delete')) ?>,
        'hol_al_long_leg': <?= json_encode(tr('hol_al_long_leg')) ?>,
        'hol_check_sugg_todo': <?= json_encode(tr('hol_check_sugg_todo')) ?>,
        'hol_check_sugg_pack': <?= json_encode(tr('hol_check_sugg_pack')) ?>,
        'hol_duplicate_confirm': <?= json_encode(tr('hol_duplicate_confirm')) ?>
    };

    const savedFuelPrice = localStorage.getItem('holidays_fuel_price') || 1.85;
    window.FUEL_PRICE = parseFloat(savedFuelPrice);
    
    const displayFuelEl = document.getElementById('display_fuel_price');
    if (displayFuelEl) displayFuelEl.innerText = window.FUEL_PRICE.toFixed(2);

    window.VEHICLE_CONSUMPTION = <?= !empty($holiday['vehicle_consumption']) ? (float)$holiday['vehicle_consumption'] : 7 ?>;
    window.GLOBAL_RETURN_STEP_ID = <?= $holiday['return_step_id'] !== null ? $holiday['return_step_id'] : 'null' ?>;

</script>
