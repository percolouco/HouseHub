<?php
// migrate_leaves.php
require_once __DIR__ . '/includes/meta_db.php';

// Détection stricte de l'environnement (Docker vs XAMPP)
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

try {
    $stmt = $meta_pdo->query("SELECT db_name FROM families");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $fam) {
        $db = $fam['db_name'];
        echo "Migration sur la base : $db...<br>";
        
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 1. pf_person_leave_meta : Règles de report individuelles
        $pdo->exec("ALTER TABLE pf_person_leave_meta ADD COLUMN IF NOT EXISTS carry_over_max_days DECIMAL(5,2) DEFAULT 0.00 AFTER allowance");
        $pdo->exec("ALTER TABLE pf_person_leave_meta ADD COLUMN IF NOT EXISTS carry_over_deadline_month INT(11) DEFAULT 12 AFTER carry_over_max_days");

        // 2. pf_leave_snapshots : Ajout du solde de report périssable
        $pdo->exec("ALTER TABLE pf_leave_snapshots ADD COLUMN IF NOT EXISTS carry_over_balance DECIMAL(6,2) DEFAULT 0.00 AFTER remaining_balance");

        // 3. pf_leave_types : Nettoyage de l'ancien booléen global s'il existe
        try {
            $pdo->exec("ALTER TABLE pf_leave_types DROP COLUMN allow_carry_over");
        } catch (Exception $e) {
            // La colonne n'existait pas ou est déjà supprimée
        }

        echo "✅ Migration réussie pour $db.<br><br>";
    }
    
    echo "🎉 <strong>Toutes les bases ont été mises à jour !</strong> Vous pouvez supprimer ce fichier.";

} catch (PDOException $e) {
    die("❌ Erreur fatale : " . $e->getMessage());
}
?>