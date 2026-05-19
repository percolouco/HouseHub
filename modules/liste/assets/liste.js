/* Liste module JS */
const API = '/modules/liste/api.php';
const T   = window.LISTE_TRANSLATIONS || {};

const state = {
  lists: [],
  currentListId: null,
  items: [],
  history: [],
  categories: [],
  catMap: {},
};

// ── Constants ──────────────────────────────────────────────────────────────────

const LIST_COLORS = [
  { id: 'none',    hex: null },
  { id: 'blue',    hex: '#3b82f6' },
  { id: 'red',     hex: '#ef4444' },
  { id: 'amber',   hex: '#f59e0b' },
  { id: 'purple',  hex: '#8b5cf6' },
  { id: 'pink',    hex: '#ec4899' },
  { id: 'emerald', hex: '#10b981' },
  { id: 'orange',  hex: '#f97316' },
  { id: 'cyan',    hex: '#06b6d4' },
  { id: 'indigo',  hex: '#6366f1' },
  { id: 'teal',    hex: '#14b8a6' },
  { id: 'gray',    hex: '#6b7280' },
];

const LIST_TYPES = [
  { value: '',        label: '— Aucun —',  emoji: '' },
  { value: 'courses', label: 'Courses',    emoji: '🛒' },
  { value: 'todo',    label: 'To-do',      emoji: '✅' },
  { value: 'voyage',  label: 'Voyage',     emoji: '✈️' },
  { value: 'travail', label: 'Travail',    emoji: '💼' },
  { value: 'maison',  label: 'Maison',     emoji: '🏠' },
  { value: 'sante',   label: 'Santé',      emoji: '💊' },
  { value: 'loisirs', label: 'Loisirs',    emoji: '🎮' },
  { value: 'autre',   label: 'Autre',      emoji: '📋' },
];

// ── Utilities ──────────────────────────────────────────────────────────────────

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function toast(msg, type = 'info') {
  let c = document.getElementById('liste-toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'liste-toast-container';
    c.className = 'liste-toast-container';
    document.body.appendChild(c);
  }
  const t = document.createElement('div');
  t.className = `liste-toast liste-toast-${type}`;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.classList.add('show'), 10);
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2500);
}

async function api(action, method = 'GET', body = null, params = {}) {
  const url = new URL(API, location.origin);
  url.searchParams.set('action', action);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  return r.json();
}

// ── List modal ─────────────────────────────────────────────────────────────────

let activeModal = null;

function openListModal(listId = null) {
  if (activeModal) { activeModal.remove(); activeModal = null; }
  const list = listId ? state.lists.find(l => l.id === listId) : null;
  const isNew = !listId;

  const backdrop = document.createElement('div');
  backdrop.className = 'liste-modal-backdrop';
  backdrop.innerHTML = `
    <div class="liste-modal" role="dialog" aria-modal="true">
      <div class="liste-modal-header">
        <h3>${isNew ? 'Nouvelle liste' : 'Modifier la liste'}</h3>
        <button class="liste-modal-close" aria-label="Fermer">×</button>
      </div>
      <div class="liste-modal-body">
        <div class="liste-form-group">
          <label class="liste-form-label">Nom</label>
          <input class="liste-modal-name" type="text" maxlength="100"
                 value="${esc(list?.name ?? '')}" placeholder="Ma liste…" autocomplete="off">
        </div>
        <div class="liste-form-group">
          <label class="liste-form-label">Couleur</label>
          <div class="liste-color-picker">
            ${LIST_COLORS.map(c => {
              const isActive = (c.hex === null && !list?.color) || (list?.color === c.hex);
              const cls = 'liste-color-swatch' + (c.hex === null ? ' liste-color-swatch-none' : '') + (isActive ? ' active' : '');
              const style = c.hex ? `style="--swatch:${c.hex}"` : '';
              return `<button class="${cls}" data-color="${c.hex ?? ''}" ${style} title="${c.id}"></button>`;
            }).join('')}
          </div>
        </div>
        <div class="liste-form-group">
          <label class="liste-form-label">Type de liste</label>
          <select class="liste-modal-type">
            ${LIST_TYPES.map(t =>
              `<option value="${esc(t.value)}" ${list?.list_type === t.value ? 'selected' : ''}>${t.emoji ? t.emoji + ' ' : ''}${esc(t.label)}</option>`
            ).join('')}
          </select>
        </div>
      </div>
      <div class="liste-modal-footer">
        ${!isNew && state.lists.length > 1
          ? `<button class="btn-tool btn-tool-danger liste-modal-delete">Supprimer</button>`
          : ''}
        <button class="btn-tool liste-modal-cancel">Annuler</button>
        <button class="btn-tool btn-tool-primary liste-modal-save">Enregistrer</button>
      </div>
    </div>`;

  document.body.appendChild(backdrop);
  activeModal = backdrop;

  const nameInput = backdrop.querySelector('.liste-modal-name');
  nameInput.focus();
  nameInput.setSelectionRange(nameInput.value.length, nameInput.value.length);

  // Color swatches
  backdrop.querySelectorAll('.liste-color-swatch').forEach(btn => {
    btn.addEventListener('click', () => {
      backdrop.querySelectorAll('.liste-color-swatch').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  const close = () => { backdrop.remove(); activeModal = null; };
  backdrop.querySelector('.liste-modal-close').addEventListener('click', close);
  backdrop.querySelector('.liste-modal-cancel').addEventListener('click', close);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) close(); });

  backdrop.querySelector('.liste-modal-delete')?.addEventListener('click', () => { close(); deleteList(listId); });
  backdrop.querySelector('.liste-modal-save').addEventListener('click', () => saveListModal(backdrop, listId));
  nameInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') saveListModal(backdrop, listId);
    if (e.key === 'Escape') close();
  });
}

async function saveListModal(backdrop, listId) {
  const name = backdrop.querySelector('.liste-modal-name').value.trim();
  if (!name) { backdrop.querySelector('.liste-modal-name').focus(); return; }

  const activeSwatch = backdrop.querySelector('.liste-color-swatch.active');
  const color     = activeSwatch?.dataset.color || null;
  const list_type = backdrop.querySelector('.liste-modal-type').value || null;

  if (listId) {
    const r = await api('lists', 'PUT', { name, color, list_type }, { id: listId });
    if (r.ok) {
      const list = state.lists.find(l => l.id === listId);
      if (list) { list.name = name; list.color = color; list.list_type = list_type; }
      backdrop.remove(); activeModal = null;
      renderTabs();
      toast('Liste mise à jour');
    }
  } else {
    const r = await api('lists', 'POST', { name, color, list_type });
    if (r.id) {
      state.lists.push({ id: r.id, name, color: r.color, list_type: r.list_type, position: r.position });
      state.currentListId = r.id;
      backdrop.remove(); activeModal = null;
      await loadItems();
      renderTabs();
      toast('Liste créée : ' + name);
    }
  }
}

// ── Categories ─────────────────────────────────────────────────────────────────

async function loadCategories() {
  if (state.categories.length) return;
  const r = await api('categories');
  state.categories = r.categories || [];
  state.catMap = {};
  state.categories.forEach(c => { state.catMap[c.id] = c; });
}

let activePicker = null;

function openCategoryPicker(itemId, anchorEl) {
  closeCategoryPicker();
  const item = state.items.find(i => i.id === itemId);
  if (!item) return;

  const picker = document.createElement('div');
  picker.className = 'liste-cat-picker';
  picker.innerHTML = `
    <div class="liste-cat-picker-title">Catégorie</div>
    <div class="liste-cat-picker-list">
      <button class="liste-cat-picker-item${!item.category_id ? ' active' : ''}" data-cat="0">🏷️ Non classé</button>
      ${state.categories.map(c =>
        `<button class="liste-cat-picker-item${item.category_id == c.id ? ' active' : ''}" data-cat="${c.id}">${esc(c.icon)} ${esc(c.name)}</button>`
      ).join('')}
    </div>`;

  const rect = anchorEl.getBoundingClientRect();
  let top  = rect.bottom + 4;
  let left = rect.left;
  if (left + 220 > window.innerWidth) left = window.innerWidth - 228;
  if (top + 340 > window.innerHeight) top = rect.top - Math.min(340, top + 340 - window.innerHeight) - 4;
  picker.style.top  = top  + 'px';
  picker.style.left = left + 'px';

  document.body.appendChild(picker);
  activePicker = picker;

  picker.querySelectorAll('.liste-cat-picker-item').forEach(btn => {
    btn.addEventListener('click', async e => {
      e.stopPropagation();
      const catId = parseInt(btn.dataset.cat) || null;
      await api('set_category', 'POST', { item_id: itemId, category_id: catId });
      const it = state.items.find(i => i.id === itemId);
      if (it) it.category_id = catId;
      closeCategoryPicker();
      renderItems();
    });
  });

  setTimeout(() => {
    document.addEventListener('click', closeCategoryPicker, { once: true });
  }, 0);
}

function closeCategoryPicker() {
  if (activePicker) { activePicker.remove(); activePicker = null; }
}

// ── Tabs ───────────────────────────────────────────────────────────────────────

function typeEmoji(list_type) {
  return LIST_TYPES.find(t => t.value === list_type)?.emoji || '';
}

function renderTabs() {
  const container = document.getElementById('liste-tabs');
  if (!container) return;
  container.innerHTML = '';

  state.lists.forEach(list => {
    const tab = document.createElement('div');
    tab.className = 'liste-tab' + (list.id === state.currentListId ? ' active' : '');
    tab.dataset.id = list.id;

    const dot   = list.color ? `<span class="liste-tab-dot" style="background:${esc(list.color)}"></span>` : '';
    const emoji = typeEmoji(list.list_type);
    const prefix = emoji ? `<span class="liste-tab-type">${emoji}</span>` : '';

    if (list.id === state.currentListId) {
      tab.innerHTML = `
        ${dot}${prefix}
        <span class="liste-tab-name">${esc(list.name)}</span>
        <button class="liste-tab-btn liste-tab-edit" title="Modifier" data-id="${list.id}">✏</button>
      `;
    } else {
      tab.innerHTML = `${dot}${prefix}<span class="liste-tab-name">${esc(list.name)}</span>`;
      tab.addEventListener('click', () => switchList(list.id));
    }
    container.appendChild(tab);
  });

  container.querySelectorAll('.liste-tab-edit').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); openListModal(parseInt(btn.dataset.id)); });
  });
}

// ── List management ─────────────────────────────────────────────────────────────

document.getElementById('btn-add-list')?.addEventListener('click', () => openListModal(null));

async function deleteList(listId) {
  if (!confirm(T.confirm_delete_list || 'Supprimer cette liste et tous ses articles ?')) return;
  const r = await api('lists', 'DELETE', null, { id: listId });
  if (r.error) { toast(r.error, 'error'); return; }
  state.lists = state.lists.filter(l => l.id !== listId);
  if (state.currentListId === listId) {
    state.currentListId = state.lists[0]?.id ?? null;
  }
  await loadItems();
  renderTabs();
  toast(T.list_deleted || 'Liste supprimée');
}

// ── Switch list ─────────────────────────────────────────────────────────────────

async function switchList(listId) {
  state.currentListId = listId;
  await loadItems();
  renderTabs();
}

// ── Items ───────────────────────────────────────────────────────────────────────

async function loadItems() {
  if (!state.currentListId) { renderItems(); return; }
  const r = await api('items', 'GET', null, { list_id: state.currentListId });
  state.items = r.items || [];
  renderItems();
}

function renderItems() {
  const root = document.getElementById('liste-items-root');
  if (!root) return;
  const toolbar = document.getElementById('liste-toolbar');
  if (!state.currentListId || state.items.length === 0) {
    root.innerHTML = state.currentListId
      ? `<div class="liste-empty-items"><span>📝</span><p>Liste vide — ajoutez un article ci-dessus.</p></div>`
      : '';
    if (toolbar) toolbar.style.display = 'none';
    return;
  }
  if (toolbar) toolbar.style.display = '';

  const pending = state.items.filter(i => !i.in_cart);
  const inCart  = state.items.filter(i =>  i.in_cart);
  const hasCategories = pending.some(i => i.category_id);

  let html = '';

  if (hasCategories && state.categories.length) {
    const groups = new Map();
    pending.forEach(item => {
      const key = item.category_id ?? 0;
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(item);
    });
    const catIds = state.categories.map(c => parseInt(c.id));
    const orderedKeys = catIds.filter(id => groups.has(id));
    if (groups.has(0)) orderedKeys.push(0);

    orderedKeys.forEach(key => {
      const items = groups.get(key);
      const cat = key ? state.catMap[key] : null;
      const label = cat ? `${cat.icon} ${cat.name}` : '🏷️ Non classé';
      html += `<div class="liste-cat-section-header">${esc(label)} <span class="liste-cat-section-count">${items.length}</span></div>`;
      html += items.map(rowHtml).join('');
    });
  } else {
    html = pending.map(rowHtml).join('');
  }

  if (inCart.length) {
    html += `<div class="liste-cart-divider">Dans le panier (${inCart.length})</div>` + inCart.map(rowHtml).join('');
  }

  root.innerHTML = html;

  root.querySelectorAll('.liste-check').forEach(cb => {
    cb.addEventListener('change', () => toggleCart(parseInt(cb.dataset.id), cb.checked ? 1 : 0));
  });
  root.querySelectorAll('.btn-edit-item').forEach(btn => {
    btn.addEventListener('click', () => editItem(parseInt(btn.dataset.id)));
  });
  root.querySelectorAll('.btn-delete-item').forEach(btn => {
    btn.addEventListener('click', () => deleteItem(parseInt(btn.dataset.id)));
  });
  root.querySelectorAll('.liste-cat-badge').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); openCategoryPicker(parseInt(btn.dataset.id), btn); });
  });
}

function rowHtml(item) {
  const checked = item.in_cart ? 'checked' : '';
  const cls     = item.in_cart ? 'liste-row in-cart' : 'liste-row';
  const cat = item.category_id ? state.catMap[item.category_id] : null;
  const badgeCls  = 'liste-cat-badge' + (cat ? '' : ' liste-cat-badge-empty');
  const badgeIcon = cat ? esc(cat.icon) : '🏷️';
  const badgeTip  = cat ? esc(cat.name) : 'Non classé';
  return `<div class="${cls}" data-id="${item.id}">
    <button class="${badgeCls}" data-id="${item.id}" title="${badgeTip}">${badgeIcon}</button>
    <label class="liste-check-label">
      <input type="checkbox" class="liste-check" data-id="${item.id}" ${checked}>
      <span class="liste-label">${esc(item.label)}</span>
    </label>
    <div class="liste-row-actions">
      <button class="btn-edit-item" data-id="${item.id}" title="Modifier">✏</button>
      <button class="btn-delete-item" data-id="${item.id}" title="Supprimer">🗑</button>
    </div>
  </div>`;
}

async function addItem(label) {
  const r = await api('items', 'POST', { list_id: state.currentListId, label });
  if (r.duplicate) { toast(T.already_in || 'Déjà dans la liste', 'warn'); return; }
  if (r.id) {
    state.items.push(r);
    renderItems();
    renderHistory();
    toast(T.added || 'Ajouté');
  }
}

async function toggleCart(id, next) {
  await api('items', 'PUT', { in_cart: next }, { id });
  const item = state.items.find(i => i.id === id);
  if (item) item.in_cart = next;
  renderItems();
}

async function editItem(id) {
  const item = state.items.find(i => i.id === id);
  if (!item) return;
  const newLabel = prompt(T.edit_placeholder || 'Modifier :', item.label);
  if (newLabel === null || newLabel.trim() === '') return;
  await api('items', 'PUT', { label: newLabel.trim() }, { id });
  item.label = newLabel.trim();
  renderItems();
  toast(T.updated || 'Modifié');
}

async function deleteItem(id) {
  await api('items', 'DELETE', null, { id });
  state.items = state.items.filter(i => i.id !== id);
  renderItems();
  toast(T.deleted || 'Supprimé');
}

// ── Toolbar ────────────────────────────────────────────────────────────────────

document.getElementById('btn-uncheck-all')?.addEventListener('click', async () => {
  if (!state.currentListId) return;
  await api('uncheck_all', 'POST', { list_id: state.currentListId });
  state.items.forEach(i => i.in_cart = 0);
  renderItems();
});

document.getElementById('btn-delete-picked')?.addEventListener('click', async () => {
  if (!state.currentListId) return;
  await api('delete_picked', 'POST', { list_id: state.currentListId });
  state.items = state.items.filter(i => !i.in_cart);
  renderItems();
  toast(T.deleted || 'Supprimés');
});

document.getElementById('btn-clear-all')?.addEventListener('click', async () => {
  if (!confirm(T.confirm_clear || 'Vider toute la liste ?')) return;
  await api('clear_all', 'POST', { list_id: state.currentListId });
  state.items = [];
  renderItems();
  toast(T.deleted || 'Liste vidée');
});

// ── Add input ───────────────────────────────────────────────────────────────────

document.getElementById('btn-liste-add')?.addEventListener('click', () => {
  const input = document.getElementById('liste-input');
  const v = input?.value.trim();
  if (v) { addItem(v); input.value = ''; input.focus(); }
});

document.getElementById('liste-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('btn-liste-add')?.click();
});

// ── History ────────────────────────────────────────────────────────────────────

async function loadHistory() {
  const r = await api('history');
  state.history = r.history || [];
  renderHistory();
}

function renderHistory() {
  const root = document.getElementById('liste-history-root');
  if (!root || !state.history.length) { if (root) root.innerHTML = ''; return; }
  const currentLabels = new Set(state.items.map(i => i.label.toLowerCase().trim()));
  const suggestions = state.history.filter(h => !currentLabels.has(h.toLowerCase().trim()));
  if (!suggestions.length) { root.innerHTML = ''; return; }
  root.innerHTML = `
    <div class="liste-history-card">
      <div class="liste-history-title">${T.history_title || 'Récemment utilisés'}</div>
      <div class="liste-history-chips">
        ${suggestions.map(h => `<button class="liste-chip" data-label="${esc(h)}">${esc(h)}</button>`).join('')}
      </div>
    </div>`;
  root.querySelectorAll('.liste-chip').forEach(btn => {
    btn.addEventListener('click', () => {
      if (state.currentListId) addItem(btn.dataset.label);
    });
  });
}

// ── Init ────────────────────────────────────────────────────────────────────────

async function init() {
  const [listsR] = await Promise.all([api('lists'), loadCategories()]);
  state.lists = listsR.lists || [];
  state.currentListId = listsR.default_id || state.lists[0]?.id || null;
  renderTabs();
  await Promise.all([loadItems(), loadHistory()]);
}

init();
