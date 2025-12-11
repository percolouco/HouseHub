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

      if (!this.planningBody || !this.selectionMenu) {
        console.error("planningBody ou selectionMenu manquant");
        return;
      }

      this.isSelecting = false;
      this.selectedCells = [];
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
        this.selectionMenu.style.display === "block" &&
        !this.selectionMenu.contains(e.target)
      ) {
        this.clearSelection();
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
      } catch (error) {
        console.error("Erreur manageEvent :", error);
        alert("Erreur: " + error.message);
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
