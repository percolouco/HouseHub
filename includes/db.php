<?php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$host    = getenv('DB_HOST') ?: 'househub-db';
$db      = getenv('DB_NAME') ?: 'househub';
$user    = getenv('DB_USER') ?: 'househub';
$pass    = getenv('DB_PASS') ?: 'changeme';
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
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
} catch (\PDOException $e) {
    die("Erreur de connexion BDD : " . $e->getMessage());
}
?>