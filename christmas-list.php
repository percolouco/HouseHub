<?php
// christmas-list.php

// Protection : nécessite d'être connecté
require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Connexion DB
require __DIR__ . '/includes/db.php';

// Config page
$pageTitle  = "PachaFamily - Llista de regals";
$activePage = "christmas-list";
$bodyClass  = "pf-christmas-list";
$pageCss    = "/modules/christmas-list/christmas-list.css";

require __DIR__ . '/header.php';

// Constantes “théoriques” pour la grille
$year      = (int)date('Y');
$adults    = ['Laia', 'Laura', 'Avi Iaia'];
$children  = ['Pol', 'Pep', 'Elna', 'Bru', 'Guim'];

// IMPORTANT : aligner les codes avec ta BDD (enum('TIO','NOEL','ROIS'))
$allOccasionLabels = [
    'TIO'  => 'Tió',
    'NOEL' => 'Nadal',
    'ROIS' => 'Reis',
];

$occasionIcons = [
  'TIO'  => '/modules/christmas-list/assets/img/tio.png',           
  'NOEL' => '/modules/christmas-list/assets/img/santa.png',
  'ROIS' => '/modules/christmas-list/assets/img/reis.png',
];

// Récup toutes les données pour l’année
$stmt = $pdo->prepare("
  SELECT * 
  FROM pf_christmas_gifts
  WHERE year = :year
  ORDER BY adult_name, child_name, occasion, created_at
");
$stmt->execute(['year' => $year]);
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si aucune donnée, on évite les notices plus bas
if (!$gifts) {
    $gifts = [];
}

// 1) Indexer par [occasion][child][adult] pour l'affichage par fête
$byOccasion = []; // [occasion][child][adult][] = gifts
foreach ($gifts as $gift) {
    $o = $gift['occasion'];
    $c = $gift['child_name'];
    $a = $gift['adult_name'];
    $byOccasion[$o][$c][$a][] = $gift;
}
?>

<div class="pf-container">
  <h1>Llista de regals <?= htmlspecialchars($year) ?></h1>

  <!-- =============================================================== -->
  <!--  VUE TABLEAU PAR FÊTE : mini-tableaux par enfant (totaux en tête) -->
  <!-- =============================================================== -->
  <section class="pf-section pf-section--panel">
    <h2>Vista per festa</h2>

    <?php if (empty($gifts)): ?>
      <p>No hi ha cap regal registrat per a <?= htmlspecialchars($year) ?>.</p>
    <?php endif; ?>

    <?php foreach ($allOccasionLabels as $occCode => $occLabel): ?>
      <div class="cl-occasion-block">
        <h3 class="cl-occasion-title">
  <?php if (!empty($occasionIcons[$occCode])): ?>
    <img class="cl-occasion-icon"
         src="<?= htmlspecialchars($occasionIcons[$occCode]) ?>"
         alt=""
         aria-hidden="true">
  <?php endif; ?>
  <?= htmlspecialchars($occLabel) ?>
</h3>


        <div class="cl-occasion-children-tables">
          <?php
            // Afficher tous les enfants (même vides) pour garder l'ordre fixe
            $childrenForOccasion = $children;

            foreach ($childrenForOccasion as $childName):
              // Prépare les listes par adulte pour cet enfant et cette fête
              $lists = [];
              $totals = [];
              foreach ($adults as $adultName) {
                $lists[$adultName] = $byOccasion[$occCode][$childName][$adultName] ?? [];
                $totals[$adultName] = array_sum(array_map(
                  fn($g) => (float)$g['amount'],
                  $lists[$adultName]
                ));
              }
              // Nombre de lignes: max des tailles de listes Laia/Laura/Avi Iaia
              $maxRowsChild = max(array_map('count', $lists));
          ?>
          <table class="cl-child-table child-<?= strtolower($childName) ?>">
            <colgroup>
              <col class="cl-col" />
              <col class="cl-col" />
              <col class="cl-col" />
            </colgroup>

            <caption>
              <?= htmlspecialchars($childName) ?>
              <button
                type="button"
                class="cl-child-add-btn"
                title="Afegeix un regal"
                data-year="<?= $year ?>"
                data-child="<?= htmlspecialchars($childName) ?>"
                data-occasion="<?= htmlspecialchars($occCode) ?>"
              >+</button>
            </caption>

            <thead>
              <tr>
                <?php foreach ($adults as $adultName): ?>
                  <th>
                    <div class="cl-th-inner">
                      <span class="cl-th-label"><?= htmlspecialchars($adultName) ?></span>
                      <span class="cl-summary-adult-total">
                        <?= number_format($totals[$adultName], 0, ',', ' ') ?> €
                      </span>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>

            <tbody>
              <?php if ($maxRowsChild === 0): ?>
                <tr>
                  <td><span class="cl-empty">—</span></td>
                  <td><span class="cl-empty">—</span></td>
                  <td><span class="cl-empty">—</span></td>
                </tr>
              <?php else: ?>
                <?php for ($i = 0; $i < $maxRowsChild; $i++): ?>
                  <tr>
                    <?php foreach ($adults as $adultName): ?>
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
                              <!-- Nom du cadeau (lien ou texte) -->
                              <?php if ($plink !== ''): ?>
                                <a href="<?= htmlspecialchars($plink) ?>"
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  class="cl-gift-link"><?= $desc ?></a>
                              <?php else: ?>
                                <span class="cl-gift-desc"><?= $desc ?></span>
                              <?php endif; ?>

                              <!-- Zone de droite: prix ou icônes (selon hover/clic) -->
                              <div class="cl-gift-right">
                                <span class="cl-gift-amount">(<?= number_format($amt, 0, ',', ' ') ?> €)</span>

                                <span class="cl-gift-actions">
                                  <!-- Edit -->
                                  <button type="button"
                                          class="cl-gift-action-btn cl-gift-edit"
                                          title="Edita"
                                          aria-label="Edita"
                                          data-id="<?= $giftId ?>"
                                          data-year="<?= $year ?>"
                                          data-child="<?= htmlspecialchars($childName) ?>"
                                          data-occasion="<?= htmlspecialchars($occCode) ?>"
                                          data-adult="<?= htmlspecialchars($gift['adult_name']) ?>"
                                          data-payer="<?= htmlspecialchars($payer) ?>"
                                          data-desc="<?= htmlspecialchars($gift['gift_description']) ?>"
                                          data-amount="<?= htmlspecialchars($gift['amount']) ?>"
                                          data-link="<?= htmlspecialchars($gift['product_link'] ?? '') ?>">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true">
                                      <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
                                    </svg>
                                  </button>

                                  <!-- Delete -->
                                  <button type="button"
                                          class="cl-gift-action-btn cl-gift-delete"
                                          title="Eliminar"
                                          aria-label="Eliminar"
                                          data-id="<?= $giftId ?>">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true">
                                      <path d="M9 3h6a1 1 0 0 1 1 1v2h3a1 1 0 1 1 0 2h-1l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8H4a1 1 0 1 1 0-2h3V4a1 1 0 0 1 1-1zm-1 5h2v10H8V8zm4 0h2v10h-2V8z"/>
                                    </svg>
                                  </button>
                                </span>
                              </div>
                            </div>

                            <?php if (!empty($payer) && $payer !== $gift['adult_name']): ?>
                              <small style="color:#b91c1c; font-style:italic;">
                                (pagat per <?= htmlspecialchars($payer) ?>)
                              </small>
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

  <!-- =============================================================== -->
  <!--  RÉSUMÉ DU BUDGET (AGGRÉGÉ)                                     -->
  <!-- =============================================================== -->
  <section class="pf-section pf-section--panel">
    <h2>Resum del pressupost</h2>
    <?php
      $stmtSum = $pdo->prepare("
        SELECT adult_name, child_name, occasion, SUM(amount) AS total
        FROM pf_christmas_gifts
        WHERE year = :year
        GROUP BY adult_name, child_name, occasion
        ORDER BY adult_name, child_name, occasion
      ");
      $stmtSum->execute(['year' => $year]);
      $sums = $stmtSum->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="cl-budget-wrapper">
      <table class="pf-table pf-table--compact">
        <thead>
          <tr>
            <th>Adult</th>
            <th>Infant</th>
            <th>Festa</th>
            <th>Total</th>
          </tr>
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



  <!-- =============================================================== -->
  <!--  Calcul des dettes pour l'année en cours                        -->
  <!-- =============================================================== -->
  <?php
  // Calcul des dettes pour l'année en cours
  $people = $adults;
  // Inclure tout adulte qui apparaîtrait en base (sécurité)
  $adultsInDb = array_values(array_unique(array_column($gifts, 'adult_name')));
  $payersInDb = array_values(array_unique(array_column($gifts, 'payer_name')));
  $people = array_values(array_unique(array_merge($people, $adultsInDb, $payersInDb)));

  // Matrice brute: qui doit à qui (adult -> payer)
  $matrix = [];
  foreach ($people as $p1) {
    foreach ($people as $p2) {
      if (!isset($matrix[$p1])) $matrix[$p1] = [];
      $matrix[$p1][$p2] = 0.0;
    }
  }

  foreach ($gifts as $g) {
    $adult = $g['adult_name'];
    $payer = $g['payer_name'] ?? $g['adult_name'];
    $amt   = (float)$g['amount'];
    if ($amt > 0 && $adult !== $payer) {
      $matrix[$adult][$payer] += $amt;
    }
  }

  // Net pairwise (A<->B)
  $settlements = []; // [ [from, to, amount], ... ]
  for ($i = 0; $i < count($people); $i++) {
    for ($j = $i + 1; $j < count($people); $j++) {
      $a = $people[$i];
      $b = $people[$j];
      $net = $matrix[$a][$b] - $matrix[$b][$a];
      if ($net > 0.009) {
        $settlements[] = [$a, $b, $net]; // a doit à b
      } elseif ($net < -0.009) {
        $settlements[] = [$b, $a, -$net]; // b doit à a
      }
    }
  }
  ?>

  <section class="pf-section pf-section--panel">
    <h2>Tricount</h2>
    
    <div class="cl-budget-wrapper">
      <table class="pf-table pf-table--compact cl-debt-matrix">
        <thead>
          <tr>
            <th class="cl-matrix-corner">
              <span>Deutor ↓</span>
              <span>Creditor →</span>
            </th>
            <?php foreach ($people as $p): ?>
              <th><?= htmlspecialchars($p) ?></th>
            <?php endforeach; ?>
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
                  $display = $isDiag || $val == 0 ? '—' : number_format($val, 0, ',', ' ') . ' €';
                  $cls = $isDiag ? 'cl-mtx-diag' : ($val > 0 ? 'cl-mtx-owe' : 'cl-mtx-empty');
                ?>
                <td class="<?= $cls ?>"><?= $display ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 style="margin-top:10px;">Liquidacions</h3>
    <?php if (empty($settlements)): ?>
      <p class="cl-legend">Cap deute pendent.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($settlements as [$from, $to, $amt]): ?>
          <li><?= htmlspecialchars($from) ?> ha de pagar <?= number_format($amt, 0, ',', ' ') ?> € a <?= htmlspecialchars($to) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>




  <!-- =============================================================== -->
  <!--  LISTE DÉTAILLÉE DES CADEAUX (vue "debug"/admin")               -->
  <!-- =============================================================== -->
  <section class="pf-section pf-section--panel">
    <h2>Llista detallada de regals</h2>
    <div class="cl-detail-wrapper">
      <table class="pf-table pf-table--compact">
        <thead>
          <tr>
            <th>Adult</th>
            <th>Infant</th>
            <th>Festa</th>
            <th>Regal</th>
            <th>€</th>
            <th>Enllaç</th>
          </tr>
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

<!-- JS inline : modale d'ajout de cadeau -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('cl-gift-modal');
  const backdrop = modal.querySelector('.cl-modal-backdrop');
  const cancelBtn = modal.querySelector('.clm-cancel');

  // Ouverture modale en mode création (bouton + enfant)
  document.querySelectorAll('.cl-child-add-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const year     = btn.getAttribute('data-year');
      const child    = btn.getAttribute('data-child');
      const occasion = btn.getAttribute('data-occasion');

      modal.classList.add('cl-open');

      // Mode création
      document.getElementById('clm-action').value = 'create';
      document.getElementById('clm-id').value = '';

      // Contexte
      document.getElementById('clm-year').value     = year;
      document.getElementById('clm-child').value    = child;
      document.getElementById('clm-occasion').value = occasion;

      // Reset
      const adultSelect = document.getElementById('clm-adult');
      const payerSelect = document.getElementById('clm-payer');
      adultSelect.selectedIndex = 0;
      payerSelect.value = adultSelect.value;
      document.getElementById('clm-gift').value   = '';
      document.getElementById('clm-amount').value = '';
      document.getElementById('clm-link').value   = '';

      // Titre
      const titleEl = document.getElementById('cl-modal-title');
      titleEl.textContent = `Afegeix un regal per ${child}`;
    });
  });

  // Ouverture modale en mode édition (icône crayon)
  document.querySelectorAll('.cl-gift-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const dataset = btn.dataset;

      modal.classList.add('cl-open');

      // Mode édition
      document.getElementById('clm-action').value = 'update';
      document.getElementById('clm-id').value = dataset.id;

      // Contexte
      document.getElementById('clm-year').value     = dataset.year;
      document.getElementById('clm-child').value    = dataset.child;
      document.getElementById('clm-occasion').value = dataset.occasion;

      // Champs visibles
      document.getElementById('clm-adult').value = dataset.adult;
      document.getElementById('clm-payer').value = dataset.payer;
      document.getElementById('clm-gift').value  = dataset.desc;
      document.getElementById('clm-amount').value = dataset.amount || '';
      document.getElementById('clm-link').value   = dataset.link || '';

      // Titre
      const titleEl = document.getElementById('cl-modal-title');
      titleEl.textContent = `Edita un regal per ${dataset.child}`;
    });
  });

  // Suppression (icône poubelle)
  const deleteForm = document.getElementById('cl-delete-form');
  const deleteIdInput = document.getElementById('cld-id');

  document.querySelectorAll('.cl-gift-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const giftId = btn.getAttribute('data-id');
      if (!giftId) return;

      if (confirm('Vols eliminar aquest regal?')) {
        deleteIdInput.value = giftId;
        deleteForm.submit();
      }
    });
  });

  function closeModal() { modal.classList.remove('cl-open'); }
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
});

document.addEventListener('DOMContentLoaded', function () {
  // Toggle mobile: tap sur la zone droite bascule les icônes
  document.querySelectorAll('.cl-gift-right').forEach(function (zone) {
    zone.addEventListener('click', function (e) {
      // Si on clique une icône (bouton), ne pas toggler la zone
      if (e.target.closest('.cl-gift-action-btn')) return;
      // Fermer les autres
      document.querySelectorAll('.cl-gift-right.is-active').forEach(function (z) {
        if (z !== zone) z.classList.remove('is-active');
      });
      // Bascule sur la zone courante
      zone.classList.toggle('is-active');
    });
  });
  // Option: clic en dehors -> referme
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.cl-gift-right')) {
      document.querySelectorAll('.cl-gift-right.is-active').forEach(function (z) {
        z.classList.remove('is-active');
      });
    }
  }, true);
});
</script>


<!-- Modal d'ajout de cadeau -->
<div id="cl-gift-modal" class="cl-modal" aria-hidden="true">
  <div class="cl-modal-backdrop"></div>
  <div class="cl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cl-modal-title">
    <form method="post" action="/modules/christmas-list/save-gift.php" class="cl-modal-form">
  <h3 id="cl-modal-title">Afegeix un regal</h3>

  <input type="hidden" name="year" id="clm-year" value="<?= $year ?>">
  <input type="hidden" name="child_name" id="clm-child" value="">
  <input type="hidden" name="occasion" id="clm-occasion" value="">
  <input type="hidden" name="action" id="clm-action" value="create">
  <input type="hidden" name="gift_id" id="clm-id" value="">


  <label class="clm-label">
    Adult
    <select name="adult_name" id="clm-adult" required>
      <?php foreach ($adults as $adultName): ?>
        <option value="<?= htmlspecialchars($adultName) ?>"><?= htmlspecialchars($adultName) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label class="clm-label">
    Pagat per
    <select name="payer_name" id="clm-payer" required>
      <?php foreach ($adults as $adultName): ?>
        <option value="<?= htmlspecialchars($adultName) ?>"><?= htmlspecialchars($adultName) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label class="clm-label">
    Nom del regal
    <input type="text" name="gift_description" id="clm-gift" placeholder="p. ex., Lego Star Wars" required>
  </label>

  <label class="clm-label">
    Preu (€)
    <input type="number" name="amount" id="clm-amount" placeholder="p. ex., 49,99" step="0.01" min="0">
  </label>

  <label class="clm-label">
    Enllaç (opcional)
    <input type="url" name="product_link" id="clm-link" placeholder="https://exemple.com/producte">
  </label>

  <div class="cl-modal-actions">
    <button type="button" class="clm-cancel">Cancel·la</button>
    <button type="submit" class="clm-ok">OK</button>
  </div>
</form>

  </div>
</div>

<form id="cl-delete-form" method="post" action="/modules/christmas-list/save-gift.php" style="display:none">
  <input type="hidden" name="year" value="<?= $year ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="gift_id" id="cld-id" value="">
</form>


<?php
require __DIR__ . '/footer.php';
