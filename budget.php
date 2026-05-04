<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_login('/login.php');

// Gestion de l'onglet actif (par défaut 'suivi')
$tab = $_GET['tab'] ?? 'suivi';

$pageTitle  = tr('budget_page_title');
$activePage = "budget";
$pageCss    = "/modules/budget/budget.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
    <div class="pf-hero" style="text-align: center; margin-bottom: 30px;">
        <h1 style="margin-bottom: 20px;"><?= tr('budget_main_header') ?></h1>
        
        <nav class="budget-tabs-container">
            <a href="?tab=suivi" class="tab-item <?= $tab == 'suivi' ? 'active' : '' ?>">
                <span class="tab-icon">🗓️</span> 
                <span><?= tr('budget_tab_tracking') ?></span>
            </a>  
                      
            <a href="?tab=budget_prev" class="tab-item <?= $tab == 'budget_prev' ? 'active' : '' ?>">
                <span class="tab-icon">🎯</span> 
                <span><?= tr('budget_tab_prev') ?></span>
            </a>  

            <a href="?tab=epargne" class="tab-item <?= $tab == 'epargne' ? 'active' : '' ?>">
                <span class="tab-icon">🐷</span> 
                <span><?= tr('budget_tab_savings') ?></span>
            </a>

            <a href="?tab=recap" class="tab-item <?= $tab == 'recap' ? 'active' : '' ?>">
                <span class="tab-icon">📊</span> 
                <span><?= tr('budget_tab_recap') ?></span>
            </a>
        </nav>
    </div>

    <section class="pf-section">
    <?php 
    // ROUTAGE DYNAMIQUE
    $allowedTabs = ['recap', 'suivi', 'epargne', 'budget_prev'];
    
    if (in_array($tab, $allowedTabs)) {
        $viewPath = __DIR__ . "/modules/budget/views/" . $tab . ".php";
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<div style='text-align:center; padding:50px; color:#ef4444;'>";
            echo "<h3>" . tr('budget_err_file_not_found') . "</h3>";
            echo "<p>" . sprintf(tr('budget_err_file_detail'), htmlspecialchars($tab)) . "</p>";
            echo "</div>";
        }
    } else {
        echo "<p>" . tr('budget_err_not_authorized') . "</p>";
    }
    ?>
    </section>
</div>
<script>
// --- AUTO-SCROLL DES ONGLETS MOBILES ---
document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.querySelector('.budget-tabs-container');
    const activeTab = document.querySelector('.tab-item.active');
    
    // Si on a bien un conteneur d'onglets ET un onglet actif
    if (tabsContainer && activeTab) {
        // On vérifie si le conteneur a un scroll horizontal possible
        if (tabsContainer.scrollWidth > tabsContainer.clientWidth) {
            // On calcule la position pour centrer l'onglet à l'écran
            const scrollPos = activeTab.offsetLeft - (tabsContainer.offsetWidth / 2) + (activeTab.offsetWidth / 2);
            
            // On fait glisser le menu doucement
            tabsContainer.scrollTo({
                left: scrollPos,
                behavior: 'smooth'
            });
        }
    }
});
</script>
<?php require __DIR__ . '/footer.php'; ?>