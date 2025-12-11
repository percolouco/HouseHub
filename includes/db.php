<?php

// Activer erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Détection de l'environnement
$isLocal = (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false ||
           strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
           strpos($_SERVER['HTTP_HOST'] ?? '', '::1') !== false);

if ($isLocal) {
    // Configuration XAMPP local
    $host = 'localhost';
    $db   = 'percolo314';        // Même nom que sur OVH pour simplifier
    $user = 'root';
    $pass = '';                  // Généralement vide sur XAMPP
} else {
    // Configuration serveur OVH
    $host = 'percolo314.mysql.db';
    $db   = 'percolo314';
    $user = 'percolo314';
    $pass = 'Wxcvbn99';
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
    
    // Debug optionnel (à commenter en production)
    // $env = $isLocal ? 'LOCAL' : 'SERVEUR';
    // echo "<!-- Connecté en $env sur $host/$db -->";
    
} catch (\PDOException $e) {
    $environment = $isLocal ? 'local (XAMPP)' : 'serveur (OVH)';
    die("Erreur de connexion ($environment) : " . $e->getMessage());
}
?>