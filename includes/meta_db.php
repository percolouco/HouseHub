<?php
// includes/meta_db.php
// Connexion à la base meta (gestion des familles et utilisateurs)

$envHost = getenv('DB_HOST');

// Détection stricte : Docker injecte toujours 'househub-db' via le docker-compose
if ($envHost === 'househub-db') {
    // Environnement Docker (Ton collègue ou la Prod)
    $meta_host = 'househub-db';
    $meta_user = getenv('DB_USER') ?: 'househub';
    $meta_pass = getenv('DB_PASS') ?: 'changeme';
} else {
    // Environnement Local XAMPP (Toi)
    $meta_host = '127.0.0.1'; // Plus stable que 'localhost' sous XAMPP
    $meta_user = 'root';
    $meta_pass = '';
}

$meta_db = 'househub_meta';

try {
    $meta_pdo = new PDO(
        "mysql:host=$meta_host;dbname=$meta_db;charset=utf8mb4",
        $meta_user,
        $meta_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
        ]
    );
} catch (\PDOException $e) {
    die(json_encode(['error' => 'Meta DB unavailable: ' . $e->getMessage()]));
}
?>