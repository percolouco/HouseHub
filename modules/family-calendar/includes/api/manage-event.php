<?php
// modules/family-calendar/includes/api/manage-event.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

try {
    // --- SUPPRESSION UNITAIRE ---
    if ($action === 'delete') {
        $eventId = (int)($input['event_id'] ?? 0);
        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID manquant.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- MISE À JOUR UNITAIRE ---
    if ($action === 'update') {
        $eventId = (int)($input['event_id'] ?? 0);
        $newType = $input['new_type'] ?? '';
        if ($eventId <= 0 || !$newType) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données manquantes.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id = ?");
        $stmt->execute([$newType, $eventId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- SUPPRESSION DE MASSE (Par date et type) ---
    // Utilisé quand on ajoute un événement pour nettoyer les doublons potentiels (ex: Off vs Extra)
    if ($action === 'bulk_delete_day_types') {
        $dates = $input['dates'] ?? [];
        $types = $input['types'] ?? [];

        if (empty($dates) || empty($types)) {
            echo json_encode(['status' => 'success']); // Rien à faire
            exit;
        }

        // Création des placeholders IN (?,?,?)
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));

        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders) AND event_type IN ($typePlaceholders)";
        $stmt = $pdo->prepare($sql);
        
        // Fusion des tableaux pour l'exécution
        $stmt->execute(array_merge($dates, $types));
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- SUPPRESSION TOTALE SUR DES DATES ---
    if ($action === 'bulk_delete_all') {
        $dates = $input['dates'] ?? [];
        if (empty($dates)) {
            echo json_encode(['status' => 'success']);
            exit;
        }
        
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dates);
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Action inconnue
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action non reconnue : ' . $action]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}