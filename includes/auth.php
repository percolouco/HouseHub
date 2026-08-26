<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie qu'un utilisateur est connecté.
 * Si non, le redirige vers la page de login avec ?redirect=URL_DEMANDEE
 *
 * @param string|null $loginPage URL de la page de login (par défaut /login.php)
 */
/**
 * Vérifie qu'un utilisateur est connecté.
 * Si non, le redirige vers la page de login avec ?redirect=URL_DEMANDEE
 *
 * @param string|null $loginPage URL de la page de login (par défaut /login.php)
 */
function require_login(?string $loginPage = '/login.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // --- NOUVEAU : Auto-login via Cookie ---
    if (empty($_SESSION['user']) && isset($_COOKIE['hh_remember'])) {
        $cookieParts = explode(':', $_COOKIE['hh_remember']);
        
        if (count($cookieParts) === 2) {
            $cookieUserId = (int)$cookieParts[0];
            $cookieToken = $cookieParts[1];
            
            global $meta_pdo;
            require_once __DIR__ . '/meta_db.php';
            
            $stmt = $meta_pdo->prepare("
                SELECT u.id, u.username, u.password_hash, u.display_name, u.family_id, u.is_admin, u.is_active, u.lang, u.remember_token,
                       f.db_name, f.is_active as family_active, f.enabled_modules
                FROM users u
                LEFT JOIN families f ON f.id = u.family_id
                WHERE u.id = ?
            ");
            $stmt->execute([$cookieUserId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // On vérifie que le token correspond au hash stocké
            if ($user && $user['remember_token'] && hash_equals($user['remember_token'], hash('sha256', $cookieToken))) {
                if ($user['is_active'] && (!$user['family_id'] || $user['family_active'])) {
                    // Connexion silencieuse réussie, on peuple la session
                    $_SESSION['user'] = [
                        'id'           => (int)$user['id'],
                        'username'     => $user['username'],
                        'display_name' => $user['display_name'],
                        'family_id'    => (int)$user['family_id'],
                        'is_admin'     => (bool)$user['is_admin'],
                    ];
                    $_SESSION['family_db']       = $user['db_name'];
                    $_SESSION['app_lang']        = $user['lang'] ?? 'fr';
                    $_SESSION['enabled_modules'] = json_decode($user['enabled_modules'] ?? '["calendar","budget","holidays","gifts","calendar_ios"]', true);
                }
            }
        }
    }
    // ---------------------------------------

    if (empty($_SESSION['user'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }

        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $redirectParam = urlencode($currentUrl);
        $target = ($loginPage ?? '/login.php') . '?redirect=' . $redirectParam;

        header('Location: ' . $target);
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
if (!isset($familyPeople) && !empty($family_id)) {
    require_once __DIR__ . '/db.php'; 
    $stmtPeople = $pdo->query("SELECT id, name, user_id FROM pf_people ORDER BY id ASC");
    $familyPeople = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);
}
