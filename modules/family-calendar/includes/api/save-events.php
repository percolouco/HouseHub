<?php
// modules/family-calendar/includes/api/save-events.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../includes/db.php';

try {
    $rawInput = file_get_contents('php://input');
    $eventsToSave = json_decode($rawInput, true);

    if (empty($eventsToSave) || !is_array($eventsToSave)) {
        throw new Exception("Aucune donnée d'événement reçue.");
    }

    $pdo->beginTransaction();

    $sql = "INSERT INTO pf_events (event_date, event_type, person_id, duration)
            VALUES (:event_date, :event_type, :person_id, :duration)";
    $stmt = $pdo->prepare($sql);

    $inserted = [];

    foreach ($eventsToSave as $event) {
        // 🔥 LE CORRECTIF : On initialise à 0 (car NOT NULL en base)
        $person_id = 0;

        if (!empty($event['person_id']) && is_numeric($event['person_id'])) {
            $person_id = (int)$event['person_id'];
        } elseif (!empty($event['person']) && is_numeric($event['person'])) {
            $person_id = (int)$event['person'];
        }

        $duration = isset($event['duration']) ? (float)$event['duration'] : 1.0;

        $stmt->execute([
            ':event_date' => $event['date'],
            ':event_type' => $event['type'],
            ':person_id'  => $person_id,
            ':duration'   => $duration,
        ]);

        $inserted[] = [
            'id'        => $pdo->lastInsertId(),
            'date'      => $event['date'],
            'type'      => $event['type'],
            'duration'  => $duration,
            'person_id' => $person_id,
        ];
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'status'   => 'success',
        'inserted' => $inserted,
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'status'  => 'error', 
        'message' => 'Erreur SQL : ' . $e->getMessage()
    ]);
}
?>