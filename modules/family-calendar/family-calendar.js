/**
 * family-calendar.js (Version Sécurisée)
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];

  class FamilyCalendar {
    constructor() {
      // Éléments DOM (avec sécurité null)
      this.planningBody = document.getElementById("planningBody");
      this.selectionMenu = document.getElementById("selectionMenu");
      this.schoolHolidaysTableBody = document.querySelector(
        "#schoolHolidaysTable tbody",
      );
      this.monthCalendar = document.getElementById("fc-month-calendar");
      this.monthSelectionMenu = document.getElementById(
        "fc-month-selectionMenu",
      );

      // Déplacement des menus dans le body pour affichage correct
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

      this.isSelecting = false;
      this.selectedCells = [];
      this.monthSelectedCells = [];
      this._currentBulkInfo = null;

      // Données
      this.dbEvents = [];
      this.fixedEvents = [];
      this.events = [];
      this.leaves = [];
      this.weeks = [];
      this.monthlyLeaveBalances = {
        2: { CP: {}, JRA: {}, JA: {} },
        3: { CP: {}, JRA: {}, JA: {} },
      };

      // Si le tableau principal n'est pas là, on arrête tout pour ne pas crasher
      if (!this.planningBody) {
        console.warn("Element #planningBody introuvable. Le JS s'arrête.");
        return;
      }

      this.init();
    }

    async init() {
      this.setupEventListeners();
      const now = new Date();
      this.currentSchoolYearStart =
        now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;

      await this.refreshAllData();
      this.updateSchoolYearLabel();
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

        this.fixedEvents = await this.fetchPublicAndSchoolHolidays();

        const leavesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leaves.php",
        );
        this.leaves = leavesData.leaves || [];

        const balancesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-balances.php",
        );
        this.leaveBalances = balancesData.balances || [];

        this.events = [...this.dbEvents, ...this.fixedEvents];
        this.publicHolidayDates = new Set(
          this.fixedEvents
            .filter((e) => e.type === "PUBLIC_HOLIDAY")
            .map((e) => e.date),
        );

        this.reprocessAndRender();
      } catch (e) {
        console.error("Erreur chargement données:", e);
      }
    }

    async fetchPublicAndSchoolHolidays() {
      // 1. Jours fériés (Statique)
      const publicHolidays = [
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
      ].map((date, idx) => ({
        id: `ph-${idx}`,
        date,
        type: "PUBLIC_HOLIDAY",
        duration: 1,
      }));

      const schoolHolidayEvents = [];

      try {
        // API Gouv
        const url =
          "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='2025-2026' AND zones LIKE '%Zone C%'&limit=100";
        const res = await fetch(url);
        const data = await res.json();
        const rawRecords = data.results || [];

        // Dédoublonnage
        const uniqueHolidaysMap = new Map();
        rawRecords.forEach((r) => {
          const key = `${r.description}|${r.start_date}`;
          if (!uniqueHolidaysMap.has(key)) uniqueHolidaysMap.set(key, r);
        });
        const uniqueRecords = Array.from(uniqueHolidaysMap.values());

        // APPEL DE LA MÉTHODE D'AFFICHAGE (séparée pour la propreté)
        this.renderSchoolHolidaysTable(uniqueRecords);

        // Génération des events pour le calendrier (cases colorées)
        uniqueRecords.forEach((r, idx) => {
          let curr = new Date(r.start_date);
          const end = new Date(r.end_date);
          while (curr < end) {
            const iso = curr.toISOString().split("T")[0];
            schoolHolidayEvents.push({
              id: `sh-${iso}-${idx}`,
              date: iso,
              type: "VACANCES_SCOLAIRES",
              duration: 1,
            });
            curr.setDate(curr.getDate() + 1);
          }
        });
      } catch (e) {
        console.warn("Erreur API Vacances (pas grave, on continue)", e);
      }

      // On retourne TOUJOURS les événements, même si l'affichage du tableau échoue
      return [...publicHolidays, ...schoolHolidayEvents];
    }

    renderSchoolHolidaysTable(records) {
      // Si le tableau HTML n'existe pas (ex: on n'est pas sur la bonne page), on ne fait rien
      if (!this.schoolHolidaysTableBody) return;

      // 1. Tri chronologique strict (du plus ancien au plus récent)
      records.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));

      // 2. Génération du HTML
      this.schoolHolidaysTableBody.innerHTML = records
        .map(
          (r) => `
         <tr>
           <td>${r.description}</td>
           <td>${new Date(r.start_date).toLocaleDateString("fr-FR")}</td>
           <td>${new Date(r.end_date).toLocaleDateString("fr-FR")}</td>
         </tr>
       `,
        )
        .join("");
    }

    // ================== LOGIQUE MÉTIER ==================

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
          if (!this.publicHolidayDates.has(d.toISOString().split("T")[0]))
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
              if (usedYm >= periodStart && usedYm < ym) {
                usedBefore += usageByMonth[pid][type][usedYm];
              }
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
          td.className = "col-month";
          td.textContent = w.monthName;
          td.rowSpan = monthSpans[w.monthKey];
          tr.appendChild(td);
        }

        const tdW = document.createElement("td");
        tdW.className = "col-month";
        tdW.textContent = w.weekLabel;
        tr.appendChild(tdW);

        ["mon", "tue", "wed", "thu", "fri"].forEach((d) => {
          const td = document.createElement("td");
          const dateObj = w.dayDates[d];
          const iso = dateObj.toISOString().split("T")[0];
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
      html += `</tr></thead><tbody>`;

      const firstDay = new Date(year, month, 1);
      const startDay = (firstDay.getDay() + 6) % 7;
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      let dayCounter = 1;

      for (let row = 0; row < 6; row++) {
        html += `<tr>`;
        for (let col = 0; col < 5; col++) {
          if (
            (row === 0 && col < startDay && startDay < 5) ||
            dayCounter > daysInMonth
          ) {
            html += `<td class="fc-day--other-month"></td>`;
          } else if (row === 0 && startDay >= 5) {
            html += `<td class="fc-day--other-month"></td>`;
          } else {
            const iso = `${year}-${String(month + 1).padStart(2, "0")}-${String(dayCounter).padStart(2, "0")}`;
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
              if (dayEvts.some((e) => e.type === "AVIS"))
                cls += " fc-day--avis";
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
            dayCounter++;
          }
        }
        html += `</tr>`;
        if (dayCounter > daysInMonth) break;
      }
      html += `</tbody></table>`;
      return html;
    }

    // --- NOUVELLES MÉTHODES POUR LE RÉCAPITULATIF DYNAMIQUE ---

    /**
     * Initialise les menus déroulants (Année / Mois)
     */
    initSummaryControls() {
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (!typeSelect || !valueSelect) return;

      // 1. Scanner les données pour trouver les années et mois disponibles
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

      // 2. Fonction interne pour remplir le 2ème menu (Valeurs)
      const populateValues = () => {
        const currentType = typeSelect.value;
        const previousValue = valueSelect.value; // Pour essayer de garder la sélection si possible

        valueSelect.innerHTML = ""; // On vide

        if (currentType === "year") {
          sortedYears.forEach((y) => {
            const opt = document.createElement("option");
            opt.value = y;
            opt.textContent = y;
            valueSelect.appendChild(opt);
          });

          // Sélection intelligente : garder l'ancienne ou prendre l'année en cours
          if (sortedYears.includes(parseInt(previousValue))) {
            valueSelect.value = previousValue;
          } else {
            const currentYear = new Date().getFullYear();
            if (sortedYears.includes(currentYear))
              valueSelect.value = currentYear;
          }
        } else {
          // Mode Mois
          sortedMonths.forEach((m) => {
            const [y, mo] = m.split("-");
            const dateObj = new Date(y, mo - 1, 1);
            // Format : "octobre 2025"
            const label = new Intl.DateTimeFormat("fr-FR", {
              month: "long",
              year: "numeric",
            }).format(dateObj);

            const opt = document.createElement("option");
            opt.value = m;
            opt.textContent = label;
            valueSelect.appendChild(opt);
          });

          // Sélection intelligente : mois courant
          const nowIso = new Date().toISOString().slice(0, 7); // "2025-01"
          if (sortedMonths.includes(nowIso)) {
            valueSelect.value = nowIso;
          }
        }

        // Une fois rempli, on met à jour les chiffres
        this.updateGlobalSummary();
      };

      // 3. Attacher les écouteurs (UNE SEULE FOIS)
      if (!this.summaryListenersAttached) {
        typeSelect.addEventListener("change", populateValues);
        valueSelect.addEventListener("change", () =>
          this.updateGlobalSummary(),
        );
        this.summaryListenersAttached = true;
      }

      // 4. Premier appel pour remplir dès le chargement
      populateValues();
    }

    /**
     * Calcule et affiche les KPIs en fonction des filtres
     */
    updateGlobalSummary() {
      const div = document.getElementById("globalSummary");
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (!div) return;

      // Si les contrôles n'ont pas encore de valeur (chargement initial), on attend
      if (typeSelect && valueSelect && !valueSelect.value) return;

      const filterType = typeSelect ? typeSelect.value : "year";
      const filterValue = valueSelect
        ? valueSelect.value
        : new Date().getFullYear().toString(); // Fallback

      const stats = { off: 0, extra: 0, sick: 0, pep: 0, totalWorking: 0 };

      // Parcours précis jour par jour
      this.weeks.forEach((w) => {
        Object.entries(w.dayDates).forEach(([dayName, dateObj]) => {
          // Vérification du filtre
          let match = false;
          if (filterType === "year") {
            if (dateObj.getFullYear().toString() === filterValue) match = true;
          } else {
            // Month (YYYY-MM)
            const isoMonth = dateObj.toISOString().slice(0, 7);
            if (isoMonth === filterValue) match = true;
          }

          if (match) {
            // Récupérer les events de CE jour spécifique
            const dayEvents = w.dayFlags[dayName].events;

            // Additions
            dayEvents.forEach((e) => {
              const dur = parseFloat(e.duration) || 1;
              if (e.type === "OFF_CAROLE") stats.off += dur;
              if (e.type === "EXTRA_OFF_CAROLE") stats.extra += dur;
              if (e.type === "PEP_SICK") stats.sick += dur;
            });

            // Calcul présence (Jour ouvré - Absences)
            // Est-ce un jour ouvré ? (Pas férié, pas we - WE déjà exclu par structure semainier lun-ven)
            // NOTE : Ma structure `weeks` ne contient que Lun-Ven par défaut.
            const dateIso = dateObj.toISOString().split("T")[0];
            if (!this.publicHolidayDates.has(dateIso)) {
              // C'est un jour potentiellement ouvré
              stats.totalWorking++;

              // Calcul des absences sur ce jour
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

      // Affichage HTML Vertical
      div.innerHTML = `
         <div class="fc-summary-item">
            <span class="fc-summary-label">Off Carole</span>
            <span class="fc-summary-value">${parseFloat(stats.off.toFixed(1))} jours</span>
         </div>
         <div class="fc-summary-item">
            <span class="fc-summary-label">Extra Off Carole</span>
            <span class="fc-summary-value">${parseFloat(stats.extra.toFixed(1))} jours</span>
         </div>
         <div class="fc-summary-item">
            <span class="fc-summary-label">Pep Malade</span>
            <span class="fc-summary-value">${parseFloat(stats.sick.toFixed(1))} jours</span>
         </div>
         <div class="fc-summary-item">
            <span class="fc-summary-label">Présence Pep</span>
            <span class="fc-summary-value">${parseFloat(stats.pep.toFixed(1))} jours</span>
         </div>
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

    // ================== INTERACTIONS ==================

    setupEventListeners() {
      // Planning Hebdo
      if (this.planningBody) {
        this.planningBody.addEventListener("mousedown", (e) =>
          this.handleMouseDown(e),
        );
        document.addEventListener("mousemove", (e) => this.handleMouseMove(e));
        document.addEventListener("mouseup", (e) => this.handleMouseUp(e));
      }

      // Modale
      const btnOpen = document.getElementById("btnOpenHolidays");
      const btnClose = document.getElementById("btnCloseHolidays");
      const modal = document.getElementById("modalHolidays");
      if (btnOpen && modal)
        btnOpen.addEventListener("click", () => (modal.style.display = "flex"));
      if (btnClose && modal)
        btnClose.addEventListener(
          "click",
          () => (modal.style.display = "none"),
        );
      if (modal)
        modal.addEventListener("click", (e) => {
          if (e.target === modal) modal.style.display = "none";
        });

      // Calendrier Mensuel
      if (this.monthCalendar) {
        this.monthCalendar.addEventListener("click", (e) => {
          const td = e.target.closest("td[data-date]");
          if (td) {
            this.monthSelectedCells = [td];
            this.showMenu(e.pageX, e.pageY, [td.dataset.date], true);
          }
        });
      }

      // Menus
      document.addEventListener("click", (e) => this.closeMenusIfOutside(e));
      const handleMenu = (e) => {
        const btn = e.target.closest("button");
        if (btn) this.handleMenuAction(btn.dataset);
      };
      if (this.selectionMenu)
        this.selectionMenu.addEventListener("click", handleMenu);
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.addEventListener("click", handleMenu);

      // Nav Mensuelle
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

      // Nav Année
      document
        .getElementById("fc-prev-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(-1));
      document
        .getElementById("fc-next-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(1));

      // Boutons Vue
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
      // Fix : inclure la dernière cellule
      const td = e.target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);

      this.isSelecting = false;
      if (!this.selectedCells.length) return;

      const dates = this.selectedCells.map((c) => c.dataset.date);
      this.showMenu(e.pageX, e.pageY, dates, false);
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

      // Carole
      html += `<div class="fc-menu-section"><strong>Congés Carole</strong><div class="fc-menu-grid">`;
      html += `<button class="fc-menu-btn" data-action="add" data-type="OFF_CAROLE" data-person="Carole">Off Carole</button>`;
      html += `<button class="fc-menu-btn" data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">Extra Off</button>`;
      html += `</div><button class="fc-menu-danger" data-action="clear-type" data-cat="CONGE">Effacer congés Carole</button></div>`;

      // Garde
      html += `<div class="fc-menu-section"><strong>Garde</strong><div class="fc-menu-grid">`;
      html += `<button class="fc-menu-btn" data-action="add" data-type="CENTRE">Centre</button>`;
      html += `<button class="fc-menu-btn" data-action="add" data-type="AVIS">Avis</button>`;
      html += `</div><button class="fc-menu-danger" data-action="clear-type" data-cat="GARDE">Effacer Garde</button></div>`;

      // Pep
      html += `<div class="fc-menu-section"><strong>Pep</strong>`;
      html += `<button class="fc-menu-btn" data-action="add" data-type="PEP_SICK" style="width:100%">Pep Malade</button>`;
      html += `<button class="fc-menu-danger" data-action="clear-type" data-cat="PEP">Effacer Pep</button></div>`;

      // Alex/Laia
      html += `<div class="fc-menu-section"><strong>Congés Alex / Laia</strong><div class="fc-menu-leaves-table"><table><thead><tr><th>Alex</th><th>Laia</th></tr></thead><tbody>`;
      ["CP", "JRA", "JA"].forEach((t) => {
        html += `<tr><td><button class="fc-menu-btn" data-action="add-leave" data-pid="2" data-type="${t}">${t}</button></td>`;
        html += `<td><button class="fc-menu-btn" data-action="add-leave" data-pid="3" data-type="${t}">${t}</button></td></tr>`;
      });
      html += `</tbody></table></div>`;
      html += `<button class="fc-menu-danger" data-action="clear-leaves">Effacer Alex/Laia</button></div>`;

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
              {
                action: "bulk_delete_day_types",
                dates,
                types: typesToClear,
              },
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
            {
              action: "bulk_delete_day_types",
              dates,
              types: typesToClear,
            },
          );
        } else if (action === "add-leave") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: pid,
            },
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
