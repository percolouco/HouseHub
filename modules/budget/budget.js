/**
 * Change l'état "payé/attente" d'un frais sans recharger la page
 */
function toggleCheck(id, isChecked) {
  const formData = new FormData();
  formData.append("action", "toggle-check");
  formData.append("id", id);
  formData.append("status", isChecked ? 1 : 0);

  fetch("/modules/budget/includes/api/manage-item.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) throw new Error("Erreur réseau");
      // Optionnel : on pourrait actualiser un petit label ici
      console.log("Statut mis à jour pour l'item " + id);
    })
    .catch((error) => {
      alert("Erreur lors de la mise à jour");
      console.error(error);
    });
}

/**
 * Supprime un item après confirmation
 */
function deleteItem(id) {
  if (confirm("Voulez-vous vraiment supprimer cet élément ?")) {
    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);

    fetch("/modules/budget/includes/api/manage-item.php", {
      method: "POST",
      body: formData,
    }).then(() => {
      window.location.reload(); // On recharge pour mettre à jour les totaux
    });
  }
}

/**
 * Ouvre la modal et pré-remplit les champs pour la modification
 */
function editItem(item) {
  // Si 'item' arrive sous forme de string JSON depuis l'attribut HTML
  const data = typeof item === "string" ? JSON.parse(item) : item;

  document.getElementById("modalTitle").innerText = "Modifier : " + data.name;
  document.getElementById("item_id").value = data.id;
  document.getElementById("item_name").value = data.name;
  document.getElementById("item_amount").value = data.amount;
  document.getElementById("item_category").value = data.category;
  document.getElementById("item_type").value = data.type;
  document.getElementById("item_day").value = data.payment_day;
  document.getElementById("item_reg_month").value = data.reg_month || "";
  document.getElementById("item_is_estimate").value = data.is_estimate;

  document.getElementById("budgetModal").style.display = "flex";
}

function openModal(mode) {
  if (mode === "add") {
    document.getElementById("modalTitle").innerText = "Ajouter un élément";
    document.getElementById("item_id").value = "";
    document.querySelector("#budgetModal form").reset();
  }
  document.getElementById("budgetModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("budgetModal").style.display = "none";
}

// Variable globale pour stocker les catégories existantes (passées par PHP)
let knownCategories = [];

function openSavingsModal(mode, owner) {
  document.getElementById("savingsModal").style.display = "flex";
  document.getElementById("sav_owner").value = owner; // On stocke le owner dans le form caché

  const container = document.getElementById("linesContainer");
  container.innerHTML = "";

  if (mode === "add") {
    document.getElementById("savingsModalTitle").innerText =
      "Nouveau pour " + owner;
    document.getElementById("sav_date").valueAsDate = new Date();
    document.getElementById("sav_total").value = "";
    // Par défaut, une ligne vide
    createLine("", "");
  }
}

function editMonth(monthDate, owner, dataValues, allCats) {
  document.getElementById("savingsModal").style.display = "flex";
  document.getElementById("savingsModalTitle").innerText =
    "Modifier " + owner + " (" + monthDate + ")";
  document.getElementById("sav_owner").value = owner;
  document.getElementById("sav_date").value = monthDate;

  knownCategories = allCats || [];
  const container = document.getElementById("linesContainer");
  container.innerHTML = "";

  // Total
  if (dataValues["TOTAL_BANQUE"]) {
    document.getElementById("sav_total").value = dataValues["TOTAL_BANQUE"];
    delete dataValues["TOTAL_BANQUE"];
  } else {
    document.getElementById("sav_total").value = "";
  }

  // Lignes existantes
  for (const [category, amount] of Object.entries(dataValues)) {
    createLine(category, amount);
  }
  createLine("", "");
}

function addNewLine() {
  createLine("", "");
}

/**
 * Crée une ligne HTML dans la modale : [ Nom de la catégorie ] [ Montant ] [ X ]
 */
function createLine(name, amount) {
  const container = document.getElementById("linesContainer");
  const div = document.createElement("div");
  div.className = "savings-line-item";
  const listId = "list_" + Math.random().toString(36).substr(2, 9);

  div.innerHTML = `
        <div class="input-category-wrapper">
            <input type="text" name="cat_names[]" class="pf-input" placeholder="Catégorie" value="${name}" list="${listId}">
            <datalist id="${listId}">${knownCategories.map((c) => `<option value="${c}">`).join("")}</datalist>
        </div>
        <input type="number" step="0.01" name="cat_amounts[]" class="pf-input input-amount" placeholder="0.00" value="${amount}">
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="Supprimer">&times;</button>
    `;
  container.appendChild(div);
}

/**
 * Supprime une entrée spécifique (Mois + Catégorie) sans recharger la page (si possible)
 */
function deleteSavingsEntry(monthDate, category, owner) {
  if (!confirm(`Supprimer le montant pour "${category}" ?`)) return;

  const formData = new FormData();
  formData.append("action", "delete_entry");
  formData.append("month_date", monthDate);
  formData.append("category", category);
  formData.append("owner", owner);

  fetch("/modules/budget/includes/api/save-savings.php", {
    method: "POST",
    body: formData,
  }).then(() => window.location.reload());
}

/**
 * Duplique le dernier mois vers le mois suivant
 */
function duplicateLastMonth(lastMonthDate, owner) {
  let dateObj = new Date(lastMonthDate);
  dateObj.setMonth(dateObj.getMonth() + 1);
  let nextMonthStr = dateObj.toISOString().split("T")[0];

  let newTotal = prompt(
    `Dupliquer pour ${owner} vers ${nextMonthStr} ?\n\nNouveau TOTAL :`,
    "",
  );

  if (newTotal !== null && newTotal.trim() !== "") {
    const formData = new FormData();
    formData.append("action", "duplicate_month");
    formData.append("source_date", lastMonthDate);
    formData.append("target_date", nextMonthStr);
    formData.append("new_total", newTotal);
    formData.append("owner", owner);

    fetch("/modules/budget/includes/api/save-savings.php", {
      method: "POST",
      body: formData,
    })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) window.location.reload();
        else alert(d.error);
      });
  }
}

/**
 * Supprime toutes les données d'un mois pour le propriétaire actuel
 */
function deleteEntireMonth(monthDate, owner) {
  if (!confirm(`Supprimer TOUT le mois de ${monthDate} pour ${owner} ?`))
    return;

  const formData = new FormData();
  formData.append("action", "delete_month_global");
  formData.append("month_date", monthDate);
  formData.append("owner", owner);

  fetch("/modules/budget/includes/api/save-savings.php", {
    method: "POST",
    body: formData,
  }).then(() => window.location.reload());
}
