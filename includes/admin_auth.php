<?php
require_once __DIR__ . '/auth.php';
require_login();

if (empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    die('<h1>403 — Accès réservé aux administrateurs</h1><a href="/">Retour</a>');
}
