<?php
session_start();

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

// On renvoie vers la page de login (ou index si tu préfères)
header('Location: /login.php');
exit;
