<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_login('/login.php');

// Gestion de l'onglet actif (par défaut 'recap')
$tab = $_GET['tab'] ?? 'recap';

$pageTitle  = "PachaFamily - Budget";
$activePage = "budget";
$pageCss    = "/modules/budget/budget.css";
$pageJs     = "/modules/budget/budget.js"; 

require __DIR__ . '/header.php';
?>

<div class="pf-container">
    <div class="pf-hero" style="text-align: center; margin-bottom: 30px;">
    <h1 style="margin-bottom: 20px;">Gestion du Budget</h1>
    
    <nav class="budget-tabs-container">
        <a href="?tab=recap" class="tab-item <?= $tab == 'recap' ? 'active' : '' ?>">
            <span class="tab-icon">📊</span> 
            <span>Récapitulatif</span>
        </a>
        
        <a href="?tab=suivi" class="tab-item <?= $tab == 'suivi' ? 'active' : '' ?>">
            <span class="tab-icon">🗓️</span> 
            <span>Suivi Mensuel</span>
        </a>
        
        <a href="?tab=epargne" class="tab-item <?= $tab == 'epargne' ? 'active' : '' ?>">
            <span class="tab-icon">🐷</span> 
            <span>Épargne</span>
        </a>
    </nav>
</div>

    <section class="pf-section">
    <?php 
    // On définit le chemin relatif par rapport à la racine du projet
    $viewPath = __DIR__ . "/modules/budget/views/" . $tab . ".php";
    
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        // Petit debug pour voir où PHP cherche exactement le fichier
        echo "<p>Erreur : Le fichier est introuvable dans " . htmlspecialchars($viewPath) . "</p>";
        echo "<p>Onglet en cours de développement...</p>";
    }
    ?>
</section>
</div>
<script src="/modules/budget/budget.js"></script>
<?php require __DIR__ . '/footer.php'; ?>