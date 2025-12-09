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
 * Calcule le n° de semaine dans l'année (1..),
 * Semaine 1 = semaine contenant le 1er janvier, début au lundi.
 */
function getWeekOfYear(date) {
  const year = date.getFullYear();
  const startOfYear = new Date(year, 0, 1);
  const dayOfWeek = startOfYear.getDay() || 7; // dimanche = 0 -> 7
  const diffToMonday = dayOfWeek === 1 ? 0 : dayOfWeek - 1;
  const firstWeekMonday = new Date(year, 0, 1 - diffToMonday);

  const diffMillis = date - firstWeekMonday;
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
      dayFlags: {
        mon: { schoolHoliday: false },
        tue: { schoolHoliday: false },
        wed: { schoolHoliday: false },
        thu: { schoolHoliday: false },
        fri: { schoolHoliday: false },
      },
      totals: {
        offCarole: 0,
        extraOffCarole: 0,
        centre: 0,
        avis: 0,
      },
      isSchoolHolidayWeek: false,
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

// === 3. Événements & vacances scolaires ===

const events = []; // on pourra y ajouter OFF_CAROLE, CENTRE, etc. plus tard

async function fetchSchoolHolidays(anneeScolaire, zoneLabel) {
  const baseUrl =
    "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records";

  // On filtre uniquement sur l'année scolaire et la zone
  const where = `annee_scolaire='${anneeScolaire}' AND zones LIKE '%${zoneLabel}%' AND population<>'Enseignants'`;

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

/**
 * Ajoute les vacances scolaires comme événements de type VACANCES_SCOLAIRES
 * ET remplit le tableau recap.
 */
function addSchoolHolidayEventsFromRecords(records, events) {
  const tbody = document.querySelector("#schoolHolidaysTable tbody");
  if (tbody) tbody.innerHTML = "";

  // 1) Normaliser et regrouper par periode (start_date, end_date, description)
  const groups = new Map();

  records.forEach((record) => {
    const startIso = record.start_date; // ISO string
    const endIso = record.end_date;
    const desc = record.description || "";
    const zones = record.zones || "";

    const key = `${startIso}|${endIso}|${desc}`;

    const existing = groups.get(key);
    if (!existing) {
      groups.set(key, {
        startIso,
        endIso,
        description: desc,
        zones, // pour Zone C, ça suffira
      });
    }
    // on ignore les académies (location) pour le tableau recap
  });

  // 2) Construire le tableau recap avec un seul record par groupe
  if (tbody) {
    Array.from(groups.values())
      .sort((a, b) => new Date(a.startIso) - new Date(b.startIso))
      .forEach((g) => {
        const start = new Date(g.startIso);
        const end = new Date(g.endIso);
        const startStr = formatDayMonth(start) + "/" + start.getFullYear();
        const endStr = formatDayMonth(end) + "/" + end.getFullYear();

        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${g.description}</td>
          <td>${startStr}</td>
          <td>${endStr}</td>
          <td>${g.zones}</td>
        `;
        tbody.appendChild(tr);
      });
  }

  // 3) Créer les événements jour par jour (VACANCES_SCOLAIRES)
  groups.forEach((g) => {
    const start = new Date(g.startIso);
    const end = new Date(g.endIso);
    let current = new Date(start);
    // end = date de reprise -> on s'arrête la veille
    while (current < end) {
      const year = current.getFullYear();
      const month = String(current.getMonth() + 1).padStart(2, "0");
      const day = String(current.getDate()).padStart(2, "0");
      const isoDate = `${year}-${month}-${day}`;
      events.push({
        date: isoDate,
        type: "VACANCES_SCOLAIRES",
        duration: 1,
      });
      current.setDate(current.getDate() + 1);
    }
  });
}

/**
 * Marque les jours & semaines de vacances scolaires dans weeks.
 */
function markSchoolHolidayDaysAndWeeks(weeks, events) {
  // reset
  weeks.forEach((w) => {
    w.isSchoolHolidayWeek = false;
    Object.keys(w.dayFlags).forEach((dayKey) => {
      w.dayFlags[dayKey].schoolHoliday = false;
    });
  });

  events.forEach((evt) => {
    if (evt.type !== "VACANCES_SCOLAIRES") return;

    const [y, m, d] = evt.date.split("-").map(Number);
    const evtDate = new Date(y, m - 1, d);

    weeks.forEach((w) => {
      const dayKeys = ["mon", "tue", "wed", "thu", "fri"];
      let hasHolidayInWeek = false;

      dayKeys.forEach((dayKey) => {
        const dayDate = w.dayDates[dayKey];
        if (
          dayDate.getFullYear() === evtDate.getFullYear() &&
          dayDate.getMonth() === evtDate.getMonth() &&
          dayDate.getDate() === evtDate.getDate()
        ) {
          w.dayFlags[dayKey].schoolHoliday = true;
          hasHolidayInWeek = true;
        }
      });

      if (hasHolidayInWeek) {
        w.isSchoolHolidayWeek = true;
      }
    });
  });
}

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
  const showOnlySchoolHoliday = document.getElementById(
    "showOnlySchoolHoliday"
  )?.checked;

  const monthRowRendered = {};

  weeks.forEach((week) => {
    if (showOnlyCaroleOff && week.totals.offCarole === 0) return;
    if (showOnlySchoolHoliday && !week.isSchoolHolidayWeek) return;

    const tr = document.createElement("tr");

    // Colonne Mois
    if (!monthRowRendered[week.monthKey]) {
      monthRowRendered[week.monthKey] = true;
      const tdMonth = document.createElement("td");
      tdMonth.rowSpan = monthSpans[week.monthKey] || 1;
      tdMonth.textContent = week.monthName;
      tr.appendChild(tdMonth);
    }

    // Colonne Semaine
    const tdWeek = document.createElement("td");
    tdWeek.textContent = week.weekLabel;
    tr.appendChild(tdWeek);

    // Jours (Lun -> Ven) avec coloration jours de vacances
    ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
      const td = document.createElement("td");
      td.textContent = week.days[dayKey];
      if (week.dayFlags[dayKey].schoolHoliday) {
        td.classList.add("fc-day--school-holiday");
      }
      tr.appendChild(td);
    });

    // Totaux # Off / Extra / Centre / Avis (pour l'instant à 0)
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

    // Alex total & détail
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

// === 6. Résumé (toujours 0 utilisés pour l'instant) ===

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

async function initFamilyCalendar() {
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

  // Charger vacances scolaires 2025-2026, Zone C
  const holidaysRecords = await fetchSchoolHolidays("2025-2026", "Zone C");
  addSchoolHolidayEventsFromRecords(holidaysRecords, events);
  markSchoolHolidayDaysAndWeeks(weeks, events);

  renderTable();
}

document.addEventListener("DOMContentLoaded", () => {
  initFamilyCalendar().catch((err) => console.error(err));
});
