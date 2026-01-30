<?php
// modules/family-calendar/family-calendar.php

// Active l'affichage des erreurs pour le développement (à retirer en prod si nécessaire)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();

require __DIR__ . '/includes/db.php';

// --- On récupère TOUS les événements sauvegardés en base ---
// Note: Si la base grossit trop, il faudra filtrer par année ici.
$stmt_events = $pdo->query("SELECT * FROM pf_events");
$dbEvents = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = "PachaFamily - Family Calendar";
$activePage = "family-calendar";
$bodyClass  = "pf-family-calendar"; 
$pageCss    = "/modules/family-calendar/family-calendar.css"; 

require __DIR__ . '/header.php';
?>

<script>
  /* JSON_NUMERIC_CHECK convertit les strings "1" en entiers 1, utile pour les calculs JS */
  const serverData = <?php echo json_encode($dbEvents, JSON_NUMERIC_CHECK); ?>;
</script>

<div class="pf-d-flex pf-align-center pf-justify-between pf-mb-4">
    <h1>Family Calendar</h1>
    </div>

<section class="pf-section pf-section--panel">
  <div class="pf-flex pf-flex--wrap pf-gap-lg">
    
    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Légende</h2>
      <div class="pf-card-body">
        <div class="pf-legend-grid">
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-school-holiday"></div><span>Vacances</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-public-holiday"></div><span>Férié</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-off-carole"></div><span>Off Carole</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-extra-off-carole"></div><span>Extra Off</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-centre"></div><span>Centre</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-avis"></div><span>Avis</span></div>
            <div class="pf-legend-item"><div class="pf-legend-color fc-legend-pep-sick"></div><span>Pep Malade</span></div>
        </div>
      </div>
    </div>

    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Récapitulatif annuel</h2>
      <div class="pf-card-body" id="globalSummary">
        <span class="pf-loading-text">Chargement...</span>
      </div>
    </div>

    <div class="pf-card pf-card--small pf-card--wide">
      <h2 class="pf-card-title">Vacances scolaires - Zone C</h2>
      <div class="pf-card-body">
        <div class="pf-table-wrapper pf-table-wrapper--max-height">
          <table id="schoolHolidaysTable" class="fc-holidays-table pf-table pf-table--compact">
            <thead>
              <tr>
                <th>Période</th>
                <th>Du</th>
                <th>Au</th>
                <th>Zones</th>
              </tr>
            </thead>
            <tbody>
              </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="pf-section">
  <h2>Calendrier mensuel</h2>
  <div class="fc-month-calendar-wrapper">
    
    <div class="fc-month-header">
      <div class="fc-view-controls">
        <button id="fc-view-1month" class="fc-view-button fc-view-button--active" data-view="1month">1 mois</button>
        <button id="fc-view-2months" class="fc-view-button" data-view="2months">2 mois</button>
        <button id="fc-view-year" class="fc-view-button" data-view="year">Année</button>
      </div>
      <div class="fc-nav-controls">
        <button id="fc-prev-month" class="fc-nav-button">‹</button>
        <h3 id="fc-current-month-year"></h3>
        <button id="fc-next-month" class="fc-nav-button">›</button>
      </div>
    </div>

    <div class="fc-calendar-container">
      <div id="fc-month-calendar" class="fc-month-calendar">
        </div>
      <div id="fc-month-selectionMenu" class="fc-selection-menu" hidden></div>
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

  <div class="pf-table-wrapper" id="planningTable-wrapper">
    <table id="planningTable" class="pf-table pf-table--compact pf-table--sticky-head pf-table--bordered">
      
      <colgroup>
        <col class="col-month">   <col class="col-month">   <col class="col-day">     <col class="col-day">     <col class="col-day">     <col class="col-day">     <col class="col-day">     <col class="col-total">   <col class="col-total">   <col class="col-total">   <col class="col-total">   <col class="col-total">   <col class="col-total">   <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-laia-sub">
        <col class="col-laia-sub">
        <col class="col-laia-sub">
        <col class="col-laia-sub">
        <col class="col-laia-sub">
        <col class="col-laia-sub">
      </colgroup>

      <thead>
        <tr>
          <th rowspan="3" class="col-month">Mois</th>
          <th rowspan="3" class="col-month">Sem.</th>
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
          <th colspan="2" class="col-alex-sub">CP</th>
          <th colspan="2" class="col-alex-sub">JRA</th>
          <th colspan="2" class="col-alex-sub">JA</th>

          <th colspan="2" class="col-laia-sub">CP</th>
          <th colspan="2" class="col-laia-sub">JRA</th>
          <th colspan="2" class="col-laia-sub">JA</th>
        </tr>
        <tr>
          <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
        </tr>
      </thead>

      <tbody id="planningBody">
        </tbody>

    </table>
    
    <div id="selectionMenu" class="fc-selection-menu" hidden></div>
  </div>
</section>

<script src="/modules/family-calendar/family-calendar.js"></script>

<?php require __DIR__ . '/footer.php'; ?>