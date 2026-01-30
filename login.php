<?php
session_start();
require __DIR__ . '/includes/db.php';

$pageTitle = "Connexion";
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
        $error = "Champs obligatoires.";
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
            $error = "Identifiants incorrects.";
        }
    }
}

require __DIR__ . '/header.php';
?>

<div class="pf-login-container">
  <h1 style="text-align:center; font-size:1.8rem;">Connexion</h1>
  
  <?php if ($error): ?>
    <div class="pf-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
    <div class="pf-form-group">
      <label for="username">Identifiant</label>
      <input type="text" id="username" name="username" required autofocus placeholder="ex: pacha">
    </div>

    <div class="pf-form-group">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" required placeholder="••••••">
    </div>

    <button type="submit" class="pf-btn">Se connecter</button>
  </form>
</div>

<?php require __DIR__ . '/footer.php'; ?>