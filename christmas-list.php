<?php
// Protection : nécessite d'être connecté
require __DIR__ . '/includes/auth.php';
require_login('/christmas-list.php');

// Connexion DB
require __DIR__ . '/includes/db.php';

// Config page
$pageTitle  = "PachaFamily - Christmas list";
$activePage = "christmas-list";
$bodyClass  = "pf-christmas-list";
$pageCss    = "/modules/christmas-list/christmas-list.css";

require __DIR__ . '/header.php';

// Constantes
$year      = (int)date('Y');
$adults    = ['Laia', 'Laura', 'Avi Iaia'];
$children  = ['Pol', 'Pep', 'Elna', 'Bru', 'Guim'];

$occasions = [
    'TIO'   => 'Tió',
    'NADAL' => 'Nadal',
    'REIS'  => 'Reis',
];

// Récup données
$stmt = $pdo->prepare("
  SELECT * 
  FROM pf_christmas_gifts
  WHERE year = :year
");
$stmt->execute(['year' => $year]);
$gifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indexer par [adult][child][occasion]
$byAdult = [];
foreach ($gifts as $gift) {
    $a = $gift['adult_name'];
    $c = $gift['child_name'];
    $o = $gift['occasion'];
    $byAdult[$a][$c][$o][] = $gift;
}
?>

<div class="pf-container">
  <h1>Christmas list <?= htmlspecialchars($year) ?></h1>
  <p>Visió sintètica dels regals per adult, infant i festa. Afegeix nous regals amb el botó “+”.</p>

  <section class="pf-section pf-section--panel">
    <div class="cl-grid-wrapper">
      <table class="pf-table cl-grid-table">
        <thead>
          <!-- Ligne 1 : cellule vide + noms des enfants (colspan=3) -->
          <tr>
            <th></th>
            <?php foreach ($children as $child): ?>
              <th colspan="<?= count($occasions) ?>" class="cl-child-header">
                <?= htmlspecialchars($child) ?>
              </th>
            <?php endforeach; ?>
          </tr>

          <!-- Ligne 2 : Adult + Tió / Nadal / Reis pour chaque enfant -->
          <tr>
            <th class="cl-header-adult">Adult</th>
            <?php foreach ($children as $child): ?>
              <?php foreach ($occasions as $occCode => $occLabel): ?>
                <th class="cl-header-occ"><?= htmlspecialchars($occLabel) ?></th>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($adults as $adult): ?>
            <tr>
              <!-- Colonne adulte -->
              <td class="cl-adult-cell"><?= htmlspecialchars($adult) ?></td>

              <!-- Colonnes (enfant x fête) -->
              <?php foreach ($children as $child): ?>
                <?php foreach ($occasions as $occCode => $occLabel): ?>
                  <?php
                    $cellGifts = $byAdult[$adult][$child][$occCode] ?? [];
                    $total     = array_sum(array_column($cellGifts, 'amount'));

                    // Id unique pour cette cellule (pour cibler le menu en JS)
                    $cellId = 'cell_' . md5($adult . '_' . $child . '_' . $occCode);
                  ?>
                  <td class="cl-gift-cell" data-cell-id="<?= $cellId ?>">
  <div class="cl-gift-cell-content">
    <?php if (!empty($cellGifts)): ?>
      <ul class="cl-gift-list">
        <?php foreach ($cellGifts as $gift): ?>
          <li>
            <?= htmlspecialchars($gift['gift_description']) ?>
            <?php if ($gift['amount'] > 0): ?>
              <span class="cl-gift-amount">
                (<?= number_format($gift['amount'], 0, ',', ' ') ?> €)
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <span class="cl-empty">– Cap regal</span>
    <?php endif; ?>

    <div class="cl-total-and-add">
      <div class="cl-gift-total">
        <?php if ($total > 0): ?>
          Total: <?= number_format($total, 0, ',', ' ') ?> €
        <?php else: ?>
          &nbsp;
        <?php endif; ?>
      </div>

      <button type="button"
              class="cl-add-btn"
              data-target="<?= $cellId ?>">
        +
      </button>
    </div>
  </div>

  <!-- Menu d'ajout caché au départ -->
  <div class="cl-add-menu" id="<?= $cellId ?>">
    <form method="post" action="/modules/christmas-list/save-gift.php" class="cl-gift-form">
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="adult_name" value="<?= htmlspecialchars($adult) ?>">
      <input type="hidden" name="child_name" value="<?= htmlspecialchars($child) ?>">
      <input type="hidden" name="occasion" value="<?= htmlspecialchars($occCode) ?>">

      <input type="text" name="gift_description" placeholder="Regal..." required>

      <div class="cl-gift-form-row">
        <input type="url" name="product_link" placeholder="Enllaç">
        <input type="number" name="amount" placeholder="€" step="0.01" min="0">
      </div>

      <div class="cl-gift-form-actions">
        <button type="button" class="cl-cancel-btn" data-target="<?= $cellId ?>">Cancel·lar</button>
        <button type="submit" class="cl-ok-btn">OK</button>
      </div>
    </form>
  </div>
</td>

                <?php endforeach; ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

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
              <td><?= htmlspecialchars($occasions[$row['occasion']] ?? $row['occasion']) ?></td>
              <td><?= number_format($row['total'], 0, ',', ' ') ?> €</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- JS inline simple pour ouvrir/fermer les menus d'ajout -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Ouvrir le menu associé au bouton +
  document.querySelectorAll('.cl-add-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const targetId = btn.getAttribute('data-target');
      const menu = document.getElementById(targetId);

      // Fermer les autres menus avant d'ouvrir celui-ci
      document.querySelectorAll('.cl-add-menu').forEach(function (m) {
        if (m.id !== targetId) {
          m.classList.remove('cl-add-menu--open');
        }
      });

      menu.classList.toggle('cl-add-menu--open');
    });
  });

  // Boutons Cancel·lar pour fermer le menu
  document.querySelectorAll('.cl-cancel-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const targetId = btn.getAttribute('data-target');
      const menu = document.getElementById(targetId);
      menu.classList.remove('cl-add-menu--open');
    });
  });
});
</script>

<?php
require __DIR__ . '/footer.php';
