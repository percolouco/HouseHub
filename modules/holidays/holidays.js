// modules/holidays/holidays.js

document.addEventListener("DOMContentLoaded", () => {
  // --- Modale "Ajouter une idée"
  const addBtn = document.getElementById("hol-add-open");
  const addModal = document.getElementById("hol-add-modal");
  if (addBtn && addModal) {
    const backdrop = addModal.querySelector(".hol-backdrop");
    const cancel = addModal.querySelector(".hol-cancel");
    const open = () => addModal.classList.add("open");
    const close = () => addModal.classList.remove("open");
    addBtn.addEventListener("click", open);
    backdrop.addEventListener("click", close);
    cancel.addEventListener("click", close);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  }

  // --- Helpers modale "Éditer"
  const editModal = document.getElementById("hol-edit-modal");
  const openEditModal = () => {
    if (editModal) editModal.classList.add("open");
  };
  const closeEditModal = () => {
    if (editModal) editModal.classList.remove("open");
  };

  async function openEditForId(id) {
    try {
      const res = await fetch(
        `/modules/holidays/view.php?id=${encodeURIComponent(id)}`,
        {
          headers: { Accept: "application/json" },
        },
      );
      if (!res.ok) throw new Error("HTTP " + res.status);
      const it = await res.json();

      if (!editModal) {
        alert("Modale édition introuvable");
        return;
      }
      // Scope les sélecteurs à la modale pour éviter les null
      const $ = (sel) => editModal.querySelector(sel);
      const setVal = (sel, val) => {
        const el = $(sel);
        if (el) el.value = val ?? "";
      };

      setVal("#edit-id", it.id);
      setVal("#edit-title", it.title || "");
      setVal("#edit-country", it.country || "");
      setVal("#edit-region", it.region || "");
      setVal("#edit-city", it.city || "");
      setVal("#edit-lat", it.lat ?? "");
      setVal("#edit-lng", it.lng ?? "");
      setVal("#edit-start", it.desired_start_date ?? "");
      setVal("#edit-end", it.desired_end_date ?? "");
      setVal("#edit-season", it.season_hint || "");
      setVal("#edit-days", it.ideal_days ?? "");
      setVal("#edit-status", it.status || "draft");
      setVal("#edit-notes", it.notes || "");

      openEditModal();
    } catch {
      alert("Impossible de charger l’idée.");
    }
  }

  // --- Modale "Éditer" (liste + page détail)
  if (editModal) {
    const backdrop = editModal.querySelector(".hol-backdrop");
    const cancel = editModal.querySelector(".hol-cancel");
    if (backdrop) backdrop.addEventListener("click", closeEditModal);
    if (cancel) cancel.addEventListener("click", closeEditModal);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeEditModal();
    });

    // Boutons "Éditer" des cards (liste/planifiées/archivées)
    document.querySelectorAll(".btn-edit[data-edit-id]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-edit-id");
        if (id) openEditForId(id);
      });
    });

    // Bouton "Éditer" sur la page détail (en-tête)
    const headerEditBtn = document.getElementById("hol-edit-open");
    if (headerEditBtn) {
      headerEditBtn.addEventListener("click", () => {
        const id = headerEditBtn.getAttribute("data-edit-id");
        if (id) openEditForId(id);
      });
    }
  }

  // --- Suppression (liste + planifiées + page détail)
  document.querySelectorAll(".btn-delete[data-del-id]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-del-id");
      if (!id) return;
      if (confirm("Supprimer cette idée ?")) {
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
    });
  });

  // --- Géocodage via Nominatim + UI multi-résultats
  document.querySelectorAll(".hol-geocode-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const scope = btn.getAttribute("data-scope"); // 'add' ou 'edit'
      let form, city, region, country, latInput, lngInput;

      if (scope === "edit") {
        form = btn.closest("form") || document;
        city = (document.getElementById("edit-city")?.value || "").trim();
        region = (document.getElementById("edit-region")?.value || "").trim();
        country = (document.getElementById("edit-country")?.value || "").trim();
        latInput = document.getElementById("edit-lat");
        lngInput = document.getElementById("edit-lng");
      } else {
        form = btn.closest("form");
        city = (form?.querySelector('input[name="city"]')?.value || "").trim();
        region = (
          form?.querySelector('input[name="region"]')?.value || ""
        ).trim();
        country = (
          form?.querySelector('input[name="country"]')?.value || ""
        ).trim();
        latInput = form?.querySelector('input[name="lat"]');
        lngInput = form?.querySelector('input[name="lng"]');
      }

      const q = [city, region, country].filter(Boolean).join(", ");
      if (!q) {
        alert("Renseigne au moins Ville/Pays.");
        return;
      }

      removeNearbyPicker(btn);
      btn.disabled = true;
      const original = btn.textContent;
      btn.textContent = "Recherche...";

      try {
        const res = await fetch(
          `/modules/holidays/geocode.php?q=${encodeURIComponent(q)}&limit=5`,
          {
            headers: { Accept: "application/json" },
          },
        );
        const data = await res.json();
        if (!res.ok) throw new Error(data?.error || "Erreur géocodage");

        if ("lat" in data && "lng" in data) {
          if (latInput) latInput.value = data.lat;
          if (lngInput) lngInput.value = data.lng;
          return;
        }

        if (Array.isArray(data.results) && data.results.length > 0) {
          renderGeocodePicker(btn, data.results, (choice) => {
            if (latInput) latInput.value = choice.lat;
            if (lngInput) lngInput.value = choice.lng;
            removeNearbyPicker(btn);
          });
        } else {
          alert("Aucun résultat.");
        }
      } catch {
        alert("Impossible de trouver les coordonnées pour: " + q);
      } finally {
        btn.disabled = false;
        btn.textContent = original;
      }
    });
  });

  function renderGeocodePicker(anchorBtn, results, onPick) {
    removeNearbyPicker(anchorBtn);

    const wrapper = document.createElement("div");
    wrapper.className = "hol-geocode-picker";

    const header = document.createElement("div");
    header.className = "hol-geocode-picker__header";
    header.textContent = "Plusieurs résultats trouvés";
    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "hol-geocode-picker__close";
    closeBtn.textContent = "×";
    closeBtn.addEventListener("click", () => removeNearbyPicker(anchorBtn));
    header.appendChild(closeBtn);

    const list = document.createElement("ul");
    list.className = "hol-geocode-picker__list";

    results.forEach((r) => {
      const li = document.createElement("li");
      li.className = "hol-geocode-picker__item";

      const label = document.createElement("div");
      label.className = "hol-geocode-picker__label";
      label.textContent = r.display_name || `${r.lat}, ${r.lng}`;

      const coords = document.createElement("div");
      coords.className = "hol-geocode-picker__coords";
      coords.textContent = `(${r.lat}, ${r.lng})`;

      const pickBtn = document.createElement("button");
      pickBtn.type = "button";
      pickBtn.className = "hol-geocode-picker__pick";
      pickBtn.textContent = "Choisir";
      pickBtn.addEventListener("click", () => onPick(r));

      li.appendChild(label);
      li.appendChild(coords);
      li.appendChild(pickBtn);
      list.appendChild(li);
    });

    wrapper.appendChild(header);
    wrapper.appendChild(list);

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

  // --- Carte (Leaflet)
  const mapBtn = document.getElementById("hol-map-open");
  const mapModal = document.getElementById("hol-map-modal");
  if (mapBtn && mapModal) {
    const backdrop = mapModal.querySelector(".hol-backdrop");
    const cancel = mapModal.querySelector(".hol-cancel");
    const open = () => mapModal.classList.add("open");
    const close = () => mapModal.classList.remove("open");

    let mapInitialized = false;
    let map;

    function initMap() {
      // Évite double initialisation
      if (mapInitialized) return;
      mapInitialized = true;

      // Données carte (safe fallback)
      const MAP_DATA = Array.isArray(window.HOL_MAP_DATA)
        ? window.HOL_MAP_DATA
        : [];
      console.log("HOL_MAP_DATA (safe):", MAP_DATA);

      // Leaflet dispo ?
      if (typeof L === "undefined") {
        console.error("Leaflet non chargé");
        return;
      }

      // Init carte une seule fois (ne pas faire ça dans la boucle)
      map = L.map("hol-map", { scrollWheelZoom: true });
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap",
      }).addTo(map);

      // Corrige la taille après affichage de la modale
      setTimeout(() => map.invalidateSize(), 0);

      // Ajout des marqueurs
      const markers = [];
      MAP_DATA.forEach((it) => {
        const lat = parseFloat(it.lat);
        const lng = parseFloat(it.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const color =
          it.status === "planned"
            ? "#16a34a"
            : it.status === "favorite"
              ? "#f59e0b"
              : it.status === "shortlist"
                ? "#3b82f6"
                : "#6b7280";

        const m = L.circleMarker([lat, lng], {
          radius: 6,
          color,
          fillColor: color,
          fillOpacity: 0.85,
        }).addTo(map);

        const loc = [it.city, it.region, it.country].filter(Boolean).join(", ");
        const dates = it.desired_start_date
          ? `${it.desired_start_date}${it.desired_end_date ? " → " + it.desired_end_date : ""}`
          : "";

        m.bindPopup(`
      <strong>${esc(it.title || "")}</strong><br/>
      ${esc(loc)}<br/>
      ${dates ? "Dates: " + esc(dates) + "<br/>" : ""}
      Statut: ${esc(it.status || "")}<br/>
      <a href="/holidays.php?id=${it.id}">Ouvrir</a>
    `);

        markers.push(m);
      });

      // Vue par défaut selon nombre de points
      if (markers.length === 1) {
        map.setView(markers[0].getLatLng(), 7);
      } else if (markers.length > 1) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds(), { padding: [20, 20] });
      } else {
        console.warn(
          "HOL_MAP_DATA vide ou coordonnées non valides.",
          window.HOL_MAP_DATA,
        );
        map.setView([20, 0], 2);
      }

      // Re-valider la taille après rendu complet
      setTimeout(() => map.invalidateSize(), 100);
    }

    function esc(s) {
      return String(s).replace(
        /[&<>"']/g,
        (c) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[c],
      );
    }

    mapBtn.addEventListener("click", () => {
      open();
      setTimeout(initMap, 0);
    });
    if (backdrop) backdrop.addEventListener("click", close);
    if (cancel) cancel.addEventListener("click", close);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  }
});
