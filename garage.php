<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/i18n.php';

$pageTitle = tr('garage_page_title');
$activePage = "garage";
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="/modules/garage/assets/garage.css">
<div id="toasts" class="toast-container"></div>

<!-- Sous-navigation -->
<nav class="garage-subnav">
  <button class="nav-link active" data-page="dashboard">📊 Dashboard</button>
  <button class="nav-link" data-page="vehicles">🚗 Véhicules</button>
  <button class="nav-link" data-page="maintenances-all">🔧 Entretiens</button>
  <button class="nav-link" data-page="parts">🔩 Pièces</button>
</nav>

<!-- ── DASHBOARD ─────────────────────────────────────────────────────────── -->
<div id="page-dashboard" class="page active">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-pill blue"><div class="label">Véhicules</div><div class="value" id="stat-vehicles">--</div></div>
      <div class="stat-pill green"><div class="label">Entretiens</div><div class="value" id="stat-maintenances">--</div></div>
      <div class="stat-pill amber"><div class="label">Pièces</div><div class="value" id="stat-parts">--</div></div>
      <div class="stat-pill red"><div class="label">Coût total</div><div class="value" id="stat-cost">--</div></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">
      <div>
        <div class="card-header" style="margin-bottom:.75rem">
          <h2 style="font-size:1rem;font-weight:600">Mes véhicules</h2>
          <button class="btn btn-primary btn-sm" onclick="openAddVehicle()">+ Ajouter</button>
        </div>
        <div id="dashboard-vehicles" class="vehicles-grid"></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">🔔 Rappels & prochains entretiens</div></div>
        <div id="reminders-list"></div>
      </div>
    </div>
  </div>
</div>

<!-- ── VÉHICULES ──────────────────────────────────────────────────────────── -->
<div id="page-vehicles" class="page">
  <div class="container">
    <div class="card-header" style="margin-bottom:1rem">
      <h1 style="font-size:1.2rem;font-weight:700">🚗 Mes véhicules</h1>
      <button class="btn btn-primary" onclick="openAddVehicle()">+ Ajouter un véhicule</button>
    </div>
    <div id="vehicles-grid" class="vehicles-grid"></div>
  </div>
</div>

<!-- ── DÉTAIL VÉHICULE ─────────────────────────────────────────────────────── -->
<div id="page-vehicle" class="page">
  <div class="container">
    <div style="margin-bottom:1rem">
      <button class="btn btn-secondary btn-sm" onclick="navigate('vehicles')">← Retour</button>
    </div>
    <div id="vehicle-header"></div>
    <div class="tabs">
      <button class="tab-btn active" data-tab="maintenances" onclick="switchTab('maintenances')">🔧 Entretiens <span id="maintenance-total" style="color:var(--muted);font-size:.78rem"></span></button>
      <button class="tab-btn" data-tab="parts" onclick="switchTab('parts')">🔩 Pièces <span id="parts-total-vehicle" style="color:var(--muted);font-size:.78rem"></span></button>
    </div>
    <div id="tab-maintenances" class="tab-pane active">
      <div style="margin-bottom:1rem;display:flex;justify-content:flex-end">
        <button class="btn btn-primary btn-sm" onclick="openAddMaintenance()">+ Ajouter un entretien</button>
      </div>
      <div id="maintenance-list"></div>
    </div>
    <div id="tab-parts" class="tab-pane">
      <div style="margin-bottom:1rem;display:flex;justify-content:flex-end">
        <button class="btn btn-primary btn-sm" onclick="openAddPart()">+ Ajouter une pièce</button>
      </div>
      <div id="parts-list-vehicle"></div>
    </div>
  </div>
</div>

<!-- ── DÉTAIL ENTRETIEN ─────────────────────────────────────────────────────── -->
<div id="page-maintenance" class="page">
  <div class="container">
    <div style="margin-bottom:1rem">
      <button class="btn btn-secondary btn-sm" id="maintenance-back-btn" onclick="navigate('vehicle',{id:currentVehicleId})">← Retour au véhicule</button>
    </div>
    <div id="maintenance-detail-header"></div>
    <div class="card" style="padding:0;margin-top:1.5rem">
      <div class="card-header">
        <span class="card-title">🔩 Pièces utilisées <span id="maint-parts-count" class="badge badge-gray" style="margin-left:.5rem"></span></span>
        <button class="btn btn-primary btn-sm" onclick="toggleInlinePartForm()">+ Ajouter une pièce</button>
      </div>
      <!-- Formulaire inline ajout pièce -->
      <div id="inline-part-form" style="display:none;background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:1.25rem 1.5rem">
        <p style="font-size:.8rem;font-weight:600;color:#64748b;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em">🔄 Réutiliser une pièce connue</p>
        <div id="known-parts-list" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Nom *</label><input class="form-control" id="ipart-name" placeholder="Filtre à huile"></div>
          <div class="form-group"><label class="form-label">Référence</label><input class="form-control" id="ipart-reference" placeholder="Ex: 06A115561B" style="font-family:monospace"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Marque</label><input class="form-control" id="ipart-brand" placeholder="Bosch, NGK..."></div>
          <div class="form-group">
            <label class="form-label">Prix unitaire (€)</label>
            <input class="form-control" id="ipart-price" type="number" step="0.01" value="0" oninput="updateIPriceTTC()">
            <label style="display:flex;align-items:center;gap:.4rem;margin-top:.4rem;font-size:.8rem;color:#64748b;cursor:pointer">
              <input type="checkbox" id="ipart-prix-ht" onchange="updateIPriceTTC()" style="width:14px;height:14px"> Prix HT (+20% TVA)
            </label>
            <p id="ipart-ttc-preview" style="display:none;font-size:.8rem;color:#2563eb;margin-top:.2rem;font-family:monospace"></p>
          </div>
          <div class="form-group"><label class="form-label">Quantité</label><input class="form-control" id="ipart-quantity" type="number" value="1" min="1"></div>
        </div>
        <div class="form-group"><label class="form-label">Photo (optionnel)</label><input class="form-control" id="ipart-photo" type="file" accept="image/*"></div>
        <div style="display:flex;gap:.5rem;margin-top:.5rem">
          <button class="btn btn-primary btn-sm" onclick="saveInlinePart()">+ Ajouter</button>
          <button class="btn btn-secondary btn-sm" onclick="toggleInlinePartForm()">Annuler</button>
        </div>
      </div>
      <div id="maintenance-parts-list"></div>
    </div>
  </div>
</div>

<!-- ── TOUS LES ENTRETIENS ─────────────────────────────────────────────────── -->
<div id="page-maintenances-all" class="page">
  <div class="container">
    <div class="card-header" style="margin-bottom:1rem">
      <div>
        <h1 style="font-size:1.2rem;font-weight:700">🔧 Tous les entretiens</h1>
        <p style="color:var(--muted);font-size:.85rem"><span id="all-maint-count">0</span> entretiens · Total : <span id="all-maint-total">--</span></p>
      </div>
    </div>
    <div id="all-maintenances-list"></div>
  </div>
</div>

<!-- ── TOUTES LES PIÈCES ──────────────────────────────────────────────────── -->
<div id="page-parts" class="page">
  <div class="container">
    <div class="card-header" style="margin-bottom:1rem">
      <div>
        <h1 style="font-size:1.2rem;font-weight:700">🔩 Toutes les pièces</h1>
        <p style="color:var(--muted);font-size:.85rem"><span id="all-parts-count">0</span> pièces · Total : <span id="all-parts-total">--</span></p>
      </div>
      <button class="btn btn-primary" onclick="openAddPart()">+ Ajouter une pièce</button>
    </div>
    <div id="all-parts-list"></div>
  </div>
</div>

<!-- ══ MODALS ════════════════════════════════════════════════════════════════ -->

<!-- MODAL: VEHICLE -->
<div id="modal-vehicle" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-vehicle-title">Ajouter un véhicule</div>
      <button class="modal-close" onclick="closeModal('modal-vehicle')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-vehicle" onsubmit="return false">
        <input type="hidden" id="form-vehicle-id" name="id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nom *</label><input class="form-control" id="vehicle-name" name="name" required placeholder="Ma Clio"></div>
          <div class="form-group"><label class="form-label">Marque *</label><input class="form-control" id="vehicle-brand" name="brand" required placeholder="Renault"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Modèle *</label><input class="form-control" id="vehicle-model" name="model" required placeholder="Clio 4"></div>
          <div class="form-group"><label class="form-label">Année</label><input class="form-control" id="vehicle-year" name="year" type="number" placeholder="2019"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Immatriculation</label><input class="form-control" id="vehicle-license-plate" name="license_plate" placeholder="AB-123-CD"></div>
          <div class="form-group"><label class="form-label">Carburant</label>
            <select class="form-control" id="vehicle-fuel-type" name="fuel_type">
              <option>Essence</option><option>Diesel</option><option>Hybride</option><option>Electrique</option><option>GPL</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kilométrage</label><input class="form-control" id="vehicle-current-km" name="current_km" type="number" placeholder="85000"></div>
          <div class="form-group"><label class="form-label">Couleur</label><input class="form-control" id="vehicle-color" name="color" placeholder="Blanc nacré"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Date d'achat</label><input class="form-control" id="vehicle-purchase-date" name="purchase_date" type="date"></div>
          <div class="form-group"><label class="form-label">Prix d'achat (€)</label><input class="form-control" id="vehicle-purchase-price" name="purchase_price" type="number" step="0.01"></div>
        </div>
        <div class="form-group"><label class="form-label">VIN</label><input class="form-control" id="vehicle-vin" name="vin" placeholder="VF1..."></div>
        <div class="form-group">
          <label class="form-label">Photo</label>
          <input class="form-control" id="vehicle-photo" name="photo" type="file" accept="image/*" onchange="previewPhoto('vehicle-photo','vehicle-photo-preview')">
          <img id="vehicle-photo-preview" style="display:none;max-height:120px;margin-top:.5rem;border-radius:6px" alt="">
        </div>
        <div class="form-group"><label class="form-label">Notes</label><textarea class="form-control" id="vehicle-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-vehicle')">Annuler</button>
      <button class="btn btn-primary" onclick="saveVehicle()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL: MAINTENANCE -->
<div id="modal-maintenance" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-maintenance-title">Ajouter un entretien</div>
      <button class="modal-close" onclick="closeModal('modal-maintenance')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-maintenance" onsubmit="return false">
        <input type="hidden" id="form-maintenance-id" name="id">
        <input type="hidden" id="maintenance-vehicle-id" name="vehicle_id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Type *</label>
            <select class="form-control" id="maintenance-type" name="type">
              <option>Vidange</option><option>Révision</option><option>Freins</option><option>Pneus</option>
              <option>Distribution</option><option>Filtres</option><option>Batterie</option><option>Climatisation</option>
              <option>Carrosserie</option><option>Diagnostic</option><option>Contrôle technique</option><option>Autre</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Date *</label><input class="form-control" id="maintenance-date" name="date" type="date" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kilométrage</label><input class="form-control" id="maintenance-km" name="km" type="number" placeholder="85000"></div>
          <div class="form-group"><label class="form-label">Coût main d'œuvre (€)</label><input class="form-control" id="maintenance-cost" name="cost" type="number" step="0.01" placeholder="0"></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" id="maintenance-description" name="description" rows="2"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Mécanicien</label><input class="form-control" id="maintenance-mechanic" name="mechanic"></div>
          <div class="form-group"><label class="form-label">Garage / Atelier</label><input class="form-control" id="maintenance-garage" name="garage_name"></div>
        </div>
        <div style="background:rgba(37,99,235,.05);border:1px solid rgba(37,99,235,.1);border-radius:8px;padding:1rem;margin-top:.5rem">
          <div style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem;font-weight:600">🔔 Prochain entretien</div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Date prévue</label><input class="form-control" id="maintenance-next-date" name="next_date" type="date"></div>
            <div class="form-group"><label class="form-label">Kilométrage prévu</label><input class="form-control" id="maintenance-next-km" name="next_km" type="number" placeholder="95000"></div>
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem"><label class="form-label">Notes</label><textarea class="form-control" id="maintenance-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-maintenance')">Annuler</button>
      <button class="btn btn-primary" onclick="saveMaintenance()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL: PART -->
<div id="modal-part" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-part-title">Ajouter une pièce</div>
      <button class="modal-close" onclick="closeModal('modal-part')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-part" onsubmit="return false">
        <input type="hidden" id="form-part-id" name="id">
        <input type="hidden" id="part-vehicle-id" name="vehicle_id">
        <input type="hidden" id="part-maintenance-id" name="maintenance_id">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nom *</label><input class="form-control" id="part-name" name="name" required placeholder="Filtre à huile"></div>
          <div class="form-group"><label class="form-label">Catégorie</label>
            <select class="form-control" id="part-category" name="category">
              <option>Moteur</option><option>Freinage</option><option>Suspension</option><option>Transmission</option>
              <option>Carrosserie</option><option>Electricite</option><option>Eclairage</option><option>Filtration</option>
              <option>Refroidissement</option><option>Echappement</option><option>Pneumatiques</option><option>Autre</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Marque</label><input class="form-control" id="part-brand" name="brand" placeholder="Bosch, NGK..."></div>
          <div class="form-group"><label class="form-label">Référence</label><input class="form-control" id="part-reference" name="reference"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Prix unitaire (€)</label>
            <input class="form-control" id="part-price" name="price" type="number" step="0.01" placeholder="12.50" oninput="updatePartPriceTTC()">
            <label style="display:flex;align-items:center;gap:.4rem;margin-top:.4rem;font-size:.8rem;color:#64748b;cursor:pointer">
              <input type="checkbox" id="part-prix-ht" onchange="updatePartPriceTTC()" style="width:14px;height:14px"> Prix HT (+20% TVA)
            </label>
            <p id="part-ttc-preview" style="display:none;font-size:.8rem;color:#2563eb;margin-top:.2rem;font-family:monospace"></p>
          </div>
          <div class="form-group"><label class="form-label">Quantité</label><input class="form-control" id="part-quantity" name="quantity" type="number" value="1" min="1"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Photo</label>
          <input class="form-control" id="part-photo" name="photo" type="file" accept="image/*" onchange="previewPhoto('part-photo','part-photo-preview')">
          <img id="part-photo-preview" style="display:none;max-height:100px;margin-top:.5rem;border-radius:6px" alt="">
        </div>
        <div class="form-group"><label class="form-label">Notes</label><textarea class="form-control" id="part-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-part')">Annuler</button>
      <button class="btn btn-primary" onclick="savePart()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL: UPLOAD PHOTO VÉHICULE -->
<div id="modal-upload-photo" class="modal-backdrop">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <div class="modal-title">📷 Changer la photo</div>
      <button class="modal-close" onclick="closeModal('modal-upload-photo')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="upload-vehicle-id">
      <div class="form-group"><label class="form-label">Nouvelle photo</label><input class="form-control" id="upload-photo-file" type="file" accept="image/*"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-upload-photo')">Annuler</button>
      <button class="btn btn-primary" onclick="doUploadPhoto()">Mettre à jour</button>
    </div>
  </div>
</div>

<script src="/modules/garage/assets/garage.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
