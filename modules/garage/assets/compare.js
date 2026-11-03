// Comparateur de coût : bibliothèque de scénarios (marque / modèle / année), formulaire de saisie, comparaison en tableau + graphique.
// Les calculs sont dans tco-calc.js ; l'API est modules/garage/api.php?action=tco.
(function () {
  'use strict';
  var TCO = window.TCO, SEL_KEY = 'garage-tco-sel', FILTER_KEY = 'garage-tco-filter';
  var BRANDS = ['Alfa Romeo', 'Audi', 'BMW', 'BYD', 'Citroën', 'Cupra', 'Dacia', 'DS', 'Fiat', 'Ford', 'Honda', 'Hyundai', 'Jeep', 'Kia', 'Lexus', 'Mazda', 'Mercedes', 'MG', 'Mini', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Renault', 'Seat', 'Skoda', 'Smart', 'Suzuki', 'Tesla', 'Toyota', 'Volkswagen', 'Volvo'];
  var FUELS = { 'Essence': { price: 1.85, conso: 6.5, unit: 'L' }, 'Diesel': { price: 1.75, conso: 5.5, unit: 'L' }, 'Hybride': { price: 1.85, conso: 4.8, unit: 'L' },
                'Hybride rechargeable': { price: 1.85, conso: 6, unit: 'L' }, 'Électrique': { price: 0.25, conso: 17, unit: 'kWh' }, 'GPL': { price: 0.85, conso: 9, unit: 'L' }, 'Autre': { price: 1.8, conso: 6, unit: 'L' } };
  var MODELS = {
    'Alfa Romeo': ['Giulia', 'Giulietta', 'MiTo', 'Stelvio', 'Tonale', 'Junior'], 'Audi': ['A1', 'A3', 'A4', 'A5', 'A6', 'Q2', 'Q3', 'Q5', 'Q7', 'Q4 e-tron', 'e-tron GT', 'TT'],
    'BMW': ['Série 1', 'Série 2', 'Série 3', 'Série 4', 'Série 5', 'X1', 'X2', 'X3', 'X5', 'i4', 'iX1', 'iX3'], 'BYD': ['Dolphin', 'Atto 3', 'Seal', 'Seal U', 'Han', 'Tang'],
    'Citroën': ['C1', 'C3', 'C3 Aircross', 'C4', 'C4 X', 'C5 Aircross', 'C5 X', 'Berlingo', 'ë-C3', 'ë-C4', 'Ami'], 'Cupra': ['Born', 'Formentor', 'Leon', 'Ateca', 'Tavascan'],
    'Dacia': ['Sandero', 'Sandero Stepway', 'Logan', 'Duster', 'Jogger', 'Spring', 'Bigster'], 'DS': ['DS 3', 'DS 4', 'DS 7', 'DS 9'],
    'Fiat': ['500', '500e', '500X', 'Panda', 'Tipo', 'Doblò', 'Ducato', '600'], 'Ford': ['Fiesta', 'Focus', 'Puma', 'Kuga', 'Mustang Mach-E', 'Mondeo', 'Ranger', 'Transit', 'Explorer'],
    'Honda': ['Jazz', 'Civic', 'HR-V', 'CR-V', 'ZR-V', 'e:Ny1'], 'Hyundai': ['i10', 'i20', 'i30', 'Bayon', 'Kona', 'Tucson', 'Santa Fe', 'Ioniq 5', 'Ioniq 6'],
    'Jeep': ['Avenger', 'Renegade', 'Compass', 'Wrangler', 'Grand Cherokee'], 'Kia': ['Picanto', 'Rio', 'Ceed', 'Stonic', 'Niro', 'Sportage', 'Sorento', 'EV3', 'EV6', 'EV9'],
    'Lexus': ['CT', 'UX', 'NX', 'RX', 'ES', 'RZ'], 'Mazda': ['Mazda2', 'Mazda3', 'CX-30', 'CX-5', 'CX-60', 'MX-5', 'MX-30'],
    'Mercedes': ['Classe A', 'Classe B', 'Classe C', 'Classe E', 'CLA', 'GLA', 'GLB', 'GLC', 'EQA', 'EQB', 'Vito'], 'MG': ['MG3', 'MG4', 'MG5', 'ZS', 'HS', 'Marvel R'],
    'Mini': ['Cooper', 'Countryman', 'Clubman', 'Cabrio', 'Aceman'], 'Mitsubishi': ['Space Star', 'ASX', 'Eclipse Cross', 'Outlander', 'L200'],
    'Nissan': ['Micra', 'Juke', 'Qashqai', 'X-Trail', 'Leaf', 'Ariya', 'Townstar'], 'Opel': ['Corsa', 'Astra', 'Mokka', 'Crossland', 'Grandland', 'Combo', 'Frontera'],
    'Peugeot': ['108', '208', '2008', '308', '3008', '408', '508', '5008', 'Rifter', 'Partner', 'e-208', 'e-2008'],
    'Renault': ['Twingo', 'Clio', 'Captur', 'Mégane', 'Mégane E-Tech', 'Arkana', 'Austral', 'Scénic', 'Kadjar', 'Kangoo', 'Zoé', 'Master', 'Symbioz', 'Rafale', 'R5 E-Tech', 'R4 E-Tech'],
    'Seat': ['Ibiza', 'Arona', 'Leon', 'Ateca', 'Tarraco'], 'Skoda': ['Fabia', 'Scala', 'Octavia', 'Superb', 'Kamiq', 'Karoq', 'Kodiaq', 'Enyaq'],
    'Smart': ['#1', '#3', 'ForTwo', 'ForFour'], 'Suzuki': ['Ignis', 'Swift', 'Vitara', 'S-Cross', 'Jimny', 'Across'], 'Tesla': ['Model 3', 'Model Y', 'Model S', 'Model X'],
    'Toyota': ['Aygo X', 'Yaris', 'Yaris Cross', 'Corolla', 'C-HR', 'RAV4', 'Prius', 'Proace', 'Land Cruiser', 'bZ4X'],
    'Volkswagen': ['Polo', 'Golf', 'T-Cross', 'T-Roc', 'Tiguan', 'Passat', 'Touran', 'ID.3', 'ID.4', 'ID.5', 'ID.7', 'Caddy', 'Transporter'], 'Volvo': ['XC40', 'XC60', 'XC90', 'V60', 'V90', 'S60', 'EX30', 'EX90']
  };
  var OTHER = '__autre__';
  var MODES = { neuf: 'Achat neuf', occasion: 'Achat d\'occasion', lld: 'Location longue durée (LLD)', leasing: 'LOA / leasing (crédit-bail)' };
  var MODE_SHORT = { neuf: 'Neuf', occasion: 'Occasion', lld: 'LLD', leasing: 'LOA / leasing' };
  var BUY = ['neuf', 'occasion'], LEASE = ['lld', 'leasing'], ALL = ['neuf', 'occasion', 'lld', 'leasing'];

  // Champs du formulaire : [clé, libellé, unité, modes concernés, aide, condition]
  var NEUF = ['neuf'], vis = function (d, m) { return TCO.vis(d, m); };
  var GROUPS = [
    { t: '🚗 Utilisation', f: [
      ['km_an', 'Kilométrage annuel prévisionnel', 'km/an', ALL, ''],
      ['months', 'Durée du contrat de location', '', LEASE, 'Ex. 24, 36, 48 mois', null, 'dur'],
      ['_horizon', '', '', BUY, '', null, 'horizon'],
      ['age_start', 'Âge du véhicule à l\'achat', 'ans', ['occasion'], 'Sert au contrôle technique (1er à 4 ans, puis tous les 2 ans)'] ] },
    { t: '💶 Achat', f: [
      ['price', 'Prix d\'achat TTC', '€', BUY, ''], ['resale', 'Valeur de revente estimée à la fin', '€', BUY, 'Laisser vide : estimation automatique selon la durée comparée. Le bouton « Estimer » la remplit pour la durée actuelle.', null, 'estimate'],
      ['carte_grise', 'Carte grise', '€', BUY, ''], ['malus', 'Malus écologique / taxes à l\'achat', '€', NEUF, ''], ['bonus', 'Autres aides à l\'achat (à déduire)', '€', NEUF, ''],
      ['cee', 'Prime CEE (véhicule électrique neuf)', '', NEUF, 'Prime « Coup de pouce » des certificats d\'économies d\'énergie : le montant dépend des revenus du ménage (barème indicatif, « jusqu\'à »).', function (d) { return d.fuel === 'Électrique'; }, 'check'],
      ['revenu_part', 'Revenu fiscal de référence du ménage par part', '€', NEUF, '', function (d) { return d.fuel === 'Électrique' && d.cee; }],
      ['_cee', '', '', NEUF, '', function (d) { return d.fuel === 'Électrique' && d.cee; }, 'cee'],
      ['cee_europe', 'Batterie fabriquée en Europe (bonus supplémentaire)', '', NEUF, '', function (d) { return d.fuel === 'Électrique' && d.cee; }, 'check'],
      ['cee_europe_montant', 'Bonus batterie européenne', '€', NEUF, 'Environ 1 200 € à 2 000 €', function (d) { return d.fuel === 'Électrique' && d.cee && d.cee_europe; }],
      ['frais_divers', 'Autres frais d\'achat (livraison, garantie…)', '€', BUY, ''],
      ['credit', 'Achat financé par un crédit', '', BUY, '', null, 'check'],
      ['apport', 'Apport', '€', BUY, '', function (d) { return d.credit; }], ['credit_rate', 'Taux du crédit (TAEG)', '%', BUY, '', function (d) { return d.credit; }],
      ['credit_months', 'Durée du crédit', 'mois', BUY, '', function (d) { return d.credit; }], ['_pay', '', '', BUY, '', function (d) { return d.credit; }, 'pay'] ] },
    { t: '📄 Location (LLD / LOA)', f: [
      ['rent', 'Loyer mensuel', '€/mois', LEASE, ''], ['apport', '1er loyer majoré / apport', '€', LEASE, ''], ['frais_dossier', 'Frais de dossier', '€', LEASE, ''],
      ['depot_garantie', 'Dépôt de garantie (rendu en fin de contrat)', '€', LEASE, ''],
      ['km_incl_an', 'Forfait kilométrique max', 'km/an', LEASE, '0 = illimité'], ['km_excess', 'Coût du km supplémentaire', '€/km', LEASE, ''],
      ['remise_etat', 'Remise en état / frais de restitution estimés', '€', LEASE, ''],
      ['lease_pack', 'Entretien du contrat', '', LEASE, '', null, 'pack'],
      ['pack_mois', 'Coût mensuel du pack entretien', '€/mois', LEASE, 'À renseigner s\'il est facturé en plus du loyer (0 s\'il est déjà dans le loyer)', function (d, m) { return vis(d, m).pack !== 'sec'; }],
      ['_reve', '', '', LEASE, '', function (d, m) { return vis(d, m).pack !== 'sec'; }, 'reveal-ent'],
      ['ass_incl', 'Assurance incluse dans le loyer', '', LEASE, '', null, 'check'],
      ['_reva', '', '', LEASE, '', function (d) { return d.ass_incl; }, 'reveal-ass'],
      ['levee', 'Levée de l\'option d\'achat en fin de contrat', '', ['leasing'], '', null, 'check'],
      ['option_achat', 'Prix de l\'option d\'achat', '€', ['leasing'], '', function (d) { return d.levee; }],
      ['resale', 'Valeur de revente estimée après levée', '€', ['leasing'], '', function (d) { return d.levee; }] ] },
    { t: '🛡️ Garanties optionnelles', f: [
      ['garantie', 'Garantie mécanique', '', LEASE, 'Couvre : pannes mécaniques, électriques et électroniques ; assistance dépannage / remorquage ; prêt d\'un véhicule jusqu\'à 90 jours (km illimité, jusqu\'à 7 ans).', null, 'check'],
      ['garantie_mois', 'Coût mensuel de la garantie mécanique', '€/mois', LEASE, '', function (d) { return d.garantie; }],
      ['protection', 'Protection pécuniaire / perte financière', '', LEASE, 'En cas de destruction ou de vol : prise en charge du solde du financement restant dû et remboursement de l\'apport initial.', null, 'check'],
      ['protection_mois', 'Coût mensuel de la protection pécuniaire', '€/mois', LEASE, '', function (d) { return d.protection; }] ] },
    { t: '⛽ Coûts d\'usage', f: [
      ['conso', 'Consommation', '__unit__/100 km', ALL, ''], ['energy_price', 'Prix de l\'énergie', '€/__unit__', ALL, ''],
      ['conso_elec', 'Consommation électrique', 'kWh/100 km', ALL, 'Hybride rechargeable : 2 coûts d\'énergie (essence ci-dessus + électricité)', function (d) { return d.fuel === 'Hybride rechargeable'; }],
      ['price_elec', 'Prix de l\'électricité', '€/kWh', ALL, '', function (d) { return d.fuel === 'Hybride rechargeable'; }],
      ['part_elec', 'Part des km parcourus en électrique', '%', ALL, 'Dépend de la recharge et des trajets (souvent 30 à 70 %)', function (d) { return d.fuel === 'Hybride rechargeable'; }],
      ['assurance_an', 'Assurance (prime tous risques / tiers)', '', ALL, '', function (d, m) { return vis(d, m).ass; }, 'ass'],
      ['entretien_an', 'Entretien courant : révisions, vidanges', '€/an', ALL, '', function (d, m) { return vis(d, m).rev; }],
      ['usure_an', 'Pièces d\'usure : freins, amortisseurs…', '€/an', ALL, 'Lissez le coût sur les années : ex. 600 € de freins tous les 3 ans = 200 €/an', function (d, m) { return vis(d, m).usure; }],
      ['pneus_mode', 'Pneumatiques', '', ALL, '', function (d, m) { return vis(d, m).pneus; }, 'tyres'],
      ['pneus_an', 'Budget pneus', '€/an', ALL, '', function (d, m) { return vis(d, m).pneus && d.pneus_mode === 'budget'; }],
      ['pneus_set', 'Jeu de pneus été (ou 4 saisons)', '€', ALL, '', function (d, m) { return vis(d, m).pneus && d.pneus_mode !== 'budget'; }], ['pneus_km', 'Durée de vie du jeu', 'km', ALL, '', function (d, m) { return vis(d, m).pneus && d.pneus_mode !== 'budget'; }],
      ['pneus_hiver', 'Jeu de pneus hiver en plus', '', ALL, '', function (d, m) { return vis(d, m).pneus && d.pneus_mode !== 'budget'; }, 'check'],
      ['pneus_hiver_set', 'Jeu de pneus hiver', '€', ALL, '', function (d, m) { return vis(d, m).pneus && d.pneus_mode !== 'budget' && d.pneus_hiver; }], ['pneus_hiver_km', 'Durée de vie du jeu hiver', 'km', ALL, 'Les pneus hiver servent une partie de l\'année : comptez davantage de km que pour les pneus été', function (d, m) { return vis(d, m).pneus && d.pneus_mode !== 'budget' && d.pneus_hiver; }],
      ['ct_cost', 'Contrôle technique', '€/passage', ALL, ''], ['taxes_an', 'Taxes annuelles (vignette, taxe au poids…)', '€/an', ALL, ''],
      ['parking_mois', 'Stationnement / garage', '€/mois', ALL, ''], ['divers_an', 'Péages, lavage, accessoires…', '€/an', ALL, ''],
      ['repair_an', 'Provision pour réparations imprévues', '€/an', ALL, 'Plus élevée pour un véhicule d\'occasion ou hors garantie'] ] }
  ];
  var DEFAULTS = { km_an: 12000, months: 48, age_start: 3, part_elec: 50, conso_elec: 16, price_elec: 0.25, cee_europe_montant: 1200, lease_pack: 'sec', credit_months: 48, credit_rate: 5, pneus_mode: 'sets', pneus_km: 40000, pneus_set: 500, pneus_hiver_km: 80000, pneus_hiver_set: 450, ass_unit: 'an', ct_cost: 80, assurance_an: 650, entretien_an: 300, divers_an: 100, km_excess: 0.1 };

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

  var isLease = function (m) { return LEASE.indexOf(m) !== -1; };
  // Achat : pas de durée saisie, on prend celle de la location comparée (horizon), 48 mois sinon
  var calc = function (s, h, over) { var d = Object.assign({}, s.data || {}, { mode: s.mode }, over || {}); if (!isLease(s.mode)) d.months = h || 48; return TCO.compute(d); }; // le mode est porté par le scénario, pas par ses données

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
    var lz = chosen.filter(function (s) { return isLease(s.mode); }), hz = lz.length ? Math.round(+lz[0].data.months) || 36 : 0;
    var res = chosen.map(function (s) { return { s: s, r: calc(s, hz) }; }); res.hz = hz;
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
    box.innerHTML = (hz ? '<p class="tco-muted">Les achats sont comparés sur ' + hz + ' mois, la durée de la location sélectionnée.</p>' : '') + verdict(res) + '<div class="tco-table-wrap"><table class="tco-table"><thead>' + head + '</thead><tbody>' + body + cats + '</tbody></table></div>' + profiles(res);
    drawChart(res);
  }
  // Verdict : solution la plus avantageuse (coût mensuel lissé et coût global)
  function verdict(res) {
    if (res.length < 2) return '<div class="tco-verdict">Ajoutez un second scénario pour savoir quelle solution est la plus avantageuse.</div>';
    var byM = res.slice().sort(function (a, b) { return a.r.perMonth - b.r.perMonth; }), w = byM[0], n2 = byM[1], worst = byM[byM.length - 1];
    var byT = res.slice().sort(function (a, b) { return a.r.total - b.r.total; })[0];
    var nm = function (x) { return '<b>' + esc(x.s.label) + '</b> (' + MODE_SHORT[x.s.mode] + ')'; };
    var h = '<div class="tco-verdict tco-verdict-ok">✅ <b>Solution la plus avantageuse : </b>' + nm(w) + ' — <b>' + eur(w.r.perMonth) + ' /mois</b> tout compris, ' + eur(w.r.total) + ' sur ' + w.r.months + ' mois.<br>' +
      'Économie de <b>' + eur(n2.r.perMonth - w.r.perMonth) + ' /mois</b> (' + eur((n2.r.perMonth - w.r.perMonth) * 12) + ' /an) face à ' + nm(n2) + (res.length > 2 ? ', et de ' + eur(worst.r.perMonth - w.r.perMonth) + ' /mois face à la plus chère' : '') + '.';
    if (byT !== w) h += '<br><small>⚠️ Sur la durée totale, ' + nm(byT) + ' coûte moins cher au global (' + eur(byT.r.total) + ') car les durées simulées diffèrent.</small>';
    return h + '</div>';
  }
  // Selon le profil d'usage : meilleure solution pour différents kilométrages annuels
  function profiles(res) {
    if (res.length < 2) return '';
    var kms = [5000, 10000, 15000, 20000, 30000], cur = res.map(function (x) { return +x.s.data.km_an || 0; });
    var rows = kms.map(function (km) {
      var rr = res.map(function (x) { return calc(x.s, res.hz, { km_an: km }).perMonth; }), m = Math.min.apply(null, rr);
      return '<tr><th>' + new Intl.NumberFormat('fr-FR').format(km) + ' km/an</th>' + rr.map(function (v) { return '<td class="' + (v === m ? 'tco-best-cell' : '') + '">' + eur(v) + (v === m ? ' 🏆' : '') + '</td>'; }).join('') + '</tr>';
    }).join('');
    return '<h4 class="tco-h4">Quelle solution selon votre profil d\'usage ?</h4><div class="tco-table-wrap"><table class="tco-table"><thead><tr><th>Coût mensuel si vous roulez…</th>' + res.map(function (x) { return '<th>' + esc(x.s.label) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows + '</tbody></table></div><p class="tco-muted">Mêmes hypothèses que vos scénarios, seul le kilométrage annuel change (les kilomètres au-delà du forfait d\'une location sont facturés). Valeur de revente inchangée.</p>';
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
  function calcDraft(d) { return calc({ mode: editing.mode, data: d }); }
  function renderForm(s) {
    var d = s.data, fi = fuelInfo(d), box = $('#tco-form-fields'), html = '';
    GROUPS.forEach(function (g) {
      var fields = g.f.filter(function (f) { return f[3].indexOf(s.mode) !== -1 && (!f[5] || f[5](d, s.mode)); });
      if (!fields.length) return;
      html += '<fieldset class="tco-fs"><legend>' + g.t + '</legend><div class="tco-grid">';
      fields.forEach(function (f) {
        var k = f[0], unit = f[2].replace(/__unit__/g, fi.unit), v = d[k];
        if (f[6] === 'horizon') { html += '<p class="tco-note tco-span">⏱️ Pas de durée à saisir pour un achat : elle est alignée sur celle de la location comparée (48 mois s\'il n\'y en a pas).</p>'; return; }
        if (f[6] === 'cee') { var dc = TCO.decile(d.revenu_part), mg = TCO.menage(dc); html += '<p class="tco-note tco-span">🏷️ Décile ' + dc + ' · ' + esc(mg.label) + ' : prime CEE jusqu\'à <b>' + eur(mg.max) + '</b>' + (d.cee_europe ? ' + bonus Europe ' + eur(d.cee_europe_montant || 0) : '') + ' (déduits du coût).<br><small>Plafonds de revenu par part : D1 1 970 € · D2 7 640 € · D3 11 250 € · D4 14 130 € · D5 16 880 € · D6 19 600 € · D7 22 770 € · D8 27 310 € · D9 35 880 €. Ménages en précarité énergétique : jusqu\'à 5 700 € ; modestes : 4 700 € ; autres : 3 500 €.</small></p>'; return; }
        if (f[6] === 'pack') { var pk = vis(d, s.mode).pack; html += '<div class="tco-field tco-span"><label>' + esc(f[1]) + '</label><div class="tco-input"><select class="form-control" data-k="lease_pack"><option value="sec"' + (pk === 'sec' ? ' selected' : '') + '>Contrat « sec » (sans pack entretien)</option><option value="pack"' + (pk === 'pack' ? ' selected' : '') + '>Avec « Pack entretien / révision »</option><option value="integral"' + (pk === 'integral' ? ' selected' : '') + '>« Intégral / tout compris »</option></select></div></div>' +
          '<p class="tco-note tco-span">' + ({ sec: 'Contrat sec : révisions, freinage, suspension, essuie-glaces / ampoules / batterie et pneus sont à votre charge.', pack: 'Pack entretien (payant) : révisions incluses ; freinage et suspension généralement inclus ; essuie-glaces, ampoules, batterie souvent inclus ; pneus en option payante (restent comptés ici).', integral: 'Intégral : révisions, freinage, suspension, essuie-glaces / ampoules / batterie et pneus (selon quota) inclus.' })[pk] + '</p>'; return; }
        if (f[6] === 'reveal-ent' || f[6] === 'reveal-ass') { var ek = f[6] === 'reveal-ent' ? 'ent_show' : 'ass_show'; html += '<p class="tco-note tco-span">' + (f[6] === 'reveal-ent' ? 'Entretien' : 'Assurance') + ' pris en charge par le contrat : champs masqués. <a href="#" data-reveal="' + ek + '">' + (d[ek] ? 'Masquer à nouveau' : 'Afficher quand même') + '</a>' + (d[ek] ? ' <small>(les montants saisis sont comptés en supplément)</small>' : '') + '</p>'; return; }
        if (f[6] === 'pay') { var cr = calcDraft(d); html += '<p class="tco-note tco-span">💳 Mensualité estimée du crédit : <b>' + eur2(cr.payment) + '</b> /mois (hors assurance emprunteur)</p>'; return; }
        if (f[6] === 'dur') { var yr = d.dur_unit === 'an'; html += '<div class="tco-field"><label>' + esc(f[1]) + '</label><div class="tco-input"><input type="number" step="any" inputmode="decimal" data-dur="1" value="' + (v === undefined || v === '' ? '' : esc(yr ? Math.round(v / 12 * 100) / 100 : v)) + '"><select class="form-control" data-k="dur_unit"><option value="mois"' + (yr ? '' : ' selected') + '>mois</option><option value="an"' + (yr ? ' selected' : '') + '>années</option></select></div></div>'; return; }
        if (f[6] === 'ass') { html += '<div class="tco-field"><label>' + esc(f[1]) + '</label><div class="tco-input"><input type="number" step="any" inputmode="decimal" data-k="assurance_an" value="' + (v === undefined || v === '' ? '' : esc(v)) + '"><select class="form-control" data-k="ass_unit"><option value="an"' + (d.ass_unit === 'mois' ? '' : ' selected') + '>€/an</option><option value="mois"' + (d.ass_unit === 'mois' ? ' selected' : '') + '>€/mois</option></select></div></div>'; return; }
        if (f[6] === 'tyres') { html += '<div class="tco-field"><label>' + esc(f[1]) + '</label><div class="tco-input"><select class="form-control" data-k="pneus_mode"><option value="sets"' + (d.pneus_mode === 'budget' ? '' : ' selected') + '>Au remplacement (prix d\'un jeu, durée de vie)</option><option value="budget"' + (d.pneus_mode === 'budget' ? ' selected' : '') + '>Budget annuel</option></select></div></div>'; return; }
        if (f[6] === 'check') { html += '<label class="tco-check tco-span"><input type="checkbox" data-k="' + k + '"' + (v ? ' checked' : '') + '> ' + esc(f[1]) + '</label>' + (f[4] && v ? '<p class="tco-note tco-span">' + esc(f[4]) + '</p>' : ''); return; }
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
  // Listes prédéfinies marque / modèle / année (+ « Autre » pour une saisie libre)
  function fillVehicleSelects() {
    var e = editing, brands = Object.keys(MODELS), bo = !!e._bo || (!!e.brand && brands.indexOf(e.brand) === -1);
    var models = (!bo && MODELS[e.brand]) || [], mo = !!e._mo || bo || (!!e.model && models.indexOf(e.model) === -1);
    var o = function (v, lab, sel) { return '<option value="' + esc(v) + '"' + (sel ? ' selected' : '') + '>' + esc(lab) + '</option>'; };
    $('#tco-brand').innerHTML = o('', '— Choisir —', !bo && !e.brand) + brands.map(function (b) { return o(b, b, !bo && b === e.brand); }).join('') + o(OTHER, 'Autre (saisie libre)…', bo);
    $('#tco-brand-o').hidden = !bo; if (bo) $('#tco-brand-o').value = e.brand || '';
    $('#tco-model').innerHTML = o('', '— Choisir —', !mo && !e.model) + models.map(function (m) { return o(m, m, !mo && m === e.model); }).join('') + o(OTHER, 'Autre (saisie libre)…', mo);
    $('#tco-model').disabled = !e.brand && !bo;
    $('#tco-model-o').hidden = !mo; if (mo) $('#tco-model-o').value = e.model || '';
    var y0 = new Date().getFullYear() + 1, ys = '<option value="">—</option>';
    for (var y = y0; y >= 1990; y--) ys += '<option' + (String(e.year) === String(y) ? ' selected' : '') + '>' + y + '</option>';
    $('#tco-year').innerHTML = ys;
  }
  function openForm(s) {
    editing = JSON.parse(JSON.stringify(s)); editing._bo = editing._mo = false;
    $('#tco-m-title').textContent = editing.id ? 'Modifier le scénario' : 'Nouveau scénario';
    $('#tco-label').value = editing.label || ''; fillVehicleSelects();
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
    $('#tco-label').addEventListener('input', function () { editing.label = this.value; });
    $('#tco-brand').addEventListener('change', function () { editing._bo = this.value === OTHER; editing._mo = editing._bo; editing.brand = editing._bo ? '' : this.value; editing.model = ''; fillVehicleSelects(); });
    $('#tco-model').addEventListener('change', function () { editing._mo = this.value === OTHER; editing.model = editing._mo ? '' : this.value; fillVehicleSelects(); if (editing._mo) $('#tco-model-o').focus(); });
    $('#tco-brand-o').addEventListener('input', function () { editing.brand = this.value; });
    $('#tco-model-o').addEventListener('input', function () { editing.model = this.value; });
    $('#tco-year').addEventListener('change', function () { editing.year = this.value; });
    $('#tco-from-vehicle').addEventListener('change', function () {
      var v = vehicles[this.value]; if (!v) return;
      editing._bo = editing._mo = false; editing.brand = v.brand; editing.model = v.model; editing.year = v.year || ''; if (!editing.label) editing.label = v.name;
      $('#tco-label').value = editing.label; fillVehicleSelects();
      var f = FUELS[v.fuel_type] ? v.fuel_type : 'Autre'; editing.data.fuel = f; if (+v.consumption) editing.data.conso = +v.consumption; else editing.data.conso = FUELS[f].conso; editing.data.energy_price = FUELS[f].price;
      if (+v.purchase_price && !editing.data.price) editing.data.price = +v.purchase_price;
      $('#tco-fuel').value = f; renderForm(editing);
    });
    $('#tco-form-fields').addEventListener('input', function (e) {
      var t = e.target; if (t.dataset.dur) { editing.data.months = t.value === '' ? '' : Math.round(parseFloat(t.value) * (editing.data.dur_unit === 'an' ? 12 : 1)); upd(); }
      else if (t.dataset.k && t.tagName === 'SELECT') { return; }
      else if (t.dataset.k) { editing.data[t.dataset.k] = t.value === '' ? '' : parseFloat(t.value); upd(); }
      else if (t.dataset.c) { var row = t.closest('.tco-custom-row'), l = editing.data.custom[+row.dataset.i]; l[t.dataset.c] = t.dataset.c === 'amount' ? (t.value === '' ? '' : parseFloat(t.value)) : t.value; upd(); }
    });
    $('#tco-form-fields').addEventListener('change', function (e) {
      var t = e.target;
      if (t.dataset.k && t.tagName === 'SELECT') { editing.data[t.dataset.k] = t.value; renderForm(editing); }
      else if (t.dataset.k && t.type === 'checkbox') { editing.data[t.dataset.k] = t.checked ? 1 : 0; renderForm(editing); }
      else if (t.dataset.c === 'freq') { editing.data.custom[+t.closest('.tco-custom-row').dataset.i].freq = t.value; upd(); }
    });
    $('#tco-form-fields').addEventListener('click', function (e) {
      if (e.target.dataset && e.target.dataset.reveal) { e.preventDefault(); editing.data[e.target.dataset.reveal] = editing.data[e.target.dataset.reveal] ? 0 : 1; renderForm(editing); }
      else if (e.target.id === 'tco-add-line') { editing.data.custom = editing.data.custom || []; editing.data.custom.push({ label: '', amount: '', freq: 'unique' }); renderForm(editing); }
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
