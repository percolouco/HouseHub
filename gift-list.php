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

    <section class="pf-section pf-section--panel" style="border:1px solid var(--border-light); margin-top:40px;">
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

        <details style="margin-top:20px; padding:12px; border-radius:8px;">
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
            
            <div class="modal-footer" style="padding-top:15px; display:flex; justify-content:flex-end; gap:10px;">
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