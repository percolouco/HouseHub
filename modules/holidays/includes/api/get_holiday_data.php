<?php
// modules/holidays/includes/api/get_holiday_data.php

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

try {
    // Si tu utilises pf_holidays au lieu de pf_holidays_ideas, change le nom de la table ici !
    $st = $pdo->prepare("SELECT * FROM pf_holidays_ideas WHERE id = ?");
    $st->execute([$id]);
    $it = $st->fetch(PDO::FETCH_ASSOC);

    if (!$it) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $it['id'] = (int)$it['id'];
    
    if (isset($it['lat'])) $it['lat'] = (float)$it['lat'];
    if (isset($it['lng'])) $it['lng'] = (float)$it['lng'];
    if (isset($it['ideal_days'])) $it['ideal_days'] = (int)$it['ideal_days'];
    
    echo json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}