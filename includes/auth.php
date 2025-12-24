<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(string $redirectTarget = null): void
{
    if (!isset($_SESSION['user'])) {
        if ($redirectTarget === null) {
            $redirectTarget = $_SERVER['REQUEST_URI'] ?? '/index.php';
        }

        $redirect = urlencode($redirectTarget);
        header("Location: /login.php?redirect=$redirect");
        exit;
    }
}
