<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /printvault.php'); exit; }

$s = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
$model = $s->fetch();
if (!$model) { header('Location: /printvault.php'); exit; }

$s2 = $pdo->query("SELECT name, color FROM pf_pv_categories ORDER BY name");
$categories = $s2->fetchAll();

$ext      = strtolower($model['file_type']);
$canView  = in_array($ext, ['stl','3mf','gcode','gco','g']);
$isGcode  = in_array($ext, ['gcode','gco','g']);
$thumbUrl = $model['thumb'] ? '/uploads/printvault/thumbs/' . rawurlencode($model['thumb']) : null;
$typeIcon = ['stl'=>'🟣','3mf'=>'🔵','gcode'=>'🟢','gco'=>'🟢','g'=>'🟢'][$ext] ?? '📄';

function fmtSize(int $b): string {
    if ($b > 1048576) return round($b/1048576,1).' Mo';
    return round($b/1024).' Ko';
}
function fmtDate(string $s): string {
    return date('d/m/Y', strtotime($s));
}

$pageTitle = htmlspecialchars($model['name']) . ' — PrintVault';
$activePage = 'printvault';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/printvault/assets/printvault.css">
<link rel="stylesheet" href="/modules/printvault/assets/viewer.css">
<div id="pv-toasts" class="pv-toast-container"></div>

<div class="pv-detail-page">

  <!-- ── TOP BAR ────────────────────────────────────────────────────────── -->
  <div class="pv-detail-topbar">
    <a href="/printvault.php" class="btn btn-secondary btn-sm">← Retour</a>
    <div class="pv-detail-topbar-title">
      <span class="pv-type-badge pv-type-<?= $ext ?>"><?= strtoupper($ext) ?></span>
      <h1><?= htmlspecialchars($model['name']) ?></h1>
    </div>
    <div style="display:flex;gap:.5rem;margin-left:auto">
      <button class="btn btn-secondary btn-sm" onclick="openEdit()">✏️ Modifier</button>
      <a href="/modules/printvault/api.php?action=file&id=<?= $id ?>" class="btn btn-secondary btn-sm" download>⬇ Télécharger</a>
      <button class="btn btn-danger btn-sm" onclick="deleteModel()">🗑 Supprimer</button>
    </div>
  </div>

  <!-- ── BODY ───────────────────────────────────────────────────────────── -->
  <div class="pv-detail-body">

    <!-- Left: viewer or thumbnail -->
    <div class="pv-detail-left">
      <?php if ($canView): ?>
      <div class="pv-viewer-wrap" id="viewer-wrap">
        <div id="viewer-loading" class="pv-viewer-loading">
          <div class="pv-spinner"></div>
          <div id="viewer-loading-text">Chargement…</div>
        </div>
        <canvas id="viewer-canvas"></canvas>
        <div class="pv-viewer-controls-hint">Clic+glisser : rotation · Scroll : zoom</div>
        <?php if (!$isGcode): ?>
        <button class="pv-viewer-screenshot-btn" onclick="takeScreenshot()" title="Sauvegarder comme aperçu">📸</button>
        <?php endif; ?>
        <?php if ($isGcode): ?>
        <div class="pv-gcode-viewer-legend" id="gcode-legend">
          <div class="pv-legend-item"><span style="display:inline-block;width:12px;height:3px;background:#3b82f6;border-radius:2px;vertical-align:middle"></span> Couches basses</div>
          <div class="pv-legend-item"><span style="display:inline-block;width:12px;height:3px;background:#10b981;border-radius:2px;vertical-align:middle"></span> Couches moyennes</div>
          <div class="pv-legend-item"><span style="display:inline-block;width:12px;height:3px;background:#ef4444;border-radius:2px;vertical-align:middle"></span> Couches hautes</div>
          <div class="pv-legend-item" style="color:#475569"><span style="display:inline-block;width:12px;height:1px;background:#475569;border-radius:2px;vertical-align:middle;opacity:.5"></span> Déplacements</div>
        </div>
        <?php endif; ?>
      </div>
      <?php elseif ($thumbUrl): ?>
      <div class="pv-detail-thumb-large">
        <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="">
      </div>
      <?php else: ?>
      <div class="pv-detail-thumb-large pv-detail-thumb-placeholder">
        <span><?= $typeIcon ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: metadata -->
    <div class="pv-detail-right">

      <?php if ($model['description']): ?>
      <div class="pv-meta-section">
        <p class="pv-detail-description"><?= htmlspecialchars($model['description']) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($model['tags']): ?>
      <div class="pv-meta-section pv-tags-row">
        <?php foreach (array_filter(explode(',', $model['tags'])) as $tag): ?>
        <span class="pv-tag"><?= htmlspecialchars(trim($tag)) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- File info -->
      <div class="pv-meta-section">
        <div class="pv-meta-title">Informations</div>
        <div class="pv-meta-grid">
          <div class="pv-meta-item"><span class="pv-meta-label">Catégorie</span><span class="pv-meta-value"><?= htmlspecialchars($model['category']) ?></span></div>
          <div class="pv-meta-item"><span class="pv-meta-label">Taille fichier</span><span class="pv-meta-value"><?= fmtSize((int)$model['file_size']) ?></span></div>
          <div class="pv-meta-item"><span class="pv-meta-label">Ajouté le</span><span class="pv-meta-value"><?= fmtDate($model['created_at']) ?></span></div>
          <?php if ($model['dim_x'] > 0): ?>
          <div class="pv-meta-item pv-meta-item--full">
            <span class="pv-meta-label">Dimensions</span>
            <span class="pv-meta-value"><?= round($model['dim_x']) ?> × <?= round($model['dim_y']) ?> × <?= round($model['dim_z']) ?> mm<?= $model['volume'] > 0 ? ' · '.round($model['volume'],2).' cm³' : '' ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($isGcode && ($model['gcode_time'] || $model['gcode_filament'] || $model['gcode_nozzle'] || $model['gcode_bed'])): ?>
      <!-- GCode metadata cards -->
      <div class="pv-meta-section">
        <div class="pv-meta-title">Métadonnées impression</div>
        <div class="pv-gcode-cards">
          <?php if ($model['gcode_time']): ?>
          <div class="pv-gcode-card">
            <div class="pv-gcode-icon">⏱</div>
            <div class="pv-gcode-value"><?= htmlspecialchars($model['gcode_time']) ?></div>
            <div class="pv-gcode-label">Temps d'impression estimé</div>
          </div>
          <?php endif; ?>
          <?php if ($model['gcode_filament']): ?>
          <div class="pv-gcode-card">
            <div class="pv-gcode-icon">🧵</div>
            <div class="pv-gcode-value"><?= htmlspecialchars($model['gcode_filament']) ?></div>
            <div class="pv-gcode-label">Filament utilisé</div>
          </div>
          <?php endif; ?>
          <?php if ($model['gcode_nozzle']): ?>
          <div class="pv-gcode-card">
            <div class="pv-gcode-icon">🔥</div>
            <div class="pv-gcode-value"><?= htmlspecialchars($model['gcode_nozzle']) ?></div>
            <div class="pv-gcode-label">Température buse</div>
          </div>
          <?php endif; ?>
          <?php if ($model['gcode_bed']): ?>
          <div class="pv-gcode-card">
            <div class="pv-gcode-icon">🟦</div>
            <div class="pv-gcode-value"><?= htmlspecialchars($model['gcode_bed']) ?></div>
            <div class="pv-gcode-label">Température plateau</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ── MODAL : ÉDITION ─────────────────────────────────────────────────────── -->
<div class="pv-modal-backdrop" id="pv-edit-modal">
  <div class="pv-modal">
    <div class="pv-modal-header">
      <h3>Modifier le modèle</h3>
      <button class="pv-modal-close" onclick="document.getElementById('pv-edit-modal').classList.remove('show')">×</button>
    </div>
    <div class="pv-modal-body">
      <div class="form-group">
        <label class="form-label">Nom *</label>
        <input type="text" id="edit-name" class="form-control" value="<?= htmlspecialchars($model['name']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input type="text" id="edit-desc" class="form-control" value="<?= htmlspecialchars($model['description'] ?? '') ?>">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select id="edit-cat" class="form-control">
            <?php foreach ($categories as $c): ?>
            <option value="<?= htmlspecialchars($c['name']) ?>" <?= $c['name'] === $model['category'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tags</label>
          <input type="text" id="edit-tags" class="form-control" value="<?= htmlspecialchars($model['tags'] ?? '') ?>" placeholder="tag1, tag2">
        </div>
      </div>
    </div>
    <div class="pv-modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('pv-edit-modal').classList.remove('show')">Annuler</button>
      <button class="btn btn-primary" onclick="saveEdit()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const MODEL_ID  = <?= $id ?>;
const MODEL_EXT = '<?= $ext ?>';
const API       = '/modules/printvault/api.php';

function toast(msg, type='success') {
    const c = document.getElementById('pv-toasts');
    const t = document.createElement('div');
    t.className = 'pv-toast' + (type==='error'?' error':'');
    t.textContent = msg; c.appendChild(t); setTimeout(()=>t.remove(),3500);
}

function openEdit() { document.getElementById('pv-edit-modal').classList.add('show'); }

async function saveEdit() {
    const name = document.getElementById('edit-name').value.trim();
    if (!name) { toast('Nom requis','error'); return; }
    try {
        const r = await fetch(API+'?action=models&id='+MODEL_ID, {
            method:'PUT', credentials:'same-origin',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({name, description:document.getElementById('edit-desc').value, category:document.getElementById('edit-cat').value, tags:document.getElementById('edit-tags').value})
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error);
        toast('Mis à jour ✓');
        document.getElementById('pv-edit-modal').classList.remove('show');
        setTimeout(()=>location.reload(),600);
    } catch(e) { toast(e.message,'error'); }
}

async function deleteModel() {
    if (!confirm('Supprimer ce modèle ?')) return;
    try {
        const r = await fetch(API+'?action=models&id='+MODEL_ID, {method:'DELETE',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error);
        window.location.href = '/printvault.php';
    } catch(e) { toast(e.message,'error'); }
}

document.querySelectorAll('.pv-modal-backdrop').forEach(m => m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));
</script>

<?php if ($canView): ?>
<script type="importmap">
{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}
</script>
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
<?php if (!$isGcode): ?>
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
<?php if ($ext === '3mf'): ?>
import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';
<?php endif; ?>
<?php endif; ?>

const canvas  = document.getElementById('viewer-canvas');
const wrap    = document.getElementById('viewer-wrap');
const scene   = new THREE.Scene();
scene.background = new THREE.Color(0x0f172a);

scene.add(new THREE.AmbientLight(0xffffff, 0.6));
const key = new THREE.DirectionalLight(0xffffff, 1.2); key.position.set(5,10,7.5); scene.add(key);
const fill= new THREE.DirectionalLight(0x8ab4f8, 0.4); fill.position.set(-5,-3,-5); scene.add(fill);
const rim = new THREE.DirectionalLight(0xffffff, 0.3); rim.position.set(0,5,-10); scene.add(rim);

const grid = new THREE.GridHelper(300,50,0x1e293b,0x1e293b);
grid.material.opacity=0.4; grid.material.transparent=true; scene.add(grid);

const camera = new THREE.PerspectiveCamera(45, wrap.clientWidth/wrap.clientHeight, 0.1, 10000);
camera.position.set(150,150,150);

const renderer = new THREE.WebGLRenderer({canvas, antialias:true, preserveDrawingBuffer:true});
renderer.setPixelRatio(window.devicePixelRatio);
renderer.setSize(wrap.clientWidth, wrap.clientHeight);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping=true; controls.dampingFactor=0.05;

function fitCamera(obj) {
    const box = new THREE.Box3().setFromObject(obj);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const dist = Math.abs(Math.max(size.x,size.y,size.z)/Math.sin(camera.fov*Math.PI/180/2))*0.8;
    camera.position.set(center.x+dist*.7, center.y+dist*.6, center.z+dist*.7);
    controls.target.copy(center);
    grid.position.y = box.min.y - 0.5;
    controls.update();
}

<?php if ($isGcode): ?>
// ── GCode path viewer ─────────────────────────────────────────────────────────
function zToColor(z, minZ, maxZ) {
    const t = maxZ > minZ ? (z - minZ) / (maxZ - minZ) : 0;
    // Blue(0,0,1) → Cyan(0,1,1) → Green(0,1,0) → Yellow(1,1,0) → Red(1,0,0)
    let r, g, b;
    if (t < 0.25)      { r=0;       g=t*4;     b=1; }
    else if (t < 0.5)  { r=0;       g=1;       b=1-(t-0.25)*4; }
    else if (t < 0.75) { r=(t-0.5)*4; g=1;     b=0; }
    else               { r=1;       g=1-(t-0.75)*4; b=0; }
    return new THREE.Color(r, g, b);
}

document.getElementById('viewer-loading-text').textContent = 'Chargement des trajectoires…';

document.getElementById('viewer-loading-text').textContent = 'Parsing GCode…';

fetch('/modules/printvault/api.php?action=gcode_paths&id=<?= $id ?>', {
    credentials: 'same-origin',
    headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
})
.then(r => r.json())
.then(j => {
    if (!j.ok) { document.getElementById('viewer-loading-text').textContent = j.error; return; }
    const {flat, min_z, max_z, total} = j.data;

    document.getElementById('viewer-loading-text').textContent = 'Construction du rendu…';

    // flat = [x1,y1,z1,x2,y2,z2,ext, x1,y1,z1,...] stride 7
    const extPos=[], extCol=[], travPos=[];
    for (let i=0; i < flat.length; i+=7) {
        const x1=flat[i],y1=flat[i+1],z1=flat[i+2];
        const x2=flat[i+3],y2=flat[i+4],z2=flat[i+5];
        const ext=flat[i+6];
        if (ext) {
            const col = zToColor(z1, min_z, max_z);
            extPos.push(x1,z1,-y1, x2,z2,-y2);
            extCol.push(col.r,col.g,col.b, col.r,col.g,col.b);
        } else {
            travPos.push(x1,z1,-y1, x2,z2,-y2);
        }
    }

    const group = new THREE.Group();
    if (extPos.length) {
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(extPos, 3));
        geo.setAttribute('color',    new THREE.Float32BufferAttribute(extCol, 3));
        group.add(new THREE.LineSegments(geo, new THREE.LineBasicMaterial({vertexColors:true})));
    }
    if (travPos.length) {
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(travPos, 3));
        group.add(new THREE.LineSegments(geo, new THREE.LineBasicMaterial({color:0x334155,transparent:true,opacity:0.25})));
    }

    scene.add(group);
    fitCamera(group);
    document.getElementById('viewer-loading').style.display = 'none';
    document.getElementById('viewer-loading-text').textContent = `${total.toLocaleString()} segments`;
})
.catch(err => { document.getElementById('viewer-loading-text').textContent = 'Erreur: '+err.message; });

<?php else: ?>
// ── STL / 3MF viewer ─────────────────────────────────────────────────────────
const material = new THREE.MeshPhysicalMaterial({color:0x4cc9f0,metalness:0.3,roughness:0.4,side:THREE.DoubleSide});
let meshGroup = null;

<?php if ($ext === 'stl'): ?>
new STLLoader().load('/modules/printvault/api.php?action=model_data&id=<?= $id ?>',
    geo => {
        geo.computeVertexNormals();
        meshGroup = new THREE.Group(); meshGroup.add(new THREE.Mesh(geo,material));
        scene.add(meshGroup); fitCamera(meshGroup);
        document.getElementById('viewer-loading').style.display='none';
    },
    xhr => { if(xhr.total) document.getElementById('viewer-loading-text').textContent='Chargement… '+Math.round(xhr.loaded/xhr.total*100)+'%'; },
    err => { document.getElementById('viewer-loading-text').textContent='Erreur: '+err.message; }
);
<?php elseif ($ext === '3mf'): ?>
new ThreeMFLoader().load('/modules/printvault/api.php?action=model_data&id=<?= $id ?>',
    obj => {
        obj.traverse(c=>{if(c.isMesh)c.material=material;});
        meshGroup=obj; scene.add(meshGroup); fitCamera(meshGroup);
        document.getElementById('viewer-loading').style.display='none';
    },
    xhr => { if(xhr.total) document.getElementById('viewer-loading-text').textContent='Chargement… '+Math.round(xhr.loaded/xhr.total*100)+'%'; },
    err => { document.getElementById('viewer-loading-text').textContent='Erreur: '+err.message; }
);
<?php endif; ?>

window.takeScreenshot = async () => {
    renderer.render(scene,camera);
    canvas.toBlob(async blob=>{
        const fd=new FormData(); fd.append('thumb',blob,'thumb.png');
        const r=await fetch(API+'?action=thumb&id='+MODEL_ID,{method:'POST',credentials:'same-origin',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
        const j=await r.json();
        if(j.ok){toast('Aperçu sauvegardé ✓');}
    },'image/png',0.9);
};
<?php endif; ?>

(function animate(){requestAnimationFrame(animate);controls.update();renderer.render(scene,camera);})();

window.addEventListener('resize',()=>{
    renderer.setSize(wrap.clientWidth,wrap.clientHeight);
    camera.aspect=wrap.clientWidth/wrap.clientHeight;
    camera.updateProjectionMatrix();
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
