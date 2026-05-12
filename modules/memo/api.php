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

$UPLOAD_DIR = '/uploads/memo/';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function mOk($d)  { echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function mErr($m, $c = 400) { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }
function mBody() { return json_decode(file_get_contents('php://input'), true) ?? []; }

function parseTags(string $raw): string {
    $tags = array_unique(array_filter(array_map(function($t) {
        return strtolower(trim(ltrim($t, '#'), " \t\n\r,"));
    }, preg_split('/[\s,]+/', $raw))));
    return implode(',', $tags);
}

function imgExts(): array { return ['jpg','jpeg','png','gif','webp','svg','bmp']; }
function isImage(string $fname): bool { return in_array(strtolower(pathinfo($fname, PATHINFO_EXTENSION)), imgExts()); }

function handleUpload(string $field, string $dir): ?array {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $orig  = $_FILES[$field]['name'];
    $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = array_merge(imgExts(), ['pdf','doc','docx','txt','csv','zip','mp3','mp4']);
    if (!in_array($ext, $allowed)) return null;
    $fname = uniqid('m', true) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $fname)) return null;
    return ['filename' => $fname, 'original_name' => $orig, 'size' => filesize($dir . $fname)];
}

// ── NOTES ─────────────────────────────────────────────────────────────────────
if ($action === 'notes') {
    if ($method === 'GET') {
        $id  = $_GET['id']  ?? null;
        $q   = trim($_GET['q']   ?? '');
        $tag = trim($_GET['tag'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;
        $offset  = ($page - 1) * $perPage;

        if ($id) {
            $n = $pdo->prepare("SELECT * FROM pf_memo_notes WHERE id = ?"); $n->execute([$id]);
            $note = $n->fetch(); if (!$note) mErr('Note introuvable', 404);
            $a = $pdo->prepare("SELECT * FROM pf_memo_attachments WHERE note_id = ? ORDER BY created_at");
            $a->execute([$id]); $note['attachments'] = $a->fetchAll();
            mOk($note);
        }

        if ($q) {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT id, title, tags, updated_at, LEFT(content,200) as snippet
                FROM pf_memo_notes WHERE title LIKE ? OR content LIKE ? OR tags LIKE ?
                ORDER BY updated_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$like, $like, $like, $perPage, $offset]);
            $total = $pdo->prepare("SELECT COUNT(*) FROM pf_memo_notes WHERE title LIKE ? OR content LIKE ? OR tags LIKE ?");
            $total->execute([$like, $like, $like]);
        } elseif ($tag) {
            $stmt = $pdo->prepare("SELECT id, title, tags, updated_at, LEFT(content,200) as snippet
                FROM pf_memo_notes WHERE FIND_IN_SET(?, tags) > 0
                ORDER BY updated_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$tag, $perPage, $offset]);
            $total = $pdo->prepare("SELECT COUNT(*) FROM pf_memo_notes WHERE FIND_IN_SET(?, tags) > 0");
            $total->execute([$tag]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title, tags, updated_at, LEFT(content,200) as snippet
                FROM pf_memo_notes ORDER BY updated_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$perPage, $offset]);
            $total = $pdo->query("SELECT COUNT(*) FROM pf_memo_notes");
        }

        mOk(['notes' => $stmt->fetchAll(), 'total' => (int)$total->fetchColumn(),
             'page' => $page, 'per_page' => $perPage]);
    }

    if ($method === 'POST') {
        $d = $_POST ?: mBody();
        $title = trim($d['title'] ?? '');
        if (!$title) mErr('Titre requis');
        $tags = parseTags($d['tags'] ?? '');
        $pdo->prepare("INSERT INTO pf_memo_notes (title, content, tags) VALUES (?,?,?)")
            ->execute([$title, $d['content'] ?? '', $tags]);
        $id = $pdo->lastInsertId();

        // Files
        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $f) {
                if ($f['error'] === UPLOAD_ERR_OK) {
                    $up = handleUpload($key, $UPLOAD_DIR);
                    if ($up) {
                        $type = isImage($up['filename']) ? 'image' : 'file';
                        $pdo->prepare("INSERT INTO pf_memo_attachments (note_id,type,filename,original_name,size) VALUES (?,?,?,?,?)")
                            ->execute([$id, $type, $up['filename'], $up['original_name'], $up['size']]);
                    }
                }
            }
        }
        // URLs from JSON body
        foreach (($d['urls'] ?? []) as $urlItem) {
            if (!empty($urlItem['url'])) {
                $pdo->prepare("INSERT INTO pf_memo_attachments (note_id,type,url,label) VALUES (?,?,?,?)")
                    ->execute([$id, 'url', $urlItem['url'], $urlItem['label'] ?? '']);
            }
        }
        mOk(['id' => (int)$id]);
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) mErr('ID manquant');
        $d  = mBody();
        $title = trim($d['title'] ?? '');
        if (!$title) mErr('Titre requis');
        $tags = parseTags($d['tags'] ?? '');
        $pdo->prepare("UPDATE pf_memo_notes SET title=?, content=?, tags=?, updated_at=NOW() WHERE id=?")
            ->execute([$title, $d['content'] ?? '', $tags, $id]);
        mOk(['updated' => true]);
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) mErr('ID manquant');
        $rows = $pdo->prepare("SELECT filename FROM pf_memo_attachments WHERE note_id=? AND filename IS NOT NULL");
        $rows->execute([$id]);
        foreach ($rows->fetchAll() as $r) @unlink($UPLOAD_DIR . $r['filename']);
        $pdo->prepare("DELETE FROM pf_memo_notes WHERE id=?")->execute([$id]);
        mOk(['deleted' => true]);
    }
}

// ── TAGS ──────────────────────────────────────────────────────────────────────
if ($action === 'tags') {
    $rows = $pdo->query("SELECT tags FROM pf_memo_notes WHERE tags != ''")->fetchAll();
    $counts = [];
    foreach ($rows as $r) {
        foreach (explode(',', $r['tags']) as $t) {
            $t = trim($t);
            if ($t) $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
    }
    arsort($counts);
    mOk(array_map(fn($t, $c) => ['tag' => $t, 'count' => $c], array_keys($counts), $counts));
}

// ── ATTACHMENTS ───────────────────────────────────────────────────────────────
if ($action === 'attachments') {
    if ($method === 'POST') {
        $id = $_POST['note_id'] ?? null; if (!$id) mErr('note_id manquant');
        // File upload
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $up = handleUpload('file', $UPLOAD_DIR);
            if (!$up) mErr('Upload échoué ou format non autorisé');
            $type = isImage($up['filename']) ? 'image' : 'file';
            $pdo->prepare("INSERT INTO pf_memo_attachments (note_id,type,filename,original_name,size) VALUES (?,?,?,?,?)")
                ->execute([$id, $type, $up['filename'], $up['original_name'], $up['size']]);
            mOk(['id' => (int)$pdo->lastInsertId(), 'type' => $type,
                 'filename' => $up['filename'], 'original_name' => $up['original_name']]);
        }
        // URL
        $url = trim($_POST['url'] ?? '');
        if ($url) {
            $label = trim($_POST['label'] ?? '');
            $pdo->prepare("INSERT INTO pf_memo_attachments (note_id,type,url,label) VALUES (?,?,?,?)")
                ->execute([$id, 'url', $url, $label]);
            mOk(['id' => (int)$pdo->lastInsertId(), 'type' => 'url', 'url' => $url, 'label' => $label]);
        }
        mErr('Aucun fichier ou URL fourni');
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) mErr('ID manquant');
        $r  = $pdo->prepare("SELECT filename FROM pf_memo_attachments WHERE id=?"); $r->execute([$id]);
        $row = $r->fetch();
        if ($row && $row['filename']) @unlink($UPLOAD_DIR . $row['filename']);
        $pdo->prepare("DELETE FROM pf_memo_attachments WHERE id=?")->execute([$id]);
        mOk(['deleted' => true]);
    }
}

// ── FILE SERVE ────────────────────────────────────────────────────────────────
if ($action === 'file') {
    $id  = $_GET['id'] ?? null;
    $row = null;
    if ($id) { $s = $pdo->prepare("SELECT * FROM pf_memo_attachments WHERE id=?"); $s->execute([$id]); $row = $s->fetch(); }
    if (!$row || !$row['filename'] || !file_exists($UPLOAD_DIR . $row['filename'])) { http_response_code(404); exit; }
    $ext  = strtolower(pathinfo($row['filename'], PATHINFO_EXTENSION));
    $mime = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
        'webp'=>'image/webp','svg'=>'image/svg+xml','pdf'=>'application/pdf',
        'txt'=>'text/plain','mp4'=>'video/mp4','mp3'=>'audio/mpeg',
    ][$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . rawurlencode($row['original_name'] ?? $row['filename']) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($UPLOAD_DIR . $row['filename']); exit;
}

mErr('Action inconnue', 404);
