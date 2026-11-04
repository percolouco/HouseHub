// Module Sport : calendrier et résultats des équipes suivies (données en base, rafraîchies au plus toutes les 6 h)
(function () {
  const API = '/modules/sport/api.php';
  const COMP = { FL1: 'Ligue 1', PD: 'Liga', CL: 'Ligue des champions', FAC: 'Coupe de France', CDR: 'Copa del Rey' };
  const FINIS = ['FINISHED', 'AWARDED'], ANNULES = ['CANCELLED'], EN_COURS = ['IN_PLAY', 'PAUSED', 'LIVE'];
  let data = null, tab = 'upcoming', team = 'all', rafraichi = false, limite = 25;
  const $ = (id) => document.getElementById(id);
  const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; };
  const fmtJour = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', weekday: 'short', day: 'numeric', month: 'short' });
  const fmtHeure = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', hour: '2-digit', minute: '2-digit' });
  const fmtMois = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', month: 'long', year: 'numeric' });
  const toast = (m, t) => { if (typeof showToast === 'function') showToast(m, t || 'success'); };

  const ids = () => data.teams.map((t) => String(t.team_id));
  const mine = (id) => id !== null && ids().includes(String(id));
  const nomEquipe = (m, cote) => m[cote + '_name'] || '?';
  function teamSpan(m, cote) { const s = el('span', mine(m[cote + '_team_id']) ? 'sp-mine' : '', nomEquipe(m, cote)); return s; }
  function matchLine(m) {
    const d = el('span'); d.appendChild(teamSpan(m, 'home'));
    if (FINIS.includes(m.status) && m.score_home !== null) d.appendChild(el('span', 'sp-score', m.score_home + ' – ' + m.score_away));
    else d.appendChild(document.createTextNode(' – '));
    d.appendChild(teamSpan(m, 'away'));
    return d;
  }
  const dt = (m) => new Date(m.kickoff);
  // statut SCHEDULED = date connue mais heure pas encore fixée (l'API renvoie alors minuit UTC)
  const heure = (m) => m.status === 'SCHEDULED' ? 'horaire \u00e0 confirmer' : fmtHeure.format(dt(m));
  const aVenir = (m) => !FINIS.includes(m.status) && !ANNULES.includes(m.status);
  const visibles = () => data.matches.filter((m) => team === 'all' || String(m.home_team_id) === team || String(m.away_team_id) === team);

  function relatif(d) {
    const j = Math.round((d - Date.now()) / 86400000);
    if (j <= 0) return d - Date.now() < -3 * 3600e3 ? '' : 'aujourd’hui';
    return j === 1 ? 'demain' : 'dans ' + j + ' jours';
  }

  function renderFiltre() {
    const f = $('sp-filter'); f.textContent = '';
    const opts = [['all', 'Tous']].concat(data.teams.map((t) => [String(t.team_id), t.short_name || t.name]));
    opts.forEach(([v, lab]) => { const b = el('button', 'pf-seg-btn' + (v === team ? ' active' : ''), lab); b.type = 'button'; b.addEventListener('click', () => { team = v; limite = 25; render(); }); f.appendChild(b); });
  }

  function renderProchains() {
    const box = $('sp-next'); box.textContent = '';
    if (tab !== 'upcoming') return;
    data.teams.filter((t) => team === 'all' || String(t.team_id) === team).forEach((t) => {
      const m = data.matches.filter((x) => aVenir(x) && (String(x.home_team_id) === String(t.team_id) || String(x.away_team_id) === String(t.team_id)))[0];
      const c = el('div', 'sp-card'); c.appendChild(el('h3', null, 'Prochain match · ' + (t.short_name || t.name)));
      if (!m) { c.appendChild(el('div', 'sp-when', 'Aucun match programmé')); box.appendChild(c); return; }
      const l = el('div', 'sp-teams'); l.appendChild(matchLine(m)); c.appendChild(l);
      const d = dt(m); const w = el('div', 'sp-when', fmtJour.format(d) + ' · ' + fmtHeure.format(d) + ' · ' + (COMP[m.competition_code] || m.competition_name));
      c.appendChild(w); const r = relatif(d); if (r) c.appendChild(el('div', 'sp-when', r));
      if (m.status === 'POSTPONED') c.appendChild(el('span', 'sp-badge', 'Reporté'));
      box.appendChild(c);
    });
  }

  function renderListe() {
    const L = $('sp-list'); L.textContent = '';
    let ms = visibles().filter((m) => tab === 'upcoming' ? aVenir(m) : FINIS.includes(m.status));
    if (tab === 'results') ms = ms.slice().reverse();
    if (!ms.length) { L.appendChild(el('div', 'sp-empty', tab === 'upcoming' ? 'Aucun match à venir.' : 'Aucun résultat pour le moment.')); return; }
    const total = ms.length; ms = ms.slice(0, limite);
    let mois = '';
    ms.forEach((m) => {
      const d = dt(m), mm = fmtMois.format(d);
      if (mm !== mois) { mois = mm; L.appendChild(el('div', 'sp-month', mm)); }
      const row = el('div', 'sp-row');
      const date = el('div', 'sp-date'); date.appendChild(el('b', null, fmtJour.format(d))); date.appendChild(document.createTextNode(heure(m)));
      const mt = el('div', 'sp-match'); mt.appendChild(matchLine(m));
      if (m.status === 'POSTPONED') mt.appendChild(el('span', 'sp-badge', 'Reporté'));
      if (EN_COURS.includes(m.status)) mt.appendChild(el('span', 'sp-badge', 'En cours'));
      row.appendChild(date); row.appendChild(mt); row.appendChild(el('span', 'sp-comp', COMP[m.competition_code] || m.competition_name));
      L.appendChild(row);
    });
    if (total > ms.length) {
      const more = el('button', 'btn-secondary', 'Afficher plus (' + (total - ms.length) + ' restants)'); more.type = 'button'; more.style.margin = '12px auto'; more.style.display = 'block';
      more.addEventListener('click', () => { limite += 25; renderListe(); });
      L.appendChild(more);
    }
  }

  function renderPied() {
    const f = $('sp-foot');
    f.textContent = 'Données football-data.org' + (data.last_fetch ? ' · mises à jour le ' + fmtJour.format(new Date(data.last_fetch)) + ' à ' + fmtHeure.format(new Date(data.last_fetch)) : '') + ' · horaires de Paris';
  }

  function render() {
    if (!data) return;
    document.querySelectorAll('.pf-pill[data-tab]').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
    if (!data.has_key) {
      $('sp-filter').textContent = ''; $('sp-next').textContent = ''; $('sp-foot').textContent = '';
      const L = $('sp-list'); L.textContent = '';
      const e = el('div', 'sp-empty'); e.appendChild(el('p', null, 'Aucune clé football-data.org n’est renseignée.'));
      const b = el('button', 'btn-primary', 'Renseigner la clé'); b.type = 'button'; b.addEventListener('click', spOpenModal); e.appendChild(b); L.appendChild(e);
      return;
    }
    renderFiltre(); renderProchains(); renderListe(); renderPied();
  }

  async function charger() {
    try {
      const r = await pachaFetch(API + '?action=list');
      if (!r || r.success === false) throw new Error((r && r.error) || 'Erreur');
      data = r; render();
      // données de plus de 6 h : actualisation en arrière-plan (un seul essai par chargement de page)
      if (data.has_key && data.stale && !rafraichi) { rafraichi = true; await actualiser(false, true); }
    } catch (e) { $('sp-list').textContent = ''; $('sp-list').appendChild(el('div', 'sp-empty', 'Impossible de charger le calendrier : ' + e.message)); }
  }

  async function actualiser(force, silencieux) {
    const fd = new FormData(); fd.append('action', 'refresh'); fd.append('csrf', window.SPORT_CSRF); if (force) fd.append('force', '1');
    try {
      const r = await pachaFetch(API, { method: 'POST', body: fd });
      if (r.success === false) throw new Error(r.error);
      if (r.erreurs && r.erreurs.length) toast(r.erreurs.join(' · '), 'error');
      else if (!silencieux) toast(r.appels ? 'Calendrier actualisé' : 'Déjà à jour (actualisation limitée pour économiser l’API)');
      const l = await pachaFetch(API + '?action=list'); if (l && l.success !== false) { data = l; render(); }
    } catch (e) { toast(e.message, 'error'); }
  }

  window.spOpenModal = function () { $('sp-key').value = ''; $('sp-key-state').textContent = data && data.has_key ? 'Une clé est déjà enregistrée (la saisir remplace l’ancienne).' : 'Aucune clé enregistrée.'; $('sp-modal').classList.add('show'); };
  window.spCloseModal = function () { $('sp-modal').classList.remove('show'); };

  document.querySelectorAll('.pf-pill[data-tab]').forEach((b) => b.addEventListener('click', () => { tab = b.dataset.tab; limite = 25; render(); }));
  $('sp-refresh').addEventListener('click', () => actualiser(true, false));
  $('sp-gear').addEventListener('click', spOpenModal);
  $('sp-key-save').addEventListener('click', async () => {
    const fd = new FormData(); fd.append('action', 'save_key'); fd.append('csrf', window.SPORT_CSRF); fd.append('key', $('sp-key').value);
    try { const r = await pachaFetch(API, { method: 'POST', body: fd }); if (r.success === false) throw new Error(r.error);
      spCloseModal(); toast(r.erreurs && r.erreurs.length ? r.erreurs.join(' · ') : 'Clé enregistrée', r.erreurs && r.erreurs.length ? 'error' : 'success'); await charger(); } catch (e) { toast(e.message, 'error'); }
  });
  $('sp-key-remove').addEventListener('click', async () => {
    if (!(await pachaConfirm('Retirer la clé', 'Le calendrier ne sera plus actualisé (les données déjà en base sont conservées).'))) return;
    const fd = new FormData(); fd.append('action', 'remove_key'); fd.append('csrf', window.SPORT_CSRF);
    try { const r = await pachaFetch(API, { method: 'POST', body: fd }); if (r.success === false) throw new Error(r.error); spCloseModal(); await charger(); } catch (e) { toast(e.message, 'error'); }
  });
  charger();
})();
