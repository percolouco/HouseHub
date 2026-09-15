<?php
// food.php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; 
require_login('/login.php');

// Gestion de l'onglet actif
$tab = $_GET['tab'] ?? 'meals';

$pageTitle  = tr('mod_food_name');
$activePage = "food";
$pageCss    = "/modules/food/food.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
    <div class="pf-hero budget-hero">
        <div class="budget-title-group">
            <h1>🍳 <?= tr('mod_food_name') ?></h1>
        </div>
        
        <nav class="budget-tabs-container">
            <a href="?tab=meals" class="tab-item <?= $tab == 'meals' ? 'active' : '' ?>">
                <span class="tab-icon">🍽️</span> 
                <span><?= tr('food_tab_meals') ?></span>
            </a>
            <a href="?tab=liste" class="tab-item <?= $tab == 'liste' ? 'active' : '' ?>">
                <span class="tab-icon">🛒</span> 
                <span><?= tr('food_tab_list') ?></span>
            </a>
        </nav>
    </div>

    <section class="pf-section">
        <?php 
        $allowedTabs = ['meals', 'liste'];
        if (in_array($tab, $allowedTabs)) {
            $viewPath = __DIR__ . "/modules/food/views/{$tab}.php";
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<p>Vue introuvable : " . htmlspecialchars($tab) . "</p>";
            }
        } else {
            echo "<p>" . tr('error_occured') . "</p>";
        }
        ?>
    </section>
</div>

<?php require __DIR__ . '/footer.php'; ?>