<?php

/**
 * Normalise le mot de passe d’application Apple (supprime les espaces, garde les tirets).
 */
function hh_normalize_apple_app_password(string $password): string
{
    return preg_replace('/\s+/', '', trim($password));
}

function hh_caldav_url_is_icloud(string $url): bool
{
    $h = strtolower((string) (parse_url(trim($url), PHP_URL_HOST) ?: ''));
    return str_contains($h, 'icloud.com');
}

function hh_caldav_is_icloud_well_known_root(string $url): bool
{
    $parts = parse_url(rtrim(trim($url), '/'));
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    if ($host !== 'caldav.icloud.com') {
        return false;
    }
    $path = $parts['path'] ?? '';
    return $path === '' || $path === '/';
}

function hh_caldav_resolve_href(string $againstUrl, string $href): string
{
    $href = trim($href);
    if ($href === '') {
        return $againstUrl;
    }
    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
        return $href;
    }
    $parts = parse_url($againstUrl);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    if ($href[0] === '/') {
        return $scheme . '://' . $host . $port . $href;
    }
    $base = preg_replace('#/[^/]*$#', '/', $againstUrl);
    return rtrim($base, '/') . '/' . ltrim($href, '/');
}

/**
 * @return array{code:int, body:string}
 */
function hh_caldav_propfind(string $url, string $username, string $password, string $xml, string $depth = '0'): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/xml; charset=utf-8',
            'Depth: ' . $depth,
        ],
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['code' => 0, 'body' => ''];
    }
    return ['code' => $code, 'body' => $body];
}

function hh_caldav_first_href_by_element(string $xml, string $parentLocalName): ?string
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return null;
    }
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[local-name()='{$parentLocalName}']//*[local-name()='href']");
    if ($nodes->length === 0) {
        return null;
    }
    return trim($nodes->item(0)->textContent);
}

/**
 * @return list<array{href:string, display:string}>
 */
function hh_caldav_parse_calendar_collections(string $xml): array
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
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
        $cal = $xpath->query(".//*[local-name()='resourcetype']/*[local-name()='calendar']", $resp);
        if ($cal->length === 0 || $href === '') {
            continue;
        }
        $display = '';
        $dn = $xpath->query(".//*[local-name()='displayname']", $resp);
        if ($dn->length > 0) {
            $display = trim($dn->item(0)->textContent);
        }
        $out[] = ['href' => $href, 'display' => $display];
    }
    return $out;
}

/**
 * Liste les collections calendrier iCloud (URL complète avec / final + nom affiché).
 *
 * @return list<array{display:string, url:string}>
 *
 * @throws RuntimeException
 */
function hh_icloud_discover_calendar_entries(string $username, string $password): array
{
    $password = hh_normalize_apple_app_password($password);
    $root = 'https://caldav.icloud.com/';

    $xml1 = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<propfind xmlns="DAV:">'
        . '<prop><current-user-principal/></prop>'
        . '</propfind>';
    $r1 = hh_caldav_propfind($root, $username, $password, $xml1, '0');
    if ($r1['code'] === 401 || $r1['code'] === 403) {
        throw new RuntimeException('Identifiant Apple ou mot de passe d’app refusé (HTTP ' . $r1['code'] . ').');
    }
    if (!in_array($r1['code'], [200, 207], true)) {
        throw new RuntimeException('CalDAV racine inaccessible (HTTP ' . $r1['code'] . ').');
    }
    $principalHref = hh_caldav_first_href_by_element($r1['body'], 'current-user-principal');
    if (!$principalHref) {
        throw new RuntimeException('Réponse CalDAV inattendue : principal introuvable.');
    }
    $principalUrl = hh_caldav_resolve_href($root, $principalHref);

    $xml2 = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<propfind xmlns="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">'
        . '<prop><C:calendar-home-set/></prop>'
        . '</propfind>';
    $r2 = hh_caldav_propfind($principalUrl, $username, $password, $xml2, '0');
    if (!in_array($r2['code'], [200, 207], true)) {
        throw new RuntimeException('Lecture du principal CalDAV impossible (HTTP ' . $r2['code'] . ').');
    }
    $homeHref = hh_caldav_first_href_by_element($r2['body'], 'calendar-home-set');
    if (!$homeHref) {
        throw new RuntimeException('Aucun dossier de calendriers (calendar-home-set) trouvé.');
    }
    $homeUrl = hh_caldav_resolve_href($principalUrl, $homeHref);

    $xml3 = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<propfind xmlns="DAV:">'
        . '<prop><resourcetype/><displayname/></prop>'
        . '</propfind>';
    $r3 = hh_caldav_propfind($homeUrl, $username, $password, $xml3, '1');
    if (!in_array($r3['code'], [200, 207], true)) {
        throw new RuntimeException('Liste des calendriers impossible (HTTP ' . $r3['code'] . ').');
    }
    $raw = hh_caldav_parse_calendar_collections($r3['body']);
    if ($raw === []) {
        throw new RuntimeException('Aucun calendrier trouvé sur ce compte iCloud.');
    }

    $out = [];
    foreach ($raw as $e) {
        $url = rtrim(hh_caldav_resolve_href($homeUrl, $e['href']), '/') . '/';
        $out[] = ['display' => $e['display'], 'url' => $url];
    }
    return $out;
}

/**
 * Depuis la racine iCloud (https://caldav.icloud.com), découvre l’URL d’un calendrier (par défaut « Calendar » / « Calendrier », sinon le premier).
 *
 * @throws RuntimeException
 */
function hh_icloud_discover_default_calendar_url(string $username, string $password): string
{
    $entries = hh_icloud_discover_calendar_entries($username, $password);
    $preferred = ['calendar', 'calendrier', 'home', 'par défaut', 'default'];
    foreach ($entries as $e) {
        $lower = mb_strtolower($e['display']);
        foreach ($preferred as $p) {
            if ($lower !== '' && str_contains($lower, $p)) {
                return $e['url'];
            }
        }
    }
    return $entries[0]['url'];
}

/**
 * Vérifie qu’une URL de collection calendrier répond en CalDAV (PROPFIND).
 */
function hh_caldav_test_calendar_collection(string $username, string $password, string $calendarUrl): int
{
    $password = hh_normalize_apple_app_password($password);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<propfind xmlns="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">'
        . '<prop><resourcetype/><displayname/><C:supported-calendar-component-set/></prop>'
        . '</propfind>';
    $r = hh_caldav_propfind(rtrim($calendarUrl, '/') . '/', $username, $password, $xml, '0');
    return $r['code'];
}

/**
 * Si URL = racine iCloud bien connue, découvre et retourne l’URL d’un calendrier ; sinon retourne l’URL telle quelle (trim).
 *
 * @throws RuntimeException
 */
function hh_icloud_resolve_calendar_url_if_needed(string $username, string $password, string $calendarUrl): string
{
    $calendarUrl = trim($calendarUrl);
    if (hh_caldav_is_icloud_well_known_root($calendarUrl)) {
        return hh_icloud_discover_default_calendar_url($username, $password);
    }
    return $calendarUrl;
}
