<?php
// Connexion à la base meta (gestion des familles et utilisateurs)
$meta_host = getenv('DB_HOST') ?: 'househub-db';
$meta_db   = 'househub_meta';
$meta_user = getenv('DB_USER') ?: 'househub';
$meta_pass = getenv('DB_PASS') ?: 'changeme';

try {
    $meta_pdo = new PDO(
        "mysql:host=$meta_host;dbname=$meta_db;charset=utf8mb4",
        $meta_user,
        $meta_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (\PDOException $e) {
    die(json_encode(['error' => 'Meta DB unavailable: ' . $e->getMessage()]));
}
