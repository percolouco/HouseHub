// HouseHub — PrintVault module (local storage)
const API = '/modules/printvault/api.php';

function escHtml(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
function fmtSize(b) { if (!b) return '—'; return b > 1048576 ? (b/1048576).toFixed(1)+' Mo' : Math.round(b/1024)+' Ko'; }
function fmtDate(s) { if (!s) return '—'; return new Date(s).toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}); }

function toast(msg, type='success') {
    const c = document.getElementById('pv-toasts');
    const t = document.createElement('div');
    t.className = 'pv-toast' + (type==='error' ? ' error' : '');
    t.textContent = msg; c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

async function api(action, method='GET', data=null, extra='') {
    const opts = {method, credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}};
    if (data && !(data instanceof FormData)) { opts.headers['Content-Type']='application/json'; opts.body=JSON.stringify(data); }
    else if (data instanceof FormData) opts.body = data;
    const r = await fetch(API+'?action='+action+extra, opts);
    const text = await r.text();
    let j; try { j = JSON.parse(text); } catch(e) { console.error('Bad JSON:', text.slice(0,300)); throw new Error('Erreur serveur'); }
    if (!j.ok) throw new Error(j.error || 'Erreur');
    return j.data;
}

// ─── State ────────────────────────────────────────────────────────────────────
let currentCategory = null;
let currentType = null;
let searchQuery = '';
let allModels = [];
let categories = [];

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadAll();
    document.getElementById('pv-search')?.addEventListener('input', e => { searchQuery = e.target.value.trim(); renderModels(); });
    document.querySelectorAll('.pv-modal-backdrop').forEach(m => m.addEventListener('click', e => { if (e.target===m) m.classList.remove('show'); }));

    const dz = document.getElementById('pv-drop-zone');
    if (dz) {
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('over'); if (e.dataTransfer.files[0]) setUploadFile(e.dataTransfer.files[0]); });
        dz.addEventListener('click', () => document.getElementById('pv-file-input').click());
        document.getElementById('pv-file-input').addEventListener('change', e => { if (e.target.files[0]) setUploadFile(e.target.files[0]); });
    }
});

async function loadAll() {
    try {
        const [cats] = await Promise.all([api('categories')]);
        categories = cats;
        await loadModels();
    } catch(e) { toast(e.message, 'error'); }
}

// ─── Sidebar ──────────────────────────────────────────────────────────────────
function renderSidebar() {
    const el = document.getElementById('pv-sidebar-nav');
    let html = `
      <div class="pv-nav-section">Catégories</div>
      <div class="pv-nav-item${currentCategory===null?' active':''}" onclick="setCategory(null)">
        🗂 Toutes <span class="pv-nav-badge">${allModels.length}</span>
      </div>`;
    categories.forEach(c => {
        const count = allModels.filter(m => m.category === c.name).length;
        html += `<div class="pv-nav-item${currentCategory===c.name?' active':''}" onclick="setCategory('${escHtml(c.name).replace(/'/g,"\\'")}')">
          <span class="pv-cat-dot" style="background:${escHtml(c.color)}"></span>
          <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(c.name)}</span>
          <span class="pv-nav-badge">${count}</span>
        </div>`;
    });
    el.innerHTML = html;
}

function setCategory(cat) { currentCategory = cat; renderSidebar(); renderModels(); }
function setType(type) {
    currentType = currentType === type ? null : type;
    document.querySelectorAll('.pv-chip').forEach(c => c.classList.toggle('active', c.dataset.type === currentType));
    renderModels();
}

// ─── Models ───────────────────────────────────────────────────────────────────
async function loadModels() {
    try {
        allModels = await api('models');
        renderSidebar();
        renderModels();
    } catch(e) { toast(e.message, 'error'); }
}

function filterModels() {
    return allModels.filter(m => {
        if (currentCategory && m.category !== currentCategory) return false;
        if (currentType) {
            const ext = m.file_type.toLowerCase();
            if (currentType === 'gcode' && !['gcode','gco','g'].includes(ext)) return false;
            if (currentType !== 'gcode' && ext !== currentType) return false;
        }
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            if (!m.name.toLowerCase().includes(q) && !(m.description||'').toLowerCase().includes(q) && !(m.tags||'').toLowerCase().includes(q)) return false;
        }
        return true;
    });
}

function renderModels() {
    const el = document.getElementById('pv-grid');
    const filtered = filterModels();
    document.getElementById('pv-subtitle').textContent = filtered.length + ' modèle' + (filtered.length > 1 ? 's' : '');
    if (!filtered.length) {
        el.innerHTML = `<div class="pv-empty" style="grid-column:1/-1"><div class="icon">🖨️</div><p>${allModels.length ? 'Aucun modèle dans ce filtre.' : 'Aucun modèle. Uploadez votre premier fichier !'}</p></div>`;
        return;
    }
    el.innerHTML = filtered.map(m => cardHtml(m)).join('');
}

const typeIcon = {stl:'🟣','3mf':'🔵',gcode:'🟢',g:'🟢',gco:'🟢'};

function cardHtml(m) {
    const ext = m.file_type.toLowerCase();
    const icon = typeIcon[ext] || '📄';
    const thumbUrl = m.thumb ? `/uploads/printvault/thumbs/${encodeURIComponent(m.thumb)}` : null;
    const thumbHtml = thumbUrl
        ? `<img src="${escHtml(thumbUrl)}" alt="" loading="lazy" onerror="this.parentNode.innerHTML='${icon}'">`
        : icon;
    const dims = m.dim_x > 0 ? `${Math.round(m.dim_x)}×${Math.round(m.dim_y)}×${Math.round(m.dim_z)}mm` : '';
    const gcInfo = m.gcode_time || '';
    return `<a class="pv-card" href="/printvault-viewer.php?id=${m.id}" style="text-decoration:none">
      <div class="pv-card-thumb">${thumbHtml}</div>
      <div class="pv-card-body">
        <div class="pv-card-name">${escHtml(m.name)}</div>
        <div class="pv-card-meta">
          <span class="pv-type-badge pv-type-${escHtml(ext)}">${escHtml(m.file_type.toUpperCase())}</span>
          ${dims ? `<span>${escHtml(dims)}</span>` : ''}
          ${gcInfo ? `<span>⏱ ${escHtml(gcInfo)}</span>` : ''}
          <span style="margin-left:auto">${escHtml(fmtSize(m.file_size))}</span>
        </div>
      </div>
    </a>`;
}

// ─── Upload ───────────────────────────────────────────────────────────────────
let uploadFile = null;

function openUpload() {
    uploadFile = null;
    document.getElementById('pv-file-input').value = '';
    document.getElementById('pv-upload-filename').textContent = '';
    document.getElementById('pv-upload-name').value = '';
    document.getElementById('pv-upload-desc').value = '';
    document.getElementById('pv-upload-tags').value = '';
    const sel = document.getElementById('pv-upload-cat');
    sel.innerHTML = categories.map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
    document.getElementById('pv-upload-progress').style.display = 'none';
    document.getElementById('pv-upload-modal').classList.add('show');
}

function setUploadFile(f) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['stl','3mf','gcode','gco','g'].includes(ext)) { toast('Format non supporté', 'error'); return; }
    uploadFile = f;
    document.getElementById('pv-upload-filename').textContent = f.name;
    if (!document.getElementById('pv-upload-name').value)
        document.getElementById('pv-upload-name').value = f.name.replace(/\.[^.]+$/,'').replace(/[_-]/g,' ');
}

async function saveUpload() {
    if (!uploadFile) { toast('Choisissez un fichier', 'error'); return; }
    const name = document.getElementById('pv-upload-name').value.trim();
    if (!name) { toast('Nom requis', 'error'); return; }

    const fd = new FormData();
    fd.append('file', uploadFile);
    fd.append('name', name);
    fd.append('description', document.getElementById('pv-upload-desc').value);
    fd.append('category', document.getElementById('pv-upload-cat').value);
    fd.append('tags', document.getElementById('pv-upload-tags').value);

    const prog = document.getElementById('pv-upload-progress');
    const bar  = document.getElementById('pv-upload-bar');
    prog.style.display = ''; bar.style.width = '20%';

    try {
        await api('models', 'POST', fd);
        bar.style.width = '100%';
        setTimeout(() => {
            document.getElementById('pv-upload-modal').classList.remove('show');
            toast('Modèle ajouté ✓');
            loadModels();
        }, 300);
    } catch(e) { prog.style.display = 'none'; toast(e.message, 'error'); }
}
