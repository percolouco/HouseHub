<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit;
});

require_once dirname(__DIR__, 2) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function tOk($d)  { ob_end_clean(); echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE); exit; }
function tErr($m, $c = 400) { ob_end_clean(); http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }
function tBody() { return json_decode(file_get_contents('php://input'), true) ?? []; }

function planka_api($method, $path, $body = null, $token = null) {
    $url = 'http://10.200.33.10:1337' . $path;
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true) ?? [];
}

function planka_token(PDO $pdo) {
    $row = $pdo->query("SELECT admin_token, token_expires_at FROM pf_planka_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['token_expires_at'] > date('Y-m-d H:i:s')) return $row['admin_token'];
    $resp = planka_api('POST', '/api/access-tokens', ['emailOrUsername' => 'admin', 'password' => 'ideine']);
    $token = $resp['item'] ?? null;
    if (!$token) return null;
    $exp = date('Y-m-d H:i:s', strtotime('+30 days'));
    $pdo->prepare("INSERT INTO pf_planka_config (id,admin_token,token_expires_at) VALUES (1,?,?) ON DUPLICATE KEY UPDATE admin_token=VALUES(admin_token),token_expires_at=VALUES(token_expires_at)")->execute([$token, $exp]);
    return $token;
}

$token = planka_token($pdo);
if (!$token) tErr('Impossible d\'obtenir un token Planka', 503);

if ($action === 'boards') {
    $cfg = $pdo->query("SELECT project_id FROM pf_planka_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $project_id = $cfg['project_id'] ?? '1713338747178714130';
    $resp = planka_api('GET', '/api/projects/' . $project_id, null, $token);
    $boards = [];
    if (isset($resp['included']['boards'])) {
        foreach ($resp['included']['boards'] as $b) {
            $boards[] = ['id' => $b['id'], 'name' => $b['name']];
        }
    }
    tOk($boards);
}

if ($action === 'board') {
    $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
    $resp = planka_api('GET', '/api/boards/' . $id, null, $token);
    $inc = $resp['included'] ?? [];

    // Filter: only lists with a real name (Planka creates unnamed "None" placeholder lists)
    $lists = array_values(array_filter($inc['lists'] ?? [], fn($l) =>
        !empty($l['name']) && $l['name'] !== 'None' &&
        (isset($l['boardId']) ? $l['boardId'] === $id : true)
    ));
    // Filter cards that belong to a list in this board
    $listIds = array_column($lists, 'id');
    $cards = array_values(array_filter($inc['cards'] ?? [], fn($c) =>
        in_array($c['listId'] ?? '', $listIds)
    ));

    tOk([
        'lists'      => $lists,
        'cards'      => $cards,
        'labels'     => $inc['labels'] ?? [],
        'members'    => $inc['users'] ?? [],
        'cardLabels' => $inc['cardLabels'] ?? [],
        'tasks'      => $inc['tasks'] ?? [],
    ]);
}

if ($action === 'set_active_board') {
    $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
    $pdo->prepare("INSERT INTO pf_planka_config (id, active_board_id) VALUES (1,?) ON DUPLICATE KEY UPDATE active_board_id=VALUES(active_board_id)")->execute([$id]);
    tOk(['updated' => true]);
}

if ($action === 'create_card') {
    $list_id  = $_GET['list_id'] ?? null;  if (!$list_id)  tErr('list_id manquant');
    $board_id = $_GET['board_id'] ?? null; if (!$board_id) tErr('board_id manquant');
    $name     = trim($_GET['name'] ?? ''); if (!$name)     tErr('name manquant');
    $resp = planka_api('POST', '/api/boards/' . $board_id . '/cards', [
        'listId'   => $list_id,
        'name'     => $name,
        'position' => 65535,
    ], $token);
    tOk($resp['item'] ?? []);
}

if ($action === 'update_card') {
    $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
    $d = tBody();
    $payload = [];
    if (isset($d['name']))        $payload['name']        = $d['name'];
    if (isset($d['description']))  $payload['description']  = $d['description'];
    if (array_key_exists('dueDate', $d)) $payload['dueDate'] = $d['dueDate'];
    if (isset($d['listId']))       $payload['listId']       = $d['listId'];
    $resp = planka_api('PATCH', '/api/cards/' . $id, $payload, $token);
    tOk($resp['item'] ?? []);
}

if ($action === 'delete_card') {
    $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
    planka_api('DELETE', '/api/cards/' . $id, null, $token);
    tOk(['deleted' => true]);
}

if ($action === 'create_task') {
    $card_id = $_GET['card_id'] ?? null; if (!$card_id) tErr('card_id manquant');
    $name    = trim($_GET['name'] ?? '');  if (!$name)   tErr('name manquant');
    $resp = planka_api('POST', '/api/cards/' . $card_id . '/tasks', [
        'name'     => $name,
        'position' => 65535,
    ], $token);
    tOk($resp['item'] ?? []);
}

if ($action === 'toggle_task') {
    $id           = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
    $is_completed = ($_GET['is_completed'] ?? '0') === '1';
    $resp = planka_api('PATCH', '/api/tasks/' . $id, ['isCompleted' => $is_completed], $token);
    tOk($resp['item'] ?? []);
}

if ($action === 'set_project') {
    $project_id = $_GET['project_id'] ?? null; if (!$project_id) tErr('project_id manquant');
    $pdo->prepare("INSERT INTO pf_planka_config (id, project_id) VALUES (1,?) ON DUPLICATE KEY UPDATE project_id=VALUES(project_id)")->execute([$project_id]);
    tOk(['updated' => true]);
}

if ($action === 'config') {
    $row = $pdo->query("SELECT project_id, active_board_id FROM pf_planka_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    tOk($row ?: ['project_id' => '1713338747178714130', 'active_board_id' => null]);
}

tErr('Action inconnue', 404);
