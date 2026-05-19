<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$pdo    = get_family_pdo();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Auto-create tables if missing
$pdo->exec("CREATE TABLE IF NOT EXISTS pf_lists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL DEFAULT 'Ma liste',
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_grocery_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  list_id INT NOT NULL DEFAULT 1,
  label VARCHAR(500) NOT NULL,
  in_cart TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_items_list (list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_grocery_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label_hash CHAR(64) NOT NULL,
  label_display VARCHAR(500) NOT NULL,
  last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grocery_hist_hash (label_hash),
  KEY idx_hist_last (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add list_id column if missing (migration)
try {
    $pdo->exec("ALTER TABLE pf_grocery_items ADD COLUMN list_id INT NOT NULL DEFAULT 1 AFTER id");
    $pdo->exec("ALTER TABLE pf_grocery_items ADD KEY idx_items_list (list_id)");
} catch (Exception $e) { /* already exists */ }

// ── Helpers ──────────────────────────────────────────────────────────────────

function normalize(string $s): string {
    return mb_strtolower(trim(mb_substr($s, 0, 500)));
}

function touch_history(PDO $pdo, string $label): void {
    $hash = hash('sha256', normalize($label));
    $pdo->prepare("INSERT INTO pf_grocery_history (label_hash, label_display)
                   VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE label_display=VALUES(label_display), last_used_at=NOW()")
        ->execute([$hash, trim($label)]);
}

function ensure_default_list(PDO $pdo): int {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM pf_lists")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO pf_lists (name, position) VALUES ('Ma liste', 0)");
        return (int) $pdo->lastInsertId();
    }
    return (int) $pdo->query("SELECT id FROM pf_lists ORDER BY position, id LIMIT 1")->fetchColumn();
}

function valid_list_id(PDO $pdo, int $id): bool {
    return (bool) $pdo->prepare("SELECT 1 FROM pf_lists WHERE id=?")
                      ->execute([$id]) &&
           (bool) $pdo->prepare("SELECT 1 FROM pf_lists WHERE id=?")->execute([$id]) &&
           (int) $pdo->prepare("SELECT COUNT(*) FROM pf_lists WHERE id=?")->execute([$id]) &&
           (int) ($r = $pdo->prepare("SELECT COUNT(*) FROM pf_lists WHERE id=?")) &&
           $r->execute([$id]) && (int)$r->fetchColumn() > 0;
}

function list_exists(PDO $pdo, int $id): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM pf_lists WHERE id=?");
    $s->execute([$id]);
    return (int)$s->fetchColumn() > 0;
}

// ── Body JSON ────────────────────────────────────────────────────────────────
$body = [];
if ($method === 'PUT' || $method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) $body = json_decode($raw, true) ?? [];
    foreach ($_POST as $k => $v) if (!isset($body[$k])) $body[$k] = $v;
}

// ── Router ────────────────────────────────────────────────────────────────────

// LISTS -----------------------------------------------------------------------
if ($action === 'lists') {

    if ($method === 'GET') {
        $firstId = ensure_default_list($pdo);
        $rows = $pdo->query("SELECT id, name, position FROM pf_lists ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['lists' => $rows, 'default_id' => $firstId]);
        exit;
    }

    if ($method === 'POST') {
        $name = trim($body['name'] ?? '');
        if ($name === '') { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
        $name = mb_substr($name, 0, 100);
        $pos = (int) $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM pf_lists")->fetchColumn();
        $s = $pdo->prepare("INSERT INTO pf_lists (name, position) VALUES (?, ?)");
        $s->execute([$name, $pos]);
        $id = (int) $pdo->lastInsertId();
        echo json_encode(['id' => $id, 'name' => $name, 'position' => $pos]);
        exit;
    }

    if ($method === 'PUT') {
        $id   = (int) ($_GET['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        if (!$id || $name === '') { http_response_code(400); echo json_encode(['error'=>'invalid']); exit; }
        $name = mb_substr($name, 0, 100);
        $pdo->prepare("UPDATE pf_lists SET name=? WHERE id=?")->execute([$name, $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        $count = (int) $pdo->query("SELECT COUNT(*) FROM pf_lists")->fetchColumn();
        if ($count <= 1) { http_response_code(400); echo json_encode(['error'=>'cannot delete last list']); exit; }
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_lists WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// ITEMS -----------------------------------------------------------------------
if ($action === 'items') {
    $list_id = (int) ($_GET['list_id'] ?? $body['list_id'] ?? 0);

    if ($method === 'GET') {
        if (!$list_id) { http_response_code(400); echo json_encode(['error'=>'list_id required']); exit; }
        $s = $pdo->prepare("SELECT id, list_id, label, in_cart, position FROM pf_grocery_items WHERE list_id=? ORDER BY in_cart, position, id");
        $s->execute([$list_id]);
        echo json_encode(['items' => $s->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($method === 'POST') {
        $label = trim($body['label'] ?? '');
        if (!$list_id || $label === '') { http_response_code(400); echo json_encode(['error'=>'missing fields']); exit; }
        // Check duplicate
        $norm = normalize($label);
        $s = $pdo->prepare("SELECT COUNT(*) FROM pf_grocery_items WHERE list_id=? AND LOWER(TRIM(label))=?");
        $s->execute([$list_id, $norm]);
        if ((int)$s->fetchColumn() > 0) { echo json_encode(['duplicate' => true]); exit; }
        $pos = (int) $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 FROM pf_grocery_items WHERE list_id=?")->execute([$list_id]) &&
               ($q = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 FROM pf_grocery_items WHERE list_id=?")) &&
               $q->execute([$list_id]) ? (int)$q->fetchColumn() : 0;
        $ins = $pdo->prepare("INSERT INTO pf_grocery_items (list_id, label, in_cart, position) VALUES (?, ?, 0, ?)");
        $ins->execute([$list_id, trim($label), $pos]);
        $id = (int) $pdo->lastInsertId();
        touch_history($pdo, $label);
        echo json_encode(['id' => $id, 'list_id' => $list_id, 'label' => trim($label), 'in_cart' => 0, 'position' => $pos]);
        exit;
    }

    if ($method === 'PUT') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
        if (isset($body['in_cart'])) {
            $pdo->prepare("UPDATE pf_grocery_items SET in_cart=? WHERE id=?")->execute([(int)$body['in_cart'], $id]);
        }
        if (isset($body['label'])) {
            $label = trim($body['label']);
            if ($label !== '') {
                $pdo->prepare("UPDATE pf_grocery_items SET label=? WHERE id=?")->execute([$label, $id]);
                touch_history($pdo, $label);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
        $row = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE id=?");
        $row->execute([$id]);
        if ($r = $row->fetch()) touch_history($pdo, $r['label']);
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// BULK ACTIONS ----------------------------------------------------------------
if ($action === 'uncheck_all' && $method === 'POST') {
    $list_id = (int) ($body['list_id'] ?? 0);
    if (!$list_id) { http_response_code(400); echo json_encode(['error'=>'list_id required']); exit; }
    $pdo->prepare("UPDATE pf_grocery_items SET in_cart=0 WHERE list_id=?")->execute([$list_id]);
    echo json_encode(['ok' => true]); exit;
}

if ($action === 'delete_picked' && $method === 'POST') {
    $list_id = (int) ($body['list_id'] ?? 0);
    if (!$list_id) { http_response_code(400); echo json_encode(['error'=>'list_id required']); exit; }
    $rows = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE list_id=? AND in_cart=1");
    $rows->execute([$list_id]);
    foreach ($rows->fetchAll() as $r) touch_history($pdo, $r['label']);
    $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=? AND in_cart=1")->execute([$list_id]);
    echo json_encode(['ok' => true]); exit;
}

if ($action === 'clear_all' && $method === 'POST') {
    $list_id = (int) ($body['list_id'] ?? 0);
    if (!$list_id) { http_response_code(400); echo json_encode(['error'=>'list_id required']); exit; }
    $rows = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE list_id=?");
    $rows->execute([$list_id]);
    foreach ($rows->fetchAll() as $r) touch_history($pdo, $r['label']);
    $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=?")->execute([$list_id]);
    echo json_encode(['ok' => true]); exit;
}

// HISTORY ---------------------------------------------------------------------
if ($action === 'history' && $method === 'GET') {
    $max = 20;
    $note = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type='setting' AND reference_id='liste_history_max'");
    $note->execute();
    if ($n = $note->fetch()) $max = max(1, min(50, (int)$n['content']));
    $s = $pdo->query("SELECT label_display FROM pf_grocery_history ORDER BY last_used_at DESC LIMIT $max");
    echo json_encode(['history' => $s->fetchAll(PDO::FETCH_COLUMN)]); exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
