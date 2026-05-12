<?php
/**
 * Cron: Todo reminder notifications via Discord webhook
 * Runs every minute — sends Discord alert when due_date+due_time is reached.
 * Usage: php /opt/container/househub/cron/todo_notify.php
 */

$DB_HOST = getenv('DB_HOST') ?: 'househub-db';
$DB_USER = getenv('DB_USER') ?: 'househub';
$DB_PASS = getenv('DB_PASS') ?: 'changeme';
$DB_ROOT = getenv('DB_ROOT_PASS') ?: 'rootchangeme';

// Load .env if running from CLI (not inside container)
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if (!getenv($k)) putenv("$k=$v");
        }
    }
    $DB_HOST = getenv('DB_HOST') ?: $DB_HOST;
    $DB_USER = getenv('DB_USER') ?: $DB_USER;
    $DB_PASS = getenv('DB_PASS') ?: $DB_PASS;
    $DB_ROOT = getenv('DB_ROOT_PASS') ?: $DB_ROOT;
}

// Get list of families from meta DB
try {
    $meta = new PDO(
        "mysql:host=$DB_HOST;dbname=househub_meta;charset=utf8mb4",
        'root', $DB_ROOT,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $families = $meta->query("SELECT id FROM families")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    exit("Cannot connect to meta DB: " . $e->getMessage() . "\n");
}

foreach ($families as $family_id) {
    $db_name = 'househub_f' . $family_id;

    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$db_name;charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        continue;
    }

    // Skip if todo tables don't exist yet
    if (!$pdo->query("SHOW TABLES LIKE 'pf_todos'")->fetchColumn()) continue;

    // Get Discord webhook for this family
    $ws = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type='todo_settings' AND reference_id='webhook_discord'");
    $ws->execute();
    $webhook = $ws->fetchColumn();
    if (!$webhook) continue;

    // Tasks due now: due today, due_time reached, not done, not yet notified
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.due_date, t.due_time,
               l.name AS list_name, l.icon AS list_icon
        FROM pf_todos t
        LEFT JOIN pf_todo_lists l ON l.id = t.list_id
        WHERE t.due_date = CURDATE()
          AND t.due_time IS NOT NULL
          AND t.due_time <= CURTIME()
          AND t.done = 0
          AND t.notified = 0
    ");
    $stmt->execute();

    foreach ($stmt->fetchAll() as $t) {
        $time = substr($t['due_time'], 0, 5);
        $list = $t['list_name'] ? " _({$t['list_icon']} {$t['list_name']})_" : '';
        $msg  = "⏰ **Rappel** : {$t['title']}{$list} — prévu à {$time}";

        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['username' => 'HouseHub Todo', 'content' => $msg]),
        ]);
        curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300;
        curl_close($ch);

        if ($ok) {
            $pdo->prepare("UPDATE pf_todos SET notified=1 WHERE id=?")->execute([$t['id']]);
        }
    }
}
