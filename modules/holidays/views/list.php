<?php
// modules/holidays/views/list.php

// 1. Récupération des voyages + Calculs (Coût Total, Montant Payé, Montant Épargné/Financé)
$sql = "
    SELECT h.*, 
           (
             COALESCE(h.budget_food, 0) + 
             COALESCE(h.budget_extra, 0) + 
             COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)
           ) as total_cost,
           (SELECT COALESCE(SUM(ABS(amount)), 0) FROM pf_expenses WHERE holiday_id = h.id) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h
    ORDER BY 
        -- COALESCE permet de rejeter les voyages sans date tout à la fin de la liste (ex: l'an 2999)
        COALESCE(start_date, '2999-12-31') ASC, 
        
        FIELD(status, 'booked', 'planned', 'draft', 'passed', 'archived')
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

<?php include __DIR__ . '/modal.php'; ?>

<?php
function renderHolidayCard($h, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ?");
    $stmt->execute([$h['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $json = htmlspecialchars(json_encode(['main' => $h, 'items' => $items]), ENT_QUOTES, 'UTF-8');
    $dateDisplay = htmlspecialchars($h['period_hint'] ?? '');
    
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

    // --- Calcul des métriques financières ---
    $cost = (float)$h['total_cost'];
    $paid = (float)$h['total_paid'];
    $saved = (float)$h['total_saved']; 
    
    $leftToPay = max(0, $cost - $paid);
    
    // Calculs pour la barre de progression bicolore
    $pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
    $pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;

    echo "
    <div class='hol-idea-card' style='display: flex; flex-direction: column;'>
        <div class='hol-idea-card__head' style='display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 10px;'>
            
            <div style='flex:1;'>
                <h3 style='margin:0; font-size:1.15rem; color:#0f172a;'>
                    <a href='?tab=holiday_detail&id={$h['id']}' style='text-decoration:none; color:inherit;' title='Ouvrir la page du voyage'>
                        ".htmlspecialchars($h['title'])."
                    </a>
                </h3>
                <span style='font-size:0.7rem; padding:3px 8px; border-radius:12px; font-weight:bold; display:inline-block; margin-top:6px;' class='$statusClass'>
                    ".strtoupper($h['status'])."
                </span>
            </div>
            
            <div style='display:flex; gap:5px;'>
                <button onclick='editHoliday($json)' class='pf-btn btn-secondary' style='padding:6px; height:auto; width:auto; line-height:1;' title='Modification rapide'>✏️</button>
                <a href='?tab=holiday_detail&id={$h['id']}' class='pf-btn' style='padding:6px; height:auto; width:auto; line-height:1; text-decoration:none;' title='Gérer le voyage'>👁️</a>
            </div>

        </div>
        
        <div class='hol-idea-meta' style='margin-bottom:15px;'>
            <span>🗓️ ".($dateDisplay ?: 'Dates à définir')."</span>
        </div>
        
        <div style='margin-top:auto; padding-top:15px; border-top:1px solid #f1f5f9;'>
            
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>
                <span style='font-size:0.85rem; color:#64748b;'>Budget Total</span>
                <span style='font-size:1.1rem; font-weight:bold; color:#1e293b;'>".number_format($cost, 0, ',', ' ')." €</span>
            </div>

            <div style='width:100%; height:8px; background:#e2e8f0; border-radius:4px; margin-bottom:12px; display:flex; overflow:hidden;'>
                <div style='width:{$pctPaid}%; background:#10b981; transition:width 0.3s ease;' title='Payé'></div>
                <div style='width:{$pctSaved}%; background:#3b82f6; transition:width 0.3s ease;' title='Financé (Provision)'></div>
            </div>

            <div style='display:grid; grid-template-columns: 1fr 1fr; gap:8px; font-size:0.8rem;'>
                <div style='color:#10b981; font-weight:600;' title='Montant déjà dépensé'>✓ Payé : ".number_format($paid, 0, ',', ' ')." €</div>
                <div style='color:#3b82f6; font-weight:600; text-align:right;' title='Montant épargné non dépensé'>💼 Financé : ".number_format($saved, 0, ',', ' ')." €</div>
                <div style='color:#ef4444; font-weight:700; font-size:0.85rem; grid-column: span 2; padding-top: 4px; border-top: 1px dashed #fca5a5;'>
                    ⏳ Reste à payer : ".number_format($leftToPay, 0, ',', ' ')." €
                </div>
            </div>
            
        </div>
    </div>
    ";
}
?>