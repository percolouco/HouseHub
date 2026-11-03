// Comparateur de coût de possession (neuf / occasion / LLD / leasing) : calcul pur, sans accès au DOM.
// compute(d) -> { months, years, km, total, perMonth, perYear, perKm, cats: { clé: montant } }
(function (root) {
  'use strict';
  var n = function (v) { var x = parseFloat(String(v === undefined || v === null ? '' : v).replace(',', '.')); return isFinite(x) ? x : 0; };

  // Catégories communes à tous les modes (ordre d'affichage + libellés)
  var CATS = [
    { k: 'vehicule', label: 'Véhicule (dépréciation / loyers)', color: '#3b82f6' },
    { k: 'acquisition', label: 'Frais d\'acquisition / dossier', color: '#8b5cf6' },
    { k: 'financement', label: 'Financement & capital immobilisé', color: '#ec4899' },
    { k: 'energie', label: 'Carburant / énergie', color: '#f59e0b' },
    { k: 'assurance', label: 'Assurance', color: '#10b981' },
    { k: 'garanties', label: 'Garantie mécanique & protection pécuniaire', color: '#14b8a6' },
    { k: 'entretien', label: 'Entretien & pneus', color: '#06b6d4' },
    { k: 'taxes', label: 'Taxes & contrôle technique', color: '#84cc16' },
    { k: 'divers', label: 'Stationnement & divers', color: '#f97316' },
    { k: 'imprevus', label: 'Imprévus & fin de contrat', color: '#ef4444' },
    { k: 'autres', label: 'Autres frais', color: '#94a3b8' }
  ];

  // Intérêts payés pendant les `held` premiers mois d'un crédit amorti (mensualité constante)
  function interestPaid(principal, ratePct, creditMonths, held) {
    if (principal <= 0 || creditMonths <= 0) return 0;
    var r = ratePct / 100 / 12, m = r === 0 ? principal / creditMonths : principal * r / (1 - Math.pow(1 + r, -creditMonths));
    var bal = principal, paid = 0, lim = Math.min(held, creditMonths);
    for (var i = 0; i < lim; i++) { var it = bal * r; paid += it; bal -= (m - it); }
    return paid;
  }
  // Mensualité d'un crédit amorti (hors assurance)
  function monthlyPayment(principal, ratePct, creditMonths) {
    if (principal <= 0 || creditMonths <= 0) return 0;
    var r = ratePct / 100 / 12; return r === 0 ? principal / creditMonths : principal * r / (1 - Math.pow(1 + r, -creditMonths));
  }
  // Passages au contrôle technique sur la période : 1er à 4 ans, puis tous les 2 ans
  function ctCount(ageStart, years) {
    var c = 0, a = ageStart, end = ageStart + years;
    for (var t = 4; t < end + 1e-9; t += 2) if (t >= a - 1e-9 && t < end - 1e-9) c++;
    return c;
  }
  // Valeur de revente indicative : -20 % la 1re année (neuf), puis -10 % par an sur la valeur restante
  function estimateResale(price, mode, years, ageStart, kmAn) {
    var p = n(price), y = Math.max(0, n(years));
    var v = mode === 'neuf' ? p * (y <= 0 ? 1 : 0.8 * Math.pow(0.9, Math.max(0, y - 1))) : p * Math.pow(0.9, y);
    var kmFactor = 1 - Math.max(0, (n(kmAn) - 12000)) / 12000 * 0.03 * y; // roulage au-delà de 12 000 km/an : -3 %/an par tranche de 12 000
    return Math.max(0, Math.round(v * Math.max(0.5, kmFactor) / 10) * 10);
  }

  // Prime CEE (véhicule électrique neuf) : plafonds de revenu fiscal par part -> décile -> catégorie de ménage -> montant maximal indicatif
  var DECILES = [1970, 7640, 11250, 14130, 16880, 19600, 22770, 27310, 35880];
  function decile(rfr) { var r = n(rfr); for (var i = 0; i < DECILES.length; i++) if (r <= DECILES[i]) return i + 1; return 10; }
  function menage(dec) { return dec === 1 ? { k: 'precaire', label: 'Ménage modeste en situation de précarité énergétique', max: 5700 } : dec <= 4 ? { k: 'modeste', label: 'Ménage modeste', max: 4700 } : { k: 'autre', label: 'Autre ménage', max: 3500 }; }
  // Ce qui est compté dans les coûts d'usage (location : selon l'assurance / le pack d'entretien inclus ; un champ « réaffiché » est compté en supplément)
  function vis(d, mode) {
    var lease = mode === 'lld' || mode === 'leasing', pack = d.lease_pack || (d.ent_incl ? 'integral' : 'sec'), show = !!d.ent_show;
    if (!lease) return { ass: true, rev: true, usure: true, pneus: true, pack: 'sec' };
    return { ass: !d.ass_incl || !!d.ass_show, rev: pack === 'sec' || show, usure: pack === 'sec' || show, pneus: pack !== 'integral' || show, pack: pack };
  }

  function compute(d) {
    d = d || {};
    var mode = d.mode || 'neuf', lease = mode === 'lld' || mode === 'leasing';
    var months = Math.max(1, Math.round(n(d.months) || 48)), years = months / 12, kmAn = n(d.km_an), km = kmAn * years;
    var payment = 0, c = {}; CATS.forEach(function (x) { c[x.k] = 0; });
    var ageStart = mode === 'occasion' ? n(d.age_start) : 0, V = vis(d, mode);

    // 1) véhicule
    var price = n(d.price), resale = n(d.resale);
    if (!lease && (d.resale === undefined || d.resale === '' || d.resale === null)) resale = estimateResale(price, mode, years, ageStart, kmAn); // revente non saisie : estimation automatique sur la durée comparée
    var aides = 0;
    if (mode === 'neuf') {
      aides = n(d.bonus);
      if (d.fuel === 'Électrique' && d.cee) aides += menage(decile(d.revenu_part)).max + (d.cee_europe ? n(d.cee_europe_montant) : 0);
    }
    var oneOff = n(d.carte_grise) + (mode === 'neuf' ? n(d.malus) : 0) - aides + n(d.frais_divers);
    if (lease) {
      c.vehicule = n(d.rent) * months + n(d.apport);
      c.acquisition = n(d.frais_dossier);
      oneOff = 0;
      var inclKm = n(d.km_incl_an) * years;
      c.imprevus += (n(d.km_incl_an) > 0 ? Math.max(0, km - inclKm) * n(d.km_excess) : 0) + n(d.remise_etat);
      if (mode === 'leasing' && d.levee) { c.vehicule += n(d.option_achat) - resale; }
    } else {
      c.vehicule = price - resale;
      c.acquisition = oneOff;
      var cash = price + oneOff;
      var financed = d.credit ? Math.max(0, cash - n(d.apport)) : 0;
      payment = monthlyPayment(financed, n(d.credit_rate), Math.round(n(d.credit_months)));
      c.financement += interestPaid(financed, n(d.credit_rate), Math.round(n(d.credit_months)), months);
    }

    // 2) coûts d'usage
    var conso = n(d.conso), eprice = n(d.energy_price);
    if (d.fuel === 'Hybride rechargeable') { var pe = Math.min(100, Math.max(0, n(d.part_elec))) / 100; c.energie = km * (pe * n(d.conso_elec) / 100 * n(d.price_elec) + (1 - pe) * conso / 100 * eprice); }
    else c.energie = conso / 100 * km * eprice;
    c.assurance = !V.ass ? 0 : n(d.assurance_an) * (d.ass_unit === 'mois' ? 12 : 1) * years;
    c.garanties = !lease ? 0 : (d.garantie ? n(d.garantie_mois) * months : 0) + (d.protection ? n(d.protection_mois) * months : 0);
    c.entretien = (V.rev ? n(d.entretien_an) * years : 0) + (V.usure ? n(d.usure_an) * years : 0) + (lease && V.pack !== 'sec' ? n(d.pack_mois) * months : 0);
    if (V.pneus) {
      if (d.pneus_mode === 'budget') c.entretien += n(d.pneus_an) * years;
      else c.entretien += (n(d.pneus_km) > 0 ? km / n(d.pneus_km) * n(d.pneus_set) : 0) + (d.pneus_hiver && n(d.pneus_hiver_km) > 0 ? km / n(d.pneus_hiver_km) * n(d.pneus_hiver_set) : 0);
    }
    c.taxes = n(d.taxes_an) * years + ctCount(ageStart, years) * n(d.ct_cost);
    c.divers = n(d.parking_mois) * months + n(d.divers_an) * years;
    c.imprevus += n(d.repair_an) * years;

    // 3) lignes libres : unique, mensuel ou annuel
    (d.custom || []).forEach(function (l) {
      var a = n(l.amount); c.autres += l.freq === 'mois' ? a * months : l.freq === 'an' ? a * years : a;
    });

    var total = 0; CATS.forEach(function (x) { total += c[x.k]; });
    return { payment: payment, months: months, years: years, km: km, total: total, perMonth: total / months, perYear: total / years, perKm: km > 0 ? total / km : null, cats: c };
  }

  var api = { compute: compute, CATS: CATS, monthlyPayment: monthlyPayment, estimateResale: estimateResale, decile: decile, menage: menage, vis: vis, interestPaid: interestPaid, ctCount: ctCount };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else root.TCO = api;
})(this);
