// Module Sport : calendrier et résultats des équipes suivies (données en base, rafraîchies au plus toutes les 6 h)
(function () {
  const API = '/modules/sport/api.php';
  const LOC = window.SPORT_LOCALE || 'fr-FR';
  const fmt = (s, o) => String(s).replace(/\{(\w+)\}/g, (_, k) => o[k]);
  const compNom = (c, def) => { const k = 'sport_comp_' + c; const v = tr(k); return v && v !== k ? v : (def || c); };
  const FINIS = ['FINISHED', 'AWARDED'], ANNULES = ['CANCELLED'], EN_COURS = ['IN_PLAY', 'PAUSED', 'LIVE'];
  const SRC = { thesportsdb: 'TheSportsDB', fcbarcelona: 'fcbarcelona.fr', footmercato: 'FootMercato' };
  let data = null, tab = 'upcoming', team = 'all', comp = 'all', rafraichi = false, limite = 25;
  const $ = (id) => document.getElementById(id);
  const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; };
  const fmtJour = new Intl.DateTimeFormat(LOC, { timeZone: 'Europe/Paris', weekday: 'short', day: 'numeric', month: 'short' });
  const fmtHeure = new Intl.DateTimeFormat(LOC, { timeZone: 'Europe/Paris', hour: '2-digit', minute: '2-digit' });
  const fmtMois = new Intl.DateTimeFormat(LOC, { timeZone: 'Europe/Paris', month: 'long', year: 'numeric' });
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
  const heure = (m) => m.status === 'SCHEDULED' ? tr('sport_time_tbc') : fmtHeure.format(dt(m));
  const aVenir = (m) => !FINIS.includes(m.status) && !ANNULES.includes(m.status);
  const duChamp = (m) => comp === 'all' || m.competition_code === comp;
  const visibles = () => data.matches.filter((m) => (team === 'all' || String(m.home_team_id) === team || String(m.away_team_id) === team) && duChamp(m));

  function relatif(d) {
    const j = Math.round((d - Date.now()) / 86400000);
    if (j <= 0) return d - Date.now() < -3 * 3600e3 ? '' : tr('sport_today');
    return j === 1 ? tr('sport_tomorrow') : fmt(tr('sport_in_days'), { n: j });
  }


  // Bloc « où voir le match » : chaînes en France d'abord, autres pays dans un menu repliable
  function tvBlock(m) {
    const rows = (data.tv && data.tv[m.match_id]) || [];
    if (!rows.length) return null;
    const pays = {}, sources = [];
    rows.forEach((r) => { (pays[r.country] = pays[r.country] || []).push(...r.channels.split(',').map((x) => x.trim()).filter(Boolean)); if (!sources.includes(SRC[r.source] || r.source)) sources.push(SRC[r.source] || r.source); });
    const uniq = (a) => a.filter((v, i) => a.findIndex((x) => x.toLowerCase() === v.toLowerCase()) === i);
    const fr = pays['France'] ? uniq(pays['France']) : null;
    const autres = Object.keys(pays).filter((c) => c !== 'France').sort((a, b) => a.localeCompare(b, LOC));
    const d = el('details', 'sp-tv');
    d.appendChild(el('summary', null, '\ud83d\udcfa ' + (fr ? tr('sport_tv_in_france') + ' : ' + fr.join(', ') : tr('sport_tv_none_france'))));
    if (autres.length) {
      const l = el('div', 'sp-tv-list'); l.appendChild(el('div', 'sp-tv-h', fmt(tr('sport_tv_other'), { n: autres.length })));
      autres.forEach((c) => { const r = el('div'); r.appendChild(el('b', null, c + ' : ')); r.appendChild(document.createTextNode(uniq(pays[c]).join(', '))); l.appendChild(r); });
      d.appendChild(l);
    }
    d.appendChild(el('div', 'sp-tv-src', fmt(tr('sport_tv_sources'), { s: sources.join(', ') })));
    return d;
  }

  function renderFiltre() {
    const f = $('sp-filter'); f.textContent = '';
    const opts = [['all', tr('sport_filter_all')]].concat(data.teams.map((t) => [String(t.team_id), t.short_name || t.name]));
    opts.forEach(([v, lab]) => { const b = el('button', 'pf-seg-btn' + (v === team ? ' active' : ''), lab); b.type = 'button'; b.addEventListener('click', () => { team = v; limite = 25; render(); }); f.appendChild(b); });
  }

  function renderFiltreChamp() {
    const f = $('sp-filter-comp'); f.textContent = '';
    const codes = []; data.matches.forEach((m) => { if (!codes.includes(m.competition_code)) codes.push(m.competition_code); });
    const ordre = ['FL1', 'PD', 'CL']; codes.sort((a, b) => (ordre.indexOf(a) + 1 || 99) - (ordre.indexOf(b) + 1 || 99));
    [['all', tr('sport_filter_all')]].concat(codes.map((c) => [c, compNom(c)])).forEach(([v, lab]) => {
      const b = el('button', 'pf-seg-btn' + (v === comp ? ' active' : ''), lab); b.type = 'button';
      b.addEventListener('click', () => { comp = v; limite = 25; render(); }); f.appendChild(b);
    });
  }

  function renderProchains() {
    const box = $('sp-next'); box.textContent = '';
    if (tab !== 'upcoming') return;
    data.teams.filter((t) => team === 'all' || String(t.team_id) === team).forEach((t) => {
      const m = data.matches.filter((x) => aVenir(x) && duChamp(x) && (String(x.home_team_id) === String(t.team_id) || String(x.away_team_id) === String(t.team_id)))[0];
      const c = el('div', 'sp-card'); c.appendChild(el('h3', null, tr('sport_next_match') + ' · ' + (t.short_name || t.name)));
      if (!m) { c.appendChild(el('div', 'sp-when', tr('sport_none_planned'))); box.appendChild(c); return; }
      const l = el('div', 'sp-teams'); l.appendChild(matchLine(m)); c.appendChild(l);
      const d = dt(m); const w = el('div', 'sp-when', fmtJour.format(d) + ' · ' + fmtHeure.format(d) + ' · ' + compNom(m.competition_code, m.competition_name));
      c.appendChild(w); const r = relatif(d); if (r) c.appendChild(el('div', 'sp-when', r));
      if (m.status === 'POSTPONED') c.appendChild(el('span', 'sp-badge', tr('sport_postponed')));
      const tvb = tvBlock(m); if (tvb) c.appendChild(tvb);
      box.appendChild(c);
    });
  }

  function renderListe() {
    const L = $('sp-list'); L.textContent = '';
    let ms = visibles().filter((m) => tab === 'upcoming' ? aVenir(m) : FINIS.includes(m.status));
    if (tab === 'results') ms = ms.slice().reverse();
    if (!ms.length) { L.appendChild(el('div', 'sp-empty', tab === 'upcoming' ? tr('sport_empty_upcoming') : tr('sport_empty_results'))); return; }
    const total = ms.length; ms = ms.slice(0, limite);
    let mois = '';
    ms.forEach((m) => {
      const d = dt(m), mm = fmtMois.format(d);
      if (mm !== mois) { mois = mm; L.appendChild(el('div', 'sp-month', mm)); }
      const row = el('div', 'sp-row');
      const date = el('div', 'sp-date'); date.appendChild(el('b', null, fmtJour.format(d))); date.appendChild(document.createTextNode(heure(m)));
      const mt = el('div', 'sp-match'); mt.appendChild(matchLine(m));
      if (m.status === 'POSTPONED') mt.appendChild(el('span', 'sp-badge', tr('sport_postponed')));
      if (EN_COURS.includes(m.status)) mt.appendChild(el('span', 'sp-badge', tr('sport_live')));
      const tvr = tvBlock(m); if (tvr) mt.appendChild(tvr);
      row.classList.add('sp-comp--' + m.competition_code);
      row.appendChild(date); row.appendChild(mt); row.appendChild(el('span', 'sp-comp sp-comp--' + m.competition_code, compNom(m.competition_code, m.competition_name)));
      L.appendChild(row);
    });
    if (total > ms.length) {
      const more = el('button', 'pf-btn btn-secondary', fmt(tr('sport_show_more'), { n: total - ms.length })); more.type = 'button'; more.style.margin = '12px auto'; more.style.display = 'block';
      more.addEventListener('click', () => { limite += 25; renderListe(); });
      L.appendChild(more);
    }
  }

  function renderPied() {
    const f = $('sp-foot');
    f.textContent = tr('sport_foot_source') + (data.last_fetch ? ' · ' + fmt(tr('sport_foot_updated'), { d: fmtJour.format(new Date(data.last_fetch)), t: fmtHeure.format(new Date(data.last_fetch)) }) : '') + ' · ' + tr('sport_foot_tz');
  }

  function render() {
    if (!data) return;
    document.querySelectorAll('.pf-pill[data-tab]').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
    if (!data.has_key) {
      $('sp-filter').textContent = ''; $('sp-filter-comp').textContent = ''; $('sp-next').textContent = ''; $('sp-foot').textContent = '';
      const L = $('sp-list'); L.textContent = '';
      const e = el('div', 'sp-empty'); e.appendChild(el('p', null, tr('sport_no_key')));
      const b = el('button', 'pf-btn btn-primary', tr('sport_set_key')); b.type = 'button'; b.addEventListener('click', spOpenModal); e.appendChild(b); L.appendChild(e);
      return;
    }
    renderFiltre(); renderFiltreChamp(); renderProchains(); renderListe(); renderPied();
  }

  async function charger() {
    try {
      const r = await pachaFetch(API + '?action=list');
      if (!r || r.success === false) throw new Error((r && r.error) || 'Erreur');
      data = r; render();
      // données de plus de 6 h : actualisation en arrière-plan (un seul essai par chargement de page)
      if (data.has_key && !rafraichi) { rafraichi = true; await actualiser(false, true); }
    } catch (e) { $('sp-list').textContent = ''; $('sp-list').appendChild(el('div', 'sp-empty', tr('sport_load_error') + ' ' + e.message)); }
  }

  async function actualiser(force, silencieux) {
    const fd = new FormData(); fd.append('action', 'refresh'); fd.append('csrf', window.SPORT_CSRF); if (force) fd.append('force', '1');
    try {
      const r = await pachaFetch(API, { method: 'POST', body: fd });
      if (r.success === false) throw new Error(r.error);
      if (r.erreurs && r.erreurs.length) toast(r.erreurs.join(' · '), 'error');
      else if (!silencieux) toast(r.appels ? tr('sport_toast_refreshed') : tr('sport_toast_uptodate'));
      const l = await pachaFetch(API + '?action=list'); if (l && l.success !== false) { data = l; render(); }
    } catch (e) { toast(e.message, 'error'); }
  }

  window.spOpenModal = function () { $('sp-key').value = ''; $('sp-key-state').textContent = data && data.has_key ? tr('sport_key_has') : tr('sport_key_none'); $('sp-modal').classList.add('show'); };
  window.spCloseModal = function () { $('sp-modal').classList.remove('show'); };

  document.querySelectorAll('.pf-pill[data-tab]').forEach((b) => b.addEventListener('click', () => { tab = b.dataset.tab; limite = 25; render(); }));
  $('sp-refresh').addEventListener('click', () => actualiser(true, false));
  $('sp-gear').addEventListener('click', spOpenModal);
  $('sp-key-save').addEventListener('click', async () => {
    const fd = new FormData(); fd.append('action', 'save_key'); fd.append('csrf', window.SPORT_CSRF); fd.append('key', $('sp-key').value);
    try { const r = await pachaFetch(API, { method: 'POST', body: fd }); if (r.success === false) throw new Error(r.error);
      spCloseModal(); toast(r.erreurs && r.erreurs.length ? r.erreurs.join(' · ') : tr('sport_toast_key_saved'), r.erreurs && r.erreurs.length ? 'error' : 'success'); await charger(); } catch (e) { toast(e.message, 'error'); }
  });
  $('sp-key-remove').addEventListener('click', async () => {
    if (!(await pachaConfirm(tr('sport_key_remove'), tr('sport_key_remove_msg')))) return;
    const fd = new FormData(); fd.append('action', 'remove_key'); fd.append('csrf', window.SPORT_CSRF);
    try { const r = await pachaFetch(API, { method: 'POST', body: fd }); if (r.success === false) throw new Error(r.error); spCloseModal(); await charger(); } catch (e) { toast(e.message, 'error'); }
  });
  charger();
})();
