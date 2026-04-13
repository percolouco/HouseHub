<?php
// Protection de la page : nécessite d'être connecté
require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Inclusion i18n (déjà fait dans header, mais par sécurité si besoin de tr() avant)
require_once __DIR__ . '/includes/i18n.php';

// Configuration de la page
$pageTitle  = tr('home_title');
$activePage = "home";
$bodyClass  = "pf-home"; // Important pour l'image de fond définie dans home.css
$pageCss    = "/modules/home/home.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
  
  <div class="pf-hero">
    <h1><?= tr('home_welcome') ?></h1>
    <p><?= tr('home_subtitle') ?></p>

    <?php if (isset($_SESSION['user'])): ?>
      <div class="pf-user-info" style="margin-top: 12px; font-weight: 500;">
        <?= tr('home_logged_as') ?> 
        <strong><?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <section class="pf-section">
    <h2 style="color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.6);"><?= tr('home_modules_title') ?></h2>

    <div class="pf-modules-grid">
      
      <a href="/family-calendar.php" class="pf-module-card">
        <div class="pf-card-icon">📅</div>
        <h3 class="pf-card-title"><?= tr('mod_calendar_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_calendar_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_open') ?></span>
      </a>

      <a href="/holidays.php" class="pf-module-card">
        <div class="pf-card-icon">🏖️</div>
        <h3 class="pf-card-title"><?= tr('mod_holidays_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_holidays_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_explore') ?></span>
      </a>

      <a href="/gift-list.php" class="pf-module-card">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title"><?= tr('mod_gifts_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_gifts_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_view_lists') ?></span>
      </a>

      <a href="/budget.php" class="pf-module-card">
        <div class="pf-card-icon">💰</div>
        <h3 class="pf-card-title"><?= tr('mod_budget_name') ?></h3>
        <div class="pf-card-desc">
            <?= tr('mod_budget_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_manage') ?></span>
      </a>

    </div>
  </section>
</div>

<?php
require __DIR__ . '/footer.php';
?>