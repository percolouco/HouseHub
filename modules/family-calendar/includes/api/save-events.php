<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$eventsToSave = json_decode(file_get_contents('php://input'), true);
$rawInput = file_get_contents('php://input');
file_put_contents(
    __DIR__ . '/events-debug.log',
    "[" . date('c') . "] RAW INPUT: " . $rawInput . PHP_EOL,
    FILE_APPEND
);

$eventsToSave = json_decode($rawInput, true);


if (empty($eventsToSave) || !is_array($eventsToSave)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune donnée d\'événement reçue.']);
    exit;
}

$inserted = [];

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO pf_events (event_date, event_type, person_id, duration)
            VALUES (:event_date, :event_type, :person_id, :duration)";
    $stmt = $pdo->prepare($sql);

    foreach ($eventsToSave as $event) {
        $person_id = null;

        // Si un nom de personne est fourni
        if (!empty($event['person'])) {
            $personStmt = $pdo->prepare("SELECT id FROM pf_people WHERE name = ?");
            $personStmt->execute([$event['person']]);
            $personRow = $personStmt->fetch();
            if ($personRow) {
                $person_id = $personRow['id'];
            }
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
