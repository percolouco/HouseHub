<?php
/**
 * Script de rattrapage : Configuration dynamique des congés (CP, JRA, JA)
 * Ce script lit l'historique réel des soldes/snapshots pour pré-configurer la modale Settings.
 */

require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🚀 Début de la restauration des compteurs de congés</h1>";

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        echo "<h2>Famille : {$family['name']} ($dbName)</h2>";

        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$dbName;charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 1. Création de la table de configuration (si elle n'existe pas)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pf_person_leave_meta (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    person_id INT NOT NULL,
                    leave_type VARCHAR(50) NOT NULL,
                    anniversary_date DATE NOT NULL,
                    UNIQUE KEY uq_person_leave (person_id, leave_type),
                    FOREIGN KEY (person_id) REFERENCES pf_people(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 2. On scanne l'historique des soldes et snapshots pour trouver TOUS les types existants
            $stmtLeaves = $pdo->query("
                SELECT DISTINCT person_id, leave_type 
                FROM (
                    SELECT person_id, leave_type FROM pf_leave_balances
                    UNION
                    SELECT person_id, leave_type FROM pf_leave_snapshots
                ) as combined
                WHERE leave_type IS NOT NULL AND leave_type != ''
            ");
            
            $existingLeaves = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
            $inserted = 0;

            $stmtInsert = $pdo->prepare("
                INSERT IGNORE INTO pf_person_leave_meta (person_id, leave_type, anniversary_date) 
                VALUES (?, ?, ?)
            ");

            // 3. On injecte les données dans la configuration avec les dates par défaut de ton ancien JS
            foreach ($existingLeaves as $leave) {
                $pid = $leave['person_id'];
                $type = strtoupper(trim($leave['leave_type']));
                
                // Dates d'anniversaire par défaut (année 2000 arbitraire, seul le mois/jour compte)
                $anniversary = '2000-01-01'; 
                if ($type === 'CP')  $anniversary = '2000-06-01'; // 1er Juin
                if ($type === 'JRA') $anniversary = '2000-01-01'; // 1er Janvier
                if ($type === 'JA')  $anniversary = '2000-04-29'; // 29 Avril (issu de ton ancien JS)

                $stmtInsert->execute([$pid, $type, $anniversary]);
                if ($stmtInsert->rowCount() > 0) {
                    $inserted++;
                }
            }

            echo "✅ $inserted compteurs historiques (CP, JRA, JA...) détectés et configurés avec succès pour la modale.<br>";

        } catch (PDOException $e) {
            echo "❌ Erreur sur la base $dbName : " . $e->getMessage() . "<br>";
        }
    }

    echo "<h1>🎉 Restauration terminée avec succès !</h1>";

} catch (Exception $e) {
    die("❌ Erreur fatale Meta DB : " . $e->getMessage());
}
?>