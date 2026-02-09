document.addEventListener("DOMContentLoaded", () => {
  // Initialisation des écouteurs globaux si besoin
});

// --- 1. GESTION DE LA MODALE D'ÉDITION ---

function openHolidayModal(mode) {
  const modal = document.getElementById("holidayModal");
  const form = document.getElementById("holidayForm");
  const btnDelete = document.getElementById("btn_delete");

  // Reset complet
  form.reset();
  document.getElementById("inp_id").value = "";
  document.getElementById("list_transport").innerHTML = "";
  document.getElementById("list_accommodation").innerHTML = "";
  document.getElementById("list_activity").innerHTML = "";

  if (mode === "add") {
    document.getElementById("modalTitle").innerText = "Nouveau Voyage";
    btnDelete.style.display = "none";
  } else {
    document.getElementById("modalTitle").innerText = "Modifier le voyage";
    btnDelete.style.display = "block";
  }

  modal.classList.add("open");
  modal.style.display = "flex";

  // --- AJOUT : FORCER LE SCROLL EN HAUT ---
  const content = modal.querySelector(".pf-modal-content");
  if (content) {
    content.scrollTop = 0;
  }
  // Optionnel : Mettre le focus sur le premier champ (Titre)
  // setTimeout(() => document.getElementById('inp_title').focus(), 50);
}

function closeHolidayModal() {
  const modal = document.getElementById("holidayModal");
  modal.classList.remove("open");
  modal.style.display = "none";
}

// Fonction appelée au clic sur une carte (injectée par PHP)
function editHoliday(data) {
  openHolidayModal("edit");

  const h = data.main;

  // Remplissage des champs principaux
  document.getElementById("inp_id").value = h.id;
  document.getElementById("inp_title").value = h.title;
  document.getElementById("inp_status").value = h.status;
  document.getElementById("inp_period").value = h.period_hint;
  document.getElementById("inp_start").value = h.start_date;
  document.getElementById("inp_end").value = h.end_date;
  document.getElementById("inp_food").value =
    h.budget_food > 0 ? h.budget_food : "";
  document.getElementById("inp_extra").value =
    h.budget_extra > 0 ? h.budget_extra : "";
  document.getElementById("inp_notes").value = h.notes;

  // Remplissage des listes dynamiques
  if (data.items && data.items.length > 0) {
    data.items.forEach((item) => {
      addItem(item.category, item.name, item.amount, item.is_paid);
    });
  }
}

// --- 2. GESTION DES LISTES DYNAMIQUES (Transport, etc.) ---

function addItem(category, name = "", amount = "", isPaid = 0) {
  const container = document.getElementById("list_" + category);
  const div = document.createElement("div");

  // Utilisation des classes CSS définies précédemment
  div.className = "savings-line-item";

  // Checkbox logique pour "Payé"
  const checkedAttr = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <input type="hidden" name="items[cat][]" value="${category}">
        
        <input type="text" name="items[name][]" class="pf-input" 
               placeholder="Nom (ex: Vol)" value="${name}" 
               style="flex: 2; min-width: 0;">
               
        <input type="number" step="0.01" name="items[amount][]" class="pf-input" 
               placeholder="€" value="${amount}" 
               style="width: 80px; text-align: right;">
               
        <label title="Déjà payé ?" style="display: flex; align-items: center; cursor: pointer; padding: 0 5px;">
            <input type="checkbox" ${checkedAttr} onchange="this.nextElementSibling.value = this.checked ? 1 : 0">
            <input type="hidden" name="items[paid][]" value="${isPaid}">
            <span style="font-size:0.8rem; margin-left:4px;">Payé</span>
        </label>
        
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="Supprimer">
            &times;
        </button>
    `;

  container.appendChild(div);
}

function deleteHoliday() {
  if (!confirm("Supprimer définitivement ce voyage ?")) return;

  const form = document.getElementById("holidayForm");

  // On ajoute un input caché pour signaler la suppression au PHP
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";

  form.appendChild(input);
  form.submit();
}

// --- 3. GESTION DE LA CARTE (Leaflet) ---

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
