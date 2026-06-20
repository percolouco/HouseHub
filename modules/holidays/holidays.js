// ============================================================================
// FONCTION DE TRADUCTION JS & LANGUE COURANTE
// ============================================================================
function tr(key) {
  return window.I18N && window.I18N[key] ? window.I18N[key] : key;
}

var currentLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
var selectedItemIdForMove = null;

// ============================================================================
// UTILITAIRES MÉTÉO
// ============================================================================
function getWeatherInfo(code) {
  if (code === 0) return { icon: "☀️", label: tr("weather_sunny") };
  if ([1, 2].includes(code)) return { icon: "🌤️", label: tr("weather_sunny") };
  if ([3, 45, 48].includes(code))
    return { icon: "☁️", label: tr("weather_cloudy") };
  if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code))
    return { icon: "🌧️", label: tr("weather_rainy") };
  if ([71, 73, 75, 77, 85, 86].includes(code))
    return { icon: "❄️", label: tr("weather_snowy") };
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

    if (res.success) {
      const info = getWeatherInfo(res.data.code);
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

// ============================================================================
// 1. GESTION DE LA MODALE D'ÉDITION RAPIDE (BASES VOYAGE)
// ============================================================================
function openHolidayModal(mode) {
  const modal = document.getElementById("holidayModal");
  const form = document.getElementById("holidayForm");
  const btnDelete = document.getElementById("btn_delete");

  form.reset();
  document.getElementById("inp_id").value = "";

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
  if (!modal) return;

  openHolidayModal("edit");

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

  const vehicleInput = document.getElementById("inp_vehicle_id");
  if (vehicleInput) {
    vehicleInput.value = h.vehicle_id || "";
  }
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

// ============================================================================
// 4. GESTION DE LA CARTE DÉTAILLÉE (ROADTRIP) ET TRACÉS OSRM
// ============================================================================
var detailMap = null;

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("tripMap")) {
    initDetailMap();
  }
});

function initDetailMap() {
  if (typeof L === "undefined" || typeof MAP_POINTS === "undefined") return;

  // 1. Nettoyage de l'ancienne carte
  if (detailMap !== null) {
    detailMap.remove();
    detailMap = null;
  }

  const mapContainer = document.getElementById("tripMap");
  if (!mapContainer) return;

  mapContainer.style.touchAction = "none";
  mapContainer.ondragstart = function (e) {
    e.preventDefault();
  };

  detailMap = L.map("tripMap", { tap: false, dragging: true });

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(detailMap);

  if (MAP_POINTS.length === 0) {
    detailMap.setView([46.6, 2.4], 5);
    return;
  }

  const latlngs = [];
  const bounds = L.latLngBounds();

  // 2. Placement des marqueurs d'étapes
  MAP_POINTS.forEach((pt, index) => {
    const pos = [pt.lat, pt.lng];
    latlngs.push(pos);
    bounds.extend(pos);

    const marker = L.circleMarker(pos, {
      color: "#2563eb",
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
            <span style="font-weight:bold; color:#2563eb;">${parseFloat(pt.total_amount).toFixed(2)} €</span>
        </div>
    `);

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

  // 3. Tracés OSRM et calculs
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

      // 🔥 NOUVEAU : Compteurs et HTML de la modale
      let totalTripDistance = 0;
      let totalTripDuration = 0; // en secondes
      let transitDetailsHtml = "";

      let returnStartIndex = latlngs.length - 2;
      if (
        typeof window.GLOBAL_RETURN_STEP_ID !== "undefined" &&
        window.GLOBAL_RETURN_STEP_ID !== null
      ) {
        const customReturnStep = MAP_POINTS.findIndex(
          (p) => p.sort_order == window.GLOBAL_RETURN_STEP_ID,
        );
        if (customReturnStep > 0) returnStartIndex = customReturnStep;
      }

      results.forEach((res) => {
        const i = res.index;
        let routeColor = i >= returnStartIndex ? "#f97316" : "#3b82f6";
        let routeWeight =
          window.innerWidth < 768
            ? i >= returnStartIndex
              ? 3
              : 4
            : i >= returnStartIndex
              ? 4
              : 6;
        let routeDash = i >= returnStartIndex ? "10, 10" : null;

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

          // CALCULS
          const distanceKm = res.data.routes[0].distance / 1000;
          const durationSec = res.data.routes[0].duration; // Durée en secondes

          totalTripDistance += distanceKm;
          totalTripDuration += durationSec;

          const fuelL100 = window.VEHICLE_CONSUMPTION || 7;
          const fuelPrice = window.FUEL_PRICE || 1.85;
          const cost = (distanceKm / 100) * fuelL100 * fuelPrice;

          // 1. ON DÉCLARE LES POINTS D'ABORD
          const startPt = MAP_POINTS[res.index];
          const endPt = MAP_POINTS[res.index + 1];

          // 2. ON SAUVEGARDE EN MÉMOIRE ENSUITE (Correction du bug !)
          window.TRANSIT_DATA = window.TRANSIT_DATA || {};
          window.TRANSIT_DATA[endPt.sort_order] = {
            sec: durationSec,
            from: startPt.location_name,
            cost: cost,
          };

          // 🔥 CONSTRUCTION DU CONTENU DE LA MODALE
          transitDetailsHtml += `
            <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-light);">
                <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-main); margin-bottom: 4px;">
                    📍 ${startPt.location_name} ➔ ${endPt.location_name}
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
                    <span>🚗 ${Math.round(distanceKm)} km &nbsp;•&nbsp; ⏱️ ${formatDuration(durationSec)}</span>
                    <strong style="color: var(--primary);">⛽ ${cost.toFixed(2)} €</strong>
                </div>
            </div>
          `;

          // INJECTION DANS LA CARTE (Sous l'étape)
          const targetCard = document.getElementById(
            "step-card-" + endPt.sort_order,
          );
          if (targetCard) {
            targetCard
              .querySelectorAll(".transit-auto-info")
              .forEach((el) => el.remove());
            const rawLocationName = startPt.location_name;
            const safeLocationName = rawLocationName.replace(/'/g, "\\'");
            const expenseDesc = `Essence depuis ${rawLocationName}`;
            const isAlreadyAdded = endPt.items.some(
              (it) => it.name === expenseDesc,
            );

            const summaryHtml = `
              <div class="transit-auto-info" style="font-size: 0.8rem; color: var(--text-muted); padding: 4px 0 10px 42px; display: flex; align-items: center; gap: 8px;">
                  🚗 ${Math.round(distanceKm)} km 
                  <span style="opacity: 0.5;">|</span> 
                  <strong>⛽ ~${cost.toFixed(2)} €</strong>
                  ${!isAlreadyAdded ? `<button type="button" style="background:none; border:none; color:var(--primary); cursor:pointer; font-weight:600; font-size:0.8rem; padding:0; margin-left: 5px;" onclick="addQuickTransitExpense(${document.querySelector('input[name="holiday_id"]').value}, ${endPt.sort_order}, ${cost.toFixed(2)}, 'Essence depuis ${safeLocationName}', this, ${durationSec})">+ Ajouter</button>` : `<span style="color:var(--success); font-weight:bold; margin-left: 5px;" title="Dépense déjà ajoutée à cette étape">✓ Ajouté</span>`}
              </div>`;

            const cpHeader = targetCard.querySelector(".hol-cp-header");
            if (cpHeader) cpHeader.insertAdjacentHTML("afterend", summaryHtml);
          }
        } else {
          drawFallbackLine(res.coords, routeColor, routeWeight);
        }
      });

      // 🔥 AFFICHAGE DES TOTAUX (KM + TEMPS) EN HAUT DE PAGE
      const distEl = document.getElementById("global_total_distance");
      const timeEl = document.getElementById("global_total_duration");
      const distBlock = document.getElementById("block_total_distance");

      if (distEl && timeEl && distBlock) {
        distEl.innerText = Math.round(totalTripDistance);
        timeEl.innerText = formatDuration(totalTripDuration);
        distBlock.style.display = "block";
      }

      // 🔥 INJECTION DU HTML DANS LA MODALE
      const modalContainer = document.getElementById("transitDetailsContainer");
      if (modalContainer) {
        modalContainer.innerHTML =
          transitDetailsHtml ||
          '<p style="text-align:center; color:var(--text-muted);">Aucun trajet calculé.</p>';
      }
    });

    // 5. Fix Leaflet Resize
    setTimeout(() => {
      if (detailMap) {
        detailMap.invalidateSize();
      }
    }, 300);

    function drawFallbackLine(coords, color, weight) {
      L.polyline(coords, {
        color: color,
        weight: weight || 3,
        dashArray: "8, 8",
        opacity: 0.7,
      }).addTo(detailMap);
    }
  }
}

function panMapTo(lat, lng) {
  if (detailMap) {
    detailMap.setView([lat, lng], 14, { animate: true });

    if (window.innerWidth < 768) {
      const mapDiv = document.getElementById("tripMap");
      if (mapDiv) {
        mapDiv.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }
  }
}

// ============================================================================
// 5. LOGIQUE DE LA MODALE CHECKPOINT (ÉTAPES)
// ============================================================================
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
  if (document.getElementById("searchPlaceInput"))
    document.getElementById("searchPlaceInput").value = "";
  if (document.getElementById("searchResults"))
    document.getElementById("searchResults").innerHTML = "";

  searchBlock.style.display = "block";

  if (mode === "add") {
    document.getElementById("cpModalTitle").innerText = tr("hdl_btn_add_step");
    formBlock.style.display = "none";
    btnDel.style.display = "none";
    document.getElementById("cp_old_sort_order").value = "";
    document.getElementById("cp_name").value = "";

    if (document.getElementById("cp_step_type")) {
      document.getElementById("cp_step_type").value = "stop";
      toggleStepDates("stop");
    }
    if (document.getElementById("cp_set_as_return")) {
      document.getElementById("cp_set_as_return").checked = false;
    }

    addCpExpenseLine();
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

    // 🔥 PRE-REMPLISSAGE DU TYPE D'ÉTAPE ET UI DATES
    if (document.getElementById("cp_step_type")) {
      const type = data.step_type || "stop";
      document.getElementById("cp_step_type").value = type;
      toggleStepDates(type);
    }

    // 🔥 PRE-REMPLISSAGE DE LA CASE RETOUR BASEE SUR LA GLOBALE
    if (document.getElementById("cp_set_as_return")) {
      document.getElementById("cp_set_as_return").checked =
        window.GLOBAL_RETURN_STEP_ID == data.sort_order;
    }

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
            <select name="items[context][]" class="pf-input hol-form-select" style="width:auto; margin-left:5px; font-size:0.75rem;">
                <option value="local">📍 Sur place</option>
                <option value="transit">🛣️ Transit</option>
            </select>
            <input type="text" name="items[name][]" class="pf-input hol-form-text" placeholder="${tr("hdl_js_ph_expense_name")}" value="${name}">
            <input type="number" step="0.01" name="items[amount][]" class="pf-input hol-form-number" placeholder="0.00" value="${amount}">
            
            <label class="hol-form-paid-label" title="${tr("hdl_paid")}">
                <input type="checkbox" ${isChecked} onchange="this.nextElementSibling.value = this.checked ? 1 : 0">
                <input type="hidden" name="items[paid][]" value="${isPaid}">
                <span class="hol-form-paid-text">${tr("hdl_paid")}</span>
            </label>
          <button type="button" class="btn-icon-action delete btn-remove-expense" onclick="this.parentElement.parentElement.remove()" title="${tr("btn_delete")}">🗑️</button>        </div>
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
// 6. REORDONNANCEMENT DES ÉTAPES (DRAG & DROP PC + MOBILE)
// ============================================================================
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
// 7. MOTEUR DRAG & DROP DU PLANNING CARNET DE BORD
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

  window.CURRENT_PLANNING_STEP = step; // Mémorise l'étape en cours

  if (window.TRANSIT_DATA && window.TRANSIT_DATA[step.sort_order]) {
    // Est-ce qu'on a déjà planifié ou ajouté ce trajet ?
    let hasTransit = validItems.some((it) => it.expense_context === "transit");
    if (!hasTransit) {
      const tData = window.TRANSIT_DATA[step.sort_order];
      const h = Math.max(1, Math.round(tData.sec / 3600)); // Arrondi en heures
      validItems.push({
        id: "virtual-transit",
        name: `Essence depuis ${tData.from}`, // Garde ce nom pour lier avec le budget
        category: "transport",
        expense_context: "transit",
        duration: h,
        notes: `Trajet GPS (${Math.round(tData.sec / 60)} min). Déplacez pour planifier la route.`,
        is_virtual: true,
      });
    }
  }

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

    const isVirtual = it.is_virtual === true;
    const isTransit = it.expense_context === "transit";

    const durControls =
      isVirtual || isTransit
        ? `<span class="hol-dur-text" style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:bold;">${dur}h (Auto)</span>`
        : `<button class="hol-dur-btn" onclick="changeDuration(event, '${it.id}', -1)">-</button>
           <span class="hol-dur-text" id="dur-text-${it.id}">${dur}h</span>
           <button class="hol-dur-btn" onclick="changeDuration(event, '${it.id}', 1)">+</button>`;

    const bgStyle = isVirtual
      ? "background: repeating-linear-gradient(45deg, #ffffff, #ffffff 10px, #f8fafc 10px, #f8fafc 20px); border: 2px dashed var(--primary);"
      : "";
    const visualName = isTransit
      ? `🛣️ Trajet & ` + it.name
      : `${icon} ${it.name}`;

    const elHtml = `
            <div class="hol-drag-item ${catClass}" ${dragAttr} 
                 id="drag-item-${it.id}" data-id="${it.id}" 
                 style="--duration: ${dur}; ${bgStyle}"
                 ondragstart="dragStart(event)" onclick="handleItemTap(event, '${it.id}')">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 5px;">
                    <div class="hol-drag-title" style="flex:1;">${visualName}</div>
                    <div class="hol-item-duration-controls">
                        ${durControls}
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
  if (itemId === "virtual-transit") {
    const step = window.CURRENT_PLANNING_STEP;
    const tData = window.TRANSIT_DATA[step.sort_order];
    const holidayId = document.querySelector('input[name="holiday_id"]').value;
    const h = Math.max(1, Math.round(tData.sec / 3600));

    const fd = new FormData();
    fd.append("action", "add_single_item");
    fd.append("holiday_id", holidayId);
    fd.append("sort_order", step.sort_order);
    fd.append("category", "transport");
    fd.append("context", "transit");
    fd.append("name", `Essence depuis ${tData.from}`);
    fd.append("amount", tData.cost);
    fd.append("duration", h);
    fd.append("item_date", dateStr);
    fd.append("item_time", timeStr);

    fetch("/modules/holidays/includes/api/save_checkpoint.php", {
      method: "POST",
      body: fd,
    }).then(() => window.location.reload());
    return;
  }
}

function updateItemMemory(itemId, changes) {
  MAP_POINTS.forEach((step) => {
    let item = step.items.find((i) => i.id == itemId);
    if (item) Object.assign(item, changes);
  });
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

// Gère l'affichage des dates dans la modale d'étape
function toggleStepDates(type) {
  const grpEnd = document.getElementById("grp_end_date");
  const lblStart = document.getElementById("lbl_start_date");

  if (type === "origin") {
    grpEnd.style.display = "none";
    lblStart.innerText = "📅 Date de départ";
  } else if (type === "destination") {
    grpEnd.style.display = "none";
    lblStart.innerText = "📅 Date d'arrivée";
  } else {
    grpEnd.style.display = "block";
    lblStart.innerText = tr("hdl_label_arrival");
  }
}

// Ajout magique d'une dépense d'essence SÉCURISÉE et INSTANTANÉE
function addQuickTransitExpense(
  holidayId,
  sortOrder,
  amount,
  description,
  btnElement,
  durationSec = 3600,
) {
  if (
    !confirm(
      `Ajouter une dépense de carburant de ${amount}€ pour cette étape ?`,
    )
  )
    return;

  // 1. UI OPTIMISTE : On change visuellement le bouton tout de suite sans attendre le serveur
  const parentContainer = btnElement.parentElement;
  if (parentContainer) {
    parentContainer.innerHTML = `<span style="color:var(--success); font-weight:bold; margin-left: 5px;">✓ Ajouté</span>`;
  }

  // 2. On met à jour discrètement le compteur global en haut de page (+ montant)
  const totalTransitEl = document.querySelector(".hol-summary-value strong");
  if (totalTransitEl) {
    const currentTotal =
      parseFloat(totalTransitEl.innerText.replace(" €", "").replace(" ", "")) ||
      0;
    totalTransitEl.innerText = Math.round(currentTotal + amount) + " €";
  }

  const fd = new FormData();
  fd.append("action", "add_single_item");
  fd.append("holiday_id", holidayId);
  fd.append("sort_order", sortOrder);
  fd.append("category", "transport");
  fd.append("name", description);
  fd.append("amount", amount);
  fd.append("context", "transit");

  // 🔥 On sécurise la durée en base de données
  const h = Math.max(1, Math.round(durationSec / 3600));
  fd.append("duration", h);

  fetch("/modules/holidays/includes/api/save_checkpoint.php", {
    method: "POST",
    body: fd,
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.success) alert("Erreur : " + data.error);
    });
}

// Permet de modifier le prix du carburant à la volée
function updateFuelPrice() {
  const currentPrice = window.FUEL_PRICE || 1.85;
  let newPrice = prompt(
    "Définit le prix du carburant estimé (€/L) pour tes trajets :",
    currentPrice,
  );

  if (newPrice !== null) {
    newPrice = parseFloat(newPrice.replace(",", "."));
    if (!isNaN(newPrice) && newPrice > 0) {
      localStorage.setItem("holidays_fuel_price", newPrice);
      window.location.reload();
    } else {
      alert("Prix invalide.");
    }
  }
}

// Formatte les secondes en "XXhYY" ou "YYmin"
function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return "0min";
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h > 0) {
    return `${h}h${m.toString().padStart(2, "0")}`;
  }
  return `${m}min`;
}

// Ouvre et ferme la modale des détails (L'œil)
function openTransitModal() {
  document.getElementById("transitModal").style.display = "flex";
  document.body.classList.add("no-scroll");
}

function closeTransitModal() {
  document.getElementById("transitModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}
