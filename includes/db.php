<?php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: 'househub-db';
$user = getenv('DB_USER') ?: 'househub';
$pass = getenv('DB_PASS') ?: 'changeme';
$db   = $_SESSION['family_db'] ?? null;

if (!$db) {
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    if (!in_array($current, ['login.php', 'register.php'])) {
        header('Location: /login.php');
        exit;
    }
    return;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
            PDO::ATTR_TIMEOUT            => 30,
        ]
    );
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
} catch (\PDOException $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    die(json_encode(['ok' => false, 'error' => 'Erreur BDD : ' . $e->getMessage()]));
}
?>