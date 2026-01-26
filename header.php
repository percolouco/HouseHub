<?php
// S'assurer que la session est démarrée si on veut afficher l'utilisateur
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) {
    $pageTitle = "PachaFamily";
}
if (!isset($activePage)) {
    $activePage = "";
}
?>
<!DOCTYPE html>
<html lang="fr" class="<?= htmlspecialchars($bodyClass ?? '') ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

  <!-- CSS globale -->
  <link rel="stylesheet" href="/global.css">

  <!-- CSS spécifique à la page (optionnel) -->
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
  <div class="pf-page"><!-- WRAPPER OUVERT ICI -->

    <header class="pf-header">
      <div class="pf-container pf-header-content">
        <div class="pf-logo">PachaFamily</div>

        <nav class="pf-nav">
          <a href="/index.php"
             class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : ''; ?>">Accueil</a>

          <a href="/family-calendar.php"
             class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : ''; ?>">Family calendar</a>
             
          <a href="/gift-list.php"
             class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : ''; ?>">gift list</a>

          <?php if (isset($_SESSION['user'])): ?>
            <span class="pf-nav-user">
              Bonjour <?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <a href="/logout.php" class="pf-nav-link pf-nav-link--secondary">Se déconnecter</a>
          <?php else: ?>
            <a href="/login.php" class="pf-nav-link pf-nav-link--secondary">Se connecter</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>

    <main class="pf-container pf-main">
