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

  function compute(d) {
    d = d || {};
    var mode = d.mode || 'neuf', lease = mode === 'lld' || mode === 'leasing';
    var months = Math.max(1, Math.round(n(d.months) || 48)), years = months / 12, kmAn = n(d.km_an), km = kmAn * years;
    var c = {}; CATS.forEach(function (x) { c[x.k] = 0; });
    var ageStart = mode === 'occasion' ? n(d.age_start) : 0;

    // 1) véhicule
    var price = n(d.price), resale = n(d.resale), capital = 0;
    var oneOff = n(d.carte_grise) + n(d.malus) - n(d.bonus) + n(d.frais_divers);
    if (lease) {
      c.vehicule = n(d.rent) * months + n(d.apport);
      c.acquisition = n(d.frais_dossier) + n(d.carte_grise) + n(d.malus) - n(d.bonus);
      var inclKm = n(d.km_incl_an) * years;
      c.imprevus += (n(d.km_incl_an) > 0 ? Math.max(0, km - inclKm) * n(d.km_excess) : 0) + n(d.remise_etat);
      if (mode === 'leasing' && d.levee) { c.vehicule += n(d.option_achat) - resale; }
      capital = n(d.apport) + n(d.depot_garantie);
    } else {
      c.vehicule = price - resale;
      c.acquisition = oneOff;
      var cash = price + oneOff;
      var financed = d.credit ? Math.max(0, cash - n(d.apport)) : 0;
      c.financement += interestPaid(financed, n(d.credit_rate), Math.round(n(d.credit_months)), months);
      capital = d.credit ? Math.min(cash, n(d.apport)) : cash;
    }
    c.financement += capital * n(d.opp_rate) / 100 * years;

    // 2) coûts d'usage
    var conso = n(d.conso), eprice = n(d.energy_price);
    c.energie = conso / 100 * km * eprice;
    c.assurance = d.ass_incl ? 0 : n(d.assurance_an) * years;
    if (!(lease && d.ent_incl)) {
      c.entretien = n(d.entretien_an) * years + (n(d.pneus_km) > 0 ? km / n(d.pneus_km) * n(d.pneus_set) : 0);
    }
    c.taxes = n(d.taxes_an) * years + ctCount(ageStart, years) * n(d.ct_cost);
    c.divers = n(d.parking_mois) * months + n(d.divers_an) * years;
    c.imprevus += n(d.repair_an) * years;

    // 3) lignes libres : unique, mensuel ou annuel
    (d.custom || []).forEach(function (l) {
      var a = n(l.amount); c.autres += l.freq === 'mois' ? a * months : l.freq === 'an' ? a * years : a;
    });

    var total = 0; CATS.forEach(function (x) { total += c[x.k]; });
    return { months: months, years: years, km: km, total: total, perMonth: total / months, perYear: total / years, perKm: km > 0 ? total / km : null, cats: c };
  }

  var api = { compute: compute, CATS: CATS, estimateResale: estimateResale, interestPaid: interestPaid, ctCount: ctCount };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else root.TCO = api;
})(this);
