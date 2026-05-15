<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = 'PrintVault — HouseHub';
$activePage = 'printvault';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/printvault/assets/printvault.css">
<div id="pv-toasts" class="pv-toast-container"></div>

<div class="pv-layout">

  <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
  <aside class="pv-sidebar">
    <div class="pv-sidebar-top">
      <button class="btn btn-primary" onclick="openUpload()" style="width:100%;justify-content:center">+ Upload</button>
      <div class="pv-search">
        <span style="color:var(--text-muted);font-size:.9rem">🔍</span>
        <input type="text" id="pv-search" placeholder="Rechercher…">
      </div>
    </div>
    <div class="pv-type-chips">
      <span class="pv-chip stl"   data-type="stl"   onclick="setType('stl')">STL</span>
      <span class="pv-chip 3mf"   data-type="3mf"   onclick="setType('3mf')">3MF</span>
      <span class="pv-chip gcode" data-type="gcode"  onclick="setType('gcode')">GCode</span>
    </div>
    <div id="pv-sidebar-nav"></div>
  </aside>

  <!-- ── MAIN ─────────────────────────────────────────────────────────────── -->
  <div class="pv-main">
    <div class="pv-main-header">
      <div>
        <h2>🖨️ PrintVault</h2>
        <div class="pv-subtitle" id="pv-subtitle"></div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="openUpload()">+ Upload</button>
    </div>
    <div class="pv-grid" id="pv-grid">
      <div class="pv-empty" style="grid-column:1/-1"><div class="icon">🖨️</div><p>Chargement…</p></div>
    </div>
  </div>
</div>

<!-- ── MODAL : UPLOAD ──────────────────────────────────────────────────────── -->
<div class="pv-modal-backdrop" id="pv-upload-modal">
  <div class="pv-modal">
    <div class="pv-modal-header">
      <h3>Uploader un modèle</h3>
      <button class="pv-modal-close" onclick="document.getElementById('pv-upload-modal').classList.remove('show')">×</button>
    </div>
    <div class="pv-modal-body">
      <div class="pv-drop-zone" id="pv-drop-zone">
        <div class="icon">📁</div>
        <div>Glissez un fichier ici ou cliquez</div>
        <div style="font-size:.75rem;margin-top:.25rem;color:var(--text-muted)">STL · 3MF · GCode</div>
        <div class="pv-file-chosen" id="pv-upload-filename"></div>
        <input type="file" id="pv-file-input" accept=".stl,.3mf,.gcode,.gco,.g" style="display:none">
      </div>
      <div class="form-group">
        <label class="form-label">Nom *</label>
        <input type="text" id="pv-upload-name" class="form-control" placeholder="Nom du modèle">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input type="text" id="pv-upload-desc" class="form-control" placeholder="Optionnel">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select id="pv-upload-cat" class="form-control"></select>
        </div>
        <div class="form-group">
          <label class="form-label">Tags</label>
          <input type="text" id="pv-upload-tags" class="form-control" placeholder="tag1, tag2">
        </div>
      </div>
      <div id="pv-upload-progress" style="display:none">
        <div class="pv-progress"><div class="pv-progress-bar" id="pv-upload-bar" style="width:0%"></div></div>
        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Upload en cours… (parsing géométrie)</div>
      </div>
    </div>
    <div class="pv-modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('pv-upload-modal').classList.remove('show')">Annuler</button>
      <button class="btn btn-primary" onclick="saveUpload()">Uploader</button>
    </div>
  </div>
</div>

<script src="/modules/printvault/assets/printvault.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
