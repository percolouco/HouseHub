<?php
header('Content-Type: application/json');

require __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, event_date AS date, event_type AS type, person_id, duration FROM pf_events ORDER BY event_date");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'events' => $events,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erreur lors de la récupération des événements : ' . $e->getMessage(),
    ]);
}
