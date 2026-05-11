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

<!-- Sous-navigation interne Garage -->
<nav class="garage-subnav">
  <button class="nav-link active" data-page="dashboard">📊 <?= tr('garage_stat_vehicles') !== 'Véhicules' ? 'Dashboard' : 'Dashboard' ?></button>
  <button class="nav-link" data-page="vehicles">🚗 <?= tr('garage_vehicles_title') ?></button>
  <button class="nav-link" data-page="parts">🔩 <?= tr('garage_all_parts') ?></button>
</nav>

<div id="page-dashboard" class="page active">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-pill blue"><div class="label"><?= tr('garage_stat_vehicles') ?></div><div class="value" id="stat-vehicles">--</div></div>
      <div class="stat-pill green"><div class="label"><?= tr('garage_stat_maintenances') ?></div><div class="value" id="stat-maintenances">--</div></div>
      <div class="stat-pill amber"><div class="label"><?= tr('garage_stat_parts') ?></div><div class="value" id="stat-parts">--</div></div>
      <div class="stat-pill red"><div class="label"><?= tr('garage_stat_cost') ?></div><div class="value" id="stat-cost">--</div></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">
      <div>
        <div class="card-header" style="margin-bottom:.75rem">
          <h2 style="font-size:1rem;font-weight:600"><?= tr('garage_my_vehicles') ?></h2>
          <button class="btn btn-primary btn-sm" onclick="openAddVehicle()">+ <?= tr('garage_add') ?></button>
        </div>
        <div id="dashboard-vehicles" class="vehicles-grid"></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">🔔 <?= tr('garage_reminders') ?></div></div>
        <div id="reminders-list"></div>
      </div>
    </div>
  </div>
</div>

<div id="page-vehicles" class="page">
  <div class="container">
    <div class="card-header" style="margin-bottom:1rem">
      <h1 style="font-size:1.2rem;font-weight:700">🚗 <?= tr('garage_vehicles_title') ?></h1>
      <button class="btn btn-primary" onclick="openAddVehicle()">+ <?= tr('garage_add_vehicle') ?></button>
    </div>
    <div id="vehicles-grid" class="vehicles-grid"></div>
  </div>
</div>

<div id="page-vehicle" class="page">
  <div class="container">
    <div style="margin-bottom:1rem">
      <button class="btn btn-secondary btn-sm" onclick="navigate('vehicles')">← <?= tr('btn_back') ?></button>
    </div>
    <div id="vehicle-header"></div>
    <div class="tabs">
      <button class="tab-btn active" data-tab="maintenances" onclick="switchTab('maintenances')">🔧 <?= tr('garage_maintenances') ?> <span id="maintenance-total" style="color:var(--muted);font-size:.78rem"></span></button>
      <button class="tab-btn" data-tab="parts" onclick="switchTab('parts')">🔩 <?= tr('garage_parts') ?> <span id="parts-total-vehicle" style="color:var(--muted);font-size:.78rem"></span></button>
    </div>
    <div id="tab-maintenances" class="tab-pane active">
      <div style="margin-bottom:1rem;display:flex;justify-content:flex-end">
        <button class="btn btn-primary btn-sm" onclick="openAddMaintenance()">+ <?= tr('garage_add_maintenance') ?></button>
      </div>
      <div id="maintenance-list"></div>
    </div>
    <div id="tab-parts" class="tab-pane">
      <div style="margin-bottom:1rem;display:flex;justify-content:flex-end">
        <button class="btn btn-primary btn-sm" onclick="openAddPart()">+ <?= tr('garage_add_part') ?></button>
      </div>
      <div id="parts-list-vehicle"></div>
    </div>
  </div>
</div>

<div id="page-parts" class="page">
  <div class="container">
    <div class="card-header" style="margin-bottom:1rem">
      <div>
        <h1 style="font-size:1.2rem;font-weight:700">🔩 <?= tr('garage_all_parts') ?></h1>
        <p style="color:var(--muted);font-size:.85rem"><span id="all-parts-count">0</span> <?= tr('garage_parts_count') ?> · Total : <span id="all-parts-total">--</span></p>
      </div>
      <button class="btn btn-primary" onclick="openAddPart()">+ <?= tr('garage_add_part') ?></button>
    </div>
    <div id="all-parts-list"></div>
  </div>
</div>

<!-- MODAL: VEHICLE -->
<div id="modal-vehicle" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-vehicle-title"><?= tr('garage_add_vehicle') ?></div>
      <button class="modal-close" onclick="closeModal('modal-vehicle')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-vehicle" onsubmit="return false">
        <input type="hidden" id="form-vehicle-id" name="id">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_vehicle_name') ?> *</label><input class="form-control" id="vehicle-name" name="name" required placeholder="Ma Clio"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_brand') ?> *</label><input class="form-control" id="vehicle-brand" name="brand" required placeholder="Renault"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_model') ?> *</label><input class="form-control" id="vehicle-model" name="model" required placeholder="Clio 4"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_year') ?></label><input class="form-control" id="vehicle-year" name="year" type="number" placeholder="2019"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_plate') ?></label><input class="form-control" id="vehicle-license-plate" name="license_plate" placeholder="AB-123-CD"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_fuel') ?></label>
            <select class="form-control" id="vehicle-fuel-type" name="fuel_type">
              <option>Essence</option><option>Diesel</option><option>Hybride</option><option>Electrique</option><option>GPL</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_km') ?></label><input class="form-control" id="vehicle-current-km" name="current_km" type="number" placeholder="85000"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_color') ?></label><input class="form-control" id="vehicle-color" name="color" placeholder="Blanc nacré"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_purchase_date') ?></label><input class="form-control" id="vehicle-purchase-date" name="purchase_date" type="date"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_purchase_price') ?></label><input class="form-control" id="vehicle-purchase-price" name="purchase_price" type="number" step="0.01"></div>
        </div>
        <div class="form-group"><label class="form-label">VIN</label><input class="form-control" id="vehicle-vin" name="vin" placeholder="VF1..."></div>
        <div class="form-group">
          <label class="form-label"><?= tr('garage_photo') ?></label>
          <input class="form-control" id="vehicle-photo" name="photo" type="file" accept="image/*" onchange="previewPhoto('vehicle-photo','vehicle-photo-preview')">
          <img id="vehicle-photo-preview" style="display:none;max-height:120px;margin-top:.5rem;border-radius:6px" alt="">
        </div>
        <div class="form-group"><label class="form-label"><?= tr('garage_notes') ?></label><textarea class="form-control" id="vehicle-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-vehicle')"><?= tr('btn_cancel') ?></button>
      <button class="btn btn-primary" onclick="saveVehicle()"><?= tr('btn_save') ?></button>
    </div>
  </div>
</div>

<!-- MODAL: MAINTENANCE -->
<div id="modal-maintenance" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-maintenance-title"><?= tr('garage_add_maintenance') ?></div>
      <button class="modal-close" onclick="closeModal('modal-maintenance')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-maintenance" onsubmit="return false">
        <input type="hidden" id="form-maintenance-id" name="id">
        <input type="hidden" id="maintenance-vehicle-id" name="vehicle_id">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_maint_type') ?> *</label>
            <select class="form-control" id="maintenance-type" name="type">
              <option>Vidange</option><option>Révision</option><option>Freins</option><option>Pneus</option>
              <option>Distribution</option><option>Filtres</option><option>Batterie</option><option>Climatisation</option>
              <option>Carrosserie</option><option>Diagnostic</option><option>Contrôle technique</option><option>Autre</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label"><?= tr('garage_date') ?> *</label><input class="form-control" id="maintenance-date" name="date" type="date" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_km_at') ?></label><input class="form-control" id="maintenance-km" name="km" type="number" placeholder="85000"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_labor_cost') ?></label><input class="form-control" id="maintenance-cost" name="cost" type="number" step="0.01" placeholder="0"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= tr('garage_description') ?></label><textarea class="form-control" id="maintenance-description" name="description" rows="2"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_mechanic') ?></label><input class="form-control" id="maintenance-mechanic" name="mechanic"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_garage') ?></label><input class="form-control" id="maintenance-garage" name="garage"></div>
        </div>
        <div style="background:rgba(37,99,235,.05);border:1px solid rgba(37,99,235,.1);border-radius:8px;padding:1rem;margin-top:.5rem">
          <div style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem;font-weight:600">🔔 <?= tr('garage_next_reminder') ?></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label"><?= tr('garage_next_date') ?></label><input class="form-control" id="maintenance-next-date" name="next_date" type="date"></div>
            <div class="form-group"><label class="form-label"><?= tr('garage_next_km') ?></label><input class="form-control" id="maintenance-next-km" name="next_km" type="number" placeholder="95000"></div>
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem"><label class="form-label"><?= tr('garage_notes') ?></label><textarea class="form-control" id="maintenance-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-maintenance')"><?= tr('btn_cancel') ?></button>
      <button class="btn btn-primary" onclick="saveMaintenance()"><?= tr('btn_save') ?></button>
    </div>
  </div>
</div>

<!-- MODAL: PART -->
<div id="modal-part" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-part-title"><?= tr('garage_add_part') ?></div>
      <button class="modal-close" onclick="closeModal('modal-part')">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-part" onsubmit="return false">
        <input type="hidden" id="form-part-id" name="id">
        <input type="hidden" id="part-vehicle-id" name="vehicle_id">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_part_name') ?> *</label><input class="form-control" id="part-name" name="name" required placeholder="Filtre à huile"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_category') ?></label>
            <select class="form-control" id="part-category" name="category">
              <option>Moteur</option><option>Freinage</option><option>Suspension</option><option>Transmission</option>
              <option>Carrosserie</option><option>Electricite</option><option>Eclairage</option><option>Filtration</option>
              <option>Refroidissement</option><option>Echappement</option><option>Pneumatiques</option><option>Autre</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_brand') ?></label><input class="form-control" id="part-brand" name="brand" placeholder="Bosch, NGK..."></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_reference') ?></label><input class="form-control" id="part-reference" name="reference"></div>
        </div>
        <div class="form-row-3">
          <div class="form-group"><label class="form-label"><?= tr('garage_unit_price') ?></label><input class="form-control" id="part-price" name="price" type="number" step="0.01" placeholder="12.50"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_quantity') ?></label><input class="form-control" id="part-quantity" name="quantity" type="number" value="1" min="1"></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_unit') ?></label>
            <select class="form-control" id="part-unit" name="unit">
              <option value="piece">pièce</option><option value="litre">litre</option><option value="kg">kg</option><option value="m">mètre</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= tr('garage_supplier') ?></label><input class="form-control" id="part-supplier" name="supplier" placeholder="Amazon, Oscaro..."></div>
          <div class="form-group"><label class="form-label"><?= tr('garage_purchase_date') ?></label><input class="form-control" id="part-purchase-date" name="purchase_date" type="date"></div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= tr('garage_photo') ?></label>
          <input class="form-control" id="part-photo" name="photo" type="file" accept="image/*" onchange="previewPhoto('part-photo','part-photo-preview')">
          <img id="part-photo-preview" style="display:none;max-height:100px;margin-top:.5rem;border-radius:6px" alt="">
        </div>
        <div class="form-group"><label class="form-label"><?= tr('garage_notes') ?></label><textarea class="form-control" id="part-notes" name="notes" rows="2"></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-part')"><?= tr('btn_cancel') ?></button>
      <button class="btn btn-primary" onclick="savePart()"><?= tr('btn_save') ?></button>
    </div>
  </div>
</div>

<!-- MODAL: UPLOAD PHOTO -->
<div id="modal-upload-photo" class="modal-backdrop">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <div class="modal-title">📷 <?= tr('garage_change_photo') ?></div>
      <button class="modal-close" onclick="closeModal('modal-upload-photo')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="upload-vehicle-id">
      <div class="form-group"><label class="form-label"><?= tr('garage_new_photo') ?></label><input class="form-control" id="upload-photo-file" type="file" accept="image/*"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-upload-photo')"><?= tr('btn_cancel') ?></button>
      <button class="btn btn-primary" onclick="doUploadPhoto()"><?= tr('garage_update_photo') ?></button>
    </div>
  </div>
</div>

<script src="/modules/garage/assets/garage.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
