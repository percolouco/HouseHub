<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/meta_db.php';
require_once __DIR__ . '/includes/crypto.php';
require_once __DIR__ . '/includes/icloud_caldav.php';
require_once __DIR__ . '/includes/i18n.php';

$user_id   = $_SESSION['user']['id'];
$family_id = $_SESSION['user']['family_id'];

$error   = null;
$success = null;

$meta_pdo->exec("
CREATE TABLE IF NOT EXISTS user_calendar_integrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'icloud_caldav',
  username VARCHAR(255) NOT NULL,
  secret_encrypted TEXT NOT NULL,
  dav_principal_url VARCHAR(1024) DEFAULT NULL,
  calendar_url VARCHAR(1024) DEFAULT NULL,
  status VARCHAR(30) DEFAULT 'connected',
  last_sync_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_provider (user_id, provider)
)");

// ─── Actions ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = "Session invalide (CSRF). Rechargez la page.";
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_modules' && $family_id) {
        $all = ['calendar', 'budget', 'holidays', 'gifts', 'garage', 'memo', 'todo', 'liste', 'calendar_ios', 'printvault', 'planka'];
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

    if ($action === 'grocery_history_max' && $family_id) {
        require_once __DIR__ . '/includes/db.php';
        if (!isset($pdo)) {
            $error = "Base famille indisponible.";
        } else {
            $n = (int) ($_POST['history_max'] ?? 20);
            $n = max(1, min(50, $n));
            try {
                $pdo->prepare(
                    "INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('grocery_settings','history_max',?) ON DUPLICATE KEY UPDATE content=VALUES(content)"
                )->execute([(string) $n]);
                $success = tr('groceries_settings_saved');
            } catch (\Throwable $e) {
                $error = tr('groceries_settings_error') . ' ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'calendar_ios_save') {
        $username = trim($_POST['icloud_username'] ?? '');
        $appPassword = hh_normalize_apple_app_password((string) ($_POST['icloud_app_password'] ?? ''));
        $calendarUrl = trim($_POST['icloud_calendar_url'] ?? '');

        if (!$username || !$appPassword || !$calendarUrl) {
            $error = "Merci de renseigner identifiant iCloud, mot de passe d'app et URL CalDAV.";
        } else {
            try {
                $resolvedUrl = hh_icloud_resolve_calendar_url_if_needed($username, $appPassword, $calendarUrl);
                $encrypted = hh_encrypt_secret($appPassword);
                $meta_pdo->prepare("
                    INSERT INTO user_calendar_integrations (user_id, provider, username, secret_encrypted, calendar_url, status, updated_at)
                    VALUES (?, 'icloud_caldav', ?, ?, ?, 'connected', NOW())
                    ON DUPLICATE KEY UPDATE username=VALUES(username), secret_encrypted=VALUES(secret_encrypted), calendar_url=VALUES(calendar_url), status='connected', updated_at=NOW()
                ")->execute([$user_id, $username, $encrypted, $resolvedUrl]);
                $msg = "Connexion calendrier iOS enregistrée.";
                if (rtrim($resolvedUrl, '/') !== rtrim($calendarUrl, '/')) {
                    $msg .= " URL du calendrier détectée automatiquement (tu avais mis la racine iCloud).";
                }
                $success = $msg;
            } catch (\Throwable $e) {
                $error = "Impossible d'enregistrer la connexion iOS: " . $e->getMessage();
            }
        }
    }

    if ($action === 'calendar_ios_test') {
        $row = $meta_pdo->prepare("SELECT id, username, secret_encrypted, calendar_url FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'");
        $row->execute([$user_id]);
        $integration = $row->fetch();
        if (!$integration) {
            $error = "Aucune connexion iOS configurée.";
        } else {
            try {
                $pwd = hh_normalize_apple_app_password(hh_decrypt_secret($integration['secret_encrypted']));
                $calendarUrl = trim($integration['calendar_url']);
                $resolvedUrl = hh_icloud_resolve_calendar_url_if_needed($integration['username'], $pwd, $calendarUrl);
                $code = hh_caldav_test_calendar_collection($integration['username'], $pwd, $resolvedUrl);
                if (in_array($code, [200, 207], true)) {
                    if (rtrim($resolvedUrl, '/') !== rtrim($calendarUrl, '/')) {
                        $meta_pdo->prepare("UPDATE user_calendar_integrations SET calendar_url = ? WHERE id = ?")
                            ->execute([$resolvedUrl, $integration['id']]);
                    }
                    $success = "Connexion iCloud CalDAV valide (HTTP $code).";
                } else {
                    $error = "Test connexion échoué (HTTP $code). Vérifie identifiant Apple, mot de passe d’app (16 caractères) et que le compte iCloud a le Calendrier activé.";
                }
            } catch (\Throwable $e) {
                $error = "Test connexion impossible: " . $e->getMessage();
            }
        }
    }

    if ($action === 'calendar_ios_disconnect') {
        $meta_pdo->prepare("DELETE FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'")->execute([$user_id]);
        $success = "Connexion iOS supprimée.";
    }
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

$calendarIntegration = $meta_pdo->prepare("SELECT username, calendar_url, status, last_sync_at FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'");
$calendarIntegration->execute([$user_id]);
$calendarIntegration = $calendarIntegration->fetch();

$groceryHistoryMaxSetting = 20;
if ($family_id) {
    require_once __DIR__ . '/includes/db.php';
    if (isset($pdo)) {
        try {
            $gv = $pdo->query("SELECT content FROM pf_notes WHERE note_type='grocery_settings' AND reference_id='history_max'")->fetchColumn();
            if ($gv !== false && $gv !== null && $gv !== '') {
                $groceryHistoryMaxSetting = max(1, min(50, (int) $gv));
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
      $enabledMods = $_SESSION['enabled_modules'] ?? ['calendar','budget','holidays','gifts','calendar_ios'];
      $allModules = [
          'calendar' => ['icon' => '📅', 'label' => tr('menu_calendar')],
          'budget'   => ['icon' => '💰', 'label' => tr('menu_budget')],
          'holidays' => ['icon' => '🏖️', 'label' => tr('menu_holidays')],
          'gifts'    => ['icon' => '🎁', 'label' => tr('menu_gifts')],
          'garage'   => ['icon' => '🚗', 'label' => tr('menu_garage')],
          'memo'     => ['icon' => '📝', 'label' => tr('menu_memo')],
          'todo'     => ['icon' => '✅', 'label' => tr('menu_todo')],
          'liste'     => ['icon' => '📝', 'label' => tr('menu_liste')],
          'calendar_ios' => ['icon' => '📱', 'label' => tr('menu_calendar_ios')],
          'printvault'  => ['icon' => '🖨️', 'label' => tr('menu_printvault')],
          'planka'      => ['icon' => '📋', 'label' => tr('menu_planka')],
      ];
    ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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

  <?php
    $enabledModsForListe = $_SESSION['enabled_modules'] ?? [];
    if ($family_id && in_array('liste', $enabledModsForListe, true)):
  ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight">📝 <?= htmlspecialchars(tr('liste_settings_title')) ?></h2>
    <p class="pf-muted-note"><?= htmlspecialchars(tr('liste_settings_intro')) ?></p>
    <form method="post" class="pf-stack-md" style="margin-top:1rem">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="grocery_history_max">
      <div class="pf-form-group">
        <label class="pf-label" for="history_max"><?= htmlspecialchars(tr('liste_settings_history_max')) ?></label>
        <input type="number" name="history_max" id="history_max" class="pf-input" style="max-width:120px" min="1" max="50" step="1" value="<?= (int) $groceryHistoryMaxSetting ?>" required>
        <p class="pf-muted-note" style="margin-top:0.35rem"><?= htmlspecialchars(tr('liste_settings_history_hint')) ?></p>
      </div>
      <button type="submit" class="pf-btn"><?= htmlspecialchars(tr('btn_save')) ?></button>
    </form>
    <p class="pf-muted-note" style="margin-top:1rem">
      <a href="/liste.php" style="color:var(--primary);font-weight:600;"><?= htmlspecialchars(tr('mod_liste_name')) ?></a>
    </p>
  </section>
  <?php endif; ?>

  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight">📱 Intégration Calendrier iOS (CalDAV)</h2>
    <p class="pf-muted-note">Configurez ici votre calendrier iCloud pour synchroniser les événements créés dans HouseHub.</p>
    <p class="pf-muted-note" style="margin-top:8px;">
      Pour voir et gérer les événements : ouvrez le module
      <a href="/calendar-ios.php" style="color:var(--primary);font-weight:600;">Calendrier iOS</a>
      (menu du haut ou burger « Calendrier iOS », ou carte sur l’accueil). Le module doit être coché dans « Modules actifs » ci-dessus.
    </p>
    <form method="post" class="pf-stack-md">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="calendar_ios_save">
      <div class="pf-form-group">
        <label class="pf-label">Identifiant Apple (email iCloud)</label>
        <input type="text" name="icloud_username" class="pf-input" value="<?= htmlspecialchars($calendarIntegration['username'] ?? '') ?>" placeholder="nom@icloud.com" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label">Mot de passe d'app Apple</label>
        <input type="password" name="icloud_app_password" class="pf-input" placeholder="xxxx-xxxx-xxxx-xxxx" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label">URL du calendrier CalDAV</label>
        <input type="url" name="icloud_calendar_url" class="pf-input" value="<?= htmlspecialchars($calendarIntegration['calendar_url'] ?? '') ?>" placeholder="https://caldav.icloud.com/..." required>
      </div>
      <div class="pf-flex-gap-8">
        <button type="submit" class="pf-btn">Enregistrer</button>
      </div>
    </form>

    <div class="pf-flex-gap-8 pf-mt-sm">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="calendar_ios_test">
        <button type="submit" class="pf-btn btn-secondary">Tester la connexion</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="calendar_ios_disconnect">
        <button type="submit" class="pf-btn btn-secondary">Déconnecter</button>
      </form>
    </div>
    <p class="pf-muted-note" style="margin-top:10px;">
      Après enregistrement, utilisez « Tester la connexion » : un message vert confirme que l’URL CalDAV répond avec tes identifiants.
      Sur la page Calendrier iOS, le bouton « Synchroniser » envoie les événements vers iCloud et récupère ceux créés sur l’iPhone.
    </p>
    <?php if (!empty($calendarIntegration['last_sync_at'])): ?>
    <p class="pf-muted-note">Dernière synchro: <?= htmlspecialchars($calendarIntegration['last_sync_at']) ?></p>
    <?php endif; ?>
  </section>

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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload_home_bg">
      <div class="pf-form-group">
        <label class="pf-label">Nouvelle image (jpg, png, webp — max 10 Mo)</label>
        <input type="file" name="home_bg" class="pf-input" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <button type="submit" class="pf-btn pf-shrink-0">Enregistrer</button>
    </form>

    <?php if ($has_custom_bg): ?>
    <form method="post" class="pf-mt-sm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
document.querySelectorAll('form[method="post"]').forEach((form) => {
  if (!form.querySelector('input[name="csrf_token"]')) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = window.CSRF_TOKEN || '';
    form.appendChild(input);
  }
});

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
