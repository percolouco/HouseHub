const iosEventsList = document.getElementById("ios-events-list");
const iosSyncStatus = document.getElementById("ios-sync-status");
const iosForm = document.getElementById("ios-event-form");

async function iosApi(action, options = {}) {
  const method = options.method || "GET";
  const headers = {
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(options.headers || {}),
  };
  if (method !== "GET") {
    headers["X-CSRF-Token"] = window.CSRF_TOKEN || "";
    headers["Content-Type"] = "application/json";
  }
  const response = await fetch(`/modules/calendar-ios/api.php?action=${encodeURIComponent(action)}`, {
    method,
    credentials: "same-origin",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const json = await response.json();
  if (!json.ok) throw new Error(json.error || "Erreur inconnue");
  return json.data;
}

function formatDate(iso) {
  const d = new Date(iso);
  return d.toLocaleString();
}

function fillForm(evt) {
  document.getElementById("ios-event-id").value = evt.id;
  document.getElementById("ios-title").value = evt.title || "";
  document.getElementById("ios-location").value = evt.location || "";
  document.getElementById("ios-description").value = evt.description || "";
  document.getElementById("ios-start").value = (evt.start_at || "").slice(0, 16);
  document.getElementById("ios-end").value = (evt.end_at || "").slice(0, 16);
}

function resetForm() {
  iosForm.reset();
  document.getElementById("ios-event-id").value = "";
}

async function loadEvents() {
  const events = await iosApi("events");
  iosEventsList.innerHTML = "";
  if (!events.length) {
    iosEventsList.innerHTML = "<div class='pf-muted-note'>Aucun événement.</div>";
    return;
  }
  events.forEach((evt) => {
    const card = document.createElement("div");
    card.className = "ios-event-card";
    card.innerHTML = `
      <div class="ios-event-card-head">
        <div>
          <div class="ios-event-card-title">${evt.title || "(Sans titre)"}</div>
          <div class="ios-event-card-meta">${formatDate(evt.start_at)} → ${formatDate(evt.end_at)}</div>
          <div class="ios-event-card-meta">${evt.location || ""}</div>
        </div>
        <div class="ios-event-card-actions">
          <button class="pf-btn btn-secondary" data-edit="${evt.id}">Modifier</button>
          <button class="pf-btn btn-secondary" data-delete="${evt.id}">Supprimer</button>
        </div>
      </div>
    `;
    card.querySelector("[data-edit]").addEventListener("click", () => fillForm(evt));
    card.querySelector("[data-delete]").addEventListener("click", async () => {
      if (!confirm("Supprimer cet événement ?")) return;
      await iosApi("events", { method: "DELETE", body: { id: evt.id } });
      await loadEvents();
    });
    iosEventsList.appendChild(card);
  });
}

async function loadSyncStatus() {
  const status = await iosApi("sync_status");
  iosSyncStatus.textContent = status.message;
}

iosForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const id = document.getElementById("ios-event-id").value;
  const payload = {
    id: id ? parseInt(id, 10) : null,
    title: document.getElementById("ios-title").value.trim(),
    location: document.getElementById("ios-location").value.trim(),
    description: document.getElementById("ios-description").value.trim(),
    start_at: document.getElementById("ios-start").value,
    end_at: document.getElementById("ios-end").value,
  };
  await iosApi("events", { method: id ? "PUT" : "POST", body: payload });
  resetForm();
  await loadEvents();
});

document.getElementById("ios-form-reset").addEventListener("click", resetForm);
document.getElementById("ios-sync-btn").addEventListener("click", async () => {
  document.getElementById("ios-sync-btn").disabled = true;
  try {
    const data = await iosApi("sync", { method: "POST", body: {} });
    iosSyncStatus.textContent = data.message;
    await loadEvents();
  } finally {
    document.getElementById("ios-sync-btn").disabled = false;
  }
});

window.addEventListener("DOMContentLoaded", async () => {
  try {
    await loadEvents();
    await loadSyncStatus();
  } catch (e) {
    iosSyncStatus.textContent = e.message;
  }
});
