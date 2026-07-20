<?php
require_once __DIR__ . '/includes/meta_db.php';

// Récupération des identifiants de BDD depuis l'environnement (fallback par défaut)
$db_host = getenv('DB_HOST') ?: 'househub-db';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'changeme';

echo "<h1>🛠️ HouseHub - Correction AUTO_INCREMENT Voyages</h1>";

try {
    // On récupère toutes les familles actives
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $f) {
        $dbName = $f['db_name'];
        echo "<h3>Famille : {$f['name']} ($dbName)</h3><ul>";

        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$dbName;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 1. On vérifie si la clé primaire existe déjà pour éviter de faire planter le script
            $hasPrimaryKey = $pdo->query("SHOW KEYS FROM pf_holidays WHERE Key_name = 'PRIMARY'")->fetch();

            if (!$hasPrimaryKey) {
                $pdo->exec("ALTER TABLE pf_holidays ADD PRIMARY KEY (id)");
                echo "<li>✅ Clé primaire restaurée sur la colonne `id`.</li>";
            }

            // 2. On restaure la propriété AUTO_INCREMENT
            $pdo->exec("ALTER TABLE pf_holidays MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
            echo "<li>✅ Propriété AUTO_INCREMENT restaurée.</li>";

        } catch (\PDOException $e) {
            // On attrape l'erreur si la modif a déjà été faite
            echo "<li>ℹ️ Action ignorée ou table déjà à jour : " . $e->getMessage() . "</li>";
        }
        echo "</ul>";
    }
    echo "<h2>🚀 Correction terminée ! Tu peux réessayer de créer un voyage.</h2>";

} catch (Exception $e) {
    die("Erreur fatale de connexion à la Meta DB : " . $e->getMessage());
}
?>