<?php
session_start();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php'; // Toujours s'assurer que tr() est dispo

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
        $stmt = $pdo->prepare("SELECT id, username, password_hash, display_name FROM pf_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
            ];
            $redirectTo = $_GET['redirect'] ?? '/index.php';
            header('Location: ' . $redirectTo);
            exit;
        } else {
            $error = tr('error_invalid_credentials');
        }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-login-container">
  <h1 style="text-align:center; font-size:1.8rem;"><?= tr('login_header') ?></h1>
  
  <?php if ($error): ?>
    <div class="pf-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
    <div class="pf-form-group">
      <label for="username"><?= tr('label_username') ?></label>
      <input type="text" id="username" name="username" required autofocus placeholder="<?= tr('placeholder_username') ?>">
    </div>

    <div class="pf-form-group">
      <label for="password"><?= tr('label_password') ?></label>
      <input type="password" id="password" name="password" required placeholder="••••••">
    </div>

    <button type="submit" class="pf-btn"><?= tr('btn_login_submit') ?></button>
  </form>
</div>

<?php require __DIR__ . '/footer.php'; ?>