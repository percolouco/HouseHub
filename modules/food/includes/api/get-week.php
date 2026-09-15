<?php
// Désactive l'affichage des erreurs HTML pour garantir un retour JSON strict
ini_set('display_errors', 0);
error_reporting(0);

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require dirname(__DIR__, 4) . '/includes/i18n.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $dateParam = $_GET['date'] ?? date('Y-m-d');
    $baseDate = new DateTime($dateParam);
    
    // Aligner sur le lundi de la semaine
    $monday = clone $baseDate;
    if ($monday->format('N') != 1) {
        $monday->modify('last monday');
    }

    $sunday = clone $monday;
    $sunday->modify('+6 days');

    $weekData = [
        'week_label' => tr('meal_week') . ' ' . $monday->format('W'),
        'days' => []
    ];

    // Extraction des repas de la base (person_id = 0 pour le foyer global)
    $stmt = $pdo->prepare("SELECT plan_date, service, meal_name FROM pf_meals_plan WHERE plan_date BETWEEN ? AND ? AND person_id = 0");
    $stmt->execute([$monday->format('Y-m-d'), $sunday->format('Y-m-d')]);
    
    $meals = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $meals[$row['plan_date']][$row['service']] = $row['meal_name'];
    }

    $current = clone $monday;
    
    // Mappage vers les clés i18n existantes de HouseHub
    $daysMap = [
        1 => 'day_mon', 2 => 'day_tue', 3 => 'day_wed',
        4 => 'day_thu', 5 => 'day_fri', 6 => 'day_sat', 7 => 'day_sun'
    ];

    for ($i = 0; $i < 7; $i++) {
        $dStr = $current->format('Y-m-d');
        
        $dayNum = $current->format('N'); // 1 à 7
        $monthNum = $current->format('m'); // 01 à 12
        $dayDate = $current->format('d'); // 01 à 31
        
        $dayName = tr($daysMap[$dayNum]);
        $monthName = tr('month_' . $monthNum);

        $weekData['days'][] = [
            'date' => $dStr,
            'day_name' => $dayName,
            'date_formatted' => $dayDate . ' ' . $monthName,
            'lunch' => $meals[$dStr]['lunch'] ?? '',
            'dinner' => $meals[$dStr]['dinner'] ?? ''
        ];
        $current->modify('+1 day');
    }

    echo json_encode(['success' => true, 'data' => $weekData], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}