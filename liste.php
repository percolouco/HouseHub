<?php
require_once 'includes/auth.php';
require_login();
$lang = $_SESSION['lang'] ?? 'fr';
require_once 'includes/lang/load.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= tr('mod_liste_name') ?> — HouseHub</title>
  <?php require 'includes/head_common.php'; ?>
  <link rel="stylesheet" href="/modules/liste/assets/liste.css">
</head>
<body>
<?php require 'includes/navbar.php'; ?>

<main class="liste-main">

  <!-- Tabs des listes -->
  <div class="liste-tabs-bar">
    <div class="liste-tabs" id="liste-tabs"></div>
    <button class="liste-tab-add" id="btn-add-list" title="<?= tr('liste_new_list') ?>">+</button>
  </div>

  <!-- Formulaire de création de liste inline -->
  <div class="liste-new-form hidden" id="liste-new-form">
    <input type="text" id="new-list-name" maxlength="100"
           placeholder="<?= tr('liste_list_name_placeholder') ?>" autocomplete="off">
    <button id="btn-confirm-new-list"><?= tr('liste_create') ?></button>
    <button id="btn-cancel-new-list"><?= tr('cancel') ?></button>
  </div>

  <!-- Corps de la liste active -->
  <div class="liste-body" id="liste-body">

    <!-- Ajout rapide -->
    <div class="liste-add-card">
      <input type="text" id="liste-input" maxlength="500"
             placeholder="<?= tr('liste_placeholder_add') ?>" autocomplete="off">
      <button id="btn-liste-add"><?= tr('liste_btn_add') ?></button>
    </div>

    <!-- Toolbar -->
    <div class="liste-toolbar" id="liste-toolbar">
      <button class="btn-tool" id="btn-uncheck-all"><?= tr('liste_btn_uncheck_all') ?></button>
      <button class="btn-tool btn-tool-danger" id="btn-delete-picked"><?= tr('liste_btn_delete_picked') ?></button>
      <button class="btn-tool btn-tool-danger" id="btn-clear-all"><?= tr('liste_btn_clear_all') ?></button>
    </div>

    <!-- Articles -->
    <div id="liste-items-root"></div>

    <!-- Historique -->
    <div id="liste-history-root"></div>

  </div>

  <!-- État vide (aucune liste) -->
  <div class="liste-empty-state hidden" id="liste-empty-state">
    <div class="liste-empty-icon">📝</div>
    <p><?= tr('liste_empty_state') ?></p>
  </div>

</main>

<script>
const LISTE_TRANSLATIONS = {
  placeholder_add:    <?= json_encode(tr('liste_placeholder_add')) ?>,
  btn_add:            <?= json_encode(tr('liste_btn_add')) ?>,
  btn_uncheck_all:    <?= json_encode(tr('liste_btn_uncheck_all')) ?>,
  btn_delete_picked:  <?= json_encode(tr('liste_btn_delete_picked')) ?>,
  btn_clear_all:      <?= json_encode(tr('liste_btn_clear_all')) ?>,
  subtitle:           <?= json_encode(tr('liste_subtitle')) ?>,
  history_title:      <?= json_encode(tr('liste_history_title')) ?>,
  added:              <?= json_encode(tr('liste_added')) ?>,
  updated:            <?= json_encode(tr('liste_updated')) ?>,
  deleted:            <?= json_encode(tr('liste_deleted')) ?>,
  already_in:         <?= json_encode(tr('liste_already_in')) ?>,
  confirm_clear:      <?= json_encode(tr('liste_confirm_clear')) ?>,
  confirm_delete_list:<?= json_encode(tr('liste_confirm_delete_list')) ?>,
  rename_list:        <?= json_encode(tr('liste_rename_list')) ?>,
  new_name:           <?= json_encode(tr('liste_new_name')) ?>,
  list_deleted:       <?= json_encode(tr('liste_list_deleted')) ?>,
  list_created:       <?= json_encode(tr('liste_list_created')) ?>,
  list_renamed:       <?= json_encode(tr('liste_list_renamed')) ?>,
  edit_placeholder:   <?= json_encode(tr('liste_edit_placeholder')) ?>,
  cancel:             <?= json_encode(tr('cancel')) ?>,
};
</script>
<script src="/modules/liste/assets/liste.js"></script>
</body>
</html>
