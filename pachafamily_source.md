# 🦙 Source Code PachaFamily

> *Généré le 2026-05-06 13:00:43*

### 📄 Fichier : `budget.php`
```php
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
```

---

### 📄 Fichier : `family-calendar.php`
```php
<?php
// modules/family-calendar/family-calendar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$pageTitle  = tr('fc_page_title');
$activePage = "family-calendar";
$bodyClass  = "pf-family-calendar"; 
$pageCss    = "/modules/family-calendar/family-calendar.css"; 

require __DIR__ . '/header.php';
?>

<div class="pf-container" style="max-width:100%; padding:0;">
    <div class="fc-header-row">
        <h1><?= tr('fc_main_header') ?></h1>
        
        <div style="display: flex; gap: 10px;">
            <button id="btnOpenSnapshotModal" class="pf-btn-icon-text">
                <span class="icon">⚖️</span>
                <span class="text"><?= tr('fc_btn_correct_balances') ?></span>
            </button>
            <button id="btnOpenHolidays" class="pf-btn-icon-text">
                <span class="icon">🏖️</span>
                <span class="text"><?= tr('fc_btn_school_holidays') ?></span>
            </button>
        </div>
    </div>

    <div id="modalHolidays" class="pf-modal">
        <div class="pf-modal-content">
            <div class="pf-modal-header">
                <h3 class="pf-modal-title"><?= tr('fc_modal_holidays_title') ?></h3>
                <button id="btnCloseHolidays" class="pf-modal-close" onclick="document.getElementById('modalHolidays').classList.remove('open'); document.body.classList.remove('no-scroll');">&times;</button>
            </div>
            <div class="pf-modal-body" style="padding:0;">
                <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table id="schoolHolidaysTable" class="fc-holidays-table" style="min-width: 400px;">
                        <thead>
                            <tr>
                            <th><?= tr('fc_col_period') ?></th>
                            <th><?= tr('fc_col_from') ?></th>
                            <th><?= tr('fc_col_to') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="modalSnapshot" class="pf-modal">
        <div class="pf-modal-content">
            <div class="pf-modal-header">
                <h3 class="pf-modal-title">📸 <?= tr('fc_modal_snap_title') ?></h3>
                <button type="button" id="btnCloseSnapshot" class="pf-modal-close" onclick="document.getElementById('modalSnapshot').classList.remove('open'); document.body.classList.remove('no-scroll');">&times;</button>
            </div>
            <div class="pf-modal-body">
                <form id="formSnapshot">
                    <div class="pf-form-row">
                        <div class="pf-form-group">
                            <label class="pf-label"><?= tr('fc_label_person') ?></label>
                            <select id="snapPerson" class="pf-input" required>
                                <option value="2">Alex</option>
                                <option value="3">Laia</option>
                            </select>
                        </div>
                        <div class="pf-form-group">
                            <label class="pf-label"><?= tr('fc_label_leave_type') ?></label>
                            <select id="snapType" class="pf-input" required>
                                <option value="CP">CP</option>
                                <option value="JRA">JRA</option>
                                <option value="JA">JA</option>
                            </select>
                        </div>
                    </div>
                    <div class="pf-form-group">
                        <label class="pf-label"><?= tr('fc_label_apply_date') ?></label>
                        <input type="date" id="snapDate" class="pf-input" required title="<?= tr('fc_snap_date_hint') ?>">
                    </div>
                    <div class="pf-form-group">
                        <label class="pf-label"><?= tr('fc_label_remaining_balance') ?></label>
                        <div style="display:flex; align-items:center;">
                            <input type="number" step="0.5" id="snapBalance" class="pf-input" placeholder="<?= tr('fc_placeholder_balance') ?>" style="border-radius: 8px 0 0 8px; border-right: none; font-weight: bold; color: var(--pf-primary);" required>
                            <span style="background: var(--pf-bg-lighter); border: 1px solid var(--pf-border); padding: 0 12px; height: 42px; display: flex; align-items: center; border-radius: 0 8px 8px 0; color: var(--pf-text-muted); font-size: 0.9rem; font-weight: 600;">
                                <?= tr('fc_unit_days') ?>
                            </span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="pf-modal-footer">
                <button type="button" class="pf-btn pf-btn-secondary" onclick="document.getElementById('modalSnapshot').classList.remove('open'); document.body.classList.remove('no-scroll');"><?= tr('btn_cancel') ?></button>
                <button type="submit" form="formSnapshot" class="pf-btn pf-btn-primary"><?= tr('fc_btn_save_snap') ?></button>
            </div>
        </div>
    </div>
</div>

    <section class="pf-section">
      <div class="fc-month-calendar-wrapper">
        
        <div class="fc-month-header">
          
          <div class="fc-month-nav-row">
            <button id="fc-prev-month" class="fc-nav-button">‹</button>
            <h3 id="fc-current-month-year"></h3>
            <button id="fc-next-month" class="fc-nav-button">›</button>
          </div>
          
          <div class="fc-view-controls">
            <button class="fc-view-button fc-view-button--active" data-view="1month"><?= tr('fc_view_1m') ?></button>
            <button class="fc-view-button" data-view="2months"><?= tr('fc_view_2m') ?></button>
            <button class="fc-view-button" data-view="3months">3 Mois</button>
          </div>

        </div>
        
        <div class="fc-calendar-container">
          <div id="fc-month-calendar" class="fc-month-calendar"></div>
          <div id="fc-month-selectionMenu" class="fc-selection-menu" style="display:none;"></div>
        </div>

        <div id="fc-month-balances" class="fc-month-balances"></div>
        
      </div>
    </section>

    <section class="pf-section">
      <div class="fc-week-header">
        <h2><?= tr('fc_weekly_planning') ?></h2>
        <div class="fc-week-nav-controls">
          <button id="fc-prev-school-year" class="fc-nav-button">‹</button>
          <span id="fc-current-school-year-label"></span>
          <button id="fc-next-school-year" class="fc-nav-button">›</button>
        </div>
      </div>

      <div id="planningTable-wrapper">
        <table id="planningTable">
          <thead>
            <tr>
              <th rowspan="3" class="col-month col-sticky-mois"><?= tr('fc_col_month') ?></th>
              <th rowspan="3" class="col-month col-sticky-mois"><?= tr('fc_col_week_short') ?></th>
              <th rowspan="3" class="col-day"><?= tr('day_mon') ?></th>
              <th rowspan="3" class="col-day"><?= tr('day_tue') ?></th>
              <th rowspan="3" class="col-day"><?= tr('day_wed') ?></th>
              <th rowspan="3" class="col-day"><?= tr('day_thu') ?></th>
              <th rowspan="3" class="col-day"><?= tr('day_fri') ?></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_off_carole') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_extra_off') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_centre') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_avis') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_pep_sick') ?></span></th>
              <th rowspan="3" class="col-total rotated-text"><span><?= tr('leg_presence') ?></span></th>
              <th colspan="6" class="col-alex header-group">ALEX</th>
              <th colspan="6" class="col-laia header-group">LAIA</th>
            </tr>
            <tr>
              <th colspan="2" class="col-alex-sub">CP</th><th colspan="2" class="col-alex-sub">JRA</th><th colspan="2" class="col-alex-sub">JA</th>
              <th colspan="2" class="col-laia-sub">CP</th><th colspan="2" class="col-laia-sub">JRA</th><th colspan="2" class="col-laia-sub">JA</th>
            </tr>
            <tr>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-alex-sub col-alex-av"><?= tr('fc_col_av') ?></th><th class="col-alex-sub col-alex-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
                <th class="col-laia-sub col-laia-av"><?= tr('fc_col_av') ?></th><th class="col-laia-sub col-laia-use"><?= tr('fc_col_use') ?></th>
            </tr>
          </thead>
          <tbody id="planningBody"></tbody>
        </table>
        <div id="selectionMenu" class="fc-selection-menu" style="display:none;"></div>
      </div>
    </section>

    <section class="pf-section pf-section--bottom-panels">
        <div class="fc-bottom-grid">

            <div class="pf-card">
                <div class="pf-card-title fc-summary-header">
                    <span><?= tr('fc_summary_title') ?></span>
                    <div class="fc-summary-controls">
                        <select id="summType" class="fc-summ-select"> 
                            <option value="year"><?= tr('fc_summ_year') ?></option>
                            <option value="month"><?= tr('fc_summ_month') ?></option>
                        </select>
                        <select id="summValue" class="fc-summ-select"></select>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div id="globalSummary" class="fc-summary-vertical"></div>
                </div>
            </div>

            <div class="pf-card">
                <h2 class="pf-card-title"><?= tr('fc_legend_title') ?></h2>
                <div class="pf-card-body">
                    <div class="pf-legend-grid">
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-school-holiday"></div><span><?= tr('leg_school_holidays') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-public-holiday"></div><span><?= tr('leg_public_holiday') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-off-carole"></div><span><?= tr('leg_off_carole') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-extra-off-carole"></div><span><?= tr('leg_extra_off') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-centre"></div><span><?= tr('leg_centre') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-avis"></div><span><?= tr('leg_avis') ?></span></div>
                        <div class="pf-legend-item"><div class="pf-legend-color fc-legend-pep-sick"></div><span><?= tr('leg_pep_sick') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
window.I18N = {
    ...(window.I18N || {}),
    'fc_menu_carole': "<?= tr('fc_menu_carole') ?>",
    'btn_off': "<?= tr('btn_off') ?>",
    'btn_extra': "<?= tr('btn_extra') ?>",
    'leg_centre': "<?= tr('leg_centre') ?>",
    'leg_avis': "<?= tr('leg_avis') ?>",
    'leg_pep_sick': "<?= tr('leg_pep_sick') ?>",
    'leg_off_carole': "<?= tr('leg_off_carole') ?>",
    'leg_extra_off': "<?= tr('leg_extra_off') ?>",
    'leg_presence': "<?= tr('leg_presence') ?>",
    'fc_menu_kids_leaves': "<?= tr('fc_menu_kids_leaves') ?>",
    'fc_clear': "<?= tr('fc_clear') ?>",
    'fc_unit_days': "<?= tr('fc_unit_days') ?>",
    'vac_toussaint': "<?= tr('vac_toussaint') ?>",
    'vac_noel': "<?= tr('vac_noel') ?>",
    'vac_hiver': "<?= tr('vac_hiver') ?>",
    'vac_printemps': "<?= tr('vac_printemps') ?>",
    'vac_ascension': "<?= tr('vac_ascension') ?>",
    'vac_ete': "<?= tr('vac_ete') ?>",
    'leg_school_holidays': "<?= tr('leg_school_holidays') ?>",
    'fc_school_year': "<?= tr('fc_school_year') ?>",
    'fc_modal_holidays_title': "<?= tr('fc_modal_holidays_title') ?>",
    'fc_alert_burn_days': "<?= tr('fc_alert_burn_days') ?>",
    'fc_alert_burn_jra': "<?= tr('fc_alert_burn_jra') ?>",
    'ANNIV': "<?= tr('ANNIV') ?>"
};
</script>
<script src="/modules/family-calendar/family-calendar.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
```

---

### 📄 Fichier : `footer.php`
```php
  </main>

  <footer class="pf-footer">
    <div class="pf-container">
      <small>&copy; <?php echo date('Y'); ?> PachaFamily</small>
    </div>
  </footer>
  </div> <!-- FERMETURE pf-page -->

</body>
</html>

```

---

### 📄 Fichier : `generate_css_prompt.php`
```php
<?php
/**
 * PachaFamily - CSS Auditor 🦙
 * Génère un rapport complet de tous les styles (fichiers, balises, inline)
 */

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$report = "=== 🦙 RAPPORT CSS COMPLET PACHAFAMILY ===\n";
$report .= "Généré le : " . date('Y-m-d H:i:s') . "\n\n";

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $filePath = $file->getPathname();
    $relativePath = str_replace($root, '', $filePath);
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    // 1. EXTRACTION DES FICHIERS .css
    if ($ext === 'css') {
        $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n";
        $report .= file_get_contents($filePath) . "\n";
        $report .= str_repeat("-", 40) . "\n\n";
    }

    // 2. EXTRACTION DES BALISES <style> ET ATTRIBUTS style="" DANS LES .php
    if ($ext === 'php') {
        $content = file_get_contents($filePath);
        $foundInFile = false;

        // Extraction des blocs <style>
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $content, $matches)) {
            if (!$foundInFile) { $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n"; $foundInFile = true; }
            foreach ($matches[1] as $idx => $styleBlock) {
                $report .= "/* Bloc <style> #$idx */\n";
                $report .= trim($styleBlock) . "\n";
            }
        }

        // Extraction des styles inline style="..."
        // On capture le tag pour donner du contexte au style inline
        if (preg_match_all('/<([a-z0-9]+)[^>]*?\sstyle=["\']([^"]*?)["\'][^>]*>/i', $content, $matches)) {
            if (!$foundInFile) { $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n"; $foundInFile = true; }
            foreach ($matches[0] as $idx => $fullTag) {
                // On nettoie un peu pour ne garder que l'essentiel du tag et son style
                $report .= "/* Style Inline #$idx */\n";
                $report .= trim($fullTag) . "\n";
            }
        }

        if ($foundInFile) {
            $report .= str_repeat("-", 40) . "\n\n";
        }
    }
}

echo $report;
```

---

### 📄 Fichier : `gift-list.php`
```php
<?php
// modules/gift-list/gift-list.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. CONFIGURATION ---
$year       = (int)date('Y');
$pageTitle  = tr('gift_page_title');
$activePage = "gift-list";
$bodyClass  = "pf-gift-list";
$pageCss    = "/modules/gift-list/gift-list.css";

$children = ['Pol', 'Pep', 'Elna', 'Bru', 'Guim'];
$baseAdults = ['Laia', 'Laura', 'Avi Iaia'];
$extraAdults = ['Pauline', 'Papy JC', 'Mamy Caro'];

$adultsByChildForAnniv = [
    'Pol'  => array_merge($baseAdults, $extraAdults),
    'Pep'  => array_merge($baseAdults, $extraAdults),
    'Elna' => $baseAdults,
    'Bru'  => $baseAdults,
    'Guim' => $baseAdults,
];

$VIEWS = [
    'nadal'       => ['TIO', 'NOEL', 'ROIS'],
    'anniversary' => ['ANNIV', 'SANT'],
];

$currentView = strtolower($_GET['view'] ?? ($_SESSION['gift_view'] ?? 'nadal'));
if (!isset($VIEWS[$currentView])) $currentView = 'nadal';
$_SESSION['gift_view'] = $currentView;
$allowedOccasions = $VIEWS[$currentView];

$allOccasionLabels = [
    'TIO' => tr('gift_occ_tio'), 'NOEL' => tr('gift_occ_noel'), 'ROIS' => tr('gift_occ_rois'),
    'ANNIV' => tr('gift_occ_anniv'), 'SANT' => tr('gift_occ_sant')
];
$occasionIcons = [
    'TIO' => '/modules/gift-list/assets/img/tio.png', 'NOEL' => '/modules/gift-list/assets/img/santa.png',
    'ROIS' => '/modules/gift-list/assets/img/reis.png', 'ANNIV' => '/modules/gift-list/assets/img/corona.png',
    'SANT' => '/modules/gift-list/assets/img/sant.png'
];

// --- 2. DONNÉES ---
$inMarks = implode(',', array_fill(0, count($allowedOccasions), '?'));
// Tri: Par Fête, puis Enfant, puis Adulte
$sql = "SELECT * FROM pf_gifts WHERE year = ? AND occasion IN ($inMarks) ORDER BY occasion ASC, child_name ASC, adult_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$year], $allowedOccasions));
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = []; 
$adultsInView = [];

foreach ($gifts as $g) {
    $data[$g['occasion']][$g['child_name']]['gifts'][] = $g;
    $data[$g['occasion']][$g['child_name']]['totals'][$g['adult_name']] = ($data[$g['occasion']][$g['child_name']]['totals'][$g['adult_name']] ?? 0) + $g['amount'];
    $adultsInView[$g['adult_name']] = true;
}
$allAdultsList = array_keys($adultsInView); sort($allAdultsList);

// --- 3. TRICOUNT ---
$people = array_values(array_unique(array_merge($baseAdults, array_column($gifts, 'adult_name'), array_column($gifts, 'payer_name'))));
$people = array_filter($people);
$matrix = [];
foreach ($people as $p1) { foreach ($people as $p2) $matrix[$p1][$p2] = 0.0; }

foreach ($gifts as $g) {
    $adult = $g['adult_name'];
    $payer = $g['payer_name'] ?? $g['adult_name'];
    $amt   = (float)$g['amount'];
    if ($amt > 0 && $adult && $payer && $adult !== $payer) {
        $matrix[$adult][$payer] += $amt;
    }
}

$settlements = [];
$countPeople = count($people);
for ($i = 0; $i < $countPeople; $i++) {
    for ($j = $i + 1; $j < $countPeople; $j++) {
        $a = $people[$i]; $b = $people[$j];
        $net = $matrix[$a][$b] - $matrix[$b][$a];
        if ($net > 0.01) { $settlements[] = ['from' => $a, 'to' => $b, 'amount' => $net]; } 
        elseif ($net < -0.01) { $settlements[] = ['from' => $b, 'to' => $a, 'amount' => -$net]; }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-container cl-view-<?= htmlspecialchars($currentView) ?>">
    
    <div class="cl-titlebar">
        <h1>🎁 <?= sprintf(tr('gift_main_title'), $year) ?></h1>
        <div class="cl-view-switch">
            <a href="?view=nadal" class="cl-view-btn <?= $currentView === 'nadal' ? 'is-active' : '' ?>"><?= tr('gift_view_nadal') ?></a>
            <a href="?view=anniversary" class="cl-view-btn <?= $currentView === 'anniversary' ? 'is-active' : '' ?>"><?= tr('gift_view_anniv') ?></a>
        </div>
    </div>

    <div class="pf-filter-bar">
        <span style="font-size:1.2rem;">🔍</span>
        
        <div class="pf-multi-select" id="ms-child">
            <div class="pf-ms-trigger" onclick="toggleMS('ms-child-list', this)">
                👦 <span id="ms-child-label"><?= tr('gift_filter_all_children') ?></span>
            </div>
            <div class="pf-ms-dropdown" id="ms-child-list">
                <label class="pf-ms-option is-all">
                    <input type="checkbox" value="all" checked onchange="handleMSChange(this, 'child')"> 
                    <?= tr('gift_filter_all_children') ?>
                </label>
                <?php foreach($children as $c): ?>
                    <label class="pf-ms-option"><input type="checkbox" value="<?= htmlspecialchars($c) ?>" onchange="handleMSChange(this, 'child')"> <?= htmlspecialchars($c) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pf-multi-select" id="ms-adult">
            <div class="pf-ms-trigger" onclick="toggleMS('ms-adult-list', this)">
                👤 <span id="ms-adult-label"><?= tr('gift_filter_all_adults') ?></span>
            </div>
            <div class="pf-ms-dropdown" id="ms-adult-list">
                <label class="pf-ms-option is-all">
                    <input type="checkbox" value="all" checked onchange="handleMSChange(this, 'adult')"> 
                    <?= tr('gift_filter_all_adults') ?>
                </label>
                <?php foreach($allAdultsList as $a): ?>
                    <label class="pf-ms-option"><input type="checkbox" value="<?= htmlspecialchars($a) ?>" onchange="handleMSChange(this, 'adult')"> <?= htmlspecialchars($a) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <section class="pf-section">
        <?php foreach ($allowedOccasions as $occCode): ?>
            <div class="js-occ-section">
                <h2 class="cl-occasion-title">
                    <?php if(!empty($occasionIcons[$occCode])): ?><img src="<?= $occasionIcons[$occCode] ?>" class="cl-occasion-icon"><?php endif; ?>
                    <?= $allOccasionLabels[$occCode] ?>
                </h2>

                <?php foreach ($children as $child): 
                    $childData = $data[$occCode][$child] ?? ['gifts' => [], 'totals' => []];
                    $adultsForThisChild = ($currentView === 'anniversary' && in_array($child, ['Pol', 'Pep'])) ? array_merge($baseAdults, $extraAdults) : $baseAdults;
                ?>
                <div class="pf-child-section js-child" data-name="<?= htmlspecialchars($child) ?>">
                    <div class="pf-child-header">
                        <h3>👦 <?= htmlspecialchars($child) ?></h3>
                        <button class="pf-btn pf-btn-small btn-add-gift" 
                                data-child="<?= htmlspecialchars($child) ?>" 
                                data-occ="<?= htmlspecialchars($occCode) ?>" 
                                data-adults="<?= htmlspecialchars(json_encode(array_values($adultsForThisChild))) ?>">
                            ＋ <?= tr('gift_add_gift') ?>
                        </button>
                    </div>

                    <div class="pf-child-totals-bar">
                        <?php foreach ($childData['totals'] as $adult => $tot): ?>
                            <span class="pf-summary-pill js-pill-adult" data-adult="<?= htmlspecialchars($adult) ?>">👤 <?= htmlspecialchars($adult) ?> : <strong><?= number_format($tot, 2, ',', '') ?> €</strong></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($childData['gifts'])): ?>
                        <p class="js-empty-state" style="color:var(--text-muted); font-size:0.9rem; font-style:italic; margin:0;"><?= tr('gift_empty_state_no_gifts') ?></p>
                    <?php else: ?>
                        <p class="js-empty-state" style="color:var(--text-muted); font-size:0.9rem; font-style:italic; display:none; margin:0;"><?= tr('gift_empty_state_no_filter') ?></p>
                        <div class="pf-gift-feed">
                            <?php foreach ($childData['gifts'] as $g): ?>
                            <div class="pf-gift-card-compact js-gift-card" data-adult="<?= htmlspecialchars($g['adult_name']) ?>">
                                <div>
                                    <h4 class="pf-gift-title">
                                        <?= htmlspecialchars($g['gift_description']) ?> 
                                        <?php if($g['product_link']): ?><a href="<?= htmlspecialchars($g['product_link']) ?>" target="_blank" class="pf-gift-link">🔗</a><?php endif; ?>
                                    </h4>
                                    <span class="pf-pill-adult">👤 <?= htmlspecialchars($g['adult_name']) ?></span>
                                    <?php if($g['payer_name'] && $g['payer_name'] !== $g['adult_name']): ?>
                                        <div class="pf-gift-payer"><?= sprintf(tr('gift_paid_by'), htmlspecialchars($g['payer_name'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="pf-gift-footer">
                                    <span class="pf-gift-price"><?= number_format($g['amount'], 2, ',', ' ') ?> €</span>
                                    <div class="pf-gift-actions">
                                        <button class="btn-icon-action edit btn-edit-gift" data-gift="<?= htmlspecialchars(json_encode($g)) ?>">✏️</button>
                                        <button class="btn-icon-action delete btn-delete-gift" data-id="<?= $g['id'] ?>">🗑️</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="pf-section pf-section--panel" style="background:#f8fafc; border:1px solid var(--border-light); margin-top:40px;">
        <h2 style="margin-top:0; color:var(--text-main); font-size:1.3rem;">⚖️ <?= tr('gift_liquidations') ?? 'Bilan & Remboursements' ?></h2>
        
        <?php if (empty($settlements)): ?>
            <p style="color:var(--success); font-weight:700; margin-bottom:0;">✅ <?= tr('gift_no_debt') ?></p>
        <?php else: ?>
            <ul class="pf-tricount-list">
                <?php foreach ($settlements as $s): ?>
                    <li class="pf-tricount-item">
                        <span><strong><?= htmlspecialchars($s['from']) ?></strong> <?= tr('gift_owes') ?> à <?= htmlspecialchars($s['to']) ?></span>
                        <strong style="color:var(--danger);"><?= number_format($s['amount'], 2, ',', ' ') ?> €</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details style="margin-top:20px; background:white; padding:12px; border-radius:8px; border:1px solid #cbd5e1;">
            <summary style="cursor:pointer; font-weight:600; color:var(--text-muted); outline:none;"><?= tr('gift_view_matrix') ?></summary>
            <div style="overflow-x:auto; margin-top:15px;">
                <table class="pf-table pf-table--compact cl-debt-matrix">
                    <thead>
                        <tr>
                            <th style="position:sticky; left:0; background:#f8fafc; z-index:2; border-right:2px solid #e2e8f0;"><?= tr('gift_debtor') ?> \ <?= tr('gift_creditor') ?></th>
                            <?php foreach ($people as $p): ?><th><?= htmlspecialchars($p) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $debtor): ?>
                            <tr>
                                <th style="position:sticky; left:0; background:white; z-index:2; border-right:2px solid #e2e8f0;"><?= htmlspecialchars($debtor) ?></th>
                                <?php foreach ($people as $creditor): 
                                    $val = $matrix[$debtor][$creditor] ?? 0;
                                    $display = ($debtor === $creditor) || $val == 0 ? '—' : number_format($val, 2, ',', ' ') . ' €';
                                ?>
                                    <td style="<?= $val > 0 ? 'color:var(--danger); font-weight:700; background:#fef2f2;' : 'color:var(--text-muted);' ?>"><?= $display ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>
</div>

<div id="pf-gift-modal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 500px; width: 95%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="modalTitle" class="pf-modal-title" style="margin:0; border:none; padding:0;">Cadeau</h3>
            <button type="button" class="btn-modal-close" style="background:none; border:none; font-size:1.8rem; cursor:pointer; color:var(--text-muted); line-height:1;">&times;</button>
        </div>
        
        <form method="post" action="/modules/gift-list/save-gift.php" id="giftForm">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="child_name" id="modalChild">
            <input type="hidden" name="occasion" id="modalOccasion">
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="gift_id" id="modalGiftId">
            
            <div class="pf-form-group"><label class="pf-label"><?= tr('gift_col_adult') ?></label><select name="adult_name" id="modalAdult" class="pf-input" required></select></div>
            <div class="pf-form-group"><label class="pf-label"><?= tr('gift_modal_payer') ?></label><select name="payer_name" id="modalPayer" class="pf-input" required></select></div>
            <div class="pf-form-group"><label class="pf-label"><?= tr('gift_modal_gift_name') ?></label><input type="text" name="gift_description" id="modalDesc" class="pf-input" required></div>
            <div class="pf-form-group"><label class="pf-label"><?= tr('gift_modal_price') ?></label><input type="number" step="0.01" name="amount" id="modalAmount" class="pf-input"></div>
            <div class="pf-form-group" style="margin-bottom:25px;"><label class="pf-label"><?= tr('gift_modal_link') ?></label><input type="url" name="product_link" id="modalLink" class="pf-input"></div>
            
            <div class="modal-footer" style="padding-top:15px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="pf-btn btn-secondary btn-modal-close"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// ==========================================
// 1. GESTION DU COMPOSANT MULTI-SELECT VANILLA
// ==========================================

function toggleMS(listId, triggerEl) {
    document.querySelectorAll('.pf-ms-dropdown').forEach(el => { if (el.id !== listId) el.classList.remove('open'); });
    document.querySelectorAll('.pf-ms-trigger').forEach(el => { if (el !== triggerEl) el.classList.remove('active'); });
    const list = document.getElementById(listId);
    if (list) list.classList.toggle('open');
    if (triggerEl) triggerEl.classList.toggle('active');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.pf-multi-select')) {
        document.querySelectorAll('.pf-ms-dropdown').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.pf-ms-trigger').forEach(el => el.classList.remove('active'));
    }
});

function handleMSChange(cb, type) {
    const container = document.getElementById('ms-' + type + '-list');
    if (!container) return;
    
    if (cb.value === 'all' && cb.checked) {
        container.querySelectorAll('input[type="checkbox"]:not([value="all"])').forEach(i => i.checked = false);
    } else if (cb.checked) {
        const allCb = container.querySelector('input[value="all"]');
        if (allCb) allCb.checked = false;
    } else {
        const checkedSpecifics = container.querySelectorAll('input[type="checkbox"]:checked:not([value="all"])');
        if (checkedSpecifics.length === 0) {
            const allCb = container.querySelector('input[value="all"]');
            if (allCb) allCb.checked = true;
        }
    }
    
    const checkedSpecifics = container.querySelectorAll('input[type="checkbox"]:checked:not([value="all"])');
    const labelEl = document.getElementById('ms-' + type + '-label');
    
    if (labelEl) {
        if (checkedSpecifics.length === 0) {
            let defaultText = 'Tous';
            if (window.I18N) {
                if (type === 'child' && window.I18N['gift_filter_all_children']) defaultText = window.I18N['gift_filter_all_children'];
                if (type === 'adult' && window.I18N['gift_filter_all_adults']) defaultText = window.I18N['gift_filter_all_adults'];
            }
            labelEl.innerText = defaultText;
        } else if (checkedSpecifics.length === 1) {
            labelEl.innerText = checkedSpecifics[0].value;
        } else {
            labelEl.innerText = checkedSpecifics.length + ' sélections';
        }
    }
    applyGiftFilters();
}

function applyGiftFilters() {
    const cList = document.getElementById('ms-child-list');
    const aList = document.getElementById('ms-adult-list');
    if (!cList || !aList) return;
    
    const allChildCb = cList.querySelector('input[value="all"]');
    const cAll = allChildCb ? allChildCb.checked : true;
    const cVals = Array.from(cList.querySelectorAll('input[type="checkbox"]:checked:not([value="all"])')).map(i => i.value);
    
    const allAdultCb = aList.querySelector('input[value="all"]');
    const aAll = allAdultCb ? allAdultCb.checked : true;
    const aVals = Array.from(aList.querySelectorAll('input[type="checkbox"]:checked:not([value="all"])')).map(i => i.value);

    document.querySelectorAll('.js-occ-section').forEach(occSec => {
        let occHasVisible = false;
        
        occSec.querySelectorAll('.js-child').forEach(childSec => {
            const cName = childSec.getAttribute('data-name'); 
            const matchChild = cAll || (cVals.indexOf(cName) !== -1);
            let childHasVisibleCard = false;
            
            if (matchChild) {
                childSec.querySelectorAll('.js-gift-card').forEach(card => {
                    const aName = card.getAttribute('data-adult');
                    const matchAdult = aAll || (aVals.indexOf(aName) !== -1);
                    card.style.display = matchAdult ? 'flex' : 'none';
                    if (matchAdult) childHasVisibleCard = true;
                });
                
                childSec.querySelectorAll('.js-pill-adult').forEach(pill => {
                    const aName = pill.getAttribute('data-adult');
                    const matchAdult = aAll || (aVals.indexOf(aName) !== -1);
                    pill.style.opacity = matchAdult ? '1' : '0.2';
                });
                
                const empty = childSec.querySelector('.js-empty-state');
                if (empty) empty.style.display = childHasVisibleCard ? 'none' : 'block';
                
                childSec.style.display = 'block';
                occHasVisible = true;
            } else {
                childSec.style.display = 'none';
            }
        });
        
        occSec.style.display = occHasVisible ? 'block' : 'none';
    });
}

// ==========================================
// 3. LOGIQUE DE LA MODALE & DELEGATION JS
// ==========================================

const modal = document.getElementById('pf-gift-modal');
const adultSelect = document.getElementById('modalAdult');
const payerSelect = document.getElementById('modalPayer');

// Synchronisation automatique : Adulte -> Payeur
if (adultSelect && payerSelect) {
    adultSelect.addEventListener('change', function() {
        let exists = false;
        for(let i = 0; i < payerSelect.options.length; i++) {
            if(payerSelect.options[i].value === this.value) exists = true;
        }
        if (exists) payerSelect.value = this.value;
    });
}

function populateSelects(adults) {
    if (!adultSelect || !payerSelect) return;
    adultSelect.innerHTML = '';
    payerSelect.innerHTML = '';
    adults.forEach(name => {
        adultSelect.appendChild(new Option(name, name));
        payerSelect.appendChild(new Option(name, name));
    });
}

// L'écouteur d'événements global pour tous les boutons !
document.body.addEventListener('click', async function(e) {
    
    // --- 1. BOUTON FERMER MODALE ---
    if (e.target.closest('.btn-modal-close')) {
        if (modal) {
            modal.classList.remove('open');
            document.body.classList.remove('no-scroll');
        }
        return;
    }

    // --- 2. BOUTON AJOUTER (+) ---
    const btnAdd = e.target.closest('.btn-add-gift');
    if (btnAdd) {
        const childName = btnAdd.dataset.child;
        const occCode = btnAdd.dataset.occ;
        const adultsList = JSON.parse(btnAdd.dataset.adults || '[]');

        document.getElementById('modalAction').value = 'create'; 
        document.getElementById('modalGiftId').value = '';
        document.getElementById('modalChild').value = childName; 
        document.getElementById('modalOccasion').value = occCode;
        document.getElementById('modalDesc').value = ''; 
        document.getElementById('modalAmount').value = '';
        document.getElementById('modalLink').value = '';
        
        populateSelects(adultsList);
        
        if (adultSelect && payerSelect) {
            if (adultsList.indexOf('Laia') !== -1) {
                adultSelect.value = 'Laia'; payerSelect.value = 'Laia';
            } else if (adultsList.length > 0) {
                adultSelect.value = adultsList[0]; payerSelect.value = adultsList[0];
            }
        }

        const titleStr = window.I18N && window.I18N['gift_modal_title_add'] ? window.I18N['gift_modal_title_add'] : 'Ajouter pour %s';
        document.getElementById('modalTitle').textContent = titleStr.replace('%s', childName);
        
        if (modal) { modal.classList.add('open'); document.body.classList.add('no-scroll'); }
        return;
    }

    // --- 3. BOUTON MODIFIER (CRAYON) ---
    const btnEdit = e.target.closest('.btn-edit-gift');
    if (btnEdit) {
        const data = JSON.parse(btnEdit.dataset.gift || '{}');

        document.getElementById('modalAction').value = 'update'; 
        document.getElementById('modalGiftId').value = data.id;
        document.getElementById('modalChild').value = data.child_name; 
        document.getElementById('modalOccasion').value = data.occasion;
        document.getElementById('modalDesc').value = data.gift_description; 
        document.getElementById('modalAmount').value = data.amount;
        document.getElementById('modalLink').value = data.product_link;

        const payerVal = data.payer_name || data.adult_name;
        if (adultSelect && payerSelect) {
            let adultExists = false, payerExists = false;
            for(let i=0; i<adultSelect.options.length; i++) { if(adultSelect.options[i].value === data.adult_name) adultExists = true; }
            for(let i=0; i<payerSelect.options.length; i++) { if(payerSelect.options[i].value === payerVal) payerExists = true; }
            
            if (!adultExists) adultSelect.appendChild(new Option(data.adult_name, data.adult_name));
            if (!payerExists) payerSelect.appendChild(new Option(payerVal, payerVal));
            
            adultSelect.value = data.adult_name;
            payerSelect.value = payerVal;
        }
        
        document.getElementById('modalTitle').textContent = window.I18N && window.I18N['gift_modal_title_edit'] ? window.I18N['gift_modal_title_edit'] : 'Modifier le cadeau';
        
        if (modal) { modal.classList.add('open'); document.body.classList.add('no-scroll'); }
        return;
    }

    // --- 4. BOUTON SUPPRIMER (POUBELLE) ---
    const btnDel = e.target.closest('.btn-delete-gift');
    if (btnDel) {
        const giftId = btnDel.dataset.id;
        const msg = window.I18N && window.I18N['gift_confirm_delete'] ? window.I18N['gift_confirm_delete'] : 'Supprimer ce cadeau ?';
        if (!confirm(msg)) return;
        
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('gift_id', giftId);
        
        try {
            await fetch('/modules/gift-list/save-gift.php', { method: 'POST', body: fd });
            window.location.reload();
        } catch(err) {
            console.error(err);
        }
        return;
    }
});

// ==========================================
// 4. SOUMISSION AJAX DU FORMULAIRE
// ==========================================

const giftForm = document.getElementById('giftForm');
if (giftForm) {
    giftForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '...'; 
        btn.disabled = true;

        try {
            await fetch(e.target.getAttribute('action'), { method: 'POST', body: new FormData(e.target) });
            window.location.reload();
        } catch(err) {
            console.error(err);
            btn.innerText = oldText; 
            btn.disabled = false;
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
```

---

### 📄 Fichier : `global.css`
```css
/* global.css */

:root {
  /* Couleurs de base */
  --primary: #3b82f6;
  --primary-dark: #1d4ed8;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;

  /* Textes & Fonds */
  --text-main: #0f172a;
  --text-muted: #64748b;
  --bg-page: #f8fafc;
  --bg-panel: #ffffff;

  /* Bordures, Arrondis & Ombres */
  --border-light: #e2e8f0;
  --radius: 12px;
  --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg:
    0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

/* 🛡️ RESET MOBILE UNIVERSEL */
*,
*::before,
*::after {
  box-sizing: border-box;
}
html,
body {
  margin: 0;
  padding: 0;
  max-width: 100vw;
  overflow-x: hidden;
}

body {
  font-family:
    "Inter",
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    Roboto,
    sans-serif;
  background-color: var(--bg-page);
  color: var(--text-main);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

img,
svg,
video {
  max-width: 100%;
  height: auto;
}
body.no-scroll {
  overflow: hidden !important;
  height: 100vh !important;
}

/* === HEADER & NAVIGATION === */
.pf-header {
  background: white;
  border-bottom: 1px solid var(--border-light);
  padding: 0 20px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
}
.pf-logo {
  font-weight: 800;
  font-size: 1.25rem;
  color: var(--primary);
  text-decoration: none;
  letter-spacing: -0.03em;
}
.pf-nav {
  display: flex;
  gap: 8px;
}
.pf-nav-link {
  text-decoration: none;
  color: var(--text-muted);
  font-weight: 600;
  font-size: 0.9rem;
  padding: 8px 12px;
  border-radius: 8px;
  transition: all 0.2s;
}
.pf-nav-link:hover {
  background: #f1f5f9;
  color: var(--text-main);
}
.pf-nav-link--active {
  background: #eff6ff;
  color: var(--primary);
}
.pf-header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}
.pf-user-badge {
  font-size: 0.85rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 8px;
}
.pf-logout-btn {
  color: var(--danger);
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid transparent;
}
.pf-logout-btn:hover {
  background: #fef2f2;
  border-color: #fecaca;
}
.pf-burger-btn {
  display: none;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: var(--text-main);
}

.pf-mobile-menu {
  display: none;
  position: fixed;
  top: 64px;
  left: 0;
  right: 0;
  height: calc(100vh - 64px);
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  background: white;
  padding: 20px;
  flex-direction: column;
  gap: 16px;
  z-index: 99;
}
.pf-mobile-menu.is-open {
  display: flex;
}

/* === MAIN CONTENT & TYPOGRAPHY === */
.pf-main {
  flex: 1;
  width: 100%;
  max-width: 1400px;
  margin: 0 auto;
  padding: 32px 20px;
}
.pf-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
}
h1 {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 1rem;
  color: var(--text-main);
  line-height: 1.2;
}
p {
  color: var(--text-muted);
  line-height: 1.6;
  margin-bottom: 1.5rem;
}

/* === COMPOSANTS GLOBAUX === */
.pf-modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
  margin-top: 32px;
}
.pf-module-card {
  background: white;
  border: 1px solid var(--border-light);
  border-radius: var(--radius);
  padding: 24px;
  text-decoration: none;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pf-module-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow);
  border-color: #cbd5e1;
}
.pf-card-icon {
  font-size: 2rem;
  margin-bottom: 4px;
}
.pf-card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-main);
  margin: 0;
}
.pf-card-desc {
  font-size: 0.95rem;
  color: var(--text-muted);
  flex: 1;
}
.pf-card-cta {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Boutons Standards */
.pf-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  padding: 12px 24px;
  text-decoration: none;
}
.pf-btn:hover {
  background: var(--primary-dark);
}
.pf-btn.btn-secondary {
  background: white;
  color: var(--text-muted);
  border: 1px solid var(--border-light);
}
.pf-btn.btn-secondary:hover {
  background: #f8fafc;
  color: var(--text-main);
  border-color: #94a3b8;
}

/* Formulaires & Inputs Standards */
.pf-form-group {
  margin-bottom: 20px;
}
.pf-label {
  display: block;
  font-weight: 600;
  margin-bottom: 8px;
  font-size: 0.9rem;
  color: var(--text-muted);
}
.pf-input {
  width: 100%;
  padding: 12px;
  border: 1px solid var(--border-light);
  border-radius: 8px;
  font-size: 1rem;
  background: #f8fafc;
  color: var(--text-main);
  transition: all 0.2s;
  font-family: inherit;
  box-sizing: border-box;
}
.pf-input:focus {
  background: white;
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Tableaux Standards */
.pf-table {
  width: 100%;
  background: var(--bg-panel);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
}
.pf-table th {
  background: #f8fafc;
  padding: 16px;
  text-align: left;
  font-weight: 600;
  color: var(--text-muted);
  font-size: 0.8rem;
  text-transform: uppercase;
  border-bottom: 1px solid var(--border-light);
  white-space: nowrap;
}
.pf-table td {
  padding: 10px 16px;
  border-bottom: 1px solid var(--bg-page);
  vertical-align: middle;
  font-size: 0.95rem;
}
.pf-table tbody tr:last-child td {
  border-bottom: none;
}
.pf-table tbody tr:hover {
  background-color: #f8fafc;
}

/* === 🪟 LE DESIGN SYSTEM DES MODALES (Applicable partout) === */
.pf-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.pf-modal.is-active,
.pf-modal.open {
  display: flex;
} /* Supporte tes deux syntaxes JS */

.pf-modal-content {
  background: var(--bg-panel);
  width: 100%;
  max-width: 500px;
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  padding: 32px;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 10px;
  animation: pfModalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  margin: auto;
}
.pf-modal-title {
  margin-top: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main);
  border-bottom: 1px solid var(--border-light);
  padding-bottom: 15px;
  margin-bottom: 8px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border-light);
  width: 100%;
  box-sizing: border-box;
}
.modal-footer .pf-btn {
  width: auto;
  min-width: 100px;
  padding: 10px 20px;
} /* Ajustement pour modale */

@keyframes pfModalPop {
  from {
    transform: scale(0.95) translateY(10px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}

/* === FOOTER & TOASTS === */
.pf-footer {
  border-top: 1px solid var(--border-light);
  background: white;
  padding: 24px;
  text-align: center;
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-top: auto;
}
.pf-toast {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 12px 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  border-left: 5px solid var(--primary);
  z-index: 10000;
  transform: translateX(120%);
  transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  font-weight: 600;
  color: var(--text-main);
}
.pf-toast.is-visible {
  transform: translateX(0);
}

/* === RESPONSIVE MOBILE === */
@media (max-width: 768px) {
  .pf-header {
    padding: 0 16px;
  }
  .pf-nav,
  .pf-header-actions {
    display: none;
  }
  .pf-burger-btn {
    display: block;
  }
  .pf-main {
    padding: 16px 10px;
  }
  .pf-mobile-nav-link {
    font-size: 1.2rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text-main);
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light);
  }

  /* 📱 BOTTOM SHEET MODALES MOBILE */
  .pf-modal {
    align-items: center !important; /* On remet au centre */
    padding: 20px;
  }
  .pf-modal-content {
    width: 100% !important;
    max-width: none !important;
    border-radius: 20px !important; /* On garde les arrondis partout */
    transform: none !important;
    animation: pfModalPop 0.3s ease !important; /* On remet l'animation desktop */
    margin: auto !important;
  }
  .pf-modal-content::before {
    content: "";
    display: block;
    width: 40px;
    height: 5px;
    background: #cbd5e1;
    border-radius: 10px;
    margin: -10px auto 20px auto;
  }
  .modal-footer {
    flex-direction: column-reverse;
  }
  .modal-footer .pf-btn {
    width: 100%;
    margin-top: 8px;
  }

  @keyframes slideUpSheet {
    to {
      transform: translateY(0);
    }
  }
}

/* === BOUTONS D'ACTION ICÔNES UNIVERSELS (Édition / Suppression) === */
.btn-icon-action {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
}
.btn-icon-action.edit {
  color: var(--primary);
}
.btn-icon-action.edit:hover {
  background: #eff6ff; /* Fond bleu très clair au survol */
}
.btn-icon-action.delete {
  color: var(--danger);
}
.btn-icon-action.delete:hover {
  background: #fef2f2; /* Fond rouge très clair au survol */
}

/* === 13. PAGE DE CONNEXION (LOGIN) === */
.pf-login-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 200px);
  padding: 20px;
}

.pf-login-card {
  width: 100%;
  max-width: 400px;
  padding: 32px;
  background: white;
  border-radius: 16px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-light);
}

.pf-login-header {
  text-align: center;
  margin-bottom: 24px;
}

.pf-login-header .pf-login-icon {
  width: 60px; /* Tu peux ajuster cette valeur selon le rendu voulu */
  height: auto;
  margin-bottom: 15px;
  border-radius: 12px; /* Optionnel : ajoute un léger arrondi si ton favicon est carré */
}

.pf-login-header h1 {
  font-size: 1.8rem;
  margin: 0;
  color: var(--text-main);
}

.pf-login-header p {
  color: var(--text-muted);
  margin-top: 8px;
  font-size: 0.95rem;
}

.pf-login-error {
  background: #fef2f2;
  color: #ef4444;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #fca5a5;
  margin-bottom: 20px;
  font-size: 0.9rem;
  text-align: center;
  font-weight: 600;
}

.pf-btn-block {
  width: 100%;
  justify-content: center;
  margin-top: 10px;
  padding: 14px;
  font-size: 1.05rem;
}

```

---

### 📄 Fichier : `header.php`
```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Inclusion du moteur de traduction
require_once __DIR__ . '/includes/i18n.php';

/**
 * Génère une URL avec le paramètre de langue mis à jour
 */
function getLangUrl($newLang) {
    $params = $_GET;
    $params['lang'] = $newLang;
    return '?' . http_build_query($params);
}

$pageTitle = $pageTitle ?? "PachaFamily";
$activePage = $activePage ?? "";
$currentLang = $_SESSION['app_lang'] ?? 'fr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/global.css">
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss) ?>">
  <?php endif; ?>
  <style>
    /* Patch CSS pour la zone droite de l'en-tête (Desktop / Mobile) */
    .pf-header-right { display: flex; align-items: center; gap: 15px; }
    @media (max-width: 768px) {
        .pf-desktop-actions { display: none !important; }
    }
  </style>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <header class="pf-header">
    <a href="/index.php" class="pf-logo">PachaFamily</a>

    <?php if (isset($_SESSION['user'])): ?>
    <nav class="pf-nav">
      <a href="/index.php" class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_home') ?></a>
      <a href="/family-calendar.php" class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_calendar') ?></a>
      <a href="/budget.php" class="pf-nav-link <?= $activePage === 'budget' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_budget') ?></a>
      <a href="/holidays.php" class="pf-nav-link <?= $activePage === 'holidays' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_holidays') ?></a>
      <a href="/gift-list.php" class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_gifts') ?></a>
    </nav>
    <?php endif; ?>

    <div class="pf-header-right">
      
      <div style="display: flex; align-items: center; gap: 8px;">
          <a href="<?= getLangUrl('fr') ?>" style="text-decoration:none; font-weight:bold; font-size:1rem; color: <?= $currentLang === 'fr' ? '#2563eb' : '#94a3b8' ?>;" title="Français">FR</a>
          <span style="color: #cbd5e1;">|</span>
          <a href="<?= getLangUrl('ca') ?>" style="text-decoration:none; font-weight:bold; font-size:1rem; color: <?= $currentLang === 'ca' ? '#f59e0b' : '#94a3b8' ?>;" title="Català">CA</a>
      </div>

      <?php if (isset($_SESSION['user'])): ?>
        <div class="pf-desktop-actions" style="display: flex; align-items: center; gap: 10px; border-left: 1px solid #cbd5e1; padding-left: 15px;">
          <div class="pf-user-badge">
            <?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?>
          </div>
          <a href="/logout.php" class="pf-logout-btn"><?= tr('btn_logout') ?></a>
        </div>
        
        <button class="pf-burger-btn" aria-label="<?= tr('aria_menu') ?>" style="margin-left: 5px;">☰</button>
        
      <?php else: ?>
        <a href="/login.php" class="pf-nav-link" style="border-left: 1px solid #cbd5e1; padding-left: 15px; margin-left: 5px;"><?= tr('btn_login') ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (isset($_SESSION['user'])): ?>
  <div class="pf-mobile-menu">
    <a href="/index.php" class="pf-mobile-nav-link"><?= tr('menu_home') ?></a>
    <a href="/family-calendar.php" class="pf-mobile-nav-link"><?= tr('menu_calendar') ?></a>
    <a href="/budget.php" class="pf-mobile-nav-link"><?= tr('menu_budget') ?></a>
    <a href="/holidays.php" class="pf-mobile-nav-link"><?= tr('menu_holidays') ?></a>
    <a href="/gift-list.php" class="pf-mobile-nav-link"><?= tr('menu_gifts') ?></a>
    <hr style="width: 80%; border: 0; border-top: 1px solid #eee; margin: 10px 0;">
    <a href="/logout.php" class="pf-mobile-nav-link pf-mobile-logout"><?= tr('btn_logout') ?></a>
  </div>
  <?php endif; ?>

  <main class="pf-main">

  <script>
    /**
     * Pont d'Internationalisation et Configuration
     */
    window.I18N = <?php echo json_encode($current_translations_array ?? []); ?>;

    // ✅ Injection sécurisée (évite le crash si config.php n'est pas chargé)
    window.CONFIG = {
        ID_ALEX: <?php echo defined('ID_ALEX') ? ID_ALEX : 2; ?>,
        ID_LAIA: <?php echo defined('ID_LAIA') ? ID_LAIA : 3; ?>,
        CURRENCY: '<?php echo defined('CURRENCY') ? CURRENCY : "€"; ?>'
    };    
    
    function tr(key) {
        return window.I18N[key] || key;
    }

    // Gestion du menu mobile
    <?php if (isset($_SESSION['user'])): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const burgerBtn = document.querySelector('.pf-burger-btn');
        const mobileMenu = document.querySelector('.pf-mobile-menu');
        
        if(burgerBtn && mobileMenu) {
            burgerBtn.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.toggle('is-open');
                burgerBtn.textContent = isOpen ? '✕' : '☰';
                document.body.classList.toggle('no-scroll', isOpen);
            });
        }
    });
    <?php endif; ?>

    /**
     * pachaFetch : Utilitaire de requête robuste
     */
    async function pachaFetch(url, options = {}) {
        const finalUrl = url.startsWith('/') ? url.substring(1) : url;

        options.credentials = 'same-origin'; 
        options.headers = {
            ...options.headers,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        try {
            const response = await fetch(finalUrl, options);
            const rawText = await response.text();
            
            try {
                return JSON.parse(rawText);
            } catch (jsonErr) {
                console.error("Réponse corrompue (HTML reçu au lieu de JSON) :", rawText);
                throw new Error("Erreur serveur : format JSON invalide.");
            }
        } catch (err) {
            console.error(`Erreur pachaFetch [${finalUrl}] :`, err);
            throw err;
        }
    }

    /**
 * UI Utility : Toasts (Notifications)
 */
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `pf-toast pf-toast--${type}`;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    // Animation et suppression
    setTimeout(() => toast.classList.add('is-visible'), 100);
    setTimeout(() => {
        toast.classList.remove('is-visible');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * UI Utility : Confirmation stylisée (Remplace confirm())
 */
async function pachaConfirm(title, message) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'pf-modal open';
        modal.innerHTML = `
            <div class="pf-modal-content" style="max-width: 400px; align-self: center;">
                <h3 style="margin-top:0;">${title}</h3>
                <p style="color:var(--text-muted);">${message}</p>
                <div class="modal-footer">
                    <button class="pf-btn btn-secondary" id="confirm-cancel">${tr('btn_cancel')}</button>
                    <button class="pf-btn" id="confirm-ok" style="background:var(--danger);">${tr('btn_delete')}</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        document.getElementById('confirm-cancel').onclick = () => { modal.remove(); resolve(false); };
        document.getElementById('confirm-ok').onclick = () => { modal.remove(); resolve(true); };
    });
}
</script>
```

---

### 📄 Fichier : `holidays.php`
```php
<?php
// holidays.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; // Toujours s'assurer de l'accès à tr()

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. On récupère la vue demandée (par défaut 'list')
$tab = $_GET['tab'] ?? 'list';

// 2. Configuration des variables pour le header (Titres traduits)
$pageTitle  = ($tab === 'holiday_detail') 
    ? tr('hdl_title_detail') 
    : tr('hdl_title_list');
    
$activePage = "holidays";
$bodyClass  = "pf-holidays";
$pageCss    = "/modules/holidays/holidays.css";

require __DIR__ . '/header.php';

// 3. ROUTEUR DU MODULE VACANCES
if ($tab === 'holiday_detail' && isset($_GET['id'])) {
    // Si on demande le détail ET qu'un ID est fourni
    require __DIR__ . '/modules/holidays/views/detail.php';
} else {
    // Vue par défaut : la liste des cartes
    require __DIR__ . '/modules/holidays/views/list.php';
}

// 4. Inclusion du JS global du module (Pont i18n déjà géré dans le header)
echo '<script src="/modules/holidays/holidays.js"></script>';

require __DIR__ . '/footer.php';
```

---

### 📄 Fichier : `includes/auth.php`
```php
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie qu'un utilisateur est connecté.
 * Si non, le redirige vers la page de login avec ?redirect=URL_DEMANDEE
 *
 * @param string|null $loginPage URL de la page de login (par défaut /login.php)
 */
function require_login(?string $loginPage = '/login.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }

        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $redirectParam = urlencode($currentUrl);
        $target = ($loginPage ?? '/login.php') . '?redirect=' . $redirectParam;

        header('Location: ' . $target);
        exit;
    }
}

```

---

### 📄 Fichier : `includes/config.php`
```php
<?php
// PachaFamily Config - Version sans erreur
if (!defined('ID_ALEX')) {
    define('ID_ALEX', 2);
    define('ID_LAIA', 3);
    define('CURRENCY', '€');
    define('ZONE_SCOLAIRE', 'C');
}
```

---

### 📄 Fichier : `includes/db.php`
```php
<?php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
// Activer erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Détection de l'environnement
$isLocal = (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false ||
           strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
           strpos($_SERVER['HTTP_HOST'] ?? '', '::1') !== false);

if ($isLocal) {
    // Configuration XAMPP local
    $host = 'localhost';
    $db   = 'percolo314';        // Même nom que sur OVH pour simplifier
    $user = 'root';
    $pass = '';                  // Généralement vide sur XAMPP
} else {
    // Configuration serveur OVH
    $host = 'percolo314.mysql.db';
    $db   = 'percolo314';
    $user = 'percolo314';
    $pass = 'Wxcvbn99';
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
    PDO::ATTR_TIMEOUT            => 30,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Forcer la collation
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
    $pdo->exec("SET collation_database = utf8mb4_general_ci");
    $pdo->exec("SET collation_server = utf8mb4_general_ci");
    
    // Debug optionnel (à commenter en production)
    // $env = $isLocal ? 'LOCAL' : 'SERVEUR';
    // echo "<!-- Connecté en $env sur $host/$db -->";
    
} catch (\PDOException $e) {
    $environment = $isLocal ? 'local (XAMPP)' : 'serveur (OVH)';
    die("Erreur de connexion ($environment) : " . $e->getMessage());
}
?>
```

---

### 📄 Fichier : `includes/i18n.php`
```php
<?php
// Démarrer la session si ce n'est pas déjà fait (souvent fait dans auth.php, mais on sécurise)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Changement de langue si l'utilisateur a cliqué sur un drapeau (?lang=ca ou ?lang=fr)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ca'])) {
    $_SESSION['app_lang'] = $_GET['lang'];
}

// 2. Langue par défaut = Français
$current_lang = $_SESSION['app_lang'] ?? 'fr';

// 3. Chargement du bon dictionnaire
$lang_file = __DIR__ . '/lang/' . $current_lang . '.php';
if (file_exists($lang_file)) {
    $GLOBALS['translations'] = include $lang_file;
} else {
    $GLOBALS['translations'] = [];
}

// 4. La fonction magique de traduction
// Si le mot existe dans le dictionnaire on l'affiche, sinon on affiche la clé par défaut
if (!function_exists('tr')) {
    function tr($key) {
        return $GLOBALS['translations'][$key] ?? $key;
    }
}
```

---

### 📄 Fichier : `includes/lang/ca.php`
```php
<?php
// Dictionnaire Catalan
return [
    // ==========================================
    // TEST AUTO
    // ==========================================
    'test_running' => 'Proves en curs...',
    'test_passed' => 'Prova superada',
    'test_failed' => 'Prova fallida',
    'tests_title' => 'Laboratori de Proves',
    'tests_run_budget' => 'Provar el Pressupost',
    'tests_run_all' => 'Iniciar totes les proves',
    'tests_copy_report' => 'Copiar l\'informe',
    'tests_waiting' => 'Esperant l\'inici...',
    'tests_report_copied' => 'Informe copiat al porta-retalls!',
    'tests_live_preview'  => 'Previsualització en viu',
    // ==========================================
    // GLOBAL & NAVIGATION
    // ==========================================
    'menu_home'         => 'Inici',
    'menu_calendar'     => 'Calendari',
    'menu_budget'       => 'Pressupost',
    'menu_holidays'     => 'Viatges',
    'menu_gifts'        => 'Regals',
    'nav_home'          => 'Inici',
    'nav_calendar'      => 'Calendari',
    'nav_budget'        => 'Pressupost',
    'nav_holidays'      => 'Viatges',
    'nav_gifts'         => 'Regals',
    'aria_menu'         => 'Menú mòbil',

    // Boutons et actions globales
    'actions'           => 'Accions',
    'edit'              => 'Modificar',
    'delete'            => 'Suprimir',
    'save'              => 'Desar',
    'cancel'            => 'Cancel·lar',
    'btn_save'          => 'Guardar',
    'btn_cancel'        => 'Cancel·lar',
    'btn_delete'        => 'Eliminar',
    'btn_close'         => 'Tancar',
    'btn_back'          => '◀ Tornar',
    'btn_edit_bases'    => '⚙️ Modificar les bases',
    'btn_edit'          => 'Modificar',
    'error_occured'     => 'S\'ha produït un error.',
    
    // Mots génériques & Erreurs
    'error'             => 'Error.',
    'confirm_delete'    => 'Estàs segur que vols suprimir aquest element?',

    // Mois & Jours
    'month_01' => 'Gener', 'month_02' => 'Febrer', 'month_03' => 'Març',
    'month_04' => 'Abril', 'month_05' => 'Maig',   'month_06' => 'Juny',
    'month_07' => 'Juliol','month_08' => 'Agost',  'month_09' => 'Setembre',
    'month_10' => 'Octubre','month_11' => 'Novembre','month_12' => 'Desembre',
    'day_mon'  => 'Dl', 'day_tue' => 'Dt', 'day_wed' => 'Dc', 
    'day_thu'  => 'Dj', 'day_fri' => 'Dv', 'day_sat' => 'Ds', 'day_sun' => 'Dg',

    // ==========================================
    // AUTHENTIFICATION / LOGIN
    // ==========================================
    'login_title'               => 'Connexió - PachaFamily',
    'login_header'              => 'Connexió',
    'label_username'            => "Nom d'usuari",
    'label_password'            => 'Contrasenya',
    'placeholder_username'      => 'ex: pacha',
    'btn_login'                 => 'Connexió',
    'btn_login_submit'          => 'Connectar-se',
    'logout'                    => 'Tancar sessió',
    'btn_logout'                => 'Tancar sessió',
    'error_missing_fields'      => 'Camps obligatoris.',
    'error_invalid_credentials' => 'Identificadors incorrectes.',
    'login_subtitle'      => 'Si us plau, identifiqueu-vos per continuar',

    // ==========================================
    // ACCUEIL / INDEX
    // ==========================================
    'home_title'         => 'PachaFamily - Inici',
    'home_welcome'       => 'Benvinguts a PachaFamily',
    'home_subtitle'      => "Centre de control de l'organització familiar.",
    'home_logged_as'     => 'Connectat com a',
    'home_modules_title' => 'Mòduls',
    'mod_calendar_name'  => 'Calendari Familiar',
    'mod_calendar_desc'  => "Gestioneu les vacances, les modalitats de cura i l'organització setmanal.",
    'mod_holidays_name'  => 'Vacances',
    'mod_holidays_desc'  => 'Planifiqueu les vostres properes escapades, destinacions i idees de viatge.',
    'mod_gifts_name'     => 'Regals',
    'mod_gifts_desc'     => "Planifiqueu els regals per a cada infant, per Nadal i els aniversaris.",
    'mod_budget_name'    => 'Pressupost',
    'mod_budget_desc'    => 'Seguiment de despeses fixes, ingressos i balanç del compte familiar.',
    'cta_open'           => 'Obrir',
    'cta_explore'        => 'Explorar',
    'cta_view_lists'     => 'Veure les llistes',
    'cta_manage'         => 'Gestionar',

    // ==========================================
    // FAMILY CALENDAR
    // ==========================================
    'fc_page_title'             => 'PachaFamily - Calendari',
    'fc_main_header'            => 'Calendari Familiar',
    'fc_btn_correct_balances'   => 'Corregir els saldos',
    'fc_btn_school_holidays'    => 'Vacances escolars',
    'fc_modal_holidays_title'   => 'Vacances Escolars (Zona C)',
    'fc_modal_snap_title'       => 'Ajustar un saldo',
    'fc_col_period'             => 'Període',
    'fc_col_from'               => 'Des del',
    'fc_col_to'                 => 'Fins al',
    'fc_label_person'           => 'Persona',
    'fc_label_leave_type'       => 'Tipus de permís',
    'fc_label_apply_date'       => "Data d'aplicació",
    'fc_snap_date_hint'         => "Trieu preferiblement el primer dia d'un mes",
    'fc_label_remaining_balance'=> 'Saldo restant en aquesta data',
    'fc_placeholder_balance'    => 'ex: 12.5',
    'fc_btn_save_snap'          => 'Desar el correctiu',
    'fc_view_1m'                => '1 mes',
    'fc_view_2m'                => '2 mesos',
    'fc_view_3m'                => '3 mesos',
    'fc_view_year'              => 'Any',
    'fc_weekly_planning'        => 'Planificació setmanal',
    'fc_col_month'              => 'Mes',
    'fc_col_week_short'         => 'Set.',
    'fc_col_av'                 => 'Av.',
    'fc_col_use'                => 'Use',
    'fc_legend_title'           => 'Llegenda',
    'fc_summary_title'          => 'Resum',
    'fc_summ_year'              => 'Any',
    'fc_summ_month'             => 'Mes',
    'fc_school_year'            => 'Curs escolar',
    'fc_unit_days'              => 'dies',
    'fc_menu_carole'            => 'Permisos Carole',
    'fc_clear'                  => 'Esborrar',
    'fc_menu_kids_leaves'       => 'Permisos nens',
    'fc_err_save_snap'          => 'Error en desar el correctiu.',
    'fc_err_fetch_gov'          => 'Error en la connexió amb l’API governamental.',
    'fc_err_no_data_gov'        => 'No s’han trobat dades a l’API del govern per a aquest any.',
    'fc_err_action'             => 'Error en l’acció: ',
    'leg_presence'              => 'Presència Pep',
    
    'leg_school_holidays'       => 'Vacances Escolars',
    'leg_public_holiday'        => 'Festiu',
    'leg_off_carole'            => 'Off Carole',
    'leg_extra_off'             => 'Extra Off',
    'leg_centre'                => 'Casal',
    'leg_avis'                  => 'Avís',
    'leg_pep_sick'              => 'Pep Malalt',
    
    'vac_toussaint'             => 'Vacances de Tots Sants',
    'vac_noel'                  => 'Vacances de Nadal',
    'vac_hiver'                 => "Vacances d'Hivern",
    'vac_printemps'             => 'Vacances de Primavera',
    'vac_ascension'             => "Pont de l'Ascensió",
    'vac_ete'                   => "Vacances d'Estiu",
    'btn_off'                   => 'Off',
    'btn_extra'                 => 'Extra',

    'fc_alert_burn_days' => '⚠️ Risc de pèrdua : %s d a gastar abans del %s',
    'fc_alert_burn_jra'  => '⚠️ JRA: Només 2d es poden reportar després de febrer!',

    // ==========================================
    // HOLIDAYS (VOYAGES)
    // ==========================================
    'hdl_title_list'            => 'PachaFamily - Les meves vacances',
    'hdl_title_detail'          => 'PachaFamily - Detall del viatge',
    'hdl_main_title'            => 'Les meves vacances',
    'hdl_filter_all'            => 'Mostrar-ho tot',
    'hdl_filter_year'           => 'Any',
    'hdl_left_to_pay_label'     => 'Resta per pagar',
    'hdl_btn_create'            => 'Crear un viatge',
    'hdl_no_active_trips'       => 'Cap viatge en curs per aquest període. Planifiquem alguna cosa!',
    'hdl_history_title'         => 'Historial',
    'hdl_dates_to_define'       => 'Dates per definir',
    'hdl_total_budget'          => 'Pressupost Total',
    'hdl_paid'                  => 'Pagat',
    'hdl_saved'                 => 'Finançat',
    'hdl_left_to_pay'           => 'Resta per pagar',
    'hdl_status_booked'         => 'Confirmat',
    'hdl_status_planned'        => 'Planificat',
    'hdl_status_draft'          => 'Esborrany',
    'hdl_status_passed'         => 'Passat',
    'hdl_status_archived'       => 'Arxivat',
    'hdl_modal_title'           => 'Planificar el viatge',
    'hdl_label_name'            => 'Nom del viatge',
    'hdl_ph_name'               => 'Ex: Octubre - Portugal',
    'hdl_label_status'          => 'Estat',
    'hdl_label_period'          => 'Període (Text lliure)',
    'hdl_ph_period'             => 'Ex: Octubre 2026',
    'hdl_label_from'            => 'Des del',
    'hdl_label_to'              => 'Fins al',
    'hdl_cat_transport'         => 'Transport',
    'hdl_add_transport'         => 'Afegir un transport',
    'hdl_cat_accommodation'     => 'Allotjament',
    'hdl_add_accommodation'     => 'Afegir un allotjament',
    'hdl_cat_activity'          => 'Activitat',
    'hdl_add_activity'          => 'Afegir una activitat',
    'hdl_label_budget_food'     => 'Pressupost Menjar i Beguda (€)',
    'hdl_label_budget_extras'   => 'Pressupost Extras (€)',
    'hdl_label_notes'           => 'Notes',
    'hdl_ph_notes'              => 'Idees soltes...',
    'hdl_label_budget_food_extras' => 'Pressupost Menjar i Extres',
    'hdl_map_itinerary'         => 'Itinerari',
    'hdl_outbound'              => 'Anada',
    'hdl_return'                => 'Tornada',
    'hdl_btn_add_step'          => 'Afegir una etapa',
    'hdl_step_details'          => 'Detalls del roadtrip',
    'hdl_no_steps'              => 'Cap etapa definida. Comenceu per afegir-ne una!',
    'hdl_general_costs'         => 'Despeses generals i Pressupostos',
    'hdl_food_bev'              => 'Menjar i Beguda',
    'hdl_extras'                => 'Extres',
    'hdl_view_planning'         => 'Veure la planificació',
    'hdl_passing_point'         => 'Punt de pas (sense despeses)',
    'hdl_search_location'       => 'Cercar un lloc',
    'hdl_ph_search'             => 'Ciutat, hotel, adreça...',
    'hdl_label_step_name'       => "Nom de l'etapa",
    'hdl_label_arrival'         => 'Arribada',
    'hdl_label_departure'       => 'Sortida',
    'hdl_planned_expenses'      => 'Despeses previstes per a aquesta etapa',
    'hdl_btn_add_expense'       => '+ Afegir una despesa',
    'hdl_save_fav'              => 'Desar als meus preferits',
    'hdl_planning_title'        => 'Planificació de l’etapa',
    'hdl_to_pay'                => 'Per pagar',
    'hdl_js_search_loading'     => 'Cerca en curs...',
    'hdl_js_no_result'          => 'No s’han trobat resultats.',
    'hdl_js_network_error'      => 'Error de connexió al servei de mapes.',
    'hdl_js_confirm_del_trip'   => 'Estàs segur que vols suprimir aquest viatge sencer?',
    'hdl_js_confirm_del_step'   => 'Suprimir aquesta etapa?',
    'hdl_js_step_label'         => 'Etapa',
    'hdl_js_ph_expense_name'    => 'Ex: Hotel, Peatge...',
    'hdl_quick_edit_title'      => 'Modificació ràpida',
    'hdl_js_edit_step'          => 'Modificar l’etapa',
    'hdl_js_missing_dates_title'=> 'Dates mancants',
    'hdl_js_missing_dates_msg'  => 'Cal definir una data d’arribada i sortida per organitzar la planificació.',
    'hdl_to_place'              => 'Per situar',
    'hdl_from'                  => 'Des del',
    'hdl_to'                    => 'Fins al',
    'hdl_btn_add_step'          => 'Afegir una etapa',
    'hdl_quick_edit_title'      => 'Modificació ràpida',
    'hdl_paid'                  => 'Pagat',
    'weather_forecast' => 'Previsions meteorològiques',
    'weather_not_available' => 'Temps no disponible',
    'temp_max' => 'Temp. Màxima',
    'weather_loading' => 'Carregant el temps...',
    'weather_error'   => 'Temps no disponible',
    'weather_sunny'   => 'Assolellat',
    'weather_cloudy'  => 'Ennuvolat',
    'weather_rainy'   => 'Plujós',
    'weather_snowy'   => 'Nevat',
    'weather_historical' => 'Basat en l\'històric (estimació)',
    'err_invalid_holiday_id' => 'ID de viatge no vàlid.',
    'hdl_default_exp_name'   => 'Despesa',
    'hdl_select_beneficiary' => '-- Seleccionar --',

    // Anciennes clés Holidays non préfixées (conservades)
    'outbound_trip'             => 'Anada',
    'return_trip'               => 'Tornada',
    'period'                    => 'Període',
    'budget_food'               => 'Pressupost Menjar i Extres',
    'total_cost'                => 'Cost Total Estimat',
    'paid'                      => 'Pagat',
    'provisioned'               => 'Provisionat',
    'left_to_pay'               => 'Pendent de pagament',
    'map_itinerary'             => '🗺️ Itinerari',
    'place_step'                => '📍 Afegir una etapa',
    'step_details'              => '📝 Detall de les etapes',
    'no_steps'                  => 'Cap etapa planificada.',
    'travel_notes'              => '📝 Notes del viatge',
    'to_be_defined'             => 'A definir',
    'funded'                    => 'Finançat (Provisió)',
    'view_planning'             => 'Veure el planning',
    'to_pay'                    => 'A pagar',
    'from_date'                 => 'Del',
    'to_date'                   => 'al',
    'passing_point'             => 'Punt de pas (Cap despesa)',
    'search_location'           => 'Cercar una ubicació geogràfica',
    'search_placeholder'        => 'Ex: París, Ibis Barcelona...',
    'step_name'                 => 'Nom de l\'etapa',
    'arrival_optional'          => 'Arribada el (Opcional)',
    'departure_optional'        => 'Sortida el (Opcional)',
    'planned_expenses'          => 'Despeses previstes',
    'add_expense'               => '+ Afegir una despesa',
    'save_favorite'             => 'Guardar als meus preferits',
    'planning_title'            => 'Planning',
    'modal_plan_trip'           => 'Planificar el viatge',
    'modal_quick_edit'          => 'Edició ràpida',
    'err_modal_missing'         => 'Error: La modal holidayModal no es troba.',
    'placeholder_title'         => 'Títol',
    'placeholder_price'         => 'Preu (€)',
    'already_paid'              => 'Ja pagat?',
    'remove_line'               => 'Treure aquesta línia',
    'confirm_delete_trip'       => 'Vols eliminar definitivament aquest viatge? Aquesta acció és irreversible.',
    'step_number'               => 'Etapa',
    'place_new_step'            => '📍 Afegir una nova etapa',
    'edit_step'                 => '✏️ Modificar l\'etapa',
    'search_in_progress'        => 'Cerca en curs... ⏳',
    'no_result_found'           => 'Cap resultat trobat.',
    'network_error'             => 'Error de xarxa.',
    'placeholder_label'         => 'Etiqueta',
    'placeholder_notes_link'    => '🔗 Enllaç de reserva o notes (Opcional)...',
    'confirm_delete_step'       => 'Eliminar aquesta etapa i totes les despeses associades?',
    'planning_of'               => '📅 Planning : ',
    'missing_dates_title'       => '⚠️ Dates que falten',
    'missing_dates_msg'         => 'Si us plau, modifica aquesta etapa (✏️) i omple la seva <strong>Data d\'arribada i de sortida</strong>.',
    'to_place'                  => '📥 A col·locar',

    // ==========================================
    // BUDGET (GLOBAL & CATEGORIES)
    // ==========================================
    'budget_page_title'         => 'PachaFamily - Pressupost',
    'budget_main_header'        => 'Gestió del Pressupost',
    'budget_tab_tracking'       => 'Seguiment Mensual',
    'budget_tab_prev'           => 'Pressupost 2026',
    'budget_tab_savings'        => 'Estalvis',
    'budget_tab_recap'          => 'Resum',
    'budget_err_file_not_found' => 'Vaja! Fitxer no trobat.',
    'budget_err_file_detail'    => 'Comproveu que el fitxer views/%s.php existeix.',
    'budget_err_not_authorized' => 'Pàgina no autoritzada.',
    'cat_income'                => 'Ingressos',
    'cat_fmcg'                  => 'Compres (FMCG)',
    'cat_fuel'                  => 'Benzina',
    'cat_school'                => 'Escola / Llar d’infants',
    'cat_fixed'                 => 'Despeses Fixes',
    'cat_others'                => 'Altres / Imprevistos',
    'cat_savings'               => 'Estalvis',

    // --- BUDGET : SUIVI ---
    'bud_rem_school'            => 'Escola (Resta estimada)',
    'bud_rem_fmcg'              => 'Compres (Resta estimada)',
    'bud_rem_fuel'              => 'Benzina (Resta estimada)',
    'bud_closed_archived'       => 'Aquest mes està tancat i arxivat.',
    'bud_budget_of'             => 'Pressupost:',
    'bud_confirm_close'         => 'Tancar aquest mes? Això congelarà els saldos actuals i canviarà el seguiment per defecte al mes vinent.',
    'bud_close_btn'             => 'Tancar',
    'bud_confirm_reopen'        => 'Reobrir aquest mes? Els seus saldos es recalcularan en directe.',
    'bud_reopen_btn'            => 'Reobrir',
    'bud_active_month_btn'      => 'Mes Actiu',
    'bud_bank_balance'          => 'Saldo bancari',
    'bud_frozen'                => 'Congelat',
    'bud_current'               => 'Actual',
    'bud_update_btn'            => 'Act.',
    'bud_theoretical_balance'   => 'Saldo teòric final de mes',
    'bud_upcoming_charges'      => 'Càrrecs pendents',
    'bud_all_paid'              => 'Tot està pagat!',
    'bud_capacity_tooltip'      => 'Càlcul: Report Mes Anterior (%s€) + Sous (%s€) + Altres ingressos (%s€)',
    'bud_max_capacity'          => 'Capacitat Màx',
    'bud_actual'                => 'Actual',
    'bud_theoretical'           => 'Teòric',
    'bud_import_csv'            => 'Importar CSV',
    'bud_csv_file'              => 'Fitxer CSV (Punt i coma)',
    'bud_preview'               => 'Previsualitzar',
    'bud_validate_import'       => 'Validar importació',
    'bud_assign_month'          => 'Assignar al mes',
    'bud_op_date'               => 'Data Operació',
    'bud_label'                 => 'Concepte',
    'bud_amount'                => 'Import',
    'bud_category'              => 'Categoria',
    'bud_ignore'                => 'Ignorar',
    'bud_to_define'             => 'Per definir',
    'bud_is_charge'             => 'Despesa?',
    'bud_is_income'             => 'Ingrés?',
    'btn_import'                => 'Importar',
    'bud_no_lines'              => 'Cap línia.',
    'bud_confirm_delete'        => 'Suprimir aquesta línia?',
    'bud_new_transaction'       => 'Nova transacció',
    'bud_assign_to_month'       => 'Assignar al mes de gestió',
    'bud_real_date'             => 'Data real de l\'operació',
    'bud_type'                  => 'Tipus',
    'bud_type_expense'          => 'Despesa',
    'bud_type_income'           => 'Ingrés / Reemborsament',
    'bud_beneficiary'           => 'Beneficiari',
    'bud_fixed_charge'          => 'Despesa Fixa',
    'bud_expected_income'       => 'Ingrés Esperat',
    'bud_amount_eur'            => 'Import (€)',
    'bud_update_balance'        => 'Act. Saldo Bancari',
    'bud_balance_eur'           => 'Saldo (€)',
    'bud_add_title'             => 'Afegir',
    'bud_edit_title'            => 'Modificar la transacció',
    'bud_to_define_js'          => 'per definir',
    'bud_bar_actual' => "Actual",
    'bud_bar_theoretical' => "Teòric",
    'bud_bar_max_cap' => "Capacitat Màx.",
    // --- MODULE BUDGET : IMPORT CSV ---
    'bud_import_csv' => "Importar CSV",
    'bud_csv_file' => "Seleccioneu el fitxer del vostre banc (.csv)",
    'bud_preview' => "Previsualitzar",
    'bud_validate_import' => "Si us plau, comproveu i categoritzeu les línies abans de validar.",
    'bud_assign_month' => "Mes de gestió",
    'bud_op_date' => "Data",
    'bud_amount' => "Import",
    'bud_ignore' => "Ignorar (Crèdit)",
    'bud_to_define' => "Per definir",
    'bud_is_charge' => "És un càrrec fix...",
    'bud_is_income' => "És un ingrés...",

    // --- BUDGET : RECAP ---
    'bud_recap_monthly_title'   => 'Resum Mensual',
    'bud_recap_btn_add'         => 'Afegir una despesa / ingrés',
    'bud_planned_amount'        => 'Import Previst',
    'bud_day'                   => 'Dia',
    'bud_month_state'           => 'Estat del mes',
    'bud_est_short'             => 'Est.',
    'bud_reg_planned_in'        => 'Regularització prevista al %s',
    'bud_bonus'                 => 'Bonificació',
    'bud_overrun'               => 'Excés',
    'bud_remaining'             => 'Resta',
    'bud_exact_amount'          => 'Import exacte',
    'bud_per_month_short'       => 'És a dir',
    'bud_month_short'           => 'mes',
    'bud_freq_mensuel'          => 'Mensual',
    'bud_freq_annuel'           => 'Anual',
    'bud_state_validated'       => 'Validat',
    'bud_state_partial'         => 'Parcial',
    'bud_state_waiting'         => 'En espera',
    'bud_total_income_smoothed' => 'Total Ingressos (Prorratejats)',
    'bud_total_expenses_smoothed'=> 'Total Despeses (Prorratejades)',
    'bud_theoretical_balance_recap'=> 'Equilibri teòric',
    'bud_recap_footer_note'     => 'L\'estat del pagament s\'actualitza automàticament en funció de les operacions importades a la pestanya "Seguiment Mensual".',
    'bud_recap_modal_add'       => 'Afegir un element',
    'bud_recap_modal_edit'      => 'Modificar',
    'bud_label_name'            => 'Nom',
    'bud_ph_item_name'          => 'Ex: Lloguer, Sou...',
    'bud_label_keywords'        => 'Paraules clau (Detecció Auto)',
    'bud_ph_keywords'           => 'Ex: NETFLIX, PRIME VIDEO (separades per coma)',
    'bud_help_keywords'         => 'Si una despesa del mes conté aquesta paraula, la línia passarà a "Validat".',
    'bud_label_day'             => 'Dia (1-31)',
    'bud_cat_expense'           => 'Despesa',
    'bud_label_frequency'       => 'Freqüència',
    'bud_label_amount_type'     => 'Tipus d\'import',
    'bud_type_fixed'            => 'Fix (Factura)',
    'bud_type_variable'         => 'Variable (Estimació)',
    'bud_label_regularization'  => 'Regularització',
    'bud_reg_none'              => 'Cap',
    'bud_recap_confirm_delete'  => 'Segur que vols suprimir aquest element?',
    'bud_err_delete'            => 'Error en suprimir.',
    'bud_sav_modal_title_add'   => 'Introduir un mes',
    'bud_sav_ph_name'           => 'Nom (ex: Vacances)',
    'bud_prev_label_name'       => 'Nom',

    // --- BUDGET : EPARGNE ---
    'bud_sav_add_month'            => 'Introduir un mes',
    'bud_sav_add_one_month'        => '+1 Mes',
    'bud_sav_no_data'              => 'Cap dada per a %s.',
    'bud_sav_post_month'           => 'Concepte / Mes',
    'bud_sav_from_date'            => 'Des del %s',
    'bud_sav_edit_modal'           => 'Modificar amb Modal',
    'bud_sav_delete_month'         => 'Suprimir tot el mes',
    'bud_sav_total_bank'           => 'Total Banc',
    'bud_sav_extra'                => 'Extra',
    'bud_sav_modal_title_add'      => 'Introduir un mes',
    'bud_sav_modal_title_edit'     => 'Modificar:',
    'bud_sav_month_concerned'      => 'Mes afectat',
    'bud_sav_total_bank_eur'       => 'Total al Banc (€)',
    'bud_sav_ventilation'          => 'Desglossament',
    'bud_sav_adj_help'             => 'Utilitzeu l\'ajust (+/-) per recalcular automàticament.',
    'bud_sav_add_line'             => 'Línia',
    'bud_sav_current'              => 'Actual',
    'bud_sav_adjust'               => 'Ajust (+/-)',
    'bud_sav_new'                  => 'Nou',
    'bud_sav_sum_mode_title'       => 'Activar la suma ràpida',
    'bud_sav_selection'            => 'Selecció:',
    'bud_sav_ph_name'              => 'Nom (ex: Vacances)',
    'bud_sav_saving'               => 'Desant...',
    'bud_err_tech'                 => 'S\'ha produït un error tècnic.',
    'bud_sav_confirm_delete_month' => 'Suprimir TOT el mes de %m per a %o?',
    'bud_sav_prompt_duplicate'     => "Vols copiar les dades de %s a %t1?\n\nIntrodueix el nou TOTAL al banc (€) per a %t2:",
    'bud_err_server'               => 'Error del servidor: ',
    'bud_err_network_dup'          => 'Error de xarxa en duplicar.',

    // --- BUDGET : PREVISIONNEL ---
    'bud_prev_incomes'           => 'Ingressos',
    'bud_prev_person'            => 'Persona',
    'bud_prev_salary'            => 'Sou',
    'bud_prev_monthly_payment'   => 'Mensualitat',
    'bud_prev_joint_account'     => 'Cte Comú',
    'bud_prev_func_expenses'     => 'Desp. Func.',
    'bud_prev_perso_savings'     => 'Est. Perso',
    'bud_prev_family_savings'    => 'Est. Família',
    'bud_prev_available'         => 'Dispo',
    'bud_prev_perso_remaining'   => 'Restant Perso',
    'bud_prev_budget_alloc'      => 'Distribució Pressupostària',
    'bud_prev_today'             => 'Avui',
    'bud_prev_new_line'          => 'Nova Línia',
    'bud_prev_remaining'         => 'Restant',
    'bud_prev_confirm_del_line'  => 'Suprimir aquesta línia i tot el seu historial?',
    'bud_prev_notes_for'         => 'Notes per a',
    'bud_prev_saved'             => 'Desat',
    'bud_prev_notes_ph'          => 'Escriu les teves observacions, recordatoris o comentaris per a aquest mes aquí...',
    'bud_prev_save_note'         => 'Desar la nota',
    'bud_prev_transfers_to_make' => 'Transferències a fer',
    'bud_prev_destination'       => 'Destinació',
    'bud_prev_done'              => 'FET',
    'bud_prev_validate'          => 'Validar',
    'bud_prev_grand_total'       => 'GRAN TOTAL',
    'bud_prev_new_line_title'    => 'Nova Línia de Pressupost',
    'bud_prev_edit_line_title'   => 'Modificar la línia',
    'bud_prev_label_target'      => 'Objectiu (Destinació)',
    'bud_prev_choose'            => 'Tria',
    'bud_prev_link_holiday'      => 'Enllaçar a un viatge (Opcional)',
    'bud_prev_no_link'           => 'No associar',
    'bud_prev_err_no_history'    => 'Impossible: no hi ha historial disponible per copiar.',
    'bud_prev_confirm_copy'      => "Vols copiar les dades de %s a %t?\n\n⚠️ Això sobreescriurà tots els valors ja presents per a %t.",
    'bud_prev_confirm_transfers' => "Confirmes que %p ha fet totes les transferències per a %m?\n\nAixò actualitzarà l'Estalvi automàticament.",

    // ==========================================
    // CADEAUX (GIFTS)
    // ==========================================
    'gift_page_title'        => 'PachaFamily - Llista de regals',
    'gift_occ_tio'           => 'Tió',
    'gift_occ_noel'          => 'Nadal',
    'gift_occ_rois'          => 'Reis',
    'gift_occ_anniv'         => 'Aniversari',
    'gift_occ_sant'          => 'Sant',
    'gift_main_title'        => 'Llista de regals %s',
    'gift_aria_change_view'  => 'Canvia la vista',
    'gift_view_nadal'        => 'Nadal',
    'gift_view_anniv'        => 'Aniversaris',
    'gift_view_by_party'     => 'Vista per festa',
    'gift_no_gifts'          => 'No hi ha cap regal registrat per a %s en aquesta vista.',
    'gift_add_gift'          => 'Afegeix un regal',
    'gift_paid_by'           => 'pagat per %s',
    'gift_summary_title'     => 'Resum del pressupost',
    'gift_col_adult'         => 'Adult',
    'gift_col_child'         => 'Infant',
    'gift_col_party'         => 'Festa',
    'gift_debtor'            => 'Deutor',
    'gift_creditor'          => 'Creditor',
    'gift_liquidations'      => 'Tricount',
    'gift_no_debt'           => 'Cap deute pendent.',
    'gift_owes'              => 'ha de pagar',
    'gift_to'                => 'a',
    'gift_detailed_list'     => 'Llista detallada de regals',
    'gift_col_gift'          => 'Regal',
    'gift_col_link'          => 'Enllaç',
    'gift_modal_title_add'   => 'Afegeix un regal per a %s',
    'gift_modal_title_edit'  => 'Edita el regal',
    'gift_modal_payer'       => 'Pagat per',
    'gift_modal_gift_name'   => 'Nom del regal',
    'gift_modal_ph_name'     => 'p. ex., Lego Star Wars',
    'gift_modal_price'       => 'Preu (€)',
    'gift_modal_link'        => 'Enllaç (opcional)',
    'gift_confirm_delete'    => 'Vols eliminar aquest regal?',
    'gift_filter_all_children'   => 'Tots els nens',
    'gift_filter_all_adults'     => 'Tots els adults',
    'gift_empty_state_no_gifts'  => 'Cap regal de moment.',
    'gift_empty_state_no_filter' => 'Cap regal correspon al filtre.',
    'gift_view_matrix'           => 'Veure la matriu detallada',
];
```

---

### 📄 Fichier : `includes/lang/fr.php`
```php
<?php
// Dictionnaire Français
return [
    // ==========================================
    // TEST AUTO
    // ==========================================
    'test_running' => 'Tests en cours...',
    'test_passed' => 'Test réussi',
    'test_failed' => 'Test échoué',
    'tests_title' => 'Laboratoire de Tests',
    'tests_run_budget' => 'Tester le Budget',
    'tests_run_all' => 'Lancer tous les tests',
    'tests_copy_report' => 'Copier le rapport',
    'tests_waiting' => 'En attente du lancement...',
    'tests_report_copied' => 'Rapport copié dans le presse-papier !',
    'tests_live_preview'  => 'Aperçu en direct',


    // ==========================================
    // GLOBAL & NAVIGATION
    // ==========================================
    'menu_home'         => 'Accueil',
    'menu_calendar'     => 'Calendrier',
    'menu_budget'       => 'Budget',
    'menu_holidays'     => 'Voyages',
    'menu_gifts'        => 'Cadeaux',
    'nav_home'          => 'Accueil',
    'nav_calendar'      => 'Calendrier',
    'nav_budget'        => 'Budget',
    'nav_holidays'      => 'Voyages',
    'nav_gifts'         => 'Cadeaux',
    'aria_menu'         => 'Menu mobile',
    

    // Boutons et actions globales
    'actions'           => 'Actions',
    'edit'              => 'Modifier',
    'delete'            => 'Supprimer',
    'save'              => 'Enregistrer',
    'cancel'            => 'Annuler',
    'btn_save'          => 'Enregistrer',
    'btn_cancel'        => 'Annuler',
    'btn_delete'        => 'Supprimer',
    'btn_close'         => 'Fermer',
    'btn_back'          => '◀ Retour',
    'btn_edit_bases'    => '⚙️ Modifier les bases',
    'btn_edit'          => 'Modifier',
    'error_occured'     => 'Une erreur est survenue.',
    
    // Mots génériques & Erreurs
    'error'             => 'Erreur.',
    'confirm_delete'    => 'Es-tu sûr de vouloir supprimer cet élément ?',

    // Mois & Jours
    'month_01' => 'Janvier', 'month_02' => 'Février', 'month_03' => 'Mars',
    'month_04' => 'Avril',   'month_05' => 'Mai',     'month_06' => 'Juin',
    'month_07' => 'Juillet', 'month_08' => 'Août',    'month_09' => 'Septembre',
    'month_10' => 'Octobre', 'month_11' => 'Novembre','month_12' => 'Décembre',
    'day_mon'  => 'Lun', 'day_tue' => 'Mar', 'day_wed' => 'Mer', 
    'day_thu'  => 'Jeu', 'day_fri' => 'Ven', 'day_sat' => 'Sam', 'day_sun' => 'Dim',

    // ==========================================
    // AUTHENTIFICATION / LOGIN
    // ==========================================
    'login_title'               => 'Connexion - PachaFamily',
    'login_header'              => 'Connexion',
    'label_username'            => 'Identifiant',
    'label_password'            => 'Mot de passe',
    'placeholder_username'      => 'ex: pacha',
    'btn_login'                 => 'Connexion',
    'btn_login_submit'          => 'Se connecter',
    'logout'                    => 'Déconnexion',
    'btn_logout'                => 'Déconnexion',
    'error_missing_fields'      => 'Champs obligatoires.',
    'error_invalid_credentials' => 'Identifiants incorrects.',
    'login_subtitle'      => 'Veuillez vous identifier pour continuer',

    // ==========================================
    // ACCUEIL / INDEX
    // ==========================================
    'home_title'         => 'PachaFamily - Accueil',
    'home_welcome'       => 'Bienvenue sur PachaFamily',
    'home_subtitle'      => "Centre de contrôle de l'organisation familiale.",
    'home_logged_as'     => 'Connecté en tant que',
    'home_modules_title' => 'Modules',
    'mod_calendar_name'  => 'Calendrier Familial',
    'mod_calendar_desc'  => "Gérez les congés, les modes de garde et l'organisation hebdomadaire.",
    'mod_holidays_name'  => 'Vacances',
    'mod_holidays_desc'  => 'Planifiez vos prochaines escapades, destinations et idées de voyage.',
    'mod_gifts_name'     => 'Cadeaux',
    'mod_gifts_desc'     => 'Planifiez les cadeaux pour chaque enfant, pour Noël et les anniversaires.',
    'mod_budget_name'    => 'Budget',
    'mod_budget_desc'    => 'Suivi des dépenses fixes, revenus et équilibre du compte familial.',
    'cta_open'           => 'Ouvrir',
    'cta_explore'        => 'Explorer',
    'cta_view_lists'     => 'Voir les listes',
    'cta_manage'         => 'Gérer',

    // ==========================================
    // FAMILY CALENDAR
    // ==========================================
    'fc_page_title'             => 'PachaFamily - Calendrier',
    'fc_main_header'            => 'Calendrier Familial',
    'fc_btn_correct_balances'   => 'Corriger les soldes',
    'fc_btn_school_holidays'    => 'Vacances scolaires',
    'fc_modal_holidays_title'   => 'Vacances Scolaires (Zone C)',
    'fc_modal_snap_title'       => 'Ajuster un solde',
    'fc_col_period'             => 'Période',
    'fc_col_from'               => 'Du',
    'fc_col_to'                 => 'Au',
    'fc_label_person'           => 'Personne',
    'fc_label_leave_type'       => 'Type de congé',
    'fc_label_apply_date'       => "Date d'application",
    'fc_snap_date_hint'         => "Choisissez de préférence le 1er jour d'un mois",
    'fc_label_remaining_balance'=> 'Solde restant à cette date',
    'fc_placeholder_balance'    => 'ex: 12.5',
    'fc_btn_save_snap'          => 'Enregistrer le correctif',
    'fc_view_1m'                => '1 mois',
    'fc_view_2m'                => '2 mois',
    'fc_view_3m'                => '3 mois',
    'fc_view_year'              => 'Année',
    'fc_weekly_planning'        => 'Planning hebdomadaire',
    'fc_col_month'              => 'Mois',
    'fc_col_week_short'         => 'Sem.',
    'fc_col_av'                 => 'Av.',
    'fc_col_use'                => 'Use',
    'fc_legend_title'           => 'Légende',
    'fc_summary_title'          => 'Récapitulatif',
    'fc_summ_year'              => 'Année',
    'fc_summ_month'             => 'Mois',
    'fc_school_year'            => 'Année scolaire',
    'fc_unit_days'              => 'jours',
    'fc_menu_carole'            => 'Congés Carole',
    'fc_clear'                  => 'Effacer',
    'fc_menu_kids_leaves'       => 'Congés Enfants',
    'fc_err_save_snap'          => 'Erreur lors de la sauvegarde du correctif.',
    'fc_err_fetch_gov'          => 'Erreur lors de la connexion à l’API gouvernementale.',
    'fc_err_no_data_gov'        => 'Aucune donnée trouvée sur l’API du gouvernement pour cette année.',
    'fc_err_action'             => 'Erreur lors de l’action : ',
    'leg_presence'              => 'Présence Pep',
    
    'leg_school_holidays'       => 'Vacances Scolaires',
    'leg_public_holiday'        => 'Férié',
    'leg_off_carole'            => 'Off Carole',
    'leg_extra_off'             => 'Extra Off',
    'leg_centre'                => 'Centre',
    'leg_avis'                  => 'Avis',
    'leg_pep_sick'              => 'Pep Malade',
    
    'vac_toussaint'             => 'Vacances de la Toussaint',
    'vac_noel'                  => 'Vacances de Noël',
    'vac_hiver'                 => "Vacances d'Hiver",
    'vac_printemps'             => 'Vacances de Printemps',
    'vac_ascension'             => 'Pont de l’Ascension',
    'vac_ete'                   => "Vacances d'Été",
    'btn_off'                   => 'Off',
    'btn_extra'                 => 'Extra',
    
    'fc_alert_burn_days' => '⚠️ Risque de perte : %s j à poser avant le %s',
    'fc_alert_burn_jra'  => '⚠️ JRA : Seuls 2j sont reportables après février !',

    // ==========================================
    // HOLIDAYS (VOYAGES)
    // ==========================================
    'hdl_title_list'            => 'PachaFamily - Mes Vacances',
    'hdl_title_detail'          => 'PachaFamily - Détail du voyage',
    'hdl_main_title'            => 'Mes Vacances',
    'hdl_filter_all'            => 'Tout afficher',
    'hdl_filter_year'           => 'Année',
    'hdl_left_to_pay_label'     => 'Reste à payer',
    'hdl_btn_create'            => 'Créer un voyage',
    'hdl_no_active_trips'       => 'Aucun voyage en cours pour cette période. Planifions quelque chose !',
    'hdl_history_title'         => 'Historique',
    'hdl_dates_to_define'       => 'Dates à définir',
    'hdl_total_budget'          => 'Budget Total',
    'hdl_paid'                  => 'Payé',
    'hdl_saved'                 => 'Financé',
    'hdl_left_to_pay'           => 'Reste à payer',
    'hdl_status_booked'         => 'Confirmé',
    'hdl_status_planned'        => 'Planifié',
    'hdl_status_draft'          => 'Brouillon',
    'hdl_status_passed'         => 'Terminé',
    'hdl_status_archived'       => 'Archivé',
    'hdl_modal_title'           => 'Planifier le voyage',
    'hdl_label_name'            => 'Nom du voyage',
    'hdl_ph_name'               => 'Ex: Octobre - Portugal',
    'hdl_label_status'          => 'Statut',
    'hdl_label_period'          => 'Période (Texte libre)',
    'hdl_ph_period'             => 'Ex: Octobre 2026',
    'hdl_label_from'            => 'Du',
    'hdl_label_to'              => 'Au',
    'hdl_cat_transport'         => 'Transport',
    'hdl_add_transport'         => 'Ajouter un transport',
    'hdl_cat_accommodation'     => 'Hébergement',
    'hdl_add_accommodation'     => 'Ajouter un hébergement',
    'hdl_cat_activity'          => 'Activité',
    'hdl_add_activity'          => 'Ajouter une activité',
    'hdl_label_budget_food'     => 'Budget Food & Bev (€)',
    'hdl_label_budget_extras'   => 'Budget Extras (€)',
    'hdl_label_notes'           => 'Notes',
    'hdl_ph_notes'              => 'Idées en vrac...',
    'hdl_label_budget_food_extras' => 'Budget Food & Extras',
    'hdl_map_itinerary'         => 'Itinéraire',
    'hdl_outbound'              => 'Aller',
    'hdl_return'                => 'Retour',
    'hdl_btn_add_step'          => 'Ajouter une étape',
    'hdl_step_details'          => 'Détails du roadtrip',
    'hdl_no_steps'              => 'Aucune étape définie. Commencez par en ajouter une !',
    'hdl_general_costs'         => 'Frais généraux & Budgets',
    'hdl_food_bev'              => 'Food & Bev',
    'hdl_extras'                => 'Extras',
    'hdl_view_planning'         => 'Voir le planning',
    'hdl_passing_point'         => 'Point de passage (sans frais)',
    'hdl_search_location'       => 'Chercher un lieu',
    'hdl_ph_search'             => 'Ville, hôtel, adresse...',
    'hdl_label_step_name'       => "Nom de l'étape",
    'hdl_label_arrival'         => 'Arrivée',
    'hdl_label_departure'       => 'Départ',
    'hdl_planned_expenses'      => 'Dépenses prévues pour cette étape',
    'hdl_btn_add_expense'       => '+ Ajouter une dépense',
    'hdl_save_fav'              => 'Enregistrer dans mes favoris',
    'hdl_planning_title'        => 'Planning de l’étape',
    'hdl_to_pay'                => 'À payer',
    'hdl_js_search_loading'     => 'Recherche en cours...',
    'hdl_js_no_result'          => 'Aucun résultat trouvé.',
    'hdl_js_network_error'      => 'Erreur de connexion au service de carte.',
    'hdl_js_confirm_del_trip'   => 'Es-tu sûr de vouloir supprimer ce voyage entier ?',
    'hdl_js_confirm_del_step'   => 'Supprimer cette étape ?',
    'hdl_js_step_label'         => 'Étape',
    'hdl_js_ph_expense_name'    => 'Ex: Hôtel, Péage...',
    'hdl_quick_edit_title'      => 'Modification rapide',
    'hdl_js_edit_step'          => 'Modifier l’étape',
    'hdl_js_missing_dates_title'=> 'Dates manquantes',
    'hdl_js_missing_dates_msg'  => 'Veuillez définir une date d’arrivée et de départ pour organiser le planning.',
    'hdl_to_place'              => 'À placer',
    'hdl_from'                  => 'Du',
    'hdl_to'                    => 'Au',
    'hdl_btn_add_step'          => 'Ajouter une étape',
    'hdl_quick_edit_title'      => 'Modification rapide',
    'hdl_paid'                  => 'Payé',
    'weather_forecast' => 'Prévisions météo',
    'weather_not_available' => 'Météo indisponible',
    'temp_max' => 'Temp. Max',
    'weather_loading' => 'Chargement météo...',
    'weather_error'   => 'Météo indisponible',
    'weather_sunny'   => 'Ensoleillé',
    'weather_cloudy'  => 'Nuageux',
    'weather_rainy'   => 'Pluvieux',
    'weather_snowy'   => 'Neigeux',
    'weather_historical' => 'Basé sur l\'historique (estimation)',
    'err_invalid_holiday_id' => 'ID de voyage invalide.',
    'hdl_default_exp_name'   => 'Dépense',
    'hdl_select_beneficiary' => '-- Sélectionner --',

    // Anciennes clés Holidays non préfixées (conservées pour compatibilité si utilisées)
    'outbound_trip'             => 'Aller',
    'return_trip'               => 'Retour',
    'period'                    => 'Période',
    'budget_food'               => 'Budget Food & Extras',
    'total_cost'                => 'Coût Total Estimé',
    'paid'                      => 'Payé',
    'provisioned'               => 'Provisionné',
    'left_to_pay'               => 'Reste à payer',
    'map_itinerary'             => '🗺️ Itinéraire',
    'place_step'                => '📍 Placer une étape',
    'step_details'              => '📝 Détail des étapes',
    'no_steps'                  => 'Aucune étape planifiée.',
    'travel_notes'              => '📝 Notes du voyage',
    'to_be_defined'             => 'À définir',
    'funded'                    => 'Financé (Provision)',
    'view_planning'             => 'Voir le planning',
    'to_pay'                    => 'À payer',
    'from_date'                 => 'Du',
    'to_date'                   => 'au',
    'passing_point'             => 'Point de passage (Aucune dépense)',
    'search_location'           => 'Rechercher un lieu géographique',
    'search_placeholder'        => 'Ex: Paris, Ibis Barcelone...',
    'step_name'                 => 'Nom de l\'étape',
    'arrival_optional'          => 'Arrivée le (Optionnel)',
    'departure_optional'        => 'Départ le (Optionnel)',
    'planned_expenses'          => 'Dépenses prévues',
    'add_expense'               => '+ Ajouter une dépense',
    'save_favorite'             => 'Sauvegarder dans mes favoris',
    'planning_title'            => 'Planning',
    'modal_plan_trip'           => 'Planifier le voyage',
    'modal_quick_edit'          => 'Modification rapide',
    'err_modal_missing'         => 'Erreur : La modale holidayModal est introuvable.',
    'placeholder_title'         => 'Intitulé',
    'placeholder_price'         => 'Prix (€)',
    'already_paid'              => 'Déjà payé ?',
    'remove_line'               => 'Retirer cette ligne',
    'confirm_delete_trip'       => 'Voulez-vous vraiment supprimer définitivement ce voyage ? Cette action est irréversible.',
    'step_number'               => 'Étape',
    'place_new_step'            => '📍 Placer une nouvelle étape',
    'edit_step'                 => '✏️ Modifier l\'étape',
    'search_in_progress'        => 'Recherche en cours... ⏳',
    'no_result_found'           => 'Aucun résultat trouvé.',
    'network_error'             => 'Erreur réseau.',
    'placeholder_label'         => 'Libellé',
    'placeholder_notes_link'    => '🔗 Lien de réservation ou notes (Optionnel)...',
    'confirm_delete_step'       => 'Supprimer cette étape et toutes les dépenses associées ?',
    'planning_of'               => '📅 Planning : ',
    'missing_dates_title'       => '⚠️ Dates manquantes',
    'missing_dates_msg'         => 'Veuillez modifier cette étape (✏️) et renseigner sa <strong>Date d\'arrivée et de départ</strong>.',
    'to_place'                  => '📥 À Placer',

    // ==========================================
    // BUDGET (GLOBAL & CATEGORIES)
    // ==========================================
    'budget_page_title'         => 'PachaFamily - Budget',
    'budget_main_header'        => 'Gestion du Budget',
    'budget_tab_tracking'       => 'Suivi Mensuel',
    'budget_tab_prev'           => 'Budget 2026',
    'budget_tab_savings'        => 'Épargne',
    'budget_tab_recap'          => 'Récapitulatif',
    'budget_err_file_not_found' => 'Oups ! Fichier introuvable.',
    'budget_err_file_detail'    => 'Vérifiez que le fichier views/%s.php existe bien.',
    'budget_err_not_authorized' => 'Page non autorisée.',
    'cat_income'                => 'Revenus',
    'cat_fmcg'                  => 'Courses (FMCG)',
    'cat_fuel'                  => 'Essence',
    'cat_school'                => 'École / Garde',
    'cat_fixed'                 => 'Charges Fixes',
    'cat_others'                => 'Autres / Imprévus',
    'cat_savings'               => 'Épargne',

    // --- BUDGET : SUIVI ---
    'bud_rem_school'            => 'École (Reste estimé)',
    'bud_rem_fmcg'              => 'Courses (Reste estimé)',
    'bud_rem_fuel'              => 'Essence (Reste estimé)',
    'bud_closed_archived'       => 'Ce mois est clôturé et archivé.',
    'bud_budget_of'             => 'Budget :',
    'bud_confirm_close'         => 'Clôturer ce mois ? Cela figera les soldes actuels et basculera le suivi par défaut sur le mois prochain.',
    'bud_close_btn'             => 'Clôturer',
    'bud_confirm_reopen'        => 'Rouvrir ce mois ? Ses soldes seront recalculés en direct.',
    'bud_reopen_btn'            => 'Rouvrir',
    'bud_active_month_btn'      => 'Mois Actif',
    'bud_bank_balance'          => 'Solde bancaire',
    'bud_frozen'                => 'Figé',
    'bud_current'               => 'Actuel',
    'bud_update_btn'            => 'MàJ',
    'bud_theoretical_balance'   => 'Solde théorique fin de mois',
    'bud_upcoming_charges'      => 'Charges à venir',
    'bud_all_paid'              => 'Tout est payé !',
    'bud_capacity_tooltip'      => 'Calcul : Report Mois Précédent (%s€) + Salaires (%s€) + Autres rentrées (%s€)',
    'bud_max_capacity'          => 'Capacité Max',
    'bud_actual'                => 'Actuel',
    'bud_theoretical'           => 'Théorique',
    'bud_import_csv'            => 'Importer CSV',
    'bud_csv_file'              => 'Fichier CSV (Point-virgule)',
    'bud_preview'               => 'Prévisualiser',
    'bud_validate_import'       => 'Valider l\'importation',
    'bud_assign_month'          => 'Imputer au mois',
    'bud_op_date'               => 'Date Opération',
    'bud_label'                 => 'Libellé',
    'bud_amount'                => 'Montant',
    'bud_category'              => 'Catégorie',
    'bud_ignore'                => 'Ignorer',
    'bud_to_define'             => 'À définir',
    'bud_is_charge'             => 'Charge ?',
    'bud_is_income'             => 'Revenu ?',
    'btn_import'                => 'Importer',
    'bud_no_lines'              => 'Aucune ligne.',
    'bud_confirm_delete'        => 'Supprimer cette ligne ?',
    'bud_new_transaction'       => 'Nouvelle transaction',
    'bud_assign_to_month'       => 'Imputer au mois de gestion',
    'bud_real_date'             => 'Date réelle de l\'opération',
    'bud_type'                  => 'Type',
    'bud_type_expense'          => 'Dépense',
    'bud_type_income'           => 'Revenu / Remboursement',
    'bud_beneficiary'           => 'Bénéficiaire',
    'bud_fixed_charge'          => 'Charge Fixe',
    'bud_expected_income'       => 'Revenu Attendu',
    'bud_amount_eur'            => 'Montant (€)',
    'bud_update_balance'        => 'MàJ Solde Bancaire',
    'bud_balance_eur'           => 'Solde (€)',
    'bud_add_title'             => 'Ajouter',
    'bud_edit_title'            => 'Modifier la transaction',
    'bud_to_define_js'          => 'à définir',
    'bud_bar_actual' => "Actuel",
    'bud_bar_theoretical' => "Théorique",
    'bud_bar_max_cap' => "Capacité Max",
    'bud_import_csv' => "Importer CSV",
    'bud_csv_file' => "Sélectionnez le fichier de votre banque (.csv)",
    'bud_preview' => "Prévisualiser",
    'bud_validate_import' => "Veuillez vérifier et catégoriser les lignes avant validation.",
    'bud_assign_month' => "Mois de gestion",
    'bud_op_date' => "Date",
    'bud_amount' => "Montant",
    'bud_ignore' => "Ignorer (Crédit)",
    'bud_to_define' => "À définir",
    'bud_is_charge' => "Est une charge fixe...",
    'bud_is_income' => "Est un revenu...",

    // --- BUDGET : RECAP ---
    'bud_recap_monthly_title'   => 'Récapitulatif Mensuel',
    'bud_recap_btn_add'         => 'Ajouter un frais / revenu',
    'bud_planned_amount'        => 'Montant Prévu',
    'bud_day'                   => 'Jour',
    'bud_month_state'           => 'État du mois',
    'bud_est_short'             => 'Est.',
    'bud_reg_planned_in'        => 'Régul. prévue en %s',
    'bud_bonus'                 => 'Bonus',
    'bud_overrun'               => 'Dépassement',
    'bud_remaining'             => 'Reste',
    'bud_exact_amount'          => 'Montant exact',
    'bud_per_month_short'       => 'Soit',
    'bud_month_short'           => 'mois',
    'bud_freq_mensuel'          => 'Mensuel',
    'bud_freq_annuel'           => 'Annuel',
    'bud_state_validated'       => 'Validé',
    'bud_state_partial'         => 'Partiel',
    'bud_state_waiting'         => 'En attente',
    'bud_total_income_smoothed' => 'Total Revenus (Lissés)',
    'bud_total_expenses_smoothed'=> 'Total Dépenses (Lissées)',
    'bud_theoretical_balance_recap'=> 'Équilibre théorique',
    'bud_recap_footer_note'     => 'L\'état de paiement se met à jour automatiquement en fonction des opérations importées dans l\'onglet "Suivi Mensuel".',
    'bud_recap_modal_add'       => 'Ajouter un élément',
    'bud_recap_modal_edit'      => 'Modifier',
    'bud_label_name'            => 'Nom',
    'bud_ph_item_name'          => 'Ex: Loyer, Salaire...',
    'bud_label_keywords'        => 'Mots-clés (Détection Auto)',
    'bud_ph_keywords'           => 'Ex: NETFLIX, PRIME VIDEO (séparés par virgule)',
    'bud_help_keywords'         => 'Si une dépense du mois contient ce mot, la ligne passera en "Validé".',
    'bud_label_day'             => 'Jour (1-31)',
    'bud_cat_expense'           => 'Dépense (Frais)',
    'bud_label_frequency'       => 'Fréquence',
    'bud_label_amount_type'     => 'Type de montant',
    'bud_type_fixed'            => 'Fixe (Facture)',
    'bud_type_variable'         => 'Variable (Estimation)',
    'bud_label_regularization'  => 'Régularisation',
    'bud_reg_none'              => 'Aucune',
    'bud_recap_confirm_delete'  => 'Voulez-vous vraiment supprimer cet élément ?',
    'bud_err_delete'            => 'Erreur lors de la suppression.',

    // --- BUDGET : EPARGNE ---
    'bud_sav_add_month'            => 'Saisir un mois',
    'bud_sav_add_one_month'        => '+1 Mois',
    'bud_sav_no_data'              => 'Aucune donnée pour %s.',
    'bud_sav_post_month'           => 'Poste / Mois',
    'bud_sav_from_date'            => 'Dès le %s',
    'bud_sav_edit_modal'           => 'Modifier avec Modale',
    'bud_sav_delete_month'         => 'Supprimer tout le mois',
    'bud_sav_total_bank'           => 'Total Banque',
    'bud_sav_extra'                => 'Extra',
    'bud_sav_modal_title_add'      => 'Saisir un mois',
    'bud_sav_modal_title_edit'     => 'Modifier :',
    'bud_sav_month_concerned'      => 'Mois concerné',
    'bud_sav_total_bank_eur'       => 'Total en Banque (€)',
    'bud_sav_ventilation'          => 'Ventilation',
    'bud_sav_adj_help'             => 'Utilisez l\'ajustement (+/-) pour recalculer automatiquement.',
    'bud_sav_add_line'             => 'Ligne',
    'bud_sav_current'              => 'Actuel',
    'bud_sav_adjust'               => 'Ajust (+/-)',
    'bud_sav_new'                  => 'Nouveau',
    'bud_sav_sum_mode_title'       => 'Activer l\'addition rapide',
    'bud_sav_selection'            => 'Sélection :',
    'bud_sav_ph_name'              => 'Nom (ex: Vacances)',
    'bud_sav_saving'               => 'Enregistrement...',
    'bud_err_tech'                 => 'Une erreur technique est survenue.',
    'bud_sav_confirm_delete_month' => 'Supprimer TOUT le mois de %m pour %o ?',
    'bud_sav_prompt_duplicate'     => "Voulez-vous copier les données de %s vers %t1 ?\n\nSaisissez le nouveau TOTAL en banque (€) pour %t2 :",
    'bud_err_server'               => 'Erreur serveur : ',
    'bud_err_network_dup'          => 'Erreur réseau lors de la duplication.',

    // --- BUDGET : PREVISIONNEL ---
    'bud_prev_incomes'           => 'Revenus',
    'bud_prev_person'            => 'Personne',
    'bud_prev_salary'            => 'Salaire',
    'bud_prev_monthly_payment'   => 'Mensualité',
    'bud_prev_joint_account'     => 'Cpt Commun',
    'bud_prev_func_expenses'     => 'Frais Func.',
    'bud_prev_perso_savings'     => 'Eco Perso',
    'bud_prev_family_savings'    => 'Eco Family',
    'bud_prev_available'         => 'Dispo',
    'bud_prev_perso_remaining'   => 'Restant Perso',
    'bud_prev_budget_alloc'      => 'Répartition Budgétaire',
    'bud_prev_today'             => 'Auj.',
    'bud_prev_new_line'          => 'Nouvelle Ligne',
    'bud_prev_remaining'         => 'Restant',
    'bud_prev_confirm_del_line'  => 'Supprimer cette ligne et tout son historique ?',
    'bud_prev_notes_for'         => 'Notes pour',
    'bud_prev_saved'             => 'Enregistré',
    'bud_prev_notes_ph'          => 'Écrivez vos remarques, rappels ou commentaires pour ce mois ici...',
    'bud_prev_save_note'         => 'Enregistrer la note',
    'bud_prev_transfers_to_make' => 'Virements à effectuer',
    'bud_prev_destination'       => 'Destination',
    'bud_prev_done'              => 'FAIT',
    'bud_prev_validate'          => 'Valider',
    'bud_prev_grand_total'       => 'GRAND TOTAL',
    'bud_prev_new_line_title'    => 'Nouvelle Ligne de Budget',
    'bud_prev_edit_line_title'   => 'Modifier la ligne',
    'bud_prev_label_target'      => 'Cible (Destination)',
    'bud_prev_choose'            => 'Choisir',
    'bud_prev_link_holiday'      => 'Lier à un voyage (Optionnel)',
    'bud_prev_no_link'           => 'Ne pas associer',
    'bud_prev_err_no_history'    => 'Impossible : pas d\'historique disponible pour copier.',
    'bud_prev_confirm_copy'      => "Voulez-vous copier les données de %s vers %t ?\n\n⚠️ Cela écrasera toutes les valeurs déjà présentes pour %t.",
    'bud_prev_confirm_transfers' => "Confirmer que %p a bien effectué tous ses virements pour %m ?\n\nCela mettra à jour l'Épargne automatiquement.",
    'bud_sav_modal_title_add'   => 'Saisir un mois',
    'bud_sav_ph_name'           => 'Nom (ex: Vacances)',
    'bud_prev_label_name'       => 'Nom',

    // ==========================================
    // CADEAUX (GIFTS)
    // ==========================================
    'gift_page_title'        => 'PachaFamily - Liste de cadeaux',
    'gift_occ_tio'           => 'Tió',
    'gift_occ_noel'          => 'Noël',
    'gift_occ_rois'          => 'Rois mages',
    'gift_occ_anniv'         => 'Anniversaire',
    'gift_occ_sant'          => 'Saint',
    'gift_main_title'        => 'Liste de cadeaux %s',
    'gift_aria_change_view'  => 'Changer la vue',
    'gift_view_nadal'        => 'Noël',
    'gift_view_anniv'        => 'Anniversaires',
    'gift_view_by_party'     => 'Vue par fête',
    'gift_no_gifts'          => 'Aucun cadeau enregistré pour %s dans cette vue.',
    'gift_add_gift'          => 'Ajouter un cadeau',
    'gift_paid_by'           => 'payé par %s',
    'gift_summary_title'     => 'Résumé du budget',
    'gift_col_adult'         => 'Adulte',
    'gift_col_child'         => 'Enfant',
    'gift_col_party'         => 'Fête',
    'gift_debtor'            => 'Débiteur',
    'gift_creditor'          => 'Créancier',
    'gift_liquidations'      => 'Tricount',
    'gift_no_debt'           => 'Aucune dette en cours.',
    'gift_owes'              => 'doit',
    'gift_to'                => 'à',
    'gift_detailed_list'     => 'Liste détaillée des cadeaux',
    'gift_col_gift'          => 'Cadeau',
    'gift_col_link'          => 'Lien',
    'gift_modal_title_add'   => 'Ajouter un cadeau pour %s',
    'gift_modal_title_edit'  => 'Modifier le cadeau',
    'gift_modal_payer'       => 'Payé par',
    'gift_modal_gift_name'   => 'Nom du cadeau',
    'gift_modal_ph_name'     => 'ex: Lego Star Wars',
    'gift_modal_price'       => 'Prix (€)',
    'gift_modal_link'        => 'Lien (optionnel)',
    'gift_confirm_delete'    => 'Voulez-vous vraiment supprimer ce cadeau ?',
    'gift_filter_all_children'   => 'Tous les enfants',
    'gift_filter_all_adults'     => 'Tous les adultes',
    'gift_empty_state_no_gifts'  => 'Aucun cadeau pour le moment.',
    'gift_empty_state_no_filter' => 'Aucun cadeau ne correspond au filtre.',
    'gift_view_matrix'           => 'Voir la matrice détaillée',
];
```

---

### 📄 Fichier : `index.php`
```php
<?php
// Protection de la page : nécessite d'être connecté

require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Inclusion i18n (déjà fait dans header, mais par sécurité si besoin de tr() avant)

require_once __DIR__ . '/includes/i18n.php';

// Configuration de la page
$pageTitle  = tr('home_title');
$activePage = "home";
$bodyClass  = "pf-home"; // Important pour l'image de fond définie dans home.css
$pageCss    = "/modules/home/home.css";

require __DIR__ . '/header.php';
?>

<div class="pf-container">
  
  <div class="pf-hero">
    <h1><?= tr('home_welcome') ?></h1>
    <p><?= tr('home_subtitle') ?></p>

    <?php if (isset($_SESSION['user'])): ?>
      <div class="pf-user-info" style="margin-top: 12px; font-weight: 500;">
        <?= tr('home_logged_as') ?> 
        <strong><?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <section class="pf-section">
    <h2 style="color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.6);"><?= tr('home_modules_title') ?></h2>

    <div class="pf-modules-grid">
      
      <a href="/family-calendar.php" class="pf-module-card">
        <div class="pf-card-icon">📅</div>
        <h3 class="pf-card-title"><?= tr('mod_calendar_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_calendar_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_open') ?></span>
      </a>

      <a href="/holidays.php" class="pf-module-card">
        <div class="pf-card-icon">🏖️</div>
        <h3 class="pf-card-title"><?= tr('mod_holidays_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_holidays_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_explore') ?></span>
      </a>

      <a href="/gift-list.php" class="pf-module-card">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title"><?= tr('mod_gifts_name') ?></h3>
        <div class="pf-card-desc">
          <?= tr('mod_gifts_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_view_lists') ?></span>
      </a>

      <a href="/budget.php" class="pf-module-card">
        <div class="pf-card-icon">💰</div>
        <h3 class="pf-card-title"><?= tr('mod_budget_name') ?></h3>
        <div class="pf-card-desc">
            <?= tr('mod_budget_desc') ?>
        </div>
        <span class="pf-card-cta"><?= tr('cta_manage') ?></span>
      </a>

    </div>
  </section>
</div>

<?php
require __DIR__ . '/footer.php';
?>
```

---

### 📄 Fichier : `login.php`
```php
<?php
session_start();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; // Toujours s'assurer que tr() est dispo

$pageTitle = tr('login_title');
$activePage = "login";

if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = tr('error_missing_fields');
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, display_name FROM pf_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
            ];
            $redirectTo = $_GET['redirect'] ?? '/index.php';
            header('Location: ' . $redirectTo);
            exit;
        } else {
            $error = tr('error_invalid_credentials');
        }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-container pf-login-wrapper">
    <div class="pf-login-card">
        
        <div class="pf-login-header">
          <img src="/favicon.png" alt="PachaFamily Logo" class="pf-login-icon">
          <h1><?= tr('login_header') ?></h1>
          <p><?= tr('login_subtitle') ?></p>
      </div>
        
        <?php if ($error): ?>
            <div class="pf-login-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
            <div class="pf-form-group">
                <label class="pf-label" for="username"><?= tr('label_username') ?></label>
                <input type="text" id="username" name="username" class="pf-input" required autofocus placeholder="<?= tr('placeholder_username') ?>">
            </div>

            <div class="pf-form-group">
                <label class="pf-label" for="password"><?= tr('label_password') ?></label>
                <input type="password" id="password" name="password" class="pf-input" required placeholder="••••••">
            </div>

            <button type="submit" class="pf-btn pf-btn-block">
                <?= tr('btn_login_submit') ?>
            </button>
        </form>
        
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>
```

---

### 📄 Fichier : `logout.php`
```php
<?php
session_start();

// On vide toutes les variables de session
$_SESSION = [];

// On détruit la session
session_destroy();

// Optionnel : supprimer le cookie de session (plus propre)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// On renvoie vers la page de login (ou index si tu préfères)
header('Location: /login.php');
exit;

```

---

### 📄 Fichier : `modules/budget/budget.css`
```css
/* modules/budget/budget.css */

/* --- 1. VARIABLES & BASE --- */
/* Le root garde les couleurs sémantiques dont le JS/PHP a besoin localement */
.budget-view {
  font-family:
    "Segoe UI",
    system-ui,
    -apple-system,
    sans-serif;
  color: var(--text-main);
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.view-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 16px;
}
.view-header h2 {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.025em;
}

.nens-title.theme-pol {
  color: #1e3a8a !important;
  background: #eaf2ff !important;
  border-left: 4px solid #1e3a8a !important;
  border-radius: 0 8px 8px 0;
  padding: 8px 12px;
}
.nens-table.theme-pol thead th {
  background: #f5f9ff !important;
  color: #1e3a8a !important;
}
.nens-table.theme-pol {
  border-top: 2px solid #bcd3ff !important;
}
.nens-title.theme-pep {
  color: #14532d !important;
  background: #eaf7ea !important;
  border-left: 4px solid #14532d !important;
  border-radius: 0 8px 8px 0;
  padding: 8px 12px;
}
.nens-table.theme-pep thead th {
  background: #f6fdf6 !important;
  color: #14532d !important;
}
.nens-table.theme-pep {
  border-top: 2px solid #b9e3b9 !important;
}

/* --- 2. NAVIGATION TABS (Pills) --- */
.budget-tabs-container {
  display: inline-flex;
  background: var(--bg-panel);
  padding: 6px;
  border-radius: 50px;
  box-shadow: var(--shadow);
  gap: 8px;
  position: relative;
  z-index: 10;
}
.tab-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  border-radius: 40px;
  text-decoration: none;
  color: var(--text-muted);
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
}
.tab-item:hover {
  background-color: var(--bg-page);
  color: var(--text-main);
}
.tab-item.active {
  background: var(--primary);
  color: white;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
  transform: scale(1.02);
}
.tab-icon {
  font-size: 1.1em;
  line-height: 1;
}

.owner-tabs {
  display: inline-flex;
  background: #e2e8f0;
  border-radius: 8px;
  padding: 4px;
}
.owner-tab {
  padding: 6px 20px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--text-muted);
  font-weight: 600;
  transition: 0.2s;
}
.owner-tab.active {
  background: var(--bg-panel);
  color: var(--primary);
  box-shadow: var(--shadow-sm);
}

/* --- 3. BOUTONS SPÉCIFIQUES & ACTIONS --- */
.pf-btn {
  border-radius: 50px; /* Surcharge le global pour le budget */
}
.btn-icon {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 6px;
  border-radius: var(--radius);
  transition: background 0.2s;
  font-size: 1.1rem;
}
.btn-icon:hover {
  background: #e2e8f0;
  transform: scale(1.1);
}
.btn-safe-click {
  position: relative;
  z-index: 10 !important;
  pointer-events: auto !important;
}
.month-actions {
  display: flex;
  gap: 4px;
  opacity: 0.5;
  transition: opacity 0.2s ease;
}
.month-header-container:hover .month-actions {
  opacity: 1;
}
.month-actions .btn-icon-small {
  font-size: 0.75rem;
  padding: 2px 6px;
  height: 24px;
  width: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: all 0.2s;
}
.month-actions .btn-icon-small:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

/* --- 4. TABLEAU MÉTIER --- */
.row-income td:first-child {
  border-left: 4px solid var(--success);
}
.row-expense td:first-child {
  border-left: 4px solid var(--danger);
}
.row-estimate td:first-child {
  border-left: 4px solid var(--warning);
}
.row-estimate td {
  color: var(--text-muted);
  font-style: italic;
}

.table-responsive {
  overflow-x: auto;
}
.sticky-col {
  position: sticky !important;
  left: 0 !important;
  background: var(--bg-panel) !important;
  z-index: 20 !important;
  border-right: 1px solid var(--bg-page);
  min-width: 150px;
}
th.sticky-col {
  z-index: 30 !important;
  background: #f8fafc !important;
}

.cell-amount {
  font-family: "Consolas", "Monaco", monospace;
  font-weight: 700;
}
.cell-content {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  height: 100%;
}
.savings-table .row-extres td {
  background: #fffbeb;
  border-top: 2px solid #e2e8f0;
}

.btn-cell-delete {
  display: none;
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
  border: none;
  border-radius: 4px;
  width: 24px;
  height: 24px;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  align-items: center;
  justify-content: center;
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  text-decoration: none;
  transition: all 0.2s;
}
.btn-cell-delete:hover {
  background: var(--danger);
  color: white;
}
td:hover .btn-cell-delete {
  display: flex;
}

.pf-table tfoot {
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
}
.pf-table tfoot td {
  padding: 18px 16px;
  border-top: 1px solid #e2e8f0;
}

/* Inputs modifiés */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type="number"] {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* --- 5. MODALES (Extras Budget) --- */
.pf-modal-content.modal-large {
  max-width: 900px;
  transition: max-width 0.3s ease;
}
.import-table-wrapper {
  max-height: 45vh;
  overflow-y: auto;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
}
.import-dropzone {
  border: 2px dashed #cbd5e1;
  padding: 40px 20px;
  text-align: center;
  border-radius: 12px;
  background: #f8fafc;
}

/* --- 6. UTILITAIRES & BADGES --- */
.text-success {
  color: var(--success);
}
.text-danger {
  color: var(--danger);
}
.text-muted {
  color: var(--text-muted);
}
.font-bold {
  font-weight: 700;
}
.badge-type {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}
.badge-type.mensuel {
  background: #eff6ff;
  color: var(--primary);
}
.badge-type.annuel {
  background: #f3e8ff;
  color: #9333ea;
}
.budget-note {
  margin-top: 15px;
  font-size: 0.85em;
  opacity: 0.8;
  color: var(--text-muted);
}

/* --- 7. BUDGET PREVISIONNEL (TIMELINE) --- */
.prev-container {
  display: flex;
  flex-direction: column;
  gap: 30px;
}
.prev-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e2e8f0;
}
.prev-section-header h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.025em;
}

.prev-input {
  width: 100%;
  border: 1px solid transparent;
  background: transparent;
  text-align: center;
  font-family: inherit;
  font-size: 0.9rem;
  color: var(--text-main);
  font-weight: 500;
  padding: 4px 0;
  border-radius: 4px;
  transition: all 0.2s;
}
.prev-input:hover,
.prev-input:focus {
  background: #eef2ff;
  border-color: #6366f1;
  outline: none;
}
.prev-input.bold-blue {
  color: #0284c7;
  font-weight: 700;
}

.prev-section-header,
.view-header,
.budget-view > div[style*="justify-content: space-between"] {
  display: flex !important;
  flex-direction: row !important;
  justify-content: space-between !important;
  align-items: center !important;
  float: none !important;
  position: relative !important;
  width: 100% !important;
  gap: 15px !important;
}

.prev-salary-table {
  width: 100%;
  max-width: 900px;
  background: var(--bg-panel);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.prev-salary-table th {
  background: #f8fafc;
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.8rem;
  padding: 12px;
  text-align: center;
  border-bottom: 1px solid #e2e8f0;
}
.prev-salary-table th:first-child {
  text-align: left;
}
.prev-salary-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
}

.prev-timeline-wrapper {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: var(--radius);
  background: var(--bg-panel);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  margin-top: 15px;
}
.prev-alloc-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 100%;
}
.prev-alloc-table th {
  padding: 6px 10px;
  border-right: 1px solid #f1f5f9;
  white-space: nowrap;
  text-align: center;
  font-size: 0.9rem;
}
.prev-alloc-table td {
  padding: 3px 10px;
  border-bottom: 1px solid #f1f5f9;
  border-right: 1px solid #f1f5f9;
  white-space: nowrap;
  text-align: center;
  font-size: 0.9rem;
}

.col-sticky {
  position: sticky !important;
  left: 0 !important;
  z-index: 10;
  background-color: var(--bg-panel);
  border-right: 2px solid #e2e8f0;
  text-align: left !important;
  min-width: 220px;
  font-weight: 600;
  color: var(--text-main);
}
.col-sticky.header-cell {
  top: 0;
  z-index: 30 !important;
  background-color: #f8fafc;
  color: var(--text-muted);
}
.col-sticky small {
  display: block;
  font-weight: normal;
  color: var(--text-muted);
  font-style: italic;
  font-size: 0.75rem;
}

.th-month {
  background: #1e293b !important;
  color: white !important;
  font-size: 0.85rem !important;
  border-left: 2px solid white;
  padding: 8px !important;
}
.th-month.current {
  background: var(--primary) !important;
}
.th-sub {
  font-size: 0.7rem !important;
  text-transform: uppercase;
  background: #f8fafc;
  color: var(--text-muted);
}

.row-total td {
  background: #f8fafc;
  font-weight: 700;
  color: #334155;
  border-top: 2px solid #e2e8f0;
}
.row-restant td {
  background: #eff6ff;
  font-weight: 700;
  border-bottom: 2px solid #bfdbfe;
}
.txt-alex {
  color: #0891b2;
}
.txt-laia {
  color: #f59e0b;
}
.txt-global {
  color: var(--text-muted);
  font-weight: 700;
  background: #f8fafc;
}
.val-ok {
  color: var(--success);
}
.val-ko {
  color: var(--danger);
}

.nav-group {
  display: flex;
  gap: 5px;
}
.btn-nav {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 4px 10px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--text-muted);
  font-size: 0.85rem;
  font-weight: 500;
  transition: 0.2s;
}
.btn-nav:hover {
  background: #f1f5f9;
  color: var(--text-main);
  border-color: #94a3b8;
}

.row-actions {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 50 !important;
  opacity: 0;
  pointer-events: none;
  display: flex;
  gap: 8px;
  transition: opacity 0.2s ease-in-out;
}
tr:hover .row-actions {
  opacity: 1;
  pointer-events: auto !important;
}

/* --- 8. RÉCAPITULATIF VIREMENTS --- */
.recap-wrapper {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: center;
}
.recap-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  width: 100%;
  max-width: 600px;
}
.recap-header {
  background: #1e293b;
  color: white;
  padding: 12px 20px;
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  text-align: center;
}
.recap-table {
  width: 100%;
  border-collapse: collapse;
}
.recap-table th,
.recap-table td {
  padding: 10px 15px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
  font-size: 0.95rem;
}
.recap-table th {
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.8rem;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
}
.recap-table td:first-child {
  text-align: left;
  font-weight: 600;
  color: #1e293b;
}
.col-alex {
  background-color: #ecfeff;
  color: #0891b2;
  border-left: 1px solid #cffafe;
}
th.col-alex {
  background-color: #cffafe;
  border-bottom-color: #a5f3fc;
}
.col-laia {
  background-color: #fffbeb;
  color: #d97706;
  border-left: 1px solid #fef3c7;
}
th.col-laia {
  background-color: #fef3c7;
  border-bottom-color: #fde68a;
}
.col-global {
  background-color: #f8fafc;
  color: #475569;
  font-weight: 700;
  border-left: 2px solid #e2e8f0;
}
.row-grand-total td {
  background-color: #1e293b;
  color: white;
  font-weight: 800;
  border: none;
  padding: 12px 15px;
}
.row-grand-total .col-alex {
  background: #164e63;
  color: #67e8f9;
  border: none;
}
.row-grand-total .col-laia {
  background: #78350f;
  color: #fcd34d;
  border: none;
}
.row-grand-total .col-global {
  background: #334155;
  color: white;
  border: none;
}

/* --- 9. STYLES SPÉCIFIQUES AU SUIVI MENSUEL --- */
.cat-card {
  background: var(--bg-panel);
  border-radius: var(--radius);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

.cat-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px; /* Padding interne à l'encart coloré */
  width: 100%;
}

.cat-card-title-group {
  display: flex;
  flex-direction: column;
}

.cat-card-subtitle {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 2px;
}
.btn-add-item {
  background: rgba(255, 255, 255, 0.6);
  color: inherit;
  border: 1px solid rgba(0, 0, 0, 0.1);
  width: 28px;
  height: 28px;
  border-radius: 50%;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.btn-add-item:hover {
  background: var(--bg-panel);
  transform: rotate(90deg) scale(1.1);
  box-shadow: var(--shadow-sm);
  border-color: currentColor;
}
.progress-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-top: 20px;
}
.fade-pulse {
  animation: pulseText 2s infinite;
}
@keyframes pulseText {
  0% {
    opacity: 0.8;
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.8;
  }
}
.suivi-nav-group {
  display: flex;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
}
.suivi-btn-nav {
  padding: 6px 12px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: bold;
  border-right: 1px solid #cbd5e1;
  transition:
    background 0.2s,
    color 0.2s;
}
.suivi-btn-nav:last-child {
  border-right: none;
}
.suivi-btn-nav:hover {
  background: var(--bg-panel);
  color: var(--primary);
}
input[type="month"].pf-input {
  font-family: inherit;
  color: var(--text-main);
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}

/* --- 10. EPARGNE --- */
.epargne-inline-input {
  width: 100%;
  min-width: 60px;
  max-width: 85px;
  border: 1px solid transparent;
  background: transparent;
  text-align: center;
  padding: 2px 4px;
  border-radius: 4px;
  transition: 0.2s;
  font-size: 0.85rem;
  margin: 0 auto;
}
.epargne-inline-input:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}
.epargne-inline-input:focus {
  border-color: var(--primary);
  background: #fff;
  outline: none;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

/* --- 11. CALCULATRICE FLOTTANTE --- */
.pf-fab-sum {
  position: fixed;
  right: 30px;
  bottom: 30px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background-color: #2563eb;
  color: white;
  font-size: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
  cursor: pointer;
  z-index: 9000;
  transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.pf-fab-sum:hover {
  transform: scale(1.1);
  background-color: #1d4ed8;
}
.pf-fab-sum.active {
  background-color: #10b981;
  transform: scale(1.1);
  box-shadow: 0 4px 20px rgba(16, 185, 129, 0.5);
}
.pf-sum-bar {
  position: fixed;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%) translateY(100px);
  opacity: 0;
  background: white;
  padding: 12px 24px;
  border-radius: 30px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  display: flex;
  align-items: center;
  gap: 15px;
  z-index: 8999;
  border: 1px solid #e2e8f0;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  font-family: inherit;
}
.pf-sum-bar.visible {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}
.pf-sum-label {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 600;
}
.pf-sum-value {
  font-size: 1.2rem;
  color: #0f172a;
  font-weight: bold;
}
.pf-sum-close {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.5rem;
  cursor: pointer;
  margin-left: 10px;
  padding: 0;
  line-height: 1;
  transition: color 0.2s;
}
.pf-sum-close:hover {
  color: #ef4444;
}
body.sum-mode-active {
  cursor: cell !important;
}
body.sum-mode-active input,
body.sum-mode-active .sum-target {
  cursor: cell !important;
}
.sum-selected {
  outline: 2px dashed #10b981 !important;
  outline-offset: -2px;
  background-color: #ecfdf5 !important;
  color: #065f46 !important;
  transition: background-color 0.2s;
}

/* --- 12. RESPONSIVE MOBILE BUDGET --- */
@media (max-width: 768px) {
  .budget-view {
    padding: 10px !important;
    max-width: 100vw !important;
    box-sizing: border-box;
  }
  .budget-tabs-container {
    display: flex !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    justify-content: flex-start !important;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    padding-bottom: 5px;
    scrollbar-width: none;
  }
  .budget-tabs-container::-webkit-scrollbar {
    display: none;
  }
  .tab-item {
    flex: 0 0 auto !important;
    padding: 8px 16px !important;
  }
  .budget-view > div[style*="display:grid"] {
    gap: 10px !important;
    margin-bottom: 15px !important;
  }
  .budget-view > div[style*="display:grid"] > div {
    padding: 12px 10px !important;
  }
  .budget-view .categories-grid > .cat-card {
    padding: 0 !important;
  }
  .budget-view
    > div[style*="justify-content: space-between"]:not(.view-header) {
    flex-direction: column !important;
    align-items: stretch !important;
  }
  .budget-view
    > div[style*="justify-content: space-between"]:not(.view-header) {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
    margin-top: 20px !important;
  }
  .budget-view
    > div[style*="justify-content: space-between"]:not(.view-header)
    > div:first-child {
    width: 100% !important;
  }
  .budget-view
    > div[style*="justify-content: space-between"]:not(.view-header)
    > div:last-child {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    width: 100% !important;
    gap: 10px !important;
  }
  .budget-view
    > div[style*="justify-content: space-between"]:not(.view-header)
    > div:last-child
    .pf-btn {
    flex: 1 1 50% !important;
    padding: 0 5px !important;
    font-size: 0.8rem !important;
    justify-content: center;
  }
  .prev-section-header {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }
  .prev-section-header > div {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
    width: 100% !important;
    gap: 10px !important;
  }
  .prev-section-header .nav-group {
    display: flex !important;
    width: 100% !important;
    justify-content: space-between !important;
  }
  .prev-section-header .nav-group .btn-nav {
    flex: 1;
    text-align: center;
  }
  .prev-section-header .pf-btn {
    flex: 1 1 calc(50% - 10px) !important;
    width: auto !important;
    padding: 0 5px !important;
    margin: 0 !important;
    font-size: 0.85rem !important;
  }
  .prev-salary-table,
  .prev-alloc-table,
  .pf-table,
  .savings-table,
  .recap-wrapper {
    display: block !important;
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
    white-space: nowrap;
    border-radius: 8px !important;
    margin-bottom: 15px;
  }
  .recap-card {
    min-width: 100% !important;
    width: 100% !important;
  }
  .recap-table th,
  .recap-table td {
    padding: 8px 4px !important;
    font-size: 0.8rem !important;
  }
  .recap-header {
    font-size: 0.9rem !important;
    padding: 8px 10px !important;
  }
  .row-grand-total td {
    padding: 10px 4px !important;
  }
  .row-actions {
    position: static !important;
    display: inline-flex !important;
    opacity: 1 !important;
    box-shadow: none !important;
    background: transparent !important;
    border-left: none !important;
    padding: 1px 0 !important;
    transform: none !important;
    justify-content: flex-start;
    width: auto !important;
  }

  .col-sticky,
  .sticky-col {
    min-width: 120px !important;
    position: sticky !important;
    left: 0;
    z-index: 20 !important;
    white-space: normal !important;
    background: white !important;
  }
  th.sticky-col,
  th.col-sticky {
    z-index: 30 !important;
    background: #f8fafc !important;
  }
  .action-toolbar {
    flex-wrap: wrap !important;
    width: 100% !important;
  }
  .suivi-nav-group {
    width: 100%;
    justify-content: space-between;
  }
  .suivi-btn-nav {
    flex: 1;
    text-align: center;
  }
  .month-actions {
    opacity: 1 !important;
  }
  .btn-cell-delete {
    display: flex !important;
    opacity: 0.7;
    position: static;
    transform: none;
    right: auto;
    top: auto;
  }

  .cat-card-header {
    padding: 12px 10px; /* Plus serré sur mobile */
  }
}

```

---

### 📄 Fichier : `modules/budget/includes/api/get-fixed-costs.php`
```php

```

---

### 📄 Fichier : `modules/budget/includes/api/manage-item.php`
```php
<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php'; 
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- ACTION : SAUVEGARDER (AJOUT OU MODIF) ---
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'];
        $category = $_POST['category'];
        $type = $_POST['type'];
        $payment_day = empty($_POST['payment_day']) ? null : (int)$_POST['payment_day'];
        $is_estimate = $_POST['is_estimate'];
        $reg_month = $_POST['reg_month'];
        $keywords = $_POST['mapping_keywords'] ?? ''; 
        $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

        // ==========================================
        // NOUVELLE NORME COMPTABLE : Dépense = Négatif
        // ==========================================
        // On récupère le montant en valeur absolue (pour éviter les erreurs si l'utilisateur a tapé un '-')
        $amount = abs((float)$_POST['amount']); 
        
        // Si c'est un frais, on l'enregistre en négatif
        if ($category === 'expense') {
            $amount = -$amount;
        }
        // ==========================================

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE pf_budget_items SET name=?, amount=?, category=?, type=?, payment_day=?, is_estimate=?, reg_month=?, mapping_keywords=?, holiday_id=? WHERE id=?");
            $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id, $id]);
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO pf_budget_items (name, amount, category, type, payment_day, is_estimate, reg_month, mapping_keywords, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id]);
        }
        
        // Redirection
        header('Location: /budget.php?tab=recap');
        exit;
    }

    // --- ACTION : COCHER/DÉCOCHER RAPIDE (VIA JS FETCH) ---
    if ($action === 'toggle-check') {
        $id     = $_POST['id'];
        $status = $_POST['status']; 
        
        $sql = "UPDATE pf_budget_items SET is_checked = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTION : SUPPRIMER ---
    if ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM pf_budget_items WHERE id = ?")->execute([$id]);
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Location: /budget.php?tab=recap');
        }
        exit;
    }

    // --- ACTION : SUPPRIMER UNE DÉPENSE RÉELLE (Depuis l'onglet Suivi) ---
    if ($action === 'delete_expense') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([$id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTION : SAUVEGARDER UNE DÉPENSE MANUELLE (FETCH) ---
    if ($action === 'save_expense_manual') {
        header('Content-Type: application/json'); // On force le retour JSON pour notre script JS
        
        try {
            $id = !empty($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;
            $cat = $_POST['category'] ?? ''; 
            $amount = floatval($_POST['amount'] ?? 0); 
            $date = $_POST['date'] ?? date('Y-m-d');
            
            // 🛡️ SÉCURITÉ : Format attendu pour gestion_month (qui vient de input type="month") : YYYY-MM.
            $gestionMonthRaw = $_POST['gestion_month'] ?? '';
            
            // Si la valeur est vide ou '0000-00-00'
            if (empty($gestionMonthRaw) || strpos($gestionMonthRaw, '0000') !== false) {
                // On force le mois de gestion au 1er jour de la date de la dépense
                $gestionMonth = date('Y-m-01', strtotime($date));
            } else {
                // Si la valeur vient d'un input "month" (ex: "2026-04"), on rajoute "-01"
                $gestionMonth = (strlen($gestionMonthRaw) === 7) ? $gestionMonthRaw . '-01' : $gestionMonthRaw;
            }
            
            $label = trim($_POST['label'] ?? '');
            $budgetItemId = null;
            $holidayId = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

            if ($cat === 'School' && !empty($_POST['label_select'])) {
                $label = trim($_POST['label_select']);
            } elseif (($cat === 'Frais' || $cat === 'Income') && !empty($_POST['budget_item_id'])) {
                $budgetItemId = (int)$_POST['budget_item_id'];
            }

            // Vérification de sécurité
            if (empty($label) || $amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'Le label et un montant supérieur à 0 sont obligatoires.']);
                exit;
            }

            $is_credit = isset($_POST['is_credit']) ? (int)$_POST['is_credit'] : 0;
            $finalAmount = $is_credit ? abs($amount) : -abs($amount);
            
            if ($id) {
                // UPDATE
                $pdo->prepare("UPDATE pf_expenses SET date_exp=?, gestion_month=?, category=?, label=?, amount=?, budget_item_id=?, holiday_id=? WHERE id=?")
                    ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $budgetItemId, $holidayId, $id]);
            } else {
                // INSERT
                $uniqueRef = "MANUAL_" . uniqid();
                $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $uniqueRef, $budgetItemId, $holidayId]);
            }
            
            echo json_encode(['success' => true]);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
            exit;
        }
    }
}
```

---

### 📄 Fichier : `modules/budget/includes/api/save-budget.php`
```php
<?php
// modules/budget/includes/api/save-budget.php

require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();


$action = $_POST['action'] ?? '';

// =================================================================
// 7. SAUVEGARDE D'UNE NOTE GÉNÉRIQUE (pf_notes)
// =================================================================
if ($action === 'save_note') {
    // On affiche les erreurs s'il y a un souci SQL pour pouvoir déboguer
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    header('Content-Type: application/json');
    
    try {
        $noteType = $_POST['note_type'] ?? '';
        $refId = $_POST['reference_id'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($noteType) || empty($refId)) {
            throw new Exception("Le type et la référence de la note sont requis.");
        }

        // Insère ou met à jour la note si elle existe déjà
        $stmt = $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE content = VALUES(content)");
        $stmt->execute([$noteType, $refId, $content]);
        
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $e->getMessage()]);
    }
    exit;
}
// =================================================================

// 1. MISE A JOUR TABLEAU SALAIRES (AJAX)
if ($action === 'update_salary_config') {
    header('Content-Type: application/json'); // On précise JSON ici
    $year = $_POST['year'];
    $person = $_POST['person'];
    $field = $_POST['field']; // salary, mensualite, etc.
    $value = floatval($_POST['value']);

    // Liste des champs autorisés pour éviter les injections
    $allowed = ['salary', 'mensualite', 'frais_func', 'eco_perso', 'eco_family'];
    if (!in_array($field, $allowed)) { echo json_encode(['error'=>'Champ invalide']); exit; }

    $stmt = $pdo->prepare("INSERT INTO pf_salary_config (year, person, $field) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)");
    $stmt->execute([$year, $person, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// 2. MISE A JOUR TABLEAU REPARTITION (AJAX)
if ($action === 'update_allocation') {
    header('Content-Type: application/json'); 
    $date = $_POST['month_date'];
    $catId = $_POST['cat_id'];
    $person = $_POST['person']; // 'amount_alex' ou 'amount_laia'
    $value = floatval($_POST['value']);

    $stmt = $pdo->prepare("INSERT INTO pf_alloc_values (month_date, cat_id, $person) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $person = VALUES($person)");
    $stmt->execute([$date, $catId, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. GESTION DES CATEGORIES (Ajout)
if ($action === 'add_category') {
    $name = trim($_POST['name']);
    $target = trim($_POST['target']);
    $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO pf_alloc_categories (name, target, holiday_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $target, $holiday_id]);
    }
    
    // --- NOUVEAU : Réponse AJAX ---
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 4. MODIFICATION D'UNE CATEGORIE
if ($action === 'update_category') {
    $id = (int)$_POST['cat_id'];
    $name = trim($_POST['name']);
    $target = trim($_POST['target']);
    $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

    if ($id > 0 && !empty($name)) {
        $stmt = $pdo->prepare("UPDATE pf_alloc_categories SET name = ?, target = ?, holiday_id = ? WHERE id = ?");
        $stmt->execute([$name, $target, $holiday_id, $id]);
    }

    // --- NOUVEAU : Réponse AJAX ---
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 5. SUPPRESSION CATEGORIE
if ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0); 
    
    if ($id > 0) {
        $pdo->prepare("DELETE FROM pf_alloc_categories WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_alloc_values WHERE cat_id = ?")->execute([$id]); 
    }
    
    if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/budget.php')); 
    exit;
}

// 6. VALIDATION DES VIREMENTS (Complex Business Logic)
if ($action === 'validate_transfers') {
    header('Content-Type: application/json');
    $person = $_POST['person'];
    $monthDate = $_POST['month_date'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT v.*, c.name as cat_name, c.target, c.holiday_id 
            FROM pf_alloc_values v 
            JOIN pf_alloc_categories c ON v.cat_id = c.id
            WHERE v.month_date = ?
        ");
        $stmt->execute([$monthDate]);
        $budgetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transfersToDo = [];

        foreach ($budgetLines as $line) {
            $amount = ($person === 'Alex') ? $line['amount_alex'] : $line['amount_laia'];
            if ($amount <= 0) continue; 

            $target = trim($line['target']);
            $catName = trim($line['cat_name']);
            $holidayId = $line['holiday_id']; 

            $targetOwner = null;
            if ($target === 'vers L.Perso') { $targetOwner = $person; } 
            elseif ($target === 'vers L.Pol') { $targetOwner = 'Pol'; } 
            elseif ($target === 'vers L.Pep') { $targetOwner = 'Pep'; } 
            elseif ($target === 'vers commune') { continue; }

            if ($targetOwner) {
                if (!isset($transfersToDo[$targetOwner])) {
                    $transfersToDo[$targetOwner] = ['total_add' => 0, 'cats' => []];
                }
                $transfersToDo[$targetOwner]['total_add'] += $amount;
                
                if (!isset($transfersToDo[$targetOwner]['cats'][$catName])) {
                    $transfersToDo[$targetOwner]['cats'][$catName] = ['amount' => 0, 'holiday_id' => $holidayId];
                }
                $transfersToDo[$targetOwner]['cats'][$catName]['amount'] += $amount;
            }
        }

        foreach ($transfersToDo as $owner => $data) {
            // A. VERIFIER EXISTENCE (Inchangé)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pf_savings WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtCheck->execute([$owner, $monthDate]);
            $exists = $stmtCheck->fetchColumn() > 0;

            if (!$exists) {
                $prevDate = date('Y-m-d', strtotime($monthDate . ' -1 month'));
                $stmtPrev = $pdo->prepare("SELECT category, amount, holiday_id FROM pf_savings WHERE owner = ? AND month_date = ?");
                $stmtPrev->execute([$owner, $prevDate]);
                $prevLines = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

                if (empty($prevLines)) { $prevLines = [['category' => 'TOTAL_BANQUE', 'amount' => 0, 'holiday_id' => null]]; }

                $stmtInsert = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount, holiday_id) VALUES (?, ?, ?, ?, ?)");
                foreach ($prevLines as $row) {
                    $stmtInsert->execute([$monthDate, $owner, $row['category'], $row['amount'], $row['holiday_id']]);
                }
            }

            // B. UPDATE TOTAL (Inchangé)
            $stmtUpdTotal = $pdo->prepare("UPDATE pf_savings SET amount = amount + ? WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtUpdTotal->execute([$data['total_add'], $owner, $monthDate]);

            // C. UPDATE CATÉGORIES (Modifié pour gérer le holiday_id)
            foreach ($data['cats'] as $catName => $catInfo) {
                $catAmount = $catInfo['amount'];
                $catHolidayId = $catInfo['holiday_id']; // NOUVEAU

                if ($catName === 'Eco Alex' || $catName === 'Eco Laia') { continue; }

                $stmtCheckCat = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
                $stmtCheckCat->execute([$owner, $monthDate, $catName]);
                $catId = $stmtCheckCat->fetchColumn();

                if ($catId) {
                    // Update : On actualise aussi le holiday_id au cas où il aurait changé
                    $stmtUpdateCat = $pdo->prepare("UPDATE pf_savings SET amount = amount + ?, holiday_id = ? WHERE id = ?");
                    $stmtUpdateCat->execute([$catAmount, $catHolidayId, $catId]);
                } else {
                    // Insert
                    $stmtInsertCat = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount, holiday_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsertCat->execute([$monthDate, $owner, $catName, $catAmount, $catHolidayId]);
                }
            }
        }

        // 3. ENREGISTRER LA VALIDATION (Mise à jour table existante)
        
        // a. Trouver l'ID de la catégorie système
        $stmtSys = $pdo->prepare("SELECT id FROM pf_alloc_categories WHERE name = 'SYSTEM_VALIDATION' LIMIT 1");
        $stmtSys->execute();
        $sysCatId = $stmtSys->fetchColumn();

        if ($sysCatId) {
            
            if ($person === 'Alex') {
                $sql = "INSERT INTO pf_alloc_values (month_date, cat_id, amount_alex, amount_laia) 
                        VALUES (?, ?, 1, 0) 
                        ON DUPLICATE KEY UPDATE amount_alex = 1";
            } else {
                $sql = "INSERT INTO pf_alloc_values (month_date, cat_id, amount_alex, amount_laia) 
                        VALUES (?, ?, 0, 1) 
                        ON DUPLICATE KEY UPDATE amount_laia = 1";
            }
            
            $stmtVal = $pdo->prepare($sql);
            $stmtVal->execute([$monthDate, $sysCatId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;

    
}
```

---

### 📄 Fichier : `modules/budget/includes/api/save-savings.php`
```php
<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

// =================================================================
// MISE À JOUR D'UNE CELLULE EN DIRECT (AJAX)
// =================================================================
if ($action === 'update_single_entry') {
    header('Content-Type: application/json');
    $month = $_POST['month_date'];
    $cat = $_POST['category'];
    $owner = $_POST['owner'];
    $amount = (float)$_POST['amount'];

    try {
        if ($amount == 0 && $cat !== 'TOTAL_BANQUE') {
            // Si on met à 0 une ligne (autre que le total), on supprime l'entrée pour garder la base propre
            $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE month_date=? AND owner=? AND category=?");
            $stmt->execute([$month, $owner, $cat]);
        } else {
            // Sinon on insère ou on met à jour
            $stmt = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            $stmt->execute([$month, $owner, $cat, $amount]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SUPPRESSION D'UNE ENTRÉE UNIQUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_entry') {
    $owner = $_POST['owner'];
    $date = $_POST['month_date'];
    $cat = $_POST['category'];

    $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
    $stmt->execute([$owner, $date, $cat]);
    
    echo json_encode(['success' => true]);
    exit;
}

// --- ACTION : DUPLICATION DE MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate_month') {
    $owner = $_POST['owner'];
    $sourceDate = $_POST['source_date'];
    $targetDate = $_POST['target_date'];
    $newTotal = floatval($_POST['new_total']);

    try {
        $pdo->beginTransaction();

        // 1. Vérifier si le mois cible existe déjà
        $check = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? LIMIT 1");
        $check->execute([$owner, $targetDate]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ce mois existe déjà !']);
            exit;
        }

        // 2. Insérer le nouveau TOTAL BANQUE
        $stmtIns = $pdo->prepare("INSERT INTO pf_savings (owner, month_date, category, amount) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$owner, $targetDate, 'TOTAL_BANQUE', $newTotal]);

        // 3. Copier toutes les catégories (sauf TOTAL_BANQUE) du mois source
        $sqlCopy = "INSERT INTO pf_savings (owner, month_date, category, amount)
                    SELECT owner, ?, category, amount 
                    FROM pf_savings 
                    WHERE owner = ? AND month_date = ? AND category != 'TOTAL_BANQUE'";
        
        $stmtCopy = $pdo->prepare($sqlCopy);
        $stmtCopy->execute([$targetDate, $owner, $sourceDate]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SUPPRESSION GLOBALE D'UN MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_month_global') {
    $owner = $_POST['owner'];
    $date = $_POST['month_date'];

    try {
        $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ?");
        $stmt->execute([$owner, $date]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SAUVEGARDE CLASSIQUE (MODALE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner = $_POST['owner'];
    
    // CORRECTION ICI : On récupère l'onglet de redirection (Nens, Alex ou Laia)
    $redirectTab = $_POST['redirect_tab'] ?? $owner; 
    
    $dateInput = $_POST['month_date']; 
    $dateObj = new DateTime($dateInput);
    $monthDate = $dateObj->format('Y-m-01');
    $values = $_POST['values'] ?? []; // Tableau généré par nos champs JS: values[Catégorie]

    try {
        $pdo->beginTransaction();
        
        // On supprime d'abord les anciennes données du mois pour cet utilisateur
        $stmtDel = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ?");
        $stmtDel->execute([$owner, $monthDate]);
        
        // On réinsère les nouvelles données
        $stmtIns = $pdo->prepare("INSERT INTO pf_savings (owner, month_date, category, amount) VALUES (?, ?, ?, ?)");
        foreach ($values as $category => $amount) {
            $amount = floatval($amount);
            // On enregistre si c'est positif OU si c'est le total banque
            if ($amount > 0 || $category === 'TOTAL_BANQUE') {
                $stmtIns->execute([$owner, $monthDate, $category, $amount]);
            }
        }
        $pdo->commit();
        
        // Redirection vers le bon onglet
        header("Location: /budget.php?tab=epargne&owner=" . urlencode($redirectTab));        
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur : " . $e->getMessage());
    }
}
```

---

### 📄 Fichier : `modules/budget/views/budget_prev.php`
```php
<?php
// modules/budget/views/budget_prev.php

$currentYear = date('Y');

// 1. Récupération Config Salaires
$salaryConfig = [];
$stmt = $pdo->prepare("SELECT * FROM pf_salary_config WHERE year = ?");
$stmt->execute([$currentYear]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $salaryConfig[$row['person']] = $row;
}

foreach (['Alex', 'Laia'] as $p) {
    if (!isset($salaryConfig[$p])) {
        $salaryConfig[$p] = ['salary'=>0, 'mensualite'=>0, 'frais_func'=>0, 'eco_perso'=>0, 'eco_family'=>0];
    }
}

// 2. Récupération Catégories
$cats = $pdo->query("SELECT * FROM pf_alloc_categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Gestion du mois FOCUS et Navigation
$focusDate = isset($_GET['focus_date']) ? $_GET['focus_date'] : date('Y-m-01');
$focusTs = strtotime($focusDate);

$months = [];
for ($i = 0; $i < 6; $i++) {
    $months[] = date('Y-m-01', strtotime("-$i months", $focusTs));
}

$prevMonthLink = date('Y-m-01', strtotime("-1 month", $focusTs));
$nextMonthLink = date('Y-m-01', strtotime("+1 month", $focusTs));

// NOUVEAU : Récupération des Cycles configurés dans pf_notes
$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// 4. Récupération Valeurs Répartition
$inQuery = implode(',', array_fill(0, count($months), '?'));
$stmt = $pdo->prepare("SELECT * FROM pf_alloc_values WHERE month_date IN ($inQuery)");
$stmt->execute($months);

$allocs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allocs[$row['month_date']][$row['cat_id']] = $row;
}

// 5. Récupération de l'ID de la catégorie système
$sysCatId = null;
foreach ($cats as $key => $c) {
    if ($c['name'] === 'SYSTEM_VALIDATION') {
        $sysCatId = $c['id'];
        unset($cats[$key]); 
        break;
    }
}

// 6. Lecture des statuts de validation
$focusDate = $months[0];
$isValidatedAlex = false;
$isValidatedLaia = false;

if ($sysCatId && isset($allocs[$focusDate][$sysCatId])) {
    $row = $allocs[$focusDate][$sysCatId];
    $isValidatedAlex = ($row['amount_alex'] == 1);
    $isValidatedLaia = ($row['amount_laia'] == 1);
}

$stmtNote = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'budget_prev' AND reference_id = ?");
$stmtNote->execute([$focusDate]);
$currentNote = $stmtNote->fetchColumn();

// 7. Récupération des vacances actives
$activeHolidays = $pdo->query("SELECT id, title FROM pf_holidays WHERE status IN ('draft', 'planned', 'booked') ORDER BY start_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper function pour les mois
function getTranslatedMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="prev-container">

    <div>
        <div class="prev-section-header">
            <h2><?= tr('bud_prev_incomes') ?> <?= $currentYear ?></h2>
        </div>
        
        <table class="prev-salary-table">
            <thead>
                <tr>
                    <th><?= tr('bud_prev_person') ?></th>
                    <th><?= tr('bud_prev_salary') ?></th>
                    <th><?= tr('bud_prev_monthly_payment') ?><br><small style="font-weight:normal; text-transform:none;">(<?= tr('bud_prev_joint_account') ?>)</small></th>
                    <th><?= tr('bud_prev_func_expenses') ?></th>
                    <th><?= tr('bud_prev_perso_savings') ?></th>
                    <th style="background:#f0f9ff; color:#0284c7;"><?= tr('bud_prev_family_savings') ?><br><small style="font-weight:normal; text-transform:none;">(<?= tr('bud_prev_available') ?>)</small></th>
                    <th><?= tr('bud_prev_perso_remaining') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Définition des noms à partir de la config
                $family_members = [ID_ALEX => 'Alex', ID_LAIA => 'Laia'];

                foreach ($family_members as $id => $p): 
                    $d = $salaryConfig[$p]; 
                    $restant = $d['salary'] - ($d['mensualite'] + $d['frais_func'] + $d['eco_perso'] + $d['eco_family']);
                    $borderColor = ($id === ID_ALEX) ? '#0891b2' : '#f59e0b';
                ?>
                <tr data-person="<?= $p ?>">
                    <td style="text-align:left; font-weight:bold; color:var(--text-main); border-left:4px solid <?= $borderColor ?>;">
                        <?= $p ?>
                    </td>
                    <td><input type="number" class="prev-input" data-field="salary" value="<?= round($d['salary']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="mensualite" value="<?= round($d['mensualite']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="frais_func" value="<?= round($d['frais_func']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td><input type="number" class="prev-input" data-field="eco_perso" value="<?= round($d['eco_perso']) ?>" onchange="updateSalary('<?= $p ?>', this)"></td>
                    <td style="background:#f0f9ff;">
                        <input type="number" class="prev-input bold-blue" id="eco_family_<?= $p ?>" data-field="eco_family" value="<?= round($d['eco_family']) ?>" onchange="updateSalary('<?= $p ?>', this)">
                    </td>
                    <td id="restant_<?= $p ?>" style="font-weight:bold; color:var(--text-muted); text-align:center;" class="sum-target">
                        <?= number_format($restant, 0, ',', ' ') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <div class="prev-section-header" style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
    <div style="display:flex; gap:10px; align-items:center;">
        <h2 style="margin:0; font-size:1.3rem;"><?= tr('bud_prev_budget_alloc') ?></h2>
        <div class="nav-group" style="display:flex; gap:5px;">
            <a href="?tab=budget_prev&focus_date=<?= $prevMonthLink ?>" class="btn-nav">◀</a>
            <a href="?tab=budget_prev&focus_date=<?= date('Y-m-01') ?>" class="btn-nav"><?= tr('bud_prev_today') ?></a>
            <a href="?tab=budget_prev&focus_date=<?= $nextMonthLink ?>" class="btn-nav">▶</a>
        </div>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" class="pf-btn btn-secondary" onclick="duplicateMonth()">
            🔁 <?= tr('bud_sav_add_one_month') ?>
        </button>
        <button type="button" class="pf-btn" onclick="document.getElementById('addCatModal').style.display='flex'; document.body.classList.add('no-scroll');">
            ＋ <?= tr('bud_prev_new_line') ?>
        </button>
    </div>
</div>

        <div class="prev-timeline-wrapper">
            <table class="prev-alloc-table">
                <thead>
                    <tr>
                        <th class="col-sticky header-cell"></th>
                        <?php foreach ($months as $month): 
                            $isCurrent = ($month == date('Y-m-01'));
                            $cls = $isCurrent ? 'current' : '';
                        ?>
                            <th colspan="3" class="th-month <?= $cls ?>">
                                <span style="text-transform:capitalize;"><?= getTranslatedMonthName($month) ?></span>
                                <?php 
                                if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                    $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                    echo "<div style='font-size:0.75rem; font-weight:normal; color:#64748b; margin-top:2px;'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</div>";
                                }
                                ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="col-sticky header-cell"></th>
                        <?php foreach ($months as $month): ?>
                            <th class="th-sub">Global</th>
                            <th class="th-sub txt-alex">Alex</th>
                            <th class="th-sub txt-laia">Laia</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="col-sticky" style="text-align:right !important;">Total</td>
                        <?php foreach ($months as $m): ?>
                            <td class="txt-global sum-target" style="border-left:2px solid #e2e8f0;" id="total_global_<?= $m ?>">0</td>
                            <td class="txt-alex sum-target" id="total_alex_<?= $m ?>">0</td>
                            <td class="txt-laia sum-target" id="total_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <tr class="row-restant">
                        <td class="col-sticky" style="text-align:right !important;"><?= tr('bud_prev_remaining') ?> (Eco Family)</td>
                        <?php foreach ($months as $m): ?>
                            <td style="border-left:2px solid #cbd5e1; background:#e2e8f0;">-</td>
                            <td class="val-ok sum-target" id="restant_alloc_alex_<?= $m ?>">0</td>
                            <td class="val-ok sum-target" id="restant_alloc_laia_<?= $m ?>">0</td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($cats as $cat): 
                    $isIndicative = ($cat['name'] === 'Eco Alex' || $cat['name'] === 'Eco Laia');
                    $rowClass = $isIndicative ? 'row-indicative' : '';
                    $inputClass = $isIndicative ? 'ignore-calc' : ''; 
                    $rowStyle = $isIndicative ? 'background:#f8fafc; color:#94a3b8;' : '';
                ?>
                <tr class="<?= $rowClass ?>" style="<?= $rowStyle ?>">
                    <td class="col-sticky" style="position:relative; <?= $isIndicative ? 'opacity:0.8;' : '' ?>">
                        <div style="font-weight:600; color:<?= $isIndicative ? '#64748b' : 'var(--text-main)' ?>;">
                            <?= htmlspecialchars($cat['name']) ?> 
                            <?php if(!empty($cat['holiday_id'])) echo " 🌴"; ?>
                            <?php if($isIndicative): ?><span style="font-size:0.7rem; border:1px solid #cbd5e1; border-radius:4px; padding:0 4px; margin-left:5px;">Info</span><?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-style:italic; text-align:right;">
                            <?= htmlspecialchars($cat['target']) ?>
                        </div>
                        
                        <div class="row-actions">
                            <button type="button" 
                                    class="btn-icon-action edit" 
                                    title="<?= tr('edit') ?>"
                                    data-id="<?= $cat['id'] ?>"
                                    data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                    data-target="<?= htmlspecialchars($cat['target']) ?>"
                                    data-holiday="<?= $cat['holiday_id'] ?? '' ?>"
                                    onclick="openEditModal(this)">
                                ✎
                            </button>

                            <button type="button" 
                                    onclick="deleteCategory(<?= $cat['id'] ?>)" 
                                    class="btn-icon-action delete" title="<?= tr('delete') ?>">🗑️
                            </button>
                        </div>
                    </td>
                    <?php foreach ($months as $m): 
                        $val = $allocs[$m][$cat['id']] ?? ['amount_alex'=>0, 'amount_laia'=>0];
                    ?>
                        <td class="txt-global sum-target" style="border-left:2px solid #e2e8f0; <?= $isIndicative?'color:#94a3b8; font-weight:normal;':'' ?>" id="g_<?= $m ?>_<?= $cat['id'] ?>">0</td>
                        
                        <td>
                            <input type="number" step="1" class="prev-input txt-alex inp-alex-<?= $m ?> <?= $inputClass ?>" 
                                   style="<?= $isIndicative ? 'color:#64748b;' : '' ?>"
                                   value="<?= $val['amount_alex'] == 0 ? '' : round($val['amount_alex']) ?>" 
                                   placeholder="-"
                                   data-target="<?= htmlspecialchars($cat['target']) ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, 'amount_alex', this)">
                        </td>
                        
                        <td>
                            <input type="number" step="1" class="prev-input txt-laia inp-laia-<?= $m ?> <?= $inputClass ?>" 
                                   style="<?= $isIndicative ? 'color:#64748b;' : '' ?>"
                                   value="<?= $val['amount_laia'] == 0 ? '' : round($val['amount_laia']) ?>" 
                                   placeholder="-"
                                   data-target="<?= htmlspecialchars($cat['target']) ?>"
                                   onchange="updateAlloc('<?= $m ?>', <?= $cat['id'] ?>, 'amount_laia', this)">
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin: 20px 0; background: white; padding: 20px; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">
                📝 <?= tr('bud_prev_notes_for') ?> <span style="text-transform:capitalize;"><?= getTranslatedMonthName($focusDate) ?></span>
            </h3>
            <span id="note-save-indicator" style="font-size:0.85rem; color:#10b981; font-weight:bold; opacity:0; transition:opacity 0.3s;">
                ✓ <?= tr('bud_prev_saved') ?>
            </span>
        </div>
        
        <textarea 
            id="monthNoteArea" 
            class="pf-input" 
            rows="3" 
            placeholder="<?= tr('bud_prev_notes_ph') ?>" 
            style="width: 100%; resize: vertical; border-color: #cbd5e1; background: #f8fafc; margin-bottom: 10px;"
        ><?= htmlspecialchars((string)$currentNote) ?></textarea>
        
        <div style="text-align: right;">
            <button type="button" class="pf-btn" style="width: auto; display: inline-flex;" 
                    onclick="saveGenericNote('budget_prev', '<?= $focusDate ?>', document.getElementById('monthNoteArea').value)">
                <?= tr('bud_prev_save_note') ?>
            </button>
        </div>
    </div>

    <?php
    $focusMonth = $months[0]; 
    
    $targetsOrder = ['vers commune', 'vers L.Pol', 'vers L.Pep', 'vers L.Perso'];
    
    $allTargets = $targetsOrder;
    foreach($cats as $c) {
        $t = trim($c['target']);
        if(!empty($t) && !in_array($t, $allTargets)) {
            $allTargets[] = $t;
        }
    }
    $allTargets = array_unique($allTargets);

    $summaryData = [];
    foreach($allTargets as $t) $summaryData[$t] = ['Alex' => 0, 'Laia' => 0];

    foreach ($cats as $cat) {
        $t = trim($cat['target']);
        if (empty($t)) continue;

        $val = $allocs[$focusMonth][$cat['id']] ?? ['amount_alex'=>0, 'amount_laia'=>0];
        
        if (!isset($summaryData[$t])) {
            $summaryData[$t] = ['Alex' => 0, 'Laia' => 0];
        }

        $summaryData[$t]['Alex'] += $val['amount_alex'];
        $summaryData[$t]['Laia'] += $val['amount_laia'];
    }

    $grandTotalAlex = 0;
    $grandTotalLaia = 0;
    $grandTotalGlobal = 0;
    ?>

    <div class="recap-wrapper">
        <div class="recap-card">
            <div class="recap-header">
                <?= tr('bud_prev_transfers_to_make') ?> - <span style="text-transform:capitalize;"><?= getTranslatedMonthName($focusMonth) ?></span>
            </div>
            
            <table class="recap-table">
                <thead>
                    <tr>
                        <th style="text-align:left;"><?= tr('bud_prev_destination') ?></th>
                        
                        <th class="col-alex">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:5px;">
                                <span>ALEX</span>
                                <?php if($isValidatedAlex): ?>
                                    <div style="background:#10b981; color:white; padding:4px 8px; border-radius:4px; font-size:0.7rem; display:flex; align-items:center; gap:4px;">
                                        ✓ <?= tr('bud_prev_done') ?>
                                    </div>
                                <?php else: ?>
                                    <button onclick="validateTransfers('Alex', '<?= $focusMonth ?>')" class="pf-btn btn-small" style="background:white; color:#0891b2; border:1px solid #0891b2; font-size:0.7rem; padding:2px 8px; height:auto;">
                                        <?= tr('bud_prev_validate') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </th>

                        <th class="col-laia">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:5px;">
                                <span>LAIA</span>
                                <?php if($isValidatedLaia): ?>
                                    <div style="background:#10b981; color:white; padding:4px 8px; border-radius:4px; font-size:0.7rem; display:flex; align-items:center; gap:4px;">
                                        ✓ <?= tr('bud_prev_done') ?>
                                    </div>
                                <?php else: ?>
                                    <button onclick="validateTransfers('Laia', '<?= $focusMonth ?>')" class="pf-btn btn-small" style="background:white; color:#d97706; border:1px solid #d97706; font-size:0.7rem; padding:2px 8px; height:auto;">
                                        <?= tr('bud_prev_validate') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </th>
                        
                        <th class="col-global">Global</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allTargets as $target): 
                        $tId = md5($target); 
                    ?>
                    <tr id="row_summary_<?= $tId ?>">
                        <td><?= htmlspecialchars($target) ?></td>
                        <td class="col-alex sum-target" id="sum_alex_<?= $tId ?>">0 €</td>
                        <td class="col-laia sum-target" id="sum_laia_<?= $tId ?>">0 €</td>
                        <td class="col-global sum-target" id="sum_global_<?= $tId ?>">0 €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="row-grand-total">
                        <td><?= tr('bud_prev_grand_total') ?></td>
                        <td class="col-alex sum-target" id="grand_total_alex">0 €</td>
                        <td class="col-laia sum-target" id="grand_total_laia">0 €</td>
                        <td class="col-global sum-target" id="grand_total_global">0 €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div id="addCatModal" class="pf-modal">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:400px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;"><?= tr('bud_prev_new_line_title') ?></h3>
            <button onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_name') ?></label>
                <input type="text" name="name" class="pf-input" required>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" class="pf-input" required>
                    <option value="" disabled selected>-- <?= tr('bud_prev_choose') ?> --</option>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="pf-label" style="color:#8b5cf6;">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" class="pf-input" style="border-color:#8b5cf6; background:#f5f3ff;">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('bud_add_title') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="editCatModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; font-size:1.2rem;"><?= tr('bud_prev_edit_line_title') ?></h3>
            <button onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form action="/modules/budget/includes/api/save-budget.php" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="edit_cat_name" class="pf-input" required>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_prev_label_target') ?></label>
                <select name="target" id="edit_cat_target" class="pf-input" required>
                    <option value="vers L.Pol">vers L.Pol</option>
                    <option value="vers L.Pep">vers L.Pep</option>
                    <option value="vers L.Perso">vers L.Perso</option>
                    <option value="vers commune">vers commune</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="pf-label" style="color:#8b5cf6;">🌴 <?= tr('bud_prev_link_holiday') ?></label>
                <select name="holiday_id" id="edit_cat_holiday" class="pf-input" style="border-color:#8b5cf6; background:#f5f3ff;">
                    <option value="">-- <?= tr('bud_prev_no_link') ?> --</option>
                    <?php foreach ($activeHolidays as $hol): ?>
                        <option value="<?= $hol['id'] ?>"><?= htmlspecialchars($hol['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editCatModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>


<button id="fabSumMode" class="pf-fab-sum" onclick="toggleSumMode()" title="<?= tr('bud_sav_sum_mode_title') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="8" y1="12" x2="16" y2="12"></line><line x1="12" y1="8" x2="12" y2="16"></line></svg>
</button>

<div id="sumResultBar" class="pf-sum-bar">
    <span class="pf-sum-label"><?= tr('bud_sav_selection') ?></span>
    <span id="sumResultValue" class="pf-sum-value">0 €</span>
    <button onclick="toggleSumMode()" class="pf-sum-close" title="<?= tr('btn_close') ?>">&times;</button>
</div>


<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_prev_label_name': <?= json_encode(tr('bud_prev_label_name')) ?>,
    'bud_prev_err_no_history': <?= json_encode(tr('bud_prev_err_no_history')) ?>,
    'bud_prev_confirm_copy': <?= json_encode(tr('bud_prev_confirm_copy')) ?>,
    'bud_prev_confirm_transfers': <?= json_encode(tr('bud_prev_confirm_transfers')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>
};


const currentYear = <?= $currentYear ?? 'new Date().getFullYear()' ?>;
const months = <?= json_encode($months ?? []) ?>;

function openEditModal(btn) {
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const target = btn.getAttribute('data-target');
    const holiday = btn.getAttribute('data-holiday'); 

    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    document.getElementById('edit_cat_target').value = target;
    document.getElementById('edit_cat_holiday').value = holiday; 
    
    document.getElementById('editCatModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

function updateSalary(person, input) {
    const row = input.closest('tr');
    const salary = parseFloat(row.querySelector('[data-field="salary"]').value) || 0;
    const mens = parseFloat(row.querySelector('[data-field="mensualite"]').value) || 0;
    const frais = parseFloat(row.querySelector('[data-field="frais_func"]').value) || 0;
    const ecoP = parseFloat(row.querySelector('[data-field="eco_perso"]').value) || 0;
    const ecoF = parseFloat(row.querySelector('[data-field="eco_family"]').value) || 0;

    const restant = salary - (mens + frais + ecoP + ecoF);
    document.getElementById('restant_' + person).innerText = Math.round(restant).toLocaleString(window.appLang) + ' €';

    saveData('update_salary_config', { year: currentYear, person: person, field: input.dataset.field, value: input.value });
    recalcAllAllocations();
}

function updateAlloc(month, catId, personField, input) {
    saveData('update_allocation', { month_date: month, cat_id: catId, person: personField, value: input.value || 0 });
    recalcAllAllocations();
}

function duplicateMonth() {
    const targetDateStr = months[0];
    const sourceDateStr = months[1];

    if (!sourceDateStr) {
        alert(window.I18N['bud_prev_err_no_history']);
        return;
    }

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(sourceDateStr); 
    const targetName = formatMonth(targetDateStr); 

    const message = window.I18N['bud_prev_confirm_copy'].replace('%s', sourceName).replace('%t', targetName);

    if(!confirm(message)) return;
    
    document.querySelectorAll('.inp-alex-' + sourceDateStr).forEach(sourceInput => {
        const catIdMatch = sourceInput.getAttribute('onchange').match(/, (\d+),/);
        if(!catIdMatch) return;
        const catId = catIdMatch[1];
        const row = sourceInput.closest('tr');

        const valAlex = sourceInput.value;
        const targetAlex = row.querySelector('.inp-alex-' + targetDateStr);
        if(targetAlex) { 
            targetAlex.value = valAlex; 
            updateAlloc(targetDateStr, catId, 'amount_alex', targetAlex); 
        }

        const valLaia = row.querySelector('.inp-laia-' + sourceDateStr).value;
        const targetLaia = row.querySelector('.inp-laia-' + targetDateStr);
        if(targetLaia) { 
            targetLaia.value = valLaia; 
            updateAlloc(targetDateStr, catId, 'amount_laia', targetLaia); 
        }
    });
}

function recalcAllAllocations() {
    const budgetAlex = parseFloat(document.getElementById('eco_family_Alex').value) || 0;
    const budgetLaia = parseFloat(document.getElementById('eco_family_Laia').value) || 0;

    months.forEach(m => {
        let sumAlex = 0;
        let sumLaia = 0;

        document.querySelectorAll('.inp-alex-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            if (!inp.classList.contains('ignore-calc')) sumAlex += val;
            
            const row = inp.closest('tr');
            const laiaVal = parseFloat(row.querySelector('.inp-laia-' + m).value) || 0;
            const globalSum = val + laiaVal;
            
            const onchangeStr = inp.getAttribute('onchange');
            const matches = onchangeStr.match(/, (\d+),/);
            if(matches && matches[1]) {
                const gCell = document.getElementById('g_' + m + '_' + matches[1]);
                if(gCell) gCell.innerText = globalSum > 0 ? Math.round(globalSum) : '-';
            }
        });

        document.querySelectorAll('.inp-laia-' + m).forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            if (!inp.classList.contains('ignore-calc')) sumLaia += val;
        });

        document.getElementById('total_alex_' + m).innerText = Math.round(sumAlex) + ' €';
        document.getElementById('total_laia_' + m).innerText = Math.round(sumLaia) + ' €';
        document.getElementById('total_global_' + m).innerText = Math.round(sumAlex + sumLaia) + ' €';

        const restAlex = budgetAlex - sumAlex;
        const restLaia = budgetLaia - sumLaia;

        const elRestAlex = document.getElementById('restant_alloc_alex_' + m);
        const elRestLaia = document.getElementById('restant_alloc_laia_' + m);

        elRestAlex.innerText = Math.round(restAlex) + ' €';
        elRestAlex.className = 'val-' + (restAlex >= 0 ? 'ok' : 'ko') + ' sum-target'; 

        elRestLaia.innerText = Math.round(restLaia) + ' €';
        elRestLaia.className = 'val-' + (restLaia >= 0 ? 'ok' : 'ko') + ' sum-target';
    });
    updateSummaryTable();
    if(isSumModeActive) updateSumResult(); 
}

function updateSummaryTable() {
    const focusMonth = months[0];
    let grandTotalAlex = 0;
    let grandTotalLaia = 0;    
    const dataByTarget = {};

    document.querySelectorAll('.inp-alex-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].alex += (parseFloat(inp.value) || 0);
        }
    });

    document.querySelectorAll('.inp-laia-' + focusMonth).forEach(inp => {
        const target = inp.getAttribute('data-target');
        if(target) {
            if(!dataByTarget[target]) dataByTarget[target] = { alex: 0, laia: 0 };
            dataByTarget[target].laia += (parseFloat(inp.value) || 0);
        }
    });

    const tbody = document.querySelector('.recap-table tbody');
    if(tbody) {
        Array.from(tbody.rows).forEach(row => {
            const targetName = row.cells[0].innerText.trim(); 
            const alexSum = dataByTarget[targetName] ? dataByTarget[targetName].alex : 0;
            const laiaSum = dataByTarget[targetName] ? dataByTarget[targetName].laia : 0;
            const globalSum = alexSum + laiaSum;

            row.cells[1].innerText = Math.round(alexSum).toLocaleString(window.appLang) + ' €';
            row.cells[2].innerText = Math.round(laiaSum).toLocaleString(window.appLang) + ' €';
            row.cells[3].innerText = Math.round(globalSum).toLocaleString(window.appLang) + ' €';

            grandTotalAlex += alexSum;
            grandTotalLaia += laiaSum;
        });
    }

    document.getElementById('grand_total_alex').innerText = Math.round(grandTotalAlex).toLocaleString(window.appLang) + ' €';
    document.getElementById('grand_total_laia').innerText = Math.round(grandTotalLaia).toLocaleString(window.appLang) + ' €';
    document.getElementById('grand_total_global').innerText = Math.round(grandTotalAlex + grandTotalLaia).toLocaleString(window.appLang) + ' €';
}

function saveData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) formData.append(key, data[key]);
    fetch('modules/budget/includes/api/save-budget.php', { method: 'POST', body: formData });
}

function validateTransfers(person, month) {
    const msg = window.I18N['bud_prev_confirm_transfers'].replace('%p', person).replace('%m', month);
    if (!confirm(msg)) return;

    const formData = new FormData();
    formData.append('action', 'validate_transfers');
    formData.append('person', person);
    formData.append('month_date', month);
    
    fetch('modules/budget/includes/api/save-budget.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) window.location.reload();
        else alert("Erreur: " + data.error);
    })
    .catch(e => alert(window.I18N['bud_err_tech']));
}

function saveGenericNote(noteType, refId, content) {
    const formData = new FormData();
    formData.append('action', 'save_note');
    formData.append('note_type', noteType);    
    formData.append('reference_id', refId);    
    formData.append('content', content);       

    fetch('modules/budget/includes/api/save-budget.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        if (!r.ok) throw new Error(`Erreur HTTP ${r.status} : ${text}`);
        if (!text) throw new Error("Le serveur a renvoyé une réponse vide.");
        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error("Réponse inattendue (non JSON) : " + text);
        }
    })
    .then(data => {
        if(data.success) {
            const indicator = document.getElementById('note-save-indicator');
            if(indicator) {
                indicator.style.opacity = '1';
                setTimeout(() => indicator.style.opacity = '0', 2000);
            }
        } else {
            alert("Erreur lors de la sauvegarde : " + data.error);
        }
    })
    .catch(e => {
        console.error('Erreur technique:', e);
        alert(e.message);
    });
}

let isSumModeActive = false;
let selectedElementsForSum = new Set();

function toggleSumMode() {
    isSumModeActive = !isSumModeActive;
    
    const fab = document.getElementById('fabSumMode');
    const resultBar = document.getElementById('sumResultBar');
    
    if (isSumModeActive) {
        fab.classList.add('active');
        document.body.classList.add('sum-mode-active');
        resultBar.classList.add('visible');
        updateSumResult();
    } else {
        fab.classList.remove('active');
        document.body.classList.remove('sum-mode-active');
        resultBar.classList.remove('visible');
        
        selectedElementsForSum.forEach(el => el.classList.remove('sum-selected'));
        selectedElementsForSum.clear();
    }
}

function extractNumberFromText(text) {
    if (!text) return 0;
    const cleanText = text.replace(',', '.').replace(/[^\d.-]/g, '');
    return parseFloat(cleanText) || 0;
}

function updateSumResult() {
    let total = 0;
    selectedElementsForSum.forEach(el => {
        let val = 0;
        if (el.tagName === 'INPUT') {
            val = parseFloat(el.value) || 0;
        } else {
            val = extractNumberFromText(el.innerText);
        }
        total += val;
    });
    
    document.getElementById('sumResultValue').innerText = new Intl.NumberFormat(window.appLang, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(total);
}

async function deleteCategory(id) {
    if (!confirm(tr('bud_prev_confirm_del_line'))) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_category');
    formData.append('id', id);
    formData.append('ajax', '1'); // 

    try {
        const result = await pachaFetch('modules/budget/includes/api/save-budget.php', {
            method: 'POST',
            body: formData
        });
        
        if (result.success) {
            window.location.reload(); 
        }
    } catch(e) { console.error(e); }
}

document.addEventListener('click', function(e) {
    if (!isSumModeActive) return;

    const targetElement = e.target.closest('input[type="number"], .sum-target');
    
    if (targetElement) {
        e.preventDefault(); 
        
        if (selectedElementsForSum.has(targetElement)) {
            selectedElementsForSum.delete(targetElement);
            targetElement.classList.remove('sum-selected');
        } else {
            selectedElementsForSum.add(targetElement);
            targetElement.classList.add('sum-selected');
        }
        
        updateSumResult();
    }
}, true);

document.addEventListener('DOMContentLoaded', recalcAllAllocations);

// --- INTERCEPTION ASYNCHRONE DES FORMULAIRES DE MODALES ---
document.querySelectorAll('#addCatModal form, #editCatModal form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerText = '⏳ ...';

            const formData = new FormData(form);
            formData.append('ajax', '1');

            const actionUrl = form.getAttribute('action'); 
            
            // On utilise pachaFetch au lieu de fetch
            const result = await pachaFetch(actionUrl, {
                method: 'POST',
                body: formData
            });

            if (result.success) {
                form.closest('.pf-modal').style.display = 'none';
                document.body.classList.remove('no-scroll');
                window.location.reload(); 
            } else {
                alert((window.I18N['bud_err_tech'] || 'Erreur') + " : " + (result.error || "Inconnue"));
            }
        } catch (error) {
            console.error("Erreur Fetch Modale:", error);
            alert("Une erreur technique est survenue.");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
});
</script>
```

---

### 📄 Fichier : `modules/budget/views/epargne.php`
```php
<?php
// modules/budget/views/epargne.php

$requestedOwner = $_GET['owner'] ?? 'Nens'; 
$ownersToDisplay = ($requestedOwner === 'Nens') ? ['Pol', 'Pep'] : [$requestedOwner];

$cycleConfigs = [];
$stmtNotes = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type = 'month_config'");
while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('-', $row['reference_id']);
    if (count($parts) == 2) {
        $mKey = $parts[1] . '-' . $parts[0] . '-01';
        $cycleConfigs[$mKey] = json_decode($row['content'], true);
    }
}

// Récupération sécurisée du nom des mois (utilise les clés globales existantes)
function getMonthName($dateString) {
    $m = date('m', strtotime($dateString));
    $y = date('Y', strtotime($dateString));
    return tr('month_' . $m) . ' ' . $y;
}
?>

<div class="budget-view">
    <div class="view-header">
        <div class="owner-tabs">
            <a href="?tab=epargne&owner=Alex" class="owner-tab <?= $requestedOwner === 'Alex' ? 'active' : '' ?>">Alex</a>
            <a href="?tab=epargne&owner=Laia" class="owner-tab <?= $requestedOwner === 'Laia' ? 'active' : '' ?>">Laia</a>
            <a href="?tab=epargne&owner=Nens" class="owner-tab <?= $requestedOwner === 'Nens' ? 'active' : '' ?>">Nens 👶</a>
        </div>
    </div>

    <?php foreach ($ownersToDisplay as $currentOwner): 
        $stmt = $pdo->prepare("SELECT month_date, category, amount FROM pf_savings WHERE owner = ? ORDER BY month_date DESC");
        $stmt->execute([$currentOwner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $months = [];
        $allCategories = [];

        foreach ($rows as $row) {
            $m = $row['month_date'];
            $cat = $row['category'];
            $val = $row['amount'];
            $data[$m][$cat] = $val;
            if (!in_array($m, $months)) $months[] = $m;
            if ($cat !== 'TOTAL_BANQUE' && !in_array($cat, $allCategories)) $allCategories[] = $cat;
        }
        $months = array_slice($months, 0, 7); 
        sort($allCategories);

        // Définition de la classe couleur selon le propriétaire
        $ownerTextClass = '';
        if ($currentOwner === 'Alex') $ownerTextClass = 'txt-alex';
        elseif ($currentOwner === 'Laia') $ownerTextClass = 'txt-laia';
        else $ownerTextClass = 'txt-global'; // Pour Pol et Pep
    ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: <?= ($requestedOwner === 'Nens' && $currentOwner !== 'Pol') ? '40px' : '0' ?>;">        
        <div style="flex-grow: 1;">
            <?php if ($requestedOwner === 'Nens'): 
                $themeClass = 'theme-' . strtolower($currentOwner);
            ?>
                <h3 class="nens-title <?= $themeClass ?>" style="margin:0; font-size:1.2rem;">
                    <?= $currentOwner ?>
                </h3>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (!empty($months)): ?>
                <button onclick="duplicateLastMonth('<?= $months[0] ?>', '<?= $currentOwner ?>')" class="pf-btn btn-secondary">
                    🔁 <?= tr('bud_sav_add_one_month') ?>
                </button>
            <?php endif; ?>
            <button onclick="openCustomSavingsModal('<?= $currentOwner ?>')" class="pf-btn">
                ＋ <?= tr('bud_sav_add_month') ?>
            </button>
        </div>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0;">
        <?php if (empty($months)): ?>
            <div style="padding: 30px; text-align: center; color: #64748b;">
                <p><?= sprintf(tr('bud_sav_no_data'), htmlspecialchars($currentOwner)) ?></p>
            </div>
        <?php else: ?>
            <table class="pf-table savings-table nens-table theme-<?= strtolower($currentOwner) ?>" style="margin-top:0; box-shadow:none; border-radius:16px;">            
                <thead>
                    <tr>
                        <th class="sticky-col" style="background:#f8fafc;"><?= tr('bud_sav_post_month') ?></th>
                        <?php foreach ($months as $month): ?>
                            <th>
                                <div class="month-header-container" style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                    <div style="display:flex; flex-direction:column; text-align:center;">
                                        <span class="month-name" style="text-transform:capitalize;"><?= getMonthName($month) ?></span>
                                        <?php 
                                        if (isset($cycleConfigs[$month]) && !empty($cycleConfigs[$month]['start_date'])) {
                                            $cStart = date('d/m', strtotime($cycleConfigs[$month]['start_date']));
                                            echo "<span style='font-size:0.75rem; font-weight:normal; color:#64748b;'>" . sprintf(tr('bud_sav_from_date'), $cStart) . "</span>";
                                        }
                                        ?>
                                    </div>
                                    <div class="month-actions" style="justify-content: center; width: 100%;">
                                        <button class="btn-icon-small btn-safe-click" title="<?= tr('bud_sav_edit_modal') ?>"
                                                data-json="<?= htmlspecialchars(json_encode($data[$month] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick='editCustomSavingsMonth("<?= $month ?>", "<?= $currentOwner ?>", JSON.parse(this.getAttribute("data-json")))'>
                                            ✏️
                                        </button>
                                        <button class="btn-icon-small btn-safe-click" title="<?= tr('bud_sav_delete_month') ?>"
                                                onclick="deleteEntireMonth('<?= $month ?>', '<?= $currentOwner ?>')"
                                                style="color: #ef4444; border-color: #fca5a5; background: #fef2f2;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-total">
                        <td class="sticky-col"><strong><?= tr('bud_sav_total_bank') ?></strong></td>
                        <?php foreach ($months as $month): 
                            $val = $data[$month]['TOTAL_BANQUE'] ?? 0;
                        ?>
                            <td class="text-center" style="padding:4px;">
                                <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                    <input type="number" step="0.01" 
                                           class="prev-input total-input-<?= $currentOwner ?>-<?= $month ?>" 
                                           style="width: 70px; font-weight:bold; color:#2563eb;"
                                           value="<?= $val != 0 ? round($val) : '' ?>" 
                                           placeholder="0"
                                           onchange="updateEpargneCell('<?= $month ?>', 'TOTAL_BANQUE', '<?= $currentOwner ?>', this)">
                                    <span style="color:#2563eb; font-weight:bold; font-size:0.9rem;">€</span>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($allCategories as $cat): ?>
                    <tr>
                        <td class="sticky-col"><?= htmlspecialchars($cat) ?></td>
                        <?php foreach ($months as $month): 
                            $amount = $data[$month][$cat] ?? 0; 
                        ?>
                            <td class="text-center" style="padding:4px;">
                                <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                    <input type="number" step="0.01" 
                                           class="prev-input <?= $ownerTextClass ?> cat-input-<?= $currentOwner ?>-<?= $month ?>" 
                                           style="width: 70px;"
                                           value="<?= $amount != 0 ? round($amount) : '' ?>" 
                                           placeholder="-"
                                           onchange="updateEpargneCell('<?= $month ?>', '<?= htmlspecialchars($cat, ENT_QUOTES) ?>', '<?= $currentOwner ?>', this)">
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="row-extres">
                        <td class="sticky-col"><strong><?= tr('bud_sav_extra') ?></strong></td>
                        <?php foreach ($months as $month): 
                            $total = $data[$month]['TOTAL_BANQUE'] ?? 0;
                            $sum = 0;
                            foreach ($allCategories as $cat) $sum += ($data[$month][$cat] ?? 0);
                            $extra = $total - $sum;
                        ?>
                            <td class="text-center font-bold sum-target" id="extra_<?= $currentOwner ?>_<?= $month ?>" style="color: <?= $extra >= 0 ? '#10b981' : '#ef4444' ?>; padding:12px;">
                                <?= number_format($extra, 0, ',', ' ') ?> €
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?> 
</div>

<div id="savingsModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px; width: 95%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="savingsModalTitle" class="pf-modal-title" style="margin:0;"><?= tr('bud_sav_modal_title_add') ?></h3>
            <button type="button" onclick="document.getElementById('savingsModal').style.display='none'; document.body.classList.remove('no-scroll');" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="modules/budget/includes/api/save-savings.php" method="POST" id="savingsForm">
            <input type="hidden" name="owner" id="sav_owner">
            <input type="hidden" name="redirect_tab" id="redirect_tab" value="<?= htmlspecialchars($requestedOwner) ?>"> 
            <input type="hidden" name="month_date" id="sav_date_hidden">

            <div style="display:flex; gap:15px; margin-bottom:20px;">
                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label"><?= tr('bud_sav_month_concerned') ?></label>
                    <input type="month" id="sav_month" required class="pf-input">
                </div>

                <div class="form-group" style="flex:1; margin:0;">
                    <label class="pf-label"><?= tr('bud_sav_total_bank_eur') ?></label>
                    <input type="number" step="0.01" name="values[TOTAL_BANQUE]" id="sav_total" required class="pf-input no-spinners" style="font-weight:bold; color:#2563eb;">
                </div>
            </div>

            <div class="separator" style="margin: 20px 0; border-bottom: 1px solid #e2e8f0;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h4 style="margin:0; font-size:1rem; color:#1e293b;"><?= tr('bud_sav_ventilation') ?></h4>
                    <span style="font-size:0.8rem; color:#64748b;"><?= tr('bud_sav_adj_help') ?></span>
                </div>
                <button type="button" class="pf-btn btn-secondary" onclick="addCustomEpargneLine()" style="padding:4px 10px; height:auto; width:auto; font-size:0.9rem;">＋ <?= tr('bud_sav_add_line') ?></button>
            </div>

            <div style="display:flex; gap:10px; padding:0 5px 5px 5px; font-size:0.8rem; color:#64748b; font-weight:600;">
                <div style="flex:2;"><?= tr('bud_category') ?></div>
                <div style="width:100px;"><?= tr('bud_sav_current') ?></div>
                <div style="width:90px;"><?= tr('bud_sav_adjust') ?></div>
                <div style="width:100px;"><?= tr('bud_sav_new') ?></div>
                <div style="width:28px;"></div>
            </div>

            <div id="linesContainer" style="max-height: 350px; overflow-y: auto; padding-right:5px; display:flex; flex-direction:column; gap:10px;">
                </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('savingsModal').style.display='none'; document.body.classList.remove('no-scroll');" class="pf-btn btn-secondary" style="width:auto; margin:0;"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0;"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<button id="fabSumMode" class="pf-fab-sum" onclick="toggleSumMode()" title="<?= tr('bud_sav_sum_mode_title') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="8" y1="12" x2="16" y2="12"></line><line x1="12" y1="8" x2="12" y2="16"></line></svg>
</button>

<div id="sumResultBar" class="pf-sum-bar">
    <span class="pf-sum-label"><?= tr('bud_sav_selection') ?></span>
    <span id="sumResultValue" class="pf-sum-value">0 €</span>
    <button onclick="toggleSumMode()" class="pf-sum-close" title="<?= tr('btn_close') ?>">&times;</button>
</div>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_sav_modal_title_add': <?= json_encode(tr('bud_sav_modal_title_add')) ?>,
    'bud_sav_modal_title_edit': <?= json_encode(tr('bud_sav_modal_title_edit')) ?>,
    'bud_sav_ph_name': <?= json_encode(tr('bud_sav_ph_name')) ?>,
    'bud_sav_confirm_delete_month': <?= json_encode(tr('bud_sav_confirm_delete_month')) ?>,
    'bud_sav_prompt_duplicate': <?= json_encode(tr('bud_sav_prompt_duplicate')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'bud_err_server': <?= json_encode(tr('bud_err_server')) ?>,
    'bud_err_network_dup': <?= json_encode(tr('bud_err_network_dup')) ?>,
    'bud_sav_saving': <?= json_encode(tr('bud_sav_saving')) ?>,
    'bud_err_delete': <?= json_encode(tr('bud_err_delete')) ?>
};

// --- 2. GESTION DE L'ÉDITION INVISIBLE EN DIRECT ---
const cycleConfigs = <?= json_encode($cycleConfigs ?? []) ?>;

function updateEpargneCell(month, category, owner, inputEl) {
    const val = parseFloat(inputEl.value) || 0;
    const formData = new FormData();
    formData.append('action', 'update_single_entry');
    formData.append('month_date', month);
    formData.append('category', category);
    formData.append('owner', owner);
    formData.append('amount', val);

    fetch('modules/budget/includes/api/save-savings.php', {
        method: 'POST',
        body: formData
    }).catch(err => alert(window.I18N['bud_err_tech'] || 'Erreur technique'));

    const totalInput = document.querySelector(`.total-input-${owner}-${month}`);
    const totalVal = parseFloat(totalInput ? totalInput.value : 0) || 0;

    let sumCats = 0;
    document.querySelectorAll(`.cat-input-${owner}-${month}`).forEach(inp => {
        sumCats += parseFloat(inp.value) || 0;
    });

    const extra = totalVal - sumCats;
    const extraCell = document.getElementById(`extra_${owner}_${month}`);

    if (extraCell) {
        extraCell.innerText = Math.round(extra).toLocaleString(window.appLang) + ' €';
        extraCell.style.color = extra >= 0 ? '#10b981' : '#ef4444';
    }
    
    if(isSumModeActive) updateSumResult();
}

function addCustomEpargneLine(catName = '', amount = '') {
    const container = document.getElementById('linesContainer');
    const baseAmount = (amount !== '' && amount !== null) ? parseFloat(amount).toFixed(2) : '0.00';
    const inputName = catName ? `values[${catName}]` : '';

    const html = `
        <div class="ventilation-line" style="display:flex; gap:10px; align-items:center; background:#f8fafc; padding:8px; border-radius:8px; border:1px solid #e2e8f0;">
            <div style="flex:2;">
                <input type="text" class="pf-input cat-name-input" value="${catName}" placeholder="${window.I18N['bud_sav_ph_name'] || 'Catégorie'}" oninput="updateCustomFieldName(this)" style="padding:6px; font-size:0.9rem;" required>
            </div>
            <div style="width:100px;">
                <input type="number" step="0.01" class="pf-input base-amount no-spinners" value="${baseAmount}" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; background:#fff;">
            </div>
            <div style="width:90px;">
                <input type="number" step="0.01" class="pf-input adjustment-amount no-spinners" placeholder="+ / -" oninput="recalculateCustomLine(this)" style="padding:6px; font-size:0.9rem; color:#f59e0b; font-weight:bold;">
            </div>
            <div style="width:100px;">
                <input type="number" step="0.01" name="${inputName}" class="pf-input final-amount no-spinners" value="${baseAmount}" style="padding:6px; font-size:0.9rem; font-weight:bold; background:#e0f2fe; border-color:#bae6fd; color:#0369a1;" readonly>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="width:28px; height:28px; border:none; background:#fee2e2; color:#ef4444; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-weight:bold;">&times;</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function updateCustomFieldName(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const finalInput = line.querySelector('.final-amount');
    const newName = inputElement.value.trim();
    if (newName) finalInput.name = `values[${newName}]`;
    else finalInput.name = ''; 
}

function recalculateCustomLine(inputElement) {
    const line = inputElement.closest('.ventilation-line');
    const baseInput = line.querySelector('.base-amount');
    const adjInput = line.querySelector('.adjustment-amount');
    const finalInput = line.querySelector('.final-amount');
    
    const base = parseFloat(baseInput.value) || 0;
    const adj = parseFloat(adjInput.value) || 0;
    
    finalInput.value = (base + adj).toFixed(2);
}

function editCustomSavingsMonth(monthDate, owner, rowData) {
    document.getElementById('sav_owner').value = owner;
    const ym = monthDate.substring(0, 7);
    document.getElementById('sav_month').value = ym;
    
    const dateObj = new Date(monthDate);
    const monthName = dateObj.toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
    document.getElementById('savingsModalTitle').innerText = (window.I18N['bud_sav_modal_title_edit'] || 'Editer') + " " + monthName + " (" + owner + ")";
    
    document.getElementById('sav_total').value = rowData['TOTAL_BANQUE'] || '';

    const container = document.getElementById('linesContainer');
    container.innerHTML = '';

    for (const [cat, val] of Object.entries(rowData)) {
        if (cat !== 'TOTAL_BANQUE') addCustomEpargneLine(cat, val);
    }
    
    if (container.children.length === 0) addCustomEpargneLine();

    document.getElementById('savingsModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

function openCustomSavingsModal(owner) {
    document.getElementById('sav_owner').value = owner;
    document.getElementById('sav_month').value = '';
    document.getElementById('sav_total').value = '';
    
    document.getElementById('savingsModalTitle').innerText = (window.I18N['bud_sav_modal_title_add'] || 'Ajouter') + " (" + owner + ")";
    
    const container = document.getElementById('linesContainer');
    container.innerHTML = '';
    addCustomEpargneLine(); 
    
    document.getElementById('savingsModal').style.display = 'flex';
    document.body.classList.add('no-scroll');
}

window.onclick = function(event) {
    const modal = document.getElementById('savingsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
        document.body.classList.remove('no-scroll');
    }
}

const savingsForm = document.getElementById('savingsForm');
if (savingsForm) {
    savingsForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        const ym = document.getElementById('sav_month').value;
        if(ym) {
            document.getElementById('sav_date_hidden').value = ym + '-01';
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = window.I18N['bud_sav_saving'] || 'Sauvegarde...';
        submitBtn.disabled = true;

        const formData = new FormData(this);

        const actionUrl = this.getAttribute('action'); 
        const finalUrl = actionUrl.startsWith('/') ? actionUrl.substring(1) : actionUrl;

        fetch(finalUrl, { method: 'POST', body: formData })
        .then(response => response.text()) 
        .then(text => { window.location.reload(); })
        .catch(error => {
            console.error("Erreur:", error);
            alert(window.I18N['bud_err_tech'] || 'Erreur Technique');
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        });
    });
}

function deleteEntireMonth(monthDate, owner) {
    const rawMsg = window.I18N['bud_sav_confirm_delete_month'] || "Supprimer %m pour %o ?";
    const msg = rawMsg.replace('%m', monthDate).replace('%o', owner);
    if (!confirm(msg)) return;
    
    const formData = new FormData();
    formData.append("action", "delete_month_global"); 
    formData.append("month_date", monthDate);
    formData.append("owner", owner);
    
    fetch("modules/budget/includes/api/save-savings.php", { method: "POST", body: formData })
    .then(() => window.location.reload())
    .catch(err => alert(window.I18N['bud_err_delete'] || 'Erreur lors de la suppression'));
}

function duplicateLastMonth(lastMonthDate, owner) {
    let dateObj = new Date(lastMonthDate);
    dateObj.setMonth(dateObj.getMonth() + 1);
    let year = dateObj.getFullYear();
    let month = String(dateObj.getMonth() + 1).padStart(2, '0');
    let nextMonthStr = `${year}-${month}-01`;

    const formatMonth = (d) => {
        let str = new Date(d).toLocaleDateString(window.appLang, { month: 'long', year: 'numeric' });
        return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const sourceName = formatMonth(lastMonthDate); 
    const targetName = formatMonth(nextMonthStr); 

    let defaultTotal = "";
    if (cycleConfigs[nextMonthStr] && cycleConfigs[nextMonthStr].start_balance !== undefined) {
        defaultTotal = cycleConfigs[nextMonthStr].start_balance;
    }

    const rawMsg = window.I18N['bud_sav_prompt_duplicate'] || "Dupliquer %s vers %t1 ?";
    const message = rawMsg.replace('%s', sourceName).replace('%t1', targetName).replace('%t2', targetName);

    let newTotal = prompt(message, defaultTotal);

    if (newTotal !== null && newTotal.trim() !== "") {
        const formData = new FormData();
        formData.append("action", "duplicate_month");
        formData.append("source_date", lastMonthDate);
        formData.append("target_date", nextMonthStr);
        formData.append("new_total", newTotal);
        formData.append("owner", owner);

        fetch("modules/budget/includes/api/save-savings.php", { method: "POST", body: formData })
        .then(async r => {
            const text = await r.text(); 
            try {
                const d = JSON.parse(text); 
                if (d.success) window.location.reload();
                else alert((window.I18N['bud_err_server'] || 'Erreur serveur : ') + (d.error || "Inconnue"));
            } catch(e) {
                window.location.reload();
            }
        })
        .catch(err => {
            console.error(err);
            alert(window.I18N['bud_err_network_dup'] || 'Erreur réseau.');
        });
    }
}

// --- CALCULATRICE ---
let isSumModeActive = false;
let selectedElementsForSum = new Set();

function toggleSumMode() {
    isSumModeActive = !isSumModeActive;
    const fab = document.getElementById('fabSumMode');
    const resultBar = document.getElementById('sumResultBar');
    
    if (isSumModeActive) {
        fab.classList.add('active');
        document.body.classList.add('sum-mode-active');
        resultBar.classList.add('visible');
        updateSumResult();
    } else {
        fab.classList.remove('active');
        document.body.classList.remove('sum-mode-active');
        resultBar.classList.remove('visible');
        
        selectedElementsForSum.forEach(el => el.classList.remove('sum-selected'));
        selectedElementsForSum.clear();
    }
}

function extractNumberFromText(text) {
    if (!text) return 0;
    const cleanText = text.replace(',', '.').replace(/[^\d.-]/g, '');
    return parseFloat(cleanText) || 0;
}

function updateSumResult() {
    let total = 0;
    selectedElementsForSum.forEach(el => {
        let val = 0;
        if (el.tagName === 'INPUT') {
            val = parseFloat(el.value) || 0;
        } else {
            val = extractNumberFromText(el.innerText);
        }
        total += val;
    });
    
    document.getElementById('sumResultValue').innerText = new Intl.NumberFormat(window.appLang, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(total);
}

document.addEventListener('click', function(e) {
    if (!isSumModeActive) return;

    const targetElement = e.target.closest('input[type="number"], .sum-target');
    
    if (targetElement) {
        e.preventDefault(); 
        
        if (selectedElementsForSum.has(targetElement)) {
            selectedElementsForSum.delete(targetElement);
            targetElement.classList.remove('sum-selected');
        } else {
            selectedElementsForSum.add(targetElement);
            targetElement.classList.add('sum-selected');
        }
        
        updateSumResult();
    }
}, true); 
</script>
```

---

### 📄 Fichier : `modules/budget/views/recap.php`
```php
<?php
// modules/budget/views/recap.php

// 1. Récupération des Items du Budget
$stmt = $pdo->query("SELECT * FROM pf_budget_items ORDER BY category DESC, sort_order ASC, name ASC");
$items = $stmt->fetchAll();

// 2. Déterminer quel est le mois de gestion "ouvert" par défaut
$stmtActive = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'active_gestion_month' LIMIT 1");
$defaultActiveMonth = $stmtActive->fetchColumn();
if (!$defaultActiveMonth) {
    $defaultActiveMonth = date('Y-m-01');
}

// 3. Assigner le mois et l'année en fonction du mois ouvert
$currentMonth = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($defaultActiveMonth));
$currentYear = isset($_GET['y']) ? (int)$_GET['y'] : date('Y', strtotime($defaultActiveMonth));
$viewMonthDate = "$currentYear-$currentMonth-01";

$sqlReal = "SELECT budget_item_id, SUM(amount) as total_real 
            FROM pf_expenses 
            WHERE gestion_month = ? AND budget_item_id IS NOT NULL 
            GROUP BY budget_item_id";
$stmtReal = $pdo->prepare($sqlReal);
$stmtReal->execute([$viewMonthDate]);
$realTotals = $stmtReal->fetchAll(PDO::FETCH_KEY_PAIR); // Retourne un tableau [id => total]

$sqlCatReal = "SELECT category, SUM(amount) as total_real 
               FROM pf_expenses 
               WHERE gestion_month = ? AND budget_item_id IS NULL 
               GROUP BY category";
$stmtCatReal = $pdo->prepare($sqlCatReal);
$stmtCatReal->execute([$viewMonthDate]);
$catTotals = $stmtCatReal->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtLabels = $pdo->prepare("SELECT label, amount FROM pf_expenses WHERE gestion_month = ? AND budget_item_id IS NULL");
$stmtLabels->execute([$viewMonthDate]);
$unlinkedExpenses = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$monthTranslationKey = 'month_' . str_pad((int)$currentMonth, 2, '0', STR_PAD_LEFT);
$currentMonthName = tr($monthTranslationKey) . ' ' . $currentYear;

$totalDepenses = 0;
$totalRevenus = 0;
?>

<div class="budget-view">
    <div class="view-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;"><?= tr('bud_recap_monthly_title') ?></h2>
        <button onclick="openRecapModal('add')" class="pf-btn">＋ <?= tr('bud_recap_btn_add') ?></button>
    </div>

    <div class="table-responsive" style="background:white; border-radius:16px; box-shadow:var(--shadow-sm); border:1px solid #e2e8f0; overflow:hidden;">
        <table class="pf-table" style="margin:0; box-shadow:none;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th><?= tr('bud_label_name') ?></th>
                    <th><?= tr('bud_planned_amount') ?></th>
                    <th><?= tr('bud_type') ?></th>
                    <th><?= tr('bud_day') ?></th>
                    <th><?= tr('bud_month_state') ?> (<?= $currentMonthName ?>)</th>
                    <th style="text-align:right;"><?= tr('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    // --- 1. CALCUL DES TOTAUX PRÉVUS ---
                    $targetAbs = abs((float)$item['amount']); 
                    $amountToAdd = ($item['type'] === 'Annuel') ? $targetAbs / 12 : $targetAbs;
                    
                    if ($item['category'] === 'income') $totalRevenus += $amountToAdd;
                    else $totalDepenses += $amountToAdd;
                    
                    // --- 2. CALCUL DU RÉEL (Logique optimisée) ---
                    $realSum = 0;
                    $hasMatchingExpense = false;

                    // A. Correspondance directe par ID
                    if (isset($realTotals[$item['id']])) {
                        $realSum = $realTotals[$item['id']];
                        $hasMatchingExpense = true;
                    } 
                    // B. Correspondance par catégorie système (École, Essence, FMCG)
                    else {
                        $catKey = null;
                        if (trim($item['name']) === 'Estimacio escola') $catKey = 'School';
                        elseif (trim($item['name']) === 'Estimation gasolina') $catKey = 'Essence';
                        elseif (trim($item['name']) === 'Estimacio F&B & beauty') $catKey = 'FMCG';

                        if ($catKey && isset($catTotals[$catKey])) {
                            $realSum = $catTotals[$catKey];
                            $hasMatchingExpense = true;
                        }
                        // C. Correspondance par mots-clés (seulement sur les dépenses non liées)
                        elseif (!empty($item['mapping_keywords'])) {
                            $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                            foreach ($unlinkedExpenses as $uexp) {
                                foreach ($keywords as $kw) {
                                    if (!empty($kw) && stripos($uexp['label'], $kw) !== false) {
                                        $realSum += (float)$uexp['amount'];
                                        $hasMatchingExpense = true;
                                        break; 
                                    }
                                }
                            }
                        }
                    }

                    $realAbs = abs($realSum);
                    $isAutoChecked = ($hasMatchingExpense && ($realAbs >= ($targetAbs - 0.10)));

                    $rowClass = ($item['category'] === 'income') ? 'row-income' : 'row-expense';
                    if ($item['is_estimate']) $rowClass .= ' row-estimate';
                ?>
                <tr class="<?= $rowClass ?>" style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:15px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?= $item['is_estimate'] ? ' <small style="color:#64748b;">('.tr('bud_est_short').')</small>' : '' ?>
                        
                        <?php if(!empty($item['mapping_keywords'])): ?>
                            <span title="<?= htmlspecialchars($item['mapping_keywords']) ?>" style="font-size:0.7rem; cursor:help;">🔗</span>
                        <?php endif; ?>
                        
                        <?php if(!empty($item['reg_month'])): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-style:italic; margin-top:2px;">
                                📅 <?= sprintf(tr('bud_reg_planned_in'), tr('month_'.str_pad(array_search($item['reg_month'], $moisFr), 2, '0', STR_PAD_LEFT))) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="cell-amount" style="font-weight:600; padding:15px; color:<?= $item['category']==='income'?'#10b981':'#1e293b' ?>;">
                        <?= number_format($targetAbs, 2, ',', ' ') ?> €
                        
                        <?php if ($hasMatchingExpense): ?>
                            <?php 
                            // Le "Gap" est la différence visuelle. 
                            $gap = $realAbs - $targetAbs; 
                            
                            if ($gap > 0.05): ?>
                                <div style="font-size:0.75rem; color:#ef4444; font-weight:bold;">
                                    <?= $item['category'] === 'income' ? tr('bud_bonus') : tr('bud_overrun') ?> : +<?= number_format($gap, 2, ',', ' ') ?> €
                                </div>
                            <?php elseif ($gap < -0.05): ?>
                                <div style="font-size:0.75rem; color:#f59e0b; font-weight:normal;">
                                    <?= tr('bud_remaining') ?> : <?= number_format(abs($gap), 2, ',', ' ') ?> €
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.75rem; color:#10b981; font-weight:normal;">
                                    <?= tr('bud_exact_amount') ?> ✓
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['type'] === 'Annuel'): ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-weight:normal;"><?= tr('bud_per_month_short') ?> <?= number_format($amountToAdd, 2, ',', ' ') ?>/<?= tr('bud_month_short') ?></div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px;">
                        <span class="badge-type <?= strtolower($item['type']) ?>" style="background:#e2e8f0; padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:600; color:#475569;">
                            <?= tr('bud_freq_'.strtolower($item['type'])) ?>
                        </span>
                    </td>
                    <td style="padding:15px; color:#64748b; font-weight:bold;"><?= $item['payment_day'] ? $item['payment_day'] : '-' ?></td>
                    
                    <td style="padding:15px;">
                        <?php if ($isAutoChecked): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #bbf7d0;">
                                <span>✓</span> <?= tr('bud_state_validated') ?>
                            </div>
                        <?php elseif($hasMatchingExpense): ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #fde68a;">
                                <span>⏳</span> <?= tr('bud_state_partial') ?>
                            </div>
                        <?php else: ?>
                            <div style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; padding:4px 10px; border-radius:20px; font-size:0.85rem; border:1px solid #e2e8f0;">
                                <span>○</span> <?= tr('bud_state_waiting') ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:15px; text-align:right;">
                        <div class="action-buttons" style="display:flex; gap:5px; justify-content:flex-end;">
                            <button class="btn-icon-action edit" onclick='editRecapItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)' title="<?= tr('edit') ?>">✏️</button>
                            <button class="btn-icon-action delete" onclick="deleteRecapItem(<?= $item['id'] ?>)" title="<?= tr('delete') ?>">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8fafc;">
                <tr>
                    <td colspan="1" style="padding:15px;"><strong><?= tr('bud_total_income_smoothed') ?></strong></td>
                    <td colspan="5" style="padding:15px; color:#10b981;"><strong>+ <?= number_format($totalRevenus, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td colspan="1" style="padding:15px;"><strong><?= tr('bud_total_expenses_smoothed') ?></strong></td>
                    <td colspan="5" style="padding:15px; color:#ef4444;"><strong>- <?= number_format($totalDepenses, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e2e8f0; background: white;">
                    <td colspan="1" style="padding:15px; font-size:1.1rem;"><strong><?= tr('bud_theoretical_balance_recap') ?></strong></td>
                    <?php $balance = $totalRevenus - $totalDepenses; ?>
                    <td colspan="5" style="padding:15px; font-size: 1.3em;" class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong style="color:<?= $balance >= 0 ? '#10b981' : '#ef4444' ?>;"><?= number_format($balance, 2, ',', ' ') ?> € / <?= tr('bud_month_short') ?></strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="budget-note" style="margin-top:15px; font-size:0.85rem; color:#64748b;">
        <p>* <?= tr('bud_recap_footer_note') ?></p>
    </div>
</div>

<div id="budgetRecapModal" class="pf-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="pf-modal-content" style="background:white; width:95%; max-width:500px; border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); padding:30px; position:relative;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="recapModalTitle" style="margin:0; font-size:1.2rem; color:#1e293b;"><?= tr('bud_recap_modal_add') ?></h3>
            <button type="button" onclick="closeRecapModal()" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        
        <form action="modules/budget/includes/api/manage-item.php" method="POST" id="recapForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="item_id">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_name') ?></label>
                <input type="text" name="name" id="item_name" required class="pf-input" placeholder="<?= tr('bud_ph_item_name') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_keywords') ?></label>
                <input type="text" name="mapping_keywords" id="item_keywords" class="pf-input" placeholder="<?= tr('bud_ph_keywords') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f0f9ff; border-color:#bae6fd;">
                <small style="color:#64748b; font-size:0.75rem;"><?= tr('bud_help_keywords') ?></small>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_amount_eur') ?></label>
                    <input type="number" step="0.01" name="amount" id="item_amount" required class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_day') ?></label>
                    <input type="number" min="1" max="31" name="payment_day" id="item_day" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_category') ?></label>
                    <select name="category" id="item_category" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="expense"><?= tr('bud_cat_expense') ?></option>
                        <option value="income"><?= tr('bud_cat_income') ?></option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_frequency') ?></label>
                    <select name="type" id="item_type" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="Mensuel"><?= tr('bud_freq_mensuel') ?></option>
                        <option value="Annuel"><?= tr('bud_freq_annuel') ?></option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:25px;">
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_amount_type') ?></label>
                    <select name="is_estimate" id="item_is_estimate" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value="0"><?= tr('bud_type_fixed') ?></option>
                        <option value="1"><?= tr('bud_type_variable') ?></option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="pf-label" style="display:block; margin-bottom:5px; font-weight:600; color:#475569; font-size:0.9rem;"><?= tr('bud_label_regularization') ?></label>
                    <select name="reg_month" id="item_reg_month" class="pf-input" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:white;">
                        <option value=""><?= tr('bud_reg_none') ?></option>
                        <?php 
                        foreach($moisFr as $index => $m) {
                            if($index == 0) continue;
                            echo "<option value='$m'>" . tr('month_'.str_pad($index, 2, '0', STR_PAD_LEFT)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRecapModal()" class="pf-btn btn-secondary" style="width:auto; margin:0; background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" style="width:auto; margin:0; background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_recap_modal_add': <?= json_encode(tr('bud_recap_modal_add')) ?>,
    'bud_recap_modal_edit': <?= json_encode(tr('bud_recap_modal_edit')) ?>,
    'bud_recap_confirm_delete': <?= json_encode(tr('bud_recap_confirm_delete')) ?>,
    'bud_err_delete': <?= json_encode(tr('bud_err_delete')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>,
    'bud_saving': <?= json_encode(tr('bud_sav_saving') ?? 'Sauvegarde...') ?>
};

function openRecapModal(mode) {
    if (mode === "add") {
        document.getElementById("recapModalTitle").innerText = window.I18N['bud_recap_modal_add'] || 'Ajouter';
        document.getElementById("item_id").value = "";
        document.getElementById("recapForm").reset();
    }
    document.getElementById("budgetRecapModal").style.display = "flex";
    document.body.classList.add('no-scroll');
}

function closeRecapModal() {
    document.getElementById("budgetRecapModal").style.display = "none";
    document.body.classList.remove('no-scroll');
}

window.onclick = function(event) {
    const modal = document.getElementById('budgetRecapModal');
    if (event.target == modal) {
        closeRecapModal();
    }
}

function editRecapItem(item) {
    const data = typeof item === "string" ? JSON.parse(item) : item;

    document.getElementById("recapModalTitle").innerText = (window.I18N['bud_recap_modal_edit'] || 'Editer') + " : " + data.name;
    document.getElementById("item_id").value = data.id;
    document.getElementById("item_name").value = data.name;
    document.getElementById("item_keywords").value = data.mapping_keywords || ''; 
    document.getElementById("item_amount").value = Math.abs(data.amount);
    document.getElementById("item_category").value = data.category;
    document.getElementById("item_type").value = data.type;
    document.getElementById("item_day").value = data.payment_day;
    document.getElementById("item_reg_month").value = data.reg_month || "";
    document.getElementById("item_is_estimate").value = data.is_estimate;

    document.getElementById("budgetRecapModal").style.display = "flex";
    document.body.classList.add('no-scroll');
}

// --- 2. INTERCEPTION ASYNCHRONE DU FORMULAIRE ---
const recapForm = document.getElementById('recapForm');
if (recapForm) {
    recapForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = window.I18N['bud_saving'] || '⏳ ...';
        submitBtn.disabled = true;

        const formData = new FormData(this);
        formData.append('ajax', '1');

        // 💡 Utilisation sécurisée de getAttribute
        const actionUrl = this.getAttribute('action'); 
        const finalUrl = actionUrl.startsWith('/') ? actionUrl.substring(1) : actionUrl;

        try {
            const response = await fetch(finalUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            // 💡 Lecture robuste (anti-Warnings PHP)
            const textResult = await response.text();
            try {
                const result = JSON.parse(textResult);
                if (result.success) {
                    closeRecapModal();
                    window.location.reload();
                } else {
                    alert((window.I18N['bud_err_tech'] || 'Erreur') + " : " + (result.error || "Inconnue"));
                }
            } catch (jsonError) {
                console.error("Réponse non-JSON :", textResult);
                alert("Le serveur a renvoyé une erreur PHP. Regarde la console (F12).");
            }
        } catch (error) {
            console.error("Erreur Fetch:", error);
            alert(window.I18N['bud_err_tech'] || 'Erreur technique');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
}

// --- 3. SUPPRESSION ASYNCHRONE ---
async function deleteRecapItem(id) {
    if (!confirm(window.I18N['bud_recap_confirm_delete'] || "Confirmer la suppression ?")) return;

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);
    formData.append("ajax", "1"); // Signale à l'API qu'on attend du JSON

    try {
        const response = await fetch("modules/budget/includes/api/manage-item.php", {
            method: "POST",
            body: formData,
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const textResult = await response.text();
        try {
            const result = JSON.parse(textResult);
            // On s'assure que si l'API ne renvoie pas success, on le signale
            if (result.success !== false) {
                window.location.reload(); 
            } else {
                alert((window.I18N['bud_err_delete'] || 'Erreur de suppression') + " : " + (result.error || ""));
            }
        } catch (jsonErr) {
            console.error("Réponse non-JSON lors de la suppression :", textResult);
            window.location.reload(); // Fallback si l'API redirige au lieu de répondre en JSON
        }
    } catch (err) {
        console.error("Erreur réseau Suppression:", err);
        alert(window.I18N['bud_err_delete'] || 'Erreur réseau');
    }
}
</script>
```

---

### 📄 Fichier : `modules/budget/views/suivi.php`
```php
<?php
// modules/budget/views/suivi.php

// ============================================================================
// 1. GESTION DU MOIS ACTIF ET DE LA NAVIGATION
// ============================================================================

$stmtActive = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'active_gestion_month' LIMIT 1");
$defaultActiveMonth = $stmtActive->fetchColumn();
if (!$defaultActiveMonth) {
    $defaultActiveMonth = date('Y-m-01');
}

$viewM = isset($_GET['m']) ? str_pad((int)$_GET['m'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($defaultActiveMonth));
$viewY = isset($_GET['y']) ? (int)$_GET['y'] : date('Y', strtotime($defaultActiveMonth));
$viewMonthDate = "$viewY-$viewM-01";

$prevDate = date('Y-m-01', strtotime('-1 month', strtotime($viewMonthDate)));
$nextDate = date('Y-m-01', strtotime('+1 month', strtotime($viewMonthDate)));

$prevLink = "?tab=suivi&m=" . date('m', strtotime($prevDate)) . "&y=" . date('Y', strtotime($prevDate));
$nextLink = "?tab=suivi&m=" . date('m', strtotime($nextDate)) . "&y=" . date('Y', strtotime($nextDate));
$defaultLink = "?tab=suivi";

// ============================================================================
// 2. GESTION DES ACTIONS POST (Clôture, Ajouts, CSV)
// ============================================================================

if (isset($_POST['action']) && $_POST['action'] === 'close_month') {
    $monthToClose = $_POST['close_month_date'];
    $nextMonthToOpen = date('Y-m-01', strtotime('+1 month', strtotime($monthToClose)));
    
    $frozenData = [
        'is_closed' => true,
        'solde_actuel' => (float)$_POST['freeze_solde_actuel'],
        'capacite_max' => (float)$_POST['freeze_capacite_max'],
        'solde_theorique' => (float)$_POST['freeze_solde_theorique'],
        'reste_a_venir' => (float)$_POST['freeze_reste_a_venir'],
        'closed_at' => date('Y-m-d H:i:s')
    ];
    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('month_closure', ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$monthToClose, json_encode($frozenData)]);

    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('active_gestion_month', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$nextMonthToOpen]);

    echo "<script>window.location.href='?tab=suivi&m=" . date('m', strtotime($nextMonthToOpen)) . "&y=" . date('Y', strtotime($nextMonthToOpen)) . "';</script>";
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'reopen_month') {
    $pdo->prepare("DELETE FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?")->execute([$viewMonthDate]);
    $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('active_gestion_month', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
        ->execute([$viewMonthDate]);
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>";
    exit;
}



if (isset($_POST['action']) && $_POST['action'] === 'save_import') {
    $count = 0;
    $stmtExp = $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtRule = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = VALUES(category)");

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (isset($line['import_check'])) {
                $cat = $line['cat'];
                $is_credit = isset($line['is_credit']) ? (int)$line['is_credit'] : 0;
                $budgetItemId = !empty($line['budget_item_id']) ? (int)$line['budget_item_id'] : null;
                $holidayId = !empty($line['holiday_id']) ? (int)$line['holiday_id'] : null;
                $gestionMonthLine = !empty($line['gestion_month']) ? $line['gestion_month'] . '-01' : $viewMonthDate; 
                
                if ($is_credit && empty($cat)) continue;
                if (!$is_credit && empty($cat)) continue;

                $finalAmount = $is_credit ? abs($line['amount']) : -abs($line['amount']);
                $dateToSave = $line['date'];

                try {
                    $stmtExp->execute([$dateToSave, $gestionMonthLine, $cat, $line['label'], $finalAmount, $line['ref'], $budgetItemId, $holidayId]);
                    $stmtRule->execute([$line['label'], $cat]);
                    $count++;
                } catch (Exception $e) { continue; }
            }
        }
    }
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY&msg=imported_$count';</script>"; 
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_snapshot') {
    $pdo->query("DELETE FROM pf_bank_snapshots"); 
    $pdo->prepare("INSERT INTO pf_bank_snapshots (snapshot_date, amount) VALUES (?, ?)")->execute([$_POST['snapshot_date'], floatval($_POST['snapshot_amount'])]);
    
    echo "<script>window.location.href='?tab=suivi&m=$viewM&y=$viewY';</script>"; 
    exit;
}


// ============================================================================
// E-Bis. LECTURE ET PRÉVISUALISATION DU FICHIER CSV
// ============================================================================
$csvData = [];
$showPreview = false;

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $rules = []; try { $rules = $pdo->query("SELECT keyword, category FROM pf_import_rules")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
    $existingRefs = []; try { $existingRefs = $pdo->query("SELECT import_ref FROM pf_expenses WHERE import_ref IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e){}
    fgetcsv($handle, 1000, ";", "\"", "\\"); 
    
    while (($data = fgetcsv($handle, 1000, ";", "\"", "\\")) !== FALSE) {
        $rawDebit = $data[8] ?? ''; $rawCredit = $data[9] ?? ''; 
        $amount = 0; $isCredit = 0;
        if (!empty(trim($rawCredit))) { $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawCredit))); $isCredit = 1; }
        elseif (!empty(trim($rawDebit))) { $amount = abs((float)str_replace(',', '.', str_replace(' ', '', $rawDebit))); }
        else continue; 

        $dateParts = explode('/', $data[0]); 
        $dateSql = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : date('Y-m-d');
        $label = trim($data[1]) ?: trim($data[2]);
        $refCSV = trim($data[3]);
        $uniqueKey = !empty($refCSV) ? "REF_".$refCSV : "HASH_".md5($dateSql.$label.number_format($amount, 2).$isCredit);
        $isDuplicate = in_array($uniqueKey, $existingRefs);
        
        $suggestedCat = '';
        foreach ($rules as $kw => $c) { if (stripos($label, $kw) !== false) { $suggestedCat = $c; break; } }
        
        $csvData[] = ['date'=>$dateSql, 'label'=>$label, 'amount'=>$amount, 'cat'=>$suggestedCat, 'ref'=>$uniqueKey, 'is_duplicate'=>$isDuplicate, 'is_credit'=>$isCredit];
    }
    fclose($handle);
    $showPreview = true;
}

// ============================================================================
// 3. RECUPERATION DES DONNEES ET CALCULS
// ============================================================================

$stmtCheckClose = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?");
$stmtCheckClose->execute([$viewMonthDate]);
$closureJson = $stmtCheckClose->fetchColumn();
$monthState = $closureJson ? json_decode($closureJson, true) : null;
$isClosed = ($monthState && isset($monthState['is_closed']) && $monthState['is_closed'] === true);

$snapshot = ['date' => date('Y-m-d'), 'amount' => 0];
$snapStmt = $pdo->query("SELECT * FROM pf_bank_snapshots ORDER BY id DESC LIMIT 1");
if ($s = $snapStmt->fetch(PDO::FETCH_ASSOC)) {
    $snapshot = ['date' => $s['snapshot_date'], 'amount' => (float)$s['amount']];
}

$stmtPrevClose = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'month_closure' AND reference_id = ?");
$stmtPrevClose->execute([$prevDate]);
$prevClosureJson = $stmtPrevClose->fetchColumn();
$prevMonthState = $prevClosureJson ? json_decode($prevClosureJson, true) : null;

$solde_initial = ($prevMonthState && isset($prevMonthState['solde_actuel'])) ? (float)$prevMonthState['solde_actuel'] : 0; 
if ($solde_initial === 0 && !$prevMonthState) {
    $solde_initial = $snapshot['amount'];
}

$stmt = $pdo->prepare("SELECT * FROM pf_expenses WHERE gestion_month = ? ORDER BY date_exp DESC");
$stmt->execute([$viewMonthDate]);
$allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paidItemIds = array_column(array_filter($allExpenses, fn($e) => !empty($e['budget_item_id'])), 'budget_item_id');
$realExpensesLabels = array_column(array_filter($allExpenses, fn($e) => $e['amount'] < 0), 'label');

$budget_fmcg = 0; $budget_school = 0; $budget_essence = 0; $budget_frais = 0; $budget_income_prevu = 0;
$total_income = 0; $total_expenses_prevues = 0;
$reste_a_venir_calc = 0; 
$fixedChargesList = []; $incomeList = []; $pending_charges = [];

$stmt = $pdo->query("SELECT id, name, amount, type, category, is_estimate, payment_day, mapping_keywords FROM pf_budget_items ORDER BY name ASC");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $absAmount = abs((float)$item['amount']); 
    $amt = ($item['type'] === 'Annuel') ? $absAmount / 12 : $absAmount;
    $name = trim($item['name']);
    
    if ($item['category'] === 'expense' && $item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
        $fixedChargesList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount]; 
    }
    if ($item['category'] === 'income') {
        $incomeList[] = ['id' => $item['id'], 'name' => $name, 'amount' => $absAmount];
        $total_income += $amt;
        $budget_income_prevu += $amt; 
    } else {
        $total_expenses_prevues += $amt;
        if ($item['category'] === 'expense' && $item['type'] === 'Mensuel' && (int)$item['is_estimate'] === 0) {
            $isPaid = false;
            if (in_array($item['id'], $paidItemIds)) $isPaid = true;
            elseif (!empty($item['mapping_keywords'])) {
                $keywords = array_map('trim', explode(',', $item['mapping_keywords']));
                foreach ($keywords as $kw) {
                    if (empty($kw)) continue;
                    foreach ($realExpensesLabels as $realLabel) {
                        if (stripos($realLabel, $kw) !== false) { $isPaid = true; break 2; }
                    }
                }
            }
            if (!$isPaid) {
                $reste_a_venir_calc += $absAmount;
                $pending_charges[] = ['name' => $name, 'amount' => $absAmount];
            }
        }
        if ($name === 'Estimacio F&B & beauty') $budget_fmcg = $amt;
        elseif ($name === 'Estimacio escola') $budget_school = $amt;
        elseif ($name === 'Estimation gasolina') $budget_essence = $amt;
        elseif ((int)$item['is_estimate'] === 0 && $item['type'] === 'Mensuel' && $item['category'] === 'expense') { $budget_frais += $absAmount; }
    }
}

$budget_autres = max(0, $total_income - $total_expenses_prevues);

// TRADUCTION DES CATÉGORIES
$categoriesConfig = [
    'Income'  => ['type'=>'credit', 'label'=>tr('cat_income'), 'budget'=>$budget_income_prevu, 'color'=>'#10b981', 'suggestions'=>[]],
    'FMCG'    => ['type'=>'debit',  'label'=>tr('cat_fmcg'),   'budget'=>$budget_fmcg, 'color'=>'#3b82f6', 'suggestions'=>['Action', 'Carrefour', 'Lidl']],
    'Essence' => ['type'=>'debit',  'label'=>tr('cat_fuel'),   'budget'=>$budget_essence, 'color'=>'#f59e0b', 'suggestions'=>['Audi', 'Polo']],
    'School'  => ['type'=>'debit',  'label'=>tr('cat_school'), 'budget'=>$budget_school, 'color'=>'#10b981', 'suggestions'=>[]],
    'Frais'   => ['type'=>'debit',  'label'=>tr('cat_fixed'),  'budget'=>$budget_frais, 'color'=>'#ef4444', 'suggestions'=>[]],
    'Autres'  => ['type'=>'debit',  'label'=>tr('cat_others'), 'budget'=>$budget_autres, 'color'=>'#64748b', 'suggestions'=>['Restaurant', 'Cadeau']],
    'LivretA' => ['type'=>'debit',  'label'=>tr('cat_savings'),'budget'=>0, 'color'=>'#8b5cf6', 'suggestions'=>['Virement']]
];

$totals = array_fill_keys(array_keys($categoriesConfig), 0);
$expensesByCategory = array_fill_keys(array_keys($categoriesConfig), []);
$total_rentrees = 0;
$depenses_reelles = 0;

foreach ($allExpenses as $exp) {
    $cat = $exp['category'];
    if (!isset($totals[$cat])) $cat = 'Autres';
    $val = (float)$exp['amount'];
    
    if ($cat === 'Income') { $totals[$cat] += $val; } 
    else {
        if ($val > 0) $categoriesConfig[$cat]['budget'] += $val;
        else $totals[$cat] += abs($val);
    }
    $expensesByCategory[$cat][] = $exp;

    if ($val > 0) { 
        if ($cat === 'Income' || $cat !== 'Frais') $total_rentrees += $val; 
        else $depenses_reelles -= $val; 
    } else {
        $depenses_reelles += abs($val);
    }
}

// Reste à venir dynamiques
$ecole_depense = isset($totals['School']) ? $totals['School'] : 0;
$reste_ecole = max(0, $budget_school - $ecole_depense);
if ($reste_ecole > 0) {
    $reste_a_venir_calc += $reste_ecole;
    $pending_charges[] = ['name' => tr('bud_rem_school'), 'amount' => $reste_ecole];
}

$fmcg_depense = isset($totals['FMCG']) ? $totals['FMCG'] : 0;
$reste_fmcg = max(0, $budget_fmcg - $fmcg_depense);
if ($reste_fmcg > 0) {
    $reste_a_venir_calc += $reste_fmcg;
    $pending_charges[] = ['name' => tr('bud_rem_fmcg'), 'amount' => $reste_fmcg];
}

$essence_depense = isset($totals['Essence']) ? $totals['Essence'] : 0;
$reste_essence = max(0, $budget_essence - $essence_depense);
if ($reste_essence > 0) {
    $reste_a_venir_calc += $reste_essence;
    $pending_charges[] = ['name' => tr('bud_rem_fuel'), 'amount' => $reste_essence];
}

// F. Calculs des KPIs finaux
$rentrees_salaires_reels = $totals['Income'] ?? 0;
$rentrees_autres = $total_rentrees - $rentrees_salaires_reels;
$salaires_retenus = max($rentrees_salaires_reels, $budget_income_prevu);

$capacite_max_calc = $solde_initial + $salaires_retenus + $rentrees_autres;
$solde_theorique_calc = ($solde_initial + $total_rentrees) - $depenses_reelles - $reste_a_venir_calc;

if ($isClosed) {
    $solde_actuel = $monthState['solde_actuel'];
    $capacite_max = $monthState['capacite_max'];
    $solde_theorique = $monthState['solde_theorique'];
    $reste_a_venir = $monthState['reste_a_venir'];
} else {
    $solde_actuel = $snapshot['amount'];
    $capacite_max = $capacite_max_calc;
    $solde_theorique = $solde_theorique_calc;
    $reste_a_venir = $reste_a_venir_calc;
}

$solde_net = max(0, $solde_actuel - $reste_a_venir);
$charges_visibles = min($solde_actuel, $reste_a_venir); 
$max_scale = max($solde_actuel, $solde_theorique, $capacite_max, 1) * 1.1; 

$pct_net = min(100, max(0, ($solde_net / $max_scale) * 100));
$pct_charges = min(100 - $pct_net, max(0, ($charges_visibles / $max_scale) * 100));
$pct_actuel = min(100, max(0, ($solde_actuel / $max_scale) * 100));
$pct_theorique = min(100, max(0, ($solde_theorique / $max_scale) * 100));

function getDisplayLogic($spent, $bg, $type) {
    $pct = ($bg > 0) ? min(100, ($spent / $bg) * 100) : ($spent > 0 ? 100 : 0);
    $isOver = ($type === 'debit' && $spent > $bg && $bg > 0);
    $text = ($bg > 0) ? number_format(ceil($spent), 0, ',', ' ') . ' / ' . number_format(ceil($bg), 0, ',', ' ') . ' €' : number_format(ceil($spent), 0, ',', ' ') . ' €';
    return ['pct' => $pct, 'isOver' => $isOver, 'text' => $text];
}

// Noms des mois traduits
$monthNames = [
    1 => tr('month_01'), 2 => tr('month_02'), 3 => tr('month_03'), 4 => tr('month_04'),
    5 => tr('month_05'), 6 => tr('month_06'), 7 => tr('month_07'), 8 => tr('month_08'),
    9 => tr('month_09'), 10 => tr('month_10'), 11 => tr('month_11'), 12 => tr('month_12')
];
$monthName = $monthNames[(int)$viewM] . ' ' . $viewY;
?>

<div class="budget-view">

    <div style="background:white; padding:20px; border-radius:16px; box-shadow:var(--shadow-sm); margin-bottom:24px; border:1px solid #e2e8f0; position:relative;">
        
        <?php if ($isClosed): ?>
            <div style="position:absolute; top:0; left:0; right:0; background:#f1f5f9; padding:5px; text-align:center; font-size:0.8rem; font-weight:bold; color:#64748b; border-radius:16px 16px 0 0; border-bottom:1px solid #e2e8f0;">
                🔒 <?= tr('bud_closed_archived') ?>
            </div>
            <div style="height:20px;"></div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0; font-size:1.3rem; color:#0f172a; text-transform:capitalize;"><?= tr('bud_budget_of') ?> <?= $monthName ?></h2>
            </div>
            
        <div class="action-toolbar" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <?php if (!$isClosed): ?>
                    <button type="button" class="pf-btn btn-secondary" onclick="openSuiviModal('importCsvModal')" style="padding:6px 12px; height:auto; width:auto; font-size:0.85rem;">
                        📂 <?= tr('bud_import_csv') ?? 'Importer CSV' ?>
                    </button>

                    <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_close') ?>');">
                        <input type="hidden" name="action" value="close_month">
                        <input type="hidden" name="close_month_date" value="<?= $viewMonthDate ?>">
                        <input type="hidden" name="freeze_solde_actuel" value="<?= $solde_actuel ?>">
                        <input type="hidden" name="freeze_capacite_max" value="<?= $capacite_max ?>">
                        <input type="hidden" name="freeze_solde_theorique" value="<?= $solde_theorique ?>">
                        <input type="hidden" name="freeze_reste_a_venir" value="<?= $reste_a_venir ?>">
                        <button type="submit" class="pf-btn" style="background:linear-gradient(135deg, #10b981, #059669); padding:6px 12px; height:auto; width:auto; font-size:0.85rem;">🔒 <?= tr('bud_close_btn') ?></button>
                    </form>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('<?= tr('bud_confirm_reopen') ?>');">
                        <input type="hidden" name="action" value="reopen_month">
                        <button type="submit" class="pf-btn btn-secondary" style="padding:6px 12px; height:auto; width:auto; font-size:0.85rem;">🔓 <?= tr('bud_reopen_btn') ?></button>
                    </form>
                <?php endif; ?>
                
                <div class="suivi-nav-group">
                    <a href="<?= $prevLink ?>" class="suivi-btn-nav">◀</a>
                    <a href="<?= $defaultLink ?>" class="suivi-btn-nav"><?= tr('bud_active_month_btn') ?></a>
                    <a href="<?= $nextLink ?>" class="suivi-btn-nav">▶</a>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
            <div style="padding:15px; background:#f8fafc; border-radius:12px; position:relative; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;"><?= tr('bud_bank_balance') ?> <?= $isClosed ? "(".tr('bud_frozen').")" : "(".tr('bud_current').")" ?></div>
                <div style="font-size:1.4rem; font-weight:700; color:#0f172a;">
                    <?= number_format($solde_actuel, 2, ',', ' ') ?> €
                </div>
                <?php if (!$isClosed): ?>
                    <button onclick="openSuiviModal('snapshotModal')" style="position:absolute; top:12px; right:12px; background:white; border:1px solid #cbd5e1; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:0.8rem; color:#475569; transition:0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">✏️ <?= tr('bud_update_btn') ?></button>
                <?php endif; ?>
            </div>

            <div style="padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;"><?= tr('bud_theoretical_balance') ?></div>
                <div style="font-size:1.4rem; font-weight:700; color:<?= $solde_theorique < 0 ? '#ef4444' : '#334155' ?>;">
                    <?= number_format($solde_theorique, 2, ',', ' ') ?> €
                </div>
            </div>

            <div style="padding:15px; background:#fef2f2; border-radius:12px; border:1px solid #fecaca; position: relative;">
                <div style="font-size:0.85rem; color:#991b1b; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center;">
                    <span><?= tr('bud_upcoming_charges') ?></span>
                    <button onclick="toggleDiv('pendingDetailsList')" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; filter: grayscale(1); opacity: 0.7; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">👁️</button>
                </div>
                <div style="font-size:1.4rem; font-weight:700; color:#b91c1c;">
                    - <?= number_format($reste_a_venir, 2, ',', ' ') ?> €
                </div>
                <div id="pendingDetailsList" style="display:none; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #fca5a5; font-size: 0.8rem; color: #7f1d1d;">
                    <?php if (empty($pending_charges)): ?>
                        <div style="text-align:center; font-style:italic; opacity:0.8;"><?= tr('bud_all_paid') ?></div>
                    <?php else: ?>
                        <?php foreach($pending_charges as $pc): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span><?= htmlspecialchars($pc['name']) ?></span>
                                <strong><?= number_format($pc['amount'], 0, ',', ' ') ?> €</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // --- 1. CALCULS DE L'ÉCHELLE DYNAMIQUE ---
        $buffer = max(abs($capacite_max), abs($solde_actuel), abs($solde_theorique)) * 0.05; 
        $minScale = min(0, $solde_theorique, $solde_actuel) - $buffer;
        $maxScale = max($capacite_max, $solde_actuel, 0) + $buffer;
        
        $range = $maxScale - $minScale;
        if ($range <= 0) $range = 1;

        $posZero = (($minScale * -1) / $range) * 100;
        $posActuel = (($solde_actuel - $minScale) / $range) * 100;
        $posTheorique = (($solde_theorique - $minScale) / $range) * 100;
        $posMax = (($capacite_max - $minScale) / $range) * 100;

        $widthActuel = (abs($solde_actuel) / $range) * 100;
        $leftActuel = $solde_actuel >= 0 ? $posZero : $posActuel;
        
        $widthDanger = 0; $leftDanger = $posZero;
        if ($solde_theorique < 0) {
            $widthDanger = (abs($solde_theorique) / $range) * 100;
            $leftDanger = $posTheorique; 
        }
        ?>

        <div style="margin-top: 65px; margin-bottom: 75px; position: relative; font-family: inherit;">
            
            <div style="position: relative; height: 24px; background: #e2e8f0; border-radius: 12px; width: 100%; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                <?php if ($posZero > 0): ?>
                    <div style="position: absolute; left: 0; width: <?= $posZero ?>%; height: 100%; background: #fef2f2; border-radius: 12px 0 0 12px;"></div>
                <?php endif; ?>
                
                <div style="position: absolute; left: <?= $leftActuel ?>%; width: <?= $widthActuel ?>%; height: 100%; background: repeating-linear-gradient(45deg, #fbbf24, #fbbf24 10px, #f59e0b 10px, #f59e0b 20px); border-radius: <?= $solde_actuel >= 0 ? '0 12px 12px 0' : '12px' ?>; transition: width 0.5s ease-out;"></div>
                
                <?php if ($solde_theorique < 0): ?>
                    <div style="position: absolute; left: <?= $leftDanger ?>%; width: <?= $widthDanger ?>%; height: 100%; background: repeating-linear-gradient(45deg, #ef4444, #ef4444 10px, #dc2626 10px, #dc2626 20px); border-radius: 12px 0 0 12px; opacity: 0.95;"></div>
                <?php endif; ?>
            </div>

            <div style="position: absolute; left: <?= $posZero ?>%; top: -5px; height: 35px; width: 2px; background: #94a3b8; z-index: 10;"></div>
            <div style="position: absolute; left: <?= $posZero ?>%; top: 32px; transform: translateX(-50%); font-size: 0.85rem; font-weight: 800; color: #64748b;">0 €</div>

            <div style="position: absolute; left: <?= $posActuel ?>%; top: -15px; height: 40px; width: 3px; background: #1e293b; z-index: 15;"></div>
            <div style="position: absolute; left: <?= $posActuel ?>%; top: -35px; transform: translateX(-50%); font-size: 0.8rem; font-weight: bold; color: #1e293b;"><?= tr('bud_bar_actual') ?? 'Actuel' ?></div>

            <div style="position: absolute; left: <?= $posTheorique ?>%; top: 24px; height: 30px; width: 3px; background: <?= $solde_theorique >= 0 ? '#8b5cf6' : '#ef4444' ?>; z-index: 12;"></div>
            <div style="position: absolute; left: <?= $posTheorique ?>%; top: 54px; transform: translateX(-50%); font-size: 0.85rem; font-weight: bold; color: <?= $solde_theorique >= 0 ? '#8b5cf6' : '#ef4444' ?>; white-space: nowrap;"><?= tr('bud_bar_theoretical') ?? 'Théorique' ?></div>

            <?php 
            $overlapMax = abs($posMax - $posActuel) < 35; 
            $topMaxText = $overlapMax ? '-60px' : '-35px';
            $heightMaxLine = $overlapMax ? '65px' : '40px';
            $topMaxLine = $overlapMax ? '-40px' : '-15px';
            ?>
            <div style="position: absolute; left: <?= $posMax ?>%; top: <?= $topMaxLine ?>; height: <?= $heightMaxLine ?>; width: 2px; background: #cbd5e1; z-index: 10;"></div>
            
            <div style="position: absolute; left: <?= $posMax ?>%; top: <?= $topMaxText ?>; transform: translateX(calc(-100% + 10px)); font-size: 0.8rem; font-weight: bold; color: #64748b; white-space: nowrap; cursor: help;" title="<?= sprintf(tr('bud_capacity_tooltip'), number_format($solde_initial, 0), number_format($salaires_retenus, 0), number_format($rentrees_autres, 0)) ?>">
                <?= tr('bud_bar_max_cap') ?? 'Capacité Max' ?> : <?= number_format($capacite_max, 0, ',', ' ') ?> € ℹ️
            </div>
            
        </div>
    </div>


    <div class="categories-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
        <?php foreach ($categoriesConfig as $key => $conf): ?>
        <div class="cat-card" <?= $isClosed ? 'style="opacity:0.8;"' : '' ?>>
            <div class="cat-card-header" style="background:<?= $conf['color'] ?>15; border-bottom:1px solid <?= $conf['color'] ?>30;">
                <div class="cat-card-title-group">
                    <h3 style="margin:0; font-size:1rem; color:<?= $conf['color'] ?>;"><?= $conf['label'] ?></h3>
                    <div class="cat-card-subtitle">
                        <?php $logic = getDisplayLogic($totals[$key], $conf['budget'], $conf['type']); echo $logic['text']; ?>
                    </div>
                </div>
                <?php if (!$isClosed): ?>
                    <button class="btn-add-item" style="color:<?= $conf['color'] ?>;" onclick="openAddModal('<?= $key ?>', '<?= addslashes($conf['label']) ?>')">＋</button>
                <?php endif; ?>
            </div>

            <?php $barCol = ($key === 'Income') ? '#10b981' : ($logic['isOver'] ? '#ef4444' : $conf['color']); ?>
            <div style="background:#f1f5f9; height:4px; width:100%;">
                <div style="width:<?= $logic['pct'] ?>%; background:<?= $barCol ?>; height:100%;"></div>
            </div>

            <div style="flex:1; max-height:300px; overflow-y:auto; padding:0;">
                <?php if (empty($expensesByCategory[$key])): ?>
                    <div style="padding:20px; text-align:center; color:#cbd5e1; font-style:italic; font-size:0.85rem;"><?= tr('bud_no_lines') ?></div>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <?php foreach ($expensesByCategory[$key] as $exp): ?>
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:10px 15px; color:#94a3b8;"><?= date('d/m', strtotime($exp['date_exp'])) ?></td>
                            <td style="padding:10px 5px; font-weight:500;">
                                <?= htmlspecialchars($exp['label']) ?>
                            </td>
                            <td style="padding:10px 15px; text-align:right; font-weight:600; color:<?= $exp['amount'] > 0 ? '#10b981' : '#1e293b' ?>;">
                                <?= $exp['amount'] > 0 ? '+' : '-' ?><?= number_format(abs($exp['amount']), 2) ?>
                            </td>
                            <?php if (!$isClosed): ?>
                                <td style="width:70px; padding-right:10px; text-align:right; white-space:nowrap;">
                                    <button class="btn-icon-action edit" onclick='openEditModal(<?= json_encode($exp, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="<?= tr('edit') ?>">✏️</button>
                                    <button class="btn-icon-action delete btn-safe-click" onclick="deleteExpense(<?= $exp['id'] ?>)" title="<?= tr('delete') ?>">🗑️</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="manualExpenseModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:400px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTitle"><?= tr('bud_new_transaction') ?></h3>
            <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_expense_manual">
            <input type="hidden" name="expense_id" id="modalExpenseId">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_assign_to_month') ?> :</label>
                <input type="month" name="gestion_month" id="modalGestionMonth" class="pf-input" style="font-weight:bold; color:#2563eb;" required>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_real_date') ?></label>
                <input type="date" name="date" id="modalDate" class="pf-input" required>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_category') ?></label>
                <select name="category" id="modalCatSelect" class="pf-input" onchange="handleModalCatChange(this)">
                    <?php foreach($categoriesConfig as $key => $conf): ?>
                        <option value="<?= $key ?>"><?= $conf['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_type') ?></label>
                <select name="is_credit" id="modalIsCredit" class="pf-input">
                    <option value="0"><?= tr('bud_type_expense') ?> (-)</option>
                    <option value="1"><?= tr('bud_type_income') ?> (+)</option>
                </select>
            </div>
            
            <div class="form-group" id="blockInputText" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_label') ?></label>
                <input type="text" name="label" class="pf-input" id="modalLabelInput" list="modalSuggestions" autocomplete="off">
                <datalist id="modalSuggestions"></datalist>
            </div>

            <div class="form-group" id="blockInputSelect" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_beneficiary') ?></label>
                <select name="label_select" id="schoolSelect" class="pf-input">
                    <option value="Ecole Pol">Ecole Pol</option>
                    <option value="Carole">Carole</option>
                </select>
            </div>

            <div class="form-group" id="blockInputFrais" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_fixed_charge') ?></label>
                <select name="budget_item_id" id="fraisSelect" class="pf-input" disabled>
                <option value=""><?= tr('bud_select_beneficiary') ?></option>
                    <?php foreach ($fixedChargesList as $fc): ?><option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="blockInputIncome" style="margin-bottom:15px; display:none;">
                <label class="pf-label"><?= tr('bud_expected_income') ?></label>
                <select name="budget_item_id" id="incomeSelect" class="pf-input" disabled>
                    <option value=""><?= tr('bud_select_beneficiary') ?>    </option>
                    <?php foreach ($incomeList as $inc): ?><option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('bud_amount_eur') ?></label>
                <input type="number" step="0.01" name="amount" id="modalAmount" class="pf-input" placeholder="0.00" required>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeSuiviModal('manualExpenseModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="snapshotModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width:350px;">
        <div class="pf-modal-header">
            <h3 class="pf-modal-title">🏦 <?= tr('bud_update_balance') ?></h3>
            <button type="button" onclick="closeSuiviModal('snapshotModal')" class="pf-modal-close">&times;</button>
        </div>
        <div class="pf-modal-body">
            <form id="snapshotForm" method="POST">
                <input type="hidden" name="action" value="save_snapshot">
                <div class="pf-form-group">
                    <input type="date" name="snapshot_date" class="pf-input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="pf-form-group">
                    <input type="number" step="0.01" name="snapshot_amount" class="pf-input" placeholder="<?= tr('bud_balance_eur') ?>" required>
                </div>
            </form>
        </div>
        <div class="pf-modal-footer">
            <button type="button" onclick="closeSuiviModal('snapshotModal')" class="pf-btn pf-btn-secondary"><?= tr('btn_cancel') ?></button>
            <button type="submit" form="snapshotForm" class="pf-btn pf-btn-primary"><?= tr('btn_save') ?></button>
        </div>
    </div>
</div>

<div id="importCsvModal" class="pf-modal <?= $showPreview ? 'open' : '' ?>">
    <div class="pf-modal-content <?= $showPreview ? 'modal-large' : '' ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="pf-modal-title" style="margin:0; border:none; padding:0;"><?= tr('bud_import_csv') ?></h3>
            <button type="button" onclick="closeSuiviModal('importCsvModal')" class="pf-modal-close" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>

        <?php if (!$showPreview): ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="import-dropzone">
                    <div style="font-size: 2.5rem; margin-bottom: 10px;">📄</div>
                    <label class="pf-label"><?= tr('bud_csv_file') ?></label>
                    <input type="file" name="csv_file" accept=".csv" class="pf-input" style="max-width: 300px; margin: 10px auto; display: block;" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeSuiviModal('importCsvModal')" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                    <button type="submit" class="pf-btn"><?= tr('bud_preview') ?></button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" id="formMapping">
                <input type="hidden" name="action" value="save_import">
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <p style="margin:0; color:#64748b; font-size:0.95rem;"><?= tr('bud_validate_import') ?? 'Veuillez vérifier et categoriser les lignes avant validation.' ?></p>
                    <span id="missingCount" style="color:#ef4444; background:#fee2e2; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.85rem; display:none;"></span>
                </div>

                <div class="import-table-wrapper">
                    <table class="pf-table" style="margin:0; box-shadow:none; border-radius:0;">
                        <thead style="position:sticky; top:0; z-index:10; background:#f8fafc; outline: 1px solid #e2e8f0;">
                            <tr>
                                <th style="padding:10px;"><input type="checkbox" onclick="toggleAll(this)" checked></th>
                                <th style="padding:10px;"><?= tr('bud_assign_month') ?></th>
                                <th style="padding:10px;"><?= tr('bud_op_date') ?></th>
                                <th style="padding:10px;"><?= tr('bud_label') ?></th>
                                <th style="padding:10px;"><?= tr('bud_amount') ?></th>
                                <th style="padding:10px;"><?= tr('bud_category') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvData as $idx => $row): 
                                $dup = $row['is_duplicate']; $isCrd = $row['is_credit']; $dis = $dup?'disabled':''; 
                                $bgCol = $dup ? 'opacity:0.5' : (empty($row['cat']) && !$isCrd ? 'background:#fff1f2' : '');
                            ?>
                            <tr style="<?= $bgCol ?>">
                                <td style="padding:8px;">
                                    <input type="checkbox" class="line-checkbox" name="lines[<?= $idx ?>][import_check]" value="1" <?= $dup?'':'checked' ?> <?= $dis ?> onchange="checkValidation()">
                                    <input type="hidden" name="lines[<?= $idx ?>][date]" value="<?= $row['date'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][label]" value="<?= $row['label'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][amount]" value="<?= $row['amount'] ?>">
                                    <input type="hidden" name="lines[<?= $idx ?>][ref]" value="<?= $row['ref'] ?>">
                                    <input type="hidden" class="is-credit-flag" name="lines[<?= $idx ?>][is_credit]" value="<?= $isCrd ?>">
                                </td>
                                <td style="padding:8px;">
                                    <input type="month" name="lines[<?= $idx ?>][gestion_month]" value="<?= substr($viewMonthDate, 0, 7) ?>" class="pf-input" style="padding:4px; font-size:0.85rem;" <?= $dis ?>>
                                </td>
                                <td style="padding:8px; white-space:nowrap; font-weight:600;"><?= date('d/m', strtotime($row['date'])) ?></td>
                                <td style="padding:8px;"><?= htmlspecialchars($row['label']) ?></td>
                                <td style="padding:8px; font-weight:bold; color:<?= $isCrd ? '#10b981' : '#1e293b' ?>; white-space:nowrap;"><?= $isCrd ? '+' : '-' ?> <?= number_format($row['amount'],2) ?> €</td>
                                <td style="padding:8px;">
                                    <div style="display:flex; gap:5px; min-width: 250px;">
                                        <select name="lines[<?= $idx ?>][cat]" class="pf-input line-select" onchange="handleLineCatChange(this)" <?= $dis ?> style="padding:4px; font-size:0.85rem; flex:1;">
                                            <option value="">-- <?= $isCrd ? tr('bud_ignore') : tr('bud_to_define') ?> --</option>
                                            <?php foreach ($categoriesConfig as $k => $c): ?>
                                                <option value="<?= $k ?>" <?= ($row['cat']===$k)?'selected':'' ?>><?= $c['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-frais" onchange="checkValidation()" style="display:none; padding:4px; font-size:0.85rem; flex:1;" disabled>
                                            <option value="">-- <?= tr('bud_is_charge') ?> --</option>
                                            <?php foreach ($fixedChargesList as $fc): ?><option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option><?php endforeach; ?>
                                        </select>
                                        <select name="lines[<?= $idx ?>][budget_item_id]" class="pf-input select-income" onchange="checkValidation()" style="display:none; padding:4px; font-size:0.85rem; flex:1;" disabled>
                                            <option value="">-- <?= tr('bud_is_income') ?> --</option>
                                            <?php foreach ($incomeList as $inc): ?><option value="<?= $inc['id'] ?>"><?= htmlspecialchars($inc['name']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer" style="border-top:none; padding-top:20px;">
                    <a href="?tab=suivi" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></a>
                    <button type="submit" id="btnImport" class="pf-btn" style="background:linear-gradient(135deg, #10b981, #059669);"><?= tr('btn_import') ?? 'Importer les lignes cochées' ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>

<?php if ($showPreview): ?>
    document.body.classList.add('no-scroll');
<?php endif; ?>

// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
window.I18N = {
    ...(window.I18N || {}),
    'bud_confirm_delete': <?= json_encode(tr('bud_confirm_delete')) ?>,
    'bud_to_define_js': <?= json_encode(tr('bud_to_define_js')) ?>,
    'error_occured': <?= json_encode(tr('error_occured')) ?>,
    'bud_err_tech': <?= json_encode(tr('bud_err_tech')) ?>
};

const activeViewMonth = '<?= substr($viewMonthDate, 0, 7) ?>';
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'none') ? 'block' : 'none'; }
function openSuiviModal(id) { document.getElementById(id).classList.add('open'); document.body.classList.add('no-scroll'); }
function closeSuiviModal(id) { document.getElementById(id).classList.remove('open'); document.body.classList.remove('no-scroll');}

const suggestions = <?= json_encode(array_map(fn($c) => $c['suggestions'], $categoriesConfig)) ?>;

function handleModalCatChange(select) {
    const catKey = select.value;
    
    document.getElementById('blockInputText').style.display = 'none';
    document.getElementById('blockInputSelect').style.display = 'none';
    document.getElementById('blockInputFrais').style.display = 'none';
    document.getElementById('blockInputIncome').style.display = 'none';
    
    document.getElementById('modalLabelInput').required = false;
    document.getElementById('fraisSelect').required = false;
    document.getElementById('incomeSelect').required = false;
    document.getElementById('fraisSelect').disabled = true;
    document.getElementById('incomeSelect').disabled = true;

    if (catKey === 'School') { document.getElementById('blockInputSelect').style.display = 'block'; } 
    else if (catKey === 'Frais') { document.getElementById('blockInputText').style.display = 'block'; document.getElementById('blockInputFrais').style.display = 'block'; document.getElementById('fraisSelect').required = true; document.getElementById('fraisSelect').disabled = false; }
    else if (catKey === 'Income') { document.getElementById('blockInputText').style.display = 'block'; document.getElementById('blockInputIncome').style.display = 'block'; document.getElementById('incomeSelect').required = true; document.getElementById('incomeSelect').disabled = false; }
    else {
        document.getElementById('blockInputText').style.display = 'block'; document.getElementById('modalLabelInput').required = true;
        const list = document.getElementById('modalSuggestions'); list.innerHTML = ''; 
        if (suggestions[catKey]) { suggestions[catKey].forEach(i => { const op = document.createElement('option'); op.value = i; list.appendChild(op); }); }
    }
}

function openAddModal(catKey, catLabel) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "<?= tr('bud_add_title') ?> : " + catLabel;
    document.getElementById('modalExpenseId').value = ""; 
    document.getElementById('modalDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalGestionMonth').value = activeViewMonth; 
    document.getElementById('modalLabelInput').value = "";
    document.getElementById('modalAmount').value = "";
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = catKey; handleModalCatChange(catSelect);
    document.getElementById('modalIsCredit').value = (catKey === 'Income') ? "1" : "0";
}

function openEditModal(e) {
    openSuiviModal('manualExpenseModal');
    document.getElementById('modalTitle').innerText = "<?= tr('bud_edit_title') ?>";
    document.getElementById('modalExpenseId').value = e.id;
    document.getElementById('modalDate').value = e.date_exp;
    document.getElementById('modalGestionMonth').value = e.gestion_month ? e.gestion_month.substring(0, 7) : activeViewMonth;
    document.getElementById('modalLabelInput').value = e.label;
    document.getElementById('modalIsCredit').value = parseFloat(e.amount) > 0 ? "1" : "0";
    document.getElementById('modalAmount').value = Math.abs(parseFloat(e.amount));
    const catSelect = document.getElementById('modalCatSelect'); catSelect.value = e.category; handleModalCatChange(catSelect);
    if (e.category === 'Frais') document.getElementById('fraisSelect').value = e.budget_item_id;
    else if (e.category === 'Income') document.getElementById('incomeSelect').value = e.budget_item_id;
}

function handleLineCatChange(select) {
    const row = select.closest('tr');
    const fSel = row.querySelector('.select-frais'); const iSel = row.querySelector('.select-income');
    fSel.style.display = 'none'; iSel.style.display = 'none'; fSel.value = ''; iSel.value = ''; fSel.disabled = true; iSel.disabled = true;
    if (select.value === 'Frais') { fSel.style.display = 'block'; fSel.disabled = false; } 
    else if (select.value === 'Income') { iSel.style.display = 'block'; iSel.disabled = false; }
    checkValidation();
}

function toggleAll(src) { document.querySelectorAll('.line-checkbox:not([disabled])').forEach(c => c.checked = src.checked); checkValidation(); }

function checkValidation() {
    let miss = 0;
    document.querySelectorAll('.line-checkbox:checked').forEach(cb => { 
        const row = cb.closest('tr'); const isCrd = row.querySelector('.is-credit-flag').value === '1'; const cat = row.querySelector('.line-select').value;
        let v = true; if (cat==="") { if(!isCrd) v = false; } else if (cat==='Frais' && row.querySelector('.select-frais').value==="") v=false; else if (cat==='Income' && row.querySelector('.select-income').value==="") v=false;
        if (!v) { miss++; row.style.background = '#fff1f2'; } else row.style.background = '';
    });
    const btn = document.getElementById('btnImport'); const msg = document.getElementById('missingCount');
    if(miss>0) { btn.disabled = true; btn.style.opacity=0.5; msg.style.display='inline'; msg.innerText = miss + ' ' + (window.I18N['bud_to_define_js'] || ''); } 
    else { btn.disabled = false; btn.style.opacity=1; msg.style.display='none'; }
}

if(document.getElementById('formMapping')) { document.querySelectorAll('.line-select').forEach(s => handleLineCatChange(s)); checkValidation(); }

window.addEventListener('click', (e) => {
    if (e.target.classList.contains('pf-modal')) {
        e.target.style.display = 'none';
        document.body.classList.remove('no-scroll');
    }
});

// --- 2. SUPPRESSION ASYNCHRONE ---
async function deleteExpense(id) {
    const confirmed = await pachaConfirm("Suppression", tr('bud_confirm_delete'));
    if (!confirmed) return;

    const formData = new FormData();
    formData.append("action", "delete_expense");
    formData.append("id", id);

    try {
        await pachaFetch("modules/budget/includes/api/manage-item.php", { method: "POST", body: formData });
        showToast(window.I18N['bud_toast_deleted'] || "Supprimé"); 
        setTimeout(() => window.location.reload(), 800);
    } catch (err) {
        showToast(window.I18N['error_occured'] || "Erreur", "error");
    }
}

// --- 3. SAUVEGARDE ASYNCHRONE (AJOUT/MODIF) ---
document.addEventListener('DOMContentLoaded', () => {
    const formExpense = document.querySelector('#manualExpenseModal form');
    if (formExpense) {
        formExpense.addEventListener('submit', async (e) => {
            e.preventDefault(); 
            const submitBtn = formExpense.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerText = '⏳ ...';

                const formData = new FormData(formExpense);
                // Force l'action ici pour être sûr
                formData.set('action', 'save_expense_manual'); 

                const actionUrl = formExpense.getAttribute('action') || 'modules/budget/includes/api/manage-item.php';

                const result = await pachaFetch(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (result.success) {
                    closeSuiviModal('manualExpenseModal');
                    formExpense.reset();
                    window.location.reload(); 
                } else {
                    alert((window.I18N['error_occured'] || 'Erreur') + ' : ' + (result.error || 'Inconnue'));
                }
            } catch (error) {
                console.error("Erreur réseau :", error);
                alert(window.I18N['bud_err_tech'] || "Erreur critique réseau.");
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalBtnText;
            }
        });
    }
});
</script>
```

---

### 📄 Fichier : `modules/family-calendar/admin/generate-calendar-year.php`
```php
<?php
// /modules/family-calendar/admin/generate-calendar-year.php
require __DIR__ . '/../../../includes/db.php';

// S'assurer que PDO lève des exceptions en cas d'erreur
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    die("Erreur : PDO non initialisé dans db.php");
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    die("Année invalide.");
}

try {
    $pdo->beginTransaction();

    // On supprime d'abord les semaines de cette année calendaire
    $stmtDel = $pdo->prepare("DELETE FROM pf_calendar_weeks WHERE year = :year");
    $stmtDel->execute([':year' => $year]);

    // 1er janvier de l'année calendaire
    $start = new DateTime("$year-01-01");

    // On remonte au lundi précédent (ou on reste dessus si déjà lundi)
    while ($start->format('N') != 1) { // 1 = lundi
        $start->modify('-1 day');
    }

    // Dernier jour de l'année
    $end = new DateTime("$year-12-31");

    // Préparation de l'INSERT avec gestion des doublons
    // (en supposant une contrainte UNIQUE, par ex. sur (year, week_iso_year, week_iso_number))
    $sql = "
      INSERT INTO pf_calendar_weeks (
        year, week_iso_year, week_iso_number, week_label,
        month, month_name,
        week_start_date, mon_date, tue_date, wed_date, thu_date, fri_date, sat_date, sun_date
      ) VALUES (
        :year, :week_iso_year, :week_iso_number, :week_label,
        :month, :month_name,
        :week_start_date, :mon_date, :tue_date, :wed_date, :thu_date, :fri_date, :sat_date, :sun_date
      )
      ON DUPLICATE KEY UPDATE
        week_label      = VALUES(week_label),
        month           = VALUES(month),
        month_name      = VALUES(month_name),
        week_start_date = VALUES(week_start_date),
        mon_date        = VALUES(mon_date),
        tue_date        = VALUES(tue_date),
        wed_date        = VALUES(wed_date),
        thu_date        = VALUES(thu_date),
        fri_date        = VALUES(fri_date),
        sat_date        = VALUES(sat_date),
        sun_date        = VALUES(sun_date)
    ";
    $stmt = $pdo->prepare($sql);

    // fonction mois FR
    function getMonthNameFr($monthIndexZeroBased) {
        $months = [
            "Janvier", "Fevrier", "Mars", "Avril", "Mai", "Juin",
            "Juillet", "Aout", "Septembre", "Octobre", "Novembre", "Decembre",
        ];
        return $months[$monthIndexZeroBased] ?? "";
    }

    $current = clone $start;
    $insertCount = 0;

    while ($current <= $end) {
        $monday = clone $current;

        // Calcul des 7 jours de la semaine
        $mon = clone $monday;
        $tue = (clone $monday)->modify('+1 day');
        $wed = (clone $monday)->modify('+2 days');
        $thu = (clone $monday)->modify('+3 days');
        $fri = (clone $monday)->modify('+4 days');
        $sat = (clone $monday)->modify('+5 days');
        $sun = (clone $monday)->modify('+6 days');

        // Année / semaine ISO
        $weekIsoYear   = (int)$monday->format('o'); // année ISO
        $weekIsoNumber = (int)$monday->format('W');
        $weekLabel     = 'W' . str_pad($weekIsoNumber, 2, '0', STR_PAD_LEFT);

        // Détermination du mois d'affectation de la semaine
        // -> on compte combien de jours de la semaine tombent dans chaque mois
        $days = [$mon, $tue, $wed, $thu, $fri, $sat, $sun];

        // compteur mois => nb de jours (clé = numéro de mois 1..12)
        $monthCounts = [];

        foreach ($days as $d) {
            $m = (int)$d->format('n'); // 1-12
            if (!isset($monthCounts[$m])) {
                $monthCounts[$m] = 0;
            }
            $monthCounts[$m]++;
        }

        // On prend le mois ayant le plus de jours
        // (si égalité, le mois ayant la plus petite valeur numérique gagnera,
        // ce qui est raisonnable, mais on peut changer si besoin)
        $chosenMonth = null;
        $maxDays     = -1;
        foreach ($monthCounts as $m => $count) {
            if ($count > $maxDays) {
                $maxDays     = $count;
                $chosenMonth = $m;
            }
        }

        $month     = $chosenMonth;
        $monthName = getMonthNameFr($month - 1);

                $stmt->execute([
            ':year'            => $weekIsoYear,   // <-- au lieu de $year
            ':week_iso_year'   => $weekIsoYear,
            ':week_iso_number' => $weekIsoNumber,
            ':week_label'      => $weekLabel,
            ':month'           => $month,
            ':month_name'      => $monthName,
            ':week_start_date' => $monday->format('Y-m-d'),
            ':mon_date'        => $mon->format('Y-m-d'),
            ':tue_date'        => $tue->format('Y-m-d'),
            ':wed_date'        => $wed->format('Y-m-d'),
            ':thu_date'        => $thu->format('Y-m-d'),
            ':fri_date'        => $fri->format('Y-m-d'),
            ':sat_date'        => $sat->format('Y-m-d'),
            ':sun_date'        => $sun->format('Y-m-d'),
        ]);


        $insertCount++;
        $current->modify('+7 days');
    }

    $pdo->commit();

    echo "Calendrier $year généré. Semaines traitées : " . $insertCount;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur PDO : " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur générale : " . $e->getMessage());
}

```

---

### 📄 Fichier : `modules/family-calendar/family-calendar.css`
```css
/* modules/family-calendar/family-calendar.css */

:root {
  /* Couleurs sémantiques Calendrier */
  --c-school-holiday: #e5d9f2;
  --c-public-holiday: #e2e8f0; /* Plus marqué que l'original */
  --c-off-carole: #ffedd5;
  --c-extra-off: #fee2e2;
  --c-selected: #bfdbfe;

  /* Thèmes Parents (Sync Budget) */
  --bg-alex: #ecfeff;
  --text-alex: #0891b2;
  --bg-laia: #fffbeb;
  --text-laia: #d97706;

  /* Rappel des variables Global Design System pour cohérence */
  --pf-primary: #3b82f6;
  --pf-border: #e2e8f0;
  --pf-bg-lighter: #f8fafc;
  --pf-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.pf-family-calendar .pf-main {
  padding-top: 20px;
  padding-bottom: 40px;
}

/* En-tête & Boutons */
.fc-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.pf-btn-icon-text {
  background: white;
  border: 1px solid var(--pf-border);
  border-radius: 50px;
  padding: 10px 20px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--pf-shadow-sm);
}
.pf-btn-icon-text:hover {
  border-color: var(--pf-primary);
  color: var(--pf-primary);
  background: #f8fafc;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

/* Calendrier Mensuel */
.fc-month-calendar-wrapper {
  background: white;
  border: 1px solid var(--pf-border);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 32px;
  box-shadow: var(--pf-shadow-sm);
}
.fc-month-header {
  display: flex !important;
  flex-direction: column !important;
  gap: 16px !important;
  margin-bottom: 20px !important;
}
.fc-month-nav-row {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  width: 100% !important;
}
.fc-month-nav-row h3 {
  margin: 0 !important;
  font-size: 1.5rem !important;
  font-weight: 800 !important;
  color: #0f172a;
  text-transform: capitalize !important;
  text-align: center !important;
  flex-grow: 1 !important;
}
.fc-nav-button {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid var(--pf-border);
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: all 0.2s;
  color: #64748b;
}
.fc-nav-button:hover {
  border-color: var(--pf-primary);
  color: var(--pf-primary);
  background: #eff6ff;
}

.fc-view-controls {
  display: flex !important;
  width: 100% !important;
  background: #f1f5f9 !important;
  padding: 4px !important;
  border-radius: 12px !important;
}
.fc-view-button {
  flex: 1 !important;
  text-align: center !important;
  background: transparent;
  border: none;
  border-radius: 6px;
  padding: 6px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--pf-text-muted);
  cursor: pointer;
  transition: 0.2s;
}
.fc-view-button--active {
  background: white !important;
  color: var(--pf-primary) !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
  font-weight: 700;
}

.fc-month-calendar {
  width: 100%;
  display: block;
  overflow-x: auto;
}
.fc-month-table {
  min-width: 100% !important;
  width: 100% !important;
  table-layout: fixed;
  border-collapse: separate !important;
  border-spacing: 0;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--pf-border) !important;
}
.fc-month-table th {
  padding: 10px !important;
  font-size: 0.8rem !important;
  background: #f8fafc !important;
  color: #64748b !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  border-bottom: 1px solid var(--pf-border) !important;
  border-right: 1px solid var(--pf-border) !important;
}
.fc-month-table td {
  height: 80px;
  padding: 6px !important;
  font-size: 0.85rem;
  vertical-align: top;
  position: relative;
  cursor: pointer;
  border-right: 1px solid var(--pf-border) !important;
  border-bottom: 1px solid var(--pf-border) !important;
  transition: background 0.1s;
}
.fc-month-table td:hover:not(.fc-day--other-month) {
  background: #f1f5f9;
}
.fc-day--other-month {
  background: #fcfcfc !important;
}

/* Vues Multi-mois */
.fc-two-months-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  width: 100%;
  overflow-x: auto;
}
.fc-three-months-container {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  width: 100%;
  overflow-x: auto;
}
.fc-month-title {
  text-align: center;
  font-weight: 700;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid var(--pf-primary);
  text-transform: capitalize;
}

/* Décompte Congés (Pills) */
.fc-month-balances {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 15px;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px dashed var(--pf-border);
}
.fc-minimal-balance-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  padding: 8px 16px;
  border-radius: 50px;
  border: 1px solid var(--pf-border);
  box-shadow: var(--pf-shadow-sm);
}
.fc-minimal-balance-card strong.alex {
  color: var(--text-alex);
}
.fc-minimal-balance-card strong.laia {
  color: var(--text-laia);
}
.fc-minimal-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.fc-min-chip {
  display: flex;
  align-items: center;
  background: white;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.75rem;
  border: 1px solid var(--pf-border);
  font-weight: 600;
}
.fc-min-chip .type {
  font-weight: 700;
  color: var(--pf-text-muted);
  margin-right: 6px;
}
.fc-min-chip .val {
  font-weight: 800;
  color: var(--pf-text-main);
}
.fc-used-badge {
  margin-left: 6px;
  background: #fee2e2;
  color: var(--pf-danger);
  padding: 1px 5px;
  border-radius: 10px;
  font-size: 0.65rem;
  font-weight: bold;
}
.fc-burn-alert {
  margin-left: 5px;
  cursor: help;
  animation: pulseBurn 2s infinite;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  color: var(--pf-danger);
  font-weight: bold;
}
@keyframes pulseBurn {
  0% {
    transform: scale(1);
    opacity: 0.8;
  }
  50% {
    transform: scale(1.2);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 0.8;
  }
}

/* Couleurs des jours */
.fc-day--school-holiday {
  background: var(--c-school-holiday) !important;
}
.fc-day--public-holiday {
  background: var(--c-public-holiday) !important;
  font-weight: 700;
}
.fc-day--off-carole {
  background: var(--c-off-carole) !important;
}
.fc-day--extra-off-carole {
  background: var(--c-extra-off) !important;
}
.fc-day--selected {
  background: var(--c-selected) !important;
  outline: 2px solid var(--pf-primary);
  z-index: 5;
}
.fc-day--centre::after {
  content: "🏫";
  position: absolute;
  top: 2px;
  right: 2px;
  font-size: 12px;
}
.fc-day--avis::after {
  content: "";
  position: absolute;
  top: 2px;
  right: 2px;
  width: 14px;
  height: 14px;
  background: url("/modules/family-calendar/assets/img/avis.svg") no-repeat
    center;
  background-size: contain;
}
.fc-pep-sick-emoji {
  position: absolute;
  bottom: 2px;
  right: 2px;
  font-size: 12px;
}
.fc-day--off-carole.fc-day--school-holiday,
.fc-day--extra-off-carole.fc-day--school-holiday,
.fc-day--centre.fc-day--school-holiday,
.fc-day--avis.fc-day--school-holiday {
  box-shadow: inset 0 -8px 0 0 var(--c-school-holiday) !important;
}

/* Planning Hebo (Desktop) */
.fc-week-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.fc-week-nav-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  padding: 4px 12px;
  border-radius: 50px;
}
#fc-current-school-year-label {
  font-size: 1rem;
  font-weight: 800;
  min-width: 120px;
  text-align: center;
}
#planningTable-wrapper {
  max-height: 80vh;
  overflow: auto;
  border: 1px solid var(--pf-border);
  border-radius: 16px;
  background: white;
  box-shadow: var(--pf-shadow-sm);
  position: relative;
  margin-bottom: 24px;
}
#planningTable {
  width: 100%;
  min-width: 1000px;
  border-collapse: separate;
  border-spacing: 0;
}

/* =========================================================
   HARMONISATION DES BORDURES ET HEADERS STICKY (1px strict)
   ========================================================= */
#planningTable thead tr {
  height: 30px; /* On fixe la hauteur pour un calcul parfait */
}
#planningTable thead th {
  position: sticky;
  background: #f8fafc !important;
  border: none !important; /* On supprime les bordures natives */

  /* On utilise UNIQUEMENT des ombres internes pour simuler 1px de bordure sans doublement */
  box-shadow:
    inset 0 -1px 0 var(--pf-border),
    inset -1px 0 0 var(--pf-border) !important;

  padding: 6px;
  font-size: 0.75rem;
  z-index: 20;
  color: #475569;
  background-clip: padding-box;
}
#planningTable thead tr:nth-child(1) th {
  top: 0;
  z-index: 22; /* Au premier plan */
}
#planningTable thead tr:nth-child(2) th {
  top: 30px; /* Exactement la hauteur de la ligne 1 */
  z-index: 21;
}
#planningTable thead tr:nth-child(3) th {
  top: 60px; /* Ligne 1 + Ligne 2 */
  z-index: 20;
}

.col-alex.header-group,
.col-alex-sub {
  background: var(--bg-alex) !important;
  color: var(--text-alex) !important;
}
.col-laia.header-group,
.col-laia-sub {
  background: var(--bg-laia) !important;
  color: var(--text-laia) !important;
}

/* Cellules standards du corps */
#planningTable tbody td {
  position: relative;
  border: none !important; /* Reset de sécurité */
  border-right: 1px solid var(--pf-border) !important;
  border-bottom: 1px solid var(--pf-border) !important;
  height: 36px;
  padding: 0 4px;
  text-align: center;
  font-size: 0.85rem;
  white-space: nowrap;
  cursor: pointer;
}

/* Dimensions des colonnes */
.col-month {
  width: 54px !important;
  min-width: 54px !important;
  max-width: 54px !important;
  background: white;
  text-align: center;
  vertical-align: middle;
}
th.col-day,
td.col-day {
  min-width: 35px !important;
}
th.col-total,
td.col-total,
th.col-alex-sub,
td.col-alex-sub,
th.col-laia-sub,
td.col-laia-sub {
  width: 32px !important;
  min-width: 32px !important;
  max-width: 32px !important;
}

.rotated-text span {
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  font-size: 0.7rem;
  color: var(--pf-text-muted);
}

/* =========================================================
   GESTION DES COLONNES STICKY (Mois & Semaine)
   ========================================================= */

#planningTable tbody td.col-sticky-mois {
  position: sticky !important;
  left: 0 !important;
  z-index: 15 !important;
  background: white !important;
  padding: 0 !important;
}

#planningTable tbody td.col-sticky-mois .fc-sticky-mois-label {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  writing-mode: vertical-rl;
  letter-spacing: 1px;
  text-align: center;
}

/* 2. Sticky Semaine (Body) */
#planningTable tbody td.col-sticky-sem {
  position: sticky !important;
  left: 54px !important;
  z-index: 14 !important;
  background: white !important;
  /* Ombre portée pour délimiter la zone sticky du reste du tableau */
  box-shadow: 4px 0 6px -2px rgba(0, 0, 0, 0.1);
}

/* 3. Sticky Mois (Header) */
#planningTable thead tr th.col-sticky-mois {
  position: sticky !important;
  top: 0 !important;
  left: 0 !important;
  z-index: 30 !important;
  background: var(--pf-bg-lighter) !important;
  /* Les bordures sont gérées par le box-shadow général du thead th */
}

/* 4. Sticky Semaine (Header) */
#planningTable thead tr th.col-sticky-sem {
  position: sticky !important;
  top: 0 !important;
  left: 54px !important;
  z-index: 29 !important;
  background: var(--pf-bg-lighter) !important;
  /* Ombre interne (bordures) + Ombre portée (effet sticky) */
  box-shadow:
    inset 0 -1px 0 var(--pf-border),
    inset -1px 0 0 var(--pf-border),
    4px 0 6px -2px rgba(0, 0, 0, 0.1) !important;
}

/* Rétablissement horizontal pour les headers mois et semaine */
#planningTable thead th.col-month,
#planningTable tbody td.col-sticky-sem {
  writing-mode: horizontal-tb;
  transform: none;
  padding: 6px !important;
  text-align: center;
}

/* Panneaux bas & Légendes */
.fc-bottom-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  align-items: start;
}

/* Nouvelles bordures style "Budget" pour les conteneurs */
.pf-card {
  background: white;
  border: 1px solid var(--pf-border);
  border-radius: 16px;
  box-shadow: var(--pf-shadow-sm);
  padding: 24px;
}
.pf-card-title {
  margin-top: 0;
  margin-bottom: 16px;
  font-size: 1.25rem;
  color: #0f172a;
  border-bottom: 1px solid var(--pf-border);
  padding-bottom: 12px;
}

.pf-legend-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pf-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  padding: 6px 10px;
  background: #f8fafc;
  border: 1px solid var(--pf-border);
  border-radius: 8px;
  font-weight: 500;
  color: #475569;
}
.pf-legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
}
.fc-legend-school-holiday {
  background: var(--c-school-holiday);
}
.fc-legend-public-holiday {
  background: var(--c-public-holiday);
}
.fc-legend-off-carole {
  background: var(--c-off-carole);
}
.fc-legend-extra-off-carole {
  background: var(--c-extra-off);
}
.fc-legend-centre::after {
  content: "🏫";
}
.fc-legend-pep-sick::after {
  content: "🤒";
}
.fc-legend-avis {
  background: white url("/modules/family-calendar/assets/img/avis.svg")
    no-repeat center;
  background-size: contain;
  border: none;
}

.fc-summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--pf-border);
}
.fc-summ-select {
  border: 1px solid var(--pf-border);
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 0.8rem;
  height: auto;
  background: #f8fafc;
}
.fc-summary-item {
  display: flex;
  justify-content: space-between;
  padding: 12px 4px;
  border-bottom: 1px solid var(--pf-border); /* Ligne de séparation visible */
}
.fc-summary-item:last-child {
  border-bottom: none;
}
.fc-summary-label {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
}
.fc-summary-value {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

/* Menu Contextuel Tactile */
.fc-selection-menu {
  position: absolute;
  z-index: 9000;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow: var(--pf-shadow-lg);
  border-radius: 16px;
  padding: 10px 12px;
  min-width: 220px;
  display: none;
  animation: menuPopIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  transform-origin: top center;
}
@keyframes menuPopIn {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
.fc-menu-section {
  padding: 6px 0;
  border-bottom: 1px dashed #cbd5e1;
}
.fc-menu-section:last-child {
  border: none;
  padding-bottom: 0;
}
.fc-menu-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}
.fc-menu-section strong {
  font-size: 0.7rem;
  text-transform: uppercase;
  color: var(--pf-text-muted);
}
.fc-menu-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
}
.fc-menu-btn {
  background: white;
  border: 1px solid var(--pf-border);
  padding: 6px 8px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
  transition: 0.2s;
  box-shadow: var(--pf-shadow-sm);
}
.fc-menu-btn:hover {
  background: var(--pf-bg-page);
  border-color: var(--pf-primary);
  color: var(--pf-primary);
}
.fc-menu-clear-icon {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
  display: flex;
  border-radius: 6px;
}
.fc-menu-clear-icon svg {
  width: 14px;
  height: 14px;
}
.fc-menu-clear-icon:hover {
  background: #fef2f2;
  color: var(--pf-danger);
}
.fc-menu-leaves-table table {
  width: 100%;
  border-spacing: 2px;
}
.fc-menu-leaves-table th {
  font-size: 0.7rem;
  color: var(--pf-text-muted);
  text-align: center;
}
.fc-th-inline {
  display: flex;
  justify-content: center;
  gap: 4px;
}

/* Table specifique modale vacances */
.fc-holidays-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.fc-holidays-table th {
  background: #f8fafc;
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  position: sticky;
  top: 0;
  border-bottom: 2px solid var(--pf-border);
}
.fc-holidays-table td {
  padding: 10px 16px;
  border-bottom: 1px solid var(--pf-border);
}
.fc-holidays-table tr:nth-child(even) {
  background: #fafafa;
}

/* ===  MODALES : Alignement du Header et de la Croix === */
.pf-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 15px;
  border-bottom: 1px solid var(--pf-border);
  padding-bottom: 15px;
}

.pf-modal-header .pf-modal-title {
  margin: 0;
  padding: 0;
  border-bottom: none; /* On annule la bordure par défaut du global.css pour la déléguer au header */
  flex-grow: 1;
}

.pf-modal-close {
  background: none;
  border: none;
  font-size: 1.8rem;
  line-height: 1;
  cursor: pointer;
  color: #94a3b8;
  padding: 0 0 0 15px;
  transition: color 0.2s;
}

.pf-modal-close:hover {
  color: #ef4444; /* Devient rouge au survol */
}

/* Mobile */
@media (max-width: 768px) {
  .pf-family-calendar .pf-container {
    padding: 0 !important;
  }
  .fc-header-row {
    flex-direction: column;
    gap: 15px;
    padding: 0 10px;
  }
  .fc-header-row > div {
    flex-direction: column;
    width: 100%;
  }
  .pf-btn-icon-text {
    width: 100%;
    justify-content: center;
  }
  .fc-week-header,
  #planningTable-wrapper {
    display: none !important;
  }
  .fc-month-calendar-wrapper {
    padding: 16px !important;
    margin-bottom: 20px !important;
  }
  .fc-month-table td {
    height: 46px !important;
    padding: 2px !important;
    font-size: 0.75rem !important;
  }
  .fc-bottom-grid {
    grid-template-columns: 1fr;
    padding: 10px;
  }
  .fc-selection-menu {
    position: fixed !important;
    top: auto !important;
    left: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    border-radius: 24px 24px 0 0;
    padding: 24px 20px;
    transform: translateY(100%);
    animation: slideUpSheet 0.3s forwards;
    z-index: 10000 !important;
  }
  .fc-selection-menu::before {
    content: "";
    display: block;
    width: 40px;
    height: 5px;
    background: #cbd5e1;
    border-radius: 10px;
    margin: 0 auto 15px auto;
  }
}

```

---

### 📄 Fichier : `modules/family-calendar/family-calendar.js`
```javascript
/**
 * family-calendar.js (Version Optimisée - API BDD & Décalage Vendredi)
 */
document.addEventListener("DOMContentLoaded", () => {
  const CONGE_TYPES = ["OFF_CAROLE", "EXTRA_OFF_CAROLE"];
  const GUARDE_TYPES = ["CENTRE", "AVIS"];
  const PEP_TYPES = ["PEP_SICK"];
  const FAMILY = {
    ALEX: { id: 2, prefix: "alex" },
    LAIA: { id: 3, prefix: "laia" },
  };
  const LEAVES_CONFIG = {
    CP: {
      startMonth: 8, // Le cycle commence en août (après la tolérance de juillet)
      defaultBalance: 25,
    },
    JRA: {
      // Tu pourras ajouter les années suivantes ici
      yearlyTotals: {
        2024: 10, // ex: 0.83 * 12 arrondi
        2025: 10,
        2026: 11, // ex: 0.9 * 12 arrondi
      },
      defaultBalance: 10,
      toleranceMonths: 2, // Janvier et Février
      maxReport: 2,
    },
    JA: {
      [FAMILY.ALEX.id]: { startMonth: 4, startDay: 29, defaultBalance: 4 },
      [FAMILY.LAIA.id]: { startMonth: 10, startDay: 1, defaultBalance: 4 }, // Date de Laia à adapter
    },
  };

  class FamilyCalendar {
    constructor() {
      this.planningBody = document.getElementById("planningBody");
      this.selectionMenu = document.getElementById("selectionMenu");
      this.schoolHolidaysTableBody = document.querySelector(
        "#schoolHolidaysTable tbody",
      );
      this.monthCalendar = document.getElementById("fc-month-calendar");
      this.monthSelectionMenu = document.getElementById(
        "fc-month-selectionMenu",
      );

      if (
        this.selectionMenu &&
        this.selectionMenu.parentElement !== document.body
      ) {
        document.body.appendChild(this.selectionMenu);
      }
      if (
        this.monthSelectionMenu &&
        this.monthSelectionMenu.parentElement !== document.body
      ) {
        document.body.appendChild(this.monthSelectionMenu);
      }

      this.currentMonth = new Date();
      this.currentMonth.setDate(1);
      this.viewMode = "1month";
      this.currentSchoolYearStart = null;
      this.modalSelectedYear = null; // Pour la modale des vacances

      this.isSelecting = false;
      this.selectedCells = [];
      this.monthSelectedCells = [];
      this._currentBulkInfo = null;

      this.dbEvents = [];
      this.fixedEvents = []; // Fériés statiques
      this.events = [];
      this.leaves = [];
      this.weeks = [];
      this.monthlyLeaveBalances = {
        2: { CP: {}, JRA: {}, JA: {} },
        3: { CP: {}, JRA: {}, JA: {} },
      };

      if (!this.planningBody) return;

      this.init();
    }

    getLocalIsoDate(dateObj) {
      const y = dateObj.getFullYear();
      const m = String(dateObj.getMonth() + 1).padStart(2, "0");
      const d = String(dateObj.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    }

    async init() {
      this.setupEventListeners();
      const now = new Date();
      this.currentSchoolYearStart =
        now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;
      this.modalSelectedYear = this.currentSchoolYearStart;

      this.setupModalUI(); // Prépare le selecteur d'année dans la modale
      await this.refreshAllData();
      this.updateSchoolYearLabel();

      setTimeout(() => this.scrollToCurrentMonth(), 100);
    }

    // Modifie dynamiquement le titre de la modale pour y insérer le selecteur d'année
    setupModalUI() {
      const headerH2 = document.querySelector(".fc-modal-header h2");
      if (headerH2 && !document.getElementById("holidayYearSelect")) {
        headerH2.innerHTML = `${tr("fc_modal_holidays_title")} 
          <select id="holidayYearSelect" style="margin-left:15px; font-size:1rem; padding:4px; border-radius:4px; border:1px solid #cbd5e1;">
            <option value="${this.currentSchoolYearStart - 1}">${this.currentSchoolYearStart - 1} - ${this.currentSchoolYearStart}</option>
            <option value="${this.currentSchoolYearStart}" selected>${this.currentSchoolYearStart} - ${this.currentSchoolYearStart + 1}</option>
            <option value="${this.currentSchoolYearStart + 1}">${this.currentSchoolYearStart + 1} - ${this.currentSchoolYearStart + 2}</option>
            <option value="${this.currentSchoolYearStart + 2}">${this.currentSchoolYearStart + 2} - ${this.currentSchoolYearStart + 3}</option>
          </select>`;

        document
          .getElementById("holidayYearSelect")
          .addEventListener("change", (e) => {
            this.modalSelectedYear = parseInt(e.target.value);
            this.renderModalHolidays();
          });
      }
    }

    async refreshAllData() {
      try {
        const weeksData = await this.fetchApi(
          `/modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php?school_year_start=${this.currentSchoolYearStart}`,
        );
        this.weeks = this.processWeeks(weeksData.weeks || []);

        const eventsData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-events.php",
        );
        this.dbEvents = (eventsData.events || []).map((e) => ({
          ...e,
          duration: parseFloat(e.duration),
        }));

        this.fixedEvents = await this.fetchPublicHolidays();

        const leavesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leaves.php",
        );
        this.leaves = leavesData.leaves || [];

        const balancesData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-balances.php",
        );
        this.leaveBalances = balancesData.balances || [];

        // --- NOUVEAU : Chargement des correctifs de congés ---
        const snapshotsData = await this.fetchApi(
          "/modules/family-calendar/includes/api/get-leave-snapshots.php",
        );
        this.leaveSnapshots = snapshotsData.snapshots || [];

        this.events = [...this.dbEvents, ...this.fixedEvents];
        this.publicHolidayDates = new Set(
          this.fixedEvents
            .filter((e) => e.type === "PUBLIC_HOLIDAY")
            .map((e) => e.date),
        );

        this.reprocessAndRender();
        this.renderModalHolidays();
      } catch (e) {
        console.error("Erreur chargement données:", e);
      }
    }

    // Les jours fériés restent hardcodés car ils sont fixes.
    async fetchPublicHolidays() {
      try {
        // Appel à l'API officielle des jours fériés en métropole
        const res = await fetch(
          "https://calendrier.api.gouv.fr/jours-feries/metropole.json",
        );
        const holidays = await res.json();

        // L'API renvoie un objet : { "2024-01-01": "Jour de l'an", ... }
        // On le transforme en tableau compatible avec ton système d'événements
        return Object.keys(holidays).map((date, idx) => ({
          id: `ph-${idx}`,
          date: date,
          name: holidays[date], // On garde le nom au cas où tu veuilles l'afficher plus tard
          type: "PUBLIC_HOLIDAY",
          duration: 1,
        }));
      } catch (error) {
        console.error(
          "Erreur lors de la récupération des jours fériés :",
          error,
        );
        return []; // Évite de casser le calendrier si l'API de l'État est indisponible
      }
    }

    // --- RECONSTRUCTION DE LA MODALE VIA LA BDD (Par blocs consécutifs) ---
    renderModalHolidays() {
      if (!this.schoolHolidaysTableBody) return;

      // Filtrer les événements de type VACANCES_SCOLAIRES pour l'année scolaire sélectionnée
      const startDate = `${this.modalSelectedYear}-09-01`;
      const endDate = `${this.modalSelectedYear + 1}-08-31`;

      const yearHolidays = this.events.filter(
        (e) =>
          e.type === "VACANCES_SCOLAIRES" &&
          e.date >= startDate &&
          e.date <= endDate,
      );

      // S'il n'y a pas de vacances en base pour cette année, on affiche le bouton "Générer"
      if (yearHolidays.length === 0) {
        this.schoolHolidaysTableBody.innerHTML = `
          <tr>
            <td colspan="3" style="text-align:center; padding: 30px;">
              <p style="color:#64748b; margin-bottom:15px;">Les vacances de cette année ne sont pas encore enregistrées.</p>
              <button id="btnFetchGovHolidays" class="pf-btn">Importer depuis l'API Gouvernement</button>
            </td>
          </tr>
        `;

        document
          .getElementById("btnFetchGovHolidays")
          .addEventListener("click", (e) => {
            e.target.innerText = "Téléchargement en cours...";
            e.target.disabled = true;
            this.fetchAndSaveGovHolidays(this.modalSelectedYear);
          });
        return;
      }

      // 1. Tri chronologique strict des jours
      yearHolidays.sort((a, b) => new Date(a.date) - new Date(b.date));

      // 2. Regroupement par blocs de jours consécutifs
      const blocks = [];
      let currentBlock = null;

      yearHolidays.forEach((e) => {
        const d = new Date(e.date + "T00:00:00"); // Force locale

        if (!currentBlock) {
          currentBlock = { start: d, end: d };
          blocks.push(currentBlock);
        } else {
          // Calcul de l'écart en jours entre la date actuelle et la fin du bloc en cours
          const diffTime = d.getTime() - currentBlock.end.getTime();
          const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

          // Si l'écart est minime (<= 4 jours, pour absorber un éventuel week-end non stocké)
          // on considère qu'on est toujours dans la même période de vacances.
          if (diffDays <= 4) {
            currentBlock.end = d;
          } else {
            // Sinon, l'écart est grand : c'est une NOUVELLE période de vacances
            currentBlock = { start: d, end: d };
            blocks.push(currentBlock);
          }
        }
      });

      // 3. Rendu HTML et déduction des noms
      let html = "";
      blocks.forEach((block) => {
        // Déduction intelligente du nom selon le mois de départ ET la durée
        const m = block.start.getMonth() + 1;
        const durationDays =
          Math.round(
            (block.end.getTime() - block.start.getTime()) /
              (1000 * 60 * 60 * 24),
          ) + 1;

        let name = tr("leg_school_holidays");

        if (m === 10 || m === 11) {
          name = tr("vac_toussaint");
        } else if (m === 12 || m === 1) {
          name = tr("vac_noel");
        } else if (m === 2 || m === 3) {
          name = tr("vac_hiver");
        } else if (m === 4 || (m === 5 && durationDays > 6)) {
          name = tr("vac_printemps");
        } else if (m === 5 && durationDays <= 6) {
          name = tr("vac_ascension");
        } else if (m === 7 || m === 8) {
          name = tr("vac_ete");
        }

        html += `
          <tr>
            <td><strong>${name}</strong></td>
            <td>${block.start.toLocaleDateString("fr-FR")}</td>
            <td>${block.end.toLocaleDateString("fr-FR")}</td>
          </tr>
        `;
      });

      this.schoolHolidaysTableBody.innerHTML = html;
    }

    // --- IMPORTATION DEPUIS L'API & SAUVEGARDE EN BDD ---
    async fetchAndSaveGovHolidays(yearStart) {
      try {
        const yearStr = `${yearStart}-${yearStart + 1}`;
        const url = `https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=annee_scolaire='${yearStr}' AND zones LIKE '%Zone C%'&limit=100`;

        const res = await fetch(url);
        const data = await res.json();
        const rawRecords = data.results || [];

        // Dédoublonnage
        const uniqueMap = new Map();
        rawRecords.forEach((r) => {
          const key = `${r.description}|${r.start_date}`;
          if (!uniqueMap.has(key)) uniqueMap.set(key, r);
        });

        const payload = [];

        Array.from(uniqueMap.values()).forEach((r) => {
          const startDateStr = r.start_date.split("T")[0];
          const endDateStr = r.end_date.split("T")[0];

          let curr = new Date(startDateStr + "T00:00:00");
          const end = new Date(endDateStr + "T00:00:00");

          // REGLE METIER : Si le 1er jour est un VENDREDI (jour=5), on décale le début au SAMEDI.
          if (curr.getDay() === 5) {
            curr.setDate(curr.getDate() + 1);
          }

          while (curr < end) {
            const iso = this.getLocalIsoDate(curr);
            payload.push({
              date: iso,
              type: "VACANCES_SCOLAIRES",
              duration: 1,
              person: r.description, // ASTUCE: On stocke le nom de la vacance ici !
            });
            curr.setDate(curr.getDate() + 1);
          }
        });

        if (payload.length > 0) {
          // On envoie le gros lot à la base de données via l'API existante
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            payload,
          );
          await this.refreshAllData(); // Recharge tout, ce qui mettra à jour la modale
        } else {
          alert(
            "Aucune donnée trouvée sur l'API du gouvernement pour cette année.",
          );
          document.getElementById("btnFetchGovHolidays").innerText =
            "Réessayer";
          document.getElementById("btnFetchGovHolidays").disabled = false;
        }
      } catch (e) {
        console.error("Erreur API", e);
        alert("Erreur lors de la connexion à l'API gouvernementale.");
      }
    }

    // ================================================================
    // LE RESTE DU CODE (AFFICHAGE) RESTE INCHANGÉ MAIS OPTIMISÉ
    // ================================================================

    reprocessAndRender() {
      this.reprocessEvents();
      this.calculateMonthlyBalances();
      this.initSummaryControls();
      this.renderTable();
      this.renderMonthCalendar();
    }

    reprocessEvents() {
      this.weeks.forEach((w) => {
        Object.keys(w.totals).forEach((k) => (w.totals[k] = 0));
        Object.values(w.dayFlags).forEach((f) => (f.events = []));

        this.events.forEach((e) => {
          const d = new Date(e.date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dayKey = Object.keys(w.dayDates).find(
              (k) => w.dayDates[k].getTime() === d.getTime(),
            );
            if (dayKey) w.dayFlags[dayKey].events.push(e);

            const dur = parseFloat(e.duration) || 1;
            const typeMap = {
              OFF_CAROLE: "offCarole",
              EXTRA_OFF_CAROLE: "extraOffCarole",
              CENTRE: "centre",
              AVIS: "avis",
              PEP_SICK: "pepSick",
            };
            if (typeMap[e.type]) w.totals[typeMap[e.type]] += dur;
          }
        });

        this.leaves.forEach((l) => {
          const d = new Date(l.leave_date + "T00:00:00");
          if (d >= w.dayDates.mon && d <= w.dayDates.fri) {
            const dur = parseFloat(l.duration) || 1;
            const prefix =
              l.person_id === FAMILY.ALEX.id
                ? FAMILY.ALEX.prefix
                : l.person_id === FAMILY.LAIA.id
                  ? FAMILY.LAIA.prefix
                  : null;
            if (prefix) w.totals[`${prefix}${l.leave_type}`] += dur;
          }
        });

        let workingDays = 0;
        Object.values(w.dayDates).forEach((d) => {
          if (!this.publicHolidayDates.has(this.getLocalIsoDate(d)))
            workingDays++;
        });
        w.totals.presencePep = Math.max(
          0,
          workingDays -
            (w.totals.offCarole + w.totals.extraOffCarole + w.totals.pepSick),
        );
      });
    }

    calculateMonthlyBalances() {
      const balances = {
        [FAMILY.ALEX.id]: { CP: {}, JRA: {}, JA: {} },
        [FAMILY.LAIA.id]: { CP: {}, JRA: {}, JA: {} },
      };

      // Liste des mois actuellement affichés dans le planning
      const ymSet = new Set();
      this.weeks.forEach((w) => ymSet.add(w.monthKey));
      const ymList = Array.from(ymSet).sort();

      // Pré-calcul de l'utilisation par mois
      const usageByMonth = {};
      this.leaves.forEach((l) => {
        const pid = l.person_id;
        const type = l.leave_type;
        const ym = l.leave_date.substring(0, 7);

        if (!usageByMonth[pid]) usageByMonth[pid] = {};
        if (!usageByMonth[pid][type]) usageByMonth[pid][type] = {};
        usageByMonth[pid][type][ym] =
          (usageByMonth[pid][type][ym] || 0) + parseFloat(l.duration);
      });

      [FAMILY.ALEX.id, FAMILY.LAIA.id].forEach((pid) => {
        ["CP", "JRA", "JA"].forEach((type) => {
          ymList.forEach((ym) => {
            const [currYear, currMonth] = ym.split("-").map(Number);

            let cycleStartStr = "";
            let initialBalance = 0;

            // --- 1. RECHERCHE D'UN CORRECTIF MANUEL (SNAPSHOT) ---
            // On cherche le snapshot le plus récent qui est inférieur ou égal au mois en cours de calcul
            const latestSnapshot = (this.leaveSnapshots || [])
              .filter(
                (s) =>
                  s.person_id == pid &&
                  s.leave_type == type &&
                  s.snapshot_date.substring(0, 7) <= ym,
              )
              // On trie du plus récent au plus ancien pour prendre le premier
              .sort((a, b) =>
                b.snapshot_date.localeCompare(a.snapshot_date),
              )[0];

            if (latestSnapshot) {
              // Si on trouve un correctif, il devient notre nouveau "point zéro"
              cycleStartStr = latestSnapshot.snapshot_date.substring(0, 7);
              initialBalance = parseFloat(latestSnapshot.remaining_balance); // Utilisation de VOTRE nom de colonne
            }
            // --- 2. SINON, CALCUL CLASSIQUE PAR DÉFAUT ---
            else {
              if (type === "CP") {
                const refYear =
                  currMonth >= LEAVES_CONFIG.CP.startMonth
                    ? currYear
                    : currYear - 1;
                cycleStartStr = `${refYear}-${String(LEAVES_CONFIG.CP.startMonth).padStart(2, "0")}`;
                const dbBal = this.leaveBalances.find(
                  (b) =>
                    b.person_id == pid &&
                    b.leave_type == "CP" &&
                    b.balance_year == refYear,
                );
                initialBalance = dbBal
                  ? parseFloat(dbBal.initial_balance)
                  : LEAVES_CONFIG.CP.defaultBalance;
              } else if (type === "JRA") {
                cycleStartStr = `${currYear}-01`;
                initialBalance =
                  LEAVES_CONFIG.JRA.yearlyTotals[currYear] ||
                  LEAVES_CONFIG.JRA.defaultBalance;

                // Logique de report du reliquat de l'année N-1
                if (currMonth <= LEAVES_CONFIG.JRA.toleranceMonths) {
                  const prevYear = currYear - 1;
                  const prevInitial =
                    LEAVES_CONFIG.JRA.yearlyTotals[prevYear] ||
                    LEAVES_CONFIG.JRA.defaultBalance;

                  let usedPrevYear = 0;
                  for (let m = 1; m <= 12; m++) {
                    const mStr = `${prevYear}-${String(m).padStart(2, "0")}`;
                    usedPrevYear += usageByMonth[pid]?.[type]?.[mStr] || 0;
                  }

                  const remainingPrevYear = Math.max(
                    0,
                    prevInitial - usedPrevYear,
                  );
                  // Ajout du report plafonné à 2 jours
                  initialBalance += Math.min(
                    remainingPrevYear,
                    LEAVES_CONFIG.JRA.maxReport,
                  );
                  console.log(
                    `[JRA] Personne ${pid}, Année ${currYear}: Report de ${Math.min(remainingPrevYear, LEAVES_CONFIG.JRA.maxReport)}j inclus.`,
                  );
                }
              } else if (type === "JA") {
                const configJA = LEAVES_CONFIG.JA[pid];
                const isPastAnniversary =
                  currMonth > configJA.startMonth ||
                  (currMonth === configJA.startMonth &&
                    configJA.startDay === 1);
                const refYear = isPastAnniversary ? currYear : currYear - 1;
                cycleStartStr = `${refYear}-${String(configJA.startMonth).padStart(2, "0")}`;
                const dbBal = this.leaveBalances.find(
                  (b) =>
                    b.person_id == pid &&
                    b.leave_type == "JA" &&
                    b.balance_year == refYear,
                );
                initialBalance = dbBal
                  ? parseFloat(dbBal.initial_balance)
                  : configJA.defaultBalance;
              }
            }

            // --- 3. DÉDUCTION DES CONGÉS PRIS DEPUIS LE POINT ZÉRO ---
            let usedBeforeCurrentMonth = 0;

            Object.keys(usageByMonth[pid]?.[type] || {}).forEach((usedYm) => {
              // On ne déduit que ce qui a été posé entre le début du cycle (ou la date du snapshot) et le mois en cours
              if (usedYm >= cycleStartStr && usedYm < ym) {
                usedBeforeCurrentMonth += usageByMonth[pid][type][usedYm];
              }
            });

            const available = Math.max(
              0,
              initialBalance - usedBeforeCurrentMonth,
            );
            const usedInMonth = usageByMonth[pid]?.[type]?.[ym] || 0;

            balances[pid][type][ym] = {
              availableAtMonthStart: available,
              usedInMonth: usedInMonth,
            };
          });
        });
      });

      this.monthlyLeaveBalances = balances;
    }

    renderTable() {
      if (!this.planningBody) return;
      this.planningBody.innerHTML = "";
      const monthSpans = this.weeks.reduce((acc, w) => {
        acc[w.monthKey] = (acc[w.monthKey] || 0) + 1;
        return acc;
      }, {});
      const processedMonths = {};
      const processedLeavesCols = {};
      const fmt = (n) =>
        n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "";

      this.weeks.forEach((w, idx) => {
        const tr = document.createElement("tr");
        tr.setAttribute("data-month", w.monthKey);
        if (idx === 0 || this.weeks[idx - 1].monthKey !== w.monthKey)
          tr.classList.add("fc-month-first-week-row");
        if (
          idx === this.weeks.length - 1 ||
          this.weeks[idx + 1].monthKey !== w.monthKey
        )
          tr.classList.add("fc-month-last-week-row");

        if (!processedMonths[w.monthKey]) {
          processedMonths[w.monthKey] = true;
          const td = document.createElement("td");
          td.className = "col-month col-sticky-mois";
          td.innerHTML = `<span class="fc-sticky-mois-label">${w.monthName}</span>`;
          td.rowSpan = monthSpans[w.monthKey];
          tr.appendChild(td);
        }

        const tdW = document.createElement("td");
        tdW.className = "col-month col-sticky-sem";
        tdW.textContent = w.weekLabel;
        tr.appendChild(tdW);

        ["mon", "tue", "wed", "thu", "fri"].forEach((d) => {
          const td = document.createElement("td");
          const dateObj = w.dayDates[d];
          const iso = this.getLocalIsoDate(dateObj);
          td.dataset.date = iso;
          td.textContent = String(dateObj.getDate()).padStart(2, "0");
          td.className = "col-day";

          w.dayFlags[d].events.forEach((evt) => {
            if (evt.type === "OFF_CAROLE")
              td.classList.add("fc-day--off-carole");
            if (evt.type === "EXTRA_OFF_CAROLE")
              td.classList.add("fc-day--extra-off-carole");
            if (evt.type === "PUBLIC_HOLIDAY")
              td.classList.add("fc-day--public-holiday");
            if (evt.type === "VACANCES_SCOLAIRES")
              td.classList.add("fc-day--school-holiday");
            if (evt.type === "CENTRE") td.classList.add("fc-day--centre");
            if (evt.type === "AVIS") td.classList.add("fc-day--avis");
            if (evt.type === "PEP_SICK")
              td.innerHTML += `<span class="fc-pep-sick-emoji">🤒</span>`;
          });

          const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
          if (dayLeaves.length) {
            let html = `<div style="position:absolute; bottom:0; left:0; width:100%; font-size:9px; line-height:1; display:flex; justify-content:center; gap:2px; pointer-events:none;">`;
            if (dayLeaves.some((l) => l.person_id === window.CONFIG.ID_ALEX))
              html += `<span style="color:#0f766e; font-weight:800;">A</span>`;
            if (dayLeaves.some((l) => l.person_id === window.CONFIG.ID_LAIA))
              html += `<span style="color:#b45309; font-weight:800;">L</span>`;
            html += `</div>`;
            td.innerHTML += html;
          }
          tr.appendChild(td);
        });

        [
          "offCarole",
          "extraOffCarole",
          "centre",
          "avis",
          "pepSick",
          "presencePep",
        ].forEach((k) => {
          const td = document.createElement("td");
          td.className = "col-total";
          td.textContent = fmt(w.totals[k]);
          tr.appendChild(td);
        });

        if (!processedLeavesCols[w.monthKey]) {
          processedLeavesCols[w.monthKey] = true;
          const span = monthSpans[w.monthKey];
          const ym = w.monthKey;
          const renderPersonCols = (pid, prefix) => {
            ["CP", "JRA", "JA"].forEach((type) => {
              const info = this.monthlyLeaveBalances[pid][type][ym];
              const tdAv = document.createElement("td");
              tdAv.className = `${prefix}-sub ${prefix}-av`;
              tdAv.rowSpan = span;
              tdAv.textContent = info ? fmt(info.availableAtMonthStart) : "-";
              tr.appendChild(tdAv);
              const tdUse = document.createElement("td");
              tdUse.className = `${prefix}-sub ${prefix}-use`;
              tdUse.rowSpan = span;
              tdUse.textContent = info ? fmt(info.usedInMonth) : "";
              tr.appendChild(tdUse);
            });
          };
          renderPersonCols(FAMILY.ALEX.id, `col-${FAMILY.ALEX.prefix}`);
          renderPersonCols(FAMILY.LAIA.id, `col-${FAMILY.LAIA.prefix}`);
        }
        this.planningBody.appendChild(tr);
      });
    }

    // --- Auto-scroll vers le mois en cours ---
    scrollToCurrentMonth() {
      const wrapper = document.getElementById("planningTable-wrapper");
      const thead = document.querySelector("#planningTable thead");
      if (!wrapper || !thead) return;

      const now = new Date();
      // Construit la clé au format "YYYY-MM"
      const currentYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;

      // Cherche la TOUTE PREMIÈRE ligne qui correspond à ce mois
      const targetRow = document.querySelector(
        `#planningTable tbody tr[data-month="${currentYm}"]`,
      );

      if (targetRow) {
        // On donne 50ms au navigateur pour finir son rendu graphique avant de calculer les hauteurs
        setTimeout(() => {
          const scrollPos = targetRow.offsetTop - thead.offsetHeight;
          wrapper.scrollTo({
            top: scrollPos > 0 ? scrollPos : 0,
            behavior: "smooth",
          });
        }, 50);
      }
    }

    renderMonthCalendar() {
      if (!this.monthCalendar) return;
      this.monthCalendar.innerHTML = "";

      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const lang = window.I18N_LANG || "fr-FR";
      const titleEl = document.querySelector("#fc-current-month-year");

      if (titleEl) {
        if (this.viewMode === "3months") {
          const nextM2 = new Date(y, m + 2, 1);
          const m1 = new Intl.DateTimeFormat(lang, { month: "short" }).format(
            this.currentMonth,
          );
          const m2 = new Intl.DateTimeFormat(lang, {
            month: "short",
            year: "numeric",
          }).format(nextM2);
          titleEl.textContent = `${m1} - ${m2}`;
          this.renderThreeMonthsView();
        } else if (this.viewMode === "2months") {
          const nextM = new Date(y, m + 1, 1);
          const m1 = new Intl.DateTimeFormat(lang, { month: "short" }).format(
            this.currentMonth,
          );
          const m2 = new Intl.DateTimeFormat(lang, {
            month: "short",
            year: "numeric",
          }).format(nextM);
          titleEl.textContent = `${m1} - ${m2}`;
          this.renderTwoMonthsView();
        } else {
          titleEl.textContent = new Intl.DateTimeFormat(lang, {
            month: "long",
            year: "numeric",
          }).format(this.currentMonth);
          this.monthCalendar.innerHTML = this.generateMonthHTML(y, m);
        }
      }

      this.renderMonthBalances();
      this.syncSummaryWithMonth();
    }

    renderTwoMonthsView() {
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const nextDate = new Date(y, m + 1, 1);
      const lang = window.I18N_LANG || "fr-FR";

      let html = `<div class="fc-two-months-container">`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(this.currentMonth)}</div>${this.generateMonthHTML(y, m)}</div>`;
      html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat(lang, { month: "long" }).format(nextDate)}</div>${this.generateMonthHTML(nextDate.getFullYear(), nextDate.getMonth())}</div>`;
      html += `</div>`;

      this.monthCalendar.innerHTML = html;
    }

    renderThreeMonthsView() {
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();
      const d1 = this.currentMonth;
      const d2 = new Date(y, m + 1, 1);
      const d3 = new Date(y, m + 2, 1);

      let html = `<div class="fc-three-months-container">`;
      [d1, d2, d3].forEach((d) => {
        html += `<div class="fc-month-container"><div class="fc-month-title">${new Intl.DateTimeFormat("fr-FR", { month: "long" }).format(d)}</div>${this.generateMonthHTML(d.getFullYear(), d.getMonth())}</div>`;
      });
      html += `</div>`;
      this.monthCalendar.innerHTML = html;
    }

    renderMonthBalances() {
      const container = document.getElementById("fc-month-balances");
      if (!container) return;

      const monthsToDisplay = [];
      const y = this.currentMonth.getFullYear();
      const m = this.currentMonth.getMonth();

      let numMonths =
        this.viewMode === "2months" ? 2 : this.viewMode === "3months" ? 3 : 1;

      for (let i = 0; i < numMonths; i++) {
        const d = new Date(y, m + i, 1);
        monthsToDisplay.push(
          `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
        );
      }

      container.style.display = "flex";
      let html = "";

      [FAMILY.ALEX, FAMILY.LAIA].forEach((person) => {
        html += `<div class="fc-minimal-balance-card">
                  <strong class="${person.prefix}">${person.prefix.toUpperCase()}</strong>
                  <div class="fc-minimal-chips">`;

        ["CP", "JRA", "JA"].forEach((type) => {
          const startInfo =
            this.monthlyLeaveBalances[person.id]?.[type]?.[monthsToDisplay[0]];
          const startBal = startInfo ? startInfo.availableAtMonthStart : 0;

          let totalUsed = 0;
          monthsToDisplay.forEach((ym) => {
            const info = this.monthlyLeaveBalances[person.id]?.[type]?.[ym];
            if (info) totalUsed += info.usedInMonth;
          });

          const endBal = Math.max(0, startBal - totalUsed);
          const fmt = (n) =>
            n > 0 ? (Number.isInteger(n) ? n : n.toFixed(1)) : "0";

          const usedHtml =
            totalUsed > 0
              ? `<span class="fc-used-badge" title="Posé sur la période">-${fmt(totalUsed)}</span>`
              : "";

          // --- LOGIQUE D'ALERTE FIRE 🔥 ---
          let alertHtml = "";
          const currentYm = monthsToDisplay[0];
          const [cYear, cMonth] = currentYm.split("-").map(Number);

          if (endBal > 0) {
            if (type === "CP" && cMonth >= 6 && cMonth <= 7) {
              // CP : Alerte en Juin et Juillet uniquement
              let msg = (
                window.I18N["fc_alert_burn_days"] || "Perte: %s j avant le %s"
              )
                .replace("%s", fmt(endBal))
                .replace("%s", "31/07");
              alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥</div>`;
            } else if (type === "JRA" && (cMonth === 1 || cMonth === 2)) {
              // JRA : Alerte Janvier/Février si > 2 jours
              if (endBal > 2) {
                let msg =
                  window.I18N["fc_alert_burn_jra"] || "Seuls 2j reportables";
                alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥 ${fmt(endBal - 2)}</div>`;
              }
            } else if (type === "JA") {
              const configJA = LEAVES_CONFIG.JA[person.id];
              let limitMonth = configJA.startMonth - 1 || 12;
              if (cMonth === limitMonth || cMonth === configJA.startMonth) {
                let msg = (window.I18N["fc_alert_burn_days"] || "Perte: %s j")
                  .replace("%s", fmt(endBal))
                  .replace("%s", window.I18N["ANNIV"] || "Anniv");
                alertHtml = `<div class="fc-burn-alert" title="${msg}">🔥</div>`;
              }
            }
          }

          html += `<div class="fc-min-chip" title="Solde départ: ${fmt(startBal)}">
                      <span class="type">${type}</span>
                      <span class="val" title="Restant">${fmt(endBal)}</span>
                      ${usedHtml}
                      ${alertHtml}
                   </div>`;
        });
        html += `</div></div>`;
      });
      container.innerHTML = html;
    }

    syncSummaryWithMonth() {
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (typeSelect && valueSelect) {
        // Le récap se synchronise avec le PREMIER mois affiché de la période
        const ym = `${this.currentMonth.getFullYear()}-${String(this.currentMonth.getMonth() + 1).padStart(2, "0")}`;

        if (typeSelect.value !== "month") {
          typeSelect.value = "month";
          typeSelect.dispatchEvent(new Event("change"));
        }
        valueSelect.value = ym;
        this.updateGlobalSummary();
      }
    }

    generateMonthHTML(year, month) {
      let html = `<table class="fc-month-table"><thead><tr>`;
      ["L", "M", "M", "J", "V"].forEach((d) => (html += `<th>${d}</th>`));
      html += `</tr></thead><tbody><tr>`;

      const daysInMonth = new Date(year, month + 1, 0).getDate();
      let currentRenderedCols = 0;

      const firstDay = new Date(year, month, 1);
      let startDay = (firstDay.getDay() + 6) % 7;

      if (startDay < 5) {
        for (let i = 0; i < startDay; i++) {
          html += `<td class="fc-day--other-month"></td>`;
          currentRenderedCols++;
        }
      }

      for (let dayCounter = 1; dayCounter <= daysInMonth; dayCounter++) {
        const dateObj = new Date(year, month, dayCounter);
        const dayOfWeek = dateObj.getDay();

        if (dayOfWeek === 0 || dayOfWeek === 6) continue;

        if (currentRenderedCols === 5) {
          html += `</tr><tr>`;
          currentRenderedCols = 0;
        }

        const iso = this.getLocalIsoDate(dateObj);
        let cls = "fc-month-day";
        const dayEvts = this.events.filter((e) => e.date === iso);

        if (dayEvts.some((e) => e.type === "VACANCES_SCOLAIRES"))
          cls += " fc-day--school-holiday";
        if (dayEvts.some((e) => e.type === "PUBLIC_HOLIDAY"))
          cls += " fc-day--public-holiday";
        if (dayEvts.some((e) => e.type === "OFF_CAROLE"))
          cls += " fc-day--off-carole";
        if (dayEvts.some((e) => e.type === "EXTRA_OFF_CAROLE"))
          cls += " fc-day--extra-off-carole";
        if (dayEvts.some((e) => ["CENTRE", "AVIS"].includes(e.type))) {
          cls += " fc-day--has-guard";
          if (dayEvts.some((e) => e.type === "CENTRE"))
            cls += " fc-day--centre";
          if (dayEvts.some((e) => e.type === "AVIS")) cls += " fc-day--avis";
        }

        let content = `<div style="position:relative; height:100%;">${dayCounter}`;
        if (dayEvts.some((e) => e.type === "PEP_SICK"))
          content += `<span style="position:absolute; bottom:2px; right:2px;">🤒</span>`;
        const dayLeaves = this.leaves.filter((l) => l.leave_date === iso);
        if (dayLeaves.length) {
          content += `<div style="position:absolute; bottom:2px; left:2px; font-size:10px; font-weight:bold;">`;
          if (dayLeaves.some((l) => l.person_id === 2))
            content += `<span style="color:#0f766e">A</span> `;
          if (dayLeaves.some((l) => l.person_id === 3))
            content += `<span style="color:#b45309">L</span>`;
          content += `</div>`;
        }
        content += `</div>`;
        html += `<td class="${cls}" data-date="${iso}">${content}</td>`;
        currentRenderedCols++;
      }

      while (currentRenderedCols < 5 && currentRenderedCols > 0) {
        html += `<td class="fc-day--other-month"></td>`;
        currentRenderedCols++;
      }

      html += `</tr></tbody></table>`;
      return html;
    }

    initSummaryControls() {
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");
      if (!typeSelect || !valueSelect) return;

      const years = new Set();
      const months = new Set();

      this.weeks.forEach((w) => {
        Object.values(w.dayDates).forEach((d) => {
          years.add(d.getFullYear());
          const mKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
          months.add(mKey);
        });
      });

      const sortedYears = Array.from(years).sort();
      const sortedMonths = Array.from(months).sort();

      const populateValues = () => {
        const currentType = typeSelect.value;
        const previousValue = valueSelect.value;
        valueSelect.innerHTML = "";

        if (currentType === "year") {
          sortedYears.forEach((y) => {
            const opt = document.createElement("option");
            opt.value = y;
            opt.textContent = y;
            valueSelect.appendChild(opt);
          });
          if (sortedYears.includes(parseInt(previousValue))) {
            valueSelect.value = previousValue;
          } else {
            const currentYear = new Date().getFullYear();
            if (sortedYears.includes(currentYear))
              valueSelect.value = currentYear;
          }
        } else {
          sortedMonths.forEach((m) => {
            const [y, mo] = m.split("-");
            const dateObj = new Date(y, mo - 1, 1);
            const label = new Intl.DateTimeFormat("fr-FR", {
              month: "long",
              year: "numeric",
            }).format(dateObj);
            const opt = document.createElement("option");
            opt.value = m;
            opt.textContent = label;
            valueSelect.appendChild(opt);
          });

          const now = new Date();
          const nowIso = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
          if (sortedMonths.includes(nowIso)) {
            valueSelect.value = nowIso;
          }
        }
        this.updateGlobalSummary();
      };

      if (!this.summaryListenersAttached) {
        typeSelect.addEventListener("change", populateValues);
        valueSelect.addEventListener("change", () =>
          this.updateGlobalSummary(),
        );
        this.summaryListenersAttached = true;
      }
      populateValues();
    }

    updateGlobalSummary() {
      const div = document.getElementById("globalSummary");
      const typeSelect = document.getElementById("summType");
      const valueSelect = document.getElementById("summValue");

      if (!div || (typeSelect && valueSelect && !valueSelect.value)) return;

      const filterType = typeSelect ? typeSelect.value : "year";
      const filterValue = valueSelect
        ? valueSelect.value
        : new Date().getFullYear().toString();

      const stats = { off: 0, extra: 0, sick: 0, pep: 0, totalWorking: 0 };

      this.weeks.forEach((w) => {
        Object.entries(w.dayDates).forEach(([dayName, dateObj]) => {
          let match = false;
          if (filterType === "year") {
            if (dateObj.getFullYear().toString() === filterValue) match = true;
          } else {
            const isoMonth = this.getLocalIsoDate(dateObj).slice(0, 7);
            if (isoMonth === filterValue) match = true;
          }

          if (match) {
            const dayEvents = w.dayFlags[dayName].events;
            dayEvents.forEach((e) => {
              const dur = parseFloat(e.duration) || 1;
              if (e.type === "OFF_CAROLE") stats.off += dur;
              if (e.type === "EXTRA_OFF_CAROLE") stats.extra += dur;
              if (e.type === "PEP_SICK") stats.sick += dur;
            });

            const dateIso = this.getLocalIsoDate(dateObj);
            if (!this.publicHolidayDates.has(dateIso)) {
              stats.totalWorking++;
              let dayAbsence = 0;
              dayEvents.forEach((e) => {
                if (
                  ["OFF_CAROLE", "EXTRA_OFF_CAROLE", "PEP_SICK"].includes(
                    e.type,
                  )
                ) {
                  dayAbsence += parseFloat(e.duration) || 1;
                }
              });
              stats.pep += Math.max(0, 1 - dayAbsence);
            }
          }
        });
      });

      div.innerHTML = `
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_off_carole")}</span><span class="fc-summary-value">${parseFloat(stats.off.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_extra_off")}</span><span class="fc-summary-value">${parseFloat(stats.extra.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_pep_sick")}</span><span class="fc-summary-value">${parseFloat(stats.sick.toFixed(1))} ${tr("fc_unit_days")}</span></div>
         <div class="fc-summary-item"><span class="fc-summary-label">${tr("leg_presence")}</span><span class="fc-summary-value">${parseFloat(stats.pep.toFixed(1))} ${tr("fc_unit_days")}</span></div>
       `;
    }

    updateSchoolYearLabel() {
      const lbl = document.getElementById("fc-current-school-year-label");
      if (lbl)
        lbl.textContent = `${this.currentSchoolYearStart} – ${this.currentSchoolYearStart + 1}`;
    }

    processWeeks(rawWeeks) {
      return rawWeeks.map((w) => ({
        id: `${w.week_iso_year}-W${w.week_iso_number}`,
        monthKey: `${w.year}-${String(w.month).padStart(2, "0")}`,
        monthName: w.month_name,
        weekLabel: w.week_label,
        dayDates: {
          mon: new Date(w.mon_date + "T00:00:00"),
          tue: new Date(w.tue_date + "T00:00:00"),
          wed: new Date(w.wed_date + "T00:00:00"),
          thu: new Date(w.thu_date + "T00:00:00"),
          fri: new Date(w.fri_date + "T00:00:00"),
        },
        dayFlags: {
          mon: { events: [] },
          tue: { events: [] },
          wed: { events: [] },
          thu: { events: [] },
          fri: { events: [] },
        },
        totals: {
          offCarole: 0,
          extraOffCarole: 0,
          centre: 0,
          avis: 0,
          pepSick: 0,
          presencePep: 0,
          alexCP: 0,
          alexJRA: 0,
          alexJA: 0,
          laiaCP: 0,
          laiaJRA: 0,
          laiaJA: 0,
        },
      }));
    }

    async fetchApi(url) {
      return pachaFetch(url);
    }

    async postApi(url, data) {
      return pachaFetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });
    }

    async changeSchoolYear(delta) {
      this.currentSchoolYearStart += delta;
      this.updateSchoolYearLabel();
      await this.refreshAllData();
    }

    setupEventListeners() {
      if (this.planningBody) {
        // Souris (PC)
        this.planningBody.addEventListener("mousedown", (e) =>
          this.handleMouseDown(e),
        );
        document.addEventListener("mousemove", (e) => this.handleMouseMove(e));
        document.addEventListener("mouseup", (e) => this.handleMouseUp(e));

        // Tactile (Mobile/Tablette)
        this.planningBody.addEventListener(
          "touchstart",
          (e) => this.handleTouchStart(e),
          { passive: false },
        );
        document.addEventListener("touchmove", (e) => this.handleTouchMove(e), {
          passive: false,
        });
        document.addEventListener("touchend", (e) => this.handleTouchEnd(e));
      }

      // --- GESTION MODALE VACANCES SCOLAIRES ---
      const btnOpen = document.getElementById("btnOpenHolidays");
      const btnClose = document.getElementById("btnCloseHolidays");
      const modal = document.getElementById("modalHolidays");

      if (btnOpen && modal) {
        btnOpen.addEventListener("click", () => {
          modal.classList.add("open"); // Utilisation propre de la classe CSS
          document.body.classList.add("no-scroll");

          const yearSelect = document.getElementById("holidayYearSelect");
          if (yearSelect) yearSelect.value = this.currentSchoolYearStart;
          this.modalSelectedYear = this.currentSchoolYearStart;
          this.renderModalHolidays();
        });
      }
      if (btnClose && modal) {
        btnClose.addEventListener("click", () => {
          modal.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      }
      if (modal) {
        modal.addEventListener("click", (e) => {
          if (e.target === modal) {
            modal.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });
      }

      // --- GESTION MODALE CORRECTIF DES SOLDES (SNAPSHOTS) ---
      const btnSnap = document.getElementById("btnOpenSnapshotModal");
      const modalSnap = document.getElementById("modalSnapshot");
      const btnCloseSnap = document.getElementById("btnCloseSnapshot");
      const formSnap = document.getElementById("formSnapshot");

      if (btnSnap && modalSnap) {
        btnSnap.addEventListener("click", () => {
          modalSnap.classList.add("open"); // On ouvre la modale correctement
          document.body.classList.add("no-scroll");

          // Pré-remplir la date avec le 1er jour du mois en cours
          const today = new Date();
          const y = today.getFullYear();
          const m = String(today.getMonth() + 1).padStart(2, "0");
          const snapDateInput = document.getElementById("snapDate");
          if (snapDateInput) snapDateInput.value = `${y}-${m}-01`;
        });
      }

      if (btnCloseSnap && modalSnap) {
        btnCloseSnap.addEventListener("click", () => {
          modalSnap.classList.remove("open");
          document.body.classList.remove("no-scroll");
        });
      }

      if (modalSnap) {
        modalSnap.addEventListener("click", (e) => {
          if (e.target === modalSnap) {
            modalSnap.classList.remove("open");
            document.body.classList.remove("no-scroll");
          }
        });
      }

      if (formSnap) {
        formSnap.addEventListener("submit", async (e) => {
          e.preventDefault();

          const payload = {
            person_id: document.getElementById("snapPerson").value,
            leave_type: document.getElementById("snapType").value,
            snapshot_date: document.getElementById("snapDate").value,
            remaining_balance: document.getElementById("snapBalance").value,
          };

          try {
            await this.postApi(
              "/modules/family-calendar/includes/api/save-leave-snapshot.php",
              payload,
            );

            modalSnap.classList.remove("open"); // Fermeture propre
            document.body.classList.remove("no-scroll");
            formSnap.reset();

            await this.refreshAllData();
          } catch (error) {
            alert("Erreur lors de la sauvegarde du correctif.");
          }
        });
      }

      // --- GESTION DU RESTE DE L'INTERFACE ---
      if (this.monthCalendar) {
        this.monthCalendar.addEventListener("click", (e) => {
          const td = e.target.closest("td[data-date]");
          if (td && td.dataset.date) {
            this.monthSelectedCells = [td];
            this.showMenu(e.pageX, e.pageY, [td.dataset.date], true);
          }
        });
      }

      document.addEventListener("click", (e) => this.closeMenusIfOutside(e));
      const handleMenu = (e) => {
        const btn = e.target.closest("button");
        if (btn && btn.dataset.action) this.handleMenuAction(btn.dataset);
      };

      if (this.selectionMenu)
        this.selectionMenu.addEventListener("click", handleMenu);
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.addEventListener("click", handleMenu);

      document
        .getElementById("fc-prev-month")
        ?.addEventListener("click", () => {
          this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
          this.renderMonthCalendar();
        });
      document
        .getElementById("fc-next-month")
        ?.addEventListener("click", () => {
          this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
          this.renderMonthCalendar();
        });
      document
        .getElementById("fc-prev-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(-1));
      document
        .getElementById("fc-next-school-year")
        ?.addEventListener("click", () => this.changeSchoolYear(1));

      document.querySelectorAll(".fc-view-button").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          document
            .querySelectorAll(".fc-view-button")
            .forEach((b) => b.classList.remove("fc-view-button--active"));
          e.target.classList.add("fc-view-button--active");
          this.viewMode = e.target.dataset.view;
          this.renderMonthCalendar();
        });
      });
    }

    handleMouseDown(e) {
      const td = e.target.closest("#planningTable td[data-date]");
      if (!td) return;
      e.preventDefault();
      this.clearSelection();
      this.isSelecting = true;
      this.selectCell(td);
    }

    handleMouseMove(e) {
      if (!this.isSelecting) return;
      const td = e.target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
    }

    handleMouseUp(e) {
      if (!this.isSelecting) return;
      const td = e.target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
      this.isSelecting = false;
      if (!this.selectedCells.length) return;
      const dates = this.selectedCells.map((c) => c.dataset.date);
      this.showMenu(e.pageX, e.pageY, dates, false);
    }

    // --- GESTION DU TACTILE (Swipe pour sélectionner) ---
    handleTouchStart(e) {
      const td = e.target.closest("#planningTable td[data-date]");
      if (!td) return;

      // Si l'utilisateur touche une case, on bloque le scroll de la page pour le laisser sélectionner
      e.preventDefault();

      this.clearSelection();
      this.isSelecting = true;
      this.selectCell(td);
    }

    handleTouchMove(e) {
      if (!this.isSelecting) return;
      e.preventDefault(); // Bloque le défilement de l'écran pendant qu'on glisse le doigt

      const touch = e.touches[0];
      // elementFromPoint permet de savoir sur quelle case se trouve le doigt actuellement
      const target = document.elementFromPoint(touch.clientX, touch.clientY);
      if (!target) return;

      const td = target.closest("#planningTable td[data-date]");
      if (td && !this.selectedCells.includes(td)) this.selectCell(td);
    }

    handleTouchEnd(e) {
      if (!this.isSelecting) return;
      this.isSelecting = false;
      if (!this.selectedCells.length) return;

      const dates = this.selectedCells.map((c) => c.dataset.date);
      // Sur mobile, on s'en fiche de X et Y car le menu s'affiche en bas de l'écran
      this.showMenu(0, 0, dates, false);
    }

    selectCell(cell) {
      cell.classList.add("fc-day--selected");
      this.selectedCells.push(cell);
    }

    clearSelection() {
      this.selectedCells.forEach((c) => c.classList.remove("fc-day--selected"));
      this.selectedCells = [];
      if (this.selectionMenu) this.selectionMenu.style.display = "none";
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.style.display = "none";
    }

    closeMenusIfOutside(e) {
      if (
        !this.selectionMenu?.contains(e.target) &&
        !this.monthSelectionMenu?.contains(e.target) &&
        !this.menuJustOpened
      ) {
        this.clearSelection();
      }
    }

    showMenu(x, y, dates, isMonthView) {
      const menu = isMonthView ? this.monthSelectionMenu : this.selectionMenu;
      if (!menu) return;

      this._currentBulkInfo = { dates };

      // Gestion de la date affichée (Singulier/Pluriel)
      const dateLabel =
        dates.length > 1
          ? `${dates.length} ${tr("fc_unit_days")}`
          : new Date(dates[0]).toLocaleDateString(window.I18N_LANG || "fr-FR");

      // Icône SVG Poubelle (Uniformisée)
      const trashSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>`;

      // Fonction utilitaire interne pour les en-têtes de section avec suppression
      const buildHeader = (title, action, cat) => `
        <div class="fc-menu-header">
          <strong>${title}</strong>
          ${action ? `<button class="fc-menu-clear-icon" title="${tr("fc_clear")}" data-action="${action}" ${cat ? `data-cat="${cat}"` : ""}>${trashSvg}</button>` : ""}
        </div>
      `;

      // 1. En-tête du menu (Date ou nombre de jours)
      let html = `<div class="fc-menu-section" style="border-bottom: none; padding-bottom: 0;">
                    <strong style="font-size:0.85rem; color:var(--text-main); margin-bottom: 4px;">${dateLabel}</strong>
                  </div>`;

      // 2. Section Carole (Congés)
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("fc_menu_carole"), "clear-type", "CONGE")}
                 <div class="fc-menu-grid">
                   <button class="fc-menu-btn" data-action="add" data-type="OFF_CAROLE" data-person="Carole">${tr("btn_off")}</button>
                   <button class="fc-menu-btn" data-action="add" data-type="EXTRA_OFF_CAROLE" data-person="Carole">${tr("btn_extra")}</button>
                 </div>
               </div>`;

      // 3. Section Garde (Centre / Avis)
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("leg_centre"), "clear-type", "GARDE")}
                 <div class="fc-menu-grid">
                   <button class="fc-menu-btn" data-action="add" data-type="CENTRE">${tr("leg_centre")}</button>
                   <button class="fc-menu-btn" data-action="add" data-type="AVIS">${tr("leg_avis")}</button>
                 </div>
               </div>`;

      // 4. Section Pep (Maladie)
      html += `<div class="fc-menu-section">
                 ${buildHeader("Pep", "clear-type", "PEP")}
                 <button class="fc-menu-btn" data-action="add" data-type="PEP_SICK" style="width:100%">${tr("leg_pep_sick")} 🤒</button>
               </div>`;

      // 5. Section Enfants (Alex & Laia)
      html += `<div class="fc-menu-section">
                 ${buildHeader(tr("fc_menu_kids_leaves"), null, null)}
                 <div class="fc-menu-leaves-table">
                   <table>
                     <thead>
                       <tr>
                         <th>
                            <div class="fc-th-inline">
                              Alex
                              <button class="fc-menu-clear-icon fc-menu-btn-th" data-action="clear-leaves-person" data-pid="2" title="${tr("fc_clear")} Alex">${trashSvg}</button>
                            </div>
                         </th>
                         <th>
                            <div class="fc-th-inline">
                              Laia
                              <button class="fc-menu-clear-icon fc-menu-btn-th" data-action="clear-leaves-person" data-pid="3" title="${tr("fc_clear")} Laia">${trashSvg}</button>
                            </div>
                         </th>
                       </tr>
                     </thead>
                     <tbody>`;

      ["CP", "JRA", "JA"].forEach((t) => {
        html += `<tr>
                   <td><button class="fc-menu-btn" data-action="add-leave" data-pid="2" data-type="${t}">${t}</button></td>
                   <td><button class="fc-menu-btn" data-action="add-leave" data-pid="3" data-type="${t}">${t}</button></td>
                 </tr>`;
      });

      html += `      </tbody>
                   </table>
                 </div>
               </div>`;

      menu.innerHTML = html;

      // Positionnement (Gestion des bords d'écran)
      const menuWidth = 240;
      let left = x + 10;
      if (left + menuWidth > window.innerWidth) left = x - menuWidth - 10;

      menu.style.left = `${left}px`;
      menu.style.top = `${y + 10}px`;
      menu.style.display = "block";

      this.menuJustOpened = true;
      setTimeout(() => (this.menuJustOpened = false), 100);
    }

    async handleMenuAction(dataset) {
      if (this.selectionMenu) this.selectionMenu.style.display = "none";
      if (this.monthSelectionMenu)
        this.monthSelectionMenu.style.display = "none";

      const { action, type, pid, cat } = dataset;
      const dates = this._currentBulkInfo.dates;

      try {
        if (action === "add") {
          let typesToClear = [];
          if (["OFF_CAROLE", "EXTRA_OFF_CAROLE"].includes(type))
            typesToClear = CONGE_TYPES;
          if (["CENTRE", "AVIS"].includes(type)) typesToClear = GUARDE_TYPES;
          if (type === "PEP_SICK") typesToClear = PEP_TYPES;

          if (typesToClear.length) {
            await this.postApi(
              "/modules/family-calendar/includes/api/manage-event.php",
              { action: "bulk_delete_day_types", dates, types: typesToClear },
            );
          }
          const payload = dates.map((d) => ({
            date: d,
            type: type,
            duration: 1,
            person: "Carole",
          }));
          await this.postApi(
            "/modules/family-calendar/includes/api/save-events.php",
            payload,
          );
        } else if (action === "clear-type") {
          let typesToClear = [];
          if (cat === "CONGE") typesToClear = CONGE_TYPES;
          if (cat === "GARDE") typesToClear = GUARDE_TYPES;
          if (cat === "PEP") typesToClear = PEP_TYPES;

          await this.postApi(
            "/modules/family-calendar/includes/api/manage-event.php",
            { action: "bulk_delete_day_types", dates, types: typesToClear },
          );
        } else if (action === "add-leave") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: parseInt(pid),
            },
          );

          const payload = dates.map((d) => ({
            date: d,
            person_id: parseInt(pid), // On s'assure que c'est bien un format numérique
            leave_type: type,
            duration: 1,
          }));
          await this.postApi(
            "/modules/family-calendar/includes/api/save-leaves.php",
            payload,
          );
        } else if (action === "clear-leaves-person") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: parseInt(pid),
            },
          );
        } else if (action === "clear-leaves") {
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: window.CONFIG.ID_ALEX,
            },
          );
          await this.postApi(
            "/modules/family-calendar/includes/api/manage-leaf.php",
            {
              action: "bulk_delete_day_person",
              dates,
              person_id: window.CONFIG.ID_LAIA,
            },
          );
        }

        await this.refreshAllData();
      } catch (e) {
        alert("Erreur action: " + e.message);
      }
    }
  }

  new FamilyCalendar();
});

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-calendar-weeks-scolaire.php`
```php
<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

/**
 * Paramètre: school_year_start (optionnel)
 * - année de début d'année scolaire (ex: 2025 pour 09/2025 -> 08/2026)
 * - défaut: année courante si non fourni
 */
$schoolYearStart = isset($_GET['school_year_start'])
    ? (int)$_GET['school_year_start']
    : (int)date('Y');

if ($schoolYearStart < 2000 || $schoolYearStart > 2100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Année scolaire invalide.']);
    exit;
}

$yearStart = $schoolYearStart;
$yearEnd   = $schoolYearStart + 1;

// On veut toutes les semaines:
// - de septembre (month >= 9) de yearStart
// - à août (month <= 8) de yearEnd

try {
    $sql = "
        SELECT
          year,
          week_iso_year,
          week_iso_number,
          week_label,
          month,
          month_name,
          week_start_date,
          mon_date,
          tue_date,
          wed_date,
          thu_date,
          fri_date
        FROM pf_calendar_weeks
        WHERE
          (year = :year_start AND month >= 9)
          OR (year = :year_end AND month <= 8)
        ORDER BY week_start_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':year_start' => $yearStart,
        ':year_end'   => $yearEnd,
    ]);

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'        => 'success',
        'school_year'   => $schoolYearStart,
        'weeks'         => $weeks,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-calendar-weeks.php`
```php
<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    $stmt = $pdo->prepare("
        SELECT
          year,
          week_iso_year,
          week_iso_number,
          week_label,
          month,
          month_name,
          week_start_date,
          mon_date,
          tue_date,
          wed_date,
          thu_date,
          fri_date
        FROM pf_calendar_weeks
        WHERE year = :year
        ORDER BY week_start_date ASC
    ");
    $stmt->execute([':year' => $year]);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['weeks' => $weeks]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-events.php`
```php
<?php
header('Content-Type: application/json');

require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, event_date AS date, event_type AS type, person_id, duration FROM pf_events ORDER BY event_date");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'events' => $events,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erreur lors de la récupération des événements : ' . $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-leave-balances.php`
```php
<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT person_id, leave_type, initial_balance, balance_year
        FROM pf_leave_balances
    ");
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['balances' => $balances]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-leave-snapshots.php`
```php
<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT person_id, leave_type, snapshot_date, remaining_balance
        FROM pf_leave_snapshots
        ORDER BY snapshot_date ASC
    ");
    $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['snapshots' => $snapshots]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-leaves.php`
```php
<?php
// api/get-leaves.php
header('Content-Type: application/json');

require __DIR__ . '/../../../../includes/db.php';

try {
    $stmt = $pdo->query("
        SELECT
          l.id,
          l.person_id,
          p.name AS person_name,
          l.leave_type,
          l.leave_date,
          l.duration
        FROM pf_leaves l
        JOIN pf_people p ON p.id = l.person_id
        ORDER BY l.leave_date, l.person_id
    ");

    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'leaves' => $leaves,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/get-person-leave-meta.php`
```php
<?php
// /modules/family-calendar/includes/api/get-person-leave-meta.php

require __DIR__ . '/../../../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("
        SELECT person_id, anniversary_date
        FROM pf_person_leave_meta
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'meta' => $rows,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/manage-event.php`
```php
<?php
// modules/family-calendar/includes/api/manage-event.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

try {
    // --- SUPPRESSION UNITAIRE ---
    if ($action === 'delete') {
        $eventId = (int)($input['event_id'] ?? 0);
        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID manquant.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM pf_events WHERE id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- MISE À JOUR UNITAIRE ---
    if ($action === 'update') {
        $eventId = (int)($input['event_id'] ?? 0);
        $newType = $input['new_type'] ?? '';
        if ($eventId <= 0 || !$newType) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données manquantes.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE pf_events SET event_type = ? WHERE id = ?");
        $stmt->execute([$newType, $eventId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- SUPPRESSION DE MASSE (Par date et type) ---
    // Utilisé quand on ajoute un événement pour nettoyer les doublons potentiels (ex: Off vs Extra)
    if ($action === 'bulk_delete_day_types') {
        $dates = $input['dates'] ?? [];
        $types = $input['types'] ?? [];
        $dates = array_filter($dates, function($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        });

        if (empty($dates) || empty($types)) {
            echo json_encode(['status' => 'success']);
            exit;
        }

        // Création des placeholders IN (?,?,?)
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));

        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders) AND event_type IN ($typePlaceholders)";
        $stmt = $pdo->prepare($sql);
        
        // Fusion des tableaux pour l'exécution
        $stmt->execute(array_merge($dates, $types));
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- SUPPRESSION TOTALE SUR DES DATES ---
    if ($action === 'bulk_delete_all') {
        $dates = $input['dates'] ?? [];
        $dates = array_filter($dates, function($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        });

        if (empty($dates) || empty($types)) {
            echo json_encode(['status' => 'success']);
            exit;
        }
        
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
        $sql = "DELETE FROM pf_events WHERE event_date IN ($datePlaceholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dates);
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Action inconnue
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action non reconnue : ' . $action]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
```

---

### 📄 Fichier : `modules/family-calendar/includes/api/manage-leaf.php`
```php
<?php
// api/manage-leaf.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON invalide.']);
    exit;
}

$action = $input['action'] ?? null;
if (!$action) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action manquante.']);
    exit;
}

try {
    // ===================== DELETE TOUS LES LEAVES D'UN JOUR (EXISTANT) =====================
    if ($action === 'delete_day') {

        if (empty($input['date'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date manquante pour delete_day.']);
            exit;
        }

        $dateObj = date_create($input['date']);
        if (!$dateObj) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date invalide.']);
            exit;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmt = $pdo->prepare("DELETE FROM pf_leaves WHERE leave_date = ?");
        $stmt->execute([$leaveDate]);

        echo json_encode(['status' => 'success', 'message' => 'Congés supprimés pour ce jour.']);
        exit;
    }

    // ===================== DELETE POUR UNE PERSONNE / JOUR (SINGLE) =====================
    if ($action === 'delete_day_person') {
        $date = $input['date'] ?? null;
        $personId = $input['person_id'] ?? null;

        if (empty($date) || empty($personId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'date ou person_id manquant pour delete_day_person.']);
            exit;
        }

        $dateObj = date_create($date);
        if (!$dateObj) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Date invalide pour delete_day_person.']);
            exit;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmt = $pdo->prepare("
            DELETE FROM pf_leaves
            WHERE leave_date = :date
              AND person_id = :person_id
        ");
        $stmt->execute([
            ':date'      => $leaveDate,
            ':person_id' => $personId,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Congés supprimés pour cette personne et ce jour.',
            'deleted' => $stmt->rowCount(),
        ]);
        exit;
    }

    // ===================== BULK DELETE POUR UNE PERSONNE / PLUSIEURS JOURS =====================
    if ($action === 'bulk_delete_day_person') {
        $dates    = $input['dates'] ?? null;
        $personId = $input['person_id'] ?? null;

        if (!is_array($dates) || empty($dates) || empty($personId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'dates ou person_id manquant pour bulk_delete_day_person.']);
            exit;
        }

        // Normaliser les dates en 'Y-m-d'
        $normalizedDates = [];
        foreach ($dates as $d) {
            $dateObj = date_create($d);
            if ($dateObj) {
                $normalizedDates[] = $dateObj->format('Y-m-d');
            }
        }

        if (empty($normalizedDates)) {
            echo json_encode([
                'status'  => 'success',
                'message' => 'Aucune date valide fournie.',
                'deleted' => 0,
            ]);
            exit;
        }

        // Construire le IN (...)
        $placeholders = implode(',', array_fill(0, count($normalizedDates), '?'));
        $sql = "
            DELETE FROM pf_leaves
            WHERE person_id = ?
              AND leave_date IN ($placeholders)
        ";
        $stmt = $pdo->prepare($sql);

        $params = array_merge([$personId], $normalizedDates);
        $stmt->execute($params);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Congés supprimés pour cette personne sur les dates fournies.',
            'deleted' => $stmt->rowCount(),
        ]);
        exit;
    }

    // ===================== ACTION NON RECONNUE =====================
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

```

---

### 📄 Fichier : `modules/family-calendar/includes/api/save-events.php`
```php
<?php
// includes/api/save-events.php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$rawInput = file_get_contents('php://input');
$eventsToSave = json_decode($rawInput, true);

// Optionnel : tu peux commenter/supprimer ces logs en production pour économiser du disque
file_put_contents(
    __DIR__ . '/events-debug.log',
    "[" . date('c') . "] RAW INPUT: " . $rawInput . PHP_EOL,
    FILE_APPEND
);

if (empty($eventsToSave) || !is_array($eventsToSave)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucune donnée d\'événement reçue.']);
    exit;
}

$inserted = [];

try {
    // 1. OPTIMISATION : On récupère toutes les personnes d'un coup (Mapping)
    $stmtPeople = $pdo->query("SELECT id, name FROM pf_people");
    $peopleMap = [];
    while ($row = $stmtPeople->fetch(PDO::FETCH_ASSOC)) {
        // On crée un tableau associatif : ['Carole' => 1, 'Alex' => 2, etc.]
        $peopleMap[$row['name']] = $row['id'];
    }

    $pdo->beginTransaction();

    $sql = "INSERT INTO pf_events (event_date, event_type, person_id, duration)
            VALUES (:event_date, :event_type, :person_id, :duration)";
    $stmt = $pdo->prepare($sql);

    foreach ($eventsToSave as $event) {
        $person_id = null;

        // 2. On vérifie simplement dans notre tableau (plus de requête SQL ici !)
        if (!empty($event['person']) && isset($peopleMap[$event['person']])) {
            $person_id = $peopleMap[$event['person']];
        }

        $stmt->execute([
            ':event_date' => $event['date'],
            ':event_type' => $event['type'],
            ':person_id'  => $person_id,
            ':duration'   => $event['duration'] ?? 1.0,
        ]);

        $inserted[] = [
            'id'        => $pdo->lastInsertId(),
            'date'      => $event['date'],
            'type'      => $event['type'],
            'duration'  => $event['duration'] ?? 1.0,
            'person_id' => $person_id,
        ];
    }

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'inserted' => $inserted,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()]);
}
```

---

### 📄 Fichier : `modules/family-calendar/includes/api/save-leave-snapshot.php`
```php
<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['person_id']) || empty($input['leave_type']) || empty($input['snapshot_date']) || !isset($input['remaining_balance'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données manquantes.']);
    exit;
}

try {
    // Étape 1 : Par sécurité, on supprime un éventuel snapshot existant exactement à la même date pour cette personne et ce type
    $stmtDel = $pdo->prepare("DELETE FROM pf_leave_snapshots WHERE person_id = ? AND leave_type = ? AND snapshot_date = ?");
    $stmtDel->execute([$input['person_id'], $input['leave_type'], $input['snapshot_date']]);

    // Étape 2 : On insère le nouveau solde
    $stmt = $pdo->prepare("INSERT INTO pf_leave_snapshots (person_id, leave_type, snapshot_date, remaining_balance) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $input['person_id'],
        $input['leave_type'],
        $input['snapshot_date'],
        $input['remaining_balance']
    ]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
```

---

### 📄 Fichier : `modules/family-calendar/includes/api/save-leaves.php`
```php
<?php
// api/save-leaves.php
header('Content-Type: application/json');

require __DIR__ . '/../../../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données invalides (attendu tableau JSON).']);
    exit;
}

// Exemple d'élément attendu :
// { date: '2025-09-01', person_id: 2, leave_type: 'CP', duration: 1.0 }

$validLeaveTypes = ['CP', 'JRA', 'JA'];

try {
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare("
        INSERT INTO pf_leaves (person_id, leave_type, leave_date, duration)
        VALUES (:person_id, :leave_type, :leave_date, :duration)
    ");

    foreach ($input as $item) {
        $date      = $item['date']      ?? null;
        $personId  = $item['person_id'] ?? null;
        $leaveType = $item['leave_type'] ?? null;
        $duration  = isset($item['duration']) ? (float)$item['duration'] : 1.0;

        if (!$date || !$personId || !$leaveType) {
            continue; // on ignore les lignes incomplètes
        }

        if (!in_array($leaveType, $validLeaveTypes, true)) {
            continue; // leave_type invalide
        }

        // Normalisation de la date
        $dateObj = date_create($date);
        if (!$dateObj) {
            continue;
        }
        $leaveDate = $dateObj->format('Y-m-d');

        $stmtInsert->execute([
            ':person_id'   => (int)$personId,
            ':leave_type'  => $leaveType,
            ':leave_date'  => $leaveDate,
            ':duration'    => $duration,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Congés enregistrés.',
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

```

---

### 📄 Fichier : `modules/gift-list/gift-list.css`
```css
/* modules/gift-list/gift-list.css */

.pf-gift-list h1 {
  font-size: 1.6rem;
  font-weight: 800;
  margin: 0;
  color: var(--text-main);
}

.cl-titlebar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border-light);
  padding-bottom: 15px;
}

.cl-view-switch {
  display: flex;
  background: white;
  padding: 4px;
  border-radius: 8px;
  border: 1px solid var(--border-light);
  box-shadow: var(--shadow-sm);
}

.cl-view-btn {
  padding: 6px 16px;
  border-radius: 6px;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-muted);
  transition: 0.2s;
}

.cl-view-btn.is-active {
  background: #334155;
  color: white;
}

/* === FILTRES STICKY === */
.pf-filter-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 24px;
  position: sticky;
  top: 64px;
  z-index: 50;
  flex-wrap: wrap;
}

.pf-filter-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 6px;
}

.pf-filter-select {
  padding: 6px 12px;
  border-radius: 20px; /* Forme de pillule */
  border: 1px solid var(--border-light);
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  outline: none;
  font-family: inherit;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  width: auto; /* Empêche de prendre toute la largeur */
  appearance: none; /* Nettoie la flèche système... */
  -webkit-appearance: none;
  -moz-appearance: none;
  /* ...pour mettre une flèche personnalisée plus discrète */
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 12px;
  padding-right: 28px; /* Place pour la flèche */
}

.pf-filter-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

/* === SECTIONS ENFANTS === */
.pf-child-section {
  margin-bottom: 30px;
  background: white;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--border-light);
  box-shadow: var(--shadow-sm);
}
.pf-child-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.pf-child-header h3 {
  margin: 0;
  font-size: 1.3rem;
  color: var(--text-main);
}

/* === BARRE DES TOTAUX (PILLS) === */
.pf-child-totals-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 12px;
  margin-bottom: 15px;
  border-bottom: 1px solid #f1f5f9;
  scrollbar-width: none; /* Firefox */
}
.pf-child-totals-bar::-webkit-scrollbar {
  display: none; /* Chrome/Safari */
}
.pf-summary-pill {
  white-space: nowrap;
  background: #f8fafc;
  padding: 4px 12px;
  border-radius: 50px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  border: 1px solid #cbd5e1;
  transition: opacity 0.2s;
}

/* === FEED DES CARTES CADEAUX === */
.pf-gift-feed {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}
.pf-gift-card-compact {
  background: white;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  padding: 12px;
  position: relative;
  transition: 0.2s;
  display: flex;
  flex-direction: column;
  min-height: 100px;
}
.pf-gift-card-compact:hover {
  border-color: #cbd5e1;
  box-shadow: var(--shadow-md);
}
.pf-gift-title {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.95rem;
  margin: 0 0 10px 0;
  line-height: 1.3;
  word-break: break-word;
}
.pf-gift-link {
  text-decoration: none;
  margin-left: 4px;
}
.pf-gift-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: auto;
}
.pf-gift-price {
  font-weight: 800;
  color: var(--success);
  font-size: 1rem;
}
.pf-gift-badges-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
}
.pf-pill-adult {
  font-size: 0.7rem;
  font-weight: 700;
  background: #e0f2fe;
  color: #0369a1;
  padding: 2px 6px;
  border-radius: 4px;
}
.pf-pill-occ {
  font-size: 0.65rem;
  font-weight: 700;
  background: #fef3c7;
  color: #b45309;
  padding: 2px 6px;
  border-radius: 4px;
}
.pf-gift-payer {
  font-size: 0.7rem;
  color: #ef4444;
  font-weight: 600;
  font-style: italic;
  margin-top: 5px;
}
.pf-gift-actions {
  display: flex;
  gap: 4px;
}

/* === TRICOUNT / LIQUIDATIONS === */
.pf-tricount-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.pf-tricount-item {
  background: white;
  padding: 12px 15px;
  border: 1px solid var(--border-light);
  border-radius: 8px;
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.95rem;
}
.cl-debt-matrix th:first-child,
.cl-debt-matrix td:first-child {
  position: sticky;
  left: 0;
  background: var(--bg-page);
  z-index: 2;
  border-right: 2px solid var(--border-light);
}

/* Icônes des fêtes (Tió, Noël, etc.) plus petites et bien alignées */
.cl-occasion-icon {
  width: 20px; /* Réduit pour être discret */
  height: 20px;
  object-fit: contain;
  vertical-align: middle;
}

.cl-occasion-title {
  display: flex;
  align-items: center;
  gap: 8px; /* Espace réduit entre l'icône et le texte */
}

@media (max-width: 768px) {
  .pf-child-section {
    padding: 15px;
  }
  .pf-filter-bar {
    padding: 0 5px;
  }
  .pf-filter-label {
    display: none;
  }
  .pf-gift-feed {
    grid-template-columns: 1fr 1fr;
  }
  .pf-gift-title {
    font-size: 0.85rem;
  }
  .pf-gift-price {
    font-size: 0.9rem;
  }
}
@media (max-width: 400px) {
  .pf-gift-feed {
    grid-template-columns: 1fr;
  }
}
/* === COMPOSANT MULTI-SELECT VANILLA === */
.pf-multi-select {
  position: relative;
  display: inline-block;
}

/* Le bouton déclencheur (réutilise le style des filtres) */
.pf-ms-trigger {
  padding: 6px 16px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  gap: 6px;
  user-select: none;
}
.pf-ms-trigger:hover {
  border-color: #cbd5e1;
}
.pf-ms-trigger.active {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

/* Le menu déroulant */
.pf-ms-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  background: white;
  border: 1px solid var(--border-light);
  border-radius: 12px;
  padding: 8px;
  box-shadow: var(--shadow-lg);
  z-index: 100;
  min-width: 200px;
  flex-direction: column;
  gap: 4px;
  max-height: 60vh;
  overflow-y: auto;
}
.pf-ms-dropdown.open {
  display: flex;
  animation: popDown 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Les options (checkboxes) */
.pf-ms-option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  padding: 8px 10px;
  border-radius: 6px;
  color: var(--text-main);
  transition: 0.2s;
}
.pf-ms-option:hover {
  background: #f8fafc;
}
.pf-ms-option input[type="checkbox"] {
  margin: 0;
  cursor: pointer;
  accent-color: var(--primary);
  width: 16px;
  height: 16px;
}
.pf-ms-option.is-all {
  font-weight: 700;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 10px;
  margin-bottom: 4px;
  border-radius: 6px 6px 0 0;
}

@keyframes popDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

```

---

### 📄 Fichier : `modules/gift-list/save-gift.php`
```php
<?php
// modules/gift-list/save-gift.php

require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gift-list.php');
    exit;
}

// Configuration
$tableName = 'pf_gifts'; // Harmonisé avec gift-list.php

// Récupération des données
$action = $_POST['action'] ?? 'create';
$gift_id = (int)($_POST['gift_id'] ?? 0);

// Logique de redirection (pour rester sur la bonne vue)
// Par défaut on renvoie vers le referer ou vers la page principale
$redirectUrl = '/gift-list.php';
$occasionForView = $_POST['occasion'] ?? '';
if (in_array($occasionForView, ['ANNIV', 'SANT'])) {
    $redirectUrl .= '?view=anniversary';
} else {
    $redirectUrl .= '?view=nadal';
}

try {
    // --- SUPPRESSION ---
    if ($action === 'delete') {
        if ($gift_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = :id");
            $stmt->execute(['id' => $gift_id]);
        }
        header("Location: $redirectUrl");
        exit;
    }

    // Champs communs
    $year       = (int)($_POST['year'] ?? date('Y'));
    $adult_name = trim($_POST['adult_name'] ?? '');
    $payer_name = trim($_POST['payer_name'] ?? '');
    $child_name = trim($_POST['child_name'] ?? '');
    $occasion   = trim($_POST['occasion'] ?? '');
    $gift_desc  = trim($_POST['gift_description'] ?? '');
    $prod_link  = trim($_POST['product_link'] ?? '');
    $amount     = ($_POST['amount'] !== '') ? (float)$_POST['amount'] : 0.0;

    // Si payeur vide, c'est l'adulte responsable qui paye
    if ($payer_name === '') {
        $payer_name = $adult_name;
    }

    // Validation minimale
    if (!$adult_name || !$child_name || !$occasion || !$gift_desc) {
        // En cas d'erreur, on redirige sans rien faire (ou on pourrait gérer une erreur)
        header("Location: $redirectUrl");
        exit;
    }

    // --- UPDATE ---
    if ($action === 'update' && $gift_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE {$tableName}
            SET year = :year,
                adult_name = :adult_name,
                payer_name = :payer_name,
                child_name = :child_name,
                occasion = :occasion,
                gift_description = :gift_description,
                product_link = :product_link,
                amount = :amount
            WHERE id = :id
        ");
        $stmt->execute([
            'id'               => $gift_id,
            'year'             => $year,
            'adult_name'       => $adult_name,
            'payer_name'       => $payer_name,
            'child_name'       => $child_name,
            'occasion'         => $occasion,
            'gift_description' => $gift_desc,
            'product_link'     => $prod_link ?: null,
            'amount'           => $amount,
        ]);
    } 
    // --- CREATE ---
    else {
        $stmt = $pdo->prepare("
            INSERT INTO {$tableName}
              (year, adult_name, payer_name, child_name, occasion, gift_description, product_link, amount)
            VALUES
              (:year, :adult_name, :payer_name, :child_name, :occasion, :gift_description, :product_link, :amount)
        ");
        $stmt->execute([
            'year'             => $year,
            'adult_name'       => $adult_name,
            'payer_name'       => $payer_name,
            'child_name'       => $child_name,
            'occasion'         => $occasion,
            'gift_description' => $gift_desc,
            'product_link'     => $prod_link ?: null,
            'amount'           => $amount,
        ]);
    }

} catch (PDOException $e) {
    // Log l'erreur si besoin
}

header("Location: $redirectUrl");
exit;
```

---

### 📄 Fichier : `modules/holidays/holidays.css`
```css
/* modules/holidays/holidays.css */

/* --- 1. VARIABLES & BASE --- */

/* Bloque le défilement de la page en arrière-plan */
body.no-scroll {
  overflow: hidden !important;
}
:root {
  --primary: #2563eb;
  --primary-hover: #1d4ed8;
  --bg-page: #f1f5f9;
  --bg-card: #ffffff;
  --text-main: #1e293b;
  --text-muted: #64748b;

  --radius-l: 16px;
  --radius-m: 10px;
  --radius-s: 6px;

  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg:
    0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --shadow-hover:
    0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}

.pf-holidays {
  background-color: var(--bg-page);
  font-family:
    "Segoe UI",
    system-ui,
    -apple-system,
    sans-serif;
  color: var(--text-main);
  padding-bottom: 50px;
}

/* --- 2. EN-TÊTE (Refonte Responsive) --- */
.pf-holidays__titlebar {
  background: transparent;
  padding-bottom: 1rem;
  margin-bottom: 2rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
}

.hol-title-group {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  flex: 1; /* Prend l'espace disponible */
}

.hol-main-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.hol-filters-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.hol-year-select {
  width: auto !important; /* Force la largeur au contenu sur PC */
  padding: 6px 12px;
  font-weight: bold;
  cursor: pointer;
}

.hol-badge-left-to-pay {
  background: #fff1f2;
  color: #be123c;
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: bold;
  border: 1px solid #fecdd3;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.hol-badge-amount {
  font-size: 1rem;
}

.hol-actions-group {
  flex-shrink: 0;
}

/* --- 3. BOUTONS --- */
.hol-add-btn,
.hol-map-toggle,
.pf-btn {
  border: none;
  border-radius: 50px;
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-sm);
  text-decoration: none;
}

.hol-add-btn,
.pf-btn {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}
.hol-add-btn:hover,
.pf-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.hol-map-toggle {
  background: white;
  color: #0f766e;
  border: 1px solid #ccfbf1;
}
.hol-map-toggle:hover {
  background: #f0fdfa;
  color: #0d9488;
  border-color: #99f6e4;
}

.pf-btn.btn-secondary {
  background: white;
  color: var(--text-muted);
  border: 1px solid #cbd5e1;
  box-shadow: none;
}
.pf-btn.btn-secondary:hover {
  background: #f8fafc;
  color: var(--text-main);
}

/* Petit bouton + pour les colonnes */
.btn-icon-small {
  background: white !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 6px !important;
  width: 32px !important;
  height: 32px !important;
  min-width: 32px !important;
  padding: 0 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}

.btn-icon-small:hover {
  background: #f1f5f9 !important;
  border-color: #cbd5e1 !important;
  transform: scale(1.05);
}

/* --- 4. CARTES --- */
.hol-ideas-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.hol-idea-card {
  background: var(--bg-card);
  border-radius: var(--radius-l);
  box-shadow: var(--shadow-md);
  padding: 20px;
  display: flex;
  flex-direction: column;
  transition:
    transform 0.25s ease,
    box-shadow 0.25s ease;
  border: 1px solid transparent;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.hol-idea-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-hover);
  border-color: #e2e8f0;
}

.hol-idea-card__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.hol-idea-card h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main);
  line-height: 1.3;
}

.hol-idea-meta {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hol-notes {
  font-size: 0.85rem;
  color: #64748b;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin: 0;
  font-style: italic;
}

/* --- 5. UTILITAIRES STATUTS --- */
.bg-green-100 {
  background-color: #dcfce7;
}
.text-green-800 {
  color: #166534;
}
.bg-blue-100 {
  background-color: #dbeafe;
}
.text-blue-800 {
  color: #1e40af;
}
.bg-yellow-50 {
  background-color: #fefce8;
}
.text-yellow-800 {
  color: #854d0e;
}
.bg-gray-100 {
  background-color: #f3f4f6;
}
.text-gray-600 {
  color: #4b5563;
}

/* --- 6. MODALE & FORMULAIRE (Refonte Centrage & Largeur) --- */
.pf-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  align-items: center; /* Centrage Vertical */
  justify-content: center; /* Centrage Horizontal */
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
}
.pf-modal.open {
  display: flex;
}

.pf-modal-content {
  position: relative;
  background: white;
  width: 95%; /* Responsive */
  max-width: 1100px; /* Largeur augmentée pour 3 colonnes */
  max-height: 90vh;
  overflow-y: auto;
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  padding: 32px;
  display: flex;
  flex-direction: column;
  margin: auto; /* Sécurité centrage */
}

/* Cas spécifique modale carte */
.hol-dialog--map {
  width: 95vw;
  height: 90vh;
  max-width: 1400px;
  padding: 0;
  background: white;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
}

/* Inputs généraux */
.pf-label {
  display: block;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.pf-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: var(--radius-m);
  font-size: 1rem;
  background: #ffffff; /* Fond blanc forcé */
  color: var(--text-main);
  box-sizing: border-box;
  transition: all 0.2s;
}

.pf-input:focus {
  background: white;
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

/* --- 7. SECTIONS DE DÉTAILS (Transport/Hotel/Activité) --- */
.hol-columns-wrapper {
  display: flex; /* Flexbox */
  flex-direction: column; /* Alignement vertical (les uns sous les autres) */
  gap: 24px; /* Espace entre les blocs */
  width: 100%;
}

.hol-col {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  width: 100%; /* Prend toute la largeur disponible */
  box-sizing: border-box; /* Empêche le padding de casser la largeur */
}

.hol-col-header {
  display: flex;
  justify-content: space-between; /* Titre à gauche, bouton à droite */
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
  margin-bottom: 12px;
}

.hol-col-header h4 {
  margin: 0;
  font-size: 1rem;
  text-transform: uppercase;
  font-weight: 700;
  /* On retire les bordures/marges de l'ancien h4 car c'est le header qui gère */
  border: none;
  padding: 0;
}

/* Le bouton "+" style Gift List */
.btn-add-item {
  /* Style de base */
  background: rgba(255, 255, 255, 0.6);
  color: var(--text-muted);
  border: 1px solid rgba(0, 0, 0, 0.1);

  /* Forme et Taille */
  width: 28px;
  height: 28px;
  border-radius: 50%;

  /* Texte */
  font-size: 18px;
  line-height: 1;

  /* Flex pour centrer le + */
  display: flex;
  align-items: center;
  justify-content: center;

  cursor: pointer;

  /* Animation fluide "Cubic Bezier" copiée de gift-list */
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.btn-add-item:hover {
  background: white;
  color: var(--primary); /* On ajoute la couleur primaire au survol */

  /* L'effet de rotation et d'agrandissement */
  transform: rotate(90deg) scale(1.1);

  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
  border-color: currentColor;
}

/* --- 8. ITEMS DYNAMIQUES (Inputs optimisés) --- */
.savings-line-item {
  display: flex;
  align-items: center;
  gap: 8px; /* Espace réduit */
  margin-bottom: 10px;
  background: white;
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

/* Champ NOM : prend toute la place */
.savings-line-item input[type="text"] {
  flex-grow: 1;
  min-width: 120px;
  padding: 6px 10px;
  font-size: 0.9rem;
}

/* Champ PRIX : compact */
.savings-line-item input[type="number"] {
  width: 120px !important;
  padding: 6px 8px !important;
  text-align: right;
  font-weight: 600;
  color: #059669;
}

/* Checkbox Payé */
.savings-line-item label {
  display: flex;
  align-items: center;
  font-size: 0.75rem;
  color: #64748b;
  gap: 4px;
  cursor: pointer;
  white-space: nowrap;
}

/* Bouton Supprimer (Croix) */
.btn-remove {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: #ef4444;
  border: none;
  border-radius: 4px;
  font-size: 1.2rem;
  cursor: pointer;
  line-height: 1;
  transition: background 0.2s;
}
.btn-remove:hover {
  background: #fef2f2;
  color: #b91c1c;
}

/* --- 9. FOOTER MODALE --- */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

/* --- 10. MOBILE --- */
@media (max-width: 768px) {
  .pf-holidays__titlebar {
    flex-direction: column;
    align-items: stretch; /* Étire les éléments de haut en bas */
    gap: 15px;
  }
  .hol-title-group {
    flex-direction: column;
    align-items: stretch;
    gap: 15px;
  }

  .hol-filters-row {
    justify-content: space-between; /* Écarte l'année et le badge */
    width: 100%;
  }
  .hol-badge-left-to-pay {
    flex: 1;
    justify-content: center; /* Centre le texte dans le badge */
    font-size: 0.8rem; /* Un peu plus petit sur mobile */
  }

  /* On empile proprement les boutons d'action */
  .hol-title-actions {
    width: 100%;
    flex-direction: column; /* Essentiel pour que les boutons width: 100% ne s'écrasent pas */
    gap: 10px;
  }
  .hol-actions-group .hol-add-btn {
    width: 100%;
    justify-content: center;
    padding: 12px;
    font-size: 1rem;
  }
  .hol-add-btn,
  .hol-map-toggle {
    width: 100%;
    justify-content: center; /* Centre le texte et l'icône */
  }

  .form-row {
    flex-direction: column;
    gap: 10px;
  }

  .hol-columns-wrapper {
    grid-template-columns: 1fr;
  }

  /* Modale 100% écran avec scroll natif fluide */
  .pf-modal-content {
    padding: 16px;
    width: 100%;
    height: 100%;
    max-height: 100vh;
    border-radius: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* Adaptation des lignes de budget sur mobile pour éviter qu'elles ne soient compressées */
  .savings-line-item {
    flex-wrap: wrap;
    gap: 10px;
  }
  .savings-line-item input[type="text"] {
    min-width: 100%; /* Le nom de la dépense prend toute la ligne */
  }
  .savings-line-item input[type="number"] {
    flex-grow: 1; /* Le prix s'ajuste sur la 2ème ligne */
  }

  .hol-date-weather-wrapper {
    gap: 6px;
  }

  .hol-cp-header {
    align-items: flex-start;
    flex-direction: column; /* On passe le titre et le prix l'un sous l'autre si besoin */
  }

  .hol-cp-info-group {
    flex: 1;
    min-width: 0;
    width: 100%;
    align-items: flex-start;
  }

  .hol-cp-title {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    line-height: 1.4;
    word-break: break-word;
    padding-top: 2px;
  }

  .hol-cp-actions-group {
    width: 100%;
    justify-content: flex-end;
    margin-top: 8px;
  }
}

/* --- 11. CALENDRIER & DATES (Force Light Theme) --- */
input[type="date"] {
  color-scheme: light; /* Force le popup blanc */
  background-color: #ffffff;
  color: #1e293b;
}

/* Cible spécifique pour l'icône calendrier (Chrome/Edge/Safari) */
input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.6;
  filter: invert(0);
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
}

/* ==========================================================================
   12. PAGE DE DÉTAIL DU VOYAGE (detail.php)
   ========================================================================== */

/* Structure principale de la page détail */
.pf-holidays-detail {
  padding-top: 10px;
}

/* En-tête de la page détail */
.hol-detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 15px;
}

.hol-detail-title-group {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}

.hol-detail-title-group h1 {
  margin: 0;
  font-size: 1.5rem;
  color: var(--text-main);
}

.hol-badge-status {
  font-size: 0.75rem;
  padding: 4px 10px;
  border-radius: 12px;
  font-weight: bold;
  background: #e0f2fe;
  color: #0369a1;
}

/* Bloc Résumé Financier */
.hol-summary-card {
  background: white;
  padding: 20px;
  border-radius: var(--radius-l);
  box-shadow: var(--shadow-sm);
  border: 1px solid #e2e8f0;
  margin-bottom: 24px;
}

.hol-summary-grid {
  display: flex;
  justify-content: space-between;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 15px;
}

.hol-summary-item {
  min-width: 150px;
}

.hol-summary-label {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-bottom: 4px;
}

.hol-summary-value {
  font-weight: 600;
  color: var(--text-main);
  font-size: 1.1rem;
}

.hol-summary-value.total {
  font-size: 1.4rem;
  font-weight: bold;
  color: #0f172a;
}

/* Grille principale : Carte + Timeline */
.hol-layout-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
  align-items: stretch;
}

/* Panneaux (Carte et Timeline) */
.hol-panel {
  background: white;
  border-radius: var(--radius-l);
  box-shadow: var(--shadow-sm);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  height: 600px;
  overflow: hidden;
}

.hol-panel-header {
  padding: 15px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.hol-panel-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: var(--text-main);
}

.hol-panel-body {
  padding: 15px;
  overflow-y: auto;
  flex: 1;
}

/* Styles des Checkpoints (Timeline) */
.hol-checkpoint {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 15px;
  overflow: hidden;
  background: white;
}

.hol-cp-header {
  background: #f8fafc;
  padding: 12px 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  gap: 12px;
}

.hol-cp-info-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.hol-cp-title {
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hol-cp-title:hover {
  color: var(--primary);
}

.hol-cp-actions-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.hol-cp-total {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--primary);
  white-space: nowrap;
}

.hol-cp-body {
  padding: 10px 15px;
}

.hol-expense-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  margin-bottom: 5px;
  border-bottom: 1px dashed #f1f5f9;
  padding-bottom: 5px;
}

.hol-expense-line:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.hol-notes-panel {
  padding: 15px;
  border-top: 1px solid #e2e8f0;
  background: #fffbeb;
  flex-shrink: 0;
  max-height: 200px;
  overflow-y: auto;
}

/* --- RESPONSIVE DETAIL PAGE --- */
@media (max-width: 992px) {
  .hol-layout-grid {
    grid-template-columns: 1fr; /* Sur tablette/mobile, la carte et la liste s'empilent */
  }

  .hol-panel {
    height: 500px; /* On réduit un peu la hauteur pour que ce soit agréable sur mobile */
  }

  .hol-summary-grid {
    flex-direction: column; /* Les infos financières s'empilent */
  }

  .hol-summary-item {
    text-align: left !important; /* On annule l'alignement à droite du total */
  }
}

/* ==========================================================================
   13. BARRE DE PROGRESSION & FINANCES
   ========================================================================== */
.hol-progress-bar {
  width: 100%;
  height: 12px;
  background: #e2e8f0;
  border-radius: 6px;
  margin-bottom: 10px;
  display: flex;
  overflow: hidden;
}
.hol-progress-paid {
  background: #10b981;
}
.hol-progress-saved {
  background: #3b82f6;
}

.hol-progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
}
.hol-label-paid {
  color: #10b981;
  font-weight: 600;
}
.hol-label-saved {
  color: #3b82f6;
  font-weight: 600;
}
.hol-label-left {
  color: #ef4444;
  font-weight: 700;
}

/* ==========================================================================
   14. LÉGENDE DE LA CARTE
   ========================================================================== */
.hol-panel-header-group {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}
.hol-map-legend {
  display: flex;
  gap: 12px;
  font-size: 0.75rem;
  color: #64748b;
  background: white;
  padding: 4px 10px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}
.hol-legend-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.hol-legend-color {
  display: inline-block;
  width: 12px;
  height: 4px;
  border-radius: 2px;
}
.hol-legend-color.aller {
  background: #3b82f6;
}
.hol-legend-color.interm {
  background: #8b5cf6;
}
.hol-legend-color.retour {
  background: transparent;
  height: 0;
  border-bottom: 3px dashed #f97316;
  border-radius: 0;
}

/* ==========================================================================
   15. LIGNES DE DÉPENSES (Timeline & Modale JS)
   ========================================================================== */
.hol-expense-wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  margin-bottom: 8px;
  border-bottom: 1px dashed #f1f5f9;
  padding-bottom: 8px;
}
.hol-expense-wrapper:last-child {
  margin-bottom: 0;
  border-bottom: none;
  padding-bottom: 0;
}

.hol-expense-main {
  display: flex;
  justify-content: space-between;
  width: 100%;
  font-size: 0.85rem;
}
.hol-expense-name {
  color: #475569;
  font-weight: 500;
}
.hol-expense-amount {
  color: var(--text-main);
  font-weight: bold;
}
.status-paid {
  color: #10b981;
  margin-left: 5px;
}
.status-pending {
  color: #f59e0b;
  margin-left: 5px;
}

.hol-expense-note {
  font-size: 0.75rem;
  color: #94a3b8;
  padding-left: 24px;
  word-break: break-all;
}
.hol-expense-link {
  color: #3b82f6;
  text-decoration: none;
}
.hol-expense-link:hover {
  text-decoration: underline;
}

.hol-empty-step {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-style: italic;
  padding: 5px 0;
}

/* Boutons de petite taille */
.pf-btn-small {
  padding: 6px 14px !important;
  font-size: 0.85rem !important;
  width: auto !important;
  height: auto !important;
  margin: 0 !important;
}

/* Modale JS d'ajout de dépense */
.hol-form-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.hol-form-inner {
  display: flex;
  gap: 8px;
  align-items: center;
  width: 100%;
}
.hol-form-select {
  width: 50px;
  padding: 8px 4px;
  font-size: 1.2rem;
  cursor: pointer;
}
.hol-form-text {
  flex: 2;
  padding: 8px;
  font-size: 0.9rem;
}
.hol-form-number {
  width: 80px;
  text-align: right;
  padding: 8px;
  font-size: 0.9rem;
}
.hol-form-paid-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  width: 55px;
}
.hol-form-paid-text {
  font-size: 0.75rem;
  margin-left: 4px;
  font-weight: bold;
  color: #64748b;
}
.hol-form-notes-input {
  font-size: 0.8rem;
  padding: 6px 8px;
  border-style: dashed;
  color: #475569;
  width: calc(100% - 58px);
  margin-left: 58px;
}
.btn-remove-expense {
  width: 28px;
  height: 28px;
  border: none;
  background: #fee2e2;
  color: #ef4444;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: 0.2s;
}
.btn-remove-expense:hover {
  background: #fca5a5;
  color: #b91c1c;
}

/* ==========================================================================
   16. PLANNING DRAG & DROP (CARNET DE VOYAGE)
   ========================================================================== */
/* Simplification de la sous-ligne du formulaire d'édition */
.hol-form-subrow {
  display: flex;
  margin-top: 6px;
  padding-left: 58px;
}
.hol-form-notes-full {
  margin: 0 !important;
  width: 100%;
}

/* Layout du Planning Interactif */
.hol-planning-layout {
  display: flex;
  gap: 20px;
  height: 65vh;
  align-items: stretch;
  overflow: hidden;
}
@media (max-width: 768px) {
  .hol-planning-layout {
    flex-direction: column;
    height: 75vh;
  }
  .hol-unmapped-zone {
    width: 100%;
    height: 180px;
    min-height: 180px;
    flex-direction: row;
    overflow-x: auto;
    overflow-y: hidden;
    align-items: flex-start;
  }
  .hol-calendar-zone {
    flex: 1;
  }
}

/* Zone des activités non planifiées */
.hol-unmapped-zone {
  width: 250px;
  background: #f8fafc;
  padding: 15px;
  border-radius: 12px;
  border: 2px dashed #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
}
.hol-unmapped-title {
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 5px;
  text-align: center;
}

/* Grille du calendrier */
.hol-calendar-zone {
  flex: 1;
  display: flex;
  gap: 15px;
  overflow: auto; /* Gère à la fois gauche/droite ET haut/bas */
  padding-bottom: 10px;
  position: relative;
}
.hol-calendar-zone::-webkit-scrollbar,
.hol-unmapped-zone::-webkit-scrollbar {
  height: 10px;
}
.hol-calendar-zone::-webkit-scrollbar-track,
.hol-unmapped-zone::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 5px;
}
.hol-calendar-zone::-webkit-scrollbar-thumb,
.hol-unmapped-zone::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 5px;
}
.hol-calendar-zone::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.hol-calendar-zone .hol-drag-item {
  position: absolute;
  top: 1px;
  left: 45px;
  right: 5px;
  height: calc(75px * var(--duration) - 2px);
}
/* Dans la boîte d'attente : Position normale */
.hol-unmapped-zone .hol-drag-item {
  position: relative;
  min-width: 150px;
  height: auto;
}

/* Mode "Tap-to-Move" pour Mobile */
.hol-drag-item.selected-for-move {
  border-color: #f59e0b;
  transform: scale(1.02);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
}

/* Boutons Durée */
.hol-item-duration-controls {
  display: flex;
  align-items: center;
  gap: 3px;
  margin: 0;
  padding: 2px;
  border: none;
  background: rgba(255, 255, 255, 0.4); /* Léger fond pour se détacher */
  border-radius: 4px;
}

.hol-day-column {
  min-width: 220px;
  flex: 1;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  height: max-content; /* Laisse la colonne grandir jusqu'à 22h */
  overflow: visible;
}

.hol-calendar-day-header {
  text-align: center;
  padding: 12px 10px;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  position: sticky;
  top: 0; /* Garde la date toujours visible en haut ! */
  z-index: 20;
}

/* Les créneaux horaires (Drop Zones) */
.hol-time-slots-container {
  display: flex;
  flex-direction: column;
}

.hol-time-slot {
  height: 75px;
  border-bottom: 1px solid #f1f5f9;
  position: relative;
  transition: background 0.2s;
  box-sizing: border-box;
}

.hol-time-slot.drag-over {
  background: #e0f2fe; /* Surbrillance quand on survole avec une activité */
}
.hol-slot-label {
  position: absolute;
  top: 2px;
  right: 5px;
  font-size: 0.65rem;
  font-weight: 700;
  color: #94a3b8;
  pointer-events: none;
}

/* L'Activité (Élément draggable) */
.hol-drag-item {
  background: white;
  padding: 6px 8px;
  border-radius: 6px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
  cursor: grab;
  font-size: 0.85rem;
  border: 1px solid #e2e8f0;
  border-left: 4px solid var(--primary);
  display: flex;
  flex-direction: column;
  z-index: 10;
  overflow: hidden;
  transition:
    transform 0.2s,
    border-color 0.2s;
}
.hol-drag-item:active {
  cursor: grabbing;
}
.hol-drag-item.cat-accommodation {
  border-left-color: #8b5cf6;
}
.hol-drag-item.cat-transport {
  border-left-color: #10b981;
}

.hol-drag-title {
  font-weight: 600;
  color: #0f172a;
  line-height: 1.2;
}
.hol-drag-note {
  font-size: 0.75rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Élargir la modale de planning */
#planningModal .pf-modal-content {
  max-width: 1100px !important;
  width: 95%;
}

.hol-dur-btn {
  background: #f1f5f9;
  border: none;
  border-radius: 4px;
  width: 18px;
  height: 18px; /* Plus petit */
  cursor: pointer;
  font-weight: bold;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}
.hol-dur-btn:hover {
  background: #e2e8f0;
}
.hol-dur-text {
  font-size: 0.7rem;
  color: #64748b;
  font-weight: 700;
}

.highlight-step {
  transition:
    box-shadow 0.3s,
    transform 0.3s;
  box-shadow: 0 0 0 3px var(--primary);
  transform: scale(1.02);
}

/* Style du badge météo injecté par le JS */
.hol-weather-info {
  display: inline-flex;
}

.pf-weather-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc; /* Slate 50 */
  border: 1px solid #e2e8f0; /* Slate 200 */
  padding: 1px 6px;
  border-radius: 6px;
  color: #475569; /* Slate 600 */
  font-weight: 600;
  font-size: 0.7rem;
  line-height: 1;
}

.pf-weather-icon {
  font-size: 0.9rem;
}

/* Conteneur Date + Météo */
.hol-date-weather-wrapper {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: normal;
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap; /* Le secret pour le responsive ! */
}

/* On s'assure que le texte de la date ne se coupe pas au milieu d'un mot */
.hol-step-dates {
  white-space: nowrap;
}
.leaflet-container img.leaflet-tile {
  pointer-events: none !important;
  user-select: none !important;
  -moz-user-select: none !important;
}
#tripMap {
  touch-action: none;
}

```

---

### 📄 Fichier : `modules/holidays/holidays.js`
```javascript
// ============================================================================
// FONCTION DE TRADUCTION JS & LANGUE COURANTE
// ============================================================================
function tr(key) {
  return window.I18N && window.I18N[key] ? window.I18N[key] : key;
}

// On utilise 'var' au lieu de 'const/let' pour éviter les crashs si le fichier est lu 2 fois
var currentLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";
var selectedItemIdForMove = null; // Déplacé ici pour plus de clarté

// ============================================================================
// UTILITAIRES MÉTÉO
// ============================================================================
function getWeatherInfo(code) {
  // Transformation en conditions pour regrouper les codes WMO
  if (code === 0) return { icon: "☀️", label: tr("weather_sunny") };
  if ([1, 2].includes(code)) return { icon: "🌤️", label: tr("weather_sunny") };
  if ([3, 45, 48].includes(code))
    return { icon: "☁️", label: tr("weather_cloudy") };
  // Les codes 51 à 67 et 80 à 82 couvrent toutes les formes de pluie et bruine
  if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code))
    return { icon: "🌧️", label: tr("weather_rainy") };
  // Les codes neigeux
  if ([71, 73, 75, 77, 85, 86].includes(code))
    return { icon: "❄️", label: tr("weather_snowy") };
  // Orages
  if ([95, 96, 99].includes(code))
    return { icon: "⛈️", label: tr("weather_rainy") };

  return { icon: "🌡️", label: tr("weather_forecast") };
}

async function loadWeatherForStep(pt) {
  if (!pt.step_start_date || !pt.lat || !pt.lng) return;

  const container = document.querySelector(
    `#step-card-${pt.sort_order} .hol-weather-info`,
  );
  if (!container) return;

  try {
    const resp = await fetch(
      `/modules/holidays/includes/api/get_weather.php?lat=${pt.lat}&lng=${pt.lng}&date=${pt.step_start_date}`,
    );
    const res = await resp.json();

    console.log(`Météo pour ${pt.location_name} :`, res);

    if (res.success) {
      const info = getWeatherInfo(res.data.code);

      // Si c'est une estimation basée sur le passé, on adapte l'affichage
      const approxSymbol = res.data.is_historical ? "~" : "";
      const badgeTitle = res.data.is_historical
        ? `${info.label} (${tr("weather_historical")})`
        : info.label;
      const opacityStyle = res.data.is_historical
        ? "opacity: 0.85; font-style: italic;"
        : "";

      container.innerHTML = `
        <div class="pf-weather-badge" title="${badgeTitle}" style="${opacityStyle}">
          <span class="pf-weather-icon">${info.icon}</span>
          <span>${approxSymbol}${Math.round(res.data.temp_min)}° / ${Math.round(res.data.temp_max)}°C</span>
        </div>`;
    }
  } catch (e) {
    console.error("Weather error", e);
  }
}

// ============================================================================
// FERMETURE UNIVERSELLE DES MODALES
// ============================================================================
window.addEventListener("click", function (event) {
  if (event.target.classList.contains("pf-modal")) {
    event.target.style.display = "none";
    document.body.classList.remove("no-scroll");
  }
});

// --- 1. GESTION DE LA MODALE D'ÉDITION RAPIDE ---

function openHolidayModal(mode) {
  const modal = document.getElementById("holidayModal");
  const form = document.getElementById("holidayForm");
  const btnDelete = document.getElementById("btn_delete");

  form.reset();
  document.getElementById("inp_id").value = "";
  document.getElementById("list_transport").innerHTML = "";
  document.getElementById("list_accommodation").innerHTML = "";
  document.getElementById("list_activity").innerHTML = "";

  if (mode === "add") {
    document.getElementById("modalTitle").innerText = tr("hdl_modal_title");
    btnDelete.style.display = "none";
  } else {
    document.getElementById("modalTitle").innerText = tr(
      "hdl_quick_edit_title",
    );
    btnDelete.style.display = "block";
  }

  modal.style.display = "flex";
  setTimeout(() => document.getElementById("inp_title").focus(), 100);
}

function closeHolidayModal() {
  document.getElementById("holidayModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function editHoliday(data) {
  const h = data.main;
  const modal = document.getElementById("holidayModal");

  if (!modal) {
    alert(tr("err_modal_missing"));
    return;
  }

  document.body.appendChild(modal);

  if (typeof openHolidayModal === "function") {
    openHolidayModal("edit");
  }

  modal.classList.add("open");
  modal.style.setProperty("display", "flex", "important");
  modal.style.setProperty("z-index", "999999", "important");
  document.body.classList.add("no-scroll");

  try {
    document.getElementById("inp_id").value = h.id;
    document.getElementById("inp_title").value = h.title;
    document.getElementById("inp_status").value = h.status;
    document.getElementById("inp_period").value = h.period_hint || "";
    document.getElementById("inp_start").value = h.start_date || "";
    document.getElementById("inp_end").value = h.end_date || "";

    document.getElementById("inp_food").value =
      h.budget_food > 0 ? h.budget_food : "";
    document.getElementById("inp_extra").value =
      h.budget_extra > 0 ? h.budget_extra : "";
    document.getElementById("inp_notes").value = h.notes || "";
  } catch (err) {
    console.error("Erreur champs textes :", err);
  }

  try {
    document.getElementById("list_transport").innerHTML = "";
    document.getElementById("list_accommodation").innerHTML = "";
    document.getElementById("list_activity").innerHTML = "";

    if (data.items && data.items.length > 0) {
      data.items.forEach((item) => {
        if (
          typeof addItem === "function" &&
          item.name !== "PF_TECHNICAL_POINT"
        ) {
          addItem(item.category, item.name, item.amount, item.is_paid);
        }
      });
    }
  } catch (err) {
    console.error("Erreur listes :", err);
  }
}

// --- 2. GESTION DES LISTES DYNAMIQUES DANS LA MODALE ---

function addItem(category, name = "", amount = "", isPaid = 0) {
  const container = document.getElementById("list_" + category);
  const div = document.createElement("div");

  div.style.display = "flex";
  div.style.gap = "8px";
  div.style.alignItems = "center";
  div.style.marginBottom = "10px";

  const checkedAttr = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <input type="hidden" name="items[cat][]" value="${category}">
        <input type="text" name="items[name][]" class="pf-input" placeholder="${tr("hdl_js_ph_expense_name")}" value="${name}" style="flex: 2; padding: 8px; font-size:0.9rem;" required>
        <input type="number" step="0.01" name="items[amount][]" class="pf-input" placeholder="0.00" value="${amount}" style="width: 80px; text-align: right; padding: 8px; font-size:0.9rem;">
        <label title="${tr("hdl_paid")}" style="display: flex; align-items: center; cursor: pointer; padding: 0 5px;">
            <input type="checkbox" ${checkedAttr} onchange="this.nextElementSibling.value = this.checked ? 1 : 0" style="margin:0;">
            <input type="hidden" name="items[paid][]" value="${isPaid}">
            <span style="font-size:0.75rem; margin-left:4px; font-weight:bold; color:#64748b;">${tr("hdl_paid")}</span>
        </label>
        <button type="button" onclick="this.parentElement.remove()" class="btn-icon-action delete" title="${tr("btn_delete")}">
            🗑️
        </button>
    `;

  container.appendChild(div);
}

function deleteHoliday() {
  if (!confirm(tr("hdl_js_confirm_del_trip"))) return;
  const form = document.getElementById("holidayForm");
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

// --- 3. GESTION DE LA CARTE ---

var map = null;

function toggleMap() {
  const modal = document.getElementById("hol-map-modal");
  if (!modal) return;
  if (modal.style.display === "flex") {
    modal.style.display = "none";
  } else {
    modal.style.display = "flex";
    setTimeout(initMap, 100);
  }
}

function initMap() {
  if (map) {
    map.invalidateSize();
    return;
  }
  if (typeof L === "undefined") return;

  map = L.map("hol-map").setView([46.6, 2.4], 4);
  L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
    {
      attribution: "© OpenStreetMap",
    },
  ).addTo(map);

  if (typeof HOL_MAP_POINTS !== "undefined") {
    HOL_MAP_POINTS.forEach((pt) => {
      const color =
        pt.status === "planned" || pt.status === "booked" ? "green" : "blue";
      L.circleMarker([pt.lat, pt.lng], {
        color: color,
        radius: 8,
        fillOpacity: 0.8,
      })
        .addTo(map)
        .bindPopup(`<b>${pt.title}</b><br>${pt.status}`);
    });
  }
}

// ============================================================================
// 4. GESTION DE LA CARTE DÉTAILLÉE (ROADTRIP) ET GÉOCODAGE
// ============================================================================

var detailMap = null;

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("tripMap")) {
    initDetailMap();
  }
});

function initDetailMap() {
  if (typeof L === "undefined" || typeof MAP_POINTS === "undefined") return;

  // 1. 🧹 NETTOYAGE PROPRE : On détruit l'ancienne instance si elle existe (Évite le bug de la souris bloquée)
  if (detailMap !== null) {
    detailMap.remove();
    detailMap = null;
  }

  const mapContainer = document.getElementById("tripMap");
  if (!mapContainer) return;

  // 2. 🛡️ BOUCLIER FIREFOX DESKTOP : Empêche le drag natif HTML5 de voler le clic
  mapContainer.style.touchAction = "none";
  mapContainer.ondragstart = function (e) {
    e.preventDefault();
  };

  // 3. 🛠️ INITIALISATION DE LA CARTE
  detailMap = L.map("tripMap", {
    tap: false, // Désactive le tap simulé (anti-warning mobile/Firefox)
    dragging: true, // Force l'autorisation du déplacement à la souris
  });

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(detailMap);

  // Cas : Aucun point
  if (MAP_POINTS.length === 0) {
    detailMap.setView([46.6, 2.4], 5); // France par défaut
    return;
  }

  const latlngs = [];
  const bounds = L.latLngBounds();

  // 4. PLACEMENT DES MARQUEURS
  MAP_POINTS.forEach((pt, index) => {
    const pos = [pt.lat, pt.lng];
    latlngs.push(pos);
    bounds.extend(pos);

    const color = "#2563eb";

    const marker = L.circleMarker(pos, {
      color: color,
      radius: window.innerWidth < 768 ? 6 : 8,
      fillOpacity: 1,
      fillColor: "white",
      weight: 3,
    }).addTo(detailMap);

    const stepLabel = window.I18N
      ? window.I18N["hdl_js_step_label"]
      : tr("hdl_js_step_label");

    marker.bindPopup(`
        <div style="text-align:center;">
            <div style="font-size:0.75rem; color:#64748b; margin-bottom:2px; font-weight:bold;">${stepLabel} ${index + 1}</div>
            <strong style="font-size:1rem; color:#0f172a;">${pt.location_name}</strong><br>
            <span style="font-weight:bold; color:${color};">${parseFloat(pt.total_amount).toFixed(2)} €</span>
        </div>
    `);

    // Animation au clic sur le marqueur
    marker.on("click", function () {
      const card = document.getElementById("step-card-" + pt.sort_order);
      if (card) {
        card.scrollIntoView({ behavior: "smooth", block: "center" });
        card.style.transition = "box-shadow 0.3s, transform 0.3s";
        card.style.boxShadow = "0 0 0 3px #3b82f6";
        card.style.transform = "scale(1.02)";
        setTimeout(() => {
          card.style.boxShadow = "";
          card.style.transform = "";
        }, 1500);
      }
    });
  });

  const mapPadding = window.innerWidth < 768 ? [20, 20] : [50, 50];

  // 5. CENTRAGE ET TRACÉS (OSRM)
  if (latlngs.length === 1) {
    detailMap.setView(latlngs[0], 12);
  } else if (latlngs.length > 1) {
    detailMap.fitBounds(bounds, { padding: mapPadding });

    const routePromises = [];

    for (let i = 0; i < latlngs.length - 1; i++) {
      const startPt = MAP_POINTS[i];
      const endPt = MAP_POINTS[i + 1];
      const coordsString = `${startPt.lng},${startPt.lat};${endPt.lng},${endPt.lat}`;

      const promise = fetch(
        `https://router.project-osrm.org/route/v1/driving/${coordsString}?overview=full&geometries=geojson`,
      )
        .then((response) => response.json())
        .then((data) => ({
          index: i,
          data: data,
          coords: [latlngs[i], latlngs[i + 1]],
        }))
        .catch((err) => ({
          index: i,
          error: true,
          coords: [latlngs[i], latlngs[i + 1]],
        }));
      routePromises.push(promise);
    }

    Promise.all(routePromises).then((results) => {
      results.sort((a, b) => a.index - b.index);

      let returnStartIndex = latlngs.length - 2;
      const customReturnStep = MAP_POINTS.findIndex((p) => p.is_return == 1);
      if (customReturnStep > 0) {
        returnStartIndex = customReturnStep;
      }

      results.forEach((res) => {
        const i = res.index;
        let routeColor = "#3b82f6";
        let routeWeight = window.innerWidth < 768 ? 4 : 6;
        let routeDash = null;

        if (i >= returnStartIndex) {
          routeColor = "#f97316";
          routeWeight = window.innerWidth < 768 ? 3 : 4;
          routeDash = "10, 10";
        }

        if (res.data && res.data.code === "Ok" && res.data.routes.length > 0) {
          const routeCoords = res.data.routes[0].geometry.coordinates.map(
            (c) => [c[1], c[0]],
          );
          L.polyline(routeCoords, {
            color: routeColor,
            weight: routeWeight,
            dashArray: routeDash,
            opacity: 0.9,
            lineCap: "round",
            lineJoin: "round",
          }).addTo(detailMap);
        } else {
          drawFallbackLine(res.coords, routeColor, routeWeight);
        }
      });
    });
  }

  // 6. LANCEMENT DE LA MÉTÉO
  if (typeof MAP_POINTS !== "undefined") {
    MAP_POINTS.forEach((pt) => {
      if (typeof loadWeatherForStep === "function") {
        loadWeatherForStep(pt);
      }
    });
  }

  // 7. FIX FINAL : Force Leaflet à recalculer sa taille une fois le DOM stabilisé
  setTimeout(() => {
    if (detailMap) {
      detailMap.invalidateSize();
    }
  }, 300);

  // Fonction utilitaire locale
  function drawFallbackLine(coords, color, weight) {
    L.polyline(coords, {
      color: color,
      weight: weight || 3,
      dashArray: "8, 8",
      opacity: 0.7,
    }).addTo(detailMap);
  }
}

function panMapTo(lat, lng) {
  if (detailMap) {
    detailMap.setView([lat, lng], 14, { animate: true });

    // 🛠️ ERGONOMIE MOBILE : Auto-scroll vers la carte si on est sur petit écran
    if (window.innerWidth < 768) {
      const mapDiv = document.getElementById("tripMap");
      if (mapDiv) {
        mapDiv.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }
  }
}

// --- LOGIQUE DE LA MODALE CHECKPOINT ---

function openCheckpointModal(mode, data = null) {
  const searchBlock = document.getElementById("cpSearchBlock");
  const formBlock = document.getElementById("formCheckpoint");
  const container = document.getElementById("cpExpensesContainer");
  const btnDel = document.getElementById("btnDeleteCp");

  container.innerHTML = "";

  if (document.getElementById("cp_start_date"))
    document.getElementById("cp_start_date").value = "";
  if (document.getElementById("cp_end_date"))
    document.getElementById("cp_end_date").value = "";
  document.getElementById("searchPlaceInput").value = "";
  document.getElementById("searchResults").innerHTML = "";

  searchBlock.style.display = "block";

  if (mode === "add") {
    document.getElementById("cpModalTitle").innerText = tr("hdl_btn_add_step");
    formBlock.style.display = "none";
    btnDel.style.display = "none";
    document.getElementById("cp_old_sort_order").value = "";
    document.getElementById("cp_name").value = "";
    addCpExpenseLine();
    document.getElementById("cp_is_return").checked = false;
  } else if (mode === "edit" && data) {
    document.getElementById("cpModalTitle").innerText = tr("hdl_js_edit_step");
    formBlock.style.display = "block";
    btnDel.style.display = "block";

    document.getElementById("cp_lat").value = data.lat;
    document.getElementById("cp_lng").value = data.lng;
    document.getElementById("cp_old_sort_order").value = data.sort_order;
    document.getElementById("cp_name").value = data.location_name;
    document.getElementById("cp_start_date").value = data.step_start_date || "";
    document.getElementById("cp_end_date").value = data.step_end_date || "";
    document.getElementById("cp_is_return").checked = data.is_return == 1;

    if (data.items && data.items.length > 0) {
      let visibleCount = 0;
      data.items.forEach((it) => {
        if (it.name !== "PF_TECHNICAL_POINT") {
          addCpExpenseLine(
            it.category,
            it.name,
            it.amount,
            it.is_paid,
            it.notes || "",
            it.id || "",
            it.item_date || "",
            it.item_time || "",
            it.duration || 1,
          );
          visibleCount++;
        }
      });
      if (visibleCount === 0) addCpExpenseLine();
    } else {
      addCpExpenseLine();
    }
  }
  document.getElementById("checkpointModal").style.display = "flex";
  document.body.classList.add("no-scroll");
}

function searchPlace() {
  const q = document.getElementById("searchPlaceInput").value.trim();
  if (q.length < 3) return;

  const resultsDiv = document.getElementById("searchResults");
  resultsDiv.innerHTML = `<span style="color:#64748b; font-size:0.85rem;">${tr("hdl_js_search_loading")}</span>`;

  fetch(
    "/modules/holidays/includes/api/geocode.php?limit=5&q=" +
      encodeURIComponent(q),
  )
    .then((res) => res.json())
    .then((data) => {
      resultsDiv.innerHTML = "";
      if (data.error || !data.results || data.results.length === 0) {
        resultsDiv.innerHTML = `<span style="color:#ef4444; font-size:0.85rem;">${tr("hdl_js_no_result")}</span>`;
        return;
      }
      data.results.forEach((place) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "pf-btn btn-secondary";
        btn.style.textAlign = "left";
        btn.style.padding = "8px";
        btn.style.height = "auto";
        btn.innerText = "📍 " + place.display_name;
        btn.onclick = () =>
          selectPlace(place.lat, place.lng, place.display_name);
        resultsDiv.appendChild(btn);
      });
    })
    .catch((err) => {
      resultsDiv.innerHTML = `<span style="color:#ef4444; font-size:0.85rem;">${tr("hdl_js_network_error")}</span>`;
    });
}

function selectPlace(lat, lng, fullName) {
  document.getElementById("cp_lat").value = lat;
  document.getElementById("cp_lng").value = lng;
  document.getElementById("cp_name").value = fullName.split(",")[0].trim();
  document.getElementById("formCheckpoint").style.display = "block";
}

function addCpExpenseLine(
  category = "accommodation",
  name = "",
  amount = "",
  isPaid = 0,
  notes = "",
  itemId = "",
  itemDate = "",
  itemTime = "",
  itemDur = 1,
) {
  const container = document.getElementById("cpExpensesContainer");
  const div = document.createElement("div");
  div.className = "hol-form-row";
  const isChecked = isPaid == 1 ? "checked" : "";

  div.innerHTML = `
        <div class="hol-form-inner">
            <select name="items[cat][]" class="pf-input hol-form-select">
                <option value="accommodation" ${category === "accommodation" ? "selected" : ""}>🏨</option>
                <option value="transport" ${category === "transport" ? "selected" : ""}>🚗</option>
                <option value="activity" ${category === "activity" ? "selected" : ""}>🎫</option>
            </select>
            <input type="text" name="items[name][]" class="pf-input hol-form-text" placeholder="${tr("hdl_js_ph_expense_name")}" value="${name}">
            <input type="number" step="0.01" name="items[amount][]" class="pf-input hol-form-number" placeholder="0.00" value="${amount}">
            
            <label class="hol-form-paid-label" title="${tr("hdl_paid")}">
                <input type="checkbox" ${isChecked} onchange="this.nextElementSibling.value = this.checked ? 1 : 0">
                <input type="hidden" name="items[paid][]" value="${isPaid}">
                <span class="hol-form-paid-text">${tr("hdl_paid")}</span>
            </label>
          <button type="button" class="btn-icon-action delete btn-remove-expense" onclick="this.parentElement.parentElement.remove()" title="${tr("btn_delete")}">🗑️</button>        </div>
        <div class="hol-form-subrow">
            <input type="text" name="items[notes][]" class="pf-input hol-form-notes-input hol-form-notes-full" placeholder="${tr("hdl_ph_notes")}" value="${notes}">
        </div>
        <input type="hidden" name="items[id][]" value="${itemId}">
        <input type="hidden" name="items[date][]" value="${itemDate}">
        <input type="hidden" name="items[time][]" value="${itemTime}">
        <input type="hidden" name="items[duration][]" value="${itemDur}">
    `;
  container.appendChild(div);
}

function deleteCheckpoint() {
  if (!confirm(tr("hdl_js_confirm_del_step"))) return;
  const form = document.getElementById("formCheckpoint");
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "action_delete";
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

// ============================================================================
// 5. RÉORDONNANCEMENT DES ÉTAPES (DRAG & DROP PC + FLÈCHES MOBILE)
// ============================================================================

// On sort cette fonction pour pouvoir l'appeler depuis les boutons fléchés sur mobile
function saveCheckpointOrder() {
  const locations = [
    ...document.querySelectorAll(".hol-checkpoint-draggable"),
  ].map((el) => el.getAttribute("data-location"));
  const holidayId = document.querySelector('input[name="holiday_id"]').value;

  const formData = new FormData();
  formData.append("holiday_id", holidayId);
  formData.append("locations", JSON.stringify(locations));

  fetch("/modules/holidays/includes/api/reorder_checkpoints.php", {
    method: "POST",
    body: formData,
  }).then(() => window.location.reload());
}

// Fonction appelée par les flèches Haut/Bas sur mobile
function moveStepMobile(btn, direction) {
  const item = btn.closest(".hol-checkpoint-draggable");
  const container = item.parentElement;

  if (
    direction === -1 &&
    item.previousElementSibling &&
    item.previousElementSibling.classList.contains("hol-checkpoint-draggable")
  ) {
    container.insertBefore(item, item.previousElementSibling);
    saveCheckpointOrder();
  } else if (
    direction === 1 &&
    item.nextElementSibling &&
    item.nextElementSibling.classList.contains("hol-checkpoint-draggable")
  ) {
    container.insertBefore(item, item.nextElementSibling.nextElementSibling);
    saveCheckpointOrder();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const checkpoints = document.querySelectorAll(".hol-checkpoint-draggable");
  const container = checkpoints[0]?.parentElement;
  if (!container) return;

  const isMobile = window.innerWidth <= 768;
  let draggedItem = null;

  checkpoints.forEach((item) => {
    // Si on est sur mobile, on supprime l'attribut draggable pour éviter les conflits de scroll
    if (isMobile) {
      item.removeAttribute("draggable");
      return;
    }

    item.addEventListener("dragstart", function (e) {
      draggedItem = this;
      setTimeout(() => (this.style.opacity = "0.4"), 0);
    });

    item.addEventListener("dragend", function () {
      setTimeout(() => {
        this.style.opacity = "1";
        draggedItem = null;
        saveCheckpointOrder();
      }, 0);
    });

    item.addEventListener("dragover", function (e) {
      e.preventDefault();
      const afterElement = getDragAfterElement(container, e.clientY);
      if (afterElement == null) {
        container.appendChild(draggedItem);
      } else {
        container.insertBefore(draggedItem, afterElement);
      }
    });
  });

  function getDragAfterElement(container, y) {
    const draggableElements = [
      ...container.querySelectorAll(
        '.hol-checkpoint-draggable:not([style*="opacity: 0.4"])',
      ),
    ];
    return draggableElements.reduce(
      (closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset: offset, element: child };
        } else {
          return closest;
        }
      },
      { offset: Number.NEGATIVE_INFINITY },
    ).element;
  }
});

// ============================================================================
// MOTEUR DRAG & DROP DU PLANNING
// ============================================================================

function closePlanningModal() {
  document.getElementById("planningModal").style.display = "none";
  document.body.classList.remove("no-scroll");
}

function openPlanningModal(step) {
  document.getElementById("planningModalTitle").innerText =
    tr("hdl_planning_title") + " : " + step.location_name;
  const container = document.getElementById("planningContainer");
  selectedItemIdForMove = null;

  let validItems = step.items.filter((it) => it.name !== "PF_TECHNICAL_POINT");

  if (!step.step_start_date || !step.step_end_date) {
    container.innerHTML = `<div style="text-align:center; padding:40px;"><h3>${tr("hdl_js_missing_dates_title")}</h3><p style="color:#64748b;">${tr("hdl_js_missing_dates_msg")}</p></div>`;
    document.getElementById("planningModal").style.display = "flex";
    return;
  }

  let datesToDisplay = [];
  let curr = new Date(step.step_start_date);
  let end = new Date(step.step_end_date);
  while (curr <= end) {
    datesToDisplay.push(curr.toISOString().split("T")[0]);
    curr.setDate(curr.getDate() + 1);
  }

  let html = `
        <div class="hol-planning-layout">
            <div class="hol-unmapped-zone" id="unmapped-pool" 
                 ondragover="allowDrop(event)" ondrop="handleDropEvent(event, '', '')"
                 onclick="handleZoneTap(event, '', '')">
                <div class="hol-unmapped-title" style="width:100%;">📥 ${tr("hdl_to_place")}</div>
            </div>
            <div class="hol-calendar-zone">
    `;

  datesToDisplay.forEach((dateStr) => {
    const dObj = new Date(dateStr);
    const dayName = dObj.toLocaleDateString(currentLang, { weekday: "short" });
    const dayNum = dObj.toLocaleDateString(currentLang, {
      day: "numeric",
      month: "short",
    });

    html += `
            <div class="hol-day-column">
                <div class="hol-calendar-day-header">
                    <div class="hol-cal-weekday">${dayName}</div>
                    <div class="hol-cal-date">${dayNum}</div>
                    <div id="plan-weather-${dateStr}" style="margin-top: 5px; display: flex; justify-content: center; min-height: 20px;"></div>
                </div>
                <div class="hol-time-slots-container">
        `;

    for (let h = 8; h <= 22; h++) {
      let hourStr = h.toString().padStart(2, "0") + ":00";
      html += `
                <div class="hol-time-slot" data-date="${dateStr}" data-time="${hourStr}" 
                     ondragover="allowDrop(event)" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)" 
                     ondrop="handleDropEvent(event, '${dateStr}', '${hourStr}')"
                     onclick="handleZoneTap(event, '${dateStr}', '${hourStr}')">
                    <span class="hol-slot-label">${hourStr}</span>
                </div>
            `;
    }
    html += `</div></div>`;
  });
  html += `</div></div>`;
  container.innerHTML = html;

  // 1. On détecte si on est sur mobile juste avant la boucle
  const isMobile = window.innerWidth <= 768;
  const dragAttr = isMobile ? "" : 'draggable="true"';

  validItems.forEach((it) => {
    let icon = "🏷️";
    let catClass = "cat-activity";
    if (it.category === "accommodation") {
      icon = "🏨";
      catClass = "cat-accommodation";
    }
    if (it.category === "transport") {
      icon = "🚗";
      catClass = "cat-transport";
    }

    const dur = it.duration || 1;
    const noteHtml = it.notes
      ? `<div class="hol-drag-note">${it.notes}</div>`
      : "";

    // 2. MODIFICATION ICI : On remplace le texte en dur draggable="true" par la variable ${dragAttr}
    const elHtml = `
            <div class="hol-drag-item ${catClass}" ${dragAttr} 
                 id="drag-item-${it.id}" data-id="${it.id}" 
                 style="--duration: ${dur};"
                 ondragstart="dragStart(event)" onclick="handleItemTap(event, ${it.id})">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 5px;">
                    <div class="hol-drag-title" style="flex:1;">${icon} ${it.name}</div>
                    <div class="hol-item-duration-controls">
                        <button class="hol-dur-btn" onclick="changeDuration(event, ${it.id}, -1)">-</button>
                        <span class="hol-dur-text" id="dur-text-${it.id}">${dur}h</span>
                        <button class="hol-dur-btn" onclick="changeDuration(event, ${it.id}, 1)">+</button>
                    </div>
                </div>
                ${noteHtml}
            </div>
        `;

    if (it.item_date && it.item_time && datesToDisplay.includes(it.item_date)) {
      const hourPrefix = it.item_time.substring(0, 2) + ":00";
      const targetSlot = container.querySelector(
        `.hol-time-slot[data-date="${it.item_date}"][data-time="${hourPrefix}"]`,
      );
      if (targetSlot) {
        targetSlot.insertAdjacentHTML("beforeend", elHtml);
        return;
      }
    }
    document
      .getElementById("unmapped-pool")
      .insertAdjacentHTML("beforeend", elHtml);
  });

  document.getElementById("planningModal").style.display = "flex";
  document.body.classList.add("no-scroll");

  datesToDisplay.forEach((dateStr) => {
    loadWeatherForPlanning(step.lat, step.lng, dateStr);
  });
}

function changeDuration(e, itemId, delta) {
  e.stopPropagation();
  const itemEl = document.getElementById("drag-item-" + itemId);
  let currentDur = parseInt(itemEl.style.getPropertyValue("--duration")) || 1;
  let newDur = currentDur + delta;
  if (newDur < 1) newDur = 1;
  if (newDur > 12) newDur = 12;
  itemEl.style.setProperty("--duration", newDur);
  document.getElementById(`dur-text-${itemId}`).innerText = newDur + "h";
  updateItemMemory(itemId, { duration: newDur });
  const formData = new FormData();
  formData.append("action", "update_item_duration");
  formData.append("item_id", itemId);
  formData.append("duration", newDur);
  fetch("/modules/holidays/includes/api/save_checkpoint.php", {
    method: "POST",
    body: formData,
  });
}

function handleItemTap(e, itemId) {
  e.stopPropagation();
  document
    .querySelectorAll(".hol-drag-item")
    .forEach((el) => el.classList.remove("selected-for-move"));
  if (selectedItemIdForMove === itemId) {
    selectedItemIdForMove = null;
  } else {
    selectedItemIdForMove = itemId;
    document
      .getElementById("drag-item-" + itemId)
      .classList.add("selected-for-move");
  }
}

function handleZoneTap(e, dateStr, timeStr) {
  if (selectedItemIdForMove) {
    handleDropLogic(selectedItemIdForMove, dateStr, timeStr, e.currentTarget);
    selectedItemIdForMove = null;
    document
      .querySelectorAll(".hol-drag-item")
      .forEach((el) => el.classList.remove("selected-for-move"));
  }
}

function dragStart(e) {
  e.dataTransfer.setData("text/plain", e.target.id);
  e.dataTransfer.effectAllowed = "move";
}
function allowDrop(e) {
  e.preventDefault();
}
function dragEnter(e) {
  e.preventDefault();
  let s = e.target.closest(".hol-time-slot");
  if (s) s.classList.add("drag-over");
}
function dragLeave(e) {
  let s = e.target.closest(".hol-time-slot");
  if (s) s.classList.remove("drag-over");
}

function handleDropEvent(e, dateStr, timeStr) {
  e.preventDefault();
  let slot = e.target.closest(".hol-time-slot");
  if (slot) slot.classList.remove("drag-over");
  const idStr = e.dataTransfer.getData("text/plain");
  const itemId = idStr.replace("drag-item-", "");
  const dropZone = slot || document.getElementById("unmapped-pool");
  handleDropLogic(itemId, dateStr, timeStr, dropZone);
}

function handleDropLogic(itemId, dateStr, timeStr, dropZone) {
  const draggedEl = document.getElementById("drag-item-" + itemId);
  if (dropZone && draggedEl) {
    dropZone.appendChild(draggedEl);
    updateItemMemory(itemId, { item_date: dateStr, item_time: timeStr });
    const formData = new FormData();
    formData.append("action", "update_item_datetime");
    formData.append("item_id", itemId);
    formData.append("item_date", dateStr);
    formData.append("item_time", timeStr);
    fetch("/modules/holidays/includes/api/save_checkpoint.php", {
      method: "POST",
      body: formData,
    });
  }
}

function updateItemMemory(itemId, changes) {
  MAP_POINTS.forEach((step) => {
    let item = step.items.find((i) => i.id == itemId);
    if (item) Object.assign(item, changes);
  });
}

function saveItemDateTime(itemId, dateStr, timeStr) {
  const formData = new FormData();
  formData.append("action", "update_item_datetime");
  formData.append("item_id", itemId);
  formData.append("item_date", dateStr);
  formData.append("item_time", timeStr);
  fetch("/modules/holidays/includes/api/save_checkpoint.php", {
    method: "POST",
    body: formData,
  }).catch((err) => console.error("Erreur:", err));
}

// ============================================================================
// MÉTÉO SPÉCIFIQUE AU HEADER DU PLANNING
// ============================================================================
async function loadWeatherForPlanning(lat, lng, dateStr) {
  const container = document.getElementById(`plan-weather-${dateStr}`);
  if (!container || !lat || !lng) return;

  try {
    const resp = await fetch(
      `/modules/holidays/includes/api/get_weather.php?lat=${lat}&lng=${lng}&date=${dateStr}`,
    );
    const res = await resp.json();

    if (res.success) {
      const info = getWeatherInfo(res.data.code);
      const approxSymbol = res.data.is_historical ? "~" : "";

      container.innerHTML = `
        <div class="pf-weather-badge" style="font-size: 0.65rem; padding: 2px 6px; ${res.data.is_historical ? "opacity: 0.8;" : ""}" title="${info.label}">
          <span class="pf-weather-icon">${info.icon}</span>
          <span>${approxSymbol}${Math.round(res.data.temp_min)}° / ${Math.round(res.data.temp_max)}°C</span>
        </div>
      `;
    }
  } catch (e) {
    console.error("Erreur météo planning", e);
  }
}

```

---

### 📄 Fichier : `modules/holidays/includes/api/geocode.php`
```php
<?php
// modules/holidays/includes/api/geocode.php

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

// 1. Validation de l'entrée
$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_q']);
    exit;
}

$limit = (int)($_GET['limit'] ?? 1);
if ($limit < 1) $limit = 1;
if ($limit > 10) $limit = 10; 

// 2. Normalisation pour le cache
$qNorm = mb_strtolower($q);
$qHash = hash('sha256', $qNorm);

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pf_geocode_cache (
            q_hash CHAR(64) PRIMARY KEY,
            q VARCHAR(255),
            lat DECIMAL(10, 7),
            lng DECIMAL(10, 7),
            display_name TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {}

// 3. Vérification du cache
try {
    $st = $pdo->prepare("SELECT lat, lng, display_name FROM pf_geocode_cache WHERE q_hash = ?");
    $st->execute([$qHash]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if ($limit === 1) {
            echo json_encode([
                'lat'          => (float)$row['lat'],
                'lng'          => (float)$row['lng'],
                'display_name' => $row['display_name'],
                'cached'       => true
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['results' => [[
                'lat'          => (float)$row['lat'],
                'lng'          => (float)$row['lng'],
                'display_name' => $row['display_name'],
                'cached'       => true
            ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
} catch (Throwable $e) {}

// 4. NOUVELLE API : Appel à Photon (Komoot) - Beaucoup plus tolérant pour les adresses !
$endpoint = 'https://photon.komoot.io/api/';
$params = http_build_query([
    'q'     => $q,
    'limit' => $limit,
    'lang'  => 'fr' // On force les résultats en français
], '', '&', PHP_QUERY_RFC3986);

$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'PachaFamily-Holidays/1.0',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $http !== 200) {
    http_response_code(502); 
    echo json_encode(['error' => 'geocode_failed']);
    exit;
}

$data = json_decode($body, true);

if (!isset($data['features']) || empty($data['features'])) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

// 5. Formatage des résultats (Photon renvoie les données différemment de Nominatim)
$results = [];
foreach ($data['features'] as $feature) {
    $prop = $feature['properties'];
    $coords = $feature['geometry']['coordinates']; // [lng, lat]
    
    // Construction propre du nom (Nom du lieu + Rue + Ville + Pays)
    $nameParts = [];
    if (!empty($prop['name'])) $nameParts[] = $prop['name'];
    
    if (!empty($prop['housenumber']) && !empty($prop['street'])) {
        $nameParts[] = $prop['housenumber'] . ' ' . $prop['street'];
    } elseif (!empty($prop['street'])) {
        $nameParts[] = $prop['street'];
    }
    
    if (!empty($prop['city'])) $nameParts[] = $prop['city'];
    elseif (!empty($prop['town'])) $nameParts[] = $prop['town'];
    elseif (!empty($prop['village'])) $nameParts[] = $prop['village'];
    
    if (!empty($prop['country'])) $nameParts[] = $prop['country'];
    
    $displayName = implode(', ', array_unique($nameParts));
    
    $results[] = [
        'lat' => round((float)$coords[1], 6),
        'lng' => round((float)$coords[0], 6),
        'display_name' => $displayName
    ];
}

// 6. Mise en cache du premier résultat
if (isset($results[0])) {
    try {
        $st = $pdo->prepare("
            INSERT INTO pf_geocode_cache (q_hash, q, lat, lng, display_name, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), display_name=VALUES(display_name), updated_at=NOW()
        ");
        $st->execute([$qHash, $q, $results[0]['lat'], $results[0]['lng'], $results[0]['display_name']]);
    } catch (Throwable $e) {}
}

// 7. Retour JSON pour ton Javascript
if ($limit === 1) {
    echo json_encode($results[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
```

---

### 📄 Fichier : `modules/holidays/includes/api/get_holiday_data.php`
```php
<?php
// modules/holidays/includes/api/get_holiday_data.php

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

try {
    // Si tu utilises pf_holidays au lieu de pf_holidays_ideas, change le nom de la table ici !
    $st = $pdo->prepare("SELECT * FROM pf_holidays_ideas WHERE id = ?");
    $st->execute([$id]);
    $it = $st->fetch(PDO::FETCH_ASSOC);

    if (!$it) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $it['id'] = (int)$it['id'];
    
    if (isset($it['lat'])) $it['lat'] = (float)$it['lat'];
    if (isset($it['lng'])) $it['lng'] = (float)$it['lng'];
    if (isset($it['ideal_days'])) $it['ideal_days'] = (int)$it['ideal_days'];
    
    echo json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
```

---

### 📄 Fichier : `modules/holidays/includes/api/get_weather.php`
```php
<?php
// modules/holidays/includes/api/get_weather.php
ini_set('display_errors', 0);
error_reporting(0); 

$basePath = '../../../../';
require_once $basePath . 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $basePath . 'includes/auth.php';

header('Content-Type: application/json');

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$date = $_GET['date'] ?? null;

if (!$lat || !$lng || !$date) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']); 
    exit;
}

$date = substr($date, 0, 10);
$today = new DateTime();
$targetDate = new DateTime($date);
$interval = $today->diff($targetDate);
$daysDiff = (int)$interval->format('%R%a');

// --- 🧠 GESTION INTELLIGENTE DU TEMPS (Prévisions vs Historique) ---
if ($daysDiff > 16) {
    // 1. VOYAGE LOINTAIN : Calcul de la moyenne sur 3 ans
    $currentYear = (int)$today->format('Y');
    $monthDay = $targetDate->format('m-d');
    if ($monthDay === '02-29') $monthDay = '02-28'; // Sécurité année bissextile

    $tempMaxSum = 0;
    $tempMinSum = 0;
    $validYearsCount = 0;
    $representativeCode = 0; // On gardera le code de l'année N-1

    for ($i = 1; $i <= 3; $i++) {
        $pastYear = $currentYear - $i;
        $searchDate = $pastYear . '-' . $monthDay;
        
        $url = "https://archive-api.open-meteo.com/v1/archive?latitude=$lat&longitude=$lng&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=$searchDate&end_date=$searchDate";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Timeout court pour ne pas ralentir le serveur
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $data = json_decode($res, true);
            if (isset($data['daily']['temperature_2m_max'][0])) {
                $tempMaxSum += $data['daily']['temperature_2m_max'][0];
                $tempMinSum += $data['daily']['temperature_2m_min'][0];
                
                if ($i === 1) { // On prend le temps qu'il a fait à N-1 pour l'icône
                    $representativeCode = $data['daily']['weather_code'][0];
                }
                $validYearsCount++;
            }
        }
    }

    // Si on a pu récupérer au moins une année de données
    if ($validYearsCount > 0) {
        echo json_encode([
            'success' => true,
            'data' => [
                'code' => $representativeCode,
                'temp_max' => round($tempMaxSum / $validYearsCount, 1), // Moyenne arrondie à 1 décimale
                'temp_min' => round($tempMinSum / $validYearsCount, 1),
                'is_historical' => true
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Archives indisponibles']);
    }
    exit; // On arrête l'exécution ici pour les voyages lointains

} elseif ($daysDiff < -2) {
    // 2. VOYAGE PASSÉ : On interroge les archives pour cette date précise
    $baseUrl = "https://archive-api.open-meteo.com/v1/archive";
    $searchDate = $date;
} else {
    // 3. FUTUR PROCHE (Prévisions fiables) : On interroge les prévisions
    $baseUrl = "https://api.open-meteo.com/v1/forecast";
    $searchDate = $date;
}

// --- APPEL API CLASSIQUE (Pour les prévisions et les voyages passés) ---
$url = "$baseUrl?latitude=$lat&longitude=$lng&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=$searchDate&end_date=$searchDate";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
curl_close($ch);

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Erreur API Open-Meteo']); 
    exit;
}

$data = json_decode($res, true);

if (isset($data['daily']['weather_code'][0])) {
    echo json_encode([
        'success' => true,
        'data' => [
            'code' => $data['daily']['weather_code'][0],
            'temp_max' => $data['daily']['temperature_2m_max'][0],
            'temp_min' => $data['daily']['temperature_2m_min'][0],
            'is_historical' => false
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée pour cette date']);
}
```

---

### 📄 Fichier : `modules/holidays/includes/api/reorder_checkpoints.php`
```php
<?php
// modules/holidays/includes/api/reorder_checkpoints.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

$holiday_id = (int)$_POST['holiday_id'];
$locations = json_decode($_POST['locations'] ?? '[]', true);

if ($holiday_id > 0 && is_array($locations)) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET sort_order = ? WHERE holiday_id = ? AND location_name = ?");
        
        foreach ($locations as $index => $loc) {
            // On met à jour toutes les dépenses de ce lieu avec son nouveau rang (0, 1, 2...)
            $stmt->execute([$index, $holiday_id, $loc]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
```

---

### 📄 Fichier : `modules/holidays/includes/api/save_checkpoint.php`
```php
<?php
// modules/holidays/includes/api/save_checkpoint.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

// INTERCEPTION AJAX : Sauvegarde du planning (Drag & Drop / Durée)
if (isset($_POST['action']) && in_array($_POST['action'], ['update_item_datetime', 'update_item_duration'])) {
    $itemId = (int)$_POST['item_id'];
    
    if ($_POST['action'] === 'update_item_datetime') {
        $itemDate = !empty($_POST['item_date']) ? $_POST['item_date'] : null;
        $itemTime = !empty($_POST['item_time']) ? $_POST['item_time'] : null;
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET item_date = ?, item_time = ? WHERE id = ?");
        $stmt->execute([$itemDate, $itemTime, $itemId]);
    } else {
        $duration = (int)$_POST['duration'];
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET duration = ? WHERE id = ?");
        $stmt->execute([$duration, $itemId]);
    }
    
    echo json_encode(['success' => true]);
    exit; // Crucial : on arrête le script ici !
}

$holiday_id = (int)$_POST['holiday_id'];
$location_name = trim($_POST['location_name']);
$lat = (float)$_POST['lat'];
$lng = (float)$_POST['lng'];
$old_sort_order = isset($_POST['old_sort_order']) && $_POST['old_sort_order'] !== '' ? (int)$_POST['old_sort_order'] : null;

// 1. SUPPRESSION
if (isset($_POST['action_delete']) && $_POST['action_delete'] === '1') {
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
    header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
    exit;
}

if ($holiday_id > 0 && !empty($location_name)) {
    try {
        $pdo->beginTransaction();

        // 2. DÉTERMINER L'ORDRE (Identifiant de l'étape)
        if ($old_sort_order !== null) {
            // Modification : on supprime l'ancien contenu de cette étape précise
            $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
            $target_order = $old_sort_order;
        } else {
            // Ajout : on place à la fin (Max + 1)
            $stmtMax = $pdo->prepare("SELECT MAX(sort_order) FROM pf_holidays_items WHERE holiday_id = ?");
            $stmtMax->execute([$holiday_id]);
            $max = $stmtMax->fetchColumn();
            $target_order = ($max !== null) ? (int)$max + 1 : 0;
        }

        // Récupération des dates de l'étape globale
        $step_start = !empty($_POST['step_start_date']) ? $_POST['step_start_date'] : null;
        $step_end = !empty($_POST['step_end_date']) ? $_POST['step_end_date'] : null;
        $is_return = isset($_POST['is_return']) ? 1 : 0; // NOUVEAU

        // 3. INSERTION DES LIGNES (16 Colonnes)
        $stmt = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, notes, item_date, item_time, step_start_date, step_end_date, duration, is_return) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $validItemsCount = 0;

        if (isset($_POST['items']['name'])) {
            foreach ($_POST['items']['name'] as $i => $raw_name) {
                $name = trim($raw_name);
                $amount_raw = $_POST['items']['amount'][$i];
                if ($name !== '' || $amount_raw !== '') {
                    $cat = $_POST['items']['cat'][$i] ?? 'activity';
                    $amount = (float)$amount_raw;
                    $paid = (isset($_POST['items']['paid'][$i]) && (int)$_POST['items']['paid'][$i] === 1) ? 1 : 0;
                    $note = trim($_POST['items']['notes'][$i] ?? '');
                    
                    $date = !empty($_POST['items']['date'][$i]) ? $_POST['items']['date'][$i] : null;
                    $time = !empty($_POST['items']['time'][$i]) ? $_POST['items']['time'][$i] : null;
                    $dur  = !empty($_POST['items']['duration'][$i]) ? (int)$_POST['items']['duration'][$i] : 1;

                    // Ajout de $is_return à la fin
                    $stmt->execute([$holiday_id, $cat, $name ?: tr('hdl_default_exp_name'), $amount, $paid, $location_name, $lat, $lng, $target_order, $note, $date, $time, $step_start, $step_end, $dur, $is_return]);
                    $validItemsCount++;
                }
            }
        }

        if ($validItemsCount === 0) {
            $stmt->execute([$holiday_id, 'activity', 'PF_TECHNICAL_POINT', 0, 1, $location_name, $lat, $lng, $target_order, '', null, null, $step_start, $step_end, 1, $is_return]);
        }

        // 4. GESTION DES FAVORIS
        if (isset($_POST['save_favorite']) && $_POST['save_favorite'] == '1') {
            $stmtFav = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'holiday_favorites'");
            $favs = json_decode($stmtFav->fetchColumn() ?: '[]', true);
            $exists = false;
            foreach ($favs as $f) { if ($f['name'] === $location_name) $exists = true; }
            if (!$exists) {
                $favs[] = ['name' => $location_name, 'lat' => $lat, 'lng' => $lng];
                $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('holiday_favorites', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")->execute([json_encode($favs)]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
exit;
```

---

### 📄 Fichier : `modules/holidays/includes/api/save_holiday.php`
```php
<?php
// modules/holidays/includes/api/save_holiday.php

// On remonte de 4 niveaux pour atteindre la racine (api -> includes -> holidays -> modules -> racine)
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login(); // Si cette fonction nécessite une redirection, gère-la dans auth.php

if (isset($_POST['action_delete']) && $_POST['action_delete'] == '1') {
    $stmt = $pdo->prepare("DELETE FROM pf_holidays WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header("Location: /holidays.php");
    exit;
}

$id = $_POST['id'] ?? '';
$title = $_POST['title'];
$period = $_POST['period_hint'];
$start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
$status = $_POST['status'];
$food = !empty($_POST['budget_food']) ? $_POST['budget_food'] : 0;
$extra = !empty($_POST['budget_extra']) ? $_POST['budget_extra'] : 0;
$notes = $_POST['notes'];

try {
    $pdo->beginTransaction();

    if ($id) {
        // UPDATE
        $sql = "UPDATE pf_holidays SET title=?, period_hint=?, start_date=?, end_date=?, status=?, budget_food=?, budget_extra=?, notes=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $id]);
    } else {
        // INSERT
        $sql = "INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes]);
        $id = $pdo->lastInsertId();
    }

    // GESTION DES ITEMS GLOBAUX (On ne supprime QUE les items qui n'ont pas de lieu défini !)
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND (location_name IS NULL OR location_name = '')")->execute([$id]);
    
    if (!empty($_POST['items']['name'])) {
        $stmtItem = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid) VALUES (?, ?, ?, ?, ?)");
        
        $count = count($_POST['items']['name']);
        for ($i = 0; $i < $count; $i++) {
            // Utilisation de "?? ''" pour sécuriser si la donnée n'est pas envoyée
            $cat = $_POST['items']['cat'][$i] ?? '';
            $name = trim($_POST['items']['name'][$i] ?? '');
            $amount = floatval($_POST['items']['amount'][$i] ?? 0);
            $paid = isset($_POST['items']['paid'][$i]) ? $_POST['items']['paid'][$i] : 0;

            if (!empty($name)) {
                $stmtItem->execute([$id, $cat, $name, $amount, $paid]);
            }
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur base de données : " . $e->getMessage());
}

// Redirection vers la page principale
header("Location: /holidays.php");
exit;
```

---

### 📄 Fichier : `modules/holidays/views/detail.php`
```php
<?php
// modules/holidays/views/detail.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) { 
    echo "<div class='pf-section'><p>".tr('err_invalid_holiday_id')."</p></div>"; 
    exit; 
}

// Récupération des données du voyage
$stmt = $pdo->prepare("
    SELECT h.*, 
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE((SELECT SUM(amount) FROM pf_holidays_items WHERE holiday_id = h.id), 0)) as total_cost,
           ((SELECT COALESCE(SUM(ABS(amount)), 0) FROM pf_expenses WHERE holiday_id = h.id) + (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND is_paid = 1)) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h WHERE h.id = ?
");
$stmt->execute([$id]);
$holiday = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupération des items (étapes et frais généraux)
$stmtItems = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ? ORDER BY sort_order ASC, id ASC");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Favoris pour le géocodage
$stmtFav = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'holiday_favorites'");
$favorites = json_decode($stmtFav->fetchColumn() ?: '[]', true);

$steps = [];
$generalItems = [];

foreach ($items as $it) {
    if ($it['location_name'] !== null) {
        $orderKey = $it['sort_order']; 
        if (!isset($steps[$orderKey])) {
            $steps[$orderKey] = [
                'location_name' => $it['location_name'],
                'lat' => (float)$it['lat'],
                'lng' => (float)$it['lng'],
                'sort_order' => $it['sort_order'],
                'step_start_date' => $it['step_start_date'],
                'step_end_date' => $it['step_end_date'],
                'is_return' => (int)$it['is_return'], 
                'total_amount' => 0,
                'items' => []
            ];
        }
        $steps[$orderKey]['items'][] = $it;
        $steps[$orderKey]['total_amount'] += (float)$it['amount'];
    } else {
        $generalItems[] = $it;
    }
}
ksort($steps); 
$mapPoints = array_values($steps);

// Affichage de la date
$dateDisplay = htmlspecialchars($holiday['period_hint'] ?? '');
if (empty($dateDisplay) && $holiday['start_date']) {
    $dateDisplay = date('d/m/Y', strtotime($holiday['start_date']));
    if ($holiday['end_date']) $dateDisplay .= ' → ' . date('d/m/Y', strtotime($holiday['end_date']));
}

$cost = (float)$holiday['total_cost'];
$paid = (float)$holiday['total_paid'];
$saved = (float)$holiday['total_saved'];
$leftToPay = max(0, $cost - $paid);
$pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
$pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  .mobile-only { display: none !important; }
  @media (max-width: 768px) {
    .desktop-only { display: none !important; }
    .mobile-only { display: flex !important; }
  }
</style>

<div class="pf-holidays-detail">
    
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="?tab=list" class="pf-btn btn-secondary pf-btn-small" style="width: fit-content; text-decoration: none;"><?= tr('btn_back') ?></a>
            <div style="display: flex; align-items: center; gap: 15px;">
                <h1 style="margin: 0; font-size: 1.8rem;"><?= htmlspecialchars($holiday['title']) ?></h1>
                <span class="hol-badge-status"><?= tr('hdl_status_' . $holiday['status']) ?></span>
            </div>
        </div>

        <div>
            <script id="holidayDataJson" type="application/json">
                <?= json_encode(['main' => $holiday, 'items' => $generalItems], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
            </script>
            <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="editHoliday(JSON.parse(document.getElementById('holidayDataJson').textContent))">
                <?= tr('btn_edit_bases') ?>
            </button>
        </div>
    </div>

    <div class="hol-summary-card">
        <div class="hol-summary-grid">
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_label_period') ?></div>
                <div class="hol-summary-value"><?= $dateDisplay ?: tr('hdl_dates_to_define') ?></div>
            </div>
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_label_budget_food_extras') ?></div>
                <div class="hol-summary-value">🍔 <?= number_format($holiday['budget_food'], 0) ?> € | 🎁 <?= number_format($holiday['budget_extra'], 0) ?> €</div>
            </div>
            <div class="hol-summary-item">
                <div class="hol-summary-label"><?= tr('hdl_total_budget') ?></div>
                <div class="hol-summary-value total"><?= number_format($cost, 0, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="hol-progress-bar">
            <div class="hol-progress-paid" style="width:<?= $pctPaid ?>%;" title="<?= tr('hdl_paid') ?>"></div>
            <div class="hol-progress-saved" style="width:<?= $pctSaved ?>%;" title="<?= tr('hdl_saved') ?>"></div>
        </div>
        <div class="hol-progress-labels">
            <span class="hol-label-paid">✓ <?= tr('hdl_paid') ?> : <?= number_format($paid, 0, ',', ' ') ?> €</span>
            <span class="hol-label-saved">💼 <?= tr('hdl_saved') ?> : <?= number_format($saved, 0, ',', ' ') ?> €</span>
            <span class="hol-label-left">⏳ <?= tr('hdl_left_to_pay') ?> : <?= number_format($leftToPay, 0, ',', ' ') ?> €</span>
        </div>
    </div>

    <div class="hol-layout-grid">
        <div class="hol-panel">
            <div class="hol-panel-header" style="flex-wrap: wrap; gap: 10px;">
                <div class="hol-panel-header-group">
                    <h3 style="margin:0;"><?= tr('hdl_map_itinerary') ?></h3>
                    <div class="hol-map-legend">
                        <span class="hol-legend-item" title="<?= tr('hdl_outbound') ?>"><span class="hol-legend-color aller"></span> <?= tr('hdl_outbound') ?></span>
                        <span class="hol-legend-item" title="<?= tr('hdl_return') ?>"><span class="hol-legend-color retour"></span> <?= tr('hdl_return') ?></span>
                    </div>
                </div>
                <button class="pf-btn pf-btn-small" onclick="openCheckpointModal('add')"><?= tr('hdl_btn_add_step') ?></button>
            </div>
                <div id="tripMap" style="flex:1; width:100%; min-height: 400px; background:#f1f5f9; position: relative; z-index: 1;"></div>        </div>

        <div class="hol-panel">
            <div class="hol-panel-header">
                <h3 style="margin:0;"><?= tr('hdl_step_details') ?></h3>
            </div>
            
            <div class="hol-panel-body">
                <?php if (empty($steps)): ?>
                    <p style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:40px;"><?= tr('hdl_no_steps') ?></p>
                <?php else: ?>

                    <?php if (!empty($generalItems) || $holiday['budget_food'] > 0 || $holiday['budget_extra'] > 0): ?>
                        <div class="hol-checkpoint" style="border-left-color: #64748b; background: #f8fafc; margin-bottom: 20px;">
                            <div class="hol-cp-header" style="background: transparent;">
                                <div class="hol-cp-info-group">
                                    <span style="font-size:1.4rem;">🌍</span>
                                    <div class="hol-cp-title"><?= tr('hdl_general_costs') ?></div>
                                </div>
                                <?php 
                                    $generalTotal = $holiday['budget_food'] + $holiday['budget_extra'];
                                    foreach ($generalItems as $gi) { $generalTotal += $gi['amount']; }
                                ?>
                                <div class="hol-cp-actions-group">
                                    <div class="hol-cp-total"><?= number_format($generalTotal, 2, ',', ' ') ?> €</div>
                                    <button onclick='editHoliday(JSON.parse(document.getElementById("holidayDataJson").textContent))' class="btn-icon-small" title="<?= tr('btn_edit') ?>">⚙️</button>
                                </div>
                            </div>

                            <div class="hol-cp-body">
                                <?php if ($holiday['budget_food'] > 0): ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name" style="color:#64748b;">🍔 <?= tr('hdl_food_bev') ?></span>
                                        <span><strong class="hol-expense-amount"><?= number_format($holiday['budget_food'], 2, ',', ' ') ?> €</strong><span class="status-pending">⏳</span></span>
                                    </div></div>
                                <?php endif; ?>
                                <?php if ($holiday['budget_extra'] > 0): ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name" style="color:#64748b;">🎁 <?= tr('hdl_extras') ?></span>
                                        <span><strong class="hol-expense-amount"><?= number_format($holiday['budget_extra'], 2, ',', ' ') ?> €</strong><span class="status-pending">⏳</span></span>
                                    </div></div>
                                <?php endif; ?>

                                <?php foreach ($generalItems as $it): 
                                    $icon = match($it['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                ?>
                                    <div class="hol-expense-wrapper"><div class="hol-expense-main">
                                        <span class="hol-expense-name"><?= $icon ?> <?= htmlspecialchars($it['name']) ?></span>
                                        <span>
                                            <strong class="hol-expense-amount"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                            <span class="<?= $it['is_paid'] ? 'status-paid' : 'status-pending' ?>"><?= $it['is_paid'] ? '✓' : '⏳' ?></span>
                                        </span>
                                    </div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($steps as $step): ?>
                        <div id="step-card-<?= $step['sort_order'] ?>" class="hol-checkpoint hol-checkpoint-draggable" draggable="true" data-location="<?= htmlspecialchars($step['location_name']) ?>">
                            <div class="hol-cp-header">
                                <div class="hol-cp-info-group">
                                    <span class="desktop-only" style="color:#94a3b8; font-size:1.1rem; cursor:grab; user-select:none;">☰</span>
                                        <div class="mobile-only" style="flex-direction:column; gap:4px; margin-right:8px;">
                                            <button type="button" onclick="moveStepMobile(this, -1)" class="btn-icon-small" style="width:24px!important; height:24px!important; font-size:0.6rem;">▲</button>
                                            <button type="button" onclick="moveStepMobile(this, 1)" class="btn-icon-small" style="width:24px!important; height:24px!important; font-size:0.6rem;">▼</button>
                                        </div>
                                    <div class="hol-cp-title" onclick="panMapTo(<?= $step['lat'] ?>, <?= $step['lng'] ?>)" title="<?= htmlspecialchars($step['location_name']) ?>">
                                        📍 <?= htmlspecialchars($step['location_name']) ?>
                                        <?php if (!empty($step['is_return'])): ?>
                                            <span style="background: #fff7ed; color: #ea580c; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 5px; border: 1px solid #ffedd5; vertical-align: middle;">🏁 <?= tr('hdl_return') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($step['step_start_date']) && !empty($step['step_end_date'])): ?>
                                            <div class="hol-date-weather-wrapper">
                                                <span class="hol-step-dates">
                                                    <?= tr('hdl_from') ?> <?= date('d/m', strtotime($step['step_start_date'])) ?> <?= tr('hdl_to') ?> <?= date('d/m', strtotime($step['step_end_date'])) ?>
                                                </span>
                                                <div class="hol-weather-info"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="hol-cp-actions-group">
                                    <div class="hol-cp-total"><?= number_format($step['total_amount'], 2, ',', ' ') ?> €</div>
                                    <button onclick='openPlanningModal(<?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small" title="<?= tr('hdl_view_planning') ?>">📅</button>
                                    <button onclick='openCheckpointModal("edit", <?= htmlspecialchars(json_encode($step), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon-small" title="<?= tr('btn_edit') ?>">✏️</button>
                                </div>
                            </div>

                            <div class="hol-cp-body">
                                <?php 
                                    $visibleItemsCount = 0;
                                    foreach ($step['items'] as $it): 
                                        if ($it['name'] === 'PF_TECHNICAL_POINT') continue; 
                                        $visibleItemsCount++;
                                        $icon = match($it['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                ?>
                                        <div class="hol-expense-wrapper">
                                            <div class="hol-expense-main">
                                                <span class="hol-expense-name"><?= $icon ?> <?= htmlspecialchars($it['name']) ?></span>
                                                <span>
                                                    <strong class="hol-expense-amount"><?= number_format($it['amount'], 2, ',', ' ') ?> €</strong>
                                                    <span class="<?= $it['is_paid'] ? 'status-paid' : 'status-pending' ?>" title="<?= $it['is_paid'] ? tr('hdl_paid') : tr('hdl_to_pay') ?>"><?= $it['is_paid'] ? '✓' : '⏳' ?></span>
                                                </span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                                
                                <?php if ($visibleItemsCount === 0): ?>
                                    <div class="hol-empty-step">📍 <?= tr('hdl_passing_point') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($holiday['notes'])): ?>
    <div class="hol-summary-card" style="margin-top: 24px; padding: 25px; border-left: 5px solid #f59e0b;">
        <h3 style="margin: 0 0 15px 0; font-size: 1.2rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            📝 <?= tr('hdl_label_notes') ?>
        </h3>
        <div style="font-size: 0.95rem; color: #334155; white-space: pre-wrap; line-height: 1.6;">
            <?= htmlspecialchars($holiday['notes']) ?>
        </div>
    </div>
    <?php endif; ?>

</div> 

<div id="checkpointModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="cpModalTitle" style="margin:0;">📍 <?= tr('hdl_btn_add_step') ?></h3>
            <button type="button" onclick="closeCheckpointModal()" class="pf-modal-close">&times;</button>
        </div>
        
        <div id="cpSearchBlock" style="margin-bottom:20px;">
            <?php if (!empty($favorites)): ?>
            <div style="margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php foreach($favorites as $fav): ?>
                    <button type="button" class="pf-btn btn-secondary" style="padding:4px 10px; font-size:0.8rem; border-radius:20px;" onclick="selectPlace(<?= $fav['lat'] ?>, <?= $fav['lng'] ?>, '<?= htmlspecialchars(addslashes($fav['name'])) ?>')">
                        ⭐ <?= htmlspecialchars($fav['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <label class="pf-label"><?= tr('hdl_search_location') ?></label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchPlaceInput" class="pf-input" placeholder="<?= tr('hdl_ph_search') ?>" onkeypress="if(event.key === 'Enter') { searchPlace(); return false; }">
                <button type="button" class="pf-btn btn-secondary" onclick="searchPlace()">🔍</button>
            </div>
            <div id="searchResults" style="margin-top:10px; max-height:200px; overflow-y:auto;"></div>
        </div>

        <form action="/modules/holidays/includes/api/save_checkpoint.php" method="POST" id="formCheckpoint" style="display:none; border-top:1px solid #e2e8f0; padding-top:20px;">
            <input type="hidden" name="holiday_id" value="<?= $id ?>">
            <input type="hidden" name="old_location_name" id="cp_old_name">
            <input type="hidden" name="lat" id="cp_lat">
            <input type="hidden" name="lng" id="cp_lng">
            <input type="hidden" name="old_sort_order" id="cp_old_sort_order">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label class="pf-label"><?= tr('hdl_label_step_name') ?></label>
                <input type="text" name="location_name" id="cp_name" class="pf-input" style="font-weight:bold; color:var(--primary);" required>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px; background:#f8fafc; padding:12px; border-radius:8px;">
                <div class="form-group" style="flex:1;">
                    <label class="pf-label"><?= tr('hdl_label_arrival') ?></label>
                    <input type="date" name="step_start_date" id="cp_start_date" class="pf-input">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="pf-label"><?= tr('hdl_label_departure') ?></label>
                    <input type="date" name="step_end_date" id="cp_end_date" class="pf-input">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:flex; align-items:center; cursor:pointer; color:#ea580c; font-weight:600;">
                    <input type="checkbox" name="is_return" id="cp_is_return" value="1" style="margin-right:8px;">
                    🏁 <?= tr('hdl_return') ?>
                </label>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <label class="pf-label" style="margin:0;"><?= tr('hdl_planned_expenses') ?></label>
                <button type="button" class="pf-btn btn-secondary pf-btn-small" onclick="addCpExpenseLine()"><?= tr('hdl_btn_add_expense') ?></button>
            </div>

            <div id="cpExpensesContainer" style="margin-bottom:15px;"></div>

            <div style="margin-bottom: 20px;">
                <label style="display:flex; align-items:center; cursor:pointer; font-size:0.85rem; color:#475569;">
                    <input type="checkbox" name="save_favorite" value="1" style="margin-right:8px;">
                    ⭐ <?= tr('hdl_save_fav') ?>
                </label>
            </div>

            <div class="modal-footer" style="padding-top:15px; border-top:1px solid #e2e8f0;">
                <button type="button" onclick="deleteCheckpoint()" id="btnDeleteCp" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; display:none;"><?= tr('btn_delete') ?></button>
                <button type="button" onclick="closeCheckpointModal()" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<div id="planningModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 550px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="planningModalTitle" style="margin:0; color:var(--primary);">📅 <?= tr('hdl_planning_title') ?></h3>
            <button type="button" onclick="closePlanningModal()" class="pf-modal-close">&times;</button>
        </div>
        <div id="planningContainer" style="width: 100%;"></div>
        <div class="modal-footer">
            <button type="button" onclick="closePlanningModal()" class="pf-btn btn-secondary"><?= tr('btn_close') ?></button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET VARIABLES ---
window.MAP_POINTS = <?= json_encode($mapPoints ?? []) ?>;
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";

window.I18N = {
    ...(window.I18N || {}),
    'hdl_js_search_loading': "<?= tr('hdl_js_search_loading') ?>",
    'hdl_js_no_result': "<?= tr('hdl_js_no_result') ?>",
    'hdl_js_confirm_del_trip': "<?= tr('hdl_js_confirm_del_trip') ?>",
    'hdl_js_confirm_del_step': "<?= tr('hdl_js_confirm_del_step') ?>",
    'hdl_js_step_label': "<?= tr('hdl_js_step_label') ?>",
    'hdl_js_ph_expense_name': "<?= tr('hdl_js_ph_expense_name') ?>",
    'hdl_js_delete_line': "<?= tr('btn_delete') ?>",
    'hdl_planning_title': "<?= tr('hdl_planning_title') ?>",
    'hdl_to_place': "<?= tr('hdl_to_place') ?>",
    'hdl_js_missing_dates_title': "<?= tr('hdl_js_missing_dates_title') ?>",
    'hdl_js_missing_dates_msg': "<?= tr('hdl_js_missing_dates_msg') ?>",
    'hdl_modal_title': "<?= tr('hdl_modal_title') ?>",
    'hdl_js_edit_step': "<?= tr('hdl_js_edit_step') ?>",
    'hdl_ph_notes': "<?= tr('hdl_ph_notes') ?>",
    'hdl_btn_add_step': "<?= tr('hdl_btn_add_step') ?>",
    'hdl_quick_edit_title': "<?= tr('hdl_quick_edit_title') ?>",
    
    // --- NOUVELLES CLÉS MÉTÉO ICI ---
    'weather_sunny': "<?= tr('weather_sunny') ?>",
    'weather_cloudy': "<?= tr('weather_cloudy') ?>",
    'weather_rainy': "<?= tr('weather_rainy') ?>",
    'weather_snowy': "<?= tr('weather_snowy') ?>",
    'weather_forecast': "<?= tr('weather_forecast') ?>",
    'weather_historical': "<?= tr('weather_historical') ?>"
};

// Fallback de sécurité pour s'assurer que les modales peuvent toujours se fermer
window.closeCheckpointModal = window.closeCheckpointModal || function() {
    const modal = document.getElementById('checkpointModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};

window.closePlanningModal = window.closePlanningModal || function() {
    const modal = document.getElementById('planningModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};
</script>

<script src="/modules/holidays/holidays.js"></script>
```

---

### 📄 Fichier : `modules/holidays/views/list.php`
```php
<?php
// modules/holidays/views/list.php

// 1. Gestion du filtrage par année
$selectedYear = $_GET['y'] ?? date('Y');

$yearStmt = $pdo->query("SELECT DISTINCT YEAR(start_date) as yr FROM pf_holidays WHERE start_date IS NOT NULL ORDER BY yr DESC");
$availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $availableYears)) {
    $availableYears[] = date('Y');
    rsort($availableYears);
}

$whereSQL = "";
$params = [];
if ($selectedYear !== 'all') {
    $whereSQL = "WHERE YEAR(h.start_date) = ? OR h.start_date IS NULL";
    $params[] = $selectedYear;
}

// 2. Récupération des voyages + Calculs
$sql = "
    SELECT h.*, 
           (COALESCE(h.budget_food, 0) + COALESCE(h.budget_extra, 0) + COALESCE(SUM(hi.amount), 0)) as total_cost,
           ((SELECT COALESCE(SUM(ABS(amount)), 0) FROM pf_expenses WHERE holiday_id = h.id) + 
            (SELECT COALESCE(SUM(amount), 0) FROM pf_holidays_items WHERE holiday_id = h.id AND is_paid = 1)) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM pf_savings WHERE holiday_id = h.id) as total_saved
    FROM pf_holidays h
    LEFT JOIN pf_holidays_items hi ON h.id = hi.holiday_id
    $whereSQL
    GROUP BY h.id
    ORDER BY COALESCE(start_date, '2999-12-31') ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active = array_filter($holidays, fn($h) => in_array($h['status'], ['draft', 'planned', 'booked']));
$history = array_filter($holidays, fn($h) => in_array($h['status'], ['passed', 'archived']));

$globalLeftToPay = 0;
foreach ($active as $h) {
    $globalLeftToPay += max(0, (float)$h['total_cost'] - (float)$h['total_paid']);
}
?>

<div class="pf-holidays">
    <div class="pf-holidays__titlebar">
    <div class="hol-title-group">
        <h1 class="hol-main-title"><?= tr('hdl_main_title') ?> ✈️</h1>
        
        <div class="hol-filters-row">
            <select onchange="window.location.href='?tab=list&y='+this.value" class="pf-input hol-year-select">
                <option value="all" <?= $selectedYear === 'all' ? 'selected' : '' ?>><?= tr('hdl_filter_all') ?></option>
                <?php foreach($availableYears as $yr): ?>
                    <option value="<?= $yr ?>" <?= (string)$selectedYear === (string)$yr ? 'selected' : '' ?>><?= tr('hdl_filter_year') ?> <?= $yr ?></option>
                <?php endforeach; ?>
            </select>

            <div class="hol-badge-left-to-pay">
                <span>⏳ <?= tr('hdl_left_to_pay_label') ?> (<?= $selectedYear === 'all' ? tr('hdl_filter_all') : $selectedYear ?>) :</span>
                <span class="hol-badge-amount"><?= number_format($globalLeftToPay, 0, ',', ' ') ?> €</span>
            </div>
        </div>
    </div>

    <div class="hol-actions-group">
        <button class="pf-btn hol-add-btn" onclick="openHolidayModal('add')">+ <?= tr('hdl_btn_create') ?></button>
    </div>
</div>

    <section class="pf-section">
        <div class="hol-ideas-grid">
            <?php if (empty($active)): ?>
                <p style="color:var(--text-muted); font-style:italic;"><?= tr('hdl_no_active_trips') ?></p>
            <?php endif; ?>

            <?php foreach ($active as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($history)): ?>
    <section class="pf-section" style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        <h3 style="color:var(--text-muted);"><?= tr('hdl_history_title') ?></h3>
        <div class="hol-ideas-grid" style="opacity: 0.7;">
            <?php foreach ($history as $h): ?>
                <?php renderHolidayCard($h, $pdo); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
// --- 1. SÉCURISATION TRADUCTIONS ET LANGUE ---
window.appLang = document.documentElement.lang === "ca" ? "ca-ES" : "fr-FR";

window.I18N = {
    ...(window.I18N || {}),
    'hdl_js_search_loading': "<?= tr('hdl_js_search_loading') ?>",
    'hdl_js_no_result': "<?= tr('hdl_js_no_result') ?>",
    'hdl_js_confirm_del_trip': "<?= tr('hdl_js_confirm_del_trip') ?>",
    'hdl_js_confirm_del_step': "<?= tr('hdl_js_confirm_del_step') ?>",
    'hdl_js_step_label': "<?= tr('hdl_js_step_label') ?>",
    'hdl_js_ph_expense_name': "<?= tr('hdl_js_ph_expense_name') ?>",
    'hdl_js_delete_line': "<?= tr('btn_delete') ?>",
    'hdl_planning_title': "<?= tr('hdl_planning_title') ?>",
    'hdl_to_place': "<?= tr('hdl_to_place') ?>",
    'hdl_js_missing_dates_title': "<?= tr('hdl_js_missing_dates_title') ?>",
    'hdl_js_missing_dates_msg': "<?= tr('hdl_js_missing_dates_msg') ?>",
    'hdl_modal_title': "<?= tr('hdl_modal_title') ?>",
    'hdl_quick_edit_title': "<?= tr('hdl_quick_edit_title') ?>"
};

// Fallback de sécurité pour s'assurer que la modale peut toujours se fermer
window.closeHolidayModal = window.closeHolidayModal || function() {
    const modal = document.getElementById('holidayModal');
    if(modal) modal.style.display = 'none';
    document.body.classList.remove('no-scroll');
};
</script>

<script src="/modules/holidays/holidays.js"></script>

<?php
function renderHolidayCard($h, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM pf_holidays_items WHERE holiday_id = ?");
    $stmt->execute([$h['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $json = htmlspecialchars(json_encode(['main' => $h, 'items' => $items]), ENT_QUOTES, 'UTF-8');
    $dateDisplay = htmlspecialchars($h['period_hint'] ?? '');
    
    if (empty($dateDisplay) && $h['start_date']) {
        $dateDisplay = date('d/m/Y', strtotime($h['start_date']));
        if ($h['end_date']) $dateDisplay .= ' → ' . date('d/m/Y', strtotime($h['end_date']));
    }
    
    $statusClass = match($h['status']) {
        'booked' => 'bg-green-100 text-green-800',
        'planned' => 'bg-blue-100 text-blue-800',
        'passed' => 'bg-gray-100 text-gray-600',
        default => 'bg-yellow-50 text-yellow-800'
    };

    $cost = (float)$h['total_cost'];
    $paid = (float)$h['total_paid'];
    $saved = (float)$h['total_saved']; 
    $leftToPay = max(0, $cost - $paid);
    
    $pctPaid = $cost > 0 ? min(100, ($paid / $cost) * 100) : 0;
    $pctSaved = $cost > 0 ? min(100 - $pctPaid, ($saved / $cost) * 100) : 0;

    $statusLabel = tr('hdl_status_' . $h['status']);

    echo "
    <div class='hol-idea-card' style='display: flex; flex-direction: column;'>
        <div class='hol-idea-card__head' style='display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 10px;'>
            <div style='flex:1;'>
                <h3 style='margin:0; font-size:1.15rem; color:#0f172a;'>
                    <a href='?tab=holiday_detail&id={$h['id']}' style='text-decoration:none; color:inherit;'>
                        ".htmlspecialchars($h['title'])."
                    </a>
                </h3>
                <span style='font-size:0.7rem; padding:3px 8px; border-radius:12px; font-weight:bold; display:inline-block; margin-top:6px;' class='$statusClass'>
                    ".strtoupper($statusLabel)."
                </span>
            </div>
            <div style='display:flex; gap:5px;'>
                <button onclick='editHoliday($json)' class='pf-btn btn-secondary' style='padding:6px;'>✏️</button>
                <a href='?tab=holiday_detail&id={$h['id']}' class='pf-btn' style='padding:6px; text-decoration:none;'>👁️</a>
            </div>
        </div>
        
        <div class='hol-idea-meta' style='margin-bottom:15px;'>
            <span>🗓️ ".($dateDisplay ?: tr('hdl_dates_to_define'))."</span>
        </div>
        
        <div style='margin-top:auto; padding-top:15px; border-top:1px solid #f1f5f9;'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>
                <span style='font-size:0.85rem; color:#64748b;'>".tr('hdl_total_budget')."</span>
                <span style='font-size:1.1rem; font-weight:bold; color:#1e293b;'>".number_format($cost, 0, ',', ' ')." €</span>
            </div>

            <div style='width:100%; height:8px; background:#e2e8f0; border-radius:4px; margin-bottom:12px; display:flex; overflow:hidden;'>
                <div style='width:{$pctPaid}%; background:#10b981; transition:width 0.3s ease;'></div>
                <div style='width:{$pctSaved}%; background:#3b82f6; transition:width 0.3s ease;'></div>
            </div>

            <div style='display:grid; grid-template-columns: 1fr 1fr; gap:8px; font-size:0.8rem;'>
                <div style='color:#10b981; font-weight:600;'>✓ ".tr('hdl_paid')." : ".number_format($paid, 0, ',', ' ')." €</div>
                <div style='color:#3b82f6; font-weight:600; text-align:right;'>💼 ".tr('hdl_saved')." : ".number_format($saved, 0, ',', ' ')." €</div>
                <div style='color:#ef4444; font-weight:700; font-size:0.85rem; grid-column: span 2; padding-top: 4px; border-top: 1px dashed #fca5a5;'>
                    ⏳ ".tr('hdl_left_to_pay')." : ".number_format($leftToPay, 0, ',', ' ')." €
                </div>
            </div>
        </div>
    </div>
    ";
}
?>
```

---

### 📄 Fichier : `modules/holidays/views/modal.php`
```php
<div id="holidayModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 800px; width: 95%;">
        <h3 id="modalTitle" class="pf-modal-title"><?= tr('hdl_modal_title') ?></h3>
        
        <form action="/modules/holidays/includes/api/save_holiday.php" method="POST" id="holidayForm">
            <input type="hidden" name="id" id="inp_id">
            <input type="hidden" name="action" value="save">

            <div class="form-row">
                <div style="flex: 2;">
                    <label class="pf-label"><?= tr('hdl_label_name') ?></label>
                    <input type="text" name="title" id="inp_title" class="pf-input" placeholder="<?= tr('hdl_ph_name') ?>" required>
                </div>
                <div style="flex: 1;">
                    <label class="pf-label"><?= tr('hdl_label_status') ?></label>
                    <select name="status" id="inp_status" class="pf-input">
                        <option value="draft"><?= tr('hdl_status_draft') ?> ✏️</option>
                        <option value="planned"><?= tr('hdl_status_planned') ?> 📅</option>
                        <option value="booked"><?= tr('hdl_status_booked') ?> ✅</option>
                        <option value="passed"><?= tr('hdl_status_passed') ?> 👋</option>
                        <option value="archived"><?= tr('hdl_status_archived') ?> 🗄️</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label"><?= tr('hdl_label_period') ?></label>
                    <input type="text" name="period_hint" id="inp_period" class="pf-input" placeholder="<?= tr('hdl_ph_period') ?>">
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1">
                        <label class="pf-label"><?= tr('hdl_label_from') ?></label>
                        <input type="date" name="start_date" id="inp_start" class="pf-input">
                    </div>
                    <div style="flex:1">
                        <label class="pf-label"><?= tr('hdl_label_to') ?></label>
                        <input type="date" name="end_date" id="inp_end" class="pf-input">
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div class="hol-columns-wrapper">
                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#2563eb;">🚗 <?= tr('hdl_cat_transport') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('transport')" title="<?= tr('hdl_add_transport') ?>">＋</button>
                    </div>
                    <div id="list_transport" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#059669;">🏨 <?= tr('hdl_cat_accommodation') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('accommodation')" title="<?= tr('hdl_add_accommodation') ?>">＋</button>
                    </div>
                    <div id="list_accommodation" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#d97706;">🎫 <?= tr('hdl_cat_activity') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('activity')" title="<?= tr('hdl_add_activity') ?>">＋</button>
                    </div>
                    <div id="list_activity" class="dynamic-list"></div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div class="form-row">
                <div>
                    <label class="pf-label">🍔 <?= tr('hdl_label_budget_food') ?></label>
                    <input type="number" step="0.01" name="budget_food" id="inp_food" class="pf-input" placeholder="0.00">
                </div>
                <div>
                    <label class="pf-label">🎁 <?= tr('hdl_label_budget_extras') ?></label>
                    <input type="number" step="0.01" name="budget_extra" id="inp_extra" class="pf-input" placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label class="pf-label"><?= tr('hdl_label_notes') ?></label>
                <textarea name="notes" id="inp_notes" class="pf-input" rows="2" placeholder="<?= tr('hdl_ph_notes') ?>"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="deleteHoliday()" id="btn_delete" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; margin-right:auto; display:none;"><?= tr('btn_delete') ?></button>
                <button type="button" onclick="closeHolidayModal()" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>
```

---

### 📄 Fichier : `modules/home/home.css`
```css
/* modules/home/home.css */

/* === 1. CONFIGURATION DU FOND & DU BODY === */
body.pf-home {
  /* Image de fond desktop */
  background-image: url("/modules/home/assets/img/background.jpg");
  background-size: cover;
  background-position: center center;
  background-attachment: fixed; /* Effet parallax léger */
  background-repeat: no-repeat;

  /* Couleur de texte par défaut sur la home (Blanc) */
  color: #ffffff;
}

/* Voile sombre pour garantir la lisibilité du texte blanc */
body.pf-home::before {
  content: "";
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5); /* Bleu nuit semi-transparent */
  z-index: -1; /* Derrière le contenu */
}

/* === 2. HEADER SPÉCIFIQUE (Translucide) === */
body.pf-home .pf-header {
  background: rgba(36, 59, 83, 0.85); /* Ton ancien bleu sombre */
  backdrop-filter: blur(8px); /* Effet verre dépoli */
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

/* Adaptation des couleurs des liens du header sur fond sombre */
body.pf-home .pf-logo {
  color: #ffffff;
}
body.pf-home .pf-burger-btn {
  color: #ffffff;
}

body.pf-home .pf-nav-link {
  color: rgba(255, 255, 255, 0.8);
}
body.pf-home .pf-nav-link:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}
body.pf-home .pf-nav-link--active {
  background: rgba(255, 255, 255, 0.9);
  color: #243b53; /* Texte foncé sur fond blanc */
}

/* Badge utilisateur */
body.pf-home .pf-user-badge {
  color: rgba(255, 255, 255, 0.9);
}
body.pf-home .pf-logout-btn {
  color: #fca5a5;
} /* Rouge clair */
body.pf-home .pf-logout-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: #fca5a5;
}

/* === 3. HERO SECTION (Message de Bienvenue) === */
.pf-hero {
  margin-top: 40px;
  margin-bottom: 60px;
  text-align: right; /* Aligné à droite comme demandé */
}

.pf-hero h1 {
  color: #ffffff;
  font-size: 2.5rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4); /* Ombre pour détacher le texte de l'image */
  margin-bottom: 12px;
}

.pf-hero p {
  color: #e2e8f0; /* Gris très clair */
  font-size: 1.2rem;
  font-weight: 500;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}

/* === 4. GRILLE DES MODULES === */

/* Titre de section "Modules" */
body.pf-home .pf-modules-grid {
  /* On ajoute un titre visuel ou on espace simplement */
  margin-top: 20px;
}

/* Les cartes restent blanches, donc on force le texte foncé à l'intérieur */
.pf-module-card {
  background: rgba(255, 255, 255, 0.95); /* Presque opaque */
  border: none;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  color: var(--text-main); /* Retour à la couleur sombre globale */
}

.pf-module-card:hover {
  background: #ffffff;
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

/* On s'assure que les titres et textes DANS les cartes ne sont pas blancs */
.pf-module-card h3.pf-card-title {
  color: var(--text-main);
}
.pf-module-card p.pf-card-desc {
  color: var(--text-muted);
}

/* === 5. MOBILE RESPONSIVE === */
@media (max-width: 768px) {
  body.pf-home {
    /* Image spécifique mobile */
    background-image: url("/modules/home/assets/img/background_m.jpg");
    background-attachment: scroll; /* Fixed bug souvent sur mobile, scroll est mieux */
  }

  /* Sur mobile, on recentre le texte pour l'équilibre */
  .pf-hero {
    text-align: center;
    margin-top: 20px;
    margin-bottom: 40px;
  }

  .pf-hero h1 {
    font-size: clamp(1.8rem, 6vw, 2.5rem); /* Typographie fluide */
  }

  /* Ajustement header mobile */
  body.pf-home .pf-header {
    padding: 0 16px;
  }
}

```

---

### 📄 Fichier : `test-engine.js`
```javascript
/**
 * PachaFamily - Test Engine 🦙
 * Moteur de tests automatisés (E2E) - Version Anti-Flakiness & QA Exhaustive
 */

const PachaTestEngine = {
  arena: document.getElementById("test-arena"),
  reportBox: document.getElementById("test-report"),
  doc: null,

  // ==========================================
  // 🛠️ HELPERS (BLINDÉS POUR LE QA)
  // ==========================================

  wait: (ms) => new Promise((r) => setTimeout(r, ms)),

  log: function (msg, icon = "ℹ️") {
    this.reportBox.value += `[${new Date().toLocaleTimeString()}] ${icon} ${msg}\n`;
    this.reportBox.scrollTop = this.reportBox.scrollHeight;
  },

  assert: function (cond, msgPass, msgFail) {
    this.log(cond ? msgPass : msgFail || msgPass, cond ? "✅" : "❌");
  },

  // 📡 Intercepte les erreurs de l'application dans l'iframe pour les afficher dans le rapport
  injectIframeErrorCatcher: function () {
    if (!this.arena.contentWindow) return;

    this.arena.contentWindow.onerror = (msg, url, line) => {
      this.log(`Erreur App (Ligne ${line}): ${msg}`, "🔥");
    };

    const origError = this.arena.contentWindow.console.error;
    this.arena.contentWindow.console.error = (...args) => {
      this.log(`Console Error: ${args.join(" ")}`, "🔥");
      origError.apply(this.arena.contentWindow.console, args);
    };
  },

  // 🛡️ Attend le rechargement de la page de manière stricte
  actionAndWaitForReload: async function (actionFn, timeout = 5000) {
    return new Promise((resolve, reject) => {
      let reloaded = false;

      this.arena.onload = () => {
        reloaded = true;
        this.doc = this.arena.contentWindow.document;
        this.arena.contentWindow.confirm = () => true;
        this.arena.contentWindow.alert = () => true;
        this.injectIframeErrorCatcher();
        resolve();
      };

      // Exécute l'action et gère le rejet si elle plante
      Promise.resolve(actionFn()).catch((err) => reject(err));

      setTimeout(() => {
        if (!reloaded) {
          this.log(
            "⚠️ Le rechargement n'a pas eu lieu. Suite du test...",
            "⏱️",
          );
          resolve();
        }
      }, timeout);
    });
  },

  load: async function (url) {
    this.log(`Chargement de la page : ${url}`, "🔄");
    return new Promise((resolve) => {
      this.arena.onload = () => {
        this.doc = this.arena.contentWindow.document;
        this.arena.contentWindow.confirm = () => true;
        this.arena.contentWindow.alert = () => true;
        this.injectIframeErrorCatcher();
        resolve();
      };
      this.arena.src = url;
    });
  },

  get: function (sel) {
    return this.doc.querySelector(sel);
  },

  // 🖱️ Clic strict : Si l'élément n'existe pas, on DÉCLENCHE UNE ERREUR FATALE
  click: async function (sel, delay = 500) {
    const el = typeof sel === "string" ? this.get(sel) : sel;
    if (!el) {
      throw new Error(
        `Clic impossible : l'élément '${typeof sel === "string" ? sel : "DOM Object"}' est introuvable.`,
      );
    }

    this.arena.contentWindow.confirm = () => true;
    this.arena.contentWindow.alert = () => true;
    el.click();
    await this.wait(delay);
    return true;
  },

  fill: function (sel, val) {
    const el = this.get(sel);
    if (!el) throw new Error(`Remplissage impossible : '${sel}' introuvable.`);
    el.value = val;
  },

  select: async function (sel, val, delay = 300) {
    const el = this.get(sel);
    if (!el) throw new Error(`Sélection impossible : '${sel}' introuvable.`);
    el.value = val;
    el.dispatchEvent(new Event("change"));
    await this.wait(delay);
  },

  findInTable: function (text) {
    return Array.from(this.doc.querySelectorAll("td")).find((el) =>
      el.textContent.includes(text),
    );
  },

  // ==========================================
  // 🧪 SCÉNARIO EXHAUSTIF : SUIVI MENSUEL (MODALES & CRUD)
  // ==========================================
  testBudgetSuiviExhaustive: async function () {
    this.reportBox.value = "";
    this.log("=== 🚀 DÉBUT EXHAUSTIF : SUIVI MENSUEL ===", "INFO");

    await this.load("budget.php?tab=suivi");
    await this.wait(1000);

    // --- 1. GESTION DE L'ÉTAT (CLÔTURE / RÉOUVERTURE) ---
    this.log("🔍 Test 1 : État du mois (Clôture / Réouverture)");
    let btnReopenInitial = this.get(
      'form input[value="reopen_month"] ~ button[type="submit"]',
    );
    if (btnReopenInitial) {
      this.log(
        "🔒 Mois actuellement fermé. Réouverture forcée pour les tests...",
        "WARN",
      );
      await this.actionAndWaitForReload(
        async () => await this.click(btnReopenInitial),
      );
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP
      this.assert(
        this.get('form input[value="close_month"]') !== null,
        "Mois rouvert.",
      );
    }

    // --- 2. UI TOGGLES ---
    this.log("🔍 Test 2 : Toggle 'Charges à venir'");
    const toggleBtn = this.get(
      "button[onclick*=\"toggleDiv('pendingDetailsList')\"]",
    );
    if (toggleBtn) {
      await this.click(toggleBtn);
      const pendingList = this.get("#pendingDetailsList");
      this.assert(
        pendingList && pendingList.style.display !== "none",
        "Liste des charges déployée.",
      );
    }

    // --- 3. MODALE CSV ---
    this.log("🔍 Test 3 : Modale Import CSV");
    const btnCsv = this.get('button[onclick*="importCsvModal"]');
    if (btnCsv) {
      await this.click(btnCsv);
      const csvModal = this.get("#importCsvModal");
      this.assert(
        csvModal &&
          (csvModal.classList.contains("is-open") ||
            csvModal.style.display === "flex"),
        "Modale CSV ouverte.",
      );

      // Fermeture
      const closeCsvBtn = csvModal.querySelector(
        ".pf-modal-close, button[onclick*='closeSuiviModal']",
      );
      if (closeCsvBtn) await this.click(closeCsvBtn);
      this.assert(
        !csvModal.classList.contains("is-open") &&
          csvModal.style.display !== "flex",
        "Modale CSV fermée.",
      );
    }

    // --- 4. MODALE SNAPSHOT (UPDATE BALANCE) ---
    this.log("🔍 Test 4 : Modale Snapshot (Solde Bancaire)");
    const btnSnapshot = this.get('button[onclick*="snapshotModal"]');
    if (btnSnapshot) {
      await this.click(btnSnapshot);
      this.assert(this.get("#snapshotModal"), "Modale Snapshot ouverte.");

      this.fill('#snapshotModal input[name="snapshot_amount"]', "1337.00");
      this.log("Soumission du nouveau solde...", "INFO");
      await this.actionAndWaitForReload(async () => {
        await this.click('#snapshotModal button[type="submit"]');
      });

      const pageText = this.doc.body.innerText;
      this.assert(
        pageText.includes("1 337"),
        "Solde mis à jour et formaté à 1 337 €.",
      );
    }

    // --- 5. CRUD DÉPENSE (AJOUT / ÉDITION / SUPPRESSION) ---
    this.log("🔍 Test 5 : CRUD Dépense Manuelle");
    const btnAdd = this.get(".btn-add-item");

    if (btnAdd) {
      // 5A. AJOUT
      this.log("Création d'une nouvelle dépense...");
      await this.click(btnAdd);
      await this.select("#modalCatSelect", "Autres");
      this.fill("#modalAmount", "77.77");
      this.fill("#modalLabelInput", "E2E_QA_TEST_EXPENSE");

      await this.actionAndWaitForReload(async () => {
        await this.click('#manualExpenseModal button[type="submit"]');
      });

      let newExpense = this.findInTable("E2E_QA_TEST_EXPENSE");
      this.assert(
        newExpense !== undefined,
        "Dépense 'E2E_QA_TEST_EXPENSE' trouvée dans le DOM.",
      );

      if (newExpense) {
        // 5B. ÉDITION
        this.log("✏️ Édition de la dépense...");
        const row = newExpense.closest("tr");

        // 🛡️ NOUVEAU : Sécurité anti-crash
        if (!row) {
          throw new Error(
            "L'élément trouvé n'est pas dans une ligne de tableau (<tr> manquant). Le test est corrompu.",
          );
        }

        const btnEdit = row.querySelector('button[onclick*="openEditModal"]');
        if (btnEdit) {
          await this.click(btnEdit);
          this.fill("#modalAmount", "88.88");
          await this.actionAndWaitForReload(async () => {
            await this.click('#manualExpenseModal button[type="submit"]');
          });

          const editedExpense = this.findInTable("E2E_QA_TEST_EXPENSE");
          this.assert(
            editedExpense &&
              editedExpense.closest("tr").innerHTML.includes("88"),
            "Montant mis à jour à 88.88€.",
          );
        }

        // 5C. SUPPRESSION (Gère pachaConfirm custom modal)
        this.log("🧹 Teardown : Suppression de la dépense...");
        const rowToDel = this.findInTable("E2E_QA_TEST_EXPENSE").closest("tr");
        const btnDel = rowToDel.querySelector("button.delete");

        if (btnDel) {
          await this.actionAndWaitForReload(async () => {
            await this.click(btnDel); // Déclenche pachaConfirm
            await this.wait(500); // Attend l'animation de la modale custom

            const confirmBtn = this.get("#confirm-ok");
            if (confirmBtn) {
              await this.click(confirmBtn); // Valide la suppression (fetch AJAX)
            }
          }, 3000);

          const checkGone = this.findInTable("E2E_QA_TEST_EXPENSE");
          this.assert(
            checkGone === undefined,
            "Base de données propre ! Dépense supprimée.",
          );
        }
      }
    } else {
      this.log("Bouton '.btn-add-item' introuvable.", "❌");
    }

    // --- 6. CLÔTURE ET RÉOUVERTURE DU MOIS (TEARDOWN STATE) ---
    this.log("🔍 Test 6 : Clôture du mois");
    let btnClose = this.get(
      'form input[value="close_month"] ~ button[type="submit"]',
    );
    if (btnClose) {
      this.log("Verrouillage du mois...");
      await this.actionAndWaitForReload(async () => await this.click(btnClose));
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP (Mois suivant)

      this.log("Retour au mois précédent pour vérification...");
      await this.actionAndWaitForReload(
        async () =>
          await this.click(".suivi-nav-group a.suivi-btn-nav:first-child"),
      );

      this.assert(
        this.get('form input[value="reopen_month"]') !== null,
        "Mois clôturé avec succès.",
      );

      // Teardown
      this.log("🧹 Teardown : Restauration du mois (Réouverture)");
      await this.actionAndWaitForReload(
        async () =>
          await this.click(
            'form input[value="reopen_month"] ~ button[type="submit"]',
          ),
      );
      await this.wait(1500); // ⏱️ Attente de la 2e redirection JS du PHP

      this.assert(
        this.get('form input[value="close_month"]') !== null,
        "Mois rouvert avec succès.",
      );
    }

    this.log("=== 🏁 FIN DU SCÉNARIO EXHAUSTIF ===", "INFO");
  },

  // ==========================================
  // 🧪 SCÉNARIO 2 : BUDGET PRÉVISIONNEL
  // ==========================================
  testBudgetPrev: async function () {
    this.reportBox.value = "";
    this.log("=== 🚀 DÉBUT DU SCÉNARIO : BUDGET PRÉVISIONNEL ===", "INFO");

    await this.load("budget.php?tab=budget_prev");
    await this.wait(1000);

    this.log("🔍 Étape 1 : Test du mode Somme flottant...", "INFO");
    await this.click("#fabSumMode");
    this.assert(
      this.doc.body.classList.contains("sum-mode-active"),
      "Le mode somme s'active.",
    );

    const firstSalaryInput = this.get('input[data-field="salary"]');
    if (firstSalaryInput) {
      await this.click(firstSalaryInput, 500);
      const sumValue = this.get("#sumResultValue").innerText;
      this.assert(
        sumValue !== "0,00 €" && sumValue !== "0 €",
        `Le total interactif affiche : ${sumValue}`,
      );
    }

    await this.click(".pf-sum-close");
    this.assert(
      !this.doc.body.classList.contains("sum-mode-active"),
      "Le mode somme se désactive.",
    );

    this.log("🔍 Étape 2 : Test de sauvegarde de la note...", "INFO");
    const noteArea = this.get("#monthNoteArea");
    if (noteArea) {
      const oldNote = noteArea.value;
      this.fill("#monthNoteArea", "🤖 Test Auto");
      await this.click('button[onclick^="saveGenericNote"]', 1500);

      const indicator = this.get("#note-save-indicator");
      this.assert(indicator !== null, "Indicateur de sauvegarde affiché.");

      this.fill("#monthNoteArea", oldNote);
      await this.click('button[onclick^="saveGenericNote"]', 500);
    }

    const uniqueCatName = "TEST_CAT_" + Math.floor(Math.random() * 10000);
    this.log(
      `🔍 Étape 3 : Création de la catégorie '${uniqueCatName}'...`,
      "INFO",
    );

    await this.click('button[onclick*="addCatModal"]');
    this.fill('#addCatModal input[name="name"]', uniqueCatName);
    await this.select('#addCatModal select[name="target"]', "vers commune");

    await this.actionAndWaitForReload(async () => {
      await this.click('#addCatModal button[type="submit"]');
    });

    const newCatCell = Array.from(
      this.doc.querySelectorAll(".prev-alloc-table td, .prev-alloc-table div"),
    ).find((el) => el.textContent.includes(uniqueCatName));
    this.assert(
      newCatCell !== undefined,
      `SUCCÈS : '${uniqueCatName}' insérée !`,
    );

    if (newCatCell) {
      this.log("🧹 Étape 4 : Nettoyage de la BDD...", "WARN");
      const row = newCatCell.closest("tr");
      const delBtn = row.querySelector(".delete");

      if (delBtn) {
        await this.actionAndWaitForReload(async () => {
          await this.click(delBtn);
        });

        const checkGone = Array.from(
          this.doc.querySelectorAll(".prev-alloc-table td"),
        ).find((td) => td.textContent.includes(uniqueCatName));
        this.assert(
          checkGone === undefined,
          "NETTOYAGE PARFAIT : Ligne effacée.",
        );
      }
    }

    this.log("=== 🏁 FIN DU SCÉNARIO ===", "INFO");
  },
};

// ==========================================
// ROUTEUR DU LABORATOIRE ET BOUTON COPIER
// ==========================================
document.getElementById("btn-run-test")?.addEventListener("click", async () => {
  const selectedTest = document.getElementById("test-selector").value;
  const btnRun = document.getElementById("btn-run-test");

  btnRun.disabled = true;
  btnRun.style.opacity = "0.5";

  try {
    switch (selectedTest) {
      case "budget_suivi_exhaustive":
        await PachaTestEngine.testBudgetSuiviExhaustive();
        break;
      case "budget_prev":
        await PachaTestEngine.testBudgetPrev();
        break;
      default:
        PachaTestEngine.log(`Scénario non implémenté : ${selectedTest}`, "⚠️");
    }
  } catch (error) {
    PachaTestEngine.log(`Erreur JS critique : ${error.message}`, "❌");
    console.error(error);
  }

  btnRun.disabled = false;
  btnRun.style.opacity = "1";
});

// 📋 Bouton "Copier"
document
  .getElementById("btn-copy-report")
  ?.addEventListener("click", async () => {
    const textArea = document.getElementById("test-report");
    const reportText = textArea.value;
    const successMsg = window.I18N
      ? window.I18N.tests_report_copied || "Rapport copié !"
      : "Rapport copié !";

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(reportText);
        alert(successMsg);
      } else {
        textArea.select();
        document.execCommand("copy");
        window.getSelection().removeAllRanges();
        alert(successMsg);
      }
    } catch (err) {
      alert("Erreur lors de la copie du rapport.");
      console.error(err);
    }
  });

```

---

### 📄 Fichier : `tests.php`
```php
<?php
require_once 'includes/auth.php'; 
require_once 'includes/db.php';
require_once 'includes/i18n.php';
require_once 'header.php';
?>

<style>
    /* Styles spécifiques au Laboratoire (Look IDE) */
    .lab-select {
        padding: 6px 32px 6px 12px; /* Espace à droite pour la flèche */
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        cursor: pointer;
        appearance: none; /* Supprime la flèche par défaut du navigateur */
        -webkit-appearance: none;
        -moz-appearance: none;
        /* Ajout d'une flèche SVG sur-mesure */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 14px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    
    .lab-select:hover {
        border-color: #94a3b8;
    }
    
    .lab-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .lab-container {
        max-width: 1400px; 
        margin: 0 auto; 
        display: flex; 
        gap: 20px;
        height: 75vh; /* Prend une bonne partie de l'écran */
    }
    .lab-panel {
        flex: 1; 
        display: flex; 
        flex-direction: column;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    .lab-header {
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 15px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .lab-title { margin: 0; font-size: 1.1rem; color: #334155; display: flex; align-items: center; gap: 8px; }
    
    /* Boutons de contrôle minimalistes */
    .lab-controls { display: flex; gap: 8px; }
    .btn-lab {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        cursor: pointer;
        border: none;
        font-weight: 600;
        transition: 0.2s;
        display: flex; align-items: center; gap: 5px;
    }
    .btn-play { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .btn-play:hover { background: #dbeafe; }
    .btn-copy { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .btn-copy:hover { background: #dcfce7; }

    /* Terminal Console */
    .lab-console {
        flex: 1;
        width: 100%;
        padding: 15px;
        background: #1e1e1e;
        color: #10b981;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        border: none;
        resize: none;
        outline: none;
    }
</style>

<section class="pf-section lab-container">
    
    <div class="lab-panel">
        <div class="lab-header">
            <h2 class="lab-title">🧪 <?= tr('tests_title') ?></h2>
            <div class="lab-controls" style="display: flex; align-items: center; gap: 10px;">
                <select id="test-selector" class="lab-select">
                    <optgroup label="💰 Module Budget">
                        <option value="budget_suivi_exhaustive">Suivi Mensuel (Modales & CRUD)</option>
                        <option value="budget_prev">Budget Prévisionnel</option>
                        <option value="budget_epargne" disabled>Épargne (Bientôt...)</option>
                        <option value="budget_recap" disabled>Récapitulatif (Bientôt...)</option>
                    </optgroup>
                    <optgroup label="📅 Autres Modules">
                        <option value="calendar" disabled>Calendrier Familial (Bientôt...)</option>
                        <option value="holidays" disabled>Voyages & Roadtrips (Bientôt...)</option>
                        <option value="gifts" disabled>Liste de Cadeaux (Bientôt...)</option>
                    </optgroup>
                </select>
                
                <button id="btn-run-test" class="btn-lab btn-play">▶ Lancer le test</button>
                <button id="btn-test-all" class="btn-lab btn-play" style="opacity: 0.5; cursor: not-allowed;" title="Bientôt disponible">▶ Tout lancer</button>
                <button id="btn-copy-report" class="btn-lab btn-copy">📋 <?= tr('tests_copy_report') ?></button>
            </div>
        </div>
        <textarea id="test-report" class="lab-console" readonly><?= tr('tests_waiting') ?></textarea>
    </div>

    <div class="lab-panel">
        <div class="lab-header" style="background: #f1f5f9; justify-content: center;">
            <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Live Preview</span>
        </div>
        <iframe id="test-arena" style="width: 100%; height: 100%; border: none;"></iframe>
    </div>

</section>

<script src="test-engine.js"></script>

<?php require_once 'footer.php'; ?>
```

---

