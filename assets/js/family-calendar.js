/**
 * family-calendar.js
 * Distinction congés / modes de garde + modif/suppression locales.
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const MODIFIABLE_TYPES = [...CONGE_TYPES, ...GUARDE_TYPES];

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
        w.totals = { offCarole: 0, extraOffCarole: 0, centre: 0, avis: 0 };
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
        }
      });
    }

    updateGlobalSummary() {
      const summaryDiv = document.getElementById("globalSummary");
      if (!summaryDiv) return;

      // On filtre uniquement sur les événements "DB" pour le récap (pas les vacances/fériés)
      const allEvents = this.dbEvents || [];

      const totalOff = allEvents
        .filter((e) => e.type === "OFF_CAROLE")
        .reduce((sum, e) => sum + Number(e.duration || 1), 0);

      const totalExtraOff = allEvents
        .filter((e) => e.type === "EXTRA_OFF_CAROLE")
        .reduce((sum, e) => sum + Number(e.duration || 1), 0);

      summaryDiv.innerHTML = `
    <p><strong>Off Carole :</strong> ${totalOff} jours</p>
    <p><strong>Extra Off Carole :</strong> ${totalExtraOff} jours</p>
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
          td.dataset.date = `${dayDate.getFullYear()}-${String(
            dayDate.getMonth() + 1
          ).padStart(2, "0")}-${String(dayDate.getDate()).padStart(2, "0")}`;
          td.textContent = `${String(dayDate.getDate()).padStart(
            2,
            "0"
          )}/${String(dayDate.getMonth() + 1).padStart(2, "0")}`;

          td.className = "";
          let hasGarde = false;
          let gardeType = null;

          week.dayFlags[dayKey].eventsOnDay.forEach((evt) => {
            const classMap = {
              VACANCES_SCOLAIRES: "fc-day--school-holiday",
              PUBLIC_HOLIDAY: "fc-day--public-holiday",
              OFF_CAROLE: "fc-day--off-carole",
              EXTRA_OFF_CAROLE: "fc-day--extra-off-carole",
            };

            if (classMap[evt.type]) {
              td.classList.add(classMap[evt.type]);
            }

            if (GUARDE_TYPES.includes(evt.type)) {
              hasGarde = true;
              gardeType = evt.type;
            }
          });

          if (hasGarde) {
            td.classList.add("fc-day--has-guard");
            if (gardeType === "CENTRE") td.classList.add("fc-day--centre");
            if (gardeType === "AVIS") td.classList.add("fc-day--avis");
          }

          tr.appendChild(td);
        });

        const tdOff = document.createElement("td");
        tdOff.textContent = formatTotal(week.totals.offCarole);
        tr.appendChild(tdOff);

        const tdExtra = document.createElement("td");
        tdExtra.textContent = formatTotal(week.totals.extraOffCarole);
        tr.appendChild(tdExtra);

        const tdCentre = document.createElement("td");
        tdCentre.textContent = formatTotal(week.totals.centre);
        tr.appendChild(tdCentre);

        const tdAvis = document.createElement("td");
        tdAvis.textContent = formatTotal(week.totals.avis);
        tr.appendChild(tdAvis);

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
      const cell = e.target.closest("td[data-date]");
      if (!cell) return;
      e.preventDefault();
      this.clearSelection();

      this.isSelecting = true;
      cell.classList.add("fc-day--selected");
      this.selectedCells.push(cell);
    }

    handleMouseMove(e) {
      if (!this.isSelecting) return;
      const cell = e.target.closest("td[data-date]");
      if (cell && !this.selectedCells.includes(cell)) {
        cell.classList.add("fc-day--selected");
        this.selectedCells.push(cell);
      }
    }

    handleMouseUp(e) {
      if (!this.isSelecting) return;
      this.isSelecting = false;
      if (this.selectedCells.length === 0) return;

      if (this.selectedCells.length === 1) {
        const date = this.selectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((e) => CONGE_TYPES.includes(e.type));
        const garde = eventsOnDay.find((e) => GUARDE_TYPES.includes(e.type));

        if (!conge && !garde) {
          this.showAddMenu(e);
        } else {
          this.showEditMenuForDay(e, { conge, garde, date });
        }
      } else {
        // Multi-jours : on reste en mode ajout, avec contrôles plus tard
        this.showAddMenu(e);
      }
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
      `;
      this.positionAndShowMenu(e);
    }

    showEditMenuForDay(e, { conge, garde, date }) {
      console.log(
        "[EDIT MENU] pour date",
        date,
        "conge =",
        conge,
        "garde =",
        garde
      );
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

      this.selectionMenu.innerHTML = congeSection + gardeSection;
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

      // === AJOUT MULTI-JOURS (sélection de plusieurs cellules) ===
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
          const response = await fetch("/api/save-events.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(newEvents),
          });
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          // Resync DB events
          const resEvents = await fetch("/api/get-events.php");
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

      // === AJOUT SUR UNE SEULE DATE (menu combiné d'un jour) ===
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
          const response = await fetch("/api/save-events.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify([newEvent]),
          });
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          // Resync DB events
          const resEvents = await fetch("/api/get-events.php");
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

      // === SUPPRESSION ===
      if (action === "delete") {
        if (!eventId) {
          console.warn("Bouton delete sans eventId, action ignorée.");
          this.clearSelection();
          return;
        }

        this.clearSelection();

        try {
          const response = await fetch("/api/manage-event.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "delete", event_id: eventId }),
          });
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          // Resync DB events
          const resEvents = await fetch("/api/get-events.php");
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

      // === MODIFICATION (change OFF <-> EXTRA ou CENTRE <-> AVIS) ===
      if (action === "update") {
        console.log("[UPDATE] click sur bouton update", { eventId, newType });

        if (!eventId) {
          console.warn("Bouton update sans eventId, action ignorée.");
          this.clearSelection();
          return;
        }

        this.clearSelection();

        try {
          const response = await fetch("/api/manage-event.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "update",
              event_id: eventId,
              new_type: newType,
            }),
          });
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          // Resync DB events
          const resEvents = await fetch("/api/get-events.php");
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
        const response = await fetch("/api/manage-event.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        if (!response.ok) {
          const errData = await response.json().catch(() => ({}));
          throw new Error(errData.message || "Erreur HTTP " + response.status);
        }

        const resEvents = await fetch("/api/get-events.php");
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
        // Navigation par année scolaire (septembre à août)
        this.currentMonth.setFullYear(
          this.currentMonth.getFullYear() + direction
        );
      } else if (this.viewMode === "2months") {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + direction);
        // S'assurer qu'on reste dans une année scolaire valide
        this.adjustToSchoolYear();
      } else {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + direction);
        // S'assurer qu'on reste dans une année scolaire valide
        this.adjustToSchoolYear();
      }
      this.renderMonthCalendar();
    }

    adjustToSchoolYear() {
      const month = this.currentMonth.getMonth();
      const year = this.currentMonth.getFullYear();
      // Si on dépasse août (mois 7), on passe à septembre de l'année suivante
      if (month > 7) {
        this.currentMonth.setFullYear(year + 1);
        this.currentMonth.setMonth(8); // Septembre
      }
      // Si on est avant septembre (mois 8), on reste dans l'année scolaire
      // mais on pourrait vouloir revenir à septembre de l'année en cours
      if (month < 8) {
        // On garde l'année mais on pourrait vouloir ajuster
        // Pour l'instant, on laisse comme ça car ça peut être août de l'année scolaire
      }
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;

      // Mettre à jour le titre selon le mode
      const monthTitle = document.getElementById("fc-current-month-year");
      if (monthTitle) {
        const year = this.currentMonth.getFullYear();
        if (this.viewMode === "year") {
          // Afficher l'année scolaire (ex: 2025-2026)
          monthTitle.textContent = `${year}-${year + 1}`;
        } else if (this.viewMode === "2months") {
          const month = this.currentMonth.getMonth();
          const monthName = getMonthNameFr(month);
          const nextMonth = new Date(year, month + 1, 1);
          const nextMonthName = getMonthNameFr(nextMonth.getMonth());
          // Ajuster l'année si on passe de décembre à janvier
          const displayYear = month >= 8 ? year : year + 1;
          monthTitle.textContent = `${monthName} - ${nextMonthName} ${displayYear}`;
        } else {
          const month = this.currentMonth.getMonth();
          const monthName = getMonthNameFr(month);
          // Afficher l'année scolaire si on est après août
          if (month >= 8) {
            monthTitle.textContent = `${monthName} ${year} (${year}-${
              year + 1
            })`;
          } else {
            monthTitle.textContent = `${monthName} ${year} (${
              year - 1
            }-${year})`;
          }
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

      // Afficher le tableau récapitulatif
      this.renderMonthSummary();
    }

    renderSingleMonthView() {
      const year = this.currentMonth.getFullYear();
      const month = this.currentMonth.getMonth();
      this.monthCalendar.innerHTML = this.generateMonthHTML(year, month);
    }

    calculateMonthTotals(year, month) {
      // Calculer le premier et dernier jour du mois (uniquement jours de semaine)
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const totals = {
        offCarole: 0,
        extraOffCarole: 0,
        centre: 0,
        avis: 0,
      };

      // Parcourir tous les jours du mois
      for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const dayOfWeek = date.getDay();

        // Ignorer les samedi (6) et dimanche (0)
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          continue;
        }

        const isoDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(
          day
        ).padStart(2, "0")}`;

        // Trouver les événements pour ce jour
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
          }
        });
      }

      return totals;
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
      html += `<div class="fc-month-container">`;
      html += `<div class="fc-month-title">${getMonthNameFr(month)} ${
        month >= 8 ? year : year + 1
      }</div>`;
      html += this.generateMonthHTML(year, month);
      html += `</div>`;
      html += `<div class="fc-month-container">`;
      html += `<div class="fc-month-title">${getMonthNameFr(nextMonthIndex)} ${
        nextMonthIndex >= 8 ? nextYear : nextYear + 1
      }</div>`;
      html += this.generateMonthHTML(nextYear, nextMonthIndex);
      html += `</div>`;
      html += "</div>";
      this.monthCalendar.innerHTML = html;
    }

    renderYearView() {
      const year = this.currentMonth.getFullYear();
      let html = '<div class="fc-year-container">';
      // Année scolaire : septembre (8) à août (7) de l'année suivante
      const schoolYearMonths = [];
      // Septembre à décembre de l'année en cours
      for (let month = 8; month < 12; month++) {
        schoolYearMonths.push({ year, month });
      }
      // Janvier à août de l'année suivante
      for (let month = 0; month < 8; month++) {
        schoolYearMonths.push({ year: year + 1, month });
      }

      schoolYearMonths.forEach(({ year: monthYear, month }) => {
        html += `<div class="fc-year-month">`;
        html += `<div class="fc-year-month-title">${getMonthNameFr(
          month
        )} ${monthYear}</div>`;
        html += this.generateMonthHTML(monthYear, month);
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

        dayEvents.forEach((evt) => {
          const classMap = {
            VACANCES_SCOLAIRES: "fc-day--school-holiday",
            PUBLIC_HOLIDAY: "fc-day--public-holiday",
            OFF_CAROLE: "fc-day--off-carole",
            EXTRA_OFF_CAROLE: "fc-day--extra-off-carole",
          };

          if (classMap[evt.type]) {
            classes += " " + classMap[evt.type];
          }

          if (GUARDE_TYPES.includes(evt.type)) {
            hasGarde = true;
            gardeType = evt.type;
          }
        });

        if (hasGarde) {
          classes += " fc-day--has-guard";
          if (gardeType === "CENTRE") classes += " fc-day--centre";
          if (gardeType === "AVIS") classes += " fc-day--avis";
        }

        html += `<td class="${classes}" data-date="${isoDate}">${day}</td>`;
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
        const date = this.monthSelectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((e) => CONGE_TYPES.includes(e.type));
        const garde = eventsOnDay.find((e) => GUARDE_TYPES.includes(e.type));

        if (!conge && !garde) {
          this.showMonthAddMenu(e);
        } else {
          this.showMonthEditMenuForDay(e, { conge, garde, date });
        }
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
      `;
      this.positionAndShowMonthMenu(e);
    }

    showMonthEditMenuForDay(e, { conge, garde, date }) {
      if (!this.monthSelectionMenu) return;
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

      this.monthSelectionMenu.innerHTML = congeSection + gardeSection;
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
          const response = await fetch("/api/save-events.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(newEvents),
          });
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          const resEvents = await fetch("/api/get-events.php");
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
          const response = await fetch("/api/save-events.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify([newEvent]),
          });
          if (!response.ok) {
            throw new Error("Erreur HTTP " + response.status);
          }

          const resEvents = await fetch("/api/get-events.php");
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
          const response = await fetch("/api/manage-event.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "delete", event_id: eventId }),
          });
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch("/api/get-events.php");
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
          const response = await fetch("/api/manage-event.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "update",
              event_id: eventId,
              new_type: newType,
            }),
          });
          if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(
              errData.message || "Erreur HTTP " + response.status
            );
          }

          const resEvents = await fetch("/api/get-events.php");
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
