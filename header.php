<?php
// header.php
// $pageTitle et $activePage peuvent être définis avant l'include dans chaque page.
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
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="pf-header">
    <div class="pf-container pf-header-content">
      <div class="pf-logo">PachaFamily</div>
      <nav class="pf-nav">
        <a href="/index.php"
           class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>">Accueil</a>
        <a href="/family-calendar.php"
           class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>">Family calendar</a>
      </nav>
    </div>
  </header>

  <main class="pf-container pf-main">
