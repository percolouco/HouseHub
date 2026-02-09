<?php
// modules/holidays/index.php

// 1. Récupération des voyages + Calcul du coût total
$sql = "
    SELECT h.*, 
           (
             COALESCE(h.budget_food, 0) + 
             COALESCE(h.budget_extra, 0) + 
             COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)
           ) as total_cost
    FROM pf_holidays h
    ORDER BY FIELD(status, 'booked', 'planned', 'draft', 'passed', 'archived'), 
             start_date ASC
";
$holidays = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Tri par statut
$active = array_filter($holidays, fn($h) => in_array($h['status'], ['draft', 'planned', 'booked']));
$history = array_filter($holidays, fn($h) => in_array($h['status'], ['passed', 'archived']));
?>

<div class="pf-holidays">
    
    <div class="pf-holidays__titlebar">
        <h1>Mes Vacances ✈️</h1>
        <div class="hol-title-actions">
            <button class="hol-add-btn" onclick="openHolidayModal('add')">+ Créer un voyage</button>
        </div>
    </div>

    <section class="pf-section">
        <div class="hol-ideas-grid">
            <?php if (empty($active)): ?>
                <p style="color:var(--text-muted); font-style:italic;">Aucun voyage en cours. Planifions quelque chose !</p>
            <?php endif; ?>

            <?php foreach ($active as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($history)): ?>
    <section class="pf-section" style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <h3 style="color:var(--text-muted);">Historique</h3>
        <div class="hol-ideas-grid" style="opacity: 0.7;">
            <?php foreach ($history as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<div id="holidayModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 800px; width: 95%;">
        <h3 id="modalTitle" class="pf-modal-title">Planifier le voyage</h3>
        
        <form action="/modules/holidays/save_new.php" method="POST" id="holidayForm">
            <input type="hidden" name="id" id="inp_id">
            <input type="hidden" name="action" value="save">

            <div class="form-row">
                <div style="flex: 2;">
                    <label class="pf-label">Nom du voyage</label>
                    <input type="text" name="title" id="inp_title" class="pf-input" placeholder="Ex: Octobre - Portugal" required>
                </div>
                <div style="flex: 1;">
                    <label class="pf-label">Statut</label>
                    <select name="status" id="inp_status" class="pf-input">
                        <option value="draft">Brouillon ✏️</option>
                        <option value="planned">Planifié 📅</option>
                        <option value="booked">Réservé ✅</option>
                        <option value="passed">Passé 👋</option>
                        <option value="archived">Archivé 🗄️</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label">Période (Texte libre)</label>
                    <input type="text" name="period_hint" id="inp_period" class="pf-input" placeholder="Ex: Octobre 2026">
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1">
                        <label class="pf-label">Du</label>
                        <input type="date" name="start_date" id="inp_start" class="pf-input" lang="fr">
                    </div>
                    <div style="flex:1">
                        <label class="pf-label">Au</label>
                        <input type="date" name="end_date" id="inp_end" class="pf-input" lang="fr">
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

<div class="hol-columns-wrapper">
                
                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#2563eb;">🚗 Transport</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('transport')" title="Ajouter un transport">＋</button>
                    </div>
                    <div id="list_transport" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#059669;">🏨 Hébergement</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('accommodation')" title="Ajouter un hébergement">＋</button>
                    </div>
                    <div id="list_accommodation" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#d97706;">🎫 Activité</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('activity')" title="Ajouter une activité">＋</button>
                    </div>
                    <div id="list_activity" class="dynamic-list"></div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div class="form-row">
                <div>
                    <label class="pf-label">🍔 Budget Food & Bev (€)</label>
                    <input type="number" step="0.01" name="budget_food" id="inp_food" class="pf-input" placeholder="0.00">
                </div>
                <div>
                    <label class="pf-label">🎁 Budget Extras (€)</label>
                    <input type="number" step="0.01" name="budget_extra" id="inp_extra" class="pf-input" placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label class="pf-label">Notes</label>
                <textarea name="notes" id="inp_notes" class="pf-input" rows="2" placeholder="Idées en vrac..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="deleteHoliday()" id="btn_delete" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; margin-right:auto; display:none;">Supprimer</button>
                <button type="button" onclick="document.getElementById('holidayModal').style.display='none'" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php
function renderHolidayCard($h, $pdo) {
    // Récupérer le détail des items pour l'affichage JS futur
    $stmt = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ?");
    $stmt->execute([$h['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON pour le JS
    $json = htmlspecialchars(json_encode(['main' => $h, 'items' => $items]), ENT_QUOTES, 'UTF-8');
    
    // Affichage des dates
    $dateDisplay = htmlspecialchars($h['period_hint'] ?? '');
    
    // MODIFICATION ICI : Format d/m/Y (jj/mm/aaaa)
    if (empty($dateDisplay) && $h['start_date']) {
        $dateDisplay = date('d/m/Y', strtotime($h['start_date']));
        if ($h['end_date']) $dateDisplay .= ' → ' . date('d/m/Y', strtotime($h['end_date']));
    }
    
    $statusClass = match($h['status']) {
        'booked' => 'bg-green-100 text-green-800',
        'planned' => 'bg-blue-100 text-blue-800',
        'passed' => 'bg-gray-100 text-gray-600',
        default => 'bg-yellow-50 text-yellow-800'
    };

    echo "
    <div class='hol-idea-card' onclick='editHoliday($json)'>
        <div class='hol-idea-card__head'>
            <h3>".htmlspecialchars($h['title'])."</h3>
            <span style='font-size:0.75rem; padding:2px 8px; border-radius:12px; font-weight:bold;' class='$statusClass'>
                ".strtoupper($h['status'])."
            </span>
        </div>
        <div class='hol-idea-meta'>
            <span>🗓️ ".($dateDisplay ?: 'Dates à définir')."</span>
        </div>
        
        <div style='margin-top:auto; padding-top:10px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;'>
            <span style='font-size:0.85rem; color:#64748b;'>Budget Total</span>
            <span style='font-size:1.1rem; font-weight:bold; color:#1e293b;'>".number_format($h['total_cost'], 0, ',', ' ')." €</span>
        </div>
    </div>
    ";
}
?>

<script src="/modules/holidays/holidays.js"></script>