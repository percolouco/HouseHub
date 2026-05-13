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
    $defaultCt = ($method === 'REPORT' || $method === 'PROPFIND')
        ? 'application/xml; charset=utf-8'
        : 'text/calendar; charset=utf-8';
    $requestHeaders = array_merge([
        'Content-Type: ' . $defaultCt,
    ], $headers);
    $timeout = ($method === 'REPORT' || $method === 'PROPFIND') ? 60 : 20;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
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

/**
 * Déplie les lignes ICS pliées (RFC 5545).
 */
function ios_ics_unfold(string $ics): string
{
    $ics = str_replace("\r\n", "\n", $ics);
    return preg_replace("/\n[ \t]/", '', $ics);
}

/**
 * @return list<array{external_uid:string,title:string,description:string,location:string,start_at:string,end_at:string}>
 */
function ios_parse_vevents_from_ics(string $ics): array
{
    $ics = ios_ics_unfold($ics);
    $events = [];
    if (!preg_match_all('/BEGIN:VEVENT\s*(.*?)\s*END:VEVENT/s', $ics, $matches)) {
        return [];
    }
    foreach ($matches[1] as $chunk) {
        if (!preg_match('/^UID:([^\r\n]+)/m', $chunk, $uidM)) {
            continue;
        }
        $uid = trim($uidM[1]);
        $summary = '';
        if (preg_match('/^SUMMARY:([^\r\n]*)/m', $chunk, $m)) {
            $summary = str_replace('\\,', ',', str_replace('\\;', ';', trim($m[1])));
        }
        $description = '';
        if (preg_match('/^DESCRIPTION:([^\r\n]*)/m', $chunk, $m)) {
            $description = str_replace('\\,', ',', str_replace('\\;', ';', str_replace('\\n', "\n", trim($m[1]))));
        }
        $location = '';
        if (preg_match('/^LOCATION:([^\r\n]*)/m', $chunk, $m)) {
            $location = str_replace('\\,', ',', str_replace('\\;', ';', trim($m[1])));
        }
        $startRaw = null;
        if (preg_match('/^DTSTART[^:]*:([^\r\n]+)/m', $chunk, $m)) {
            $startRaw = trim($m[1]);
        }
        if ($startRaw === null || $startRaw === '') {
            continue;
        }
        $endRaw = null;
        if (preg_match('/^DTEND[^:]*:([^\r\n]+)/m', $chunk, $m)) {
            $endRaw = trim($m[1]);
        }
        if (preg_match('/^\d{8}$/', $startRaw)) {
            $startTs = strtotime($startRaw . 'T000000 UTC');
        } else {
            $norm = preg_replace('/^(\d{8})T(\d{6})Z?$/', '$1T$2 UTC', $startRaw);
            $startTs = strtotime($norm ?: $startRaw);
            if ($startTs === false) {
                $startTs = strtotime($startRaw);
            }
        }
        if ($startTs === false) {
            continue;
        }
        if ($endRaw !== null && $endRaw !== '') {
            if (preg_match('/^\d{8}$/', $endRaw)) {
                $endTs = strtotime($endRaw . 'T000000 UTC');
            } else {
                $normE = preg_replace('/^(\d{8})T(\d{6})Z?$/', '$1T$2 UTC', $endRaw);
                $endTs = strtotime($normE ?: $endRaw);
                if ($endTs === false) {
                    $endTs = strtotime($endRaw);
                }
            }
        } else {
            $endTs = $startTs;
        }
        if ($endTs === false) {
            $endTs = $startTs;
        }
        $events[] = [
            'external_uid' => $uid,
            'title' => $summary,
            'description' => $description,
            'location' => $location,
            'start_at' => date('Y-m-d H:i:s', $startTs),
            'end_at' => date('Y-m-d H:i:s', $endTs),
        ];
    }
    return $events;
}

/**
 * Associe chaque fragment iCalendar du REPORT à l’URL de ressource CalDAV (nécessaire pour PUT/DELETE sur iCloud).
 *
 * @return list<array{resource_url:string,ical:string}>
 */
function ios_caldav_parse_report_calendar_fragments(string $xmlBody, string $calendarBaseUrl): array
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xmlBody)) {
        return [];
    }
    $xpath = new DOMXPath($dom);
    $responses = $xpath->query("//*[local-name()='response']");
    $out = [];
    foreach ($responses as $resp) {
        $hrefNodes = $xpath->query(".//*[local-name()='href']", $resp);
        if ($hrefNodes->length === 0) {
            continue;
        }
        $href = trim($hrefNodes->item(0)->textContent);
        if ($href === '') {
            continue;
        }
        $cd = $xpath->query(".//*[local-name()='calendar-data']", $resp);
        if ($cd->length === 0) {
            continue;
        }
        $ical = trim($cd->item(0)->textContent);
        if ($ical === '' || stripos($ical, 'BEGIN:VEVENT') === false) {
            continue;
        }
        $resourceUrl = (str_starts_with($href, 'http://') || str_starts_with($href, 'https://'))
            ? $href
            : hh_caldav_resolve_href(rtrim($calendarBaseUrl, '/') . '/', $href);
        $out[] = ['resource_url' => $resourceUrl, 'ical' => $ical];
    }
    return $out;
}

/**
 * URLs des collections calendrier à interroger (tous les calendriers iCloud du compte, sinon l’URL configurée seule).
 *
 * @return list<string>
 */
function ios_sync_calendar_collection_urls(array $integration, string $password): array
{
    $primary = rtrim($integration['calendar_url'] ?? '', '/') . '/';
    if (hh_caldav_url_is_icloud($primary)) {
        try {
            $entries = hh_icloud_discover_calendar_entries($integration['username'], $password);

            return array_values(array_unique(array_map(static function (array $e): string {
                return rtrim($e['url'], '/') . '/';
            }, $entries)));
        } catch (Throwable $e) {
            // compte partiellement lisible : au moins le calendrier principal
        }
    }
    return [$primary];
}

/**
 * @return list<array<string,mixed>> champs événement + _resource_url + _calendar_collection_url
 */
function ios_fetch_remote_events(array $integration, string $password): array
{
    $calUrl = rtrim($integration['calendar_url'], '/') . '/';
    $tStart = gmdate('Ymd\THis\Z', strtotime('-3 years'));
    $tEnd = gmdate('Ymd\THis\Z', strtotime('+4 years'));
    $reportXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav" xmlns:D="DAV:">'
        . '<D:prop><C:calendar-data/></D:prop>'
        . '<C:filter>'
        . '<C:comp-filter name="VCALENDAR">'
        . '<C:comp-filter name="VEVENT">'
        . '<C:time-range start="' . htmlspecialchars($tStart, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" end="' . htmlspecialchars($tEnd, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>'
        . '</C:comp-filter></C:comp-filter></C:filter>'
        . '</C:calendar-query>';

    $res = ios_caldav_request($calUrl, $integration['username'], $password, 'REPORT', $reportXml, [
        'Accept: application/xml, text/xml',
        'Depth: 1',
    ]);
    if (!in_array($res['code'], [200, 207], true)) {
        throw new RuntimeException('Lecture CalDAV (REPORT) impossible (HTTP ' . $res['code'] . ').');
    }
    $fragments = ios_caldav_parse_report_calendar_fragments($res['body'], $calUrl);
    $byUid = [];
    foreach ($fragments as $frag) {
        foreach (ios_parse_vevents_from_ics($frag['ical']) as $ev) {
            $ev['_resource_url'] = $frag['resource_url'];
            $ev['_calendar_collection_url'] = $calUrl;
            $byUid[$ev['external_uid']] = $ev;
        }
    }
    if ($byUid === [] && strpos($res['body'], 'multistatus') === false) {
        $get = ios_caldav_request($calUrl, $integration['username'], $password, 'GET', null, ['Accept: text/calendar']);
        if ($get['code'] >= 200 && $get['code'] < 400 && str_contains($get['body'], 'BEGIN:VEVENT')) {
            foreach (ios_parse_vevents_from_ics($get['body']) as $ev) {
                $ev['_resource_url'] = null;
                $ev['_calendar_collection_url'] = $calUrl;
                $byUid[$ev['external_uid']] = $ev;
            }
        }
    }
    return array_values($byUid);
}

/**
 * Fusionne tous les calendriers du compte (iCloud) ou une seule collection.
 *
 * @return list<array<string,mixed>>
 */
function ios_fetch_remote_events_all_calendars(array $integration, string $password): array
{
    $urls = ios_sync_calendar_collection_urls($integration, $password);
    $merged = [];
    foreach ($urls as $url) {
        $sub = $integration;
        $sub['calendar_url'] = $url;
        foreach (ios_fetch_remote_events($sub, $password) as $ev) {
            $merged[$ev['external_uid']] = $ev;
        }
    }
    return array_values($merged);
}

function ios_push_event_to_remote(array $integration, array $event, string $password, ?string $resourceUrl = null, ?string $collectionUrlOverride = null): array
{
    $uid = $event['external_uid'] ?: ('hh-' . $event['id'] . '@househub');
    $collection = rtrim($collectionUrlOverride ?? $integration['calendar_url'], '/');
    $url = $resourceUrl ?: ($collection . '/' . rawurlencode($uid) . '.ics');
    $ics = ios_make_ics($event);
    return ios_caldav_request($url, $integration['username'], $password, 'PUT', $ics);
}

function ios_delete_remote_event(array $integration, string $externalUid, string $password, ?string $resourceUrl = null, ?string $collectionUrlOverride = null): array
{
    $collection = rtrim($collectionUrlOverride ?? $integration['calendar_url'], '/');
    $url = $resourceUrl ?: ($collection . '/' . rawurlencode($externalUid) . '.ics');
    return ios_caldav_request($url, $integration['username'], $password, 'DELETE', null, ['Accept: */*']);
}
