/**
 * PachaFamily - Test Engine 🦙
 * Moteur de tests automatisés (E2E) - Version Anti-Flakiness & QA Exhaustive
 */

const PachaTestEngine = {
  arena: document.getElementById("test-arena"),
  reportBox: document.getElementById("test-report"),
  doc: null,

  // ==========================================
  // 🛠️ HELPERS (BLINDÉS POUR LE QA)
  // ==========================================

  wait: (ms) => new Promise((r) => setTimeout(r, ms)),

  log: function (msg, icon = "ℹ️") {
    this.reportBox.value += `[${new Date().toLocaleTimeString()}] ${icon} ${msg}\n`;
    this.reportBox.scrollTop = this.reportBox.scrollHeight;
  },

  assert: function (cond, msgPass, msgFail) {
    this.log(cond ? msgPass : msgFail || msgPass, cond ? "✅" : "❌");
  },

  // 📡 Intercepte les erreurs de l'application dans l'iframe pour les afficher dans le rapport
  injectIframeErrorCatcher: function () {
    if (!this.arena.contentWindow) return;

    this.arena.contentWindow.onerror = (msg, url, line) => {
      this.log(`Erreur App (Ligne ${line}): ${msg}`, "🔥");
    };

    const origError = this.arena.contentWindow.console.error;
    this.arena.contentWindow.console.error = (...args) => {
      this.log(`Console Error: ${args.join(" ")}`, "🔥");
      origError.apply(this.arena.contentWindow.console, args);
    };
  },

  // 🛡️ Attend le rechargement de la page de manière stricte
  actionAndWaitForReload: async function (actionFn, timeout = 5000) {
    return new Promise((resolve, reject) => {
      let reloaded = false;

      this.arena.onload = () => {
        reloaded = true;
        this.doc = this.arena.contentWindow.document;
        this.arena.contentWindow.confirm = () => true;
        this.arena.contentWindow.alert = () => true;
        this.injectIframeErrorCatcher();
        resolve();
      };

      // Exécute l'action et gère le rejet si elle plante
      Promise.resolve(actionFn()).catch((err) => reject(err));

      setTimeout(() => {
        if (!reloaded) {
          this.log(
            "⚠️ Le rechargement n'a pas eu lieu. Suite du test...",
            "⏱️",
          );
          resolve();
        }
      }, timeout);
    });
  },

  load: async function (url) {
    this.log(`Chargement de la page : ${url}`, "🔄");
    return new Promise((resolve) => {
      this.arena.onload = () => {
        this.doc = this.arena.contentWindow.document;
        this.arena.contentWindow.confirm = () => true;
        this.arena.contentWindow.alert = () => true;
        this.injectIframeErrorCatcher();
        resolve();
      };
      this.arena.src = url;
    });
  },

  get: function (sel) {
    return this.doc.querySelector(sel);
  },

  // 🖱️ Clic strict : Si l'élément n'existe pas, on DÉCLENCHE UNE ERREUR FATALE
  click: async function (sel, delay = 500) {
    const el = typeof sel === "string" ? this.get(sel) : sel;
    if (!el) {
      throw new Error(
        `Clic impossible : l'élément '${typeof sel === "string" ? sel : "DOM Object"}' est introuvable.`,
      );
    }

    this.arena.contentWindow.confirm = () => true;
    this.arena.contentWindow.alert = () => true;
    el.click();
    await this.wait(delay);
    return true;
  },

  fill: function (sel, val) {
    const el = this.get(sel);
    if (!el) throw new Error(`Remplissage impossible : '${sel}' introuvable.`);
    el.value = val;
  },

  select: async function (sel, val, delay = 300) {
    const el = this.get(sel);
    if (!el) throw new Error(`Sélection impossible : '${sel}' introuvable.`);
    el.value = val;
    el.dispatchEvent(new Event("change"));
    await this.wait(delay);
  },

  findInTable: function (text) {
    return Array.from(this.doc.querySelectorAll("td")).find((el) =>
      el.textContent.includes(text),
    );
  },

  // ==========================================
  // 🧪 SCÉNARIO EXHAUSTIF : SUIVI MENSUEL (MODALES & CRUD)
  // ==========================================
  testBudgetSuiviExhaustive: async function () {
    this.reportBox.value = "";
    this.log("=== 🚀 DÉBUT EXHAUSTIF : SUIVI MENSUEL ===", "INFO");

    await this.load("budget.php?tab=suivi");
    await this.wait(1000);

    // --- 1. GESTION DE L'ÉTAT (CLÔTURE / RÉOUVERTURE) ---
    this.log("🔍 Test 1 : État du mois (Clôture / Réouverture)");
    let btnReopenInitial = this.get(
      'form input[value="reopen_month"] ~ button[type="submit"]',
    );
    if (btnReopenInitial) {
      this.log(
        "🔒 Mois actuellement fermé. Réouverture forcée pour les tests...",
        "WARN",
      );
      await this.actionAndWaitForReload(
        async () => await this.click(btnReopenInitial),
      );
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP
      this.assert(
        this.get('form input[value="close_month"]') !== null,
        "Mois rouvert.",
      );
    }

    // --- 2. UI TOGGLES ---
    this.log("🔍 Test 2 : Toggle 'Charges à venir'");
    const toggleBtn = this.get(
      "button[onclick*=\"toggleDiv('pendingDetailsList')\"]",
    );
    if (toggleBtn) {
      await this.click(toggleBtn);
      const pendingList = this.get("#pendingDetailsList");
      this.assert(
        pendingList && pendingList.style.display !== "none",
        "Liste des charges déployée.",
      );
    }

    // --- 3. MODALE CSV ---
    this.log("🔍 Test 3 : Modale Import CSV");
    const btnCsv = this.get('button[onclick*="importCsvModal"]');
    if (btnCsv) {
      await this.click(btnCsv);
      const csvModal = this.get("#importCsvModal");
      this.assert(
        csvModal &&
          (csvModal.classList.contains("is-open") ||
            csvModal.style.display === "flex"),
        "Modale CSV ouverte.",
      );

      // Fermeture
      const closeCsvBtn = csvModal.querySelector(
        ".pf-modal-close, button[onclick*='closeSuiviModal']",
      );
      if (closeCsvBtn) await this.click(closeCsvBtn);
      this.assert(
        !csvModal.classList.contains("is-open") &&
          csvModal.style.display !== "flex",
        "Modale CSV fermée.",
      );
    }

    // --- 4. MODALE SNAPSHOT (UPDATE BALANCE) ---
    this.log("🔍 Test 4 : Modale Snapshot (Solde Bancaire)");
    const btnSnapshot = this.get('button[onclick*="snapshotModal"]');
    if (btnSnapshot) {
      await this.click(btnSnapshot);
      this.assert(this.get("#snapshotModal"), "Modale Snapshot ouverte.");

      this.fill('#snapshotModal input[name="snapshot_amount"]', "1337.00");
      this.log("Soumission du nouveau solde...", "INFO");
      await this.actionAndWaitForReload(async () => {
        await this.click('#snapshotModal button[type="submit"]');
      });

      const pageText = this.doc.body.innerText;
      this.assert(
        pageText.includes("1 337"),
        "Solde mis à jour et formaté à 1 337 €.",
      );
    }

    // --- 5. CRUD DÉPENSE (AJOUT / ÉDITION / SUPPRESSION) ---
    this.log("🔍 Test 5 : CRUD Dépense Manuelle");
    const btnAdd = this.get(".btn-add-item");

    if (btnAdd) {
      // 5A. AJOUT
      this.log("Création d'une nouvelle dépense...");
      await this.click(btnAdd);
      await this.select("#modalCatSelect", "Autres");
      this.fill("#modalAmount", "77.77");
      this.fill("#modalLabelInput", "E2E_QA_TEST_EXPENSE");

      await this.actionAndWaitForReload(async () => {
        await this.click('#manualExpenseModal button[type="submit"]');
      });

      let newExpense = this.findInTable("E2E_QA_TEST_EXPENSE");
      this.assert(
        newExpense !== undefined,
        "Dépense 'E2E_QA_TEST_EXPENSE' trouvée dans le DOM.",
      );

      if (newExpense) {
        // 5B. ÉDITION
        this.log("✏️ Édition de la dépense...");
        const row = newExpense.closest("tr");

        // 🛡️ NOUVEAU : Sécurité anti-crash
        if (!row) {
          throw new Error(
            "L'élément trouvé n'est pas dans une ligne de tableau (<tr> manquant). Le test est corrompu.",
          );
        }

        const btnEdit = row.querySelector('button[onclick*="openEditModal"]');
        if (btnEdit) {
          await this.click(btnEdit);
          this.fill("#modalAmount", "88.88");
          await this.actionAndWaitForReload(async () => {
            await this.click('#manualExpenseModal button[type="submit"]');
          });

          const editedExpense = this.findInTable("E2E_QA_TEST_EXPENSE");
          this.assert(
            editedExpense &&
              editedExpense.closest("tr").innerHTML.includes("88"),
            "Montant mis à jour à 88.88€.",
          );
        }

        // 5C. SUPPRESSION (Gère pachaConfirm custom modal)
        this.log("🧹 Teardown : Suppression de la dépense...");
        const rowToDel = this.findInTable("E2E_QA_TEST_EXPENSE").closest("tr");
        const btnDel = rowToDel.querySelector("button.delete");

        if (btnDel) {
          await this.actionAndWaitForReload(async () => {
            await this.click(btnDel); // Déclenche pachaConfirm
            await this.wait(500); // Attend l'animation de la modale custom

            const confirmBtn = this.get("#confirm-ok");
            if (confirmBtn) {
              await this.click(confirmBtn); // Valide la suppression (fetch AJAX)
            }
          }, 3000);

          const checkGone = this.findInTable("E2E_QA_TEST_EXPENSE");
          this.assert(
            checkGone === undefined,
            "Base de données propre ! Dépense supprimée.",
          );
        }
      }
    } else {
      this.log("Bouton '.btn-add-item' introuvable.", "❌");
    }

    // --- 6. CLÔTURE ET RÉOUVERTURE DU MOIS (TEARDOWN STATE) ---
    this.log("🔍 Test 6 : Clôture du mois");
    let btnClose = this.get(
      'form input[value="close_month"] ~ button[type="submit"]',
    );
    if (btnClose) {
      this.log("Verrouillage du mois...");
      await this.actionAndWaitForReload(async () => await this.click(btnClose));
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP (Mois suivant)

      this.log("Retour au mois précédent pour vérification...");
      await this.actionAndWaitForReload(
        async () =>
          await this.click(".suivi-nav-group a.suivi-btn-nav:first-child"),
      );

      this.assert(
        this.get('form input[value="reopen_month"]') !== null,
        "Mois clôturé avec succès.",
      );

      // Teardown
      this.log("🧹 Teardown : Restauration du mois (Réouverture)");
      await this.actionAndWaitForReload(
        async () =>
          await this.click(
            'form input[value="reopen_month"] ~ button[type="submit"]',
          ),
      );
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP

      this.assert(
        this.get('form input[value="close_month"]') !== null,
        "Mois rouvert avec succès.",
      );
    }

    this.log("=== 🏁 FIN DU SCÉNARIO EXHAUSTIF ===", "INFO");
  },

  // ==========================================
  // 🧪 SCÉNARIO 2 : BUDGET PRÉVISIONNEL
  // ==========================================
  testBudgetPrev: async function () {
    this.reportBox.value = "";
    this.log("=== 🚀 DÉBUT DU SCÉNARIO : BUDGET PRÉVISIONNEL ===", "INFO");

    await this.load("budget.php?tab=budget_prev");
    await this.wait(1000);

    this.log("🔍 Étape 1 : Test du mode Somme flottant...", "INFO");
    await this.click("#fabSumMode");
    this.assert(
      this.doc.body.classList.contains("sum-mode-active"),
      "Le mode somme s'active.",
    );

    const firstSalaryInput = this.get('input[data-field="salary"]');
    if (firstSalaryInput) {
      await this.click(firstSalaryInput, 500);
      const sumValue = this.get("#sumResultValue").innerText;
      this.assert(
        sumValue !== "0,00 €" && sumValue !== "0 €",
        `Le total interactif affiche : ${sumValue}`,
      );
    }

    await this.click(".pf-sum-close");
    this.assert(
      !this.doc.body.classList.contains("sum-mode-active"),
      "Le mode somme se désactive.",
    );

    this.log("🔍 Étape 2 : Test de sauvegarde de la note...", "INFO");
    const noteArea = this.get("#monthNoteArea");
    if (noteArea) {
      const oldNote = noteArea.value;
      this.fill("#monthNoteArea", "🤖 Test Auto");
      await this.click('button[onclick^="saveGenericNote"]', 1500);

      const indicator = this.get("#note-save-indicator");
      this.assert(indicator !== null, "Indicateur de sauvegarde affiché.");

      this.fill("#monthNoteArea", oldNote);
      await this.click('button[onclick^="saveGenericNote"]', 500);
    }

    const uniqueCatName = "TEST_CAT_" + Math.floor(Math.random() * 10000);
    this.log(
      `🔍 Étape 3 : Création de la catégorie '${uniqueCatName}'...`,
      "INFO",
    );

    await this.click('button[onclick*="addCatModal"]');
    this.fill('#addCatModal input[name="name"]', uniqueCatName);
    await this.select('#addCatModal select[name="target"]', "vers commune");

    await this.actionAndWaitForReload(async () => {
      await this.click('#addCatModal button[type="submit"]');
    });

    const newCatCell = Array.from(
      this.doc.querySelectorAll(".prev-alloc-table td, .prev-alloc-table div"),
    ).find((el) => el.textContent.includes(uniqueCatName));
    this.assert(
      newCatCell !== undefined,
      `SUCCÈS : '${uniqueCatName}' insérée !`,
    );

    if (newCatCell) {
      this.log("🧹 Étape 4 : Nettoyage de la BDD...", "WARN");
      const row = newCatCell.closest("tr");
      const delBtn = row.querySelector(".delete");

      if (delBtn) {
        await this.actionAndWaitForReload(async () => {
          await this.click(delBtn);
        });

        const checkGone = Array.from(
          this.doc.querySelectorAll(".prev-alloc-table td"),
        ).find((td) => td.textContent.includes(uniqueCatName));
        this.assert(
          checkGone === undefined,
          "NETTOYAGE PARFAIT : Ligne effacée.",
        );
      }
    }

    this.log("=== 🏁 FIN DU SCÉNARIO ===", "INFO");
  },
};

// ==========================================
// ROUTEUR DU LABORATOIRE ET BOUTON COPIER
// ==========================================
document.getElementById("btn-run-test")?.addEventListener("click", async () => {
  const selectedTest = document.getElementById("test-selector").value;
  const btnRun = document.getElementById("btn-run-test");

  btnRun.disabled = true;
  btnRun.style.opacity = "0.5";

  try {
    switch (selectedTest) {
      case "budget_suivi_exhaustive":
        await PachaTestEngine.testBudgetSuiviExhaustive();
        break;
      case "budget_prev":
        await PachaTestEngine.testBudgetPrev();
        break;
      default:
        PachaTestEngine.log(`Scénario non implémenté : ${selectedTest}`, "⚠️");
    }
  } catch (error) {
    PachaTestEngine.log(`Erreur JS critique : ${error.message}`, "❌");
    console.error(error);
  }

  btnRun.disabled = false;
  btnRun.style.opacity = "1";
});

// 📋 Bouton "Copier"
document
  .getElementById("btn-copy-report")
  ?.addEventListener("click", async () => {
    const textArea = document.getElementById("test-report");
    const reportText = textArea.value;
    const successMsg = window.I18N
      ? window.I18N.tests_report_copied || "Rapport copié !"
      : "Rapport copié !";

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(reportText);
        alert(successMsg);
      } else {
        textArea.select();
        document.execCommand("copy");
        window.getSelection().removeAllRanges();
        alert(successMsg);
      }
    } catch (err) {
      alert("Erreur lors de la copie du rapport.");
      console.error(err);
    }
  });
