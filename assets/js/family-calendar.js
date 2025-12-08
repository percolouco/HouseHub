// assets/js/family-calendar.js

// === 1. Utilitaires dates ===

/**
 * Formate une Date en "dd/mm".
 */
function formatDayMonth(date) {
  const d = String(date.getDate()).padStart(2, "0");
  const m = String(date.getMonth() + 1).padStart(2, "0");
  return `${d}/${m}`;
}

/**
 * Retourne le nom de mois en français à partir du monthIndex JS (0..11).
 */
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
 * Genere les semaines de la 1ere semaine de septembre 2025
 * a la derniere semaine d'aout 2026.
 * Chaque element contient:
 * - monthKey (ex: "2025-09")
 * - monthName (ex: "Septembre")
 * - weekLabel (W1, W2, ...)
 * - days: { mon, tue, wed, thu, fri } en "dd/mm"
 * - offCarole, extraOffCarole, centre, avis
 * - alex: { total, detail }
 * - laia: { total, detail }
 */
function generateWeeks() {
  const weeks = [];

  const start = new Date(2025, 8, 1); // 1er septembre 2025 (mois 8 car 0-based)
  const end = new Date(2026, 7, 31); // 31 aout 2026 (mois 7)

  let current = new Date(start);

  // se placer sur le premier lundi >= 1er septembre 2025
  while (current.getDay() !== 1) {
    // 1 = lundi
    current.setDate(current.getDate() + 1);
  }

  let weekCount = 1;

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

    const monthIndex = monday.getMonth();
    const year = monday.getFullYear();
    const monthKey = `${year}-${String(monthIndex + 1).padStart(2, "0")}`;
    const monthName = getMonthNameFr(monthIndex);

    weeks.push({
      monthKey,
      monthName,
      weekLabel: `W${weekCount}`,
      days: {
        mon: formatDayMonth(monday),
        tue: formatDayMonth(tuesday),
        wed: formatDayMonth(wednesday),
        thu: formatDayMonth(thursday),
        fri: formatDayMonth(friday),
      },
      offCarole: 0,
      extraOffCarole: 0,
      centre: 0,
      avis: 0,
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
    weekCount++;
  }

  return weeks;
}

const weeks = generateWeeks();

// === 2. Calcul des rowspans pour la colonne Mois ===

function computeMonthSpans(weeks) {
  const counts = {};
  weeks.forEach((w) => {
    counts[w.monthKey] = (counts[w.monthKey] || 0) + 1;
  });
  return counts;
}

const monthSpans = computeMonthSpans(weeks);

// === 3. Rendu du tableau ===

function renderTable() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;

  planningBody.innerHTML = "";

  const showOnlyCaroleOff =
    document.getElementById("showOnlyCaroleOff")?.checked;
  // showOnlySchoolHoliday est ignore pour l'instant

  const monthRowRendered = {}; // monthKey -> bool

  weeks.forEach((week, index) => {
    if (showOnlyCaroleOff && week.offCarole === 0) return;

    const tr = document.createElement("tr");

    // Colonne Mois avec rowspan uniquement pour la 1ere ligne du mois
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

    // Jours Lundi -> Vendredi
    ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
      const td = document.createElement("td");
      td.textContent = week.days[dayKey];
      tr.appendChild(td);
    });

    // # Off Carole
    const tdOff = document.createElement("td");
    tdOff.innerHTML = `<input type="number" step="0.25" min="0" value="${week.offCarole}" data-week="${index}" data-field="offCarole">`;
    tr.appendChild(tdOff);

    // # Extra off Carole
    const tdExtraOff = document.createElement("td");
    tdExtraOff.innerHTML = `<input type="number" step="0.25" min="0" value="${week.extraOffCarole}" data-week="${index}" data-field="extraOffCarole">`;
    tr.appendChild(tdExtraOff);

    // #Centre
    const tdCentre = document.createElement("td");
    tdCentre.innerHTML = `<input type="number" step="0.25" min="0" value="${week.centre}" data-week="${index}" data-field="centre">`;
    tr.appendChild(tdCentre);

    // #Avis
    const tdAvis = document.createElement("td");
    tdAvis.innerHTML = `<input type="number" step="0.25" min="0" value="${week.avis}" data-week="${index}" data-field="avis">`;
    tr.appendChild(tdAvis);

    // Alex total & détail
    const tdAlexTotal = document.createElement("td");
    tdAlexTotal.textContent = week.alex.total.toFixed
      ? week.alex.total.toFixed(2)
      : week.alex.total;
    tr.appendChild(tdAlexTotal);

    const tdAlexDetail = document.createElement("td");
    tdAlexDetail.innerHTML = `<input type="text" value="${
      week.alex.detail || ""
    }" data-week="${index}" data-field="alex.detail">`;
    tr.appendChild(tdAlexDetail);

    // Laia total & détail
    const tdLaiaTotal = document.createElement("td");
    tdLaiaTotal.textContent = week.laia.total.toFixed
      ? week.laia.total.toFixed(2)
      : week.laia.total;
    tr.appendChild(tdLaiaTotal);

    const tdLaiaDetail = document.createElement("td");
    tdLaiaDetail.innerHTML = `<input type="text" value="${
      week.laia.detail || ""
    }" data-week="${index}" data-field="laia.detail">`;
    tr.appendChild(tdLaiaDetail);

    planningBody.appendChild(tr);
  });

  attachInputListeners();
  updateSummary();
}

// === 4. Listeners sur les inputs ===

function attachInputListeners() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;

  planningBody.querySelectorAll("input").forEach((input) => {
    input.addEventListener("change", (e) => {
      const weekIndex = parseInt(e.target.dataset.week, 10);
      const field = e.target.dataset.field;
      const value =
        e.target.type === "number"
          ? parseFloat(e.target.value || "0")
          : e.target.value;

      if (Number.isNaN(weekIndex) || !weeks[weekIndex]) return;

      if (
        field === "offCarole" ||
        field === "extraOffCarole" ||
        field === "centre" ||
        field === "avis"
      ) {
        weeks[weekIndex][field] = value;
      } else if (field === "alex.detail") {
        weeks[weekIndex].alex.detail = value;
      } else if (field === "laia.detail") {
        weeks[weekIndex].laia.detail = value;
      }

      // plus tard : calculer alex.total / laia.total en fonction des CP/RTT/JA
      updateSummary();
    });
  });
}

// === 5. Résumé (pour l'instant, 0 utilisés) ===

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

  // Pour l'instant, on n'a pas encore branché les CP/RTT/JA par semaine
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

// === 6. Init ===

function initFamilyCalendar() {
  if (!document.getElementById("planningBody")) return;

  // Soldes initiaux
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

  // Filtres (pour l'instant seul showOnlyCaroleOff a un effet)
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
