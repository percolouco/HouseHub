<?php
// modules/gift-list/gift-list.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. CONFIGURATION & DONNÉES ---

$year       = (int)date('Y');
$pageTitle  = "PachaFamily - Llista de regals";
$activePage = "gift-list";
$bodyClass  = "pf-gift-list";
$pageCss    = "/modules/gift-list/gift-list.css";

// Personnes
$baseAdults = ['Laia', 'Laura', 'Avi Iaia'];
$children   = ['Pol', 'Pep', 'Elna', 'Bru', 'Guim'];

// Configuration des Vues
$VIEWS = [
    'nadal'       => ['TIO', 'NOEL', 'ROIS'],
    'anniversary' => ['ANNIV', 'SANT'],
];

// Vue courante
$currentView = strtolower($_GET['view'] ?? ($_SESSION['gift_view'] ?? 'nadal'));
if (!isset($VIEWS[$currentView])) $currentView = 'nadal';
$_SESSION['gift_view'] = $currentView;

$allowedOccasions = $VIEWS[$currentView];

// Logique spécifique : Adultes supplémentaires pour Anniversaires
$extraAdults = ['Pauline', 'Papy JC', 'Mamy Caro'];
$adultsByChildForAnniv = [
    'Pol'  => array_merge($baseAdults, $extraAdults),
    'Pep'  => array_merge($baseAdults, $extraAdults),
    'Elna' => $baseAdults,
    'Bru'  => $baseAdults,
    'Guim' => $baseAdults,
];

// Labels & Icônes
$allOccasionLabels = [
    'TIO'   => 'Tió',
    'NOEL'  => 'Nadal',
    'ROIS'  => 'Reis',
    'ANNIV' => 'Anniversary',
    'SANT'  => 'Sant',
];
$occasionIcons = [
    'TIO'   => '/modules/gift-list/assets/img/tio.png',
    'NOEL'  => '/modules/gift-list/assets/img/santa.png',
    'ROIS'  => '/modules/gift-list/assets/img/reis.png',
    'ANNIV' => '/modules/gift-list/assets/img/corona.png',
    'SANT'  => '/modules/gift-list/assets/img/sant.png',
];

$tableGifts = 'pf_gifts';

// --- 2. RÉCUPÉRATION DES DONNÉES ---

// Préparation requête
$inMarks = implode(',', array_fill(0, count($allowedOccasions), '?'));
$sql = "SELECT * FROM {$tableGifts} WHERE year = ? AND occasion IN ($inMarks) ORDER BY adult_name, child_name, occasion, created_at";
$stmt = $pdo->prepare($sql);
$params = array_merge([$year], $allowedOccasions);
$stmt->execute($params);
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Indexation [occasion][child][adult] pour la matrice
$byOccasion = [];
foreach ($gifts as $gift) {
    $byOccasion[$gift['occasion']][$gift['child_name']][$gift['adult_name']][] = $gift;
}

// Occasions à afficher
$occasionsToShow = array_values(array_intersect(array_keys($allOccasionLabels), $allowedOccasions));

// --- 3. CALCUL TRICOUNT (Backend) ---
// On pré-calcule ici pour alléger la vue HTML

$people = $baseAdults;
$adultsInDb = array_column($gifts, 'adult_name');
$payersInDb = array_column($gifts, 'payer_name'); // Peut contenir des NULL si colonne vide, mais array_unique gère
// Nettoyage et fusion des participants
$people = array_values(array_unique(array_merge($people, $adultsInDb, $payersInDb)));
$people = array_filter($people); // Enlève les vides éventuels

// Matrice de dettes
$matrix = [];
foreach ($people as $p1) {
    foreach ($people as $p2) $matrix[$p1][$p2] = 0.0;
}

foreach ($gifts as $g) {
    $adult = $g['adult_name'];
    $payer = $g['payer_name'] ?? $g['adult_name'];
    $amt   = (float)$g['amount'];
    
    // Si payé par quelqu'un d'autre que le bénéficiaire (l'adulte responsable)
    if ($amt > 0 && $adult && $payer && $adult !== $payer) {
        if (isset($matrix[$adult][$payer])) {
            $matrix[$adult][$payer] += $amt;
        }
    }
}

// Résolution des dettes (Liquidations)
$settlements = [];
$countPeople = count($people);
for ($i = 0; $i < $countPeople; $i++) {
    for ($j = $i + 1; $j < $countPeople; $j++) {
        $a = $people[$i];
        $b = $people[$j];
        $net = $matrix[$a][$b] - $matrix[$b][$a];
        
        if ($net > 0.01) {
            $settlements[] = ['from' => $a, 'to' => $b, 'amount' => $net];
        } elseif ($net < -0.01) {
            $settlements[] = ['from' => $b, 'to' => $a, 'amount' => -$net];
        }
    }
}

// --- 4. DÉBUT DU RENDU HTML ---
require __DIR__ . '/header.php';
?>

<div class="pf-container cl-view-<?= htmlspecialchars($currentView) ?>">
    
    <div class="cl-titlebar">
        <h1>Llista de regals <?= htmlspecialchars($year) ?></h1>
        <div class="cl-view-switch" aria-label="Canvia la vista">
            <a href="?view=nadal" class="cl-view-btn <?= $currentView === 'nadal' ? 'is-active' : '' ?>">Nadal</a>
            <a href="?view=anniversary" class="cl-view-btn <?= $currentView === 'anniversary' ? 'is-active' : '' ?>">Anniversary</a>
        </div>
    </div>

    <section class="pf-section pf-section--panel">
        <h2>Vista per festa</h2>

        <?php if (empty($gifts)): ?>
            <p class="cl-legend">No hi ha cap regal registrat per a <?= htmlspecialchars($year) ?> en aquesta vista.</p>
        <?php endif; ?>

        <?php foreach ($occasionsToShow as $occCode): ?>
            <div class="cl-occasion-block">
                <h3 class="cl-occasion-title">
                    <?php if (!empty($occasionIcons[$occCode])): ?>
                        <img class="cl-occasion-icon" src="<?= htmlspecialchars($occasionIcons[$occCode]) ?>" alt="" aria-hidden="true">
                    <?php endif; ?>
                    <?= htmlspecialchars($allOccasionLabels[$occCode] ?? $occCode) ?>
                </h3>

                <div class="cl-occasion-children-tables">
                    <?php foreach ($children as $childName): ?>
                        <?php
                            // Détermine quels adultes participent pour cet enfant
                            $adultsForChild = ($currentView === 'anniversary')
                                ? ($adultsByChildForAnniv[$childName] ?? $baseAdults)
                                : $baseAdults;

                            // Prépare listes/total par adulte
                            $lists  = [];
                            $totals = [];
                            foreach ($adultsForChild as $adultName) {
                                $lists[$adultName]  = $byOccasion[$occCode][$childName][$adultName] ?? [];
                                $totals[$adultName] = array_sum(array_column($lists[$adultName], 'amount'));
                            }
                            
                            // Calcul hauteur tableau
                            $counts = array_map('count', $lists);
                            $maxRowsChild = !empty($counts) ? max($counts) : 0;
                        ?>
                        
                        <table class="cl-child-table child-<?= strtolower($childName) ?>">
                            <colgroup>
                                <?php foreach ($adultsForChild as $_): ?>
                                    <col class="cl-col" />
                                <?php endforeach; ?>
                            </colgroup>

                            <caption>
                                <?= htmlspecialchars($childName) ?>
                                <button type="button" class="cl-child-add-btn" title="Afegeix un regal"
                                    data-year="<?= $year ?>"
                                    data-child="<?= htmlspecialchars($childName) ?>"
                                    data-occasion="<?= htmlspecialchars($occCode) ?>"
                                    data-adults="<?= htmlspecialchars(json_encode(array_values($adultsForChild)), ENT_QUOTES) ?>">
                                    +
                                </button>
                            </caption>

                            <thead>
                                <tr>
                                    <?php foreach ($adultsForChild as $adultName): ?>
                                        <th>
                                            <div class="cl-th-inner">
                                                <span class="cl-th-label"><?= htmlspecialchars($adultName) ?></span>
                                                <span class="cl-summary-adult-total"><?= number_format($totals[$adultName], 0, ',', ' ') ?> €</span>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if ($maxRowsChild === 0): ?>
                                    <tr>
                                        <?php foreach ($adultsForChild as $_): ?>
                                            <td><span class="cl-empty">—</span></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php else: ?>
                                    <?php for ($i = 0; $i < $maxRowsChild; $i++): ?>
                                        <tr>
                                            <?php foreach ($adultsForChild as $adultName): ?>
                                                <?php $gift = $lists[$adultName][$i] ?? null; ?>
                                                <td>
                                                    <?php if ($gift): ?>
                                                        <?php
                                                            $giftId = (int)$gift['id'];
                                                            $desc   = htmlspecialchars($gift['gift_description']);
                                                            $amt    = (float)$gift['amount'];
                                                            $plink  = trim($gift['product_link'] ?? '');
                                                            $payer  = $gift['payer_name'] ?? $gift['adult_name'];
                                                        ?>
                                                        <div class="cl-gift-item">
                                                            <div class="cl-gift-line">
                                                                <?php if ($plink !== ''): ?>
                                                                    <a href="<?= htmlspecialchars($plink) ?>" target="_blank" rel="noopener noreferrer" class="cl-gift-link"><?= $desc ?></a>
                                                                <?php else: ?>
                                                                    <span class="cl-gift-desc"><?= $desc ?></span>
                                                                <?php endif; ?>
                                                                
                                                                <div class="cl-gift-right">
                                                                    <span class="cl-gift-amount">(<?= number_format($amt, 0, ',', ' ') ?> €)</span>
                                                                    <span class="cl-gift-actions">
                                                                        <button type="button" class="cl-gift-action-btn cl-gift-edit" aria-label="Edita"
                                                                            data-id="<?= $giftId ?>"
                                                                            data-year="<?= $year ?>"
                                                                            data-child="<?= htmlspecialchars($childName) ?>"
                                                                            data-occasion="<?= htmlspecialchars($occCode) ?>"
                                                                            data-adult="<?= htmlspecialchars($gift['adult_name']) ?>"
                                                                            data-payer="<?= htmlspecialchars($payer) ?>"
                                                                            data-desc="<?= htmlspecialchars($gift['gift_description']) ?>"
                                                                            data-amount="<?= htmlspecialchars($gift['amount']) ?>"
                                                                            data-link="<?= htmlspecialchars($gift['product_link'] ?? '') ?>">
                                                                            ✎
                                                                        </button>
                                                                        <button type="button" class="cl-gift-action-btn cl-gift-delete" aria-label="Eliminar" data-id="<?= $giftId ?>">
                                                                            ×
                                                                        </button>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if (!empty($payer) && $payer !== $gift['adult_name']): ?>
                                                                <small style="color:#b91c1c; font-style:italic; display:block; font-size:0.75em;">(pagat per <?= htmlspecialchars($payer) ?>)</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="cl-empty">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="pf-section pf-section--panel">
        <h2>Resum del pressupost</h2>
        <?php
            // Calcul agrégé SQL pour vérification
            $stmtSum = $pdo->prepare("SELECT adult_name, child_name, occasion, SUM(amount) AS total FROM {$tableGifts} WHERE year = ? AND occasion IN ($inMarks) GROUP BY adult_name, child_name, occasion ORDER BY adult_name, child_name, occasion");
            $stmtSum->execute($params);
            $sums = $stmtSum->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="cl-budget-wrapper">
            <table class="pf-table pf-table--compact">
                <thead>
                    <tr><th>Adult</th><th>Infant</th><th>Festa</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sums as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['adult_name']) ?></td>
                            <td><?= htmlspecialchars($row['child_name']) ?></td>
                            <td><?= htmlspecialchars($allOccasionLabels[$row['occasion']] ?? $row['occasion']) ?></td>
                            <td><?= number_format($row['total'], 0, ',', ' ') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="pf-section pf-section--panel">
        <h2>Tricount</h2>
        <div class="cl-budget-wrapper">
            <table class="pf-table pf-table--compact cl-debt-matrix">
                <thead>
                    <tr>
                        <th class="cl-matrix-corner"><span>Deutor ↓</span><span>Creditor →</span></th>
                        <?php foreach ($people as $p): ?><th><?= htmlspecialchars($p) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $debtor): ?>
                        <tr>
                            <th><?= htmlspecialchars($debtor) ?></th>
                            <?php foreach ($people as $creditor): ?>
                                <?php
                                    $val = $matrix[$debtor][$creditor] ?? 0;
                                    $isDiag = ($debtor === $creditor);
                                    $cls = $isDiag ? 'cl-mtx-diag' : ($val > 0 ? 'cl-mtx-owe' : 'cl-mtx-empty');
                                    $display = $isDiag || $val == 0 ? '—' : number_format($val, 0, ',', ' ') . ' €';
                                ?>
                                <td class="<?= $cls ?>"><?= $display ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin-top:16px; font-size:1.1rem; color:#374151;">Liquidacions</h3>
        <?php if (empty($settlements)): ?>
            <p class="cl-legend">Cap deute pendent.</p>
        <?php else: ?>
            <ul class="hol-list">
                <?php foreach ($settlements as $s): ?>
                    <li><strong><?= htmlspecialchars($s['from']) ?></strong> ha de pagar <strong><?= number_format($s['amount'], 2, ',', ' ') ?> €</strong> a <?= htmlspecialchars($s['to']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="pf-section pf-section--panel">
        <h2>Llista detallada de regals</h2>
        <div class="cl-detail-wrapper">
            <table class="pf-table pf-table--compact">
                <thead>
                    <tr><th>Adult</th><th>Infant</th><th>Festa</th><th>Regal</th><th>€</th><th>Enllaç</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($gifts as $g): ?>
                        <tr>
                            <td><?= htmlspecialchars($g['adult_name']) ?></td>
                            <td><?= htmlspecialchars($g['child_name']) ?></td>
                            <td><?= htmlspecialchars($allOccasionLabels[$g['occasion']] ?? $g['occasion']) ?></td>
                            <td><?= htmlspecialchars($g['gift_description']) ?></td>
                            <td><?= number_format($g['amount'], 0, ',', ' ') ?></td>
                            <td>
                                <?php if (!empty($g['product_link'])): ?>
                                    <a href="<?= htmlspecialchars($g['product_link']) ?>" target="_blank">🔗</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('cl-gift-modal');
    const backdrop = modal ? modal.querySelector('.cl-modal-backdrop') : null;
    const cancelBtn = modal ? modal.querySelector('.clm-cancel') : null;

    function toggleModal(show) {
        if (!modal) return;
        modal.classList.toggle('cl-open', show);
    }

    // Gestion des options de select (Adultes dynamiques selon l'enfant)
    function populateSelects(adults) {
        const selects = [document.getElementById('clm-adult'), document.getElementById('clm-payer')];
        selects.forEach(sel => {
            if (!sel) return;
            sel.innerHTML = '';
            adults.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                sel.appendChild(opt);
            });
        });
    }

    // --- BOUTON AJOUT (+) ---
    document.querySelectorAll('.cl-child-add-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            let adults = [];
            try { adults = JSON.parse(d.adults || '[]'); } catch(e) {}

            populateSelects(adults);
            
            // Valeurs par défaut
            document.getElementById('clm-action').value = 'create';
            document.getElementById('clm-id').value = '';
            document.getElementById('clm-year').value = d.year;
            document.getElementById('clm-child').value = d.child;
            document.getElementById('clm-occasion').value = d.occasion;
            
            document.getElementById('clm-gift').value = '';
            document.getElementById('clm-amount').value = '';
            document.getElementById('clm-link').value = '';

            document.getElementById('cl-modal-title').textContent = `Afegeix un regal per ${d.child}`;
            toggleModal(true);
        });
    });

    // --- BOUTON ÉDITION (Crayon) ---
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.cl-gift-edit');
        if (!btn) return;

        const d = btn.dataset;
        // Pour l'édition, on s'assure que l'adulte actuel est dans la liste (même si pas standard)
        const adultSelect = document.getElementById('clm-adult');
        const payerSelect = document.getElementById('clm-payer');
        
        // On ne recharge pas toute la liste adults ici car complexe à récupérer du DOM, 
        // on ajoute juste l'option si manquante.
        [adultSelect, payerSelect].forEach(sel => {
            const val = (sel === adultSelect) ? d.adult : (d.payer || d.adult);
            if (!Array.from(sel.options).some(o => o.value === val)) {
                const opt = document.createElement('option');
                opt.value = val;
                opt.textContent = val;
                sel.appendChild(opt);
            }
            sel.value = val;
        });

        document.getElementById('clm-action').value = 'update';
        document.getElementById('clm-id').value = d.id;
        document.getElementById('clm-year').value = d.year;
        document.getElementById('clm-child').value = d.child;
        document.getElementById('clm-occasion').value = d.occasion;
        
        document.getElementById('clm-gift').value = d.desc;
        document.getElementById('clm-amount').value = d.amount;
        document.getElementById('clm-link').value = d.link;

        document.getElementById('cl-modal-title').textContent = `Edita el regal`;
        toggleModal(true);
    });

    // --- SUPPRESSION ---
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.cl-gift-delete');
        if (!btn) return;
        
        if (confirm('Vols eliminar aquest regal?')) {
            const form = document.getElementById('cl-delete-form');
            document.getElementById('cld-id').value = btn.dataset.id;
            form.submit();
        }
    });

    // Fermeture
    if(cancelBtn) cancelBtn.addEventListener('click', () => toggleModal(false));
    if(backdrop) backdrop.addEventListener('click', () => toggleModal(false));
    document.addEventListener('keydown', (e) => { if(e.key === 'Escape') toggleModal(false); });
});
</script>

<div id="cl-gift-modal" class="cl-modal" aria-hidden="true">
    <div class="cl-modal-backdrop"></div>
    <div class="cl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cl-modal-title">
        <form method="post" action="/modules/gift-list/save-gift.php" class="cl-modal-form">
            <h3 id="cl-modal-title">Regal</h3>

            <input type="hidden" name="year" id="clm-year">
            <input type="hidden" name="child_name" id="clm-child">
            <input type="hidden" name="occasion" id="clm-occasion">
            <input type="hidden" name="action" id="clm-action">
            <input type="hidden" name="gift_id" id="clm-id">

            <label class="clm-label">
                Adult
                <select name="adult_name" id="clm-adult" required></select>
            </label>

            <label class="clm-label">
                Pagat per
                <select name="payer_name" id="clm-payer" required></select>
            </label>

            <label class="clm-label">
                Nom del regal
                <input type="text" name="gift_description" id="clm-gift" placeholder="p. ex., Lego Star Wars" required>
            </label>

            <label class="clm-label">
                Preu (€)
                <input type="number" name="amount" id="clm-amount" placeholder="49.99" step="0.01" min="0">
            </label>

            <label class="clm-label">
                Enllaç (opcional)
                <input type="url" name="product_link" id="clm-link" placeholder="https://...">
            </label>

            <div class="cl-modal-actions">
                <button type="button" class="clm-cancel">Cancel·la</button>
                <button type="submit" class="clm-ok">Guardar</button>
            </div>
        </form>
    </div>
</div>

<form id="cl-delete-form" method="post" action="/modules/gift-list/save-gift.php" style="display:none">
    <input type="hidden" name="year" value="<?= $year ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="gift_id" id="cld-id" value="">
</form>

<?php require __DIR__ . '/footer.php'; ?>