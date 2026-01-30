<?php
// Protection de la page : nécessite d'être connecté
require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Configuration de la page
$pageTitle  = "PachaFamily - Accueil";
$activePage = "home";
$bodyClass  = "pf-home"; // Important pour l'image de fond définie dans home.css
$pageCss    = "/modules/home/home.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
  
  <div class="pf-hero">
    <h1>Bienvenue sur PachaFamily</h1>
    <p>Centre de contrôle de l'organisation familiale.</p>

    <?php if (isset($_SESSION['user'])): ?>
      <div class="pf-user-info" style="margin-top: 12px; font-weight: 500;">
        Connecté en tant que 
        <strong><?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <section class="pf-section">
    <h2 style="color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.6);">Modules</h2>

    <div class="pf-modules-grid">
      
      <a href="/family-calendar.php" class="pf-module-card">
        <div class="pf-card-icon">📅</div>
        <h3 class="pf-card-title">Family calendar</h3>
        <div class="pf-card-desc">
          Gérez les congés, les modes de garde et l'organisation hebdomadaire.
        </div>
        <span class="pf-card-cta">Ouvrir</span>
      </a>

      <a href="/holidays.php" class="pf-module-card">
        <div class="pf-card-icon">🏖️</div>
        <h3 class="pf-card-title">Vacances</h3>
        <div class="pf-card-desc">
          Planifiez vos prochaines escapades, destinations et idées de voyage.
        </div>
        <span class="pf-card-cta">Explorer</span>
      </a>

      <a href="/gift-list.php" class="pf-module-card">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title">Cadeaux</h3>
        <div class="pf-card-desc">
          Planifiez les cadeaux pour chaque enfant, pour Noël, les anniversaires et autres.
        </div>
        <span class="pf-card-cta">Voir les listes</span>
      </a>

      <div class="pf-module-card pf-card--disabled">
        <div class="pf-card-icon">💰</div>
        <h3 class="pf-card-title">Budget</h3>
        <div class="pf-card-desc">
          Bientôt disponible : suivi des dépenses, catégories et budget mensuel.
        </div>
        <span class="pf-card-cta">À venir</span>
      </div>

    </div>
  </section>
</div>

<?php
require __DIR__ . '/footer.php';
?>