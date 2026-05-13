const iosEventsList = document.getElementById("ios-events-list");
const iosSyncStatus = document.getElementById("ios-sync-status");
const iosForm = document.getElementById("ios-event-form");
const iosAgendaMount = document.getElementById("ios-agenda-mount");
const iosAgendaTitle = document.getElementById("ios-agenda-title");

const H_START = 6;
const H_END = 22;
const PX_PER_H = 44;
const TRACK_H = (H_END - H_START) * PX_PER_H;

let iosEventsCache = [];
/** @type {'day'|'week'|'month'} */
let iosViewMode = "week";
/** Ancre de navigation (jour courant dans la période affichée) */
let iosCursor = new Date();
let iosNowTimer = null;
let iosListVisible = false;

const PALETTE = [
  "ios-cal-c0",
  "ios-cal-c1",
  "ios-cal-c2",
  "ios-cal-c3",
  "ios-cal-c4",
  "ios-cal-c5",
];

const SUGGEST_HEX = ["#22c55e", "#3b82f6", "#8b5cf6", "#eab308", "#14b8a6", "#f43f5e"];

function escHtml(s) {
  const d = document.createElement("div");
  d.textContent = s ?? "";
  return d.innerHTML;
}

function sqlToDatetimeLocal(s) {
  if (!s) return "";
  const m = /^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})/.exec(String(s).trim());
  if (!m) return "";
  return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}`;
}

function localDatetimeToSql(s) {
  if (!s) return "";
  const t = s.includes("T") ? s.replace("T", " ") : s;
  return t.length === 16 ? `${t}:00` : t;
}

function parseEvtDate(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  return Number.isNaN(d.getTime()) ? null : d;
}

function darkenHex(hex, amount) {
  const n = parseInt(hex.slice(1), 16);
  const r = Math.max(0, (n >> 16) - amount);
  const g = Math.max(0, ((n >> 8) & 0xff) - amount);
  const b = Math.max(0, (n & 0xff) - amount);
  return `#${[r, g, b].map((x) => x.toString(16).padStart(2, "0")).join("")}`;
}

function suggestedColorForKey(key) {
  const k = String(key || "");
  let h = 0;
  for (let i = 0; i < k.length; i++) h = (h * 31 + k.charCodeAt(i)) >>> 0;
  return SUGGEST_HEX[h % SUGGEST_HEX.length];
}

function colorClassForEvent(evt) {
  const key = String(evt.calendar_source_url || evt.external_uid || evt.id || "");
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
  return PALETTE[h % PALETTE.length];
}

/** Applique couleur perso (API) ou palette par défaut */
function styleEventBlock(el, evt) {
  el.className = "ios-event-block";
  el.style.background = "";
  el.style.boxShadow = "";
  const hex = evt.display_color;
  if (hex && /^#[0-9A-Fa-f]{6}$/i.test(hex)) {
    const c = hex.startsWith("#") ? hex : `#${hex.slice(-6)}`;
    el.classList.add("ios-event-block--custom");
    el.style.background = `linear-gradient(135deg, ${darkenHex(c, 40)} 0%, ${c} 100%)`;
    el.style.boxShadow = "inset 3px 0 0 rgba(255,255,255,0.35)";
  } else {
    el.classList.add(colorClassForEvent(evt));
  }
}

function mondayOf(date) {
  const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const dow = d.getDay();
  const diff = dow === 0 ? -6 : 1 - dow;
  d.setDate(d.getDate() + diff);
  return d;
}

function addDays(d, n) {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
}

function addMonths(d, n) {
  const x = new Date(d);
  x.setMonth(x.getMonth() + n);
  return x;
}

function sameLocalDay(a, b) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

/** Segment d’un événement visible dans [dayMidnight, dayMidnight+1j[ */
function segmentInLocalDay(evt, dayMidnight) {
  const s = parseEvtDate(evt.start_at);
  const e = parseEvtDate(evt.end_at);
  if (!s || !e) return null;
  const dayEnd = addDays(dayMidnight, 1);
  if (e <= dayMidnight || s >= dayEnd) return null;
  const segStart = s > dayMidnight ? s : dayMidnight;
  const segEnd = e < dayEnd ? e : dayEnd;
  return { segStart, segEnd, evt };
}

function pxInTrackFromHourFraction(dayMidnight, t) {
  const grid0 = new Date(dayMidnight);
  grid0.setHours(H_START, 0, 0, 0);
  const grid1 = new Date(dayMidnight);
  grid1.setHours(H_END, 0, 0, 0);
  const tt = Math.min(Math.max(t.getTime(), grid0.getTime()), grid1.getTime());
  return ((tt - grid0.getTime()) / 3600000) * PX_PER_H;
}

function heightPxForSegment(dayMidnight, segStart, segEnd) {
  const a = pxInTrackFromHourFraction(dayMidnight, segStart);
  const b = pxInTrackFromHourFraction(dayMidnight, segEnd);
  return Math.max(18, b - a);
}

function nowLineTopPx(colDay) {
  const now = new Date();
  if (!sameLocalDay(now, colDay)) return null;
  return pxInTrackFromHourFraction(colDay, now);
}

function assignLanes(segments) {
  if (segments.length === 0) return;
  segments.sort((a, b) => a.segStart - b.segStart);
  const laneEnds = [];
  segments.forEach((seg) => {
    let L = 0;
    while (laneEnds[L] && seg.segStart < laneEnds[L]) L++;
    laneEnds[L] = seg.segEnd;
    seg._lane = L;
  });
  const maxL = segments.reduce((m, s) => Math.max(m, s._lane), 0);
  segments.forEach((s) => {
    s._laneCount = maxL + 1;
  });
}

async function iosApi(action, options = {}) {
  const method = options.method || "GET";
  const headers = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(options.headers || {}),
  };
  if (method !== "GET") {
    headers["X-CSRF-Token"] = window.CSRF_TOKEN || "";
    headers["Content-Type"] = "application/json";
  }
  const response = await fetch(`/modules/calendar-ios/api.php?action=${encodeURIComponent(action)}`, {
    method,
    credentials: "same-origin",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const json = await response.json();
  if (!json.ok) throw new Error(json.error || "Erreur inconnue");
  return json.data;
}

function formatDate(iso) {
  const d = parseEvtDate(iso);
  return d ? d.toLocaleString("fr-FR") : iso;
}

function fillForm(evt) {
  document.getElementById("ios-event-id").value = evt.id;
  document.getElementById("ios-title").value = evt.title || "";
  document.getElementById("ios-location").value = evt.location || "";
  document.getElementById("ios-description").value = evt.description || "";
  document.getElementById("ios-start").value = sqlToDatetimeLocal(evt.start_at);
  document.getElementById("ios-end").value = sqlToDatetimeLocal(evt.end_at);
}

function resetForm() {
  iosForm.reset();
  document.getElementById("ios-event-id").value = "";
}

function eventsForLocalDay(y, m, d) {
  return iosEventsCache.filter((evt) => {
    const t = parseEvtDate(evt.start_at);
    return t && t.getFullYear() === y && t.getMonth() === m && t.getDate() === d;
  });
}

function updateToolbarTitle() {
  if (!iosAgendaTitle) return;
  if (iosViewMode === "day") {
    iosAgendaTitle.textContent = iosCursor.toLocaleDateString("fr-FR", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    });
    return;
  }
  if (iosViewMode === "week") {
    const mon = mondayOf(iosCursor);
    const sun = addDays(mon, 6);
    if (mon.getMonth() === sun.getMonth() && mon.getFullYear() === sun.getFullYear()) {
      iosAgendaTitle.textContent = `${mon.getDate()} – ${sun.getDate()} ${sun.toLocaleDateString("fr-FR", { month: "long", year: "numeric" })}`;
    } else {
      iosAgendaTitle.textContent = `${mon.toLocaleDateString("fr-FR", { day: "numeric", month: "short" })} – ${sun.toLocaleDateString("fr-FR", { day: "numeric", month: "short", year: "numeric" })}`;
    }
    return;
  }
  iosAgendaTitle.textContent = new Date(iosCursor.getFullYear(), iosCursor.getMonth(), 1).toLocaleDateString("fr-FR", {
    month: "long",
    year: "numeric",
  });
}

function setActiveSegments() {
  document.querySelectorAll(".ios-seg").forEach((b) => {
    b.classList.toggle("ios-seg--active", b.getAttribute("data-ios-view") === iosViewMode);
  });
}

function renderTimeGutter() {
  const frag = document.createDocumentFragment();
  for (let h = H_START; h < H_END; h++) {
    const row = document.createElement("div");
    row.className = "ios-agenda-gutter-cell";
    row.textContent = `${h} h`;
    frag.appendChild(row);
  }
  return frag;
}

function buildDayColumn(dayMidnight) {
  const col = document.createElement("div");
  col.className = "ios-agenda-col";
  const inner = document.createElement("div");
  inner.className = "ios-agenda-col-inner";
  inner.style.height = `${TRACK_H}px`;

  const segs = [];
  iosEventsCache.forEach((evt) => {
    const seg = segmentInLocalDay(evt, dayMidnight);
    if (seg) segs.push(seg);
  });
  assignLanes(segs);

  segs.forEach((seg) => {
    const top = pxInTrackFromHourFraction(dayMidnight, seg.segStart);
    const h = heightPxForSegment(dayMidnight, seg.segStart, seg.segEnd);
    const w = 100 / seg._laneCount;
    const left = (100 * seg._lane) / seg._laneCount;
    const blk = document.createElement("button");
    blk.type = "button";
    styleEventBlock(blk, seg.evt);
    blk.style.top = `${top}px`;
    blk.style.height = `${h}px`;
    blk.style.left = `calc(${left}% + 2px)`;
    blk.style.width = `calc(${w}% - 4px)`;
    blk.innerHTML = `<span class="ios-event-block-title">${escHtml(seg.evt.title || "(Sans titre)")}</span>`;
    blk.title = [seg.evt.title, seg.evt.location, seg.evt.calendar_source_url].filter(Boolean).join("\n");
    blk.addEventListener("click", () => {
      fillForm(seg.evt);
      document.getElementById("ios-edit-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    inner.appendChild(blk);
  });

  col.appendChild(inner);
  return col;
}

function renderWeekOrDay() {
  if (!iosAgendaMount) return;
  const isDay = iosViewMode === "day";
  const weekStart = isDay ? new Date(iosCursor.getFullYear(), iosCursor.getMonth(), iosCursor.getDate()) : mondayOf(iosCursor);
  const days = isDay ? [weekStart] : Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));

  const wrap = document.createElement("div");
  wrap.className = "ios-week-wrap";

  const head = document.createElement("div");
  head.className = "ios-week-head" + (isDay ? " ios-week-head--day" : "");
  const corner = document.createElement("div");
  corner.className = "ios-week-corner";
  head.appendChild(corner);
  const today = new Date();
  days.forEach((d) => {
    const th = document.createElement("div");
    th.className = "ios-week-head-day";
    if (sameLocalDay(d, today)) th.classList.add("ios-week-head-day--today");
    const num = document.createElement("span");
    num.className = "ios-week-head-num";
    num.textContent = String(d.getDate());
    const wd = d.toLocaleDateString("fr-FR", { weekday: "short" }).replace(/\.$/, "");
    th.appendChild(num);
    th.appendChild(document.createTextNode(` ${wd}.`));
    head.appendChild(th);
  });
  wrap.appendChild(head);

  const scroll = document.createElement("div");
  scroll.className = "ios-week-scroll";

  const track = document.createElement("div");
  track.className = "ios-week-track";

  const gutter = document.createElement("div");
  gutter.className = "ios-agenda-gutter";
  gutter.appendChild(renderTimeGutter());
  track.appendChild(gutter);

  const colsWrap = document.createElement("div");
  colsWrap.className = "ios-agenda-cols-wrap";

  const cols = document.createElement("div");
  cols.className = `ios-agenda-cols${isDay ? " ios-agenda-cols--day" : ""}`;
  days.forEach((d0) => {
    const dm = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate());
    cols.appendChild(buildDayColumn(dm));
  });
  colsWrap.appendChild(cols);

  const todayCol = days.findIndex((d) => sameLocalDay(d, new Date()));
  if (todayCol >= 0) {
    const dm = new Date(days[todayCol].getFullYear(), days[todayCol].getMonth(), days[todayCol].getDate());
    const nowTop = nowLineTopPx(dm);
    if (nowTop !== null) {
      const ov = document.createElement("div");
      ov.className = "ios-now-overlay";
      const line = document.createElement("div");
      line.className = "ios-now-line-span";
      line.style.top = `${nowTop}px`;
      const lbl = document.createElement("div");
      lbl.className = "ios-now-lbl-span";
      lbl.textContent = new Date().toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
      lbl.style.top = `${Math.max(2, nowTop - 10)}px`;
      ov.appendChild(line);
      ov.appendChild(lbl);
      colsWrap.appendChild(ov);
    }
  }

  track.appendChild(colsWrap);
  scroll.appendChild(track);
  wrap.appendChild(scroll);
  iosAgendaMount.innerHTML = "";
  iosAgendaMount.appendChild(wrap);
}

function renderMonthInAgenda() {
  if (!iosAgendaMount) return;
  const y = iosCursor.getFullYear();
  const m = iosCursor.getMonth();
  const first = new Date(y, m, 1);
  const last = new Date(y, m + 1, 0);
  let startWeekday = first.getDay() - 1;
  if (startWeekday < 0) startWeekday = 6;

  const wrap = document.createElement("div");
  wrap.className = "ios-month-agenda";
  const wk = document.createElement("div");
  wk.className = "ios-month-agenda-weekdays";
  ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"].forEach((t) => {
    const s = document.createElement("span");
    s.textContent = t;
    wk.appendChild(s);
  });
  wrap.appendChild(wk);
  const grid = document.createElement("div");
  grid.className = "ios-month-agenda-grid";

  for (let i = 0; i < startWeekday; i++) {
    const c = document.createElement("div");
    c.className = "ios-month-cell ios-month-cell--pad";
    grid.appendChild(c);
  }
  const today = new Date();
  for (let d = 1; d <= last.getDate(); d++) {
    const cell = document.createElement("button");
    cell.type = "button";
    cell.className = "ios-month-cell";
    const list = eventsForLocalDay(y, m, d);
    if (list.length) cell.classList.add("ios-month-cell--has");
    if (y === today.getFullYear() && m === today.getMonth() && d === today.getDate()) {
      cell.classList.add("ios-month-cell--today");
    }
    cell.innerHTML = `<span class="ios-month-num">${d}</span>`;
    if (list.length) {
      const dots = document.createElement("div");
      dots.className = "ios-month-dots";
      list.slice(0, 4).forEach(() => {
        const dot = document.createElement("span");
        dot.className = "ios-month-dot";
        dots.appendChild(dot);
      });
      cell.appendChild(dots);
    }
    cell.addEventListener("click", () => {
      iosViewMode = "day";
      iosCursor = new Date(y, m, d);
      setActiveSegments();
      updateToolbarTitle();
      renderAgenda();
    });
    grid.appendChild(cell);
  }
  wrap.appendChild(grid);
  iosAgendaMount.innerHTML = "";
  iosAgendaMount.appendChild(wrap);
}

function renderAgenda() {
  updateToolbarTitle();
  setActiveSegments();
  if (iosViewMode === "month") {
    renderMonthInAgenda();
  } else {
    renderWeekOrDay();
  }
}

function startNowTimer() {
  if (iosNowTimer) clearInterval(iosNowTimer);
  iosNowTimer = setInterval(() => {
    if (iosViewMode === "week" || iosViewMode === "day") renderAgenda();
  }, 60000);
}

function showListSection(show) {
  iosListVisible = !!show;
  const sec = document.getElementById("ios-list-section");
  if (!sec) return;
  sec.style.display = show ? "block" : "none";
  if (show && iosAgendaMount) {
    iosAgendaMount.innerHTML =
      '<p class="ios-agenda-list-hint">Vue liste ci-dessous. Utilise <strong>Jour</strong>, <strong>Semaine</strong> ou <strong>Mois</strong> pour retrouver l’agenda.</p>';
  } else {
    renderAgenda();
  }
}

async function loadEvents() {
  const events = await iosApi("events");
  iosEventsCache = events;
  if (iosEventsList) {
    iosEventsList.innerHTML = "";
    if (!events.length) {
      iosEventsList.innerHTML = "<div class='ios-agenda-intro'>Aucun événement.</div>";
    } else {
      events.forEach((evt) => {
        const card = document.createElement("div");
        card.className = "ios-event-card";
        if (evt.display_color && /^#[0-9A-Fa-f]{6}$/i.test(evt.display_color)) {
          card.style.borderLeft = `4px solid ${evt.display_color}`;
        }
        card.innerHTML = `
      <div class="ios-event-card-head">
        <div>
          <div class="ios-event-card-title">${escHtml(evt.title || "(Sans titre)")}</div>
          <div class="ios-event-card-meta">${escHtml(formatDate(evt.start_at))} → ${escHtml(formatDate(evt.end_at))}</div>
          <div class="ios-event-card-meta">${escHtml(evt.location || "")}</div>
        </div>
        <div class="ios-event-card-actions">
          <button type="button" class="ios-agenda-btn" data-edit="${evt.id}">Modifier</button>
          <button type="button" class="ios-agenda-btn" data-delete="${evt.id}">Supprimer</button>
        </div>
      </div>
    `;
        card.querySelector("[data-edit]").addEventListener("click", () => fillForm(evt));
        card.querySelector("[data-delete]").addEventListener("click", async () => {
          if (!confirm("Supprimer cet événement ?")) return;
          await iosApi("events", { method: "DELETE", body: { id: evt.id } });
          await loadEvents();
        });
        iosEventsList.appendChild(card);
      });
    }
  }
  if (!iosListVisible) {
    renderAgenda();
  }
}

async function loadSyncStatus() {
  if (!iosSyncStatus) return;
  const status = await iosApi("sync_status");
  iosSyncStatus.textContent = status.message;
}

async function loadCalendarPrefs() {
  const wrap = document.getElementById("ios-calendar-prefs-rows");
  const msg = document.getElementById("ios-calendar-prefs-msg");
  if (!wrap) return;
  if (msg) msg.textContent = "";
  wrap.innerHTML = "<span class=\"ios-agenda-intro\">Chargement des calendriers…</span>";
  try {
    const data = await iosApi("calendar_sources");
    const rows = data.calendars || [];
    wrap.innerHTML = "";
    if (!rows.length) {
      wrap.innerHTML =
        "<span class=\"ios-agenda-intro\">Aucun calendrier détecté. Configure iCloud dans Paramètres puis synchronise.</span>";
      return;
    }
    rows.forEach((row, idx) => {
      const id = `ios-cal-cb-${idx}`;
      const rowEl = document.createElement("div");
      rowEl.className = "ios-cal-pref-row";
      rowEl.dataset.urlKey = row.url_key;

      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.id = id;
      cb.checked = row.visible !== false;

      const lab = document.createElement("label");
      lab.className = "ios-cal-pref-label";
      lab.htmlFor = id;
      lab.textContent = row.display || row.url_key;

      const colInp = document.createElement("input");
      colInp.type = "color";
      colInp.className = "ios-cal-pref-color";
      colInp.value = row.color || suggestedColorForKey(row.url_key);
      colInp.title = "Couleur dans l’agenda";

      const autoBtn = document.createElement("button");
      autoBtn.type = "button";
      autoBtn.className = "ios-cal-pref-reset";
      autoBtn.textContent = "Palette auto";
      autoBtn.title = "Couleurs HouseHub par défaut (sans teinte iOS)";
      autoBtn.addEventListener("click", () => {
        rowEl.dataset.paletteAuto = "1";
        colInp.disabled = true;
        colInp.style.opacity = "0.35";
      });

      rowEl.appendChild(cb);
      rowEl.appendChild(lab);
      rowEl.appendChild(colInp);
      rowEl.appendChild(autoBtn);
      wrap.appendChild(rowEl);
    });
  } catch (e) {
    wrap.innerHTML = `<span class="ios-agenda-intro">${escHtml(e.message)}</span>`;
  }
}

async function saveCalendarPrefs() {
  const msg = document.getElementById("ios-calendar-prefs-msg");
  const wrap = document.getElementById("ios-calendar-prefs-rows");
  if (!wrap) return;
  const prefs = {};
  wrap.querySelectorAll(".ios-cal-pref-row").forEach((rowEl) => {
    const key = rowEl.dataset.urlKey;
    if (!key) return;
    const vis = rowEl.querySelector('input[type="checkbox"]')?.checked === true;
    const colInp = rowEl.querySelector(".ios-cal-pref-color");
    let color = null;
    if (!rowEl.dataset.paletteAuto && colInp && !colInp.disabled) {
      const v = colInp.value;
      if (v && /^#[0-9A-Fa-f]{6}$/i.test(v)) {
        color = v.startsWith("#") ? v.toLowerCase() : `#${v}`.toLowerCase();
      }
    }
    prefs[key] = { visible: vis, color };
  });
  try {
    await iosApi("calendar_prefs", { method: "POST", body: { prefs } });
    if (msg) msg.textContent = "Préférences enregistrées.";
    await loadEvents();
    await loadCalendarPrefs();
  } catch (e) {
    if (msg) msg.textContent = e.message;
  }
}

iosForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const idStr = document.getElementById("ios-event-id").value.trim();
  const payload = {
    title: document.getElementById("ios-title").value.trim(),
    location: document.getElementById("ios-location").value.trim(),
    description: document.getElementById("ios-description").value.trim(),
    start_at: localDatetimeToSql(document.getElementById("ios-start").value),
    end_at: localDatetimeToSql(document.getElementById("ios-end").value),
  };
  if (idStr) {
    payload.id = parseInt(idStr, 10);
    await iosApi("events", { method: "PUT", body: payload });
  } else {
    await iosApi("events", { method: "POST", body: payload });
  }
  resetForm();
  await loadEvents();
});

document.getElementById("ios-form-reset")?.addEventListener("click", resetForm);

document.getElementById("ios-sync-btn")?.addEventListener("click", async () => {
  const btn = document.getElementById("ios-sync-btn");
  btn.disabled = true;
  try {
    const data = await iosApi("sync", { method: "POST", body: {} });
    if (iosSyncStatus) iosSyncStatus.textContent = data.message;
    await loadEvents();
    await loadCalendarPrefs();
  } finally {
    btn.disabled = false;
  }
});

document.querySelectorAll(".ios-seg").forEach((btn) => {
  btn.addEventListener("click", () => {
    iosViewMode = /** @type {'day'|'week'|'month'} */ (btn.getAttribute("data-ios-view"));
    showListSection(false);
    startNowTimer();
  });
});

document.getElementById("ios-view-list")?.addEventListener("click", () => {
  showListSection(true);
  if (iosNowTimer) clearInterval(iosNowTimer);
});

document.getElementById("ios-agenda-prev")?.addEventListener("click", () => {
  if (iosViewMode === "day") iosCursor = addDays(iosCursor, -1);
  else if (iosViewMode === "week") iosCursor = addDays(iosCursor, -7);
  else iosCursor = addMonths(iosCursor, -1);
  renderAgenda();
});

document.getElementById("ios-agenda-next")?.addEventListener("click", () => {
  if (iosViewMode === "day") iosCursor = addDays(iosCursor, 1);
  else if (iosViewMode === "week") iosCursor = addDays(iosCursor, 7);
  else iosCursor = addMonths(iosCursor, 1);
  renderAgenda();
});

document.getElementById("ios-agenda-today")?.addEventListener("click", () => {
  iosCursor = new Date();
  showListSection(false);
  startNowTimer();
});

document.getElementById("ios-agenda-add")?.addEventListener("click", () => {
  resetForm();
  const now = new Date();
  now.setMinutes(Math.ceil(now.getMinutes() / 15) * 15, 0, 0);
  const end = new Date(now.getTime() + 3600000);
  const f = (d) => {
    const p = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
  };
  document.getElementById("ios-start").value = f(now);
  document.getElementById("ios-end").value = f(end);
  document.getElementById("ios-edit-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
  document.getElementById("ios-title")?.focus();
});

document.getElementById("ios-calendar-prefs-save")?.addEventListener("click", () => saveCalendarPrefs());

window.addEventListener("DOMContentLoaded", async () => {
  try {
    iosCursor = new Date();
    await loadEvents();
    await loadCalendarPrefs();
    await loadSyncStatus();
    showListSection(false);
    startNowTimer();
  } catch (e) {
    if (iosSyncStatus) iosSyncStatus.textContent = e.message;
    if (iosAgendaMount) iosAgendaMount.innerHTML = `<p class="ios-agenda-intro">${escHtml(e.message)}</p>`;
  }
});
