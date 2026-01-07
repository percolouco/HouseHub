<?php
// api/save-leaves.php
header('Content-Type: application/json');

require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données invalides (attendu tableau JSON).']);
    exit;
}

// Exemple d'élément attendu :
// { date: '2025-09-01', person_id: 2, leave_type: 'CP', duration: 1.0 }

$validLeaveTypes = ['CP', 'JRA', 'JA'];

try {
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare("
        INSERT INTO pf_leaves (person_id, leave_type, leave_date, duration)
        VALUES (:person_id, :leave_type, :leave_date, :duration)
    ");

    foreach ($input as $item) {
        $date      = $item['date']      ?? null;
        $personId  = $item['person_id'] ?? null;
        $leaveType = $item['leave_type'] ?? null;
        $duration  = isset($item['duration']) ? (float)$item['duration'] : 1.0;

        if (!$date || !$personId || !$leaveType) {
            continue; // on ignore les lignes incomplètes
        }

        if (!in_array($leaveType, $validLeaveTypes, true)) {
            continue; // leave_type invalide
        }

        // Normalisation de la date
        $dateObj = date_create($date);
        if (!$dateObj) {
            continue;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmtInsert->execute([
            ':person_id'   => (int)$personId,
            ':leave_type'  => $leaveType,
            ':leave_date'  => $leaveDate,
            ':duration'    => $duration,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Congés enregistrés.',
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
