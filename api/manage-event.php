<?php
header('Content-Type: application/json');
require __DIR__ . '/../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || !isset($input['event_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action ou ID manquant.']);
    exit;
}

$action = $input['action'];
$eventId = $input['event_id'];

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['status' => 'success', 'message' => 'Événement supprimé.']);

    } elseif ($action === 'update' && isset($input['new_type'])) {
        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id = ?");
        $stmt->execute([$input['new_type'], $eventId]);
        echo json_encode(['status' => 'success', 'message' => 'Événement mis à jour.']);

    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Action non valide ou données manquantes.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
