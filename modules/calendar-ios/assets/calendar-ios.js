const iosEventsList = document.getElementById("ios-events-list");
const iosSyncStatus = document.getElementById("ios-sync-status");
const iosForm = document.getElementById("ios-event-form");
const iosCalGrid = document.getElementById("ios-cal-grid");
const iosMonthLabel = document.getElementById("ios-month-label");
const iosDayDetail = document.getElementById("ios-day-detail");

let iosEventsCache = [];
/** @type {Date} mois affiché (jour ignoré) */
let iosMonthCursor = new Date();
let iosView = "month";

function escHtml(s) {
  const d = document.createElement("div");
  d.textContent = s ?? "";
  return d.innerHTML;
}

/** SQL datetime → valeur input datetime-local */
function sqlToDatetimeLocal(s) {
  if (!s) return "";
  const m = /^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})/.exec(String(s).trim());
  if (!m) return "";
  return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}`;
}

/** datetime-local → SQL pour l’API */
function localDatetimeToSql(s) {
  if (!s) return "";
  const t = s.includes("T") ? s.replace("T", " ") : s;
  return t.length === 16 ? `${t}:00` : t;
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
  const d = new Date(String(iso).replace(" ", "T"));
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
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
    const t = new Date(String(evt.start_at).replace(" ", "T"));
    return !Number.isNaN(t.getTime()) && t.getFullYear() === y && t.getMonth() === m && t.getDate() === d;
  });
}

function renderMonthGrid() {
  if (!iosCalGrid) return;
  const y = iosMonthCursor.getFullYear();
  const m = iosMonthCursor.getMonth();
  if (iosMonthLabel) {
    iosMonthLabel.textContent = new Date(y, m, 1).toLocaleDateString("fr-FR", { month: "long", year: "numeric" });
  }

  const first = new Date(y, m, 1);
  const last = new Date(y, m + 1, 0);
  let startWeekday = first.getDay() - 1;
  if (startWeekday < 0) startWeekday = 6;

  const frag = document.createDocumentFragment();
  for (let i = 0; i < startWeekday; i++) {
    const c = document.createElement("div");
    c.className = "ios-cal-cell ios-cal-cell--pad";
    frag.appendChild(c);
  }

  for (let d = 1; d <= last.getDate(); d++) {
    const cell = document.createElement("button");
    cell.type = "button";
    cell.className = "ios-cal-cell";
    const dayEvents = eventsForLocalDay(y, m, d);
    if (dayEvents.length) cell.classList.add("ios-cal-cell--has");
    const today = new Date();
    if (y === today.getFullYear() && m === today.getMonth() && d === today.getDate()) {
      cell.classList.add("ios-cal-cell--today");
    }
    cell.innerHTML = `<span class="ios-cal-daynum">${d}</span>`;
    if (dayEvents.length) {
      const dots = document.createElement("div");
      dots.className = "ios-cal-dots";
      dayEvents.slice(0, 4).forEach(() => {
        const dot = document.createElement("span");
        dot.className = "ios-cal-dot";
        dots.appendChild(dot);
      });
      cell.appendChild(dots);
    }
    cell.addEventListener("click", () => showDayDetail(y, m, d));
    frag.appendChild(cell);
  }
  iosCalGrid.innerHTML = "";
  iosCalGrid.appendChild(frag);
}

function showDayDetail(y, mo, d) {
  if (!iosDayDetail) return;
  const list = eventsForLocalDay(y, mo, d);
  if (!list.length) {
    iosDayDetail.innerHTML = `<span class="pf-muted-note">Aucun événement le ${d}/${mo + 1}/${y}.</span>`;
    return;
  }
  const label = new Date(y, mo, d).toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
  iosDayDetail.innerHTML = `<div class="ios-day-detail-title">${escHtml(label)}</div>` + list.map((evt) => `
    <div class="ios-day-row">
      <span class="ios-day-row-time">${escHtml(String(evt.start_at).slice(11, 16) || "")}</span>
      <span class="ios-day-row-title">${escHtml(evt.title || "(Sans titre)")}</span>
      <span class="ios-day-row-actions">
        <button type="button" class="pf-btn btn-secondary btn-sm" data-ed="${evt.id}">Modifier</button>
        <button type="button" class="pf-btn btn-secondary btn-sm" data-del="${evt.id}">Supprimer</button>
      </span>
    </div>`).join("");
  iosDayDetail.querySelectorAll("[data-ed]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = parseInt(btn.getAttribute("data-ed"), 10);
      const evt = iosEventsCache.find((e) => e.id === id);
      if (evt) {
        fillForm(evt);
        document.getElementById("ios-edit-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });
  iosDayDetail.querySelectorAll("[data-del]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = parseInt(btn.getAttribute("data-del"), 10);
      if (!confirm("Supprimer cet événement ?")) return;
      await iosApi("events", { method: "DELETE", body: { id } });
      await loadEvents();
      showDayDetail(y, mo, d);
    });
  });
}

function setView(mode) {
  iosView = mode;
  const mEl = document.getElementById("ios-view-month");
  const lEl = document.getElementById("ios-view-list");
  const wrap = document.getElementById("ios-month-section");
  const listSec = document.getElementById("ios-list-section");
  mEl?.classList.toggle("ios-view-btn--active", mode === "month");
  lEl?.classList.toggle("ios-view-btn--active", mode === "list");
  if (wrap) wrap.style.display = mode === "month" ? "block" : "none";
  if (listSec) listSec.style.display = mode === "list" ? "block" : "none";
  if (mode === "month") renderMonthGrid();
}

async function loadEvents() {
  const events = await iosApi("events");
  iosEventsCache = events;
  iosEventsList.innerHTML = "";
  if (!events.length) {
    iosEventsList.innerHTML = "<div class='pf-muted-note'>Aucun événement.</div>";
  } else {
    events.forEach((evt) => {
      const card = document.createElement("div");
      card.className = "ios-event-card";
      card.innerHTML = `
      <div class="ios-event-card-head">
        <div>
          <div class="ios-event-card-title">${escHtml(evt.title || "(Sans titre)")}</div>
          <div class="ios-event-card-meta">${escHtml(formatDate(evt.start_at))} → ${escHtml(formatDate(evt.end_at))}</div>
          <div class="ios-event-card-meta">${escHtml(evt.location || "")}</div>
        </div>
        <div class="ios-event-card-actions">
          <button type="button" class="pf-btn btn-secondary" data-edit="${evt.id}">Modifier</button>
          <button type="button" class="pf-btn btn-secondary" data-delete="${evt.id}">Supprimer</button>
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
  if (iosView === "month") renderMonthGrid();
}

async function loadSyncStatus() {
  const status = await iosApi("sync_status");
  iosSyncStatus.textContent = status.message;
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
    iosSyncStatus.textContent = data.message;
    await loadEvents();
  } finally {
    btn.disabled = false;
  }
});

document.getElementById("ios-month-prev")?.addEventListener("click", () => {
  iosMonthCursor = new Date(iosMonthCursor.getFullYear(), iosMonthCursor.getMonth() - 1, 1);
  renderMonthGrid();
});

document.getElementById("ios-month-next")?.addEventListener("click", () => {
  iosMonthCursor = new Date(iosMonthCursor.getFullYear(), iosMonthCursor.getMonth() + 1, 1);
  renderMonthGrid();
});

document.getElementById("ios-view-month")?.addEventListener("click", () => setView("month"));
document.getElementById("ios-view-list")?.addEventListener("click", () => setView("list"));

window.addEventListener("DOMContentLoaded", async () => {
  try {
    await loadEvents();
    await loadSyncStatus();
    setView("month");
  } catch (e) {
    iosSyncStatus.textContent = e.message;
  }
});
