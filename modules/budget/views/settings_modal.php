<?php
// Sécurité : Ce fichier ne doit pas être appelé directement
if (!defined('CURRENCY')) {
    define('CURRENCY', '€');
}
?>
<div class="budget-settings-backdrop" id="modal-budget-settings">
    <div class="budget-settings-modal">
        <div class="bs-header">
            <h3>⚙️ <?= tr('budget_settings_title') ?></h3>
            <button class="bs-close" onclick="closeBudgetSettings()">×</button>
        </div>
        <div class="bs-layout">
            <div class="bs-sidebar">
                <button class="bs-tab-btn active" onclick="switchBsTab('accounts', this)">
                    <?= tr('bs_tab_accounts') ?>
                    <?php if (!$hasAccounts): ?><span class="alert-dot alert-dot-inline">!</span><?php endif; ?>
                </button>
                <button class="bs-tab-btn" onclick="switchBsTab('categories', this)">
                    <?= tr('bs_tab_categories') ?>
                    <?php if (!$hasCategories): ?><span class="alert-dot alert-dot-inline">!</span><?php endif; ?>
                </button>
                <button class="bs-tab-btn" onclick="switchBsTab('rules', this)"><?= tr('bs_tab_rules') ?></button>
                <button class="bs-tab-btn" onclick="switchBsTab('salaries', this)">
                    <?= tr('bs_tab_salaries') ?> 
                    <?php if (!$hasSalaries): ?><span class="alert-dot alert-dot-inline">!</span><?php endif; ?>
                </button>
                <button class="bs-tab-btn" onclick="switchBsTab('csv', this)"><?= tr('bs_tab_csv') ?></button>
            </div>
            
            <div class="bs-content">
                
                <?php if (!isset($isBudgetSetupOk) || !$isBudgetSetupOk || !$hasBudgetItems): ?>
                <div class="bs-onboarding-card">
                    <h4 class="bs-onboarding-title">
                        <span>🚀</span> <?= tr('bs_onb_title') ?>
                    </h4>
                    <p class="bs-onboarding-desc"><?= tr('bs_onb_desc') ?></p>
                    <ul class="bs-onboarding-list">
                        <li>
                            <strong class="<?= $hasCategories ? 'text-success' : 'text-danger' ?>"><?= $hasCategories ? '✅' : '❌' ?></strong>
                            <a class="bs-onboarding-link" onclick="switchBsTab('categories', document.querySelectorAll('.bs-tab-btn')[1]); return false;"><?= tr('bs_onb_cat_link') ?></a> : <?= tr('bs_onb_cat_desc') ?>
                        </li>
                        <li>
                            <strong class="<?= $hasAccounts ? 'text-success' : 'text-danger' ?>"><?= $hasAccounts ? '✅' : '❌' ?></strong>
                            <a class="bs-onboarding-link" onclick="switchBsTab('accounts', document.querySelectorAll('.bs-tab-btn')[0]); return false;"><?= tr('bs_onb_acc_link') ?></a> : <?= tr('bs_onb_acc_desc') ?>
                        </li>
                        <li>
                            <strong class="<?= $hasSalaries ? 'text-success' : 'text-danger' ?>"><?= $hasSalaries ? '✅' : '❌' ?></strong>
                            <a class="bs-onboarding-link" onclick="switchBsTab('salaries', document.querySelectorAll('.bs-tab-btn')[3]); return false;"><?= tr('bs_onb_sal_link') ?></a> : <?= tr('bs_onb_sal_desc') ?>
                        </li>
                        <li style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(180, 83, 9, 0.3);">
                            <strong class="<?= $hasBudgetItems ? 'text-success' : 'text-warning' ?>" style="color: <?= $hasBudgetItems ? '' : '#f59e0b' ?>"><?= $hasBudgetItems ? '✅' : '⏳' ?></strong>
                            <a href="?tab=recap" onclick="closeBudgetSettings();" class="bs-onboarding-link"><?= tr('bs_onb_items_link') ?></a> : <?= tr('bs_onb_items_desc') ?>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

                <div id="pane-accounts" class="bs-pane active">
                    <div class="bs-pane-header">
                        <h4 class="bs-section-title"><?= tr('bs_tab_accounts') ?></h4>
                        <div class="bs-currency-selector">
                            <span><?= tr('bs_currency_label') ?> :</span>
                            <select id="bs-currency-select" class="pf-input bs-currency-select" onchange="updateBudgetCurrency(this)">
                                <option value="€">EUR (€)</option>
                                <option value="$">USD ($)</option>
                                <option value="£">GBP (£)</option>
                                <option value="CHF">CHF</option>
                            </select>
                        </div>
                    </div>

                    <div id="bs-list-accounts" class="bs-list-container">⏳ <?= tr('loading') ?></div>
                    <hr class="bs-divider">
                    <h5 class="bs-section-subtitle"><?= tr('bs_add_account_title') ?></h5>
                    <form id="form-add-account" class="bs-form-inline">
                        <input type="text" name="name" class="pf-input bs-input-flex" placeholder="<?= tr('bs_account_name_ph') ?>" required>
                        <select name="type" class="pf-input bs-input-fixed">
                            <option value="checking"><?= tr('bs_type_checking') ?></option>
                            <option value="savings"><?= tr('bs_type_savings') ?></option>
                        </select>
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-categories" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_categories') ?></h4>
                    <div id="bs-list-categories" class="bs-list-container">⏳ <?= tr('loading') ?></div>
                    <hr class="bs-divider">
                    <h5 class="bs-section-subtitle"><?= tr('bs_add_category_title') ?></h5>
                    <form id="form-add-category" class="bs-form-inline align-center">
                        <input type="text" name="code" class="pf-input bs-input-fixed" placeholder="<?= tr('bs_cat_code_ph') ?>" required>
                        <input type="text" name="label" class="pf-input bs-input-flex" placeholder="<?= tr('bs_cat_label_ph') ?>" required>
                        
                        <select name="type" class="pf-input bs-input-fixed">
                            <option value="Expense"><?= tr('bs_type_expense') ?></option>
                            <option value="Income"><?= tr('bs_type_income') ?></option>
                            <option value="Savings"><?= tr('bs_type_savings_cat') ?></option>
                        </select>
                        
                        <select name="icon" class="pf-input bs-input-icon">
                            <option value="📌">📌</option><option value="🛒">🛒</option><option value="🥖">🥖</option>
                            <option value="🍽️">🍽️</option><option value="⛽">⛽</option><option value="🚗">🚗</option>
                            <option value="🚆">🚆</option><option value="🏠">🏠</option><option value="⚡">⚡</option>
                            <option value="💧">💧</option><option value="⚕️">⚕️</option><option value="🎒">🎒</option>
                            <option value="👕">👕</option><option value="📱">📱</option><option value="🎮">🎮</option>
                            <option value="✈️">✈️</option><option value="🎁">🎁</option><option value="🐶">🐶</option>
                            <option value="💵">💵</option><option value="🐷">🐷</option><option value="📈">📈</option>
                        </select>
                        
                        <input type="color" name="color" value="#3b82f6" class="bs-input-color">
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-rules" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_rules') ?></h4>
                    <input type="text" id="input-search-rules" class="bs-rule-search" placeholder="<?= tr('bs_rule_search_ph') ?>" onkeyup="filterRules()">
                    <div id="bs-list-rules" class="bs-list-container">⏳ <?= tr('loading') ?></div>
                    <hr class="bs-divider">
                    <h5 class="bs-section-subtitle"><?= tr('bs_add_rule_title') ?></h5>
                    <form id="form-add-rule" class="bs-form-inline align-center">
                        <input type="text" name="keyword" class="pf-input bs-input-flex" placeholder="<?= tr('bs_rule_keyword_ph') ?>" required>
                        <select name="category" id="select-rule-category" class="pf-input bs-input-fixed" required></select>
                        <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?></button>
                    </form>
                </div>

                <div id="pane-salaries" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_tab_salaries') ?> (<?= date('Y') ?>)</h4>
                    <div id="bs-list-salaries" class="bs-list-container">⏳ <?= tr('loading') ?></div>
                </div>

                <div id="pane-csv" class="bs-pane">
                    <h4 class="bs-section-title"><?= tr('bs_csv_title') ?></h4>
                    <p class="pf-muted-tiny bs-desc"><?= tr('bs_csv_desc') ?></p>
                    
                    <div id="csv-drop-zone" class="bs-csv-dropzone">
                        <div class="bs-csv-icon">📥</div>
                        <h5 class="bs-csv-title"><?= tr('bs_csv_drop_title') ?></h5>
                        <p class="pf-muted-tiny bs-csv-subtitle"><?= tr('bs_csv_drop_desc') ?></p>
                        <input type="file" id="csv_file_input" accept=".csv" style="display: none;">
                    </div>

                    <div id="csv-preview-container" class="bs-csv-preview"></div>
                    
                    <h5 class="bs-section-subtitle bordered"><?= tr('bs_csv_settings_title') ?></h5>
                    <form id="form-csv-mapping" onsubmit="saveCsvMapping(event)">
                        <div class="bs-grid-2">
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

                        <h5 class="bs-section-subtitle">
                            <?= tr('bs_csv_col_index') ?> 
                            <small class="bs-text-muted-normal"><?= tr('bs_csv_col_index_help') ?></small>
                        </h5>
                        <div class="bs-grid-2">
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_col_date') ?></label>
                                <input type="number" min="0" name="csv_col_date" id="csv_col_date" class="pf-input" value="0" required>
                            </div>
                            <div>
                                <label class="pf-label"><?= tr('bs_csv_col_label') ?></label>
                                <input type="number" min="0" name="csv_col_label" id="csv_col_label" class="pf-input" value="1" required>
                            </div>
                        </div>

                        <div class="bs-card-gray">
                            <label class="pf-label"><?= tr('bs_csv_amount_mgmt') ?></label>
                            <select name="csv_amount_type" id="csv_amount_type" class="pf-input bs-input-mb" onchange="toggleCsvAmountCols(this.value)">
                                <option value="single"><?= tr('bs_csv_amount_single') ?></option>
                                <option value="split"><?= tr('bs_csv_amount_split') ?></option>
                            </select>

                            <div class="bs-grid-2">
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

                        <div class="bs-input-mb">
                            <label class="pf-label"><?= tr('bs_csv_col_ref') ?></label>
                            <input type="number" min="0" name="csv_col_ref" id="csv_col_ref" class="pf-input" value="3">
                        </div>
                        <div class="bs-text-right">
                            <button type="submit" class="btn btn-secondary">💾 <?= tr('btn_save_format') ?></button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
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

function openBudgetSettings() {
    document.getElementById('modal-budget-settings').classList.add('show');
    document.body.classList.add('no-scroll');
    loadBudgetSettingsData();
}

function closeBudgetSettings() {
    document.getElementById('modal-budget-settings').classList.remove('show');
    document.body.classList.remove('no-scroll');
    window.location.reload();
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
        if (!response.success) throw new Error(response.error || tr('error_occured'));
        renderAccounts(response.data.accounts);
        renderCategories(response.data.categories);
        populateRuleCategories(response.data.categories);
        renderRules(response.data.rules);
        renderSalaries(response.data.salaries, response.data.year);
        const currencySelect = document.getElementById('bs-currency-select');
        if (currencySelect && response.data.currency) currencySelect.value = response.data.currency;
    } catch (err) {
        console.error("Erreur chargement paramètres :", err);
        const errorMsg = `<div class="pf-alert pf-alert--error bs-input-mb">❌ ${tr('error_loading_settings')} : ${err.message}</div>`;
        ['accounts', 'categories', 'rules', 'salaries'].forEach(id => {
            const el = document.getElementById('bs-list-' + id);
            if (el) el.innerHTML = errorMsg;
        });
    }
}

// 1. ONGLET : COMPTES
function renderAccounts(accounts) {
    const container = document.getElementById('bs-list-accounts');
    if (!container) return;
    if (!accounts || accounts.length === 0) {
        container.innerHTML = `<em class="text-muted">${tr('bs_empty_accounts')}</em>`;
        return;
    }
    let html = '';
    accounts.forEach(acc => {
        const typeBadge = acc.account_type === 'checking' ? tr('bs_type_checking') : tr('bs_type_savings');
        const defaultBadge = acc.is_default == 1 ? `<span class="bs-badge-default">${tr('bs_badge_default')}</span>` : '';
        const ownerText = acc.owner_name ? `${tr('bs_owner')} : ${acc.owner_name}` : tr('bs_shared_account');
        html += `
            <div class="bs-list-item">
                <div>
                    <strong>${acc.name}</strong> ${defaultBadge}<br>
                    <small class="text-muted">${typeBadge} · ${ownerText}</small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action delete" onclick="deleteAccount(${acc.id})" title="${tr('btn_delete')}">🗑️</button>
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

// 2. ONGLET : CATÉGORIES
function renderCategories(categories) {
    const container = document.getElementById('bs-list-categories');
    if (!container) return;
    if (!categories || categories.length === 0) {
        container.innerHTML = `<em class="text-muted">${tr('bs_empty_categories')}</em>`;
        return;
    }
    let html = '';
    categories.forEach(cat => {
        let typeLabel = cat.type === 'Income' ? tr('bs_type_income') : cat.type === 'Expense' ? tr('bs_type_expense') : tr('bs_type_savings_cat');
        html += `
            <div class="bs-list-item" style="border-left: 4px solid ${cat.color || '#ccc'};">
                <div>
                    <span style="font-size:1.2rem; margin-right:8px;">${cat.icon || '📌'}</span>
                    <strong>${cat.label}</strong> <small class="text-muted">(${cat.code} — ${typeLabel})</small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action delete" onclick="deleteCategory(${cat.id})" title="${tr('btn_delete')}">🗑️</button>
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

// 3. ONGLET : RÈGLES D'IMPORT
function renderRules(rules) {
    const container = document.getElementById('bs-list-rules');
    if (!container) return;
    if (!rules || rules.length === 0) {
        container.innerHTML = `<em class="text-muted">${tr('bs_empty_rules')}</em>`;
        return;
    }
    const groupedRules = {};
    rules.forEach(r => {
        const catName = r.cat_label || r.category;
        if (!groupedRules[catName]) groupedRules[catName] = [];
        groupedRules[catName].push(r);
    });
    let html = '';
    for (const [catName, catRules] of Object.entries(groupedRules)) {
        html += `
            <details class="pf-accordion js-rule-group" data-catname="${catName.toLowerCase()}">
                <summary class="pf-accordion-summary bs-accordion-summary">
                    <span>📁 ${catName}</span>
                    <span class="bs-accordion-count">${catRules.length}</span>
                </summary>
                <div class="pf-accordion-content" style="padding: 0.5rem;">
                    <div class="bs-rule-tags">`;
        catRules.forEach(r => {
            html += `<div class="bs-rule-tag js-rule-tag" data-keyword="${r.keyword.toLowerCase()}">
                        ${r.keyword}
                        <button type="button" class="bs-rule-tag-del" onclick="deleteRule(${r.id})" title="${tr('btn_delete')}">×</button>
                     </div>`;
        });
        html += `   </div>
                </div>
            </details>`;
    }
    container.innerHTML = html;
}

function filterRules() {
    const searchVal = document.getElementById('input-search-rules').value.toLowerCase();
    document.querySelectorAll('.js-rule-group').forEach(group => {
        let hasVisibleTag = false;
        const catName = group.getAttribute('data-catname');
        group.querySelectorAll('.js-rule-tag').forEach(tag => {
            const keyword = tag.getAttribute('data-keyword');
            if (keyword.includes(searchVal)) { tag.style.display = 'inline-flex'; hasVisibleTag = true; } 
            else { tag.style.display = 'none'; }
        });
        if (catName.includes(searchVal) && searchVal.length > 0) {
            group.querySelectorAll('.js-rule-tag').forEach(tag => tag.style.display = 'inline-flex');
            hasVisibleTag = true;
        }
        if (hasVisibleTag) {
            group.style.display = 'block';
            if (searchVal.length > 0) group.setAttribute('open', '');
        } else {
            group.style.display = 'none'; group.removeAttribute('open');
        }
    });
}

// 5. ONGLET : SALAIRES
function renderSalaries(salaries, year) {
    const container = document.getElementById('bs-list-salaries');
    if (!container) return;
    if (!salaries || salaries.length === 0) {
        container.innerHTML = `<em class="text-muted">${tr('bs_empty_salaries')} ${year}.</em>`;
        return;
    }
    const currency = '<?= CURRENCY ?>';
    let html = `<p class="bs-desc">${tr('bs_salaries_year_desc')} <strong>${year}</strong>.</p>`;
    salaries.forEach(s => {
        html += `
            <div class="bs-list-item" id="salary-item-${s.id}">
                <div>
                    <strong>${s.person}</strong><br>
                    <small class="text-muted">
                        ${tr('bs_salary_net')} : ${s.salary} ${currency}<br>
                        ${tr('bs_salary_fees')} : ${s.mensualite} ${currency}
                    </small>
                </div>
                <div class="pf-flex-gap-8">
                    <button type="button" class="btn-icon-action edit" data-id="${s.id}" data-person="${s.person}" data-salary="${s.salary}" data-mensualite="${s.mensualite}" onclick="inlineEditSalary(this)" title="${tr('btn_edit')}">✏️</button>
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

// --- CRUD ACTIONS ---
async function deleteAccount(id) { if (!await pachaConfirm(tr('btn_delete'), tr('bs_confirm_delete'))) return; try { const fd = new FormData(); fd.append('action', 'delete_account'); fd.append('id', id); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } }
const formAddAccount = document.getElementById('form-add-account');
if (formAddAccount) formAddAccount.addEventListener('submit', async (e) => { e.preventDefault(); const btn = formAddAccount.querySelector('button'); const oldText = btn.innerText; btn.innerText = '⏳'; btn.disabled = true; try { const fd = new FormData(formAddAccount); fd.append('action', 'add_account'); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); formAddAccount.reset(); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } finally { btn.innerText = oldText; btn.disabled = false; } });

async function deleteCategory(id) { if (!await pachaConfirm(tr('btn_delete'), tr('bs_confirm_delete_cat'))) return; try { const fd = new FormData(); fd.append('action', 'delete_category'); fd.append('id', id); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } }
const formAddCategory = document.getElementById('form-add-category');
if (formAddCategory) formAddCategory.addEventListener('submit', async (e) => { e.preventDefault(); const btn = formAddCategory.querySelector('button'); const oldText = btn.innerText; btn.innerText = '⏳'; btn.disabled = true; try { const fd = new FormData(formAddCategory); fd.append('action', 'add_category'); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); formAddCategory.reset(); formAddCategory.querySelector('input[type="color"]').value = "#3b82f6"; await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } finally { btn.innerText = oldText; btn.disabled = false; } });

function populateRuleCategories(categories) { const select = document.getElementById('select-rule-category'); if (!select) return; select.innerHTML = ''; if (!categories) return; categories.forEach(cat => { const opt = document.createElement('option'); opt.value = cat.code; opt.textContent = `${cat.icon || ''} ${cat.label}`; select.appendChild(opt); }); }
async function deleteRule(id) { if (!await pachaConfirm(tr('btn_delete'), tr('bs_confirm_delete_rule'))) return; try { const fd = new FormData(); fd.append('action', 'delete_rule'); fd.append('id', id); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } }
const formAddRule = document.getElementById('form-add-rule');
if (formAddRule) formAddRule.addEventListener('submit', async (e) => { e.preventDefault(); const btn = formAddRule.querySelector('button'); const oldText = btn.innerText; btn.innerText = '⏳'; btn.disabled = true; try { const fd = new FormData(formAddRule); fd.append('action', 'add_rule'); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); formAddRule.reset(); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } finally { btn.innerText = oldText; btn.disabled = false; } });

function inlineEditSalary(btnEl) {
    const id = btnEl.dataset.id; const person = btnEl.dataset.person; const salary = btnEl.dataset.salary; const mensualite = btnEl.dataset.mensualite; const currency = '<?= CURRENCY ?>';
    document.getElementById(`salary-item-${id}`).innerHTML = `
        <form onsubmit="submitInlineSalary(event, ${id})" class="bs-form-inline align-center">
            <div class="bs-input-flex"><strong>${person}</strong></div>
            <div style="display:flex; align-items:center; gap:4px;"><small class="text-muted">${tr('bs_salary_net')}:</small><input type="number" step="0.01" name="salary" class="pf-input bs-input-fixed" value="${salary}" required> ${currency}</div>
            <div style="display:flex; align-items:center; gap:4px;"><small class="text-muted">${tr('bs_salary_fees')}:</small><input type="number" step="0.01" name="mensualite" class="pf-input bs-input-fixed" value="${mensualite}" required> ${currency}</div>
            <button type="submit" class="btn btn-secondary">💾</button>
            <button type="button" class="btn btn-ghost" onclick="loadBudgetSettingsData()">❌</button>
        </form>`;
}
async function submitInlineSalary(event, id) { event.preventDefault(); const form = event.target; const btn = form.querySelector('button'); btn.innerText = '⏳'; btn.disabled = true; try { const fd = new FormData(form); fd.append('action', 'save_salary'); fd.append('id', id); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); btn.innerText = '💾'; btn.disabled = false; } }

async function updateBudgetCurrency(selectEl) { try { const fd = new FormData(); fd.append('action', 'save_currency'); fd.append('currency', selectEl.value); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); if (typeof showToast === 'function') { showToast(`${tr('bs_currency_updated')} ✅`); } await loadBudgetSettingsData(); } catch (err) { alert("Erreur : " + err.message); } }   

// CSV
function toggleCsvAmountCols(type) { if (type === 'split') { document.getElementById('wrapper_col_credit').style.display = 'block'; document.getElementById('lbl_col_debit').innerText = tr('bs_csv_col_debit'); } else { document.getElementById('wrapper_col_credit').style.display = 'none'; document.getElementById('lbl_col_debit').innerText = tr('bs_csv_col_amount'); } }
function renderCsvMapping(mapping) { if (!mapping) return; document.getElementById('csv_delimiter').value = mapping.delimiter || ';'; document.getElementById('csv_date_format').value = mapping.date_format || 'd/m/Y'; document.getElementById('csv_col_date').value = mapping.col_date ?? 0; document.getElementById('csv_col_label').value = mapping.col_label ?? 1; document.getElementById('csv_col_ref').value = mapping.col_ref ?? 3; const amountType = mapping.amount_type || 'split'; document.getElementById('csv_amount_type').value = amountType; document.getElementById('csv_col_debit').value = mapping.col_debit ?? 8; document.getElementById('csv_col_credit').value = mapping.col_credit ?? 9; toggleCsvAmountCols(amountType); }
async function saveCsvMapping(e) { e.preventDefault(); const form = e.target; const btn = form.querySelector('button'); const oldText = btn.innerText; btn.innerText = '⏳...'; btn.disabled = true; try { const fd = new FormData(form); fd.append('action', 'save_csv_mapping'); await pachaFetch('/modules/budget/includes/api/settings.php', { method: 'POST', body: fd }); if (typeof showToast === 'function') showToast(tr('bs_csv_saved')); else alert(tr('bs_csv_saved')); } catch (err) { alert(tr('error_occured') + " : " + err.message); } finally { btn.innerText = oldText; btn.disabled = false; } }

const dropZone = document.getElementById('csv-drop-zone'); const fileInput = document.getElementById('csv_file_input');
if (dropZone && fileInput) {
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.backgroundColor = 'rgba(0, 123, 255, 0.05)'; dropZone.style.borderColor = 'var(--primary-color, #007bff)'; });
    dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); dropZone.style.backgroundColor = ''; dropZone.style.borderColor = 'var(--border-light, #ccc)'; });
    dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.style.backgroundColor = ''; dropZone.style.borderColor = 'var(--border-light, #ccc)'; if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; handleCsvUpload(e.dataTransfer.files[0]); } });
    fileInput.addEventListener('change', function() { if (this.files.length) { handleCsvUpload(this.files[0]); } });
}
function handleCsvUpload(file) {
    if (!file.name.endsWith('.csv')) { alert(tr('bs_csv_err_type')); return; }
    if (typeof showToast === 'function') { showToast(`${tr('bs_csv_file_ok')} : ${file.name}`); }
    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result; const delimiter = document.getElementById('csv_delimiter').value || ';';
        const lines = text.split(/\r?\n/).filter(line => line.trim().length > 0);
        if (lines.length > 0) {
            const headers = lines[0].split(delimiter).map(h => h.replace(/^"|"$/g, '').trim());
            const sampleRows = [];
            for (let i = 1; i < Math.min(lines.length, 3); i++) { sampleRows.push(lines[i].split(delimiter).map(d => d.replace(/^"|"$/g, '').trim())); }
            renderCsvTablePreview(headers, sampleRows);
        }
    };
    reader.readAsText(file, 'ISO-8859-1');
}
function renderCsvTablePreview(headers, sampleRows) {
    const container = document.getElementById('csv-preview-container');
    let html = `<h5 class="bs-section-subtitle">👁️ ${tr('bs_csv_preview_title')}</h5>`;
    html += `<div class="bs-csv-table-wrapper"><table class="bs-csv-table"><thead class="bs-csv-thead"><tr>`;
    headers.forEach((header, index) => {
        const cleanHeader = header || `(${tr('bs_csv_empty_col')})`;
        html += `<th class="bs-csv-th"><div class="bs-csv-th-index">N° ${index}</div><div>${cleanHeader}</div></th>`;
    });
    html += `</tr></thead><tbody>`;
    if (sampleRows.length === 0) { html += `<tr><td colspan="${headers.length}" class="bs-csv-td-empty">${tr('bs_csv_no_data')}</td></tr>`; } 
    else { sampleRows.forEach(row => { html += `<tr class="bs-csv-tr">`; for (let i = 0; i < headers.length; i++) { const cellData = row[i] !== undefined ? row[i] : ''; const displayData = cellData.length > 40 ? cellData.substring(0, 40) + '...' : cellData; html += `<td class="bs-csv-td">${displayData}</td>`; } html += `</tr>`; }); }
    html += `</tbody></table></div>`;
    container.innerHTML = html; container.style.display = 'block';
}
</script>