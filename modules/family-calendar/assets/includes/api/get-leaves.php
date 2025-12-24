<?php
// api/get-leaves.php
header('Content-Type: application/json');

require __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT
          l.id,
          l.person_id,
          p.name AS person_name,
          l.leave_type,
          l.leave_date,
          l.duration
        FROM pf_leaves l
        JOIN pf_people p ON p.id = l.person_id
        ORDER BY l.leave_date, l.person_id
    ");

    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'leaves' => $leaves,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
