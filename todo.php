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
  <aside class="todo-sidebar" id="todo-sidebar">
    <div class="todo-sidebar-top">
      <button class="btn btn-primary" onclick="openAddTodo()" style="width:100%;justify-content:center">+ Nouvelle tâche</button>
    </div>

    <!-- Smart views -->
    <div class="todo-nav-section">Vues</div>
    <a class="todo-nav-item active" data-filter="all" onclick="setFilter('all')">
      <span>📋</span> Toutes
      <span class="todo-nav-badge" id="badge-all">0</span>
    </a>
    <a class="todo-nav-item" data-filter="today" onclick="setFilter('today')">
      <span>☀️</span> Aujourd'hui
      <span class="todo-nav-badge urgent" id="badge-today">0</span>
    </a>
    <a class="todo-nav-item" data-filter="upcoming" onclick="setFilter('upcoming')">
      <span>📅</span> À venir
      <span class="todo-nav-badge" id="badge-upcoming">0</span>
    </a>
    <a class="todo-nav-item" data-filter="overdue" onclick="setFilter('overdue')">
      <span>⚠️</span> En retard
      <span class="todo-nav-badge urgent" id="badge-overdue">0</span>
    </a>
    <a class="todo-nav-item" data-filter="done" onclick="setFilter('done')">
      <span>✅</span> Terminées
      <span class="todo-nav-badge" id="badge-done">0</span>
    </a>

    <!-- User lists -->
    <div class="todo-nav-section" style="display:flex;align-items:center;justify-content:space-between;padding-right:.75rem">
      Listes
      <button class="btn btn-ghost btn-icon btn-sm" onclick="openListModal()" title="Nouvelle liste" style="padding:.1rem .3rem;font-size:.85rem">+</button>
    </div>
    <div id="todo-lists-nav"></div>
  </aside>

  <!-- ── MAIN ───────────────────────────────────────────────────────────────── -->
  <div class="todo-main">

    <!-- Header -->
    <div class="todo-main-header">
      <div>
        <h2 id="todo-view-title">Toutes les tâches</h2>
        <div class="todo-subtitle" id="todo-view-subtitle"></div>
      </div>
      <div class="todo-header-actions">
        <button class="show-done-btn" id="show-done-btn" onclick="toggleShowDone()">
          <span id="show-done-icon">👁</span> <span id="show-done-label">Afficher terminées</span>
        </button>
        <button class="btn btn-primary btn-sm" onclick="openAddTodo()">+ Tâche</button>
      </div>
    </div>

    <!-- Stats bar -->
    <div class="todo-stats" id="todo-stats-bar"></div>

    <!-- Quick add -->
    <div class="todo-quick-add">
      <span style="color:var(--text-muted)">+</span>
      <input type="text" id="quick-add-input" placeholder="Ajouter une tâche rapide… (Entrée pour valider)">
      <button class="btn btn-ghost btn-sm" id="quick-add-list-btn" onclick="openAddTodo(document.getElementById('quick-add-input').value)">Options</button>
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
      <button class="todo-modal-close" onclick="closeTodoModal()">×</button>
    </div>
    <div class="todo-modal-body">
      <input type="hidden" id="todo-id">

      <div class="form-group">
        <label class="form-label">Titre *</label>
        <input type="text" id="todo-title" class="form-control" placeholder="Ex : Appeler le plombier" autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="todo-notes" class="form-control" placeholder="Détails, liens, infos…"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date limite</label>
          <input type="date" id="todo-due" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Liste</label>
          <select id="todo-list-select" class="form-control">
            <option value="">— Aucune —</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Priorité</label>
        <div class="priority-opts">
          <div class="priority-opt sel-none" data-p="none" onclick="selectPriority('none')">— Aucune</div>
          <div class="priority-opt" data-p="low" onclick="selectPriority('low')">🟢 Basse</div>
          <div class="priority-opt" data-p="medium" onclick="selectPriority('medium')">🟡 Moyenne</div>
          <div class="priority-opt" data-p="high" onclick="selectPriority('high')">🔴 Haute</div>
        </div>
        <input type="hidden" id="todo-priority" value="none">
      </div>
    </div>
    <div class="todo-modal-footer">
      <button class="btn btn-danger btn-sm" id="todo-delete-btn" onclick="deleteTodo()" style="display:none;margin-right:auto">Supprimer</button>
      <button class="btn btn-secondary" onclick="closeTodoModal()">Annuler</button>
      <button class="btn btn-primary" onclick="saveTodo()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL : LISTE ──────────────────────────────────────────────────────────── -->
<div class="todo-modal-backdrop" id="list-modal">
  <div class="todo-modal">
    <div class="todo-modal-header">
      <h3 id="list-modal-title">Nouvelle liste</h3>
      <button class="todo-modal-close" onclick="closeListModal()">×</button>
    </div>
    <div class="todo-modal-body">
      <input type="hidden" id="list-id">

      <div class="form-group">
        <label class="form-label">Nom *</label>
        <input type="text" id="list-name" class="form-control" placeholder="Ex : Maison, Travail…">
      </div>

      <div class="form-group">
        <label class="form-label">Icône</label>
        <input type="text" id="list-icon" class="form-control" placeholder="📋" maxlength="4" style="font-size:1.2rem;width:80px">
      </div>

      <div class="form-group">
        <label class="form-label">Couleur</label>
        <div class="color-swatches" id="list-color-swatches"></div>
        <input type="hidden" id="list-color" value="#3b82f6">
      </div>
    </div>
    <div class="todo-modal-footer">
      <button class="btn btn-danger btn-sm" id="list-delete-btn" onclick="deleteList()" style="display:none;margin-right:auto">Supprimer</button>
      <button class="btn btn-secondary" onclick="closeListModal()">Annuler</button>
      <button class="btn btn-primary" onclick="saveList()">Enregistrer</button>
    </div>
  </div>
</div>

<script src="/modules/todo/assets/todo.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
