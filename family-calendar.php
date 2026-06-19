<?php
// modules/family-calendar/family-calendar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

// 1. Récupération de toutes les personnes
$stmtPeople = $pdo->query("SELECT id, name, user_id, role, color, care_modes FROM pf_people WHERE is_active = 1 ORDER BY id ASC");
$familyPeople = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

$parents = [];
$kids = [];
$helpers = []; 

foreach ($familyPeople as $p) {
    $role = strtolower($p['role'] ?? '');
    if ($role === 'parent') {
        $parents[] = $p;
    } elseif ($role === 'enfant' || $role === 'child') {
        $p['modes'] = json_decode($p['care_modes'] ?? '[]', true) ?: [];
        $kids[] = $p;
    } elseif ($role === 'helper' || $role === 'nounou') {
        $helpers[] = $p; // 🟢 On stocke les intervenants
    }
}

// 2. Modes de garde (globaux du foyer, pour synchroniser avec le JS)
$stmtFoyer = $pdo->query("SELECT care_modes, zone_scolaire FROM pf_foyer_settings LIMIT 1");
$foyerData = $stmtFoyer->fetch(PDO::FETCH_ASSOC);
$activeCareModes = json_decode($foyerData['care_modes'] ?? '[]', true);
if (!is_array($activeCareModes)) $activeCareModes = [];

// 3. Matrice de congés dynamique (pour les en-têtes et la modale)
$stmtLeaves = $pdo->query("SELECT person_id, leave_type FROM pf_person_leave_meta ORDER BY id ASC");
$dbLeaves = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
$leaveMatrix = [];
$allLeaveTypes = [];
foreach ($dbLeaves as $l) {
    $leaveMatrix[$l['person_id']][] = $l['leave_type'];
    $allLeaveTypes[$l['leave_type']] = true;
}
$allLeaveTypes = array_keys($allLeaveTypes);

// ==========================================
// VÉRIFICATIONS D'INTÉGRITÉ (ALERTES)
// ==========================================
$calendarAlerts = [];

// 1. Aucun membre adulte/parent configuré
if (empty($parents)) {
    $calendarAlerts[] = "<strong>Aucun adulte configuré.</strong> Rendez-vous dans les Paramètres Généraux de HouseHub pour ajouter les membres du foyer.";
}

// 2. Enfants présents, mais aucun mode de garde global défini
if (!empty($kids) && empty($activeCareModes)) {
    $calendarAlerts[] = "<strong>Modes de garde manquants.</strong> Vous avez des enfants, mais aucun mode de garde n'est défini. Cliquez sur l'icône ⚙️ (Paramètres du calendrier) pour les ajouter.";
}

// 3. Référentiel des types de congés vide
try {
    $countLeaveTypes = $pdo->query("SELECT COUNT(*) FROM pf_leave_types")->fetchColumn();
    if ($countLeaveTypes == 0) {
        $calendarAlerts[] = "<strong>Types de congés manquants.</strong> Le catalogue des congés (CP, RTT...) est vide. Ajoutez-les dans les paramètres généraux.";
    }
} catch (Exception $e) {
    // Si la table n'existe pas encore
    $calendarAlerts[] = "<strong>Table des congés manquante.</strong> Veuillez mettre à jour la base de données.";
}

// 4. Zone scolaire manquante (si tu l'as stockée dans pf_foyer_settings)
$zoneScolaire = $foyerData['zone_scolaire'] ?? null;
if (empty($zoneScolaire)) {
    $calendarAlerts[] = "<strong>Zone scolaire non définie.</strong> Cliquez sur l'icône ⚙️ pour configurer votre zone afin d'afficher les vacances scolaires.";
}

$pageTitle  = tr('fc_page_title');
$activePage = "family-calendar";
$mainClass  = "pf-family-calendar";
$pageCss    = "/modules/family-calendar/family-calendar.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container" style="max-width:100%; padding:0;">
    <div class="fc-header-row" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">

        <!-- Bloc Titre + Roue crantée -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <h1 style="margin: 0;"><?= tr('fc_main_header') ?></h1>
            <button id="btnOpenCalendarSettings" class="btn-settings-gear" title="Paramètres du calendrier" style="font-size: 1.4rem; padding: 4px;">
                ⚙️
            </button>
        </div>

        <!-- Boutons d'actions -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button id="btnOpenSnapshotModal" class="pf-btn-icon-text">
                <span class="icon">⚖️</span>
                <span class="text"><?= tr('fc_btn_correct_balances') ?></span>
            </button>
            <button id="btnOpenHolidays" class="pf-btn-icon-text">
                <span class="icon">🏖️</span>
                <span class="text"><?= tr('fc_btn_school_holidays') ?></span>
            </button>
        </div>
    </div>

    <?php if (!empty($calendarAlerts)): ?>
    <div class="pf-alert pf-alert-warning" style="background: var(--bg-warning, #fffbeb); border: 1px solid var(--border-warning, #fcd34d); color: var(--text-warning, #92400e); padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; gap: 12px; align-items: flex-start;">
        <div style="font-size: 1.5rem; line-height: 1;">⚠️</div>
        <div>
            <h4 style="margin: 0 0 8px 0; font-size: 1.05rem;">Configuration incomplète</h4>
            <ul style="margin: 0; padding-left: 18px; font-size: 0.95rem; line-height: 1.5;">
                <?php foreach ($calendarAlerts as $alert): ?>
                    <li style="margin-bottom: 4px;"><?= $alert ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div id="modalHolidays" class="pf-modal">
        <div class="pf-modal-content">
            <div class="pf-modal-header">
                <h3 class="pf-modal-title">
                    <?= tr('fc_modal_holidays_title') ?>
                    <?php if (defined('ZONE_SCOLAIRE') && ZONE_SCOLAIRE !== 'Autre') echo '(Zone ' . htmlspecialchars(ZONE_SCOLAIRE) . ')'; ?>
                </h3>
                <button id="btnCloseHolidays" class="pf-modal-close" onclick="document.getElementById('modalHolidays').classList.remove('active')">&times;</button>
            </div>
            <div class="pf-modal-body" style="padding:0;">
                <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table id="schoolHolidaysTable" class="fc-holidays-table" style="min-width: 400px;">
                        <thead>
                            <tr>
                            <th><?= tr('fc_col_period') ?></th>
                            <th><?= tr('fc_col_from') ?></th>
                            <th><?= tr('fc_col_to') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="modalSnapshot" class="pf-modal">
        <div class="pf-modal-content">
            <div class="pf-modal-header">
                <h3 class="pf-modal-title">📸 <?= tr('fc_modal_snap_title') ?></h3>
                <button type="button" id="btnCloseSnapshot" class="pf-modal-close" onclick="document.getElementById('modalSnapshot').classList.remove('open'); document.body.classList.remove('no-scroll');">&times;</button>
            </div>
            <div class="pf-modal-body">
                <form id="formSnapshot">
                    <div class="pf-form-row">
                        <div class="pf-form-group">
                            <label class="pf-label"><?= tr('fc_label_person') ?></label>
                            <select id="snapPerson" class="pf-input" required>
                                <option value="" disabled selected>-- <?= tr('fc_choose_person') ?> --</option>
                                <?php foreach ($parents as $person): ?>
                                    <option value="<?= htmlspecialchars($person['id']) ?>">
                                        <?= htmlspecialchars($person['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf-form-group">
                            <label class="pf-label"><?= tr('fc_label_leave_type') ?></label>
                            <select id="snapType" class="pf-input" required>
                                <?php foreach ($allLeaveTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="pf-form-group">
                        <label class="pf-label"><?= tr('fc_label_apply_date') ?></label>
                        <input type="date" id="snapDate" class="pf-input" required title="<?= tr('fc_snap_date_hint') ?>">
                    </div>
                    <div class="pf-form-group">
                        <label class="pf-label"><?= tr('fc_label_remaining_balance') ?></label>
                        <div style="display:flex; align-items:center;">
                            <input type="number" step="0.5" id="snapBalance" class="pf-input" placeholder="<?= tr('fc_placeholder_balance') ?>" style="border-radius: 8px 0 0 8px; border-right: none; font-weight: bold; color: var(--pf-primary);" required>
                            <span style="background: var(--pf-bg-lighter); border: 1px solid var(--pf-border); padding: 0 12px; height: 42px; display: flex; align-items: center; border-radius: 0 8px 8px 0; color: var(--pf-text-muted); font-size: 0.9rem; font-weight: 600;">
                                <?= tr('fc_unit_days') ?>
                            </span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="pf-modal-footer">
                <button type="button" class="pf-btn pf-btn-secondary" onclick="document.getElementById('modalSnapshot').classList.remove('open'); document.body.classList.remove('no-scroll');"><?= tr('btn_cancel') ?></button>
                <button type="submit" form="formSnapshot" class="pf-btn pf-btn-primary"><?= tr('fc_btn_save_snap') ?></button>
            </div>
        </div>
    </div>

    <div id="modalCalendarSettings" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 650px; width: 95%;">
        
        <div class="pf-modal-header">
            <h3 class="pf-modal-title">⚙️ <?= tr('fc_modal_settings_title') ?></h3>
            <button type="button" class="pf-modal-close" onclick="closeCalendarSettings()">&times;</button>
        </div>

        <div style="display: flex; gap: 4px; background: var(--bg-page); padding: 4px; border-radius: 8px; margin: 1rem;">
            <button type="button" id="tab-btn-foyer" class="bs-tab-btn active" style="flex: 1; padding: 8px 12px; font-weight: 600;" onclick="switchCalendarTab('foyer')">
                🏡 <?= tr('fc_tab_foyer') ?>
            </button>
            <button type="button" id="tab-btn-membres" class="bs-tab-btn" style="flex: 1; padding: 8px 12px; font-weight: 600;" onclick="switchCalendarTab('membres')">
                👥 <?= tr('fc_tab_members') ?>
            </button>
        </div>

        <div class="pf-modal-body" style="max-height: 65vh; overflow-y: auto; padding-top: 0; padding-bottom: 1rem;">

            <div id="cal-pane-foyer" class="cal-settings-pane">
                
                <form id="formCalFoyer" onsubmit="submitCalFoyer(event)">
                    
                    <div class="pf-form-group">
                        <label class="pf-label"><?= tr('fc_zone_label') ?></label>
                        <select id="setZoneScolaire" name="zone_scolaire" class="pf-input" required style="width: 100%;">
                            <option value="A"><?= tr('fc_zone_a') ?></option>
                            <option value="B"><?= tr('fc_zone_b') ?></option>
                            <option value="C"><?= tr('fc_zone_c') ?></option>
                            <option value="Autre"><?= tr('fc_zone_other') ?></option>
                        </select>
                        <small class="pf-muted-note"><?= tr('fc_zone_desc') ?></small>
                    </div>

                    <div class="pf-form-group" style="margin-top: 1.5rem;">
                        <label class="pf-label"><?= tr('fc_care_modes_label') ?></label>
                        
                        <?php if (!empty($kids)): ?>
                            <div id="careModesContainer" style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; min-height: 38px; padding: 8px; background: var(--bg-page); border-radius: var(--radius); border: 1px solid var(--border-light);"></div>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="inputNewCareMode" class="pf-input" placeholder="<?= tr('fc_add_mode_placeholder') ?>" style="flex: 1;">
                                <button type="button" class="pf-btn btn-secondary" style="padding: 0 16px; border-radius: 6px;" onclick="addCareModeTag()">＋</button>
                            </div>
                            <small class="pf-muted-note"><?= tr('fc_care_modes_desc') ?></small>
                        <?php else: ?>
                            <div style="background: var(--bg-subtle); padding: 12px; border-radius: 8px; border: 1px dashed var(--border-main); text-align: center;">
                                <p style="margin: 0 0 10px 0; color: var(--text-muted); font-size: 0.9rem;">
                                    <?= tr('fc_empty_kids_msg') ?>
                                </p>
                                <a href="/settings.php" class="pf-btn btn-secondary" style="text-decoration: none; display: inline-block; font-size: 0.85rem;">
                                    ⚙️ <?= tr('fc_link_settings') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($kids)): ?>
                    <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                        <button type="submit" class="pf-btn pf-btn-primary"><?= tr('save') ?></button>
                    </div>
                    <?php endif; ?>
                </form>

                <div class="pf-form-group" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-light);">
                    <h4 style="margin: 0 0 10px 0; color: var(--text-main);">🏖️ <?= tr('fc_leave_catalog_title') ?></h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">
                        <?= tr('fc_leave_catalog_desc') ?>
                    </p>

                    <div id="leaveTypesContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px;"></div>

                    <div style="background: var(--bg-subtle); padding: 12px; border-radius: 8px; border: 1px solid var(--border-light);">
                        <h5 id="leaveTypeFormTitle" style="margin: 0 0 10px 0;">+ <?= tr('fc_add_leave_type') ?></h5>
                        
                        <form id="formLeaveType" style="display: flex; flex-direction: column; gap: 10px;">
                            <input type="hidden" id="lt-mode" value="add">
                            
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <div style="flex: 0 0 80px;">
                                    <label class="pf-label"><?= tr('fc_col_code') ?> *</label>
                                    <input type="text" id="lt-code" class="pf-input" maxlength="4" style="text-transform: uppercase;" required>
                                    <small class="pf-muted-note" id="lt-code-note"><?= tr('fc_code_irreversible') ?></small>
                                </div>
                                <div style="flex: 1; min-width: 150px;">
                                    <label class="pf-label"><?= tr('fc_col_label') ?> *</label>
                                    <input type="text" id="lt-label" class="pf-input" placeholder="<?= tr('fc_leave_label_ph') ?>" required>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 5px;">
                                <button type="button" class="pf-btn btn-secondary" onclick="resetLeaveTypeForm()"><?= tr('btn_cancel') ?></button>
                                <button type="button" class="pf-btn pf-btn-primary" onclick="saveLeaveType()"><?= tr('btn_save') ?></button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <div id="cal-pane-membres" class="cal-settings-pane" style="display: none;">
                
                <div style="margin-bottom: 1.2rem;">
                    <label class="pf-label"><?= tr('fc_select_member') ?></label>
                    <select id="selectCalMember" class="pf-input" style="width: 100%; font-weight: 600;" onchange="loadMemberConfigView()">
                        </select>
                </div>

                <div id="memberConfigZone" style="background: var(--bg-page); padding: 14px; border-radius: 8px; border: 1px solid var(--border-light); min-height: 150px;">
                    </div>

            </div>

        </div>
    </div>
</div>

    <div class="fc-month-calendar-wrapper">
        <div class="fc-month-header">
            <div class="fc-month-nav-row">
                <button id="fc-prev-month" class="fc-nav-button">‹</button>
                <div id="fc-smart-date-selector" style="display:flex; align-items:center; justify-content:center; flex-grow:1; gap:6px;">
                    <select id="fc-select-month" class="fc-smart-select"></select>
                    <select id="fc-select-year" class="fc-smart-select"></select>
                    <span id="fc-multi-month-suffix" style="display:none; font-size:1.3rem; font-weight:800; color:#0f172a; margin-left:4px;"></span>
                </div>
                <button id="fc-next-month" class="fc-nav-button">›</button>
            </div>

            <div class="fc-view-controls">
                <button id="fc-today-btn" class="fc-today-button" title="<?= tr('fc_today_title') ?>"><?= tr('fc_today_short') ?></button>
                <div class="fc-view-divider"></div>
                <button class="fc-view-button fc-view-button--active" data-view="1month"><?= tr('fc_view_1m') ?></button>
                <button class="fc-view-button" data-view="2months"><?= tr('fc_view_2m') ?></button>
                <button class="fc-view-button" data-view="3months"><?= tr('fc_view_3m') ?></button>
            </div>
        </div>

        <div class="fc-calendar-container">
            <div id="fc-month-calendar" class="fc-month-calendar"></div>
            <div id="fc-month-selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>

        <div id="fc-month-balances" class="fc-month-balances"></div>
    </div>

    <section class="pf-section" style="margin-top: 1.5rem;">
        <div class="fc-week-header">
            <h2><?= tr('fc_weekly_planning') ?></h2>
            <div class="fc-week-nav-controls">
                <button id="fc-prev-school-year" class="fc-nav-button">‹</button>
                <span id="fc-current-school-year-label"></span>
                <button id="fc-next-school-year" class="fc-nav-button">›</button>
            </div>
        </div>

        <div id="planningTable-wrapper">
            <table id="planningTable">
                <thead>
                    <tr>
                        <th rowspan="3" class="col-month col-sticky-mois"><?= tr('fc_col_month') ?></th>
                        <th rowspan="3" class="col-month col-sticky-mois"><?= tr('fc_col_week_short') ?></th>
                        <th rowspan="3" class="col-day"><?= tr('day_mon') ?></th>
                        <th rowspan="3" class="col-day"><?= tr('day_tue') ?></th>
                        <th rowspan="3" class="col-day"><?= tr('day_wed') ?></th>
                        <th rowspan="3" class="col-day"><?= tr('day_thu') ?></th>
                        <th rowspan="3" class="col-day"><?= tr('day_fri') ?></th>

                        <?php foreach ($activeCareModes as $mode): ?>
                            <th rowspan="3" class="col-total rotated-text"><span><?= htmlspecialchars($mode) ?></span></th>
                        <?php endforeach; ?>

                        <?php foreach ($kids as $kid): ?>
                            <th rowspan="3" class="col-total rotated-text"><span>Maladie <?= htmlspecialchars($kid['name']) ?></span></th>
                        <?php endforeach; ?>

                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia'; // Couleurs alternées
                            $pLeaves = $leaveMatrix[$parent['id']] ?? [];
                            $colspan = count($pLeaves) * 2;
                        ?>
                            <?php if ($colspan > 0): ?>
                            <th colspan="<?= $colspan ?>" class="<?= $parentClass ?> header-group"><?= htmlspecialchars(strtoupper($parent['name'])) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia';
                            $pLeaves = $leaveMatrix[$parent['id']] ?? [];
                        ?>
                            <?php foreach ($pLeaves as $type): ?>
                            <th colspan="2" class="<?= $parentClass ?>-sub"><?= htmlspecialchars($type) ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia';
                            $pLeaves = $leaveMatrix[$parent['id']] ?? [];
                        ?>
                            <?php foreach ($pLeaves as $type): ?>
                            <th class="<?= $parentClass ?>-sub <?= $parentClass ?>-av"><?= tr('fc_col_av') ?></th>
                            <th class="<?= $parentClass ?>-sub <?= $parentClass ?>-use"><?= tr('fc_col_use') ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="planningBody"></tbody>
            </table>
            <div id="selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>
    </section>

    <section class="pf-section pf-section--bottom-panels" style="margin-top: 1.5rem;">
        <div class="pf-card">
                <h2 class="pf-card-title"><?= tr('fc_legend_title') ?></h2>
                <div class="pf-card-body">
                    <div class="pf-legend-grid">
                        <!-- Fériés & Vacances (Fixes) -->
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-school-holiday"></div><span><?= tr('leg_school_holidays') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-public-holiday"></div><span><?= tr('leg_public_holiday') ?></span></div>

                        <!-- Intervenants (Dynamique) -->
                        <?php foreach ($helpers as $helper): ?>
                            <div class="pf-legend-item"><div class="pf-legend-color" style="background: var(--warning);"></div><span>Off <?= htmlspecialchars($helper['name']) ?></span></div>
                            <div class="pf-legend-item"><div class="pf-legend-color" style="background: var(--danger);"></div><span>Extra <?= htmlspecialchars($helper['name']) ?></span></div>
                        <?php endforeach; ?>

                        <!-- Modes de garde (Dynamique avec icônes) -->
                        <?php foreach ($activeCareModes as $index => $mode):
                            $modeLower = strtolower($mode);
                            if ($modeLower === 'avis'): ?>
                                <div class="pf-legend-item">
                                    <img src="/modules/family-calendar/assets/img/avis.svg" style="width:16px;height:16px;object-fit:contain;margin-right:6px;" alt="Avis">
                                    <span><?= htmlspecialchars($mode) ?></span>
                                </div>
                            <?php elseif ($modeLower === 'centre'): ?>
                                <div class="pf-legend-item">
                                    <span style="font-size:1.1rem; line-height:1; margin-right:6px;">🏫</span>
                                    <span><?= htmlspecialchars($mode) ?></span>
                                </div>
                            <?php else: 
                                $hue = ($index * 137) % 360; ?>
                                <div class="pf-legend-item">
                                    <div class="pf-legend-color" style="background: hsl(<?= $hue ?>, 70%, 50%);"></div>
                                    <span><?= htmlspecialchars($mode) ?></span>
                                </div>
                            <?php endif; 
                        endforeach; ?>

                        <!-- Enfants Malades (Dynamique avec couleur BDD) -->
                        <?php foreach ($kids as $kid): 
                            $color = !empty($kid['color']) ? $kid['color'] : 'var(--danger)';
                        ?>
                            <div class="pf-legend-item">
                                <div class="pf-legend-color" style="background: <?= htmlspecialchars($color) ?>; opacity: 0.8;"></div>
                                <span>Maladie <?= htmlspecialchars($kid['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
    </section>
</div>

<script>
window.FAMILY_CONFIG = {
    parents: <?= json_encode($parents) ?>,
    kids: <?= json_encode($kids) ?>,
    activeCareModes: <?= json_encode($activeCareModes) ?>,
    i18n: {
        settings_updated: <?= json_encode(tr('fc_settings_updated')) ?>,
        empty_matrix: "Aucun compteur de congés défini pour le moment.",
        btn_delete: <?= json_encode(tr('btn_delete') ?? 'Supprimer') ?>
    }
};
</script>
<script src="/modules/family-calendar/family-calendar.js"></script>
<?php require __DIR__ . '/footer.php'; ?>