<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/i18n.php';

$savings_inputs = $_POST['savings'] ?? [];
$current_year = date('Y');
$current_month = date('m');

try {
    // 1. Récupération des dépenses prévues
    $stmtExpenses = $pdo->prepare("
        SELECT title, amount, expected_date 
        FROM pf_expected_expenses 
        WHERE is_paid = 0 
          AND YEAR(expected_date) = ? 
          AND MONTH(expected_date) = ?
        ORDER BY expected_date ASC
    ");
    $stmtExpenses->execute([$current_year, $current_month]);
    $expenses = $stmtExpenses->fetchAll(PDO::FETCH_ASSOC);

    $q1_expenses_total = 0;
    $q2_expenses_total = 0;
    
    $detail_html = '<ul style="padding-left: 1.2rem; margin-bottom: 1rem; color: var(--text-muted); font-size:0.9rem;">';
    foreach ($expenses as $e) {
        $day = intval(date('d', strtotime($e['expected_date'])));
        if ($day <= 15) {
            $q1_expenses_total += $e['amount'];
        } else {
            $q2_expenses_total += $e['amount'];
        }
        $detail_html .= '<li>' . htmlspecialchars($e['title']) . ' (' . number_format($e['amount'], 2, ',', ' ') . ' € le ' . date('d/m', strtotime($e['expected_date'])) . ')</li>';
    }
    $detail_html .= '</ul>';

    $total_expenses = $q1_expenses_total + $q2_expenses_total;

    // 2. Récupération des dettes
    $stmtDebts = $pdo->query("SELECT payer, SUM(amount) as total_debt FROM pf_advances WHERE is_resolved = 0 GROUP BY payer");
    $debts = $stmtDebts->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. NOUVELLE LOGIQUE : Clearing intelligent (Compensation partielle ou totale)
    $total_base_inputs = array_sum(array_map('floatval', $savings_inputs));
    $surplus_for_clearing = $total_base_inputs - $total_expenses;
    $monthly_savings = 0; // Le vrai total qui sera viré après déduction des dettes
    
    $contributions_html = '<ul style="padding-left: 1.2rem; margin-bottom: 0.5rem; color: var(--text-main); font-size:0.9rem; line-height: 1.6;">';
    
    foreach ($savings_inputs as $person => $amount) {
        $base_amount = floatval($amount);
        $debt = $debts[$person] ?? 0;
        
        // Formatage bancaire propre pour les textes
        $base_fmt = number_format($base_amount, 2, ',', ' ');
        
        if ($debt > 0) {
            if ($surplus_for_clearing > 0) {
                $compensation = min($debt, $base_amount, $surplus_for_clearing);
                $net_amount = $base_amount - $compensation;
                $surplus_for_clearing -= $compensation; 
                $monthly_savings += $net_amount;
                
                $comp_fmt = number_format($compensation, 2, ',', ' ');
                $net_fmt = number_format($net_amount, 2, ',', ' ');
                
                $reste_dette = $debt - $compensation;
                $txt_reste = ($reste_dette > 0) 
                    ? ' <span style="color:var(--danger); font-size:0.8rem; margin-left: 5px;">(Reste ' . number_format($reste_dette, 2, ',', ' ') . ' € de dette)</span>' 
                    : ' <span style="color:var(--success); font-weight:bold; font-size:0.85rem; margin-left: 5px;">(Dette soldée 🎉)</span>';

                // Harmonisation en bleu (#2563eb) pour le montant net à verser
                $contributions_html .= '<li style="margin-bottom: 5px;"><strong>' . htmlspecialchars($person) . '</strong> : Base ' . $base_fmt . ' € − Remboursé ' . $comp_fmt . ' € = <strong style="color:#2563eb;">' . $net_fmt . ' € à verser</strong>' . $txt_reste . '</li>';
            } else {
                $monthly_savings += $base_amount;
                $contributions_html .= '<li style="margin-bottom: 5px;"><strong>' . htmlspecialchars($person) . '</strong> : <strong style="color:#2563eb;">' . $base_fmt . ' € à verser</strong> <span style="color:var(--danger); font-size:0.8rem; margin-left: 5px;">(Dette gelée, liquidités insuffisantes)</span></li>';
            }
        } else {
            $monthly_savings += $base_amount;
            $contributions_html .= '<li style="margin-bottom: 5px;"><strong>' . htmlspecialchars($person) . '</strong> : <strong style="color:#2563eb;">' . $base_fmt . ' € à verser</strong></li>';
        }
    }
    $contributions_html .= '</ul>';
    $contributions_html .= '<p style="color: var(--text-main); font-weight: bold; margin-top:5px; font-size:0.95rem;">Total net atterrissant sur les comptes : ' . number_format($monthly_savings, 2, ',', ' ') . ' €</p>';

    if(empty($expenses)) {
        $detail_html = '<p style="color: var(--success); font-weight:bold; margin-bottom:1rem; font-size:0.9rem;">🎉 Aucune grosse dépense enregistrée sur ce mois.</p>';
    }

    // --- MOTEUR DE RÈGLES DES QUINZAINES (Remplacement des ** par <strong>) ---
    $instructions = [];
    $remaining_to_allocate = $monthly_savings;

    // Règle 1 : Première quinzaine
    if ($q1_expenses_total > 0) {
        if ($remaining_to_allocate >= $q1_expenses_total) {
            $remaining_to_allocate -= $q1_expenses_total;
            $instructions[] = "💼 <strong>Dès le 1er</strong> : Laissez <strong>" . number_format($q1_expenses_total, 2, ',', ' ') . " €</strong> sur le compte commun pour honorer les dépenses de la 1ère quinzaine.";
        } else {
            $deficit = $q1_expenses_total - $remaining_to_allocate;
            $remaining_to_allocate = 0;
            $instructions[] = "💼 <strong>Dès le 1er</strong> : Gardez l'intégralité des apports sur le compte commun.";
            $instructions[] = "⚠️ <strong>Retrait requis</strong> : Retirez <strong>" . number_format($deficit, 2, ',', ' ') . " €</strong> depuis le Livret A vers le commun pour couvrir la 1ère quinzaine.";
        }
    }

    // Règle 2 : Deuxième quinzaine
    if ($q2_expenses_total > 0) {
        if ($remaining_to_allocate >= $q2_expenses_total) {
            $remaining_to_allocate -= $q2_expenses_total;
            $instructions[] = "📈 <strong>Dès le 1er</strong> : Placez <strong>" . number_format($q2_expenses_total, 2, ',', ' ') . " €</strong> sur le Livret A pour générer des intérêts sur la 1ère quinzaine.";
            $instructions[] = "🔄 <strong>Le 16 du mois</strong> : Transférez ces <strong>" . number_format($q2_expenses_total, 2, ',', ' ') . " €</strong> sur le compte commun pour payer la dépense.";
        } else {
            if ($remaining_to_allocate > 0) {
                $instructions[] = "📈 <strong>Dès le 1er</strong> : Placez le reste des apports (<strong>" . number_format($remaining_to_allocate, 2, ',', ' ') . " €</strong>) sur le Livret A.";
                $instructions[] = "🔄 <strong>Le 16 du mois</strong> : Retirez <strong>" . number_format($q2_expenses_total, 2, ',', ' ') . " €</strong> global du Livret A pour payer la fin de mois.";
                $remaining_to_allocate = 0;
            } else {
                $instructions[] = "🔄 <strong>Le 16 du mois</strong> : Retirez <strong>" . number_format($q2_expenses_total, 2, ',', ' ') . " €</strong> du Livret A vers le commun. <em>Ne le faites pas avant le 16 !</em>";
            }
        }
    }

    // Solde résiduel long terme
    if ($remaining_to_allocate > 0) {
        $instructions[] = "💰 <strong>Épargne stable</strong> : Placez les <strong>" . number_format($remaining_to_allocate, 2, ',', ' ') . " €</strong> restants sur votre Livret A dès le 1er.";
    }

    // --- CONSTITUTION DU RENDU HTML FINAL ---
    $html = '<div style="border-top: 1px dashed var(--border-color); padding-top:0.8rem;">';
    
    // Titre de l'état du Clearing simplifié et clair
    $html .= '<h4 style="margin:0 0 0.4rem 0; font-size:0.95rem;">🤝 Résumé des apports et remboursements internes :</h4>';
    $html .= '<div style="background: var(--bg-main); padding: 8px; border-radius: 6px; margin-bottom: 1rem; border: 1px solid var(--border-color);">';
    $html .= $contributions_html;
    $html .= '</div>';

    $html .= '<h4 style="margin:0 0 0.4rem 0; font-size:0.95rem;">Analyse des dépenses du mois :</h4>';
    $html .= $detail_html;
    
    $html .= '<div style="background: var(--bg-main); padding: 8px; border-radius: 6px; border-left: 4px solid var(--accent-color);">';
    $html .= '<h4 style="margin:0 0 0.5rem 0; color: var(--accent-color); font-size:0.95rem;">📋 Plan d\'action recommandé :</h4>';
    
    foreach ($instructions as $ins) {
        $html .= '<p style="margin:0 0 0.4rem 0; font-size: 0.9rem; line-height: 1.4;">' . $ins . '</p>';
    }
    
    $html .= '</div></div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Calcul impossible : ' . $e->getMessage()]);
}