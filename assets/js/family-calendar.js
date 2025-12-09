// assets/js/family-calendar.js

// === 1. Utilitaires dates ===

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

/**
 * Calcule le numéro de semaine dans l'année (1..52/53),
 * en prenant le lundi comme début de semaine.
 */
function getWeekOfYear(date) {
  const year = date.getFullYear();
  const startOfYear = new Date(year, 0, 1);
  // se placer au lundi de la semaine contenant le 1er janvier
  const day = startOfYear.getDay() || 7; // dimanche=0 => 7
  const diffToMonday = day > 1 ? day - 1 : 0;
  const firstMonday = new Date(year, 0, 1 - diffToMonday);

  const diffMillis = date - firstMonday;
  const diffDays = Math.floor(diffMillis / (1000 * 60 * 60 * 24));
  return Math.floor(diffDays / 7) + 1;
}

// === 2. Génération des semaines ===

function generateWeeks() {
  const weeks = [];

  const start = new Date(2025, 8, 1); // 1er sept 2025
  const end = new Date(2026, 7, 31); // 31 aout 2026

  let current = new Date(start);

  // se placer sur le premier lundi >= 1er septembre 2025
  while (current.getDay() !== 1) {
    // 1 = lundi
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
    const monthName = getMonthNameFr(monthIndex);

    weeks.push({
      id: weekId,
      year,
      weekOfYear,
      monthKey,
      monthName,
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
      totals: {
        offCarole: 0,
        extraOffCarole: 0,
        centre: 0,
        avis: 0,
      },
      alex: {
        total: 0,
        detail: "",
      },
      laia: {
        total: 0,
        detail: "",
      },
    });

    current.setDate(current.getDate() + 7);
  }

  return weeks;
}

const weeks = generateWeeks();

// === 3. Événements calendrier (exemple, plus tard ce sera ton UI/DB) ===

/**
 * Exemple de liste d'événements. Plus tard :
 * - tu auras une UI pour ajouter/supprimer ces événements
 * - tu les chargeras depuis/vers la DB.
 */
const events = [
  // Exemple : Carole off le 15/09/2025
  { date: "2025-09-15", type: "OFF_CAROLE", duration: 1 },
  // Exemple : Centre le 20/10/2025
  { date: "2025-10-20", type: "CENTRE", duration: 1 },
  // etc.
];

/**
 * Recalcule les totaux par semaine à partir des events.
 */
function recomputeTotalsFromEvents(weeks, events) {
  // reset
  weeks.forEach((w) => {
    w.totals.offCarole = 0;
    w.totals.extraOffCarole = 0;
    w.totals.centre = 0;
    w.totals.avis = 0;
  });

  events.forEach((evt) => {
    const [y, m, d] = evt.date.split("-").map(Number);
    const evtDate = new Date(y, m - 1, d);

    // trouver la semaine qui contient evtDate (entre lundi et vendredi)
    const week = weeks.find((w) => {
      const md = w.dayDates.mon;
      const fd = w.dayDates.fri;
      return evtDate >= md && evtDate <= fd;
    });
    if (!week) return;

    const dur = evt.duration || 1;

    switch (evt.type) {
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

// première agrégation
recomputeTotalsFromEvents(weeks, events);

// === 4. Calcul des rowspans pour la colonne Mois ===

function computeMonthSpans(weeks) {
  const counts = {};
  weeks.forEach((w) => {
    counts[w.monthKey] = (counts[w.monthKey] || 0) + 1;
  });
  return counts;
}

const monthSpans = computeMonthSpans(weeks);

// === 5. Rendu du tableau ===

function renderTable() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;

  planningBody.innerHTML = "";

  const showOnlyCaroleOff =
    document.getElementById("showOnlyCaroleOff")?.checked;
  // showOnlySchoolHoliday est ignoré pour l'instant

  const monthRowRendered = {};

  weeks.forEach((week) => {
    if (showOnlyCaroleOff && week.totals.offCarole === 0) return;

    const tr = document.createElement("tr");

    // Colonne Mois (rowspan sur toutes les semaines du mois)
    if (!monthRowRendered[week.monthKey]) {
      monthRowRendered[week.monthKey] = true;
      const tdMonth = document.createElement("td");
      tdMonth.rowSpan = monthSpans[week.monthKey] || 1;
      tdMonth.textContent = week.monthName;
      tr.appendChild(tdMonth);
    }

    // Semaine
    const tdWeek = document.createElement("td");
    tdWeek.textContent = week.weekLabel;
    tr.appendChild(tdWeek);

    // Jours
    ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
      const td = document.createElement("td");
      td.textContent = week.days[dayKey];
      tr.appendChild(td);
    });

    // Totaux # Off Carole, # Extra off, #Centre, #Avis
    const tdOff = document.createElement("td");
    tdOff.textContent = week.totals.offCarole.toFixed(2).replace(/\.00$/, "");
    tr.appendChild(tdOff);

    const tdExtraOff = document.createElement("td");
    tdExtraOff.textContent = week.totals.extraOffCarole
      .toFixed(2)
      .replace(/\.00$/, "");
    tr.appendChild(tdExtraOff);

    const tdCentre = document.createElement("td");
    tdCentre.textContent = week.totals.centre.toFixed(2).replace(/\.00$/, "");
    tr.appendChild(tdCentre);

    const tdAvis = document.createElement("td");
    tdAvis.textContent = week.totals.avis.toFixed(2).replace(/\.00$/, "");
    tr.appendChild(tdAvis);

    // Alex total & détail (pour l'instant, juste placeholders)
    const tdAlexTotal = document.createElement("td");
    tdAlexTotal.textContent = week.alex.total.toFixed(2).replace(/\.00$/, "");
    tr.appendChild(tdAlexTotal);

    const tdAlexDetail = document.createElement("td");
    tdAlexDetail.textContent = week.alex.detail || "";
    tr.appendChild(tdAlexDetail);

    // Laia total & détail
    const tdLaiaTotal = document.createElement("td");
    tdLaiaTotal.textContent = week.laia.total.toFixed(2).replace(/\.00$/, "");
    tr.appendChild(tdLaiaTotal);

    const tdLaiaDetail = document.createElement("td");
    tdLaiaDetail.textContent = week.laia.detail || "";
    tr.appendChild(tdLaiaDetail);

    planningBody.appendChild(tr);
  });

  updateSummary();
}

// === 6. Résumé (encore 0 utilisés pour l’instant) ===

function updateSummary() {
  const summaryDiv = document.getElementById("summaryText");
  if (!summaryDiv) return;

  const alexCpInit = parseFloat(
    document.getElementById("alexCpInit")?.value || "0"
  );
  const alexRttInit = parseFloat(
    document.getElementById("alexRttInit")?.value || "0"
  );
  const alexJaInit = parseFloat(
    document.getElementById("alexJaInit")?.value || "0"
  );

  const laiaCpInit = parseFloat(
    document.getElementById("laiaCpInit")?.value || "0"
  );
  const laiaRttInit = parseFloat(
    document.getElementById("laiaRttInit")?.value || "0"
  );
  const laiaJaInit = parseFloat(
    document.getElementById("laiaJaInit")?.value || "0"
  );

  const alexCpUsed = 0;
  const alexRttUsed = 0;
  const alexJaUsed = 0;

  const laiaCpUsed = 0;
  const laiaRttUsed = 0;
  const laiaJaUsed = 0;

  const alexCpLeft = alexCpInit - alexCpUsed;
  const alexRttLeft = alexRttInit - alexRttUsed;
  const alexJaLeft = alexJaInit - alexJaUsed;

  const laiaCpLeft = laiaCpInit - laiaCpUsed;
  const laiaRttLeft = laiaRttInit - laiaRttUsed;
  const laiaJaLeft = laiaJaInit - laiaJaUsed;

  summaryDiv.innerHTML = `
    <p><strong>Alex</strong><br>
    CP utilises : ${alexCpUsed.toFixed(2)} / ${alexCpInit.toFixed(
    2
  )} (reste ${alexCpLeft.toFixed(2)})<br>
    RTT utilises : ${alexRttUsed.toFixed(2)} / ${alexRttInit.toFixed(
    2
  )} (reste ${alexRttLeft.toFixed(2)})<br>
    JA utilises : ${alexJaUsed.toFixed(2)} / ${alexJaInit.toFixed(
    2
  )} (reste ${alexJaLeft.toFixed(2)})</p>

    <p><strong>Laia</strong><br>
    CP utilises : ${laiaCpUsed.toFixed(2)} / ${laiaCpInit.toFixed(
    2
  )} (reste ${laiaCpLeft.toFixed(2)})<br>
    RTT utilises : ${laiaRttUsed.toFixed(2)} / ${laiaRttInit.toFixed(
    2
  )} (reste ${laiaRttLeft.toFixed(2)})<br>
    JA utilises : ${laiaJaUsed.toFixed(2)} / ${laiaJaInit.toFixed(
    2
  )} (reste ${laiaJaLeft.toFixed(2)})</p>
  `;
}

// === 7. Init ===

function initFamilyCalendar() {
  if (!document.getElementById("planningBody")) return;

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

  const showOnlyCaroleOff = document.getElementById("showOnlyCaroleOff");
  if (showOnlyCaroleOff) {
    showOnlyCaroleOff.addEventListener("change", renderTable);
  }
  const showOnlySchoolHoliday = document.getElementById(
    "showOnlySchoolHoliday"
  );
  if (showOnlySchoolHoliday) {
    showOnlySchoolHoliday.addEventListener("change", renderTable);
  }

  renderTable();
}

document.addEventListener("DOMContentLoaded", initFamilyCalendar);
