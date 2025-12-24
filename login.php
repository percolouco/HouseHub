<?php
session_start();

require __DIR__ . '/includes/db.php'; // Doit définir $pdo (PDO connecté à ta DB)

$pageTitle = "PachaFamily - Login";
$activePage = "login";

// Si l'utilisateur est déjà connecté, on le renvoie à l'accueil
if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Merci de renseigner identifiant et mot de passe.";
    } else {
        // On récupère l'utilisateur dans la table pf_users
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, display_name
            FROM pf_users
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification du mot de passe
        if ($user && password_verify($password, $user['password_hash'])) {
            // Connexion OK : on stocke les infos utiles en session
            $_SESSION['user'] = [
                'id'           => (int)$user['id'],
                'username'     => $user['username'],
                'display_name' => $user['display_name'],
            ];

            // Redirection : soit la page demandée, soit l'accueil
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

<h1>Connexion</h1>

<?php if ($error): ?>
  <div class="pf-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
  <div class="pf-form-group">
    <label for="username">Identifiant</label>
    <input type="text" id="username" name="username" required autofocus>
  </div>

  <div class="pf-form-group">
    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" required>
  </div>

  <button type="submit">Se connecter</button>
</form>

<?php
require __DIR__ . '/footer.php';
