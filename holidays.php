<?php
// holidays.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; // Toujours s'assurer de l'accès à tr()

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. On récupère la vue demandée (par défaut 'list')
$tab = $_GET['tab'] ?? 'list';

// 2. Configuration des variables pour le header (Titres traduits)
$pageTitle  = ($tab === 'holiday_detail') 
    ? tr('hdl_title_detail') 
    : tr('hdl_title_list');
    
$activePage = "holidays";
$mainClass  = "pf-holidays";
$pageCss    = "/modules/holidays/holidays.css";

require __DIR__ . '/header.php';

$stmtVehicles = $pdo->query("SELECT id, name FROM pf_vehicles ORDER BY name ASC");
$garageVehicles = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

// 3. ROUTEUR DU MODULE VACANCES
if ($tab === 'holiday_detail' && isset($_GET['id'])) {
    // Si on demande le détail ET qu'un ID est fourni
    require __DIR__ . '/modules/holidays/views/detail.php';
} else {
    // Vue par défaut : la liste des cartes
    require __DIR__ . '/modules/holidays/views/list.php';
}

// 4. Inclusion des librairies globales du module (Flatpickr)
?>
<!-- Flatpickr (Vanilla JS Date Range) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css" id="flatpickr-dark-theme" disabled>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/cat.js"></script>

<?php
// On ajoute un paramètre de version (time) pour forcer le navigateur à recharger tes récentes modifications dans holidays.js
echo '<script src="/modules/holidays/holidays.js?v=' . time() . '"></script>';

require __DIR__ . '/footer.php';