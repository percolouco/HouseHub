<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = tr('mod_groceries_name') . ' — HouseHub';
$activePage = 'groceries';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/groceries/assets/groceries.css">
<div id="groceries-toasts" class="groceries-toast-container"></div>

<div class="groceries-layout">
  <div class="groceries-hero">
    <h1>🛒 <?= htmlspecialchars(tr('mod_groceries_name')) ?></h1>
    <p><?= htmlspecialchars(tr('mod_groceries_desc')) ?></p>
    <p id="groceries-subtitle" class="pf-muted-note" style="margin-top:0.5rem;font-size:0.85rem"></p>
  </div>

  <div class="groceries-quick-add">
    <input type="text" id="groceries-new" class="form-control" placeholder="<?= htmlspecialchars(tr('groceries_placeholder_add')) ?>" autocomplete="off" maxlength="500">
    <button type="button" class="btn btn-primary" onclick="addFromInput()"><?= htmlspecialchars(tr('groceries_btn_add')) ?></button>
  </div>

  <div class="groceries-toolbar">
    <button type="button" class="btn btn-secondary btn-sm" onclick="uncheckAll()"><?= htmlspecialchars(tr('groceries_btn_uncheck_all')) ?></button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="deletePicked()"><?= htmlspecialchars(tr('groceries_btn_delete_picked')) ?></button>
  </div>

  <div id="groceries-lists"></div>
</div>

<script src="/modules/groceries/assets/groceries.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
