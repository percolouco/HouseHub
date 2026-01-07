<?php
// api/manage-leaf.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON invalide.']);
    exit;
}

$action = $input['action'] ?? null;
if (!$action) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

try {
    // ===================== DELETE TOUS LES LEAVES D'UN JOUR (EXISTANT) =====================
    if ($action === 'delete_day') {

        if (empty($input['date'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date manquante pour delete_day.']);
            exit;
        }

        $dateObj = date_create($input['date']);
        if (!$dateObj) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date invalide.']);
            exit;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmt = $pdo->prepare("DELETE FROM pf_leaves WHERE leave_date = ?");
        $stmt->execute([$leaveDate]);

        echo json_encode(['status' => 'success', 'message' => 'Congés supprimés pour ce jour.']);
        exit;
    }

    // ===================== DELETE POUR UNE PERSONNE / JOUR (SINGLE) =====================
    if ($action === 'delete_day_person') {
        $date = $input['date'] ?? null;
        $personId = $input['person_id'] ?? null;

        if (empty($date) || empty($personId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'date ou person_id manquant pour delete_day_person.']);
            exit;
        }

        $dateObj = date_create($date);
        if (!$dateObj) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date invalide pour delete_day_person.']);
            exit;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmt = $pdo->prepare("
            DELETE FROM pf_leaves
            WHERE leave_date = :date
              AND person_id = :person_id
        ");
        $stmt->execute([
            ':date'      => $leaveDate,
            ':person_id' => $personId,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Congés supprimés pour cette personne et ce jour.',
            'deleted' => $stmt->rowCount(),
        ]);
        exit;
    }

    // ===================== BULK DELETE POUR UNE PERSONNE / PLUSIEURS JOURS =====================
    if ($action === 'bulk_delete_day_person') {
        $dates    = $input['dates'] ?? null;
        $personId = $input['person_id'] ?? null;

        if (!is_array($dates) || empty($dates) || empty($personId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'dates ou person_id manquant pour bulk_delete_day_person.']);
            exit;
        }

        // Normaliser les dates en 'Y-m-d'
        $normalizedDates = [];
        foreach ($dates as $d) {
            $dateObj = date_create($d);
            if ($dateObj) {
                $normalizedDates[] = $dateObj->format('Y-m-d');
            }
        }

        if (empty($normalizedDates)) {
            echo json_encode([
                'status'  => 'success',
                'message' => 'Aucune date valide fournie.',
                'deleted' => 0,
            ]);
            exit;
        }

        // Construire le IN (...)
        $placeholders = implode(',', array_fill(0, count($normalizedDates), '?'));
        $sql = "
            DELETE FROM pf_leaves
            WHERE person_id = ?
              AND leave_date IN ($placeholders)
        ";
        $stmt = $pdo->prepare($sql);

        $params = array_merge([$personId], $normalizedDates);
        $stmt->execute($params);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Congés supprimés pour cette personne sur les dates fournies.',
            'deleted' => $stmt->rowCount(),
        ]);
        exit;
    }

    // ===================== ACTION NON RECONNUE =====================
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
