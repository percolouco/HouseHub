<?php
require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🗺️ Migration : Refonte Modèle Voyages</h1><ul>";

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$dbName;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // 1. Mise à jour pf_holidays
            $pdo->exec("ALTER TABLE pf_holidays ADD COLUMN return_step_id INT DEFAULT NULL");
            
            // 2. Mise à jour pf_holidays_items
            $pdo->exec("ALTER TABLE pf_holidays_items ADD COLUMN step_type VARCHAR(20) DEFAULT 'stop'");
            $pdo->exec("ALTER TABLE pf_holidays_items ADD COLUMN expense_context VARCHAR(20) DEFAULT NULL");
            
            // 3. Migration des données (is_return -> return_step_id)
            // On cherche la première étape cochée "is_return" et on l'assigne au voyage
            $pdo->exec("
                UPDATE pf_holidays h 
                SET return_step_id = (
                    SELECT id FROM pf_holidays_items i 
                    WHERE i.holiday_id = h.id AND i.is_return = 1 AND i.location_name IS NOT NULL 
                    LIMIT 1
                )
            ");
            
            // 4. Nettoyage de l'ancienne colonne
            $pdo->exec("ALTER TABLE pf_holidays_items DROP COLUMN is_return");
            
            echo "<li>✅ {$family['name']} : Schéma Voyages mis à jour !</li>";
        } catch (\PDOException $e) {
            echo "<li>⚠️ {$family['name']} : " . $e->getMessage() . "</li>";
        }
    }
    echo "</ul><h2>🎉 Migration terminée !</h2>";
} catch (Exception $e) {
    die("Erreur fatale : " . $e->getMessage());
}
?>