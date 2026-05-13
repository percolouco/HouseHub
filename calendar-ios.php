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
        <button type="button" class="ios-agenda-btn ios-agenda-btn--ghost" id="ios-sync-btn">Synchroniser</button>
      </div>
    </div>
    <p class="ios-agenda-intro">Les événements viennent de HouseHub après synchro iCloud. <strong>Synchroniser</strong> met à jour tous les calendriers du compte ; les changements locaux partent au prochain envoi.</p>

    <details class="ios-cal-prefs-details" id="ios-cal-prefs-details">
      <summary class="ios-cal-prefs-summary">Calendriers affichés et couleurs</summary>
      <p class="ios-cal-prefs-help">Décoche un calendrier pour le masquer dans l’agenda. La couleur est libre (repère la teinte iOS sur ton téléphone puis copie-la avec le sélecteur).</p>
      <div id="ios-calendar-prefs-rows" class="ios-cal-prefs-rows"></div>
      <p id="ios-calendar-prefs-msg" class="ios-cal-prefs-msg" role="status"></p>
      <button type="button" class="pf-btn" id="ios-calendar-prefs-save">Enregistrer affichage &amp; couleurs</button>
    </details>
  </section>

  <section class="pf-panel-card ios-agenda-card">
    <div class="ios-agenda-toolbar">
      <div class="ios-agenda-title" id="ios-agenda-title" aria-live="polite">—</div>
      <div class="ios-agenda-segments" role="tablist">
        <button type="button" class="ios-seg" data-ios-view="day" role="tab">Jour</button>
        <button type="button" class="ios-seg ios-seg--active" data-ios-view="week" role="tab">Semaine</button>
        <button type="button" class="ios-seg" data-ios-view="month" role="tab">Mois</button>
      </div>
      <div class="ios-agenda-nav">
        <button type="button" class="ios-agenda-btn" id="ios-agenda-prev" aria-label="Période précédente">‹</button>
        <button type="button" class="ios-agenda-btn" id="ios-agenda-next" aria-label="Période suivante">›</button>
        <button type="button" class="ios-agenda-today" id="ios-agenda-today">Aujourd'hui</button>
        <button type="button" class="ios-agenda-add" id="ios-agenda-add" title="Nouvel événement">+</button>
        <button type="button" class="ios-agenda-listlink" id="ios-view-list">Liste</button>
      </div>
    </div>
    <div id="ios-agenda-mount" class="ios-agenda-mount"></div>
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

  <section class="pf-panel-card" id="ios-list-section" style="display:none">
    <h2 class="pf-card-h2">Événements (liste)</h2>
    <div id="ios-sync-status" class="ios-agenda-intro"></div>
    <div id="ios-events-list" class="ios-events-list"></div>
  </section>
</div>

<script src="/modules/calendar-ios/assets/calendar-ios.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
