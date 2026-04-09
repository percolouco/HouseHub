<?php
// includes/api/save-events.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$rawInput = file_get_contents('php://input');
$eventsToSave = json_decode($rawInput, true);

// Optionnel : tu peux commenter/supprimer ces logs en production pour économiser du disque
file_put_contents(
    __DIR__ . '/events-debug.log',
    "[" . date('c') . "] RAW INPUT: " . $rawInput . PHP_EOL,
    FILE_APPEND
);

if (empty($eventsToSave) || !is_array($eventsToSave)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune donnée d\'événement reçue.']);
    exit;
}

$inserted = [];

try {
    // 1. OPTIMISATION : On récupère toutes les personnes d'un coup (Mapping)
    $stmtPeople = $pdo->query("SELECT id, name FROM pf_people");
    $peopleMap = [];
    while ($row = $stmtPeople->fetch(PDO::FETCH_ASSOC)) {
        // On crée un tableau associatif : ['Carole' => 1, 'Alex' => 2, etc.]
        $peopleMap[$row['name']] = $row['id'];
    }

    $pdo->beginTransaction();

    $sql = "INSERT INTO pf_events (event_date, event_type, person_id, duration)
            VALUES (:event_date, :event_type, :person_id, :duration)";
    $stmt = $pdo->prepare($sql);

    foreach ($eventsToSave as $event) {
        $person_id = null;

        // 2. On vérifie simplement dans notre tableau (plus de requête SQL ici !)
        if (!empty($event['person']) && isset($peopleMap[$event['person']])) {
            $person_id = $peopleMap[$event['person']];
        }

        $stmt->execute([
            ':event_date' => $event['date'],
            ':event_type' => $event['type'],
            ':person_id'  => $person_id,
            ':duration'   => $event['duration'] ?? 1.0,
        ]);

        $inserted[] = [
            'id'        => $pdo->lastInsertId(),
            'date'      => $event['date'],
            'type'      => $event['type'],
            'duration'  => $event['duration'] ?? 1.0,
            'person_id' => $person_id,
        ];
    }

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'inserted' => $inserted,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()]);
}