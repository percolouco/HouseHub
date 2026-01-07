<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT person_id, leave_type, snapshot_date, remaining_balance
        FROM pf_leave_snapshots
        ORDER BY snapshot_date ASC
    ");
    $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['snapshots' => $snapshots]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
