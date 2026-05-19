<?php
// Script de migration pour mettre à jour TOUTES les bases de données familiales
require_once __DIR__ . '/includes/meta_db.php';

echo "<h1>🚀 Début de la migration...</h1>";

// 1. Récupérer toutes les bases de données des familles
$stmt = $meta_pdo->query("SELECT name, db_name FROM families WHERE db_name != ''");
$families = $stmt->fetchAll();

$host = getenv('DB_HOST') ?: 'househub-db';
$user = getenv('DB_USER') ?: 'househub';
$pass = getenv('DB_PASS') ?: 'changeme';

// 2. Boucler sur chaque famille
foreach ($families as $f) {
    $db_name = $f['db_name'];
    echo "Mise à jour de la famille <strong>{$f['name']}</strong> (Base : $db_name) ... ";
    
    try {
        $fam_pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // 3. Injecter la nouvelle table
        $sql = "
        CREATE TABLE IF NOT EXISTS pf_foyer_settings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          currency VARCHAR(10) NOT NULL DEFAULT '€',
          zone_scolaire VARCHAR(5) NOT NULL DEFAULT 'C',
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        INSERT IGNORE INTO pf_foyer_settings (id, currency, zone_scolaire) VALUES (1, '€', 'C');
        ";
        
        $fam_pdo->exec($sql);
        echo "<span style='color:green'>✅ OK</span><br>";
        
    } catch (\PDOException $e) {
        echo "<span style='color:red'>❌ Erreur : " . $e->getMessage() . "</span><br>";
    }
}

echo "<h2>🎉 Migration terminée ! Tu peux supprimer ce fichier.</h2>";
?>