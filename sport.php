<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = tr('sport_page_title');
$activePage = 'sport';
require __DIR__ . '/header.php';
$h = fn(string $k) => htmlspecialchars(tr($k), ENT_QUOTES);
$locale = ['fr' => 'fr-FR', 'en' => 'en-GB', 'ca' => 'ca-ES'][$current_lang ?? 'fr'] ?? 'fr-FR';
?>

<link rel="stylesheet" href="<?= $hhAsset('/modules/sport/assets/sport.css') ?>">
<div id="toasts" class="toast-container"></div>

<div class="pf-container">
  <div class="pf-hero">
    <div class="pf-hero-title">
      <h1>⚽ <?= $h('menu_sport') ?></h1>
      <button class="pf-gear" id="sp-gear" title="<?= $h('sport_settings') ?>" aria-label="<?= $h('sport_settings') ?>"></button>
    </div>
    <nav class="pf-pills">
      <button class="pf-pill active" data-tab="upcoming">📅 <?= $h('sport_tab_upcoming') ?></button>
      <button class="pf-pill" data-tab="results">✅ <?= $h('sport_tab_results') ?></button>
    </nav>
  </div>

  <div class="sp-filters">
    <div class="pf-seg" id="sp-filter" role="group" aria-label="<?= $h('sport_label_team') ?>"></div>
    <div class="pf-seg" id="sp-filter-comp" role="group" aria-label="<?= $h('sport_label_comp') ?>"></div>
    <button class="btn-icon-outline sp-refresh" id="sp-refresh" title="<?= $h('sport_refresh') ?>" aria-label="<?= $h('sport_refresh') ?>">↻</button>
  </div>
  <div id="sp-next" class="sp-next"></div>
  <div id="sp-list"></div>
  <p class="sp-foot" id="sp-foot"></p>
</div>

<!-- ── MODAL : PARAMÈTRES SPORT ──────────────────────────────────── -->
<div class="pf-modal" id="sp-modal">
  <div class="pf-modal-content pf-modal-sm">
    <div class="pf-modal-header">
      <h3 class="pf-modal-title"><?= $h('sport_modal_title') ?></h3>
      <button class="pf-modal-close" onclick="spCloseModal()">×</button>
    </div>
    <div class="pf-modal-body">
      <p class="sp-note"><?= $h('sport_modal_intro') ?></p>
      <div class="form-group">
        <label class="form-label" for="sp-key"><?= $h('sport_key_label') ?></label>
        <input type="password" id="sp-key" class="form-control" autocomplete="off" placeholder="<?= $h('sport_key_ph') ?>">
        <small id="sp-key-state" class="sp-note"></small>
      </div>
      <div class="sp-note"><?= $h('sport_teams_note') ?></div>
    </div>
    <div class="modal-footer">
      <button class="pf-btn btn-danger btn-sm" id="sp-key-remove" type="button"><?= $h('sport_key_remove') ?></button>
      <button class="pf-btn btn-primary" id="sp-key-save" type="button"><?= $h('sport_key_save') ?></button>
    </div>
  </div>
</div>

<script>window.SPORT_CSRF = <?= json_encode(csrf_token()) ?>; window.SPORT_LOCALE = <?= json_encode($locale) ?>;</script>
<script src="<?= $hhAsset('/modules/sport/assets/sport.js') ?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
