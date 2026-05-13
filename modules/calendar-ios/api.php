<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/meta_db.php';
require_once __DIR__ . '/caldav_sync.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = (int)($_SESSION['user']['id'] ?? 0);
$familyId = (int)($_SESSION['user']['family_id'] ?? 0);

$meta_pdo->exec("
CREATE TABLE IF NOT EXISTS user_calendar_integrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'icloud_caldav',
  username VARCHAR(255) NOT NULL,
  secret_encrypted TEXT NOT NULL,
  dav_principal_url VARCHAR(1024) DEFAULT NULL,
  calendar_url VARCHAR(1024) DEFAULT NULL,
  status VARCHAR(30) DEFAULT 'connected',
  last_sync_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_provider (user_id, provider)
)");
$pdo->exec("
CREATE TABLE IF NOT EXISTS pf_calendar_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  family_id INT NOT NULL,
  created_by_user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  is_all_day TINYINT(1) DEFAULT 0,
  timezone VARCHAR(64) DEFAULT 'Europe/Paris',
  rrule VARCHAR(500) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'confirmed',
  external_uid VARCHAR(255) DEFAULT NULL,
  sync_state VARCHAR(30) DEFAULT 'pending_push',
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_external_uid (external_uid)
)");
$pdo->exec("
CREATE TABLE IF NOT EXISTS pf_calendar_event_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  calendar_event_id INT NOT NULL,
  external_uid VARCHAR(255) NOT NULL,
  external_etag VARCHAR(255) DEFAULT NULL,
  calendar_url VARCHAR(1024) DEFAULT NULL,
  external_href VARCHAR(2048) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_calendar_event (calendar_event_id),
  UNIQUE KEY uq_external_link (external_uid)
)");
try {
    $pdo->exec('ALTER TABLE pf_calendar_event_links ADD COLUMN external_href VARCHAR(2048) DEFAULT NULL');
} catch (Throwable $e) {
    // colonne déjà présente
}

function ios_ok($data): void { echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function ios_err(string $message, int $status = 400): void { http_response_code($status); echo json_encode(['ok' => false, 'error' => $message]); exit; }
function ios_body(): array { return json_decode(file_get_contents('php://input'), true) ?? []; }
function ios_require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf($token)) {
        ios_err('Token CSRF invalide', 403);
    }
}

function ios_normalize_datetime(?string $s): ?string
{
    if ($s === null || $s === '') {
        return null;
    }
    $s = str_replace('T', ' ', trim($s));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
        $s .= ':00';
    }
    return $s;
}

$integrationStmt = $meta_pdo->prepare("SELECT * FROM user_calendar_integrations WHERE user_id = ? AND provider='icloud_caldav'");
$integrationStmt->execute([$userId]);
$integration = $integrationStmt->fetch();

if ($action === 'events' && $method === 'GET') {
    $rows = $pdo->query("SELECT * FROM pf_calendar_events ORDER BY start_at ASC")->fetchAll();
    ios_ok($rows);
}

if ($action === 'events' && $method === 'POST') {
    ios_require_csrf();
    $d = ios_body();
    if (empty($d['title']) || empty($d['start_at']) || empty($d['end_at'])) {
        ios_err('Champs requis manquants');
    }
    $stmt = $pdo->prepare("
        INSERT INTO pf_calendar_events (family_id, created_by_user_id, title, description, location, start_at, end_at, timezone, sync_state)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Europe/Paris', 'pending_push')
    ");
    $stmt->execute([
        $familyId, $userId, trim($d['title']), $d['description'] ?? null, $d['location'] ?? null,
        ios_normalize_datetime($d['start_at'] ?? null), ios_normalize_datetime($d['end_at'] ?? null),
    ]);
    ios_ok(['id' => (int)$pdo->lastInsertId()]);
}

if ($action === 'events' && $method === 'PUT') {
    ios_require_csrf();
    $d = ios_body();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) ios_err('ID invalide');
    $stmt = $pdo->prepare("
        UPDATE pf_calendar_events
        SET title=?, description=?, location=?, start_at=?, end_at=?, updated_at=NOW(), sync_state='pending_push'
        WHERE id=?
    ");
    $stmt->execute([
        trim($d['title'] ?? ''), $d['description'] ?? null, $d['location'] ?? null,
        ios_normalize_datetime($d['start_at'] ?? null), ios_normalize_datetime($d['end_at'] ?? null), $id,
    ]);
    ios_ok(['updated' => true]);
}

if ($action === 'events' && $method === 'DELETE') {
    ios_require_csrf();
    $d = ios_body();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) ios_err('ID invalide');
    $row = $pdo->prepare("SELECT id FROM pf_calendar_events WHERE id=?");
    $row->execute([$id]);
    if (!$row->fetch()) ios_err('Événement introuvable', 404);
    $pdo->prepare("UPDATE pf_calendar_events SET deleted_at=NOW(), sync_state='pending_delete' WHERE id=?")->execute([$id]);
    ios_ok(['deleted' => true]);
}

if ($action === 'sync_status' && $method === 'GET') {
    if (!$integration) {
        ios_ok(['message' => 'Aucune intégration iCloud configurée.']);
    }
    $msg = 'Connecté iCloud. Dernière synchro: ' . ($integration['last_sync_at'] ?? 'jamais');
    ios_ok(['message' => $msg]);
}

if ($action === 'sync' && $method === 'POST') {
    ios_require_csrf();
    if (!$integration) ios_err('Connexion iCloud non configurée.', 400);

    try {
        $ctx = ios_caldav_prepare($integration, $meta_pdo);
        $integration = $ctx['integration'];
        $davPassword = $ctx['password'];
        $syncUrls = ios_sync_calendar_collection_urls($integration, $davPassword);
        $remoteEvents = ios_fetch_remote_events_all_calendars($integration, $davPassword);

        $localStmt = $pdo->query("SELECT * FROM pf_calendar_events WHERE deleted_at IS NULL");
        $localEvents = $localStmt->fetchAll();

        $linkStmt = $pdo->prepare('SELECT external_href, calendar_url FROM pf_calendar_event_links WHERE calendar_event_id = ?');

        foreach ($localEvents as $evt) {
            if ($evt['sync_state'] === 'pending_push' || empty($evt['external_uid'])) {
                $linkStmt->execute([$evt['id']]);
                $lr = $linkStmt->fetch() ?: [];
                $push = ios_push_event_to_remote(
                    $integration,
                    $evt,
                    $davPassword,
                    $lr['external_href'] ?? null,
                    $lr['calendar_url'] ?? null
                );
                if ($push['code'] >= 200 && $push['code'] < 300) {
                    $uid = $evt['external_uid'] ?: ('hh-' . $evt['id'] . '@househub');
                    $pdo->prepare("UPDATE pf_calendar_events SET external_uid=?, sync_state='synced', updated_at=NOW() WHERE id=?")->execute([$uid, $evt['id']]);
                    $pdo->prepare("INSERT INTO pf_calendar_event_links (calendar_event_id, external_uid, calendar_url, external_etag, external_href) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE external_etag=VALUES(external_etag), calendar_url=VALUES(calendar_url), external_href=COALESCE(VALUES(external_href), external_href), updated_at=NOW()")
                        ->execute([$evt['id'], $uid, $integration['calendar_url'], null, $lr['external_href'] ?? null]);
                }
            }
        }

        $pendingDelete = $pdo->query("SELECT id, external_uid FROM pf_calendar_events WHERE deleted_at IS NOT NULL AND sync_state='pending_delete'")->fetchAll();
        foreach ($pendingDelete as $evt) {
            if (!empty($evt['external_uid'])) {
                $linkStmt->execute([$evt['id']]);
                $lr = $linkStmt->fetch() ?: [];
                ios_delete_remote_event(
                    $integration,
                    $evt['external_uid'],
                    $davPassword,
                    $lr['external_href'] ?? null,
                    $lr['calendar_url'] ?? null
                );
            }
            $pdo->prepare("DELETE FROM pf_calendar_event_links WHERE calendar_event_id=?")->execute([$evt['id']]);
            $pdo->prepare("DELETE FROM pf_calendar_events WHERE id=?")->execute([$evt['id']]);
        }

        foreach ($remoteEvents as $remote) {
            $href = $remote['_resource_url'] ?? null;
            $colUrl = $remote['_calendar_collection_url'] ?? $integration['calendar_url'];
            $existing = $pdo->prepare("SELECT id, updated_at FROM pf_calendar_events WHERE external_uid=? LIMIT 1");
            $existing->execute([$remote['external_uid']]);
            $row = $existing->fetch();
            if (!$row) {
                $pdo->prepare("
                    INSERT INTO pf_calendar_events (family_id, created_by_user_id, title, description, location, start_at, end_at, timezone, external_uid, sync_state)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Europe/Paris', ?, 'synced')
                ")->execute([$familyId, $userId, $remote['title'], $remote['description'], $remote['location'], $remote['start_at'], $remote['end_at'], $remote['external_uid']]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO pf_calendar_event_links (calendar_event_id, external_uid, calendar_url, external_etag, external_href) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE calendar_url=VALUES(calendar_url), external_href=COALESCE(VALUES(external_href), external_href), updated_at=NOW()")
                    ->execute([$newId, $remote['external_uid'], $colUrl, null, $href]);
            } elseif ($href) {
                $pdo->prepare('UPDATE pf_calendar_event_links SET external_href = COALESCE(external_href, ?), calendar_url = COALESCE(calendar_url, ?) WHERE calendar_event_id = ?')
                    ->execute([$href, $colUrl, $row['id']]);
            }
        }

        $meta_pdo->prepare("UPDATE user_calendar_integrations SET last_sync_at=NOW(), status='connected' WHERE id=?")->execute([$integration['id']]);
        $n = count($remoteEvents);
        $nc = count($syncUrls);
        ios_ok(['message' => 'Synchronisation terminée. ' . $n . ' événement(s) depuis ' . $nc . ' calendrier(s) distant(s).']);
    } catch (Throwable $e) {
        ios_err('CalDAV: ' . $e->getMessage(), 400);
    }
}

ios_err('Action inconnue', 404);
