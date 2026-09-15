<?php
// migrate_meals.php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/meta_db.php';
require_login();

if (empty($_SESSION['user']['is_admin'])) {
    die("Accès refusé. Droits administrateur requis.");
}

try {
    $stmt = $meta_pdo->query("SELECT db_name FROM families");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $fam) {
        $dbName = $fam['db_name'];
        echo "Migration de la base : $dbName ... <br>";

        // --- DÉBUT DU BLOC MODIFIÉ ---
        $envHost = getenv('DB_HOST');
        if ($envHost === 'househub-db') {
            $host = 'househub-db';
            $user = getenv('DB_USER') ?: 'househub';
            $pass = getenv('DB_PASS') ?: 'changeme';
        } else {
            $host = '127.0.0.1';
            $user = 'root';
            $pass = '';
        }

        $familyPdo = new PDO(
            "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // --- FIN DU BLOC MODIFIÉ ---

        $sql = "
        CREATE TABLE IF NOT EXISTS pf_meals_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_date DATE NOT NULL,
            service ENUM('lunch', 'dinner') NOT NULL,
            person_id INT NOT NULL,
            meal_name VARCHAR(500) DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_meal_plan (plan_date, service, person_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $familyPdo->exec($sql);
        echo "✅ Table pf_meals_plan créée pour $dbName.<br>";
    }
    echo "<br>🎉 Migration terminée avec succès.";

} catch (Exception $e) {
    die("❌ Erreur lors de la migration : " . $e->getMessage());
}