/* invraw — Liste module JS */
const API = '/modules/liste/api.php';
const T   = window.LISTE_TRANSLATIONS || {};

const state = {
  lists: [],
  currentListId: null,
  items: [],
  history: [],
};

// ── Utilities ─────────────────────────────────────────────────────────────────

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

// ── Tabs ──────────────────────────────────────────────────────────────────────

function renderTabs() {
  const container = document.getElementById('liste-tabs');
  if (!container) return;
  container.innerHTML = '';
  state.lists.forEach(list => {
    const tab = document.createElement('div');
    tab.className = 'liste-tab' + (list.id === state.currentListId ? ' active' : '');
    tab.dataset.id = list.id;

    if (list.id === state.currentListId) {
      tab.innerHTML = `
        <span class="liste-tab-name">${esc(list.name)}</span>
        <button class="liste-tab-btn liste-tab-rename" title="${esc(T.rename_list)}" data-id="${list.id}">✏</button>
        ${state.lists.length > 1 ? `<button class="liste-tab-btn liste-tab-delete" title="Supprimer" data-id="${list.id}">×</button>` : ''}
      `;
    } else {
      tab.innerHTML = `<span class="liste-tab-name">${esc(list.name)}</span>`;
      tab.addEventListener('click', () => switchList(list.id));
    }
    container.appendChild(tab);
  });

  // Rename button
  container.querySelectorAll('.liste-tab-rename').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); startRename(parseInt(btn.dataset.id)); });
  });
  // Delete button
  container.querySelectorAll('.liste-tab-delete').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); deleteList(parseInt(btn.dataset.id)); });
  });
}

function startRename(listId) {
  const list = state.lists.find(l => l.id === listId);
  if (!list) return;
  const name = prompt(T.new_name || 'Nouveau nom :', list.name);
  if (name === null || name.trim() === '') return;
  api('lists', 'PUT', { name: name.trim() }, { id: listId }).then(r => {
    if (r.ok) {
      list.name = name.trim();
      renderTabs();
      toast(T.list_renamed || 'Liste renommée');
    }
  });
}

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

// ── List management ────────────────────────────────────────────────────────────

document.getElementById('btn-add-list')?.addEventListener('click', () => {
  document.getElementById('liste-new-form')?.classList.remove('hidden');
  document.getElementById('btn-add-list')?.classList.add('hidden');
  document.getElementById('new-list-name')?.focus();
});
document.getElementById('btn-cancel-new-list')?.addEventListener('click', cancelNewList);
document.getElementById('btn-confirm-new-list')?.addEventListener('click', confirmNewList);
document.getElementById('new-list-name')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') confirmNewList();
  if (e.key === 'Escape') cancelNewList();
});

function cancelNewList() {
  document.getElementById('liste-new-form')?.classList.add('hidden');
  document.getElementById('btn-add-list')?.classList.remove('hidden');
  if (document.getElementById('new-list-name')) document.getElementById('new-list-name').value = '';
}

async function confirmNewList() {
  const input = document.getElementById('new-list-name');
  const name = input?.value.trim();
  if (!name) return;
  const r = await api('lists', 'POST', { name });
  if (r.id) {
    state.lists.push({ id: r.id, name: r.name, position: r.position });
    state.currentListId = r.id;
    cancelNewList();
    await loadItems();
    renderTabs();
    toast((T.list_created || 'Liste créée') + ' : ' + r.name);
  }
}

// ── Switch list ────────────────────────────────────────────────────────────────

async function switchList(listId) {
  state.currentListId = listId;
  await loadItems();
  renderTabs();
}

// ── Items ──────────────────────────────────────────────────────────────────────

async function loadItems() {
  if (!state.currentListId) {
    renderItems();
    return;
  }
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
  let html = '';
  if (pending.length) html += pending.map(rowHtml).join('');
  if (inCart.length)  html += `<div class="liste-cart-divider">Dans le panier (${inCart.length})</div>` + inCart.map(rowHtml).join('');
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
}

function rowHtml(item) {
  const checked = item.in_cart ? 'checked' : '';
  const cls     = item.in_cart ? 'liste-row in-cart' : 'liste-row';
  return `<div class="${cls}" data-id="${item.id}">
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

// ── Add input ──────────────────────────────────────────────────────────────────

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

// ── Init ───────────────────────────────────────────────────────────────────────

async function init() {
  const r = await api('lists');
  state.lists = r.lists || [];
  state.currentListId = r.default_id || state.lists[0]?.id || null;
  renderTabs();
  await Promise.all([loadItems(), loadHistory()]);
}

init();
