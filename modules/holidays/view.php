<?php
// modules/holidays/view.php

require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

try {
    $st = $pdo->prepare("SELECT * FROM pf_holidays_ideas WHERE id = ?");
    $st->execute([$id]);
    $it = $st->fetch(PDO::FETCH_ASSOC);

    if (!$it) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    // Amélioration : Typage explicite pour le JSON
    // Cela évite que JS reçoive "4" (string) au lieu de 4 (int) pour les calculs
    $it['id'] = (int)$it['id'];
    
    if (isset($it['lat'])) $it['lat'] = (float)$it['lat'];
    if (isset($it['lng'])) $it['lng'] = (float)$it['lng'];
    if (isset($it['ideal_days'])) $it['ideal_days'] = (int)$it['ideal_days'];
    
    // On s'assure que les null restent null et pas des chaines vides si la DB est stricte
    // (Optionnel mais propre)

    echo json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}