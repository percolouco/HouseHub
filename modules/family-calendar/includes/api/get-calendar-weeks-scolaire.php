<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

/**
 * Paramètre: school_year_start (optionnel)
 * - année de début d'année scolaire (ex: 2025 pour 09/2025 -> 08/2026)
 * - défaut: année courante si non fourni
 */
$schoolYearStart = isset($_GET['school_year_start'])
    ? (int)$_GET['school_year_start']
    : (int)date('Y');

if ($schoolYearStart < 2000 || $schoolYearStart > 2100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Année scolaire invalide.']);
    exit;
}

$yearStart = $schoolYearStart;
$yearEnd   = $schoolYearStart + 1;

// On veut toutes les semaines:
// - de septembre (month >= 9) de yearStart
// - à août (month <= 8) de yearEnd

try {
    $sql = "
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
        WHERE
          (year = :year_start AND month >= 9)
          OR (year = :year_end AND month <= 8)
        ORDER BY week_start_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':year_start' => $yearStart,
        ':year_end'   => $yearEnd,
    ]);

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'        => 'success',
        'school_year'   => $schoolYearStart,
        'weeks'         => $weeks,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
