<?php
require __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/meta_db.php';
require_once __DIR__ . '/../includes/i18n.php';

// ─── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle_user' && $id) {
        $meta_pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    } elseif ($action === 'toggle_family' && $id) {
        $meta_pdo->prepare("UPDATE families SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    } elseif ($action === 'toggle_admin' && $id) {
        // Ne peut pas se retirer ses propres droits admin
        if ($id !== (int)$_SESSION['user']['id']) {
            $meta_pdo->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?")->execute([$id]);
        }
    } elseif ($action === 'delete_user' && $id) {
        if ($id !== (int)$_SESSION['user']['id']) {
            $meta_pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
    } elseif ($action === 'regen_invite' && $id) {
        $new_code = bin2hex(random_bytes(8));
        $meta_pdo->prepare("UPDATE families SET invite_code = ? WHERE id = ?")->execute([$new_code, $id]);
    }
    header('Location: /admin/');
    exit;
}

// ─── Données ──────────────────────────────────────────────────────────────────
$families = $meta_pdo->query("
    SELECT f.*, COUNT(u.id) as member_count
    FROM families f
    LEFT JOIN users u ON u.family_id = f.id
    GROUP BY f.id
    ORDER BY f.id
")->fetchAll();

$users = $meta_pdo->query("
    SELECT u.*, f.name as family_name
    FROM users u
    LEFT JOIN families f ON f.id = u.family_id
    ORDER BY u.id
")->fetchAll();

$pageTitle = "Admin — HouseHub";
$activePage = "admin";
require __DIR__ . '/../header.php';
?>

<div class="pf-container" style="max-width:1000px;margin:32px auto;padding:0 16px">

  <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:4px">⚙️ Panneau d'administration</h1>
  <p style="color:#64748b;margin-bottom:32px">Gestion des espaces familiaux et des utilisateurs</p>

  <!-- ── Familles ─────────────────────────────────────────────────────────── -->
  <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:16px">
    🏠 Espaces familiaux
    <span style="background:#e2e8f0;border-radius:12px;padding:2px 10px;font-size:0.85rem;font-weight:500;margin-left:8px"><?= count($families) ?></span>
  </h2>

  <div style="overflow-x:auto;margin-bottom:40px">
    <table style="width:100%;border-collapse:collapse;font-size:0.9rem">
      <thead>
        <tr style="background:#f8fafc;text-align:left">
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">#</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Nom</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">DB</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Code invitation</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Membres</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Statut</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($families as $f): ?>
        <tr style="border-bottom:1px solid var(--border-light)">
          <td style="padding:10px 14px;color:#94a3b8"><?= $f['id'] ?></td>
          <td style="padding:10px 14px;font-weight:600"><?= htmlspecialchars($f['name']) ?></td>
          <td style="padding:10px 14px"><code style="font-size:0.8rem;background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= $f['db_name'] ?></code></td>
          <td style="padding:10px 14px">
            <code style="font-size:0.85rem"><?= $f['invite_code'] ?></code>
          </td>
          <td style="padding:10px 14px;text-align:center"><?= $f['member_count'] ?></td>
          <td style="padding:10px 14px">
            <span style="padding:3px 10px;border-radius:12px;font-size:0.8rem;font-weight:600;
              background:<?= $f['is_active'] ? '#dcfce7' : '#fee2e2' ?>;
              color:<?= $f['is_active'] ? '#16a34a' : '#dc2626' ?>">
              <?= $f['is_active'] ? 'Actif' : 'Inactif' ?>
            </span>
          </td>
          <td style="padding:10px 14px;display:flex;gap:8px;flex-wrap:wrap">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle_family">
              <input type="hidden" name="id" value="<?= $f['id'] ?>">
              <button class="pf-btn btn-secondary" style="padding:4px 12px;font-size:0.8rem">
                <?= $f['is_active'] ? 'Désactiver' : 'Activer' ?>
              </button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="regen_invite">
              <input type="hidden" name="id" value="<?= $f['id'] ?>">
              <button class="pf-btn btn-secondary" style="padding:4px 12px;font-size:0.8rem"
                onclick="return confirm('Regénérer le code ?')">
                🔄 Code
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Utilisateurs ─────────────────────────────────────────────────────── -->
  <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:16px">
    👤 Utilisateurs
    <span style="background:#e2e8f0;border-radius:12px;padding:2px 10px;font-size:0.85rem;font-weight:500;margin-left:8px"><?= count($users) ?></span>
  </h2>

  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:0.9rem">
      <thead>
        <tr style="background:#f8fafc;text-align:left">
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">#</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Utilisateur</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Espace famille</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Rôle</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Statut</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Créé le</th>
          <th style="padding:10px 14px;border-bottom:2px solid #e2e8f0">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): $isSelf = $u['id'] == $_SESSION['user']['id']; ?>
        <tr style="border-bottom:1px solid var(--border-light)<?= $isSelf ? ';background:#fafff4' : '' ?>">
          <td style="padding:10px 14px;color:#94a3b8"><?= $u['id'] ?></td>
          <td style="padding:10px 14px">
            <strong><?= htmlspecialchars($u['display_name']) ?></strong>
            <span style="color:#94a3b8;font-size:0.85rem;margin-left:6px">@<?= htmlspecialchars($u['username']) ?></span>
            <?php if ($isSelf): ?><span style="font-size:0.75rem;color:#2563eb;margin-left:4px">(vous)</span><?php endif; ?>
          </td>
          <td style="padding:10px 14px"><?= $u['family_name'] ? htmlspecialchars($u['family_name']) : '<span style="color:#94a3b8">—</span>' ?></td>
          <td style="padding:10px 14px">
            <?php if ($u['is_admin']): ?>
              <span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:10px;font-size:0.8rem;font-weight:600">Admin</span>
            <?php else: ?>
              <span style="color:#94a3b8;font-size:0.85rem">Membre</span>
            <?php endif; ?>
          </td>
          <td style="padding:10px 14px">
            <span style="padding:3px 10px;border-radius:12px;font-size:0.8rem;font-weight:600;
              background:<?= $u['is_active'] ? '#dcfce7' : '#fee2e2' ?>;
              color:<?= $u['is_active'] ? '#16a34a' : '#dc2626' ?>">
              <?= $u['is_active'] ? 'Actif' : 'Inactif' ?>
            </span>
          </td>
          <td style="padding:10px 14px;color:#64748b;font-size:0.85rem"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td style="padding:10px 14px">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php if (!$isSelf): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="pf-btn btn-secondary" style="padding:4px 10px;font-size:0.8rem">
                  <?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>
                </button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="pf-btn btn-secondary" style="padding:4px 10px;font-size:0.8rem">
                  <?= $u['is_admin'] ? '↓ Membre' : '↑ Admin' ?>
                </button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Supprimer <?= htmlspecialchars($u['username']) ?> ?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="pf-btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;color:#dc2626">🗑</button>
              </form>
              <?php else: ?>
              <span style="color:#94a3b8;font-size:0.8rem">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require __DIR__ . '/../footer.php'; ?>
