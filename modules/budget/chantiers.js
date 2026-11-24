// Onglet « Chantiers » du budget : cartes, lignes de chiffrage, main d'œuvre, dimensions, matériel, liens, notes
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const eur = v => (Math.round((+v || 0) * 100) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  const timers = {};

  async function post(action, data) {
    const fd = new FormData();
    fd.append('action', action);
    Object.keys(data || {}).forEach(k => { if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]); });
    try {
      const r = await fetch(CH.api, { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.success) { alert(j.error || CH.T.error); return null; }
      return j;
    } catch (e) { alert(CH.T.error); return null; }
  }
  const later = (key, fn, ms) => { clearTimeout(timers[key]); timers[key] = setTimeout(fn, ms || 500); };

  // ---------- fenêtres ----------
  window.chCloseModals = () => document.querySelectorAll('.ch-modal').forEach(m => m.hidden = true);
  document.addEventListener('click', e => { if (e.target.classList && e.target.classList.contains('ch-modal')) chCloseModals(); });

  function setIcon(ic) {
    $('ch-proj-icon').value = ic;
    document.querySelectorAll('#ch-icons .ch-icon-opt').forEach(b => b.classList.toggle('active', b.dataset.i === ic));
  }
  const iconBox = $('ch-icons');
  if (iconBox) iconBox.addEventListener('click', e => { const b = e.target.closest('.ch-icon-opt'); if (b) setIcon(b.dataset.i); });

  window.chOpenProject = function (p) {
    $('ch-proj-title').textContent = p ? p.name : document.querySelector('.ch-card-new') ? document.querySelector('.ch-card-new').textContent.replace('＋', '').trim() : '';
    $('ch-proj-name').value = p ? p.name : '';
    setIcon(p ? (p.icon || '🔨') : '🔨');
    $('ch-proj-status').value = p ? p.status : 'idea';
    $('ch-proj-target').value = p && p.target_budget !== null ? p.target_budget : '';
    $('ch-proj-modal').dataset.id = p ? p.id : '';
    $('ch-proj-modal').hidden = false;
    $('ch-proj-name').focus();
  };
  window.chSaveProject = async function () {
    const name = $('ch-proj-name').value.trim();
    if (!name) { $('ch-proj-name').focus(); return; }
    const j = await post('project_save', { id: $('ch-proj-modal').dataset.id || '', name, icon: $('ch-proj-icon').value, status: $('ch-proj-status').value, target_budget: $('ch-proj-target').value });
    if (!j) return;
    if ($('ch-proj-modal').dataset.id) location.reload(); else location.href = '?tab=chantiers&p=' + j.id;
  };
  window.chDeleteProject = async function () {
    if (!(await pachaConfirm('🗑️', CH.T.confirmDelProject))) return;
    if (await post('project_delete', { id: CH.pid })) location.href = '?tab=chantiers';
  };

  // ---------- liste : filtre par statut ----------
  const filters = $('ch-filters');
  if (filters) filters.addEventListener('click', e => {
    const b = e.target.closest('.ch-chip'); if (!b) return;
    filters.querySelectorAll('.ch-chip').forEach(x => x.classList.toggle('active', x === b));
    document.querySelectorAll('.ch-grid .ch-card[data-st]').forEach(c => { c.style.display = (b.dataset.st === 'all' || c.dataset.st === b.dataset.st) ? '' : 'none'; });
  });

  if (!CH.pid) return;

  // ---------- page d'un chantier ----------
  let totals = null;
  const items = CH.items.map(i => Object.assign({}, i));

  function priceShown(i) { return +(i.entered_ht == 1 ? i.unit_ht : i.unit_ttc); }
  const round2 = v => Math.round(v * 100) / 100;
  const lineTtc = i => round2((+i.qty || 0) * (+i.unit_ttc || 0));
  const lineHt = i => round2((+i.qty || 0) * (+i.unit_ht || 0));

  function recompute() {
    const t = { ttc: 0, ht: 0, labor: 0, bought: 0, lines: items.length };
    const byLot = {}, sec = { material: 0, labor: 0 };
    items.forEach(i => {
      const l = (+i.qty || 0) * (+i.unit_ttc || 0);
      t.ttc += l; t.ht += (+i.qty || 0) * (+i.unit_ht || 0);
      if (i.kind === 'labor') t.labor += l;
      if (+i.is_bought) t.bought += l;
      sec[i.kind === 'labor' ? 'labor' : 'material'] += l;
      const k = (i.lot || '').trim() || CH.L.noLot;
      byLot[k] = (byLot[k] || 0) + l;
    });
    const p = CH.project;
    const lots = Object.keys(byLot).sort().map(k => `<span class="ch-lot">${esc(k)} <strong>${eur(byLot[k])}</strong></span>`).join('');
    const over = p.target_budget !== null && p.target_budget !== '' && t.ttc > +p.target_budget;
    $('ch-summary').innerHTML = `
      <div class="ch-sum-main"><span>${esc(CH.L.totalTtc)}</span><strong>${eur(t.ttc)}</strong><small>${esc(CH.L.totalHt)} ${eur(t.ht)}</small></div>
      <div class="ch-sum-side">
        ${t.labor > 0 ? `<div>${esc(CH.L.labor)} <strong>${eur(t.labor)}</strong></div>` : ''}
        <div>${esc(CH.L.boughtTotal)} <strong>${eur(t.bought)}</strong> · ${esc(CH.L.remaining)} <strong>${eur(t.ttc - t.bought)}</strong></div>
        ${p.target_budget !== null && p.target_budget !== '' ? `<div class="${over ? 'ch-over' : ''}">${esc(CH.L.target)} <strong>${eur(p.target_budget)}</strong>${over ? ' · ' + esc(CH.L.over) + ' ' + eur(t.ttc - p.target_budget) : ''}</div>` : ''}
      </div>
      ${lots ? `<div class="ch-lots"><span class="ch-lots-t">${esc(CH.L.byLot)}</span>${lots}</div>` : ''}`;
    $('ch-tot-material').textContent = eur(sec.material);
    $('ch-tot-labor').textContent = eur(sec.labor);
    CH.provisionAmount = round2(Math.max(0, t.ttc - t.bought));
  }

  function rowHtml(i) {
    const labor = i.kind === 'labor';
    const ht = +i.entered_ht === 1;
    const vats = [20, 10, 5.5, 0];
    const v = +i.vat_rate;
    const vOpts = (vats.includes(v) ? vats : vats.concat([v])).map(x => `<option value="${x}" ${x === v ? 'selected' : ''}>${x} %</option>`).join('');
    return `<div class="ch-row ${+i.is_bought ? 'ch-bought' : ''}" data-id="${i.id}">
      <label class="ch-chk" title="${esc(labor ? CH.L.paid : CH.L.bought)}"><input type="checkbox" data-f="is_bought" ${+i.is_bought ? 'checked' : ''}></label>
      <input class="ch-in ch-name" data-f="name" value="${esc(i.name)}" placeholder="${esc(labor ? CH.L.laborName : CH.L.name)}">
      <input class="ch-in ch-qty" data-f="qty" type="number" step="any" min="0" value="${esc(+i.qty)}" title="${esc(CH.L.qty)}">
      ${labor ? `<input class="ch-in ch-unit" data-f="unit" value="${esc(i.unit)}" placeholder="${esc(CH.L.unit)}" title="${esc(CH.L.unit)}" list="ch-units">` : ''}
      <span class="ch-x">×</span>
      <input class="ch-in ch-price" data-f="price" type="number" step="any" min="0" value="${esc(round2(priceShown(i)))}" title="${esc(CH.L.price)}">
      <label class="ch-ht" title="${esc(CH.T.hint)}"><input type="checkbox" data-f="entered_ht" ${ht ? 'checked' : ''}> ${esc(CH.L.ht)}</label>
      <span class="ch-line"><strong data-r="ttc">${eur(lineTtc(i))}</strong><small data-r="ht">${eur(lineHt(i))} HT</small></span>
      <button type="button" class="ch-ico" data-act="more" title="${esc(CH.L.more)}">⋯</button>
      <button type="button" class="ch-ico" data-act="del" title="${esc(CH.L.del)}">🗑️</button>
      <div class="ch-more" hidden>
        <label>${esc(CH.L.lot)}<input class="ch-in" data-f="lot" list="ch-lots" value="${esc(i.lot)}"></label>
        <label>${esc(CH.L.store)}<input class="ch-in" data-f="store" value="${esc(i.store)}"></label>
        <label>${esc(CH.L.url)}<input class="ch-in" data-f="url" type="url" value="${esc(i.url)}" placeholder="https://"></label>
        <label>${esc(CH.L.vat)}<select class="ch-in" data-f="vat_rate">${vOpts}</select></label>
        <label class="ch-note">${esc(CH.L.note)}<input class="ch-in" data-f="note" value="${esc(i.note)}"></label>
        ${i.url ? `<a class="ch-open" href="${esc(i.url)}" target="_blank" rel="noopener noreferrer">🔗 ${esc(i.store || CH.L.open)}</a>` : ''}
      </div>
    </div>`;
  }

  function renderItems() {
    ['material', 'labor'].forEach(k => {
      $('ch-rows-' + k).innerHTML = items.filter(i => (i.kind === 'labor' ? 'labor' : 'material') === k).map(rowHtml).join('');
    });
    recompute();
  }

  function collect(row) {
    const g = f => row.querySelector(`[data-f="${f}"]`);
    const id = +row.dataset.id, i = items.find(x => +x.id === id);
    return {
      id, project_id: CH.pid, kind: i.kind,
      name: g('name').value, qty: g('qty').value, unit: g('unit') ? g('unit').value : i.unit,
      price: g('price').value, entered_ht: g('entered_ht').checked ? 1 : '', vat_rate: g('vat_rate').value,
      lot: g('lot').value, store: g('store').value, url: g('url').value, note: g('note').value, is_bought: g('is_bought').checked ? 1 : ''
    };
  }

  function applyLocal(i, d) {
    // mêmes calculs que le serveur, pour afficher le total sans attendre
    const ht = !!d.entered_ht, p = Math.max(0, parseFloat(String(d.price).replace(',', '.')) || 0), f = 1 + (+d.vat_rate || 0) / 100;
    Object.assign(i, { name: d.name, qty: parseFloat(String(d.qty).replace(',', '.')) || 0, unit: d.unit, vat_rate: +d.vat_rate, entered_ht: ht ? 1 : 0,
      unit_ht: ht ? p : p / f, unit_ttc: ht ? p * f : p, lot: d.lot, store: d.store, url: d.url, note: d.note, is_bought: d.is_bought ? 1 : 0 });
  }

  async function saveRow(row, rerender) {
    const d = collect(row), i = items.find(x => +x.id === d.id);
    applyLocal(i, d);
    row.querySelector('[data-r="ttc"]').textContent = eur(lineTtc(i));
    row.querySelector('[data-r="ht"]').textContent = eur(lineHt(i)) + ' HT';
    row.classList.toggle('ch-bought', !!+i.is_bought);
    recompute();
    const j = await post('item_save', d);
    if (!j) return;
    if (rerender) renderItems();
  }

  document.addEventListener('input', e => {
    const row = e.target.closest && e.target.closest('.ch-row'); if (!row) return;
    if (e.target.type === 'checkbox' || e.target.tagName === 'SELECT') return;
    later('row' + row.dataset.id, () => saveRow(row, false));
  });
  document.addEventListener('change', e => {
    const row = e.target.closest && e.target.closest('.ch-row'); if (!row) return;
    clearTimeout(timers['row' + row.dataset.id]);
    const f = e.target.dataset.f;
    // lot / lien modifiés : la liste de lots et le lien affiché changent
    saveRow(row, false).then(() => {
      if (f === 'url') { const i = items.find(x => +x.id === +row.dataset.id); const open = row.querySelector('.ch-open'); if (open) open.remove(); if (i.url && /^https?:\/\//i.test(i.url)) row.querySelector('.ch-more').insertAdjacentHTML('beforeend', `<a class="ch-open" href="${esc(i.url)}" target="_blank" rel="noopener noreferrer">🔗 ${esc(i.store || CH.L.open)}</a>`); }
    });
  });
  document.addEventListener('click', async e => {
    const btn = e.target.closest && e.target.closest('.ch-ico'); if (!btn) return;
    const row = btn.closest('.ch-row'), id = +row.dataset.id;
    if (btn.dataset.act === 'more') { const m = row.querySelector('.ch-more'); m.hidden = !m.hidden; return; }
    if (btn.dataset.act === 'del') {
      if (!(await pachaConfirm('🗑️', CH.T.confirmDelLine))) return;
      if (await post('item_delete', { id, project_id: CH.pid })) { items.splice(items.findIndex(x => +x.id === id), 1); renderItems(); }
    }
  });

  window.chAddItem = async function (kind) {
    const j = await post('item_save', { project_id: CH.pid, kind, name: '', qty: 1, price: 0, vat_rate: 20 });
    if (!j) return;
    items.push({ id: j.id, kind, lot: '', name: '', store: '', url: '', qty: 1, unit: '', unit_ttc: 0, unit_ht: 0, vat_rate: 20, entered_ht: 0, note: '', is_bought: 0 });
    renderItems();
    const row = document.querySelector(`.ch-row[data-id="${j.id}"] .ch-name`); if (row) row.focus();
  };

  // ---------- dimensions : surface / volume, cliquables pour remplir une quantité ----------
  let pick = null;
  const dim = id => parseFloat($(id).value.replace(',', '.')) || 0;
  function dims() {
    const w = dim('ch-dim-w'), l = dim('ch-dim-l'), h = dim('ch-dim-h');
    const r = x => (Math.round(x * 10000) / 10000).toLocaleString('fr-FR');
    $('ch-surface').textContent = r(w * l);
    $('ch-volume').textContent = r(w * l * h);
    return { surface: Math.round(w * l * 10000) / 10000, volume: Math.round(w * l * h * 10000) / 10000 };
  }
  ['ch-dim-w', 'ch-dim-l', 'ch-dim-h'].forEach(id => $(id).addEventListener('input', () => {
    dims();
    later('dims', () => post('project_dims', { id: CH.pid, dim_w: $('ch-dim-w').value, dim_l: $('ch-dim-l').value, dim_h: $('ch-dim-h').value }), 600);
  }));
  document.querySelectorAll('.ch-pick').forEach(b => b.addEventListener('click', () => {
    pick = b.dataset.k;
    document.querySelectorAll('.ch-pick').forEach(x => x.classList.toggle('active', x === b));
    document.body.classList.add('ch-picking');
    b.title = CH.T.pickHint;
    const hint = $('ch-pick-hint') || (() => { const d = document.createElement('div'); d.id = 'ch-pick-hint'; d.className = 'ch-toast'; document.body.appendChild(d); return d; })();
    hint.textContent = CH.T.pickHint; hint.hidden = false;
  }));
  document.addEventListener('click', e => {
    if (!pick) return;
    const q = e.target.closest && e.target.closest('.ch-qty');
    if (!q) { if (!e.target.closest('.ch-pick')) stopPick(); return; }
    e.preventDefault();
    const v = dims()[pick];
    q.value = v;
    q.dispatchEvent(new Event('change', { bubbles: true }));
    stopPick();
  }, true);
  function stopPick() {
    pick = null; document.body.classList.remove('ch-picking');
    document.querySelectorAll('.ch-pick').forEach(x => x.classList.remove('active'));
    const h = $('ch-pick-hint'); if (h) h.hidden = true;
  }

  // ---------- matériel à emprunter ----------
  const tools = CH.tools.map(t => Object.assign({}, t));
  function renderTools() {
    $('ch-tools').innerHTML = tools.map(t => `<div class="ch-mini" data-id="${t.id}">
      <input class="ch-in" data-f="name" value="${esc(t.name)}" placeholder="${esc(CH.L.tool)}">
      <input class="ch-in" data-f="lender" value="${esc(t.lender)}" placeholder="${esc(CH.L.lender)}">
      <button type="button" class="ch-ico" data-act="del" title="${esc(CH.L.del)}">🗑️</button></div>`).join('');
  }
  window.chAddTool = async function () {
    const j = await post('tool_save', { project_id: CH.pid, name: '', lender: '' }); if (!j) return;
    tools.push({ id: j.id, name: '', lender: '' }); renderTools();
    const f = document.querySelector(`#ch-tools .ch-mini[data-id="${j.id}"] input`); if (f) f.focus();
  };
  $('ch-tools').addEventListener('input', e => {
    const m = e.target.closest('.ch-mini'); if (!m) return;
    later('tool' + m.dataset.id, () => post('tool_save', { id: m.dataset.id, project_id: CH.pid, name: m.querySelector('[data-f=name]').value, lender: m.querySelector('[data-f=lender]').value }));
  });
  $('ch-tools').addEventListener('click', async e => {
    const b = e.target.closest('.ch-ico'); if (!b) return; const m = b.closest('.ch-mini');
    if (!(await pachaConfirm('🗑️', CH.T.confirmDelItem))) return;
    if (await post('tool_delete', { id: m.dataset.id, project_id: CH.pid })) { tools.splice(tools.findIndex(t => +t.id === +m.dataset.id), 1); renderTools(); }
  });

  // ---------- liens ----------
  const links = CH.links.map(l => Object.assign({}, l));
  function renderLinks() {
    $('ch-links').innerHTML = links.map(l => `<div class="ch-mini" data-id="${l.id}">
      <input class="ch-in" data-f="title" value="${esc(l.title)}" placeholder="${esc(CH.L.title)}">
      <input class="ch-in" data-f="url" type="url" value="${esc(l.url)}" placeholder="https://">
      <a class="ch-ico" href="${esc(l.url)}" target="_blank" rel="noopener noreferrer" title="${esc(CH.L.open)}">↗</a>
      <button type="button" class="ch-ico" data-act="del" title="${esc(CH.L.del)}">🗑️</button></div>`).join('');
  }
  window.chAddLink = async function () {
    const url = prompt(CH.L.address, 'https://'); if (!url) return;
    const j = await post('link_save', { project_id: CH.pid, title: '', url }); if (!j) return;
    links.push({ id: j.id, title: '', url }); renderLinks();
    const f = document.querySelector(`#ch-links .ch-mini[data-id="${j.id}"] input`); if (f) f.focus();
  };
  $('ch-links').addEventListener('change', async e => {
    const m = e.target.closest('.ch-mini'); if (!m) return;
    const l = links.find(x => +x.id === +m.dataset.id);
    const d = { id: m.dataset.id, project_id: CH.pid, title: m.querySelector('[data-f=title]').value, url: m.querySelector('[data-f=url]').value };
    if (await post('link_save', d)) { l.title = d.title; l.url = d.url; renderLinks(); }
  });
  $('ch-links').addEventListener('click', async e => {
    const b = e.target.closest('button.ch-ico'); if (!b) return; const m = b.closest('.ch-mini');
    if (!(await pachaConfirm('🗑️', CH.T.confirmDelItem))) return;
    if (await post('link_delete', { id: m.dataset.id, project_id: CH.pid })) { links.splice(links.findIndex(x => +x.id === +m.dataset.id), 1); renderLinks(); }
  });

  // ---------- notes ----------
  $('ch-notes').addEventListener('input', () => later('notes', () => post('project_notes', { id: CH.pid, notes: $('ch-notes').value }), 800));

  // ---------- provision ----------
  window.chOpenProvision = function () {
    $('ch-prov-amount').value = CH.provisionAmount;
    const d = new Date(); d.setMonth(d.getMonth() + 1);
    $('ch-prov-date').value = d.toISOString().slice(0, 10);
    $('ch-prov-modal').hidden = false;
  };
  window.chSaveProvision = async function () {
    const j = await post('project_provision', { id: CH.pid, amount: $('ch-prov-amount').value, expected_date: $('ch-prov-date').value });
    if (!j) return;
    chCloseModals();
    alert(j.updated ? CH.T.provisionUpd : CH.T.provisionOk);
    location.reload();
  };

  // listes d'unités proposées pour la main d'œuvre
  document.body.insertAdjacentHTML('beforeend', '<datalist id="ch-units"><option value="h"><option value="j"><option value="forfait"></datalist>');
  renderItems(); renderTools(); renderLinks(); dims();
})();
