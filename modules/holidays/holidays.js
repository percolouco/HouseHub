window.PLANNING_MODIFIED = false;

// Variable globale pour Flatpickr
let cpDateRangePicker = null;

// Écouteur pour activer le thème sombre de Flatpickr selon le thème global HouseHub
document.addEventListener("DOMContentLoaded", () => {
  const checkDarkTheme = () => {
    const isDark =
      document.documentElement.getAttribute("data-theme") === "dark";
    const themeLink = document.getElementById("flatpickr-dark-theme");
    if (themeLink) themeLink.disabled = !isDark;
  };
  checkDarkTheme();
  document
    .getElementById("theme-toggle")
    ?.addEventListener("click", () => setTimeout(checkDarkTheme, 50));
});

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

    // Réinitialisation du calendrier global
    document.getElementById("inp_start").value = "";
    document.getElementById("inp_end").value = "";
    initHolFlatpickr("", "");
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
  document.getElementById("inp_food").value =
    h.budget_food > 0 ? h.budget_food : "";
  document.getElementById("inp_extra").value =
    h.budget_extra > 0 ? h.budget_extra : "";

  if (document.getElementById("inp_notes")) {
    document.getElementById("inp_notes").value = h.notes || "";
  }

  const vehicleInput = document.getElementById("inp_vehicle_id");
  if (vehicleInput) {
    vehicleInput.value = h.vehicle_id || "";
  }

  // Initialisation des dates
  const startD = h.start_date || "";
  const endD = h.end_date || "";
  document.getElementById("inp_start").value = startD;
  document.getElementById("inp_end").value = endD;

  initHolFlatpickr(startD, endD);
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

      let totalTripDistance = 0;
      let totalTripDuration = 0; // en secondes
      let totalFuelCost = 0; // Coût exact accumulé
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

          totalFuelCost += cost;

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

          // 🔥 DÉTECTION DES PÉAGES MANUELS DE L'ÉTAPE
          let stepTollCost = 0;
          if (endPt.items && endPt.items.length > 0) {
            endPt.items.forEach((it) => {
              const normalizedName = (it.name || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
              if (
                it.category === "transport" &&
                normalizedName.includes("peage")
              ) {
                stepTollCost += parseFloat(it.amount);
              }
            });
          }

          let tollHtml =
            stepTollCost > 0
              ? `<strong style="color: var(--text-main);">💳 ${stepTollCost.toFixed(2)} €</strong>`
              : "";

          // 🔥 CONSTRUCTION DU CONTENU DE LA MODALE
          transitDetailsHtml += `
            <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-light); display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-main);">
                        📍 ${startPt.location_name} ➔ ${endPt.location_name}
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        🚗 ${Math.round(distanceKm)} km &nbsp;•&nbsp; ⏱️ ${formatDuration(durationSec)}
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px; font-size: 0.8rem;">
                    ${tollHtml}
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
      const globalFuelCostEl = document.getElementById("global_fuel_cost");

      if (distEl && timeEl && distBlock) {
        distEl.innerText = Math.round(totalTripDistance);
        timeEl.innerText = formatDuration(totalTripDuration);
        distBlock.style.display = "block";
      }

      if (globalFuelCostEl) {
        globalFuelCostEl.innerText = Math.round(totalFuelCost);
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
  const container = document.getElementById("cpExpensesContainer");
  const btnDel = document.getElementById("btnDeleteCp");
  const insertGroup = document.getElementById("cp_insert_group");
  const insertSelect = document.getElementById("cp_insert_after");

  container.innerHTML = "";
  if (document.getElementById("searchPlaceInput"))
    document.getElementById("searchPlaceInput").value = "";
  if (document.getElementById("searchResults"))
    document.getElementById("searchResults").innerHTML = "";

  const holidayData = JSON.parse(
    document.getElementById("holidayDataJson").textContent,
  ).main;
  let tripStartDate =
    holidayData.start_date || new Date().toISOString().split("T")[0];

  // ==========================================
  // CONFIGURATION DES DATES FLATPICKR
  // ==========================================
  let defaultStart = "";
  let defaultEnd = "";

  if (mode === "add") {
    document.getElementById("cpModalTitle").innerText = tr("hdl_btn_add_step");
    btnDel.style.display = "none";
    searchBlock.style.display = "block";
    insertGroup.style.display = "block";

    document.getElementById("cp_old_sort_order").value = "";
    document.getElementById("cp_name").value = "";

    switchCpTab("info");

    if (document.getElementById("cp_step_type")) {
      document.getElementById("cp_step_type").value = "stop";
      toggleStepDates("stop");
    }
    if (document.getElementById("cp_set_as_return"))
      document.getElementById("cp_set_as_return").checked = false;

    let lastDate = tripStartDate;
    if (window.MAP_POINTS && window.MAP_POINTS.length > 0) {
      const lastStep = window.MAP_POINTS[window.MAP_POINTS.length - 1];
      lastDate =
        lastStep.step_end_date || lastStep.step_start_date || tripStartDate;
    }

    insertSelect.innerHTML = `<option value="end" data-enddate="${lastDate}">-- À la fin du voyage --</option>`;
    if (window.MAP_POINTS && window.MAP_POINTS.length > 0) {
      window.MAP_POINTS.forEach((step) => {
        let dateStr = "";
        if (
          step.step_start_date &&
          step.step_end_date &&
          step.step_start_date !== step.step_end_date
        ) {
          dateStr = ` (${new Date(step.step_start_date).toLocaleDateString(window.appLang, { day: "2-digit", month: "2-digit" })} > ${new Date(step.step_end_date).toLocaleDateString(window.appLang, { day: "2-digit", month: "2-digit" })})`;
        } else if (step.step_start_date) {
          dateStr = ` (${new Date(step.step_start_date).toLocaleDateString(window.appLang, { day: "2-digit", month: "2-digit" })})`;
        }
        insertSelect.innerHTML += `<option value="${step.sort_order}" data-enddate="${step.step_end_date || step.step_start_date || tripStartDate}">Après : ${step.location_name}${dateStr}</option>`;
      });
    }

    defaultStart = lastDate;
    defaultEnd = lastDate;
    addCpExpenseLine();
  } else if (mode === "edit" && data) {
    document.getElementById("cpModalTitle").innerText = tr("hdl_js_edit_step");
    btnDel.style.display = "block";
    searchBlock.style.display = "none";
    insertGroup.style.display = "none";

    switchCpTab("prog");

    document.getElementById("cp_lat").value = data.lat;
    document.getElementById("cp_lng").value = data.lng;
    document.getElementById("cp_old_sort_order").value = data.sort_order;
    document.getElementById("cp_name").value = data.location_name;

    if (document.getElementById("cp_step_type")) {
      const type = data.step_type || "stop";
      document.getElementById("cp_step_type").value = type;
      toggleStepDates(type);
    }
    if (document.getElementById("cp_set_as_return")) {
      document.getElementById("cp_set_as_return").checked =
        window.GLOBAL_RETURN_STEP_ID == data.sort_order;
    }

    defaultStart = data.step_start_date || tripStartDate;
    defaultEnd = data.step_end_date || tripStartDate;

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
            it.expense_context || "local",
          );
          visibleCount++;
        }
      });
      if (visibleCount === 0) addCpExpenseLine();
    } else {
      addCpExpenseLine();
    }
  }

  // Destruction et ré-instanciation de Flatpickr pour forcer le saut au bon mois
  if (cpDateRangePicker) cpDateRangePicker.destroy();

  document.getElementById("cp_start_date").value = defaultStart;
  document.getElementById("cp_end_date").value = defaultEnd;

  cpDateRangePicker = flatpickr("#cp_date_range", {
    mode: "range",
    altInput: true, // 💡 NOUVEAU : Crée un champ de présentation séparé
    altFormat: "d/m", // 💡 NOUVEAU : Format ultra compact (ex: 15/08)
    dateFormat: "Y-m-d", // Format technique (MariaDB) conservé en arrière-plan
    defaultDate:
      defaultStart && defaultEnd && defaultStart !== defaultEnd
        ? [defaultStart, defaultEnd]
        : [defaultStart],
    locale: window.appLang === "ca-ES" ? "cat" : "fr",
    onChange: function (selectedDates, dateStr, instance) {
      if (selectedDates.length === 2) {
        document.getElementById("cp_start_date").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
        document.getElementById("cp_end_date").value = instance.formatDate(
          selectedDates[1],
          "Y-m-d",
        );
      } else if (selectedDates.length === 1) {
        document.getElementById("cp_start_date").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
        document.getElementById("cp_end_date").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
      } else {
        document.getElementById("cp_start_date").value = "";
        document.getElementById("cp_end_date").value = "";
      }
    },
  });

  document.getElementById("checkpointModal").style.display = "flex";
  document.body.classList.add("no-scroll");
}

// ==========================================
// 🎯 DATES GLOBALES DU VOYAGE (MODALE PRINCIPALE)
// ==========================================
let holDateRangePicker = null;

function initHolFlatpickr(defaultStart, defaultEnd) {
  if (holDateRangePicker) holDateRangePicker.destroy();

  holDateRangePicker = flatpickr("#hol_date_range", {
    mode: "range",
    altInput: true,
    altFormat: "d/m", // 💡 Format ultra compact (ex: 15/08)
    dateFormat: "Y-m-d", // 💡 Le vrai format envoyé au serveur
    defaultDate:
      defaultStart && defaultEnd && defaultStart !== defaultEnd
        ? [defaultStart, defaultEnd]
        : defaultStart
          ? [defaultStart]
          : [],
    locale: window.appLang === "ca-ES" ? "cat" : "fr",
    onChange: function (selectedDates, dateStr, instance) {
      if (selectedDates.length === 2) {
        document.getElementById("inp_start").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
        document.getElementById("inp_end").value = instance.formatDate(
          selectedDates[1],
          "Y-m-d",
        );
      } else if (selectedDates.length === 1) {
        document.getElementById("inp_start").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
        document.getElementById("inp_end").value = instance.formatDate(
          selectedDates[0],
          "Y-m-d",
        );
      } else {
        document.getElementById("inp_start").value = "";
        document.getElementById("inp_end").value = "";
      }
    },
  });
}

function switchCpTab(tabId) {
  const btnInfo = document.getElementById("tabBtnInfo");
  const btnProg = document.getElementById("tabBtnProg");
  const tabInfo = document.getElementById("cpTabInfo");
  const tabProg = document.getElementById("cpTabProg");

  if (tabId === "info") {
    btnInfo.style.borderBottomColor = "var(--primary)";
    btnInfo.style.color = "var(--primary)";
    btnProg.style.borderBottomColor = "transparent";
    btnProg.style.color = "var(--text-muted)";
    tabInfo.style.display = "block";
    tabProg.style.display = "none";
  } else {
    btnProg.style.borderBottomColor = "var(--primary)";
    btnProg.style.color = "var(--primary)";
    btnInfo.style.borderBottomColor = "transparent";
    btnInfo.style.color = "var(--text-muted)";
    tabProg.style.display = "block";
    tabInfo.style.display = "none";
  }
}

function injectDynamicDates(selectEl) {
  const selectedOpt = selectEl.options[selectEl.selectedIndex];
  if (selectedOpt && selectedOpt.dataset.enddate) {
    const dateToSet = selectedOpt.dataset.enddate;
    document.getElementById("cp_start_date").value = dateToSet;
    document.getElementById("cp_end_date").value = dateToSet;

    // On met à jour l'UI du calendrier
    if (cpDateRangePicker) {
      cpDateRangePicker.setDate([dateToSet, dateToSet]);
    }
  }
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
  expenseContext = "local",
) {
  const container = document.getElementById("cpExpensesContainer");
  const div = document.createElement("div");
  div.className = "hol-form-row";
  const isChecked = isPaid == 1 ? "checked" : "";

  // 💡 Nouveaux types (Les valeurs à 0 par défaut pour les visites gratuites)
  let defaultAmount = amount;
  if (category === "visit_free" && amount === "") defaultAmount = "0.00";

  div.innerHTML = `
        <div class="hol-form-inner">
            <select name="items[cat][]" class="pf-input hol-form-select" style="width:55px; padding:6px; font-size:1.1rem;" title="Catégorie">
                <option value="accommodation" ${category === "accommodation" ? "selected" : ""}>🏨</option>
                <option value="transport" ${category === "transport" ? "selected" : ""}>🚗</option>
                <option value="food" ${category === "food" ? "selected" : ""}>🍽️</option>
                <option value="activity" ${category === "activity" ? "selected" : ""}>🎫</option>
                <option value="visit_free" ${category === "visit_free" ? "selected" : ""}>🏞️</option>
                <option value="other" ${category === "other" ? "selected" : ""}>🛍️</option>
            </select>
            
            <input type="hidden" name="items[context][]" value="${expenseContext}">
            <input type="text" name="items[name][]" class="pf-input hol-form-text" placeholder="Description (Ex: Musée, Restaurant...)" value="${name}">
            <input type="number" step="0.01" name="items[amount][]" class="pf-input hol-form-number" placeholder="0.00" value="${defaultAmount}">
            
            <label class="hol-form-paid-label" title="${tr("hdl_paid")}">
                <input type="checkbox" ${isChecked} onchange="this.nextElementSibling.value = this.checked ? 1 : 0" style="accent-color:var(--success); width:16px; height:16px;">
                <input type="hidden" name="items[paid][]" value="${isPaid}">
                <span class="hol-form-paid-text" style="display:none;">${tr("hdl_paid")}</span>
            </label>
            <button type="button" class="btn-icon-action delete btn-remove-expense" onclick="this.parentElement.parentElement.remove()" title="${tr("btn_delete")}">🗑️</button>
        </div>
        <div class="hol-form-subrow" style="padding-left:0; margin-top:4px;">
            <input type="text" name="items[notes][]" class="pf-input hol-form-notes-input hol-form-notes-full" placeholder="🔗 Réservation ou notes..." value="${notes}" style="margin-left:0; width:100%; border-radius:6px;">
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

// 🔥 NOUVEAU : Fonction réutilisable pour ré-attacher les événements
function initStepDragAndDrop() {
  const checkpoints = document.querySelectorAll(".hol-checkpoint-draggable");
  const container = checkpoints[0]?.parentElement;
  if (!container) return;

  const isMobile = window.innerWidth <= 768;
  let draggedItem = null;

  // Nettoyage des anciens écouteurs en clonant les nœuds (Indispensable après un refresh silencieux)
  checkpoints.forEach((item) => {
    const clone = item.cloneNode(true);
    if (item.parentNode) item.parentNode.replaceChild(clone, item);
  });

  const freshCheckpoints = document.querySelectorAll(
    ".hol-checkpoint-draggable",
  );

  freshCheckpoints.forEach((item) => {
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
        if (offset < 0 && offset > closest.offset)
          return { offset: offset, element: child };
        else return closest;
      },
      { offset: Number.NEGATIVE_INFINITY },
    ).element;
  }
}

// On lance l'initialisation au démarrage de la page
document.addEventListener("DOMContentLoaded", initStepDragAndDrop);

// ============================================================================
// 7. MOTEUR DRAG & DROP DU PLANNING GLOBAL
// ============================================================================
window.PLANNING_ALL_UNPLACED = [];
window.CURRENT_PLANNING_FILTER_DATE = null;
window.PLANNING_ITEM_MAP = {};
window.CURRENT_DRAG_DURATION = 1; // Variable pour la surbrillance multi-cases

function closePlanningModal() {
  document.getElementById("planningModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function openGlobalPlanningModal() {
  const holidayDataJsonEl = document.getElementById("holidayDataJson");
  if (!holidayDataJsonEl) return;

  const holidayData = JSON.parse(holidayDataJsonEl.textContent).main;

  if (!holidayData.start_date || !holidayData.end_date) {
    alert(
      "Veuillez d'abord définir les dates globales du voyage dans 'Modifier les bases' ⚙️",
    );
    return;
  }

  document.getElementById("planningModalTitle").innerText =
    "📅 Planning Global : " + holidayData.title;
  const container = document.getElementById("planningContainer");

  selectedItemIdForMove = null;
  let allPlaced = [];
  window.PLANNING_ALL_UNPLACED = [];
  window.PLANNING_ITEM_MAP = {};

  // 1. Collecte de TOUS les éléments
  window.MAP_POINTS.forEach((step) => {
    let validItems = step.items.filter((it) => {
      if (it.name === "PF_TECHNICAL_POINT") return false;
      if (it.category === "transport" && it.expense_context !== "transit")
        return false;
      return true;
    });

    if (window.TRANSIT_DATA && window.TRANSIT_DATA[step.sort_order]) {
      let hasTransit = validItems.some(
        (it) => it.expense_context === "transit",
      );
      if (!hasTransit) {
        const tData = window.TRANSIT_DATA[step.sort_order];
        const h = Math.max(1, Math.round(tData.sec / 3600));
        validItems.push({
          id: "virtual-transit-" + step.sort_order,
          sort_order: step.sort_order,
          name: `Essence depuis ${tData.from}`,
          category: "transport",
          expense_context: "transit",
          duration: h,
          notes: `Trajet GPS (~${Math.round(tData.sec / 60)} min).`,
          is_virtual: true,
          amount: tData.cost,
        });
      }
    }

    validItems.forEach((it) => {
      it.step_start_date = step.step_start_date;
      it.step_end_date = step.step_end_date;
      it.step_location = step.location_name;
      it.sort_order = step.sort_order;

      const htmlId = it.is_virtual ? it.id : `drag-item-${it.id}`;
      window.PLANNING_ITEM_MAP[htmlId] = it;

      if (it.item_date && it.item_time) {
        allPlaced.push(it);
      } else {
        window.PLANNING_ALL_UNPLACED.push(it);
      }
    });
  });

  // 2. Génération des dates globales
  let datesToDisplay = [];
  let curr = new Date(holidayData.start_date);
  let endD = new Date(holidayData.end_date);
  while (curr <= endD) {
    datesToDisplay.push(curr.toISOString().split("T")[0]);
    curr.setDate(curr.getDate() + 1);
  }

  // 3. Construction de l'interface (Avec règles CSS injectées)
  let html = `
        <style>
            /* 🌟 MAGIE CSS : Ajustement adaptatif des tailles selon la zone */
            #unmapped-pool .hol-drag-item {
                /* Base 40px + 10px par heure supp, capé à +30px max (soit environ 4h visuelles max) */
                min-height: calc(40px + (min(var(--duration) - 1, 3) * 10px)) !important;
            }
            .hol-time-slots-container .hol-drag-item {
                /* Dans le calendrier : taille 100% fidèle (40px par heure) */
                min-height: calc(var(--duration) * 40px - 8px) !important;
            }
            /* 🌟 Surbrillance bleue pour chaque case survolée correspondante à la durée */
            .hol-time-slot.drag-over-duration {
                background: rgba(59, 130, 246, 0.15) !important;
                border-left: 3px solid var(--primary) !important;
            }
        </style>
        <div style="display: flex; width: 100%; height: 100%; gap: 15px;">
            <!-- Panneau Gauche : À Placer -->
            <div class="hol-unmapped-zone" style="width: 280px; display: flex; flex-direction: column; background: var(--bg-subtle); border-radius: 8px; border: 1px solid var(--border-light); padding: 12px; flex-shrink: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-shrink: 0;">
                    <div class="hol-unmapped-title" style="margin:0; font-weight:700; color:var(--text-main);">📥 ${tr("hdl_to_place")}</div>
                    <button onclick="filterPoolByDate(null)" class="pf-btn btn-secondary pf-btn-small" style="padding: 2px 8px; font-size: 0.75rem;">🔄 Tous</button>
                </div>
                <div id="unmapped-pool" style="flex: 1; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 8px;"
                     ondragover="allowDrop(event)" ondrop="handleDropEvent(event, '', '')"
                     onclick="handleZoneTap(event, '', '')">
                </div>
            </div>

            <!-- Grille Droite : Jours -->
            <div class="hol-calendar-zone" id="calendarZoneContainer" style="cursor: grab; flex: 1; display: flex; overflow: auto; gap: 12px; padding: 4px 4px 10px 4px; margin-top: -4px; align-items: flex-start;">
    `;

  datesToDisplay.forEach((dateStr) => {
    const dObj = new Date(dateStr);
    const dayName = dObj.toLocaleDateString(currentLang, { weekday: "short" });
    const dayNum = dObj.toLocaleDateString(currentLang, {
      day: "numeric",
      month: "short",
    });

    let unplacedForDay = window.PLANNING_ALL_UNPLACED.filter((it) =>
      isDateInStep(dateStr, it.step_start_date, it.step_end_date),
    );

    let badgeHtml = `<span class="pf-badge" id="badge-${dateStr}" style="display: ${unplacedForDay.length > 0 ? "inline-block" : "none"}; background: var(--danger); color: white; border-radius: 12px; padding: 3px 8px; font-size: 0.75rem; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.15); transition: transform 0.2s;" onclick="filterPoolByDate('${dateStr}')">${unplacedForDay.length}</span>`;

    html += `
            <div class="hol-day-column" id="col-${dateStr}" style="width: 240px; flex-shrink: 0; display: flex; flex-direction: column; background: var(--bg-panel); border: 1px solid var(--border-light); border-radius: 8px;">
                <div class="hol-calendar-day-header" style="position: sticky; top: 0; z-index: 20; text-align: center; padding: 12px 12px 8px 12px; background: var(--bg-page); border-bottom: 1px solid var(--border-light); border-radius: 8px 8px 0 0;">
                    <div style="position: absolute; top: 10px; right: 10px;">${badgeHtml}</div>
                    <div class="hol-cal-weekday" style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em; line-height: 1.4; margin-bottom: 2px;">${dayName}</div>
                    <div class="hol-cal-date" style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">${dayNum}</div>
                    <div id="plan-weather-${dateStr}" style="margin-top: 5px; display: flex; justify-content: center; min-height: 22px;"></div>
                </div>
                <div class="hol-time-slots-container" style="flex: 1; overflow: visible; padding-top: 12px;">
        `;

    for (let h = 6; h <= 23; h++) {
      let hourStr = h.toString().padStart(2, "0") + ":00";
      html += `
                <div class="hol-time-slot" data-date="${dateStr}" data-time="${hourStr}"
                     style="min-height: 40px; border-bottom: 1px dashed var(--border-light); position: relative; padding: 6px; display: flex; flex-direction: column; gap: 4px;"
                     ondragover="allowDrop(event)" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)"
                     ondrop="handleDropEvent(event, '${dateStr}', '${hourStr}')"
                     onclick="handleZoneTap(event, '${dateStr}', '${hourStr}')">
                    <span class="hol-slot-label" style="position: absolute; top: -8px; left: 4px; font-size: 0.65rem; color: var(--text-muted); background: var(--bg-panel); padding: 0 4px;">${hourStr}</span>
                </div>
            `;
    }
    html += `</div></div>`;
  });

  html += `</div></div>`;
  container.innerHTML = html;

  // 4. Placement des éléments
  allPlaced.forEach((it) => {
    if (datesToDisplay.includes(it.item_date)) {
      const hourPrefix = it.item_time.substring(0, 2) + ":00";
      const targetSlot = container.querySelector(
        `.hol-time-slot[data-date="${it.item_date}"][data-time="${hourPrefix}"]`,
      );
      if (targetSlot)
        targetSlot.insertAdjacentHTML("beforeend", buildDragItemHtml(it));
    }
  });

  // 5. Météo et Focus auto
  datesToDisplay.forEach((dateStr) => {
    let activeStep = window.MAP_POINTS.find((step) =>
      isDateInStep(dateStr, step.step_start_date, step.step_end_date),
    );
    if (activeStep && activeStep.lat && activeStep.lng) {
      loadWeatherForPlanning(activeStep.lat, activeStep.lng, dateStr);
    }
  });

  document.getElementById("planningModal").style.display = "flex";
  document.body.classList.add("no-scroll");

  let todayStr = new Date().toISOString().split("T")[0];
  let defaultFocusDate =
    todayStr >= holidayData.start_date && todayStr <= holidayData.end_date
      ? todayStr
      : holidayData.start_date;

  filterPoolByDate(defaultFocusDate);

  setTimeout(() => {
    const activeCol = document.getElementById("col-" + defaultFocusDate);
    if (activeCol) {
      document.getElementById("calendarZoneContainer").scrollTo({
        left: activeCol.offsetLeft - 300,
        behavior: "smooth",
      });
    }
  }, 200);

  // Moteur Scroll Natif (Drag To Scroll)
  const slider = document.getElementById("calendarZoneContainer");
  let isDown = false;
  let startX, startY, scrollLeft, scrollTop;

  slider.addEventListener("mousedown", (e) => {
    if (e.target.closest(".hol-drag-item") || e.target.closest("button"))
      return;
    isDown = true;
    slider.style.cursor = "grabbing";
    startX = e.pageX - slider.offsetLeft;
    startY = e.pageY - slider.offsetTop;
    scrollLeft = slider.scrollLeft;
    scrollTop = slider.scrollTop;
  });
  slider.addEventListener("mouseleave", () => {
    isDown = false;
    slider.style.cursor = "grab";
  });
  slider.addEventListener("mouseup", () => {
    isDown = false;
    slider.style.cursor = "grab";
  });
  slider.addEventListener("mousemove", (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - slider.offsetLeft;
    const y = e.pageY - slider.offsetTop;
    const walkX = (x - startX) * 1.5;
    const walkY = (y - startY) * 1.5;
    slider.scrollLeft = scrollLeft - walkX;
    slider.scrollTop = scrollTop - walkY;
  });
}

function isDateInStep(targetDate, stepStart, stepEnd) {
  if (!stepStart && !stepEnd) return false;

  const start = stepStart || stepEnd;
  const end = stepEnd || stepStart;

  return targetDate >= start && targetDate <= end;
}

function filterPoolByDate(dateStr) {
  window.CURRENT_PLANNING_FILTER_DATE = dateStr;
  const pool = document.getElementById("unmapped-pool");
  pool.innerHTML = "";

  let poolItemsHtml = "";
  let count = 0;

  // Itération sur la MAP, donc l'ordre natif de tri (sort_order/ID) est préservé !
  Object.values(window.PLANNING_ITEM_MAP).forEach((it) => {
    const htmlId = it.is_virtual ? it.id : `drag-item-${it.id}`;
    const isPlaced = document.querySelector(`.hol-time-slot #${htmlId}`);

    if (!isPlaced) {
      if (
        !dateStr ||
        isDateInStep(dateStr, it.step_start_date, it.step_end_date)
      ) {
        poolItemsHtml += buildDragItemHtml(it);
        count++;
      }
    }
  });

  if (count === 0) {
    pool.innerHTML = `<div style="text-align:center; margin-top:30px; color:var(--text-muted);"><span style="font-size:2rem;">🎉</span><br><br>Rien à placer${dateStr ? " pour cette journée" : ""}.</div>`;
  } else {
    pool.innerHTML = poolItemsHtml;
  }
}

function recalcAllBadges() {
  const pool = document.getElementById("unmapped-pool");
  const unmappedIds = Array.from(pool.children).map((el) => el.id);

  document.querySelectorAll(".hol-day-column").forEach((col) => {
    const dateStr = col.id.replace("col-", "");
    const badge = document.getElementById("badge-" + dateStr);
    if (!badge) return;

    let dayCount = 0;
    unmappedIds.forEach((htmlId) => {
      const it = window.PLANNING_ITEM_MAP[htmlId];
      if (it && isDateInStep(dateStr, it.step_start_date, it.step_end_date))
        dayCount++;
    });

    badge.innerText = dayCount;
    badge.style.display = dayCount > 0 ? "inline-block" : "none";
  });
}

function buildDragItemHtml(it) {
  // Mapping intelligent des nouvelles icônes
  let icon = "🏷️";
  let catClass = "cat-activity";

  if (it.category === "accommodation") {
    icon = "🏨";
    catClass = "cat-accommodation";
  } else if (it.category === "transport") {
    icon = "🚗";
    catClass = "cat-transport";
  } else if (it.category === "food") {
    icon = "🍽️";
  } else if (it.category === "visit_free") {
    icon = "🏞️";
  } else if (it.category === "activity") {
    icon = "🎫";
  } else if (it.category === "other") {
    icon = "🛍️";
  }

  const dur = it.duration || 1;
  const noteHtml = it.notes
    ? `<div class="hol-drag-note" style="font-size:0.7rem; color:var(--text-muted); line-height:1.2; margin-top:4px;">${it.notes}</div>`
    : "";
  const isVirtual = it.is_virtual === true;
  const isTransit = it.expense_context === "transit";

  const durControls = `<button class="hol-dur-btn" style="border:none;background:transparent;cursor:pointer;font-weight:bold;padding:0 4px;" onclick="changeDuration(event, '${it.id}', -1)">-</button>
                         <span class="hol-dur-text" id="dur-text-${it.id}" style="font-size:0.75rem;font-weight:bold;">${dur}h</span>
                         <button class="hol-dur-btn" style="border:none;background:transparent;cursor:pointer;font-weight:bold;padding:0 4px;" onclick="changeDuration(event, '${it.id}', 1)">+</button>`;

  const bgStyle =
    isVirtual || isTransit
      ? "background: repeating-linear-gradient(45deg, var(--bg-page), var(--bg-page) 10px, rgba(59, 130, 246, 0.05) 10px, rgba(59, 130, 246, 0.05) 20px); border: 2px dashed var(--primary);"
      : "background: var(--bg-panel); border: 1px solid var(--border-strong);";

  const visualName = isTransit ? `🛣️ Trajet & Essence` : `${icon} ${it.name}`;
  const isMobile = window.innerWidth <= 768;
  const dragAttr = isMobile ? "" : 'draggable="true"';
  const htmlId = isVirtual ? it.id : `drag-item-${it.id}`;

  // 💡 NOUVEAU : Affichage compact des dates associées pour faciliter le placement
  let locHintHtml = "";
  if (!it.item_date) {
    const datesStr =
      it.step_start_date &&
      it.step_end_date &&
      it.step_start_date !== it.step_end_date
        ? ` <span style="color:var(--text-muted); text-transform:none;">(${new Date(it.step_start_date).toLocaleDateString(currentLang, { day: "2-digit", month: "2-digit" })} > ${new Date(it.step_end_date).toLocaleDateString(currentLang, { day: "2-digit", month: "2-digit" })})</span>`
        : it.step_start_date
          ? ` <span style="color:var(--text-muted); text-transform:none;">(${new Date(it.step_start_date).toLocaleDateString(currentLang, { day: "2-digit", month: "2-digit" })})</span>`
          : "";

    locHintHtml = it.step_location
      ? `<div style="font-size:0.65rem; color:var(--primary); font-weight:800; margin-bottom:4px; text-transform:uppercase;">📍 ${it.step_location}${datesStr}</div>`
      : "";
  }

  return `
        <div class="hol-drag-item ${catClass}" ${dragAttr}
             id="${htmlId}" data-id="${it.id}" data-virtual="${isVirtual}" data-sort="${it.sort_order}"
             style="--duration: ${dur}; flex-shrink: 0; ${bgStyle} padding: 8px 10px; border-radius: 6px; cursor: grab; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; z-index: 10;"
             ondragstart="dragStart(event)" ondragend="dragEnd(event)" onclick="handleItemTap(event, '${htmlId}')">
            ${locHintHtml}
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div class="hol-drag-title" style="flex:1; font-size: 0.85rem; font-weight: 700; color: var(--text-main); line-height:1.2;">${visualName}</div>
                <div style="display:flex; align-items:center;">
                    <div class="hol-item-duration-controls" style="display:flex; align-items:center; background:var(--bg-subtle); border-radius:4px; border:1px solid var(--border-light);">
                        ${durControls}
                    </div>
                    <button type="button" class="hol-unplace-btn" onclick="unplaceItem(event, '${htmlId}')" title="Retirer du planning">↩️</button>
                </div>
            </div>
            ${noteHtml}
        </div>
    `;
}

function unplaceItem(e, htmlId) {
  e.stopPropagation();
  handleDropLogic(htmlId, "", "");
}

function changeDuration(e, itemId, delta) {
  e.stopPropagation();
  const itemEl =
    document.getElementById("drag-item-" + itemId) ||
    document.getElementById(itemId);
  if (!itemEl) return;

  let currentDur = parseInt(itemEl.style.getPropertyValue("--duration")) || 1;
  let newDur = currentDur + delta;
  if (newDur < 1) newDur = 1;
  if (newDur > 12) newDur = 12;

  itemEl.style.setProperty("--duration", newDur);
  document.getElementById(`dur-text-${itemId}`).innerText = newDur + "h";

  if (itemEl.getAttribute("data-virtual") === "true") {
    const it = window.PLANNING_ITEM_MAP[itemEl.id];
    if (it) it.duration = newDur;
    return;
  }

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

function handleItemTap(e, htmlId) {
  e.stopPropagation();
  document
    .querySelectorAll(".hol-drag-item")
    .forEach((el) => el.classList.remove("selected-for-move"));
  if (selectedItemIdForMove === htmlId) {
    selectedItemIdForMove = null;
  } else {
    selectedItemIdForMove = htmlId;
    const el = document.getElementById(htmlId);
    if (el) el.classList.add("selected-for-move");
  }
}

function handleZoneTap(e, dateStr, timeStr) {
  if (selectedItemIdForMove) {
    const itemEl = document.getElementById(selectedItemIdForMove);
    if (itemEl) {
      const targetZone = e.currentTarget;
      if (targetZone.id === "unmapped-pool") {
        handleDropLogic(selectedItemIdForMove, "", "");
      } else {
        targetZone.appendChild(itemEl);
        handleDropLogic(selectedItemIdForMove, dateStr, timeStr);
      }
    }
    selectedItemIdForMove = null;
    document
      .querySelectorAll(".hol-drag-item")
      .forEach((el) => el.classList.remove("selected-for-move"));
  }
}

function dragStart(e) {
  e.dataTransfer.setData("text/plain", e.target.id);
  e.dataTransfer.effectAllowed = "move";
  // Mémorisation de la durée pour le survol dynamique (Fix #2)
  window.CURRENT_DRAG_DURATION =
    parseInt(e.target.style.getPropertyValue("--duration")) || 1;
}

function dragEnd(e) {
  window.CURRENT_DRAG_DURATION = 1;
  document
    .querySelectorAll(".hol-time-slot")
    .forEach((s) => s.classList.remove("drag-over-duration"));
}

function allowDrop(e) {
  e.preventDefault();
}

function dragEnter(e) {
  e.preventDefault();
  let slot = e.target.closest(".hol-time-slot");
  if (slot) {
    // Retirer toutes les anciennes surbrillances
    document
      .querySelectorAll(".hol-time-slot")
      .forEach((s) => s.classList.remove("drag-over-duration"));

    // Appliquer la surbrillance sur les N cases consécutives
    let dur = window.CURRENT_DRAG_DURATION || 1;
    let currentSlot = slot;
    for (let i = 0; i < dur; i++) {
      if (currentSlot) {
        currentSlot.classList.add("drag-over-duration");
        currentSlot = currentSlot.nextElementSibling;
      }
    }
  }
}

function dragLeave(e) {
  // La gestion précise des surbrillances se fait via le dragEnter et dragEnd pour éviter le scintillement (flickering).
}

function handleDropEvent(e, dateStr, timeStr) {
  e.preventDefault();
  document
    .querySelectorAll(".hol-time-slot")
    .forEach((s) => s.classList.remove("drag-over-duration"));

  const idStr = e.dataTransfer.getData("text/plain");
  const itemEl = document.getElementById(idStr);
  const dropZone =
    e.target.closest(".hol-time-slot") ||
    document.getElementById("unmapped-pool");

  if (itemEl && dropZone) {
    if (dropZone.id === "unmapped-pool") {
      // FIX #3 : Contourner l'appendChild (qui met tout en bas) et déléguer à la fonction pour re-trier la colonne !
      handleDropLogic(idStr, "", "");
    } else {
      dropZone.appendChild(itemEl);
      handleDropLogic(idStr, dateStr, timeStr);
    }
  }
}

function handleDropLogic(htmlId, dateStr, timeStr) {
  const itemEl = document.getElementById(htmlId);
  if (!itemEl) return;

  const isVirtual = itemEl.getAttribute("data-virtual") === "true";
  const holidayId = document.querySelector('input[name="holiday_id"]').value;
  const sortOrder = itemEl.getAttribute("data-sort");
  const dur = parseInt(itemEl.style.getPropertyValue("--duration")) || 1;
  const realId = itemEl.getAttribute("data-id");

  // DÉSASSIGNER (Annulation) : Si on replace la carte dans la zone "À placer"
  if (dateStr === "" || timeStr === "") {
    if (!isVirtual) {
      updateItemMemory(realId, { item_date: null, item_time: null });
      const fd = new FormData();
      fd.append("action", "update_item_datetime");
      fd.append("item_id", realId);
      fd.append("item_date", "");
      fd.append("item_time", "");

      // Enregistrement en base + Toast
      fetch("/modules/holidays/includes/api/save_checkpoint.php", {
        method: "POST",
        body: fd,
      }).then(() => {
        if (typeof showToast === "function")
          showToast("📍 Élément remis en attente", "success");
      });
    }

    // 🔥 C'EST LA CLÉ : On enlève physiquement la carte du calendrier !
    itemEl.remove();

    filterPoolByDate(window.CURRENT_PLANNING_FILTER_DATE);
    recalcAllBadges();
    window.PLANNING_MODIFIED = true;
    return;
  }

  // AFFECTATION CALENDRIER
  if (isVirtual) {
    const tData = window.TRANSIT_DATA[sortOrder];
    const fd = new FormData();
    fd.append("action", "add_single_item");
    fd.append("holiday_id", holidayId);
    fd.append("sort_order", sortOrder);
    fd.append("category", "transport");
    fd.append("context", "transit");
    fd.append("expense_context", "transit");
    fd.append("name", `Essence depuis ${tData.from}`);
    fd.append("amount", tData.cost);
    fd.append("duration", dur);
    fd.append("item_date", dateStr);
    fd.append("item_time", timeStr);
    fd.append("ajax", "1");

    itemEl.setAttribute("data-virtual", "false");

    fetch("/modules/holidays/includes/api/save_checkpoint.php", {
      method: "POST",
      body: fd,
    })
      .then((res) => res.json())
      .then(async (data) => {
        if (data && data.id) {
          itemEl.id = `drag-item-${data.id}`;
          itemEl.setAttribute("data-id", data.id);

          const fdDate = new FormData();
          fdDate.append("action", "update_item_datetime");
          fdDate.append("item_id", data.id);
          fdDate.append("item_date", dateStr);
          fdDate.append("item_time", timeStr);
          await fetch("/modules/holidays/includes/api/save_checkpoint.php", {
            method: "POST",
            body: fdDate,
          });

          window.PLANNING_ITEM_MAP[itemEl.id] = {
            id: data.id,
            step_start_date: dateStr,
            step_end_date: dateStr,
            expense_context: "transit",
            category: "transport",
            is_virtual: false,
          };

          if (typeof window.MAP_POINTS !== "undefined") {
            const stepObj = window.MAP_POINTS.find(
              (s) => s.sort_order == sortOrder,
            );
            if (stepObj) {
              stepObj.items.push({
                id: data.id,
                category: "transport",
                name: `Essence depuis ${tData.from}`,
                amount: tData.cost,
                expense_context: "transit",
                duration: dur,
                item_date: dateStr,
                item_time: timeStr,
              });
            }
          }

          if (typeof showToast === "function")
            showToast("🚗 Trajet généré et sauvegardé !", "success");
          itemEl.style.transition = "box-shadow 0.3s ease";
          itemEl.style.boxShadow = "0 0 0 3px var(--success)";
          setTimeout(() => (itemEl.style.boxShadow = ""), 1500);
        } else {
          window.location.reload();
        }
      })
      .catch(() => window.location.reload());
  } else {
    updateItemMemory(realId, { item_date: dateStr, item_time: timeStr });
    const fd = new FormData();
    fd.append("action", "update_item_datetime");
    fd.append("item_id", realId);
    fd.append("item_date", dateStr);
    fd.append("item_time", timeStr);

    fetch("/modules/holidays/includes/api/save_checkpoint.php", {
      method: "POST",
      body: fd,
    })
      .then(() => {
        if (typeof showToast === "function")
          showToast("📅 Activité planifiée !", "success");
        itemEl.style.transition = "box-shadow 0.3s ease";
        itemEl.style.boxShadow = "0 0 0 3px var(--success)";
        setTimeout(() => (itemEl.style.boxShadow = ""), 1500);
      })
      .catch(() => {
        if (typeof showToast === "function")
          showToast("❌ Erreur de sauvegarde", "error");
      });
  }

  recalcAllBadges();
  silentlyRefreshSteps();
}

function updateItemMemory(itemId, changes) {
  if (typeof MAP_POINTS !== "undefined") {
    MAP_POINTS.forEach((step) => {
      let item = step.items.find((i) => i.id == itemId);
      if (item) Object.assign(item, changes);
    });
  }
}

/**
 * Rafraîchit uniquement le conteneur des étapes sans recharger toute la page
 */
async function silentlyRefreshSteps() {
  try {
    const res = await fetch(window.location.href);
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");

    // 🕵️ CIBLAGE INTELLIGENT : On trouve le panel-body qui contient les étapes (ou le texte "Aucune étape")
    const getTargetContainer = (documentObj) =>
      Array.from(documentObj.querySelectorAll(".hol-panel-body")).find(
        (el) =>
          el.querySelector(".hol-checkpoint") ||
          el.innerHTML.toLowerCase().includes("aucune étape") ||
          el.innerHTML.toLowerCase().includes("cap etapa"),
      );

    const currentContainer = getTargetContainer(document);
    const newContainer = getTargetContainer(doc);

    if (currentContainer && newContainer) {
      // Un léger effet de flash pour indiquer à l'utilisateur que ça s'est mis à jour
      currentContainer.style.opacity = "0.5";

      setTimeout(() => {
        currentContainer.innerHTML = newContainer.innerHTML;
        currentContainer.style.opacity = "1";

        // Mise à jour du prix global de l'essence en haut
        const globalFuel = document.getElementById("global_fuel_cost");
        const newGlobalFuel = doc.getElementById("global_fuel_cost");
        if (globalFuel && newGlobalFuel)
          globalFuel.innerHTML = newGlobalFuel.innerHTML;

        // 🚀 On relance le Drag & Drop des étapes !
        initStepDragAndDrop();
      }, 150);
    } else {
      window.location.reload(); // Fallback de sécurité ultime
    }
  } catch (e) {
    console.error("Erreur lors du rafraîchissement silencieux", e);
  }
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
  const dateLabel = document.getElementById("lbl_date_range");
  if (!dateLabel) return;

  if (type === "origin") {
    dateLabel.innerText = "🛫 Date de départ";
  } else if (type === "destination") {
    dateLabel.innerText = "🛬 Date d'arrivée finale";
  } else {
    dateLabel.innerText = "📅 Période de l'étape (Arrivée ➔ Départ)";
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

  fd.append("expense_context", "transit");

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

// ============================================================================
// CHOIX DE L'APPLICATION GPS (Google Maps, Waze, Apple Maps)
// ============================================================================
window.currentGpsTarget = { lat: 0, lng: 0 };

function openGpsModal(lat, lng) {
  window.currentGpsTarget = { lat: lat, lng: lng };
  document.getElementById("gpsModal").style.display = "flex";
  document.body.classList.add("no-scroll");
}

function closeGpsModal() {
  document.getElementById("gpsModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function launchGpsApp(app) {
  const lat = window.currentGpsTarget.lat;
  const lng = window.currentGpsTarget.lng;
  let url = "";

  if (app === "waze") {
    // Force l'ouverture de Waze en mode navigation
    url = `https://waze.com/ul?ll=${lat},${lng}&navigate=yes`;
  } else if (app === "gmaps") {
    // Force l'ouverture de Google Maps en mode itinéraire
    url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  } else if (app === "amaps") {
    // Force l'ouverture d'Apple Maps
    url = `http://maps.apple.com/?daddr=${lat},${lng}`;
  }

  if (url !== "") {
    window.open(url, "_blank");
    closeGpsModal();
  }
}

// ============================================================================
// GESTION DU PORTE-DOCUMENTS (UPLOAD)
// ============================================================================
window.currentDocsStepId = null;

// 🔥 On attache le verrou à window pour éviter les erreurs de redéclaration
window.isUploadingDocs = window.isUploadingDocs || false;

function openDocsModal(sortOrder) {
  window.currentDocsStepId = sortOrder;
  document.getElementById("docsModal").style.display = "flex";
  document.body.classList.add("no-scroll");
  document.getElementById("uploadStatus").innerHTML = "";

  const listContainer = document.getElementById("docsListContainer");
  listContainer.innerHTML =
    '<p style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">⏳ Chargement des documents...</p>';

  const holidayId = document.querySelector('input[name="holiday_id"]').value;

  // 🔥 On va chercher les documents existants !
  fetch(
    `/modules/holidays/includes/api/get_attachments.php?holiday_id=${holidayId}&item_id=${sortOrder}`,
  )
    .then((response) => response.json())
    .then((data) => {
      listContainer.innerHTML = ""; // On vide le message de chargement

      if (data.success && data.files.length > 0) {
        data.files.forEach((f) => {
          // On rend le nom du fichier cliquable pour ouvrir le document dans un nouvel onglet
          const docHtml = `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 6px; margin-bottom: 6px;">
                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; cursor: pointer;" onclick="window.open('/${f.file_path}', '_blank')">
                            <span style="font-size: 1.2rem;">📄</span>
                            <span style="font-size: 0.9rem; color: var(--primary); font-weight: bold; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="Ouvrir ${f.file_name}">${f.file_name}</span>
                        </div>
                        <button type="button" onclick="deleteAttachment(${f.id}, this)" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1rem;" title="Supprimer">🗑️</button>
                    </div>
                `;
          listContainer.insertAdjacentHTML("beforeend", docHtml);
        });
      } else {
        listContainer.innerHTML =
          '<p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">Aucun document pour cette étape.</p>';
      }
    })
    .catch(() => {
      listContainer.innerHTML =
        '<p style="text-align: center; color: var(--danger);">Erreur lors du chargement.</p>';
    });
}

function closeDocsModal() {
  document.getElementById("docsModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function handleFileUpload(input) {
  // 1. LE VERROU : Si un envoi est déjà en cours, on bloque tout !
  if (window.isUploadingDocs) return;

  if (!input.files || input.files.length === 0) return;

  // On ferme le verrou
  window.isUploadingDocs = true;

  const file = input.files[0];
  const holidayId = document.querySelector('input[name="holiday_id"]').value;
  const statusDiv = document.getElementById("uploadStatus");
  const listContainer = document.getElementById("docsListContainer");

  if (file.size > 5 * 1024 * 1024) {
    statusDiv.innerHTML =
      "<span style='color: var(--danger);'>Fichier trop lourd (Max 5Mo).</span>";
    input.value = "";
    window.isUploadingDocs = false; // On rouvre le verrou
    return;
  }

  statusDiv.innerHTML =
    "<span style='color: var(--primary);'>⏳ Envoi en cours...</span>";

  const fd = new FormData();
  fd.append("holiday_id", holidayId);
  fd.append("item_id", window.currentDocsStepId);
  fd.append("file", file);

  fetch("/modules/holidays/includes/api/upload_attachment.php", {
    method: "POST",
    body: fd,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        statusDiv.innerHTML = `<span style='color: var(--success);'>✅ Sauvegardé !</span>`;

        const emptyMsg = listContainer.querySelector("p");
        if (emptyMsg) emptyMsg.remove();

        const docHtml = `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 6px; margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
                        <span style="font-size: 1.2rem;">📄</span>
                        <span style="font-size: 0.9rem; color: var(--text-main); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="${data.file_name}">${data.file_name}</span>
                    </div>
                    <button type="button" onclick="deleteAttachment(${data.id}, this)" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1rem;" title="Supprimer">🗑️</button>
                </div>
            `;
        listContainer.insertAdjacentHTML("beforeend", docHtml);
      } else {
        statusDiv.innerHTML = `<span style='color: var(--danger);'>❌ Erreur: ${data.error}</span>`;
      }
    })
    .catch((err) => {
      statusDiv.innerHTML =
        "<span style='color: var(--danger);'>❌ Erreur réseau.</span>";
    })
    .finally(() => {
      input.value = "";
      // 🔥 2. On rouvre le verrou SEULEMENT quand tout est terminé
      setTimeout(() => {
        window.isUploadingDocs = false;
      }, 500);
    });
}

// Fonction pour supprimer un document
function deleteAttachment(fileId, btnElement) {
  if (!confirm("Voulez-vous vraiment supprimer ce document définitivement ?"))
    return;

  const holidayId = document.querySelector('input[name="holiday_id"]').value;
  const row = btnElement.closest('div[style*="border: 1px solid"]'); // Cible la ligne d'affichage
  row.style.opacity = "0.4"; // Effet visuel d'attente

  const fd = new FormData();
  fd.append("file_id", fileId);
  fd.append("holiday_id", holidayId);

  fetch("/modules/holidays/includes/api/delete_attachment.php", {
    method: "POST",
    body: fd,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        row.remove(); // On efface la ligne

        // Si c'était le dernier fichier, on remet le texte "Aucun document"
        const listContainer = document.getElementById("docsListContainer");
        if (listContainer.children.length === 0) {
          listContainer.innerHTML =
            '<p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">Aucun document pour cette étape.</p>';
        }
      } else {
        alert("Erreur : " + data.error);
        row.style.opacity = "1";
      }
    })
    .catch(() => {
      alert("Erreur réseau lors de la suppression.");
      row.style.opacity = "1";
    });
}

// ============================================================================
// GÉNÉRATION DU CARNET DE VOYAGE (PDF Côté Client) - VERSION TEXTE BRUT
// ============================================================================
window.generateTravelBook = function () {
  const element = document.getElementById("travelBookTemplate");
  const btn = document.querySelector('button[onclick="generateTravelBook()"]');

  if (!element) {
    alert("Erreur : Le modèle de carnet de voyage est introuvable.");
    return;
  }

  const originalText = btn.innerHTML;
  btn.innerHTML = "⏳ Génération...";
  btn.disabled = true;

  // Options ajustées avec un fond blanc forcé
  const opt = {
    margin: 10, // 10mm de marge
    filename: "Carnet_de_Route.pdf",
    image: { type: "jpeg", quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, backgroundColor: "#ffffff" },
    jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
  };

  // 🔥 L'ASTUCE MAGIQUE :
  // On extrait le HTML en texte brut et on l'encapsule dans un bloc 100% blanc.
  // Plus aucun conflit possible avec l'affichage de ta page web !
  const htmlString = `
        <div style="background-color: #ffffff; color: #000000; width: 100%;">
            ${element.outerHTML}
        </div>
    `;

  // Génération directe depuis la chaîne de texte
  html2pdf()
    .set(opt)
    .from(htmlString)
    .save()
    .then(() => {
      btn.innerHTML = originalText;
      btn.disabled = false;
    })
    .catch((err) => {
      console.error("Erreur html2pdf:", err);
      btn.innerHTML = originalText;
      btn.disabled = false;
    });
};

// ============================================================================
// SAUVEGARDE DES NOTES GLOBALES DU VOYAGE (AJAX)
// ============================================================================
async function saveHolidayGlobalNote(holidayId) {
  const btn = document.getElementById("btnSaveHolidayNote");
  const textarea = document.getElementById("holidayGlobalNotes");
  if (!textarea || !btn) return;

  const originalText = btn.innerHTML;
  btn.innerHTML = "⏳...";
  btn.disabled = true;

  const fd = new FormData();
  fd.append("action", "update_holiday_note");
  fd.append("holiday_id", holidayId);
  fd.append("notes", textarea.value);

  try {
    const res = await pachaFetch(
      "/modules/holidays/includes/api/save_checkpoint.php",
      {
        method: "POST",
        body: fd,
      },
    );

    if (res.success) {
      // L'appel utilise bien ta fonction showToast() globale pour le design des notifications !
      showToast(
        window.I18N["bud_prev_saved"] || "Notes sauvegardées !",
        "success",
      );
    } else {
      showToast(res.error || "Erreur lors de la sauvegarde", "error");
    }
  } catch (e) {
    console.error("Erreur saveHolidayGlobalNote:", e);
    showToast("Erreur réseau.", "error");
  } finally {
    btn.innerHTML = originalText;
    btn.disabled = false;
  }
}

// ============================================================================
// SOUMISSION AJAX DU FORMULAIRE "MODIFIER LES BASES"
// ============================================================================
document.addEventListener("DOMContentLoaded", () => {
  const holidayForm = document.getElementById("holidayForm");
  if (holidayForm) {
    holidayForm.addEventListener("submit", async function (e) {
      // Si on demande la suppression, on laisse le comportement natif faire le travail
      if (this.querySelector('input[name="action_delete"]')) return;

      e.preventDefault();
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = "⏳...";
      submitBtn.disabled = true;

      const fd = new FormData(this);
      // On s'assure d'appeler l'API de sauvegarde
      const actionUrl =
        this.getAttribute("action") ||
        "/modules/holidays/includes/api/save_holiday.php";

      try {
        // On utilise pachaFetch en mode raw/text car save_holiday.php fait sûrement un header('Location: ...') au lieu d'un JSON
        const response = await fetch(actionUrl, {
          method: "POST",
          body: fd,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        // Quoi qu'il arrive, on recharge la page COURANTE (detail.php) pour afficher les modifications
        window.location.reload();
      } catch (err) {
        console.error(err);
        if (typeof showToast === "function")
          showToast("Erreur lors de la sauvegarde", "error");
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      }
    });
  }
});
