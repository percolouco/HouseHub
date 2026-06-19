<?php
require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>📅 Génération du squelette du calendrier (pf_calendar_weeks)</h1>";

$monthsFr = [1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'];

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        echo "<h3>Famille : {$family['name']} ($dbName)</h3><ul>";

        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$dbName;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // On utilise REPLACE INTO pour écraser proprement si certaines semaines existent déjà
            $sql = "REPLACE INTO pf_calendar_weeks 
                    (year, week_iso_year, week_iso_number, week_label, month, month_name, week_start_date, mon_date, tue_date, wed_date, thu_date, fri_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($sql);

            // On génère de l'été 2023 jusqu'à fin 2030 !
            $startDate = new DateTime('2023-08-28'); // Un lundi
            $endDate = new DateTime('2030-12-31');
            $count = 0;

            while ($startDate <= $endDate) {
                $mon = clone $startDate;
                $tue = clone $startDate; $tue->modify('+1 day');
                $wed = clone $startDate; $wed->modify('+2 days');
                $thu = clone $startDate; $thu->modify('+3 days');
                $fri = clone $startDate; $fri->modify('+4 days');

                $year = (int)$mon->format('Y');
                $month = (int)$mon->format('n');
                $weekIsoYear = (int)$mon->format('o');
                $weekIsoNumber = (int)$mon->format('W');
                $weekLabel = "Semaine " . $weekIsoNumber;
                $monthName = $monthsFr[$month];

                $insertStmt->execute([
                    $year,
                    $weekIsoYear,
                    $weekIsoNumber,
                    $weekLabel,
                    $month,
                    $monthName,
                    $mon->format('Y-m-d'),
                    $mon->format('Y-m-d'),
                    $tue->format('Y-m-d'),
                    $wed->format('Y-m-d'),
                    $thu->format('Y-m-d'),
                    $fri->format('Y-m-d')
                ]);

                $startDate->modify('+1 week');
                $count++;
            }
            echo "<li>✅ $count semaines générées (de 2023 à 2030) !</li>";
        } catch (\PDOException $e) {
            echo "<li>❌ Erreur : " . $e->getMessage() . "</li>";
        }
        echo "</ul>";
    }
    echo "<h2>🎉 Terminé ! Tu peux supprimer ce fichier et rafraîchir ton calendrier.</h2>";

} catch (Exception $e) {
    die("Erreur fatale Meta DB : " . $e->getMessage());
}
?>