<?php
if (!isset($pageTitle)) {
    $pageTitle = "PachaFamily";
}
if (!isset($activePage)) {
    $activePage = "";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="/pachafamily/assets/css/style.css">
</head>
<body>
  <header class="pf-header">
    <div class="pf-container pf-header-content">
      <div class="pf-logo">PachaFamily</div>
      <nav class="pf-nav">
        <a href="/pachafamily/index.php"
           class="pf-nav-link <?php echo $activePage === 'home' ? 'pf-nav-link--active' : ''; ?>">Accueil</a>
        <a href="/pachafamily/family-calendar.php"
           class="pf-nav-link <?php echo $activePage === 'family-calendar' ? 'pf-nav-link--active' : ''; ?>">Family calendar</a>
      </nav>
    </div>
  </header>

  <main class="pf-container pf-main">
