/**
 * family-calendar.js (Version Optimisée - API BDD, Décalage Vendredi & Paramètres Dynamiques)
 */

// ============================================================================
// 1. VARIABLES GLOBALES & LOGIQUE DE CONFIGURATION (SETTINGS)
// ============================================================================
let calGlobalData = null;
let currentSelectedMemberId = null;
let localCareModes = [];

// Ouvre et initialise la modale avec les données fraîches de la BDD
async function openCalendarSettings() {
  try {
    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/settings.php?action=get_all",
    );
    if (!res.success) throw new Error(res.error);

    calGlobalData = res.data;

    const zoneInput = document.getElementById("setZoneScolaire");
    if (zoneInput) zoneInput.value = calGlobalData.foyer.zone_scolaire;

    localCareModes = calGlobalData.foyer.care_modes || [];
    renderCareModeTags();

    const selectMember = document.getElementById("selectCalMember");
    if (selectMember) {
      selectMember.innerHTML = calGlobalData.people
        .map(
          (p) =>
            `<option value="${p.id}" data-role="${p.role}">${p.name} (${p.role === "parent" ? tr("fc_role_adult") : tr("fc_role_child")})</option>`,
        )
        .join("");
    }

    switchCalendarTab("foyer");

    const modalSettings = document.getElementById("modalCalendarSettings");
    if (modalSettings) {
      modalSettings.classList.add("open");
      document.body.classList.add("no-scroll");
    }
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

function closeCalendarSettings() {
  document.getElementById("modalCalendarSettings").classList.remove("open");
  document.body.classList.remove("no-scroll");
}

function switchCalendarTab(tabName) {
  document
    .querySelectorAll(".cal-settings-pane")
    .forEach((p) => (p.style.display = "none"));
  document
    .querySelectorAll(".bs-tab-btn")
    .forEach((b) => b.classList.remove("active"));

  if (tabName === "foyer") {
    document.getElementById("cal-pane-foyer").style.display = "block";
    document.getElementById("tab-btn-foyer").classList.add("active");
  } else {
    document.getElementById("cal-pane-membres").style.display = "block";
    document.getElementById("tab-btn-membres").classList.add("active");
    loadMemberConfigView();
  }
}

function renderCareModeTags() {
  const container = document.getElementById("careModesContainer");
  container.innerHTML = localCareModes
    .map(
      (mode, i) => `
        <span class="bs-rule-tag" style="background:var(--bg-panel); font-size:0.85rem; padding: 4px 10px; display:inline-flex; align-items:center; gap:6px; border-radius:12px;">
            ${mode} <b style="cursor:pointer; color:var(--danger); font-size:1.1rem; line-height:1;" onclick="removeCareModeTag(${i})">×</b>
        </span>
    `,
    )
    .join("");
}

function addCareModeTag() {
  const input = document.getElementById("inputNewCareMode");
  const val = input.value.trim();
  if (val && !localCareModes.includes(val)) {
    localCareModes.push(val);
    renderCareModeTags();
    input.value = "";
  }
}

function removeCareModeTag(index) {
  localCareModes.splice(index, 1);
  renderCareModeTags();
}

async function submitCalFoyer(e) {
  e.preventDefault();
  try {
    const formData = new FormData();
    formData.append("action", "save_foyer");
    formData.append(
      "zone_scolaire",
      document.getElementById("setZoneScolaire").value,
    );
    formData.append("care_modes", JSON.stringify(localCareModes));

    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/settings.php",
      { method: "POST", body: formData },
    );
    if (!res.success) throw new Error(res.error);

    alert(
      window.FAMILY_CONFIG?.i18n?.settings_updated ||
        "Configuration enregistrée ! ✨",
    );
    window.location.reload(); // On recharge pour appliquer la zone et les colonnes de garde
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

function loadMemberConfigView() {
  const select = document.getElementById("selectCalMember");
  const zone = document.getElementById("memberConfigZone");
  if (!select || !select.value || !zone) return;

  currentSelectedMemberId = parseInt(select.value);
  const role = select.options[select.selectedIndex].dataset.role;

  if (role === "enfant") {
    zone.innerHTML = `
        <h5 style="margin:0 0 8px 0; color:var(--text-main);">${tr("fc_care_modes_title")}</h5>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">${tr("fc_care_modes_desc")}</p>
        <div style="display:flex; flex-direction:column; gap:6px;">
            ${
              localCareModes
                .map(
                  (m) => `
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem;">
                    <input type="checkbox" value="${m}" checked> ${m}
                </label>
            `,
                )
                .join("") || `<em>${tr("fc_no_care_mode")}</em>`
            }
        </div>
        <button class="pf-btn pf-btn-primary" style="margin-top:15px; width:100%;" onclick="alert('${tr("fc_child_saved")}')">${tr("btn_save_rights")}</button>
    `;
  } else {
    // 1. Récupération STRICTE des congés existants en BDD (Aucun forçage)
    let userLeaves = calGlobalData.leaves[currentSelectedMemberId] || [];

    // 2. Génération des lignes du tableau dynamiquement (Version compactée)
    const tableRows = userLeaves
      .map(
        (l) => `
        <tr class="js-leave-row" style="border-bottom: 1px solid var(--border-light);">
            <td style="padding: 6px 2px;"><input type="text" class="pf-input js-leave-type" value="${l.type}" placeholder="CP" required style="width:50px; text-transform:uppercase; padding:6px 4px; font-size:0.85rem; text-align:center;"></td>
            <td style="padding: 6px 2px;">
                <select class="pf-input js-leave-method" style="padding:6px 2px; font-size:0.85rem; width:80px;">
                    <option value="FIXED" ${l.method === "FIXED" ? "selected" : ""}>Fixe</option>
                    <option value="ACCUMULATED" ${l.method === "ACCUMULATED" ? "selected" : ""}>Cumul</option>
                </select>
            </td>
            <td style="padding: 6px 2px;"><input type="number" step="0.5" class="pf-input js-leave-allowance" value="${l.allowance || 0}" placeholder="0" required style="width:55px; padding:6px 4px; font-size:0.85rem; text-align:center;"></td>
            <td style="padding: 6px 2px;"><input type="date" class="pf-input js-leave-date" value="${l.date}" required style="padding:6px 4px; font-size:0.85rem; width:115px;"></td>
            <td style="padding: 6px 2px; text-align:right;"><button type="button" class="btn-icon-action delete" style="padding:4px; margin:0;" onclick="this.closest('tr').remove()" title="Supprimer">🗑️</button></td>
        </tr>
    `,
      )
      .join("");

    zone.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div>
                <h5 style="margin:0 0 4px 0; color:var(--text-main); font-size:1rem;">🗓️ Matrice des Congés</h5>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0; line-height:1.3;">Définissez les quotas et méthodes d'acquisition.</p>
            </div>
            <button type="button" class="pf-btn pf-btn-secondary" style="padding:6px 10px; font-size:0.8rem; white-space:nowrap;" onclick="addLeaveRowToMatrix()">➕ Ajouter</button>
        </div>

        <div style="overflow-x:hidden; margin: 0 -5px;">
            <table style="width:100%; border-collapse:collapse; margin-bottom:15px; table-layout: fixed;">
                <thead>
                    <tr style="font-size:0.75rem; text-align:left; color:var(--text-muted); text-transform:uppercase;">
                        <th style="padding:0 2px 8px; width: 15%;">Code</th>
                        <th style="padding:0 2px 8px; width: 25%;">Méthode</th>
                        <th style="padding:0 2px 8px; width: 20%;">Quota</th>
                        <th style="padding:0 2px 8px; width: 30%;">Renouv. / Fin</th>
                        <th style="padding:0 2px 8px; width: 10%;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyLeaveMatrix">
                    ${tableRows || `<tr><td colspan="5" id="emptyMatrixRow" style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic; font-size:0.9rem;">Aucun compteur défini.</td></tr>`}
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:flex-end; border-top:1px solid var(--border-light); padding-top:12px;">
            <button type="button" class="pf-btn pf-btn-primary" style="padding:8px 16px;" onclick="submitMemberLeaves()">Enregistrer</button>
        </div>
    `;
  }
}

window.addLeaveRowToMatrix = function () {
  const tbody = document.getElementById("tbodyLeaveMatrix");
  const empty = document.getElementById("emptyMatrixRow");
  if (empty) empty.remove();
  if (!tbody) return;

  const tr = document.createElement("tr");
  tr.className = "js-leave-row";
  tr.style.borderBottom = "1px solid var(--border-light)";
  tr.innerHTML = `
        <td style="padding: 6px 2px;"><input type="text" class="pf-input js-leave-type" placeholder="CP" required style="width:100%; text-transform:uppercase; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;"></td>
        <td style="padding: 6px 2px;">
            <select class="pf-input js-leave-method" style="width:100%; padding:6px 2px; font-size:0.85rem; box-sizing: border-box;">
                <option value="FIXED">Fixe</option>
                <option value="ACCUMULATED">Cumul</option>
            </select>
        </td>
        <td style="padding: 6px 2px;"><input type="number" step="0.5" class="pf-input js-leave-allowance" placeholder="0" required style="width:100%; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;"></td>
        <td style="padding: 6px 2px;"><input type="date" class="pf-input js-leave-date" required style="width:100%; padding:6px 4px; font-size:0.85rem; box-sizing: border-box;"></td>
        <td style="padding: 6px 2px; text-align:right;"><button type="button" class="btn-icon-action delete" style="padding:4px; margin:0;" onclick="this.closest('tr').remove()" title="Supprimer">🗑️</button></td>
    `;
  tbody.appendChild(tr);
};

async function submitMemberLeaves() {
  // 🟢 Sécurité : on s'assure qu'un membre est bien sélectionné
  if (!currentSelectedMemberId || isNaN(currentSelectedMemberId)) {
    alert("Erreur technique : Aucun membre valide sélectionné.");
    return;
  }

  try {
    const rows = document.querySelectorAll(".js-leave-row");

    // 🟢 Récupération enrichie avec "method" et "allowance"
    const leavesPayload = Array.from(rows)
      .map((row) => ({
        type: row.querySelector(".js-leave-type").value.trim().toUpperCase(),
        method: row.querySelector(".js-leave-method").value, // <-- NOUVEAU
        allowance: row.querySelector(".js-leave-allowance").value, // <-- NOUVEAU
        date: row.querySelector(".js-leave-date").value,
      }))
      .filter((l) => l.type && l.date); // Filtre de sécurité

    const formData = new FormData();
    formData.append("action", "save_member_leaves");
    formData.append("person_id", currentSelectedMemberId);
    formData.append("leaves", JSON.stringify(leavesPayload));

    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/settings.php",
      { method: "POST", body: formData },
    );
    if (!res.success) throw new Error(res.error);

    if (window.showToast) {
      showToast(tr("fc_matrix_saved"), "success");
    } else {
      alert(tr("fc_matrix_saved"));
    }

    closeCalendarSettings();
    setTimeout(openCalendarSettings, 300);
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

// ============================================================================
// 2. MOTEUR PRINCIPAL DU CALENDRIER (DOM CONTENT LOADED)
// ============================================================================
document.addEventListener("DOMContentLoaded", () => {
  // Récupération sécurisée depuis le nouveau format PHP
  const parents = window.FAMILY_CONFIG?.parents || [];
  const kids = window.FAMILY_CONFIG?.kids || [];
  const activeCareModes = window.FAMILY_CONFIG?.activeCareModes || [];

  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];

  // (Note: La logique LEAVES_CONFIG restera à basculer vers BDD dans un 2nd temps)
  const LEAVES_CONFIG = {
    CP: { startMonth: 8, defaultBalance: 25 },
    JRA: {
      yearlyTotals: { 2024: 10, 2025: 10, 2026: 11 },
      defaultBalance: 10,
      toleranceMonths: 2,
      maxReport: 2,
    },
    JA: {},
  };

  parents.forEach((parent) => {
    LEAVES_CONFIG.JA[parent.id] = {
      startMonth: 4,
      startDay: 29,
      defaultBalance: 4,
    };
  });

  class FamilyCalendar {
    constructor() {
      this.planningBody = document.getElementById("planningBody");
      this.selectionMenu = document.getElementById("selectionMenu");
      this.schoolHolidaysTableBody = document.querySelector(
        "#schoolHolidaysTable tbody",
      );
      this.monthCalendar = document.getElementById("fc-month-calendar");
      this.monthSelectionMenu = document.getElementById(
        "fc-month-selectionMenu",
      );

      if (
        this.selectionMenu &&
        this.selectionMenu.parentElement !== document.body
      )
        document.body.appendChild(this.selectionMenu);
      if (
        this.monthSelectionMenu &&
        this.monthSelectionMenu.parentElement !== document.body
      )
        document.body.appendChild(this.monthSelectionMenu);

      this.currentMonth = new Date();
      this.currentMonth.setDate(1);
      this.viewMode = "1month";
      this.currentSchoolYearStart = null;
      this.modalSelectedYear = null;

      this.isSelecting = false;
      this.selectedCells = [];
      this.monthSelectedCells = [];
      this._currentBulkInfo = null;

      this.dbEvents = [];
      this.fixedEvents = [];
      this.events = [];
      this.leaves = [];
      this.weeks = [];
      this.monthlyLeaveBalances = {
        2: { CP: {}, JRA: {}, JA: {} },
        3: { CP: {}, JRA: {}, JA: {} },
      };

      if (!this.planningBody) return;
      this.init();
    }

    getLocalIsoDate(dateObj) {
      const y = dateObj.getFullYear();
      const m = String(dateObj.getMonth() + 1).padStart(2, "0");
      const d = String(dateObj.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    }

    async init() {
      this.setupEventListeners();
      const now = new Date();
      this.currentSchoolYearStart =
        now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;
      this.modalSelectedYear = this.currentSchoolYearStart;

      this.setupModalUI();
      this.initSmartSelectors();
      await this.refreshAllData();
      this.updateSchoolYearLabel();

      setTimeout(() => this.scrollToCurrentMonth(), 100);
    }

    setupModalUI() {
      const headerTitle = document.querySelector(
        "#modalHolidays .pf-modal-title",
      );
      if (headerTitle && !document.getElementById("holidayYearSelect")) {
        let options = "";
        const currentY = new Date().getFullYear();
        for (let y = currentY - 2; y <= currentY + 3; y++) {
          options += `<option value="${y}" ${y === this.currentSchoolYearStart ? "selected" : ""}>${y} - ${y + 1}</option>`;
        }

        // Utilise la globale config ou fallback si non définie
        const zoneText =
          window.CONFIG?.ZONE_SCOLAIRE &&
          window.CONFIG.ZONE_SCOLAIRE !== "Autre"
            ? `(Zone ${window.CONFIG.ZONE_SCOLAIRE})`
            : "";

        headerTitle.innerHTML = `🏖️ ${window.I18N?.fc_modal_holidays_title || "Vacances"} ${zoneText}
                  <select id="holidayYearSelect" class="pf-input" style="width:auto; display:inline-block; margin-left:10px; padding:4px 10px; height:auto;">
                    ${options}
                  </select>`;

        document
          .getElementById("holidayYearSelect")
          .addEventListener("change", (e) => {
            this.modalSelectedYear = parseInt(e.target.value);
            this.renderModalHolidays();
          });
      }
    }

    initSmartSelectors() {
      const selectMonth = document.getElementById("fc-select-month");
      const selectYear = document.getElementById("fc-select-year");
      if (!selectMonth || !selectYear) return;

      const lang = window.appLang || "fr-FR";

      for (let i = 0; i < 12; i++) {
        selectMonth.add(
          new Option(
            new Intl.DateTimeFormat(lang, { month: "long" }).format(
              new Date(2000, i, 1),
            ),
            i,
          ),
        );
      }
      const currentY = new Date().getFullYear();
      for (let y = currentY - 2; y <= currentY + 5; y++) {
        selectYear.add(new Option(y, y));
      }

      const handleChange = () => {
        this.currentMonth = new Date(
          parseInt(selectYear.value),
          parseInt(selectMonth.value),
          1,
        );
        this.renderMonthCalendar();
      };

      selectMonth.addEventListener("change", handleChange);
      selectYear.addEventListener("change", handleChange);
    }

    async refreshAllData() {
      try {
        const [
          weeksData,
          eventsData,
          fixedEventsData,
          leavesData,
          balancesData,
          snapshotsData,
        ] = await Promise.all([
          this.fetchApi(
            `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${this.currentSchoolYearStart}`,
          ),
          this.fetchApi("/modules/family-calendar/includes/api/get-events.php"),
          this.fetchPublicHolidays(),
          this.fetchApi("/modules/family-calendar/includes/api/get-leaves.php"),
          this.fetchApi(
            "/modules/family-calendar/includes/api/get-leave-balances.php",
          ),
          this.fetchApi(
            "/modules/family-calendar/includes/api/get-leave-snapshots.php",
          ),
        ]);

        this.weeks = this.processWeeks(weeksData.weeks || []);
        this.dbEvents = (eventsData.events || []).map((e) => ({
          ...e,
          duration: parseFloat(e.duration),
        }));
        this.fixedEvents = fixedEventsData;
        this.leaves = leavesData.leaves || [];
        this.leaveBalances = balancesData.balances || [];
        this.leaveSnapshots = snapshotsData.snapshots || [];

        this.events = [...this.dbEvents, ...this.fixedEvents];
        this.publicHolidayDates = new Set(
          this.fixedEvents
            .filter((e) => e.type === "PUBLIC_HOLIDAY")
            .map((e) => e.date),
        );

        this.reprocessAndRender();
        this.renderModalHolidays();
      } catch (e) {
        console.error("Erreur chargement données:", e);
      }
    }

    async fetchPublicHolidays() {
      try {
        const res = await fetch(
          "https://calendrier.api.gouv.fr/jours-feries/metropole.json",
        );
        const holidays = await res.json();
        return Object.keys(holidays).map((date, idx) => ({
          id: `ph-${idx}`,
          date: date,
          name: holidays[date],
          type: "PUBLIC_HOLIDAY",
          duration: 1,
        }));
      } catch (error) {
        return [];
      }
    }

    renderModalHolidays() {
      if (!this.schoolHolidaysTableBody) return;
      const startDate = `${this.modalSelectedYear}-09-01`;
      const endDate = `${this.modalSelectedYear + 1}-08-31`;

      const yearHolidays = this.events.filter(
        (e) =>
          e.type === "VACANCES_SCOLAIRES" &&
          e.date >= startDate &&
          e.date <= endDate,
      );

      if (yearHolidays.length === 0) {
        this.schoolHolidaysTableBody.innerHTML = `
                  <tr>
                    <td colspan="3" style="text-align:center; padding: 30px;">
                      <p style="color:#64748b; margin-bottom:15px;">${window.I18N?.fc_err_no_data_gov || "Aucune donnée"}</p>
                      <button id="btnFetchGovHolidays" class="pf-btn pf-btn-primary">Importer</button>
                    </td>
                  </tr>
                `;
        document
          .getElementById("btnFetchGovHolidays")
          ?.addEventListener("click", (e) => {
            e.target.innerText = "...";
            e.target.disabled = true;
            this.fetchAndSaveGovHolidays(this.modalSelectedYear);
          });
        return;
      }

      yearHolidays.sort((a, b) => new Date(a.date) - new Date(b.date));
      const blocks = [];
      let currentBlock = null;

      yearHolidays.forEach((e) => {
        const d = new Date(e.date + "T00:00:00");
        if (!currentBlock) {
          currentBlock = { start: d, end: d };
          blocks.push(currentBlock);
        } else {
          const diffDays = Math.round(
            (d.getTime() - currentBlock.end.getTime()) / 86400000,
          );
          if (diffDays <= 4) currentBlock.end = d;
          else {
            currentBlock = { start: d, end: d };
            blocks.push(currentBlock);
          }
        }
      });

      this.schoolHolidaysTableBody.innerHTML = blocks
        .map((block) => {
          const m = block.start.getMonth() + 1;
          const dur =
            Math.round(
              (block.end.getTime() - block.start.getTime()) / 86400000,
            ) + 1;
          let name = window.I18N?.leg_school_holidays || "Vacances";
          if (m === 10 || m === 11)
            name = window.I18N?.vac_toussaint || "Toussaint";
          else if (m === 12 || m === 1) name = window.I18N?.vac_noel || "Noël";
          else if (m === 2 || m === 3) name = window.I18N?.vac_hiver || "Hiver";
          else if (m === 4 || (m === 5 && dur > 6))
            name = window.I18N?.vac_printemps || "Printemps";
          else if (m === 5 && dur <= 6)
            name = window.I18N?.vac_ascension || "Ascension";
          else if (m === 7 || m === 8) name = window.I18N?.vac_ete || "Eté";

          return `<tr>
                    <td><strong>${name}</strong></td>
                    <td>${block.start.toLocaleDateString(window.appLang || "fr-FR")}</td>
                    <td>${block.end.toLocaleDateString(window.appLang || "fr-FR")}</td>
                </tr>`;
        })
        .join("");
    }

    async fetchAndSaveGovHolidays(yearStart) {
      try {
        const yearStr = `${yearStart}-${yearStart + 1}`;
        const zone = window.CONFIG?.ZONE_SCOLAIRE || "C";

        if (zone === "Autre") {
          alert("Import auto dispo que pour Zones A, B ou C (France).");
          return;
        }

        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone ${zone}%'&limit=100`;
        const res = await fetch(url);
        const data = await res.json();

        const uniqueMap = new Map();
        (data.results || []).forEach((r) =>
          uniqueMap.set(`${r.description}|${r.start_date}`, r),
        );

        const payload = [];
        Array.from(uniqueMap.values()).forEach((r) => {
          let curr = new Date(r.start_date.split("T")[0] + "T00:00:00");
          const end = new Date(r.end_date.split("T")[0] + "T00:00:00");
          if (curr.getDay() === 5) curr.setDate(curr.getDate() + 1); // Règle: Décale vendredi -> samedi

          while (curr < end) {
            payload.push({
              date: this.getLocalIsoDate(curr),
              type: "VACANCES_SCOLAIRES",
              duration: 1,
              person: r.description,
            });
            curr.setDate(curr.getDate() + 1);
          }
        });

        if (payload.length > 0) {
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            payload,
          );
          await this.refreshAllData();
        } else {
          alert("Aucune donnée trouvée.");
        }
      } catch (e) {
        alert("Erreur API Gouv.");
      }
    }

    reprocessAndRender() {
      this.reprocessEvents();
      this.calculateMonthlyBalances();
      this.initSummaryControls();
      this.renderTable();
      this.renderMonthCalendar();
    }

    reprocessEvents() {
      this.weeks.forEach((w) => {
        Object.keys(w.totals).forEach((k) => (w.totals[k] = 0));
        Object.values(w.dayFlags).forEach((f) => (f.events = []));

        this.events.forEach((e) => {
          const d = new Date(e.date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dayKey = Object.keys(w.dayDates).find(
              (k) => w.dayDates[k].getTime() === d.getTime(),
            );
            if (dayKey) w.dayFlags[dayKey].events.push(e);

            const dur = parseFloat(e.duration) || 1;
            const typeMap = {
              OFF_CAROLE: "offCarole",
              EXTRA_OFF_CAROLE: "extraOffCarole",
              CENTRE: "centre",
              AVIS: "avis",
              PEP_SICK: "pepSick",
            };
            if (typeMap[e.type]) w.totals[typeMap[e.type]] += dur;
          }
        });

        this.leaves.forEach((l) => {
          const d = new Date(l.leave_date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dur = parseFloat(l.duration) || 1;
            let prefix = null;
            if (parents[0] && parseInt(l.person_id) === parseInt(parents[0].id))
              prefix = "alex";
            else if (
              parents[1] &&
              parseInt(l.person_id) === parseInt(parents[1].id)
            )
              prefix = "laia";
            if (prefix) w.totals[`${prefix}${l.leave_type}`] += dur;
          }
        });

        let workingDays = 0;
        Object.values(w.dayDates).forEach((d) => {
          if (!this.publicHolidayDates.has(this.getLocalIsoDate(d)))
            workingDays++;
        });
        w.totals.presencePep = Math.max(
          0,
          workingDays -
            (w.totals.offCarole + w.totals.extraOffCarole + w.totals.pepSick),
        );
      });
    }

    calculateMonthlyBalances() {
      const balances = {};
      parents.forEach((p) => {
        balances[p.id] = { CP: {}, JRA: {}, JA: {} };
      });

      const ymSet = new Set();
      this.weeks.forEach((w) => ymSet.add(w.monthKey));
      const ymList = Array.from(ymSet).sort();

      const usageByMonth = {};
      this.leaves.forEach((l) => {
        const pid = l.person_id,
          type = l.leave_type,
          ym = l.leave_date.substring(0, 7);
        if (!usageByMonth[pid]) usageByMonth[pid] = {};
        if (!usageByMonth[pid][type]) usageByMonth[pid][type] = {};
        usageByMonth[pid][type][ym] =
          (usageByMonth[pid][type][ym] || 0) + parseFloat(l.duration);
      });

      parents.forEach((parent) => {
        const pid = parent.id;
        ["CP", "JRA", "JA"].forEach((type) => {
          ymList.forEach((ym) => {
            const [currYear, currMonth] = ym.split("-").map(Number);
            let cycleStartStr = "",
              initialBalance = 0;

            const latestSnapshot = (this.leaveSnapshots || [])
              .filter(
                (s) =>
                  s.person_id == pid &&
                  s.leave_type == type &&
                  s.snapshot_date.substring(0, 7) <= ym,
              )
              .sort((a, b) =>
                b.snapshot_date.localeCompare(a.snapshot_date),
              )[0];

            if (latestSnapshot) {
              cycleStartStr = latestSnapshot.snapshot_date.substring(0, 7);
              initialBalance = parseFloat(latestSnapshot.remaining_balance);
            } else {
              if (type === "CP") {
                const refYear =
                  currMonth >= LEAVES_CONFIG.CP.startMonth
                    ? currYear
                    : currYear - 1;
                cycleStartStr = `${refYear}-${String(LEAVES_CONFIG.CP.startMonth).padStart(2, "0")}`;
                const dbBal = this.leaveBalances.find(
                  (b) =>
                    b.person_id == pid &&
                    b.leave_type == "CP" &&
                    b.balance_year == refYear,
                );
                initialBalance = dbBal
                  ? parseFloat(dbBal.initial_balance)
                  : LEAVES_CONFIG.CP.defaultBalance;
              } else if (type === "JRA") {
                cycleStartStr = `${currYear}-01`;
                initialBalance =
                  LEAVES_CONFIG.JRA.yearlyTotals[currYear] ||
                  LEAVES_CONFIG.JRA.defaultBalance;
                if (currMonth <= LEAVES_CONFIG.JRA.toleranceMonths) {
                  const prevYear = currYear - 1;
                  const prevInitial =
                    LEAVES_CONFIG.JRA.yearlyTotals[prevYear] ||
                    LEAVES_CONFIG.JRA.defaultBalance;
                  let usedPrevYear = 0;
                  for (let m = 1; m <= 12; m++)
                    usedPrevYear +=
                      usageByMonth[pid]?.[type]?.[
                        `${prevYear}-${String(m).padStart(2, "0")}`
                      ] || 0;
                  initialBalance += Math.min(
                    Math.max(0, prevInitial - usedPrevYear),
                    LEAVES_CONFIG.JRA.maxReport,
                  );
                }
              } else if (type === "JA") {
                const configJA = LEAVES_CONFIG.JA[pid];
                const isPastAnniversary =
                  currMonth > configJA.startMonth ||
                  (currMonth === configJA.startMonth &&
                    configJA.startDay === 1);
                const refYear = isPastAnniversary ? currYear : currYear - 1;
                cycleStartStr = `${refYear}-${String(configJA.startMonth).padStart(2, "0")}`;
                const dbBal = this.leaveBalances.find(
                  (b) =>
                    b.person_id == pid &&
                    b.leave_type == "JA" &&
                    b.balance_year == refYear,
                );
                initialBalance = dbBal
                  ? parseFloat(dbBal.initial_balance)
                  : configJA.defaultBalance;
              }
            }

            let usedBeforeCurrentMonth = 0;
            Object.keys(usageByMonth[pid]?.[type] || {}).forEach((usedYm) => {
              if (usedYm >= cycleStartStr && usedYm < ym)
                usedBeforeCurrentMonth += usageByMonth[pid][type][usedYm];
            });

            balances[pid][type][ym] = {
              availableAtMonthStart: Math.max(
                0,
                initialBalance - usedBeforeCurrentMonth,
              ),
              usedInMonth: usageByMonth[pid]?.[type]?.[ym] || 0,
            };
          });
        });
      });
      this.monthlyLeaveBalances = balances;
    }

    renderTable() {
      if (!this.planningBody) return;
      this.planningBody.innerHTML = "";
      const monthSpans = this.weeks.reduce((acc, w) => {
        acc[w.monthKey] = (acc[w.monthKey] || 0) + 1;
        return acc;
      }, {});
      const processedMonths = {};
      const processedLeavesCols = {};
      const fmt = (n) =>
        n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "";

      this.weeks.forEach((w, idx) => {
        const tr = document.createElement("tr");
        tr.setAttribute("data-month", w.monthKey);
        if (idx === 0 || this.weeks[idx - 1].monthKey !== w.monthKey)
          tr.classList.add("fc-month-first-week-row");
        if (
          idx === this.weeks.length - 1 ||
          this.weeks[idx + 1].monthKey !== w.monthKey
        )
          tr.classList.add("fc-month-last-week-row");

        if (!processedMonths[w.monthKey]) {
          processedMonths[w.monthKey] = true;
          const td = document.createElement("td");
          td.className = "col-month col-sticky-mois";
          td.innerHTML = `<span class="fc-sticky-mois-label">${w.monthName}</span>`;
          td.rowSpan = monthSpans[w.monthKey];
          tr.appendChild(td);
        }

        const tdW = document.createElement("td");
        tdW.className = "col-month col-sticky-sem";
        tdW.textContent = w.weekLabel;
        tr.appendChild(tdW);

        ["mon", "tue", "wed", "thu", "fri"].forEach((d) => {
          const td = document.createElement("td");
          const dateObj = w.dayDates[d];
          const iso = this.getLocalIsoDate(dateObj);
          td.dataset.date = iso;
          td.textContent = String(dateObj.getDate()).padStart(2, "0");
          td.className = "col-day";

          w.dayFlags[d].events.forEach((evt) => {
            if (evt.type === "OFF_CAROLE")
              td.classList.add("fc-day--off-carole");
            if (evt.type === "EXTRA_OFF_CAROLE")
              td.classList.add("fc-day--extra-off-carole");
            if (evt.type === "PUBLIC_HOLIDAY")
              td.classList.add("fc-day--public-holiday");
            if (evt.type === "VACANCES_SCOLAIRES")
              td.classList.add("fc-day--school-holiday");
            if (evt.type === "CENTRE") td.classList.add("fc-day--centre");
            if (evt.type === "AVIS") td.classList.add("fc-day--avis");
            if (evt.type === "PEP_SICK")
              td.innerHTML += `<span class="fc-pep-sick-emoji">🤒</span>`;
          });

          const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
          if (dayLeaves.length) {
            let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
            const colors = [
              "#0f766e",
              "#b45309",
              "#047857",
              "#4338ca",
              "#b91c1c",
            ];
            parents.forEach((person, index) => {
              if (
                dayLeaves.some(
                  (l) => parseInt(l.person_id) === parseInt(person.id),
                )
              ) {
                html += `<span style="color:${colors[index % colors.length]}; font-weight:800; margin: 0 1px;">${person.name.charAt(0).toUpperCase()}</span>`;
              }
            });
            td.innerHTML += html + `</div>`;
          }
          tr.appendChild(td);
        });

        // --- 🟢 CORRECTION 1 : Colonnes du milieu (Modes de garde & Maladie) ---
        const activeCareModes = window.FAMILY_CONFIG?.activeCareModes || [];
        const kids = window.FAMILY_CONFIG?.kids || [];

        // 1A. Modes de garde
        activeCareModes.forEach((mode) => {
          const td = document.createElement("td");
          td.className = "col-total";
          // On normalise le nom (ex: "Centre" -> "centre") pour matcher ton objet w.totals
          const key = mode.toLowerCase();
          td.textContent = fmt(w.totals[key] || 0);
          tr.appendChild(td);
        });

        // 1B. Enfants (Maladie)
        kids.forEach((kid) => {
          const td = document.createElement("td");
          td.className = "col-total";
          // Rétrocompatibilité avec ton ancien code "pepSick"
          const key =
            kid.name.toLowerCase() === "pep"
              ? "pepSick"
              : `${kid.name.toLowerCase()}Sick`;
          td.textContent = fmt(w.totals[key] || 0);
          tr.appendChild(td);
        });

        // --- 🟢 CORRECTION 2 : Colonnes des congés Parents (Dynamique !) ---
        if (!processedLeavesCols[w.monthKey]) {
          processedLeavesCols[w.monthKey] = true;

          const parentsList = window.FAMILY_CONFIG?.parents || [];
          parentsList.forEach((parent, index) => {
            const cssPrefix = index % 2 === 0 ? "col-alex" : "col-laia";

            // On récupère les compteurs réels du parent envoyés par le PHP
            const parentLeaveTypes =
              parent.leave_types && parent.leave_types.length > 0
                ? parent.leave_types
                : ["CP", "JRA", "JA"];

            parentLeaveTypes.forEach((type) => {
              const info =
                this.monthlyLeaveBalances[parent.id]?.[type]?.[w.monthKey];
              tr.innerHTML += `<td class="${cssPrefix}-sub ${cssPrefix}-av" rowspan="${monthSpans[w.monthKey]}">${info ? fmt(info.availableAtMonthStart) : "-"}</td>`;
              tr.innerHTML += `<td class="${cssPrefix}-sub ${cssPrefix}-use" rowspan="${monthSpans[w.monthKey]}">${info ? fmt(info.usedInMonth) : ""}</td>`;
            });
          });
        }

        this.planningBody.appendChild(tr);
      });
    }

    scrollToCurrentMonth() {
      const wrapper = document.getElementById("planningTable-wrapper");
      const targetRow = document.querySelector(
        `#planningTable tbody tr[data-month="${this.getLocalIsoDate(new Date()).slice(0, 7)}"]`,
      );
      if (wrapper && targetRow)
        setTimeout(
          () =>
            wrapper.scrollTo({
              top: Math.max(0, targetRow.offsetTop - 50),
              behavior: "smooth",
            }),
          50,
        );
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;
      const y = this.currentMonth.getFullYear(),
        m = this.currentMonth.getMonth();
      const selectMonth = document.getElementById("fc-select-month");
      const selectYear = document.getElementById("fc-select-year");

      if (selectMonth && selectYear) {
        selectMonth.value = m;
        selectYear.value = y;
        if (this.viewMode === "3months") this.renderThreeMonthsView();
        else if (this.viewMode === "2months") this.renderTwoMonthsView();
        else this.monthCalendar.innerHTML = this.generateMonthHTML(y, m);
      }
      this.renderMonthBalances();
      this.syncSummaryWithMonth();
    }

    renderTwoMonthsView() {
      const y = this.currentMonth.getFullYear(),
        m = this.currentMonth.getMonth();
      const nextDate = new Date(y, m + 1, 1);
      const lang = window.I18N_LANG || "fr-FR";
      this.monthCalendar.innerHTML = `<div class="fc-two-months-container">
                <div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(this.currentMonth)}</div>${this.generateMonthHTML(y, m)}</div>
                <div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(nextDate)}</div>${this.generateMonthHTML(nextDate.getFullYear(), nextDate.getMonth())}</div>
            </div>`;
    }

    renderThreeMonthsView() {
      const y = this.currentMonth.getFullYear(),
        m = this.currentMonth.getMonth();
      const [d1, d2, d3] = [
        this.currentMonth,
        new Date(y, m + 1, 1),
        new Date(y, m + 2, 1),
      ];
      let html = `<div class="fc-three-months-container">`;
      [d1, d2, d3].forEach(
        (d) =>
          (html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(d)}</div>${this.generateMonthHTML(d.getFullYear(), d.getMonth())}</div>`),
      );
      this.monthCalendar.innerHTML = html + `</div>`;
    }

    renderMonthBalances() {
      const container = document.getElementById("fc-month-balances");
      if (!container) return;
      const monthsToDisplay = [],
        y = this.currentMonth.getFullYear(),
        m = this.currentMonth.getMonth();
      let numMonths =
        this.viewMode === "2months" ? 2 : this.viewMode === "3months" ? 3 : 1;
      for (let i = 0; i < numMonths; i++)
        monthsToDisplay.push(`${y}-${String(m + i + 1).padStart(2, "0")}`);

      container.style.display = "flex";
      container.innerHTML = parents
        .map((person) => {
          let cards = `<div class="fc-minimal-balance-card"><strong class="col-alex">${person.name.toUpperCase()}</strong><div class="fc-minimal-chips">`;
          ["CP", "JRA", "JA"].forEach((type) => {
            const startBal =
              this.monthlyLeaveBalances[person.id]?.[type]?.[monthsToDisplay[0]]
                ?.availableAtMonthStart || 0;
            let totalUsed = 0;
            monthsToDisplay.forEach(
              (ym) =>
                (totalUsed +=
                  this.monthlyLeaveBalances[person.id]?.[type]?.[ym]
                    ?.usedInMonth || 0),
            );
            const endBal = Math.max(0, startBal - totalUsed);
            const fmt = (n) =>
              n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "0";

            let alertHtml = "";
            const cMonth = parseInt(monthsToDisplay[0].split("-")[1]);
            if (endBal > 0) {
              if (type === "CP" && cMonth >= 6 && cMonth <= 7)
                alertHtml = `<div class="fc-burn-alert" title="Alerte perte">🔥</div>`;
              else if (
                type === "JRA" &&
                (cMonth === 1 || cMonth === 2) &&
                endBal > 2
              )
                alertHtml = `<div class="fc-burn-alert" title="Perte imminente">🔥</div>`;
            }
            cards += `<div class="fc-min-chip" title="Solde départ: ${fmt(startBal)}"><span class="type">${type}</span><span class="val">${fmt(endBal)}</span>${totalUsed > 0 ? `<span class="fc-used-badge">-${fmt(totalUsed)}</span>` : ""}${alertHtml}</div>`;
          });
          return cards + `</div></div>`;
        })
        .join("");
    }

    syncSummaryWithMonth() {
      const typeSelect = document.getElementById("summType"),
        valueSelect = document.getElementById("summValue");
      if (typeSelect && valueSelect) {
        if (typeSelect.value !== "month") {
          typeSelect.value = "month";
          typeSelect.dispatchEvent(new Event("change"));
        }
        valueSelect.value = `${this.currentMonth.getFullYear()}-${String(this.currentMonth.getMonth() + 1).padStart(2, "0")}`;
        this.updateGlobalSummary();
      }
    }

    generateMonthHTML(year, month) {
      let html = `<table class="fc-month-table"><thead><tr>`;
      ["L", "M", "M", "J", "V"].forEach((d) => (html += `<th>${d}</th>`));
      html += `</tr></thead><tbody><tr>`;

      const daysInMonth = new Date(year, month + 1, 0).getDate();
      let currentRenderedCols = 0,
        startDay = (new Date(year, month, 1).getDay() + 6) % 7;

      if (startDay < 5) {
        for (let i = 0; i < startDay; i++) {
          html += `<td class="fc-day--other-month"></td>`;
          currentRenderedCols++;
        }
      }

      for (let d = 1; d <= daysInMonth; d++) {
        const dateObj = new Date(year, month, d),
          dayOfWeek = dateObj.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) continue;

        if (currentRenderedCols === 5) {
          html += `</tr><tr>`;
          currentRenderedCols = 0;
        }
        const iso = this.getLocalIsoDate(dateObj),
          todayIso = this.getLocalIsoDate(new Date());

        let cls = "fc-month-day" + (iso === todayIso ? " fc-day--today" : "");
        const dayEvts = this.events.filter((e) => e.date === iso);

        if (dayEvts.some((e) => e.type === "VACANCES_SCOLAIRES"))
          cls += " fc-day--school-holiday";
        if (dayEvts.some((e) => e.type === "PUBLIC_HOLIDAY"))
          cls += " fc-day--public-holiday";
        if (dayEvts.some((e) => e.type === "OFF_CAROLE"))
          cls += " fc-day--off-carole";
        if (dayEvts.some((e) => e.type === "EXTRA_OFF_CAROLE"))
          cls += " fc-day--extra-off-carole";
        if (dayEvts.some((e) => ["CENTRE", "AVIS"].includes(e.type)))
          cls += " fc-day--has-guard";

        let content = `<div style="position:relative; height:100%;"><span class="fc-day-number">${d}</span>`;
        if (dayEvts.some((e) => e.type === "PEP_SICK"))
          content += `<span style="position:absolute; bottom:2px; right:2px;">🤒</span>`;

        const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
        if (dayLeaves.length) {
          content += `<div style="position:absolute; bottom:2px; left:2px; font-size:10px; font-weight:bold;">`;
          parents.forEach((parent, index) => {
            if (
              dayLeaves.some(
                (l) => parseInt(l.person_id) === parseInt(parent.id),
              )
            ) {
              content += `<span style="color:${index % 2 === 0 ? "#0f766e" : "#b45309"}">${parent.name.charAt(0).toUpperCase()}</span> `;
            }
          });
          content += `</div>`;
        }
        html += `<td class="${cls}" data-date="${iso}">${content}</div></td>`;
        currentRenderedCols++;
      }
      while (currentRenderedCols < 5 && currentRenderedCols > 0) {
        html += `<td class="fc-day--other-month"></td>`;
        currentRenderedCols++;
      }
      return html + `</tr></tbody></table>`;
    }

    initSummaryControls() {
      const typeSelect = document.getElementById("summType"),
        valueSelect = document.getElementById("summValue");
      if (!typeSelect || !valueSelect) return;

      const years = new Set(),
        months = new Set();
      this.weeks.forEach((w) =>
        Object.values(w.dayDates).forEach((d) => {
          years.add(d.getFullYear());
          months.add(
            `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
          );
        }),
      );

      const populateValues = () => {
        const currentType = typeSelect.value,
          prevValue = valueSelect.value;
        valueSelect.innerHTML = "";
        if (currentType === "year") {
          Array.from(years)
            .sort()
            .forEach((y) => valueSelect.add(new Option(y, y)));
          valueSelect.value = years.has(parseInt(prevValue))
            ? prevValue
            : new Date().getFullYear();
        } else {
          Array.from(months)
            .sort()
            .forEach((m) => {
              const [y, mo] = m.split("-");
              valueSelect.add(
                new Option(
                  new Intl.DateTimeFormat("fr-FR", {
                    month: "long",
                    year: "numeric",
                  }).format(new Date(y, mo - 1, 1)),
                  m,
                ),
              );
            });
          const nowIso = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, "0")}`;
          if (months.has(nowIso)) valueSelect.value = nowIso;
        }
        this.updateGlobalSummary();
      };

      if (!this.summaryListenersAttached) {
        typeSelect.addEventListener("change", populateValues);
        valueSelect.addEventListener("change", () =>
          this.updateGlobalSummary(),
        );
        this.summaryListenersAttached = true;
      }
      populateValues();
    }

    updateGlobalSummary() {
      const div = document.getElementById("globalSummary"),
        typeSelect = document.getElementById("summType"),
        valueSelect = document.getElementById("summValue");
      if (!div || !valueSelect?.value) return;

      const filterType = typeSelect.value,
        filterValue = valueSelect.value;
      const stats = { off: 0, extra: 0, sick: 0, pep: 0 };

      this.weeks.forEach((w) => {
        Object.values(w.dayDates).forEach((dateObj) => {
          if (
            (filterType === "year" &&
              dateObj.getFullYear().toString() === filterValue) ||
            (filterType === "month" &&
              this.getLocalIsoDate(dateObj).slice(0, 7) === filterValue)
          ) {
            const dayEvents = this.events.filter(
              (e) => e.date === this.getLocalIsoDate(dateObj),
            );
            dayEvents.forEach((e) => {
              const dur = parseFloat(e.duration) || 1;
              if (e.type === "OFF_CAROLE") stats.off += dur;
              if (e.type === "EXTRA_OFF_CAROLE") stats.extra += dur;
              if (e.type === "PEP_SICK") stats.sick += dur;
            });
            if (!this.publicHolidayDates.has(this.getLocalIsoDate(dateObj))) {
              let dayAbsence = 0;
              dayEvents.forEach((e) => {
                if (
                  ["OFF_CAROLE", "EXTRA_OFF_CAROLE", "PEP_SICK"].includes(
                    e.type,
                  )
                )
                  dayAbsence += parseFloat(e.duration) || 1;
              });
              stats.pep += Math.max(0, 1 - dayAbsence);
            }
          }
        });
      });

      const tr = window.I18N || {};
      div.innerHTML = `
                <div class="fc-summary-item"><span class="fc-summary-label">${tr.leg_off_carole || "Off"}</span><span class="fc-summary-value">${parseFloat(stats.off.toFixed(1))} j</span></div>
                <div class="fc-summary-item"><span class="fc-summary-label">${tr.leg_extra_off || "Extra"}</span><span class="fc-summary-value">${parseFloat(stats.extra.toFixed(1))} j</span></div>
                <div class="fc-summary-item"><span class="fc-summary-label">${tr.leg_pep_sick || "Maladie"}</span><span class="fc-summary-value">${parseFloat(stats.sick.toFixed(1))} j</span></div>
                <div class="fc-summary-item"><span class="fc-summary-label">${tr.leg_presence || "Présence"}</span><span class="fc-summary-value">${parseFloat(stats.pep.toFixed(1))} j</span></div>
            `;
    }

    updateSchoolYearLabel() {
      const lbl = document.getElementById("fc-current-school-year-label");
      if (lbl)
        lbl.textContent = `${this.currentSchoolYearStart} – ${this.currentSchoolYearStart + 1}`;
    }

    processWeeks(rawWeeks) {
      return rawWeeks.map((w) => ({
        id: `${w.week_iso_year}-W${w.week_iso_number}`,
        monthKey: `${w.year}-${String(w.month).padStart(2, "0")}`,
        monthName: w.month_name,
        weekLabel: w.week_label,
        dayDates: {
          mon: new Date(w.mon_date + "T00:00:00"),
          tue: new Date(w.tue_date + "T00:00:00"),
          wed: new Date(w.wed_date + "T00:00:00"),
          thu: new Date(w.thu_date + "T00:00:00"),
          fri: new Date(w.fri_date + "T00:00:00"),
        },
        dayFlags: {
          mon: { events: [] },
          tue: { events: [] },
          wed: { events: [] },
          thu: { events: [] },
          fri: { events: [] },
        },
        totals: {
          offCarole: 0,
          extraOffCarole: 0,
          centre: 0,
          avis: 0,
          pepSick: 0,
          presencePep: 0,
          alexCP: 0,
          alexJRA: 0,
          alexJA: 0,
          laiaCP: 0,
          laiaJRA: 0,
          laiaJA: 0,
        },
      }));
    }

    async fetchApi(url) {
      return pachaFetch(url);
    }
    async postApi(url, data) {
      return pachaFetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
    }
    async changeSchoolYear(delta) {
      this.currentSchoolYearStart += delta;
      this.updateSchoolYearLabel();
      await this.refreshAllData();
    }

    setupEventListeners() {
      // -- BRANCHEMENT MODALE DE CONFIGURATION (NOUVEAU) --
      const btnSettings = document.getElementById("btnOpenCalendarSettings");
      if (btnSettings)
        btnSettings.addEventListener("click", openCalendarSettings);

      if (this.planningBody) {
        this.planningBody.addEventListener("mousedown", (e) =>
          this.handleMouseDown(e),
        );
        document.addEventListener("mousemove", (e) => this.handleMouseMove(e));
        document.addEventListener("mouseup", (e) => this.handleMouseUp(e));
        this.planningBody.addEventListener(
          "touchstart",
          (e) => this.handleTouchStart(e),
          { passive: false },
        );
        document.addEventListener("touchmove", (e) => this.handleTouchMove(e), {
          passive: false,
        });
        document.addEventListener("touchend", (e) => this.handleTouchEnd(e));
      }
      const scrollWrapper = document.getElementById("planningTable-wrapper");
      if (scrollWrapper) {
        scrollWrapper.addEventListener("scroll", async () => {
          if (this._isAutoLoading) return;
          if (
            scrollWrapper.scrollTop + scrollWrapper.clientHeight >=
            scrollWrapper.scrollHeight - 5
          ) {
            this._isAutoLoading = true;
            await this.changeSchoolYear(1);
            scrollWrapper.scrollTop = 5;
            setTimeout(() => (this._isAutoLoading = false), 500);
          } else if (scrollWrapper.scrollTop === 0) {
            this._isAutoLoading = true;
            await this.changeSchoolYear(-1);
            scrollWrapper.scrollTop =
              scrollWrapper.scrollHeight - scrollWrapper.clientHeight - 5;
            setTimeout(() => (this._isAutoLoading = false), 500);
          }
        });
      }

      const btnOpen = document.getElementById("btnOpenHolidays"),
        btnClose = document.getElementById("btnCloseHolidays"),
        modal = document.getElementById("modalHolidays");
      if (btnOpen && modal)
        btnOpen.addEventListener("click", () => {
          modal.classList.add("open");
          document.body.classList.add("no-scroll");
          this.renderModalHolidays();
        });
      if (btnClose && modal)
        btnClose.addEventListener("click", () => {
          modal.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      if (modal)
        modal.addEventListener("click", (e) => {
          if (e.target === modal) {
            modal.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });

      const btnSnap = document.getElementById("btnOpenSnapshotModal"),
        modalSnap = document.getElementById("modalSnapshot"),
        btnCloseSnap = document.getElementById("btnCloseSnapshot"),
        formSnap = document.getElementById("formSnapshot");
      if (btnSnap && modalSnap)
        btnSnap.addEventListener("click", () => {
          modalSnap.classList.add("open");
          document.body.classList.add("no-scroll");
          const snapDateInput = document.getElementById("snapDate");
          if (snapDateInput)
            snapDateInput.value = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, "0")}-01`;
        });
      if (btnCloseSnap && modalSnap)
        btnCloseSnap.addEventListener("click", () => {
          modalSnap.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      if (modalSnap)
        modalSnap.addEventListener("click", (e) => {
          if (e.target === modalSnap) {
            modalSnap.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });
      if (formSnap)
        formSnap.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            await this.postApi(
              "/modules/family-calendar/includes/api/save-leave-snapshot.php",
              {
                person_id: document.getElementById("snapPerson").value,
                leave_type: document.getElementById("snapType").value,
                snapshot_date: document.getElementById("snapDate").value,
                remaining_balance: document.getElementById("snapBalance").value,
              },
            );
            modalSnap.classList.remove("open");
            document.body.classList.remove("no-scroll");
            formSnap.reset();
            await this.refreshAllData();
          } catch (error) {
            alert("Erreur lors de la sauvegarde.");
          }
        });

      if (this.monthCalendar)
        this.monthCalendar.addEventListener("click", (e) => {
          const td = e.target.closest("td[data-date]");
          if (td && td.dataset.date) {
            this.monthSelectedCells = [td];
            this.showMenu(e.pageX, e.pageY, [td.dataset.date], true);
          }
        });
      document.addEventListener("click", (e) => this.closeMenusIfOutside(e));
      const handleMenu = (e) => {
        const btn = e.target.closest("button");
        if (btn && btn.dataset.action) this.handleMenuAction(btn.dataset);
      };
      if (this.selectionMenu)
        this.selectionMenu.addEventListener("click", handleMenu);
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.addEventListener("click", handleMenu);

      document
        .getElementById("fc-prev-month")
        ?.addEventListener("click", () => {
          this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
          this.renderMonthCalendar();
        });
      document
        .getElementById("fc-next-month")
        ?.addEventListener("click", () => {
          this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
          this.renderMonthCalendar();
        });
      document.getElementById("fc-today-btn")?.addEventListener("click", () => {
        this.currentMonth = new Date();
        this.currentMonth.setDate(1);
        this.renderMonthCalendar();
      });

      if (this.monthCalendar) {
        let startX = 0,
          startY = 0;
        this.monthCalendar.addEventListener(
          "touchstart",
          (e) => {
            startX = e.changedTouches[0].screenX;
            startY = e.changedTouches[0].screenY;
          },
          { passive: true },
        );
        this.monthCalendar.addEventListener(
          "touchend",
          (e) => {
            const dX = e.changedTouches[0].screenX - startX,
              dY = e.changedTouches[0].screenY - startY;
            if (Math.abs(dX) > Math.abs(dY) && Math.abs(dX) > 50) {
              const num =
                this.viewMode === "2months"
                  ? 2
                  : this.viewMode === "3months"
                    ? 3
                    : 1;
              this.currentMonth.setMonth(
                this.currentMonth.getMonth() + (dX < 0 ? num : -num),
              );
              this.renderMonthCalendar();
            }
          },
          { passive: true },
        );
      }
      document
        .getElementById("fc-prev-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(-1));
      document
        .getElementById("fc-next-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(1));
      document.querySelectorAll(".fc-view-button").forEach((btn) =>
        btn.addEventListener("click", (e) => {
          document
            .querySelectorAll(".fc-view-button")
            .forEach((b) => b.classList.remove("fc-view-button--active"));
          e.target.classList.add("fc-view-button--active");
          this.viewMode = e.target.dataset.view;
          this.renderMonthCalendar();
        }),
      );
    }

    handleMouseDown(e) {
      const td = e.target.closest("#planningTable td[data-date]");
      if (!td) return;
      e.preventDefault();
      this.clearSelection();
      this.isSelecting = true;
      this.selectCell(td);
    }
    handleMouseMove(e) {
      if (!this.isSelecting) return;
      const td = e.target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
    }
    handleMouseUp(e) {
      if (!this.isSelecting) return;
      const td = e.target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
      this.isSelecting = false;
      if (this.selectedCells.length)
        this.showMenu(
          e.pageX,
          e.pageY,
          this.selectedCells.map((c) => c.dataset.date),
          false,
        );
    }
    handleTouchStart(e) {
      const td = e.target.closest("#planningTable td[data-date]");
      if (!td) return;
      e.preventDefault();
      this.clearSelection();
      this.isSelecting = true;
      this.selectCell(td);
    }
    handleTouchMove(e) {
      if (!this.isSelecting) return;
      e.preventDefault();
      const touch = e.touches[0],
        target = document.elementFromPoint(touch.clientX, touch.clientY);
      if (!target) return;
      const td = target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
    }
    handleTouchEnd(e) {
      if (!this.isSelecting) return;
      this.isSelecting = false;
      if (this.selectedCells.length)
        this.showMenu(
          0,
          0,
          this.selectedCells.map((c) => c.dataset.date),
          false,
        );
    }

    selectCell(cell) {
      cell.classList.add("fc-day--selected");
      this.selectedCells.push(cell);
    }
    clearSelection() {
      this.selectedCells.forEach((c) => c.classList.remove("fc-day--selected"));
      this.selectedCells = [];
      if (this.selectionMenu) this.selectionMenu.style.display = "none";
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.style.display = "none";
    }
    closeMenusIfOutside(e) {
      if (
        !this.selectionMenu?.contains(e.target) &&
        !this.monthSelectionMenu?.contains(e.target) &&
        !this.menuJustOpened
      )
        this.clearSelection();
    }

    showMenu(x, y, dates, isMonthView) {
      const menu = isMonthView ? this.monthSelectionMenu : this.selectionMenu;
      if (!menu) return;
      this._currentBulkInfo = { dates };

      const activeEvents = new Set();
      this.events.forEach((e) => {
        if (dates.includes(e.date)) activeEvents.add(e.type);
      });

      const activeLeaves = {};
      parents.forEach((p) => (activeLeaves[p.id] = new Set()));
      this.leaves.forEach((l) => {
        if (dates.includes(l.leave_date) && activeLeaves[l.person_id])
          activeLeaves[l.person_id].add(l.leave_type);
      });

      const getBtnClass = (type, pid = null) =>
        (pid ? activeLeaves[pid]?.has(type) : activeEvents.has(type))
          ? "fc-menu-btn--active"
          : "";
      const getBtnIcon = (type, pid = null) =>
        (pid ? activeLeaves[pid]?.has(type) : activeEvents.has(type))
          ? "✓ "
          : "";
      const tr = window.I18N || {};
      const dateLabel =
        dates.length > 1
          ? `${dates.length} Jours`
          : new Date(dates[0]).toLocaleDateString(window.I18N_LANG || "fr-FR");
      const trashSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>`;
      const buildHeader = (title, action, cat) =>
        `<div class="fc-menu-header"><strong>${title}</strong>${action ? `<button class="fc-menu-clear-icon" data-action="${action}" ${cat ? `data-cat="${cat}"` : ""}>${trashSvg}</button>` : ""}</div>`;

      let html = `<div class="fc-menu-section" style="border-bottom: none; padding-bottom: 0;"><strong style="font-size:0.85rem;">${dateLabel}</strong></div>`;
      html += `<div class="fc-menu-section">${buildHeader(tr.fc_menu_carole || "Carole", "clear-type", "CONGE")}<div class="fc-menu-grid"><button class="fc-menu-btn ${getBtnClass("OFF_CAROLE")}" data-action="add" data-type="OFF_CAROLE">${getBtnIcon("OFF_CAROLE")}${tr.btn_off || "OFF"}</button><button class="fc-menu-btn ${getBtnClass("EXTRA_OFF_CAROLE")}" data-action="add" data-type="EXTRA_OFF_CAROLE">${getBtnIcon("EXTRA_OFF_CAROLE")}${tr.btn_extra || "EXTRA"}</button></div></div>`;
      html += `<div class="fc-menu-section">${buildHeader(tr.leg_centre || "Centre", "clear-type", "GARDE")}<div class="fc-menu-grid"><button class="fc-menu-btn ${getBtnClass("CENTRE")}" data-action="add" data-type="CENTRE">${getBtnIcon("CENTRE")}${tr.leg_centre || "Centre"}</button><button class="fc-menu-btn ${getBtnClass("AVIS")}" data-action="add" data-type="AVIS">${getBtnIcon("AVIS")}${tr.leg_avis || "Avis"}</button></div></div>`;
      html += `<div class="fc-menu-section">${buildHeader("Pep", "clear-type", "PEP")}<button class="fc-menu-btn ${getBtnClass("PEP_SICK")}" data-action="add" data-type="PEP_SICK" style="width:100%">${getBtnIcon("PEP_SICK")}${tr.leg_pep_sick || "Maladie"} 🤒</button></div>`;
      html += `<div class="fc-menu-section">${buildHeader(tr.fc_menu_kids_leaves || "Congés Parents", null, null)}<div class="fc-menu-leaves-table"><table><thead><tr>`;
      parents.forEach(
        (p) =>
          (html += `<th><div class="fc-th-inline">${p.name} <button class="fc-menu-clear-icon fc-menu-btn-th" data-action="clear-leaves-person" data-pid="${p.id}">${trashSvg}</button></div></th>`),
      );
      html += `</tr></thead><tbody>`;
      ["CP", "JRA", "JA"].forEach((t) => {
        html += `<tr>`;
        parents.forEach(
          (p) =>
            (html += `<td><button class="fc-menu-btn ${getBtnClass(t, p.id)}" data-action="add-leave" data-pid="${p.id}" data-type="${t}">${getBtnIcon(t, p.id)}${t}</button></td>`),
        );
        html += `</tr>`;
      });
      html += `</tbody></table></div></div>`;

      menu.innerHTML = html;
      let left = x + 10;
      if (left + 240 > window.innerWidth) left = x - 250;
      menu.style.left = `${left}px`;
      menu.style.top = `${y + 10}px`;
      menu.style.display = "block";
      this.menuJustOpened = true;
      setTimeout(() => (this.menuJustOpened = false), 100);
    }

    async handleMenuAction(dataset) {
      if (this.selectionMenu) this.selectionMenu.style.display = "none";
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.style.display = "none";
      const { action, type, pid, cat } = dataset,
        dates = this._currentBulkInfo.dates;

      try {
        if (action === "add") {
          let typesToClear = [];
          if (["OFF_CAROLE", "EXTRA_OFF_CAROLE"].includes(type))
            typesToClear = CONGE_TYPES;
          if (["CENTRE", "AVIS"].includes(type)) typesToClear = GUARDE_TYPES;
          if (type === "PEP_SICK") typesToClear = PEP_TYPES;

          if (typesToClear.length)
            await this.postApi(
              "/modules/family-calendar/includes/api/manage-event.php",
              { action: "bulk_delete_day_types", dates, types: typesToClear },
            );
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            dates.map((d) => ({
              date: d,
              type: type,
              duration: 1,
              person: "Carole",
            })),
          );
        } else if (action === "clear-type") {
          let typesToClear =
            cat === "CONGE"
              ? CONGE_TYPES
              : cat === "GARDE"
                ? GUARDE_TYPES
                : cat === "PEP"
                  ? PEP_TYPES
                  : [];
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-event.php",
            { action: "bulk_delete_day_types", dates, types: typesToClear },
          );
        } else if (action === "add-leave") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: parseInt(pid),
            },
          );
          await this.postApi(
            "/modules/family-calendar/includes/api/save-leaves.php",
            dates.map((d) => ({
              date: d,
              person_id: parseInt(pid),
              leave_type: type,
              duration: 1,
            })),
          );
        } else if (action === "clear-leaves-person") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: parseInt(pid),
            },
          );
        }
        await this.refreshAllData();
      } catch (e) {
        alert("Erreur: " + e.message);
      }
    }
  }

  new FamilyCalendar();
});
