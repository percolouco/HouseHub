<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = 'Notes — HouseHub';
$activePage = 'memo';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/memo/assets/memo.css">
<div id="memo-toasts" class="memo-toast-container"></div>

<div class="memo-layout">

  <!-- ── SIDEBAR ────────────────────────────────────────────────────────────── -->
  <aside class="memo-sidebar" id="memo-sidebar">
    <div class="memo-sidebar-top">
      <button class="btn btn-primary" onclick="openCreate()" style="width:100%;justify-content:center">+ Nouvelle note</button>
      <div class="memo-search">
        <span style="color:var(--text-muted);font-size:.9rem">🔍</span>
        <input type="text" id="memo-search-input" placeholder="Rechercher…">
      </div>
    </div>
    <div class="memo-tags-list" id="memo-tags-list"></div>
  </aside>

  <!-- ── MAIN ───────────────────────────────────────────────────────────────── -->
  <div class="memo-main">

    <!-- Page : liste ─────────────────────────────────────────────────────── -->
    <div id="memo-page-list" class="memo-page active">
      <div class="memo-list-header">
        <div>
          <div id="memo-list-title" style="font-size:1rem;font-weight:700">📝 Notes</div>
          <div id="memo-list-subtitle" class="memo-list-subtitle"></div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openCreate()">+ Nouvelle note</button>
      </div>
      <div id="memo-notes-grid" class="notes-grid"></div>
      <div id="memo-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:0 1.5rem 1.5rem;gap:.5rem"></div>
    </div>

    <!-- Page : vue note ──────────────────────────────────────────────────── -->
    <div id="memo-page-view" class="memo-page">
      <div class="memo-view-header">
        <div class="memo-view-title" id="view-title"></div>
        <div class="memo-view-meta">
          <div id="view-tags" style="display:flex;gap:.35rem;flex-wrap:wrap"></div>
          <span id="view-date" style="margin-left:auto"></span>
          <div class="memo-view-actions">
            <button class="btn btn-secondary btn-sm" onclick="loadList(currentQ,currentTag)">← Retour</button>
            <button class="btn btn-secondary btn-sm" onclick="openEdit(currentNoteId)">✏️ Modifier</button>
            <button class="btn btn-danger btn-sm" onclick="deleteNote(currentNoteId)">🗑️</button>
          </div>
        </div>
      </div>
      <div class="memo-content-area">
        <div class="md-body" id="view-md"></div>
      </div>
      <div class="memo-attachments" id="view-attachments" style="display:none"></div>
    </div>

    <!-- Page : édition ───────────────────────────────────────────────────── -->
    <div id="memo-page-edit" class="memo-page">
      <div class="memo-edit-area">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;flex-wrap:wrap;gap:.5rem">
          <h2 id="edit-page-title" style="font-size:1rem;font-weight:700;margin:0">Nouvelle note</h2>
          <div style="display:flex;gap:.5rem">
            <button class="btn btn-secondary" onclick="currentNoteId?viewNote(currentNoteId):loadList()">Annuler</button>
            <button class="btn btn-primary" onclick="saveNote()">💾 Enregistrer</button>
          </div>
        </div>
        <div class="memo-edit-form">

          <!-- Titre -->
          <div class="form-group">
            <label class="form-label">Titre *</label>
            <input type="text" id="edit-title" class="form-control" placeholder="Titre de la note…" required>
          </div>

          <!-- Contenu + preview -->
          <div class="form-group">
            <label class="form-label">Contenu <span style="font-size:.72rem;color:var(--text-muted)">(Markdown supporté)</span></label>
            <div class="edit-split">
              <textarea id="edit-content" class="form-control edit-textarea" placeholder="Écrivez ici en Markdown…"></textarea>
              <div class="md-preview-box md-body" id="md-live-preview" style="min-height:320px"></div>
            </div>
          </div>

          <!-- Tags -->
          <div class="form-group">
            <label class="form-label">Tags</label>
            <div class="tags-input-wrap" onclick="document.getElementById('edit-tags-input').focus()">
              <div id="edit-tags-chips" style="display:contents"></div>
              <input type="text" id="edit-tags-input" placeholder="Ajouter un tag…" autocomplete="off">
            </div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem">Appuyez sur Entrée ou virgule pour valider</div>
          </div>

          <!-- Fichiers -->
          <div class="form-group">
            <label class="form-label">Fichiers & Images</label>
            <div id="edit-drop-zone" class="attach-drop">
              📎 Glissez-déposez des fichiers ou cliquez pour choisir (images, PDF…)
              <input type="file" id="edit-file-input" multiple accept="image/*,.pdf,.doc,.docx,.txt,.csv,.zip" style="display:none">
            </div>
            <div id="edit-attach-preview" class="attach-preview-list"></div>
            <div id="edit-existing-attachments" style="margin-top:.75rem"></div>
          </div>

          <!-- URLs -->
          <div class="form-group">
            <label class="form-label">Liens / URLs</label>
            <div id="edit-url-rows"></div>
          </div>

        </div>
      </div>
    </div>

  </div><!-- memo-main -->
</div><!-- memo-layout -->

<!-- Lightbox -->
<div id="memo-lightbox" class="lightbox">
  <img src="" alt="" onclick="event.stopPropagation()">
</div>

<!-- marked.js pour le rendu Markdown -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
if(window.marked){
  marked.setOptions({breaks:true,gfm:true});
}
</script>
<script src="/modules/memo/assets/memo.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
