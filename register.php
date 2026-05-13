<?php
session_start();
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/meta_db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}

$error   = null;
$success = null;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createFamilyDb(PDO $meta, string $db_host, string $db_user, string $db_pass, int $family_id): string
{
    $db_name = 'househub_f' . $family_id;
    $schema  = file_get_contents(__DIR__ . '/docker/schema_family.sql');

    $root_pdo = new PDO(
        "mysql:host=$db_host;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $root_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $root_pdo->exec("USE `$db_name`");

    // Exécuter le schéma instruction par instruction
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
        if ($stmt !== '') {
            try { $root_pdo->exec($stmt); } catch (\PDOException $e) { /* ignore DROP IF EXISTS warnings */ }
        }
    }

    return $db_name;
}

// ─── Traitement du formulaire ─────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = "Session invalide (CSRF). Rechargez la page.";
    } else {
    $action       = $_POST['action'] ?? 'create';
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';
    $family_name  = trim($_POST['family_name'] ?? '');
    $invite_code  = trim($_POST['invite_code'] ?? '');

    // Validations communes
    if (!$username || !$display_name || !$password) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit faire au moins 6 caractères.";
    } elseif ($password !== $password2) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^[\p{L}0-9_.-]{3,50}$/u', $username)) {
        $error = "Nom d'utilisateur invalide (3-50 car., lettres/chiffres/._-)";
    } else {
        // Vérifier que l'username n'existe pas
        $chk = $meta_pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $error = "Ce nom d'utilisateur est déjà pris.";
        }
    }

    if (!$error) {
        $db_host = getenv('DB_HOST') ?: 'househub-db';
        $db_user = getenv('DB_USER') ?: 'househub';
        $db_pass = getenv('DB_PASS') ?: 'changeme';
        $hash    = password_hash($password, PASSWORD_BCRYPT);

        try {
            if ($action === 'join' && $invite_code) {
                // ── Rejoindre une famille existante ──────────────────────────
                $fam = $meta_pdo->prepare("SELECT * FROM families WHERE invite_code = ?");
                $fam->execute([$invite_code]);
                $family = $fam->fetch();

                if (!$family) {
                    $error = "Code d'invitation invalide.";
                } else {
                    $meta_pdo->prepare(
                        "INSERT INTO users (username, password_hash, display_name, family_id) VALUES (?, ?, ?, ?)"
                    )->execute([$username, $hash, $display_name, $family['id']]);

                    $_SESSION['user'] = [
                        'id'           => (int)$meta_pdo->lastInsertId(),
                        'username'     => $username,
                        'display_name' => $display_name,
                        'family_id'    => (int)$family['id'],
                    ];
                    $_SESSION['family_db'] = $family['db_name'];
                    session_regenerate_id(true);
                    header('Location: /index.php');
                    exit;
                }

            } else {
                // ── Créer une nouvelle famille ────────────────────────────────
                if (!$family_name) {
                    $error = "Le nom de la famille est obligatoire.";
                } else {
                    $meta_pdo->beginTransaction();

                    // Créer la famille avec un invite_code unique
                    $invite = bin2hex(random_bytes(8));
                    $meta_pdo->prepare(
                        "INSERT INTO families (name, db_name, invite_code) VALUES (?, '', ?)"
                    )->execute([$family_name, $invite]);
                    $family_id = (int)$meta_pdo->lastInsertId();

                    // Créer la DB famille
                    $db_name = createFamilyDb($meta_pdo, $db_host, $db_user, $db_pass, $family_id);

                    // Mettre à jour db_name maintenant qu'on a l'ID
                    $meta_pdo->prepare("UPDATE families SET db_name = ? WHERE id = ?")
                             ->execute([$db_name, $family_id]);

                    // Créer l'utilisateur
                    $meta_pdo->prepare(
                        "INSERT INTO users (username, password_hash, display_name, family_id) VALUES (?, ?, ?, ?)"
                    )->execute([$username, $hash, $display_name, $family_id]);
                    $user_id = (int)$meta_pdo->lastInsertId();

                    $meta_pdo->commit();

                    $_SESSION['user'] = [
                        'id'           => $user_id,
                        'username'     => $username,
                        'display_name' => $display_name,
                        'family_id'    => $family_id,
                    ];
                    $_SESSION['family_db'] = $db_name;
                    session_regenerate_id(true);
                    header('Location: /index.php');
                    exit;
                }
            }
        } catch (\Exception $e) {
            if ($meta_pdo->inTransaction()) $meta_pdo->rollBack();
            $error = "Erreur lors de la création : " . $e->getMessage();
        }
    }
    }
}

$pageTitle = "Inscription — HouseHub";
$activePage = "register";
require __DIR__ . '/header.php';
?>

<div class="pf-container pf-login-wrapper">
  <div class="pf-login-card" style="max-width:480px">

    <header class="pf-login-header">
      <img src="/favicon.png" alt="HouseHub Logo" class="pf-login-icon">
      <h1>Créer un compte</h1>
      <p>Nouvel espace familial ou rejoindre un existant</p>
    </header>

    <?php if ($error): ?>
      <div class="pf-login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Onglets -->
    <div style="display:flex;gap:8px;margin-bottom:24px">
      <button id="tab-create" onclick="switchTab('create')"
        class="pf-btn" style="flex:1">Nouvel espace</button>
      <button id="tab-join" onclick="switchTab('join')"
        class="pf-btn btn-secondary" style="flex:1">Rejoindre</button>
    </div>

    <!-- Formulaire commun -->
    <form method="post" action="/register.php" id="reg-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" id="reg-action" value="create">

      <div class="pf-form-group">
        <label class="pf-label">Nom d'utilisateur</label>
        <input type="text" name="username" class="pf-input" required
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          placeholder="ex: perco" autocapitalize="none">
      </div>

      <div class="pf-form-group">
        <label class="pf-label">Prénom / Pseudo affiché</label>
        <input type="text" name="display_name" class="pf-input" required
          value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>"
          placeholder="ex: Perco">
      </div>

      <div class="pf-form-group">
        <label class="pf-label">Mot de passe</label>
        <input type="password" name="password" class="pf-input" required
          placeholder="••••••" autocomplete="new-password">
      </div>

      <div class="pf-form-group">
        <label class="pf-label">Confirmer le mot de passe</label>
        <input type="password" name="password2" class="pf-input" required
          placeholder="••••••" autocomplete="new-password">
      </div>

      <!-- Champs spécifiques : Nouvel espace -->
      <div id="section-create">
        <div class="pf-form-group">
          <label class="pf-label">Nom de la famille / foyer</label>
          <input type="text" name="family_name" class="pf-input"
            value="<?= htmlspecialchars($_POST['family_name'] ?? '') ?>"
            placeholder="ex: Famille Dupont">
        </div>
      </div>

      <!-- Champs spécifiques : Rejoindre -->
      <div id="section-join" style="display:none">
        <div class="pf-form-group">
          <label class="pf-label">Code d'invitation</label>
          <input type="text" name="invite_code" class="pf-input"
            value="<?= htmlspecialchars($_POST['invite_code'] ?? '') ?>"
            placeholder="ex: a1b2c3d4e5f6g7h8" autocapitalize="none">
          <small style="color:#64748b;font-size:0.8rem;margin-top:4px;display:block">
            Demande ce code à la personne qui administre l'espace.
          </small>
        </div>
      </div>

      <button type="submit" class="pf-btn pf-btn-block" style="margin-top:8px">
        Créer mon compte
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:0.9rem;color:#64748b">
      Déjà un compte ? <a href="/login.php" style="color:var(--primary)">Se connecter</a>
    </p>

  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('section-create').style.display = tab === 'create' ? '' : 'none';
  document.getElementById('section-join').style.display   = tab === 'join'   ? '' : 'none';
  document.getElementById('reg-action').value = tab;
  document.getElementById('tab-create').className = 'pf-btn' + (tab === 'create' ? '' : ' btn-secondary');
  document.getElementById('tab-join').className   = 'pf-btn' + (tab === 'join'   ? '' : ' btn-secondary');
}
<?php if (($_POST['action'] ?? '') === 'join'): ?>
switchTab('join');
<?php endif; ?>
</script>

<?php require __DIR__ . '/footer.php'; ?>
