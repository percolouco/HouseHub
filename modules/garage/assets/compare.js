// Comparateur de coût : bibliothèque de scénarios (marque / modèle / année), formulaire de saisie, comparaison en tableau + graphique.
// Les calculs sont dans tco-calc.js ; l'API est modules/garage/api.php?action=tco.
(function () {
  'use strict';
  var TCO = window.TCO, SEL_KEY = 'garage-tco-sel', FILTER_KEY = 'garage-tco-filter';
  var BRANDS = ['Alfa Romeo', 'Audi', 'BMW', 'BYD', 'Citroën', 'Cupra', 'Dacia', 'DS', 'Fiat', 'Ford', 'Honda', 'Hyundai', 'Jeep', 'Kia', 'Lexus', 'Mazda', 'Mercedes', 'MG', 'Mini', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Renault', 'Seat', 'Skoda', 'Smart', 'Suzuki', 'Tesla', 'Toyota', 'Volkswagen', 'Volvo'];
  var FUELS = { 'Essence': { price: 1.85, conso: 6.5, unit: 'L' }, 'Diesel': { price: 1.75, conso: 5.5, unit: 'L' }, 'Hybride': { price: 1.85, conso: 4.8, unit: 'L' },
                'Électrique': { price: 0.25, conso: 17, unit: 'kWh' }, 'GPL': { price: 0.85, conso: 9, unit: 'L' }, 'Autre': { price: 1.8, conso: 6, unit: 'L' } };
  var MODES = { neuf: 'Achat neuf', occasion: 'Achat d\'occasion', lld: 'Location longue durée (LLD)', leasing: 'Leasing (LOA / crédit-bail)' };
  var MODE_SHORT = { neuf: 'Neuf', occasion: 'Occasion', lld: 'LLD', leasing: 'Leasing' };
  var BUY = ['neuf', 'occasion'], LEASE = ['lld', 'leasing'], ALL = ['neuf', 'occasion', 'lld', 'leasing'];

  // Champs du formulaire : [clé, libellé, unité, modes concernés, aide, condition]
  var GROUPS = [
    { t: '🚗 Utilisation', f: [
      ['km_an', 'Kilométrage annuel', 'km/an', ALL, ''], ['months', 'Durée de détention / du contrat', 'mois', ALL, 'Sert aussi à calculer les coûts « par mois » et « par an »'],
      ['age_start', 'Âge du véhicule à l\'achat', 'ans', ['occasion'], 'Sert au contrôle technique (1er à 4 ans, puis tous les 2 ans)'] ] },
    { t: '💶 Achat', modes: BUY, f: [
      ['price', 'Prix d\'achat TTC', '€', BUY, ''], ['resale', 'Valeur de revente estimée à la fin', '€', BUY, 'Bouton « Estimer » : décote indicative (-20 % la 1re année pour le neuf, puis -10 %/an)', null, 'estimate'],
      ['carte_grise', 'Carte grise', '€', ALL, ''], ['malus', 'Malus écologique / taxes à l\'achat', '€', ALL, ''], ['bonus', 'Bonus / aides (à déduire)', '€', ALL, ''],
      ['frais_divers', 'Autres frais d\'achat (livraison, garantie…)', '€', BUY, ''],
      ['credit', 'Achat financé par un crédit', '', BUY, '', null, 'check'],
      ['apport', 'Apport', '€', BUY, '', function (d) { return d.credit; }], ['credit_rate', 'Taux du crédit (TAEG)', '%', BUY, '', function (d) { return d.credit; }],
      ['credit_months', 'Durée du crédit', 'mois', BUY, '', function (d) { return d.credit; }],
      ['opp_rate', 'Rendement de l\'argent immobilisé', '%/an', ALL, 'Coût d\'opportunité : ce que l\'argent immobilisé aurait rapporté placé (ex. 2-3 %). 0 pour l\'ignorer'] ] },
    { t: '📄 Location (LLD / leasing)', modes: LEASE, f: [
      ['rent', 'Loyer mensuel', '€/mois', LEASE, ''], ['apport', '1er loyer majoré / apport', '€', LEASE, ''], ['frais_dossier', 'Frais de dossier', '€', LEASE, ''],
      ['depot_garantie', 'Dépôt de garantie (rendu en fin de contrat)', '€', LEASE, 'Argent immobilisé : compté dans le coût d\'opportunité'],
      ['km_incl_an', 'Kilomètres inclus', 'km/an', LEASE, '0 = illimité'], ['km_excess', 'Prix du km excédentaire', '€/km', LEASE, ''],
      ['remise_etat', 'Remise en état / frais de restitution estimés', '€', LEASE, ''],
      ['ent_incl', 'Entretien et pneus inclus dans le loyer', '', LEASE, '', null, 'check'], ['ass_incl', 'Assurance incluse dans le loyer', '', LEASE, '', null, 'check'],
      ['levee', 'Levée de l\'option d\'achat en fin de contrat', '', ['leasing'], '', null, 'check'],
      ['option_achat', 'Prix de l\'option d\'achat', '€', ['leasing'], '', function (d) { return d.levee; }],
      ['resale', 'Valeur de revente estimée après levée', '€', ['leasing'], '', function (d) { return d.levee; }] ] },
    { t: '⛽ Coûts d\'usage', f: [
      ['conso', 'Consommation', '__unit__/100 km', ALL, ''], ['energy_price', 'Prix de l\'énergie', '€/__unit__', ALL, ''],
      ['assurance_an', 'Assurance', '€/an', ALL, ''],
      ['entretien_an', 'Entretien (révision, vidange…)', '€/an', ALL, ''], ['pneus_set', 'Jeu de pneus', '€', ALL, ''], ['pneus_km', 'Durée de vie d\'un jeu de pneus', 'km', ALL, ''],
      ['ct_cost', 'Contrôle technique', '€/passage', ALL, ''], ['taxes_an', 'Taxes annuelles (vignette, taxe au poids…)', '€/an', ALL, ''],
      ['parking_mois', 'Stationnement / garage', '€/mois', ALL, ''], ['divers_an', 'Péages, lavage, accessoires…', '€/an', ALL, ''],
      ['repair_an', 'Provision pour réparations imprévues', '€/an', ALL, 'Plus élevée pour un véhicule d\'occasion ou hors garantie'] ] }
  ];
  var DEFAULTS = { km_an: 12000, months: 48, age_start: 3, opp_rate: 2, credit_months: 48, credit_rate: 5, pneus_km: 40000, pneus_set: 500, ct_cost: 80, assurance_an: 650, entretien_an: 300, divers_an: 100, km_excess: 0.1 };

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var eur = function (v) { return (v == null || !isFinite(v)) ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v); };
  var eur2 = function (v) { return (v == null || !isFinite(v)) ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v); };
  var load = function (k, d) { try { return JSON.parse(localStorage.getItem(k)) || d; } catch (e) { return d; } };
  var store = function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ } };

  var list = [], vehicles = [], sel = new Set(load(SEL_KEY, [])), filt = Object.assign({ brand: '', model: '', year: '', mode: '', q: '' }, load(FILTER_KEY, {})), chart = null, editing = null, ready = false;

  async function call(method, extra, body) {
    var r = await fetch('/modules/garage/api.php?action=tco' + (extra || ''), { method: method, headers: body ? { 'Content-Type': 'application/json' } : {}, body: body ? JSON.stringify(body) : undefined });
    var j = await r.json(); if (!j.ok) throw new Error(j.error || 'Erreur'); return j.data;
  }

  var calc = function (s) { return TCO.compute(Object.assign({}, s.data || {}, { mode: s.mode })); }; // le mode est porté par le scénario, pas par ses données

  // ----- Liste filtrée -----
  function filtered() {
    var q = filt.q.trim().toLowerCase();
    return list.filter(function (s) {
      return (!filt.brand || s.brand === filt.brand) && (!filt.model || s.model === filt.model) && (!filt.year || String(s.year || '') === filt.year) && (!filt.mode || s.mode === filt.mode) &&
        (!q || (s.label + ' ' + s.brand + ' ' + s.model).toLowerCase().indexOf(q) !== -1);
    });
  }
  function uniq(arr) { return arr.filter(function (v, i) { return v !== '' && v != null && arr.indexOf(v) === i; }); }
  function fillFilters() {
    var opt = function (vals, cur, all) { return '<option value="">' + all + '</option>' + vals.map(function (v) { return '<option' + (String(v) === cur ? ' selected' : '') + '>' + esc(v) + '</option>'; }).join(''); };
    var brands = uniq(list.map(function (s) { return s.brand; })).sort();
    var models = uniq(list.filter(function (s) { return !filt.brand || s.brand === filt.brand; }).map(function (s) { return s.model; })).sort();
    var years = uniq(list.filter(function (s) { return (!filt.brand || s.brand === filt.brand) && (!filt.model || s.model === filt.model); }).map(function (s) { return s.year; })).sort(function (a, b) { return b - a; });
    if (models.indexOf(filt.model) === -1) filt.model = ''; if (years.map(String).indexOf(filt.year) === -1) filt.year = '';
    $('#tco-f-brand').innerHTML = opt(brands, filt.brand, 'Toutes les marques');
    $('#tco-f-model').innerHTML = opt(models, filt.model, 'Tous les modèles');
    $('#tco-f-year').innerHTML = opt(years, filt.year, 'Toutes les années');
    $('#tco-f-mode').innerHTML = '<option value="">Tous les modes</option>' + Object.keys(MODES).map(function (k) { return '<option value="' + k + '"' + (filt.mode === k ? ' selected' : '') + '>' + MODES[k] + '</option>'; }).join('');
    $('#tco-f-q').value = filt.q;
  }
  function renderList() {
    var rows = filtered(), el = $('#tco-list');
    el.innerHTML = rows.length ? rows.map(function (s) {
      var r = calc(s), on = sel.has(s.id);
      return '<div class="tco-item' + (on ? ' on' : '') + '" data-id="' + s.id + '">' +
        '<label class="tco-pick"><input type="checkbox" ' + (on ? 'checked' : '') + '></label>' +
        '<div class="tco-item-main"><b>' + esc(s.label) + '</b><span>' + esc(s.brand + ' ' + s.model + (s.year ? ' · ' + s.year : '')) + ' · <em class="tco-mode tco-m-' + s.mode + '">' + MODE_SHORT[s.mode] + '</em></span></div>' +
        '<div class="tco-item-cost"><b>' + eur(r.perMonth) + '</b><span>/mois</span></div>' +
        '<div class="tco-item-act"><button type="button" data-a="edit" title="Modifier">✏️</button><button type="button" data-a="dup" title="Dupliquer">📄</button><button type="button" data-a="del" title="Supprimer">🗑️</button></div></div>';
    }).join('') : '<p class="tco-muted">' + (list.length ? 'Aucun scénario pour ces filtres.' : 'Aucun scénario enregistré. Ajoutez-en un pour commencer la comparaison.') + '</p>';
    $('#tco-count').textContent = rows.length + ' / ' + list.length;
  }

  // ----- Comparaison -----
  function renderCompare() {
    var chosen = list.filter(function (s) { return sel.has(s.id); }), box = $('#tco-result');
    if (!chosen.length) { box.innerHTML = '<p class="tco-muted">Cochez au moins un scénario (idéalement 2 à 4) pour les comparer.</p>'; if (chart) { chart.destroy(); chart = null; } $('#tco-chart-wrap').hidden = true; return; }
    var res = chosen.map(function (s) { return { s: s, r: calc(s) }; });
    var best = Math.min.apply(null, res.map(function (x) { return x.r.perMonth; }));
    var head = '<tr><th></th>' + res.map(function (x) { return '<th class="' + (x.r.perMonth === best && res.length > 1 ? 'tco-best' : '') + '"><b>' + esc(x.s.label) + '</b><small>' + esc(x.s.brand + ' ' + x.s.model + (x.s.year ? ' ' + x.s.year : '')) + '</small><em class="tco-mode tco-m-' + x.s.mode + '">' + MODE_SHORT[x.s.mode] + '</em></th>'; }).join('') + '</tr>';
    var row = function (lab, fn, cls) { return '<tr class="' + (cls || '') + '"><th>' + lab + '</th>' + res.map(function (x) { return '<td>' + fn(x) + '</td>'; }).join('') + '</tr>'; };
    var body = row('Coût mensuel', function (x) { return '<b>' + eur(x.r.perMonth) + '</b>' + (res.length > 1 && x.r.perMonth !== best ? '<small class="tco-bad">+' + eur(x.r.perMonth - best) + '</small>' : ''); }, 'tco-key') +
      row('Coût annuel', function (x) { return eur(x.r.perYear); }, 'tco-key') +
      row('Coût au km', function (x) { return x.r.perKm === null ? '—' : eur2(x.r.perKm); }) +
      row('Durée / km total', function (x) { return x.r.months + ' mois · ' + new Intl.NumberFormat('fr-FR').format(Math.round(x.r.km)) + ' km'; }) +
      row('Coût total sur la durée', function (x) { return eur(x.r.total); }, 'tco-key');
    var cats = '<tr class="tco-sep"><th colspan="' + (res.length + 1) + '">Détail par poste (coût mensuel)</th></tr>' +
      TCO.CATS.map(function (c) { return row('<i class="tco-dot" style="background:' + c.color + '"></i>' + c.label, function (x) { var v = x.r.cats[c.k]; return v ? eur(v / x.r.months) : '<span class="tco-muted">—</span>'; }); }).join('');
    box.innerHTML = '<div class="tco-table-wrap"><table class="tco-table"><thead>' + head + '</thead><tbody>' + body + cats + '</tbody></table></div>';
    drawChart(res);
  }
  function drawChart(res) {
    $('#tco-chart-wrap').hidden = false;
    if (!window.Chart) return;
    var cs = getComputedStyle(document.body), txt = cs.color || '#334155';
    var ds = TCO.CATS.map(function (c) {
      return { label: c.label, backgroundColor: c.color, data: res.map(function (x) { return Math.round(x.r.cats[c.k] / x.r.months * 100) / 100; }) };
    }).filter(function (d) { return d.data.some(function (v) { return v; }); });
    if (chart) chart.destroy();
    chart = new Chart($('#tco-chart'), {
      type: 'bar', data: { labels: res.map(function (x) { return x.s.label + ' (' + MODE_SHORT[x.s.mode] + ')'; }), datasets: ds },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: txt, boxWidth: 12 } }, tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ' : ' + eur2(c.parsed.x) + ' /mois'; }, footer: function (it) { return 'Total : ' + eur2(it.reduce(function (s, i) { return s + i.parsed.x; }, 0)) + ' /mois'; } } } },
        scales: { x: { stacked: true, ticks: { color: txt, callback: function (v) { return eur(v); } }, grid: { color: 'rgba(148,163,184,.25)' }, title: { display: true, text: 'Coût par mois', color: txt } }, y: { stacked: true, ticks: { color: txt }, grid: { display: false } } } }
    });
    $('#tco-chart-wrap').style.height = Math.max(200, 90 + res.length * 60 + 70) + 'px';
  }
  function refresh() { fillFilters(); renderList(); renderCompare(); store(FILTER_KEY, filt); store(SEL_KEY, Array.from(sel)); }

  // ----- Formulaire -----
  function fuelInfo(d) { return FUELS[d.fuel] || FUELS['Essence']; }
  function renderForm(s) {
    var d = s.data, fi = fuelInfo(d), box = $('#tco-form-fields'), html = '';
    GROUPS.forEach(function (g) {
      var fields = g.f.filter(function (f) { return f[3].indexOf(s.mode) !== -1 && (!f[5] || f[5](d)); });
      if (!fields.length) return;
      html += '<fieldset class="tco-fs"><legend>' + g.t + '</legend><div class="tco-grid">';
      fields.forEach(function (f) {
        var k = f[0], unit = f[2].replace(/__unit__/g, fi.unit), v = d[k];
        if (f[6] === 'check') { html += '<label class="tco-check tco-span"><input type="checkbox" data-k="' + k + '"' + (v ? ' checked' : '') + '> ' + esc(f[1]) + '</label>'; return; }
        html += '<div class="tco-field"><label>' + esc(f[1]) + (f[4] && f[6] !== 'estimate' ? ' <span class="tco-help" title="' + esc(f[4]) + '">ⓘ</span>' : '') + '</label><div class="tco-input"><input type="number" step="any" inputmode="decimal" data-k="' + k + '" value="' + (v === undefined || v === '' ? '' : esc(v)) + '"><span>' + esc(unit) + '</span>' +
          (f[6] === 'estimate' ? '<button type="button" class="tco-est" title="' + esc(f[4]) + '">Estimer</button>' : '') + '</div></div>';
      });
      html += '</div></fieldset>';
    });
    html += '<fieldset class="tco-fs"><legend>➕ Autres frais (réguliers ou ponctuels)</legend><div id="tco-custom">' +
      (d.custom || []).map(function (l, i) { return customRow(l, i); }).join('') + '</div><button type="button" class="btn btn-secondary btn-sm" id="tco-add-line">+ Ajouter une ligne</button></fieldset>';
    box.innerHTML = html;
    updatePreview();
  }
  function customRow(l, i) {
    return '<div class="tco-custom-row" data-i="' + i + '"><input class="form-control" data-c="label" placeholder="Libellé" value="' + esc(l.label || '') + '"><input class="form-control" data-c="amount" type="number" step="any" placeholder="Montant" value="' + esc(l.amount == null ? '' : l.amount) + '">' +
      '<select class="form-control" data-c="freq">' + [['unique', 'ponctuel'], ['mois', 'par mois'], ['an', 'par an']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.freq || 'unique') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select><button type="button" class="tco-x" title="Supprimer">✕</button></div>';
  }
  function updatePreview() {
    var s = editing; if (!s) return;
    var r = calc(s);
    $('#tco-preview').innerHTML = '<b>' + eur(r.perMonth) + '</b> /mois · ' + eur(r.perYear) + ' /an · ' + eur(r.total) + ' sur ' + r.months + ' mois';
  }
  function openForm(s) {
    editing = JSON.parse(JSON.stringify(s));
    $('#tco-m-title').textContent = editing.id ? 'Modifier le scénario' : 'Nouveau scénario';
    ['label', 'brand', 'model', 'year'].forEach(function (k) { $('#tco-' + k).value = editing[k] == null ? '' : editing[k]; });
    $('#tco-mode').innerHTML = Object.keys(MODES).map(function (k) { return '<option value="' + k + '"' + (editing.mode === k ? ' selected' : '') + '>' + MODES[k] + '</option>'; }).join('');
    $('#tco-fuel').innerHTML = Object.keys(FUELS).map(function (k) { return '<option' + (editing.data.fuel === k ? ' selected' : '') + '>' + k + '</option>'; }).join('');
    var sv = $('#tco-from-vehicle'); sv.innerHTML = '<option value="">— Pré-remplir depuis mes véhicules —</option>' + vehicles.map(function (v, i) { return '<option value="' + i + '">' + esc(v.name + ' (' + v.brand + ' ' + v.model + (v.year ? ' ' + v.year : '') + ')') + '</option>'; }).join('');
    renderForm(editing);
    $('#modal-tco').classList.add('show');
  }
  function newScenario(mode) {
    var d = Object.assign({ fuel: 'Essence', conso: FUELS.Essence.conso, energy_price: FUELS.Essence.price, repair_an: mode === 'occasion' ? 400 : 0, custom: [] }, DEFAULTS);
    if (LEASE.indexOf(mode) !== -1) { d.months = 36; d.km_incl_an = 10000; }
    return { id: null, label: '', mode: mode, brand: filt.brand || '', model: filt.model || '', year: filt.year ? +filt.year : '', data: d };
  }

  function bind() {
    $('#tco-add').addEventListener('click', function () { openForm(newScenario('neuf')); });
    ['brand', 'model', 'year', 'mode'].forEach(function (k) { $('#tco-f-' + k).addEventListener('change', function () { filt[k] = this.value; if (k === 'brand') { filt.model = ''; filt.year = ''; } if (k === 'model') filt.year = ''; refresh(); }); });
    $('#tco-f-q').addEventListener('input', function () { filt.q = this.value; renderList(); store(FILTER_KEY, filt); });
    $('#tco-sel-all').addEventListener('click', function () { filtered().forEach(function (s) { sel.add(s.id); }); refresh(); });
    $('#tco-sel-none').addEventListener('click', function () { sel.clear(); refresh(); });
    $('#tco-list').addEventListener('click', function (e) {
      var it = e.target.closest('.tco-item'); if (!it) return; var id = +it.dataset.id, s = list.filter(function (x) { return x.id === id; })[0]; if (!s) return;
      var a = e.target.closest('button'); a = a && a.dataset.a;
      if (a === 'edit') openForm(s);
      else if (a === 'dup') { var c = JSON.parse(JSON.stringify(s)); c.id = null; c.label = s.label + ' (copie)'; openForm(c); }
      else if (a === 'del') { if (confirm('Supprimer « ' + s.label + ' » ?')) call('DELETE', '&id=' + id).then(function () { sel.delete(id); return reload(); }).catch(function (er) { alert(er.message); }); }
      else if (e.target.closest('.tco-pick')) { if (e.target.checked) sel.add(id); else sel.delete(id); refresh(); }
    });
    var m = $('#modal-tco');
    var upd = function () { if (!editing) return; updatePreview(); };
    $('#tco-mode').addEventListener('change', function () {
      var was = LEASE.indexOf(editing.mode) !== -1, now = LEASE.indexOf(this.value) !== -1, d = editing.data;
      if (now && !was) { if (+d.months === 48) d.months = 36; if (!d.km_incl_an) d.km_incl_an = 10000; } else if (was && !now && +d.months === 36) d.months = 48;
      editing.mode = this.value; renderForm(editing);
    });
    $('#tco-fuel').addEventListener('change', function () {
      var old = fuelInfo(editing.data), nw = FUELS[this.value] || FUELS.Essence;
      if (!editing.data.conso || +editing.data.conso === old.conso) editing.data.conso = nw.conso;
      if (!editing.data.energy_price || +editing.data.energy_price === old.price) editing.data.energy_price = nw.price;
      editing.data.fuel = this.value; renderForm(editing);
    });
    ['label', 'brand', 'model', 'year'].forEach(function (k) { $('#tco-' + k).addEventListener('input', function () { editing[k] = this.value; }); });
    $('#tco-from-vehicle').addEventListener('change', function () {
      var v = vehicles[this.value]; if (!v) return;
      editing.brand = v.brand; editing.model = v.model; editing.year = v.year || ''; if (!editing.label) editing.label = v.name;
      ['brand', 'model', 'year', 'label'].forEach(function (k) { $('#tco-' + k).value = editing[k]; });
      var f = FUELS[v.fuel_type] ? v.fuel_type : 'Autre'; editing.data.fuel = f; if (+v.consumption) editing.data.conso = +v.consumption; else editing.data.conso = FUELS[f].conso; editing.data.energy_price = FUELS[f].price;
      if (+v.purchase_price && !editing.data.price) editing.data.price = +v.purchase_price;
      $('#tco-fuel').value = f; renderForm(editing);
    });
    $('#tco-form-fields').addEventListener('input', function (e) {
      var t = e.target; if (t.dataset.k) { editing.data[t.dataset.k] = t.value === '' ? '' : parseFloat(t.value); upd(); }
      else if (t.dataset.c) { var row = t.closest('.tco-custom-row'), l = editing.data.custom[+row.dataset.i]; l[t.dataset.c] = t.dataset.c === 'amount' ? (t.value === '' ? '' : parseFloat(t.value)) : t.value; upd(); }
    });
    $('#tco-form-fields').addEventListener('change', function (e) {
      var t = e.target;
      if (t.dataset.k && t.type === 'checkbox') { editing.data[t.dataset.k] = t.checked ? 1 : 0; renderForm(editing); }
      else if (t.dataset.c === 'freq') { editing.data.custom[+t.closest('.tco-custom-row').dataset.i].freq = t.value; upd(); }
    });
    $('#tco-form-fields').addEventListener('click', function (e) {
      if (e.target.id === 'tco-add-line') { editing.data.custom = editing.data.custom || []; editing.data.custom.push({ label: '', amount: '', freq: 'unique' }); renderForm(editing); }
      else if (e.target.classList.contains('tco-x')) { editing.data.custom.splice(+e.target.closest('.tco-custom-row').dataset.i, 1); renderForm(editing); }
      else if (e.target.classList.contains('tco-est')) {
        var d = editing.data; if (!(+d.price)) { alert('Renseignez d\'abord le prix d\'achat.'); return; }
        d.resale = TCO.estimateResale(d.price, editing.mode, (+d.months || 48) / 12 + (editing.mode === 'occasion' ? 0 : 0), d.age_start, d.km_an); renderForm(editing);
      }
    });
    $('#tco-save').addEventListener('click', async function () {
      if (!editing.brand.trim() || !editing.model.trim()) { alert('Indiquez la marque et le modèle.'); return; }
      var body = { label: editing.label, mode: editing.mode, brand: editing.brand, model: editing.model, year: editing.year, data: editing.data };
      this.disabled = true;
      try { var id = editing.id; if (id) await call('PUT', '&id=' + id, body); else { var r = await call('POST', '', body); id = r.id; } sel.add(id); m.classList.remove('show'); await reload(); }
      catch (er) { alert(er.message); } finally { this.disabled = false; }
    });
    $('#tco-cancel').addEventListener('click', function () { m.classList.remove('show'); });
    $('#tco-close').addEventListener('click', function () { m.classList.remove('show'); });
    var dl = $('#tco-brands'); dl.innerHTML = BRANDS.map(function (b) { return '<option value="' + esc(b) + '">'; }).join('');
  }

  async function reload() { list = await call('GET'); var ids = new Set(list.map(function (s) { return s.id; })); sel.forEach(function (i) { if (!ids.has(i)) sel.delete(i); }); refresh(); }
  window.loadCompare = async function () {
    try {
      if (!ready) { bind(); ready = true; }
      try { vehicles = await (await fetch('/modules/garage/api.php?action=vehicles')).json().then(function (j) { return j.ok ? j.data : []; }); } catch (e) { vehicles = []; }
      await reload();
    } catch (e) { $('#tco-list').innerHTML = '<p class="tco-muted">' + esc(e.message) + '</p>'; }
  };
  // Ouverture de l'onglet : branchement direct (ne dépend pas de la version de garage.js en cache du navigateur)
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.nav-link[data-page="compare"]');
    if (b && !window.__tcoLoading) { window.__tcoLoading = true; window.loadCompare().finally(function () { window.__tcoLoading = false; }); }
  });
})();
