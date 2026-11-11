// GarageManager - SPA Frontend
const API = "/modules/garage/api.php";
const UPLOADS = "/modules/garage/api.php?action=photo&file=";

function $(s, ctx = document) {
  return ctx.querySelector(s);
}
function $$(s, ctx = document) {
  return [...ctx.querySelectorAll(s)];
}
function fmt(n) {
  return n != null ? Number(n).toLocaleString("fr-FR") : "--";
}
function fmtPrice(n) {
  return n != null ? Number(n).toFixed(2) + " €" : "--";
}
function fmtDate(d) {
  if (!d) return "--";
  return new Date(d).toLocaleDateString("fr-FR");
}
function formatBytes(n) {
  n = Number(n) || 0;
  if (n < 1024) return n + " o";
  if (n < 1048576) return (n / 1024).toFixed(1) + " Ko";
  return (n / 1048576).toFixed(1) + " Mo";
}
function ago(d) {
  if (!d) return "";
  const diff = Math.floor((Date.now() - new Date(d)) / 86400000);
  if (diff === 0) return "aujourd'hui";
  if (diff === 1) return "hier";
  return "il y a " + diff + "j";
}
function escHtml(s) {
  const d = document.createElement("div");
  d.textContent = String(s ?? "");
  return d.innerHTML;
}
function toast(msg, type = "success") {
  const c = document.getElementById("toasts");
  const t = document.createElement("div");
  t.className = "toast " + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ─── STATE ────────────────────────────────────────────────────────────────────
let currentPage = "dashboard";
let currentVehicleId = null;
let currentMaintenanceId = null;

// ─── NAV ──────────────────────────────────────────────────────────────────────
function navigate(page, params = {}) {
  currentPage = page;
  $$(".page").forEach((p) => p.classList.remove("active"));
  $$(".nav-link").forEach((n) => n.classList.remove("active"));
  document.getElementById("page-" + page)?.classList.add("active");
  const navTarget =
    page === "maintenance"
      ? "maintenances-all"
      : page === "vehicle"
        ? "vehicles"
        : page;
  document
    .querySelector('.nav-link[data-page="' + navTarget + '"]')
    ?.classList.add("active");
  if (page === "dashboard") loadDashboard();
  else if (page === "vehicles") loadVehicles();
  else if (page === "vehicle") loadVehicleDetail(params.id);
  else if (page === "maintenance") loadMaintenanceDetail(params.id);
  else if (page === "parts") loadParts();
  else if (page === "maintenances-all") loadAllMaintenances();
  else if (page === "compare" && window.loadCompare) window.loadCompare();
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function api(action, method = "GET", data = null, extra = "") {
  const opts = { method, headers: {} };
  if (data && !(data instanceof FormData)) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(data);
  } else if (data instanceof FormData) {
    opts.body = data;
  }
  const r = await fetch(API + "?action=" + action + extra, opts);
  const j = await r.json();
  if (!j.ok) throw new Error(j.error || "Erreur API");
  return j.data;
}

// ─── DASHBOARD ────────────────────────────────────────────────────────────────
async function loadDashboard() {
  try {
    const [stats, vehicles, reminders] = await Promise.all([
      api("stats"),
      api("vehicles"),
      api("maintenances"),
    ]);
    $("#stat-vehicles").textContent = stats.vehicles;
    $("#stat-maintenances").textContent = stats.maintenances;
    $("#stat-parts").textContent = stats.parts;
    $("#stat-cost").textContent = fmtPrice(
      parseFloat(stats.total_cost) + parseFloat(stats.total_parts_cost),
    );
    const det = document.getElementById("stat-cost-detail");
    if (det)
      det.textContent =
        "MO " + fmtPrice(stats.total_cost) + " · Pièces " + fmtPrice(stats.total_parts_cost);
    renderDashboardVehicles(vehicles);
    renderReminders(reminders);
  } catch (e) {
    toast(e.message, "error");
  }
}

function renderDashboardVehicles(list) {
  const el = $("#dashboard-vehicles");
  if (!list.length) {
    el.innerHTML =
      '<div class="empty-state"><div class="icon">🚗</div><p>Aucun véhicule</p></div>';
    return;
  }
  el.innerHTML = list
    .slice(0, 6)
    .map((v) => vehicleCardHTML(v))
    .join("");
  $$(".vehicle-card", el).forEach((c) =>
    c.addEventListener("click", () =>
      navigate("vehicle", { id: c.dataset.id }),
    ),
  );
}

function vehicleCardHTML(v) {
  const photoHTML = v.photo
    ? '<img src="' + UPLOADS + v.photo + '" alt="">'
    : '<span class="no-photo">🚗</span>';
  const fuel = v.fuel_type || "Essence";
  const fuelClass = "fuel-" + fuel.replace(/\s/g, "");

  return (
    '<div class="vehicle-card" data-id="' +
    v.id +
    '">' +
    '<div class="vehicle-card-img">' +
    photoHTML +
    '<span class="fuel-badge ' +
    fuelClass +
    '">' +
    escHtml(fuel) +
    "</span>" +
    "</div>" +
    '<div class="vehicle-card-body">' +
    '<div class="vehicle-card-title">' +
    escHtml(v.name) +
    "</div>" +
    '<div class="vehicle-card-sub">' +
    escHtml(v.brand) +
    " " +
    escHtml(v.model) +
    (v.year ? " &middot; " + v.year : "") +
    "</div>" +
    '<div class="vehicle-card-stats">' +
    '<div class="vc-stat">🔧 <strong>' +
    (v.maintenance_count || 0) +
    "</strong> entretiens</div>" +
    // Affichage conditionnel de la conso
    (v.consumption
      ? '<div class="vc-stat">⛽ <strong>' +
        v.consumption +
        "</strong> L/100</div>"
      : "") +
    '<div class="vc-stat" title="Total = main d\'œuvre + pièces">💶 <strong>' +
    fmtPrice(parseFloat(v.total_cost || 0) + parseFloat(v.parts_cost || 0)) +
    "</strong></div>" +
    "</div>" +
    '<div class="vc-breakdown">MO ' +
    fmtPrice(v.total_cost) +
    " · Pièces " +
    fmtPrice(v.parts_cost) +
    "</div>" +
    "</div>" +
    '<div class="vehicle-card-footer">' +
    (v.license_plate
      ? '<span class="plate">' + escHtml(v.license_plate) + "</span>"
      : "<span></span>") +
    '<span class="vc-stat">' +
    (v.current_km ? fmt(v.current_km) + " km" : "--") +
    "</span>" +
    "</div>" +
    "</div>"
  );
}

function renderReminders(list) {
  const el = $("#reminders-list");
  const today = new Date();
  if (!list.length) {
    el.innerHTML =
      '<div class="empty-state" style="padding:1.5rem"><p style="color:var(--muted)">Aucun rappel configuré</p></div>';
    return;
  }
  el.innerHTML =
    '<div class="table-wrap"><table><thead><tr><th>Véhicule</th><th>Type</th><th>Prochaine date</th><th>Prochain km</th><th>Écart km</th></tr></thead><tbody>' +
    list
      .map((r) => {
        const diff =
          r.next_km && r.current_km ? r.next_km - r.current_km : null;
        const dateOk = r.next_date ? new Date(r.next_date) > today : true;
        const kmOk = diff == null || diff > 0;
        const cls =
          !dateOk || !kmOk
            ? "badge-red"
            : diff != null && diff < 2000
              ? "badge-amber"
              : "badge-green";
        return (
          "<tr><td><strong>" +
          escHtml(r.vehicle_name) +
          "</strong>" +
          (r.license_plate
            ? ' <span class="plate">' + escHtml(r.license_plate) + "</span>"
            : "") +
          "</td>" +
          "<td>" +
          escHtml(r.type) +
          "</td>" +
          "<td>" +
          (r.next_date
            ? '<span class="badge ' +
              cls +
              '">' +
              fmtDate(r.next_date) +
              "</span>"
            : "--") +
          "</td>" +
          "<td>" +
          (r.next_km ? fmt(r.next_km) + " km" : "--") +
          "</td>" +
          "<td>" +
          (diff != null
            ? '<span class="badge ' +
              (diff < 0
                ? "badge-red"
                : diff < 2000
                  ? "badge-amber"
                  : "badge-green") +
              '">' +
              (diff >= 0 ? "+" : "") +
              fmt(diff) +
              " km</span>"
            : "--") +
          "</td></tr>"
        );
      })
      .join("") +
    "</tbody></table></div>";
}

// ─── VEHICLES LIST ────────────────────────────────────────────────────────────
async function loadVehicles() {
  try {
    const list = await api("vehicles");
    const el = $("#vehicles-grid");
    if (!list.length) {
      el.innerHTML =
        '<div class="empty-state"><div class="icon">🚗</div><p>Aucun véhicule. Ajoutez-en un !</p></div>';
      return;
    }
    el.innerHTML = list.map((v) => vehicleCardHTML(v)).join("");
    $$(".vehicle-card", el).forEach((c) =>
      c.addEventListener("click", () =>
        navigate("vehicle", { id: c.dataset.id }),
      ),
    );
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── VEHICLE DETAIL ───────────────────────────────────────────────────────────
async function loadVehicleDetail(id) {
  currentVehicleId = id;
  try {
    const [v, maintenances, parts] = await Promise.all([
      api("vehicles", "GET", null, "&id=" + id),
      api("maintenances", "GET", null, "&vehicle_id=" + id),
      api("parts", "GET", null, "&vehicle_id=" + id),
    ]);
    renderVehicleHeader(v);
    renderMaintenances(maintenances);
    renderVehicleParts(parts);
    setVehicleDocTabBadge(v);
    await loadVehicleDocuments();
    document.title = "GarageManager · " + v.name;
  } catch (e) {
    toast(e.message, "error");
  }
}

function renderVehicleHeader(v) {
  const photoHTML = v.photo
    ? '<img src="' + UPLOADS + v.photo + '" alt="">'
    : '<div class="no-photo">🚗</div>';

  const moCost = parseFloat(v.stats?.total || 0);
  const partsCost = parseFloat(v.parts_stats?.total || 0);
  const totalCost = (moCost + partsCost).toFixed(2);

  $("#vehicle-header").innerHTML =
    '<div class="vehicle-detail-header">' +
    '<div class="vehicle-detail-img">' +
    photoHTML +
    "</div>" +
    '<div class="vehicle-meta">' +
    "<h2>" +
    escHtml(v.name) +
    "</h2>" +
    '<p class="sub">' +
    escHtml(v.brand) +
    " " +
    escHtml(v.model) +
    (v.year ? " · " + v.year : "") +
    "</p>" +
    '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem">' +
    (v.license_plate
      ? '<span class="plate">' + escHtml(v.license_plate) + "</span>"
      : "") +
    '<span class="badge badge-blue">' +
    (v.fuel_type || "Essence") +
    "</span>" +
    (v.color
      ? '<span class="badge badge-gray">🎨 ' + escHtml(v.color) + "</span>"
      : "") +
    "</div>" +
    '<div class="meta-grid">' +
    '<div class="meta-item"><span class="k">Km actuel</span><br><span class="v">' +
    fmt(v.current_km) +
    " km</span></div>" +
    (v.purchase_km != null && v.purchase_km !== ""
      ? '<div class="meta-item"><span class="k">Km à l\'achat</span><br><span class="v">' +
        fmt(v.purchase_km) +
        " km</span></div>" +
        (Number(v.current_km) >= Number(v.purchase_km)
          ? '<div class="meta-item"><span class="k">Parcourus depuis l\'achat</span><br><span class="v">' +
            fmt(Number(v.current_km) - Number(v.purchase_km)) +
            " km</span></div>"
          : "")
      : "") +
    // 🔥 L'AJOUT EST ICI : Affichage de la consommation moyenne si elle existe
    (v.consumption
      ? '<div class="meta-item"><span class="k">Conso. moy.</span><br><span class="v">⛽ ' +
        v.consumption +
        " L/100</span></div>"
      : "") +
    '<div class="meta-item"><span class="k">Nb d\'entretiens</span><br><span class="v">' +
    (v.stats?.cnt || 0) +
    "</span></div>" +
    '<div class="meta-item" title="Total = main d\'œuvre + pièces (hors prix d\'achat)"><span class="k">Total (MO + pièces)</span><br><span class="v">' +
    fmtPrice(totalCost) +
    "</span></div>" +
    '<div class="meta-item" title="Somme des coûts de main d\'œuvre saisis sur les entretiens"><span class="k">dont main d\'œuvre</span><br><span class="v">' +
    fmtPrice(moCost) +
    "</span></div>" +
    '<div class="meta-item" title="Somme (prix × quantité) de toutes les pièces du véhicule"><span class="k">dont pièces</span><br><span class="v">' +
    fmtPrice(partsCost) +
    "</span></div>" +
    (function () {
      if (!v.purchase_date) return "";
      const d0 = new Date(v.purchase_date);
      if (isNaN(d0)) return "";
      const mois = Math.max(1, (Date.now() - d0.getTime()) / 86400000 / 30.4375);
      return (
        '<div class="meta-item" title="Total (MO + pièces) divisé par le nombre de mois depuis la date d\'achat (' +
        mois.toFixed(1).replace(".", ",") +
        ' mois, minimum 1)"><span class="k">Coût mensuel</span><br><span class="v">' +
        fmtPrice(totalCost / mois) +
        " / mois</span></div>"
      );
    })() +
    (v.purchase_date
      ? '<div class="meta-item"><span class="k">Achat</span><br><span class="v">' +
        fmtDate(v.purchase_date) +
        "</span></div>"
      : "") +
    (v.vin
      ? '<div class="meta-item"><span class="k">VIN</span><br><span class="v" style="font-size:.75rem;font-family:monospace">' +
        escHtml(v.vin) +
        "</span></div>"
      : "") +
    "</div>" +
    '<div class="garage-docs-recap">' +
    "📎 <strong>" +
    (Number(v.vehicle_docs_count) || 0) +
    "</strong> " +
    (Number(v.vehicle_docs_count) === 1 ? "document" : "documents") +
    " véhicule · " +
    "<strong>" +
    (Number(v.maint_docs_count) || 0) +
    "</strong> sur entretiens (factures, etc.) · " +
    '<button type="button" class="btn-link-sm" onclick="switchTab(\'documents\')">Voir les documents véhicule</button>' +
    "</div>" +
    "</div>" +
    '<div class="vehicle-detail-actions">' +
    '<button class="btn btn-secondary btn-sm" onclick="openEditVehicle(' +
    v.id +
    ')">✏️ Modifier</button>' +
    '<button class="btn btn-secondary btn-sm" onclick="openUploadPhoto(' +
    v.id +
    ')">📷 Photo</button>' +
    '<button class="btn btn-danger btn-sm" onclick="deleteVehicle(' +
    v.id +
    ')">🗑️ Supprimer</button>' +
    "</div>" +
    "</div>";
}

function renderMaintenances(list) {
  const el = $("#maintenance-list");
  const totalCost = list.reduce(
    (s, m) => s + parseFloat(m.cost || 0) + parseFloat(m.parts_cost || 0),
    0,
  );
  $("#maintenance-total").textContent = fmtPrice(totalCost) + " (MO + pièces rattachées)";
  if (!list.length) {
    el.innerHTML =
      '<div class="empty-state"><div class="icon">🔧</div><p>Aucun entretien enregistré</p></div>';
    return;
  }
  el.innerHTML =
    '<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Kilométrage</th><th>Coût MO</th><th>Pièces</th><th>Prochain</th><th></th></tr></thead><tbody>' +
    list
      .map(
        (m) =>
          '<tr style="cursor:pointer" onclick="navigate(\'maintenance\',{id:' +
          m.id +
          '})">' +
          "<td><strong>" +
          fmtDate(m.date) +
          '</strong><br><span style="color:var(--muted);font-size:.75rem">' +
          ago(m.date) +
          "</span>" +
          (m.documents_count > 0
            ? ' <span class="badge badge-gray garage-maint-doc-badge" title="Documents / factures">📎 ' +
              m.documents_count +
              "</span>"
            : "") +
          "</td>" +
          "<td>" +
          typeBadges(m.type, "badge-blue") +
          "</td>" +
          '<td style="max-width:200px;color:var(--muted)">' +
          (m.description ? escHtml(m.description) : "--") +
          "</td>" +
          "<td>" +
          (m.km ? fmt(m.km) + " km" : "--") +
          "</td>" +
          "<td>" +
          fmtPrice(m.cost) +
          "</td>" +
          "<td>" +
          (m.parts_count > 0
            ? '<span class="badge badge-purple">🔩 ' +
              m.parts_count +
              " (" +
              fmtPrice(m.parts_cost) +
              ")</span>"
            : "--") +
          "</td>" +
          '<td style="font-size:.78rem">' +
          (m.next_date ? "📅 " + fmtDate(m.next_date) : "") +
          " " +
          (m.next_km ? "<br>🛣️ " + fmt(m.next_km) + " km" : "") +
          "</td>" +
          '<td onclick="event.stopPropagation()">' +
          '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditMaintenance(' +
          m.id +
          ')" title="Modifier">✏️</button> ' +
          '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintenance(' +
          m.id +
          ')" title="Supprimer">🗑️</button>' +
          "</td>" +
          "</tr>",
      )
      .join("") +
    "</tbody></table></div>";
  addTableFilters(el, "maint");
}

function renderVehicleParts(list) {
  const el = $("#parts-list-vehicle");
  const total = list.reduce(
    (s, p) => s + parseFloat(p.price || 0) * parseInt(p.quantity || 1),
    0,
  );
  const unlinked = list
    .filter((p) => !p.maintenance_id)
    .reduce((s, p) => s + parseFloat(p.price || 0) * parseInt(p.quantity || 1), 0);
  $("#parts-total-vehicle").textContent =
    fmtPrice(total) + (unlinked > 0 ? " (dont " + fmtPrice(unlinked) + " non rattachées)" : "");
  if (!list.length) {
    el.innerHTML =
      '<div class="empty-state"><div class="icon">🔩</div><p>Aucune pièce enregistrée</p></div>';
    return;
  }
  el.innerHTML =
    '<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Référence</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th>Entretien</th><th></th></tr></thead><tbody>' +
    list
      .map(
        (p) =>
          "<tr>" +
          "<td>" +
          (p.photo
            ? '<img class="part-thumb" src="' + UPLOADS + p.photo + '" alt="">'
            : '<span style="color:var(--muted)">--</span>') +
          "</td>" +
          "<td><strong>" +
          escHtml(p.name) +
          "</strong></td>" +
          "<td>" +
          (p.brand ? escHtml(p.brand) : "--") +
          "</td>" +
          '<td><code style="font-size:.75rem;color:var(--muted)">' +
          (p.reference ? escHtml(p.reference) : "--") +
          "</code></td>" +
          '<td><span class="badge badge-gray">' +
          escHtml(p.category) +
          "</span></td>" +
          "<td>" +
          fmtPrice(p.price) +
          "</td>" +
          "<td>" +
          p.quantity +
          " " +
          escHtml(p.unit || "pièce") +
          "</td>" +
          "<td><strong>" +
          fmtPrice(parseFloat(p.price || 0) * parseInt(p.quantity || 1)) +
          "</strong></td>" +
          '<td style="font-size:.78rem">' +
          (p.maintenance_type
            ? escHtml(p.maintenance_type) +
              " (" +
              fmtDate(p.maintenance_date) +
              ")"
            : "--") +
          "</td>" +
          "<td>" +
          '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart(' +
          p.id +
          ')" title="Modifier">✏️</button> ' +
          '<button class="btn btn-danger btn-sm btn-icon" onclick="deletePart(' +
          p.id +
          ')" title="Supprimer">🗑️</button>' +
          "</td>" +
          "</tr>",
      )
      .join("") +
    "</tbody></table></div>";
  addTableFilters(el, "vparts");
}

// ─── MAINTENANCE DETAIL ───────────────────────────────────────────────────────
async function loadMaintenanceDetail(id) {
  currentMaintenanceId = id;
  try {
    const [m, parts] = await Promise.all([
      api("maintenances", "GET", null, "&id=" + id),
      api("parts", "GET", null, "&maintenance_id=" + id),
    ]);
    currentVehicleId = m.vehicle_id;
    renderMaintenanceDetailHeader(m);
    renderMaintenanceParts(parts, id, m.vehicle_id);
    await loadMaintenanceDocuments();
    loadKnownParts(m.vehicle_id);
    const f = document.getElementById("inline-part-form");
    if (f) f.style.display = "none";
  } catch (e) {
    toast(e.message, "error");
  }
}

function renderMaintenanceDetailHeader(m) {
  document.getElementById("maintenance-detail-header").innerHTML =
    '<div class="vehicle-detail-header" style="align-items:flex-start">' +
    '<div class="vehicle-meta" style="flex:1">' +
    '<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">' +
    typeBadges(m.type, "badge-blue") +
    '<span style="color:var(--muted)">' +
    fmtDate(m.date) +
    " · " +
    ago(m.date) +
    "</span>" +
    (m.documents_count > 0
      ? '<span class="badge badge-gray" title="Documents attachés à cette révision">📎 ' +
        m.documents_count +
        " fichier" +
        (m.documents_count > 1 ? "s" : "") +
        "</span>"
      : "") +
    (m.vehicle_name
      ? '<span style="color:var(--muted);font-size:.85rem">🚗 <a href="#" onclick="navigate(\'vehicle\',{id:' +
        m.vehicle_id +
        '});return false" style="color:inherit;text-decoration:underline">' +
        escHtml(m.vehicle_name) +
        "</a></span>"
      : "") +
    "</div>" +
    (m.description
      ? '<p style="margin-bottom:.75rem">' + escHtml(m.description) + "</p>"
      : "") +
    '<div class="meta-grid">' +
    (m.km
      ? '<div class="meta-item"><span class="k">Kilométrage</span><br><span class="v">' +
        fmt(m.km) +
        " km</span></div>"
      : "") +
    '<div class="meta-item"><span class="k">Coût M.O.</span><br><span class="v">' +
    fmtPrice(m.cost) +
    "</span></div>" +
    (m.mechanic
      ? '<div class="meta-item"><span class="k">Mécanicien</span><br><span class="v">' +
        escHtml(m.mechanic) +
        "</span></div>"
      : "") +
    (m.garage_name
      ? '<div class="meta-item"><span class="k">Garage</span><br><span class="v">' +
        escHtml(m.garage_name) +
        "</span></div>"
      : "") +
    (m.next_date
      ? '<div class="meta-item"><span class="k">Prochain entretien</span><br><span class="v">📅 ' +
        fmtDate(m.next_date) +
        "</span></div>"
      : "") +
    (m.next_km
      ? '<div class="meta-item"><span class="k">Prochain km</span><br><span class="v">🛣️ ' +
        fmt(m.next_km) +
        " km</span></div>"
      : "") +
    "</div>" +
    (m.notes
      ? '<p style="margin-top:.75rem;color:var(--muted);font-size:.85rem;font-style:italic">' +
        escHtml(m.notes) +
        "</p>"
      : "") +
    "</div>" +
    '<div class="vehicle-detail-actions">' +
    '<button class="btn btn-secondary btn-sm" onclick="openEditMaintenance(' +
    m.id +
    ')">✏️ Modifier</button>' +
    '<button class="btn btn-danger btn-sm" onclick="deleteMaintenance(' +
    m.id +
    ')">🗑️ Supprimer</button>' +
    "</div>" +
    "</div>";
}

function renderMaintenanceParts(list, mid, vid) {
  const el = document.getElementById("maintenance-parts-list");
  const cnt = document.getElementById("maint-parts-count");
  if (cnt)
    cnt.textContent = list.length
      ? list.length + " pièce" + (list.length > 1 ? "s" : "")
      : "";
  if (!list.length) {
    el.innerHTML =
      '<div class="empty-state" style="padding:2rem"><div class="icon">🔩</div><p>Aucune pièce utilisée</p></div>';
    return;
  }
  const total = list.reduce(
    (s, p) => s + parseFloat(p.price || 0) * parseInt(p.quantity || 1),
    0,
  );
  el.innerHTML =
    '<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Réf.</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th></th></tr></thead><tbody>' +
    list
      .map(
        (p) =>
          "<tr>" +
          "<td>" +
          (p.photo
            ? '<img class="part-thumb" src="' + UPLOADS + p.photo + '" alt="">'
            : "--") +
          "</td>" +
          "<td><strong>" +
          escHtml(p.name) +
          "</strong></td>" +
          "<td>" +
          (p.brand ? escHtml(p.brand) : "--") +
          "</td>" +
          '<td><code style="font-size:.75rem;color:var(--muted)">' +
          (p.reference ? escHtml(p.reference) : "--") +
          "</code></td>" +
          '<td><span class="badge badge-gray">' +
          escHtml(p.category) +
          "</span></td>" +
          "<td>" +
          fmtPrice(p.price) +
          "</td>" +
          "<td>" +
          p.quantity +
          " " +
          escHtml(p.unit || "pièce") +
          "</td>" +
          "<td><strong>" +
          fmtPrice(parseFloat(p.price || 0) * parseInt(p.quantity || 1)) +
          "</strong></td>" +
          "<td>" +
          '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart(' +
          p.id +
          ')" title="Modifier">✏️</button> ' +
          '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintPart(' +
          p.id +
          "," +
          mid +
          "," +
          vid +
          ')" title="Supprimer">🗑️</button>' +
          "</td>" +
          "</tr>",
      )
      .join("") +
    '<tr style="background:rgba(0,0,0,.02)"><td colspan="7" style="text-align:right;font-weight:600;color:var(--muted)">Total pièces</td><td><strong>' +
    fmtPrice(total) +
    "</strong></td><td></td></tr>" +
    "</tbody></table></div>";
  addTableFilters(el, "mparts");
}

async function deleteMaintPart(id, mid, vid) {
  if (!confirm("Supprimer cette pièce ?")) return;
  try {
    await api("parts", "DELETE", null, "&id=" + id);
    toast("Pièce supprimée");
    const parts = await api("parts", "GET", null, "&maintenance_id=" + mid);
    renderMaintenanceParts(parts, mid, vid);
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── INLINE PART FORM ────────────────────────────────────────────────────────
function toggleInlinePartForm() {
  const f = document.getElementById("inline-part-form");
  if (!f) return;
  const visible = f.style.display === "block";
  f.style.display = visible ? "none" : "block";
  if (!visible) document.getElementById("ipart-name")?.focus();
}

async function loadKnownParts(vehicleId) {
  try {
    const parts = await api("parts", "GET", null, "&vehicle_id=" + vehicleId);
    const el = document.getElementById("known-parts-list");
    if (!el) return;
    if (!parts.length) {
      el.innerHTML =
        '<p style="color:var(--muted);font-size:.8rem;margin-bottom:.75rem">Aucune pièce connue pour ce véhicule</p>';
      return;
    }
    const seen = new Set();
    const unique = parts.filter((p) => {
      if (seen.has(p.name)) return false;
      seen.add(p.name);
      return true;
    });
    const wrap = document.createElement("div");
    wrap.className = "scroll-list";
    unique.forEach((p, i) => {
      const lbl = document.createElement("label");
      lbl.className = "scroll-list-item";
      lbl.innerHTML =
        '<input type="radio" name="_prefill" value="' +
        i +
        '" style="width:auto;background:none;border:none;padding:0;accent-color:var(--primary)">' +
        '<span class="item-name">' +
        escHtml(p.name) +
        "</span>" +
        (p.reference
          ? '<span class="item-ref">' + escHtml(p.reference) + "</span>"
          : "") +
        (p.brand
          ? '<span class="item-brand">' + escHtml(p.brand) + "</span>"
          : "") +
        '<span class="item-price">' +
        fmtPrice(p.price) +
        "</span>";
      lbl.querySelector("input").addEventListener("change", () => {
        const n = document.getElementById("ipart-name");
        const b = document.getElementById("ipart-brand");
        const r = document.getElementById("ipart-reference");
        const pr = document.getElementById("ipart-price");
        if (n) n.value = p.name;
        if (b) b.value = p.brand || "";
        if (r) r.value = p.reference || "";
        if (pr) {
          pr.value = p.price;
          updateIPriceTTC();
        }
      });
      wrap.appendChild(lbl);
    });
    el.innerHTML = "";
    el.appendChild(wrap);
  } catch (e) {}
}

function updateIPriceTTC() {
  const ht = document.getElementById("ipart-prix-ht")?.checked;
  const price = parseFloat(document.getElementById("ipart-price")?.value) || 0;
  const preview = document.getElementById("ipart-ttc-preview");
  if (!preview) return;
  if (ht && price > 0) {
    preview.textContent = "→ Prix TTC : " + (price * 1.2).toFixed(2) + " €";
    preview.style.display = "block";
  } else {
    preview.style.display = "none";
  }
}

async function saveInlinePart() {
  const name = document.getElementById("ipart-name")?.value.trim();
  if (!name) {
    toast("Nom requis", "error");
    return;
  }
  let price = parseFloat(document.getElementById("ipart-price")?.value) || 0;
  if (document.getElementById("ipart-prix-ht")?.checked)
    price = parseFloat((price * 1.2).toFixed(2));
  const fd = new FormData();
  fd.append("name", name);
  fd.append("vehicle_id", currentVehicleId || "");
  fd.append("maintenance_id", currentMaintenanceId || "");
  fd.append("price", price);
  fd.append(
    "quantity",
    parseInt(document.getElementById("ipart-quantity")?.value) || 1,
  );
  fd.append("category", "Autre");
  fd.append("unit", "pièce");
  const brand = document.getElementById("ipart-brand")?.value.trim();
  const ref = document.getElementById("ipart-reference")?.value.trim();
  if (brand) fd.append("brand", brand);
  if (ref) fd.append("reference", ref);
  const photoFile = document.getElementById("ipart-photo")?.files[0];
  if (photoFile) fd.append("photo", photoFile);
  try {
    await api("parts", "POST", fd);
    toast("Pièce ajoutée");
    ["ipart-name", "ipart-brand", "ipart-reference"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = "";
    });
    const pr = document.getElementById("ipart-price");
    if (pr) pr.value = "0";
    const qty = document.getElementById("ipart-quantity");
    if (qty) qty.value = "1";
    const ht = document.getElementById("ipart-prix-ht");
    if (ht) ht.checked = false;
    const prev = document.getElementById("ipart-ttc-preview");
    if (prev) prev.style.display = "none";
    const ph = document.getElementById("ipart-photo");
    if (ph) ph.value = "";
    const parts = await api(
      "parts",
      "GET",
      null,
      "&maintenance_id=" + currentMaintenanceId,
    );
    renderMaintenanceParts(parts, currentMaintenanceId, currentVehicleId);
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── ALL PARTS ────────────────────────────────────────────────────────────────
async function loadParts() {
  try {
    const list = await api("parts");
    const el = $("#all-parts-list");
    const total = list.reduce(
      (s, p) => s + parseFloat(p.price || 0) * parseInt(p.quantity || 1),
      0,
    );
    $("#all-parts-total").textContent = fmtPrice(total);
    $("#all-parts-count").textContent = list.length;
    if (!list.length) {
      el.innerHTML =
        '<div class="empty-state"><div class="icon">🔩</div><p>Aucune pièce</p></div>';
      return;
    }
    const byVehicle = {};
    list.forEach((p) => {
      const key = p.vehicle_name || "Sans véhicule";
      if (!byVehicle[key]) byVehicle[key] = [];
      byVehicle[key].push(p);
    });
    let html = "";
    for (const [vname, parts] of Object.entries(byVehicle)) {
      const vtotal = parts.reduce(
        (s, p) => s + parseFloat(p.price || 0) * parseInt(p.quantity || 1),
        0,
      );
      html +=
        '<div style="margin-bottom:2rem">' +
        '<div class="card-header" style="margin-bottom:.5rem">' +
        '<h3 style="font-size:1rem;font-weight:700">🚗 ' +
        escHtml(vname) +
        "</h3>" +
        '<span style="margin-left:auto;color:var(--muted);font-size:.85rem">' +
        parts.length +
        " pièce" +
        (parts.length > 1 ? "s" : "") +
        " · " +
        fmtPrice(vtotal) +
        "</span>" +
        "</div>" +
        '<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Référence</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th>Entretien</th><th></th></tr></thead><tbody>' +
        parts
          .map(
            (p) =>
              "<tr>" +
              "<td>" +
              (p.photo
                ? '<img class="part-thumb" src="' +
                  UPLOADS +
                  p.photo +
                  '" alt="">'
                : "--") +
              "</td>" +
              "<td><strong>" +
              escHtml(p.name) +
              "</strong></td>" +
              "<td>" +
              (p.brand ? escHtml(p.brand) : "--") +
              "</td>" +
              '<td><code style="font-size:.75rem;color:var(--muted)">' +
              (p.reference ? escHtml(p.reference) : "--") +
              "</code></td>" +
              '<td><span class="badge badge-gray">' +
              escHtml(p.category) +
              "</span></td>" +
              "<td>" +
              fmtPrice(p.price) +
              "</td>" +
              "<td>" +
              p.quantity +
              " " +
              escHtml(p.unit || "pièce") +
              "</td>" +
              "<td><strong>" +
              fmtPrice(parseFloat(p.price || 0) * parseInt(p.quantity || 1)) +
              "</strong></td>" +
              '<td style="font-size:.78rem">' +
              (p.maintenance_type
                ? escHtml(p.maintenance_type) +
                  " (" +
                  fmtDate(p.maintenance_date) +
                  ")"
                : "--") +
              "</td>" +
              "<td>" +
              '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart(' +
              p.id +
              ')" title="Modifier">✏️</button> ' +
              '<button class="btn btn-danger btn-sm btn-icon" onclick="deletePart(' +
              p.id +
              ')" title="Supprimer">🗑️</button>' +
              "</td>" +
              "</tr>",
          )
          .join("") +
        "</tbody></table></div></div>";
    }
    el.innerHTML = html;
    addTableFilters(el, "allparts");
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── ALL MAINTENANCES ─────────────────────────────────────────────────────────
async function loadAllMaintenances() {
  try {
    const vehicles = await api("vehicles");
    const el = $("#all-maintenances-list");
    if (!vehicles.length) {
      el.innerHTML =
        '<div class="empty-state"><div class="icon">🔧</div><p>Aucun véhicule</p></div>';
      return;
    }
    let totalAll = 0,
      countAll = 0,
      html = "";
    for (const v of vehicles) {
      const mlist = await api(
        "maintenances",
        undefined,
        null,
        "&vehicle_id=" + v.id,
      );
      if (!mlist.length) continue;
      const total = mlist.reduce(
        (s, m) => s + parseFloat(m.cost || 0) + parseFloat(m.parts_cost || 0),
        0,
      );
      totalAll += total;
      countAll += mlist.length;
      html +=
        '<div style="margin-bottom:2rem">' +
        '<div class="card-header" style="margin-bottom:.5rem">' +
        '<h3 style="font-size:1rem;font-weight:700">🚗 ' +
        escHtml(v.name) +
        "</h3>" +
        (v.license_plate
          ? '<span class="plate">' + escHtml(v.license_plate) + "</span>"
          : "") +
        '<span style="margin-left:auto;color:var(--muted);font-size:.85rem">' +
        mlist.length +
        " entretiens · " +
        fmtPrice(total) +
        "</span>" +
        "</div>" +
        '<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Km</th><th>Coût M.O.</th><th>Pièces</th><th>Total</th><th>Mécanicien</th><th></th></tr></thead><tbody>' +
        mlist
          .map(
            (m) =>
              '<tr style="cursor:pointer" onclick="navigate(\'maintenance\',{id:' +
              m.id +
              '})">' +
              "<td>" +
              fmtDate(m.date) +
              "</td>" +
              "<td>" +
              typeBadges(m.type, "badge-gray") +
              "</td>" +
              "<td>" +
              (m.description ? escHtml(m.description) : "--") +
              "</td>" +
              "<td>" +
              (m.km ? fmt(m.km) + " km" : "--") +
              "</td>" +
              "<td>" +
              fmtPrice(m.cost) +
              "</td>" +
              "<td>" +
              (m.parts_count > 0
                ? m.parts_count + " (" + fmtPrice(m.parts_cost) + ")"
                : "--") +
              "</td>" +
              "<td><strong>" +
              fmtPrice(
                parseFloat(m.cost || 0) + parseFloat(m.parts_cost || 0),
              ) +
              "</strong></td>" +
              "<td>" +
              (m.mechanic ? escHtml(m.mechanic) : "--") +
              "</td>" +
              '<td onclick="event.stopPropagation()">' +
              '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintenance(' +
              m.id +
              ')" title="Supprimer">🗑️</button>' +
              "</td>" +
              "</tr>",
          )
          .join("") +
        "</tbody></table></div></div>";
    }
    $("#all-maint-count").textContent = countAll;
    $("#all-maint-total").textContent = fmtPrice(totalAll) + " (MO + pièces)";
    el.innerHTML =
      html ||
      '<div class="empty-state"><div class="icon">🔧</div><p>Aucun entretien</p></div>';
    addTableFilters(el, "allmaint");
  } catch (e) {
    console.error(e);
  }
}

// ─── MODALS ───────────────────────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add("show");
}
function closeModal(id) {
  document.getElementById(id).classList.remove("show");
}

// ─── TVA ──────────────────────────────────────────────────────────────────────
function updatePartPriceTTC() {
  const ht = document.getElementById("part-prix-ht")?.checked;
  const price = parseFloat(document.getElementById("part-price")?.value) || 0;
  const preview = document.getElementById("part-ttc-preview");
  if (!preview) return;
  if (ht && price > 0) {
    preview.textContent = "→ Prix TTC : " + (price * 1.2).toFixed(2) + " €";
    preview.style.display = "block";
  } else {
    preview.style.display = "none";
  }
}

// ─── VEHICLE CRUD ─────────────────────────────────────────────────────────────
function openAddVehicle() {
  $("#form-vehicle").reset();
  $("#form-vehicle-id").value = "";
  $("#modal-vehicle-title").textContent = "Ajouter un véhicule";
  $("#vehicle-photo-preview").src = "";
  $("#vehicle-photo-preview").style.display = "none";
  openModal("modal-vehicle");
}
function openEditVehicle(id) {
  api("vehicles", "GET", null, "&id=" + id)
    .then((v) => {
      $("#form-vehicle-id").value = v.id;
      $("#modal-vehicle-title").textContent = "Modifier le véhicule";
      [
        "name",
        "brand",
        "model",
        "year",
        "license_plate",
        "vin",
        "color",
        "purchase_date",
        "purchase_price",
        "purchase_km",
        "current_km",
        "consumption",
        "notes",
      ].forEach((f) => {
        const el = $("#vehicle-" + f.replace(/_/g, "-"));
        if (el) el.value = v[f] ?? "";
      });
      setSelectVal("vehicle-fuel-type", v.fuel_type);
      if (v.photo) {
        $("#vehicle-photo-preview").src = UPLOADS + v.photo;
        $("#vehicle-photo-preview").style.display = "block";
      } else {
        $("#vehicle-photo-preview").style.display = "none";
      }
      openModal("modal-vehicle");
    })
    .catch((e) => toast(e.message, "error"));
}
async function saveVehicle() {
  const id = $("#form-vehicle-id").value;
  const fd = new FormData($("#form-vehicle"));
  try {
    if (id) {
      const d = {};
      for (const [k, v] of fd.entries()) d[k] = v;
      await api("vehicles", "PUT", d, "&id=" + id);
      const photoFile = $("#vehicle-photo").files[0];
      if (photoFile) {
        const pfd = new FormData();
        pfd.append("photo", photoFile);
        await fetch(API + "?action=upload_vehicle_photo&id=" + id, {
          method: "POST",
          body: pfd,
        });
      }
      toast("Véhicule modifié");
    } else {
      await api("vehicles", "POST", fd);
      toast("Véhicule ajouté");
    }
    closeModal("modal-vehicle");
    if (currentPage === "vehicles") loadVehicles();
    else if (currentPage === "vehicle") loadVehicleDetail(currentVehicleId);
    else loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}
async function deleteVehicle(id) {
  if (!confirm("Supprimer ce véhicule et tout son historique ?")) return;
  try {
    await api("vehicles", "DELETE", null, "&id=" + id);
    toast("Véhicule supprimé");
    navigate("vehicles");
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── MAINTENANCE CRUD ─────────────────────────────────────────────────────────
function openAddMaintenance() {
  $("#form-maintenance").reset();
  $("#form-maintenance-id").value = "";
  $("#maintenance-vehicle-id").value = currentVehicleId || "";
  $("#modal-maintenance-title").textContent = "Ajouter un entretien";
  $("#maintenance-date").value = new Date().toISOString().slice(0, 10);
  buildTypeBox("");
  openModal("modal-maintenance");
}
function setSelectVal(elId, val) {
  const el = document.getElementById(elId);
  if (!el || val == null) return;
  el.value = val;
  if (el.value !== String(val) && val !== "") {
    const o = new Option(val, val);
    el.add(o);
    el.value = val;
  }
}
function openEditMaintenance(id) {
  api("maintenances", "GET", null, "&id=" + id)
    .then((m) => {
      $("#form-maintenance-id").value = m.id;
      $("#maintenance-vehicle-id").value = m.vehicle_id;
      $("#modal-maintenance-title").textContent = "Modifier l'entretien";
      buildTypeBox(m.type);
      const map = {
        description: "maintenance-description",
        date: "maintenance-date",
        km: "maintenance-km",
        cost: "maintenance-cost",
        mechanic: "maintenance-mechanic",
        garage_name: "maintenance-garage",
        next_date: "maintenance-next-date",
        next_km: "maintenance-next-km",
        notes: "maintenance-notes",
      };
      for (const [k, elId] of Object.entries(map)) {
        const el = document.getElementById(elId);
        if (el) el.value = m[k] ?? "";
      }
      openModal("modal-maintenance");
    })
    .catch((e) => toast(e.message, "error"));
}
async function saveMaintenance() {
  const id = $("#form-maintenance-id").value;
  const d = {};
  new FormData($("#form-maintenance")).forEach((v, k) => (d[k] = v));
  d.type = selectedTypes().join(", ");
  if (!d.type) return toast("Choisissez au moins un type d'entretien", "error");
  try {
    if (id) {
      await api("maintenances", "PUT", d, "&id=" + id);
      toast("Entretien modifié");
    } else {
      await api("maintenances", "POST", d);
      toast("Entretien ajouté");
    }
    closeModal("modal-maintenance");
    if (currentPage === "maintenance")
      loadMaintenanceDetail(currentMaintenanceId);
    else loadVehicleDetail(currentVehicleId);
  } catch (e) {
    toast(e.message, "error");
  }
}
async function deleteMaintenance(id) {
  if (!confirm("Supprimer cet entretien ?")) return;
  try {
    await api("maintenances", "DELETE", null, "&id=" + id);
    toast("Entretien supprimé");
    if (currentPage === "maintenance")
      navigate("vehicle", { id: currentVehicleId });
    else loadVehicleDetail(currentVehicleId);
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── PARTS CRUD ───────────────────────────────────────────────────────────────
function openAddPart() {
  $("#form-part").reset();
  $("#form-part-id").value = "";
  $("#part-vehicle-id").value = currentVehicleId || "";
  $("#part-maintenance-id").value = currentMaintenanceId || "";
  $("#modal-part-title").textContent = "Ajouter une pièce";
  $("#part-photo-preview").src = "";
  $("#part-photo-preview").style.display = "none";
  const ht = document.getElementById("part-prix-ht");
  if (ht) ht.checked = false;
  const prev = document.getElementById("part-ttc-preview");
  if (prev) prev.style.display = "none";
  openModal("modal-part");
}
function openEditPart(id) {
  api("parts", "GET", null, "&id=" + id)
    .then((p) => {
      $("#form-part-id").value = p.id;
      $("#part-vehicle-id").value = p.vehicle_id || "";
      $("#part-maintenance-id").value = p.maintenance_id || "";
      $("#modal-part-title").textContent = "Modifier la pièce";
      const map = {
        name: "part-name",
        brand: "part-brand",
        reference: "part-reference",
        price: "part-price",
        quantity: "part-quantity",
        notes: "part-notes",
      };
      for (const [k, elId] of Object.entries(map)) {
        const el = document.getElementById(elId);
        if (el) el.value = p[k] ?? "";
      }
      setSelectVal("part-category", p.category);
      const ht = document.getElementById("part-prix-ht");
      if (ht) ht.checked = false;
      const prev = document.getElementById("part-ttc-preview");
      if (prev) prev.style.display = "none";
      if (p.photo) {
        $("#part-photo-preview").src = UPLOADS + p.photo;
        $("#part-photo-preview").style.display = "block";
      } else {
        $("#part-photo-preview").style.display = "none";
      }
      openModal("modal-part");
    })
    .catch((e) => toast(e.message, "error"));
}
async function savePart() {
  const id = $("#form-part-id").value;
  const fd = new FormData($("#form-part"));
  if (document.getElementById("part-prix-ht")?.checked) {
    const htPrice = parseFloat(fd.get("price")) || 0;
    fd.set("price", (htPrice * 1.2).toFixed(2));
  }
  try {
    if (id) {
      const d = {};
      for (const [k, v] of fd.entries()) d[k] = v;
      await api("parts", "PUT", d, "&id=" + id);
      const photoFile = $("#part-photo").files[0];
      if (photoFile) {
        const pfd = new FormData();
        pfd.append("photo", photoFile);
        await fetch(API + "?action=upload_part_photo&id=" + id, {
          method: "POST",
          body: pfd,
        });
      }
      toast("Pièce modifiée");
    } else {
      await api("parts", "POST", fd);
      toast("Pièce ajoutée");
    }
    closeModal("modal-part");
    if (currentPage === "maintenance")
      loadMaintenanceDetail(currentMaintenanceId);
    else if (currentPage === "vehicle") loadVehicleDetail(currentVehicleId);
    else loadParts();
  } catch (e) {
    toast(e.message, "error");
  }
}
async function deletePart(id) {
  if (!confirm("Supprimer cette pièce ?")) return;
  try {
    await api("parts", "DELETE", null, "&id=" + id);
    toast("Pièce supprimée");
    if (currentPage === "maintenance")
      loadMaintenanceDetail(currentMaintenanceId);
    else if (currentPage === "vehicle") loadVehicleDetail(currentVehicleId);
    else loadParts();
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── UPLOAD PHOTO ─────────────────────────────────────────────────────────────
function openUploadPhoto(id) {
  $("#upload-vehicle-id").value = id;
  openModal("modal-upload-photo");
}
async function doUploadPhoto() {
  const id = $("#upload-vehicle-id").value;
  const file = $("#upload-photo-file").files[0];
  if (!file) {
    toast("Choisissez une photo", "error");
    return;
  }
  const fd = new FormData();
  fd.append("photo", file);
  try {
    await fetch(API + "?action=upload_vehicle_photo&id=" + id, {
      method: "POST",
      body: fd,
    });
    toast("Photo mise à jour");
    closeModal("modal-upload-photo");
    loadVehicleDetail(id);
  } catch (e) {
    toast(e.message, "error");
  }
}

function setVehicleDocTabBadge(v) {
  const el = document.getElementById("vehicle-docs-tab-count");
  if (!el) return;
  const n = Number(v.vehicle_docs_count) || 0;
  el.textContent = n > 0 ? "(" + n + ")" : "";
}

async function loadVehicleDocuments() {
  if (!currentVehicleId) return;
  try {
    const list = await api(
      "garage_documents",
      "GET",
      null,
      "&vehicle_id=" + currentVehicleId + "&vehicle_only=1",
    );
    renderGarageDocList("vehicle-documents-list", list);
  } catch (e) {
    toast(e.message, "error");
  }
}

async function loadMaintenanceDocuments() {
  if (!currentMaintenanceId) return;
  try {
    const list = await api(
      "garage_documents",
      "GET",
      null,
      "&maintenance_id=" + currentMaintenanceId,
    );
    renderGarageDocList("maintenance-documents-list", list);
  } catch (e) {
    toast(e.message, "error");
  }
}

function renderGarageDocList(elId, list) {
  const el = document.getElementById(elId);
  if (!el) return;
  if (!list || !list.length) {
    el.innerHTML =
      '<div class="empty-state garage-doc-empty"><p>Aucun document pour l’instant</p></div>';
    return;
  }
  el.innerHTML =
    '<ul class="garage-doc-list">' +
    list
      .map((d) => {
        const href =
          API + "?action=garage_document&id=" + encodeURIComponent(d.id);
        const title = escHtml(
          d.label || d.original_name || d.filename || "Document",
        );
        const meta = fmtDate(d.created_at) + " · " + formatBytes(d.size || 0);
        return (
          '<li class="garage-doc-item">' +
          '<a class="garage-doc-link" href="' +
          href +
          '" target="_blank" rel="noopener noreferrer">📄 ' +
          title +
          "</a>" +
          '<span class="garage-doc-meta">' +
          meta +
          "</span>" +
          '<button type="button" class="btn btn-danger btn-sm btn-icon" onclick="deleteGarageDocument(' +
          parseInt(d.id, 10) +
          ')" title="Supprimer">🗑️</button>' +
          "</li>"
        );
      })
      .join("") +
    "</ul>";
}

async function uploadVehicleDocument() {
  const inp = document.getElementById("vehicle-doc-file");
  if (!inp || !inp.files[0]) {
    toast("Choisissez un fichier", "error");
    return;
  }
  const fd = new FormData();
  fd.append("vehicle_id", String(currentVehicleId));
  fd.append("file", inp.files[0]);
  const lab = document.getElementById("vehicle-doc-label")?.value.trim();
  if (lab) fd.append("label", lab);
  try {
    const r = await fetch(API + "?action=garage_documents", {
      method: "POST",
      body: fd,
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || "Erreur");
    toast("Document ajouté");
    inp.value = "";
    const ll = document.getElementById("vehicle-doc-label");
    if (ll) ll.value = "";
    const v = await api("vehicles", "GET", null, "&id=" + currentVehicleId);
    renderVehicleHeader(v);
    setVehicleDocTabBadge(v);
    await loadVehicleDocuments();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function uploadMaintenanceDocument() {
  const inp = document.getElementById("maint-doc-file");
  if (!inp || !inp.files[0]) {
    toast("Choisissez un fichier", "error");
    return;
  }
  if (!currentVehicleId || !currentMaintenanceId) {
    toast("Contexte manquant", "error");
    return;
  }
  const fd = new FormData();
  fd.append("vehicle_id", String(currentVehicleId));
  fd.append("maintenance_id", String(currentMaintenanceId));
  fd.append("file", inp.files[0]);
  const lab = document.getElementById("maint-doc-label")?.value.trim();
  if (lab) fd.append("label", lab);
  try {
    const r = await fetch(API + "?action=garage_documents", {
      method: "POST",
      body: fd,
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || "Erreur");
    toast("Document ajouté");
    inp.value = "";
    const ll = document.getElementById("maint-doc-label");
    if (ll) ll.value = "";
    const m = await api(
      "maintenances",
      "GET",
      null,
      "&id=" + currentMaintenanceId,
    );
    renderMaintenanceDetailHeader(m);
    await loadMaintenanceDocuments();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function deleteGarageDocument(id) {
  if (!confirm("Supprimer ce document ?")) return;
  try {
    await api("garage_documents", "DELETE", null, "&id=" + id);
    toast("Document supprimé");
    if (currentPage === "vehicle") {
      const v = await api("vehicles", "GET", null, "&id=" + currentVehicleId);
      renderVehicleHeader(v);
      setVehicleDocTabBadge(v);
      await loadVehicleDocuments();
    } else if (currentPage === "maintenance") {
      const m = await api(
        "maintenances",
        "GET",
        null,
        "&id=" + currentMaintenanceId,
      );
      renderMaintenanceDetailHeader(m);
      await loadMaintenanceDocuments();
    }
  } catch (e) {
    toast(e.message, "error");
  }
}

// ─── TABS ────────────────────────────────────────────────────────────────────
function switchTab(tab) {
  $$(".tab-btn").forEach((b) => b.classList.remove("active"));
  $$(".tab-pane").forEach((p) => p.classList.remove("active"));
  document
    .querySelector('.tab-btn[data-tab="' + tab + '"]')
    ?.classList.add("active");
  document.getElementById("tab-" + tab)?.classList.add("active");
  if (tab === "documents" && currentVehicleId) loadVehicleDocuments();
}

// ─── PHOTO PREVIEW ────────────────────────────────────────────────────────────
function previewPhoto(inputId, previewId) {
  const file = document.getElementById(inputId).files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    const img = document.getElementById(previewId);
    img.src = e.target.result;
    img.style.display = "block";
  };
  reader.readAsDataURL(file);
}

// ─── INIT ────────────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  $$(".nav-link").forEach((n) =>
    n.addEventListener("click", () => navigate(n.dataset.page)),
  );
  $$(".pf-modal").forEach((m) =>
    m.addEventListener("click", (e) => {
      if (e.target === m) m.classList.remove("show");
    }),
  );
  navigate("dashboard");
});

// ─── TYPES D'ENTRETIEN (sélection multiple, stockés « A, B, C ») ──────────────
const MAINT_TYPES = [
  "Vidange", "Révision", "Freins", "Pneus", "Distribution", "Filtres", "Batterie",
  "Climatisation", "Suspension", "Transmission", "Embrayage", "Moteur",
  "Refroidissement", "Échappement", "Électricité", "Éclairage", "Essuie-glaces",
  "Géométrie / direction", "Carrosserie", "Diagnostic", "Contrôle technique", "Autre",
];
function splitTypes(t) {
  return String(t || "").split(",").map((x) => x.trim()).filter(Boolean);
}
function typeBadges(t, cls) {
  const l = splitTypes(t);
  if (!l.length) return "--";
  return l.map((x) => '<span class="badge ' + (cls || "badge-blue") + '">' + escHtml(x) + "</span>").join(" ");
}
function buildTypeBox(selected) {
  const sel = splitTypes(selected);
  const all = MAINT_TYPES.concat(sel.filter((x) => !MAINT_TYPES.includes(x)));
  document.getElementById("maintenance-type-box").innerHTML = all
    .map(
      (t) =>
        '<label class="type-chip"><input type="checkbox" value="' + escHtml(t) + '"' +
        (sel.includes(t) ? " checked" : "") + "><span>" + escHtml(t) + "</span></label>",
    )
    .join("");
}
function selectedTypes() {
  return Array.from(document.querySelectorAll("#maintenance-type-box input:checked")).map((i) => i.value);
}

// ─── FILTRES DANS LES EN-TÊTES DES TABLEAUX ───────────────────────────────────
const TF_SELECT_COLS = ["Type", "Catégorie", "Marque", "Entretien", "Mécanicien"];
const TF_SKIP_COLS = ["", "Photo"];
const tfState = {};
function tfNorm(x) {
  return String(x || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().trim();
}
function addTableFilters(root, keyPrefix) {
  if (!root) return;
  root.querySelectorAll("table").forEach((table, ti) => {
    const head = table.tHead && table.tHead.rows[0];
    const body = table.tBodies[0];
    if (!head || !body || body.rows.length < 2) return;
    const heads = Array.from(head.cells).map((c) => c.textContent.trim());
    const key = (keyPrefix || "t") + ":" + ti + ":" + heads.join("|");
    const st = tfState[key] || (tfState[key] = {});
    const row = document.createElement("tr");
    row.className = "tf-row";
    const apply = () => {
      const f = Array.from(row.cells).map((c) => {
        const el = c.firstChild;
        return el ? { v: el.value, sel: el.tagName === "SELECT" } : null;
      });
      Array.from(body.rows).forEach((tr) => {
        let ok = true;
        f.forEach((fi, i) => {
          if (!ok || !fi || !fi.v) return;
          const cell = tr.cells[i];
          if (!cell) return;
          if (fi.sel) {
            const toks = Array.from(cell.querySelectorAll(".badge")).map((b) => b.textContent.trim());
            const vals = toks.length ? toks : [cell.textContent.trim()];
            ok = vals.some((x) => tfNorm(x) === tfNorm(fi.v));
          } else {
            ok = tfNorm(cell.textContent).includes(tfNorm(fi.v));
          }
        });
        tr.style.display = ok ? "" : "none";
      });
    };
    heads.forEach((h, i) => {
      const th = document.createElement("th");
      if (TF_SKIP_COLS.includes(h)) { row.appendChild(th); return; }
      let el;
      if (TF_SELECT_COLS.includes(h)) {
        const set = new Set();
        Array.from(body.rows).forEach((tr) => {
          const c = tr.cells[i];
          if (!c) return;
          const bs = Array.from(c.querySelectorAll(".badge")).map((b) => b.textContent.trim());
          (bs.length ? bs : [c.textContent.trim()]).forEach((x) => { if (x && x !== "--") set.add(x); });
        });
        el = document.createElement("select");
        el.innerHTML = '<option value="">Tous</option>' +
          Array.from(set).sort((a, b) => a.localeCompare(b, "fr")).map((x) => "<option>" + escHtml(x) + "</option>").join("");
      } else {
        el = document.createElement("input");
        el.type = "search";
        el.placeholder = "Filtrer";
      }
      el.className = "tf-input";
      el.value = st[i] || "";
      if (el.tagName === "SELECT" && el.value !== (st[i] || "")) el.value = "";
      el.addEventListener("input", () => { st[i] = el.value; apply(); });
      el.addEventListener("click", (e) => e.stopPropagation());
      th.appendChild(el);
      row.appendChild(th);
    });
    table.tHead.appendChild(row);
    apply();
  });
}
