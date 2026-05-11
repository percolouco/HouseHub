<?php
session_start();
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/meta_db.php';

$pageTitle = tr('login_title');
$activePage = "login";

if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = tr('error_missing_fields');
    } else {
        $stmt = $meta_pdo->prepare("
            SELECT u.id, u.username, u.password_hash, u.display_name, u.family_id, u.is_admin, u.is_active, u.lang,
                   f.db_name, f.is_active as family_active, f.enabled_modules
            FROM users u
            LEFT JOIN families f ON f.id = u.family_id
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = "Compte désactivé. Contactez l'administrateur.";
            } elseif ($user['family_id'] && !$user['family_active']) {
                $error = "Espace familial désactivé. Contactez l'administrateur.";
            } else {
                $_SESSION['user'] = [
                    'id'           => (int)$user['id'],
                    'username'     => $user['username'],
                    'display_name' => $user['display_name'],
                    'family_id'    => (int)$user['family_id'],
                    'is_admin'     => (bool)$user['is_admin'],
                ];
                $_SESSION['family_db']       = $user['db_name'];
                $_SESSION['app_lang']        = $user['lang'] ?? 'fr';
                $_SESSION['enabled_modules'] = json_decode($user['enabled_modules'] ?? '["calendar","budget","holidays","gifts"]', true);
                $redirectTo = $_GET['redirect'] ?? '/index.php';
                header('Location: ' . $redirectTo);
                exit;
            }
        } else {
            $error = tr('error_invalid_credentials');
        }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-container pf-login-wrapper">
    <div class="pf-login-card">
        
        <header class="pf-login-header">
            <img src="/favicon.png" alt="HouseHub Logo" class="pf-login-icon">
            <h1><?= tr('login_header') ?></h1>
            <p><?= tr('login_subtitle') ?></p>
        </header>
        
        <?php if ($error): ?>
            <div class="pf-login-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
            <div class="pf-form-group">
                <label class="pf-label" for="username"><?= tr('label_username') ?></label>
                <input type="text" id="username" name="username" class="pf-input" 
                       required autofocus placeholder="<?= tr('placeholder_username') ?>"
                       autocomplete="username" autocapitalize="none">
            </div>

            <div class="pf-form-group">
                <label class="pf-label" for="password"><?= tr('label_password') ?></label>
                <input type="password" id="password" name="password" class="pf-input" 
                       required placeholder="••••••" autocomplete="current-password">
            </div>

            <button type="submit" class="pf-btn pf-btn-block">
                <?= tr('btn_login_submit') ?>
            </button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:0.9rem;color:#64748b">
          Pas encore de compte ? <a href="/register.php" style="color:var(--primary)">Créer un espace</a>
        </p>

    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>