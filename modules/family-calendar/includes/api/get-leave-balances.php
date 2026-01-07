<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT person_id, leave_type, initial_balance, balance_year
        FROM pf_leave_balances
    ");
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['balances' => $balances]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
