<?php
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

function tOk($d)  { echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE); exit; }
function tErr($m, $c = 400) { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }
function tBody() { return json_decode(file_get_contents('php://input'), true) ?? []; }

function discordNotify(PDO $pdo, string $msg): void {
    $s = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type='todo_settings' AND reference_id='webhook_discord'");
    $s->execute(); $url = $s->fetchColumn();
    if (!$url) return;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>4,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['username'=>'HouseHub Todo','content'=>$msg])]);
    curl_exec($ch); curl_close($ch);
}

// ── LISTS ──────────────────────────────────────────────────────────────────────
if ($action === 'lists') {
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT l.*, COUNT(t.id) as total,
             SUM(CASE WHEN t.done=0 THEN 1 ELSE 0 END) as pending
             FROM pf_todo_lists l
             LEFT JOIN pf_todos t ON t.list_id = l.id
             GROUP BY l.id ORDER BY l.position ASC, l.id ASC"
        )->fetchAll();
        tOk($rows);
    }
    if ($method === 'POST') {
        $d = tBody();
        $name = trim($d['name'] ?? ''); if (!$name) tErr('Nom requis');
        $pdo->prepare("INSERT INTO pf_todo_lists (name, color, icon) VALUES (?,?,?)")
            ->execute([$name, $d['color'] ?? '#3b82f6', $d['icon'] ?? '📋']);
        tOk(['id' => (int)$pdo->lastInsertId()]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
        $d  = tBody();
        $name = trim($d['name'] ?? ''); if (!$name) tErr('Nom requis');
        $pdo->prepare("UPDATE pf_todo_lists SET name=?, color=?, icon=? WHERE id=?")
            ->execute([$name, $d['color'] ?? '#3b82f6', $d['icon'] ?? '📋', $id]);
        tOk(['updated' => true]);
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
        $pdo->prepare("DELETE FROM pf_todo_lists WHERE id=?")->execute([$id]);
        tOk(['deleted' => true]);
    }
}

// ── TODOS ──────────────────────────────────────────────────────────────────────
if ($action === 'todos') {
    if ($method === 'GET') {
        $list_id  = $_GET['list_id'] ?? null;
        $show_done = ($_GET['show_done'] ?? '0') === '1';
        $priority = $_GET['priority'] ?? null;

        $where = [];
        $params = [];

        if ($list_id === 'all' || $list_id === null) {
            // all
        } elseif ($list_id === 'today') {
            $where[] = 't.due_date = CURDATE()';
            $where[] = 't.done = 0';
        } elseif ($list_id === 'upcoming') {
            $where[] = 't.due_date >= CURDATE()';
            $where[] = 't.done = 0';
        } elseif ($list_id === 'overdue') {
            $where[] = 't.due_date < CURDATE()';
            $where[] = 't.done = 0';
        } elseif ($list_id === 'done') {
            $where[] = 't.done = 1';
        } else {
            $where[] = 't.list_id = ?'; $params[] = $list_id;
        }

        if (!$show_done && !in_array($list_id, ['done', 'overdue'])) {
            $where[] = 't.done = 0';
        }

        if ($priority) { $where[] = 't.priority = ?'; $params[] = $priority; }

        $sql = "SELECT t.*, l.name as list_name, l.color as list_color, l.icon as list_icon
                FROM pf_todos t LEFT JOIN pf_todo_lists l ON l.id = t.list_id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY t.done ASC, FIELD(t.priority,"high","medium","low","none") ASC, t.due_date ASC, t.created_at ASC';

        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        tOk($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $d = tBody();
        $title = trim($d['title'] ?? ''); if (!$title) tErr('Titre requis');
        $pdo->prepare("INSERT INTO pf_todos (list_id, title, notes, due_date, due_time, priority) VALUES (?,?,?,?,?,?)")
            ->execute([
                $d['list_id'] ?: null,
                $title,
                $d['notes'] ?? null,
                $d['due_date'] ?: null,
                $d['due_time'] ?: null,
                $d['priority'] ?? 'none'
            ]);
        $id = (int)$pdo->lastInsertId();
        $new = $pdo->prepare("SELECT t.*, l.name as list_name, l.color as list_color, l.icon as list_icon FROM pf_todos t LEFT JOIN pf_todo_lists l ON l.id = t.list_id WHERE t.id=?");
        $new->execute([$id]);
        $row = $new->fetch();
        // Only notify immediately if no due_time (otherwise cron sends it at the right time)
        if (empty($d['due_time'])) {
            discordNotify($pdo, "📋 **Nouvelle tâche** : " . $title . ($row['list_name'] ? " _(". $row['list_name'] .")_" : ""));
        }
        tOk($row);
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
        $d  = tBody();

        // Toggle done
        if (isset($d['done'])) {
            $done = $d['done'] ? 1 : 0;
            $pdo->prepare("UPDATE pf_todos SET done=?, done_at=?, updated_at=NOW() WHERE id=?")
                ->execute([$done, $done ? date('Y-m-d H:i:s') : null, $id]);
            if ($done) {
                $t = $pdo->prepare("SELECT title FROM pf_todos WHERE id=?"); $t->execute([$id]);
                $row = $t->fetch();
                if ($row) discordNotify($pdo, "✅ **Tâche terminée** : " . $row['title']);
            }
            tOk(['done' => $done]);
        }

        // Full update
        $title = trim($d['title'] ?? ''); if (!$title) tErr('Titre requis');
        // Reset notified if due_time changed
        $pdo->prepare("UPDATE pf_todos SET list_id=?, title=?, notes=?, due_date=?, due_time=?, priority=?, notified=0, updated_at=NOW() WHERE id=?")
            ->execute([$d['list_id'] ?: null, $title, $d['notes'] ?? null, $d['due_date'] ?: null, $d['due_time'] ?: null, $d['priority'] ?? 'none', $id]);
        tOk(['updated' => true]);
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) tErr('ID manquant');
        $pdo->prepare("DELETE FROM pf_todos WHERE id=?")->execute([$id]);
        tOk(['deleted' => true]);
    }
}

// ── STATS ──────────────────────────────────────────────────────────────────────
if ($action === 'stats') {
    tOk([
        'total'    => $pdo->query("SELECT COUNT(*) FROM pf_todos")->fetchColumn(),
        'pending'  => $pdo->query("SELECT COUNT(*) FROM pf_todos WHERE done=0")->fetchColumn(),
        'done'     => $pdo->query("SELECT COUNT(*) FROM pf_todos WHERE done=1")->fetchColumn(),
        'today'    => $pdo->query("SELECT COUNT(*) FROM pf_todos WHERE due_date=CURDATE() AND done=0")->fetchColumn(),
        'overdue'  => $pdo->query("SELECT COUNT(*) FROM pf_todos WHERE due_date < CURDATE() AND done=0")->fetchColumn(),
    ]);
}

// ── SETTINGS ───────────────────────────────────────────────────────────────────
if ($action === 'settings') {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT reference_id, content FROM pf_notes WHERE note_type='todo_settings'")->fetchAll();
        $s = [];
        foreach ($rows as $r) $s[$r['reference_id']] = $r['content'];
        tOk($s);
    }
    if ($method === 'PUT') {
        $d = tBody();
        foreach ($d as $key => $val) {
            $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('todo_settings',?,?) ON DUPLICATE KEY UPDATE content=?")
                ->execute([$key, $val ?? '', $val ?? '']);
        }
        tOk(['updated' => true]);
    }
}

tErr('Action inconnue', 404);
