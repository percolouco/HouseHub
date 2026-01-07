<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

$action = $input['action'];

try {
    if ($action === 'delete') {
        // Suppression d'un seul événement
        if (!isset($input['event_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID manquant pour la suppression.']);
            exit;
        }

        $eventId = (int)$input['event_id'];
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id = ?");
        $stmt->execute([$eventId]);

        echo json_encode(['status' => 'success', 'message' => 'Événement supprimé.']);

    } elseif ($action === 'update') {
        // Mise à jour d'un seul événement
        if (!isset($input['event_id'], $input['new_type'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID ou nouveau type manquant pour la mise à jour.']);
            exit;
        }

        $eventId = (int)$input['event_id'];
        $newType = $input['new_type'];

        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id = ?");
        $stmt->execute([$newType, $eventId]);

        echo json_encode(['status' => 'success', 'message' => 'Événement mis à jour.']);

    } elseif ($action === 'bulk_delete') {
        // Suppression en masse
        if (empty($input['event_ids']) || !is_array($input['event_ids'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Liste event_ids manquante pour bulk_delete.']);
            exit;
        }

        $eventIds = array_map('intval', $input['event_ids']);
        $eventIds = array_filter($eventIds, fn($id) => $id > 0);

        if (empty($eventIds)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Aucun ID valide pour bulk_delete.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id IN ($placeholders)");
        $stmt->execute($eventIds);

        echo json_encode(['status' => 'success', 'message' => 'Événements supprimés en masse.']);

    } elseif ($action === 'bulk_update') {
        // Mise à jour en masse
        if (empty($input['event_ids']) || !is_array($input['event_ids']) || !isset($input['new_type'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'event_ids ou new_type manquant pour bulk_update.']);
            exit;
        }

        $eventIds = array_map('intval', $input['event_ids']);
        $eventIds = array_filter($eventIds, fn($id) => $id > 0);
        $newType = $input['new_type'];

        if (empty($eventIds)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Aucun ID valide pour bulk_update.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $params = array_merge([$newType], $eventIds);

        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);

        echo json_encode(['status' => 'success', 'message' => 'Événements mis à jour en masse.']);

    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
