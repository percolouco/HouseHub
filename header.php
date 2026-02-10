<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = $pageTitle ?? "PachaFamily";
$activePage = $activePage ?? "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/global.css">
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss) ?>">
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <header class="pf-header">
    <a href="/index.php" class="pf-logo">PachaFamily</a>

    <nav class="pf-nav">
      <a href="/index.php" class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>">Accueil</a>
      <a href="/family-calendar.php" class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>">Calendrier</a>
      <a href="/budget.php" class="pf-nav-link <?= $activePage === 'budget' ? 'pf-nav-link--active' : '' ?>">Budget</a>
      <a href="/holidays.php" class="pf-nav-link <?= $activePage === 'holidays' ? 'pf-nav-link--active' : '' ?>">Vacances</a>
      <a href="/gift-list.php" class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : '' ?>">Cadeaux</a>
    </nav>

    <div class="pf-header-actions">
      <?php if (isset($_SESSION['user'])): ?>
        <div class="pf-user-badge">
          <?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?>
        </div>
        <a href="/logout.php" class="pf-logout-btn">Déconnexion</a>
      <?php else: ?>
        <a href="/login.php" class="pf-nav-link">Connexion</a>
      <?php endif; ?>
    </div>

    <button class="pf-burger-btn" aria-label="Menu">☰</button>
  </header>

  <div class="pf-mobile-menu">
    <a href="/index.php" class="pf-mobile-nav-link">Accueil</a>
    <a href="/family-calendar.php" class="pf-mobile-nav-link">Calendrier</a>
    <a href="/budget.php" class="pf-mobile-nav-link">Budget</a>
    <a href="/holidays.php" class="pf-mobile-nav-link">Vacances</a>
    <a href="/gift-list.php" class="pf-mobile-nav-link">Cadeaux</a>
    <?php if (isset($_SESSION['user'])): ?>
      <a href="/logout.php" class="pf-mobile-nav-link pf-mobile-logout">Se déconnecter</a>
    <?php else: ?>
      <a href="/login.php" class="pf-mobile-nav-link">Se connecter</a>
    <?php endif; ?>
  </div>

  <main class="pf-main">

  <script>
    // Script simple pour le menu mobile
    const burgerBtn = document.querySelector('.pf-burger-btn');
    const mobileMenu = document.querySelector('.pf-mobile-menu');
    
    if(burgerBtn) {
      burgerBtn.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.toggle('is-open');
        burgerBtn.textContent = isOpen ? '✕' : '☰';
      });
    }
  </script>