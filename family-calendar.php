<?php
// Active l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login('/family-calendar.php');

require __DIR__ . '/includes/db.php';

// --- On récupère TOUS les événements sauvegardés en base ---
$stmt_events = $pdo->query("SELECT * FROM pf_events");
$dbEvents = $stmt_events->fetchAll();

$pageTitle  = "PachaFamily - Family Calendar";
$activePage = "family-calendar";
$bodyClass  = "pf-family-calendar"; 
$pageCss    = "/modules/family-calendar/family-calendar.css"; 

require __DIR__ . '/header.php';

?>

<!-- ===================================================================== -->
<!--  INJECTION DES DONNÉES DU SERVEUR VERS JAVASCRIPT                     -->
<!--  Cette variable `serverData` sera lue par le script JS au démarrage.  -->
<!-- ===================================================================== -->
<script>
  const serverData = <?php echo json_encode($dbEvents, JSON_NUMERIC_CHECK); ?>;
</script>

<h1>Family Calendar</h1>

<!-- ===================================================================== -->
<!--  PANNEAU DE CONTRÔLE : Légende, Récapitulatif et Vacances             -->
<!-- ===================================================================== -->
<section class="pf-section pf-section--panel">
  <div class="pf-flex pf-flex--wrap pf-gap-lg">
    
    <!-- LÉGENDE -->
    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Légende</h2>
      <div class="pf-card-body">
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-school-holiday"></div>
          <span>Vacances scolaires</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-public-holiday"></div>
          <span>Jour férié</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-off-carole"></div>
          <span>Off Carole</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-extra-off-carole"></div>
          <span>Extra Off Carole</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-centre"></div>
          <span>Centre</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-avis"></div>
          <span>Avis</span>  
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-legend-pep-sick"></div>
          <span>Pep malade</span>
        </div>
      </div>
    </div>


    <!-- RÉCAPITULATIF ANNUEL -->
    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Récapitulatif annuel</h2>
      <div class="pf-card-body" id="globalSummary">
        <!-- Rempli par le JavaScript -->
      </div>
    </div>

    <!-- VACANCES SCOLAIRES -->
    <div class="pf-card pf-card--small pf-card--wide">
      <h2 class="pf-card-title">Vacances scolaires - Zone C (2025-2026)</h2>
      <div class="pf-card-body">
        <div class="pf-table-wrapper">
          <table id="schoolHolidaysTable" class="fc-holidays-table">
            <thead>
              <tr>
                <th>Période</th>
                <th>Du</th>
                <th>Au</th>
                <th>Zones</th>
              </tr>
            </thead>
            <tbody>
              <!-- Rempli par JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ===================================================================== -->
<!--  CALENDRIER MENSUEL                                                   -->
<!-- ===================================================================== -->
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
    <div class="fc-calendar-and-summary">
      <div id="fc-month-calendar" class="fc-month-calendar">
    </div>
    <!-- Le menu contextuel pour le calendrier mensuel -->
    <div id="fc-month-selectionMenu" class="fc-selection-menu"></div>
  </div>
    </div>
</section>

<!-- ===================================================================== -->
<!--  PLANNING PRINCIPAL                                                   -->
<!-- ===================================================================== -->
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
    <table id="planningHeaderTable" class="pf-table pf-table--compact">
      <colgroup>
        <col class="col-month">   <!-- Mois -->
        <col class="col-month">   <!-- Semaine -->
        <col class="col-day">     <!-- Lundi -->
        <col class="col-day">     <!-- Mardi -->
        <col class="col-day">     <!-- Mercredi -->
        <col class="col-day">     <!-- Jeudi -->
        <col class="col-day">     <!-- Vendredi -->
        <col class="col-total">   <!-- # Off -->
        <col class="col-total">   <!-- # Extra -->
        <col class="col-total">   <!-- # Centre -->
        <col class="col-total">   <!-- # Avis -->
        <col class="col-total">   <!-- # Pep malade -->
        <col class="col-total">   <!-- # Pep Présence -->
        <!-- ALEX (6 colonnes) -->
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <col class="col-alex-sub">
        <!-- LAIA (6 colonnes) -->
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
          <th rowspan="3" class="col-month">Semaine</th>
          <th rowspan="3" class="col-day">Lundi</th>
          <th rowspan="3" class="col-day">Mardi</th>
          <th rowspan="3" class="col-day">Mercredi</th>
          <th rowspan="3" class="col-day">Jeudi</th>
          <th rowspan="3" class="col-day">Vendredi</th>
          <th rowspan="3" class="col-total"># Off Carole</th>
          <th rowspan="3" class="col-total"># Extra off Carole</th>
          <th rowspan="3" class="col-total"># Centre</th>
          <th rowspan="3" class="col-total"># Avis</th>
          <th rowspan="3" class="col-total"># Pep malade</th>
          <th rowspan="3" class="col-total"># Pep Présence</th>

          <!-- Ligne 1 : ALEX / LAIA -->
          <th colspan="6" class="col-alex">ALEX</th>
          <th colspan="6" class="col-laia">LAIA</th>
        </tr>
        <tr>
          <!-- Ligne 2 : CP / JRA / JA (regroupement) -->
          <th colspan="2" class="col-alex-sub">CP</th>
          <th colspan="2" class="col-alex-sub">JRA</th>
          <th colspan="2" class="col-alex-sub">JA</th>

          <th colspan="2" class="col-laia-sub">CP</th>
          <th colspan="2" class="col-laia-sub">JRA</th>
          <th colspan="2" class="col-laia-sub">JA</th>
        </tr>
        <tr>
          <!-- Ligne 3 : Available / Use pour chaque type -->
          <!-- ALEX -->
            <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <th class="col-alex-sub col-alex-av">Av.</th>
            <th class="col-alex-sub col-alex-use">Use</th>
            <!-- LAIA -->
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
            <th class="col-laia-sub col-laia-av">Av.</th>
            <th class="col-laia-sub col-laia-use">Use</th>
        </tr>
      </thead>
    </table>
    <table id="planningTable" class="pf-table pf-table--compact">
      <tbody id="planningBody">
        <colgroup>
          <col class="col-month">   <!-- Mois -->
          <col class="col-month">   <!-- Semaine -->
          <col class="col-day">     <!-- Lundi -->
          <col class="col-day">     <!-- Mardi -->
          <col class="col-day">     <!-- Mercredi -->
          <col class="col-day">     <!-- Jeudi -->
          <col class="col-day">     <!-- Vendredi -->
          <col class="col-total">   <!-- # Off -->
          <col class="col-total">   <!-- # Extra -->
          <col class="col-total">   <!-- # Centre -->
          <col class="col-total">   <!-- # Avis -->
          <col class="col-total">   <!-- # Pep malade -->
          <col class="col-total">   <!-- # Pep Présence -->
          <!-- ALEX (6 colonnes) -->
          <col class="col-alex-sub">
          <col class="col-alex-sub">
          <col class="col-alex-sub">
          <col class="col-alex-sub">
          <col class="col-alex-sub">
          <col class="col-alex-sub">
          <!-- LAIA (6 colonnes) -->
          <col class="col-laia-sub">
          <col class="col-laia-sub">
          <col class="col-laia-sub">
          <col class="col-laia-sub">
          <col class="col-laia-sub">
          <col class="col-laia-sub">
        </colgroup>
        <!-- lignes générées par JS -->
      </tbody>

    </table>
    
    <!-- Le menu contextuel est caché par défaut et son contenu est généré par JS -->
    <div id="selectionMenu" class="fc-selection-menu"></div>
  </div>
</section>

<!-- ===================================================================== -->
<!--  CHARGEMENT DU SCRIPT JAVASCRIPT PRINCIPAL                            -->
<!-- ===================================================================== -->
<script src="/modules/family-calendar/family-calendar.js"></script>

<?php
// Inclusion du pied de page
require __DIR__ . '/footer.php';
?>
