<?php
// /modules/family-calendar/includes/api/get-person-leave-meta.php

require __DIR__ . '/../../../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("
        SELECT person_id, anniversary_date
        FROM pf_person_leave_meta
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'meta' => $rows,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ]);
}
