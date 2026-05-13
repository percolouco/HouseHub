<?php
require_once dirname(__DIR__, 2) . '/includes/crypto.php';
require_once dirname(__DIR__, 2) . '/includes/icloud_caldav.php';

/**
 * Mot de passe normalisé + URL calendrier (découverte si racine iCloud).
 *
 * @return array{integration: array, password: string}
 */
function ios_caldav_prepare(array $integration, ?PDO $metaPdo = null): array
{
    $password = hh_normalize_apple_app_password(hh_decrypt_secret($integration['secret_encrypted']));
    $url = trim($integration['calendar_url'] ?? '');
    $resolvedUrl = hh_icloud_resolve_calendar_url_if_needed($integration['username'], $password, $url);
    $out = $integration;
    $out['calendar_url'] = $resolvedUrl;
    if ($metaPdo && !empty($integration['id']) && rtrim($url, '/') !== rtrim($resolvedUrl, '/')) {
        $metaPdo->prepare('UPDATE user_calendar_integrations SET calendar_url = ? WHERE id = ?')
            ->execute([$resolvedUrl, $integration['id']]);
    }
    return ['integration' => $out, 'password' => $password];
}

function ios_make_ics(array $event): string
{
    $uid = $event['external_uid'] ?: ('hh-' . $event['id'] . '@househub');
    $start = gmdate('Ymd\THis\Z', strtotime($event['start_at']));
    $end = gmdate('Ymd\THis\Z', strtotime($event['end_at']));
    $summary = addcslashes($event['title'] ?? '', ",;");
    $description = addcslashes($event['description'] ?? '', ",;");
    $location = addcslashes($event['location'] ?? '', ",;");
    $updated = gmdate('Ymd\THis\Z');

    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HouseHub//Calendar iOS//FR\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:$updated\r\nDTSTART:$start\r\nDTEND:$end\r\nSUMMARY:$summary\r\nDESCRIPTION:$description\r\nLOCATION:$location\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

function ios_caldav_request(string $url, string $username, string $password, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $requestHeaders = array_merge([
        'Content-Type: text/calendar; charset=utf-8',
    ], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'headers' => '', 'body' => ''];
    }
    return [
        'code' => $code,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function ios_fetch_remote_events(array $integration, string $password): array
{
    $res = ios_caldav_request($integration['calendar_url'], $integration['username'], $password, 'GET', null, ['Accept: text/calendar']);
    if ($res['code'] < 200 || $res['code'] >= 400) {
        throw new RuntimeException('Lecture CalDAV impossible (HTTP ' . $res['code'] . ')');
    }
    $blocks = preg_split('/BEGIN:VEVENT|END:VEVENT/', $res['body']);
    $events = [];
    foreach ($blocks as $chunk) {
        if (strpos($chunk, 'UID:') === false) {
            continue;
        }
        preg_match('/UID:(.+)\R/', $chunk, $uidM);
        preg_match('/SUMMARY:(.+)\R/', $chunk, $sumM);
        preg_match('/DESCRIPTION:(.+)\R/', $chunk, $descM);
        preg_match('/LOCATION:(.+)\R/', $chunk, $locM);
        preg_match('/DTSTART(?:;VALUE=DATE)?:(.+)\R/', $chunk, $startM);
        preg_match('/DTEND(?:;VALUE=DATE)?:(.+)\R/', $chunk, $endM);
        if (empty($uidM[1]) || empty($startM[1])) {
            continue;
        }
        $start = date('Y-m-d H:i:s', strtotime(trim($startM[1])));
        $end = !empty($endM[1]) ? date('Y-m-d H:i:s', strtotime(trim($endM[1]))) : $start;
        $events[] = [
            'external_uid' => trim($uidM[1]),
            'title' => trim($sumM[1] ?? ''),
            'description' => trim($descM[1] ?? ''),
            'location' => trim($locM[1] ?? ''),
            'start_at' => $start,
            'end_at' => $end,
        ];
    }
    return $events;
}

function ios_push_event_to_remote(array $integration, array $event, string $password): array
{
    $uid = $event['external_uid'] ?: ('hh-' . $event['id'] . '@househub');
    $url = rtrim($integration['calendar_url'], '/') . '/' . rawurlencode($uid) . '.ics';
    $ics = ios_make_ics($event);
    return ios_caldav_request($url, $integration['username'], $password, 'PUT', $ics);
}

function ios_delete_remote_event(array $integration, string $externalUid, string $password): array
{
    $url = rtrim($integration['calendar_url'], '/') . '/' . rawurlencode($externalUid) . '.ics';
    return ios_caldav_request($url, $integration['username'], $password, 'DELETE', null, ['Accept: */*']);
}
