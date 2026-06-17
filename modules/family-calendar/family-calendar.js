/**
 * family-calendar.js (Version Multi-tenant 100% Purifiée)
 * 100% Dynamique - Zero nom hardcodé.
 */

// ============================================================================
// 1. VARIABLES GLOBALES & LOGIQUE DE CONFIGURATION (SETTINGS)
// ============================================================================
let calGlobalData = null;
let currentSelectedMemberId = null;
let localCareModes = [];

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
        .map((p) => {
          let roleDisplay = tr("fc_role_unknown") || "Inconnu";
          if (p.role === "parent")
            roleDisplay = "👨‍👩‍👦 " + (tr("fc_role_adult") || "Adulte");
          else if (p.role === "child" || p.role === "enfant")
            roleDisplay = "👶 " + (tr("fc_role_child") || "Enfant");
          else if (p.role === "helper")
            roleDisplay = "💼 " + (tr("fc_role_helper") || "Intervenant");

          return `<option value="${p.id}" data-role="${p.role}">${p.name} (${roleDisplay})</option>`;
        })
        .join("");
    }

    if (typeof populateCalendarSettings === "function") {
      populateCalendarSettings(calGlobalData.calendar_settings);
    }

    switchCalendarTab("foyer");

    const modalSettings = document.getElementById("modalCalendarSettings");
    if (modalSettings) {
      modalSettings.classList.add("open");
      document.body.classList.add("no-scroll");
    }
  } catch (err) {
    alert((tr("fc_error") || "Erreur : ") + err.message);
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
  } else if (tabName === "affichage") {
    document.getElementById("cal-pane-affichage").style.display = "block";
    document.getElementById("tab-btn-affichage").classList.add("active");
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

    if (window.showToast)
      showToast(
        tr("fc_settings_updated") || "Configuration enregistrée !",
        "success",
      );
    else alert(tr("fc_settings_updated") || "Configuration enregistrée !");

    setTimeout(() => window.location.reload(), 800);
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

window.addLeaveRowToMatrix = function () {
  const tbody = document.getElementById("tbodyLeaveMatrix");
  const empty = document.getElementById("emptyMatrixRow");
  if (empty) empty.remove();
  if (!tbody) return;

  const rowTypeOptions = (calGlobalData.leave_types || [])
    .map((lt) => `<option value="${lt.code}">${lt.code} - ${lt.label}</option>`)
    .join("");

  const trRow = document.createElement("tr");
  trRow.className = "js-leave-row";
  trRow.style.borderBottom = "1px solid var(--border-light)";
  trRow.innerHTML = `
        <td style="padding: 6px 2px;">
            <select class="pf-input js-leave-type" required style="width:100%; padding:6px 2px; font-size:0.8rem; box-sizing: border-box;">
                <option value="">${tr("fc_leave_code_placeholder") || "Code..."}</option>
                ${rowTypeOptions}
            </select>
        </td>
        <td style="padding: 6px 2px;">
            <select class="pf-input js-leave-method" style="width:100%; padding:6px 2px; font-size:0.8rem; box-sizing: border-box;">
                <option value="FIXED">${tr("fc_leave_method_fixed") || "Fixe"}</option>
                <option value="ACCUMULATED">${tr("fc_leave_method_accumulated") || "Cumul"}</option>
            </select>
        </td>
        <td style="padding: 6px 2px;"><input type="number" step="0.5" class="pf-input js-leave-allowance" placeholder="0" required style="width:100%; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;"></td>
        <td style="padding: 6px 2px;"><input type="text" class="pf-input js-leave-date" placeholder="${tr("fc_date_format_placeholder") || "JJ/MM"}" pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[012])" maxlength="5" required style="width:100%; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;" title="${tr("fc_date_format_title") || "Format JJ/MM"}"></td>
        <td style="padding: 6px 2px; text-align:right;"><button type="button" class="btn-icon-action delete" style="padding:4px; margin:0;" onclick="this.closest('tr').remove()" title="${tr("btn_delete") || "Supprimer"}">🗑️</button></td>
  `;
  tbody.appendChild(trRow);
};

window.populateCalendarSettings = function (settings) {
  if (!settings) return;
  const viewSelect = document.getElementById("setCalView");
  if (viewSelect && settings.calendar_default_view)
    viewSelect.value = settings.calendar_default_view;
  const firstDaySelect = document.getElementById("setCalFirstDay");
  if (firstDaySelect && settings.calendar_first_day !== undefined)
    firstDaySelect.value = settings.calendar_first_day;
  const hoursInput = document.getElementById("setCalHours");
  if (hoursInput && settings.calendar_working_hours)
    hoursInput.value = settings.calendar_working_hours;
};

window.loadAdultConfigView = function (memberId, zoneElement) {
  let userLeaves = calGlobalData.leaves[memberId] || [];

  const tableRows = userLeaves
    .map((l) => {
      let dateDisp = "";
      if (l.date) {
        const parts = l.date.split("-");
        if (parts.length >= 3) dateDisp = `${parts[2]}/${parts[1]}`;
      }

      let rowTypeOptions = (calGlobalData.leave_types || [])
        .map(
          (lt) =>
            `<option value="${lt.code}" ${lt.code === l.type ? "selected" : ""}>${lt.code} - ${lt.label}</option>`,
        )
        .join("");

      if (!calGlobalData.leave_types.some((lt) => lt.code === l.type)) {
        rowTypeOptions += `<option value="${l.type}" selected>${l.type} (${tr("fc_leave_obsolete") || "Obsolète"})</option>`;
      }

      return `
      <tr class="js-leave-row" style="border-bottom: 1px solid var(--border-light);">
          <td style="padding: 6px 2px;">
              <select class="pf-input js-leave-type" required style="width:100%; padding:6px 2px; font-size:0.8rem; box-sizing: border-box;">
                  <option value="">${tr("fc_leave_code_placeholder") || "Code..."}</option>
                  ${rowTypeOptions}
              </select>
          </td>
          <td style="padding: 6px 2px;">
              <select class="pf-input js-leave-method" style="width:100%; padding:6px 2px; font-size:0.8rem; box-sizing: border-box;">
                  <option value="FIXED" ${l.method === "FIXED" ? "selected" : ""}>${tr("fc_leave_method_fixed") || "Fixe"}</option>
                  <option value="ACCUMULATED" ${l.method === "ACCUMULATED" ? "selected" : ""}>${tr("fc_leave_method_accumulated") || "Cumul"}</option>
              </select>
          </td>
          <td style="padding: 6px 2px;"><input type="number" step="0.5" class="pf-input js-leave-allowance" value="${l.allowance || 0}" placeholder="0" required style="width:100%; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;"></td>
          <td style="padding: 6px 2px;"><input type="text" class="pf-input js-leave-date" value="${dateDisp}" placeholder="${tr("fc_date_format_placeholder") || "JJ/MM"}" pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[012])" maxlength="5" required style="width:100%; padding:6px 4px; font-size:0.85rem; text-align:center; box-sizing: border-box;" title="${tr("fc_date_format_title") || "Format JJ/MM"}"></td>
          <td style="padding: 6px 2px; text-align:right;"><button type="button" class="btn-icon-action delete" style="padding:4px; margin:0;" onclick="this.closest('tr').remove()" title="${tr("btn_delete") || "Supprimer"}">🗑️</button></td>
      </tr>
      `;
    })
    .join("");

  zoneElement.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div>
                <h5 style="margin:0 0 4px 0; color:var(--text-main); font-size:1rem;">🗓️ ${tr("fc_matrix_title") || "Matrice des Congés"}</h5>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0; line-height:1.3;">${tr("fc_matrix_desc") || "Définissez les quotas et la date de renouvellement."}</p>
            </div>
            <button type="button" class="pf-btn pf-btn-secondary" style="padding:6px 10px; font-size:0.8rem; white-space:nowrap;" onclick="addLeaveRowToMatrix()">➕ ${tr("btn_add") || "Ajouter"}</button>
        </div>
        <div style="overflow-x:hidden; margin: 0 -5px;">
            <table style="width:100%; border-collapse:collapse; margin-bottom:15px; table-layout: fixed;">
                <thead>
                    <tr style="font-size:0.75rem; text-align:left; color:var(--text-muted); text-transform:uppercase;">
                        <th style="padding:0 2px 8px; width: 33%;">${tr("fc_table_code") || "Code"}</th>
                        <th style="padding:0 2px 8px; width: 22%;">${tr("fc_table_method") || "Méthode"}</th>
                        <th style="padding:0 2px 8px; width: 15%;">${tr("fc_table_quota") || "Quota"}</th>
                        <th style="padding:0 2px 8px; width: 20%;">${tr("fc_table_renewal") || "Renouv."}</th>
                        <th style="padding:0 2px 8px; width: 10%;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyLeaveMatrix">
                    ${tableRows || `<tr><td colspan="5" id="emptyMatrixRow" style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic; font-size:0.9rem;">${tr("fc_matrix_empty") || "Aucun compteur défini."}</td></tr>`}
                </tbody>
            </table>
        </div>
        <div style="display:flex; justify-content:flex-end; border-top:1px solid var(--border-light); padding-top:12px;">
            <button type="button" class="pf-btn pf-btn-primary" style="padding:8px 16px;" onclick="submitMemberLeaves()">${tr("btn_save") || "Enregistrer"}</button>
        </div>
    `;
};

window.loadMemberConfigView = function () {
  const select = document.getElementById("selectCalMember");
  const zone = document.getElementById("memberConfigZone");
  if (!select || !select.value || !zone) return;

  window.currentSelectedMemberId = parseInt(select.value);
  const role = (
    select.options[select.selectedIndex].dataset.role || ""
  ).toLowerCase();

  if (role === "child" || role === "enfant") {
    const currentPerson = calGlobalData.people.find(
      (k) => parseInt(k.id) === window.currentSelectedMemberId,
    );
    let savedModes = [];
    try {
      if (currentPerson.modes && Array.isArray(currentPerson.modes))
        savedModes = currentPerson.modes;
      else if (typeof currentPerson.care_modes === "string")
        savedModes = JSON.parse(currentPerson.care_modes);
    } catch (e) {}

    const activeModesInFoyer = calGlobalData.foyer.care_modes || [];
    const modesHtml = activeModesInFoyer
      .map(
        (m) => `
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem;">
                <input type="checkbox" class="js-care-mode-cb" value="${m}" ${savedModes.includes(m) ? "checked" : ""}> ${m}
            </label>
        `,
      )
      .join("");

    zone.innerHTML = `
            <h5 style="margin:0 0 8px 0; color:var(--text-main);">${tr("fc_care_modes_title") || "Modes de Garde"}</h5>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">Quels modes de garde s'appliquent à cet enfant ?</p>
            <div style="display:flex; flex-direction:column; gap:6px;">
                ${modesHtml || `<em>Aucun mode de garde configuré dans le foyer.</em>`}
            </div>
            <button class="pf-btn pf-btn-primary" style="margin-top:15px; width:100%;" onclick="submitChildCareModes()">${tr("btn_save_rights") || "Enregistrer"}</button>
        `;
  } else {
    window.loadAdultConfigView(window.currentSelectedMemberId, zone);
  }
};

window.submitChildCareModes = async function () {
  if (!window.currentSelectedMemberId) return;
  const checkboxes = document.querySelectorAll(".js-care-mode-cb:checked");
  const selectedModes = Array.from(checkboxes).map((cb) => cb.value);
  try {
    const formData = new FormData();
    formData.append("action", "save_child_care_modes");
    formData.append("person_id", window.currentSelectedMemberId);
    formData.append("care_modes", JSON.stringify(selectedModes));

    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/settings.php",
      { method: "POST", body: formData },
    );
    if (!res.success) throw new Error(res.error);
    if (window.showToast)
      showToast(tr("fc_child_saved") || "Sauvegardé !", "success");
    else alert(tr("fc_child_saved") || "Sauvegardé !");

    const currentPerson = calGlobalData.people.find(
      (k) => parseInt(k.id) === window.currentSelectedMemberId,
    );
    if (currentPerson) {
      currentPerson.care_modes = JSON.stringify(selectedModes);
    }
  } catch (err) {
    alert("Erreur: " + err.message);
  }
};

async function submitMemberLeaves() {
  if (!currentSelectedMemberId || isNaN(currentSelectedMemberId)) {
    alert("Erreur technique : Aucun membre valide sélectionné.");
    return;
  }
  try {
    const rows = document.querySelectorAll(".js-leave-row");
    const leavesPayload = Array.from(rows)
      .map((row) => ({
        type: row.querySelector(".js-leave-type").value.trim().toUpperCase(),
        method: row.querySelector(".js-leave-method").value,
        allowance: row.querySelector(".js-leave-allowance").value,
        date: row.querySelector(".js-leave-date").value,
      }))
      .filter((l) => l.type && l.date);

    const formData = new FormData();
    formData.append("action", "save_member_leaves");
    formData.append("person_id", currentSelectedMemberId);
    formData.append("leaves", JSON.stringify(leavesPayload));

    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/settings.php",
      { method: "POST", body: formData },
    );
    if (!res.success) throw new Error(res.error);

    if (window.showToast)
      showToast(tr("fc_matrix_saved") || "Matrice enregistrée", "success");
    else alert(tr("fc_matrix_saved") || "Matrice enregistrée");

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

      this.parents = [];
      this.kids = [];
      this.helpers = [];
      this.careModes = [];
      this.leaveMatrix = {};
      this.monthlyLeaveBalances = {};

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

      try {
        const res = await pachaFetch(
          "/modules/family-calendar/includes/api/settings.php?action=get_all",
        );
        if (res && res.success && res.data) {
          window.calGlobalData = res.data;

          this.parents = (res.data.people || []).filter(
            (p) => p.role === "parent",
          );
          this.kids = (res.data.people || []).filter((p) =>
            ["child", "enfant"].includes(p.role),
          );
          this.helpers = (res.data.people || []).filter(
            (p) => p.role === "helper",
          );
          this.careModes = res.data.foyer.care_modes || [];
          this.leaveMatrix = res.data.leaves || {};

          const settings = res.data.calendar_settings || {};
          if (settings.calendar_default_view) {
            this.viewMode = settings.calendar_default_view;
            document
              .querySelectorAll(".fc-view-button")
              .forEach((b) => b.classList.remove("fc-view-button--active"));
            const activeBtn = document.querySelector(
              `.fc-view-button[data-view="${this.viewMode}"]`,
            );
            if (activeBtn) activeBtn.classList.add("fc-view-button--active");
          }
        }
      } catch (e) {
        console.warn("Erreur chargement de l'annuaire familial:", e);
      }

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
        const zoneText =
          window.calGlobalData?.foyer?.zone_scolaire &&
          window.calGlobalData.foyer.zone_scolaire !== "Autre"
            ? `(Zone ${window.calGlobalData.foyer.zone_scolaire})`
            : "";
        headerTitle.innerHTML = `🏖️ ${tr("fc_modal_holidays_title") || "Vacances"} ${zoneText}
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
      for (let y = currentY - 2; y <= currentY + 5; y++)
        selectYear.add(new Option(y, y));

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
          snapshotsData,
        ] = await Promise.all([
          this.fetchApi(
            `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${this.currentSchoolYearStart}`,
          ),
          this.fetchApi("/modules/family-calendar/includes/api/get-events.php"),
          this.fetchPublicHolidays(),
          this.fetchApi("/modules/family-calendar/includes/api/get-leaves.php"),
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

    renderModalHolidays() {}
    async fetchAndSaveGovHolidays(yearStart) {}

    reprocessAndRender() {
      this.reprocessEvents();
      this.calculateMonthlyBalances();
      this.renderTable();
      this.renderMonthCalendar();
    }

    reprocessEvents() {
      const upperCareModes = this.careModes.map((m) => m.toUpperCase());

      this.weeks.forEach((w) => {
        w.totals = {};
        this.careModes.forEach((m) => (w.totals["mode_" + m] = 0));
        this.kids.forEach((k) => (w.totals["sick_" + k.id] = 0));
        this.helpers.forEach((h) => {
          w.totals["off_" + h.id] = 0;
          w.totals["extra_" + h.id] = 0;
        });
        this.parents.forEach((p) => {
          const types = this.leaveMatrix[p.id] || [];
          types.forEach((t) => (w.totals[`leave_${p.id}_${t.type}`] = 0));
        });

        Object.values(w.dayFlags).forEach((f) => (f.events = []));

        this.events.forEach((e) => {
          const d = new Date(e.date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dayKey = Object.keys(w.dayDates).find(
              (k) => w.dayDates[k].getTime() === d.getTime(),
            );
            if (dayKey) w.dayFlags[dayKey].events.push(e);

            const dur = parseFloat(e.duration) || 1;

            if (e.type === "CHILD_SICK")
              w.totals["sick_" + e.person_id] =
                (w.totals["sick_" + e.person_id] || 0) + dur;
            if (e.type === "HELPER_OFF")
              w.totals["off_" + e.person_id] =
                (w.totals["off_" + e.person_id] || 0) + dur;
            if (e.type === "HELPER_EXTRA")
              w.totals["extra_" + e.person_id] =
                (w.totals["extra_" + e.person_id] || 0) + dur;

            // Modes de garde dynamiques
            if (upperCareModes.includes(e.type)) {
              const originalMode =
                this.careModes.find((m) => m.toUpperCase() === e.type) ||
                e.type;
              w.totals["mode_" + originalMode] =
                (w.totals["mode_" + originalMode] || 0) + dur;
            }
          }
        });

        this.leaves.forEach((l) => {
          const d = new Date(l.leave_date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dur = parseFloat(l.duration) || 1;
            w.totals[`leave_${l.person_id}_${l.leave_type}`] =
              (w.totals[`leave_${l.person_id}_${l.leave_type}`] || 0) + dur;
          }
        });

        let workingDays = 0;
        Object.values(w.dayDates).forEach((d) => {
          if (!this.publicHolidayDates.has(this.getLocalIsoDate(d)))
            workingDays++;
        });

        let helperAbsences = 0;
        this.helpers.forEach(
          (h) =>
            (helperAbsences +=
              (w.totals["off_" + h.id] || 0) +
              (w.totals["extra_" + h.id] || 0)),
        );

        if (this.kids.length > 0) {
          w.totals.presenceKid = Math.max(
            0,
            workingDays -
              (helperAbsences + (w.totals["sick_" + this.kids[0].id] || 0)),
          );
        }
      });
    }

    calculateMonthlyBalances() {
      const balances = {};
      this.parents.forEach((p) => (balances[p.id] = {}));

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

      this.parents.forEach((parent) => {
        const pid = parent.id;
        const matrix = this.leaveMatrix[pid] || [];

        matrix.forEach((conf) => {
          const type = conf.type;
          balances[pid][type] = {};

          ymList.forEach((ym) => {
            const [currYear, currMonth] = ym.split("-").map(Number);
            let cycleStartStr = "",
              initialBalance = parseFloat(conf.allowance || 0);

            if (conf.date) {
              const parts = conf.date.split("-");
              if (parts.length >= 2) {
                const monthRenouvellement = parseInt(parts[1]);
                const dayRenouvellement =
                  parts.length === 3 ? parseInt(parts[2]) : 1;
                const isPastAnniversary =
                  currMonth > monthRenouvellement ||
                  (currMonth === monthRenouvellement && 1 >= dayRenouvellement);
                const refYear = isPastAnniversary ? currYear : currYear - 1;
                cycleStartStr = `${refYear}-${String(monthRenouvellement).padStart(2, "0")}`;
              }
            } else {
              cycleStartStr = `${currYear}-01`;
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
      const upperCareModes = this.careModes.map((m) => m.toUpperCase());

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
          td.className = "col-day";

          w.dayFlags[d].events.forEach((evt) => {
            if (evt.type === "PUBLIC_HOLIDAY")
              td.classList.add("fc-day--public-holiday");
            if (evt.type === "VACANCES_SCOLAIRES")
              td.classList.add("fc-day--school-holiday");
            if (evt.type === "HELPER_OFF")
              td.classList.add("fc-day--off-carole");
            if (evt.type === "HELPER_EXTRA")
              td.classList.add("fc-day--extra-off-carole");
            if (upperCareModes.includes(evt.type))
              td.classList.add("fc-day--has-guard");
          });

          let content = `<div style="position:relative; height:100%; width:100%; min-height:40px;">
             <span style="display:block; padding:2px;">${String(dateObj.getDate()).padStart(2, "0")}</span>`;

          let iconsHtml = `<div style="position:absolute; top:2px; right:2px; display:flex; gap:2px;">`;
          w.dayFlags[d].events.forEach((evt) => {
            if (upperCareModes.includes(evt.type)) {
              const modeName = evt.type.toLowerCase();
              if (modeName === "avis")
                iconsHtml += `<img src="/modules/family-calendar/assets/img/avis.svg" class="fc-icon-avis" title="Avis" style="width:14px; height:14px; object-fit:contain;">`;
              else if (modeName === "centre")
                iconsHtml += `<span class="fc-icon-centre" title="Centre" style="font-size:1.1rem; line-height:1;">🏫</span>`;
              else
                iconsHtml += `<span style="background:var(--primary); color:#fff; border-radius:3px; padding:0 3px; font-size:9px;">${modeName.substring(0, 3)}</span>`;
            }
          });
          content += iconsHtml + `</div>`;

          let sickHtml = `<div style="position:absolute; bottom:2px; right:2px; display:flex; flex-direction:column; align-items:flex-end; gap:1px; font-size:11px; font-weight:bold; line-height:1;">`;
          w.dayFlags[d].events.forEach((evt) => {
            if (evt.type === "CHILD_SICK") {
              const k = this.kids.find(
                (x) => parseInt(x.id) === parseInt(evt.person_id),
              );
              if (k) {
                sickHtml += `<span style="color:${k.color || "#e11d48"};">${k.name}<span style="font-size:10px;">🤒</span></span>`;
              }
            }
          });
          content += sickHtml + `</div>`;

          const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
          if (dayLeaves.length) {
            let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
            this.parents.forEach((person) => {
              if (
                dayLeaves.some(
                  (l) => parseInt(l.person_id) === parseInt(person.id),
                )
              ) {
                html += `<span style="color:${person.color || "#000"}; font-weight:800; margin: 0 1px;">${person.name.charAt(0).toUpperCase()}</span>`;
              }
            });
            content += html + `</div>`;
          }
          td.innerHTML = content + `</div>`;
          tr.appendChild(td);
        });

        this.careModes.forEach((mode) => {
          const td = document.createElement("td");
          td.className = "col-total";
          td.textContent = fmt(w.totals["mode_" + mode] || 0);
          tr.appendChild(td);
        });
        this.kids.forEach((kid) => {
          const td = document.createElement("td");
          td.className = "col-total";
          td.textContent = fmt(w.totals["sick_" + kid.id] || 0);
          tr.appendChild(td);
        });

        if (!processedLeavesCols[w.monthKey]) {
          processedLeavesCols[w.monthKey] = true;
          this.parents.forEach((parent, index) => {
            const cssPrefix = index % 2 === 0 ? "col-alex" : "col-laia";
            (this.leaveMatrix[parent.id] || []).forEach((conf) => {
              const info =
                this.monthlyLeaveBalances[parent.id]?.[conf.type]?.[w.monthKey];
              tr.innerHTML += `<td class="${cssPrefix}-sub ${cssPrefix}-av" rowspan="${monthSpans[w.monthKey]}">${info ? fmt(info.availableAtMonthStart) : "-"}</td>`;
              tr.innerHTML += `<td class="${cssPrefix}-sub ${cssPrefix}-use" rowspan="${monthSpans[w.monthKey]}">${info ? fmt(info.usedInMonth) : ""}</td>`;
            });
          });
        }
        this.planningBody.appendChild(tr);
      });
    }

    generateMonthHTML(year, month) {
      let html = `<table class="fc-month-table"><thead><tr>`;
      ["L", "M", "M", "J", "V"].forEach((d) => (html += `<th>${d}</th>`));
      html += `</tr></thead><tbody><tr>`;

      const daysInMonth = new Date(year, month + 1, 0).getDate();
      let currentRenderedCols = 0,
        startDay = (new Date(year, month, 1).getDay() + 6) % 7;
      const upperCareModes = this.careModes.map((m) => m.toUpperCase());

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
        if (dayEvts.some((e) => e.type === "HELPER_OFF"))
          cls += " fc-day--off-carole";
        if (dayEvts.some((e) => e.type === "HELPER_EXTRA"))
          cls += " fc-day--extra-off-carole";
        if (dayEvts.some((e) => upperCareModes.includes(e.type)))
          cls += " fc-day--has-guard";

        let content = `<div style="position:relative; height:100%; min-height:55px;"><span class="fc-day-number">${d}</span>`;

        let iconsHtml = `<div style="position:absolute; top:2px; right:2px; display:flex; gap:2px;">`;
        dayEvts.forEach((evt) => {
          if (upperCareModes.includes(evt.type)) {
            const modeName = evt.type.toLowerCase();
            if (modeName === "avis")
              iconsHtml += `<img src="/modules/family-calendar/assets/img/avis.svg" class="fc-icon-avis" title="Avis" style="width:14px; height:14px; object-fit:contain;">`;
            else if (modeName === "centre")
              iconsHtml += `<span class="fc-icon-centre" title="Centre" style="font-size:1.1rem; line-height:1;">🏫</span>`;
            else
              iconsHtml += `<span style="background:var(--primary); color:#fff; border-radius:3px; padding:0 3px; font-size:9px;">${modeName.substring(0, 3)}</span>`;
          }
        });
        content += iconsHtml + `</div>`;

        let sickHtml = `<div style="position:absolute; bottom:2px; right:2px; display:flex; flex-direction:column; align-items:flex-end; gap:1px; font-weight:bold; line-height:1;">`;
        dayEvts.forEach((evt) => {
          if (evt.type === "CHILD_SICK") {
            const k = this.kids.find(
              (x) => parseInt(x.id) === parseInt(evt.person_id),
            );
            if (k) {
              sickHtml += `<span style="color:${k.color || "#e11d48"}; font-size:10px;">${k.name} 🤒</span>`;
            }
          }
        });
        content += sickHtml + `</div>`;

        const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
        if (dayLeaves.length) {
          content += `<div style="position:absolute; bottom:2px; left:2px; font-size:10px; font-weight:bold;">`;
          this.parents.forEach((parent) => {
            if (
              dayLeaves.some(
                (l) => parseInt(l.person_id) === parseInt(parent.id),
              )
            ) {
              content += `<span style="color:${parent.color || "#333"}">${parent.name.charAt(0).toUpperCase()}</span> `;
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

    generateMonthSummaryHTML(year, month) {
      const stats = { off: 0, extra: 0, sick: 0, presence: 0 };
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      for (let d = 1; d <= daysInMonth; d++) {
        const dateObj = new Date(year, month, d);
        const dayOfWeek = dateObj.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) continue;

        const iso = this.getLocalIsoDate(dateObj);
        const dayEvents = this.events.filter((e) => e.date === iso);

        dayEvents.forEach((e) => {
          const dur = parseFloat(e.duration) || 1;
          if (e.type === "HELPER_OFF") stats.off += dur;
          if (e.type === "HELPER_EXTRA") stats.extra += dur;
          if (e.type === "CHILD_SICK") stats.sick += dur;
        });

        if (
          !this.publicHolidayDates.has(iso) &&
          !dayEvents.some((e) => e.type === "VACANCES_SCOLAIRES")
        ) {
          let dayAbsence = 0;
          dayEvents.forEach((e) => {
            if (["HELPER_OFF", "HELPER_EXTRA", "CHILD_SICK"].includes(e.type)) {
              dayAbsence += parseFloat(e.duration) || 1;
            }
          });
          stats.presence += Math.max(0, 1 - dayAbsence);
        }
      }

      return `
          <div class="fc-month-summary-inline" style="display:flex; justify-content:space-around; gap:10px; margin-top:8px; font-size:0.75rem; background: var(--bg-panel); padding: 8px; border-radius: 8px; border: 1px solid var(--border-light);">
              <div class="fc-summ-pill" style="display:flex; flex-direction:column; align-items:center; flex:1;"><span>Off</span> <strong style="color:var(--text-main); font-size:0.95rem;">${parseFloat(stats.off.toFixed(1))} j</strong></div>
              <div class="fc-summ-pill" style="display:flex; flex-direction:column; align-items:center; flex:1;"><span>Extra</span> <strong style="color:var(--text-main); font-size:0.95rem;">${parseFloat(stats.extra.toFixed(1))} j</strong></div>
              <div class="fc-summ-pill" style="display:flex; flex-direction:column; align-items:center; flex:1;"><span>Maladie</span> <strong style="color:var(--text-main); font-size:0.95rem;">${parseFloat(stats.sick.toFixed(1))} j</strong></div>
              <div class="fc-summ-pill" style="display:flex; flex-direction:column; align-items:center; flex:1;"><span>Présence</span> <strong style="color:var(--primary); font-size:0.95rem;">${parseFloat(stats.presence.toFixed(1))} j</strong></div>
          </div>
      `;
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
        else
          this.monthCalendar.innerHTML = `<div class="fc-month-container">${this.generateMonthHTML(y, m)}${this.generateMonthSummaryHTML(y, m)}</div>`;
      }
      this.renderMonthBalances();
    }

    renderTwoMonthsView() {
      const y = this.currentMonth.getFullYear(),
        m = this.currentMonth.getMonth();
      const nextDate = new Date(y, m + 1, 1);
      const lang = window.appLang || "fr-FR";
      this.monthCalendar.innerHTML = `<div class="fc-two-months-container">
          <div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(this.currentMonth)}</div>${this.generateMonthHTML(y, m)}${this.generateMonthSummaryHTML(y, m)}</div>
          <div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(nextDate)}</div>${this.generateMonthHTML(nextDate.getFullYear(), nextDate.getMonth())}${this.generateMonthSummaryHTML(nextDate.getFullYear(), nextDate.getMonth())}</div>
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
      const lang = window.appLang || "fr-FR";
      [d1, d2, d3].forEach(
        (d) =>
          (html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(d)}</div>${this.generateMonthHTML(d.getFullYear(), d.getMonth())}${this.generateMonthSummaryHTML(d.getFullYear(), d.getMonth())}</div>`),
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
      container.innerHTML = this.parents
        .map((person) => {
          let cards = `<div class="fc-minimal-balance-card"><strong style="color:${person.color || "#333"}">${person.name.toUpperCase()}</strong><div class="fc-minimal-chips">`;
          const types = this.leaveMatrix[person.id] || [];

          types.forEach((conf) => {
            const type = conf.type;
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
              if (type === "CP" && (cMonth === 5 || cMonth === 6))
                alertHtml = `<div class="fc-burn-alert" title="Alerte perte">🔥</div>`;
              else if (
                type === "JRA" &&
                (cMonth === 1 || cMonth === 12) &&
                endBal > 2
              )
                alertHtml = `<div class="fc-burn-alert" title="Perte imminente">🔥</div>`;
            }

            cards += `<div class="fc-min-chip" title="Solde: ${fmt(startBal)}"><span class="type">${type}</span><span class="val">${fmt(endBal)}</span>${totalUsed > 0 ? `<span class="fc-used-badge">-${fmt(totalUsed)}</span>` : ""}${alertHtml}</div>`;
          });
          return cards + `</div></div>`;
        })
        .join("");
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
        totals: {},
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

      if (this.monthCalendar) {
        this.monthCalendar.addEventListener("click", (e) => {
          const td = e.target.closest("td[data-date]");
          if (td && td.dataset.date) {
            this.monthSelectedCells = [td];
            this.showMenu(e.pageX, e.pageY, [td.dataset.date], true);
          }
        });
      }

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

      const activeEventMap = new Set();
      this.events.forEach((e) => {
        if (dates.includes(e.date)) {
          const personKey =
            e.person_id !== null && e.person_id !== undefined
              ? e.person_id.toString()
              : "0";
          activeEventMap.add(`${e.type}_${personKey}`);
        }
      });

      const activeLeaves = {};
      this.parents.forEach((p) => (activeLeaves[p.id] = new Set()));
      this.leaves.forEach((l) => {
        if (dates.includes(l.leave_date) && activeLeaves[l.person_id])
          activeLeaves[l.person_id].add(l.leave_type);
      });

      const getActiveStyleE = (type, personStr, color) =>
        activeEventMap.has(`${type}_${personStr}`)
          ? `border: 1px solid ${color} !important; background: var(--bg-soft) !important; color: ${color} !important; font-weight: 700;`
          : "";
      const getActiveStyleL = (type, pid, color) =>
        activeLeaves[pid]?.has(type)
          ? `border: 1px solid ${color} !important; background: var(--bg-soft) !important; color: ${color} !important; font-weight: 700;`
          : "";

      const trLang = window.I18N || {};
      const dateLabel =
        dates.length > 1
          ? `${dates.length} Jours sélectionnés`
          : new Date(dates[0]).toLocaleDateString(window.appLang || "fr-FR", {
              weekday: "long",
              day: "numeric",
              month: "long",
            });

      const trashSvg = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>`;
      const buildHeader = (title, action, cat) => `
        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin: 8px 0 4px 0;">
            <span>${title}</span>
            ${action ? `<button style="background:none; border:none; cursor:pointer; color:var(--danger); padding:0; display:flex; opacity:0.6;" data-action="${action}" data-cat="${cat}">${trashSvg}</button>` : ""}
        </div>`;

      let html = `<div class="fc-menu-section" style="padding-bottom: 4px;"><strong style="font-size:0.85rem; color:var(--text-main); text-transform:capitalize;">${dateLabel}</strong></div>`;

      if (this.helpers.length > 0) {
        this.helpers.forEach((h) => {
          html += `<div class="fc-menu-section">${buildHeader(h.name, "clear-type", "HELPER_" + h.id)}<div class="fc-menu-grid">
            <button class="fc-menu-btn" style="${getActiveStyleE("HELPER_OFF", h.id, "var(--warning)")}" data-action="add" data-type="HELPER_OFF" data-person="${h.id}">Off</button>
            <button class="fc-menu-btn" style="${getActiveStyleE("HELPER_EXTRA", h.id, "var(--danger)")}" data-action="add" data-type="HELPER_EXTRA" data-person="${h.id}">Extra</button>
          </div></div>`;
        });
      }

      if (this.careModes.length > 0) {
        html += `<div class="fc-menu-section">${buildHeader(trLang.fc_care_modes_title || "Modes de garde", "clear-type", "CARE_MODE")}<div class="fc-menu-grid">`;
        this.careModes.forEach((m) => {
          let iconHtml =
            m.toLowerCase() === "avis"
              ? `<img src="/modules/family-calendar/assets/img/avis.svg" class="fc-icon-avis" title="Avis" style="width:14px; height:14px; object-fit:contain; margin-right:4px;"> `
              : m.toLowerCase() === "centre"
                ? `<span class="fc-icon-centre" style="font-size:1.1rem; line-height:1; margin-right:4px;">🏫</span> `
                : "";
          const modeType = m.toUpperCase();
          html += `<button class="fc-menu-btn" style="display:flex; align-items:center; ${getActiveStyleE(modeType, "0", "var(--primary)")}" data-action="add" data-type="${modeType}" data-person="0">${iconHtml}${m}</button>`;
        });
        html += `</div></div>`;
      }

      if (this.kids.length > 0) {
        html += `<div class="fc-menu-section">${buildHeader(trLang.leg_pep_sick || "Maladie", "clear-type", "CHILD_SICK")}<div class="fc-menu-grid" style="grid-template-columns: 1fr;">`;
        this.kids.forEach((k) => {
          const color = k.color || "var(--danger)";
          html += `<button class="fc-menu-btn" style="${getActiveStyleE("CHILD_SICK", k.id, color)}" data-action="add" data-type="CHILD_SICK" data-person="${k.id}">${k.name} 🤒</button>`;
        });
        html += `</div></div>`;
      }

      html += `<div class="fc-menu-section" style="border-bottom:none;">${buildHeader(trLang.fc_menu_kids_leaves || "Congés Adultes", null, null)}<div style="display:grid; grid-template-columns: auto 1fr; gap:6px; align-items:center; font-size:0.8rem;">`;

      const allParentLeaveTypes = new Set();
      this.parents.forEach((p) => {
        (this.leaveMatrix[p.id] || []).forEach((l) =>
          allParentLeaveTypes.add(l.type),
        );
      });

      this.parents.forEach((p) => {
        const pColor = p.color || "var(--primary)";
        html += `<div style="font-weight:600; color:${pColor}; display:flex; align-items:center; justify-content:space-between; padding-right:8px; border-right:1px solid var(--border-light);">
                      ${p.name} <button style="background:none; border:none; cursor:pointer; color:var(--danger); padding:0; display:flex; opacity:0.4; margin-left:6px;" data-action="clear-leaves-person" data-pid="${p.id}">${trashSvg}</button>
                   </div>`;
        html += `<div style="display:flex; gap:4px; flex-wrap:wrap;">`;
        Array.from(allParentLeaveTypes).forEach((t) => {
          const hasThisLeave = (this.leaveMatrix[p.id] || []).some(
            (l) => l.type === t,
          );
          if (hasThisLeave) {
            html += `<button class="fc-menu-btn" style="padding: 2px 6px; font-size:0.75rem; ${getActiveStyleL(t, p.id, pColor)}" data-action="add-leave" data-pid="${p.id}" data-type="${t}">${t}</button>`;
          }
        });
        html += `</div>`;
      });
      html += `</div></div>`;

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

      const { action, type, person, pid, cat } = dataset;
      const dates = this._currentBulkInfo.dates;
      const upperCareModes = this.careModes.map((m) => m.toUpperCase());

      try {
        if (action === "add") {
          let typesToClear = [];
          if (["HELPER_OFF", "HELPER_EXTRA"].includes(type))
            typesToClear = ["HELPER_OFF", "HELPER_EXTRA"];
          if (upperCareModes.includes(type)) typesToClear = [...upperCareModes];
          if (type === "CHILD_SICK") typesToClear = ["CHILD_SICK"];

          if (typesToClear.length) {
            await this.postApi(
              "/modules/family-calendar/includes/api/manage-event.php",
              {
                action: "bulk_delete_day_types_person",
                dates,
                types: typesToClear,
                person_id: parseInt(person) || 0,
              },
            );
          }
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            dates.map((d) => ({
              date: d,
              type: type,
              duration: 1,
              person_id: parseInt(person) || 0,
            })),
          );
        } else if (action === "clear-type") {
          let typesToClear = [];
          let personToClear = null;

          if (cat.startsWith("HELPER_")) {
            typesToClear = ["HELPER_OFF", "HELPER_EXTRA"];
            personToClear = parseInt(cat.split("_")[1]) || 0;
          } else if (cat === "CARE_MODE") {
            typesToClear = [...upperCareModes];
            personToClear = 0;
          } else if (cat === "CHILD_SICK") {
            typesToClear = ["CHILD_SICK"];
          }

          await this.postApi(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              action: "bulk_delete_day_types_person",
              dates,
              types: typesToClear,
              person_id: personToClear,
            },
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
