<?php
// holidays.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. On récupère la vue demandée (par défaut 'list')
$tab = $_GET['tab'] ?? 'list';

// 2. Configuration des variables pour le header
$pageTitle  = ($tab === 'holiday_detail') ? "PachaFamily - Détail du voyage" : "PachaFamily - Mes Vacances";
$activePage = "holidays";
$bodyClass  = "pf-holidays";
$pageCss    = "/modules/holidays/holidays.css";

require __DIR__ . '/header.php';

// 3. ROUTEUR DU MODULE VACANCES
if ($tab === 'holiday_detail' && isset($_GET['id'])) {
    // Si on demande le détail ET qu'un ID est fourni
    require __DIR__ . '/modules/holidays/views/detail.php';
} else {
    // Vue par défaut : la liste des cartes
    require __DIR__ . '/modules/holidays/views/list.php';
}

// 4. Inclusion du JS global du module (s'applique aux deux vues)
echo '<script src="/modules/holidays/holidays.js"></script>';

require __DIR__ . '/footer.php';