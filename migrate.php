<?php
require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🗺️ Migrations HouseHub OS</h1><ul>";

try {
    // On récupère toutes les familles actives dans la base meta
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        echo "<li><strong>Famille : {$family['name']} ({$dbName})</strong><ul>";
        
        try {
            // Connexion PDO spécifique à la base de la famille (Multi-tenant)
            $pdo = new PDO("mysql:host=$db_host;dbname=$dbName;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // ─────────────────────────────────────────────────────────────
            // ✈️ MIGRATION 1 : Refonte Modèle Voyages
            // ─────────────────────────────────────────────────────────────
            try {
                $pdo->exec("ALTER TABLE pf_holidays ADD COLUMN return_step_id INT DEFAULT NULL");
                $pdo->exec("ALTER TABLE pf_holidays_items ADD COLUMN step_type VARCHAR(20) DEFAULT 'stop'");
                $pdo->exec("ALTER TABLE pf_holidays_items ADD COLUMN expense_context VARCHAR(20) DEFAULT NULL");
                
                // Migration des données
                $pdo->exec("
                    UPDATE pf_holidays h 
                    SET return_step_id = (
                        SELECT id FROM pf_holidays_items i 
                        WHERE i.holiday_id = h.id AND i.is_return = 1 AND i.location_name IS NOT NULL 
                        LIMIT 1
                    )
                ");
                
                // Nettoyage
                $pdo->exec("ALTER TABLE pf_holidays_items DROP COLUMN is_return");
                echo "<li>✅ Schéma Voyages mis à jour !</li>";
                
            } catch (\PDOException $e) {
                // 1060 : Duplicate column name / 1091 : Can't drop column (already dropped)
                if (in_array($e->errorInfo[1], [1060, 1091])) {
                    echo "<li>⏳ Schéma Voyages déjà à jour. (Ignoré)</li>";
                } else {
                    echo "<li>⚠️ Erreur sur Voyages : " . $e->getMessage() . "</li>";
                }
            }

            // ─────────────────────────────────────────────────────────────
            // 💰 MIGRATION 2 : Provisions & Optimisation Trésorerie (Quinzaines)
            // ─────────────────────────────────────────────────────────────
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `pf_expected_expenses` (
                      `id` INT(11) NOT NULL AUTO_INCREMENT,
                      `title` VARCHAR(150) NOT NULL,
                      `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                      `expected_date` DATE NOT NULL,
                      `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
                      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      INDEX `idx_expected_date` (`expected_date`),
                      INDEX `idx_is_paid` (`is_paid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                echo "<li>✅ Table `pf_expected_expenses` (Provisions Budget) créée/vérifiée !</li>";
                
            } catch (\PDOException $e) {
                echo "<li>⚠️ Erreur sur Provisions Budget : " . $e->getMessage() . "</li>";
            }

        } catch (\PDOException $e) {
            echo "<li>❌ Impossible de se connecter à la base de données : " . $e->getMessage() . "</li>";
        }
        
        echo "</ul></li><br>";
    }
    
    echo "</ul><h2>🎉 Migrations terminées avec succès !</h2>";

} catch (Exception $e) {
    die("Erreur fatale globale : " . $e->getMessage());
}
?>