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
      "/modules/family-calendar/includes/api/calendar-settings.php?action=get_all",
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

function switchCalendarTab(tabId) {
  document
    .querySelectorAll(".bs-tab-btn")
    .forEach((btn) => btn.classList.remove("active"));
  document
    .querySelectorAll(".cal-settings-pane")
    .forEach((pane) => (pane.style.display = "none"));

  document.getElementById(`tab-btn-${tabId}`).classList.add("active");
  document.getElementById(`cal-pane-${tabId}`).style.display = "block";

  if (tabId === "foyer") {
    loadLeaveCatalog();
  } else if (tabId === "membres") {
    if (document.getElementById("selectCalMember").value) {
      loadMemberConfigView();
    }
  }
}

function renderCareModeTags() {
  const container = document.getElementById("careModesContainer");
  if (!container) return;

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
  if (!input) return;

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
      "/modules/family-calendar/includes/api/calendar-settings.php",
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
      "/modules/family-calendar/includes/api/calendar-settings.php",
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

// ==========================================
// NOUVEAU CATALOGUE GLOBAL (FOYER)
// ==========================================
let globalLeaveCatalog = [];

async function loadLeaveCatalog() {
  try {
    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/calendar-settings.php?action=get_leave_types",
    );
    globalLeaveCatalog = res.data || [];
    renderLeaveCatalog(globalLeaveCatalog);
  } catch (err) {
    console.error("Erreur chargement catalogue", err);
  }
}

function renderLeaveCatalog(types) {
  const container = document.getElementById("leaveTypesContainer");
  if (!container) return;
  container.innerHTML = "";

  if (types.length === 0) {
    container.innerHTML = `<p class="pf-muted-note">Aucun congé configuré.</p>`;
    return;
  }

  types.forEach((lt) => {
    container.innerHTML += `
            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-page); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-light); margin-bottom: 6px;">
                <div>
                    <span style="font-weight: bold; color: var(--text-main);">${lt.label}</span>
                    <span style="background: var(--bg-subtle); border: 1px solid var(--border-main); border-radius: 4px; padding: 2px 6px; font-size: 0.8rem; margin-left: 8px; color: var(--text-muted);">${lt.code}</span>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="pf-btn btn-secondary" style="padding: 4px 8px;" onclick="editLeaveType('${lt.code}', '${lt.label.replace(/'/g, "\\'")}')">✏️</button>
                    <button type="button" class="pf-btn btn-secondary" style="padding: 4px 8px; color: var(--danger);" onclick="deleteLeaveType('${lt.code}', '${lt.label.replace(/'/g, "\\'")}')">🗑️</button>
                </div>
            </div>
        `;
  });
}

function editLeaveType(code, label) {
  document.getElementById("leaveTypeFormTitle").innerText =
    "✏️ Modifier : " + label;
  document.getElementById("lt-mode").value = "edit";

  const codeInput = document.getElementById("lt-code");
  codeInput.value = code;
  codeInput.readOnly = true;
  codeInput.style.backgroundColor = "var(--bg-subtle)";
  codeInput.style.opacity = "0.6";
  codeInput.style.cursor = "not-allowed";

  document.getElementById("lt-code-note").innerText = "Non modifiable";
  document.getElementById("lt-label").value = label;
}

function resetLeaveTypeForm() {
  document.getElementById("leaveTypeFormTitle").innerText = "+ Ajouter";
  document.getElementById("lt-mode").value = "add";

  const codeInput = document.getElementById("lt-code");
  codeInput.value = "";
  codeInput.readOnly = false;
  codeInput.style.backgroundColor = "";
  codeInput.style.opacity = "1";
  codeInput.style.cursor = "text";

  document.getElementById("lt-code-note").innerText = "Irréversible";
  document.getElementById("lt-label").value = "";
}

async function deleteLeaveType(code, label) {
  if (
    !confirm(
      `Supprimer définitivement le congé "${label}" du catalogue ? Cela le retirera également des membres qui l'utilisent.`,
    )
  )
    return;

  const fd = new FormData();
  fd.append("action", "delete_leave_type");
  fd.append("code", code);

  try {
    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/calendar-settings.php",
      { method: "POST", body: fd },
    );
    if (!res.success) throw new Error(res.error);

    if (window.showToast) showToast("Congé supprimé avec succès !", "success");
    resetLeaveTypeForm();
    loadLeaveCatalog();

    if (document.getElementById("selectCalMember").value) {
      loadMemberConfigView();
    }
  } catch (err) {
    alert("Erreur de suppression : " + err.message);
  }
}

async function saveLeaveType() {
  const code = document.getElementById("lt-code").value.toUpperCase();
  const label = document.getElementById("lt-label").value;

  if (!code || !label)
    return window.showToast
      ? showToast("Code et Label requis", "error")
      : alert("Code et Label requis");

  const fd = new FormData();
  fd.append("action", "save_leave_type");
  fd.append("mode", document.getElementById("lt-mode").value);
  fd.append("code", code);
  fd.append("label", label);

  try {
    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/calendar-settings.php",
      { method: "POST", body: fd },
    );
    if (!res.success) throw new Error(res.error);

    if (window.showToast) showToast("Catalogue mis à jour !", "success");
    resetLeaveTypeForm();
    loadLeaveCatalog();
  } catch (err) {
    alert("Erreur d'enregistrement : " + err.message);
  }
}

// ==========================================
// MODALE SETTINGS : AFFECTATION (MEMBRES)
// ==========================================
async function loadMemberConfigView() {
  const select = document.getElementById("selectCalMember");
  const zone = document.getElementById("memberConfigZone");
  if (!select || !select.value || !zone) return;

  const personId = parseInt(select.value);
  window.currentSelectedMemberId = personId;
  const role = (
    select.options[select.selectedIndex].dataset.role || ""
  ).toLowerCase();

  zone.innerHTML = '<p class="pf-muted-note">Chargement...</p>';

  if (role === "child" || role === "enfant") {
    const currentPerson = calGlobalData.people.find(
      (k) => parseInt(k.id) === personId,
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
    try {
      const res = await pachaFetch(
        `/modules/family-calendar/includes/api/calendar-settings.php?action=get_person_leaves&person_id=${personId}`,
      );
      const memberLeaves = res.data || [];
      renderMemberLeavesView(personId, memberLeaves);
    } catch (err) {
      zone.innerHTML =
        '<p class="pf-muted-note" style="color:var(--danger);">Erreur de chargement.</p>';
    }
  }
}

function renderMemberLeavesView(personId, memberLeaves) {
  const zone = document.getElementById("memberConfigZone");
  let html = `<h5 style="margin: 0 0 10px 0; color: var(--text-main);">Congés attribués</h5>`;

  if (memberLeaves.length === 0) {
    html += `<p class="pf-muted-note" style="font-size: 0.85rem;">Aucun congé attribué à ce membre.</p>`;
  } else {
    html += `<div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px;">`;
    memberLeaves.forEach((ml) => {
      const moisRenouv = ml.anniversary_date
        ? parseInt(ml.anniversary_date.split("-")[1])
        : 1;
      const methodeLabel = ml.method === "ACCUMULATED" ? "Graduel" : "Fixe";

      html += `
        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-subtle); padding: 8px; border-radius: 6px; border: 1px solid var(--border-light);">
            <div>
                <strong style="color: var(--text-main);">${ml.leave_type}</strong>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 5px;">
                    (Quota: <b>${ml.allowance}j</b> - Renouv: <b>Mois ${moisRenouv}</b> - Acquis: <b>${methodeLabel}</b>)
                </span>
            </div>
            <button class="pf-btn btn-secondary" style="padding: 2px 6px; color: var(--danger);" onclick="deleteMemberLeave(${ml.id}, ${personId})">🗑️</button>
        </div>
      `;
    });
    html += `</div>`;
  }

  html += `
        <hr style="border: 0; border-top: 1px solid var(--border-light); margin: 15px 0;">
        <h5 style="margin: 0 0 10px 0;">+ Attribuer un congé</h5>
        <div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 120px;">
                <label class="pf-label" style="font-size: 0.8rem;">Type de congé</label>
                <select id="new-member-leave-type" class="pf-input" style="width: 100%;">
                    <option value="" disabled selected>-- Sélectionner --</option>
                    ${globalLeaveCatalog.map((lt) => `<option value="${lt.code}">${lt.label}</option>`).join("")}
                </select>
            </div>
            <div style="flex: 0 0 70px;">
                <label class="pf-label" style="font-size: 0.8rem;">Quota</label>
                <input type="number" step="0.5" id="new-member-leave-allowance" class="pf-input" value="0" style="width: 100%;">
            </div>
            <div style="flex: 0 0 100px;">
                <label class="pf-label" style="font-size: 0.8rem;">Renouvellement</label>
                <select id="new-member-leave-reset" class="pf-input" style="width: 100%;">
                    <option value="1">${tr("month_jan") || "Janvier"}</option>
                    <option value="2">${tr("month_feb") || "Février"}</option>
                    <option value="3">${tr("month_mar") || "Mars"}</option>
                    <option value="4">${tr("month_apr") || "Avril"}</option>
                    <option value="5">${tr("month_may") || "Mai"}</option>
                    <option value="6">${tr("month_jun") || "Juin"}</option>
                    <option value="7">${tr("month_jul") || "Juillet"}</option>
                    <option value="8">${tr("month_aug") || "Août"}</option>
                    <option value="9">${tr("month_sep") || "Septembre"}</option>
                    <option value="10">${tr("month_oct") || "Octobre"}</option>
                    <option value="11">${tr("month_nov") || "Novembre"}</option>
                    <option value="12">${tr("month_dec") || "Décembre"}</option>
                </select>
            </div>
            <div style="flex: 0 0 90px;">
                <label class="pf-label" style="font-size: 0.8rem;">Acquisition</label>
                <select id="new-member-leave-method" class="pf-input" style="width: 100%;">
                    <option value="FIXED">Fixe</option>
                    <option value="ACCUMULATED">Graduel</option>
                </select>
            </div>
            <button class="pf-btn pf-btn-primary" onclick="addMemberLeave(${personId})">Ajouter</button>
        </div>
    `;

  zone.innerHTML = html;
}

async function addMemberLeave(personId) {
  const leaveCode = document.getElementById("new-member-leave-type").value;
  const allowance = document.getElementById("new-member-leave-allowance").value;
  const resetMonth = document.getElementById("new-member-leave-reset").value;
  const method = document.getElementById("new-member-leave-method").value;

  if (!leaveCode) {
    return window.showToast
      ? showToast("Veuillez sélectionner un type de congé", "error")
      : alert("Veuillez sélectionner un type de congé");
  }

  const fd = new FormData();
  fd.append("action", "add_person_leave");
  fd.append("person_id", personId);
  fd.append("leave_type", leaveCode);
  fd.append("allowance", allowance);
  fd.append("reset_month", resetMonth);
  fd.append("method", method);

  try {
    const res = await pachaFetch(
      "/modules/family-calendar/includes/api/calendar-settings.php",
      { method: "POST", body: fd },
    );

    if (!res.success) throw new Error(res.error);
    if (window.showToast) showToast("Congé attribué avec succès !", "success");
    loadMemberConfigView();
  } catch (err) {
    if (window.showToast)
      showToast("Erreur lors de l'attribution : " + err.message, "error");
    else alert("Erreur: " + err.message);
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
          "/modules/family-calendar/includes/api/calendar-settings.php?action=get_all",
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

    // 🔥 LE FIX (refreshAllData avec schoolHols injecté et fusionné)
    async refreshAllData() {
      try {
        const zone = window.calGlobalData?.foyer?.zone_scolaire || "C";
        const [
          weeksData,
          eventsData,
          publicHols,
          schoolHols, // 🏖️ NOUVEAU : On réceptionne les vacances
          leavesData,
          snapshotsData,
        ] = await Promise.all([
          this.fetchApi(
            `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${this.currentSchoolYearStart}`,
          ),
          this.fetchApi("/modules/family-calendar/includes/api/get-events.php"),
          this.fetchPublicHolidays(),
          this.fetchSchoolHolidays(zone), // 🏖️ NOUVEAU : On appelle l'API
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

        // 🔥 LE FIX : On fusionne les jours fériés ET les vacances
        this.fixedEvents = [...publicHols, ...schoolHols];
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

    async fetchSchoolHolidays(zone) {
      if (!zone || zone === "Autre") return [];
      try {
        const yearStr = `${this.currentSchoolYearStart}-${this.currentSchoolYearStart + 1}`;
        // Ton URL qui utilise le LIKE, beaucoup plus robuste !
        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone ${zone}%'&limit=100`;

        const res = await fetch(url);
        const data = await res.json();
        const rawRecords = data.results || [];

        // Ton système de dédoublonnage
        const uniqueMap = new Map();
        rawRecords.forEach((r) => {
          const key = `${r.description}|${r.start_date}`;
          if (!uniqueMap.has(key)) uniqueMap.set(key, r);
        });

        const holidays = [];

        Array.from(uniqueMap.values()).forEach((r) => {
          const startDateStr = r.start_date.split("T")[0];
          const endDateStr = r.end_date.split("T")[0];

          let curr = new Date(startDateStr + "T00:00:00");
          const end = new Date(endDateStr + "T00:00:00");

          // TA REGLE METIER : Si vendredi, on passe au samedi
          if (curr.getDay() === 5) {
            curr.setDate(curr.getDate() + 1);
          }

          while (curr < end) {
            holidays.push({
              id: `sh-${curr.getTime()}`,
              date: this.getLocalIsoDate(curr),
              name: r.description,
              type: "VACANCES_SCOLAIRES",
              duration: 1,
            });
            curr.setDate(curr.getDate() + 1);
          }
        });

        return holidays;
      } catch (e) {
        console.error("Erreur API Vacances:", e);
        return [];
      }
    }

    async renderModalHolidays() {
      const tbody = document.querySelector("#schoolHolidaysTable tbody");
      if (!tbody) return;

      const zone = window.calGlobalData?.foyer?.zone_scolaire || "C";
      if (zone === "Autre") {
        tbody.innerHTML =
          "<tr><td colspan='3' style='text-align:center;'>Zone 'Autre' sélectionnée. Pas de données auto.</td></tr>";
        return;
      }

      tbody.innerHTML =
        "<tr><td colspan='3' style='text-align:center; padding: 20px;'>Chargement des données du Ministère... ⏳</td></tr>";

      try {
        const year = this.modalSelectedYear || this.currentSchoolYearStart;
        const yearStr = `${year}-${year + 1}`;
        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone ${zone}%'&limit=100&order_by=start_date`;

        const res = await fetch(url);
        const data = await res.json();

        if (data.results && data.results.length > 0) {
          // Ton système de dédoublonnage pour la modale
          const uniqueMap = new Map();
          data.results.forEach((r) => {
            const key = `${r.description}|${r.start_date}`;
            if (!uniqueMap.has(key)) uniqueMap.set(key, r);
          });

          let rows = "";
          Array.from(uniqueMap.values()).forEach((p) => {
            // On applique aussi le décalage du vendredi pour l'affichage propre dans la modale
            let d1Date = new Date(p.start_date.split("T")[0] + "T00:00:00");
            if (d1Date.getDay() === 5) d1Date.setDate(d1Date.getDate() + 1);

            const d1 = d1Date.toLocaleDateString(window.appLang || "fr-FR");
            const d2 = new Date(p.end_date.split("T")[0]).toLocaleDateString(
              window.appLang || "fr-FR",
            );
            rows += `<tr><td><strong>${p.description}</strong></td><td>${d1}</td><td>${d2}</td></tr>`;
          });
          tbody.innerHTML = rows;
        } else {
          tbody.innerHTML = `<tr><td colspan='3' style='text-align:center; padding: 20px;'>Aucune vacance trouvée pour ${yearStr}.</td></tr>`;
        }
      } catch (e) {
        tbody.innerHTML =
          "<tr><td colspan='3' style='color:red; text-align:center;'>Erreur de connexion à l'API du gouvernement.</td></tr>";
      }
    }

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
      this.parents.forEach((p) => (balances[String(p.id)] = {}));

      const ymList = [];
      const tempDate = new Date();
      tempDate.setFullYear(tempDate.getFullYear() - 2);
      for (let i = 0; i < 60; i++) {
        ymList.push(
          `${tempDate.getFullYear()}-${String(tempDate.getMonth() + 1).padStart(2, "0")}`,
        );
        tempDate.setMonth(tempDate.getMonth() + 1);
      }

      const usageByMonth = {};
      const allPlacedLeaves = [...(this.leaves || []), ...(this.events || [])];

      allPlacedLeaves.forEach((l) => {
        const rawType = l.leave_type || l.event_type || l.type;
        const rawDate = l.leave_date || l.event_date || l.date;
        if (!rawType || !rawDate) return;

        const pid = String(l.person_id || l.person);
        const type = String(rawType).trim().toUpperCase();
        const ym = String(rawDate).substring(0, 7);

        if (!usageByMonth[pid]) usageByMonth[pid] = {};
        if (!usageByMonth[pid][type]) usageByMonth[pid][type] = {};

        let dur = parseFloat(l.duration);
        if (isNaN(dur)) dur = 1;

        usageByMonth[pid][type][ym] = (usageByMonth[pid][type][ym] || 0) + dur;
      });

      this.parents.forEach((parent) => {
        const pid = String(parent.id);
        const matrix =
          this.leaveMatrix[pid] || this.leaveMatrix[Number(pid)] || [];

        matrix.forEach((conf) => {
          const type = String(conf.leave_type || conf.type)
            .trim()
            .toUpperCase();
          if (!balances[pid][type]) balances[pid][type] = {};

          let monthRenouvellement = 1;
          const dateVal = conf.anniversary_date || conf.date;
          if (dateVal && dateVal.includes("-")) {
            monthRenouvellement = parseInt(dateVal.split("-")[1], 10) || 1;
          }

          let initialBalance = parseFloat(conf.allowance);
          if (isNaN(initialBalance)) initialBalance = 0;

          ymList.forEach((ym) => {
            const [currYearStr, currMonthStr] = ym.split("-");
            const currYear = parseInt(currYearStr, 10);
            const currMonth = parseInt(currMonthStr, 10);

            const isPastAnniversary = currMonth >= monthRenouvellement;
            const refYear = isPastAnniversary ? currYear : currYear - 1;
            const cycleStartStr = `${refYear}-${String(monthRenouvellement).padStart(2, "0")}`;

            let acquiredBalance = initialBalance;
            if (conf.method === "ACCUMULATED") {
              let monthsPassed =
                (currYear - refYear) * 12 +
                (currMonth - monthRenouvellement) +
                1;
              acquiredBalance = Math.min(
                initialBalance,
                (initialBalance / 12) * monthsPassed,
              );
            }

            let usedBeforeCurrentMonth = 0;
            Object.keys(usageByMonth[pid]?.[type] || {}).forEach((usedYm) => {
              if (usedYm >= cycleStartStr && usedYm < ym) {
                usedBeforeCurrentMonth += usageByMonth[pid][type][usedYm];
              }
            });

            balances[pid][type][ym] = {
              availableAtMonthStart: Math.max(
                0,
                acquiredBalance - usedBeforeCurrentMonth,
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

      try {
        this.planningBody.innerHTML = "";

        if (!this.weeks || this.weeks.length === 0) {
          this.planningBody.innerHTML =
            "<tr><td colspan='15' style='text-align:center; padding: 20px; color: var(--text-muted);'>Aucune donnée pour cette année scolaire.</td></tr>";
          return;
        }

        const monthSpans = this.weeks.reduce((acc, w) => {
          acc[w.monthKey] = (acc[w.monthKey] || 0) + 1;
          return acc;
        }, {});

        const processedMonths = {};
        const processedLeavesCols = {};
        const fmt = (n) =>
          n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "";

        const upperCareModes = (this.careModes || []).map((m) =>
          String(m).toUpperCase(),
        );

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
            td.innerHTML = `<span class="fc-sticky-mois-label">${w.monthName || ""}</span>`;
            td.rowSpan = monthSpans[w.monthKey];
            tr.appendChild(td);
          }

          const tdW = document.createElement("td");
          tdW.className = "col-month col-sticky-sem";
          tdW.textContent = w.weekLabel || "";
          tr.appendChild(tdW);

          ["mon", "tue", "wed", "thu", "fri"].forEach((d) => {
            const td = document.createElement("td");
            const dateObj = w.dayDates[d];
            if (!dateObj) return;

            const iso = this.getLocalIsoDate(dateObj);
            td.dataset.date = iso;
            td.className = "col-day";

            const events = w.dayFlags?.[d]?.events || [];

            events.forEach((evt) => {
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
            events.forEach((evt) => {
              if (upperCareModes.includes(evt.type)) {
                const modeName = String(evt.type).toLowerCase();
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
            events.forEach((evt) => {
              if (evt.type === "CHILD_SICK") {
                const k = (this.kids || []).find(
                  (x) => parseInt(x.id) === parseInt(evt.person_id),
                );
                if (k) {
                  sickHtml += `<span style="color:${k.color || "#e11d48"};">${k.name}<span style="font-size:10px;">🤒</span></span>`;
                }
              }
            });
            content += sickHtml + `</div>`;

            const dayLeaves = (this.leaves || []).filter(
              (l) => l.leave_date === iso || l.date === iso,
            );
            if (dayLeaves.length) {
              let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
              (this.parents || []).forEach((person) => {
                if (
                  dayLeaves.some(
                    (l) =>
                      parseInt(l.person_id || l.person) === parseInt(person.id),
                  )
                ) {
                  html += `<span style="color:${person.color || "#000"}; font-weight:800; margin: 0 1px;">${String(person.name).charAt(0).toUpperCase()}</span>`;
                }
              });
              content += html + `</div>`;
            }
            td.innerHTML = content + `</div>`;
            tr.appendChild(td);
          });

          (this.careModes || []).forEach((mode) => {
            const td = document.createElement("td");
            td.className = "col-total";
            td.textContent = fmt(w.totals["mode_" + mode] || 0);
            tr.appendChild(td);
          });

          (this.kids || []).forEach((kid) => {
            const td = document.createElement("td");
            td.className = "col-total";
            td.textContent = fmt(w.totals["sick_" + kid.id] || 0);
            tr.appendChild(td);
          });

          if (!processedLeavesCols[w.monthKey]) {
            processedLeavesCols[w.monthKey] = true;
            (this.parents || []).forEach((parent, index) => {
              const cssPrefix = index % 2 === 0 ? "col-alex" : "col-laia";
              const matrix =
                this.leaveMatrix[String(parent.id)] ||
                this.leaveMatrix[Number(parent.id)] ||
                [];

              matrix.forEach((conf) => {
                const type = String(conf.leave_type || conf.type)
                  .trim()
                  .toUpperCase();
                const info =
                  this.monthlyLeaveBalances[String(parent.id)]?.[type]?.[
                    w.monthKey
                  ];

                const tdAv = document.createElement("td");
                tdAv.className = `${cssPrefix}-sub ${cssPrefix}-av`;
                tdAv.rowSpan = monthSpans[w.monthKey];
                tdAv.textContent = info ? fmt(info.availableAtMonthStart) : "-";
                tr.appendChild(tdAv);

                const tdUse = document.createElement("td");
                tdUse.className = `${cssPrefix}-sub ${cssPrefix}-use`;
                tdUse.rowSpan = monthSpans[w.monthKey];
                tdUse.textContent = info ? fmt(info.usedInMonth) : "";
                tr.appendChild(tdUse);
              });
            });
          }

          this.planningBody.appendChild(tr);
        });
      } catch (e) {
        console.error("🔥 Erreur fatale dans renderTable :", e);
        this.planningBody.innerHTML = `<tr><td colspan="15" style="color:red; font-weight:bold; padding:20px; text-align:center;">Erreur d'affichage : ${e.message} <br> <small>Regarde la console pour plus de détails.</small></td></tr>`;
      }
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

      this.calculateMonthlyBalances();

      const monthsToDisplay = [];
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      let numMonths =
        this.viewMode === "2months" ? 2 : this.viewMode === "3months" ? 3 : 1;

      for (let i = 0; i < numMonths; i++) {
        monthsToDisplay.push(`${y}-${String(m + i + 1).padStart(2, "0")}`);
      }

      container.style.display = "flex";
      container.innerHTML = this.parents
        .map((person) => {
          let cards = `<div class="fc-minimal-balance-card"><strong style="color:${person.color || "#333"}">${person.name.toUpperCase()}</strong><div class="fc-minimal-chips">`;
          const types =
            this.leaveMatrix[String(person.id)] ||
            this.leaveMatrix[Number(person.id)] ||
            [];

          types.forEach((conf) => {
            const type = String(conf.leave_type || conf.type)
              .trim()
              .toUpperCase();
            const startBal =
              this.monthlyLeaveBalances[String(person.id)]?.[type]?.[
                monthsToDisplay[0]
              ]?.availableAtMonthStart || 0;

            let totalUsed = 0;
            monthsToDisplay.forEach((ym) => {
              totalUsed +=
                this.monthlyLeaveBalances[String(person.id)]?.[type]?.[ym]
                  ?.usedInMonth || 0;
            });

            const endBal = Math.max(0, startBal - totalUsed);
            const fmt = (n) =>
              n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "0";

            let alertHtml = "";
            const cMonth = parseInt(monthsToDisplay[0].split("-")[1], 10);
            const dateVal = conf.anniversary_date || conf.date;

            if (endBal > 0 && dateVal && dateVal.includes("-")) {
              const resetMonth = parseInt(dateVal.split("-")[1], 10) || 1;
              const alertMonth = resetMonth === 1 ? 12 : resetMonth - 1;

              if (cMonth === alertMonth || cMonth === resetMonth) {
                alertHtml = `<div class="fc-burn-alert" title="Alerte : ${fmt(endBal)} jour(s) perdu(s) à la fin du cycle !">🔥</div>`;
              }
            }

            cards += `<div class="fc-min-chip" title="Solde: ${fmt(startBal)}"><span class="type">${conf.type || type}</span><span class="val">${fmt(endBal)}</span>${totalUsed > 0 ? `<span class="fc-used-badge">-${fmt(totalUsed)}</span>` : ""}${alertHtml}</div>`;
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
        weekLabel: w.week_iso_number,
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

      const btnSnapshot = document.getElementById("btnOpenSnapshotModal");
      if (btnSnapshot) {
        btnSnapshot.addEventListener("click", () => {
          const modal =
            document.getElementById("modalSnapshot") ||
            document.getElementById("snapshotModal");
          if (modal) modal.style.display = "flex";
        });
      }

      const btnHolidays = document.getElementById("btnOpenHolidays");
      if (btnHolidays) {
        btnHolidays.addEventListener("click", () => {
          const modal =
            document.getElementById("modalHolidays") ||
            document.getElementById("schoolHolidaysModal");
          if (modal) modal.style.display = "flex";
        });
      }

      if (btnSettings)
        btnSettings.addEventListener("click", openCalendarSettings);

      window.addEventListener("click", (event) => {
        if (
          event.target.classList.contains("pf-modal") ||
          event.target.classList.contains("modal-overlay")
        ) {
          event.target.style.display = "none";
          event.target.classList.remove("open");
          document.body.classList.remove("no-scroll");
        }
      });

      const btnCloseSnap = document.getElementById("btnCloseSnapshot");
      if (btnCloseSnap) {
        btnCloseSnap.addEventListener("click", () => {
          const m =
            document.getElementById("modalSnapshot") ||
            document.getElementById("snapshotModal");
          if (m) m.style.display = "none";
          document.body.classList.remove("no-scroll");
        });
      }

      const btnCloseHol = document.getElementById("btnCloseHolidays");
      if (btnCloseHol) {
        btnCloseHol.addEventListener("click", () => {
          const m =
            document.getElementById("modalHolidays") ||
            document.getElementById("schoolHolidaysModal");
          if (m) m.style.display = "none";
          document.body.classList.remove("no-scroll");
        });
      }

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
