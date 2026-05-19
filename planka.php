<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = 'Planka — HouseHub';
$activePage = 'planka';
$pageCss    = '/modules/planka/assets/planka.css';
require __DIR__ . '/header.php';
?>

<div class="pk-page" id="pk-page">

  <div class="pk-topbar">
    <h1>📋 Planka</h1>
    <div class="pk-board-selector" id="pk-board-selector"></div>
    <div class="pk-topbar-actions">
      <button class="pk-btn-icon" onclick="openSettings()" title="Paramètres">⚙️</button>
    </div>
  </div>

  <div class="pk-board-area" id="pk-board-area">
    <div class="pk-loading" id="pk-loading">
      <div class="pk-spinner"></div>
      Chargement…
    </div>
  </div>

</div>

<!-- ── MODAL CARTE ──────────────────────────────────────────────────────────── -->
<div class="pk-modal-backdrop hidden" id="pk-card-modal">
  <div class="pk-modal">
    <div class="pk-modal-header">
      <h3>Carte</h3>
      <button class="pk-modal-close" onclick="closeCardModal()">×</button>
    </div>
    <div class="pk-modal-body">
      <div class="pk-form-group">
        <label class="pk-form-label">Titre</label>
        <input type="text" id="pk-card-name" class="pk-input" placeholder="Titre de la carte">
      </div>
      <div class="pk-form-group">
        <label class="pk-form-label">Description</label>
        <textarea id="pk-card-desc" class="pk-textarea" placeholder="Description…"></textarea>
      </div>
      <div class="pk-form-group">
        <label class="pk-form-label">Date d'échéance</label>
        <input type="date" id="pk-card-due" class="pk-input">
      </div>
      <div class="pk-form-group">
        <label class="pk-form-label">Colonne</label>
        <select id="pk-card-list" class="pk-select"></select>
      </div>
      <div class="pk-form-group">
        <label class="pk-form-label">Checklist</label>
        <div class="pk-checklist" id="pk-task-list"></div>
        <div class="pk-add-task-row">
          <input type="text" id="pk-new-task-name" class="pk-input" placeholder="Nouvelle tâche…">
          <button class="pk-btn pk-btn-secondary pk-btn-sm" onclick="addTask()">+ Ajouter</button>
        </div>
      </div>
    </div>
    <div class="pk-modal-footer">
      <button class="pk-btn pk-btn-danger" id="pk-delete-btn" onclick="deleteCard()">Supprimer</button>
      <button class="pk-btn pk-btn-secondary" onclick="closeCardModal()">Annuler</button>
      <button class="pk-btn pk-btn-primary" onclick="saveCard()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL PARAMÈTRES ─────────────────────────────────────────────────────── -->
<div class="pk-modal-backdrop hidden" id="pk-settings-modal">
  <div class="pk-modal">
    <div class="pk-modal-header">
      <h3>⚙️ Paramètres Planka</h3>
      <button class="pk-modal-close" onclick="closeSettings()">×</button>
    </div>
    <div class="pk-modal-body">
      <div class="pk-form-group">
        <label class="pk-form-label">Project ID</label>
        <input type="text" id="pk-project-id" class="pk-input" value="1713338747178714130">
        <span style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem">ID du projet Planka à afficher.</span>
      </div>
    </div>
    <div class="pk-modal-footer">
      <button class="pk-btn pk-btn-secondary" onclick="closeSettings()">Annuler</button>
      <button class="pk-btn pk-btn-primary" onclick="saveSettings()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const API = 'modules/planka/api.php';
let state = { boards: [], activeBoardId: null, lists: [], cards: [], labels: [], cardLabels: [], tasks: [] };
let editingCard = null;

async function apiFetch(action, params = {}, method = 'GET', body = null) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const opts = { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(`${API}?${qs}`, opts);
    const txt = await r.text();
    try { return JSON.parse(txt); } catch { return { ok: false, error: txt }; }
}

async function init() {
    const cfg = await apiFetch('config');
    if (cfg.ok) {
        if (cfg.data.active_board_id) state.activeBoardId = cfg.data.active_board_id;
    }
    const res = await apiFetch('boards');
    if (!res.ok) { document.getElementById('pk-loading').textContent = 'Erreur: ' + (res.error || 'impossible de charger les boards'); return; }
    state.boards = res.data;
    if (!state.activeBoardId && state.boards.length) state.activeBoardId = state.boards[0].id;
    renderBoardSelector();
    if (state.activeBoardId) await loadBoard(state.activeBoardId);
    else document.getElementById('pk-loading').textContent = 'Aucun board disponible.';
}

function renderBoardSelector() {
    const el = document.getElementById('pk-board-selector');
    el.innerHTML = state.boards.map(b => `<button class="pk-board-btn${b.id === state.activeBoardId ? ' active' : ''}" onclick="switchBoard('${b.id}')">${b.name}</button>`).join('');
}

async function switchBoard(id) {
    state.activeBoardId = id;
    renderBoardSelector();
    await apiFetch('set_active_board', { id });
    await loadBoard(id);
}

async function loadBoard(id) {
    document.getElementById('pk-board-area').innerHTML = '<div class="pk-loading"><div class="pk-spinner"></div>Chargement…</div>';
    const res = await apiFetch('board', { id });
    if (!res.ok) { document.getElementById('pk-board-area').textContent = 'Erreur: ' + res.error; return; }
    const d = res.data;
    state.lists      = d.lists || [];
    state.cards      = d.cards || [];
    state.labels     = d.labels || [];
    state.cardLabels = d.cardLabels || [];
    state.tasks      = d.tasks || [];
    renderBoard();
}

function renderBoard() {
    const area = document.getElementById('pk-board-area');
    const sorted = [...state.lists].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    area.innerHTML = sorted.map(list => renderList(list)).join('') + `
<div class="pk-list pk-list-new">
  <button class="pk-add-list-btn" onclick="promptCreateList()">＋ Ajouter une liste</button>
</div>`;
    // Drag source — cards
    area.querySelectorAll('.pk-card').forEach(el => {
        el.addEventListener('click', () => openCardModal(el.dataset.id));
        el.addEventListener('dragstart', e => {
            e.dataTransfer.setData('card-id', el.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => el.classList.add('is-dragging'), 0);
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('is-dragging');
            area.querySelectorAll('.drag-over,.drop-before-active').forEach(x => x.classList.remove('drag-over','drop-before-active'));
        });
    });

    // Drop zone — before a specific card
    area.querySelectorAll('.pk-drop-before').forEach(el => {
        el.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); el.classList.add('drop-before-active'); });
        el.addEventListener('dragleave', () => el.classList.remove('drop-before-active'));
        el.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation();
            el.classList.remove('drop-before-active');
            const cardId = e.dataTransfer.getData('card-id');
            if (!cardId) return;
            const listId  = el.dataset.listId;
            const prevPos = parseFloat(el.dataset.prevPos || 0);
            const curPos  = parseFloat(el.dataset.curPos  || 0);
            const newPos  = prevPos === 0 ? curPos / 2 : (prevPos + curPos) / 2;
            moveCard(cardId, listId, newPos);
        });
    });

    // Drop zone — end of column (whole .pk-cards)
    area.querySelectorAll('.pk-cards').forEach(el => {
        el.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; el.classList.add('drag-over'); });
        el.addEventListener('dragleave', e => { if (!el.contains(e.relatedTarget)) el.classList.remove('drag-over'); });
        el.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation();
            el.classList.remove('drag-over');
            const cardId = e.dataTransfer.getData('card-id');
            if (!cardId) return;
            const listId = el.dataset.listId;
            const listCards = state.cards.filter(c => c.listId === listId && c.id !== cardId);
            const maxPos = listCards.reduce((m, c) => Math.max(m, c.position || 0), 0);
            moveCard(cardId, listId, maxPos + 65535);
        });
    });
}

function renderList(list) {
    const cards = state.cards.filter(c => c.listId === list.id).sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    return `
<div class="pk-list" data-list-id="${list.id}">
  <div class="pk-list-header">
    <span class="pk-list-name">${esc(list.name)}</span>
    <span class="pk-list-count">${cards.length}</span>
  </div>
  <div class="pk-cards" id="cards-${list.id}" data-list-id="${list.id}" data-drop-end="1">
    ${cards.length === 0 ? '<div class="pk-drop-hint">Déposer ici</div>' : ''}
    ${cards.map((c, i) => renderCard(c, cards[i-1] ?? null)).join('')}
  </div>
  <button class="pk-add-card-btn" onclick="quickAddCard('${list.id}')">＋ Ajouter une carte</button>
</div>`;
}

function renderCard(card, prevCard) {
    const labels = (state.cardLabels || []).filter(cl => cl.cardId === card.id).map(cl => {
        const lbl = (state.labels || []).find(l => l.id === cl.labelId);
        if (!lbl) return '';
        return `<span class="pk-label pk-label-color--${lbl.color || 'morning-sky'}" title="${esc(lbl.name || '')}"></span>`;
    }).join('');
    const cardTasks = (state.tasks || []).filter(t => t.cardId === card.id);
    const doneCount = cardTasks.filter(t => t.isCompleted).length;
    const dueHtml = card.dueDate ? (() => {
        const d = new Date(card.dueDate); const now = new Date();
        const diff = (d - now) / 86400000;
        const cls = diff < 0 ? 'overdue' : diff < 3 ? 'soon' : '';
        return `<span class="pk-due ${cls}">📅 ${formatDate(card.dueDate)}</span>`;
    })() : '';
    const taskHtml = cardTasks.length ? `<span class="pk-task-count">☑ ${doneCount}/${cardTasks.length}</span>` : '';
    const prevPos = prevCard ? (prevCard.position ?? 0) : 0;
    return `
<div class="pk-drop-before" data-list-id="${card.listId}" data-before-id="${card.id}" data-prev-pos="${prevPos}" data-cur-pos="${card.position ?? 0}"></div>
<div class="pk-card" data-id="${card.id}" data-list-id="${card.listId}" data-position="${card.position ?? 0}" draggable="true">
  ${labels ? `<div class="pk-card-labels">${labels}</div>` : ''}
  <div class="pk-card-name">${esc(card.name)}</div>
  ${dueHtml || taskHtml ? `<div class="pk-card-meta">${dueHtml}${taskHtml}</div>` : ''}
</div>`;
}

async function moveCard(cardId, listId, position) {
    const card = state.cards.find(c => c.id === cardId);
    if (!card) return;
    // Skip no-op (same list, same position range)
    if (card.listId === listId && Math.abs((card.position || 0) - position) < 1) return;
    const body = { listId, position };
    const res = await apiFetch('update_card', { id: cardId }, 'PATCH', body);
    if (!res.ok) { showToast('Erreur : ' + (res.error || 'déplacement impossible'), 'error'); return; }
    card.listId   = listId;
    card.position = position;
    renderBoard();
    showToast('Carte déplacée ✓');
}

async function quickAddCard(listId) {
    const name = prompt('Nom de la carte :');
    if (!name || !name.trim()) return;
    const listCards = state.cards.filter(c => c.listId === listId);
    const maxPos = listCards.reduce((m, c) => Math.max(m, c.position || 0), 0);
    const res = await apiFetch('create_card', { list_id: listId, name: name.trim(), position: maxPos + 65535 });
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    state.cards.push(res.data);
    renderBoard();
    showToast('Carte créée');
}

function openCardModal(id) {
    const card = state.cards.find(c => c.id === id);
    if (!card) return;
    editingCard = card;
    document.getElementById('pk-card-name').value = card.name || '';
    document.getElementById('pk-card-desc').value = card.description || '';
    document.getElementById('pk-card-due').value  = card.dueDate ? card.dueDate.substring(0, 10) : '';
    const sel = document.getElementById('pk-card-list');
    sel.innerHTML = state.lists.map(l => `<option value="${l.id}"${l.id === card.listId ? ' selected' : ''}>${esc(l.name)}</option>`).join('');
    renderTaskList(card);
    document.getElementById('pk-card-modal').classList.remove('hidden');
    document.getElementById('pk-new-task-name').value = '';
}

function renderTaskList(card) {
    const el = document.getElementById('pk-task-list');
    const tasks = (state.tasks || []).filter(t => t.cardId === card.id).sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    el.innerHTML = tasks.map(t => `
<div class="pk-task-item" data-task-id="${t.id}">
  <input type="checkbox" class="pk-task-cb" ${t.isCompleted ? 'checked' : ''} onchange="toggleTask('${t.id}', this.checked)">
  <span class="pk-task-label${t.isCompleted ? ' done' : ''}">${esc(t.name)}</span>
</div>`).join('') || '<span style="font-size:.78rem;color:var(--text-muted)">Aucune tâche</span>';
}

function closeCardModal() {
    document.getElementById('pk-card-modal').classList.add('hidden');
    editingCard = null;
}

async function saveCard() {
    if (!editingCard) return;
    const name = document.getElementById('pk-card-name').value.trim();
    if (!name) { showToast('Titre requis', 'error'); return; }
    const due  = document.getElementById('pk-card-due').value;
    const body = {
        name,
        description: document.getElementById('pk-card-desc').value,
        dueDate: due ? due + 'T00:00:00.000Z' : null,
        listId: document.getElementById('pk-card-list').value,
    };
    const res = await apiFetch('update_card', { id: editingCard.id }, 'PATCH', body);
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    const idx = state.cards.findIndex(c => c.id === editingCard.id);
    if (idx >= 0) state.cards[idx] = { ...state.cards[idx], ...res.data };
    closeCardModal();
    renderBoard();
    showToast('Carte enregistrée');
}

async function deleteCard() {
    if (!editingCard) return;
    const ok = await pachaConfirm('Supprimer la carte ?', 'Cette action est irréversible.');
    if (!ok) return;
    const res = await apiFetch('delete_card', { id: editingCard.id });
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    state.cards = state.cards.filter(c => c.id !== editingCard.id);
    closeCardModal();
    renderBoard();
    showToast('Carte supprimée');
}

async function addTask() {
    if (!editingCard) return;
    const name = document.getElementById('pk-new-task-name').value.trim();
    if (!name) return;
    const res = await apiFetch('create_task', { card_id: editingCard.id, name });
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    state.tasks.push(res.data);
    document.getElementById('pk-new-task-name').value = '';
    renderTaskList(editingCard);
    renderBoard();
}

async function toggleTask(id, checked) {
    const res = await apiFetch('toggle_task', { id, is_completed: checked ? '1' : '0' });
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    const t = state.tasks.find(t => t.id === id);
    if (t) { t.isCompleted = checked; renderTaskList(editingCard); renderBoard(); }
}

function openSettings() {
    apiFetch('config').then(res => {
        if (res.ok && res.data.project_id) document.getElementById('pk-project-id').value = res.data.project_id;
    });
    document.getElementById('pk-settings-modal').classList.remove('hidden');
}

function closeSettings() { document.getElementById('pk-settings-modal').classList.add('hidden'); }

async function saveSettings() {
    const project_id = document.getElementById('pk-project-id').value.trim();
    if (!project_id) return;
    const res = await apiFetch('set_project', { project_id });
    if (!res.ok) { showToast('Erreur: ' + res.error, 'error'); return; }
    closeSettings();
    state.activeBoardId = null;
    showToast('Projet mis à jour, rechargement…');
    setTimeout(() => init(), 800);
}

async function promptCreateList() {
    const name = prompt('Nom de la nouvelle liste :');
    if (!name || !name.trim()) return;
    const maxPos = state.lists.reduce((m, l) => Math.max(m, l.position || 0), 0);
    const res = await apiFetch('create_list', { board_id: state.activeBoardId, name: name.trim(), position: maxPos + 65535 });
    if (!res.ok) { showToast('Erreur : ' + (res.error || 'création impossible'), 'error'); return; }
    state.lists.push(res.data);
    renderBoard();
    showToast('Liste créée ✓');
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

init();
</script>

<?php require __DIR__ . '/footer.php'; ?>
