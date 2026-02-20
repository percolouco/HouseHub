/**
 * family-calendar.js (Version Optimisée - API BDD & Décalage Vendredi)
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];

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
      ) {
        document.body.appendChild(this.selectionMenu);
      }
      if (
        this.monthSelectionMenu &&
        this.monthSelectionMenu.parentElement !== document.body
      ) {
        document.body.appendChild(this.monthSelectionMenu);
      }

      this.currentMonth = new Date();
      this.currentMonth.setDate(1);
      this.viewMode = "1month";
      this.currentSchoolYearStart = null;
      this.modalSelectedYear = null; // Pour la modale des vacances

      this.isSelecting = false;
      this.selectedCells = [];
      this.monthSelectedCells = [];
      this._currentBulkInfo = null;

      this.dbEvents = [];
      this.fixedEvents = []; // Fériés statiques
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

      this.setupModalUI(); // Prépare le selecteur d'année dans la modale
      await this.refreshAllData();
      this.updateSchoolYearLabel();
    }

    // Modifie dynamiquement le titre de la modale pour y insérer le selecteur d'année
    setupModalUI() {
      const headerH2 = document.querySelector(".fc-modal-header h2");
      if (headerH2 && !document.getElementById("holidayYearSelect")) {
        headerH2.innerHTML = `Vacances Scolaires (Zone C) 
          <select id="holidayYearSelect" style="margin-left:15px; font-size:1rem; padding:4px; border-radius:4px; border:1px solid #cbd5e1;">
            <option value="${this.currentSchoolYearStart - 1}">${this.currentSchoolYearStart - 1} - ${this.currentSchoolYearStart}</option>
            <option value="${this.currentSchoolYearStart}" selected>${this.currentSchoolYearStart} - ${this.currentSchoolYearStart + 1}</option>
            <option value="${this.currentSchoolYearStart + 1}">${this.currentSchoolYearStart + 1} - ${this.currentSchoolYearStart + 2}</option>
            <option value="${this.currentSchoolYearStart + 2}">${this.currentSchoolYearStart + 2} - ${this.currentSchoolYearStart + 3}</option>
          </select>`;

        document
          .getElementById("holidayYearSelect")
          .addEventListener("change", (e) => {
            this.modalSelectedYear = parseInt(e.target.value);
            this.renderModalHolidays();
          });
      }
    }

    async refreshAllData() {
      try {
        const weeksData = await this.fetchApi(
          `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${this.currentSchoolYearStart}`,
        );
        this.weeks = this.processWeeks(weeksData.weeks || []);

        const eventsData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-events.php",
        );
        this.dbEvents = (eventsData.events || []).map((e) => ({
          ...e,
          duration: parseFloat(e.duration),
        }));

        this.fixedEvents = this.getPublicHolidays(); // Uniquement les jours fériés maintenant

        const leavesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leaves.php",
        );
        this.leaves = leavesData.leaves || [];

        const balancesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-balances.php",
        );
        this.leaveBalances = balancesData.balances || [];

        // Les vacances scolaires sont maintenant dans dbEvents !
        this.events = [...this.dbEvents, ...this.fixedEvents];
        this.publicHolidayDates = new Set(
          this.fixedEvents
            .filter((e) => e.type === "PUBLIC_HOLIDAY")
            .map((e) => e.date),
        );

        this.reprocessAndRender();
        this.renderModalHolidays(); // Met à jour la modale
      } catch (e) {
        console.error("Erreur chargement données:", e);
      }
    }

    // Les jours fériés restent hardcodés car ils sont fixes.
    getPublicHolidays() {
      return [
        "2025-01-01",
        "2025-04-21",
        "2025-05-01",
        "2025-05-08",
        "2025-05-29",
        "2025-06-09",
        "2025-07-14",
        "2025-08-15",
        "2025-11-01",
        "2025-11-11",
        "2025-12-25",
        "2026-01-01",
        "2026-04-06",
        "2026-05-01",
        "2026-05-08",
        "2026-05-14",
        "2026-05-25",
        "2026-07-14",
        "2026-08-15",
        "2026-11-01",
        "2026-11-11",
        "2026-12-25",
      ].map((date, idx) => ({
        id: `ph-${idx}`,
        date,
        type: "PUBLIC_HOLIDAY",
        duration: 1,
      }));
    }

    // --- RECONSTRUCTION DE LA MODALE VIA LA BDD (Par blocs consécutifs) ---
    renderModalHolidays() {
      if (!this.schoolHolidaysTableBody) return;

      // Filtrer les événements de type VACANCES_SCOLAIRES pour l'année scolaire sélectionnée
      const startDate = `${this.modalSelectedYear}-09-01`;
      const endDate = `${this.modalSelectedYear + 1}-08-31`;

      const yearHolidays = this.events.filter(
        (e) =>
          e.type === "VACANCES_SCOLAIRES" &&
          e.date >= startDate &&
          e.date <= endDate,
      );

      // S'il n'y a pas de vacances en base pour cette année, on affiche le bouton "Générer"
      if (yearHolidays.length === 0) {
        this.schoolHolidaysTableBody.innerHTML = `
          <tr>
            <td colspan="3" style="text-align:center; padding: 30px;">
              <p style="color:#64748b; margin-bottom:15px;">Les vacances de cette année ne sont pas encore enregistrées.</p>
              <button id="btnFetchGovHolidays" class="pf-btn">Importer depuis l'API Gouvernement</button>
            </td>
          </tr>
        `;

        document
          .getElementById("btnFetchGovHolidays")
          .addEventListener("click", (e) => {
            e.target.innerText = "Téléchargement en cours...";
            e.target.disabled = true;
            this.fetchAndSaveGovHolidays(this.modalSelectedYear);
          });
        return;
      }

      // 1. Tri chronologique strict des jours
      yearHolidays.sort((a, b) => new Date(a.date) - new Date(b.date));

      // 2. Regroupement par blocs de jours consécutifs
      const blocks = [];
      let currentBlock = null;

      yearHolidays.forEach((e) => {
        const d = new Date(e.date + "T00:00:00"); // Force locale

        if (!currentBlock) {
          currentBlock = { start: d, end: d };
          blocks.push(currentBlock);
        } else {
          // Calcul de l'écart en jours entre la date actuelle et la fin du bloc en cours
          const diffTime = d.getTime() - currentBlock.end.getTime();
          const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

          // Si l'écart est minime (<= 4 jours, pour absorber un éventuel week-end non stocké)
          // on considère qu'on est toujours dans la même période de vacances.
          if (diffDays <= 4) {
            currentBlock.end = d;
          } else {
            // Sinon, l'écart est grand : c'est une NOUVELLE période de vacances
            currentBlock = { start: d, end: d };
            blocks.push(currentBlock);
          }
        }
      });

      // 3. Rendu HTML et déduction des noms
      let html = "";
      blocks.forEach((block) => {
        // Déduction intelligente du nom selon le mois de départ ET la durée
        const m = block.start.getMonth() + 1;
        const durationDays =
          Math.round(
            (block.end.getTime() - block.start.getTime()) /
              (1000 * 60 * 60 * 24),
          ) + 1;

        let name = "Vacances scolaires";

        if (m === 10 || m === 11) {
          name = "Vacances de la Toussaint";
        } else if (m === 12 || m === 1) {
          name = "Vacances de Noël";
        } else if (m === 2 || m === 3) {
          name = "Vacances d'Hiver";
        } else if (m === 4 || (m === 5 && durationDays > 6)) {
          name = "Vacances de Printemps";
        } else if (m === 5 && durationDays <= 6) {
          name = "Pont de Mai (Ascension)";
        } else if (m === 7 || m === 8) {
          name = "Vacances d'Été";
        }

        html += `
          <tr>
            <td><strong>${name}</strong></td>
            <td>${block.start.toLocaleDateString("fr-FR")}</td>
            <td>${block.end.toLocaleDateString("fr-FR")}</td>
          </tr>
        `;
      });

      this.schoolHolidaysTableBody.innerHTML = html;
    }

    // --- IMPORTATION DEPUIS L'API & SAUVEGARDE EN BDD ---
    async fetchAndSaveGovHolidays(yearStart) {
      try {
        const yearStr = `${yearStart}-${yearStart + 1}`;
        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone C%'&limit=100`;

        const res = await fetch(url);
        const data = await res.json();
        const rawRecords = data.results || [];

        // Dédoublonnage
        const uniqueMap = new Map();
        rawRecords.forEach((r) => {
          const key = `${r.description}|${r.start_date}`;
          if (!uniqueMap.has(key)) uniqueMap.set(key, r);
        });

        const payload = [];

        Array.from(uniqueMap.values()).forEach((r) => {
          const startDateStr = r.start_date.split("T")[0];
          const endDateStr = r.end_date.split("T")[0];

          let curr = new Date(startDateStr + "T00:00:00");
          const end = new Date(endDateStr + "T00:00:00");

          // REGLE METIER : Si le 1er jour est un VENDREDI (jour=5), on décale le début au SAMEDI.
          if (curr.getDay() === 5) {
            curr.setDate(curr.getDate() + 1);
          }

          while (curr < end) {
            const iso = this.getLocalIsoDate(curr);
            payload.push({
              date: iso,
              type: "VACANCES_SCOLAIRES",
              duration: 1,
              person: r.description, // ASTUCE: On stocke le nom de la vacance ici !
            });
            curr.setDate(curr.getDate() + 1);
          }
        });

        if (payload.length > 0) {
          // On envoie le gros lot à la base de données via l'API existante
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            payload,
          );
          await this.refreshAllData(); // Recharge tout, ce qui mettra à jour la modale
        } else {
          alert(
            "Aucune donnée trouvée sur l'API du gouvernement pour cette année.",
          );
          document.getElementById("btnFetchGovHolidays").innerText =
            "Réessayer";
          document.getElementById("btnFetchGovHolidays").disabled = false;
        }
      } catch (e) {
        console.error("Erreur API", e);
        alert("Erreur lors de la connexion à l'API gouvernementale.");
      }
    }

    // ================================================================
    // LE RESTE DU CODE (AFFICHAGE) RESTE INCHANGÉ MAIS OPTIMISÉ
    // ================================================================

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
            const prefix =
              l.person_id === 2 ? "alex" : l.person_id === 3 ? "laia" : null;
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
      const balances = {
        2: { CP: {}, JRA: {}, JA: {} },
        3: { CP: {}, JRA: {}, JA: {} },
      };
      const ymSet = new Set();
      this.weeks.forEach((w) => ymSet.add(w.monthKey));
      const ymList = Array.from(ymSet).sort();

      const usageByMonth = {};
      this.leaves.forEach((l) => {
        const pid = l.person_id;
        const type = l.leave_type;
        const ym = l.leave_date.substring(0, 7);
        if (!usageByMonth[pid]) usageByMonth[pid] = {};
        if (!usageByMonth[pid][type]) usageByMonth[pid][type] = {};
        usageByMonth[pid][type][ym] =
          (usageByMonth[pid][type][ym] || 0) + parseFloat(l.duration);
      });

      [2, 3].forEach((pid) => {
        ["CP", "JRA", "JA"].forEach((type) => {
          ymList.forEach((ym) => {
            const [currYear, currMonth] = ym.split("-").map(Number);
            let refYear = currYear;
            if (type === "CP")
              refYear = currMonth >= 6 ? currYear : currYear - 1;

            const initialObj = this.leaveBalances.find(
              (b) =>
                b.person_id == pid &&
                b.leave_type == type &&
                b.balance_year == refYear,
            );
            const initial = initialObj
              ? parseFloat(initialObj.initial_balance)
              : type === "CP"
                ? 25
                : type === "JRA"
                  ? 10
                  : 0;

            let usedBefore = 0;
            let periodStartMonth = type === "CP" ? "06" : "01";
            const periodStart = `${refYear}-${periodStartMonth}`;

            Object.keys(usageByMonth[pid]?.[type] || {}).forEach((usedYm) => {
              if (usedYm >= periodStart && usedYm < ym)
                usedBefore += usageByMonth[pid][type][usedYm];
            });

            const available = Math.max(0, initial - usedBefore);
            const usedInMonth = usageByMonth[pid]?.[type]?.[ym] || 0;
            balances[pid][type][ym] = {
              availableAtMonthStart: available,
              usedInMonth: usedInMonth,
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
          td.textContent = w.monthName;
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
            let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; line-height:1; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
            if (dayLeaves.some((l) => l.person_id === 2))
              html += `<span style="color:#0f766e; font-weight:800;">A</span>`;
            if (dayLeaves.some((l) => l.person_id === 3))
              html += `<span style="color:#b45309; font-weight:800;">L</span>`;
            html += `</div>`;
            td.innerHTML += html;
          }
          tr.appendChild(td);
        });

        [
          "offCarole",
          "extraOffCarole",
          "centre",
          "avis",
          "pepSick",
          "presencePep",
        ].forEach((k) => {
          const td = document.createElement("td");
          td.className = "col-total";
          td.textContent = fmt(w.totals[k]);
          tr.appendChild(td);
        });

        if (!processedLeavesCols[w.monthKey]) {
          processedLeavesCols[w.monthKey] = true;
          const span = monthSpans[w.monthKey];
          const ym = w.monthKey;
          const renderPersonCols = (pid, prefix) => {
            ["CP", "JRA", "JA"].forEach((type) => {
              const info = this.monthlyLeaveBalances[pid][type][ym];
              const tdAv = document.createElement("td");
              tdAv.className = `${prefix}-sub ${prefix}-av`;
              tdAv.rowSpan = span;
              tdAv.textContent = info ? fmt(info.availableAtMonthStart) : "-";
              tr.appendChild(tdAv);
              const tdUse = document.createElement("td");
              tdUse.className = `${prefix}-sub ${prefix}-use`;
              tdUse.rowSpan = span;
              tdUse.textContent = info ? fmt(info.usedInMonth) : "";
              tr.appendChild(tdUse);
            });
          };
          renderPersonCols(2, "col-alex");
          renderPersonCols(3, "col-laia");
        }
        this.planningBody.appendChild(tr);
      });
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;
      this.monthCalendar.innerHTML = "";

      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();

      const titleEl = document.querySelector("#fc-current-month-year");
      if (titleEl) {
        if (this.viewMode === "year") {
          titleEl.textContent = `Année scolaire ${this.currentSchoolYearStart} – ${this.currentSchoolYearStart + 1}`;
        } else if (this.viewMode === "2months") {
          const nextM = new Date(y, m + 1, 1);
          const m1 = new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(
            this.currentMonth,
          );
          const m2 = new Intl.DateTimeFormat("fr-FR", {
            month: "long",
            year: "numeric",
          }).format(nextM);
          titleEl.textContent = `${m1} - ${m2}`;
        } else {
          titleEl.textContent = new Intl.DateTimeFormat("fr-FR", {
            month: "long",
            year: "numeric",
          }).format(this.currentMonth);
        }
      }

      if (this.viewMode === "year") {
        this.renderYearView();
        return;
      }
      if (this.viewMode === "2months") {
        this.renderTwoMonthsView();
        return;
      }
      this.monthCalendar.innerHTML = this.generateMonthHTML(y, m);
    }

    renderTwoMonthsView() {
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const nextDate = new Date(y, m + 1, 1);
      let html = `<div class="fc-two-months-container">`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(this.currentMonth)}</div>${this.generateMonthHTML(y, m)}</div>`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(nextDate)}</div>${this.generateMonthHTML(nextDate.getFullYear(), nextDate.getMonth())}</div>`;
      html += `</div>`;
      this.monthCalendar.innerHTML = html;
    }

    renderYearView() {
      const startYear = this.currentSchoolYearStart;
      let html = '<div class="fc-year-container">';
      for (let i = 8; i < 12; i++)
        html += `<div class="fc-year-month"><div class="fc-year-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(new Date(startYear, i))}</div>${this.generateMonthHTML(startYear, i)}</div>`;
      for (let i = 0; i < 8; i++)
        html += `<div class="fc-year-month"><div class="fc-year-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(new Date(startYear + 1, i))}</div>${this.generateMonthHTML(startYear + 1, i)}</div>`;
      html += "</div>";
      this.monthCalendar.innerHTML = html;
    }

    generateMonthHTML(year, month) {
      let html = `<table class="fc-month-table"><thead><tr>`;
      ["L", "M", "M", "J", "V"].forEach((d) => (html += `<th>${d}</th>`));
      html += `</tr></thead><tbody><tr>`;

      const daysInMonth = new Date(year, month + 1, 0).getDate();
      let currentRenderedCols = 0;

      const firstDay = new Date(year, month, 1);
      let startDay = (firstDay.getDay() + 6) % 7;

      if (startDay < 5) {
        for (let i = 0; i < startDay; i++) {
          html += `<td class="fc-day--other-month"></td>`;
          currentRenderedCols++;
        }
      }

      for (let dayCounter = 1; dayCounter <= daysInMonth; dayCounter++) {
        const dateObj = new Date(year, month, dayCounter);
        const dayOfWeek = dateObj.getDay();

        if (dayOfWeek === 0 || dayOfWeek === 6) continue;

        if (currentRenderedCols === 5) {
          html += `</tr><tr>`;
          currentRenderedCols = 0;
        }

        const iso = this.getLocalIsoDate(dateObj);
        let cls = "fc-month-day";
        const dayEvts = this.events.filter((e) => e.date === iso);

        if (dayEvts.some((e) => e.type === "VACANCES_SCOLAIRES"))
          cls += " fc-day--school-holiday";
        if (dayEvts.some((e) => e.type === "PUBLIC_HOLIDAY"))
          cls += " fc-day--public-holiday";
        if (dayEvts.some((e) => e.type === "OFF_CAROLE"))
          cls += " fc-day--off-carole";
        if (dayEvts.some((e) => e.type === "EXTRA_OFF_CAROLE"))
          cls += " fc-day--extra-off-carole";
        if (dayEvts.some((e) => ["CENTRE", "AVIS"].includes(e.type))) {
          cls += " fc-day--has-guard";
          if (dayEvts.some((e) => e.type === "CENTRE"))
            cls += " fc-day--centre";
          if (dayEvts.some((e) => e.type === "AVIS")) cls += " fc-day--avis";
        }

        let content = `<div style="position:relative; height:100%;">${dayCounter}`;
        if (dayEvts.some((e) => e.type === "PEP_SICK"))
          content += `<span style="position:absolute; bottom:2px; right:2px;">🤒</span>`;
        const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
        if (dayLeaves.length) {
          content += `<div style="position:absolute; bottom:2px; left:2px; font-size:10px; font-weight:bold;">`;
          if (dayLeaves.some((l) => l.person_id === 2))
            content += `<span style="color:#0f766e">A</span> `;
          if (dayLeaves.some((l) => l.person_id === 3))
            content += `<span style="color:#b45309">L</span>`;
          content += `</div>`;
        }
        content += `</div>`;
        html += `<td class="${cls}" data-date="${iso}">${content}</td>`;
        currentRenderedCols++;
      }

      while (currentRenderedCols < 5 && currentRenderedCols > 0) {
        html += `<td class="fc-day--other-month"></td>`;
        currentRenderedCols++;
      }

      html += `</tr></tbody></table>`;
      return html;
    }

    initSummaryControls() {
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");
      if (!typeSelect || !valueSelect) return;

      const years = new Set();
      const months = new Set();

      this.weeks.forEach((w) => {
        Object.values(w.dayDates).forEach((d) => {
          years.add(d.getFullYear());
          const mKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
          months.add(mKey);
        });
      });

      const sortedYears = Array.from(years).sort();
      const sortedMonths = Array.from(months).sort();

      const populateValues = () => {
        const currentType = typeSelect.value;
        const previousValue = valueSelect.value;
        valueSelect.innerHTML = "";

        if (currentType === "year") {
          sortedYears.forEach((y) => {
            const opt = document.createElement("option");
            opt.value = y;
            opt.textContent = y;
            valueSelect.appendChild(opt);
          });
          if (sortedYears.includes(parseInt(previousValue))) {
            valueSelect.value = previousValue;
          } else {
            const currentYear = new Date().getFullYear();
            if (sortedYears.includes(currentYear))
              valueSelect.value = currentYear;
          }
        } else {
          sortedMonths.forEach((m) => {
            const [y, mo] = m.split("-");
            const dateObj = new Date(y, mo - 1, 1);
            const label = new Intl.DateTimeFormat("fr-FR", {
              month: "long",
              year: "numeric",
            }).format(dateObj);
            const opt = document.createElement("option");
            opt.value = m;
            opt.textContent = label;
            valueSelect.appendChild(opt);
          });

          const now = new Date();
          const nowIso = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
          if (sortedMonths.includes(nowIso)) {
            valueSelect.value = nowIso;
          }
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
      const div = document.getElementById("globalSummary");
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (!div || (typeSelect && valueSelect && !valueSelect.value)) return;

      const filterType = typeSelect ? typeSelect.value : "year";
      const filterValue = valueSelect
        ? valueSelect.value
        : new Date().getFullYear().toString();

      const stats = { off: 0, extra: 0, sick: 0, pep: 0, totalWorking: 0 };

      this.weeks.forEach((w) => {
        Object.entries(w.dayDates).forEach(([dayName, dateObj]) => {
          let match = false;
          if (filterType === "year") {
            if (dateObj.getFullYear().toString() === filterValue) match = true;
          } else {
            const isoMonth = this.getLocalIsoDate(dateObj).slice(0, 7);
            if (isoMonth === filterValue) match = true;
          }

          if (match) {
            const dayEvents = w.dayFlags[dayName].events;
            dayEvents.forEach((e) => {
              const dur = parseFloat(e.duration) || 1;
              if (e.type === "OFF_CAROLE") stats.off += dur;
              if (e.type === "EXTRA_OFF_CAROLE") stats.extra += dur;
              if (e.type === "PEP_SICK") stats.sick += dur;
            });

            const dateIso = this.getLocalIsoDate(dateObj);
            if (!this.publicHolidayDates.has(dateIso)) {
              stats.totalWorking++;
              let dayAbsence = 0;
              dayEvents.forEach((e) => {
                if (
                  ["OFF_CAROLE", "EXTRA_OFF_CAROLE", "PEP_SICK"].includes(
                    e.type,
                  )
                ) {
                  dayAbsence += parseFloat(e.duration) || 1;
                }
              });
              stats.pep += Math.max(0, 1 - dayAbsence);
            }
          }
        });
      });

      div.innerHTML = `
         <div class="fc-summary-item"><span class="fc-summary-label">Off Carole</span><span class="fc-summary-value">${parseFloat(stats.off.toFixed(1))} jours</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">Extra Off Carole</span><span class="fc-summary-value">${parseFloat(stats.extra.toFixed(1))} jours</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">Pep Malade</span><span class="fc-summary-value">${parseFloat(stats.sick.toFixed(1))} jours</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">Présence Pep</span><span class="fc-summary-value">${parseFloat(stats.pep.toFixed(1))} jours</span></div>
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
      return fetch(url).then((r) => r.json());
    }

    async postApi(url, data) {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!res.ok) throw new Error(await res.text());
      return res.json();
    }

    async changeSchoolYear(delta) {
      this.currentSchoolYearStart += delta;
      this.updateSchoolYearLabel();
      await this.refreshAllData();
    }

    setupEventListeners() {
      if (this.planningBody) {
        // Souris (PC)
        this.planningBody.addEventListener("mousedown", (e) =>
          this.handleMouseDown(e),
        );
        document.addEventListener("mousemove", (e) => this.handleMouseMove(e));
        document.addEventListener("mouseup", (e) => this.handleMouseUp(e));

        // Tactile (Mobile/Tablette) - NOUVEAU
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

      const btnOpen = document.getElementById("btnOpenHolidays");
      const btnClose = document.getElementById("btnCloseHolidays");
      const modal = document.getElementById("modalHolidays");

      if (btnOpen && modal) {
        btnOpen.addEventListener("click", () => {
          modal.style.display = "flex";
          // Remet l'année par défaut sur l'année actuellement affichée dans le calendrier
          const yearSelect = document.getElementById("holidayYearSelect");
          if (yearSelect) yearSelect.value = this.currentSchoolYearStart;
          this.modalSelectedYear = this.currentSchoolYearStart;
          this.renderModalHolidays();
        });
      }
      if (btnClose && modal)
        btnClose.addEventListener(
          "click",
          () => (modal.style.display = "none"),
        );
      if (modal)
        modal.addEventListener("click", (e) => {
          if (e.target === modal) modal.style.display = "none";
        });

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
      document
        .getElementById("fc-prev-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(-1));
      document
        .getElementById("fc-next-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(1));

      document.querySelectorAll(".fc-view-button").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          document
            .querySelectorAll(".fc-view-button")
            .forEach((b) => b.classList.remove("fc-view-button--active"));
          e.target.classList.add("fc-view-button--active");
          this.viewMode = e.target.dataset.view;
          this.renderMonthCalendar();
        });
      });
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
      if (!this.selectedCells.length) return;
      const dates = this.selectedCells.map((c) => c.dataset.date);
      this.showMenu(e.pageX, e.pageY, dates, false);
    }

    // --- GESTION DU TACTILE (Swipe pour sélectionner) ---
    handleTouchStart(e) {
      const td = e.target.closest("#planningTable td[data-date]");
      if (!td) return;

      // Si l'utilisateur touche une case, on bloque le scroll de la page pour le laisser sélectionner
      e.preventDefault();

      this.clearSelection();
      this.isSelecting = true;
      this.selectCell(td);
    }

    handleTouchMove(e) {
      if (!this.isSelecting) return;
      e.preventDefault(); // Bloque le défilement de l'écran pendant qu'on glisse le doigt

      const touch = e.touches[0];
      // elementFromPoint permet de savoir sur quelle case se trouve le doigt actuellement
      const target = document.elementFromPoint(touch.clientX, touch.clientY);
      if (!target) return;

      const td = target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
    }

    handleTouchEnd(e) {
      if (!this.isSelecting) return;
      this.isSelecting = false;
      if (!this.selectedCells.length) return;

      const dates = this.selectedCells.map((c) => c.dataset.date);
      // Sur mobile, on s'en fiche de X et Y car le menu s'affiche en bas de l'écran
      this.showMenu(0, 0, dates, false);
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
      ) {
        this.clearSelection();
      }
    }

    showMenu(x, y, dates, isMonthView) {
      const menu = isMonthView ? this.monthSelectionMenu : this.selectionMenu;
      if (!menu) return;

      this._currentBulkInfo = { dates };
      const dateLabel =
        dates.length > 1
          ? `${dates.length} jours`
          : new Date(dates[0]).toLocaleDateString("fr-FR");
      let html = `<div class="fc-menu-section"><strong>${dateLabel}</strong></div>`;
      html += `<div class="fc-menu-section"><strong>Congés Carole</strong><div class="fc-menu-grid"><button class="fc-menu-btn" data-action="add" data-type="OFF_CAROLE" data-person="Carole">Off Carole</button><button class="fc-menu-btn" data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">Extra Off</button></div><button class="fc-menu-danger" data-action="clear-type" data-cat="CONGE">Effacer congés Carole</button></div>`;
      html += `<div class="fc-menu-section"><strong>Garde</strong><div class="fc-menu-grid"><button class="fc-menu-btn" data-action="add" data-type="CENTRE">Centre</button><button class="fc-menu-btn" data-action="add" data-type="AVIS">Avis</button></div><button class="fc-menu-danger" data-action="clear-type" data-cat="GARDE">Effacer Garde</button></div>`;
      html += `<div class="fc-menu-section"><strong>Pep</strong><button class="fc-menu-btn" data-action="add" data-type="PEP_SICK" style="width:100%">Pep Malade</button><button class="fc-menu-danger" data-action="clear-type" data-cat="PEP">Effacer Pep</button></div>`;
      html += `<div class="fc-menu-section"><strong>Congés Alex / Laia</strong><div class="fc-menu-leaves-table"><table><thead><tr><th>Alex</th><th>Laia</th></tr></thead><tbody>`;
      ["CP", "JRA", "JA"].forEach((t) => {
        html += `<tr><td><button class="fc-menu-btn" data-action="add-leave" data-pid="2" data-type="${t}">${t}</button></td><td><button class="fc-menu-btn" data-action="add-leave" data-pid="3" data-type="${t}">${t}</button></td></tr>`;
      });
      html += `</tbody></table></div><button class="fc-menu-danger" data-action="clear-leaves">Effacer Alex/Laia</button></div>`;

      menu.innerHTML = html;
      const menuWidth = 240;
      let left = x + 10;
      if (left + menuWidth > window.innerWidth) left = x - menuWidth - 10;
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

      const { action, type, pid, cat } = dataset;
      const dates = this._currentBulkInfo.dates;

      try {
        if (action === "add") {
          let typesToClear = [];
          if (["OFF_CAROLE", "EXTRA_OFF_CAROLE"].includes(type))
            typesToClear = CONGE_TYPES;
          if (["CENTRE", "AVIS"].includes(type)) typesToClear = GUARDE_TYPES;
          if (type === "PEP_SICK") typesToClear = PEP_TYPES;

          if (typesToClear.length) {
            await this.postApi(
              "/modules/family-calendar/includes/api/manage-event.php",
              { action: "bulk_delete_day_types", dates, types: typesToClear },
            );
          }
          const payload = dates.map((d) => ({
            date: d,
            type: type,
            duration: 1,
            person: "Carole",
          }));
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            payload,
          );
        } else if (action === "clear-type") {
          let typesToClear = [];
          if (cat === "CONGE") typesToClear = CONGE_TYPES;
          if (cat === "GARDE") typesToClear = GUARDE_TYPES;
          if (cat === "PEP") typesToClear = PEP_TYPES;

          await this.postApi(
            "/modules/family-calendar/includes/api/manage-event.php",
            { action: "bulk_delete_day_types", dates, types: typesToClear },
          );
        } else if (action === "add-leave") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            { action: "bulk_delete_day_person", dates, person_id: pid },
          );
          const payload = dates.map((d) => ({
            date: d,
            person_id: pid,
            leave_type: type,
            duration: 1,
          }));
          await this.postApi(
            "/modules/family-calendar/includes/api/save-leaves.php",
            payload,
          );
        } else if (action === "clear-leaves") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            { action: "bulk_delete_day_person", dates, person_id: 2 },
          );
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            { action: "bulk_delete_day_person", dates, person_id: 3 },
          );
        }

        await this.refreshAllData();
      } catch (e) {
        alert("Erreur action: " + e.message);
      }
    }
  }

  new FamilyCalendar();
});
