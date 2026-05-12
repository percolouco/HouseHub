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

    if ($action === 'set_modules' && $family_id) {
        $all = ['calendar', 'budget', 'holidays', 'gifts', 'garage', 'memo', 'todo'];
        $enabled = array_values(array_filter($all, fn($m) => isset($_POST['mod_' . $m])));
        if (empty($enabled)) {
            $error = "Vous devez garder au moins un module actif.";
        } else {
            $meta_pdo->prepare("UPDATE families SET enabled_modules = ? WHERE id = ?")
                     ->execute([json_encode($enabled), $family_id]);
            $_SESSION['enabled_modules'] = $enabled;
            $success = "Modules mis à jour.";
        }
    }

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

    if ($action === 'upload_home_bg' && $family_id) {
        $upload_dir = '/uploads/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
        $file = $_FILES['home_bg'] ?? null;
        if ($file && $file['error'] === 0) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $error = "Format non supporté (jpg, png, webp).";
            } else {
                $dest = $upload_dir . 'home_bg_' . $family_id . '.' . $ext;
                // Supprimer anciens fichiers de ce family
                foreach (glob($upload_dir . 'home_bg_' . $family_id . '.*') as $old) @unlink($old);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $success = "Image d'accueil mise à jour.";
                } else {
                    $error = "Erreur lors de l'upload.";
                }
            }
        }
    }

    if ($action === 'reset_home_bg' && $family_id) {
        foreach (glob('/uploads/home_bg_' . $family_id . '.*') as $old) @unlink($old);
        $success = "Image d'accueil réinitialisée.";
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

<div class="pf-container pf-settings-page">

  <h1 class="pf-settings-title">⚙️ Paramètres</h1>

  <?php if ($success): ?>
    <div class="pf-alert pf-alert--success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="pf-alert pf-alert--error">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Langue ─────────────────────────────────────────────────────────── -->
  <section class="pf-panel-card">
    <h2 class="pf-card-h2">🌐 Langue / Language</h2>
    <form method="post" class="pf-lang-form">
      <input type="hidden" name="action" value="set_lang">
      <?php
        $s = 'class="pf-flag-inline"';
        $fr_flag = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 12" width="20" height="13" '.$s.'><rect width="6" height="12" fill="#002395"/><rect x="6" width="6" height="12" fill="#fff"/><rect x="12" width="6" height="12" fill="#ED2939"/></svg>';
        $en_flag = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30" width="20" height="13" '.$s.'><rect width="60" height="30" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/><path d="M30,0 V30 M0,15 H60" stroke="#fff" stroke-width="10"/><path d="M30,0 V30 M0,15 H60" stroke="#C8102E" stroke-width="6"/></svg>';
        $senyera  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 12" width="20" height="13" '.$s.'><rect width="18" height="12" fill="#FCDD09"/><rect y="1.33" width="18" height="1.33" fill="#DA121A"/><rect y="4" width="18" height="1.33" fill="#DA121A"/><rect y="6.67" width="18" height="1.33" fill="#DA121A"/><rect y="9.33" width="18" height="1.33" fill="#DA121A"/></svg>';
        $langs = [
            'fr' => ['flag' => $fr_flag, 'label' => 'Français'],
            'en' => ['flag' => $en_flag, 'label' => 'English'],
            'ca' => ['flag' => $senyera,  'label' => 'Català'],
        ];
        $currentLang = $_SESSION['app_lang'] ?? 'fr';
        foreach ($langs as $code => $lang):
      ?>
      <button type="submit" name="lang" value="<?= $code ?>"
        class="pf-btn <?= $currentLang === $code ? 'pf-btn--current-lang' : 'btn-secondary' ?>">
        <?= $lang['flag'] ?> <?= $lang['label'] ?>
      </button>
      <?php endforeach; ?>
    </form>
  </section>

  <!-- ── Modules ────────────────────────────────────────────────────────── -->
  <?php if ($family_id): ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight">🧩 Modules actifs</h2>
    <p class="pf-muted-note">Choisissez les modules visibles dans la navigation (partagé avec tous les membres de l'espace).</p>
    <?php
      $enabledMods = $_SESSION['enabled_modules'] ?? ['calendar','budget','holidays','gifts'];
      $allModules = [
          'calendar' => ['icon' => '📅', 'label' => tr('menu_calendar')],
          'budget'   => ['icon' => '💰', 'label' => tr('menu_budget')],
          'holidays' => ['icon' => '🏖️', 'label' => tr('menu_holidays')],
          'gifts'    => ['icon' => '🎁', 'label' => tr('menu_gifts')],
          'garage'   => ['icon' => '🚗', 'label' => tr('menu_garage')],
          'memo'     => ['icon' => '📝', 'label' => tr('menu_memo')],
          'todo'     => ['icon' => '✅', 'label' => tr('menu_todo')],
      ];
    ?>
    <form method="post">
      <input type="hidden" name="action" value="set_modules">
      <div class="pf-stack-md">
        <?php foreach ($allModules as $key => $mod): $active = in_array($key, $enabledMods); ?>
        <label class="pf-module-tile">
          <input type="checkbox" name="mod_<?= $key ?>" <?= $active ? 'checked' : '' ?> class="pf-checkbox-lg">
          <span class="pf-tile-icon"><?= $mod['icon'] ?></span>
          <span class="pf-tile-label"><?= $mod['label'] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="pf-btn">Enregistrer</button>
    </form>
  </section>
  <?php endif; ?>

  <!-- ── Fond page d'accueil ───────────────────────────────────────────── -->
  <?php if ($family_id):
    $bg_file = null;
    foreach (glob('/uploads/home_bg_' . $family_id . '.*') as $f) { $bg_file = $f; break; }
    $has_custom_bg = $bg_file !== null;
  ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight">🖼️ Image d'accueil</h2>
    <p class="pf-muted-note">Photo de fond affichée sur la page d'accueil (partagée avec tous les membres).</p>

    <?php if ($has_custom_bg): ?>
    <div class="pf-home-bg-preview">
      <img src="/home-bg.php" alt="Fond actuel">
      <span class="pf-badge-overlay">Image personnalisée</span>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="pf-inline-form-end">
      <input type="hidden" name="action" value="upload_home_bg">
      <div class="pf-form-group">
        <label class="pf-label">Nouvelle image (jpg, png, webp — max 10 Mo)</label>
        <input type="file" name="home_bg" class="pf-input" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <button type="submit" class="pf-btn pf-shrink-0">Enregistrer</button>
    </form>

    <?php if ($has_custom_bg): ?>
    <form method="post" class="pf-mt-sm">
      <input type="hidden" name="action" value="reset_home_bg">
      <button type="submit" class="pf-btn btn-secondary pf-btn-sm-text"
        onclick="return confirm('Revenir à l\'image par défaut ?')">🔄 Remettre l'image par défaut</button>
    </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ── Profil ──────────────────────────────────────────────────────────── -->
  <section class="pf-panel-card">
    <h2 class="pf-card-h2">👤 Mon profil</h2>
    <form method="post">
      <input type="hidden" name="action" value="update_profile">
      <div class="pf-form-group">
        <label class="pf-label">Nom d'utilisateur</label>
        <input type="text" class="pf-input pf-input--disabled-muted" value="<?= htmlspecialchars($user['username']) ?>" disabled>
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Prénom / Pseudo affiché</label>
        <input type="text" name="display_name" class="pf-input" value="<?= htmlspecialchars($user['display_name']) ?>" required>
      </div>
      <button type="submit" class="pf-btn">Enregistrer</button>
    </form>
  </section>

  <!-- ── Mot de passe ────────────────────────────────────────────────────── -->
  <section class="pf-panel-card">
    <h2 class="pf-card-h2">🔒 Changer le mot de passe</h2>
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
  <section class="pf-panel-card">
    <h2 class="pf-card-h2">🏠 Mon espace familial</h2>

    <form method="post" class="pf-mb-section">
      <input type="hidden" name="action" value="update_family_name">
      <div class="pf-form-group">
        <label class="pf-label">Nom de l'espace</label>
        <div class="pf-flex-gap-8">
          <input type="text" name="family_name" class="pf-input" value="<?= htmlspecialchars($family['name']) ?>" required>
          <button type="submit" class="pf-btn pf-shrink-0">Enregistrer</button>
        </div>
      </div>
    </form>

    <!-- Code d'invitation -->
    <div class="pf-dashed-panel">
      <p class="pf-muted-note pf-muted-note--tight">Code d'invitation — partagez-le pour inviter quelqu'un</p>
      <div class="pf-invite-row">
        <code id="invite-code" class="pf-invite-code"
          onclick="copyCode()" title="Cliquer pour copier">
          <?= htmlspecialchars($family['invite_code']) ?>
        </code>
        <span id="copy-msg" class="pf-copy-msg">✓ Copié !</span>
        <form method="post" class="pf-ml-auto">
          <input type="hidden" name="action" value="regen_invite">
          <button type="submit" class="pf-btn btn-secondary pf-btn-sm-text"
            onclick="return confirm('Regénérer le code ? L\'ancien sera invalidé.')">
            🔄 Nouveau code
          </button>
        </form>
      </div>
      <p class="pf-invite-footnote">
        Lien d'inscription : <strong><?= $_SERVER['HTTP_HOST'] ?>/register.php</strong>
      </p>
    </div>

    <!-- Membres -->
    <h3 class="pf-members-heading">Membres (<?= count($members) ?>)</h3>
    <div class="pf-stack-sm">
      <?php foreach ($members as $m): ?>
      <div class="pf-member-card">
        <div>
          <strong><?= htmlspecialchars($m['display_name']) ?></strong>
          <span class="pf-muted-inline">@<?= htmlspecialchars($m['username']) ?></span>
          <?php if ($m['is_admin']): ?>
            <span class="pf-admin-badge">Admin</span>
          <?php endif; ?>
        </div>
        <span class="pf-muted-tiny"><?= date('d/m/Y', strtotime($m['created_at'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($_SESSION['user']['is_admin']): ?>
  <div class="pf-settings-admin-link">
    <a href="/admin/">→ Panneau d'administration</a>
  </div>
  <?php endif; ?>

</div>

<script>
function copyCode() {
  const code = document.getElementById('invite-code').textContent.trim();
  navigator.clipboard.writeText(code).then(() => {
    const msg = document.getElementById('copy-msg');
    msg.classList.add('is-visible');
    setTimeout(() => msg.classList.remove('is-visible'), 2000);
  });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
