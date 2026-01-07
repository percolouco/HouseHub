/**
 * family-calendar.js
 * Distinction congés / modes de garde + modif/suppression locales.
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];
  const MODIFIABLE_TYPES = [...CONGE_TYPES, ...GUARDE_TYPES, ...PEP_TYPES];

  class FamilyCalendar {
    constructor() {
      this.planningBody = document.getElementById("planningBody");
      this.selectionMenu = document.getElementById("selectionMenu");
      this.schoolHolidaysTableBody = document.querySelector(
        "#schoolHolidaysTable tbody"
      );
      this.monthCalendar = document.getElementById("fc-month-calendar");
      this.monthSelectionMenu = document.getElementById(
        "fc-month-selectionMenu"
      );
      // Initialiser avec septembre (mois 8, car 0-indexé) pour l'année scolaire
      this.currentMonth = new Date();
      const currentMonthIndex = this.currentMonth.getMonth();
      // Si on est avant septembre, on affiche l'année scolaire précédente
      if (currentMonthIndex < 8) {
        this.currentMonth.setFullYear(this.currentMonth.getFullYear() - 1);
      }
      this.currentMonth.setMonth(8); // Septembre
      this.currentMonth.setDate(1); // Premier jour du mois
      this.viewMode = "1month"; // "1month", "2months", "year"

      if (!this.planningBody || !this.selectionMenu) {
        console.error("planningBody ou selectionMenu manquant");
        return;
      }

      this.isSelecting = false;
      this.selectedCells = [];
      this.monthSelectedCells = [];
      this.isMonthSelecting = false;
      this.dbEvents = [];
      this.fixedEvents = [];
      this.events = [];
      this.menuJustOpened = false;
      this.leaves = [];

      this.init();
      window.cal = this;
    }

    // ================== INIT ==================
    async init() {
      this.setupEventListeners();
      this.weeks = this.generateWeeksStructure();

      this.dbEvents = this.loadDbEvents();
      this.fixedEvents = await this.fetchPublicAndSchoolHolidays();
      this.events = [...this.dbEvents, ...this.fixedEvents];
      this.leaves = await this.fetchLeaves();

      // Nouveau : set des jours fériés (dates ISO)
      this.publicHolidayDates = new Set(
        this.fixedEvents
          .filter((e) => e.type === "PUBLIC_HOLIDAY")
          .map((e) => e.date)
      );

      this.reprocessAndRender();
      this.renderMonthCalendar();
    }

    loadDbEvents() {
      if (typeof serverData !== "undefined" && Array.isArray(serverData)) {
        return serverData.map((evt) => ({
          id: evt.id,
          date: evt.event_date,
          type: evt.event_type,
          duration: parseFloat(evt.duration),
          person_id: evt.person_id,
        }));
      }
      return [];
    }

    async fetchLeaves() {
      try {
        const res = await fetch(
          "/modules/family-calendar/includes/api/get-leaves.php"
        );
        if (!res.ok) {
          throw new Error("Erreur HTTP " + res.status);
        }
        const data = await res.json();
        return data.leaves || [];
      } catch (err) {
        console.error("Erreur lors du chargement des congés Alex/Laia :", err);
        return [];
      }
    }

    async fetchPublicAndSchoolHolidays() {
      // 1. Jours fériés (fixes)
      const publicHolidays = [
        { date: "2025-11-01", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2025-11-11", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2025-12-25", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-01-01", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-04-06", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-05-01", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-05-08", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-05-14", type: "PUBLIC_HOLIDAY", duration: 1 },
        { date: "2026-05-25", type: "PUBLIC_HOLIDAY", duration: 1 },
      ].map((e, idx) => ({
        id: `ph-${idx}`,
        ...e,
      }));

      // 2. Vacances scolaires (API)
      const schoolHolidayEvents = [];
      let holidayRecords = [];

      try {
        const response = await fetch(
          "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='2025-2026' AND zones LIKE '%Zone C%'&limit=100"
        );
        const schoolHolidaysData = await response.json();
        holidayRecords = schoolHolidaysData.results || [];

        holidayRecords.forEach((record, index) => {
          let current = new Date(record.start_date);
          let end = new Date(record.end_date);

          while (current < end) {
            const isoDate = `${current.getFullYear()}-${String(
              current.getMonth() + 1
            ).padStart(2, "0")}-${String(current.getDate()).padStart(2, "0")}`;

            schoolHolidayEvents.push({
              id: `sh-${isoDate}-${index}`,
              date: isoDate,
              type: "VACANCES_SCOLAIRES",
              duration: 1,
            });

            current.setDate(current.getDate() + 1);
          }
        });

        // Remplir le tableau HTML des vacances
        this.renderSchoolHolidaysTable(holidayRecords);
      } catch (error) {
        console.error("Impossible de charger les vacances scolaires.", error);
      }

      // 3. Retourne tous les événements "fixes"
      return [...publicHolidays, ...schoolHolidayEvents];
    }

    generateWeeksStructure() {
      const weeks = [];
      const start = new Date(2025, 8, 1); // 1er septembre 2025
      const end = new Date(2026, 7, 31); // 31 août 2026
      let current = new Date(start);

      // Remonter au lundi le plus proche <= start
      while (current.getDay() !== 1) current.setDate(current.getDate() - 1);

      while (current <= end) {
        const monday = new Date(current);

        // C’est CETTE date qui doit servir de référence pour le mois
        const weekMonthDate = monday;

        const weekData = {
          id: `${monday.getFullYear()}-W${getWeekOfYear(monday)}`,
          monthKey: `${weekMonthDate.getFullYear()}-${String(
            weekMonthDate.getMonth() + 1
          ).padStart(2, "0")}`,
          monthName: getMonthNameFr(weekMonthDate.getMonth()),
          weekLabel: `W${getWeekOfYear(monday)}`,
          dayDates: {},
          dayFlags: {},
        };

        ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey, index) => {
          const dayDate = new Date(monday);
          dayDate.setDate(monday.getDate() + index);
          weekData.dayDates[dayKey] = dayDate;
          weekData.dayFlags[dayKey] = { eventsOnDay: [] };
        });

        weeks.push(weekData);
        current.setDate(current.getDate() + 7);
      }

      // Juste pour verrouiller l’ordre si un jour tu modifies current ailleurs
      weeks.sort((a, b) => a.dayDates.mon - b.dayDates.mon);

      return weeks;
    }

    reprocessAndRender() {
      this.reprocessEvents();
      this.renderTable();
      this.updateGlobalSummary();
      this.renderMonthCalendar();
    }

    reprocessEvents() {
      this.weeks.forEach((w) => {
        w.totals = {
          offCarole: 0,
          extraOffCarole: 0,
          centre: 0,
          avis: 0,
          pepSick: 0,
          presencePep: 0,
        };
        Object.values(w.dayFlags).forEach((df) => (df.eventsOnDay = []));
      });

      this.events.forEach((evt) => {
        const evtDate = new Date(evt.date + "T00:00:00");
        const week = this.weeks.find(
          (w) => evtDate >= w.dayDates.mon && evtDate <= w.dayDates.fri
        );
        if (!week) return;

        const dayKey = Object.keys(week.dayDates).find(
          (k) => week.dayDates[k].toDateString() === evtDate.toDateString()
        );
        if (dayKey) {
          week.dayFlags[dayKey].eventsOnDay.push(evt);
        }

        const dur = parseFloat(evt.duration) || 1;
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
          case "PEP_SICK":
            week.totals.pepSick += dur;
            break;
        }
      });

      // Calcul présence Pep par semaine
      this.weeks.forEach((w) => {
        // Calcul du nombre de jours potentiels d'accueil Pep dans la semaine
        let workingDays = 0;
        ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
          const d = w.dayDates[dayKey];
          const isoDate = `${d.getFullYear()}-${String(
            d.getMonth() + 1
          ).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

          const isPublicHoliday =
            this.publicHolidayDates && this.publicHolidayDates.has(isoDate);

          if (!isPublicHoliday) {
            workingDays++;
          }
        });

        const absencesPep =
          (w.totals.offCarole || 0) +
          (w.totals.extraOffCarole || 0) +
          (w.totals.pepSick || 0);

        w.totals.presencePep = Math.max(0, workingDays - absencesPep);
        w.totals.workingDays = workingDays; // si tu veux l'afficher un jour
      });
    }

    updateGlobalSummary() {
      const summaryDiv = document.getElementById("globalSummary");
      if (!summaryDiv) return;

      const allEvents = this.dbEvents || [];

      const totalOff = allEvents
        .filter((e) => e.type === "OFF_CAROLE")
        .reduce((sum, e) => sum + Number(e.duration || 1), 0);

      const totalExtraOff = allEvents
        .filter((e) => e.type === "EXTRA_OFF_CAROLE")
        .reduce((sum, e) => sum + Number(e.duration || 1), 0);

      const totalPepSick = allEvents
        .filter((e) => e.type === "PEP_SICK")
        .reduce((sum, e) => sum + Number(e.duration || 1), 0);

      // Calculer les jours ouvrés & présence Pep sur l'année scolaire
      let totalWorkingDays = 0;
      let totalPresencePep = 0;

      // On parcourt l'année scolaire en mois, en réutilisant calculateMonthTotals
      const start = new Date(2025, 8, 1); // 1 sept 2025
      const end = new Date(2026, 7, 31); // 31 août 2026

      const current = new Date(start);
      while (current <= end) {
        const y = current.getFullYear();
        const m = current.getMonth();
        const monthTotals = this.calculateMonthTotals(y, m);

        totalWorkingDays += monthTotals.workingDays || 0;
        totalPresencePep += monthTotals.presencePep || 0;

        // Passer au 1er du mois suivant
        current.setMonth(current.getMonth() + 1);
        current.setDate(1);
      }

      summaryDiv.innerHTML = `
    <p><strong>Off Carole :</strong> ${totalOff} jours</p>
    <p><strong>Extra Off Carole :</strong> ${totalExtraOff} jours</p>
    <p><strong>Pep malade :</strong> ${totalPepSick} jours</p>
    <p><strong>Jours potentiels d'accueil Pep (année scolaire, hors fériés) :</strong> ${totalWorkingDays}</p>
    <p><strong>Présence Pep (année scolaire) :</strong> ${totalPresencePep} jours</p>
  `;
    }

    renderSchoolHolidaysTable(holidayRecords) {
      if (!this.schoolHolidaysTableBody) return;
      this.schoolHolidaysTableBody.innerHTML = "";

      const uniqueHolidays = new Map();
      holidayRecords.forEach((r) =>
        uniqueHolidays.set(`${r.start_date}|${r.end_date}`, r)
      );

      [...uniqueHolidays.values()]
        .sort((a, b) => new Date(a.start_date) - new Date(b.start_date))
        .forEach((record) => {
          const tr = document.createElement("tr");
          const startDate = new Date(record.start_date);
          const endDate = new Date(record.end_date);
          tr.innerHTML = `
            <td>${record.description}</td>
            <td>${startDate.toLocaleDateString("fr-FR")}</td>
            <td>${endDate.toLocaleDateString("fr-FR")}</td>
            <td>${record.zones}</td>
          `;
          this.schoolHolidaysTableBody.appendChild(tr);
        });
    }

    renderTable() {
      this.planningBody.innerHTML = "";
      const monthSpans = this.weeks.reduce(
        (acc, w) => ({ ...acc, [w.monthKey]: (acc[w.monthKey] || 0) + 1 }),
        {}
      );
      const monthRowRendered = {};
      const formatTotal = (total) =>
        total > 0 ? (Number.isInteger(total) ? total : total.toFixed(1)) : "";

      this.weeks.forEach((week) => {
        const tr = document.createElement("tr");

        // Mois
        if (!monthRowRendered[week.monthKey]) {
          monthRowRendered[week.monthKey] = true;
          const tdMonth = document.createElement("td");
          tdMonth.rowSpan = monthSpans[week.monthKey] || 1;
          tdMonth.textContent = week.monthName;
          tdMonth.classList.add("col-month");
          tr.appendChild(tdMonth);
        }

        // Semaine
        const tdWeek = document.createElement("td");
        tdWeek.textContent = week.weekLabel;
        tdWeek.classList.add("col-month");
        tr.appendChild(tdWeek);

        // Jours Lundi–Vendredi
        ["mon", "tue", "wed", "thu", "fri"].forEach((dayKey) => {
          const td = document.createElement("td");
          td.classList.add("col-day");

          const dayDate = week.dayDates[dayKey];
          td.dataset.date = `${dayDate.getFullYear()}-${String(
            dayDate.getMonth() + 1
          ).padStart(2, "0")}-${String(dayDate.getDate()).padStart(2, "0")}`;
          td.textContent = `${String(dayDate.getDate()).padStart(
            2,
            "0"
          )}/${String(dayDate.getMonth() + 1).padStart(2, "0")}`;

          let hasGarde = false;
          let gardeType = null;
          let hasPepSick = false;

          week.dayFlags[dayKey].eventsOnDay.forEach((evt) => {
            const classMap = {
              VACANCES_SCOLAIRES: "fc-day--school-holiday",
              PUBLIC_HOLIDAY: "fc-day--public-holiday",
              OFF_CAROLE: "fc-day--off-carole",
              EXTRA_OFF_CAROLE: "fc-day--extra-off-carole",
              // pas de couleur pour PEP_SICK dans l'hebdo
            };

            if (classMap[evt.type]) {
              td.classList.add(classMap[evt.type]);
            }

            if (GUARDE_TYPES.includes(evt.type)) {
              hasGarde = true;
              gardeType = evt.type;
            }

            if (evt.type === "PEP_SICK") {
              hasPepSick = true;
            }
          });

          if (hasGarde) {
            td.classList.add("fc-day--has-guard");
            if (gardeType === "CENTRE") td.classList.add("fc-day--centre");
            if (gardeType === "AVIS") td.classList.add("fc-day--avis");
          }

          if (hasPepSick) {
            const pepSpan = document.createElement("span");
            pepSpan.className = "fc-pep-sick-emoji";
            pepSpan.textContent = "🤒";
            td.appendChild(pepSpan);
          }

          // Congés Alex/Laia
          const isoDate = td.dataset.date;
          const leavesOnDay = this.leaves.filter(
            (lv) => lv.leave_date === isoDate
          );

          if (leavesOnDay.length > 0) {
            const container = document.createElement("div");
            container.className = "fc-leaves-label-container";

            const hasAlex = leavesOnDay.some((lv) => lv.person_id === 2);
            const hasLaia = leavesOnDay.some((lv) => lv.person_id === 3);

            if (hasAlex) {
              const alexSpan = document.createElement("span");
              alexSpan.className = "fc-leaves-label";
              alexSpan.textContent = "AF";
              container.appendChild(alexSpan);
            }

            if (hasLaia) {
              const laiaSpan = document.createElement("span");
              laiaSpan.className = "fc-leaves-label";
              laiaSpan.textContent = "LM";
              container.appendChild(laiaSpan);
            }

            td.appendChild(container);
          }

          tr.appendChild(td);
        });

        // Totaux
        const tdOff = document.createElement("td");
        tdOff.textContent = formatTotal(week.totals.offCarole);
        tdOff.classList.add("col-total");
        tr.appendChild(tdOff);

        const tdExtra = document.createElement("td");
        tdExtra.textContent = formatTotal(week.totals.extraOffCarole);
        tdExtra.classList.add("col-total");
        tr.appendChild(tdExtra);

        const tdCentre = document.createElement("td");
        tdCentre.textContent = formatTotal(week.totals.centre);
        tdCentre.classList.add("col-total");
        tr.appendChild(tdCentre);

        const tdAvis = document.createElement("td");
        tdAvis.textContent = formatTotal(week.totals.avis);
        tdAvis.classList.add("col-total");
        tr.appendChild(tdAvis);

        const tdPepSick = document.createElement("td");
        tdPepSick.textContent = formatTotal(week.totals.pepSick);
        tdPepSick.classList.add("col-total");
        tr.appendChild(tdPepSick);

        const tdPresencePep = document.createElement("td");
        tdPresencePep.textContent = formatTotal(week.totals.presencePep);
        tdPresencePep.classList.add("col-total");
        tr.appendChild(tdPresencePep);

        // 6 colonnes ALEX
        for (let i = 0; i < 6; i++) {
          const td = document.createElement("td");
          td.textContent = "";
          td.classList.add("col-alex-sub");
          tr.appendChild(td);
        }

        // 6 colonnes LAIA
        for (let i = 0; i < 6; i++) {
          const td = document.createElement("td");
          td.textContent = "";
          td.classList.add("col-laia-sub");
          tr.appendChild(td);
        }

        this.planningBody.appendChild(tr);
      });
    }

    // ================== INTERACTIONS ==================
    setupEventListeners() {
      this.planningBody.addEventListener("mousedown", (e) =>
        this.handleMouseDown(e)
      );
      document.addEventListener("mousemove", (e) => this.handleMouseMove(e));
      document.addEventListener("mouseup", (e) => this.handleMouseUp(e));
      document.addEventListener(
        "click",
        (e) => this.handleClickOutsideMenu(e),
        true
      );
      this.selectionMenu.addEventListener("click", (e) =>
        this.handleMenuClick(e)
      );

      // Événements pour le calendrier mensuel
      if (this.monthCalendar) {
        this.monthCalendar.addEventListener("mousedown", (e) =>
          this.handleMonthMouseDown(e)
        );
        document.addEventListener("mousemove", (e) =>
          this.handleMonthMouseMove(e)
        );
        document.addEventListener("mouseup", (e) => this.handleMonthMouseUp(e));
        if (this.monthSelectionMenu) {
          // Gestion add / add-single / delete / update sur le calendrier mensuel
          this.monthSelectionMenu.addEventListener("click", (e) =>
            this.handleMonthMenuClick(e)
          );
        }
      }

      // Navigation du calendrier mensuel
      const prevBtn = document.getElementById("fc-prev-month");
      const nextBtn = document.getElementById("fc-next-month");
      if (prevBtn) {
        prevBtn.addEventListener("click", () => this.navigateMonth(-1));
      }
      if (nextBtn) {
        nextBtn.addEventListener("click", () => this.navigateMonth(1));
      }

      // Boutons de changement de vue
      const viewButtons = document.querySelectorAll(".fc-view-button");
      viewButtons.forEach((btn) => {
        btn.addEventListener("click", (e) => {
          const view = e.target.dataset.view;
          this.setViewMode(view);
        });
      });
    }

    handleMouseDown(e) {
      console.log("handleMouseDown raw target:", e.target);

      const cell = e.target.closest("#planningTable td[data-date]");
      console.log("handleMouseDown cell:", cell);

      if (!cell) return;

      e.preventDefault();

      this.clearSelection();
      this.isSelecting = true;

      cell.classList.add("fc-day--selected");
      this.selectedCells = [cell];

      console.log(
        "handleMouseDown after select, selectedCells =",
        this.selectedCells
      );
    }

    handleMouseMove(e) {
      if (!this.isSelecting) return;

      const cell = e.target.closest("#planningTable td[data-date]");
      if (!cell) return;

      if (!this.selectedCells.includes(cell)) {
        cell.classList.add("fc-day--selected");
        this.selectedCells.push(cell);
      }
    }

    handleMouseUp(e) {
      console.log(
        "handleMouseUp called, selectedCells.length =",
        this.selectedCells.length
      );

      if (!this.isSelecting) {
        return;
      }

      this.isSelecting = false;

      if (this.selectedCells.length === 0) return;

      if (this.selectedCells.length === 1) {
        // Cas 1 jour : logique habituelle
        const date = this.selectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((ev) => CONGE_TYPES.includes(ev.type));
        const garde = eventsOnDay.find((ev) => GUARDE_TYPES.includes(ev.type));
        const pep = eventsOnDay.find((ev) => PEP_TYPES.includes(ev.type));

        if (!conge && !garde && !pep) {
          this.showAddMenu(e);
        } else {
          this.showEditMenuForDay(e, { conge, garde, pep, date });
        }

        return;
      }

      // ===== Multi-jours =====
      const selectedDates = this.selectedCells.map((c) => c.dataset.date);
      const eventsOnDates = this.events.filter(
        (evt) =>
          selectedDates.includes(evt.date) &&
          MODIFIABLE_TYPES.includes(evt.type)
      );

      const conges = eventsOnDates.filter((ev) =>
        CONGE_TYPES.includes(ev.type)
      );
      const gardes = eventsOnDates.filter((ev) =>
        GUARDE_TYPES.includes(ev.type)
      );

      const uniqueCongeTypes = new Set(conges.map((c) => c.type));
      const uniqueGardeTypes = new Set(gardes.map((g) => g.type));

      const allDatesHaveConge =
        conges.length === selectedDates.length && uniqueCongeTypes.size === 1;
      const allDatesHaveGarde =
        gardes.length === selectedDates.length && uniqueGardeTypes.size === 1;

      console.log("Multi-jours:");
      console.log("selectedDates:", selectedDates);
      console.log("eventsOnDates:", eventsOnDates);
      console.log("conges:", conges);
      console.log("gardes:", gardes);
      console.log("uniqueCongeTypes:", Array.from(uniqueCongeTypes));
      console.log("uniqueGardeTypes:", Array.from(uniqueGardeTypes));
      console.log("allDatesHaveConge:", allDatesHaveConge);
      console.log("allDatesHaveGarde:", allDatesHaveGarde);

      if (allDatesHaveConge || allDatesHaveGarde) {
        const bulkInfo = {
          selectedDates,
          conges,
          gardes,
          congeType: allDatesHaveConge ? conges[0].type : null,
          gardeType: allDatesHaveGarde ? gardes[0].type : null,
        };
        this.showBulkMenu(e, bulkInfo);
      } else {
        this.showAddMenu(e);
      }
    }

    showBulkMenu(e, bulkInfo) {
      const { selectedDates, conges, gardes, congeType, gardeType } = bulkInfo;
      const nbDays = selectedDates.length;

      let html =
        '<div class="fc-menu-section"><strong>Actions multi-jours</strong></div>';

      if (congeType) {
        const oppositeConge =
          congeType === "OFF_CAROLE" ? "EXTRA_OFF_CAROLE" : "OFF_CAROLE";
        html += `
      <div class="fc-menu-section"><strong>Congés Carole (${nbDays} jours)</strong></div>
      <button
        data-action="bulk-update-conge"
        data-new-type="${oppositeConge}"
        data-target="conge"
      >
        Remplacer ${congeType.replace(/_/g, " ")} par ${oppositeConge.replace(
          /_/g,
          " "
        )} sur ${nbDays} jours
      </button>
      <button
        data-action="bulk-delete-conge"
        data-target="conge"
      >
        Supprimer le congé sur ${nbDays} jours
      </button>
    `;
      }

      if (gardeType) {
        const oppositeGarde = gardeType === "CENTRE" ? "AVIS" : "CENTRE";
        html += `
      <div class="fc-menu-section"><strong>Mode de garde (${nbDays} jours)</strong></div>
      <button
        data-action="bulk-update-garde"
        data-new-type="${oppositeGarde}"
        data-target="garde"
      >
        Remplacer ${gardeType} par ${oppositeGarde} sur ${nbDays} jours
      </button>
      <button
        data-action="bulk-delete-garde"
        data-target="garde"
      >
        Supprimer le mode de garde sur ${nbDays} jours
      </button>
    `;
      }

      this.selectionMenu.innerHTML = html;
      // On stocke bulkInfo pour réutilisation dans handleMenuClick
      this._currentBulkInfo = bulkInfo;
      this.positionAndShowMenu(e);
    }

    handleClickOutsideMenu(e) {
      if (this.menuJustOpened) {
        this.menuJustOpened = false;
        return;
      }
      if (
        this.selectionMenu &&
        this.selectionMenu.style.display === "block" &&
        !this.selectionMenu.contains(e.target)
      ) {
        this.clearSelection();
      }
      if (
        this.monthSelectionMenu &&
        this.monthSelectionMenu.style.display === "block" &&
        !this.monthSelectionMenu.contains(e.target)
      ) {
        this.clearMonthSelection();
      }
    }

    clearSelection() {
      this.selectionMenu.style.display = "none";
      this.selectedCells.forEach((cell) =>
        cell.classList.remove("fc-day--selected")
      );
      this.selectedCells = [];
    }

    showAddMenu(e) {
      this.selectionMenu.innerHTML = `
        <div class="fc-menu-section"><strong>Ajouter</strong></div>
        <button data-action="add" data-type="OFF_CAROLE" data-person="Carole">Off Carole</button>
        <button data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">Extra Off Carole</button>
        <div class="fc-menu-section"><strong>Mode de Garde</strong></div>
        <button data-action="add" data-type="CENTRE">Centre</button>
        <button data-action="add" data-type="AVIS">Avis</button>
        <div class="fc-menu-section"><strong>Pep</strong></div>
        <button data-action="add" data-type="PEP_SICK">Pep malade</button>
      `;
      this.positionAndShowMenu(e);
    }

    showEditMenuForDay(e, { conge, garde, pep, date }) {
      console.log(
        "[EDIT MENU] pour date",
        date,
        "conge =",
        conge,
        "garde =",
        garde
      );

      // --- Section congé Carole ---
      let congeSection = "";
      if (conge) {
        const oppositeConge =
          conge.type === "OFF_CAROLE" ? "EXTRA_OFF_CAROLE" : "OFF_CAROLE";
        congeSection = `
      <div class="fc-menu-section"><strong>Congé Carole</strong></div>
      <button data-action="update" data-event-id="${
        conge.id
      }" data-new-type="${oppositeConge}">
        Remplacer par ${oppositeConge.replace(/_/g, " ")}
      </button>
      <button data-action="delete" data-event-id="${conge.id}">
        Supprimer le congé
      </button>
    `;
      } else {
        congeSection = `
      <div class="fc-menu-section"><strong>Ajouter un congé</strong></div>
      <button data-action="add-single" data-type="OFF_CAROLE" data-date="${date}" data-person="Carole">
        Off Carole
      </button>
      <button data-action="add-single" data-type="EXTRA_OFF_CAROLE" data-date="${date}" data-person="Carole">
        Extra Off Carole
      </button>
    `;
      }

      // --- Section mode de garde ---
      let gardeSection = "";
      if (garde) {
        const oppositeGarde = garde.type === "CENTRE" ? "AVIS" : "CENTRE";
        gardeSection = `
      <div class="fc-menu-section"><strong>Mode de garde</strong></div>
      <button data-action="update" data-event-id="${garde.id}" data-new-type="${oppositeGarde}">
        Remplacer par ${oppositeGarde}
      </button>
      <button data-action="delete" data-event-id="${garde.id}">
        Supprimer le mode de garde
      </button>
    `;
      } else {
        gardeSection = `
      <div class="fc-menu-section"><strong>Ajouter mode de garde</strong></div>
      <button data-action="add-single" data-type="CENTRE" data-date="${date}">
        Centre
      </button>
      <button data-action="add-single" data-type="AVIS" data-date="${date}">
        Avis
      </button>
    `;
      }

      // --- Section Pep malade ---
      let pepSection = "";
      if (pep) {
        pepSection = `
      <div class="fc-menu-section"><strong>Pep</strong></div>
      <button data-action="delete" data-event-id="${pep.id}">
        Supprimer "Pep malade"
      </button>
    `;
      } else {
        pepSection = `
      <div class="fc-menu-section"><strong>Pep</strong></div>
      <button data-action="add-single" data-type="PEP_SICK" data-date="${date}">
        Marquer Pep malade
      </button>
    `;
      }

      // --- Section congés Alex / Laia ---
      console.log(
        "[EDIT MENU] this.leaves length =",
        (this.leaves || []).length
      );

      const leavesOnDay = (this.leaves || []).filter(
        (lv) => lv.leave_date === date
      );
      console.log("[EDIT MENU] leavesOnDay =", leavesOnDay);

      let leavesSection = `
    <div class="fc-menu-section"><strong>Congés Alex / Laia</strong></div>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="CP">
      Alex - CP
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="JRA">
      Alex - JRA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="JA">
      Alex - JA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="CP">
      Laia - CP
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="JRA">
      Laia - JRA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="JA">
      Laia - JA
    </button>
  `;

      if (leavesOnDay.length > 0) {
        leavesSection += `
      <button data-action="delete-leaves-day" data-date="${date}">
        Supprimer les congés Alex/Laia ce jour-là
      </button>
    `;
      }

      const fullHtml = congeSection + gardeSection + pepSection + leavesSection;
      console.log("[EDIT MENU] full menu HTML length =", fullHtml.length);

      this.selectionMenu.innerHTML = fullHtml;
      this.positionAndShowMenu(e);
    }

    positionAndShowMenu(e) {
      this.selectionMenu.style.display = "block";
      this.selectionMenu.style.left = `${e.pageX + 5}px`;
      this.selectionMenu.style.top = `${e.pageY + 5}px`;
      this.menuJustOpened = true;
    }

    async handleMenuClick(e) {
      const button = e.target.closest("button[data-action]");
      if (!button) return;

      const { action, eventId, newType, type, person, date } = button.dataset;

      // === BULK ACTIONS (multi-jours) ============================================
      if (
        action === "bulk-update-conge" ||
        action === "bulk-delete-conge" ||
        action === "bulk-update-garde" ||
        action === "bulk-delete-garde"
      ) {
        if (!this._currentBulkInfo) {
          this.clearSelection();
          return;
        }

        const bulkInfo = this._currentBulkInfo;
        const target = button.dataset.target; // "conge" ou "garde"

        let events = [];
        if (target === "conge") {
          events = bulkInfo.conges;
        } else if (target === "garde") {
          events = bulkInfo.gardes;
        }

        const eventIds = events.map((ev) => ev.id);
        this.clearSelection();
        this._currentBulkInfo = null;

        try {
          let payload;
          if (
            action === "bulk-delete-conge" ||
            action === "bulk-delete-garde"
          ) {
            payload = {
              action: "bulk_delete",
              event_ids: eventIds,
            };
          } else {
            const bulkNewType = button.dataset.newType;
            payload = {
              action: "bulk_update",
              event_ids: eventIds,
              new_type: bulkNewType,
            };
          }

          const res = await fetch(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(payload),
            }
          );
          if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            throw new Error(errData.message || "Erreur HTTP " + res.status);
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk:", err);
          alert("Erreur lors de l'action multi-jours: " + err.message);
        }

        return;
      }

      // === AJOUT D'UN CONGÉ ALEX/LAIA SUR UNE SEULE DATE ==========================
      if (action === "add-leave") {
        const leaveDate = date;
        const personId = parseInt(button.dataset.personId, 10);
        const leaveType = button.dataset.leaveType;

        if (!leaveDate || !personId || !leaveType) {
          this.clearSelection();
          return;
        }

        const newLeave = {
          date: leaveDate,
          person_id: personId,
          leave_type: leaveType,
          duration: 1.0,
        };

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/save-leaves.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify([newLeave]),
            }
          );
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          // Recharger les congés
          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de l'ajout de congé Alex/Laia.");
        }

        return;
      }

      // === SUPPRIMER TOUS LES CONGÉS ALEX/LAIA D'UN JOUR ==========================
      if (action === "delete-leaves-day") {
        const leaveDate = date;
        if (!leaveDate) {
          this.clearSelection();
          return;
        }

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ action: "delete_day", date: leaveDate }),
            }
          );
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error(err);
          alert(
            "Erreur lors de la suppression des congés Alex/Laia pour ce jour: " +
              err.message
          );
        }

        return;
      }

      // === AJOUT MULTI-JOURS (sélection de plusieurs cellules) ===================
      if (action === "add") {
        const selectedDates = this.selectedCells.map((c) => c.dataset.date);
        const existingOnDates = this.dbEvents.filter((evt) =>
          selectedDates.includes(evt.date)
        );

        // Contrôles métier : pas de double congé / pas de double mode de garde
        if (CONGE_TYPES.includes(type)) {
          const hasCongeConflict = existingOnDates.some((evt) =>
            CONGE_TYPES.includes(evt.type)
          );
          if (hasCongeConflict) {
            alert(
              "Impossible d'ajouter un congé : au moins une date a déjà un congé (Off/Extra Off)."
            );
            this.clearSelection();
            return;
          }
        } else if (GUARDE_TYPES.includes(type)) {
          const hasGardeConflict = existingOnDates.some((evt) =>
            GUARDE_TYPES.includes(evt.type)
          );
          if (hasGardeConflict) {
            alert(
              "Impossible d'ajouter un mode de garde : au moins une date a déjà Centre/Avis."
            );
            this.clearSelection();
            return;
          }
        }

        const newEvents = this.selectedCells.map((cell) => ({
          date: cell.dataset.date,
          type,
          person,
          duration: 1.0,
        }));

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/save-events.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(newEvents),
            }
          );
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          // Resync DB events
          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de l'ajout.");
        }

        return;
      }

      // === AJOUT SUR UNE SEULE DATE (menu combiné d'un jour) =====================
      if (action === "add-single") {
        const targetDate = date;
        const existingOnDate = this.dbEvents.filter(
          (evt) => evt.date === targetDate
        );

        if (CONGE_TYPES.includes(type)) {
          const hasCongeConflict = existingOnDate.some((evt) =>
            CONGE_TYPES.includes(evt.type)
          );
          if (hasCongeConflict) {
            alert("Un congé existe déjà ce jour-là.");
            this.clearSelection();
            return;
          }
        } else if (GUARDE_TYPES.includes(type)) {
          const hasGardeConflict = existingOnDate.some((evt) =>
            GUARDE_TYPES.includes(evt.type)
          );
          if (hasGardeConflict) {
            alert("Un mode de garde existe déjà ce jour-là.");
            this.clearSelection();
            return;
          }
        }

        const newEvent = {
          date: targetDate,
          type,
          person,
          duration: 1.0,
        };

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/save-events.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify([newEvent]),
            }
          );
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de l'ajout.");
        }

        return;
      }

      // === SUPPRESSION SIMPLE ====================================================
      if (action === "delete") {
        if (!eventId) {
          console.warn("Bouton delete sans eventId, action ignorée.");
          this.clearSelection();
          return;
        }

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ action: "delete", event_id: eventId }),
            }
          );
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de la suppression : " + err.message);
        }

        return;
      }

      // === MODIFICATION SIMPLE ===================================================
      if (action === "update") {
        console.log("[UPDATE] click sur bouton update", { eventId, newType });

        if (!eventId) {
          console.warn("Bouton update sans eventId, action ignorée.");
          this.clearSelection();
          return;
        }

        this.clearSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                action: "update",
                event_id: eventId,
                new_type: newType,
              }),
            }
          );
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error("[UPDATE] Erreur lors de la modification :", err);
          alert("Erreur lors de la modification : " + err.message);
        }

        return;
      }
    }

    async manageEvent(payload) {
      try {
        const response = await fetch(
          "/modules/family-calendar/includes/api/manage-event.php",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          }
        );
        if (!response.ok) {
          const errData = await response.json().catch(() => ({}));
          throw new Error(errData.message || "Erreur HTTP " + response.status);
        }

        const resEvents = await fetch(
          "/modules/family-calendar/includes/api/get-events.php"
        );
        if (!resEvents.ok) {
          throw new Error("Erreur lors du rechargement des événements.");
        }
        const dataEvents = await resEvents.json();

        this.dbEvents = dataEvents.events || [];
        this.events = [...this.dbEvents, ...this.fixedEvents];

        this.reprocessAndRender();
        this.renderMonthCalendar();
      } catch (error) {
        console.error("Erreur manageEvent :", error);
        alert("Erreur: " + error.message);
      }
    }

    // ================== CALENDRIER MENSUEL ==================
    setViewMode(mode) {
      this.viewMode = mode;
      // Mettre à jour les boutons actifs
      document.querySelectorAll(".fc-view-button").forEach((btn) => {
        btn.classList.remove("fc-view-button--active");
        if (btn.dataset.view === mode) {
          btn.classList.add("fc-view-button--active");
        }
      });
      this.renderMonthCalendar();
    }

    navigateMonth(direction) {
      if (this.viewMode === "year") {
        // Navigation par année scolaire : on avance/recul d'un an,
        // en gardant currentMonth fixé sur septembre
        const year = this.currentMonth.getFullYear() + direction;
        this.currentMonth = new Date(year, 8, 1); // 8 = septembre
      } else {
        // Navigation simple par mois (1 ou 2 mois)
        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth() + direction;

        // new Date gère automatiquement le dépassement (ex: 2025, 12 => jan 2026)
        this.currentMonth = new Date(year, month, 1);
      }

      this.renderMonthCalendar();
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;

      // Mettre à jour le titre selon le mode
      const monthTitle = document.getElementById("fc-current-month-year");
      if (monthTitle) {
        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth();

        // Calcul de l'année scolaire à partir du mois courant
        const schoolYearStart = month >= 8 ? year : year - 1;
        const schoolYearEnd = schoolYearStart + 1;

        if (this.viewMode === "year") {
          // Année scolaire complète
          monthTitle.textContent = `Année scolaire ${schoolYearStart}–${schoolYearEnd}`;
        } else if (this.viewMode === "2months") {
          const monthName = getMonthNameFr(month);
          const next = new Date(year, month + 1, 1);
          const nextMonthIndex = next.getMonth();
          const nextYear = next.getFullYear();
          const nextMonthName = getMonthNameFr(nextMonthIndex);

          monthTitle.textContent = `${monthName} ${year} – ${nextMonthName} ${nextYear} (${schoolYearStart}–${schoolYearEnd})`;
        } else {
          // Vue 1 mois
          const monthName = getMonthNameFr(month);
          monthTitle.textContent = `${monthName} ${year} (${schoolYearStart}–${schoolYearEnd})`;
        }
      }

      // Appeler la fonction appropriée selon le mode
      if (this.viewMode === "year") {
        this.renderYearView();
      } else if (this.viewMode === "2months") {
        this.renderTwoMonthsView();
      } else {
        this.renderSingleMonthView();
      }
    }

    renderSingleMonthView() {
      const year = this.currentMonth.getFullYear();
      const month = this.currentMonth.getMonth();
      const calendarHTML = this.generateMonthHTML(year, month);
      const summaryHTML = this.generateMonthSummaryHTML(year, month);
      this.monthCalendar.innerHTML = calendarHTML + summaryHTML;
    }

    calculateMonthTotals(year, month) {
      const lastDay = new Date(year, month + 1, 0);
      const totals = {
        offCarole: 0,
        extraOffCarole: 0,
        centre: 0,
        avis: 0,
        pepSick: 0,
        presencePep: 0,
        workingDays: 0, // jours potentiels d'accueil Pep
      };

      for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const dayOfWeek = date.getDay(); // 0=dim, 6=sam

        if (dayOfWeek === 0 || dayOfWeek === 6) {
          continue; // on saute samedi/dimanche
        }

        const isoDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(
          day
        ).padStart(2, "0")}`;

        // Si jour férié, ce n'est PAS un jour potentiel d'accueil Pep
        const isPublicHoliday =
          this.publicHolidayDates && this.publicHolidayDates.has(isoDate);

        if (!isPublicHoliday) {
          totals.workingDays++;
        }

        // Événements de base (pf_events) pour ce jour
        const dayEvents = this.dbEvents.filter((evt) => evt.date === isoDate);

        dayEvents.forEach((evt) => {
          const dur = parseFloat(evt.duration) || 1;
          switch (evt.type) {
            case "OFF_CAROLE":
              totals.offCarole += dur;
              break;
            case "EXTRA_OFF_CAROLE":
              totals.extraOffCarole += dur;
              break;
            case "CENTRE":
              totals.centre += dur;
              break;
            case "AVIS":
              totals.avis += dur;
              break;
            case "PEP_SICK":
              totals.pepSick += dur;
              break;
          }
        });
      }

      const absencesPep =
        (totals.offCarole || 0) +
        (totals.extraOffCarole || 0) +
        (totals.pepSick || 0);

      totals.presencePep = Math.max(0, totals.workingDays - absencesPep);

      return totals;
    }

    generateMonthSummaryHTML(year, month) {
      const totals = this.calculateMonthTotals(year, month);
      const formatTotal = (total) =>
        total > 0 ? (Number.isInteger(total) ? total : total.toFixed(1)) : "0";

      const monthLabel = `${getMonthNameFr(month)} ${year}`;

      return `
    <div class="fc-month-summary-inline">
      <table>
        <thead>
          <tr>
            <th>Mois</th>
            <th>Jours ouvrés</th>
            <th># Off Carole</th>
            <th># Extra off Carole</th>
            <th># Pep malade</th>
            <th># Présence Pep</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>${monthLabel}</td>
            <td>${formatTotal(totals.workingDays)}</td>
            <td>${formatTotal(totals.offCarole)}</td>
            <td>${formatTotal(totals.extraOffCarole)}</td>
            <td>${formatTotal(totals.pepSick)}</td>
            <td>${formatTotal(totals.presencePep)}</td>
          </tr>
        </tbody>
      </table>
    </div>
  `;
    }

    renderMonthSummary() {
      const summaryDiv = document.getElementById("fc-month-summary");
      if (!summaryDiv) return;

      let html = '<div class="fc-summary-table-wrapper">';
      html += '<table class="fc-summary-table">';
      html += "<thead><tr>";
      html += "<th>Mois</th>";
      html += "<th># Off Carole</th>";
      html += "<th># Extra off Carole</th>";
      html += "<th># Centre</th>";
      html += "<th># Avis</th>";
      html += "</tr></thead><tbody>";

      const formatTotal = (total) =>
        total > 0 ? (Number.isInteger(total) ? total : total.toFixed(1)) : "";

      if (this.viewMode === "year") {
        // Afficher tous les mois de l'année scolaire
        const year = this.currentMonth.getFullYear();
        const schoolYearMonths = [];
        for (let month = 8; month < 12; month++) {
          schoolYearMonths.push({ year, month });
        }
        for (let month = 0; month < 8; month++) {
          schoolYearMonths.push({ year: year + 1, month });
        }

        schoolYearMonths.forEach(({ year: monthYear, month }) => {
          const totals = this.calculateMonthTotals(monthYear, month);
          html += "<tr>";
          html += `<td>${getMonthNameFr(month)} ${monthYear}</td>`;
          html += `<td>${formatTotal(totals.offCarole)}</td>`;
          html += `<td>${formatTotal(totals.extraOffCarole)}</td>`;
          html += `<td>${formatTotal(totals.centre)}</td>`;
          html += `<td>${formatTotal(totals.avis)}</td>`;
          html += "</tr>";
        });

        // Ligne de total
        const yearTotals = schoolYearMonths.reduce(
          (acc, { year: monthYear, month }) => {
            const totals = this.calculateMonthTotals(monthYear, month);
            acc.offCarole += totals.offCarole;
            acc.extraOffCarole += totals.extraOffCarole;
            acc.centre += totals.centre;
            acc.avis += totals.avis;
            return acc;
          },
          { offCarole: 0, extraOffCarole: 0, centre: 0, avis: 0 }
        );
        html += '<tr class="fc-summary-total-row">';
        html += `<td><strong>Total ${year}-${year + 1}</strong></td>`;
        html += `<td><strong>${formatTotal(
          yearTotals.offCarole
        )}</strong></td>`;
        html += `<td><strong>${formatTotal(
          yearTotals.extraOffCarole
        )}</strong></td>`;
        html += `<td><strong>${formatTotal(yearTotals.centre)}</strong></td>`;
        html += `<td><strong>${formatTotal(yearTotals.avis)}</strong></td>`;
        html += "</tr>";
      } else if (this.viewMode === "2months") {
        // Afficher les 2 mois
        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth();
        const nextMonth = month + 1;
        const nextYear = nextMonth > 11 ? year + 1 : year;
        const nextMonthIndex = nextMonth > 11 ? 0 : nextMonth;

        // Premier mois
        const totals1 = this.calculateMonthTotals(year, month);
        html += "<tr>";
        html += `<td>${getMonthNameFr(month)} ${
          month >= 8 ? year : year + 1
        }</td>`;
        html += `<td>${formatTotal(totals1.offCarole)}</td>`;
        html += `<td>${formatTotal(totals1.extraOffCarole)}</td>`;
        html += `<td>${formatTotal(totals1.centre)}</td>`;
        html += `<td>${formatTotal(totals1.avis)}</td>`;
        html += "</tr>";

        // Deuxième mois
        const totals2 = this.calculateMonthTotals(nextYear, nextMonthIndex);
        html += "<tr>";
        html += `<td>${getMonthNameFr(nextMonthIndex)} ${
          nextMonthIndex >= 8 ? nextYear : nextYear + 1
        }</td>`;
        html += `<td>${formatTotal(totals2.offCarole)}</td>`;
        html += `<td>${formatTotal(totals2.extraOffCarole)}</td>`;
        html += `<td>${formatTotal(totals2.centre)}</td>`;
        html += `<td>${formatTotal(totals2.avis)}</td>`;
        html += "</tr>";

        // Ligne de total
        const combinedTotals = {
          offCarole: totals1.offCarole + totals2.offCarole,
          extraOffCarole: totals1.extraOffCarole + totals2.extraOffCarole,
          centre: totals1.centre + totals2.centre,
          avis: totals1.avis + totals2.avis,
        };
        html += '<tr class="fc-summary-total-row">';
        html += "<td><strong>Total</strong></td>";
        html += `<td><strong>${formatTotal(
          combinedTotals.offCarole
        )}</strong></td>`;
        html += `<td><strong>${formatTotal(
          combinedTotals.extraOffCarole
        )}</strong></td>`;
        html += `<td><strong>${formatTotal(
          combinedTotals.centre
        )}</strong></td>`;
        html += `<td><strong>${formatTotal(combinedTotals.avis)}</strong></td>`;
        html += "</tr>";
      } else {
        // Afficher 1 mois
        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth();
        const totals = this.calculateMonthTotals(year, month);
        html += "<tr>";
        html += `<td>${getMonthNameFr(month)} ${
          month >= 8 ? year : year + 1
        }</td>`;
        html += `<td>${formatTotal(totals.offCarole)}</td>`;
        html += `<td>${formatTotal(totals.extraOffCarole)}</td>`;
        html += `<td>${formatTotal(totals.centre)}</td>`;
        html += `<td>${formatTotal(totals.avis)}</td>`;
        html += "</tr>";
      }

      html += "</tbody></table>";
      html += "</div>";
      summaryDiv.innerHTML = html;
    }

    renderTwoMonthsView() {
      const year = this.currentMonth.getFullYear();
      const month = this.currentMonth.getMonth();
      const nextMonth = month + 1;
      const nextYear = nextMonth > 11 ? year + 1 : year;
      const nextMonthIndex = nextMonth > 11 ? 0 : nextMonth;

      let html = '<div class="fc-two-months-container">';

      // Premier mois
      html += `<div class="fc-month-container">`;
      html += `<div class="fc-month-title">${getMonthNameFr(month)} ${
        month >= 8 ? year : year + 1
      }</div>`;
      html += this.generateMonthHTML(year, month);
      html += this.generateMonthSummaryHTML(year, month);
      html += `</div>`;

      // Deuxième mois
      html += `<div class="fc-month-container">`;
      html += `<div class="fc-month-title">${getMonthNameFr(nextMonthIndex)} ${
        nextMonthIndex >= 8 ? nextYear : nextYear + 1
      }</div>`;
      html += this.generateMonthHTML(nextYear, nextMonthIndex);
      html += this.generateMonthSummaryHTML(nextYear, nextMonthIndex);
      html += `</div>`;

      html += "</div>";
      this.monthCalendar.innerHTML = html;
    }

    renderYearView() {
      const year = this.currentMonth.getFullYear();
      let html = '<div class="fc-year-container">';
      const schoolYearMonths = [];
      for (let month = 8; month < 12; month++) {
        schoolYearMonths.push({ year, month });
      }
      for (let month = 0; month < 8; month++) {
        schoolYearMonths.push({ year: year + 1, month });
      }

      schoolYearMonths.forEach(({ year: monthYear, month }) => {
        html += `<div class="fc-year-month">`;
        html += `<div class="fc-year-month-title">${getMonthNameFr(
          month
        )} ${monthYear}</div>`;
        html += this.generateMonthHTML(monthYear, month);
        html += this.generateMonthSummaryHTML(monthYear, month);
        html += `</div>`;
      });
      html += "</div>";
      this.monthCalendar.innerHTML = html;
    }

    generateMonthHTML(year, month) {
      // Premier jour du mois et dernier jour
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();
      // Calculer le premier lundi du mois (ou avant si le 1er n'est pas un lundi)
      const firstDayOfWeek =
        firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // Lundi = 0
      const startDayOfWeek = firstDayOfWeek; // 0 = Lundi, 4 = Vendredi

      // Noms des jours (uniquement semaine : Lun-Ven)
      const dayNames = ["Lun", "Mar", "Mer", "Jeu", "Ven"];

      let html = '<table class="fc-month-table"><thead><tr>';
      dayNames.forEach((day) => {
        html += `<th>${day}</th>`;
      });
      html += "</tr></thead><tbody>";

      // Jours du mois précédent (si nécessaire) - seulement les jours de semaine
      let dayCount = 0;
      html += "<tr>";
      // On ne remplit que jusqu'à vendredi (5 jours max)
      const daysToFill = Math.min(startDayOfWeek, 5);
      for (let i = 0; i < daysToFill; i++) {
        html += '<td class="fc-day--other-month"></td>';
        dayCount++;
      }

      // Jours du mois actuel (uniquement lundi à vendredi)
      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const dayOfWeek = date.getDay(); // 0 = Dimanche, 1 = Lundi, ..., 6 = Samedi

        // Ignorer les samedi (6) et dimanche (0)
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          continue;
        }

        // Nouvelle ligne chaque lundi (quand dayCount est un multiple de 5)
        if (dayCount > 0 && dayCount % 5 === 0) {
          html += "</tr><tr>";
        }

        const isoDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(
          day
        ).padStart(2, "0")}`;

        // Trouver les événements pour ce jour
        const dayEvents = this.events.filter((evt) => evt.date === isoDate);

        let classes = "fc-month-day";
        let hasGarde = false;
        let gardeType = null;
        let hasPepSick = false; // <- ajouté

        dayEvents.forEach((evt) => {
          const classMap = {
            VACANCES_SCOLAIRES: "fc-day--school-holiday",
            PUBLIC_HOLIDAY: "fc-day--public-holiday",
            OFF_CAROLE: "fc-day--off-carole",
            EXTRA_OFF_CAROLE: "fc-day--extra-off-carole",
            // PEP_SICK: "fc-day--pep-sick",
          };

          if (classMap[evt.type]) {
            classes += " " + classMap[evt.type];
          }

          if (GUARDE_TYPES.includes(evt.type)) {
            hasGarde = true;
            gardeType = evt.type;
          }

          if (evt.type === "PEP_SICK") {
            hasPepSick = true;
          }
        });

        if (hasGarde) {
          classes += " fc-day--has-guard";
          if (gardeType === "CENTRE") classes += " fc-day--centre";
          if (gardeType === "AVIS") classes += " fc-day--avis";
        }

        // Construire la cellule avec indicateur congés Alex/Laia
        // (on génère un TD "manuel" pour pouvoir inclure le conteneur)
        const leavesOnDay = this.leaves.filter(
          (lv) => lv.leave_date === isoDate
        );

        let cellInnerHTML = `${day}`;

        if (hasPepSick) {
          cellInnerHTML += `<span class="fc-pep-sick-emoji">🤒</span>`;
        }

        if (leavesOnDay.length > 0) {
          const hasAlex = leavesOnDay.some((lv) => lv.person_id === 2); // Alex
          const hasLaia = leavesOnDay.some((lv) => lv.person_id === 3); // Laia

          let leavesHtml = `<div class="fc-leaves-label-container">`;
          if (hasAlex) leavesHtml += `<span class="fc-leaves-label">AF</span>`;
          if (hasLaia) leavesHtml += `<span class="fc-leaves-label">LM</span>`;
          leavesHtml += `</div>`;

          cellInnerHTML += leavesHtml;
        }

        html += `<td class="${classes}" data-date="${isoDate}">${cellInnerHTML}</td>`;
        dayCount++;
      }

      // Jours du mois suivant (pour compléter la dernière ligne) - seulement jusqu'à vendredi
      const remainingDays = 5 - (dayCount % 5);
      if (remainingDays < 5 && remainingDays > 0) {
        for (let i = 0; i < remainingDays; i++) {
          html += '<td class="fc-day--other-month"></td>';
        }
      }
      html += "</tr></tbody></table>";

      return html;
    }

    handleMonthMouseDown(e) {
      const cell = e.target.closest("td[data-date]");
      if (!cell) return;
      e.preventDefault();
      this.clearMonthSelection();

      this.isMonthSelecting = true;
      cell.classList.add("fc-day--selected");
      this.monthSelectedCells.push(cell);
    }

    handleMonthMouseMove(e) {
      if (!this.isMonthSelecting) return;
      const cell = e.target.closest("td[data-date]");
      if (cell && !this.monthSelectedCells.includes(cell)) {
        cell.classList.add("fc-day--selected");
        this.monthSelectedCells.push(cell);
      }
    }

    handleMonthMouseUp(e) {
      if (!this.isMonthSelecting) return;
      this.isMonthSelecting = false;
      if (this.monthSelectedCells.length === 0) return;

      if (this.monthSelectedCells.length === 1) {
        // Cas 1 jour : logique habituelle
        const date = this.monthSelectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((e) => CONGE_TYPES.includes(e.type));
        const garde = eventsOnDay.find((e) => GUARDE_TYPES.includes(e.type));
        const pep = eventsOnDay.find((e) => PEP_TYPES.includes(e.type));
        if (!conge && !garde && !pep) {
          this.showMonthAddMenu(e);
        } else {
          this.showMonthEditMenuForDay(e, { conge, garde, pep, date });
        }
        return;
      }

      // ===== Multi-jours : tentative de bulk edit =====
      const selectedDates = this.monthSelectedCells.map((c) => c.dataset.date);
      const eventsOnDates = this.events.filter(
        (evt) =>
          selectedDates.includes(evt.date) &&
          MODIFIABLE_TYPES.includes(evt.type)
      );

      const conges = eventsOnDates.filter((ev) =>
        CONGE_TYPES.includes(ev.type)
      );
      const gardes = eventsOnDates.filter((ev) =>
        GUARDE_TYPES.includes(ev.type)
      );

      const uniqueCongeTypes = new Set(conges.map((c) => c.type));
      const uniqueGardeTypes = new Set(gardes.map((g) => g.type));

      const allDatesHaveConge =
        conges.length === selectedDates.length && uniqueCongeTypes.size === 1;
      const allDatesHaveGarde =
        gardes.length === selectedDates.length && uniqueGardeTypes.size === 1;

      if (allDatesHaveConge || allDatesHaveGarde) {
        const bulkInfo = {
          selectedDates,
          conges,
          gardes,
          congeType: allDatesHaveConge ? conges[0].type : null,
          gardeType: allDatesHaveGarde ? gardes[0].type : null,
        };
        this.showMonthBulkMenu(e, bulkInfo);
      } else {
        this.showMonthAddMenu(e);
      }
    }

    clearMonthSelection() {
      if (this.monthSelectionMenu) {
        this.monthSelectionMenu.style.display = "none";
      }
      this.monthSelectedCells.forEach((cell) =>
        cell.classList.remove("fc-day--selected")
      );
      this.monthSelectedCells = [];
    }

    showMonthAddMenu(e) {
      if (!this.monthSelectionMenu) return;
      this.monthSelectionMenu.innerHTML = `
        <div class="fc-menu-section"><strong>Ajouter</strong></div>
        <button data-action="add" data-type="OFF_CAROLE" data-person="Carole">Off Carole</button>
        <button data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">Extra Off Carole</button>
        <div class="fc-menu-section"><strong>Mode de Garde</strong></div>
        <button data-action="add" data-type="CENTRE">Centre</button>
        <button data-action="add" data-type="AVIS">Avis</button>
        <div class="fc-menu-section"><strong>Pep</strong></div>
        <button data-action="add" data-type="PEP_SICK">Pep malade</button>
      `;
      this.positionAndShowMonthMenu(e);
    }

    showMonthEditMenuForDay(e, { conge, garde, pep, date }) {
      if (!this.monthSelectionMenu) return;

      // --- Section congé Carole ---
      let congeSection = "";
      if (conge) {
        const oppositeConge =
          conge.type === "OFF_CAROLE" ? "EXTRA_OFF_CAROLE" : "OFF_CAROLE";
        congeSection = `
      <div class="fc-menu-section"><strong>Congé Carole</strong></div>
      <button data-action="update" data-event-id="${
        conge.id
      }" data-new-type="${oppositeConge}">
        Remplacer par ${oppositeConge.replace(/_/g, " ")}
      </button>
      <button data-action="delete" data-event-id="${conge.id}">
        Supprimer le congé
      </button>
    `;
      } else {
        congeSection = `
      <div class="fc-menu-section"><strong>Ajouter un congé</strong></div>
      <button data-action="add-single" data-type="OFF_CAROLE" data-date="${date}" data-person="Carole">
        Off Carole
      </button>
      <button data-action="add-single" data-type="EXTRA_OFF_CAROLE" data-date="${date}" data-person="Carole">
        Extra Off Carole
      </button>
    `;
      }

      // --- Section mode de garde ---
      let gardeSection = "";
      if (garde) {
        const oppositeGarde = garde.type === "CENTRE" ? "AVIS" : "CENTRE";
        gardeSection = `
      <div class="fc-menu-section"><strong>Mode de garde</strong></div>
      <button data-action="update" data-event-id="${garde.id}" data-new-type="${oppositeGarde}">
        Remplacer par ${oppositeGarde}
      </button>
      <button data-action="delete" data-event-id="${garde.id}">
        Supprimer le mode de garde
      </button>
    `;
      } else {
        gardeSection = `
      <div class="fc-menu-section"><strong>Ajouter mode de garde</strong></div>
      <button data-action="add-single" data-type="CENTRE" data-date="${date}">
        Centre
      </button>
      <button data-action="add-single" data-type="AVIS" data-date="${date}">
        Avis
      </button>
    `;
      }

      // --- Section Pep malade ---
      let pepSection = "";
      if (pep) {
        pepSection = `
      <div class="fc-menu-section"><strong>Pep</strong></div>
      <button data-action="delete" data-event-id="${pep.id}">
        Supprimer "Pep malade"
      </button>
    `;
      } else {
        pepSection = `
      <div class="fc-menu-section"><strong>Pep</strong></div>
      <button data-action="add-single" data-type="PEP_SICK" data-date="${date}">
        Marquer Pep malade
      </button>
    `;
      }

      // --- Section congés Alex / Laia ---
      const leavesOnDay = (this.leaves || []).filter(
        (lv) => lv.leave_date === date
      );

      let leavesSection = `
    <div class="fc-menu-section"><strong>Congés Alex / Laia</strong></div>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="CP">
      Alex - CP
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="JRA">
      Alex - JRA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="2" data-leave-type="JA">
      Alex - JA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="CP">
      Laia - CP
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="JRA">
      Laia - JRA
    </button>
    <button data-action="add-leave" data-date="${date}" data-person-id="3" data-leave-type="JA">
      Laia - JA
    </button>
  `;

      if (leavesOnDay.length > 0) {
        leavesSection += `
      <button data-action="delete-leaves-day" data-date="${date}">
        Supprimer les congés Alex/Laia ce jour-là
      </button>
    `;
      }

      // --- Assemblage du menu ---
      this.monthSelectionMenu.innerHTML =
        congeSection + gardeSection + pepSection + leavesSection;
      this.positionAndShowMonthMenu(e);
    }

    showMonthBulkMenu(e, bulkInfo) {
      if (!this.monthSelectionMenu) return;

      const { selectedDates, conges, gardes, congeType, gardeType } = bulkInfo;
      const nbDays = selectedDates.length;

      let html =
        '<div class="fc-menu-section"><strong>Actions multi-jours</strong></div>';

      if (congeType) {
        const oppositeConge =
          congeType === "OFF_CAROLE" ? "EXTRA_OFF_CAROLE" : "OFF_CAROLE";
        html += `
      <div class="fc-menu-section"><strong>Congés Carole (${nbDays} jours)</strong></div>
      <button
        data-action="bulk-update-conge"
        data-new-type="${oppositeConge}"
        data-target="conge"
      >
        Remplacer ${congeType.replace(/_/g, " ")} par ${oppositeConge.replace(
          /_/g,
          " "
        )} sur ${nbDays} jours
      </button>
      <button
        data-action="bulk-delete-conge"
        data-target="conge"
      >
        Supprimer le congé sur ${nbDays} jours
      </button>
    `;
      }

      if (gardeType) {
        const oppositeGarde = gardeType === "CENTRE" ? "AVIS" : "CENTRE";
        html += `
      <div class="fc-menu-section"><strong>Mode de garde (${nbDays} jours)</strong></div>
      <button
        data-action="bulk-update-garde"
        data-new-type="${oppositeGarde}"
        data-target="garde"
      >
        Remplacer ${gardeType} par ${oppositeGarde} sur ${nbDays} jours
      </button>
      <button
        data-action="bulk-delete-garde"
        data-target="garde"
      >
        Supprimer le mode de garde sur ${nbDays} jours
      </button>
    `;
      }

      this.monthSelectionMenu.innerHTML = html;
      // On réutilise la même propriété que pour le planning hebdo :
      this._currentBulkInfo = bulkInfo;
      this.positionAndShowMonthMenu(e);
    }

    positionAndShowMonthMenu(e) {
      if (!this.monthSelectionMenu) return;
      this.monthSelectionMenu.style.display = "block";
      this.monthSelectionMenu.style.left = `${e.pageX + 5}px`;
      this.monthSelectionMenu.style.top = `${e.pageY + 5}px`;
      this.menuJustOpened = true;
    }

    async handleMonthMenuClick(e) {
      const button = e.target.closest("button[data-action]");
      if (!button) return;

      const { action, eventId, newType, type, person, date } = button.dataset;

      // Réutiliser la même logique que handleMenuClick mais avec monthSelectedCells
      if (action === "add") {
        const selectedDates = this.monthSelectedCells.map(
          (c) => c.dataset.date
        );
        const existingOnDates = this.dbEvents.filter((evt) =>
          selectedDates.includes(evt.date)
        );

        if (CONGE_TYPES.includes(type)) {
          const hasCongeConflict = existingOnDates.some((evt) =>
            CONGE_TYPES.includes(evt.type)
          );
          if (hasCongeConflict) {
            alert(
              "Impossible d'ajouter un congé : au moins une date a déjà un congé (Off/Extra Off)."
            );
            this.clearMonthSelection();
            return;
          }
        } else if (GUARDE_TYPES.includes(type)) {
          const hasGardeConflict = existingOnDates.some((evt) =>
            GUARDE_TYPES.includes(evt.type)
          );
          if (hasGardeConflict) {
            alert(
              "Impossible d'ajouter un mode de garde : au moins une date a déjà Centre/Avis."
            );
            this.clearMonthSelection();
            return;
          }
        }

        const newEvents = this.monthSelectedCells.map((cell) => ({
          date: cell.dataset.date,
          type,
          person,
          duration: 1.0,
        }));

        this.clearMonthSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/save-events.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(newEvents),
            }
          );
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de l'ajout.");
        }
        return;
      }

      if (action === "add-single") {
        const targetDate = date;
        const existingOnDate = this.dbEvents.filter(
          (evt) => evt.date === targetDate
        );

        if (CONGE_TYPES.includes(type)) {
          const hasCongeConflict = existingOnDate.some((evt) =>
            CONGE_TYPES.includes(evt.type)
          );
          if (hasCongeConflict) {
            alert("Un congé existe déjà ce jour-là.");
            this.clearMonthSelection();
            return;
          }
        } else if (GUARDE_TYPES.includes(type)) {
          const hasGardeConflict = existingOnDate.some((evt) =>
            GUARDE_TYPES.includes(evt.type)
          );
          if (hasGardeConflict) {
            alert("Un mode de garde existe déjà ce jour-là.");
            this.clearMonthSelection();
            return;
          }
        }

        const newEvent = {
          date: targetDate,
          type,
          person,
          duration: 1.0,
        };

        this.clearMonthSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/save-events.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify([newEvent]),
            }
          );
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de l'ajout.");
        }
        return;
      }

      if (action === "delete") {
        if (!eventId) {
          this.clearMonthSelection();
          return;
        }

        this.clearMonthSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ action: "delete", event_id: eventId }),
            }
          );
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error(err);
          alert("Erreur lors de la suppression : " + err.message);
        }
        return;
      }

      if (action === "update") {
        if (!eventId) {
          this.clearMonthSelection();
          return;
        }

        this.clearMonthSelection();

        try {
          const response = await fetch(
            "/modules/family-calendar/includes/api/manage-event.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                action: "update",
                event_id: eventId,
                new_type: newType,
              }),
            }
          );
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          if (!resEvents.ok) {
            throw new Error("Erreur lors du rechargement des événements.");
          }
          const dataEvents = await resEvents.json();

          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("[UPDATE] Erreur lors de la modification :", err);
          alert("Erreur lors de la modification : " + err.message);
        }
        return;
      }
    }
  }

  function getWeekOfYear(date) {
    const d = new Date(
      Date.UTC(date.getFullYear(), date.getMonth(), date.getDate())
    );
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
  }

  function getMonthNameFr(monthIndex) {
    return (
      [
        "Janvier",
        "Fevrier",
        "Mars",
        "Avril",
        "Mai",
        "Juin",
        "Juillet",
        "Aout",
        "Septembre",
        "Octobre",
        "Novembre",
        "Decembre",
      ][monthIndex] || ""
    );
  }

  new FamilyCalendar();
});
