<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Inclusion du moteur de traduction
require_once __DIR__ . '/includes/i18n.php';

/**
 * Génère une URL avec le paramètre de langue mis à jour
 */
function getLangUrl($newLang) {
    $params = $_GET;
    $params['lang'] = $newLang;
    return '?' . http_build_query($params);
}

$pageTitle = $pageTitle ?? "PachaFamily";
$activePage = $activePage ?? "";
$currentLang = $_SESSION['app_lang'] ?? 'fr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/global.css">
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss) ?>">
  <?php endif; ?>
  <style>
    /* Patch CSS pour la zone droite de l'en-tête (Desktop / Mobile) */
    .pf-header-right { display: flex; align-items: center; gap: 15px; }
    @media (max-width: 768px) {
        .pf-desktop-actions { display: none !important; }
    }
  </style>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <header class="pf-header">
    <a href="/index.php" class="pf-logo">PachaFamily</a>

    <?php if (isset($_SESSION['user'])): ?>
    <nav class="pf-nav">
      <a href="/index.php" class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_home') ?></a>
      <a href="/family-calendar.php" class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_calendar') ?></a>
      <a href="/budget.php" class="pf-nav-link <?= $activePage === 'budget' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_budget') ?></a>
      <a href="/holidays.php" class="pf-nav-link <?= $activePage === 'holidays' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_holidays') ?></a>
      <a href="/gift-list.php" class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_gifts') ?></a>
    </nav>
    <?php endif; ?>

    <div class="pf-header-right">
      
      <div style="display: flex; align-items: center; gap: 8px;">
          <a href="<?= getLangUrl('fr') ?>" style="text-decoration:none; font-weight:bold; font-size:1rem; color: <?= $currentLang === 'fr' ? '#2563eb' : '#94a3b8' ?>;" title="Français">FR</a>
          <span style="color: #cbd5e1;">|</span>
          <a href="<?= getLangUrl('ca') ?>" style="text-decoration:none; font-weight:bold; font-size:1rem; color: <?= $currentLang === 'ca' ? '#f59e0b' : '#94a3b8' ?>;" title="Català">CA</a>
      </div>

      <?php if (isset($_SESSION['user'])): ?>
        <div class="pf-desktop-actions" style="display: flex; align-items: center; gap: 10px; border-left: 1px solid #cbd5e1; padding-left: 15px;">
          <div class="pf-user-badge">
            <?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?>
          </div>
          <a href="/logout.php" class="pf-logout-btn"><?= tr('btn_logout') ?></a>
        </div>
        
        <button class="pf-burger-btn" aria-label="<?= tr('aria_menu') ?>" style="margin-left: 5px;">☰</button>
        
      <?php else: ?>
        <a href="/login.php" class="pf-nav-link" style="border-left: 1px solid #cbd5e1; padding-left: 15px; margin-left: 5px;"><?= tr('btn_login') ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (isset($_SESSION['user'])): ?>
  <div class="pf-mobile-menu">
    <a href="/index.php" class="pf-mobile-nav-link"><?= tr('menu_home') ?></a>
    <a href="/family-calendar.php" class="pf-mobile-nav-link"><?= tr('menu_calendar') ?></a>
    <a href="/budget.php" class="pf-mobile-nav-link"><?= tr('menu_budget') ?></a>
    <a href="/holidays.php" class="pf-mobile-nav-link"><?= tr('menu_holidays') ?></a>
    <a href="/gift-list.php" class="pf-mobile-nav-link"><?= tr('menu_gifts') ?></a>
    <hr style="width: 80%; border: 0; border-top: 1px solid #eee; margin: 10px 0;">
    <a href="/logout.php" class="pf-mobile-nav-link pf-mobile-logout"><?= tr('btn_logout') ?></a>
  </div>
  <?php endif; ?>

  <main class="pf-main">

  <script>
    /**
     * Pont d'Internationalisation et Configuration
     */
    window.I18N = <?php echo json_encode($current_translations_array ?? []); ?>;

    // ✅ Injection sécurisée (évite le crash si config.php n'est pas chargé)
    window.CONFIG = {
        ID_ALEX: <?php echo defined('ID_ALEX') ? ID_ALEX : 2; ?>,
        ID_LAIA: <?php echo defined('ID_LAIA') ? ID_LAIA : 3; ?>,
        CURRENCY: '<?php echo defined('CURRENCY') ? CURRENCY : "€"; ?>'
    };    
    
    function tr(key) {
        return window.I18N[key] || key;
    }

    // Gestion du menu mobile
    <?php if (isset($_SESSION['user'])): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const burgerBtn = document.querySelector('.pf-burger-btn');
        const mobileMenu = document.querySelector('.pf-mobile-menu');
        
        if(burgerBtn && mobileMenu) {
            burgerBtn.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.toggle('is-open');
                burgerBtn.textContent = isOpen ? '✕' : '☰';
                document.body.classList.toggle('no-scroll', isOpen);
            });
        }
    });
    <?php endif; ?>

    /**
     * pachaFetch : Utilitaire de requête robuste
     */
    async function pachaFetch(url, options = {}) {
        const finalUrl = url.startsWith('/') ? url.substring(1) : url;

        options.credentials = 'same-origin'; 
        options.headers = {
            ...options.headers,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        try {
            const response = await fetch(finalUrl, options);
            const rawText = await response.text();
            
            try {
                return JSON.parse(rawText);
            } catch (jsonErr) {
                console.error("Réponse corrompue (HTML reçu au lieu de JSON) :", rawText);
                throw new Error("Erreur serveur : format JSON invalide.");
            }
        } catch (err) {
            console.error(`Erreur pachaFetch [${finalUrl}] :`, err);
            throw err;
        }
    }
</script>