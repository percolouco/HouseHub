<?php
require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle  = "PachaFamily - Idées de vacances";
$activePage = "holidays";
$bodyClass  = "pf-holidays";
$pageCss    = "/modules/holidays/holidays.css";

require __DIR__ . '/header.php';

require __DIR__ . '/modules/holidays/index.php';

require __DIR__ . '/footer.php';
