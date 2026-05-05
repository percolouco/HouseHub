<?php
// modules/family-calendar/family-calendar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = tr('fc_page_title');
$activePage = "family-calendar";
$bodyClass  = "pf-family-calendar"; 
$pageCss    = "/modules/family-calendar/family-calendar.css"; 

require __DIR__ . '/header.php';
?>

<div class="pf-container" style="max-width:100%; padding:0;">
    <div class="fc-header-row">
        <h1><?= tr('fc_main_header') ?></h1>
        
        <div style="display: flex; gap: 10px;">
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

    <div id="modalHolidays" class="fc-modal-overlay" style="display: none;">
        <div class="fc-modal-content">
            <div class="fc-modal-header">
                <h2><?= tr('fc_modal_holidays_title') ?></h2>
                <button id="btnCloseHolidays" class="fc-modal-close">×</button>
            </div>
            <div class="fc-modal-body">
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

    <div id="modalSnapshot" class="fc-modal-overlay" style="display: none;">
        <div class="fc-modal-content" style="max-width: 400px;">
            <div class="fc-modal-header">
                <h2><?= tr('fc_modal_snap_title') ?></h2>
                <button id="btnCloseSnapshot" class="fc-modal-close">×</button>
        </div>
            <div class="fc-modal-body" style="padding: 24px;">
            <form id="formSnapshot">
                    <div style="margin-bottom: 16px;">
                        <label class="pf-label"><?= tr('fc_label_person') ?></label>
                        <select id="snapPerson" class="pf-input" required>
                            <option value="2">Alex</option>
                            <option value="3">Laia</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label class="pf-label"><?= tr('fc_label_leave_type') ?></label>
                        <select id="snapType" class="pf-input" required>
                            <option value="CP">CP</option>
                            <option value="JRA">JRA</option>
                            <option value="JA">JA</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 16px;">
                    <label class="pf-label"><?= tr('fc_label_apply_date') ?></label>
                        <input type="date" id="snapDate" class="pf-input" required title="<?= tr('fc_snap_date_hint') ?>">
                </div>
                    <div style="margin-bottom: 24px;">
                    <label class="pf-label"><?= tr('fc_label_remaining_balance') ?></label>
                        <input type="number" step="0.5" id="snapBalance" class="pf-input" placeholder="<?= tr('fc_placeholder_balance') ?>" required>
                    </div>
                    <button type="submit" class="pf-btn" style="width: 100%;"><?= tr('fc_btn_save_snap') ?></button>
            </form>
        </div>
    </div>
</div>

    <section class="pf-section">
      <div class="fc-month-calendar-wrapper">
        
        <div class="fc-month-header">
          
          <div class="fc-month-nav-row">
            <button id="fc-prev-month" class="fc-nav-button">‹</button>
            <h3 id="fc-current-month-year"></h3>
            <button id="fc-next-month" class="fc-nav-button">›</button>
          </div>
          
          <div class="fc-view-controls">
            <button class="fc-view-button fc-view-button--active" data-view="1month"><?= tr('fc_view_1m') ?></button>
            <button class="fc-view-button" data-view="2months"><?= tr('fc_view_2m') ?></button>
            <button class="fc-view-button" data-view="3months">3 Mois</button>
          </div>

        </div>
        
        <div class="fc-calendar-container">
          <div id="fc-month-calendar" class="fc-month-calendar"></div>
          <div id="fc-month-selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>

        <div id="fc-month-balances" class="fc-month-balances"></div>
        
      </div>
    </section>

    <section class="pf-section">
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
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_off_carole') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_extra_off') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_centre') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_avis') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_pep_sick') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_presence') ?></span></th>
              <th colspan="6" class="col-alex header-group">ALEX</th>
              <th colspan="6" class="col-laia header-group">LAIA</th>
            </tr>
            <tr>
              <th colspan="2" class="col-alex-sub">CP</th><th colspan="2" class="col-alex-sub">JRA</th><th colspan="2" class="col-alex-sub">JA</th>
              <th colspan="2" class="col-laia-sub">CP</th><th colspan="2" class="col-laia-sub">JRA</th><th colspan="2" class="col-laia-sub">JA</th>
            </tr>
            <tr>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
            </tr>
          </thead>
          <tbody id="planningBody"></tbody>
        </table>
        <div id="selectionMenu" class="fc-selection-menu" style="display:none;"></div>
      </div>
    </section>

    <section class="pf-section pf-section--bottom-panels">
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
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-off-carole"></div><span><?= tr('leg_off_carole') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-extra-off-carole"></div><span><?= tr('leg_extra_off') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-centre"></div><span><?= tr('leg_centre') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-avis"></div><span><?= tr('leg_avis') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-pep-sick"></div><span><?= tr('leg_pep_sick') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
window.I18N = {
    ...(window.I18N || {}),
    'fc_menu_carole': "<?= tr('fc_menu_carole') ?>",
    'btn_off': "<?= tr('btn_off') ?>",
    'btn_extra': "<?= tr('btn_extra') ?>",
    'leg_centre': "<?= tr('leg_centre') ?>",
    'leg_avis': "<?= tr('leg_avis') ?>",
    'leg_pep_sick': "<?= tr('leg_pep_sick') ?>",
    'leg_off_carole': "<?= tr('leg_off_carole') ?>",
    'leg_extra_off': "<?= tr('leg_extra_off') ?>",
    'leg_presence Pep': "<?= tr('leg_presence Pep') ?>",
    'fc_menu_kids_leaves': "<?= tr('fc_menu_kids_leaves') ?>",
    'fc_clear': "<?= tr('fc_clear') ?>",
    'fc_unit_days': "<?= tr('fc_unit_days') ?>",
    'vac_toussaint': "<?= tr('vac_toussaint') ?>",
    'vac_noel': "<?= tr('vac_noel') ?>",
    'vac_hiver': "<?= tr('vac_hiver') ?>",
    'vac_printemps': "<?= tr('vac_printemps') ?>",
    'vac_ascension': "<?= tr('vac_ascension') ?>",
    'vac_ete': "<?= tr('vac_ete') ?>",
    'leg_school_holidays': "<?= tr('leg_school_holidays') ?>",
    'fc_school_year': "<?= tr('fc_school_year') ?>",
    'fc_modal_holidays_title': "<?= tr('fc_modal_holidays_title') ?>",
    'fc_alert_burn_days': "<?= tr('fc_alert_burn_days') ?>",
    'fc_alert_burn_jra': "<?= tr('fc_alert_burn_jra') ?>",
    'ANNIV': "<?= tr('ANNIV') ?>"
};
</script>
<script src="/modules/family-calendar/family-calendar.js"></script>
<?php require __DIR__ . '/footer.php'; ?>