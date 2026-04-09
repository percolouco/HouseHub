<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['person_id']) || empty($input['leave_type']) || empty($input['snapshot_date']) || !isset($input['remaining_balance'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données manquantes.']);
    exit;
}

try {
    // Étape 1 : Par sécurité, on supprime un éventuel snapshot existant exactement à la même date pour cette personne et ce type
    $stmtDel = $pdo->prepare("DELETE FROM pf_leave_snapshots WHERE person_id = ? AND leave_type = ? AND snapshot_date = ?");
    $stmtDel->execute([$input['person_id'], $input['leave_type'], $input['snapshot_date']]);

    // Étape 2 : On insère le nouveau solde
    $stmt = $pdo->prepare("INSERT INTO pf_leave_snapshots (person_id, leave_type, snapshot_date, remaining_balance) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $input['person_id'],
        $input['leave_type'],
        $input['snapshot_date'],
        $input['remaining_balance']
    ]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}