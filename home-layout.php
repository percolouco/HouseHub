<?php
// Enregistre l'ordre des apps de l'écran d'accueil (vue « apps »).
require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require_once __DIR__ . '/includes/meta_db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/ui_prefs.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => tr('error_csrf')]);
    exit;
}

hhUiPrefs(); // garantit que les colonnes existent
$uid = (int)$_SESSION['user']['id'];
if (($_POST['action'] ?? '') === 'reset') {
    $order = [];
} else {
    $order = json_decode($_POST['order'] ?? '[]', true);
    $valid = array_keys(hhAppRegistry());
    $order = is_array($order) ? array_values(array_unique(array_filter($order, fn($k) => is_string($k) && in_array($k, $valid, true)))) : [];
}
$meta_pdo->prepare("UPDATE users SET app_order = ? WHERE id = ?")->execute([json_encode($order), $uid]);
hhUiPrefs(true);
echo json_encode(['success' => true]);
