<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_login('/login.php');

// ============================================================================
// DIAGNOSTIC ONBOARDING (Vérifie si le budget est prêt à être utilisé)
// ============================================================================
$currentYear = date('Y');
$hasCategories = ((int)$pdo->query("SELECT COUNT(*) FROM pf_budget_categories")->fetchColumn() > 0);
$hasSalaries = ((int)$pdo->query("SELECT COUNT(*) FROM pf_salary_config WHERE year = $currentYear")->fetchColumn() > 0);
$hasAccounts = ((int)$pdo->query("SELECT COUNT(*) FROM pf_bank_accounts")->fetchColumn() > 0);
$hasBudgetItems = ((int)$pdo->query("SELECT COUNT(*) FROM pf_budget_items")->fetchColumn() > 0);

// Le minimum vital pour que ça tourne
$isBudgetSetupOk = ($hasCategories && $hasSalaries && $hasAccounts);

// Gestion de l'onglet actif (par défaut 'suivi')
$tab = $_GET['tab'] ?? 'suivi';

$pageTitle  = tr('budget_page_title');
$activePage = "budget";
$pageCss    = "/modules/budget/budget.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
    <div class="pf-hero budget-hero">
        <div class="budget-title-group">
            <h1><?= tr('budget_main_header') ?></h1>
            <!-- Bouton avec séparation claire entre l'engrenage animable et l'alerte fixe -->
            <button class="btn-header-settings" onclick="openBudgetSettings()" title="<?= tr('settings') ?>">
                <span class="gear-icon-anim">⚙️</span>
                <?php if (!$isBudgetSetupOk || !$hasBudgetItems): ?>
                    <span class="alert-dot">!</span>
                <?php endif; ?>
            </button>
        </div>
        
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
    $allowedTabs = ['recap', 'suivi', 'epargne', 'budget_prev'];
    
    if (in_array($tab, $allowedTabs)) {
        $viewPath = __DIR__ . "/modules/budget/views/" . $tab . ".php";
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<div class='budget-error-msg'>";
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

<?php 
require __DIR__ . '/modules/budget/views/settings_modal.php';
require __DIR__ . '/footer.php'; 
?>