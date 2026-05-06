<?php
// modules/holidays/views/list.php

// 1. Gestion du filtrage par année
$selectedYear = $_GET['y'] ?? date('Y');

$yearStmt = $pdo->query("SELECT DISTINCT YEAR(start_date) as yr FROM pf_holidays WHERE start_date IS NOT NULL ORDER BY yr DESC");
$availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $availableYears)) {
    $availableYears[] = date('Y');
    rsort($availableYears);
}

$whereSQL = "";
$params = [];
if ($selectedYear !== 'all') {
    $whereSQL = "WHERE YEAR(h.start_date) = ? OR h.start_date IS NULL";
    $params[] = $selectedYear;
}

// 2. Récupération des voyages + Calculs
$sql = "
    SELECT h.*, 
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE(SUM(hi.amount), 0)) as total_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND is_paid = 1) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h
    LEFT JOIN pf_holidays_items hi ON h.id = hi.holiday_id
    $whereSQL
    GROUP BY h.id
    ORDER BY COALESCE(start_date, '2999-12-31') ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active = array_filter($holidays, fn($h) => in_array($h['status'], ['draft', 'planned', 'booked']));
$history = array_filter($holidays, fn($h) => in_array($h['status'], ['passed', 'archived']));

$globalLeftToPay = 0;
foreach ($active as $h) {
    $globalLeftToPay += max(0, (float)$h['total_cost'] - (float)$h['total_paid']);
}
?>

<div class="pf-holidays">
    <div class="pf-holidays__titlebar">
    <div class="hol-title-group">
        <h1 class="hol-main-title"><?= tr('hdl_main_title') ?> ✈️</h1>
        
        <div class="hol-filters-row">
            <select onchange="window.location.href='?tab=list&y='+this.value" class="pf-input hol-year-select">
                <option value="all" <?= $selectedYear === 'all' ? 'selected' : '' ?>><?= tr('hdl_filter_all') ?></option>
                <?php foreach($availableYears as $yr): ?>
                    <option value="<?= $yr ?>" <?= (string)$selectedYear === (string)$yr ? 'selected' : '' ?>><?= tr('hdl_filter_year') ?> <?= $yr ?></option>
                <?php endforeach; ?>
            </select>

            <div class="hol-badge-left-to-pay">
                <span>⏳ <?= tr('hdl_left_to_pay_label') ?> (<?= $selectedYear === 'all' ? tr('hdl_filter_all') : $selectedYear ?>) :</span>
                <span class="hol-badge-amount"><?= number_format($globalLeftToPay, 0, ',', ' ') ?> €</span>
            </div>
        </div>
    </div>

    <div class="hol-actions-group">
        <button class="pf-btn hol-add-btn" onclick="openHolidayModal('add')">+ <?= tr('hdl_btn_create') ?></button>
    </div>
</div>

    <section class="pf-section">
        <div class="hol-ideas-grid">
            <?php if (empty($active)): ?>
                <p style="color:var(--text-muted); font-style:italic;"><?= tr('hdl_no_active_trips') ?></p>
            <?php endif; ?>

            <?php foreach ($active as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($history)): ?>
    <section class="pf-section" style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <h3 style="color:var(--text-muted);"><?= tr('hdl_history_title') ?></h3>
        <div class="hol-ideas-grid" style="opacity: 0.7;">
            <?php foreach ($history as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";

window.I18N = {
    ...(window.I18N || {}),
    'hdl_js_search_loading': "<?= tr('hdl_js_search_loading') ?>",
    'hdl_js_no_result': "<?= tr('hdl_js_no_result') ?>",
    'hdl_js_confirm_del_trip': "<?= tr('hdl_js_confirm_del_trip') ?>",
    'hdl_js_confirm_del_step': "<?= tr('hdl_js_confirm_del_step') ?>",
    'hdl_js_step_label': "<?= tr('hdl_js_step_label') ?>",
    'hdl_js_ph_expense_name': "<?= tr('hdl_js_ph_expense_name') ?>",
    'hdl_js_delete_line': "<?= tr('btn_delete') ?>",
    'hdl_planning_title': "<?= tr('hdl_planning_title') ?>",
    'hdl_to_place': "<?= tr('hdl_to_place') ?>",
    'hdl_js_missing_dates_title': "<?= tr('hdl_js_missing_dates_title') ?>",
    'hdl_js_missing_dates_msg': "<?= tr('hdl_js_missing_dates_msg') ?>",
    'hdl_modal_title': "<?= tr('hdl_modal_title') ?>",
    'hdl_paid': "<?= tr('hdl_paid') ?>",
    'hdl_quick_edit_title': "<?= tr('hdl_quick_edit_title') ?>"
};

// Fallback de sécurité pour s'assurer que la modale peut toujours se fermer
window.closeHolidayModal = window.closeHolidayModal || function() {
    const modal = document.getElementById('holidayModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};
</script>

<script src="/modules/holidays/holidays.js"></script>

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

    $cost = (float)$h['total_cost'];
    $paid = (float)$h['total_paid'];
    $saved = (float)$h['total_saved']; 
    $leftToPay = max(0, $cost - $paid);
    
    $pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
    $pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;

    $statusLabel = tr('hdl_status_' . $h['status']);

    echo "
    <div class='hol-idea-card' style='display: flex; flex-direction: column;'>
        <div class='hol-idea-card__head' style='display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 10px;'>
            <div style='flex:1;'>
                <h3 style='margin:0; font-size:1.15rem; color:#0f172a;'>
                    <a href='?tab=holiday_detail&id={$h['id']}' style='text-decoration:none; color:inherit;'>
                        ".htmlspecialchars($h['title'])."
                    </a>
                </h3>
                <span style='font-size:0.7rem; padding:3px 8px; border-radius:12px; font-weight:bold; display:inline-block; margin-top:6px;' class='$statusClass'>
                    ".strtoupper($statusLabel)."
                </span>
            </div>
            <div style='display:flex; gap:5px;'>
                <button onclick='editHoliday($json)' class='pf-btn btn-secondary' style='padding:6px;'>✏️</button>
                <a href='?tab=holiday_detail&id={$h['id']}' class='pf-btn' style='padding:6px; text-decoration:none;'>👁️</a>
            </div>
        </div>
        
        <div class='hol-idea-meta' style='margin-bottom:15px;'>
            <span>🗓️ ".($dateDisplay ?: tr('hdl_dates_to_define'))."</span>
        </div>
        
        <div style='margin-top:auto; padding-top:15px; border-top:1px solid #f1f5f9;'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>
                <span style='font-size:0.85rem; color:#64748b;'>".tr('hdl_total_budget')."</span>
                <span style='font-size:1.1rem; font-weight:bold; color:#1e293b;'>".number_format($cost, 0, ',', ' ')." €</span>
            </div>

            <div style='width:100%; height:8px; background:#e2e8f0; border-radius:4px; margin-bottom:12px; display:flex; overflow:hidden;'>
                <div style='width:{$pctPaid}%; background:#10b981; transition:width 0.3s ease;'></div>
                <div style='width:{$pctSaved}%; background:#3b82f6; transition:width 0.3s ease;'></div>
            </div>

            <div style='display:grid; grid-template-columns: 1fr 1fr; gap:8px; font-size:0.8rem;'>
                <div style='color:#10b981; font-weight:600;'>✓ ".tr('hdl_paid')." : ".number_format($paid, 0, ',', ' ')." €</div>
                <div style='color:#3b82f6; font-weight:600; text-align:right;'>💼 ".tr('hdl_saved')." : ".number_format($saved, 0, ',', ' ')." €</div>
                <div style='color:#ef4444; font-weight:700; font-size:0.85rem; grid-column: span 2; padding-top: 4px; border-top: 1px dashed #fca5a5;'>
                    ⏳ ".tr('hdl_left_to_pay')." : ".number_format($leftToPay, 0, ',', ' ')." €
                </div>
            </div>
        </div>
    </div>
    ";
}
?>