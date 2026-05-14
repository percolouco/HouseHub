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
        $max = (int) $pdo->query('SELECT COALESCE(MAX(position),0) FROM pf_grocery_items')->fetchColumn();
        $pdo->prepare('INSERT INTO pf_grocery_items (label, position) VALUES (?,?)')->execute([$label, $max + 1]);
        $id = (int) $pdo->lastInsertId();
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

if ($action === 'uncheck_all' && $method === 'POST') {
    $pdo->exec('UPDATE pf_grocery_items SET in_cart=0');
    gOk(['updated' => true]);
}

if ($action === 'delete_picked' && $method === 'POST') {
    $pdo->exec('DELETE FROM pf_grocery_items WHERE in_cart=1');
    gOk(['deleted' => true]);
}

gErr('Action inconnue', 404);
