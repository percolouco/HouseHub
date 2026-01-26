<?php
// Protection de la page : nécessite d'être connecté
require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Configuration de la page
$pageTitle  = "PachaFamily - Accueil";
$activePage = "home";
$bodyClass  = "pf-home";
$pageCss    = "/modules/home/home.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
  <h1>Bienvenue sur PachaFamily</h1>
  <p>Centre de contrôle de l'organisation familiale.</p>

  <?php if (isset($_SESSION['user'])): ?>
    <div class="pf-user-info">
      Connecté en tant que 
      <strong><?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?></strong>
      – <a href="/logout.php">Se déconnecter</a>
    </div>
  <?php endif; ?>

  <section class="pf-section">
    <h2>Modules</h2>

    <div class="pf-flex pf-flex--wrap pf-gap-lg pf-modules-cards">
      <!-- Card : Family calendar -->
      <a href="/family-calendar.php" class="pf-card pf-card--module">
        <div class="pf-card-icon">📅</div>
        <h3 class="pf-card-title">Family calendar</h3>
        <div class="pf-card-body">
          Gérez les congés, vacances et la garde sur un calendrier partagé
          pour toute la famille.
        </div>
        <span class="pf-card-cta">Accéder au module</span>
      </a>

      <!-- Module : holidays -->
      <a href="/holidays.php" class="pf-card pf-card--module">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title">Holidays</h3>
        <div class="pf-card-body">
          Plannification des vacances.
        </div>
        <span class="pf-card-cta">Accéder au module</span>
      </a>

            <!-- Module : gift list -->
      <a href="/gift-list.php" class="pf-card pf-card--module">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title">Gift list</h3>
        <div class="pf-card-body">
          Planifica els regals de cada nen, per al Tió, el Nadal i els Reis.
        </div>
        <span class="pf-card-cta">Accedir al mòdul</span>
      </a>

      <!-- Exemple de futur module -->
      <div class="pf-card pf-card--module pf-card--disabled">
        <div class="pf-card-icon">💰</div>
        <h3 class="pf-card-title">Budget familial</h3>
        <div class="pf-card-body">
          Bientôt disponible : suivi des dépenses, catégories et budget mensuel.
        </div>
        <span class="pf-card-cta">À venir</span>
      </div>

    </div>
  </section>
</div>

<?php
require __DIR__ . '/footer.php';
