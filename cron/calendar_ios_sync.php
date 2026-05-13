<?php
require_once dirname(__DIR__) . '/includes/meta_db.php';

$rows = $meta_pdo->query("SELECT user_id FROM user_calendar_integrations WHERE provider='icloud_caldav' AND status='connected'")->fetchAll();
foreach ($rows as $row) {
    $userId = (int)$row['user_id'];
    $url = 'http://localhost/modules/calendar-ios/api.php?action=sync';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'X-Requested-With: XMLHttpRequest',
            // Le cron réel devra utiliser un mécanisme d'auth/session technique.
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
