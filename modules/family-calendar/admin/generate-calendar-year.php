<?php
// /modules/family-calendar/admin/generate-calendar-year.php
require __DIR__ . '/../../../includes/db.php';

// S'assurer que PDO lève des exceptions en cas d'erreur
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    die("Erreur : PDO non initialisé dans db.php");
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    die("Année invalide.");
}

try {
    $pdo->beginTransaction();

    // On supprime d'abord les semaines de cette année calendaire
    $stmtDel = $pdo->prepare("DELETE FROM pf_calendar_weeks WHERE year = :year");
    $stmtDel->execute([':year' => $year]);

    // 1er janvier de l'année calendaire
    $start = new DateTime("$year-01-01");

    // On remonte au lundi précédent (ou on reste dessus si déjà lundi)
    while ($start->format('N') != 1) { // 1 = lundi
        $start->modify('-1 day');
    }

    // Dernier jour de l'année
    $end = new DateTime("$year-12-31");

    // Préparation de l'INSERT avec gestion des doublons
    // (en supposant une contrainte UNIQUE, par ex. sur (year, week_iso_year, week_iso_number))
    $sql = "
      INSERT INTO pf_calendar_weeks (
        year, week_iso_year, week_iso_number, week_label,
        month, month_name,
        week_start_date, mon_date, tue_date, wed_date, thu_date, fri_date, sat_date, sun_date
      ) VALUES (
        :year, :week_iso_year, :week_iso_number, :week_label,
        :month, :month_name,
        :week_start_date, :mon_date, :tue_date, :wed_date, :thu_date, :fri_date, :sat_date, :sun_date
      )
      ON DUPLICATE KEY UPDATE
        week_label      = VALUES(week_label),
        month           = VALUES(month),
        month_name      = VALUES(month_name),
        week_start_date = VALUES(week_start_date),
        mon_date        = VALUES(mon_date),
        tue_date        = VALUES(tue_date),
        wed_date        = VALUES(wed_date),
        thu_date        = VALUES(thu_date),
        fri_date        = VALUES(fri_date),
        sat_date        = VALUES(sat_date),
        sun_date        = VALUES(sun_date)
    ";
    $stmt = $pdo->prepare($sql);

    // fonction mois FR
    function getMonthNameFr($monthIndexZeroBased) {
        $months = [
            "Janvier", "Fevrier", "Mars", "Avril", "Mai", "Juin",
            "Juillet", "Aout", "Septembre", "Octobre", "Novembre", "Decembre",
        ];
        return $months[$monthIndexZeroBased] ?? "";
    }

    $current = clone $start;
    $insertCount = 0;

    while ($current <= $end) {
        $monday = clone $current;

        // Calcul des 7 jours de la semaine
        $mon = clone $monday;
        $tue = (clone $monday)->modify('+1 day');
        $wed = (clone $monday)->modify('+2 days');
        $thu = (clone $monday)->modify('+3 days');
        $fri = (clone $monday)->modify('+4 days');
        $sat = (clone $monday)->modify('+5 days');
        $sun = (clone $monday)->modify('+6 days');

        // Année / semaine ISO
        $weekIsoYear   = (int)$monday->format('o'); // année ISO
        $weekIsoNumber = (int)$monday->format('W');
        $weekLabel     = 'W' . str_pad($weekIsoNumber, 2, '0', STR_PAD_LEFT);

        // Détermination du mois d'affectation de la semaine
        // -> on compte combien de jours de la semaine tombent dans chaque mois
        $days = [$mon, $tue, $wed, $thu, $fri, $sat, $sun];

        // compteur mois => nb de jours (clé = numéro de mois 1..12)
        $monthCounts = [];

        foreach ($days as $d) {
            $m = (int)$d->format('n'); // 1-12
            if (!isset($monthCounts[$m])) {
                $monthCounts[$m] = 0;
            }
            $monthCounts[$m]++;
        }

        // On prend le mois ayant le plus de jours
        // (si égalité, le mois ayant la plus petite valeur numérique gagnera,
        // ce qui est raisonnable, mais on peut changer si besoin)
        $chosenMonth = null;
        $maxDays     = -1;
        foreach ($monthCounts as $m => $count) {
            if ($count > $maxDays) {
                $maxDays     = $count;
                $chosenMonth = $m;
            }
        }

        $month     = $chosenMonth;
        $monthName = getMonthNameFr($month - 1);

                $stmt->execute([
            ':year'            => $weekIsoYear,   // <-- au lieu de $year
            ':week_iso_year'   => $weekIsoYear,
            ':week_iso_number' => $weekIsoNumber,
            ':week_label'      => $weekLabel,
            ':month'           => $month,
            ':month_name'      => $monthName,
            ':week_start_date' => $monday->format('Y-m-d'),
            ':mon_date'        => $mon->format('Y-m-d'),
            ':tue_date'        => $tue->format('Y-m-d'),
            ':wed_date'        => $wed->format('Y-m-d'),
            ':thu_date'        => $thu->format('Y-m-d'),
            ':fri_date'        => $fri->format('Y-m-d'),
            ':sat_date'        => $sat->format('Y-m-d'),
            ':sun_date'        => $sun->format('Y-m-d'),
        ]);


        $insertCount++;
        $current->modify('+7 days');
    }

    $pdo->commit();

    echo "Calendrier $year généré. Semaines traitées : " . $insertCount;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur PDO : " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur générale : " . $e->getMessage());
}
