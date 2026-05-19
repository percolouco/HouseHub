/**
 * family-calendar.js (Version Optimisée - API BDD & Décalage Vendredi)
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];
  const FAMILY = {
    ALEX: { id: 2, prefix: "alex" },
    LAIA: { id: 3, prefix: "laia" },
  };
  const LEAVES_CONFIG = {
    CP: {
      startMonth: 8, // Le cycle commence en août (après la tolérance de juillet)
      defaultBalance: 25,
    },
    JRA: {
      // Tu pourras ajouter les années suivantes ici
      yearlyTotals: {
        2024: 10, // ex: 0.83 * 12 arrondi
        2025: 10,
        2026: 11, // ex: 0.9 * 12 arrondi
      },
      defaultBalance: 10,
      toleranceMonths: 2, // Janvier et Février
      maxReport: 2,
    },
    JA: {
      [FAMILY.ALEX.id]: { startMonth: 4, startDay: 29, defaultBalance: 4 },
      [FAMILY.LAIA.id]: { startMonth: 10, startDay: 1, defaultBalance: 4 }, // Date de Laia à adapter
    },
  };

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

      this.setupModalUI();
      this.initSmartSelectors();
      await this.refreshAllData();
      this.updateSchoolYearLabel();

      setTimeout(() => this.scrollToCurrentMonth(), 100);
    }

    // Modifie dynamiquement le titre de la modale pour y insérer le selecteur d'année
    setupModalUI() {
      const headerTitle = document.querySelector(
        "#modalHolidays .pf-modal-title",
      );

      if (headerTitle && !document.getElementById("holidayYearSelect")) {
        let options = "";
        const currentY = new Date().getFullYear();
        // On affiche de N-2 à N+3 pour avoir un bel historique/futur
        for (let y = currentY - 2; y <= currentY + 3; y++) {
          const selected = y === this.currentSchoolYearStart ? "selected" : "";
          options += `<option value="${y}" ${selected}>${y} - ${y + 1}</option>`;
        }

        // --- NOUVEAU : Récupération de la zone dynamique ---
        const zoneText =
          window.CONFIG &&
          window.CONFIG.ZONE_SCOLAIRE &&
          window.CONFIG.ZONE_SCOLAIRE !== "Autre"
            ? `(Zone ${window.CONFIG.ZONE_SCOLAIRE})`
            : "";

        // Injection du texte de la zone dans le header
        headerTitle.innerHTML = `🏖️ ${window.I18N["fc_modal_holidays_title"]} ${zoneText}
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

      // 1. Remplir les mois en tenant compte de la langue locale
      for (let i = 0; i < 12; i++) {
        const d = new Date(2000, i, 1);
        const monthName = new Intl.DateTimeFormat(lang, {
          month: "long",
        }).format(d);
        selectMonth.add(new Option(monthName, i));
      }

      // 2. Remplir les années (de l'année actuelle -2 à +5)
      const currentY = new Date().getFullYear();
      for (let y = currentY - 2; y <= currentY + 5; y++) {
        selectYear.add(new Option(y, y));
      }

      // 3. Écouteurs d'événements pour le rechargement en direct
      const handleChange = () => {
        const m = parseInt(selectMonth.value);
        const y = parseInt(selectYear.value);
        this.currentMonth = new Date(y, m, 1);
        this.renderMonthCalendar();
      };

      selectMonth.addEventListener("change", handleChange);
      selectYear.addEventListener("change", handleChange);
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

        this.fixedEvents = await this.fetchPublicHolidays();

        const leavesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leaves.php",
        );
        this.leaves = leavesData.leaves || [];

        const balancesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-balances.php",
        );
        this.leaveBalances = balancesData.balances || [];

        // --- NOUVEAU : Chargement des correctifs de congés ---
        const snapshotsData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-snapshots.php",
        );
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

    // Les jours fériés restent hardcodés car ils sont fixes.
    async fetchPublicHolidays() {
      try {
        // Appel à l'API officielle des jours fériés en métropole
        const res = await fetch(
          "https://calendrier.api.gouv.fr/jours-feries/metropole.json",
        );
        const holidays = await res.json();

        // L'API renvoie un objet : { "2024-01-01": "Jour de l'an", ... }
        // On le transforme en tableau compatible avec ton système d'événements
        return Object.keys(holidays).map((date, idx) => ({
          id: `ph-${idx}`,
          date: date,
          name: holidays[date], // On garde le nom au cas où tu veuilles l'afficher plus tard
          type: "PUBLIC_HOLIDAY",
          duration: 1,
        }));
      } catch (error) {
        console.error(
          "Erreur lors de la récupération des jours fériés :",
          error,
        );
        return []; // Évite de casser le calendrier si l'API de l'État est indisponible
      }
    }

    // --- RECONSTRUCTION DE LA MODALE VIA LA BDD (Par blocs consécutifs) ---
    renderModalHolidays() {
      if (!this.schoolHolidaysTableBody) return;

      // On utilise bien l'année de la modale, indépendamment du planning de fond
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
              <p style="color:#64748b; margin-bottom:15px;">${tr("fc_err_no_data_gov")}</p>
              <button id="btnFetchGovHolidays" class="pf-btn">${tr("btn_import")}</button>
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
            (d.getTime() - currentBlock.end.getTime()) / (1000 * 60 * 60 * 24),
          );
          if (diffDays <= 4) {
            currentBlock.end = d;
          } else {
            currentBlock = { start: d, end: d };
            blocks.push(currentBlock);
          }
        }
      });

      let html = "";
      blocks.forEach((block) => {
        const m = block.start.getMonth() + 1;
        const durationDays =
          Math.round(
            (block.end.getTime() - block.start.getTime()) /
              (1000 * 60 * 60 * 24),
          ) + 1;

        let name = tr("leg_school_holidays");
        if (m === 10 || m === 11) name = tr("vac_toussaint");
        else if (m === 12 || m === 1) name = tr("vac_noel");
        else if (m === 2 || m === 3) name = tr("vac_hiver");
        else if (m === 4 || (m === 5 && durationDays > 6))
          name = tr("vac_printemps");
        else if (m === 5 && durationDays <= 6) name = tr("vac_ascension");
        else if (m === 7 || m === 8) name = tr("vac_ete");

        html += `
          <tr>
            <td><strong>${name}</strong></td>
            <td>${block.start.toLocaleDateString(window.appLang || "fr-FR")}</td>
            <td>${block.end.toLocaleDateString(window.appLang || "fr-FR")}</td>
          </tr>
        `;
      });

      this.schoolHolidaysTableBody.innerHTML = html;
    }

    // --- IMPORTATION DEPUIS L'API & SAUVEGARDE EN BDD ---
    async fetchAndSaveGovHolidays(yearStart) {
      try {
        const yearStr = `${yearStart}-${yearStart + 1}`;
        const zone = window.CONFIG?.ZONE_SCOLAIRE || "C";

        if (zone === "Autre") {
          alert(
            "L'importation automatique n'est disponible que pour les zones A, B ou C (France).",
          );
          const btn = document.getElementById("btnFetchGovHolidays");
          if (btn) {
            btn.innerText = "Non disponible";
            btn.disabled = true;
          }
          return;
        }

        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone ${zone}%'&limit=100`;
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
              l.person_id === FAMILY.ALEX.id
                ? FAMILY.ALEX.prefix
                : l.person_id === FAMILY.LAIA.id
                  ? FAMILY.LAIA.prefix
                  : null;
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
        [FAMILY.ALEX.id]: { CP: {}, JRA: {}, JA: {} },
        [FAMILY.LAIA.id]: { CP: {}, JRA: {}, JA: {} },
      };

      // Liste des mois actuellement affichés dans le planning
      const ymSet = new Set();
      this.weeks.forEach((w) => ymSet.add(w.monthKey));
      const ymList = Array.from(ymSet).sort();

      // Pré-calcul de l'utilisation par mois
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

      [FAMILY.ALEX.id, FAMILY.LAIA.id].forEach((pid) => {
        ["CP", "JRA", "JA"].forEach((type) => {
          ymList.forEach((ym) => {
            const [currYear, currMonth] = ym.split("-").map(Number);

            let cycleStartStr = "";
            let initialBalance = 0;

            // --- 1. RECHERCHE D'UN CORRECTIF MANUEL (SNAPSHOT) ---
            // On cherche le snapshot le plus récent qui est inférieur ou égal au mois en cours de calcul
            const latestSnapshot = (this.leaveSnapshots || [])
              .filter(
                (s) =>
                  s.person_id == pid &&
                  s.leave_type == type &&
                  s.snapshot_date.substring(0, 7) <= ym,
              )
              // On trie du plus récent au plus ancien pour prendre le premier
              .sort((a, b) =>
                b.snapshot_date.localeCompare(a.snapshot_date),
              )[0];

            if (latestSnapshot) {
              // Si on trouve un correctif, il devient notre nouveau "point zéro"
              cycleStartStr = latestSnapshot.snapshot_date.substring(0, 7);
              initialBalance = parseFloat(latestSnapshot.remaining_balance); // Utilisation de VOTRE nom de colonne
            }
            // --- 2. SINON, CALCUL CLASSIQUE PAR DÉFAUT ---
            else {
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

                // Logique de report du reliquat de l'année N-1
                if (currMonth <= LEAVES_CONFIG.JRA.toleranceMonths) {
                  const prevYear = currYear - 1;
                  const prevInitial =
                    LEAVES_CONFIG.JRA.yearlyTotals[prevYear] ||
                    LEAVES_CONFIG.JRA.defaultBalance;

                  let usedPrevYear = 0;
                  for (let m = 1; m <= 12; m++) {
                    const mStr = `${prevYear}-${String(m).padStart(2, "0")}`;
                    usedPrevYear += usageByMonth[pid]?.[type]?.[mStr] || 0;
                  }

                  const remainingPrevYear = Math.max(
                    0,
                    prevInitial - usedPrevYear,
                  );
                  // Ajout du report plafonné à 2 jours
                  initialBalance += Math.min(
                    remainingPrevYear,
                    LEAVES_CONFIG.JRA.maxReport,
                  );
                  console.log(
                    `[JRA] Personne ${pid}, Année ${currYear}: Report de ${Math.min(remainingPrevYear, LEAVES_CONFIG.JRA.maxReport)}j inclus.`,
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

            // --- 3. DÉDUCTION DES CONGÉS PRIS DEPUIS LE POINT ZÉRO ---
            let usedBeforeCurrentMonth = 0;

            Object.keys(usageByMonth[pid]?.[type] || {}).forEach((usedYm) => {
              // On ne déduit que ce qui a été posé entre le début du cycle (ou la date du snapshot) et le mois en cours
              if (usedYm >= cycleStartStr && usedYm < ym) {
                usedBeforeCurrentMonth += usageByMonth[pid][type][usedYm];
              }
            });

            const available = Math.max(
              0,
              initialBalance - usedBeforeCurrentMonth,
            );
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
            let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; line-height:1; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
            if (dayLeaves.some((l) => l.person_id === window.CONFIG.ID_ALEX))
              html += `<span style="color:#0f766e; font-weight:800;">A</span>`;
            if (dayLeaves.some((l) => l.person_id === window.CONFIG.ID_LAIA))
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
          renderPersonCols(FAMILY.ALEX.id, `col-${FAMILY.ALEX.prefix}`);
          renderPersonCols(FAMILY.LAIA.id, `col-${FAMILY.LAIA.prefix}`);
        }
        this.planningBody.appendChild(tr);
      });
    }

    // --- Auto-scroll vers le mois en cours ---
    scrollToCurrentMonth() {
      const wrapper = document.getElementById("planningTable-wrapper");
      const thead = document.querySelector("#planningTable thead");
      if (!wrapper || !thead) return;

      const now = new Date();
      // Construit la clé au format "YYYY-MM"
      const currentYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;

      // Cherche la TOUTE PREMIÈRE ligne qui correspond à ce mois
      const targetRow = document.querySelector(
        `#planningTable tbody tr[data-month="${currentYm}"]`,
      );

      if (targetRow) {
        // On donne 50ms au navigateur pour finir son rendu graphique avant de calculer les hauteurs
        setTimeout(() => {
          const scrollPos = targetRow.offsetTop - thead.offsetHeight;
          wrapper.scrollTo({
            top: scrollPos > 0 ? scrollPos : 0,
            behavior: "smooth",
          });
        }, 50);
      }
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;
      this.monthCalendar.innerHTML = "";

      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();

      const selectMonth = document.getElementById("fc-select-month");
      const selectYear = document.getElementById("fc-select-year");
      // On masque définitivement le suffixe encombrant
      const suffixEl = document.getElementById("fc-multi-month-suffix");

      if (selectMonth && selectYear) {
        selectMonth.value = m;
        selectYear.value = y;
        if (suffixEl) suffixEl.style.display = "none";

        if (this.viewMode === "3months") {
          this.renderThreeMonthsView();
        } else if (this.viewMode === "2months") {
          this.renderTwoMonthsView();
        } else {
          this.monthCalendar.innerHTML = this.generateMonthHTML(y, m);
        }
      }

      this.renderMonthBalances();
      this.syncSummaryWithMonth();
    }

    renderTwoMonthsView() {
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const nextDate = new Date(y, m + 1, 1);
      const lang = window.I18N_LANG || "fr-FR";

      let html = `<div class="fc-two-months-container">`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(this.currentMonth)}</div>${this.generateMonthHTML(y, m)}</div>`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(nextDate)}</div>${this.generateMonthHTML(nextDate.getFullYear(), nextDate.getMonth())}</div>`;
      html += `</div>`;

      this.monthCalendar.innerHTML = html;
    }

    renderThreeMonthsView() {
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const d1 = this.currentMonth;
      const d2 = new Date(y, m + 1, 1);
      const d3 = new Date(y, m + 2, 1);

      let html = `<div class="fc-three-months-container">`;
      [d1, d2, d3].forEach((d) => {
        html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(d)}</div>${this.generateMonthHTML(d.getFullYear(), d.getMonth())}</div>`;
      });
      html += `</div>`;
      this.monthCalendar.innerHTML = html;
    }

    renderMonthBalances() {
      const container = document.getElementById("fc-month-balances");
      if (!container) return;

      const monthsToDisplay = [];
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();

      let numMonths =
        this.viewMode === "2months" ? 2 : this.viewMode === "3months" ? 3 : 1;

      for (let i = 0; i < numMonths; i++) {
        const d = new Date(y, m + i, 1);
        monthsToDisplay.push(
          `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
        );
      }

      container.style.display = "flex";
      let html = "";

      [FAMILY.ALEX, FAMILY.LAIA].forEach((person) => {
        html += `<div class="fc-minimal-balance-card">
                  <strong class="${person.prefix}">${person.prefix.toUpperCase()}</strong>
                  <div class="fc-minimal-chips">`;

        ["CP", "JRA", "JA"].forEach((type) => {
          const startInfo =
            this.monthlyLeaveBalances[person.id]?.[type]?.[monthsToDisplay[0]];
          const startBal = startInfo ? startInfo.availableAtMonthStart : 0;

          let totalUsed = 0;
          monthsToDisplay.forEach((ym) => {
            const info = this.monthlyLeaveBalances[person.id]?.[type]?.[ym];
            if (info) totalUsed += info.usedInMonth;
          });

          const endBal = Math.max(0, startBal - totalUsed);
          const fmt = (n) =>
            n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "0";

          const usedHtml =
            totalUsed > 0
              ? `<span class="fc-used-badge" title="Posé sur la période">-${fmt(totalUsed)}</span>`
              : "";

          // --- LOGIQUE D'ALERTE FIRE 🔥 ---
          let alertHtml = "";
          const currentYm = monthsToDisplay[0];
          const [cYear, cMonth] = currentYm.split("-").map(Number);

          if (endBal > 0) {
            if (type === "CP" && cMonth >= 6 && cMonth <= 7) {
              // CP : Alerte en Juin et Juillet uniquement
              let msg = (
                window.I18N["fc_alert_burn_days"] || "Perte: %s j avant le %s"
              )
                .replace("%s", fmt(endBal))
                .replace("%s", "31/07");
              alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥</div>`;
            } else if (type === "JRA" && (cMonth === 1 || cMonth === 2)) {
              // JRA : Alerte Janvier/Février si > 2 jours
              if (endBal > 2) {
                let msg =
                  window.I18N["fc_alert_burn_jra"] || "Seuls 2j reportables";
                alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥 ${fmt(endBal - 2)}</div>`;
              }
            } else if (type === "JA") {
              const configJA = LEAVES_CONFIG.JA[person.id];
              let limitMonth = configJA.startMonth - 1 || 12;
              if (cMonth === limitMonth || cMonth === configJA.startMonth) {
                let msg = (window.I18N["fc_alert_burn_days"] || "Perte: %s j")
                  .replace("%s", fmt(endBal))
                  .replace("%s", window.I18N["ANNIV"] || "Anniv");
                alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥</div>`;
              }
            }
          }

          html += `<div class="fc-min-chip" title="Solde départ: ${fmt(startBal)}">
                      <span class="type">${type}</span>
                      <span class="val" title="Restant">${fmt(endBal)}</span>
                      ${usedHtml}
                      ${alertHtml}
                   </div>`;
        });
        html += `</div></div>`;
      });
      container.innerHTML = html;
    }

    syncSummaryWithMonth() {
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (typeSelect && valueSelect) {
        // Le récap se synchronise avec le PREMIER mois affiché de la période
        const ym = `${this.currentMonth.getFullYear()}-${String(this.currentMonth.getMonth() + 1).padStart(2, "0")}`;

        if (typeSelect.value !== "month") {
          typeSelect.value = "month";
          typeSelect.dispatchEvent(new Event("change"));
        }
        valueSelect.value = ym;
        this.updateGlobalSummary();
      }
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
        const todayIso = this.getLocalIsoDate(new Date()); // Date du jour

        let cls = "fc-month-day";
        if (iso === todayIso) cls += " fc-day--today"; // On applique la classe !
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

        let content = `<div style="position:relative; height:100%;"><span class="fc-day-number">${dayCounter}</span>`;
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
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_off_carole")}</span><span class="fc-summary-value">${parseFloat(stats.off.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_extra_off")}</span><span class="fc-summary-value">${parseFloat(stats.extra.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_pep_sick")}</span><span class="fc-summary-value">${parseFloat(stats.sick.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_presence")}</span><span class="fc-summary-value">${parseFloat(stats.pep.toFixed(1))} ${tr("fc_unit_days")}</span></div>
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
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });
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

        // Tactile (Mobile/Tablette)
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
          // Bloquer si on est déjà en train de charger
          if (this._isAutoLoading) return;

          // Détection du bas (On descend dans le temps : passage à l'année suivante)
          // On met une tolérance de 5px pour les calculs de pixels décimaux
          if (
            scrollWrapper.scrollTop + scrollWrapper.clientHeight >=
            scrollWrapper.scrollHeight - 5
          ) {
            this._isAutoLoading = true;
            await this.changeSchoolYear(1);
            // On replace le scroll tout en haut pour la continuité visuelle
            scrollWrapper.scrollTop = 5;
            setTimeout(() => (this._isAutoLoading = false), 500);
          }
          // Détection du haut (On remonte le temps : passage à l'année précédente)
          else if (scrollWrapper.scrollTop === 0) {
            this._isAutoLoading = true;
            await this.changeSchoolYear(-1);
            // On replace le scroll tout en bas pour la continuité visuelle
            scrollWrapper.scrollTop =
              scrollWrapper.scrollHeight - scrollWrapper.clientHeight - 5;
            setTimeout(() => (this._isAutoLoading = false), 500);
          }
        });
      }

      // --- GESTION MODALE VACANCES SCOLAIRES ---
      const btnOpen = document.getElementById("btnOpenHolidays");
      const btnClose = document.getElementById("btnCloseHolidays");
      const modal = document.getElementById("modalHolidays");

      if (btnOpen && modal) {
        btnOpen.addEventListener("click", () => {
          modal.classList.add("open"); // Utilisation propre de la classe CSS
          document.body.classList.add("no-scroll");

          const yearSelect = document.getElementById("holidayYearSelect");
          if (yearSelect) yearSelect.value = this.currentSchoolYearStart;
          this.modalSelectedYear = this.currentSchoolYearStart;
          this.renderModalHolidays();
        });
      }
      if (btnClose && modal) {
        btnClose.addEventListener("click", () => {
          modal.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      }
      if (modal) {
        modal.addEventListener("click", (e) => {
          if (e.target === modal) {
            modal.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });
      }

      // --- GESTION MODALE CORRECTIF DES SOLDES (SNAPSHOTS) ---
      const btnSnap = document.getElementById("btnOpenSnapshotModal");
      const modalSnap = document.getElementById("modalSnapshot");
      const btnCloseSnap = document.getElementById("btnCloseSnapshot");
      const formSnap = document.getElementById("formSnapshot");

      if (btnSnap && modalSnap) {
        btnSnap.addEventListener("click", () => {
          modalSnap.classList.add("open"); // On ouvre la modale correctement
          document.body.classList.add("no-scroll");

          // Pré-remplir la date avec le 1er jour du mois en cours
          const today = new Date();
          const y = today.getFullYear();
          const m = String(today.getMonth() + 1).padStart(2, "0");
          const snapDateInput = document.getElementById("snapDate");
          if (snapDateInput) snapDateInput.value = `${y}-${m}-01`;
        });
      }

      if (btnCloseSnap && modalSnap) {
        btnCloseSnap.addEventListener("click", () => {
          modalSnap.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      }

      if (modalSnap) {
        modalSnap.addEventListener("click", (e) => {
          if (e.target === modalSnap) {
            modalSnap.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });
      }

      if (formSnap) {
        formSnap.addEventListener("submit", async (e) => {
          e.preventDefault();

          const payload = {
            person_id: document.getElementById("snapPerson").value,
            leave_type: document.getElementById("snapType").value,
            snapshot_date: document.getElementById("snapDate").value,
            remaining_balance: document.getElementById("snapBalance").value,
          };

          try {
            await this.postApi(
              "/modules/family-calendar/includes/api/save-leave-snapshot.php",
              payload,
            );

            modalSnap.classList.remove("open"); // Fermeture propre
            document.body.classList.remove("no-scroll");
            formSnap.reset();

            await this.refreshAllData();
          } catch (error) {
            alert("Erreur lors de la sauvegarde du correctif.");
          }
        });
      }

      // --- GESTION DU RESTE DE L'INTERFACE ---
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
      // --- BOUTON AUJOURD'HUI ---
      document.getElementById("fc-today-btn")?.addEventListener("click", () => {
        this.currentMonth = new Date();
        this.currentMonth.setDate(1); // Force au 1er du mois pour éviter les bugs de fins de mois
        this.renderMonthCalendar();
      });

      // --- GESTION DU SWIPE MENSUEL (MOBILE) ---
      if (this.monthCalendar) {
        let touchStartX = 0;
        let touchStartY = 0;

        // On écoute le début du toucher
        this.monthCalendar.addEventListener(
          "touchstart",
          (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
          },
          { passive: true },
        );

        // On écoute la fin du toucher
        this.monthCalendar.addEventListener(
          "touchend",
          (e) => {
            const touchEndX = e.changedTouches[0].screenX;
            const touchEndY = e.changedTouches[0].screenY;

            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;

            // Détection d'un swipe horizontal (on exclut les swipes verticaux de défilement)
            // Seuil de 50px pour éviter les déclenchements par erreur
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
              const numMonths =
                this.viewMode === "2months"
                  ? 2
                  : this.viewMode === "3months"
                    ? 3
                    : 1;

              if (deltaX < 0) {
                // Swipe vers la GAUCHE = Aller dans le FUTUR (Mois suivants)
                this.currentMonth.setMonth(
                  this.currentMonth.getMonth() + numMonths,
                );
              } else {
                // Swipe vers la DROITE = Revenir dans le PASSÉ (Mois précédents)
                this.currentMonth.setMonth(
                  this.currentMonth.getMonth() - numMonths,
                );
              }

              this.renderMonthCalendar(); // Rafraîchit l'UI
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

      // --- 🔍 DÉTECTION DES ÉVÉNEMENTS & CONGÉS EXISTANTS ---
      const activeEvents = new Set();
      this.events.forEach((e) => {
        if (dates.includes(e.date)) activeEvents.add(e.type);
      });

      const activeLeaves = { 2: new Set(), 3: new Set() };
      this.leaves.forEach((l) => {
        if (dates.includes(l.leave_date)) {
          if (activeLeaves[l.person_id])
            activeLeaves[l.person_id].add(l.leave_type);
        }
      });

      // Petites fonctions utilitaires pour gérer l'UI du bouton
      const getBtnClass = (type, personId = null) => {
        let isActive = personId
          ? activeLeaves[personId] && activeLeaves[personId].has(type)
          : activeEvents.has(type);
        return isActive ? "fc-menu-btn--active" : "";
      };
      const getBtnIcon = (type, personId = null) => {
        let isActive = personId
          ? activeLeaves[personId] && activeLeaves[personId].has(type)
          : activeEvents.has(type);
        return isActive ? "✓ " : "";
      };

      // Gestion de la date affichée (Singulier/Pluriel)
      const dateLabel =
        dates.length > 1
          ? `${dates.length} ${tr("fc_unit_days")}`
          : new Date(dates[0]).toLocaleDateString(window.I18N_LANG || "fr-FR");

      // Icône SVG Poubelle
      const trashSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>`;

      const buildHeader = (title, action, cat) => `
        <div class="fc-menu-header">
          <strong>${title}</strong>
          ${action ? `<button class="fc-menu-clear-icon" title="${tr("fc_clear")}" data-action="${action}" ${cat ? `data-cat="${cat}"` : ""}>${trashSvg}</button>` : ""}
        </div>
      `;

      let html = `<div class="fc-menu-section" style="border-bottom: none; padding-bottom: 0;">
                    <strong style="font-size:0.85rem; color:var(--text-main); margin-bottom: 4px;">${dateLabel}</strong>
                  </div>`;

      // 2. Section Carole
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("fc_menu_carole"), "clear-type", "CONGE")}
                 <div class="fc-menu-grid">
                   <button class="fc-menu-btn ${getBtnClass("OFF_CAROLE")}" data-action="add" data-type="OFF_CAROLE" data-person="Carole">${getBtnIcon("OFF_CAROLE")}${tr("btn_off")}</button>
                   <button class="fc-menu-btn ${getBtnClass("EXTRA_OFF_CAROLE")}" data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">${getBtnIcon("EXTRA_OFF_CAROLE")}${tr("btn_extra")}</button>
                 </div>
               </div>`;

      // 3. Section Garde
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("leg_centre"), "clear-type", "GARDE")}
                 <div class="fc-menu-grid">
                   <button class="fc-menu-btn ${getBtnClass("CENTRE")}" data-action="add" data-type="CENTRE">${getBtnIcon("CENTRE")}${tr("leg_centre")}</button>
                   <button class="fc-menu-btn ${getBtnClass("AVIS")}" data-action="add" data-type="AVIS">${getBtnIcon("AVIS")}${tr("leg_avis")}</button>
                 </div>
               </div>`;

      // 4. Section Pep
      html += `<div class="fc-menu-section">
                 ${buildHeader("Pep", "clear-type", "PEP")}
                 <button class="fc-menu-btn ${getBtnClass("PEP_SICK")}" data-action="add" data-type="PEP_SICK" style="width:100%">${getBtnIcon("PEP_SICK")}${tr("leg_pep_sick")} 🤒</button>
               </div>`;

      // 5. Section Enfants
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("fc_menu_kids_leaves"), null, null)}
                 <div class="fc-menu-leaves-table">
                   <table>
                     <thead>
                       <tr>
                         <th>
                            <div class="fc-th-inline">
                              Alex <button class="fc-menu-clear-icon fc-menu-btn-th" data-action="clear-leaves-person" data-pid="2" title="${tr("fc_clear")} Alex">${trashSvg}</button>
                            </div>
                         </th>
                         <th>
                            <div class="fc-th-inline">
                              Laia <button class="fc-menu-clear-icon fc-menu-btn-th" data-action="clear-leaves-person" data-pid="3" title="${tr("fc_clear")} Laia">${trashSvg}</button>
                            </div>
                         </th>
                       </tr>
                     </thead>
                     <tbody>`;

      ["CP", "JRA", "JA"].forEach((t) => {
        html += `<tr>
                   <td><button class="fc-menu-btn ${getBtnClass(t, 2)}" data-action="add-leave" data-pid="2" data-type="${t}">${getBtnIcon(t, 2)}${t}</button></td>
                   <td><button class="fc-menu-btn ${getBtnClass(t, 3)}" data-action="add-leave" data-pid="3" data-type="${t}">${getBtnIcon(t, 3)}${t}</button></td>
                 </tr>`;
      });

      html += `      </tbody>
                   </table>
                 </div>
               </div>`;

      menu.innerHTML = html;

      // Positionnement (Gestion des bords d'écran)
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
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: parseInt(pid),
            },
          );

          const payload = dates.map((d) => ({
            date: d,
            person_id: parseInt(pid), // On s'assure que c'est bien un format numérique
            leave_type: type,
            duration: 1,
          }));
          await this.postApi(
            "/modules/family-calendar/includes/api/save-leaves.php",
            payload,
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
        } else if (action === "clear-leaves") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: window.CONFIG.ID_ALEX,
            },
          );
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: window.CONFIG.ID_LAIA,
            },
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
