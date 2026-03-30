<?php
// modules/holidays/views/list.php

// 1. Gestion du filtrage par année
$selectedYear = $_GET['y'] ?? date('Y');

// Récupérer les années disponibles dans la BDD pour le menu déroulant
$yearStmt = $pdo->query("SELECT DISTINCT YEAR(start_date) as yr FROM pf_holidays WHERE start_date IS NOT NULL ORDER BY yr DESC");
$availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $availableYears)) {
    $availableYears[] = date('Y');
    rsort($availableYears); // Trie par ordre décroissant
}

// Construction de la condition de filtrage SQL
$whereSQL = "";
$params = [];
if ($selectedYear !== 'all') {
    // On affiche les voyages de l'année OU les voyages sans date (pour ne pas perdre les brouillons)
    $whereSQL = "WHERE YEAR(h.start_date) = ? OR h.start_date IS NULL";
    $params[] = $selectedYear;
}

// 2. Récupération des voyages + Calculs
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
    $whereSQL
    ORDER BY 
        COALESCE(start_date, '2999-12-31') ASC, 
        FIELD(status, 'booked', 'planned', 'draft', 'passed', 'archived')
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Tri par statut
$active = array_filter($holidays, fn($h) => in_array($h['status'], ['draft', 'planned', 'booked']));
$history = array_filter($holidays, fn($h) => in_array($h['status'], ['passed', 'archived']));

// 4. Calcul du Reste à Payer Global (uniquement sur les voyages Actifs affichés)
$globalLeftToPay = 0;
foreach ($active as $h) {
    $cost = (float)$h['total_cost'];
    $paid = (float)$h['total_paid'];
    $globalLeftToPay += max(0, $cost - $paid);
}
?>

<div class="pf-holidays">
    
    <div class="pf-holidays__titlebar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:30px;">
        
        <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
            <h1 style="margin:0;">Mes Vacances ✈️</h1>
            
            <select onchange="window.location.href='?tab=list&y='+this.value" class="pf-input" style="width:auto; padding:6px 12px; font-weight:bold; cursor:pointer; border-radius:8px; border:1px solid #cbd5e1; margin:0;">
                <option value="all" <?= $selectedYear === 'all' ? 'selected' : '' ?>>Tout afficher</option>
                <?php foreach($availableYears as $yr): ?>
                    <option value="<?= $yr ?>" <?= (string)$selectedYear === (string)$yr ? 'selected' : '' ?>>Année <?= $yr ?></option>
                <?php endforeach; ?>
            </select>

            <div style="background:#fff1f2; color:#be123c; padding:6px 12px; border-radius:8px; font-weight:bold; border:1px solid #fecdd3; font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                <span>⏳ Reste à payer (<?= $selectedYear === 'all' ? 'Global' : $selectedYear ?>) :</span>
                <span style="font-size:1rem;"><?= number_format($globalLeftToPay, 0, ',', ' ') ?> €</span>
            </div>
        </div>

        <div>
            <button class="pf-btn" onclick="openHolidayModal('add')" style="margin:0;">+ Créer un voyage</button>
        </div>

    </div>

    <section class="pf-section">
        <div class="hol-ideas-grid">
            <?php if (empty($active)): ?>
                <p style="color:var(--text-muted); font-style:italic;">Aucun voyage en cours pour cette période. Planifions quelque chose !</p>
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
// FONCTION D'AFFICHAGE DE LA CARTE (Ne pas modifier, elle est déjà optimale)
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

    $cost = (float)$h['total_cost'];
    $paid = (float)$h['total_paid'];
    $saved = (float)$h['total_saved']; 
    
    $leftToPay = max(0, $cost - $paid);
    
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