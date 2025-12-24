<?php
// api/manage-leaf.php
header('Content-Type: application/json');
require __DIR__ . '/../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

$action = $input['action'];

try {
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

    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
