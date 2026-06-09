<?php
// modules/gift-list/gift-list.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. CONFIGURATION DE BASE ---
$year       = (int)date('Y');
$pageTitle  = tr('gift_page_title');
$activePage = "gift-list";
$mainClass  = "pf-gift-list";
$pageCss    = "/modules/gift-list/gift-list.css";

// --- 2. RÉCUPÉRATION DYNAMIQUE DES DONNÉES (Multi-tenant) ---
$stmt = $pdo->query("SELECT name FROM pf_people WHERE role = 'enfant' AND is_active = 1 ORDER BY name ASC");
$children = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Aucun enfant configuré'];

$stmt = $pdo->query("SELECT name FROM pf_people WHERE role NOT IN ('enfant', 'nounou') AND is_active = 1 ORDER BY name ASC");
$allAdultsList = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Aucun adulte configuré'];

$stmt = $pdo->query("SELECT code, name, month_date FROM pf_gift_occasions WHERE is_active = 1 ORDER BY month_date ASC, id ASC");
$activeOccasions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeOccasions)) {
    $activeOccasions = [['code' => 'DEMO', 'name' => 'Fête par défaut', 'month_date' => null]];
}
$allowedOccasionCodes = array_column($activeOccasions, 'code');

$occasionIcons = [
    'TIO' => '/modules/gift-list/assets/img/tio.png', 'NOEL' => '/modules/gift-list/assets/img/santa.png',
    'ROIS' => '/modules/gift-list/assets/img/reis.png', 'ANNIV' => '/modules/gift-list/assets/img/corona.png',
    'SANT' => '/modules/gift-list/assets/img/sant.png'
];

// --- 3. CHARGEMENT DES CADEAUX ---
$inMarks = implode(',', array_fill(0, count($allowedOccasionCodes), '?'));
$sql = "SELECT * FROM pf_gifts WHERE year = ? AND occasion IN ($inMarks) ORDER BY occasion ASC, child_name ASC, adult_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$year], $allowedOccasionCodes));
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = []; 
$adultsInView = []; 

foreach ($gifts as $g) {
    $data[$g['occasion']][$g['child_name']]['gifts'][] = $g;
    $data[$g['occasion']][$g['child_name']]['totals'][$g['adult_name']] = ($data[$g['occasion']][$g['child_name']]['totals'][$g['adult_name']] ?? 0) + $g['amount'];
    $adultsInView[$g['adult_name']] = true;
    if (!empty($g['payer_name'])) {
        $adultsInView[$g['payer_name']] = true;
    }
}

// --- 4. TRICOUNT (Bilan et Remboursements) ---
$people = array_values(array_unique(array_merge($allAdultsList, array_keys($adultsInView))));
$people = array_filter($people);
sort($people);

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

<div class="pf-container cl-view-dynamic">
    
    <div class="cl-titlebar">
        <h1>🎁 <?= sprintf(tr('gift_main_title'), $year) ?></h1>
        <button class="btn btn-ghost btn-icon" id="btn-open-gift-settings" title="<?= tr('settings') ?>">⚙️</button>
    </div>

    <div class="pf-filter-bar">
        <span class="pf-filter-icon">🔍</span>
        
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
        <?php foreach ($activeOccasions as $occ): 
            $occCode = $occ['code'];
            $occName = $occ['name'];
        ?>
            <div class="js-occ-section">
                <h2 class="cl-occasion-title">
                    <?php if(!empty($occasionIcons[$occCode])): ?>
                        <img src="<?= $occasionIcons[$occCode] ?>" class="cl-occasion-icon" alt="">
                    <?php else: ?>
                        <span>🎀</span> 
                    <?php endif; ?>
                    <?= htmlspecialchars($occName) ?>
                </h2>

                <?php foreach ($children as $child): 
                    $childData = $data[$occCode][$child] ?? ['gifts' => [], 'totals' => []];
                ?>
                <div class="pf-child-section js-child" data-name="<?= htmlspecialchars($child) ?>">
                    <div class="pf-child-header">
                        <h3>👦 <?= htmlspecialchars($child) ?></h3>
                        <button class="pf-btn pf-btn-small btn-add-gift" 
                                data-child="<?= htmlspecialchars($child) ?>" 
                                data-occ="<?= htmlspecialchars($occCode) ?>" 
                                data-adults="<?= htmlspecialchars(json_encode(array_values($allAdultsList))) ?>">
                            ＋ <?= tr('gift_add_gift') ?>
                        </button>
                    </div>

                    <div class="pf-child-totals-bar">
                        <?php foreach ($childData['totals'] as $adult => $tot): ?>
                            <span class="pf-summary-pill js-pill-adult" data-adult="<?= htmlspecialchars($adult) ?>">👤 <?= htmlspecialchars($adult) ?> : <strong><?= number_format($tot, 2, ',', '') ?> €</strong></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($childData['gifts'])): ?>
                        <p class="js-empty-state gift-empty-state"><?= tr('gift_empty_state_no_gifts') ?></p>
                    <?php else: ?>
                        <p class="js-empty-state gift-empty-state pf-hidden"><?= tr('gift_empty_state_no_filter') ?></p>
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

    <section class="pf-section pf-section--panel gift-tricount-section">
        <h2 class="gift-tricount-title">⚖️ <?= tr('gift_liquidations') ?? 'Bilan & Remboursements' ?></h2>
        
        <?php if (empty($settlements)): ?>
            <p class="gift-tricount-success">✅ <?= tr('gift_no_debt') ?></p>
        <?php else: ?>
            <ul class="pf-tricount-list">
                <?php foreach ($settlements as $s): ?>
                    <li class="pf-tricount-item">
                        <span><strong><?= htmlspecialchars($s['from']) ?></strong> <?= tr('gift_owes') ?> à <?= htmlspecialchars($s['to']) ?></span>
                        <strong class="gift-tricount-debt-amount"><?= number_format($s['amount'], 2, ',', ' ') ?> €</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details class="gift-matrix-details">
            <summary class="gift-matrix-summary"><?= tr('gift_view_matrix') ?></summary>
            <div class="gift-matrix-wrapper">
                <table class="pf-table pf-table--compact cl-debt-matrix">
                    <thead>
                        <tr>
                            <th class="sticky-col bg-header"><?= tr('gift_debtor') ?> \ <?= tr('gift_creditor') ?></th>
                            <?php foreach ($people as $p): ?><th><?= htmlspecialchars($p) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $debtor): ?>
                            <tr>
                                <th class="sticky-col bg-body"><?= htmlspecialchars($debtor) ?></th>
                                <?php foreach ($people as $creditor): 
                                    $val = $matrix[$debtor][$creditor] ?? 0;
                                    $display = ($debtor === $creditor) || $val == 0 ? '—' : number_format($val, 2, ',', ' ') . ' €';
                                    $cellClass = $val > 0 ? 'gift-cell-danger' : 'gift-cell-muted';
                                ?>
                                    <td class="<?= $cellClass ?>"><?= $display ?></td>
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
    <div class="pf-modal-content gift-modal-custom-content">
        <div class="gift-modal-custom-header">
            <h3 id="modalTitle" class="pf-modal-title gift-modal-custom-title">Cadeau</h3>
            <button type="button" class="btn-modal-close gift-modal-close-btn">&times;</button>
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
            <div class="pf-form-group gift-form-group-spaced"><label class="pf-label"><?= tr('gift_modal_link') ?></label><input type="url" name="product_link" id="modalLink" class="pf-input"></div>
            
            <div class="modal-footer gift-modal-custom-footer">
                <button type="button" class="pf-btn btn-secondary btn-modal-close"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modale Paramètres Fêtes -->
<div class="gift-settings-backdrop" id="modal-gift-settings">
    <div class="gift-settings-modal">
        <div class="gift-settings-header">
            <h3>⚙️ <?= tr('gift_settings_title') ?? 'Configuration' ?></h3>
            <button class="gift-settings-close" id="btn-close-gift-settings">×</button>
        </div>
        <div class="gift-settings-body">
            
            <h4 class="gift-add-subtitle"><?= tr('gift_settings_add_title') ?? '+ Ajouter' ?></h4>
            <form id="form-add-occasion" class="gift-add-form">
                <input type="text" class="pf-input" name="name" placeholder="<?= tr('gift_settings_name_placeholder') ?? 'Nom' ?>" required style="flex: 1; min-width: 150px; padding: 0.4rem 0.75rem;">
                <input type="text" class="pf-input" name="month_date" placeholder="<?= tr('gift_settings_date_placeholder') ?? 'MM-JJ' ?>" style="width: 120px; padding: 0.4rem 0.75rem;">
                <button type="submit" class="btn btn-secondary"><?= tr('btn_add') ?? 'Ajouter' ?></button>
            </form>
            <small class="gift-help-text"><?= tr('gift_settings_date_help') ?? 'Optionnel' ?></small>

            <hr style="border:0; border-bottom:1px solid var(--border-light); margin: 1rem 0;">

            <form id="form-save-toggles">
                <div id="occasions-list-container" class="gift-occasions-list"></div>
                
                <div class="modal-footer gift-modal-custom-footer" style="padding-top: 1rem; border-top: 1px solid var(--border-light);">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-settings"><?= tr('btn_cancel') ?? 'Annuler' ?></button>
                    <button type="submit" class="btn btn-primary"><?= tr('btn_save') ?? 'Enregistrer' ?></button>
                </div>
            </form>

        </div>
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
// 2. GESTION DE LA MODALE CADEAU (AJOUT/ÉDITION)
// ==========================================
const modal = document.getElementById('pf-gift-modal');
const adultSelect = document.getElementById('modalAdult');
const payerSelect = document.getElementById('modalPayer');

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

document.body.addEventListener('click', async function(e) {
    if (e.target.closest('.btn-modal-close')) {
        if (modal) {
            modal.classList.remove('open');
            document.body.classList.remove('no-scroll');
        }
        return;
    }

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

// ==========================================
// GESTION DES PARAMÈTRES (MODALE FÊTES ⚙️)
// ==========================================

// 1. Fonctions d'ouverture / fermeture propres
function closeGiftSettings() {
    document.getElementById('modal-gift-settings').classList.remove('show');
    document.body.classList.remove('no-scroll');
}

const btnOpenSettings = document.getElementById('btn-open-gift-settings');
if (btnOpenSettings) {
    btnOpenSettings.addEventListener('click', async (e) => {
        e.preventDefault();
        const modalSettings = document.getElementById('modal-gift-settings');
        if (modalSettings) {
            modalSettings.classList.add('show');
            document.body.classList.add('no-scroll');
            await loadGiftOccasions();
        }
    });
}

document.getElementById('btn-close-gift-settings')?.addEventListener('click', closeGiftSettings);
document.getElementById('btn-cancel-settings')?.addEventListener('click', closeGiftSettings);

// Fermeture au clic sur le fond (backdrop)
const modalGiftSettings = document.getElementById('modal-gift-settings');
if (modalGiftSettings) {
    modalGiftSettings.addEventListener('click', (e) => {
        if (e.target === modalGiftSettings) {
            closeGiftSettings();
        }
    });
}

// 2. Chargement dynamique de la liste
async function loadGiftOccasions() {
    const container = document.getElementById('occasions-list-container');
    if (!container) return;
    
    const txtLoading = window.I18N && window.I18N['loading'] ? window.I18N['loading'] : '⏳';
    const txtEmpty = window.I18N && window.I18N['gift_settings_empty'] ? window.I18N['gift_settings_empty'] : 'Vide';
    const txtActive = window.I18N && window.I18N['gift_settings_active'] ? window.I18N['gift_settings_active'] : 'Actif';

    container.innerHTML = `<div class="gift-loader">${txtLoading}</div>`;

    try {
        const res = await fetch('/modules/gift-list/api-settings.php?action=get_occasions');
        const text = await res.text();
        const data = JSON.parse(text);

        if (!data.ok) throw new Error(data.error);

        if (data.data.length === 0) {
            container.innerHTML = `<em>${txtEmpty}</em>`;
            return;
        }

        let html = '<div class="gift-occasions-grid">';
        data.data.forEach(occ => {
            const isChecked = occ.is_active == 1 ? 'checked' : '';
            const dateBadge = occ.month_date ? `<span class="gift-date-badge">${occ.month_date}</span>` : '';
            
            html += `
                <div class="pf-card gift-occasion-card">
                    <div>
                        <strong>${occ.name}</strong> ${dateBadge}
                    </div>
                    <label class="gift-occasion-toggle">
                        <input type="checkbox" class="occ-toggle-cb" data-id="${occ.id}" ${isChecked}>
                        <small>${txtActive}</small>
                    </label>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;

    } catch (e) {
        container.innerHTML = `<div class="gift-error-msg">Erreur : ${e.message}</div>`;
    }
}

// 3. Soumission du formulaire d'AJOUT
const formAddOccasion = document.getElementById('form-add-occasion');
if (formAddOccasion) {
    formAddOccasion.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formAddOccasion.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '⏳';
        btn.disabled = true;

        const formData = new FormData(formAddOccasion);
        formData.append('action', 'add_occasion');

        try {
            const res = await fetch('/modules/gift-list/api-settings.php', { method: 'POST', body: formData });
            const text = await res.text();
            const data = JSON.parse(text);

            if (!data.ok) throw new Error(data.error || 'Erreur');
            
            formAddOccasion.reset();
            await loadGiftOccasions(); // Rafraîchit juste la liste dans la modale !

        } catch (err) {
            alert("Erreur: " + err.message);
        } finally {
            btn.innerText = oldText;
            btn.disabled = false;
        }
    });
}

// 4. Soumission GLOBALE (Bouton Enregistrer) pour les Checkboxes
const formSaveToggles = document.getElementById('form-save-toggles');
if (formSaveToggles) {
    formSaveToggles.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formSaveToggles.querySelector('button[type="submit"]');
        const oldText = btn.innerText;
        btn.innerText = '⏳';
        btn.disabled = true;

        // On récolte l'état de toutes les checkboxes
        const states = [];
        formSaveToggles.querySelectorAll('.occ-toggle-cb').forEach(cb => {
            states.push({ id: cb.dataset.id, state: cb.checked ? 1 : 0 });
        });

        const formData = new FormData();
        formData.append('action', 'save_toggles');
        formData.append('states', JSON.stringify(states));

        try {
            const res = await fetch('/modules/gift-list/api-settings.php', { method: 'POST', body: formData });
            const text = await res.text();
            const data = JSON.parse(text);

            if (!data.ok) throw new Error(data.error);

            // Succès : on rafraîchit la page pour afficher/masquer les onglets
            window.location.reload(); 
        } catch (err) {
            alert("Erreur: " + err.message);
            btn.innerText = oldText;
            btn.disabled = false;
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>