// HouseHub — Liste de courses
const API = '/modules/groceries/api.php';

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s ?? '');
  return d.innerHTML;
}

function T(key, fallback) {
  if (typeof tr === 'function') {
    const v = tr(key);
    if (v && v !== key) return v;
  }
  return fallback;
}

function escAttr(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

function toast(msg, type = 'success') {
  const c = document.getElementById('groceries-toasts');
  const t = document.createElement('div');
  t.className = 'groceries-toast' + (type === 'error' ? ' error' : '');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

async function api(action, method = 'GET', data = null, extra = '') {
  const opts = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  };
  if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  const r = await fetch(API + '?action=' + action + extra, opts);
  const text = await r.text();
  let j;
  try {
    j = JSON.parse(text);
  } catch (e) {
    console.error('Bad JSON from API:', text.slice(0, 200));
    throw new Error(T('error_occured', 'Erreur serveur'));
  }
  if (!j.ok) throw new Error(j.error || T('error_occured', 'Erreur'));
  return j.data;
}

async function loadAll() {
  try {
    const [items, hist] = await Promise.all([api('items', 'GET'), api('history', 'GET')]);
    render(items);
    updateSubtitle(items);
    renderHistory(hist);
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

function updateSubtitle(items) {
  const el = document.getElementById('groceries-subtitle');
  if (!el) return;
  const pending = items.filter((i) => !parseInt(i.in_cart, 10)).length;
  const cart = items.filter((i) => parseInt(i.in_cart, 10)).length;
  if (!items.length) {
    el.textContent = '';
    return;
  }
  const tpl = typeof tr === 'function' ? tr('groceries_subtitle') : '';
  el.textContent = (tpl && tpl !== 'groceries_subtitle' ? tpl : '{pending} à prendre · {cart} dans le caddie')
    .replace('{pending}', String(pending))
    .replace('{cart}', String(cart));
}

function renderHistory(hist) {
  const root = document.getElementById('groceries-history-root');
  if (!root) return;
  const max = hist && typeof hist.history_max === 'number' ? hist.history_max : 20;
  const labels = (hist && hist.labels) || [];
  const title = escHtml(T('groceries_history_title', 'Historique'));
  const metaTpl = T('groceries_history_meta', 'Jusqu’à {max} produits · ');
  const meta = escHtml(metaTpl.replace('{max}', String(max)));
  const linkLabel = escHtml(T('groceries_settings_link', 'Paramètres'));
  let chips = '';
  labels.forEach((lab) => {
    const l = String(lab);
    chips +=
      '<button type="button" class="groceries-history-chip" data-label="' +
      escAttr(l) +
      '"><span class="groceries-history-chip-plus">+</span>' +
      escHtml(l) +
      '</button>';
  });
  const emptyHint = labels.length
    ? ''
    : '<p class="groceries-history-empty">' + escHtml(T('groceries_history_empty', '')) + '</p>';
  root.innerHTML =
    '<div class="groceries-history-inner">' +
    '<div class="groceries-history-head">' +
    '<h2 class="groceries-history-h2">' +
    title +
    '</h2>' +
    '<p class="groceries-history-meta">' +
    meta +
    '<a href="/settings.php" class="groceries-history-settings-link">' +
    linkLabel +
    '</a></p></div>' +
    '<div class="groceries-history-chips">' +
    chips +
    '</div>' +
    emptyHint +
    '</div>';
}

function render(items) {
  const wrap = document.getElementById('groceries-lists');
  if (!items.length) {
    const msg = T('groceries_empty', 'Rien pour le moment. Ajoutez un produit ci-dessus.');
    wrap.innerHTML =
      '<div class="groceries-empty"><div class="groceries-empty-icon">🛒</div><p>' + escHtml(msg) + '</p></div>';
    return;
  }
  const pending = items.filter((i) => !parseInt(i.in_cart, 10));
  const cart = items.filter((i) => parseInt(i.in_cart, 10));
  const labPending = escHtml(T('groceries_section_pending', 'À prendre'));
  const labCart = escHtml(T('groceries_section_cart', 'Dans le caddie'));
  let html = '';
  if (pending.length) {
    html += '<div class="groceries-section-title">' + labPending + '</div><div class="groceries-list">';
    pending.forEach((i) => {
      html += rowHtml(i, false);
    });
    html += '</div>';
  }
  if (cart.length) {
    html += '<div class="groceries-section-title">' + labCart + '</div><div class="groceries-list">';
    cart.forEach((i) => {
      html += rowHtml(i, true);
    });
    html += '</div>';
  }
  wrap.innerHTML = html;
}

function rowHtml(i, inCart) {
  const id = i.id;
  const checkClass = 'groceries-check' + (inCart ? ' checked' : '');
  const rowClass = 'groceries-row' + (inCart ? ' in-cart' : '');
  const aria = escAttr(T('groceries_aria_toggle', 'Marquer dans le caddie'));
  return (
    '<div class="' +
    rowClass +
    '" data-id="' +
    id +
    '">' +
    '<button type="button" class="' +
    checkClass +
    '" onclick="toggleCart(' +
    id +
    ',' +
    (inCart ? '0' : '1') +
    ')" aria-label="' +
    aria +
    '"></button>' +
    '<div class="groceries-label">' +
    escHtml(i.label) +
    '</div>' +
    '<div class="groceries-row-actions">' +
    '<button type="button" class="btn-icon-action edit" onclick="editItem(' +
    id +
    ')" title="' +
    escAttr(T('edit', 'Modifier')) +
    '" aria-label="' +
    escAttr(T('edit', 'Modifier')) +
    '">✏️</button>' +
    '<button type="button" class="btn-icon-action delete" onclick="deleteItem(' +
    id +
    ')" title="' +
    escAttr(T('delete', 'Supprimer')) +
    '" aria-label="' +
    escAttr(T('delete', 'Supprimer')) +
    '">🗑️</button>' +
    '</div></div>'
  );
}

async function addFromInput() {
  const input = document.getElementById('groceries-new');
  const label = (input.value || '').trim();
  if (!label) return;
  try {
    await api('items', 'POST', { label: label });
    input.value = '';
    input.focus();
    toast(T('groceries_added', 'Ajouté'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function addFromHistory(label) {
  const t = String(label || '').trim();
  if (!t) return;
  try {
    await api('items', 'POST', { label: t });
    toast(T('groceries_added', 'Ajouté'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function toggleCart(id, next) {
  try {
    await api('items', 'PUT', { in_cart: !!parseInt(next, 10) }, '&id=' + id);
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function editItem(id) {
  const row = document.querySelector('.groceries-row[data-id="' + id + '"]');
  const current = row ? row.querySelector('.groceries-label').textContent : '';
  const label = window.prompt(T('groceries_prompt_edit', 'Modifier le produit'), current);
  if (label === null) return;
  const t = label.trim();
  if (!t) {
    toast(T('groceries_label_empty', 'Libellé vide'), 'error');
    return;
  }
  try {
    await api('items', 'PUT', { label: t }, '&id=' + id);
    toast(T('groceries_updated', 'Mis à jour'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function deleteItem(id) {
  const ok = await pachaConfirm(
    T('groceries_confirm_del_title', 'Supprimer ?'),
    T('groceries_confirm_del_body', 'Retirer ce produit de la liste ?')
  );
  if (!ok) return;
  try {
    await api('items', 'DELETE', null, '&id=' + id);
    toast(T('groceries_deleted', 'Supprimé'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function uncheckAll() {
  const ok = await pachaConfirm(
    T('groceries_confirm_uncheck_title', 'Tout remettre à prendre'),
    T('groceries_confirm_uncheck_body', '')
  );
  if (!ok) return;
  try {
    await api('uncheck_all', 'POST', {});
    toast(T('groceries_reset_next', 'Liste prête pour la prochaine sortie'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function deletePicked() {
  const ok = await pachaConfirm(
    T('groceries_confirm_clear_title', 'Retirer les produits du caddie'),
    T('groceries_confirm_clear_body', '')
  );
  if (!ok) return;
  try {
    await api('delete_picked', 'POST', {});
    toast(T('groceries_picked_removed', 'Articles retirés'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

async function clearWholeList() {
  const ok = await pachaConfirm(
    T('groceries_confirm_empty_list_title', 'Vider toute la liste ?'),
    T('groceries_confirm_empty_list_body', '')
  );
  if (!ok) return;
  try {
    await api('clear_all', 'POST', {});
    toast(T('groceries_list_cleared', 'Liste vidée'));
    loadAll();
  } catch (e) {
    toast(e.message || T('error_occured', 'Erreur'), 'error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('groceries-new');
  if (input) {
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addFromInput();
      }
    });
  }
  const histRoot = document.getElementById('groceries-history-root');
  if (histRoot) {
    histRoot.addEventListener('click', (e) => {
      const btn = e.target.closest('.groceries-history-chip');
      if (!btn || !histRoot.contains(btn)) return;
      const raw = btn.getAttribute('data-label');
      if (raw) addFromHistory(raw);
    });
  }
  loadAll();
});
