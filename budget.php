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
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 20px;">
            <h1 style="margin: 0;"><?= tr('budget_main_header') ?></h1>
            <button class="btn-settings-gear" onclick="openBudgetSettings()" title="<?= tr('settings') ?>">
                ⚙️
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
<div class="budget-settings-backdrop" id="modal-budget-settings">
    <div class="budget-settings-modal">
        <div class="bs-header">
            <h3>⚙️ <?= tr('budget_settings_title') ?></h3>
            <button class="bs-close" onclick="closeBudgetSettings()">×</button>
        </div>
        <div class="bs-layout">
            <div class="bs-sidebar">
                <button class="bs-tab-btn active" onclick="switchBsTab('accounts', this)"><?= tr('bs_tab_accounts') ?></button>
                <button class="bs-tab-btn" onclick="switchBsTab('categories', this)"><?= tr('bs_tab_categories') ?></button>
                <button class="bs-tab-btn" onclick="switchBsTab('rules', this)"><?= tr('bs_tab_rules') ?></button>
                <button class="bs-tab-btn" onclick="switchBsTab('salaries', this)"><?= tr('bs_tab_salaries') ?></button>
                <button class="bs-tab-btn" onclick="switchBsTab('csv', this)"><?= tr('bs_tab_csv') ?></button>
            </div>
            
            <div class="bs-content">
                
                <div id="pane-accounts" class="bs-pane active">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">
                        <h4 class="bs-section-title" style="margin: 0; border: none; padding: 0;"><?= tr('bs_tab_accounts') ?></h4>
                        
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
                            <span><?= tr('bs_currency_label') ?> :</span>
                            <select id="bs-currency-select" class="pf-input" style="width: 75px; padding: 3px 6px; font-size: 0.8rem; height: auto; cursor: pointer; background: var(--bg-page);" onchange="updateBudgetCurrency(this)">
                                <option value="€">EUR (€)</option>
                                <option value="$">USD ($)</option>
                                <option value="£">GBP (£)</option>
                                <option value="CHF">CHF</option>
                            </select>
                        </div>
                    </div>

                    <div id="bs-list-accounts">⏳ <?= tr('loading') ?></div>
                    
                    <hr style="margin: 1.5rem 0; border:0; border-bottom:1px solid var(--border-light);">
                    
                    <h5 class="bs-section-title" style="font-size:1rem;"><?= tr('bs_add_account_title') ?></h5>
                    <form id="form-add-account" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <input type="text" name="name" class="pf-input" placeholder="<?= tr('bs_account_name_ph') ?>" required style="flex: 1; min-width: 150px; padding: 0.4rem 0.75rem;">
                        <select name="type" class="pf-input" style="width: 130px; padding: 0.4rem 0.75rem;">
                            <option value="checking"><?= tr('bs_type_checking') ?></option>
                            <option value="savings"><?= tr('bs_type_savings') ?></option>
                        </select>
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-categories" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_categories') ?></h4>
                    <div id="bs-list-categories">⏳ <?= tr('loading') ?></div>
                    
                    <hr style="margin: 1.5rem 0; border:0; border-bottom:1px solid var(--border-light);">
                    
                    <h5 class="bs-section-title" style="font-size:1rem;"><?= tr('bs_add_category_title') ?></h5>
                    <form id="form-add-category" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="code" class="pf-input" placeholder="<?= tr('bs_cat_code_ph') ?>" required style="width: 120px; padding: 0.4rem 0.75rem;">
                        <input type="text" name="label" class="pf-input" placeholder="<?= tr('bs_cat_label_ph') ?>" required style="flex: 1; min-width: 150px; padding: 0.4rem 0.75rem;">
                        
                        <select name="type" class="pf-input" style="width: 120px; padding: 0.4rem 0.75rem;">
                            <option value="Expense"><?= tr('bs_type_expense') ?></option>
                            <option value="Income"><?= tr('bs_type_income') ?></option>
                            <option value="Savings"><?= tr('bs_type_savings_cat') ?></option>
                        </select>
                        
                        <select name="icon" class="pf-input" style="width: 65px; padding: 0.4rem 0.2rem; font-size: 1.1rem; cursor: pointer; text-align: center;">
                            <option value="📌">📌</option>
                            <option value="🛒">🛒</option>
                            <option value="🥖">🥖</option>
                            <option value="🍽️">🍽️</option>
                            <option value="⛽">⛽</option>
                            <option value="🚗">🚗</option>
                            <option value="🚆">🚆</option>
                            <option value="🏠">🏠</option>
                            <option value="⚡">⚡</option>
                            <option value="💧">💧</option>
                            <option value="⚕️">⚕️</option>
                            <option value="🎒">🎒</option>
                            <option value="👕">👕</option>
                            <option value="📱">📱</option>
                            <option value="🎮">🎮</option>
                            <option value="✈️">✈️</option>
                            <option value="🎁">🎁</option>
                            <option value="🐶">🐶</option>
                            <option value="💵">💵</option>
                            <option value="🐷">🐷</option>
                            <option value="📈">📈</option>
                        </select>
                        
                        <input type="color" name="color" value="#3b82f6" style="width: 38px; height: 38px; padding: 0; border: 1px solid var(--border-light); border-radius: 6px; cursor: pointer;">
                        
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-rules" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_rules') ?></h4>
                    
                    <input type="text" id="input-search-rules" class="bs-rule-search" placeholder="🔍 Rechercher un mot-clé (ex: AUCHAN)..." onkeyup="filterRules()">
                    
                    <div id="bs-list-rules">⏳ <?= tr('loading') ?></div>

                    <hr style="margin: 1.5rem 0; border:0; border-bottom:1px solid var(--border-light);">
                    
                    <h5 class="bs-section-title" style="font-size:1rem;"><?= tr('bs_add_rule_title') ?></h5>
                    <form id="form-add-rule" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="keyword" class="pf-input" placeholder="<?= tr('bs_rule_keyword_ph') ?>" required style="flex: 1; min-width: 150px; padding: 0.4rem 0.75rem;">
                        
                        <select name="category" id="select-rule-category" class="pf-input" required style="width: 180px; padding: 0.4rem 0.75rem;">
                            </select>
                        
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-salaries" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_salaries') ?> (<?= date('Y') ?>)</h4>
                    <div id="bs-list-salaries">⏳ <?= tr('loading') ?></div>
                </div>

                <div id="pane-csv" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_csv_title') ?></h4>
                    <p class="pf-muted-tiny" style="margin-top:0; margin-bottom:15px;"><?= tr('bs_csv_desc') ?></p>
                    
                    <div id="csv-drop-zone" style="border: 2px dashed var(--border-light, #ccc); border-radius: var(--radius); padding: 30px; text-align: center; cursor: pointer; margin-bottom: 25px; transition: all 0.2s;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">📥</div>
                        <h5 style="margin: 0 0 5px 0;"><?= tr('bs_csv_drop_title') ?></h5>
                        <p class="pf-muted-tiny" style="margin: 0;"><?= tr('bs_csv_drop_desc') ?></p>
                        <input type="file" id="csv_file_input" accept=".csv" style="display: none;">
                    </div>

                    <div id="csv-preview-container" style="display:none; background: var(--bg-soft, #f8fafc); padding: 15px; border-radius: var(--radius); border: 1px dashed var(--primary); margin-bottom: 25px;">
                    </div>
                    
                    <h5 style="margin: 0 0 15px 0; border-bottom: 1px solid var(--border-light); padding-bottom: 5px;">
                        <?= tr('bs_csv_settings_title') ?>
                    </h5>

                    <form id="form-csv-mapping" onsubmit="saveCsvMapping(event)">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_delimiter') ?></label>
                                <select name="csv_delimiter" id="csv_delimiter" class="pf-input">
                                    <option value=";"><?= tr('bs_csv_delim_semi') ?></option>
                                    <option value=","><?= tr('bs_csv_delim_comma') ?></option>
                                    <option value="\t"><?= tr('bs_csv_delim_tab') ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_date_format') ?></label>
                                <select name="csv_date_format" id="csv_date_format" class="pf-input">
                                    <option value="d/m/Y">JJ/MM/AAAA</option>
                                    <option value="Y-m-d">AAAA-MM-JJ</option>
                                </select>
                            </div>
                        </div>

                        <h5 style="margin: 15px 0 10px; font-size: 0.95rem;">
                            <?= tr('bs_csv_col_index') ?> 
                            <small style="color:var(--text-muted); font-weight:normal;"><?= tr('bs_csv_col_index_help') ?></small>
                        </h5>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_col_date') ?></label>
                                <input type="number" min="0" name="csv_col_date" id="csv_col_date" class="pf-input" value="0" required>
                            </div>
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_col_label') ?></label>
                                <input type="number" min="0" name="csv_col_label" id="csv_col_label" class="pf-input" value="1" required>
                            </div>
                        </div>

                        <div style="background: var(--bg-panel); padding: 10px; border-radius: var(--radius); border: 1px solid var(--border-light); margin-bottom: 15px;">
                            <label class="pf-label"><?= tr('bs_csv_amount_mgmt') ?></label>
                            <select name="csv_amount_type" id="csv_amount_type" class="pf-input" style="margin-bottom: 10px;" onchange="toggleCsvAmountCols(this.value)">
                                <option value="single"><?= tr('bs_csv_amount_single') ?></option>
                                <option value="split"><?= tr('bs_csv_amount_split') ?></option>
                            </select>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label class="pf-label" id="lbl_col_debit"><?= tr('bs_csv_col_amount') ?></label>
                                    <input type="number" min="0" name="csv_col_debit" id="csv_col_debit" class="pf-input" value="8" required>
                                </div>
                                <div id="wrapper_col_credit" style="display:none;">
                                    <label class="pf-label"><?= tr('bs_csv_col_credit') ?></label>
                                    <input type="number" min="0" name="csv_col_credit" id="csv_col_credit" class="pf-input" value="9">
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label class="pf-label"><?= tr('bs_csv_col_ref') ?></label>
                            <input type="number" min="0" name="csv_col_ref" id="csv_col_ref" class="pf-input" value="3">
                        </div>

                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-secondary">💾 <?= tr('btn_save_format') ?></button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// --- AUTO-SCROLL DES ONGLETS MOBILES ---
document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.querySelector('.budget-tabs-container');
    const activeTab = document.querySelector('.tab-item.active');
    if (tabsContainer && activeTab) {
        if (tabsContainer.scrollWidth > tabsContainer.clientWidth) {
            const scrollPos = activeTab.offsetLeft - (tabsContainer.offsetWidth / 2) + (activeTab.offsetWidth / 2);
            tabsContainer.scrollTo({ left: scrollPos, behavior: 'smooth' });
        }
    }
});

// --- PARAMÈTRES BUDGET (MODALE & ONGLETS) ---
function openBudgetSettings() {
    document.getElementById('modal-budget-settings').classList.add('show');
    document.body.classList.add('no-scroll');
    loadBudgetSettingsData();
}

function closeBudgetSettings() {
    document.getElementById('modal-budget-settings').classList.remove('show');
    document.body.classList.remove('no-scroll');
}

document.getElementById('modal-budget-settings')?.addEventListener('click', (e) => {
    if (e.target.id === 'modal-budget-settings') closeBudgetSettings();
});

function switchBsTab(tabId, btnEl) {
    document.querySelectorAll('.bs-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.bs-pane').forEach(p => p.classList.remove('active'));
    
    btnEl.classList.add('active');
    document.getElementById('pane-' + tabId).classList.add('active');
}

async function loadBudgetSettingsData() {
    try {
        const response = await pachaFetch('/modules/budget/includes/api/settings.php?action=get_all', { method: 'GET' });

        if (!response.success) {
            throw new Error(response.error || "<?= addslashes(tr('error_occured')) ?>");
        }

        renderAccounts(response.data.accounts);
        renderCategories(response.data.categories);
        populateRuleCategories(response.data.categories);
        renderRules(response.data.rules);
        renderSalaries(response.data.salaries, response.data.year);
        const currencySelect = document.getElementById('bs-currency-select');
        if (currencySelect && response.data.currency) {
            currencySelect.value = response.data.currency;
        }

    } catch (err) {
        console.error("Erreur chargement paramètres :", err);
        const errorMsg = `<div class="pf-alert pf-alert--error" style="margin-top:10px;">❌ Erreur : ${err.message}</div>`;
        ['accounts', 'categories', 'rules', 'salaries'].forEach(id => {
            const el = document.getElementById('bs-list-' + id);
            if (el) el.innerHTML = errorMsg;
        });
    }
}

// ==========================================================================
// --- RENDU DES ONGLETS (PARAMÈTRES BUDGET) ---
// ==========================================================================

// 1. ONGLET : COMPTES BANCAIRES
function renderAccounts(accounts) {
    const container = document.getElementById('bs-list-accounts');
    if (!container) return;

    if (!accounts || accounts.length === 0) {
        container.innerHTML = '<em style="color:var(--text-muted)"><?= addslashes(tr("bs_empty_accounts")) ?></em>';
        return;
    }
    
    let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">';
    accounts.forEach(acc => {
        const typeBadge = acc.account_type === 'checking' ? '<?= addslashes(tr("bs_type_checking")) ?>' : '<?= addslashes(tr("bs_type_savings")) ?>';
        const defaultBadge = acc.is_default == 1 ? '<span style="font-size:0.7em; background:var(--success); color:white; padding:2px 6px; border-radius:4px; margin-left:8px;"><?= addslashes(tr("bs_badge_default")) ?></span>' : '';
        const ownerText = acc.owner_name ? `<?= addslashes(tr("bs_owner")) ?> : ${acc.owner_name}` : '<?= addslashes(tr("bs_shared_account")) ?>';
        
        html += `
            <div class="bs-list-item" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-panel); border-radius: var(--radius); border: 1px solid var(--border-light);">
                <div>
                    <strong>${acc.name}</strong> ${defaultBadge}<br>
                    <small style="color:var(--text-muted);">${typeBadge} · ${ownerText}</small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action delete" onclick="deleteAccount(${acc.id})" title="<?= addslashes(tr('btn_delete') ?? 'Supprimer') ?>">🗑️</button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

// 2. ONGLET : CATÉGORIES (Affichage à plat, sans accordéon)
function renderCategories(categories) {
    const container = document.getElementById('bs-list-categories');
    if (!container) return;

    if (!categories || categories.length === 0) {
        container.innerHTML = '<em style="color:var(--text-muted)"><?= addslashes(tr("bs_empty_categories")) ?></em>';
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">';
    categories.forEach(cat => {
        let typeLabel = '';
        if (cat.type === 'Income') typeLabel = '<?= addslashes(tr("bs_type_income")) ?>';
        else if (cat.type === 'Expense') typeLabel = '<?= addslashes(tr("bs_type_expense")) ?>';
        else typeLabel = '<?= addslashes(tr("bs_type_savings_cat")) ?>';

        html += `
            <div class="bs-list-item" style="border-left: 4px solid ${cat.color || '#ccc'}; display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-panel); border-radius: var(--radius); border: 1px solid var(--border-light); border-left-width: 4px;">
                <div>
                    <span style="font-size:1.2rem; margin-right:8px;">${cat.icon || '📌'}</span>
                    <strong style="color: var(--text-main); font-size: 0.95rem;">${cat.label}</strong> <small style="color:var(--text-muted)">(${cat.code} — ${typeLabel})</small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action delete" onclick="deleteCategory(${cat.id})" title="<?= addslashes(tr('btn_delete') ?? 'Supprimer') ?>">🗑️</button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

// 3. ONGLET : RÈGLES D'IMPORT (Affichage en accordéons par catégorie)
function renderRules(rules) {
    const container = document.getElementById('bs-list-rules');
    if (!container) return;

    if (!rules || rules.length === 0) {
        container.innerHTML = '<em style="color:var(--text-muted)"><?= addslashes(tr("bs_empty_rules")) ?></em>';
        return;
    }

    // Regroupement des règles par catégorie budgétaire
    const groupedRules = {};
    rules.forEach(r => {
        const catName = r.cat_label || r.category;
        if (!groupedRules[catName]) groupedRules[catName] = [];
        groupedRules[catName].push(r);
    });

    let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">';
    for (const [catName, catRules] of Object.entries(groupedRules)) {
        html += `
            <details class="pf-accordion js-rule-group" data-catname="${catName.toLowerCase()}">
                <summary class="pf-accordion-summary" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                    <span>📁 ${catName}</span>
                    <span style="font-size: 0.8rem; background: var(--border-light); padding: 2px 8px; border-radius: 10px; color: var(--text-muted); font-weight: normal; margin-left: auto; margin-right: 10px;">
                        ${catRules.length}
                    </span>
                </summary>
                <div class="pf-accordion-content" style="padding: 0.5rem;">
                    <div class="bs-rule-tags" style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
        `;
        
        catRules.forEach(r => {
            html += `
                        <div class="bs-rule-tag js-rule-tag" data-keyword="${r.keyword.toLowerCase()}">
                            ${r.keyword}
                            <button type="button" class="bs-rule-tag-del" onclick="deleteRule(${r.id})" title="<?= addslashes(tr('btn_delete') ?? 'Supprimer') ?>">×</button>
                        </div>
            `;
        });
        
        html += `
                    </div>
                </div>
            </details>
        `;
    }
    html += '</div>';
    container.innerHTML = html;
}

// 4. ACTION : FILTRAGE AVANCÉ DES RÈGLES (Recherche intelligente avec auto-ouverture)
function filterRules() {
    const searchVal = document.getElementById('input-search-rules').value.toLowerCase();
    const groups = document.querySelectorAll('.js-rule-group');

    groups.forEach(group => {
        let hasVisibleTag = false;
        const tags = group.querySelectorAll('.js-rule-tag');
        const catName = group.getAttribute('data-catname');
        
        tags.forEach(tag => {
            const keyword = tag.getAttribute('data-keyword');
            if (keyword.includes(searchVal)) {
                tag.style.display = 'inline-flex';
                hasVisibleTag = true;
            } else {
                tag.style.display = 'none';
            }
        });

        // Si le nom de la catégorie principale correspond à la recherche, on affiche tout le groupe
        if (catName.includes(searchVal) && searchVal.length > 0) {
            tags.forEach(tag => tag.style.display = 'inline-flex');
            hasVisibleTag = true;
        }

        if (hasVisibleTag) {
            group.style.display = 'block';
            // Ouvre l'accordéon automatiquement pendant la saisie d'une recherche
            if (searchVal.length > 0) {
                group.setAttribute('open', '');
            }
        } else {
            group.style.display = 'none';
            group.removeAttribute('open');
        }
    });
}

// 5. ONGLET : SALAIRES
function renderSalaries(salaries, year) {
    const container = document.getElementById('bs-list-salaries');
    if (!container) return;

    if (!salaries || salaries.length === 0) {
        container.innerHTML = `<em style="color:var(--text-muted)"><?= addslashes(tr("bs_empty_salaries")) ?> ${year}.</em>`;
        return;
    }

    let html = `<p class="pf-muted-tiny" style="margin-top:0; margin-bottom:10px;"><?= addslashes(tr("bs_salaries_year_desc")) ?> <strong>${year}</strong>.</p>`;
    const currency = '<?= CURRENCY ?>';
    html += '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
    
    salaries.forEach(s => {
        html += `
            <div class="bs-list-item" id="salary-item-${s.id}" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-panel); border-radius: var(--radius); border: 1px solid var(--border-light);">
                <div>
                    <strong>${s.person}</strong><br>
                    <small style="color:var(--text-muted);">
                        <?= addslashes(tr("bs_salary_net")) ?> : ${s.salary} ${currency}<br>
                        <?= addslashes(tr("bs_salary_fees")) ?> : ${s.mensualite} ${currency}
                    </small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action edit" 
                            data-id="${s.id}" 
                            data-person="${s.person}" 
                            data-salary="${s.salary}" 
                            data-mensualite="${s.mensualite}" 
                            onclick="inlineEditSalary(this)" 
                            title="<?= addslashes(tr('btn_edit') ?? 'Modifier') ?>">✏️</button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

// --- CRUD ACTIONS ---

async function deleteAccount(id) {
    if (!confirm('<?= addslashes(tr("bs_confirm_delete")) ?>')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_account');
        formData.append('id', id);

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
            method: 'POST',
            body: formData
        });

        if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
        
        await loadBudgetSettingsData(); 
    } catch (err) {
        alert("Erreur : " + err.message);
    }
}

const formAddAccount = document.getElementById('form-add-account');
if (formAddAccount) {
    formAddAccount.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formAddAccount.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '⏳';
        btn.disabled = true;

        try {
            const formData = new FormData(formAddAccount);
            formData.append('action', 'add_account');

            const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
                method: 'POST',
                body: formData
            });

            if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
            
            formAddAccount.reset(); 
            await loadBudgetSettingsData(); 
        } catch (err) {
            alert("Erreur : " + err.message);
        } finally {
            btn.innerText = oldText;
            btn.disabled = false;
        }
    });
}

// --- CRUD CATÉGORIES ---

async function deleteCategory(id) {
    if (!confirm('<?= addslashes(tr("bs_confirm_delete_cat")) ?>')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_category');
        formData.append('id', id);

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
            method: 'POST',
            body: formData
        });

        if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
        
        await loadBudgetSettingsData(); 
    } catch (err) {
        alert("Erreur : " + err.message);
    }
}

const formAddCategory = document.getElementById('form-add-category');
if (formAddCategory) {
    formAddCategory.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formAddCategory.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '⏳';
        btn.disabled = true;

        try {
            const formData = new FormData(formAddCategory);
            formData.append('action', 'add_category');

            const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
                method: 'POST',
                body: formData
            });

            if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
            
            formAddCategory.reset(); 
            // On remet une couleur par défaut propre après reset
            formAddCategory.querySelector('input[type="color"]').value = "#3b82f6";
            await loadBudgetSettingsData(); 
        } catch (err) {
            alert("Erreur : " + err.message);
        } finally {
            btn.innerText = oldText;
            btn.disabled = false;
        }
    });
}

// --- CRUD RÈGLES D'IMPORT ---

// Remplir le menu déroulant des catégories dans le formulaire d'ajout
function populateRuleCategories(categories) {
    const select = document.getElementById('select-rule-category');
    if (!select) return;
    
    select.innerHTML = ''; // On vide
    if (!categories || categories.length === 0) return;

    categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.code;
        opt.textContent = `${cat.icon || ''} ${cat.label}`;
        select.appendChild(opt);
    });
}

// Supprimer une règle
async function deleteRule(id) {
    if (!confirm('<?= addslashes(tr("bs_confirm_delete_rule")) ?>')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_rule');
        formData.append('id', id);

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: formData });

        if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
        
        await loadBudgetSettingsData(); 
    } catch (err) {
        alert("Erreur : " + err.message);
    }
}

// Ajouter une règle
const formAddRule = document.getElementById('form-add-rule');
if (formAddRule) {
    formAddRule.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formAddRule.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '⏳';
        btn.disabled = true;

        try {
            const formData = new FormData(formAddRule);
            formData.append('action', 'add_rule');

            const response = await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: formData });

            if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
            
            formAddRule.reset(); 
            await loadBudgetSettingsData(); 
        } catch (err) {
            alert("Erreur : " + err.message);
        } finally {
            btn.innerText = oldText;
            btn.disabled = false;
        }
    });
}

// --- CRUD SALAIRES (EDITION INLINE) ---

// Active le mode édition sur la ligne sélectionnée
function inlineEditSalary(btnEl) {
    const id = btnEl.dataset.id;
    const person = btnEl.dataset.person;
    const salary = btnEl.dataset.salary;
    const mensualite = btnEl.dataset.mensualite;
    const currency = '<?= CURRENCY ?>';

    const rowEl = document.getElementById(`salary-item-${id}`);
    if (!rowEl) return;

    // Remplacement temporaire du contenu de la ligne par un formulaire compact
    rowEl.innerHTML = `
        <form onsubmit="submitInlineSalary(event, ${id})" style="display: flex; width: 100%; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 100px;">
                <strong style="color: var(--text-main); font-size: 0.95rem;">${person}</strong>
            </div>
            <div style="display: flex; align-items: center; gap: 4px;">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-right: 2px;"><?= addslashes(tr("bs_salary_net")) ?>:</small>
                <input type="number" step="0.01" name="salary" class="pf-input" value="${salary}" required style="width: 85px; padding: 4px 8px;"> <span style="font-size: 0.85rem;">${currency}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 4px;">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-right: 2px;"><?= addslashes(tr("bs_salary_fees")) ?>:</small>
                <input type="number" step="0.01" name="mensualite" class="pf-input" value="${mensualite}" required style="width: 85px; padding: 4px 8px;"> <span style="font-size: 0.85rem;">${currency}</span>
            </div>
            <div style="display: flex; gap: 4px; margin-left: auto;">
                <button type="submit" class="btn btn-secondary" style="padding: 4px 10px;" title="<?= addslashes(tr('btn_save') ?? 'Enregistrer') ?>">💾</button>
                <button type="button" class="btn btn-ghost" onclick="loadBudgetSettingsData()" style="padding: 4px 10px;" title="<?= addslashes(tr('btn_cancel') ?? 'Annuler') ?>">❌</button>
            </div>
        </form>
    `;
}

// Soumission du formulaire d'édition à l'API
async function submitInlineSalary(event, id) {
    event.preventDefault();
    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    btn.innerText = '⏳';
    btn.disabled = true;

    try {
        const formData = new FormData(form);
        formData.append('action', 'save_salary');
        formData.append('id', id);

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
            method: 'POST',
            body: formData
        });

        if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');

        await loadBudgetSettingsData(); // Recharge les données fraîches à plat
    } catch (err) {
        alert("Erreur : " + err.message);
        btn.innerText = '💾';
        btn.disabled = false;
    }
}
// Sauvegarde de la devise du foyer en temps réel
async function updateBudgetCurrency(selectEl) {
    try {
        const formData = new FormData();
        formData.append('action', 'save_currency');
        formData.append('currency', selectEl.value);

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
            method: 'POST',
            body: formData
        });

        if (!response.success) throw new Error(response.error || '<?= addslashes(tr("error_occured")) ?>');
        
        // Si ton application possède un système de notification Toast global
        if (typeof showToast === 'function') {
            showToast('<?= addslashes(tr("bs_currency_updated")) ?> ✅');
        }
        
        // On rafraîchit les listes pour mettre à jour l'affichage des symboles (ex: dans l'onglet salaires)
        await loadBudgetSettingsData();
    } catch (err) {
        alert("Erreur : " + err.message);
    }
}   

// --- GESTION DU MAPPING CSV ---
window.I18N = {
    ...(window.I18N || {}),
    'bs_csv_col_amount': <?= json_encode(tr('bs_csv_col_amount')) ?>,
    'bs_csv_col_debit': <?= json_encode(tr('bs_csv_col_debit')) ?>,
    'bs_csv_saved': <?= json_encode(tr('bs_csv_saved')) ?>,
    'error_occured': <?= json_encode(tr('error_occured')) ?>
};

function toggleCsvAmountCols(type) {
    if (type === 'split') {
        document.getElementById('wrapper_col_credit').style.display = 'block';
        document.getElementById('lbl_col_debit').innerText = window.I18N['bs_csv_col_debit'];
    } else {
        document.getElementById('wrapper_col_credit').style.display = 'none';
        document.getElementById('lbl_col_debit').innerText = window.I18N['bs_csv_col_amount'];
    }
}

function renderCsvMapping(mapping) {
    if (!mapping) return;
    document.getElementById('csv_delimiter').value = mapping.delimiter || ';';
    document.getElementById('csv_date_format').value = mapping.date_format || 'd/m/Y';
    document.getElementById('csv_col_date').value = mapping.col_date ?? 0;
    document.getElementById('csv_col_label').value = mapping.col_label ?? 1;
    document.getElementById('csv_col_ref').value = mapping.col_ref ?? 3;
    
    const amountType = mapping.amount_type || 'split';
    document.getElementById('csv_amount_type').value = amountType;
    document.getElementById('csv_col_debit').value = mapping.col_debit ?? 8;
    document.getElementById('csv_col_credit').value = mapping.col_credit ?? 9;
    
    toggleCsvAmountCols(amountType);
}

async function saveCsvMapping(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const oldText = btn.innerText;
    btn.innerText = '⏳...';
    btn.disabled = true;

    try {
        const formData = new FormData(form);
        formData.append('action', 'save_csv_mapping');

        const response = await pachaFetch('/modules/budget/includes/api/settings.php', {
            method: 'POST',
            body: formData
        });

        if (!response.success) throw new Error(response.error || window.I18N['error_occured']);
        
        if (typeof showToast === 'function') showToast(window.I18N['bs_csv_saved']);
        else alert(window.I18N['bs_csv_saved']);
        
    } catch (err) {
        alert(window.I18N['error_occured'] + " : " + err.message);
    } finally {
        btn.innerText = oldText;
        btn.disabled = false;
    }
}

// --- GESTION DE LA DROP ZONE CSV ET FILEREADER ---

// Ajout des nouvelles clés I18N magiques
window.I18N = {
    ...(window.I18N || {}),
    'bs_csv_err_type': <?= json_encode(tr('bs_csv_err_type')) ?>,
    'bs_csv_file_ok': <?= json_encode(tr('bs_csv_file_ok')) ?>,
    'bs_csv_preview_title': <?= json_encode(tr('bs_csv_preview_title')) ?>,
    'bs_csv_empty_col': <?= json_encode(tr('bs_csv_empty_col')) ?>,
    'bs_csv_no_data': <?= json_encode(tr('bs_csv_no_data')) ?>
};

const dropZone = document.getElementById('csv-drop-zone');
const fileInput = document.getElementById('csv_file_input');

if (dropZone && fileInput) {
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = 'rgba(0, 123, 255, 0.05)';
        dropZone.style.borderColor = 'var(--primary-color, #007bff)';
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '';
        dropZone.style.borderColor = 'var(--border-light, #ccc)';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '';
        dropZone.style.borderColor = 'var(--border-light, #ccc)';
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleCsvUpload(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleCsvUpload(this.files[0]);
        }
    });
}

function handleCsvUpload(file) {
    if (!file.name.endsWith('.csv')) {
        alert(window.I18N['bs_csv_err_type']);
        return;
    }
    
    if (typeof showToast === 'function') {
        showToast(window.I18N['bs_csv_file_ok'] + ' : ' + file.name);
    }
    
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const text = e.target.result;
        const delimiter = document.getElementById('csv_delimiter').value || ';';
        
        const lines = text.split(/\r?\n/).filter(line => line.trim().length > 0);
        
        if (lines.length > 0) {
            // Extraction des en-têtes (Ligne 0)
            const headers = lines[0].split(delimiter).map(h => h.replace(/^"|"$/g, '').trim());
            
            // Extraction de max 2 lignes de données (Ligne 1 et 2)
            const sampleRows = [];
            for (let i = 1; i < Math.min(lines.length, 3); i++) {
                const rowData = lines[i].split(delimiter).map(d => d.replace(/^"|"$/g, '').trim());
                sampleRows.push(rowData);
            }
            
            renderCsvTablePreview(headers, sampleRows);
        }
    };
    
    reader.readAsText(file, 'ISO-8859-1');
}

function renderCsvTablePreview(headers, sampleRows) {
    const container = document.getElementById('csv-preview-container');
    
    let html = `<h5 style="margin: 0 0 10px 0; color: var(--primary); font-size: 0.95rem;">👁️ ${window.I18N['bs_csv_preview_title']}</h5>`;
    
    // Conteneur avec scroll horizontal si le tableau est trop large
    html += `<div style="overflow-x: auto; max-width: 100%; border: 1px solid var(--border-light); border-radius: 8px; background: white;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left; white-space: nowrap;">
            <thead style="background: var(--bg-page, #f1f5f9); border-bottom: 2px solid var(--border-light);">
                <tr>`;
    
    // Rendu des en-têtes de colonnes (avec le numéro)
    headers.forEach((header, index) => {
        const cleanHeader = header || `(${window.I18N['bs_csv_empty_col']})`;
        html += `<th style="padding: 10px 15px; font-weight: 600; color: var(--text-main); border-right: 1px solid var(--border-light);">
                    <div style="font-size: 0.75rem; color: var(--primary); margin-bottom: 2px;">N° ${index}</div>
                    <div>${cleanHeader}</div>
                 </th>`;
    });
    
    html += `   </tr>
            </thead>
            <tbody>`;
            
    // Rendu des données
    if (sampleRows.length === 0) {
        html += `<tr><td colspan="${headers.length}" style="padding: 15px; text-align: center; color: var(--text-muted); font-style: italic;">${window.I18N['bs_csv_no_data']}</td></tr>`;
    } else {
        sampleRows.forEach(row => {
            html += `<tr style="border-bottom: 1px solid #e2e8f0;">`;
            // On boucle sur la taille des headers pour s'assurer que l'affichage ne casse pas si la ligne de données est incomplète
            for (let i = 0; i < headers.length; i++) {
                const cellData = row[i] !== undefined ? row[i] : '';
                // Raccourcir la donnée si elle est vraiment très longue (ex: libellé de 100 caractères)
                const displayData = cellData.length > 40 ? cellData.substring(0, 40) + '...' : cellData;
                
                html += `<td style="padding: 10px 15px; color: var(--text-muted); border-right: 1px dashed #e2e8f0;">${displayData}</td>`;
            }
            html += `</tr>`;
        });
    }

    html += `</tbody></table></div>`;
    
    container.innerHTML = html;
    container.style.display = 'block';
}
</script>
<?php require __DIR__ . '/footer.php'; ?>