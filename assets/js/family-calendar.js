// assets/js/family-calendar.js

// === VARIABLES GLOBALES POUR LA SÉLECTION ===
let isSelecting = false;
let selectedCells = [];
const events = []; // Contient TOUS les événements (vacances, off carole, centre, avis)

// === 1. Utilitaires dates ===
// ... (garder les fonctions formatDayMonth, getMonthNameFr, getWeekOfYear)
function formatDayMonth(date) {
  const d = String(date.getDate()).padStart(2, "0");
  const m = String(date.getMonth() + 1).padStart(2, "0");
  return `${d}/${m}`;
}

function getMonthNameFr(monthIndex) {
  const map = {
    0: "Janvier",
    1: "Fevrier",
    2: "Mars",
    3: "Avril",
    4: "Mai",
    5: "Juin",
    6: "Juillet",
    7: "Aout",
    8: "Septembre",
    9: "Octobre",
    10: "Novembre",
    11: "Decembre",
  };
  return map[monthIndex] || "";
}

function getWeekOfYear(date) {
  const year = date.getFullYear();
  const startOfYear = new Date(year, 0, 1);
  const dayOfWeek = startOfYear.getDay() || 7;
  const diffToMonday = dayOfWeek === 1 ? 0 : dayOfWeek - 1;
  const firstWeekMonday = new Date(year, 0, 1 - diffToMonday);
  const diffMillis = date - firstWeekMonday;
  const diffDays = Math.floor(diffMillis / (1000 * 60 * 60 * 24));
  return Math.floor(diffDays / 7) + 1;
}

// === 2. Génération des semaines ===
function generateWeeks() {
  const weeks = [];
  const start = new Date(2025, 8, 1);
  const end = new Date(2026, 7, 31);
  let current = new Date(start);
  while (current.getDay() !== 1) {
    current.setDate(current.getDate() + 1);
  }
  while (current <= end) {
    const monday = new Date(current);
    const tuesday = new Date(current);
    tuesday.setDate(monday.getDate() + 1);
    const wednesday = new Date(current);
    wednesday.setDate(monday.getDate() + 2);
    const thursday = new Date(current);
    thursday.setDate(monday.getDate() + 3);
    const friday = new Date(current);
    friday.setDate(monday.getDate() + 4);
    const year = monday.getFullYear();
    const weekOfYear = getWeekOfYear(monday);
    const weekId = `${year}-W${String(weekOfYear).padStart(2, "0")}`;
    const monthIndex = monday.getMonth();
    const monthKey = `${year}-${String(monthIndex + 1).padStart(2, "0")}`;
    weeks.push({
      id: weekId,
      year,
      weekOfYear,
      monthKey,
      monthName: getMonthNameFr(monthIndex),
      weekLabel: `W${weekOfYear}`,
      days: {
        mon: formatDayMonth(monday),
        tue: formatDayMonth(tuesday),
        wed: formatDayMonth(wednesday),
        thu: formatDayMonth(thursday),
        fri: formatDayMonth(friday),
      },
      dayDates: {
        mon: monday,
        tue: tuesday,
        wed: wednesday,
        thu: thursday,
        fri: friday,
      },
      dayFlags: {
        mon: { schoolHoliday: false },
        tue: { schoolHoliday: false },
        wed: { schoolHoliday: false },
        thu: { schoolHoliday: false },
        fri: { schoolHoliday: false },
      },
      totals: { offCarole: 0, extraOffCarole: 0, centre: 0, avis: 0 },
      isSchoolHolidayWeek: false,
      alex: { total: 0, detail: "" },
      laia: { total: 0, detail: "" },
    });
    current.setDate(current.getDate() + 7);
  }
  return weeks;
}
const weeks = generateWeeks();

// === 3. Gestion des événements (Vacances, Off, Centre, Avis) ===

async function fetchSchoolHolidays(anneeScolaire, zoneLabel) {
  const baseUrl =
    "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records";
  const where = `annee_scolaire='${anneeScolaire}' AND zones LIKE '%${zoneLabel}%'`;
  const params = new URLSearchParams({
    where,
    limit: "100",
    order_by: "start_date",
  });
  const url = `${baseUrl}?${params.toString()}`;
  const res = await fetch(url);
  if (!res.ok) {
    console.error("Erreur API vacances scolaires", res.status, res.statusText);
    return [];
  }
  const data = await res.json();
  return data.results || [];
}

function addSchoolHolidayEventsFromRecords(records, events) {
  const tbody = document.querySelector("#schoolHolidaysTable tbody");
  if (tbody) tbody.innerHTML = "";
  const groups = new Map();
  records.forEach((record) => {
    const key = `${record.start_date}|${record.end_date}|${record.description}`;
    if (!groups.has(key)) {
      groups.set(key, {
        startIso: record.start_date,
        endIso: record.end_date,
        description: record.description || "",
        zones: record.zones || "",
      });
    }
  });
  if (tbody) {
    Array.from(groups.values())
      .sort((a, b) => new Date(a.startIso) - new Date(b.startIso))
      .forEach((g) => {
        const firstDayVacation = new Date(
          g.startIso.substring(0, 10) + "T00:00:00"
        );
        firstDayVacation.setDate(firstDayVacation.getDate() + 1);
        const lastDayVacation = new Date(
          g.endIso.substring(0, 10) + "T00:00:00"
        );
        const repriseDate = new Date(lastDayVacation);
        repriseDate.setDate(repriseDate.getDate() + 1);
        const startStr =
          formatDayMonth(firstDayVacation) +
          "/" +
          firstDayVacation.getFullYear();
        const endStr =
          formatDayMonth(repriseDate) + "/" + repriseDate.getFullYear();
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${g.description}</td><td>${startStr}</td><td>${endStr}</td><td>${g.zones}</td>`;
        tbody.appendChild(tr);

        let current = new Date(firstDayVacation);
        while (current <= lastDayVacation) {
          const isoDate = `${current.getFullYear()}-${String(
            current.getMonth() + 1
          ).padStart(2, "0")}-${String(current.getDate()).padStart(2, "0")}`;
          events.push({
            date: isoDate,
            type: "VACANCES_SCOLAIRES",
            duration: 1,
          });
          current.setDate(current.getDate() + 1);
        }
      });
  }
}

/**
 * Marque les jours de vacances et recalcule les totaux pour Off Carole, Centre, Avis
 */
function reprocessEvents(weeks, events) {
  // Reset all flags and totals
  weeks.forEach((w) => {
    w.isSchoolHolidayWeek = false;
    Object.keys(w.dayFlags).forEach(
      (dayKey) => (w.dayFlags[dayKey].schoolHoliday = false)
    );
    w.totals = { offCarole: 0, extraOffCarole: 0, centre: 0, avis: 0 };
  });

  // Apply each event to the corresponding week
  events.forEach((evt) => {
    const [y, m, d] = evt.date.split("-").map(Number);
    const evtDate = new Date(y, m - 1, d);

    const week = weeks.find(
      (w) => evtDate >= w.dayDates.mon && evtDate <= w.dayDates.fri
    );
    if (!week) return;

    const dur = evt.duration || 1;

    switch (evt.type) {
      case "VACANCES_SCOLAIRES":
        const dayKey = Object.keys(week.dayDates).find(
          (key) => week.dayDates[key].toDateString() === evtDate.toDateString()
        );
        if (dayKey) {
          week.dayFlags[dayKey].schoolHoliday = true;
          week.isSchoolHolidayWeek = true;
        }
        break;
      case "OFF_CAROLE":
        week.totals.offCarole += dur;
        break;
      case "EXTRA_OFF_CAROLE":
        week.totals.extraOffCarole += dur;
        break;
      case "CENTRE":
        week.totals.centre += dur;
        break;
      case "AVIS":
        week.totals.avis += dur;
        break;
      default:
        break;
    }
  });
}

// === 4. Rendu et logique d'affichage ===

function computeMonthSpans(weeks) {
  const counts = {};
  weeks.forEach((w) => {
    counts[w.monthKey] = (counts[w.monthKey] || 0) + 1;
  });
  return counts;
}
const monthSpans = computeMonthSpans(weeks);

function renderTable() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;
  planningBody.innerHTML = "";

  const showOnlyCaroleOff =
    document.getElementById("showOnlyCaroleOff")?.checked;
  const showOnlySchoolHoliday = document.getElementById(
    "showOnlySchoolHoliday"
  )?.checked;
  const monthRowRendered = {};

  weeks.forEach((week) => {
    if (showOnlyCaroleOff && week.totals.offCarole === 0) return;
    if (showOnlySchoolHoliday && !week.isSchoolHolidayWeek) return;

    const tr = document.createElement("tr");

    if (!monthRowRendered[week.monthKey]) {
      monthRowRendered[week.monthKey] = true;
      const tdMonth = document.createElement("td");
      tdMonth.rowSpan = monthSpans[week.monthKey] || 1;
      tdMonth.textContent = week.monthName;
      tr.appendChild(tdMonth);
    }

    const tdWeek = document.createElement("td");
    tdWeek.textContent = week.weekLabel;
    tr.appendChild(tdWeek);

    ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
      const td = document.createElement("td");
      const dayDate = week.dayDates[dayKey];
      const isoDate = `${dayDate.getFullYear()}-${String(
        dayDate.getMonth() + 1
      ).padStart(2, "0")}-${String(dayDate.getDate()).padStart(2, "0")}`;
      td.dataset.date = isoDate; // IMPORTANT: on ajoute la date pour la retrouver
      td.textContent = week.days[dayKey];
      if (week.dayFlags[dayKey].schoolHoliday) {
        td.classList.add("fc-day--school-holiday");
      }
      tr.appendChild(td);
    });

    const formatTotal = (total) =>
      total > 0 ? (Number.isInteger(total) ? total : total.toFixed(1)) : "";
    tr.innerHTML += `
      <td>${formatTotal(week.totals.offCarole)}</td>
      <td>${formatTotal(week.totals.extraOffCarole)}</td>
      <td>${formatTotal(week.totals.centre)}</td>
      <td>${formatTotal(week.totals.avis)}</td>
      <td>${formatTotal(week.alex.total)}</td>
      <td>${week.alex.detail || ""}</td>
      <td>${formatTotal(week.laia.total)}</td>
      <td>${week.laia.detail || ""}</td>
    `;

    planningBody.appendChild(tr);
  });
  updateSummary();
}

function updateSummary() {
  // ... (garder la fonction updateSummary telle quelle)
}

// === 5. Logique de sélection et menu contextuel ===

function clearSelection() {
  const menu = document.getElementById("selectionMenu");
  if (menu) menu.style.display = "none";
  selectedCells.forEach((cell) => cell.classList.remove("fc-day--selected"));
  selectedCells = [];
}

function showSelectionMenu(e) {
  const menu = document.getElementById("selectionMenu");
  if (!menu) return;
  menu.style.left = `${e.pageX + 5}px`;
  menu.style.top = `${e.pageY + 5}px`;
  menu.style.display = "block";
}

function applyEventTypeToSelection(eventType) {
  if (selectedCells.length === 0) return;

  selectedCells.forEach((cell) => {
    const date = cell.dataset.date;
    if (date) {
      // Pour l'instant, on ajoute un événement par jour. On pourrait optimiser plus tard.
      events.push({
        date: date,
        type: eventType,
        duration: 1,
      });
    }
  });

  reprocessEvents(weeks, events);
  renderTable(); // Redessine la table avec les nouveaux totaux
  clearSelection();
}

// === 6. Init ===

async function initFamilyCalendar() {
  if (!document.getElementById("planningBody")) return;

  // Écouteurs pour les soldes et filtres
  [
    "alexCpInit",
    "alexRttInit",
    "alexJaInit",
    "laiaCpInit",
    "laiaRttInit",
    "laiaJaInit",
  ].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", updateSummary);
  });
  ["showOnlyCaroleOff", "showOnlySchoolHoliday"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", renderTable);
  });

  // Écouteurs pour la sélection par cliquer-glisser
  const planningBody = document.getElementById("planningBody");
  planningBody.addEventListener("mousedown", (e) => {
    if (e.target.tagName === "TD" && e.target.dataset.date) {
      isSelecting = true;
      clearSelection();
      e.target.classList.add("fc-day--selected");
      selectedCells.push(e.target);
      e.preventDefault();
    }
  });

  planningBody.addEventListener("mousemove", (e) => {
    if (isSelecting && e.target.tagName === "TD" && e.target.dataset.date) {
      if (!selectedCells.includes(e.target)) {
        e.target.classList.add("fc-day--selected");
        selectedCells.push(e.target);
      }
    }
  });

  document.addEventListener("mouseup", (e) => {
    if (isSelecting) {
      isSelecting = false;
      if (selectedCells.length > 0) {
        showSelectionMenu(e);
      }
    }
  });

  // Écouteur pour les clics hors du menu pour le fermer
  document.addEventListener("click", (e) => {
    const menu = document.getElementById("selectionMenu");
    if (menu && !menu.contains(e.target) && selectedCells.length > 0) {
      // Si on clique hors du menu APRES une sélection, on la nettoie
      if (menu.style.display === "block") {
        clearSelection();
      }
    }
  });

  // Écouteur pour les boutons du menu
  const menu = document.getElementById("selectionMenu");
  if (menu) {
    menu.addEventListener("click", (e) => {
      if (e.target.tagName === "BUTTON" && e.target.dataset.type) {
        const eventType = e.target.dataset.type;
        applyEventTypeToSelection(eventType);
      }
    });
  }

  // Initialisation des données
  const holidaysRecords = await fetchSchoolHolidays("2025-2026", "Zone C");
  addSchoolHolidayEventsFromRecords(holidaysRecords, events);
  reprocessEvents(weeks, events); // Appliquer aussi les vacances aux totaux si besoin (ici juste la couleur)
  renderTable();
}

document.addEventListener("DOMContentLoaded", () => {
  initFamilyCalendar().catch((err) => console.error(err));
});
