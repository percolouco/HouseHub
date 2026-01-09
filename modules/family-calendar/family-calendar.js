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

      // Mois courant réel pour le calendrier mensuel
      this.currentMonth = new Date();
      this.currentMonth.setDate(1); // premier jour du mois courant

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

      // Déterminer l'année scolaire en cours à partir de la date du jour
      const now = new Date();
      const nowMonth = now.getMonth(); // 0-11
      const nowYear = now.getFullYear();
      this.currentSchoolYearStart = nowMonth >= 8 ? nowYear : nowYear - 1;

      // Charger les semaines pour l'année scolaire courante
      this.weeks = await this.fetchWeeksStructureScolaire(
        this.currentSchoolYearStart
      );

      // Mettre à jour le label d'année scolaire dans l'UI
      this.updateSchoolYearLabel();

      this.dbEvents = this.loadDbEvents();
      this.fixedEvents = await this.fetchPublicAndSchoolHolidays();
      this.events = [...this.dbEvents, ...this.fixedEvents];
      this.leaves = await this.fetchLeaves();
      this.publicHolidayDates = new Set(
        this.fixedEvents
          .filter((e) => e.type === "PUBLIC_HOLIDAY")
          .map((e) => e.date)
      );
      this.leaveBalances = await this.fetchLeaveBalances();
      this.leaveSnapshots = await this.fetchLeaveSnapshots();
      this.personLeaveMeta = await this.fetchPersonLeaveMeta();

      this.reprocessAndRender();
      this.renderMonthCalendar();
    }

    async fetchLeaveBalances() {
      try {
        const res = await fetch(
          "/modules/family-calendar/includes/api/get-leave-balances.php"
        );
        if (!res.ok) {
          throw new Error("Erreur HTTP " + res.status);
        }
        const data = await res.json();
        return data.balances || [];
      } catch (err) {
        console.error("Erreur lors du chargement des soldes de congés:", err);
        return [];
      }
    }

    async fetchLeaveSnapshots() {
      try {
        const res = await fetch(
          "/modules/family-calendar/includes/api/get-leave-snapshots.php"
        );
        if (!res.ok) {
          throw new Error("Erreur HTTP " + res.status);
        }
        const data = await res.json();
        return data.snapshots || [];
      } catch (err) {
        console.error(
          "Erreur lors du chargement des snapshots de congés:",
          err
        );
        return [];
      }
    }

    async fetchPersonLeaveMeta() {
      try {
        const res = await fetch(
          "/modules/family-calendar/includes/api/get-person-leave-meta.php"
        );
        if (!res.ok) {
          throw new Error("Erreur HTTP " + res.status);
        }
        const data = await res.json();
        // meta: [ { person_id: 2, anniversary_date: "2020-04-30" }, ... ]
        const map = {};
        (data.meta || []).forEach((row) => {
          const pid = parseInt(row.person_id, 10);
          map[pid] = row.anniversary_date; // string "YYYY-MM-DD"
        });
        return map; // { 2: "2020-04-30", 3: "2020-04-30", ... }
      } catch (err) {
        console.error("Erreur fetchPersonLeaveMeta:", err);
        return {};
      }
    }

    getAvailableAtMonthStart(personId, leaveType, dateStr) {
      if (!this.monthlyLeaveBalances) return null;

      const dateObj = new Date(dateStr + "T00:00:00");
      const ymKey = `${dateObj.getFullYear()}-${String(
        dateObj.getMonth() + 1
      ).padStart(2, "0")}`;

      const personBal = (this.monthlyLeaveBalances[personId] || {})[leaveType];
      if (!personBal) return null;

      const info = personBal[ymKey];
      if (!info) return null;

      return info.availableAtMonthStart != null
        ? parseFloat(info.availableAtMonthStart)
        : null;
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

    async fetchWeeksStructureScolaire(schoolYearStart) {
      try {
        const year = schoolYearStart || new Date().getFullYear();
        const res = await fetch(
          `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${year}`
        );
        if (!res.ok) {
          throw new Error("Erreur HTTP " + res.status);
        }
        const data = await res.json();
        const weeks = data.weeks || [];

        return weeks.map((w) => {
          const mon = new Date(w.mon_date + "T00:00:00");
          const tue = new Date(w.tue_date + "T00:00:00");
          const wed = new Date(w.wed_date + "T00:00:00");
          const thu = new Date(w.thu_date + "T00:00:00");
          const fri = new Date(w.fri_date + "T00:00:00");

          return {
            id: `${w.week_iso_year}-W${w.week_iso_number}`, // ex: 2026-W01
            monthKey: `${w.year}-${String(w.month).padStart(2, "0")}`, // année calendaire + mois
            monthName: w.month_name,
            weekLabel: w.week_label,
            dayDates: {
              mon,
              tue,
              wed,
              thu,
              fri,
            },
            dayFlags: {
              mon: { eventsOnDay: [] },
              tue: { eventsOnDay: [] },
              wed: { eventsOnDay: [] },
              thu: { eventsOnDay: [] },
              fri: { eventsOnDay: [] },
            },
          };
        });
      } catch (err) {
        console.error("Erreur chargement calendar weeks scolaire:", err);
        return [];
      }
    }

    reprocessAndRender() {
      this.reprocessEvents();
      this.calculateMonthlyLeaveBalances();
      this.renderTable();
      this.updateGlobalSummary();
      this.renderMonthCalendar();
    }

    reprocessEvents() {
      // Réinitialisation des semaines
      this.weeks.forEach((w) => {
        w.totals = {
          offCarole: 0,
          extraOffCarole: 0,
          centre: 0,
          avis: 0,
          pepSick: 0,
          presencePep: 0,
          // Nouveaux totaux pour Alex / Laia
          alexCP: 0,
          alexJRA: 0,
          alexJA: 0,
          laiaCP: 0,
          laiaJRA: 0,
          laiaJA: 0,
        };
        Object.values(w.dayFlags).forEach((df) => {
          df.eventsOnDay = [];
        });
      });
      // ================== Événements Carole / garde / Pep ==================
      this.events.forEach((evt) => {
        const evtDate = new Date(evt.date + "T00:00:00");
        // Trouver la semaine correspondant à la date de l'événement
        const week = this.weeks.find(
          (w) => evtDate >= w.dayDates.mon && evtDate <= w.dayDates.fri
        );
        if (!week) return;
        // Identifier le jour (mon, tue, wed, thu, fri)
        const dayKey = Object.keys(week.dayDates).find(
          (key) => week.dayDates[key].toDateString() === evtDate.toDateString()
        );
        if (dayKey) {
          week.dayFlags[dayKey].eventsOnDay.push(evt);
        }
        const dur = parseFloat(evt.duration) || 1;
        // Mettre à jour les totaux hebdo selon le type
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
          default:
            break;
        }
      });
      // ================== Congés Alex / Laia (CP / JRA / JA) ==================
      (this.leaves || []).forEach((lv) => {
        const lvDate = new Date(lv.leave_date + "T00:00:00");
        const week = this.weeks.find(
          (w) => lvDate >= w.dayDates.mon && lvDate <= w.dayDates.fri
        );
        if (!week) return;
        const isAlex = lv.person_id === 2;
        const isLaia = lv.person_id === 3;
        const type = lv.leave_type; // "CP", "JRA" ou "JA"
        const dur = parseFloat(lv.duration) || 1;
        if (isAlex) {
          if (type === "CP") week.totals.alexCP += dur;
          if (type === "JRA") week.totals.alexJRA += dur;
          if (type === "JA") week.totals.alexJA += dur;
        } else if (isLaia) {
          if (type === "CP") week.totals.laiaCP += dur;
          if (type === "JRA") week.totals.laiaJRA += dur;
          if (type === "JA") week.totals.laiaJA += dur;
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

    calculateMonthlyLeaveBalances() {
      const usageMonth = {}; // usageMonth[person_id][leave_type][ym] = total days in THAT month
      const pivotDateStr = "2025-09-01";
      const pivotDate = new Date(pivotDateStr + "T00:00:00");

      const getSnapshotRemaining = (personId, leaveType, targetDateStr) => {
        if (!this.leaveSnapshots || !this.leaveSnapshots.length) return null;

        const targetDate = new Date(targetDateStr + "T00:00:00");
        let best = null;

        this.leaveSnapshots.forEach((s) => {
          if (
            parseInt(s.person_id, 10) === personId &&
            s.leave_type === leaveType
          ) {
            const d = new Date(s.snapshot_date + "T00:00:00");
            if (d <= targetDate) {
              if (!best || d > best.date) {
                best = {
                  date: d,
                  remaining: parseFloat(s.remaining_balance) || 0,
                };
              }
            }
          }
        });

        return best ? best.remaining : null;
      };

      // 1. Usage mensuel à partir des leaves (pf_leaves)
      (this.leaves || []).forEach((lv) => {
        const personId = parseInt(lv.person_id, 10);
        const leaveType = lv.leave_type;
        const dateObj = new Date(lv.leave_date + "T00:00:00");
        const year = dateObj.getFullYear();
        const month = dateObj.getMonth() + 1;
        const ymKey = `${year}-${String(month).padStart(2, "0")}`;
        const dur = parseFloat(lv.duration) || 1;

        if (!usageMonth[personId]) usageMonth[personId] = {};
        if (!usageMonth[personId][leaveType])
          usageMonth[personId][leaveType] = {};
        usageMonth[personId][leaveType][ymKey] =
          (usageMonth[personId][leaveType][ymKey] || 0) + dur;
      });

      // 2. Liste des mois présents dans le planning (YYYY-MM)
      const allYmKeys = new Set();
      (this.weeks || []).forEach((w) => {
        const d = w.dayDates.mon; // lundi
        const ymKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(
          2,
          "0"
        )}`;
        allYmKeys.add(ymKey);
      });
      const ymList = Array.from(allYmKeys).sort(); // ex: "2025-09", "2025-10", ...

      const monthlyBalances = {}; // monthlyBalances[person_id][leave_type][ym] = { usedInMonth, availableAtMonthStart }

      // 3. Indexer les balances annuelles (droits)
      // balancesByYear[person_id][leave_type][year] = initial_balance
      const balancesByYear = {};
      (this.leaveBalances || []).forEach((b) => {
        const personId = parseInt(b.person_id, 10);
        const leaveType = b.leave_type;
        const balanceYear = parseInt(b.balance_year, 10);
        const initial = parseFloat(b.initial_balance) || 0;

        if (!balancesByYear[personId]) balancesByYear[personId] = {};
        if (!balancesByYear[personId][leaveType])
          balancesByYear[personId][leaveType] = {};
        balancesByYear[personId][leaveType][balanceYear] = initial;
      });

      const persons = [2, 3]; // Alex, Laia
      const types = ["CP", "JRA", "JA"];

      persons.forEach((personId) => {
        if (!monthlyBalances[personId]) monthlyBalances[personId] = {};

        types.forEach((leaveType) => {
          if (!monthlyBalances[personId][leaveType])
            monthlyBalances[personId][leaveType] = {};

          const personUsageAll = (usageMonth[personId] || {})[leaveType] || {};

          // ===================== CP =====================
          // 25/an, utilisables du 01/08 N au 31/07 N+1.
          // On considère que les 25 sont dispo au 01/08.
          if (leaveType === "CP") {
            ymList.forEach((ym) => {
              const [yStr, mStr] = ym.split("-");
              const year = parseInt(yStr, 10);
              const month = parseInt(mStr, 10);

              // Déterminer l'année scolaire CP (N)
              // Si mois >= 8 -> année scolaire = year
              // Sinon -> année scolaire = year - 1
              const cpSchoolYear = month >= 8 ? year : year - 1;

              const initial =
                ((balancesByYear[personId] || {}).CP || {})[cpSchoolYear] || 0;

              // Période effective de cette "cohorte" CP:
              // [cpSchoolYear-08-01, (cpSchoolYear+1)-07-31]
              const periodStart = `${cpSchoolYear}-08-01`;
              const periodEnd = `${cpSchoolYear + 1}-07-31`;

              // Date du début du mois courant
              const monthStart = `${year}-${String(month).padStart(2, "0")}-01`;

              // usedBeforeMonth = somme des CP sur la période, avant le 1er de ce mois
              let usedBeforeMonth = 0;

              Object.entries(personUsageAll).forEach(([ym2, val]) => {
                // Convertir ym2 -> date 1er du mois
                const [yyStr, mmStr] = ym2.split("-");
                const yy = parseInt(yyStr, 10);
                const mm = parseInt(mmStr, 10);

                // Jour 01 pour comparaison lexicographique
                const ym2MonthStart = `${yy}-${mmStr}-01`;

                // On ne compte que les CP dans la période de validité
                if (
                  ym2MonthStart >= periodStart &&
                  ym2MonthStart < monthStart &&
                  ym2MonthStart <= periodEnd
                ) {
                  usedBeforeMonth += parseFloat(val) || 0;
                }
              });

              const usedInMonth = parseFloat(personUsageAll[ym] || 0);

              let availableAtMonthStart = initial - usedBeforeMonth;
              if (availableAtMonthStart < 0) availableAtMonthStart = 0;

              monthlyBalances[personId][leaveType][ym] = {
                usedInMonth,
                usedBeforeMonth,
                availableAtMonthStart,
              };
            });

            return; // CP géré, on passe au type suivant
          }

          // ===================== JRA (version simple pour affichage) =====================
          // On considère les JRA de l'année Y comme un bloc de initial_balance,
          // utilisable du 01/01/Y au 28/02/Y+1 (tolérance incluse),
          // et on affiche pour chaque mois le solde disponible en fonction de l'usage.
          if (leaveType === "JRA") {
            ymList.forEach((ym) => {
              const [yStr, mStr] = ym.split("-");
              const year = parseInt(yStr, 10);
              const month = parseInt(mStr, 10);

              // Année "source" des JRA: c'est simplement l'année civile
              const jraYear = year;
              const totalYear =
                ((balancesByYear[personId] || {}).JRA || {})[jraYear] || 0;

              // Période de validité: [01/01/jraYear, 28/02/jraYear+1]
              const periodStart = `${jraYear}-01-01`;
              const periodEnd = `${jraYear + 1}-02-28`;

              // Début du mois courant
              const monthStart = `${year}-${String(month).padStart(2, "0")}-01`;

              // usedBeforeMonth: somme des JRA sur la période, avant le 1er de ce mois
              let usedBeforeMonth = 0;

              Object.entries(personUsageAll).forEach(([ym2, val]) => {
                const [yyStr, mmStr] = ym2.split("-");
                const yy = parseInt(yyStr, 10);
                const mm = parseInt(mmStr, 10);
                const ym2MonthStart = `${yy}-${mmStr}-01`;

                if (
                  ym2MonthStart >= periodStart &&
                  ym2MonthStart < monthStart &&
                  ym2MonthStart <= periodEnd
                ) {
                  usedBeforeMonth += parseFloat(val) || 0;
                }
              });

              const usedInMonth = parseFloat(personUsageAll[ym] || 0);

              let availableAtMonthStart = totalYear - usedBeforeMonth;
              if (availableAtMonthStart < 0) availableAtMonthStart = 0;

              monthlyBalances[personId][leaveType][ym] = {
                usedInMonth,
                usedBeforeMonth,
                availableAtMonthStart,
              };
            });

            return; // JRA géré
          }

          // ===================== JA (jours d'ancienneté) =====================
          // Gérés par cycle anniversaire (pf_person_leave_meta).
          if (leaveType === "JA") {
            const anniversaryStr =
              (this.personLeaveMeta && this.personLeaveMeta[personId]) || null;

            ymList.forEach((ym) => {
              const [yStr, mStr] = ym.split("-");
              const year = parseInt(yStr, 10);
              const month = parseInt(mStr, 10);

              const personUsage = personUsageAll;

              // Si pas de date anniversaire connue, fallback sur ancienne logique annuelle
              if (!anniversaryStr) {
                const initial =
                  ((balancesByYear[personId] || {}).JA || {})[year] || 0;

                let usedBeforeMonth = 0;
                Object.entries(personUsage).forEach(([ym2, val]) => {
                  const [y2Str] = ym2.split("-");
                  const y2 = parseInt(y2Str, 10);
                  if (y2 === year && ym2 < ym) {
                    usedBeforeMonth += parseFloat(val) || 0;
                  }
                });

                const usedInMonth = parseFloat(personUsage[ym] || 0);

                let availableAtMonthStart = initial - usedBeforeMonth;
                if (availableAtMonthStart < 0) availableAtMonthStart = 0;

                monthlyBalances[personId][leaveType][ym] = {
                  usedInMonth,
                  usedBeforeMonth,
                  availableAtMonthStart,
                };
                return; // pour ce ym
              }

              // --- Logique cycle anniversaire ---
              // anniversaryStr ex: "2020-04-30"
              const annDate = new Date(anniversaryStr + "T00:00:00");
              const annDay = annDate.getDate(); // 30
              const annMonth = annDate.getMonth(); // 0-11 -> 3 pour Avril

              // Date du mois courant (on prend le 1er pour simplifier)
              const currentMonthStart = new Date(year, month - 1, 1);

              // On calcule le cycleStart / cycleEnd autour de ce currentMonthStart
              // 1) Anniversaire de l'année courante
              const anniversaryThisYear = new Date(year, annMonth, annDay);

              let cycleStart, cycleEnd, cycleYear;

              if (currentMonthStart >= anniversaryThisYear) {
                // Le cycle courant a commencé cette année
                cycleStart = new Date(year, annMonth, annDay + 1); // lendemain
                cycleEnd = new Date(year + 1, annMonth, annDay); // prochain anniv
                cycleYear = year;
              } else {
                // Le cycle courant a commencé l'année précédente
                cycleStart = new Date(year - 1, annMonth, annDay + 1);
                cycleEnd = new Date(year, annMonth, annDay);
                cycleYear = year - 1;
              }

              // On formate ces dates pour comparer avec les ym
              const toIso = (d) =>
                `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(
                  2,
                  "0"
                )}-${String(d.getDate()).padStart(2, "0")}`;

              const periodStart = toIso(cycleStart);
              const periodEnd = toIso(cycleEnd);

              // Début du mois courant
              const monthStartIso = `${year}-${String(month).padStart(
                2,
                "0"
              )}-01`;

              // Initial pour ce cycle: soit pf_leave_balances[cycleYear], soit 4 par défaut
              let initial =
                ((balancesByYear[personId] || {}).JA || {})[cycleYear] || 0;
              if (!initial) {
                initial = 4; // fallback si tu ne remplis pas pf_leave_balances pour JA tous les ans
              }

              // usedBeforeMonth: JA posés sur ce cycle avant le 1er du mois courant
              let usedBeforeMonth = 0;
              Object.entries(personUsage).forEach(([ym2, val]) => {
                // ym2 = "YYYY-MM"
                const [yyStr, mmStr] = ym2.split("-");
                const yy = parseInt(yyStr, 10);
                const mm = parseInt(mmStr, 10);
                const ym2Start = `${yy}-${mmStr}-01`;

                if (
                  ym2Start >= periodStart &&
                  ym2Start < monthStartIso &&
                  ym2Start <= periodEnd
                ) {
                  usedBeforeMonth += parseFloat(val) || 0;
                }
              });

              const usedInMonth = parseFloat(personUsage[ym] || 0);

              let availableAtMonthStart = initial - usedBeforeMonth;
              if (availableAtMonthStart < 0) availableAtMonthStart = 0;

              monthlyBalances[personId][leaveType][ym] = {
                usedInMonth,
                usedBeforeMonth,
                availableAtMonthStart,
              };
            });

            return; // JA géré
          }
        });
      });

      this.monthlyLeaveBalances = monthlyBalances;
    }

    calculateLeaveUsage() {
      // Agrège les jours utilisés par personne et type pour l'année de référence
      const usage = {}; // key: `${person_id}|${leave_type}|${year}`

      (this.leaves || []).forEach((lv) => {
        // lv.leave_date est au format 'YYYY-MM-DD'
        const year = new Date(lv.leave_date + "T00:00:00").getFullYear();
        const key = `${lv.person_id}|${lv.leave_type}|${year}`;
        const dur = parseFloat(lv.duration) || 1;
        usage[key] = (usage[key] || 0) + dur;
      });

      return usage;
    }

    calculateMonthlyLeaveUsage() {
      const usageMonth = {}; // usageMonth[person_id][leave_type][ym] = total days

      (this.leaves || []).forEach((lv) => {
        const personId = parseInt(lv.person_id, 10);
        const leaveType = lv.leave_type;
        const dateObj = new Date(lv.leave_date + "T00:00:00");
        const year = dateObj.getFullYear();
        const month = dateObj.getMonth() + 1;
        const ymKey = `${year}-${String(month).padStart(2, "0")}`;
        const dur = parseFloat(lv.duration) || 1;

        if (!usageMonth[personId]) usageMonth[personId] = {};
        if (!usageMonth[personId][leaveType])
          usageMonth[personId][leaveType] = {};
        usageMonth[personId][leaveType][ymKey] =
          (usageMonth[personId][leaveType][ymKey] || 0) + dur;
      });

      return usageMonth;
    }

    calculateAvailableBalances() {
      const usage = this.calculateLeaveUsage();

      // balances[person_id][leave_type][year] = { initial, used, remaining }
      const balances = {};

      (this.leaveBalances || []).forEach((b) => {
        const personId = parseInt(b.person_id, 10);
        const leaveType = b.leave_type;
        const year = parseInt(b.balance_year, 10);
        const initial = parseFloat(b.initial_balance) || 0;

        const key = `${personId}|${leaveType}|${year}`;
        const used = usage[key] || 0;
        let remaining = initial - used;
        if (remaining < 0) remaining = 0;

        if (!balances[personId]) balances[personId] = {};
        if (!balances[personId][leaveType]) balances[personId][leaveType] = {};
        balances[personId][leaveType][year] = { initial, used, remaining };
      });

      return balances;
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
      // map pour savoir si on a déjà rendu les cellules Alex/Laia pour un mois
      const alexLaiaRowRendered = {};

      const formatTotal = (total) =>
        total > 0 ? (Number.isInteger(total) ? total : total.toFixed(1)) : "";

      this.weeks.forEach((week, index) => {
        const tr = document.createElement("tr");

        // Déterminer si c'est la première ou la dernière semaine du mois
        const isFirstWeekOfMonth =
          index === 0 || this.weeks[index - 1].monthKey !== week.monthKey;
        const isLastWeekOfMonth =
          index === this.weeks.length - 1 ||
          this.weeks[index + 1].monthKey !== week.monthKey;

        if (isFirstWeekOfMonth) {
          tr.classList.add("fc-month-first-week-row");
        }
        if (isLastWeekOfMonth) {
          tr.classList.add("fc-month-last-week-row");
        }

        // Colonne Mois (avec rowSpan)
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

        // ===== Colonnes Alex/Laia Av./Use fusionnées par mois =====

        // Mois de la semaine pour les soldes mensuels
        const weekMonthDate = week.dayDates.mon; // lundi de la semaine
        const ymKey = `${weekMonthDate.getFullYear()}-${String(
          weekMonthDate.getMonth() + 1
        ).padStart(2, "0")}`;

        const monthlyBalances = this.monthlyLeaveBalances || {};

        const computeMonthInfo = (personId, leaveType, ymKey) => {
          const personBal = (monthlyBalances[personId] || {})[leaveType];
          if (!personBal) return null;
          const info = personBal[ymKey];
          if (!info) return null;
          return info;
        };

        const alexCPMonth = computeMonthInfo(2, "CP", ymKey);
        const alexJRAMonth = computeMonthInfo(2, "JRA", ymKey);
        const alexJAMonth = computeMonthInfo(2, "JA", ymKey);

        const laiaCPMonth = computeMonthInfo(3, "CP", ymKey);
        const laiaJRAMonth = computeMonthInfo(3, "JRA", ymKey);
        const laiaJAMonth = computeMonthInfo(3, "JA", ymKey);

        const getMonthVal = (info, field) =>
          info && info[field] != null ? info[field] : 0;

        // On ne rend les colonnes Alex/Laia qu'une fois par mois
        if (!alexLaiaRowRendered[week.monthKey]) {
          alexLaiaRowRendered[week.monthKey] = true;

          const rowSpan = monthSpans[week.monthKey] || 1;

          // 6 colonnes ALEX : [CP Av., CP Use, JRA Av., JRA Use, JA Av., JA Use]
          for (let i = 0; i < 6; i++) {
            const td = document.createElement("td");
            td.classList.add("col-alex-sub");
            td.rowSpan = rowSpan;

            if (i % 2 === 0) {
              // Av.
              td.classList.add("col-alex-av");
              let value = "";
              if (i === 0) {
                value = alexCPMonth
                  ? formatTotal(
                      getMonthVal(alexCPMonth, "availableAtMonthStart")
                    )
                  : "";
              } else if (i === 2) {
                value = alexJRAMonth
                  ? formatTotal(
                      getMonthVal(alexJRAMonth, "availableAtMonthStart")
                    )
                  : "";
              } else if (i === 4) {
                value = alexJAMonth
                  ? formatTotal(
                      getMonthVal(alexJAMonth, "availableAtMonthStart")
                    )
                  : "";
              }
              td.textContent = value;
            } else {
              // Use
              td.classList.add("col-alex-use");
              let value = "";
              if (i === 1) {
                value = alexCPMonth
                  ? formatTotal(getMonthVal(alexCPMonth, "usedInMonth"))
                  : "";
              } else if (i === 3) {
                value = alexJRAMonth
                  ? formatTotal(getMonthVal(alexJRAMonth, "usedInMonth"))
                  : "";
              } else if (i === 5) {
                value = alexJAMonth
                  ? formatTotal(getMonthVal(alexJAMonth, "usedInMonth"))
                  : "";
              }
              td.textContent = value;
            }

            tr.appendChild(td);
          }

          // 6 colonnes LAIA : [CP Av., CP Use, JRA Av., JRA Use, JA Av., JA Use]
          for (let i = 0; i < 6; i++) {
            const td = document.createElement("td");
            td.classList.add("col-laia-sub");
            td.rowSpan = rowSpan;

            if (i % 2 === 0) {
              td.classList.add("col-laia-av");
              let value = "";
              if (i === 0) {
                value = laiaCPMonth
                  ? formatTotal(
                      getMonthVal(laiaCPMonth, "availableAtMonthStart")
                    )
                  : "";
              } else if (i === 2) {
                value = laiaJRAMonth
                  ? formatTotal(
                      getMonthVal(laiaJRAMonth, "availableAtMonthStart")
                    )
                  : "";
              } else if (i === 4) {
                value = laiaJAMonth
                  ? formatTotal(
                      getMonthVal(laiaJAMonth, "availableAtMonthStart")
                    )
                  : "";
              }
              td.textContent = value;
            } else {
              td.classList.add("col-laia-use");
              let value = "";
              if (i === 1) {
                value = laiaCPMonth
                  ? formatTotal(getMonthVal(laiaCPMonth, "usedInMonth"))
                  : "";
              } else if (i === 3) {
                value = laiaJRAMonth
                  ? formatTotal(getMonthVal(laiaJRAMonth, "usedInMonth"))
                  : "";
              } else if (i === 5) {
                value = laiaJAMonth
                  ? formatTotal(getMonthVal(laiaJAMonth, "usedInMonth"))
                  : "";
              }
              td.textContent = value;
            }

            tr.appendChild(td);
          }
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

      // Navigation année scolaire (planning hebdo)
      const prevSchoolYearBtn = document.getElementById("fc-prev-school-year");
      const nextSchoolYearBtn = document.getElementById("fc-next-school-year");
      if (prevSchoolYearBtn) {
        prevSchoolYearBtn.addEventListener("click", () =>
          this.changeSchoolYear(-1)
        );
      }
      if (nextSchoolYearBtn) {
        nextSchoolYearBtn.addEventListener("click", () =>
          this.changeSchoolYear(1)
        );
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

      // ===== Cas 1 seule cellule =====
      if (this.selectedCells.length === 1) {
        const date = this.selectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((ev) => CONGE_TYPES.includes(ev.type));
        const garde = eventsOnDay.find((ev) => GUARDE_TYPES.includes(ev.type));
        const pep = eventsOnDay.find((ev) => PEP_TYPES.includes(ev.type));

        this.showEditMenuForDay(e, { conge, garde, pep, date });
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

      // On construit quand même un bulkInfo partiel (même si pas homogène)
      const bulkInfo = {
        selectedDates,
        conges,
        gardes,
        congeType: allDatesHaveConge ? conges[0].type : null,
        gardeType: allDatesHaveGarde ? gardes[0].type : null,
      };

      // On affiche toujours le menu bulk pour permettre Alex/Laia,
      // et éventuellement les actions Carole/garde si congeType/gardeType sont définis.
      this.showBulkMenu(e, bulkInfo);
    }

    showBulkMenu(e, bulkInfo) {
      const { selectedDates } = bulkInfo;
      const nbDays = selectedDates.length;

      let html = `
    <div class="fc-menu-section">
      <strong>Actions multi-jours</strong>
    </div>
  `;

      // Section Congés Carole
      html += `
  <div class="fc-menu-section">
    <strong>Congés Carole (${nbDays} jours)</strong>
    <div class="fc-menu-grid">
      <button
        class="fc-menu-btn fc-menu-btn--conge"
        data-action="add"
        data-type="OFF_CAROLE"
        data-person="Carole"
      >
        Off Carole
      </button>
      <button
        class="fc-menu-btn fc-menu-btn--conge"
        data-action="add"
        data-type="EXTRA_OFF_CAROLE"
        data-person="Carole"
      >
        Extra Off Carole
      </button>
    </div>
    <button
      class="fc-menu-btn fc-menu-danger"
      data-action="bulk-clear-conge"
    >
      Supprimer tous les congés Carole sur ces jours
    </button>
  </div>
`;

      // Section Mode de garde – toujours visible
      html += `
  <div class="fc-menu-section">
    <strong>Mode de garde (${nbDays} jours)</strong>
    <div class="fc-menu-grid">
      <button
        class="fc-menu-btn fc-menu-btn--garde"
        data-action="add"
        data-type="CENTRE"
      >
        Centre
      </button>
      <button
        class="fc-menu-btn fc-menu-btn--garde"
        data-action="add"
        data-type="AVIS"
      >
        Avis
      </button>
    </div>
    <button
      class="fc-menu-btn fc-menu-danger"
      data-action="bulk-clear-garde"
    >
      Supprimer tous les modes de garde sur ces jours
    </button>
  </div>
`;

      // Section bulk congés Alex / Laia – tableau identique au single
      html += `
  <div class="fc-menu-section">
    <strong>Congés Alex / Laia (${nbDays} jours)</strong>
    <div class="fc-menu-leaves-table">
      <table>
        <thead>
          <tr>
            <th>Alex</th>
            <th>Laia</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="2"
                data-leave-type="CP"
              >
                CP
              </button>
            </td>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="3"
                data-leave-type="CP"
              >
                CP
              </button>
            </td>
          </tr>
          <tr>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="2"
                data-leave-type="JRA"
              >
                JRA
              </button>
            </td>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="3"
                data-leave-type="JRA"
              >
                JRA
              </button>
            </td>
          </tr>
          <tr>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="2"
                data-leave-type="JA"
              >
                JA
              </button>
            </td>
            <td>
              <button
                type="button"
                class="fc-menu-btn fc-menu-leave-btn"
                data-action="bulk-add-leave"
                data-person-id="3"
                data-leave-type="JA"
              >
                JA
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
`;

      // Après le tableau CP/JRA/JA
      html += `
  <button
    class="fc-menu-btn fc-menu-danger"
    data-action="bulk-clear-leave"
    data-person-id="2"
  >
    Supprimer tous les congés Alex sur ces jours
  </button>
  <button
    class="fc-menu-btn fc-menu-danger"
    data-action="bulk-clear-leave"
    data-person-id="3"
  >
    Supprimer tous les congés Laia sur ces jours
  </button>
`;

      this.selectionMenu.innerHTML = html;
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
    <div class="fc-menu-section">
      <strong>Ajouter</strong>
      <div class="fc-menu-grid">
        <button class="fc-menu-btn fc-menu-btn--conge" data-action="add" data-type="OFF_CAROLE" data-person="Carole">
          Off Carole
        </button>
        <button class="fc-menu-btn fc-menu-btn--conge" data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">
          Extra Off Carole
        </button>
      </div>
    </div>

    <div class="fc-menu-section">
      <strong>Mode de garde</strong>
      <div class="fc-menu-grid">
        <button class="fc-menu-btn fc-menu-btn--garde" data-action="add" data-type="CENTRE">
          Centre
        </button>
        <button class="fc-menu-btn fc-menu-btn--garde" data-action="add" data-type="AVIS">
          Avis
        </button>
      </div>
    </div>

    <div class="fc-menu-section">
      <strong>Pep</strong>
      <div class="fc-menu-grid">
        <button class="fc-menu-btn fc-menu-btn--pep" data-action="add" data-type="PEP_SICK">
          Pep malade
        </button>
      </div>
    </div>
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
      <div class="fc-menu-section">
        <strong>Congé Carole</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="update"
            data-event-id="${conge.id}"
            data-new-type="${oppositeConge}"
          >
            Remplacer par<br> ${oppositeConge.replace(/_/g, " ")}
          </button>
          <button
            class="fc-menu-btn fc-menu-danger"
            data-action="delete"
            data-event-id="${conge.id}"
          >
            Supprimer le congé
          </button>
        </div>
      </div>
    `;
      } else {
        congeSection = `
      <div class="fc-menu-section">
        <strong>Ajouter un congé</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="add-single"
            data-type="OFF_CAROLE"
            data-date="${date}"
            data-person="Carole"
          >
            Off Carole
          </button>
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="add-single"
            data-type="EXTRA_OFF_CAROLE"
            data-date="${date}"
            data-person="Carole"
          >
            Extra Off Carole
          </button>
        </div>
      </div>
    `;
      }

      // --- Section mode de garde ---
      let gardeSection = "";
      if (garde) {
        const oppositeGarde = garde.type === "CENTRE" ? "AVIS" : "CENTRE";
        gardeSection = `
      <div class="fc-menu-section">
        <strong>Mode de garde</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="update"
            data-event-id="${garde.id}"
            data-new-type="${oppositeGarde}"
          >
            Remplacer par<br> ${oppositeGarde}
          </button>
          <button
            class="fc-menu-btn fc-menu-danger"
            data-action="delete"
            data-event-id="${garde.id}"
          >
            Supprimer le mode de garde
          </button>
        </div>
      </div>
    `;
      } else {
        gardeSection = `
      <div class="fc-menu-section">
        <strong>Ajouter mode de garde</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="add-single"
            data-type="CENTRE"
            data-date="${date}"
          >
            Centre
          </button>
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="add-single"
            data-type="AVIS"
            data-date="${date}"
          >
            Avis
          </button>
        </div>
      </div>
    `;
      }

      // --- Section Pep malade ---
      let pepSection = "";
      if (pep) {
        pepSection = `
      <div class="fc-menu-section">
        <strong>Pep</strong>
        <button
          class="fc-menu-btn fc-menu-danger"
          data-action="delete"
          data-event-id="${pep.id}"
        >
          Supprimer "Pep malade"
        </button>
      </div>
    `;
      } else {
        pepSection = `
      <div class="fc-menu-section">
        <strong>Pep</strong>
        <button
          class="fc-menu-btn fc-menu-btn--pep"
          data-action="add-single"
          data-type="PEP_SICK"
          data-date="${date}"
        >
          Pep malade
        </button>
      </div>
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
    <div class="fc-menu-section">
      <strong>Congés Alex / Laia</strong>
      <div class="fc-menu-leaves-table">
        <table>
          <thead>
            <tr>
              
              <th>Alex</th>
              <th>Laia</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
            </tr>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
            </tr>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  `;

      // Bouton de suppression si congés existants ce jour-là
      if (leavesOnDay.length > 0) {
        leavesSection += `
      <button
        type="button"
        class="fc-menu-btn fc-menu-danger"
        data-action="delete-leaves-day"
        data-date="${date}"
      >
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
      const wrapper = document.getElementById("planningTable-wrapper");
      if (!wrapper) {
        // fallback sécurité
        this.selectionMenu.style.display = "block";
        this.selectionMenu.style.left = `${e.clientX + 5}px`;
        this.selectionMenu.style.top = `${e.clientY + 5}px`;
        return;
      }

      const rect = wrapper.getBoundingClientRect();

      this.selectionMenu.style.display = "block";

      // Position relative au wrapper (qui scrolle)
      const x = e.clientX - rect.left + wrapper.scrollLeft;
      const y = e.clientY - rect.top + wrapper.scrollTop;

      this.selectionMenu.style.left = `${x + 5}px`;
      this.selectionMenu.style.top = `${y + 5}px`;

      this.menuJustOpened = true;
      setTimeout(() => {
        this.menuJustOpened = false;
      }, 0);
    }

    // ===== Helpers Carole / garde =====

    // Single day – Carole
    async setCaroleSingle(date, type) {
      try {
        // 1) supprimer OFF_CAROLE / EXTRA_OFF_CAROLE sur ce jour
        await this.manageEvent({
          action: "delete_day_types",
          date,
          types: CONGE_TYPES, // ["OFF_CAROLE", "EXTRA_OFF_CAROLE"]
        });

        // 2) ajouter le nouveau congé
        await this.manageEvent({
          action: "add_multiple",
          events: [
            {
              date,
              type,
              person: "Carole",
              duration: 1.0,
            },
          ],
        });
      } catch (err) {
        console.error("setCaroleSingle error:", err);
        alert("Erreur lors de la mise à jour du congé Carole : " + err.message);
      }
    }

    // Single day – Garde
    async setGardeSingle(date, type) {
      try {
        await this.manageEvent({
          action: "delete_day_types",
          date,
          types: GUARDE_TYPES, // ["CENTRE","AVIS"]
        });

        await this.manageEvent({
          action: "add_multiple",
          events: [
            {
              date,
              type,
              duration: 1.0,
            },
          ],
        });
      } catch (err) {
        console.error("setGardeSingle error:", err);
        alert(
          "Erreur lors de la mise à jour du mode de garde : " + err.message
        );
      }
    }

    // Bulk – Carole
    async setCaroleBulk(dates, type) {
      try {
        await this.manageEvent({
          action: "bulk_delete_day_types",
          dates,
          types: CONGE_TYPES,
        });

        await this.manageEvent({
          action: "add_multiple",
          events: dates.map((date) => ({
            date,
            type,
            person: "Carole",
            duration: 1.0,
          })),
        });
      } catch (err) {
        console.error("setCaroleBulk error:", err);
        alert("Erreur bulk congés Carole : " + err.message);
      }
    }

    // Bulk – Garde
    async setGardeBulk(dates, type) {
      try {
        await this.manageEvent({
          action: "bulk_delete_day_types",
          dates,
          types: GUARDE_TYPES,
        });

        await this.manageEvent({
          action: "add_multiple",
          events: dates.map((date) => ({
            date,
            type,
            duration: 1.0,
          })),
        });
      } catch (err) {
        console.error("setGardeBulk error:", err);
        alert("Erreur bulk mode de garde : " + err.message);
      }
    }

    async handleMenuClick(e) {
      const button = e.target.closest("button[data-action]");
      if (!button) return;

      const { action, eventId, newType, type, person, date } = button.dataset;

      // ===================== SINGLE DAY – Carole / Garde / Pep =====================

      // Single day Carole/garde (add-single depuis le menu d’un jour)
      if (action === "add-single") {
        const targetDate = date;

        // Switch congé Carole (OFF_CAROLE / EXTRA_OFF_CAROLE)
        if (CONGE_TYPES.includes(type)) {
          try {
            // Récupérer les events de ce jour
            const existingOnDate = this.dbEvents.filter(
              (evt) => evt.date === targetDate && CONGE_TYPES.includes(evt.type)
            );

            // Supprimer tous les congés Carole ce jour-là
            for (const evt of existingOnDate) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            // Ajouter le nouveau type
            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify([
                  {
                    date: targetDate,
                    type,
                    person: "Carole",
                    duration: 1.0,
                  },
                ]),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
          } catch (err) {
            console.error("Erreur congé Carole:", err);
            alert("Erreur congé Carole : " + err.message);
          }

          this.clearSelection();
          return;
        }

        // Switch mode de garde (CENTRE / AVIS)
        if (GUARDE_TYPES.includes(type)) {
          try {
            const existingOnDate = this.dbEvents.filter(
              (evt) =>
                evt.date === targetDate && GUARDE_TYPES.includes(evt.type)
            );

            // Supprimer tous les modes de garde ce jour-là
            for (const evt of existingOnDate) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            // Ajouter le nouveau type
            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify([
                  {
                    date: targetDate,
                    type,
                    duration: 1.0,
                  },
                ]),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
          } catch (err) {
            console.error("Erreur mode de garde:", err);
            alert("Erreur mode de garde : " + err.message);
          }

          this.clearSelection();
          return;
        }

        // Pep (comportement existant)
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
          if (!response.ok) throw new Error("Erreur HTTP " + response.status);

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error("Erreur ajout Pep:", err);
          alert("Erreur lors de l'ajout.");
        }

        return;
      }

      // ===================== BULK – Carole / Garde / Pep ===========================

      if (action === "add") {
        const selectedDates = this.selectedCells.map((c) => c.dataset.date);

        // Bulk congés Carole
        if (CONGE_TYPES.includes(type)) {
          try {
            // Supprimer tous les OFF/EXTRA_OFF sur ces dates
            const existingOnDates = this.dbEvents.filter(
              (evt) =>
                selectedDates.includes(evt.date) &&
                CONGE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDates) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            // Ajouter le type choisi sur chaque date
            const newEvents = selectedDates.map((d) => ({
              date: d,
              type,
              person: "Carole",
              duration: 1.0,
            }));

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(newEvents),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
          } catch (err) {
            console.error("Erreur bulk congés Carole:", err);
            alert("Erreur bulk congés Carole : " + err.message);
          }

          this.clearSelection();
          return;
        }

        // Bulk mode de garde
        if (GUARDE_TYPES.includes(type)) {
          try {
            const existingOnDates = this.dbEvents.filter(
              (evt) =>
                selectedDates.includes(evt.date) &&
                GUARDE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDates) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            const newEvents = selectedDates.map((d) => ({
              date: d,
              type,
              duration: 1.0,
            }));

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(newEvents),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
          } catch (err) {
            console.error("Erreur bulk mode de garde:", err);
            alert("Erreur bulk mode de garde : " + err.message);
          }

          this.clearSelection();
          return;
        }

        // Bulk Pep (comportement existant)
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
          if (!response.ok) throw new Error("Erreur HTTP " + response.status);

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error("Erreur bulk add:", err);
          alert("Erreur lors de l'ajout.");
        }

        return;
      }

      // ===================== ALEX / LAIA (single / bulk) ==========================

      // Single day leaves Alex/Laia
      if (action === "add-leave") {
        const leaveDate = date;
        const personId = parseInt(button.dataset.personId, 10);
        const leaveType = button.dataset.leaveType; // "CP", "JRA", "JA"

        if (!leaveDate || !personId || !leaveType) {
          this.clearSelection();
          return;
        }

        // Bloquer tous les types de congés (CP, JRA, JA) pour Alex (2) et Laia (3)
        if (personId === 2 || personId === 3) {
          const available = this.getAvailableAtMonthStart(
            personId,
            leaveType,
            leaveDate
          );
          // On pose 1 jour
          if (available != null && available < 1) {
            const disp = available.toFixed(2);
            alert(
              `Impossible d'ajouter ${leaveType} pour ${
                personId === 2 ? "Alex" : "Laia"
              } : il reste ${disp} jour(s) disponible(s) au début de ce mois.`
            );
            this.clearSelection();
            return;
          }
        }

        this.clearSelection();

        try {
          // 1) Supprimer tous les congés Alex/Laia pour cette personne ce jour-là
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "delete_day_person",
              date: leaveDate,
              person_id: personId,
            }),
          });

          // 2) Ajouter le type choisi
          const responseAdd = await fetch(
            "/modules/family-calendar/includes/api/save-leaves.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify([
                {
                  date: leaveDate,
                  person_id: personId,
                  leave_type: leaveType,
                  duration: 1.0,
                },
              ]),
            }
          );
          if (!responseAdd.ok) {
            throw new Error("Erreur HTTP " + responseAdd.status);
          }

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur set leave single:", err);
          alert("Erreur congé Alex/Laia : " + err.message);
        }

        return;
      }

      if (action === "delete-leaves-day") {
        const leaveDate = date;
        if (!leaveDate) {
          this.clearSelection();
          return;
        }

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
          console.error("Erreur delete leaves day:", err);
          alert(
            "Erreur lors de la suppression des congés Alex/Laia pour ce jour: " +
              err.message
          );
        }

        this.clearSelection();
        return;
      }

      // Bulk leaves Alex/Laia via bulk-add-leave
      if (action === "bulk-add-leave") {
        if (!this._currentBulkInfo) {
          this.clearMonthSelection();
          return;
        }

        const selectedDates = this._currentBulkInfo.selectedDates || [];
        const personId = parseInt(button.dataset.personId, 10);
        const leaveType = button.dataset.leaveType; // "CP", "JRA", "JA"

        // Blocage pour Alex / Laia
        if (personId === 2 || personId === 3) {
          // Regrouper les dates par mois
          const datesByMonth = {}; // ymKey -> dates[]
          selectedDates.forEach((d) => {
            const dateObj = new Date(d + "T00:00:00");
            const ymKey = `${dateObj.getFullYear()}-${String(
              dateObj.getMonth() + 1
            ).padStart(2, "0")}`;
            if (!datesByMonth[ymKey]) datesByMonth[ymKey] = [];
            datesByMonth[ymKey].push(d);
          });

          // Pour chaque mois, vérifier Av. >= nb jours demandés dans ce mois
          for (const ymKey of Object.keys(datesByMonth)) {
            const datesInMonth = datesByMonth[ymKey];
            const anyDate = datesInMonth[0];
            const available = this.getAvailableAtMonthStart(
              personId,
              leaveType,
              anyDate
            );
            const needed = datesInMonth.length;

            if (available != null && available < needed) {
              const disp = available.toFixed(2);
              alert(
                `Impossible d'ajouter ${leaveType} pour ${
                  personId === 2 ? "Alex" : "Laia"
                } sur ${needed} jour(s) dans ${ymKey} : il reste ${disp} jour(s) disponible(s).`
              );
              this.clearMonthSelection();
              this._currentBulkInfo = null;
              return;
            }
          }
        }

        this.clearMonthSelection();
        this._currentBulkInfo = null;

        try {
          // 1) supprimer les congés existants pour cette personne sur toutes les dates
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "bulk_delete_day_person",
              dates: selectedDates,
              person_id: personId,
            }),
          });

          // 2) ajouter le nouveau type sur toutes les dates
          const newLeaves = selectedDates.map((leaveDate) => ({
            date: leaveDate,
            person_id: personId,
            leave_type: leaveType,
            duration: 1.0,
          }));

          const responseAdd = await fetch(
            "/modules/family-calendar/includes/api/save-leaves.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(newLeaves),
            }
          );
          if (!responseAdd.ok) {
            throw new Error("Erreur HTTP " + responseAdd.status);
          }

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk-add-leave (month):", err);
          alert("Erreur bulk congés Alex/Laia : " + err.message);
        }

        return;
      }

      // ===================== SUPPRESSION SIMPLE EVENT =============================

      if (action === "delete") {
        if (!eventId) {
          this.clearSelection();
          return;
        }

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
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error("Erreur delete:", err);
          alert("Erreur lors de la suppression : " + err.message);
        }

        this.clearSelection();
        return;
      }

      // ===================== SUPPRESSION BULK LEAVE =============================

      if (action === "bulk-clear-leave") {
        if (!this._currentBulkInfo) {
          this.clearSelection();
          return;
        }
        const selectedDates = this._currentBulkInfo.selectedDates || [];
        const personId = parseInt(button.dataset.personId, 10);

        try {
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "bulk_delete_day_person",
              dates: selectedDates,
              person_id: personId,
            }),
          });

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk-clear-leave:", err);
          alert(
            "Erreur lors de la suppression des congés sur ces jours : " +
              err.message
          );
        }

        this._currentBulkInfo = null;
        this.clearSelection();
        return;
      }

      // ===================== UPDATE SIMPLE (utilisé par les boutons "Remplacer par") =====

      if (action === "update") {
        if (!eventId) {
          this.clearSelection();
          return;
        }

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
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
        } catch (err) {
          console.error("Erreur update:", err);
          alert("Erreur lors de la modification : " + err.message);
        }

        this.clearSelection();
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

      // Si on passe en vue "year", caler currentMonth sur septembre
      if (mode === "year" && this.currentSchoolYearStart != null) {
        this.currentMonth = new Date(this.currentSchoolYearStart, 8, 1);
      }

      this.renderMonthCalendar();
    }

    async navigateMonth(direction) {
      if (this.viewMode === "year") {
        // Navigation par année scolaire via les flèches :
        // on réutilise changeSchoolYear
        await this.changeSchoolYear(direction);
        return;
      }

      // Navigation simple par mois (1 ou 2 mois)
      const year = this.currentMonth.getFullYear();
      const month = this.currentMonth.getMonth() + direction;

      this.currentMonth = new Date(year, month, 1);
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

    updateSchoolYearLabel() {
      const label = document.getElementById("fc-current-school-year-label");
      if (!label || this.currentSchoolYearStart == null) return;
      const start = this.currentSchoolYearStart;
      const end = start + 1;
      label.textContent = `Année scolaire ${start}–${end}`;
    }

    async changeSchoolYear(delta) {
      // delta = -1 ou +1
      this.currentSchoolYearStart = (this.currentSchoolYearStart || 0) + delta;

      // Mettre currentMonth sur septembre de cette nouvelle année pour la vue "year"
      // (pour le mensuel, on garde currentMonth tel quel, sauf si tu passes explicitement en vue year)
      if (this.viewMode === "year") {
        this.currentMonth = new Date(this.currentSchoolYearStart, 8, 1); // septembre
      }

      // Recharger les semaines de cette année scolaire
      this.weeks = await this.fetchWeeksStructureScolaire(
        this.currentSchoolYearStart
      );

      // Mettre à jour données / affichage
      this.reprocessAndRender();
      this.updateSchoolYearLabel();
      this.renderMonthCalendar();
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
        total > 0 ? (Number.isInteger(total) ? total : total.toFixed(2)) : "";

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
        const date = this.monthSelectedCells[0].dataset.date;
        const eventsOnDay = this.events.filter(
          (evt) => evt.date === date && MODIFIABLE_TYPES.includes(evt.type)
        );
        const conge = eventsOnDay.find((e) => CONGE_TYPES.includes(e.type));
        const garde = eventsOnDay.find((e) => GUARDE_TYPES.includes(e.type));
        const pep = eventsOnDay.find((e) => PEP_TYPES.includes(e.type));

        // Toujours ouvrir le menu complet qui inclut Alex/Laia
        this.showMonthEditMenuForDay(e, { conge, garde, pep, date });
        return;
      }

      // ===== Multi-jours : toujours ouvrir le menu bulk =====
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

      const bulkInfo = {
        selectedDates,
        conges,
        gardes,
        congeType: allDatesHaveConge ? conges[0].type : null,
        gardeType: allDatesHaveGarde ? gardes[0].type : null,
      };

      // Peu importe qu'il y ait déjà des congés ou non, on ouvre le bulk
      this.showMonthBulkMenu(e, bulkInfo);
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
    <div class="fc-menu-section">
      <strong>Ajouter</strong>
      <div class="fc-menu-grid">
        <button
          class="fc-menu-btn fc-menu-btn--conge"
          data-action="add"
          data-type="OFF_CAROLE"
          data-person="Carole"
        >
          Off Carole
        </button>
        <button
          class="fc-menu-btn fc-menu-btn--conge"
          data-action="add"
          data-type="EXTRA_OFF_CAROLE"
          data-person="Carole"
        >
          Extra Off Carole
        </button>
      </div>
    </div>

    <div class="fc-menu-section">
      <strong>Mode de garde</strong>
      <div class="fc-menu-grid">
        <button
          class="fc-menu-btn fc-menu-btn--garde"
          data-action="add"
          data-type="CENTRE"
        >
          Centre
        </button>
        <button
          class="fc-menu-btn fc-menu-btn--garde"
          data-action="add"
          data-type="AVIS"
        >
          Avis
        </button>
      </div>
    </div>

    <div class="fc-menu-section">
      <strong>Pep</strong>
      <div class="fc-menu-grid">
        <button
          class="fc-menu-btn fc-menu-btn--pep"
          data-action="add"
          data-type="PEP_SICK"
        >
          Pep malade
        </button>
      </div>
    </div>
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
      <div class="fc-menu-section">
        <strong>Congé Carole</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="update"
            data-event-id="${conge.id}"
            data-new-type="${oppositeConge}"
          >
            Remplacer par<br> ${oppositeConge.replace(/_/g, " ")}
          </button>
          <button
            class="fc-menu-btn fc-menu-danger"
            data-action="delete"
            data-event-id="${conge.id}"
          >
            Supprimer le congé
          </button>
        </div>
      </div>
    `;
      } else {
        congeSection = `
      <div class="fc-menu-section">
        <strong>Ajouter un congé</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="add-single"
            data-type="OFF_CAROLE"
            data-date="${date}"
            data-person="Carole"
          >
            Off Carole
          </button>
          <button
            class="fc-menu-btn fc-menu-btn--conge"
            data-action="add-single"
            data-type="EXTRA_OFF_CAROLE"
            data-date="${date}"
            data-person="Carole"
          >
            Extra Off Carole
          </button>
        </div>
      </div>
    `;
      }

      // --- Section mode de garde ---
      let gardeSection = "";
      if (garde) {
        const oppositeGarde = garde.type === "CENTRE" ? "AVIS" : "CENTRE";
        gardeSection = `
      <div class="fc-menu-section">
        <strong>Mode de garde</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="update"
            data-event-id="${garde.id}"
            data-new-type="${oppositeGarde}"
          >
            Remplacer par<br> ${oppositeGarde}
          </button>
          <button
            class="fc-menu-btn fc-menu-danger"
            data-action="delete"
            data-event-id="${garde.id}"
          >
            Supprimer le mode de garde
          </button>
        </div>
      </div>
    `;
      } else {
        gardeSection = `
      <div class="fc-menu-section">
        <strong>Ajouter mode de garde</strong>
        <div class="fc-menu-grid">
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="add-single"
            data-type="CENTRE"
            data-date="${date}"
          >
            Centre
          </button>
          <button
            class="fc-menu-btn fc-menu-btn--garde"
            data-action="add-single"
            data-type="AVIS"
            data-date="${date}"
          >
            Avis
          </button>
        </div>
      </div>
    `;
      }

      // --- Section Pep malade ---
      let pepSection = "";
      if (pep) {
        pepSection = `
      <div class="fc-menu-section">
        <strong>Pep</strong>
        <button
          class="fc-menu-btn fc-menu-danger"
          data-action="delete"
          data-event-id="${pep.id}"
        >
          Supprimer "Pep malade"
        </button>
      </div>
    `;
      } else {
        pepSection = `
      <div class="fc-menu-section">
        <strong>Pep</strong>
        <button
          class="fc-menu-btn fc-menu-btn--pep"
          data-action="add-single"
          data-type="PEP_SICK"
          data-date="${date}"
        >
          Pep malade
        </button>
      </div>
    `;
      }

      // --- Section congés Alex / Laia ---
      const leavesOnDay = (this.leaves || []).filter(
        (lv) => lv.leave_date === date
      );

      let leavesSection = `
    <div class="fc-menu-section">
      <strong>Congés Alex / Laia</strong>
      <div class="fc-menu-leaves-table">
        <table>
          <thead>
            <tr>
              
              <th>Alex</th>
              <th>Laia</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
            </tr>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
            </tr>
            <tr>
              
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="2"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="add-leave"
                  data-date="${date}"
                  data-person-id="3"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  `;

      if (leavesOnDay.length > 0) {
        leavesSection += `
      <button
        type="button"
        class="fc-menu-btn fc-menu-danger"
        data-action="delete-leaves-day"
        data-date="${date}"
      >
        Supprimer les congés Alex/Laia ce jour-là
      </button>
    `;
      }

      this.monthSelectionMenu.innerHTML =
        congeSection + gardeSection + pepSection + leavesSection;
      this.positionAndShowMonthMenu(e);
    }

    showMonthBulkMenu(e, bulkInfo) {
      if (!this.monthSelectionMenu) return;

      const { selectedDates } = bulkInfo;
      const nbDays = selectedDates.length;

      let html = `
    <div class="fc-menu-section">
      <strong>Actions multi-jours</strong>
    </div>
  `;

      // === Congés Carole (toujours visible) ===
      html += `
    <div class="fc-menu-section">
      <strong>Congés Carole (${nbDays} jours)</strong>
      <div class="fc-menu-grid">
        <button
          class="fc-menu-btn fc-menu-btn--conge"
          data-action="add"
          data-type="OFF_CAROLE"
          data-person="Carole"
        >
          Off Carole
        </button>
        <button
          class="fc-menu-btn fc-menu-btn--conge"
          data-action="add"
          data-type="EXTRA_OFF_CAROLE"
          data-person="Carole"
        >
          Extra Off Carole
        </button>
      </div>
      <button
        class="fc-menu-btn fc-menu-danger"
        data-action="bulk-clear-conge"
      >
        Supprimer tous les congés Carole sur ces jours
      </button>
    </div>
  `;

      // === Mode de garde (toujours visible) ===
      html += `
    <div class="fc-menu-section">
      <strong>Mode de garde (${nbDays} jours)</strong>
      <div class="fc-menu-grid">
        <button
          class="fc-menu-btn fc-menu-btn--garde"
          data-action="add"
          data-type="CENTRE"
        >
          Centre
        </button>
        <button
          class="fc-menu-btn fc-menu-btn--garde"
          data-action="add"
          data-type="AVIS"
        >
          Avis
        </button>
      </div>
      <button
        class="fc-menu-btn fc-menu-danger"
        data-action="bulk-clear-garde"
      >
        Supprimer tous les modes de garde sur ces jours
      </button>
    </div>
  `;

      // === Congés Alex / Laia (tableau identique au single) ===
      html += `
    <div class="fc-menu-section">
      <strong>Congés Alex / Laia (${nbDays} jours)</strong>
      <div class="fc-menu-leaves-table">
        <table>
          <thead>
            <tr>
              <th>Alex</th>
              <th>Laia</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="2"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="3"
                  data-leave-type="CP"
                >
                  CP
                </button>
              </td>
            </tr>
            <tr>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="2"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="3"
                  data-leave-type="JRA"
                >
                  JRA
                </button>
              </td>
            </tr>
            <tr>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="2"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="fc-menu-btn fc-menu-leave-btn"
                  data-action="bulk-add-leave"
                  data-person-id="3"
                  data-leave-type="JA"
                >
                  JA
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <button
        class="fc-menu-btn fc-menu-danger"
        data-action="bulk-clear-leave"
        data-person-id="2"
      >
        Supprimer tous les congés Alex sur ces jours
      </button>
      <button
        class="fc-menu-btn fc-menu-danger"
        data-action="bulk-clear-leave"
        data-person-id="3"
      >
        Supprimer tous les congés Laia sur ces jours
      </button>
    </div>
  `;

      this.monthSelectionMenu.innerHTML = html;
      this._currentBulkInfo = bulkInfo;
      this.positionAndShowMonthMenu(e);
    }

    positionAndShowMonthMenu(e) {
      if (!this.monthSelectionMenu) return;

      // parent direct qui contient le calendrier + le menu
      const wrapper = this.monthSelectionMenu.parentElement; // .fc-calendar-and-summary
      const rect = wrapper.getBoundingClientRect();

      this.monthSelectionMenu.style.display = "block";

      // Position relative au wrapper, comme pour l’hebdo
      this.monthSelectionMenu.style.left = `${e.clientX - rect.left + 5}px`;
      this.monthSelectionMenu.style.top = `${e.clientY - rect.top + 5}px`;

      this.menuJustOpened = true;
      setTimeout(() => {
        this.menuJustOpened = false;
      }, 0);
    }

    async handleMonthMenuClick(e) {
      const button = e.target.closest("button[data-action]");
      if (!button) return;

      const { action, eventId, newType, type, person, date } = button.dataset;

      // === AJOUT MULTI-JOURS (Carole / garde / Pep) ============================
      if (action === "add") {
        const selectedDates = this.monthSelectedCells.map(
          (c) => c.dataset.date
        );

        // Bulk congés Carole
        if (CONGE_TYPES.includes(type)) {
          try {
            const existingOnDates = this.dbEvents.filter(
              (evt) =>
                selectedDates.includes(evt.date) &&
                CONGE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDates) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            const newEvents = selectedDates.map((d) => ({
              date: d,
              type,
              person: "Carole",
              duration: 1.0,
            }));

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(newEvents),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
            this.renderMonthCalendar();
          } catch (err) {
            console.error("Erreur bulk Carole (month):", err);
            alert("Erreur bulk congés Carole : " + err.message);
          }

          this.clearMonthSelection();
          return;
        }

        // Bulk mode de garde
        if (GUARDE_TYPES.includes(type)) {
          try {
            const existingOnDates = this.dbEvents.filter(
              (evt) =>
                selectedDates.includes(evt.date) &&
                GUARDE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDates) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            const newEvents = selectedDates.map((d) => ({
              date: d,
              type,
              duration: 1.0,
            }));

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(newEvents),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
            this.renderMonthCalendar();
          } catch (err) {
            console.error("Erreur bulk garde (month):", err);
            alert("Erreur bulk mode de garde : " + err.message);
          }

          this.clearMonthSelection();
          return;
        }

        // Bulk Pep
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
          if (!response.ok) throw new Error("Erreur HTTP " + response.status);

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk add (month):", err);
          alert("Erreur lors de l'ajout.");
        }
        return;
      }

      // === AJOUT SUR UNE SEULE DATE (Carole / garde / Pep) =====================
      if (action === "add-single") {
        const targetDate = date;

        // Switch congé Carole
        if (CONGE_TYPES.includes(type)) {
          try {
            const existingOnDate = this.dbEvents.filter(
              (evt) => evt.date === targetDate && CONGE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDate) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify([
                  {
                    date: targetDate,
                    type,
                    person: "Carole",
                    duration: 1.0,
                  },
                ]),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
            this.renderMonthCalendar();
          } catch (err) {
            console.error("Erreur Carole (month single):", err);
            alert("Erreur congé Carole : " + err.message);
          }

          this.clearMonthSelection();
          return;
        }

        // Switch mode de garde
        if (GUARDE_TYPES.includes(type)) {
          try {
            const existingOnDate = this.dbEvents.filter(
              (evt) =>
                evt.date === targetDate && GUARDE_TYPES.includes(evt.type)
            );
            for (const evt of existingOnDate) {
              await fetch(
                "/modules/family-calendar/includes/api/manage-event.php",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    action: "delete",
                    event_id: evt.id,
                  }),
                }
              );
            }

            const response = await fetch(
              "/modules/family-calendar/includes/api/save-events.php",
              {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify([
                  {
                    date: targetDate,
                    type,
                    duration: 1.0,
                  },
                ]),
              }
            );
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);

            const resEvents = await fetch(
              "/modules/family-calendar/includes/api/get-events.php"
            );
            const dataEvents = await resEvents.json();
            this.dbEvents = dataEvents.events || [];
            this.events = [...this.dbEvents, ...this.fixedEvents];

            this.reprocessAndRender();
            this.renderMonthCalendar();
          } catch (err) {
            console.error("Erreur garde (month single):", err);
            alert("Erreur mode de garde : " + err.message);
          }

          this.clearMonthSelection();
          return;
        }

        // Pep
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
          if (!response.ok) throw new Error("Erreur HTTP " + response.status);

          const resEvents = await fetch(
            "/modules/family-calendar/includes/api/get-events.php"
          );
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur add (month single):", err);
          alert("Erreur lors de l'ajout.");
        }
        return;
      }

      // === SUPPRESSION SIMPLE (Carole / garde / Pep) ===========================
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
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur delete (month):", err);
          alert("Erreur lors de la suppression : " + err.message);
        }
        return;
      }

      // === SUPPRESSION BULK ALEX / LAIA ===========================
      if (action === "bulk-clear-leave") {
        if (!this._currentBulkInfo) {
          this.clearSelection();
          return;
        }
        const selectedDates = this._currentBulkInfo.selectedDates || [];
        const personId = parseInt(button.dataset.personId, 10);

        try {
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "bulk_delete_day_person",
              dates: selectedDates,
              person_id: personId,
            }),
          });

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk-clear-leave:", err);
          alert(
            "Erreur lors de la suppression des congés sur ces jours : " +
              err.message
          );
        }

        this._currentBulkInfo = null;
        this.clearSelection();
        return;
      }

      // === MODIFICATION SIMPLE ===================================================
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
          const dataEvents = await resEvents.json();
          this.dbEvents = dataEvents.events || [];
          this.events = [...this.dbEvents, ...this.fixedEvents];

          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur update (month):", err);
          alert("Erreur lors de la modification : " + err.message);
        }
        return;
      }

      // === ALEX / LAIA (logique identique à handleMenuClick) ===================

      if (action === "add-leave") {
        const leaveDate = date;
        const personId = parseInt(button.dataset.personId, 10);
        const leaveType = button.dataset.leaveType; // "CP", "JRA", "JA"

        if (!leaveDate || !personId || !leaveType) {
          this.clearMonthSelection();
          return;
        }

        // Bloquer tous les types de congés (CP, JRA, JA) pour Alex (2) et Laia (3)
        if (personId === 2 || personId === 3) {
          const available = this.getAvailableAtMonthStart(
            personId,
            leaveType,
            leaveDate
          );
          // On pose 1 jour
          if (available != null && available < 1) {
            const disp = available.toFixed(2);
            alert(
              `Impossible d'ajouter ${leaveType} pour ${
                personId === 2 ? "Alex" : "Laia"
              } : il reste ${disp} jour(s) disponible(s) au début de ce mois.`
            );
            this.clearMonthSelection();
            return;
          }
        }

        this.clearMonthSelection();

        try {
          // 1) supprimer les congés existants pour cette personne ce jour-là
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "delete_day_person",
              date: leaveDate,
              person_id: personId,
            }),
          });

          // 2) ajouter le nouveau type
          const newLeave = {
            date: leaveDate,
            person_id: personId,
            leave_type: leaveType,
            duration: 1.0,
          };

          const responseAdd = await fetch(
            "/modules/family-calendar/includes/api/save-leaves.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify([newLeave]),
            }
          );
          if (!responseAdd.ok) {
            throw new Error("Erreur HTTP " + responseAdd.status);
          }

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur add-leave (month):", err);
          alert("Erreur congé Alex/Laia : " + err.message);
        }

        return;
      }

      if (action === "delete-leaves-day") {
        const leaveDate = date;
        if (!leaveDate) {
          this.clearMonthSelection();
          return;
        }

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
          console.error("Erreur delete_leaves_day (month):", err);
          alert(
            "Erreur lors de la suppression des congés Alex/Laia pour ce jour: " +
              err.message
          );
        }

        this.clearMonthSelection();
        return;
      }

      if (action === "bulk-add-leave") {
        if (!this._currentBulkInfo) {
          this.clearMonthSelection();
          return;
        }

        const selectedDates = this._currentBulkInfo.selectedDates || [];
        const personId = parseInt(button.dataset.personId, 10);
        const leaveType = button.dataset.leaveType; // "CP", "JRA", "JA"

        if (personId === 2 || personId === 3) {
          // Regrouper les dates par mois
          const datesByMonth = {}; // ymKey -> dates[]
          selectedDates.forEach((d) => {
            const dateObj = new Date(d + "T00:00:00");
            const ymKey = `${dateObj.getFullYear()}-${String(
              dateObj.getMonth() + 1
            ).padStart(2, "0")}`;
            if (!datesByMonth[ymKey]) datesByMonth[ymKey] = [];
            datesByMonth[ymKey].push(d);
          });

          // Pour chaque mois, vérifier Av. >= nb jours demandés dans ce mois
          for (const ymKey of Object.keys(datesByMonth)) {
            const datesInMonth = datesByMonth[ymKey];
            const anyDate = datesInMonth[0];
            const available = this.getAvailableAtMonthStart(
              personId,
              leaveType,
              anyDate
            );
            const needed = datesInMonth.length;

            if (available != null && available < needed) {
              const disp = available.toFixed(2);
              alert(
                `Impossible d'ajouter ${leaveType} pour ${
                  personId === 2 ? "Alex" : "Laia"
                } sur ${needed} jour(s) dans ${ymKey} : il reste ${disp} jour(s) disponible(s).`
              );
              this.clearMonthSelection();
              this._currentBulkInfo = null;
              return;
            }
          }
        }

        this._currentBulkInfo = null;

        try {
          // delete & add (ton code existant)
          await fetch("/modules/family-calendar/includes/api/manage-leaf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "bulk_delete_day_person",
              dates: selectedDates,
              person_id: personId,
            }),
          });

          const newLeaves = selectedDates.map((d) => ({
            date: d,
            person_id: personId,
            leave_type: leaveType,
            duration: 1.0,
          }));

          const responseAdd = await fetch(
            "/modules/family-calendar/includes/api/save-leaves.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(newLeaves),
            }
          );
          if (!responseAdd.ok)
            throw new Error("Erreur HTTP " + responseAdd.status);

          this.leaves = await this.fetchLeaves();
          this.reprocessAndRender();
          this.renderMonthCalendar();
        } catch (err) {
          console.error("Erreur bulk-add-leave:", err);
          alert("Erreur bulk congés Alex/Laia : " + err.message);
        }

        this.clearMonthSelection();
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
