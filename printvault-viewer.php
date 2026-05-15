<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('Modèle introuvable'); }

$s = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
$model = $s->fetch();
if (!$model) { http_response_code(404); exit('Modèle introuvable'); }

$ext = strtolower($model['file_type']);
$canView = in_array($ext, ['stl','3mf']);
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($model['name']) ?> — PrintVault</title>
  <link rel="icon" href="/favicon.png">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#0f172a; color:#e2e8f0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; overflow:hidden; }
    #canvas-container { width:100vw; height:100vh; position:relative; }
    canvas { display:block; }
    #ui-overlay {
      position:fixed; top:0; left:0; right:0;
      display:flex; align-items:center; justify-content:space-between;
      padding:.75rem 1.25rem; background:rgba(15,23,42,.85); backdrop-filter:blur(8px);
      border-bottom:1px solid rgba(255,255,255,.08); z-index:10;
    }
    #model-name { font-size:.95rem; font-weight:600; color:#f1f5f9; }
    #model-meta { font-size:.75rem; color:#94a3b8; margin-top:.15rem; }
    .ui-btns { display:flex; gap:.5rem; }
    .ui-btn {
      padding:.35rem .8rem; border-radius:8px; border:1px solid rgba(255,255,255,.15);
      background:rgba(255,255,255,.08); color:#e2e8f0; font-size:.8rem; cursor:pointer;
      transition:background .15s; white-space:nowrap;
    }
    .ui-btn:hover { background:rgba(255,255,255,.15); }
    .ui-btn.primary { background:#3b82f6; border-color:#3b82f6; color:#fff; }
    .ui-btn.primary:hover { background:#2563eb; }
    #loading {
      position:fixed; inset:0; display:flex; flex-direction:column;
      align-items:center; justify-content:center; background:#0f172a; z-index:20;
      gap:1rem; color:#94a3b8; font-size:.9rem;
    }
    .spinner { width:40px; height:40px; border:3px solid #1e293b; border-top-color:#3b82f6; border-radius:50%; animation:spin .8s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    #error { display:none; text-align:center; }
    #controls-hint {
      position:fixed; bottom:1rem; left:50%; transform:translateX(-50%);
      font-size:.72rem; color:#475569; background:rgba(15,23,42,.7);
      padding:.35rem .75rem; border-radius:999px; pointer-events:none;
    }
    #gcode-info {
      position:fixed; inset:0; display:flex; flex-direction:column;
      align-items:center; justify-content:center; gap:1.5rem; padding:2rem;
    }
    .info-card {
      background:#1e293b; border:1px solid #334155; border-radius:14px;
      padding:1.5rem 2rem; max-width:420px; width:100%;
    }
    .info-card h2 { font-size:1.1rem; margin-bottom:1rem; color:#f1f5f9; }
    .info-row { display:flex; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid #1e293b; font-size:.875rem; }
    .info-row:last-child { border:none; }
    .info-label { color:#94a3b8; }
    .info-value { color:#e2e8f0; font-weight:500; }
  </style>
</head>
<body>

<div id="ui-overlay">
  <div>
    <div id="model-name"><?= htmlspecialchars($model['name']) ?></div>
    <div id="model-meta">
      <?= htmlspecialchars(strtoupper($model['file_type'])) ?>
      <?php if ($model['dim_x'] > 0): ?>
        · <?= round($model['dim_x']) ?>×<?= round($model['dim_y']) ?>×<?= round($model['dim_z']) ?> mm
      <?php endif; ?>
      · <?= round(($model['file_size']??0)/1024) ?> Ko
    </div>
  </div>
  <div class="ui-btns">
    <button class="ui-btn" onclick="resetCamera()">⟳ Centrer</button>
    <?php if ($canView): ?>
    <button class="ui-btn" onclick="takeScreenshot()" id="thumb-btn">📸 Aperçu</button>
    <?php endif; ?>
    <button class="ui-btn" onclick="window.close()">✕ Fermer</button>
    <a href="/modules/printvault/api.php?action=file&id=<?= $id ?>" class="ui-btn primary" download>⬇ Télécharger</a>
  </div>
</div>

<?php if ($canView): ?>

<div id="loading">
  <div class="spinner"></div>
  <div>Chargement du modèle…</div>
  <div id="error" style="color:#ef4444"></div>
</div>

<div id="canvas-container"></div>
<div id="controls-hint">Clic+glisser : rotation · Scroll : zoom · Clic droit : déplacer</div>

<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
<?php if ($ext === '3mf'): ?>
import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';
<?php endif; ?>

const MODEL_URL = '/modules/printvault/api.php?action=model_data&id=<?= $id ?>';
const MODEL_ID  = <?= $id ?>;
const API       = '/modules/printvault/api.php';

// Scene
const scene    = new THREE.Scene();
scene.background = new THREE.Color(0x0f172a);
scene.add(new THREE.AmbientLight(0xffffff, 0.6));

const keyLight = new THREE.DirectionalLight(0xffffff, 1.2);
keyLight.position.set(5, 10, 7.5);
scene.add(keyLight);

const fillLight = new THREE.DirectionalLight(0x8ab4f8, 0.4);
fillLight.position.set(-5, -3, -5);
scene.add(fillLight);

const rimLight = new THREE.DirectionalLight(0xffffff, 0.3);
rimLight.position.set(0, 5, -10);
scene.add(rimLight);

// Grid
const grid = new THREE.GridHelper(200, 40, 0x1e293b, 0x1e293b);
grid.material.opacity = 0.5; grid.material.transparent = true;
scene.add(grid);

// Camera & renderer
const camera = new THREE.PerspectiveCamera(45, window.innerWidth/window.innerHeight, 0.1, 10000);
camera.position.set(100, 100, 100);

const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
renderer.setPixelRatio(window.devicePixelRatio);
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.shadowMap.enabled = true;
document.getElementById('canvas-container').appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true; controls.dampingFactor = 0.05;

let meshGroup = null;

function fitCamera(object) {
    const box = new THREE.Box3().setFromObject(object);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    const fov = camera.fov * (Math.PI / 180);
    const dist = Math.abs(maxDim / Math.sin(fov / 2)) * 0.75;
    camera.position.set(center.x + dist * 0.7, center.y + dist * 0.5, center.z + dist * 0.7);
    controls.target.copy(center);
    // Align grid to bottom of object
    grid.position.y = box.min.y;
    controls.update();
}

window.resetCamera = () => { if (meshGroup) fitCamera(meshGroup); };

const material = new THREE.MeshPhysicalMaterial({
    color: 0x4cc9f0, metalness: 0.3, roughness: 0.4,
    side: THREE.DoubleSide,
});

// Load model
<?php if ($ext === 'stl'): ?>
new STLLoader().load(MODEL_URL,
    (geometry) => {
        geometry.computeVertexNormals();
        const mesh = new THREE.Mesh(geometry, material);
        meshGroup = new THREE.Group(); meshGroup.add(mesh);
        scene.add(meshGroup);
        fitCamera(meshGroup);
        document.getElementById('loading').style.display = 'none';
    },
    (xhr) => {
        if (xhr.total) document.querySelector('#loading div:last-child').textContent =
            'Chargement… ' + Math.round(xhr.loaded/xhr.total*100) + '%';
    },
    (err) => {
        document.getElementById('error').style.display = '';
        document.getElementById('error').textContent = 'Erreur chargement: ' + err.message;
    }
);
<?php elseif ($ext === '3mf'): ?>
new ThreeMFLoader().load(MODEL_URL,
    (obj) => {
        meshGroup = obj;
        // Apply material to all meshes
        obj.traverse(child => {
            if (child.isMesh) child.material = material;
        });
        scene.add(meshGroup);
        fitCamera(meshGroup);
        document.getElementById('loading').style.display = 'none';
    },
    (xhr) => {
        if (xhr.total) document.querySelector('#loading div:last-child').textContent =
            'Chargement… ' + Math.round(xhr.loaded/xhr.total*100) + '%';
    },
    (err) => {
        document.getElementById('error').style.display = '';
        document.getElementById('error').textContent = 'Erreur: ' + err.message;
    }
);
<?php endif; ?>

// Animation loop
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}
animate();

window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});

// Screenshot → save as thumbnail
window.takeScreenshot = async () => {
    renderer.render(scene, camera);
    const canvas = renderer.domElement;
    canvas.toBlob(async (blob) => {
        const fd = new FormData();
        fd.append('thumb', blob, 'thumb.png');
        try {
            const r = await fetch(API + '?action=thumb&id=' + MODEL_ID, {
                method: 'POST', credentials: 'same-origin', body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const j = await r.json();
            if (j.ok) {
                document.getElementById('thumb-btn').textContent = '✅ Sauvegardé';
                setTimeout(() => { document.getElementById('thumb-btn').textContent = '📸 Aperçu'; }, 2000);
            }
        } catch(e) { console.error(e); }
    }, 'image/png', 0.9);
};
</script>

<?php else: ?>
<!-- GCode / non-viewable file: show metadata only -->
<div id="gcode-info">
  <div class="info-card">
    <h2>🟢 <?= htmlspecialchars($model['name']) ?></h2>
    <?php $rows = [
      ['Fichier', $model['original_name']],
      ['Taille', round($model['file_size']/1024).' Ko'],
      ['Catégorie', $model['category']],
      $model['gcode_time']     ? ['Temps impression', $model['gcode_time']]    : null,
      $model['gcode_filament'] ? ['Filament',         $model['gcode_filament']]: null,
      $model['gcode_nozzle']   ? ['Buse',             $model['gcode_nozzle']]  : null,
      $model['gcode_bed']      ? ['Plateau',          $model['gcode_bed']]     : null,
      ['Ajouté le', date('d/m/Y', strtotime($model['created_at']))],
    ]; ?>
    <?php foreach (array_filter($rows) as $r): ?>
    <div class="info-row">
      <span class="info-label"><?= htmlspecialchars($r[0]) ?></span>
      <span class="info-value"><?= htmlspecialchars($r[1]) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</body>
</html>
