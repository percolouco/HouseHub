<?php
// modules/gift-list/gift-list.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. CONFIGURATION & DONNÉES ---
$year       = (int)date('Y');
$pageTitle  = tr('gift_page_title');
$activePage = "gift-list";
$bodyClass  = "pf-gift-list";
$pageCss    = "/modules/gift-list/gift-list.css";

$baseAdults = ['Laia', 'Laura', 'Avi Iaia'];
$children   = ['Pol', 'Pep', 'Elna', 'Bru', 'Guim'];
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
    'TIO'   => tr('gift_occ_tio'),
    'NOEL'  => tr('gift_occ_noel'),
    'ROIS'  => tr('gift_occ_rois'),
    'ANNIV' => tr('gift_occ_anniv'),
    'SANT'  => tr('gift_occ_sant'),
];

$occasionIcons = [
    'TIO'   => '/modules/gift-list/assets/img/tio.png',
    'NOEL'  => '/modules/gift-list/assets/img/santa.png',
    'ROIS'  => '/modules/gift-list/assets/img/reis.png',
    'ANNIV' => '/modules/gift-list/assets/img/corona.png',
    'SANT'  => '/modules/gift-list/assets/img/sant.png',
];

// --- 2. RÉCUPÉRATION DES DONNÉES ---
$inMarks = implode(',', array_fill(0, count($allowedOccasions), '?'));
// Le tri SQL regroupe par Occasion, puis par Enfant, puis par Adulte
$sql = "SELECT * FROM pf_gifts WHERE year = ? AND occasion IN ($inMarks) ORDER BY occasion ASC, child_name ASC, adult_name ASC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$year], $allowedOccasions));
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$dataByOccasion = [];
$adultsInView = [];

foreach ($gifts as $g) {
    $occ = $g['occasion'];
    $child = $g['child_name'];
    $adult = $g['adult_name'];
    
    // Groupement structuré : Occasion -> Enfant -> Cadeaux
    $dataByOccasion[$occ][$child]['gifts'][] = $g;
    
    // Totaux pour les petites pills sous le nom de l'enfant
    if (!isset($dataByOccasion[$occ][$child]['totals'][$adult])) {
        $dataByOccasion[$occ][$child]['totals'][$adult] = 0;
    }
    $dataByOccasion[$occ][$child]['totals'][$adult] += (float)$g['amount'];
    
    $adultsInView[$adult] = true;
}

$allAdultsList = array_keys($adultsInView);
sort($allAdultsList);
$occasionsToShow = array_values(array_intersect(array_keys($allOccasionLabels), $allowedOccasions));

// --- 3. CALCUL TRICOUNT (Bilan financier global de la vue) ---
$people = array_values(array_unique(array_merge($baseAdults, array_column($gifts, 'adult_name'), array_column($gifts, 'payer_name'))));
$people = array_filter($people);

$matrix = [];
foreach ($people as $p1) {
    foreach ($people as $p2) $matrix[$p1][$p2] = 0.0;
}

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
        $a = $people[$i];
        $b = $people[$j];
        $net = $matrix[$a][$b] - $matrix[$b][$a];
        if ($net > 0.01) { $settlements[] = ['from' => $a, 'to' => $b, 'amount' => $net]; } 
        elseif ($net < -0.01) { $settlements[] = ['from' => $b, 'to' => $a, 'amount' => -$net]; }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-container cl-view-<?= htmlspecialchars($currentView) ?>">
    
    <div class="cl-titlebar">
        <h1><?= sprintf(tr('gift_main_title'), $year) ?></h1>
        <div class="cl-view-switch" aria-label="<?= tr('gift_aria_change_view') ?>">
            <a href="?view=nadal" class="cl-view-btn <?= $currentView === 'nadal' ? 'is-active' : '' ?>"><?= tr('gift_view_nadal') ?></a>
            <a href="?view=anniversary" class="cl-view-btn <?= $currentView === 'anniversary' ? 'is-active' : '' ?>"><?= tr('gift_view_anniv') ?></a>
        </div>
    </div>

    <div class="pf-filter-bar">
        <div class="pf-filter-label">🔍</div>
        <select id="filterChild" class="pf-filter-select" onchange="applyGiftFilters()">
            <option value="all">👦 <?= tr('gift_filter_all_children') ?></option>
            <?php foreach ($children as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="filterAdult" class="pf-filter-select" onchange="applyGiftFilters()">
            <option value="all">👤 <?= tr('gift_filter_all_adults') ?></option>
            <?php foreach ($allAdultsList as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <section class="pf-section">
        <?php if (empty($gifts)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted); font-style:italic; background:white; border-radius:12px; border:1px solid var(--border-light);">
                <?= sprintf(tr('gift_no_gifts'), $year) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($occasionsToShow as $occCode): ?>
            <div class="pf-occasion-wrapper js-occ-wrapper">
                <h2 class="cl-occasion-title">
                    <?php if (!empty($occasionIcons[$occCode])): ?>
                        <img class="cl-occasion-icon" src="<?= htmlspecialchars($occasionIcons[$occCode]) ?>" alt="" aria-hidden="true">
                    <?php endif; ?>
                    <?= htmlspecialchars($allOccasionLabels[$occCode] ?? $occCode) ?>
                </h2>

                <?php foreach ($children as $childName): 
                    
                    $childData = $dataByOccasion[$occCode][$childName] ?? ['gifts' => [], 'totals' => []];
                    $childGifts = $childData['gifts'] ?? [];
                    $childTotals = $childData['totals'] ?? [];
                    $adultsForChild = ($currentView === 'anniversary') ? ($adultsByChildForAnniv[$childName] ?? $baseAdults) : $baseAdults;
                    
                    // Sécurisation du JSON pour éviter l'erreur "openGiftModal is not defined"
                    $addBtnData = json_encode([
                        'child' => $childName,
                        'occ' => $occCode,
                        'adults' => array_values($adultsForChild)
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                    <div class="pf-child-section js-child-section" data-child="<?= htmlspecialchars($childName) ?>">
                        <div class="pf-child-header">
                            <h3>👦 <?= htmlspecialchars($childName) ?></h3>
                            <button type="button" class="pf-btn pf-btn-small" onclick='openGiftModal("add", <?= $addBtnData ?>)'>
                                ＋ <?= tr('gift_add_gift') ?>
                            </button>
                        </div>

                        <?php if (!empty($childTotals)): ?>
                        <div class="pf-child-totals-bar">
                            <?php foreach ($childTotals as $adult => $tot): ?>
                                <span class="pf-summary-pill js-pill-adult" data-adult="<?= htmlspecialchars($adult) ?>">
                                    👤 <?= htmlspecialchars($adult) ?> : <strong><?= number_format($tot, 2, ',', '') ?> €</strong>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($childGifts)): ?>
                            <p class="js-empty-state" style="color:var(--text-muted); font-size:0.9rem; font-style:italic; margin:0;"><?= tr('gift_empty_state_no_gifts') ?></p>
                        <?php else: ?>
                            <p class="js-empty-state" style="color:var(--text-muted); font-size:0.9rem; font-style:italic; display:none; margin:0;"><?= tr('gift_empty_state_no_filter') ?></p>  
                            <div class="pf-gift-feed">
                                <?php foreach ($childGifts as $gift): 
                                    $editBtnData = json_encode($gift, JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                    <div class="pf-gift-card-compact js-gift-card" data-adult="<?= htmlspecialchars($gift['adult_name']) ?>">
                                        <div>
                                            <h4 class="pf-gift-title">
                                                <?= htmlspecialchars($gift['gift_description']) ?>
                                                <?php if(!empty($gift['product_link'])): ?>
                                                    <a href="<?= htmlspecialchars($gift['product_link']) ?>" target="_blank" class="pf-gift-link" title="Voir l'article">🔗</a>
                                                <?php endif; ?>
                                            </h4>
                                            
                                            <div class="pf-gift-badges-col">
                                                <span class="pf-pill-adult">👤 <?= htmlspecialchars($gift['adult_name']) ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if(!empty($gift['payer_name']) && $gift['payer_name'] !== $gift['adult_name']): ?>
                                            <div class="pf-gift-payer"><?= sprintf(tr('gift_paid_by'), htmlspecialchars($gift['payer_name'])) ?></div>
                                        <?php endif; ?>

                                        <div class="pf-gift-footer">
                                            <span class="pf-gift-price"><?= number_format($gift['amount'], 2, ',', ' ') ?> €</span>
                                            <div class="pf-gift-actions">
                                                <button type="button" class="btn-icon-action edit" aria-label="<?= tr('edit') ?>" onclick='openGiftModal("edit", <?= $editBtnData ?>)'>✏️</button>
                                                <button type="button" class="btn-icon-action delete" aria-label="<?= tr('delete') ?>" onclick="deleteGift(<?= $gift['id'] ?>)">🗑️</button>
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
                                    $isDiag = ($debtor === $creditor);
                                    $display = $isDiag || $val == 0 ? '—' : number_format($val, 2, ',', ' ') . ' €';
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
            <h3 id="pf-modal-title" class="pf-modal-title" style="margin:0; border:none; padding:0;"><?= tr('gift_col_gift') ?></h3>
            <button type="button" onclick="closeGiftModal()" style="background:none; border:none; font-size:1.8rem; cursor:pointer; color:var(--text-muted); line-height:1;">&times;</button>
        </div>
        
        <form method="post" action="/modules/gift-list/save-gift.php" id="pf-gift-form">
            <input type="hidden" name="year" id="m_year" value="<?= $year ?>">
            <input type="hidden" name="child_name" id="m_child">
            <input type="hidden" name="occasion" id="m_occ">
            <input type="hidden" name="action" id="m_action">
            <input type="hidden" name="gift_id" id="m_id">

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('gift_col_adult') ?></label>
                <select name="adult_name" id="m_adult" class="pf-input" required></select>
            </div>

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('gift_modal_payer') ?></label>
                <select name="payer_name" id="m_payer" class="pf-input" required></select>
            </div>

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('gift_modal_gift_name') ?></label>
                <input type="text" name="gift_description" id="m_gift" class="pf-input" placeholder="<?= tr('gift_modal_ph_name') ?>" required>
            </div>

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('gift_modal_price') ?></label>
                <input type="number" name="amount" id="m_amount" class="pf-input" placeholder="0.00" step="0.01" min="0">
            </div>

            <div class="pf-form-group" style="margin-bottom:25px;">
                <label class="pf-label"><?= tr('gift_modal_link') ?></label>
                <input type="url" name="product_link" id="m_link" class="pf-input" placeholder="https://...">
            </div>

            <div class="modal-footer" style="padding-top:15px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeGiftModal()" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- SYNCHRONISATION AUTO : ADULTE -> PAYEUR ---
document.getElementById('m_adult').addEventListener('change', function() {
    const payerSelect = document.getElementById('m_payer');
    // Si la valeur choisie pour l'adulte existe dans la liste des payeurs, on l'applique
    if (Array.from(payerSelect.options).some(o => o.value === this.value)) {
        payerSelect.value = this.value;
    }
});

// --- LOGIQUE DES FILTRES ---
function applyGiftFilters() {
    const fChild = document.getElementById('filterChild').value;
    const fAdult = document.getElementById('filterAdult').value;

    document.querySelectorAll('.js-occ-wrapper').forEach(occWrapper => {
        let hasVisibleChildrenInOccasion = false;

        occWrapper.querySelectorAll('.js-child-section').forEach(childSec => {
            const cName = childSec.dataset.child;
            const matchChild = (fChild === 'all' || cName === fChild);
            let visibleCount = 0;

            childSec.querySelectorAll('.js-gift-card').forEach(card => {
                const matchAdult = (fAdult === 'all' || card.dataset.adult === fAdult);
                if (matchChild && matchAdult) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            childSec.querySelectorAll('.js-pill-adult').forEach(pill => {
                pill.style.opacity = (fAdult === 'all' || pill.dataset.adult === fAdult) ? '1' : '0.3';
            });

            const emptyState = childSec.querySelector('.js-empty-state');
            if (matchChild) {
                childSec.style.display = 'block';
                hasVisibleChildrenInOccasion = true;
                if (emptyState) emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
            } else {
                childSec.style.display = 'none';
            }
        });

        // Masquer complètement la fête (ex: Tió) s'il n'y a plus aucun enfant visible dedans
        occWrapper.style.display = hasVisibleChildrenInOccasion ? 'block' : 'none';
    });
}

// --- LOGIQUE DE LA MODALE ---
const modal = document.getElementById('pf-gift-modal');
const adultSelect = document.getElementById('m_adult');
const payerSelect = document.getElementById('m_payer');

function populateSelects(adults) {
    adultSelect.innerHTML = '';
    payerSelect.innerHTML = '';
    adults.forEach(name => {
        adultSelect.appendChild(new Option(name, name));
        payerSelect.appendChild(new Option(name, name));
    });
}

function openGiftModal(mode, data) {
    if (mode === 'add') {
        document.getElementById('m_action').value = 'create';
        document.getElementById('m_id').value = '';
        document.getElementById('m_child').value = data.child;
        document.getElementById('m_occ').value = data.occ; // L'occasion est passée par le bouton !
        document.getElementById('m_gift').value = '';
        document.getElementById('m_amount').value = '';
        document.getElementById('m_link').value = '';
        
        populateSelects(data.adults);
        document.getElementById('pf-modal-title').textContent = (window.I18N && window.I18N['gift_modal_title_add'] ? window.I18N['gift_modal_title_add'] : 'Ajouter pour %s').replace('%s', data.child);
    } 
    else if (mode === 'edit') {
        document.getElementById('m_action').value = 'update';
        document.getElementById('m_id').value = data.id;
        document.getElementById('m_child').value = data.child_name;
        document.getElementById('m_occ').value = data.occasion;
        document.getElementById('m_gift').value = data.gift_description;
        document.getElementById('m_amount').value = data.amount;
        document.getElementById('m_link').value = data.product_link;

        const payerVal = data.payer_name || data.adult_name;
        if (!Array.from(adultSelect.options).some(o => o.value === data.adult_name)) {
            adultSelect.appendChild(new Option(data.adult_name, data.adult_name));
        }
        if (!Array.from(payerSelect.options).some(o => o.value === payerVal)) {
            payerSelect.appendChild(new Option(payerVal, payerVal));
        }
        
        adultSelect.value = data.adult_name;
        payerSelect.value = payerVal;
        
        document.getElementById('pf-modal-title').textContent = window.I18N && window.I18N['gift_modal_title_edit'] ? window.I18N['gift_modal_title_edit'] : 'Modifier le cadeau';
    }

    modal.classList.add('open');
    document.body.classList.add('no-scroll');
}

function closeGiftModal() {
    modal.classList.remove('open');
    document.body.classList.remove('no-scroll');
}

// --- SOUMISSION AJAX ---
document.getElementById('pf-gift-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const oldText = btn.innerText;
    btn.innerText = '...'; btn.disabled = true;

    try {
        await fetch(e.target.action, { method: 'POST', body: new FormData(e.target) });
        window.location.reload();
    } catch(err) {
        console.error(err);
        btn.innerText = oldText; btn.disabled = false;
    }
});

// --- SUPPRESSION AJAX ---
async function deleteGift(id) {
    const msg = window.I18N && window.I18N['gift_confirm_delete'] ? window.I18N['gift_confirm_delete'] : 'Supprimer ce cadeau ?';
    if (!confirm(msg)) return;
    
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('gift_id', id);
    
    try {
        await fetch('/modules/gift-list/save-gift.php', { method: 'POST', body: fd });
        window.location.reload();
    } catch(err) {
        console.error(err);
    }
}
</script>

<?php require __DIR__ . '/footer.php'; ?>