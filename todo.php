<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = 'Todo — HouseHub';
$activePage = 'todo';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/todo/assets/todo.css">
<div id="todo-toasts" class="todo-toast-container"></div>

<div class="todo-layout">

  <!-- ── SIDEBAR ────────────────────────────────────────────────────────────── -->
  <aside class="todo-sidebar">
    <div class="todo-sidebar-top">
      <button class="btn btn-primary" onclick="openAddTodo()" style="width:100%;justify-content:center">+ Nouvelle tâche</button>
    </div>
    <!-- Contenu rendu dynamiquement par JS (vues + listes) -->
    <div id="todo-sidebar-lists"></div>
  </aside>

  <!-- ── MAIN ───────────────────────────────────────────────────────────────── -->
  <div class="todo-main">

    <!-- Header -->
    <div class="todo-main-header">
      <div>
        <h2 id="todo-header-title">Toutes les tâches</h2>
        <div class="todo-subtitle" id="todo-view-subtitle"></div>
      </div>
      <div class="todo-header-actions">
        <button class="show-done-btn" id="show-done-btn" onclick="toggleShowDone()">
          👁 Afficher terminées
        </button>
        <button class="btn btn-ghost btn-icon" onclick="openSettings()" title="Paramètres Todo">⚙️</button>
        <button class="btn btn-primary btn-sm" onclick="openAddTodo()">+ Tâche</button>
      </div>
    </div>

    <!-- Quick add -->
    <div class="todo-quick-add">
      <span style="color:var(--text-muted)">+</span>
      <input type="text" id="quick-add-input" placeholder="Ajouter une tâche rapide… (Entrée pour valider)">
      <select id="quick-add-list" class="form-control" style="width:auto;font-size:.8rem;padding:.25rem .5rem;border:none;background:transparent;color:var(--text-muted)"></select>
    </div>

    <!-- Todo list -->
    <div class="todo-list" id="todo-list"></div>

  </div>
</div>

<!-- ── MODAL : TÂCHE ──────────────────────────────────────────────────────────── -->
<div class="todo-modal-backdrop" id="todo-modal">
  <div class="todo-modal">
    <div class="todo-modal-header">
      <h3 id="todo-modal-title">Nouvelle tâche</h3>
      <button class="todo-modal-close" onclick="closeModal('todo-modal')">×</button>
    </div>
    <div class="todo-modal-body">

      <div class="form-group">
        <label class="form-label">Titre *</label>
        <input type="text" id="todo-form-title" class="form-control" placeholder="Ex : Appeler le plombier">
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="todo-form-notes" class="form-control" placeholder="Détails, liens, infos…"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date limite</label>
          <input type="date" id="todo-form-due" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Heure</label>
          <input type="time" id="todo-form-time" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Liste</label>
        <select id="todo-form-list" class="form-control">
          <option value="">— Aucune —</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Priorité</label>
        <div class="priority-opts">
          <div class="priority-opt sel-none" data-p="none" onclick="setPriority('none')">— Aucune</div>
          <div class="priority-opt" data-p="low" onclick="setPriority('low')">🟢 Basse</div>
          <div class="priority-opt" data-p="medium" onclick="setPriority('medium')">🟡 Moyenne</div>
          <div class="priority-opt" data-p="high" onclick="setPriority('high')">🔴 Haute</div>
        </div>
      </div>
    </div>
    <div class="todo-modal-footer">
      <button class="btn btn-danger btn-sm" id="todo-delete-btn" onclick="deleteTodoFromModal()" style="display:none;margin-right:auto">Supprimer</button>
      <button class="btn btn-secondary" onclick="closeModal('todo-modal')">Annuler</button>
      <button class="btn btn-primary" onclick="saveTodo()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL : LISTE ──────────────────────────────────────────────────────────── -->
<div class="todo-modal-backdrop" id="list-modal">
  <div class="todo-modal">
    <div class="todo-modal-header">
      <h3 id="list-modal-title">Nouvelle liste</h3>
      <button class="todo-modal-close" onclick="closeModal('list-modal')">×</button>
    </div>
    <div class="todo-modal-body">

      <div class="form-group">
        <label class="form-label">Nom *</label>
        <input type="text" id="list-form-name" class="form-control" placeholder="Ex : Maison, Travail…">
      </div>

      <div class="form-group">
        <label class="form-label">Icône</label>
        <div id="list-icon-opts" style="display:flex;flex-wrap:wrap;gap:.35rem"></div>
      </div>

      <div class="form-group">
        <label class="form-label">Couleur</label>
        <div class="color-swatches" id="list-color-swatches"></div>
      </div>
    </div>
    <div class="todo-modal-footer">
      <button class="btn btn-danger btn-sm" id="list-delete-btn" onclick="deleteList()" style="display:none;margin-right:auto">Supprimer</button>
      <button class="btn btn-secondary" onclick="closeModal('list-modal')">Annuler</button>
      <button class="btn btn-primary" onclick="saveList()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL : PARAMÈTRES ─────────────────────────────────────────────────────── -->
<div class="todo-modal-backdrop" id="settings-modal">
  <div class="todo-modal">
    <div class="todo-modal-header">
      <h3>⚙️ Paramètres Todo</h3>
      <button class="todo-modal-close" onclick="closeModal('settings-modal')">×</button>
    </div>
    <div class="todo-modal-body">
      <div class="form-group">
        <label class="form-label">Webhook Discord</label>
        <input type="url" id="settings-webhook" class="form-control" placeholder="https://discord.com/api/webhooks/…">
        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem">
          Recevoir une notification Discord quand une tâche est créée ou terminée.
        </div>
      </div>
    </div>
    <div class="todo-modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('settings-modal')">Annuler</button>
      <button class="btn btn-primary" onclick="saveSettings()">Enregistrer</button>
    </div>
  </div>
</div>

<script src="/modules/todo/assets/todo.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
