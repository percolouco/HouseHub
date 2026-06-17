<?php
// modules/family-calendar/includes/api/manage-event.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

try {
    if ($action === 'delete') {
        $eventId = (int)($input['event_id'] ?? 0);
        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'ID manquant.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['success' => true, 'status' => 'success']);
        exit;
    }

    if ($action === 'update') {
        $eventId = (int)($input['event_id'] ?? 0);
        $newType = $input['new_type'] ?? '';
        if ($eventId <= 0 || !$newType) {
            http_response_code(400);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Données manquantes.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id = ?");
        $stmt->execute([$newType, $eventId]);
        echo json_encode(['success' => true, 'status' => 'success']);
        exit;
    }

    if ($action === 'bulk_delete_day_types_person') {
        $dates = $input['dates'] ?? [];
        $types = $input['types'] ?? [];
        $person_id = $input['person_id'] ?? null;

        $dates = array_filter($dates, function($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); });
        
        if (empty($dates) || empty($types)) { 
            echo json_encode(['success' => true, 'status' => 'success']); 
            exit; 
        }

        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));

        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders) AND event_type IN ($typePlaceholders)";
        $params = array_merge($dates, $types);

        // 🔥 LE CORRECTIF : On cherche le 0 en base de données
        if ($person_id === null || $person_id === '' || (int)$person_id === 0) {
            $sql .= " AND person_id = 0";
        } else {
            $sql .= " AND person_id = ?";
            $params[] = (int)$person_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'status' => 'success']);
        exit;
    }

    if ($action === 'bulk_delete_day_types') {
        $dates = $input['dates'] ?? [];
        $types = $input['types'] ?? [];
        $dates = array_filter($dates, function($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); });

        if (empty($dates) || empty($types)) {
            echo json_encode(['success' => true, 'status' => 'success']);
            exit;
        }

        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));

        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders) AND event_type IN ($typePlaceholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($dates, $types));
        
        echo json_encode(['success' => true, 'status' => 'success']);
        exit;
    }

    if ($action === 'bulk_delete_all') {
        $dates = $input['dates'] ?? [];
        $dates = array_filter($dates, function($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); });

        if (empty($dates)) { 
            echo json_encode(['success' => true, 'status' => 'success']);
            exit;
        }
        
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dates);
        
        echo json_encode(['success' => true, 'status' => 'success']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Action non reconnue : ' . $action]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $e->getMessage()]);
}
?>