// modules/holidays/holidays.js

document.addEventListener("DOMContentLoaded", () => {
  // --- 1. FONCTIONS UTILITAIRES ---

  /**
   * Configure les comportements de fermeture d'une modale (Backdrop, Cancel, Escape)
   */
  function setupModal(modalId, openAction = null) {
    const modal = document.getElementById(modalId);
    if (!modal) return null;

    const backdrop = modal.querySelector(".hol-backdrop");
    const cancelBtn = modal.querySelector(".hol-cancel");

    const close = () => modal.classList.remove("open");
    const open = () => {
      modal.classList.add("open");
      if (openAction) openAction();
    };

    if (backdrop) backdrop.addEventListener("click", close);
    if (cancelBtn) cancelBtn.addEventListener("click", close);

    // Fermeture avec ECHAP (uniquement si cette modale est ouverte)
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("open")) {
        close();
      }
    });

    return { modal, open, close };
  }

  /**
   * Échappe les caractères HTML pour éviter les failles XSS simples
   */
  function esc(s) {
    if (s === null || s === undefined) return "";
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // --- 2. GESTION MODALE "AJOUTER" ---
  const addModalCtrl = setupModal("hol-add-modal");
  const addBtn = document.getElementById("hol-add-open");
  if (addBtn && addModalCtrl) {
    addBtn.addEventListener("click", addModalCtrl.open);
  }

  // --- 3. GESTION MODALE "ÉDITER" ---
  const editModalCtrl = setupModal("hol-edit-modal");

  // Fonction pour charger et ouvrir l'édition via AJAX
  async function openEditForId(id) {
    if (!editModalCtrl) return;

    try {
      const res = await fetch(
        `/modules/holidays/view.php?id=${encodeURIComponent(id)}`,
        { headers: { Accept: "application/json" } },
      );
      if (!res.ok) throw new Error("Erreur HTTP " + res.status);

      const it = await res.json();
      const modal = editModalCtrl.modal;

      // Helper pour remplir les champs
      const setVal = (sel, val) => {
        const el = modal.querySelector(sel);
        if (el) el.value = val ?? "";
      };

      // Remplissage du formulaire
      setVal("#edit-id", it.id);
      setVal("#edit-title", it.title);
      setVal("#edit-country", it.country);
      setVal("#edit-region", it.region);
      setVal("#edit-city", it.city);
      setVal("#edit-lat", it.lat);
      setVal("#edit-lng", it.lng);
      setVal("#edit-start", it.desired_start_date);
      setVal("#edit-end", it.desired_end_date);
      setVal("#edit-season", it.season_hint);
      setVal("#edit-days", it.ideal_days);
      setVal("#edit-status", it.status || "draft");
      setVal("#edit-notes", it.notes);

      editModalCtrl.open();
    } catch (err) {
      console.error(err);
      alert("Impossible de charger les données de l'idée.");
    }
  }

  // Écouteurs sur les boutons "Éditer" (Liste + Détail)
  document.body.addEventListener("click", (e) => {
    // Utilisation de la délégation d'événement pour gérer tous les boutons (même dynamiques)
    const btn = e.target.closest(".btn-edit");
    if (btn && btn.hasAttribute("data-edit-id")) {
      const id = btn.getAttribute("data-edit-id");
      openEditForId(id);
    }
    // Cas spécifique du bouton dans le header de la vue détail
    else if (e.target.id === "hol-edit-open") {
      const id = e.target.getAttribute("data-edit-id");
      openEditForId(id);
    }
  });

  // --- 4. GESTION SUPPRESSION ---
  document.body.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-delete");
    if (btn && btn.hasAttribute("data-del-id")) {
      const id = btn.getAttribute("data-del-id");
      if (
        confirm(
          "Voulez-vous vraiment supprimer cette idée ?\nCette action est irréversible.",
        )
      ) {
        // Création d'un formulaire temporaire pour le POST
        const form = document.createElement("form");
        form.method = "post";
        form.action = "/modules/holidays/save.php";
        form.innerHTML = `
          <input type="hidden" name="action" value="delete_idea">
          <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
  });

  // --- 5. GÉOCODAGE (Nominatim) ---
  document.querySelectorAll(".hol-geocode-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const scope = btn.getAttribute("data-scope"); // 'add' ou 'edit'
      let latInput,
        lngInput,
        qParts = [];

      // Récupération des inputs selon le scope
      if (scope === "edit") {
        const getVal = (id) =>
          (document.getElementById(id)?.value || "").trim();
        qParts = [
          getVal("edit-city"),
          getVal("edit-region"),
          getVal("edit-country"),
        ];
        latInput = document.getElementById("edit-lat");
        lngInput = document.getElementById("edit-lng");
      } else {
        // Scope 'add' : on cherche dans le formulaire parent
        const form = btn.closest("form");
        const getVal = (name) =>
          (form?.querySelector(`input[name="${name}"]`)?.value || "").trim();
        qParts = [getVal("city"), getVal("region"), getVal("country")];
        latInput = form?.querySelector('input[name="lat"]');
        lngInput = form?.querySelector('input[name="lng"]');
      }

      const q = qParts.filter(Boolean).join(", ");
      if (!q) {
        alert(
          "Veuillez renseigner au moins une Ville ou un Pays pour géocoder.",
        );
        return;
      }

      // UI : chargement
      removeNearbyPicker(btn);
      btn.disabled = true;
      const originalText = btn.textContent;
      btn.textContent = "⏳...";

      try {
        const res = await fetch(
          `/modules/holidays/geocode.php?q=${encodeURIComponent(q)}&limit=5`,
          {
            headers: { Accept: "application/json" },
          },
        );
        const data = await res.json();

        if (!res.ok) throw new Error(data?.error || "Erreur inconnue");

        // Cas 1: Résultat direct (lat/lng uniques)
        if (data.lat && data.lng) {
          if (latInput) latInput.value = data.lat;
          if (lngInput) lngInput.value = data.lng;
        }
        // Cas 2: Liste de choix
        else if (Array.isArray(data.results) && data.results.length > 0) {
          renderGeocodePicker(btn, data.results, (choice) => {
            if (latInput) latInput.value = choice.lat;
            if (lngInput) lngInput.value = choice.lng;
            removeNearbyPicker(btn);
          });
        } else {
          alert("Aucun résultat trouvé pour : " + q);
        }
      } catch (err) {
        console.error(err);
        alert("Erreur lors du géocodage.");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  });

  function renderGeocodePicker(anchorBtn, results, onPick) {
    removeNearbyPicker(anchorBtn);

    const wrapper = document.createElement("div");
    wrapper.className = "hol-geocode-picker";

    // Header
    const header = document.createElement("div");
    header.className = "hol-geocode-picker__header";
    header.innerHTML = `<span>Choix multiples</span><button type="button" class="hol-close">×</button>`;
    header
      .querySelector(".hol-close")
      .addEventListener("click", () => removeNearbyPicker(anchorBtn));
    wrapper.appendChild(header);

    // Liste
    const list = document.createElement("ul");
    list.className = "hol-geocode-picker__list";

    results.forEach((r) => {
      const li = document.createElement("li");
      li.className = "hol-geocode-picker__item";
      li.innerHTML = `
        <div class="hol-info">
            <span class="hol-label">${esc(r.display_name)}</span>
            <span class="hol-coords">(${r.lat}, ${r.lng})</span>
        </div>
        <button type="button">Choisir</button>
      `;
      li.querySelector("button").addEventListener("click", () => onPick(r));
      list.appendChild(li);
    });

    wrapper.appendChild(list);

    // Insertion après le conteneur du bouton
    const container =
      anchorBtn.closest(".hol-inline") || anchorBtn.parentElement;
    container.insertAdjacentElement("afterend", wrapper);
  }

  function removeNearbyPicker(anchorBtn) {
    const container =
      anchorBtn.closest(".hol-inline") || anchorBtn.parentElement;
    const next = container?.nextElementSibling;
    if (next && next.classList.contains("hol-geocode-picker")) {
      next.remove();
    }
  }

  // --- 6. CARTE LEAFLET ---
  let mapInitialized = false;
  let mapInstance;

  // Fonction d'initialisation de la carte (appelée à l'ouverture de la modale)
  function initMap() {
    if (mapInitialized) {
      // Si déjà init, on force juste le redimensionnement pour éviter les bugs d'affichage
      setTimeout(() => mapInstance.invalidateSize(), 200);
      return;
    }

    if (typeof L === "undefined") {
      console.error("Leaflet n'est pas chargé.");
      return;
    }

    // Récupération sécurisée des données injectées par PHP
    const MAP_DATA = Array.isArray(window.HOL_MAP_DATA)
      ? window.HOL_MAP_DATA
      : [];

    mapInstance = L.map("hol-map", { scrollWheelZoom: true });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "© OpenStreetMap",
    }).addTo(mapInstance);

    const markers = [];

    MAP_DATA.forEach((it) => {
      const lat = parseFloat(it.lat);
      const lng = parseFloat(it.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      // Code couleur selon statut
      const colors = {
        planned: "#16a34a",
        favorite: "#f59e0b",
        shortlist: "#3b82f6",
        default: "#6b7280",
      };
      const color = colors[it.status] || colors.default;

      const m = L.circleMarker([lat, lng], {
        radius: 7,
        color: "#ffffff",
        weight: 1,
        fillColor: color,
        fillOpacity: 0.9,
      }).addTo(mapInstance);

      // Construction du popup
      const loc = [it.city, it.region, it.country].filter(Boolean).join(", ");
      const dates = it.desired_start_date
        ? `${it.desired_start_date}${it.desired_end_date ? " → " + it.desired_end_date : ""}`
        : null;

      m.bindPopup(`
        <div class="hol-map-popup">
            <strong>${esc(it.title)}</strong>
            <div style="font-size:0.9em; color:#666;">${esc(loc)}</div>
            ${dates ? `<div style="font-size:0.85em; margin-top:4px;">📅 ${esc(dates)}</div>` : ""}
            <div style="margin-top:8px;">
                <span class="hol-status hol-status--${esc(it.status)}">${esc(it.status)}</span>
                <a href="/holidays.php?id=${it.id}" style="margin-left:8px;">Voir</a>
            </div>
        </div>
      `);

      markers.push(m);
    });

    // Centrage de la carte
    if (markers.length > 0) {
      const group = L.featureGroup(markers);
      mapInstance.fitBounds(group.getBounds(), { padding: [50, 50] });
    } else {
      mapInstance.setView([46.603354, 1.888334], 5); // France par défaut si vide
    }

    mapInitialized = true;

    // Hack indispensable pour que Leaflet calcule la bonne taille dans une modale
    setTimeout(() => mapInstance.invalidateSize(), 200);
  }

  // Connexion de la carte à la modale
  const mapBtn = document.getElementById("hol-map-open");
  if (mapBtn) {
    const mapModalCtrl = setupModal("hol-map-modal", initMap); // On passe initMap en callback d'ouverture
    mapBtn.addEventListener("click", mapModalCtrl.open);
  }
});
