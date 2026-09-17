<?php
// food.php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; 
require_login('/login.php');

// 1. Détection des vues actives
$enabledViews = $_SESSION['enabled_views'] ?? ['food_meals', 'food_liste'];
$allowedTabs = [];

if (in_array('food_meals', $enabledViews)) $allowedTabs[] = 'meals';
if (in_array('food_liste', $enabledViews)) $allowedTabs[] = 'liste';

// 2. Fallback intelligent de l'onglet actif
if (empty($allowedTabs)) {
    $tab = 'empty'; // L'utilisateur a désactivé les deux vues
} else {
    // Par défaut, on prend le premier onglet disponible (Meals, ou Liste si Meals est désactivé)
    $defaultTab = $allowedTabs[0]; 
    $tab = $_GET['tab'] ?? $defaultTab;
    
    // Sécurité URL : Si on force un onglet non autorisé
    if (!in_array($tab, $allowedTabs)) {
        $tab = $defaultTab;
    }
}

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
        
        <?php if ($tab !== 'empty'): ?>
        <nav class="budget-tabs-container">
            <?php if (in_array('meals', $allowedTabs)): ?>
            <a href="?tab=meals" class="tab-item <?= $tab == 'meals' ? 'active' : '' ?>">
                <span class="tab-icon">🍽️</span> 
                <span><?= tr('food_tab_meals') ?></span>
            </a>
            <?php endif; ?>

            <?php if (in_array('liste', $allowedTabs)): ?>
            <a href="?tab=liste" class="tab-item <?= $tab == 'liste' ? 'active' : '' ?>">
                <span class="tab-icon">🛒</span> 
                <span><?= tr('food_tab_list') ?></span>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>

    <section class="pf-section">
        <?php 
        if ($tab === 'empty') {
            // État Vide Intégré (Zéro vue activée)
            echo "
            <div class='pf-empty-state' style='text-align:center; padding: 60px 20px; max-width: 600px; margin: 0 auto; background: var(--bg-panel); border-radius: 16px; border: 1px dashed var(--border-strong); box-shadow: var(--shadow-sm);'>
                <div style='font-size: 4rem; margin-bottom: 20px; opacity: 0.5;'>👀</div>
                <h3 style='color: var(--text-main); margin-bottom: 10px; font-size: 1.3rem;'>" . tr('food_err_no_views') . "</h3>
                <p style='color: var(--text-muted); margin-bottom: 30px; font-size: 0.95rem; line-height: 1.5;'>" . tr('food_err_no_views_desc') . "</p>
                <a href='/settings.php' class='pf-btn'>⚙️ " . tr('settings') . "</a>
            </div>";
        } else {
            // Routage classique
            $viewPath = __DIR__ . "/modules/food/views/{$tab}.php";
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<p>Vue introuvable : " . htmlspecialchars($tab) . "</p>";
            }
        }
        ?>
    </section>
</div>

<?php require __DIR__ . '/footer.php'; ?>