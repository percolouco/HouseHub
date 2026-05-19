<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = tr('mod_liste_name') . ' — HouseHub';
$activePage = 'liste';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/liste/assets/liste.css">

<main class="liste-main">

  <!-- Tabs des listes -->
  <div class="liste-tabs-bar">
    <div class="liste-tabs" id="liste-tabs"></div>
    <button class="liste-tab-add" id="btn-add-list" title="<?= htmlspecialchars(tr('liste_new_list')) ?>">+</button>
  </div>

  <!-- Formulaire de création de liste inline -->
  <div class="liste-new-form hidden" id="liste-new-form">
    <input type="text" id="new-list-name" maxlength="100"
           placeholder="<?= htmlspecialchars(tr('liste_list_name_placeholder')) ?>" autocomplete="off">
    <button id="btn-confirm-new-list"><?= htmlspecialchars(tr('liste_create')) ?></button>
    <button id="btn-cancel-new-list"><?= htmlspecialchars(tr('cancel')) ?></button>
  </div>

  <!-- Corps de la liste active -->
  <div class="liste-body" id="liste-body">

    <div class="liste-add-card">
      <input type="text" id="liste-input" maxlength="500"
             placeholder="<?= htmlspecialchars(tr('liste_placeholder_add')) ?>" autocomplete="off">
      <button id="btn-liste-add"><?= htmlspecialchars(tr('liste_btn_add')) ?></button>
    </div>

    <div class="liste-toolbar" id="liste-toolbar">
      <button class="btn-tool" id="btn-uncheck-all"><?= htmlspecialchars(tr('liste_btn_uncheck_all')) ?></button>
      <button class="btn-tool btn-tool-danger" id="btn-delete-picked"><?= htmlspecialchars(tr('liste_btn_delete_picked')) ?></button>
      <button class="btn-tool btn-tool-danger" id="btn-clear-all"><?= htmlspecialchars(tr('liste_btn_clear_all')) ?></button>
    </div>

    <div id="liste-items-root"></div>
    <div id="liste-history-root"></div>

  </div>

</main>

<script>
const LISTE_TRANSLATIONS = {
  already_in:           <?= json_encode(tr('liste_already_in')) ?>,
  confirm_clear:        <?= json_encode(tr('liste_confirm_clear')) ?>,
  confirm_delete_list:  <?= json_encode(tr('liste_confirm_delete_list')) ?>,
  rename_list:          <?= json_encode(tr('liste_rename_list')) ?>,
  new_name:             <?= json_encode(tr('liste_new_name')) ?>,
  list_deleted:         <?= json_encode(tr('liste_list_deleted')) ?>,
  list_created:         <?= json_encode(tr('liste_list_created')) ?>,
  list_renamed:         <?= json_encode(tr('liste_list_renamed')) ?>,
  edit_placeholder:     <?= json_encode(tr('liste_edit_placeholder')) ?>,
  history_title:        <?= json_encode(tr('liste_history_title')) ?>,
  added:                <?= json_encode(tr('liste_added')) ?>,
  updated:              <?= json_encode(tr('liste_updated')) ?>,
  deleted:              <?= json_encode(tr('liste_deleted')) ?>,
  cancel:               <?= json_encode(tr('cancel')) ?>,
};
</script>
<script src="/modules/liste/assets/liste.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
