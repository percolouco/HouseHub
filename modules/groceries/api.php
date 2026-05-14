<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
});

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

$pdo->exec("
CREATE TABLE IF NOT EXISTS pf_grocery_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(500) NOT NULL,
  in_cart TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
CREATE TABLE IF NOT EXISTS pf_grocery_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label_hash CHAR(64) NOT NULL,
  label_display VARCHAR(500) NOT NULL,
  last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grocery_hist_hash (label_hash),
  KEY idx_hist_last (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function gOk($d)
{
    echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE);
    exit;
}
function gErr($m, $c = 400)
{
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m]);
    exit;
}
function gBody()
{
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function groceryNormalize(string $label): string
{
    $t = trim($label);
    if ($t === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $t = mb_strtolower($t, 'UTF-8');
    } else {
        $t = strtolower($t);
    }
    if (function_exists('mb_strlen') && mb_strlen($t, 'UTF-8') > 500) {
        $t = mb_substr($t, 0, 500, 'UTF-8');
    } elseif (strlen($t) > 500) {
        $t = substr($t, 0, 500);
    }
    return $t;
}

function groceryHistoryMax(PDO $pdo): int
{
    try {
        $s = $pdo->query("SELECT content FROM pf_notes WHERE note_type='grocery_settings' AND reference_id='history_max'");
        $v = $s ? $s->fetchColumn() : false;
        $n = ($v !== false && $v !== null && $v !== '') ? (int) $v : 20;
    } catch (\Throwable $e) {
        $n = 20;
    }
    return max(1, min(50, $n));
}

function groceryTouchHistory(PDO $pdo, string $labelDisplay): void
{
    $norm = groceryNormalize($labelDisplay);
    if ($norm === '') {
        return;
    }
    $disp = trim($labelDisplay);
    if (function_exists('mb_strlen') && mb_strlen($disp, 'UTF-8') > 500) {
        $disp = mb_substr($disp, 0, 500, 'UTF-8');
    } elseif (strlen($disp) > 500) {
        $disp = substr($disp, 0, 500);
    }
    $hash = hash('sha256', $norm, false);
    $pdo->prepare(
        'INSERT INTO pf_grocery_history (label_hash, label_display) VALUES (?,?)
         ON DUPLICATE KEY UPDATE label_display=VALUES(label_display), last_used_at=NOW()'
    )->execute([$hash, $disp]);

    $max = groceryHistoryMax($pdo);
    $cnt = (int) $pdo->query('SELECT COUNT(*) FROM pf_grocery_history')->fetchColumn();
    if ($cnt <= $max) {
        return;
    }
    $toDel = $cnt - $max;
    $stmt = $pdo->prepare('SELECT id FROM pf_grocery_history ORDER BY last_used_at ASC, id ASC LIMIT ?');
    $stmt->execute([$toDel]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM pf_grocery_history WHERE id IN ($placeholders)")->execute($ids);
}

function groceryCurrentNormSet(PDO $pdo): array
{
    $rows = $pdo->query('SELECT label FROM pf_grocery_items')->fetchAll(PDO::FETCH_COLUMN);
    $set = [];
    foreach ($rows as $lab) {
        $n = groceryNormalize((string) $lab);
        if ($n !== '') {
            $set[$n] = true;
        }
    }
    return $set;
}

if ($action === 'items') {
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT id, label, in_cart, position, created_at, updated_at
             FROM pf_grocery_items
             ORDER BY in_cart ASC, position ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['in_cart'] = (int) $r['in_cart'];
            $r['id'] = (int) $r['id'];
            $r['position'] = (int) $r['position'];
        }
        unset($r);
        gOk($rows);
    }

    if ($method === 'POST') {
        $d = gBody();
        $label = trim($d['label'] ?? '');
        if ($label === '') {
            gErr('Libellé requis');
        }
        $norm = groceryNormalize($label);
        $chk = $pdo->query('SELECT label FROM pf_grocery_items');
        while ($existing = $chk->fetchColumn()) {
            if (groceryNormalize((string) $existing) === $norm) {
                gErr(function_exists('tr') ? tr('groceries_err_duplicate') : 'Ce produit est déjà dans la liste.');
            }
        }
        $max = (int) $pdo->query('SELECT COALESCE(MAX(position),0) FROM pf_grocery_items')->fetchColumn();
        $pdo->prepare('INSERT INTO pf_grocery_items (label, position) VALUES (?,?)')->execute([$label, $max + 1]);
        $id = (int) $pdo->lastInsertId();
        groceryTouchHistory($pdo, $label);
        $s = $pdo->prepare('SELECT id, label, in_cart, position, created_at, updated_at FROM pf_grocery_items WHERE id=?');
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $row['in_cart'] = (int) $row['in_cart'];
        $row['id'] = (int) $row['id'];
        $row['position'] = (int) $row['position'];
        gOk($row);
    }

    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            gErr('ID manquant');
        }
        $d = gBody();
        if (array_key_exists('in_cart', $d)) {
            $in = !empty($d['in_cart']) ? 1 : 0;
            $pdo->prepare('UPDATE pf_grocery_items SET in_cart=?, updated_at=NOW() WHERE id=?')->execute([$in, $id]);
            gOk(['in_cart' => $in]);
        }
        $label = trim($d['label'] ?? '');
        if ($label === '') {
            gErr('Libellé requis');
        }
        $pdo->prepare('UPDATE pf_grocery_items SET label=?, updated_at=NOW() WHERE id=?')->execute([$label, $id]);
        groceryTouchHistory($pdo, $label);
        gOk(['updated' => true]);
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            gErr('ID manquant');
        }
        $pdo->prepare('DELETE FROM pf_grocery_items WHERE id=?')->execute([$id]);
        gOk(['deleted' => true]);
    }
}

if ($action === 'history' && $method === 'GET') {
    $max = groceryHistoryMax($pdo);
    $rows = $pdo->query(
        'SELECT label_display FROM pf_grocery_history ORDER BY last_used_at DESC LIMIT 100'
    )->fetchAll(PDO::FETCH_COLUMN);
    $onList = groceryCurrentNormSet($pdo);
    $labels = [];
    foreach ($rows as $disp) {
        $disp = (string) $disp;
        if (groceryNormalize($disp) === '') {
            continue;
        }
        if (isset($onList[groceryNormalize($disp)])) {
            continue;
        }
        $labels[] = $disp;
        if (count($labels) >= $max) {
            break;
        }
    }
    gOk(['labels' => $labels, 'history_max' => $max]);
}

if ($action === 'uncheck_all' && $method === 'POST') {
    $pdo->exec('UPDATE pf_grocery_items SET in_cart=0');
    gOk(['updated' => true]);
}

if ($action === 'delete_picked' && $method === 'POST') {
    $labs = $pdo->query('SELECT label FROM pf_grocery_items WHERE in_cart=1')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($labs as $lab) {
        groceryTouchHistory($pdo, (string) $lab);
    }
    $pdo->exec('DELETE FROM pf_grocery_items WHERE in_cart=1');
    gOk(['deleted' => true]);
}

if ($action === 'clear_all' && $method === 'POST') {
    $labs = $pdo->query('SELECT label FROM pf_grocery_items')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($labs as $lab) {
        groceryTouchHistory($pdo, (string) $lab);
    }
    $pdo->exec('DELETE FROM pf_grocery_items');
    gOk(['deleted' => true]);
}

gErr('Action inconnue', 404);
