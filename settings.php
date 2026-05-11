<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/meta_db.php';
require_once __DIR__ . '/includes/i18n.php';

$user_id   = $_SESSION['user']['id'];
$family_id = $_SESSION['user']['family_id'];

$error   = null;
$success = null;

// ─── Actions ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_lang') {
        $lang = $_POST['lang'] ?? 'fr';
        if (in_array($lang, ['fr', 'ca', 'en'])) {
            $_SESSION['app_lang'] = $lang;
            $meta_pdo->prepare("UPDATE users SET lang = ? WHERE id = ?")
                     ->execute([$lang, $user_id]);
            $success = "Language updated.";
        }
    }

    if ($action === 'update_profile') {
        $display_name = trim($_POST['display_name'] ?? '');
        if (!$display_name) {
            $error = "Le prénom ne peut pas être vide.";
        } else {
            $meta_pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?")
                     ->execute([$display_name, $user_id]);
            $_SESSION['user']['display_name'] = $display_name;
            $success = "Profil mis à jour.";
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $row = $meta_pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $row->execute([$user_id]);
        $row = $row->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            $error = "Mot de passe actuel incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "Le nouveau mot de passe doit faire au moins 6 caractères.";
        } elseif ($new !== $confirm) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $meta_pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                     ->execute([password_hash($new, PASSWORD_BCRYPT), $user_id]);
            $success = "Mot de passe modifié avec succès.";
        }
    }

    if ($action === 'update_family_name' && $family_id) {
        $name = trim($_POST['family_name'] ?? '');
        if ($name) {
            $meta_pdo->prepare("UPDATE families SET name = ? WHERE id = ?")
                     ->execute([$name, $family_id]);
            $success = "Nom de l'espace mis à jour.";
        }
    }

    if ($action === 'regen_invite' && $family_id) {
        $new_code = bin2hex(random_bytes(8));
        $meta_pdo->prepare("UPDATE families SET invite_code = ? WHERE id = ?")
                 ->execute([$new_code, $family_id]);
        $success = "Nouveau code d'invitation généré.";
    }
}

// ─── Chargement données ───────────────────────────────────────────────────────
$user = $meta_pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$family = null;
$members = [];
if ($family_id) {
    $fam = $meta_pdo->prepare("SELECT * FROM families WHERE id = ?");
    $fam->execute([$family_id]);
    $family = $fam->fetch();

    $mem = $meta_pdo->prepare("SELECT display_name, username, created_at, is_admin FROM users WHERE family_id = ? ORDER BY id");
    $mem->execute([$family_id]);
    $members = $mem->fetchAll();
}

$pageTitle = "Paramètres — HouseHub";
$activePage = "settings";
require __DIR__ . '/header.php';
?>

<div class="pf-container" style="max-width:680px;margin:32px auto;padding:0 16px">

  <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:32px">⚙️ Paramètres</h1>

  <?php if ($success): ?>
    <div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:24px;color:#166534">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:24px;color:#991b1b">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Langue ─────────────────────────────────────────────────────────── -->
  <section style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px">🌐 Langue / Language</h2>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="hidden" name="action" value="set_lang">
      <?php
        $langs = ['fr' => '🇫🇷 Français', 'en' => '🇬🇧 English', 'ca' => '🏴󠁥󠁳󠁣󠁴󠁿 Català'];
        $currentLang = $_SESSION['app_lang'] ?? 'fr';
        foreach ($langs as $code => $label):
      ?>
      <button type="submit" name="lang" value="<?= $code ?>"
        class="pf-btn <?= $currentLang === $code ? '' : 'btn-secondary' ?>"
        style="<?= $currentLang === $code ? 'pointer-events:none' : '' ?>">
        <?= $label ?>
      </button>
      <?php endforeach; ?>
    </form>
  </section>

  <!-- ── Profil ──────────────────────────────────────────────────────────── -->
  <section style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px">👤 Mon profil</h2>
    <form method="post">
      <input type="hidden" name="action" value="update_profile">
      <div class="pf-form-group">
        <label class="pf-label">Nom d'utilisateur</label>
        <input type="text" class="pf-input" value="<?= htmlspecialchars($user['username']) ?>" disabled style="background:#f8fafc;color:#94a3b8">
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Prénom / Pseudo affiché</label>
        <input type="text" name="display_name" class="pf-input" value="<?= htmlspecialchars($user['display_name']) ?>" required>
      </div>
      <button type="submit" class="pf-btn">Enregistrer</button>
    </form>
  </section>

  <!-- ── Mot de passe ────────────────────────────────────────────────────── -->
  <section style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px">🔒 Changer le mot de passe</h2>
    <form method="post">
      <input type="hidden" name="action" value="change_password">
      <div class="pf-form-group">
        <label class="pf-label">Mot de passe actuel</label>
        <input type="password" name="current_password" class="pf-input" required placeholder="••••••">
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Nouveau mot de passe</label>
        <input type="password" name="new_password" class="pf-input" required placeholder="••••••" autocomplete="new-password">
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Confirmer</label>
        <input type="password" name="confirm_password" class="pf-input" required placeholder="••••••" autocomplete="new-password">
      </div>
      <button type="submit" class="pf-btn">Modifier</button>
    </form>
  </section>

  <?php if ($family): ?>
  <!-- ── Espace familial ─────────────────────────────────────────────────── -->
  <section style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px">🏠 Mon espace familial</h2>

    <form method="post" style="margin-bottom:20px">
      <input type="hidden" name="action" value="update_family_name">
      <div class="pf-form-group">
        <label class="pf-label">Nom de l'espace</label>
        <div style="display:flex;gap:8px">
          <input type="text" name="family_name" class="pf-input" value="<?= htmlspecialchars($family['name']) ?>" required>
          <button type="submit" class="pf-btn" style="flex-shrink:0">Enregistrer</button>
        </div>
      </div>
    </form>

    <!-- Code d'invitation -->
    <div style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:10px;padding:20px">
      <p style="font-size:0.85rem;color:#64748b;margin-bottom:8px">Code d'invitation — partagez-le pour inviter quelqu'un</p>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <code id="invite-code" style="font-size:1.3rem;font-weight:700;letter-spacing:3px;color:#1e40af;cursor:pointer"
          onclick="copyCode()" title="Cliquer pour copier">
          <?= htmlspecialchars($family['invite_code']) ?>
        </code>
        <span id="copy-msg" style="color:#16a34a;font-size:0.85rem;opacity:0;transition:opacity .3s">✓ Copié !</span>
        <form method="post" style="margin-left:auto">
          <input type="hidden" name="action" value="regen_invite">
          <button type="submit" class="pf-btn btn-secondary" style="font-size:0.85rem"
            onclick="return confirm('Regénérer le code ? L\'ancien sera invalidé.')">
            🔄 Nouveau code
          </button>
        </form>
      </div>
      <p style="margin-top:12px;font-size:0.8rem;color:#94a3b8">
        Lien d'inscription : <strong><?= $_SERVER['HTTP_HOST'] ?>/register.php</strong>
      </p>
    </div>

    <!-- Membres -->
    <h3 style="font-size:0.95rem;font-weight:600;margin:20px 0 12px">Membres (<?= count($members) ?>)</h3>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($members as $m): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8fafc;border-radius:8px">
        <div>
          <strong><?= htmlspecialchars($m['display_name']) ?></strong>
          <span style="color:#94a3b8;font-size:0.85rem;margin-left:6px">@<?= htmlspecialchars($m['username']) ?></span>
          <?php if ($m['is_admin']): ?>
            <span style="background:#dbeafe;color:#1d4ed8;padding:1px 7px;border-radius:8px;font-size:0.75rem;font-weight:600;margin-left:6px">Admin</span>
          <?php endif; ?>
        </div>
        <span style="font-size:0.8rem;color:#94a3b8"><?= date('d/m/Y', strtotime($m['created_at'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($_SESSION['user']['is_admin']): ?>
  <div style="text-align:center;margin-top:8px">
    <a href="/admin/" style="color:#2563eb;font-size:0.9rem">→ Panneau d'administration</a>
  </div>
  <?php endif; ?>

</div>

<script>
function copyCode() {
  const code = document.getElementById('invite-code').textContent.trim();
  navigator.clipboard.writeText(code).then(() => {
    const msg = document.getElementById('copy-msg');
    msg.style.opacity = '1';
    setTimeout(() => msg.style.opacity = '0', 2000);
  });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
