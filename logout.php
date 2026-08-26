<?php
session_start();

// --- NOUVEAU : Révocation du "Se souvenir de moi" ---
if (isset($_SESSION['user']['id'])) {
    require_once __DIR__ . '/includes/meta_db.php';
    // On invalide le token en base de données
    $stmt = $meta_pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
}

// On détruit le cookie côté navigateur
if (isset($_COOKIE['hh_remember'])) {
    setcookie('hh_remember', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}
// ----------------------------------------------------

// On vide toutes les variables de session
$_SESSION = [];

// On détruit la session
session_destroy();

// Optionnel : supprimer le cookie de session (plus propre)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// On renvoie vers la page de login
header('Location: /login.php');
exit;