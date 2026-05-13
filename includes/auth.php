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
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }

        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $redirectParam = urlencode($currentUrl);
        $target = ($loginPage ?? '/login.php') . '?redirect=' . $redirectParam;

        header('Location: ' . $target);
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
