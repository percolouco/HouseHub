<?php
// modules/family-calendar/family-calendar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/includes/db.php';

// Récupération des événements
$stmt_events = $pdo->query("SELECT * FROM pf_events");
$dbEvents = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = "PachaFamily - Family Calendar";
$activePage = "family-calendar";
$bodyClass  = "pf-family-calendar"; 
$pageCss    = "/modules/family-calendar/family-calendar.css"; 

require __DIR__ . '/header.php';
?>

<script>
  const serverData = <?php echo json_encode($dbEvents, JSON_NUMERIC_CHECK); ?>;
</script>

<div class="pf-container" style="max-width:100%; padding:0;">
    <div class="fc-header-row">
        <h1>Family Calendar</h1>
        
        <button id="btnOpenHolidays" class="pf-btn-icon-text">
            <span class="icon">🏖️</span>
            <span class="text">Voir les vacances scolaires</span>
        </button>
    </div>

    <div id="modalHolidays" class="fc-modal-overlay" style="display: none;">
        <div class="fc-modal-content">
            <div class="fc-modal-header">
                <h2>Vacances Scolaires (Zone C)</h2>
                <button id="btnCloseHolidays" class="fc-modal-close">×</button>
            </div>
            <div class="fc-modal-body">
                <table id="schoolHolidaysTable" class="fc-holidays-table">
                    <thead>
                        <tr>
                            <th>Période</th>
                            <th>Du</th>
                            <th>Au</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <section class="pf-section">
      <div class="fc-month-calendar-wrapper">
        <div class="fc-month-header">
          <div class="fc-view-controls">
            <button class="fc-view-button fc-view-button--active" data-view="1month">1 mois</button>
            <button class="fc-view-button" data-view="2months">2 mois</button>
            <button class="fc-view-button" data-view="year">Année</button>
          </div>
          <h3 id="fc-current-month-year"></h3>
          <div class="fc-nav-controls">
            <button id="fc-prev-month" class="fc-nav-button">‹</button>
            <button id="fc-next-month" class="fc-nav-button">›</button>
          </div>
        </div>
        
        <div class="fc-calendar-container">
          <div id="fc-month-calendar" class="fc-month-calendar"></div>
          <div id="fc-month-selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>
      </div>
    </section>

    <section class="pf-section">
      <div class="fc-week-header">
        <h2>Planning hebdo</h2>
        <div class="fc-week-nav-controls">
          <button id="fc-prev-school-year" class="fc-nav-button">‹</button>
          <span id="fc-current-school-year-label"></span>
          <button id="fc-next-school-year" class="fc-nav-button">›</button>
        </div>
      </div>

      <div id="planningTable-wrapper">
        <table id="planningTable">
          <colgroup>
            <col class="col-month"><col class="col-month">
            <col class="col-day"><col class="col-day"><col class="col-day"><col class="col-day"><col class="col-day">
            <col class="col-total"><col class="col-total"><col class="col-total"><col class="col-total"><col class="col-total"><col class="col-total">
            <col class="col-alex-sub"><col class="col-alex-sub"><col class="col-alex-sub"><col class="col-alex-sub"><col class="col-alex-sub"><col class="col-alex-sub">
            <col class="col-laia-sub"><col class="col-laia-sub"><col class="col-laia-sub"><col class="col-laia-sub"><col class="col-laia-sub"><col class="col-laia-sub">
          </colgroup>
          <thead>
            <tr>
              <th rowspan="3" class="col-month col-sticky-mois">Mois</th>
              <th rowspan="3" class="col-month col-sticky-mois">Sem.</th>
              <th rowspan="3" class="col-day">Lun</th>
              <th rowspan="3" class="col-day">Mar</th>
              <th rowspan="3" class="col-day">Mer</th>
              <th rowspan="3" class="col-day">Jeu</th>
              <th rowspan="3" class="col-day">Ven</th>
              <th rowspan="3" class="col-total rotated-text"><span>Off Carole</span></th>
              <th rowspan="3" class="col-total rotated-text"><span>Extra Off</span></th>
              <th rowspan="3" class="col-total rotated-text"><span>Centre</span></th>
              <th rowspan="3" class="col-total rotated-text"><span>Avis</span></th>
              <th rowspan="3" class="col-total rotated-text"><span>Pep Malade</span></th>
              <th rowspan="3" class="col-total rotated-text"><span>Présence</span></th>
              <th colspan="6" class="col-alex header-group">ALEX</th>
              <th colspan="6" class="col-laia header-group">LAIA</th>
            </tr>
            <tr>
              <th colspan="2" class="col-alex-sub">CP</th><th colspan="2" class="col-alex-sub">JRA</th><th colspan="2" class="col-alex-sub">JA</th>
              <th colspan="2" class="col-laia-sub">CP</th><th colspan="2" class="col-laia-sub">JRA</th><th colspan="2" class="col-laia-sub">JA</th>
            </tr>
            <tr>
                <th class="col-alex-sub col-alex-av">Av.</th><th class="col-alex-sub col-alex-use">Use</th>
                <th class="col-alex-sub col-alex-av">Av.</th><th class="col-alex-sub col-alex-use">Use</th>
                <th class="col-alex-sub col-alex-av">Av.</th><th class="col-alex-sub col-alex-use">Use</th>
                <th class="col-laia-sub col-laia-av">Av.</th><th class="col-laia-sub col-laia-use">Use</th>
                <th class="col-laia-sub col-laia-av">Av.</th><th class="col-laia-sub col-laia-use">Use</th>
                <th class="col-laia-sub col-laia-av">Av.</th><th class="col-laia-sub col-laia-use">Use</th>
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
                <h2 class="pf-card-title">Légende</h2>
                <div class="pf-card-body">
                    <div class="pf-legend-grid">
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-school-holiday"></div><span>Vacances Scolaires</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-public-holiday"></div><span>Férié</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-off-carole"></div><span>Off Carole</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-extra-off-carole"></div><span>Extra Off</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-centre"></div><span>Centre</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-avis"></div><span>Avis</span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-pep-sick"></div><span>Pep Malade</span></div>
                    </div>
                </div>
            </div>

            <div class="pf-card">
                <div class="pf-card-title fc-summary-header">
                    <span>Récapitulatif</span>
                    <div class="fc-summary-controls">
                        <select id="summType" class="fc-summ-select"> <option value="year">Année</option>
    <option value="month">Mois</option>
</select>
<select id="summValue" class="fc-summ-select"> </select>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div id="globalSummary" class="fc-summary-vertical">
                        </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script src="/modules/family-calendar/family-calendar.js"></script>
<?php require __DIR__ . '/footer.php'; ?>