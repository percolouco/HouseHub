// ============================================================================
// FONCTION DE TRADUCTION JS & LANGUE COURANTE
// ============================================================================
function tr(key) {
  return window.I18N && window.I18N[key] ? window.I18N[key] : key;
}

// On utilise 'var' au lieu de 'const/let' pour éviter les crashs si le fichier est lu 2 fois
var currentLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
var selectedItemIdForMove = null; // Déplacé ici pour plus de clarté

// ============================================================================
// UTILITAIRES MÉTÉO
// ============================================================================
function getWeatherInfo(code) {
  // Transformation en conditions pour regrouper les codes WMO
  if (code === 0) return { icon: "☀️", label: tr("weather_sunny") };
  if ([1, 2].includes(code)) return { icon: "🌤️", label: tr("weather_sunny") };
  if ([3, 45, 48].includes(code))
    return { icon: "☁️", label: tr("weather_cloudy") };
  // Les codes 51 à 67 et 80 à 82 couvrent toutes les formes de pluie et bruine
  if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code))
    return { icon: "🌧️", label: tr("weather_rainy") };
  // Les codes neigeux
  if ([71, 73, 75, 77, 85, 86].includes(code))
    return { icon: "❄️", label: tr("weather_snowy") };
  // Orages
  if ([95, 96, 99].includes(code))
    return { icon: "⛈️", label: tr("weather_rainy") };

  return { icon: "🌡️", label: tr("weather_forecast") };
}

async function loadWeatherForStep(pt) {
  if (!pt.step_start_date || !pt.lat || !pt.lng) return;

  const container = document.querySelector(
    `#step-card-${pt.sort_order} .hol-weather-info`,
  );
  if (!container) return;

  try {
    const resp = await fetch(
      `/modules/holidays/includes/api/get_weather.php?lat=${pt.lat}&lng=${pt.lng}&date=${pt.step_start_date}`,
    );
    const res = await resp.json();

    console.log(`Météo pour ${pt.location_name} :`, res);

    if (res.success) {
      const info = getWeatherInfo(res.data.code);

      // Si c'est une estimation basée sur le passé, on adapte l'affichage
      const approxSymbol = res.data.is_historical ? "~" : "";
      const badgeTitle = res.data.is_historical
        ? `${info.label} (${tr("weather_historical")})`
        : info.label;
      const opacityStyle = res.data.is_historical
        ? "opacity: 0.85; font-style: italic;"
        : "";

      container.innerHTML = `
        <div class="pf-weather-badge" title="${badgeTitle}" style="${opacityStyle}">
          <span class="pf-weather-icon">${info.icon}</span>
          <span>${approxSymbol}${Math.round(res.data.temp_min)}° / ${Math.round(res.data.temp_max)}°C</span>
        </div>`;
    }
  } catch (e) {
    console.error("Weather error", e);
  }
}

// ============================================================================
// FERMETURE UNIVERSELLE DES MODALES
// ============================================================================
window.addEventListener("click", function (event) {
  if (event.target.classList.contains("pf-modal")) {
    event.target.style.display = "none";
    document.body.classList.remove("no-scroll");
  }
});

// --- 1. GESTION DE LA MODALE D'ÉDITION RAPIDE ---

function openHolidayModal(mode) {
  const modal = document.getElementById("holidayModal");
  const form = document.getElementById("holidayForm");
  const btnDelete = document.getElementById("btn_delete");

  form.reset();
  document.getElementById("inp_id").value = "";
  document.getElementById("list_transport").innerHTML = "";
  document.getElementById("list_accommodation").innerHTML = "";
  document.getElementById("list_activity").innerHTML = "";

  if (mode === "add") {
    document.getElementById("modalTitle").innerText = tr("hdl_modal_title");
    btnDelete.style.display = "none";
  } else {
    document.getElementById("modalTitle").innerText = tr(
      "hdl_quick_edit_title",
    );
    btnDelete.style.display = "block";
  }

  modal.style.display = "flex";
  setTimeout(() => document.getElementById("inp_title").focus(), 100);
}

function closeHolidayModal() {
  document.getElementById("holidayModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function editHoliday(data) {
  const h = data.main;
  const modal = document.getElementById("holidayModal");

  if (!modal) {
    alert(tr("err_modal_missing"));
    return;
  }

  document.body.appendChild(modal);

  if (typeof openHolidayModal === "function") {
    openHolidayModal("edit");
  }

  modal.classList.add("open");
  modal.style.setProperty("display", "flex", "important");
  modal.style.setProperty("z-index", "999999", "important");
  document.body.classList.add("no-scroll");

  try {
    document.getElementById("inp_id").value = h.id;
    document.getElementById("inp_title").value = h.title;
    document.getElementById("inp_status").value = h.status;
    document.getElementById("inp_period").value = h.period_hint || "";
    document.getElementById("inp_start").value = h.start_date || "";
    document.getElementById("inp_end").value = h.end_date || "";

    document.getElementById("inp_food").value =
      h.budget_food > 0 ? h.budget_food : "";
    document.getElementById("inp_extra").value =
      h.budget_extra > 0 ? h.budget_extra : "";
    document.getElementById("inp_notes").value = h.notes || "";
  } catch (err) {
    console.error("Erreur champs textes :", err);
  }

  try {
    document.getElementById("list_transport").innerHTML = "";
    document.getElementById("list_accommodation").innerHTML = "";
    document.getElementById("list_activity").innerHTML = "";

    if (data.items && data.items.length > 0) {
      data.items.forEach((item) => {
        if (
          typeof addItem === "function" &&
          item.name !== "PF_TECHNICAL_POINT"
        ) {
          addItem(item.category, item.name, item.amount, item.is_paid);
        }
      });
    }
  } catch (err) {
    console.error("Erreur listes :", err);
  }
}

// --- 2. GESTION DES LISTES DYNAMIQUES DANS LA MODALE ---

function addItem(category, name = "", amount = "", isPaid = 0) {
  const container = document.getElementById("list_" + category);
  const div = document.createElement("div");

  div.style.display = "flex";
  div.style.gap = "8px";
  div.style.alignItems = "center";
  div.style.marginBottom = "10px";

  const checkedAttr = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <input type="hidden" name="items[cat][]" value="${category}">
        <input type="text" name="items[name][]" class="pf-input" placeholder="${tr("hdl_js_ph_expense_name")}" value="${name}" style="flex: 2; padding: 8px; font-size:0.9rem;" required>
        <input type="number" step="0.01" name="items[amount][]" class="pf-input" placeholder="0.00" value="${amount}" style="width: 80px; text-align: right; padding: 8px; font-size:0.9rem;">
        <label title="${tr("hdl_paid")}" style="display: flex; align-items: center; cursor: pointer; padding: 0 5px;">
            <input type="checkbox" ${checkedAttr} onchange="this.nextElementSibling.value = this.checked ? 1 : 0" style="margin:0;">
            <input type="hidden" name="items[paid][]" value="${isPaid}">
            <span style="font-size:0.75rem; margin-left:4px; font-weight:bold; color:#64748b;">${tr("hdl_paid")}</span>
        </label>
        <button type="button" onclick="this.parentElement.remove()" title="${tr("btn_delete")}" style="width: 28px; height: 28px; border: none; background: #fee2e2; color: #ef4444; border-radius: 6px; cursor: pointer; font-weight: bold; display:flex; align-items:center; justify-content:center;">
            &times;
        </button>
    `;

  container.appendChild(div);
}

function deleteHoliday() {
  if (!confirm(tr("hdl_js_confirm_del_trip"))) return;
  const form = document.getElementById("holidayForm");
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

// --- 3. GESTION DE LA CARTE ---

var map = null;

function toggleMap() {
  const modal = document.getElementById("hol-map-modal");
  if (!modal) return;
  if (modal.style.display === "flex") {
    modal.style.display = "none";
  } else {
    modal.style.display = "flex";
    setTimeout(initMap, 100);
  }
}

function initMap() {
  if (map) {
    map.invalidateSize();
    return;
  }
  if (typeof L === "undefined") return;

  map = L.map("hol-map").setView([46.6, 2.4], 4);
  L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
    {
      attribution: "© OpenStreetMap",
    },
  ).addTo(map);

  if (typeof HOL_MAP_POINTS !== "undefined") {
    HOL_MAP_POINTS.forEach((pt) => {
      const color =
        pt.status === "planned" || pt.status === "booked" ? "green" : "blue";
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

var detailMap = null;

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("tripMap")) {
    initDetailMap();
  }
});

function initDetailMap() {
  if (typeof L === "undefined" || typeof MAP_POINTS === "undefined") return;

  // 1. 🧹 NETTOYAGE PROPRE : On détruit l'ancienne instance si elle existe (Évite le bug de la souris bloquée)
  if (detailMap !== null) {
    detailMap.remove();
    detailMap = null;
  }

  const mapContainer = document.getElementById("tripMap");
  if (!mapContainer) return;

  // 2. 🛡️ BOUCLIER FIREFOX DESKTOP : Empêche le drag natif HTML5 de voler le clic
  mapContainer.style.touchAction = "none";
  mapContainer.ondragstart = function (e) {
    e.preventDefault();
  };

  // 3. 🛠️ INITIALISATION DE LA CARTE
  detailMap = L.map("tripMap", {
    tap: false, // Désactive le tap simulé (anti-warning mobile/Firefox)
    dragging: true, // Force l'autorisation du déplacement à la souris
  });

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(detailMap);

  // Cas : Aucun point
  if (MAP_POINTS.length === 0) {
    detailMap.setView([46.6, 2.4], 5); // France par défaut
    return;
  }

  const latlngs = [];
  const bounds = L.latLngBounds();

  // 4. PLACEMENT DES MARQUEURS
  MAP_POINTS.forEach((pt, index) => {
    const pos = [pt.lat, pt.lng];
    latlngs.push(pos);
    bounds.extend(pos);

    const color = "#2563eb";

    const marker = L.circleMarker(pos, {
      color: color,
      radius: window.innerWidth < 768 ? 6 : 8,
      fillOpacity: 1,
      fillColor: "white",
      weight: 3,
    }).addTo(detailMap);

    const stepLabel = window.I18N
      ? window.I18N["hdl_js_step_label"]
      : tr("hdl_js_step_label");

    marker.bindPopup(`
        <div style="text-align:center;">
            <div style="font-size:0.75rem; color:#64748b; margin-bottom:2px; font-weight:bold;">${stepLabel} ${index + 1}</div>
            <strong style="font-size:1rem; color:#0f172a;">${pt.location_name}</strong><br>
            <span style="font-weight:bold; color:${color};">${parseFloat(pt.total_amount).toFixed(2)} €</span>
        </div>
    `);

    // Animation au clic sur le marqueur
    marker.on("click", function () {
      const card = document.getElementById("step-card-" + pt.sort_order);
      if (card) {
        card.scrollIntoView({ behavior: "smooth", block: "center" });
        card.style.transition = "box-shadow 0.3s, transform 0.3s";
        card.style.boxShadow = "0 0 0 3px #3b82f6";
        card.style.transform = "scale(1.02)";
        setTimeout(() => {
          card.style.boxShadow = "";
          card.style.transform = "";
        }, 1500);
      }
    });
  });

  const mapPadding = window.innerWidth < 768 ? [20, 20] : [50, 50];

  // 5. CENTRAGE ET TRACÉS (OSRM)
  if (latlngs.length === 1) {
    detailMap.setView(latlngs[0], 12);
  } else if (latlngs.length > 1) {
    detailMap.fitBounds(bounds, { padding: mapPadding });

    const routePromises = [];

    for (let i = 0; i < latlngs.length - 1; i++) {
      const startPt = MAP_POINTS[i];
      const endPt = MAP_POINTS[i + 1];
      const coordsString = `${startPt.lng},${startPt.lat};${endPt.lng},${endPt.lat}`;

      const promise = fetch(
        `https://router.project-osrm.org/route/v1/driving/${coordsString}?overview=full&geometries=geojson`,
      )
        .then((response) => response.json())
        .then((data) => ({
          index: i,
          data: data,
          coords: [latlngs[i], latlngs[i + 1]],
        }))
        .catch((err) => ({
          index: i,
          error: true,
          coords: [latlngs[i], latlngs[i + 1]],
        }));
      routePromises.push(promise);
    }

    Promise.all(routePromises).then((results) => {
      results.sort((a, b) => a.index - b.index);

      let returnStartIndex = latlngs.length - 2;
      const customReturnStep = MAP_POINTS.findIndex((p) => p.is_return == 1);
      if (customReturnStep > 0) {
        returnStartIndex = customReturnStep;
      }

      results.forEach((res) => {
        const i = res.index;
        let routeColor = "#3b82f6";
        let routeWeight = window.innerWidth < 768 ? 4 : 6;
        let routeDash = null;

        if (i >= returnStartIndex) {
          routeColor = "#f97316";
          routeWeight = window.innerWidth < 768 ? 3 : 4;
          routeDash = "10, 10";
        }

        if (res.data && res.data.code === "Ok" && res.data.routes.length > 0) {
          const routeCoords = res.data.routes[0].geometry.coordinates.map(
            (c) => [c[1], c[0]],
          );
          L.polyline(routeCoords, {
            color: routeColor,
            weight: routeWeight,
            dashArray: routeDash,
            opacity: 0.9,
            lineCap: "round",
            lineJoin: "round",
          }).addTo(detailMap);
        } else {
          drawFallbackLine(res.coords, routeColor, routeWeight);
        }
      });
    });
  }

  // 6. LANCEMENT DE LA MÉTÉO
  if (typeof MAP_POINTS !== "undefined") {
    MAP_POINTS.forEach((pt) => {
      if (typeof loadWeatherForStep === "function") {
        loadWeatherForStep(pt);
      }
    });
  }

  // 7. FIX FINAL : Force Leaflet à recalculer sa taille une fois le DOM stabilisé
  setTimeout(() => {
    if (detailMap) {
      detailMap.invalidateSize();
    }
  }, 300);

  // Fonction utilitaire locale
  function drawFallbackLine(coords, color, weight) {
    L.polyline(coords, {
      color: color,
      weight: weight || 3,
      dashArray: "8, 8",
      opacity: 0.7,
    }).addTo(detailMap);
  }
}

function panMapTo(lat, lng) {
  if (detailMap) {
    detailMap.setView([lat, lng], 14, { animate: true });

    // 🛠️ ERGONOMIE MOBILE : Auto-scroll vers la carte si on est sur petit écran
    if (window.innerWidth < 768) {
      const mapDiv = document.getElementById("tripMap");
      if (mapDiv) {
        mapDiv.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }
  }
}

// --- LOGIQUE DE LA MODALE CHECKPOINT ---

function openCheckpointModal(mode, data = null) {
  const searchBlock = document.getElementById("cpSearchBlock");
  const formBlock = document.getElementById("formCheckpoint");
  const container = document.getElementById("cpExpensesContainer");
  const btnDel = document.getElementById("btnDeleteCp");

  container.innerHTML = "";

  if (document.getElementById("cp_start_date"))
    document.getElementById("cp_start_date").value = "";
  if (document.getElementById("cp_end_date"))
    document.getElementById("cp_end_date").value = "";
  document.getElementById("searchPlaceInput").value = "";
  document.getElementById("searchResults").innerHTML = "";

  searchBlock.style.display = "block";

  if (mode === "add") {
    document.getElementById("cpModalTitle").innerText = tr("hdl_btn_add_step");
    formBlock.style.display = "none";
    btnDel.style.display = "none";
    document.getElementById("cp_old_sort_order").value = "";
    document.getElementById("cp_name").value = "";
    addCpExpenseLine();
    document.getElementById("cp_is_return").checked = false;
  } else if (mode === "edit" && data) {
    document.getElementById("cpModalTitle").innerText = tr("hdl_js_edit_step");
    formBlock.style.display = "block";
    btnDel.style.display = "block";

    document.getElementById("cp_lat").value = data.lat;
    document.getElementById("cp_lng").value = data.lng;
    document.getElementById("cp_old_sort_order").value = data.sort_order;
    document.getElementById("cp_name").value = data.location_name;
    document.getElementById("cp_start_date").value = data.step_start_date || "";
    document.getElementById("cp_end_date").value = data.step_end_date || "";
    document.getElementById("cp_is_return").checked = data.is_return == 1;

    if (data.items && data.items.length > 0) {
      let visibleCount = 0;
      data.items.forEach((it) => {
        if (it.name !== "PF_TECHNICAL_POINT") {
          addCpExpenseLine(
            it.category,
            it.name,
            it.amount,
            it.is_paid,
            it.notes || "",
            it.id || "",
            it.item_date || "",
            it.item_time || "",
            it.duration || 1,
          );
          visibleCount++;
        }
      });
      if (visibleCount === 0) addCpExpenseLine();
    } else {
      addCpExpenseLine();
    }
  }
  document.getElementById("checkpointModal").style.display = "flex";
  document.body.classList.add("no-scroll");
}

function searchPlace() {
  const q = document.getElementById("searchPlaceInput").value.trim();
  if (q.length < 3) return;

  const resultsDiv = document.getElementById("searchResults");
  resultsDiv.innerHTML = `<span style="color:#64748b; font-size:0.85rem;">${tr("hdl_js_search_loading")}</span>`;

  fetch(
    "/modules/holidays/includes/api/geocode.php?limit=5&q=" +
      encodeURIComponent(q),
  )
    .then((res) => res.json())
    .then((data) => {
      resultsDiv.innerHTML = "";
      if (data.error || !data.results || data.results.length === 0) {
        resultsDiv.innerHTML = `<span style="color:#ef4444; font-size:0.85rem;">${tr("hdl_js_no_result")}</span>`;
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
      resultsDiv.innerHTML = `<span style="color:#ef4444; font-size:0.85rem;">${tr("hdl_js_network_error")}</span>`;
    });
}

function selectPlace(lat, lng, fullName) {
  document.getElementById("cp_lat").value = lat;
  document.getElementById("cp_lng").value = lng;
  document.getElementById("cp_name").value = fullName.split(",")[0].trim();
  document.getElementById("formCheckpoint").style.display = "block";
}

function addCpExpenseLine(
  category = "accommodation",
  name = "",
  amount = "",
  isPaid = 0,
  notes = "",
  itemId = "",
  itemDate = "",
  itemTime = "",
  itemDur = 1,
) {
  const container = document.getElementById("cpExpensesContainer");
  const div = document.createElement("div");
  div.className = "hol-form-row";
  const isChecked = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <div class="hol-form-inner">
            <select name="items[cat][]" class="pf-input hol-form-select">
                <option value="accommodation" ${category === "accommodation" ? "selected" : ""}>🏨</option>
                <option value="transport" ${category === "transport" ? "selected" : ""}>🚗</option>
                <option value="activity" ${category === "activity" ? "selected" : ""}>🎫</option>
            </select>
            <input type="text" name="items[name][]" class="pf-input hol-form-text" placeholder="${tr("hdl_js_ph_expense_name")}" value="${name}">
            <input type="number" step="0.01" name="items[amount][]" class="pf-input hol-form-number" placeholder="0.00" value="${amount}">
            
            <label class="hol-form-paid-label" title="${tr("hdl_paid")}">
                <input type="checkbox" ${isChecked} onchange="this.nextElementSibling.value = this.checked ? 1 : 0">
                <input type="hidden" name="items[paid][]" value="${isPaid}">
                <span class="hol-form-paid-text">${tr("hdl_paid")}</span>
            </label>
            <button type="button" class="btn-remove-expense" onclick="this.parentElement.parentElement.remove()" title="${tr("btn_delete")}">&times;</button>
        </div>
        <div class="hol-form-subrow">
            <input type="text" name="items[notes][]" class="pf-input hol-form-notes-input hol-form-notes-full" placeholder="${tr("hdl_ph_notes")}" value="${notes}">
        </div>
        <input type="hidden" name="items[id][]" value="${itemId}">
        <input type="hidden" name="items[date][]" value="${itemDate}">
        <input type="hidden" name="items[time][]" value="${itemTime}">
        <input type="hidden" name="items[duration][]" value="${itemDur}">
    `;
  container.appendChild(div);
}

function deleteCheckpoint() {
  if (!confirm(tr("hdl_js_confirm_del_step"))) return;
  const form = document.getElementById("formCheckpoint");
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

// ============================================================================
// 5. RÉORDONNANCEMENT DES ÉTAPES (DRAG & DROP PC + FLÈCHES MOBILE)
// ============================================================================

// On sort cette fonction pour pouvoir l'appeler depuis les boutons fléchés sur mobile
function saveCheckpointOrder() {
  const locations = [
    ...document.querySelectorAll(".hol-checkpoint-draggable"),
  ].map((el) => el.getAttribute("data-location"));
  const holidayId = document.querySelector('input[name="holiday_id"]').value;

  const formData = new FormData();
  formData.append("holiday_id", holidayId);
  formData.append("locations", JSON.stringify(locations));

  fetch("/modules/holidays/includes/api/reorder_checkpoints.php", {
    method: "POST",
    body: formData,
  }).then(() => window.location.reload());
}

// Fonction appelée par les flèches Haut/Bas sur mobile
function moveStepMobile(btn, direction) {
  const item = btn.closest(".hol-checkpoint-draggable");
  const container = item.parentElement;

  if (
    direction === -1 &&
    item.previousElementSibling &&
    item.previousElementSibling.classList.contains("hol-checkpoint-draggable")
  ) {
    container.insertBefore(item, item.previousElementSibling);
    saveCheckpointOrder();
  } else if (
    direction === 1 &&
    item.nextElementSibling &&
    item.nextElementSibling.classList.contains("hol-checkpoint-draggable")
  ) {
    container.insertBefore(item, item.nextElementSibling.nextElementSibling);
    saveCheckpointOrder();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const checkpoints = document.querySelectorAll(".hol-checkpoint-draggable");
  const container = checkpoints[0]?.parentElement;
  if (!container) return;

  const isMobile = window.innerWidth <= 768;
  let draggedItem = null;

  checkpoints.forEach((item) => {
    // Si on est sur mobile, on supprime l'attribut draggable pour éviter les conflits de scroll
    if (isMobile) {
      item.removeAttribute("draggable");
      return;
    }

    item.addEventListener("dragstart", function (e) {
      draggedItem = this;
      setTimeout(() => (this.style.opacity = "0.4"), 0);
    });

    item.addEventListener("dragend", function () {
      setTimeout(() => {
        this.style.opacity = "1";
        draggedItem = null;
        saveCheckpointOrder();
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
});

// ============================================================================
// MOTEUR DRAG & DROP DU PLANNING
// ============================================================================

function closePlanningModal() {
  document.getElementById("planningModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function openPlanningModal(step) {
  document.getElementById("planningModalTitle").innerText =
    tr("hdl_planning_title") + " : " + step.location_name;
  const container = document.getElementById("planningContainer");
  selectedItemIdForMove = null;

  let validItems = step.items.filter((it) => it.name !== "PF_TECHNICAL_POINT");

  if (!step.step_start_date || !step.step_end_date) {
    container.innerHTML = `<div style="text-align:center; padding:40px;"><h3>${tr("hdl_js_missing_dates_title")}</h3><p style="color:#64748b;">${tr("hdl_js_missing_dates_msg")}</p></div>`;
    document.getElementById("planningModal").style.display = "flex";
    return;
  }

  let datesToDisplay = [];
  let curr = new Date(step.step_start_date);
  let end = new Date(step.step_end_date);
  while (curr <= end) {
    datesToDisplay.push(curr.toISOString().split("T")[0]);
    curr.setDate(curr.getDate() + 1);
  }

  let html = `
        <div class="hol-planning-layout">
            <div class="hol-unmapped-zone" id="unmapped-pool" 
                 ondragover="allowDrop(event)" ondrop="handleDropEvent(event, '', '')"
                 onclick="handleZoneTap(event, '', '')">
                <div class="hol-unmapped-title" style="width:100%;">📥 ${tr("hdl_to_place")}</div>
            </div>
            <div class="hol-calendar-zone">
    `;

  datesToDisplay.forEach((dateStr) => {
    const dObj = new Date(dateStr);
    const dayName = dObj.toLocaleDateString(currentLang, { weekday: "short" });
    const dayNum = dObj.toLocaleDateString(currentLang, {
      day: "numeric",
      month: "short",
    });

    html += `
            <div class="hol-day-column">
                <div class="hol-calendar-day-header">
                    <div class="hol-cal-weekday">${dayName}</div>
                    <div class="hol-cal-date">${dayNum}</div>
                    <div id="plan-weather-${dateStr}" style="margin-top: 5px; display: flex; justify-content: center; min-height: 20px;"></div>
                </div>
                <div class="hol-time-slots-container">
        `;

    for (let h = 8; h <= 22; h++) {
      let hourStr = h.toString().padStart(2, "0") + ":00";
      html += `
                <div class="hol-time-slot" data-date="${dateStr}" data-time="${hourStr}" 
                     ondragover="allowDrop(event)" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)" 
                     ondrop="handleDropEvent(event, '${dateStr}', '${hourStr}')"
                     onclick="handleZoneTap(event, '${dateStr}', '${hourStr}')">
                    <span class="hol-slot-label">${hourStr}</span>
                </div>
            `;
    }
    html += `</div></div>`;
  });
  html += `</div></div>`;
  container.innerHTML = html;

  // 1. On détecte si on est sur mobile juste avant la boucle
  const isMobile = window.innerWidth <= 768;
  const dragAttr = isMobile ? "" : 'draggable="true"';

  validItems.forEach((it) => {
    let icon = "🏷️";
    let catClass = "cat-activity";
    if (it.category === "accommodation") {
      icon = "🏨";
      catClass = "cat-accommodation";
    }
    if (it.category === "transport") {
      icon = "🚗";
      catClass = "cat-transport";
    }

    const dur = it.duration || 1;
    const noteHtml = it.notes
      ? `<div class="hol-drag-note">${it.notes}</div>`
      : "";

    // 2. MODIFICATION ICI : On remplace le texte en dur draggable="true" par la variable ${dragAttr}
    const elHtml = `
            <div class="hol-drag-item ${catClass}" ${dragAttr} 
                 id="drag-item-${it.id}" data-id="${it.id}" 
                 style="--duration: ${dur};"
                 ondragstart="dragStart(event)" onclick="handleItemTap(event, ${it.id})">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 5px;">
                    <div class="hol-drag-title" style="flex:1;">${icon} ${it.name}</div>
                    <div class="hol-item-duration-controls">
                        <button class="hol-dur-btn" onclick="changeDuration(event, ${it.id}, -1)">-</button>
                        <span class="hol-dur-text" id="dur-text-${it.id}">${dur}h</span>
                        <button class="hol-dur-btn" onclick="changeDuration(event, ${it.id}, 1)">+</button>
                    </div>
                </div>
                ${noteHtml}
            </div>
        `;

    if (it.item_date && it.item_time && datesToDisplay.includes(it.item_date)) {
      const hourPrefix = it.item_time.substring(0, 2) + ":00";
      const targetSlot = container.querySelector(
        `.hol-time-slot[data-date="${it.item_date}"][data-time="${hourPrefix}"]`,
      );
      if (targetSlot) {
        targetSlot.insertAdjacentHTML("beforeend", elHtml);
        return;
      }
    }
    document
      .getElementById("unmapped-pool")
      .insertAdjacentHTML("beforeend", elHtml);
  });

  document.getElementById("planningModal").style.display = "flex";
  document.body.classList.add("no-scroll");

  datesToDisplay.forEach((dateStr) => {
    loadWeatherForPlanning(step.lat, step.lng, dateStr);
  });
}

function changeDuration(e, itemId, delta) {
  e.stopPropagation();
  const itemEl = document.getElementById("drag-item-" + itemId);
  let currentDur = parseInt(itemEl.style.getPropertyValue("--duration")) || 1;
  let newDur = currentDur + delta;
  if (newDur < 1) newDur = 1;
  if (newDur > 12) newDur = 12;
  itemEl.style.setProperty("--duration", newDur);
  document.getElementById(`dur-text-${itemId}`).innerText = newDur + "h";
  updateItemMemory(itemId, { duration: newDur });
  const formData = new FormData();
  formData.append("action", "update_item_duration");
  formData.append("item_id", itemId);
  formData.append("duration", newDur);
  fetch("/modules/holidays/includes/api/save_checkpoint.php", {
    method: "POST",
    body: formData,
  });
}

function handleItemTap(e, itemId) {
  e.stopPropagation();
  document
    .querySelectorAll(".hol-drag-item")
    .forEach((el) => el.classList.remove("selected-for-move"));
  if (selectedItemIdForMove === itemId) {
    selectedItemIdForMove = null;
  } else {
    selectedItemIdForMove = itemId;
    document
      .getElementById("drag-item-" + itemId)
      .classList.add("selected-for-move");
  }
}

function handleZoneTap(e, dateStr, timeStr) {
  if (selectedItemIdForMove) {
    handleDropLogic(selectedItemIdForMove, dateStr, timeStr, e.currentTarget);
    selectedItemIdForMove = null;
    document
      .querySelectorAll(".hol-drag-item")
      .forEach((el) => el.classList.remove("selected-for-move"));
  }
}

function dragStart(e) {
  e.dataTransfer.setData("text/plain", e.target.id);
  e.dataTransfer.effectAllowed = "move";
}
function allowDrop(e) {
  e.preventDefault();
}
function dragEnter(e) {
  e.preventDefault();
  let s = e.target.closest(".hol-time-slot");
  if (s) s.classList.add("drag-over");
}
function dragLeave(e) {
  let s = e.target.closest(".hol-time-slot");
  if (s) s.classList.remove("drag-over");
}

function handleDropEvent(e, dateStr, timeStr) {
  e.preventDefault();
  let slot = e.target.closest(".hol-time-slot");
  if (slot) slot.classList.remove("drag-over");
  const idStr = e.dataTransfer.getData("text/plain");
  const itemId = idStr.replace("drag-item-", "");
  const dropZone = slot || document.getElementById("unmapped-pool");
  handleDropLogic(itemId, dateStr, timeStr, dropZone);
}

function handleDropLogic(itemId, dateStr, timeStr, dropZone) {
  const draggedEl = document.getElementById("drag-item-" + itemId);
  if (dropZone && draggedEl) {
    dropZone.appendChild(draggedEl);
    updateItemMemory(itemId, { item_date: dateStr, item_time: timeStr });
    const formData = new FormData();
    formData.append("action", "update_item_datetime");
    formData.append("item_id", itemId);
    formData.append("item_date", dateStr);
    formData.append("item_time", timeStr);
    fetch("/modules/holidays/includes/api/save_checkpoint.php", {
      method: "POST",
      body: formData,
    });
  }
}

function updateItemMemory(itemId, changes) {
  MAP_POINTS.forEach((step) => {
    let item = step.items.find((i) => i.id == itemId);
    if (item) Object.assign(item, changes);
  });
}

function saveItemDateTime(itemId, dateStr, timeStr) {
  const formData = new FormData();
  formData.append("action", "update_item_datetime");
  formData.append("item_id", itemId);
  formData.append("item_date", dateStr);
  formData.append("item_time", timeStr);
  fetch("/modules/holidays/includes/api/save_checkpoint.php", {
    method: "POST",
    body: formData,
  }).catch((err) => console.error("Erreur:", err));
}

// ============================================================================
// MÉTÉO SPÉCIFIQUE AU HEADER DU PLANNING
// ============================================================================
async function loadWeatherForPlanning(lat, lng, dateStr) {
  const container = document.getElementById(`plan-weather-${dateStr}`);
  if (!container || !lat || !lng) return;

  try {
    const resp = await fetch(
      `/modules/holidays/includes/api/get_weather.php?lat=${lat}&lng=${lng}&date=${dateStr}`,
    );
    const res = await resp.json();

    if (res.success) {
      const info = getWeatherInfo(res.data.code);
      const approxSymbol = res.data.is_historical ? "~" : "";

      container.innerHTML = `
        <div class="pf-weather-badge" style="font-size: 0.65rem; padding: 2px 6px; ${res.data.is_historical ? "opacity: 0.8;" : ""}" title="${info.label}">
          <span class="pf-weather-icon">${info.icon}</span>
          <span>${approxSymbol}${Math.round(res.data.temp_min)}° / ${Math.round(res.data.temp_max)}°C</span>
        </div>
      `;
    }
  } catch (e) {
    console.error("Erreur météo planning", e);
  }
}
