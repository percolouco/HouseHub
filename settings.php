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
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => tr('error_csrf')]);
            exit;
        }
        $error = tr('error_csrf');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'set_modules' && $family_id) {
            $all = ['calendar', 'budget', 'holidays', 'gifts', 'garage', 'memo', 'todo', 'liste', 'calendar_ios', 'printvault', 'planka'];
            $enabled = array_values(array_filter($all, fn($m) => isset($_POST['mod_' . $m])));
            if (empty($enabled)) {
                $error = tr('set_err_min_module');
            } else {
                $meta_pdo->prepare("UPDATE families SET enabled_modules = ? WHERE id = ?")
                         ->execute([json_encode($enabled), $family_id]);
                $_SESSION['enabled_modules'] = $enabled;
                $success = tr('set_success_modules');
            }
        }

        if ($action === 'set_lang') {
            $lang = $_POST['lang'] ?? 'fr';
            if (in_array($lang, ['fr', 'ca', 'en'])) {
                $_SESSION['app_lang'] = $lang;
                $meta_pdo->prepare("UPDATE users SET lang = ? WHERE id = ?")
                         ->execute([$lang, $user_id]);
                $success = tr('set_success_lang');
            }
        }

        if ($action === 'update_profile') {
            $display_name = trim($_POST['display_name'] ?? '');
            $color        = trim($_POST['color'] ?? '#0891b2');

            if (!$display_name) {
                $error = tr('set_err_empty_name');
            } else {
                $meta_pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?")
                         ->execute([$display_name, $user_id]);
                
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    $stmtPeopleColor = $pdo->prepare("UPDATE pf_people SET color = ? WHERE user_id = ?");
                    $stmtPeopleColor->execute([$color, $user_id]);
                }

                $_SESSION['user']['display_name'] = $display_name;
                $success = tr('set_success_profile');
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
                $error = tr('set_err_wrong_pwd');
            } elseif (strlen($new) < 6) {
                $error = tr('set_err_pwd_length');
            } elseif ($new !== $confirm) {
                $error = tr('set_err_pwd_match');
            } else {
                $meta_pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                         ->execute([password_hash($new, PASSWORD_BCRYPT), $user_id]);
                $success = tr('set_success_pwd');
            }
        }

        if ($action === 'update_family_name' && $family_id) {
            $name = trim($_POST['family_name'] ?? '');
            if ($name) {
                $meta_pdo->prepare("UPDATE families SET name = ? WHERE id = ?")
                         ->execute([$name, $family_id]);
                $success = tr('set_success_family');
            }
        }

        if ($action === 'regen_invite' && $family_id) {
            $new_code = bin2hex(random_bytes(8));
            $meta_pdo->prepare("UPDATE families SET invite_code = ? WHERE id = ?")
                     ->execute([$new_code, $family_id]);
            $success = tr('set_success_invite');
        }

        if ($action === 'upload_home_bg' && $family_id) {
            $upload_dir = '/uploads/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
            $file = $_FILES['home_bg'] ?? null;
            if ($file && $file['error'] === 0) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $error = tr('set_err_bg_format');
                } else {
                    $dest = $upload_dir . 'home_bg_' . $family_id . '.' . $ext;
                    foreach (glob($upload_dir . 'home_bg_' . $family_id . '.*') as $old) @unlink($old);
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $success = tr('set_success_bg');
                    } else {
                        $error = tr('set_err_upload');
                    }
                }
            }
        }

        if ($action === 'reset_home_bg' && $family_id) {
            foreach (glob('/uploads/home_bg_' . $family_id . '.*') as $old) @unlink($old);
            $success = tr('set_success_bg_reset');
        }

        if ($action === 'grocery_history_max' && $family_id) {
            require_once __DIR__ . '/includes/db.php';
            if (!isset($pdo)) {
                $error = tr('set_err_db_unavailable');
            } else {
                $n = (int) ($_POST['history_max'] ?? 20);
                $n = max(1, min(50, $n));
                try {
                    $pdo->prepare(
                        "INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('grocery_settings','history_max',?) ON DUPLICATE KEY UPDATE content=VALUES(content)"
                    )->execute([(string) $n]);
                    $success = tr('groceries_settings_saved') ?: 'Paramètres des courses enregistrés.';
                } catch (\Throwable $e) {
                    $error = (tr('groceries_settings_error') ?: 'Erreur') . ' : ' . $e->getMessage();
                }
            }
        }
        
        if ($action === 'calendar_ios_save') {
            $username = trim($_POST['icloud_username'] ?? '');
            $appPassword = hh_normalize_apple_app_password((string) ($_POST['icloud_app_password'] ?? ''));
            $calendarUrl = trim($_POST['icloud_calendar_url'] ?? '');

            if (!$username || !$appPassword || !$calendarUrl) {
                $error = tr('set_err_ios_missing');
            } else {
                try {
                    $resolvedUrl = hh_icloud_resolve_calendar_url_if_needed($username, $appPassword, $calendarUrl);
                    $encrypted = hh_encrypt_secret($appPassword);
                    $meta_pdo->prepare("
                        INSERT INTO user_calendar_integrations (user_id, provider, username, secret_encrypted, calendar_url, status, updated_at)
                        VALUES (?, 'icloud_caldav', ?, ?, ?, 'connected', NOW())
                        ON DUPLICATE KEY UPDATE username=VALUES(username), secret_encrypted=VALUES(secret_encrypted), calendar_url=VALUES(calendar_url), status='connected', updated_at=NOW()
                    ")->execute([$user_id, $username, $encrypted, $resolvedUrl]);
                    $msg = tr('set_success_ios');
                    if (rtrim($resolvedUrl, '/') !== rtrim($calendarUrl, '/')) {
                        $msg .= tr('set_success_ios_auto');
                    }
                    $success = $msg;
                } catch (\Throwable $e) {
                    $error = tr('set_err_ios_save') . $e->getMessage();
                }
            }
        }

        if ($action === 'calendar_ios_test') {
            $row = $meta_pdo->prepare("SELECT id, username, secret_encrypted, calendar_url FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'");
            $row->execute([$user_id]);
            $integration = $row->fetch();
            if (!$integration) {
                $error = tr('set_err_ios_none');
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
                        $success = sprintf(tr('set_success_ios_test'), $code);
                    } else {
                        $error = sprintf(tr('set_err_ios_test'), $code);
                    }
                } catch (\Throwable $e) {
                    $error = tr('set_err_ios_test_fail') . $e->getMessage();
                }
            }
        }

        if ($action === 'calendar_ios_disconnect') {
            $meta_pdo->prepare("DELETE FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'")->execute([$user_id]);
            $success = tr('set_success_ios_del');
        }

        // --- GESTION DES PROFILS RATTACHÉS (Enfants, Nounous, etc.) ---
        if ($action === 'add_attached_profile') {
            $name = trim($_POST['profile_name'] ?? '');
            $role = trim($_POST['profile_role'] ?? 'child');
            $color = trim($_POST['profile_color'] ?? '#3b82f6');
            
            if ($name) {
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    $stmt = $pdo->prepare("INSERT INTO pf_people (name, role, color) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $role, $color]);
                    $success = "Profil de {$name} ajouté avec succès.";
                }
            } else {
                $error = "Le prénom est obligatoire.";
            }
        }

        if ($action === 'update_attached_profile_color') {
            $profile_id = (int)($_POST['profile_id'] ?? 0);
            $color = trim($_POST['color'] ?? '#3b82f6');
            if ($profile_id > 0) {
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    $stmt = $pdo->prepare("UPDATE pf_people SET color = ? WHERE id = ? AND user_id IS NULL");
                    $stmt->execute([$color, $profile_id]);
                    $success = "Couleur mise à jour.";
                }
            }
        }

        if ($action === 'delete_attached_profile') {
            $profile_id = (int)($_POST['profile_id'] ?? 0);
            if ($profile_id > 0) {
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    $stmt = $pdo->prepare("DELETE FROM pf_people WHERE id = ? AND user_id IS NULL");
                    $stmt->execute([$profile_id]);
                    $success = tr('set_success_profile_del');
                }
            }
        }
        if ($action === 'update_profile_color') {
            $profile_id = (int)($_POST['profile_id'] ?? 0);
            $color = trim($_POST['color'] ?? '#0891b2');            
            if ($profile_id > 0) {
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    $stmt = $pdo->prepare("UPDATE pf_people SET color = ? WHERE id = ? AND user_id IS NULL");
                     $stmt->execute([$color, $profile_id]);
                    $success = tr('set_success_color_profile');
                }
            }
        }
        if ($action === 'update_attached_profile_color') {
            $profile_id = (int)($_POST['profile_id'] ?? 0);
            $color = trim($_POST['color'] ?? '');
            
            // Vérification stricte format Hexadécimal
            if ($profile_id > 0 && preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                require_once __DIR__ . '/includes/db.php';
                if (isset($pdo)) {
                    // user_id IS NULL garantit qu'on ne modifie pas un vrai compte Parent par accident
                    $stmt = $pdo->prepare("UPDATE pf_people SET color = ? WHERE id = ? AND user_id IS NULL");
                    $stmt->execute([$color, $profile_id]);
                    
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                    $success = tr('set_success_color_update');
                }
            }
        }
    }
}

// ─── Chargement données ───────────────────────────────────────────────────────

if ($family_id) {
    require_once __DIR__ . '/includes/db.php';
}

$stmtUser = $meta_pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

$user_color = '#0891b2'; 
if ($family_id && isset($pdo)) {
    $stmtColorFetch = $pdo->prepare("SELECT color FROM pf_people WHERE user_id = ?");
    $stmtColorFetch->execute([$user_id]);
    $fetched_color = $stmtColorFetch->fetchColumn();
    if ($fetched_color) {
        $user_color = $fetched_color;
    }
}

$family = null;
$members = [];
$attached_profiles = [];

if ($family_id) {
    $fam = $meta_pdo->prepare("SELECT * FROM families WHERE id = ?");
    $fam->execute([$family_id]);
    $family = $fam->fetch();

    $mem = $meta_pdo->prepare("SELECT display_name, username, created_at, is_admin FROM users WHERE family_id = ? ORDER BY id");
    $mem->execute([$family_id]);
    $members = $mem->fetchAll();

    if (isset($pdo)) {
        $stmtAttached = $pdo->query("SELECT id, name, role, color FROM pf_people WHERE user_id IS NULL ORDER BY role ASC, name ASC");
        $attached_profiles = $stmtAttached->fetchAll(PDO::FETCH_ASSOC);
    }
}

$calendarIntegration = $meta_pdo->prepare("SELECT username, calendar_url, status, last_sync_at FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'");
$calendarIntegration->execute([$user_id]);
$calendarIntegration = $calendarIntegration->fetch();

$groceryHistoryMaxSetting = 20;
if ($family_id && isset($pdo)) {
    try {
        $gv = $pdo->query("SELECT content FROM pf_notes WHERE note_type='grocery_settings' AND reference_id='history_max'")->fetchColumn();
        if ($gv !== false && $gv !== null && $gv !== '') {
            $groceryHistoryMaxSetting = max(1, min(50, (int) $gv));
        }
    } catch (\Throwable $e) {}
}

$pageTitle = tr('set_title_page');
$activePage = "settings";
require __DIR__ . '/header.php';
?>

<div class="pf-container pf-settings-page">

  <h1 class="pf-settings-title"><?= tr('set_title_page') ?></h1>

  <section class="pf-panel-card">
    <h2 class="pf-card-h2"><?= tr('set_title_lang') ?></h2>
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

  <?php if ($family_id): ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight"><?= tr('set_title_modules') ?></h2>
    <p class="pf-muted-note"><?= tr('set_desc_modules') ?></p>
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
      <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
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
    <form method="post" class="pf-stack-md pf-mt-md">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="grocery_history_max">
      <div class="pf-form-group">
        <label class="pf-label" for="history_max"><?= htmlspecialchars(tr('liste_settings_history_max')) ?></label>
        <input type="number" name="history_max" id="history_max" class="pf-input pf-input-sm" min="1" max="50" step="1" value="<?= (int) $groceryHistoryMaxSetting ?>" required>
        <p class="pf-muted-note pf-mt-xs"><?= htmlspecialchars(tr('liste_settings_history_hint')) ?></p>
      </div>
      <button type="submit" class="pf-btn"><?= htmlspecialchars(tr('btn_save')) ?></button>
    </form>
  </section>
  <?php endif; ?>

  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight"><?= tr('set_title_ios') ?></h2>
    <p class="pf-muted-note"><?= tr('set_desc_ios') ?></p>
    <form method="post" class="pf-stack-md">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="calendar_ios_save">
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_apple_id') ?></label>
        <input type="text" name="icloud_username" class="pf-input" value="<?= htmlspecialchars($calendarIntegration['username'] ?? '') ?>" placeholder="<?= tr('placeholder_apple_id') ?>" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_apple_pwd') ?></label>
        <input type="password" name="icloud_app_password" class="pf-input" placeholder="<?= tr('placeholder_apple_pwd') ?>" required>
      </div>
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_apple_url') ?></label>
        <input type="url" name="icloud_calendar_url" class="pf-input" value="<?= htmlspecialchars($calendarIntegration['calendar_url'] ?? '') ?>" placeholder="https://caldav.icloud.com/..." required>
      </div>
      <div class="pf-flex-gap-8">
        <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
      </div>
    </form>

    <div class="pf-flex-gap-8 pf-mt-sm">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="calendar_ios_test">
        <button type="submit" class="pf-btn btn-secondary"><?= tr('set_btn_test_ios') ?></button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="calendar_ios_disconnect">
        <button type="submit" class="pf-btn btn-secondary"><?= tr('set_btn_disconnect_ios') ?></button>
      </form>
    </div>
  </section>

  <?php if ($family_id):
    $bg_file = null;
    foreach (glob('/uploads/home_bg_' . $family_id . '.*') as $f) { $bg_file = $f; break; }
    $has_custom_bg = $bg_file !== null;
  ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2 pf-card-h2--tight"><?= tr('set_title_bg') ?></h2>
    <p class="pf-muted-note"><?= tr('set_desc_bg') ?></p>

    <?php if ($has_custom_bg): ?>
    <div class="pf-home-bg-preview">
      <img src="/home-bg.php" alt="Fond actuel">
      <span class="pf-badge-overlay"><?= tr('set_badge_custom_bg') ?></span>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="pf-inline-form-end">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload_home_bg">
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_new_bg') ?></label>
        <input type="file" name="home_bg" class="pf-input" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <button type="submit" class="pf-btn pf-shrink-0"><?= tr('btn_save') ?></button>
    </form>

    <?php if ($has_custom_bg): ?>
    <form method="post" class="pf-mt-sm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="reset_home_bg">
      <button type="submit" class="pf-btn btn-secondary pf-btn-sm-text"
        onclick="return confirm('<?= addslashes(tr('set_confirm_reset_bg')) ?>')"><?= tr('set_btn_reset_bg') ?></button>
    </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="pf-panel-card">
    <h2 class="pf-card-h2"><?= tr('set_title_profile') ?></h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_profile">
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_username') ?></label>
        <input type="text" class="pf-input pf-input--disabled-muted" value="<?= htmlspecialchars($user['username']) ?>" disabled>
      </div>
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_display_name') ?></label>
        <input type="text" name="display_name" class="pf-input" value="<?= htmlspecialchars($user['display_name']) ?>" required>
      </div>
      
      <div class="pf-form-group">
        <label class="pf-label" for="profile_color">Couleur thématique de mon profil</label>
        
        <!-- Le champ caché qui va stocker la valeur -->
        <input type="hidden" name="color" id="profile_color" value="<?= htmlspecialchars($user_color) ?>">
        
        <!-- Le panel de couleurs prédéfinies -->
        <div class="pf-color-palette" id="color-palette">
            <?php
            $palette = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#64748b'];
            foreach ($palette as $c): 
                $isSelected = (strtolower($user_color) === $c) ? 'selected' : '';
            ?>
                <button type="button" 
                        class="pf-color-swatch <?= $isSelected ?>" 
                        style="background-color: <?= $c ?>;" 
                        data-color="<?= $c ?>"
                        onclick="selectProfileColor(this, '<?= $c ?>')">
                </button>
            <?php endforeach; ?>
        </div>

        <span class="pf-muted-note pf-profile-color-note"><?= tr('set_desc_profile_color') ?></span>
      </div>
      
      <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
    </form>
  </section>

  <section class="pf-panel-card">
    <h2 class="pf-card-h2"><?= tr('set_title_pwd') ?></h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="change_password">
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_cur_pwd') ?></label>
        <input type="password" name="current_password" class="pf-input" required placeholder="••••••">
      </div>
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_new_pwd') ?></label>
        <input type="password" name="new_password" class="pf-input" required placeholder="••••••" autocomplete="new-password">
      </div>
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_confirm_pwd') ?></label>
        <input type="password" name="confirm_password" class="pf-input" required placeholder="••••••" autocomplete="new-password">
      </div>
      <button type="submit" class="pf-btn"><?= tr('set_btn_edit') ?></button>
    </form>
  </section>

  <?php if ($family): ?>
  <section class="pf-panel-card">
    <h2 class="pf-card-h2"><?= tr('set_title_family') ?></h2>

    <form method="post" class="pf-mb-section">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_family_name">
      <div class="pf-form-group">
        <label class="pf-label"><?= tr('set_label_family_name') ?></label>
        <div class="pf-flex-gap-8">
          <input type="text" name="family_name" class="pf-input" value="<?= htmlspecialchars($family['name']) ?>" required>
          <button type="submit" class="pf-btn pf-shrink-0"><?= tr('btn_save') ?></button>
        </div>
      </div>
    </form>

    <div class="pf-dashed-panel">
      <p class="pf-muted-note pf-muted-note--tight"><?= tr('set_desc_invite') ?></p>
      <div class="pf-invite-row">
        <code id="invite-code" class="pf-invite-code"
          onclick="copyCode()" title="<?= tr('set_title_copy') ?>">
          <?= htmlspecialchars($family['invite_code']) ?>
        </code>
        <span id="copy-msg" class="pf-copy-msg"><?= tr('set_msg_copied') ?></span>
        <form method="post" class="pf-ml-auto">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="regen_invite">
          <button type="submit" class="pf-btn btn-secondary pf-btn-sm-text"
            onclick="return confirm('<?= addslashes(tr('set_confirm_new_code')) ?>')">
            <?= tr('set_btn_new_code') ?>
          </button>
        </form>
      </div>
      <p class="pf-invite-footnote">
        <?= tr('set_desc_register_link') ?> <strong><?= $_SERVER['HTTP_HOST'] ?>/register.php</strong>
      </p>
    </div>

    <h3 class="pf-members-heading"><?= tr('set_title_members') ?> (<?= count($members) ?>)</h3>
    <div class="pf-stack-sm">
      <?php foreach ($members as $m): ?>
      <div class="pf-member-card">
        <div>
          <strong><?= htmlspecialchars($m['display_name']) ?></strong>
          <span class="pf-muted-inline">@<?= htmlspecialchars($m['username']) ?></span>
          <?php if ($m['is_admin']): ?>
            <span class="pf-admin-badge"><?= tr('set_badge_admin') ?></span>
          <?php endif; ?>
        </div>
        <span class="pf-muted-tiny"><?= date('d/m/Y', strtotime($m['created_at'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <h3 class="pf-members-heading pf-mt-lg">🧸 <?= tr('settings_attached_profiles_title') ?></h3>
    <p class="pf-muted-note pf-muted-note--tight pf-mb-md"><?= tr('settings_attached_profiles_desc') ?></p>
    
  <div class="pf-stack-sm" style="margin-bottom: 1.5rem;">
      <?php foreach ($attached_profiles as $p): 
         $roleBadge = tr('role_unknown');
         if (in_array($p['role'], ['child', 'enfant'])) $roleBadge = tr('role_child_icon');
         elseif ($p['role'] === 'helper') $roleBadge = tr('role_helper_icon');
         elseif ($p['role'] === 'relative') $roleBadge = tr('role_relative_icon');
         
         $pColor = htmlspecialchars($p['color'] ?? '#3b82f6');
      ?>
      <div class="pf-member-card" style="display: flex; justify-content: space-between; align-items: center; border-left: 4px solid <?= $pColor ?>;">
        <div>
          <strong><?= htmlspecialchars($p['name']) ?></strong>
          <span class="pf-muted-inline"><?= $roleBadge ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            
            <div class="pf-color-dropdown">
                <button type="button" class="pf-color-trigger" style="background-color: <?= $pColor ?>;" title="<?= tr('change_color') ?>" onclick="toggleColorMenu(this)"></button>
                <div class="pf-color-menu">
                    <?php foreach(['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#64748b'] as $c): ?>
                        <button type="button" class="pf-color-swatch" style="background-color: <?= $c ?>;" onclick="updateAttachedProfileColor(<?= $p['id'] ?>, '<?= $c ?>', this)"></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_attached_profile">
              <input type="hidden" name="profile_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-icon-action delete" title="<?= tr('btn_delete') ?>" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 4px;" onclick="return confirm('<?= addslashes(tr('confirm_delete_profile')) ?>')">🗑️</button>
            </form>
        </div>
      </div>
      <?php endforeach; ?>
      
      <?php if (empty($attached_profiles)): ?>
        <div class="pf-muted-note" style="padding: 10px; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px;"><?= tr('settings_no_attached_profiles') ?></div>
      <?php endif; ?>
    </div>

    <form method="post" style="display: flex; gap: 8px; background: var(--bg-soft); padding: 12px; border-radius: 8px; border: 1px dashed var(--border-light); align-items: flex-end; flex-wrap: wrap;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_attached_profile">
      
      <div class="pf-form-group" style="margin-bottom: 0; flex: 2; min-width: 120px;">
        <label class="pf-label" style="font-size: 0.8rem; margin-bottom: 4px;"><?= tr('label_firstname') ?></label>
        <input type="text" name="profile_name" class="pf-input" placeholder="<?= tr('placeholder_firstname_ex') ?>" required>
      </div>
      
      <div class="pf-form-group" style="margin-bottom: 0; flex: 1.5; min-width: 120px;">
        <label class="pf-label" style="font-size: 0.8rem; margin-bottom: 4px;"><?= tr('label_role') ?></label>
        <select name="profile_role" class="pf-input" required>
            <option value="child"><?= tr('role_child_icon') ?></option>
            <option value="helper"><?= tr('role_helper_icon') ?></option>
            <option value="relative"><?= tr('role_relative_icon') ?></option>
        </select>
      </div>

      <div class="pf-form-group" style="margin-bottom: 0; display: flex; flex-direction: column; align-items: flex-start;">
        <label class="pf-label" style="font-size: 0.8rem; margin-bottom: 4px;"><?= tr('label_color') ?></label>
        <div style="display: flex; gap: 4px; flex-wrap: wrap; padding-bottom: 4px;">
          <?php foreach(['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'] as $i => $c): ?>
          <label class="pf-radio-swatch" style="background-color: <?= $c ?>;" title="<?= $c ?>">
              <input type="radio" name="profile_color" value="<?= $c ?>" <?= $i === 0 ? 'checked' : '' ?> style="display:none;">
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <button type="submit" class="pf-btn pf-shrink-0" style="padding: 8px 16px; margin-bottom: 2px;"><?= tr('btn_add') ?></button>
    </form>

  </section>
  <?php endif; ?>

  <?php if (!empty($_SESSION['user']['is_admin'])): ?>
  <div class="pf-settings-admin-link">
    <a href="/admin/"><?= tr('set_link_admin') ?></a>
  </div>
  <?php endif; ?>

</div>

<script>
// --- INJECTION CSRF GLOBALE ---
document.querySelectorAll('form[method="post"]').forEach((form) => {
  if (!form.querySelector('input[name="csrf_token"]')) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = window.CSRF_TOKEN || '';
    form.appendChild(input);
  }
});

// --- COPIE DU CODE D'INVITATION ---
function copyCode() {
  const code = document.getElementById('invite-code').textContent.trim();
  navigator.clipboard.writeText(code).then(() => {
    const msg = document.getElementById('copy-msg');
    msg.classList.add('is-visible');
    setTimeout(() => msg.classList.remove('is-visible'), 2000);
  });
}

// --- GESTION DES NOTIFICATIONS TOAST ---
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($success): ?>
        if (typeof showToast === 'function') {
            showToast(<?= json_encode($success) ?>, 'success');
        } else {
            alert(<?= json_encode($success) ?>);
        }
    <?php endif; ?>
    
    <?php if ($error): ?>
        if (typeof showToast === 'function') {
            showToast(<?= json_encode($error) ?>, 'error');
        } else {
            alert(<?= json_encode($error) ?>);
        }
    <?php endif; ?>
});



function selectProfileColor(btn, hexColor) {
    // 1. Mettre à jour l'input caché
    document.getElementById('profile_color').value = hexColor;
    
    // 2. Mettre à jour l'UI (enlever la classe selected partout, l'ajouter sur le bouton cliqué)
    const swatches = document.querySelectorAll('#color-palette .pf-color-swatch');
    swatches.forEach(el => el.classList.remove('selected'));
    btn.classList.add('selected');
}

function toggleColorMenu(btn) {
    // Fermer tous les autres popovers
    document.querySelectorAll('.pf-color-menu').forEach(menu => {
        if (menu !== btn.nextElementSibling) menu.classList.remove('is-open');
    });
    // Ouvrir/Fermer celui cliqué
    btn.nextElementSibling.classList.toggle('is-open');
}

// Fermer le menu si on clique en dehors
document.addEventListener('click', (e) => {
    if (!e.target.closest('.pf-color-dropdown')) {
        document.querySelectorAll('.pf-color-menu').forEach(menu => menu.classList.remove('is-open'));
    }
});

// --- MISE À JOUR DYNAMIQUE COULEUR PROFIL RATTACHÉ ---
async function updateAttachedProfileColor(profileId, newColor, btnElement) {
    const fd = new FormData();
    fd.append('action', 'update_attached_profile_color');
    fd.append('profile_id', profileId);
    fd.append('color', newColor);
    fd.append('csrf_token', window.CSRF_TOKEN); // Sécurité absolue

    try {
        const res = await pachaFetch('/settings.php', { method: 'POST', body: fd });
        if (res && res.success) {
            if (typeof showToast === 'function') {
                showToast(window.I18N['color_updated'] || 'Couleur mise à jour !', 'success');
            }
            
            const dropdown = btnElement.closest('.pf-color-dropdown');
            if (dropdown) {
                // Màj visuelle de la pastille déclencheur
                dropdown.querySelector('.pf-color-trigger').style.backgroundColor = newColor;
                dropdown.querySelector('.pf-color-menu').classList.remove('is-open');
            }
            
            // Màj de la bordure gauche de la carte
            const card = btnElement.closest('.pf-member-card');
            if (card) {
                card.style.borderLeftColor = newColor;
            }
        } else {
            if (typeof showToast === 'function') showToast(window.I18N['error_occured'] || 'Erreur', 'error');
        }
    } catch (err) {
        console.error(err);
        if (typeof showToast === 'function') showToast(window.I18N['error_occured'] || 'Erreur', 'error');
    }
}
</script>

<?php require __DIR__ . '/footer.php'; ?>