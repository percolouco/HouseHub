<?php
require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🗺️ Migration : Ajout du Véhicule aux Voyages</h1><ul>";

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$dbName;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Ajout de la colonne vehicle_id
            $pdo->exec("ALTER TABLE pf_holidays ADD COLUMN vehicle_id INT DEFAULT NULL");
            
            // Optionnel mais propre : On ajoute une clé étrangère
            $pdo->exec("ALTER TABLE pf_holidays ADD CONSTRAINT fk_holiday_vehicle FOREIGN KEY (vehicle_id) REFERENCES pf_vehicles(id) ON DELETE SET NULL");
            
            echo "<li>✅ {$family['name']} : Colonne vehicle_id ajoutée !</li>";
        } catch (\PDOException $e) {
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<li>⏩ {$family['name']} : Déjà à jour.</li>";
            } else {
                echo "<li>❌ {$family['name']} : Erreur - " . $e->getMessage() . "</li>";
            }
        }
    }
    echo "</ul><h2>🎉 Migration terminée !</h2>";
} catch (Exception $e) {
    die("Erreur fatale : " . $e->getMessage());
}
?>