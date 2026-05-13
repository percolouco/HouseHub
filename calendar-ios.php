<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle = 'Calendrier iOS — HouseHub';
$activePage = 'calendar-ios';
$mainClass = 'pf-calendar-ios-page';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/calendar-ios/assets/calendar-ios.css">

<div class="pf-container ios-calendar-wrap">
  <section class="pf-panel-card">
    <div class="ios-calendar-head">
      <h1>📱 <?= tr('menu_calendar_ios') ?></h1>
      <div class="ios-calendar-head-actions">
        <button class="pf-btn btn-secondary" id="ios-sync-btn">Synchroniser</button>
      </div>
    </div>
    <p class="pf-muted-note">Les événements affichés viennent de HouseHub après import. <strong>Synchroniser</strong> interroge <strong>tous tes calendriers iCloud</strong> (même compte) sur une fenêtre d’environ trois ans en arrière à quatre ans en avant. Modification et suppression sont renvoyées vers iCloud à la synchro suivante.</p>
    <div class="ios-view-toggle" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
      <button type="button" class="pf-btn btn-secondary ios-view-btn ios-view-btn--active" id="ios-view-month">Vue mois</button>
      <button type="button" class="pf-btn btn-secondary ios-view-btn" id="ios-view-list">Liste</button>
    </div>
  </section>

  <section class="pf-panel-card" id="ios-month-section">
    <div class="ios-cal-toolbar">
      <button type="button" class="pf-btn btn-secondary" id="ios-month-prev" aria-label="Mois précédent">←</button>
      <span id="ios-month-label" class="ios-cal-month-label"></span>
      <button type="button" class="pf-btn btn-secondary" id="ios-month-next" aria-label="Mois suivant">→</button>
    </div>
    <div class="ios-cal-weekdays" aria-hidden="true">
      <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
    </div>
    <div id="ios-cal-grid" class="ios-cal-grid"></div>
    <div id="ios-day-detail" class="ios-day-detail"></div>
  </section>

  <section class="pf-panel-card" id="ios-edit-panel">
    <h2 class="pf-card-h2">Ajouter / Modifier un événement</h2>
    <form id="ios-event-form" class="ios-form-grid">
      <input type="hidden" id="ios-event-id">
      <div class="pf-form-group">
        <label class="pf-label">Titre</label>
        <input class="pf-input" id="ios-title" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Lieu</label>
        <input class="pf-input" id="ios-location">
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Début</label>
        <input class="pf-input" id="ios-start" type="datetime-local" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Fin</label>
        <input class="pf-input" id="ios-end" type="datetime-local" required>
      </div>
      <div class="pf-form-group ios-form-full">
        <label class="pf-label">Description</label>
        <textarea class="pf-input" id="ios-description"></textarea>
      </div>
      <div class="ios-form-full ios-form-actions">
        <button class="pf-btn" type="submit">Enregistrer</button>
        <button class="pf-btn btn-secondary" type="button" id="ios-form-reset">Réinitialiser</button>
      </div>
    </form>
  </section>

  <section class="pf-panel-card" id="ios-list-section">
    <h2 class="pf-card-h2">Événements (liste)</h2>
    <div id="ios-sync-status" class="pf-muted-note"></div>
    <div id="ios-events-list" class="ios-events-list"></div>
  </section>
</div>

<script src="/modules/calendar-ios/assets/calendar-ios.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
