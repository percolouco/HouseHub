<?php
// debug temporaire si besoin
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$pageTitle = "PachaFamily - Family Calendar";
$activePage = "family-calendar";
require __DIR__ . '/header.php';
?>

<h1>Family Calendar</h1>
<p>Planning annuel de septembre a aout - Carole (assistante maternelle), Alex et Laia (CP / RTT / JA).</p>

<section class="pf-section pf-section--panel">
  <div class="pf-flex pf-flex--wrap pf-gap-lg">
    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Soldes initiaux</h2>
      <div class="pf-card-body">
        <p><strong>Alex</strong></p>
        <label>CP :
          <input id="alexCpInit" type="number" step="0.25" value="24.5">
        </label><br>
        <label>RTT :
          <input id="alexRttInit" type="number" step="0.25" value="1.5">
        </label><br>
        <label>JA :
          <input id="alexJaInit" type="number" step="0.25" value="1">
        </label>

        <p><strong>Laia</strong></p>
        <label>CP :
          <input id="laiaCpInit" type="number" step="0.25" value="19">
        </label><br>
        <label>RTT :
          <input id="laiaRttInit" type="number" step="0.25" value="4">
        </label><br>
        <label>JA :
          <input id="laiaJaInit" type="number" step="0.25" value="4">
        </label>
      </div>
    </div>

    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Filtres</h2>
      <div class="pf-card-body">
        <label>
          <input id="showOnlyCaroleOff" type="checkbox">
          Voir uniquement les semaines ou Carole est en conges
        </label><br>
        <label>
          <input id="showOnlySchoolHoliday" type="checkbox">
          Voir uniquement les vacances scolaires
        </label>
      </div>
    </div>

    <div class="pf-card pf-card--small">
      <h2 class="pf-card-title">Resume</h2>
      <div class="pf-card-body" id="summaryText">
        <!-- Rempli par JS -->
      </div>
    </div>
  </div>
</section>

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
          <th colspan="2">Alex</th>
          <th colspan="2">Laia</th>
        </tr>
        <tr>
          <th></th>
          <th></th>
          <th>jj/mm</th>
          <th>jj/mm</th>
          <th>jj/mm</th>
          <th>jj/mm</th>
          <th>jj/mm</th>
          <th>jours</th>
          <th>jours</th>
          <th>jours</th>
          <th>jours</th>
          <th>Total</th>
          <th>Détail</th>
          <th>Total</th>
          <th>Détail</th>
        </tr>
      </thead>
      <tbody id="planningBody">
        <!-- Rempli par family-calendar.js -->
      </tbody>
    </table>
  </div>
</section>

<script src="/assets/js/family-calendar.js"></script>

<?php
require __DIR__ . '/footer.php';
