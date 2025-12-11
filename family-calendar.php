<?php
// Active l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// On se connecte à la base de données
require __DIR__ . '/includes/db.php';

// --- On récupère TOUS les événements sauvegardés en base ---
$stmt_events = $pdo->query("SELECT * FROM pf_events");
$dbEvents = $stmt_events->fetchAll();

// --- Configuration de la page ---
$pageTitle = "PachaFamily - Family Calendar";
$activePage = "family-calendar";
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
          <div class="pf-legend-color fc-day--school-holiday"></div>
          <span>Vacances scolaires</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-day--public-holiday"></div>
          <span>Jour férié</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-day--off-carole"></div>
          <span>Off Carole</span>
        </div>
        <div class="pf-legend-item">
          <div class="pf-legend-color fc-day--extra-off-carole"></div>
          <span>Extra Off Carole</span>
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
    <div id="fc-month-calendar" class="fc-month-calendar">
      <!-- Le calendrier mensuel sera généré par JavaScript -->
    </div>
    <!-- Le menu contextuel pour le calendrier mensuel -->
    <div id="fc-month-selectionMenu" class="fc-selection-menu"></div>
  </div>
</section>

<!-- ===================================================================== -->
<!--  PLANNING PRINCIPAL                                                   -->
<!-- ===================================================================== -->
<section class="pf-section">
  <h2>Planning par semaine</h2>
  <div class="pf-table-wrapper">
    <table id="planningTable" class="pf-table pf-table--compact">
      <thead>
        <tr>
          <th>Mois</th>
          <th>Semaine</th>
          <th>Lundi</th>
          <th>Mardi</th>
          <th>Mercredi</th>
          <th>Jeudi</th>
          <th>Vendredi</th>
          <th># Off Carole</th>
          <th># Extra off Carole</th>
          <th>#Centre</th>
          <th>#Avis</th>
        </tr>
      </thead>
      <tbody id="planningBody">
        <!-- Le contenu de ce tableau est entièrement généré par family-calendar.js -->
      </tbody>
    </table>
    
    <!-- Le menu contextuel est caché par défaut et son contenu est généré par JS -->
    <div id="selectionMenu" class="fc-selection-menu"></div>
  </div>
</section>


<!-- ===================================================================== -->
<!--  CHARGEMENT DU SCRIPT JAVASCRIPT PRINCIPAL                            -->
<!-- ===================================================================== -->
<script src="/assets/js/family-calendar.js"></script>


<?php
// Inclusion du pied de page
require __DIR__ . '/footer.php';
?>
