<?php
// modules/family-calendar/family-calendar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

// 1. Récupération de toutes les personnes avec leurs modes de garde
$stmtPeople = $pdo->query("SELECT id, name, user_id, role, care_modes FROM pf_people WHERE is_active = 1 ORDER BY id ASC");
$familyPeople = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

$parents = [];
$kids = [];

// Liste de tous les modes de garde actifs dans le foyer
$activeCareModes = []; 

foreach ($familyPeople as $p) {
    $role = strtolower($p['role'] ?? '');
    if ($role === 'parent') {
        // --- 🟢 NOUVEAU : Récupération dynamique des types de congés du parent ---
        $stmtLT = $pdo->prepare("
            SELECT DISTINCT leave_type FROM (
                SELECT leave_type FROM pf_leave_balances WHERE person_id = ?
                UNION
                SELECT leave_type FROM pf_leave_snapshots WHERE person_id = ?
            ) as t ORDER BY leave_type = 'CP' DESC, leave_type = 'JRA' DESC, leave_type = 'JA' DESC, leave_type ASC
        ");
        $stmtLT->execute([$p['id'], $p['id']]);
        $lTypes = $stmtLT->fetchAll(PDO::FETCH_COLUMN);

        // Fallback de sécurité si aucun compteur n'est encore paramétré
        if (empty($lTypes)) {
            $lTypes = ['CP', 'JRA', 'JA'];
        }
        $p['leave_types'] = $lTypes;
        
        $parents[] = $p;
    } elseif ($role === 'enfant') {
        // Décodage sécurisé des modes de garde
        $modes = json_decode($p['care_modes'] ?? '[]', true);
        if (!is_array($modes)) $modes = [];
        $p['modes'] = $modes;
        $kids[] = $p;

        foreach ($modes as $m) {
            $activeCareModes[$m] = true;
        }
    }
}
$activeCareModes = array_keys($activeCareModes); // On récupère uniquement les noms uniques

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
            <button id="btnOpenCalendarSettings" class="btn-settings-gear" title="<?= tr('fc_btn_settings') ?>">
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
                                <?php foreach ($familyPeople as $person): ?>
                                    <option value="<?= htmlspecialchars($person['id']) ?>">
                                        <?= htmlspecialchars($person['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf-form-group">
                            <label class="pf-label"><?= tr('fc_label_leave_type') ?></label>
                            <select id="snapType" class="pf-input" required>
                                <option value="CP">CP</option>
                                <option value="JRA">JRA</option>
                                <option value="JA">JA</option>
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

    <!-- 3. MODALE : PARAMÈTRES AVANCÉS DU CALENDRIER -->
    <div id="modalCalendarSettings" class="pf-modal">
        <div class="pf-modal-content" style="max-width: 750px; width: 95%; padding: 0; overflow: hidden; display: flex; flex-direction: column;">
            
            <!-- HEADER -->
            <div class="pf-modal-header" style="padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-light); background: var(--bg-panel);">
                <h3 class="pf-modal-title" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <span style="color: var(--text-muted); font-size: 1.2rem;">⚙️</span> <?= tr('fc_modal_settings_title') ?>
                </h3>
                <button type="button" class="pf-modal-close" style="margin-right: 1.5rem;" onclick="closeCalendarSettings()">&times;</button>
            </div>

            <!-- CORPS SCINDÉ EN DEUX (SIDEBAR / CONTENU) -->
            <div style="display: flex; flex-direction: row; height: 60vh; min-height: 400px; background: var(--bg-panel);">

                <!-- SIDEBAR GAUCHE -->
                <div style="width: 220px; border-right: 1px solid var(--border-light); padding: 1rem 0; display: flex; flex-direction: column; gap: 4px;">
                    <button type="button" id="tab-btn-foyer" class="fc-sidebar-tab bs-tab-btn active" onclick="switchCalendarTab('foyer')">
                        <?= tr('fc_tab_foyer') ?>
                    </button>
                    <button type="button" id="tab-btn-membres" class="fc-sidebar-tab bs-tab-btn" onclick="switchCalendarTab('membres')">
                        <?= tr('fc_tab_members') ?>
                    </button>
                </div>

                <!-- CONTENU DROITE -->
                <div class="pf-modal-body" style="flex: 1; padding: 1.5rem; overflow-y: auto;">

                    <!-- PANE A : CONFIGURATION GÉNÉRALE DU FOYER -->
                    <div id="cal-pane-foyer" class="cal-settings-pane">
                        <div style="display: flex; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.8rem;">
                            <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-main);"><?= tr('fc_tab_foyer') ?></h4>
                        </div>

                        <form id="formCalFoyer" onsubmit="submitCalFoyer(event)">
                            <div class="pf-form-group" style="margin-bottom: 1.5rem;">
                                <label class="pf-label" style="font-weight: 600; margin-bottom: 6px;"><?= tr('fc_zone_label') ?></label>
                                <select id="setZoneScolaire" name="zone_scolaire" class="pf-input" required style="width: 100%; border-radius: 8px; background-color: var(--bg-page);">
                                    <option value="A">Zone A</option>
                                    <option value="B">Zone B</option>
                                    <option value="C">Zone C</option>
                                    <option value="Autre">Autre / Hors France</option>
                                </select>
                                <small class="pf-muted-note" style="margin-top: 4px; display: block;"><?= tr('fc_zone_desc') ?></small>
                            </div>

                            <div class="pf-form-group">
                                <label class="pf-label" style="font-weight: 600; margin-bottom: 6px;"><?= tr('fc_care_modes_label') ?></label>
                                <div style="background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 8px; padding: 8px;">
                                    <div id="careModesContainer" style="display: flex; flex-wrap: wrap; gap: 6px; min-height: 32px; margin-bottom: 8px;"></div>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" id="inputNewCareMode" class="pf-input" placeholder="<?= tr('fc_add_mode_placeholder') ?>" style="flex: 1; background: var(--bg-soft); border: none; border-radius: 6px; padding: 6px 12px;">
                                        <button type="button" class="pf-btn pf-btn-secondary" style="padding: 0 14px; border-radius: 6px;" onclick="addCareModeTag()">➕</button>
                                    </div>
                                </div>
                                <small class="pf-muted-note" style="margin-top: 6px; display: block;"><?= tr('fc_care_modes_desc') ?></small>
                            </div>
                            
                            <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 12px;">
                                <button type="button" class="pf-btn pf-btn-secondary" onclick="closeCalendarSettings()"><?= tr('btn_cancel') ?></button>
                                <button type="submit" class="pf-btn pf-btn-primary"><?= tr('save') ?></button>
                            </div>
                        </form>
                    </div>

                    <!-- PANE B : CONFIGURATION DE LA MATRICE COMPTEURS -->
                    <div id="cal-pane-membres" class="cal-settings-pane" style="display: none;">
                        <div style="display: flex; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.8rem;">
                            <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-main);"><?= tr('fc_tab_members') ?></h4>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label class="pf-label" style="font-weight: 600; margin-bottom: 6px;"><?= tr('fc_select_member') ?></label>
                            <select id="selectCalMember" class="pf-input" style="width: 100%; font-weight: 600; border-radius: 8px; background-color: var(--bg-page);" onchange="loadMemberConfigView()"></select>
                        </div>
                        
                        <div id="memberConfigZone" style="background: var(--bg-page); padding: 16px; border-radius: 12px; border: 1px solid var(--border-light); min-height: 120px;">
                            <!-- Injecté à la volée en fonction du membre sélectionné -->
                        </div>
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

                        <!-- 🟢 NOUVEAU : Colspan dynamique selon le nombre de compteurs de chaque parent -->
                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia';
                            $colspan = count($parent['leave_types']) * 2;
                        ?>
                            <th colspan="<?= $colspan ?>" class="<?= $parentClass ?> header-group"><?= htmlspecialchars(strtoupper($parent['name'])) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <!-- 🟢 NOUVEAU : Noms des compteurs dynamiques -->
                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia';
                            foreach ($parent['leave_types'] as $lt):
                        ?>
                            <th colspan="2" class="<?= $parentClass ?>-sub"><?= htmlspecialchars(strtoupper($lt)) ?></th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                    <tr>
                        <!-- 🟢 NOUVEAU : Sous-colonnes Av./Use dynamiques -->
                        <?php foreach ($parents as $index => $parent):
                            $parentClass = ($index % 2 === 0) ? 'col-alex' : 'col-laia';
                            foreach ($parent['leave_types'] as $lt):
                        ?>
                            <th class="<?= $parentClass ?>-sub <?= $parentClass ?>-av"><?= tr('fc_col_av') ?></th>
                            <th class="<?= $parentClass ?>-sub <?= $parentClass ?>-use"><?= tr('fc_col_use') ?></th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody id="planningBody"></tbody>
            </table>
            <div id="selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>
    </section>

    <section class="pf-section pf-section--bottom-panels" style="margin-top: 1.5rem;">
        <div class="fc-bottom-grid">
            <div class="pf-card">
                <div class="pf-card-title fc-summary-header">
                    <span><?= tr('fc_summary_title') ?></span>
                    <div class="fc-summary-controls">
                        <select id="summType" class="fc-summ-select"> 
                            <option value="year"><?= tr('fc_summ_year') ?></option>
                            <option value="month"><?= tr('fc_summ_month') ?></option>
                        </select>
                        <select id="summValue" class="fc-summ-select"></select>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div id="globalSummary" class="fc-summary-vertical"></div>
                </div>
            </div>

            <div class="pf-card">
                <h2 class="pf-card-title"><?= tr('fc_legend_title') ?></h2>
                <div class="pf-card-body">
                    <div class="pf-legend-grid">
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-school-holiday"></div><span><?= tr('leg_school_holidays') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-public-holiday"></div><span><?= tr('leg_public_holiday') ?></span></div>
                        
                        <?php foreach ($activeCareModes as $index => $mode): 
                            $hue = ($index * 137) % 360; 
                        ?>
                            <div class="pf-legend-item">
                                <div class="pf-legend-color" style="background: hsl(<?= $hue ?>, 70%, 50%);"></div>
                                <span><?= htmlspecialchars($mode) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($kids as $kid): ?>
                            <div class="pf-legend-item">
                                <div class="pf-legend-color" style="background: var(--pf-danger, #ef4444); opacity: 0.8;"></div>
                                <span>Maladie <?= htmlspecialchars($kid['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
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