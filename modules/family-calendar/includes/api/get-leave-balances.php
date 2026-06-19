<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

try {
    // 🔥 LE FIX : On interroge la NOUVELLE table des quotas individuels !
    $stmt = $pdo->query("
        SELECT 
            person_id, 
            leave_type AS type, 
            allowance, 
            method, 
            anniversary_date AS date
        FROM pf_person_leave_meta
    ");
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'balances' => $balances
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
?>