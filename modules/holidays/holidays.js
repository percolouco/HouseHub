document.addEventListener("DOMContentLoaded", () => {
  // Fermer les modales si on clique en dehors du contenu
  window.onclick = function (event) {
    const modal = document.getElementById("holidayModal");
    if (event.target == modal) {
      closeHolidayModal();
    }
  };
});

// --- 1. GESTION DE LA MODALE D'ÉDITION RAPIDE ---

function openHolidayModal(mode) {
  const modal = document.getElementById("holidayModal");
  const form = document.getElementById("holidayForm");
  const btnDelete = document.getElementById("btn_delete");

  // Reset complet pour éviter les résidus d'une carte précédente
  form.reset();
  document.getElementById("inp_id").value = "";
  document.getElementById("list_transport").innerHTML = "";
  document.getElementById("list_accommodation").innerHTML = "";
  document.getElementById("list_activity").innerHTML = "";

  if (mode === "add") {
    document.getElementById("modalTitle").innerText = "Planifier le voyage";
    btnDelete.style.display = "none";
  } else {
    document.getElementById("modalTitle").innerText = "Modification rapide";
    btnDelete.style.display = "block";
  }

  modal.style.display = "flex";

  // Focus sur le champ titre pour une saisie rapide (petit délai pour l'animation d'ouverture)
  setTimeout(() => document.getElementById("inp_title").focus(), 100);
}

function closeHolidayModal() {
  document.getElementById("holidayModal").style.display = "none";
}

// Fonction appelée au clic sur le bouton ✏️ de la carte (injectée par PHP)
function editHoliday(data) {
  const h = data.main;

  // On ouvre la modale en mode édition (ça reset les listes)
  openHolidayModal("edit");

  // Remplissage des champs principaux
  document.getElementById("inp_id").value = h.id;
  document.getElementById("inp_title").value = h.title;
  document.getElementById("inp_status").value = h.status;
  document.getElementById("inp_period").value = h.period_hint || "";
  document.getElementById("inp_start").value = h.start_date || "";
  document.getElementById("inp_end").value = h.end_date || "";

  // Pour éviter d'afficher "0" dans un champ vide, on vérifie si la valeur est > 0
  document.getElementById("inp_food").value =
    h.budget_food > 0 ? h.budget_food : "";
  document.getElementById("inp_extra").value =
    h.budget_extra > 0 ? h.budget_extra : "";
  document.getElementById("inp_notes").value = h.notes || "";

  // Remplissage des listes dynamiques (Transport, Hébergement, Activité)
  if (data.items && data.items.length > 0) {
    data.items.forEach((item) => {
      addItem(item.category, item.name, item.amount, item.is_paid);
    });
  }
}

// --- 2. GESTION DES LISTES DYNAMIQUES DANS LA MODALE ---

function addItem(category, name = "", amount = "", isPaid = 0) {
  const container = document.getElementById("list_" + category);
  const div = document.createElement("div");

  // Style en ligne pour s'assurer que ça reste propre sans dépendre de classes externes complexes
  div.style.display = "flex";
  div.style.gap = "8px";
  div.style.alignItems = "center";
  div.style.marginBottom = "10px";

  // Astuce pour lier la checkbox visuelle à l'input caché (valeur 0 ou 1 pour MySQL)
  const checkedAttr = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <input type="hidden" name="items[cat][]" value="${category}">
        
        <input type="text" name="items[name][]" class="pf-input" 
               placeholder="Intitulé" value="${name}" 
               style="flex: 2; padding: 8px; font-size:0.9rem;" required>
               
        <input type="number" step="0.01" name="items[amount][]" class="pf-input" 
               placeholder="Prix (€)" value="${amount}" 
               style="width: 80px; text-align: right; padding: 8px; font-size:0.9rem;">
               
        <label title="Déjà payé ?" style="display: flex; align-items: center; cursor: pointer; padding: 0 5px;">
            <input type="checkbox" ${checkedAttr} onchange="this.nextElementSibling.value = this.checked ? 1 : 0" style="margin:0;">
            <input type="hidden" name="items[paid][]" value="${isPaid}">
            <span style="font-size:0.75rem; margin-left:4px; font-weight:bold; color:#64748b;">Payé</span>
        </label>
        
        <button type="button" onclick="this.parentElement.remove()" title="Retirer cette ligne" 
                style="width: 28px; height: 28px; border: none; background: #fee2e2; color: #ef4444; border-radius: 6px; cursor: pointer; font-weight: bold; display:flex; align-items:center; justify-content:center;">
            &times;
        </button>
    `;

  container.appendChild(div);
}

function deleteHoliday() {
  if (
    !confirm(
      "Voulez-vous vraiment supprimer définitivement ce voyage ? Cette action est irréversible.",
    )
  )
    return;

  const form = document.getElementById("holidayForm");

  // On injecte un input caché pour signaler au PHP que c'est une demande de suppression
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";

  form.appendChild(input);
  form.submit();
}

// --- 3. GESTION DE LA CARTE (Leaflet - Optionnel selon si tu l'utilises ou non) ---

let map = null;

function toggleMap() {
  const modal = document.getElementById("hol-map-modal");

  if (modal.style.display === "flex") {
    modal.style.display = "none";
  } else {
    modal.style.display = "flex";
    // Petit délai pour laisser le temps au DOM de s'afficher avant d'init Leaflet
    setTimeout(initMap, 100);
  }
}

function initMap() {
  if (map) {
    map.invalidateSize(); // Recalcule la taille si la fenêtre a changé
    return;
  }

  if (typeof L === "undefined") return;

  // Centré sur l'Europe par défaut
  map = L.map("hol-map").setView([46.6, 2.4], 4);

  L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
    {
      attribution: "© OpenStreetMap",
    },
  ).addTo(map);

  // On récupère les points définis en global dans le PHP
  if (typeof HOL_MAP_POINTS !== "undefined") {
    HOL_MAP_POINTS.forEach((pt) => {
      const color =
        pt.status === "planned" || pt.status === "booked" ? "green" : "blue";

      // Cercle simple
      L.circleMarker([pt.lat, pt.lng], {
        color: color,
        radius: 8,
        fillOpacity: 0.8,
      })
        .addTo(map)
        .bindPopup(`<b>${pt.title}</b><br>${pt.status}`);
    });
  }
}
// ============================================================================
// 4. GESTION DE LA CARTE DÉTAILLÉE (ROADTRIP) ET GÉOCODAGE
// ============================================================================

let detailMap = null;

document.addEventListener("DOMContentLoaded", () => {
  // Si on est sur la page détail et que la div "tripMap" existe, on initie la carte
  if (document.getElementById("tripMap")) {
    initDetailMap();
  }
});

function initDetailMap() {
  if (typeof L === "undefined" || typeof MAP_POINTS === "undefined") return;

  detailMap = L.map("tripMap");

  // Fond de carte propre (CartoDB Voyager)
  L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
    {
      attribution: "© OpenStreetMap",
    },
  ).addTo(detailMap);

  if (MAP_POINTS.length === 0) {
    detailMap.setView([46.6, 2.4], 5); // Centré sur la France par défaut si vide
    return;
  }

  const latlngs = [];
  const bounds = L.latLngBounds();

  MAP_POINTS.forEach((pt, index) => {
    const pos = [pt.lat, pt.lng];
    latlngs.push(pos);
    bounds.extend(pos);

    // Couleur selon le paiement
    const color = pt.paid == 1 ? "#10b981" : "#f59e0b";

    // On place un petit cercle pour chaque point
    const marker = L.circleMarker(pos, {
      color: color,
      radius: 7,
      fillOpacity: 1,
      fillColor: "white",
      weight: 3,
    }).addTo(detailMap);

    // Numérotation et Popup
    marker.bindPopup(`
            <div style="text-align:center;">
                <div style="font-size:0.7rem; color:#64748b; margin-bottom:2px;">Étape ${index + 1}</div>
                <strong>${pt.title}</strong><br>
                <span style="font-weight:bold; color:${color};">${parseFloat(pt.amount).toFixed(2)} €</span>
            </div>
        `);
  });

  // On dessine le trait en pointillés pour relier le roadtrip !
  if (latlngs.length > 1) {
    L.polyline(latlngs, {
      color: "#3b82f6",
      weight: 3,
      dashArray: "8, 8",
      opacity: 0.7,
    }).addTo(detailMap);
  }

  // On zoome automatiquement pour voir tous les points
  detailMap.fitBounds(bounds, { padding: [50, 50] });
}

// Fonction appelée quand on clique sur "👁️ Voir sur la carte" dans la liste
function panMapTo(lat, lng) {
  if (detailMap) {
    detailMap.setView([lat, lng], 14, { animate: true });
  }
}

// --- LOGIQUE DE LA MODALE CHECKPOINT (Lignes multiples) ---

function openCheckpointModal(mode, data = null) {
  const searchBlock = document.getElementById("cpSearchBlock");
  const formBlock = document.getElementById("formCheckpoint");
  const container = document.getElementById("cpExpensesContainer");
  const btnDel = document.getElementById("btnDeleteCp");

  container.innerHTML = "";

  if (mode === "add") {
    document.getElementById("cpModalTitle").innerText =
      "📍 Placer une nouvelle étape";
    searchBlock.style.display = "block";
    formBlock.style.display = "none";
    btnDel.style.display = "none";
    document.getElementById("cp_old_sort_order").value = ""; // Important : vide pour ajout
    document.getElementById("cp_name").value = "";
    addCpExpenseLine();
  } else if (mode === "edit" && data) {
    document.getElementById("cpModalTitle").innerText = "✏️ Modifier l'étape";
    searchBlock.style.display = "none";
    formBlock.style.display = "block";
    btnDel.style.display = "block";

    document.getElementById("cp_lat").value = data.lat;
    document.getElementById("cp_lng").value = data.lng;
    document.getElementById("cp_old_sort_order").value = data.sort_order; // On stocke l'index unique
    document.getElementById("cp_name").value = data.location_name;

    if (data.items && data.items.length > 0) {
      let visibleCount = 0;
      data.items.forEach((it) => {
        if (it.name !== "PF_TECHNICAL_POINT") {
          addCpExpenseLine(it.category, it.name, it.amount, it.is_paid);
          visibleCount++;
        }
      });
      if (visibleCount === 0) addCpExpenseLine();
    } else {
      addCpExpenseLine();
    }
  }
  document.getElementById("checkpointModal").style.display = "flex";
}

function searchPlace() {
  const q = document.getElementById("searchPlaceInput").value.trim();
  if (q.length < 3) return;

  const resultsDiv = document.getElementById("searchResults");
  resultsDiv.innerHTML =
    '<span style="color:#64748b; font-size:0.85rem;">Recherche en cours... ⏳</span>';

  fetch(
    "/modules/holidays/includes/api/geocode.php?limit=5&q=" +
      encodeURIComponent(q),
  )
    .then((res) => res.json())
    .then((data) => {
      resultsDiv.innerHTML = "";
      if (data.error || !data.results || data.results.length === 0) {
        resultsDiv.innerHTML =
          '<span style="color:#ef4444; font-size:0.85rem;">Aucun résultat trouvé.</span>';
        return;
      }

      data.results.forEach((place) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "pf-btn btn-secondary";
        btn.style.textAlign = "left";
        btn.style.padding = "8px";
        btn.style.height = "auto";
        btn.innerText = "📍 " + place.display_name;
        btn.onclick = () =>
          selectPlace(place.lat, place.lng, place.display_name);
        resultsDiv.appendChild(btn);
      });
    })
    .catch((err) => {
      resultsDiv.innerHTML =
        '<span style="color:#ef4444; font-size:0.85rem;">Erreur réseau.</span>';
    });
}

function selectPlace(lat, lng, fullName) {
  document.getElementById("cp_lat").value = lat;
  document.getElementById("cp_lng").value = lng;
  // On nettoie le nom pour que ce soit joli (ex: "Paris, France" -> "Paris")
  document.getElementById("cp_name").value = fullName.split(",")[0].trim();
  document.getElementById("cpSearchBlock").style.display = "none";
  document.getElementById("formCheckpoint").style.display = "block";
}

function addCpExpenseLine(
  category = "accommodation",
  name = "",
  amount = "",
  isPaid = 0,
) {
  const container = document.getElementById("cpExpensesContainer");
  const div = document.createElement("div");
  div.style.display = "flex";
  div.style.gap = "8px";
  div.style.alignItems = "center";

  const isChecked = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <select name="items[cat][]" class="pf-input" style="width:50px; padding:8px 4px; font-size:1.2rem; cursor:pointer;" title="Catégorie">
            <option value="accommodation" ${category === "accommodation" ? "selected" : ""}>🏨</option>
            <option value="transport" ${category === "transport" ? "selected" : ""}>🚗</option>
            <option value="activity" ${category === "activity" ? "selected" : ""}>🎫</option>
        </select>
        <input type="text" name="items[name][]" class="pf-input" placeholder="Libellé (Optionnel)" value="${name}" style="flex:2; padding:8px; font-size:0.9rem;">
        <input type="number" step="0.01" name="items[amount][]" class="pf-input" placeholder="0.00" value="${amount}" style="width:80px; text-align:right; padding:8px; font-size:0.9rem;">
        <label title="Payé ?" style="display:flex; align-items:center; cursor:pointer;">
            <input type="checkbox" ${isChecked} onchange="this.nextElementSibling.value = this.checked ? 1 : 0" style="margin:0;">
            <input type="hidden" name="items[paid][]" value="${isPaid}">
            <span style="font-size:0.75rem; margin-left:2px; font-weight:bold; color:#64748b;">Payé</span>
        </label>
        <button type="button" onclick="this.parentElement.remove()" style="width:28px; height:28px; border:none; background:#fee2e2; color:#ef4444; border-radius:6px; cursor:pointer; font-weight:bold; display:flex; justify-content:center; align-items:center;">&times;</button>
    `;
  container.appendChild(div);
}

function deleteCheckpoint() {
  if (!confirm("Supprimer cette étape et toutes les dépenses associées ?"))
    return;
  const form = document.getElementById("formCheckpoint");
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

// ============================================================================
// 5. GLISSER-DÉPOSER POUR RÉORDONNER LES ÉTAPES (ROADTRIP)
// ============================================================================
document.addEventListener("DOMContentLoaded", () => {
  const checkpoints = document.querySelectorAll(".hol-checkpoint-draggable");
  const container = checkpoints[0]?.parentElement;
  if (!container) return;

  let draggedItem = null;

  checkpoints.forEach((item) => {
    item.addEventListener("dragstart", function (e) {
      draggedItem = this;
      setTimeout(() => (this.style.opacity = "0.4"), 0);
    });

    item.addEventListener("dragend", function () {
      setTimeout(() => {
        this.style.opacity = "1";
        draggedItem = null;
        saveCheckpointOrder(); // On sauvegarde quand on lâche !
      }, 0);
    });

    item.addEventListener("dragover", function (e) {
      e.preventDefault();
      const afterElement = getDragAfterElement(container, e.clientY);
      if (afterElement == null) {
        container.appendChild(draggedItem);
      } else {
        container.insertBefore(draggedItem, afterElement);
      }
    });
  });

  function getDragAfterElement(container, y) {
    const draggableElements = [
      ...container.querySelectorAll(
        '.hol-checkpoint-draggable:not([style*="opacity: 0.4"])',
      ),
    ];
    return draggableElements.reduce(
      (closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset: offset, element: child };
        } else {
          return closest;
        }
      },
      { offset: Number.NEGATIVE_INFINITY },
    ).element;
  }

  function saveCheckpointOrder() {
    // On récupère le nom des lieux dans le nouvel ordre de haut en bas
    const locations = [
      ...document.querySelectorAll(".hol-checkpoint-draggable"),
    ].map((el) => el.getAttribute("data-location"));
    const holidayId = document.querySelector('input[name="holiday_id"]').value; // Input caché dans la modale

    const formData = new FormData();
    formData.append("holiday_id", holidayId);
    formData.append("locations", JSON.stringify(locations));

    fetch("/modules/holidays/includes/api/reorder_checkpoints.php", {
      method: "POST",
      body: formData,
    }).then(() => {
      // Recharge la page pour que la carte redessine le trait bleu dans le bon ordre !
      window.location.reload();
    });
  }
});
