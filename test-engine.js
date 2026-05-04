/**
 * PachaFamily - Test Engine 🦙
 * Moteur de tests automatisés (E2E) - Version Anti-Flakiness
 */

const PachaTestEngine = {
  arena: document.getElementById("test-arena"),
  reportBox: document.getElementById("test-report"),
  doc: null,

  // ==========================================
  // 🛠️ HELPERS
  // ==========================================

  wait: (ms) => new Promise((r) => setTimeout(r, ms)),

  log: function (msg, icon = "ℹ️") {
    this.reportBox.value += `[${new Date().toLocaleTimeString()}] ${icon} ${msg}\n`;
    this.reportBox.scrollTop = this.reportBox.scrollHeight;
  },

  assert: function (cond, msgPass, msgFail) {
    this.log(cond ? msgPass : msgFail || msgPass, cond ? "✅" : "❌");
  },

  // 🛡️ NOUVEAU : Attend le rechargement de la page après une action
  actionAndWaitForReload: async function (actionFn, timeout = 5000) {
    return new Promise(async (resolve) => {
      let reloaded = false;
      // On écoute le prochain rechargement de l'iframe
      this.arena.onload = () => {
        reloaded = true;
        this.doc = this.arena.contentWindow.document;
        this.arena.contentWindow.confirm = () => true;
        this.arena.contentWindow.alert = () => true;
        resolve();
      };

      await actionFn(); // On lance le clic ou la soumission

      // Sécurité anti-blocage (si le fetch échoue et ne recharge pas la page)
      setTimeout(() => {
        if (!reloaded) {
          this.log(
            "⚠️ Le rechargement de la page n'a pas eu lieu dans le temps imparti.",
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
        resolve();
      };
      this.arena.src = url;
    });
  },

  get: function (sel) {
    return this.doc.querySelector(sel);
  },

  // 🖱️ Clic unique et infaillible
  click: async function (sel, delay = 500) {
    const el = typeof sel === "string" ? this.get(sel) : sel;
    if (el) {
      // 1. Coupe les popups bloquantes
      this.arena.contentWindow.confirm = () => true;
      this.arena.contentWindow.alert = () => true;

      // 2. Un seul et unique clic !
      el.click();

      await this.wait(delay);
      return true;
    }
    return false;
  },

  fill: function (sel, val) {
    const el = this.get(sel);
    if (el) el.value = val;
  },

  select: async function (sel, val, delay = 300) {
    const el = this.get(sel);
    if (el) {
      el.value = val;
      el.dispatchEvent(new Event("change"));
      await this.wait(delay);
    }
  },

  findInTable: function (text) {
    return Array.from(this.doc.querySelectorAll("td, div")).find((el) =>
      el.textContent.includes(text),
    );
  },

  // ==========================================
  // 🧪 SCÉNARIO 1 : SUIVI MENSUEL
  // ==========================================

  runBudgetTests: async function () {
    this.reportBox.value = "";
    this.log("=== 🚀 DÉBUT DU SCÉNARIO : SUIVI BUDGET ===", "INFO");

    await this.load("budget.php?tab=suivi");
    await this.wait(1000);

    if (!this.get(".btn-add-item")) {
      this.log("Mois clôturé, test annulé. Déverrouille-le !", "⚠️");
      return;
    }

    await this.click("button[onclick=\"toggleDiv('pendingDetailsList')\"]");
    this.assert(
      this.get("#pendingDetailsList").style.display !== "none",
      "Déploiement : Charges à venir",
    );

    await this.click(".btn-add-item");
    await this.select("#modalCatSelect", "Autres");
    this.fill("#modalAmount", "42.42");
    this.fill("#modalLabelInput", "TEST_AUTO_PACHA");

    this.log("Enregistrement de la dépense...", "WARN");
    await this.actionAndWaitForReload(async () => {
      await this.click('#manualExpenseModal button[type="submit"]');
    });

    let targetTd = Array.from(this.doc.querySelectorAll("td")).find((el) =>
      el.textContent.includes("TEST_AUTO_PACHA"),
    );
    this.assert(
      targetTd !== undefined,
      "Dépense créée avec succès (42.42€).",
      "Échec : Dépense introuvable.",
    );

    if (targetTd) {
      this.log("Nettoyage de la base de données...", "WARN");
      await this.actionAndWaitForReload(async () => {
        await this.click(
          targetTd.closest("tr").querySelector('a[href*="delete_expense"]'),
        );
      });
      const checkGone = Array.from(this.doc.querySelectorAll("td")).find((el) =>
        el.textContent.includes("TEST_AUTO_PACHA"),
      );
      this.assert(
        checkGone === undefined,
        "Base de données nettoyée avec succès.",
      );
    }

    this.log("=== 🏁 FIN DU SCÉNARIO ===", "INFO");
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
      "Le bouton somme ne répond pas.",
    );

    const firstSalaryInput = this.get('input[data-field="salary"]');
    if (firstSalaryInput) {
      await this.click(firstSalaryInput, 500);
      this.assert(
        firstSalaryInput.classList.contains("sum-selected"),
        "La cellule est bien sélectionnée.",
      );
      const sumValue = this.get("#sumResultValue").innerText;
      this.assert(
        sumValue !== "0,00 €" && sumValue !== "0 €",
        `Le total interactif affiche : ${sumValue}`,
        "Le calcul de la somme a échoué.",
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
      this.assert(
        indicator !== null,
        "Sauvegarde asynchrone exécutée.",
        "L'indicateur de sauvegarde ne s'est pas affiché.",
      );

      this.fill("#monthNoteArea", oldNote);
      await this.click('button[onclick^="saveGenericNote"]', 500);
    }

    const uniqueCatName = "TEST_CAT_" + Math.floor(Math.random() * 10000);
    this.log(
      `🔍 Étape 3 : Création de la catégorie '${uniqueCatName}'...`,
      "INFO",
    );

    await this.click('button[onclick*="addCatModal"]');
    this.assert(
      this.get("#addCatModal").style.display === "flex",
      "Ouverture de la modale d'ajout.",
    );

    this.fill('#addCatModal input[name="name"]', uniqueCatName);
    await this.select('#addCatModal select[name="target"]', "vers commune");

    this.log("Attente du rechargement après création...", "WARN");
    // 🔄 UTILISATION DU NOUVEAU HELPER SYNCHRONE
    await this.actionAndWaitForReload(async () => {
      await this.click('#addCatModal button[type="submit"]');
    });

    const newCatCell = Array.from(
      this.doc.querySelectorAll(".prev-alloc-table td, .prev-alloc-table div"),
    ).find((el) => el.textContent.includes(uniqueCatName));
    this.assert(
      newCatCell !== undefined,
      `SUCCÈS : '${uniqueCatName}' insérée au tableau !`,
      "ÉCHEC : Ligne introuvable après rechargement.",
    );

    if (newCatCell) {
      this.log("🧹 Étape 4 : Nettoyage de la BDD...", "WARN");
      const row = newCatCell.closest("tr");
      const delBtn = row.querySelector(".delete");

      if (delBtn) {
        // 🔄 UTILISATION DU NOUVEAU HELPER SYNCHRONE
        await this.actionAndWaitForReload(async () => {
          await this.click(delBtn);
        });

        const checkGone = Array.from(
          this.doc.querySelectorAll(".prev-alloc-table td"),
        ).find((td) => td.textContent.includes(uniqueCatName));
        this.assert(
          checkGone === undefined,
          "NETTOYAGE PARFAIT : Ligne effacée.",
          "La ligne est restée dans le tableau.",
        );
      } else {
        this.log("Bouton de suppression (.delete) introuvable.", "FAIL");
      }
    }

    this.log("=== 🏁 FIN DU SCÉNARIO ===", "INFO");
  },
};

// ============================================================================
// ROUTEUR DU LABORATOIRE ET BOUTON COPIER
// ============================================================================

document.getElementById("btn-run-test")?.addEventListener("click", async () => {
  const selectedTest = document.getElementById("test-selector").value;
  const btnRun = document.getElementById("btn-run-test");

  btnRun.disabled = true;
  btnRun.style.opacity = "0.5";

  try {
    switch (selectedTest) {
      case "budget_suivi":
        await PachaTestEngine.runBudgetTests();
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
