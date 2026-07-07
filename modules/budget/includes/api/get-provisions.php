<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/i18n.php';

try {
    // Récupérer les provisions non payées, classées par date
    $stmt = $pdo->query("SELECT id, title, amount, expected_date FROM pf_expected_expenses WHERE is_paid = 0 ORDER BY expected_date ASC");
    $provisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($provisions)) {
        $html = '<p class="pf-text-muted">' . tr('no_provisions') . '</p>';
    } else {
        $html = '<table class="pf-table" style="width:100%; border-collapse: collapse;">';
        $html .= '<thead style="border-bottom: 2px solid var(--border-color); text-align: left;">';
        $html .= '<tr><th>' . tr('provision_label') . '</th><th>' . tr('amount') . '</th><th>' . tr('expected_date') . '</th><th style="text-align:right;">Actions</th></tr>';
        $html .= '</thead><tbody>';
        
        foreach ($provisions as $p) {
            $dateFormated = date('d/m/Y', strtotime($p['expected_date']));
            $html .= '<tr style="border-bottom: 1px solid var(--border-color); height: 45px;">';
            $html .= '<td>' . htmlspecialchars($p['title']) . '</td>';
            $html .= '<td>' . number_format($p['amount'], 2, ',', ' ') . ' €</td>';
            $html .= '<td>' . $dateFormated . '</td>';
            $html .= '<td style="text-align:right;"><button class="pf-btn pf-btn-danger btn-delete-provision" data-id="' . $p['id'] . '">' . tr('btn_delete') . '</button></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    echo json_encode(['success' => true, 'html' => $html]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}