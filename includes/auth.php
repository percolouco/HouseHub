<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(?string $redirectTarget = '/index.php'): void
{
    // Exemple d’implémentation typique
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        // Si null → on choisit un fallback (par exemple index)
        $target = $redirectTarget ?? '/index.php';
        header('Location: ' . $target);
        exit;
    }
}

