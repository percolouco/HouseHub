<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_login('/login.php');

// Gestion de l'onglet actif (par défaut 'recap')
$tab = $_GET['tab'] ?? 'suivi';

$pageTitle  = "PachaFamily - Budget";
$activePage = "budget";
$pageCss    = "/modules/budget/budget.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
    <div class="pf-hero" style="text-align: center; margin-bottom: 30px;">
        <h1 style="margin-bottom: 20px;">Gestion du Budget</h1>
        
        <nav class="budget-tabs-container">
            <a href="?tab=suivi" class="tab-item <?= $tab == 'suivi' ? 'active' : '' ?>">
                <span class="tab-icon">🗓️</span> 
                <span>Suivi Mensuel</span>
            </a>  
                      
            <a href="?tab=budget_prev" class="tab-item <?= $tab == 'budget_prev' ? 'active' : '' ?>">
                <span class="tab-icon">🎯</span> 
                <span>Budget 2026</span>
            </a>  

            <a href="?tab=epargne" class="tab-item <?= $tab == 'epargne' ? 'active' : '' ?>">
                <span class="tab-icon">🐷</span> 
                <span>Épargne</span>
            </a>

            <a href="?tab=recap" class="tab-item <?= $tab == 'recap' ? 'active' : '' ?>">
                <span class="tab-icon">📊</span> 
                <span>Récapitulatif</span>
            </a>
        </nav>
    </div>

    <section class="pf-section">
    <?php 
    // ROUTAGE DYNAMIQUE
    // Sécurisation basique pour éviter d'inclure n'importe quoi
    $allowedTabs = ['recap', 'suivi', 'epargne', 'budget_prev'];
    
    if (in_array($tab, $allowedTabs)) {
        $viewPath = __DIR__ . "/modules/budget/views/" . $tab . ".php";
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<div style='text-align:center; padding:50px; color:#ef4444;'>";
            echo "<h3>Oups ! Fichier introuvable.</h3>";
            echo "<p>Vérifiez que le fichier <code>views/" . htmlspecialchars($tab) . ".php</code> existe bien.</p>";
            echo "</div>";
        }
    } else {
        echo "<p>Page non autorisée.</p>";
    }
    ?>
    </section>
</div>
<?php require __DIR__ . '/footer.php'; ?>