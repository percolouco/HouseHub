<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Inclusion du moteur de traduction
require_once __DIR__ . '/includes/i18n.php';

// Petite fonction pour changer de langue sans perdre les paramètres de l'URL (ex: id=5)
function getLangUrl($newLang) {
    $params = $_GET;
    $params['lang'] = $newLang;
    return '?' . http_build_query($params);
}

$pageTitle = $pageTitle ?? "PachaFamily";
$activePage = $activePage ?? "";
$currentLang = $_SESSION['app_lang'] ?? 'fr'; // Récupération de la langue active
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/global.css">
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss) ?>">
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <header class="pf-header">
    <a href="/index.php" class="pf-logo">PachaFamily</a>

    <nav class="pf-nav">
      <a href="/index.php" class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_home') ?></a>
      <a href="/family-calendar.php" class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_calendar') ?></a>
      <a href="/budget.php" class="pf-nav-link <?= $activePage === 'budget' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_budget') ?></a>
      <a href="/holidays.php" class="pf-nav-link <?= $activePage === 'holidays' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_holidays') ?></a>
      <a href="/gift-list.php" class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_gifts') ?></a>
    </nav>

    <div class="pf-header-actions">
      <div style="display: flex; align-items: center; gap: 8px; margin-right: 15px; border-right: 1px solid #cbd5e1; padding-right: 15px;">
          <a href="<?= getLangUrl('fr') ?>" style="text-decoration:none; font-weight:bold; color: <?= $currentLang === 'fr' ? '#2563eb' : '#94a3b8' ?>;" title="Français">FR</a>
          <span style="color: #cbd5e1;">|</span>
          <a href="<?= getLangUrl('ca') ?>" style="text-decoration:none; font-weight:bold; color: <?= $currentLang === 'ca' ? '#f59e0b' : '#94a3b8' ?>;" title="Català">CA</a>
      </div>

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
    <a href="/index.php" class="pf-mobile-nav-link"><?= tr('menu_home') ?></a>
    <a href="/family-calendar.php" class="pf-mobile-nav-link"><?= tr('menu_calendar') ?></a>
    <a href="/budget.php" class="pf-mobile-nav-link"><?= tr('menu_budget') ?></a>
    <a href="/holidays.php" class="pf-mobile-nav-link"><?= tr('menu_holidays') ?></a>
    <a href="/gift-list.php" class="pf-mobile-nav-link"><?= tr('menu_gifts') ?></a>
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