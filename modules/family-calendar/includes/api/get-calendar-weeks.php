<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    $stmt = $pdo->prepare("
        SELECT
          year,
          week_iso_year,
          week_iso_number,
          week_label,
          month,
          month_name,
          week_start_date,
          mon_date,
          tue_date,
          wed_date,
          thu_date,
          fri_date
        FROM pf_calendar_weeks
        WHERE year = :year
        ORDER BY week_start_date ASC
    ");
    $stmt->execute([':year' => $year]);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['weeks' => $weeks]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
