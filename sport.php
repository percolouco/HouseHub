<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = 'Sport — HouseHub';
$activePage = 'sport';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= $hhAsset('/modules/sport/assets/sport.css') ?>">
<div id="toasts" class="toast-container"></div>

<div class="pf-container">
  <div class="pf-hero">
    <div class="pf-hero-title">
      <h1>⚽ Sport</h1>
      <button class="pf-gear" id="sp-gear" title="Paramètres Sport" aria-label="Paramètres Sport"></button>
      <button class="btn-icon-outline" id="sp-refresh" title="Actualiser" aria-label="Actualiser">↻</button>
    </div>
    <nav class="pf-pills">
      <button class="pf-pill active" data-tab="upcoming">📅 À venir</button>
      <button class="pf-pill" data-tab="results">✅ Résultats</button>
    </nav>
  </div>

  <div class="sp-filters">
    <div class="pf-seg" id="sp-filter" role="group" aria-label="Équipe"></div>
    <div class="pf-seg" id="sp-filter-comp" role="group" aria-label="Championnat"></div>
  </div>
  <div id="sp-next" class="sp-next"></div>
  <div id="sp-list"></div>
  <p class="sp-foot" id="sp-foot"></p>
</div>

<!-- ── MODAL : PARAMÈTRES SPORT ──────────────────────────────────── -->
<div class="pf-modal" id="sp-modal">
  <div class="pf-modal-content pf-modal-sm">
    <div class="pf-modal-header">
      <h3 class="pf-modal-title">Paramètres Sport</h3>
      <button class="pf-modal-close" onclick="spCloseModal()">×</button>
    </div>
    <div class="pf-modal-body">
      <p class="sp-note">Les calendriers viennent de <b>football-data.org</b> (gratuit). Crée un compte gratuit pour obtenir ta clé, puis colle-la ici. Elle reste sur le serveur et n'est jamais réaffichée.</p>
      <div class="form-group">
        <label class="form-label" for="sp-key">Clé football-data.org</label>
        <input type="password" id="sp-key" class="form-control" autocomplete="off" placeholder="Coller la clé">
        <small id="sp-key-state" class="sp-note"></small>
      </div>
      <div class="sp-note">Équipes suivies : Paris Saint-Germain, FC Barcelone. Les données sont mises en cache 6 h pour limiter les appels à l'API.</div>
    </div>
    <div class="modal-footer">
      <button class="pf-btn btn-danger btn-sm" id="sp-key-remove" type="button">Retirer la clé</button>
      <button class="pf-btn btn-primary" id="sp-key-save" type="button">Enregistrer et actualiser</button>
    </div>
  </div>
</div>

<script>window.SPORT_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= $hhAsset('/modules/sport/assets/sport.js') ?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
