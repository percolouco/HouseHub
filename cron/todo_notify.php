<?php
/**
 * Cron: Todo daily reminder notifications via Discord webhook
 * Runs every minute — sends Discord reminder when due_time is reached.
 * Repeats every day at the same time (resets at midnight via notified_date).
 * Usage: php /opt/container/househub/cron/todo_notify.php
 */

date_default_timezone_set('Europe/Paris');

$DB_HOST = getenv('DB_HOST') ?: 'househub-db';
$DB_USER = getenv('DB_USER') ?: 'househub';
$DB_PASS = getenv('DB_PASS') ?: 'changeme';
$DB_ROOT = getenv('DB_ROOT_PASS') ?: 'rootchangeme';

// Load .env if running from CLI outside container
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

    if (!$pdo->query("SHOW TABLES LIKE 'pf_todos'")->fetchColumn()) continue;

    $ws = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type='todo_settings' AND reference_id='webhook_discord'");
    $ws->execute();
    $webhook = $ws->fetchColumn();
    if (!$webhook) continue;

    // Daily reminder: exact HH:MM match (same logic as the original todo app)
    $now = date('H:i');
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.notes, t.due_time, t.priority,
               l.name AS list_name, l.icon AS list_icon, l.color AS list_color
        FROM pf_todos t
        LEFT JOIN pf_todo_lists l ON l.id = t.list_id
        WHERE t.due_time IS NOT NULL
          AND TIME_FORMAT(t.due_time, '%H:%i') = ?
          AND t.done = 0
    ");
    $stmt->execute([$now]);

    $priColors = ['high' => 0xef4444, 'medium' => 0xf59e0b, 'low' => 0x22c55e, 'none' => 0x3b82f6];
    $priLabels = ['high' => '🔴 Haute', 'medium' => '🟡 Moyenne', 'low' => '🟢 Basse', 'none' => null];

    foreach ($stmt->fetchAll() as $t) {
        $time     = substr($t['due_time'], 0, 5);
        $priority = $t['priority'] ?? 'none';
        $color    = $priColors[$priority] ?? 0x3b82f6;

        $fields = [];
        if ($t['list_name']) {
            $fields[] = ['name' => 'Liste', 'value' => $t['list_icon'] . ' ' . $t['list_name'], 'inline' => true];
        }
        if ($priLabels[$priority]) {
            $fields[] = ['name' => 'Priorité', 'value' => $priLabels[$priority], 'inline' => true];
        }
        $fields[] = ['name' => 'Heure', 'value' => '⏰ ' . $time, 'inline' => true];

        $embed = [
            'title'       => '🔔 Rappel : ' . $t['title'],
            'color'       => $color,
            'fields'      => $fields,
            'footer'      => ['text' => 'HouseHub Todo'],
        ];
        if (!empty($t['notes'])) {
            $embed['description'] = $t['notes'];
        }

        $payload = ['username' => 'HouseHub Todo', 'embeds' => [$embed]];

        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
