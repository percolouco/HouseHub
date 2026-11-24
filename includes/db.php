<?php
// includes/db.php

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$envHost = getenv('DB_HOST');

// 1. Détection stricte de l'environnement
if ($envHost === 'househub-db') {
    // Docker
    $host = 'househub-db';
    $user = getenv('DB_USER') ?: 'househub';
    $pass = getenv('DB_PASS') ?: '';
} else {
    // XAMPP
    $host = '127.0.0.1'; // Plus stable que 'localhost' sous XAMPP
    $user = 'root';
    $pass = '';
}

// 2. Multi-tenant strict : cibler la DB de la famille courante
$db = 'househub_meta'; // Fallback par défaut
if (!empty($_SESSION['user']['family_id'])) {
    $db = 'househub_f' . (int)$_SESSION['user']['family_id'];
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
    PDO::ATTR_TIMEOUT            => 30,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Forcer la collation
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
    $pdo->exec("SET collation_database = utf8mb4_general_ci");
    $pdo->exec("SET collation_server = utf8mb4_general_ci");

    // Ordre d'affichage des personnes (réglable dans Paramètres) : colonne ajoutée si absente
    if ($db !== 'househub_meta') {
        try {
            if (!$pdo->query("SHOW COLUMNS FROM pf_people LIKE 'sort_order'")->fetch()) {
                $pdo->exec("ALTER TABLE pf_people ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
                $pdo->exec("UPDATE pf_people SET sort_order = id");
            }
        } catch (\Throwable $e) { /* table absente ou droits insuffisants */ }
        // Budget : mois concernés par une ligne (NULL = tous les mois ; ex. « 1,2,3,4,5,6,7,8,9,10 » pour la taxe foncière)
        try {
            if (!$pdo->query("SHOW COLUMNS FROM pf_budget_items LIKE 'active_months'")->fetch()) {
                $pdo->exec("ALTER TABLE pf_budget_items ADD COLUMN active_months VARCHAR(40) DEFAULT NULL");
            }
        } catch (\Throwable $e) { /* table absente ou droits insuffisants */ }
    }

} catch (\PDOException $e) {
    die("Erreur de connexion BDD (Host: $host, DB: $db) : " . $e->getMessage());
}
?>