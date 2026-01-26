<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie qu'un utilisateur est connecté.
 * Si non, le redirige vers la page de login avec ?redirect=URL_DEMANDEE
 *
 * @param string|null $loginPage URL de la page de login (par défaut /login.php)
 */
function require_login(?string $loginPage = '/login.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        // URL de la page courante (ex: /gift-list.php?foo=bar)
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';

        // On redirige vers la page de login en ajoutant ?redirect=...
        $redirectParam = urlencode($currentUrl);
        $target = ($loginPage ?? '/login.php') . '?redirect=' . $redirectParam;

        header('Location: ' . $target);
        exit;
    }
}
