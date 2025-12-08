// assets/js/family-calendar.js

const weeks = [
  {
    month: "September",
    code: "W36",
    dates: "01/09 - 07/09",
    caroleOffDays: 0,
    isSchoolHoliday: false,
    isBankHoliday: false,
    alex: { cp: 0, rtt: 0, ja: 0 },
    laia: { cp: 0, rtt: 0, ja: 0 },
  },
  {
    month: "September",
    code: "W38",
    dates: "15/09 - 21/09",
    caroleOffDays: 2,
    isSchoolHoliday: false,
    isBankHoliday: false,
    alex: { cp: 0, rtt: 0, ja: 0 },
    laia: { cp: 0, rtt: 0, ja: 0 },
  },
  {
    month: "October",
    code: "W40",
    dates: "29/09 - 05/10",
    caroleOffDays: 0,
    isSchoolHoliday: false,
    isBankHoliday: false,
    alex: { cp: 0, rtt: 0, ja: 0 },
    laia: { cp: 0, rtt: 0, ja: 0 },
  },
  {
    month: "August",
    code: "W32",
    dates: "03/08 - 09/08",
    caroleOffDays: 5,
    isSchoolHoliday: true,
    isBankHoliday: false,
    alex: { cp: 0, rtt: 0, ja: 0 },
    laia: { cp: 0, rtt: 0, ja: 0 },
  },
  // TODO: compléter le reste des semaines W36 → W35
];

function renderTable() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;

  planningBody.innerHTML = "";

  const showOnlyCaroleOff =
    document.getElementById("showOnlyCaroleOff")?.checked;
  const showOnlySchoolHoliday = document.getElementById(
    "showOnlySchoolHoliday"
  )?.checked;

  weeks.forEach((week, index) => {
    if (showOnlyCaroleOff && week.caroleOffDays === 0) return;
    if (showOnlySchoolHoliday && !week.isSchoolHoliday) return;

    const tr = document.createElement("tr");

    if (week.caroleOffDays > 0) tr.classList.add("fc-row--carole-off");
    if (week.isSchoolHoliday) tr.classList.add("fc-row--school-holiday");
    if (week.isBankHoliday) tr.classList.add("fc-row--bank-holiday");

    tr.innerHTML = `
      <td>${week.month}</td>
      <td>${week.code}</td>
      <td>${week.dates}</td>
      <td>${week.caroleOffDays || ""}</td>
      <td>${week.isSchoolHoliday ? "Oui" : ""}</td>
      <td>${week.isBankHoliday ? "Oui" : ""}</td>
      <td><input type="number" step="0.25" min="0" value="${
        week.alex.cp
      }" data-week="${index}" data-person="alex" data-type="cp"></td>
      <td><input type="number" step="0.25" min="0" value="${
        week.alex.rtt
      }" data-week="${index}" data-person="alex" data-type="rtt"></td>
      <td><input type="number" step="0.25" min="0" value="${
        week.alex.ja
      }" data-week="${index}" data-person="alex" data-type="ja"></td>
      <td><input type="number" step="0.25" min="0" value="${
        week.laia.cp
      }" data-week="${index}" data-person="laia" data-type="cp"></td>
      <td><input type="number" step="0.25" min="0" value="${
        week.laia.rtt
      }" data-week="${index}" data-person="laia" data-type="rtt"></td>
      <td><input type="number" step="0.25" min="0" value="${
        week.laia.ja
      }" data-week="${index}" data-person="laia" data-type="ja"></td>
    `;

    planningBody.appendChild(tr);
  });

  attachInputListeners();
  updateSummary();
}

function attachInputListeners() {
  const planningBody = document.getElementById("planningBody");
  if (!planningBody) return;

  planningBody.querySelectorAll("input[type='number']").forEach((input) => {
    input.addEventListener("change", (e) => {
      const w = parseInt(e.target.dataset.week, 10);
      const person = e.target.dataset.person;
      const type = e.target.dataset.type;
      const value = parseFloat(e.target.value || "0");
      weeks[w][person][type] = value;
      updateSummary();
    });
  });
}

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

  let alexCpUsed = 0,
    alexRttUsed = 0,
    alexJaUsed = 0;
  let laiaCpUsed = 0,
    laiaRttUsed = 0,
    laiaJaUsed = 0;

  weeks.forEach((week) => {
    alexCpUsed += week.alex.cp;
    alexRttUsed += week.alex.rtt;
    alexJaUsed += week.alex.ja;

    laiaCpUsed += week.laia.cp;
    laiaRttUsed += week.laia.rtt;
    laiaJaUsed += week.laia.ja;
  });

  const alexCpLeft = alexCpInit - alexCpUsed;
  const alexRttLeft = alexRttInit - alexRttUsed;
  const alexJaLeft = alexJaInit - alexJaUsed;

  const laiaCpLeft = laiaCpInit - laiaCpUsed;
  const laiaRttLeft = laiaRttInit - laiaRttUsed;
  const laiaJaLeft = laiaJaInit - laiaJaUsed;

  summaryDiv.innerHTML = `
    <p><strong>Alex</strong><br>
    CP utilisés : ${alexCpUsed.toFixed(2)} / ${alexCpInit.toFixed(
    2
  )} (reste ${alexCpLeft.toFixed(2)})<br>
    RTT utilisés : ${alexRttUsed.toFixed(2)} / ${alexRttInit.toFixed(
    2
  )} (reste ${alexRttLeft.toFixed(2)})<br>
    JA utilisés : ${alexJaUsed.toFixed(2)} / ${alexJaInit.toFixed(
    2
  )} (reste ${alexJaLeft.toFixed(2)})</p>

    <p><strong>Laia</strong><br>
    CP utilisés : ${laiaCpUsed.toFixed(2)} / ${laiaCpInit.toFixed(
    2
  )} (reste ${laiaCpLeft.toFixed(2)})<br>
    RTT utilisés : ${laiaRttUsed.toFixed(2)} / ${laiaRttInit.toFixed(
    2
  )} (reste ${laiaRttLeft.toFixed(2)})<br>
    JA utilisés : ${laiaJaUsed.toFixed(2)} / ${laiaJaInit.toFixed(2)})</p>
  `;
}

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

  ["showOnlyCaroleOff", "showOnlySchoolHoliday"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", renderTable);
  });

  renderTable();
}

document.addEventListener("DOMContentLoaded", initFamilyCalendar);
