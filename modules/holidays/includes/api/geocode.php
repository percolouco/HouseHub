<?php
// modules/holidays/includes/api/geocode.php

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

// 1. Validation de l'entrée
$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_q']);
    exit;
}

$limit = (int)($_GET['limit'] ?? 1);
if ($limit < 1) $limit = 1;
if ($limit > 10) $limit = 10; 

// 2. Normalisation pour le cache
$qNorm = mb_strtolower($q);
$qHash = hash('sha256', $qNorm);

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pf_geocode_cache (
            q_hash CHAR(64) PRIMARY KEY,
            q VARCHAR(255),
            lat DECIMAL(10, 7),
            lng DECIMAL(10, 7),
            display_name TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {}

// 3. Vérification du cache (uniquement quand on ne veut qu'un résultat :
//    la recherche à choix multiples doit toujours proposer plusieurs lieux)
if ($limit === 1) {
    try {
        $st = $pdo->prepare("SELECT lat, lng, display_name FROM pf_geocode_cache WHERE q_hash = ?");
        $st->execute([$qHash]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                'lat'          => (float)$row['lat'],
                'lng'          => (float)$row['lng'],
                'display_name' => $row['display_name'],
                'cached'       => true
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    } catch (Throwable $e) {}
}

function hol_geocode_get(string $url, int $timeout): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'HouseHub-Holidays/1.0',
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http !== 200) return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

// 4. Service principal : Photon (Komoot), tolérant sur les adresses
//    S'il vient de tomber en panne, on l'évite pendant 10 minutes pour ne pas ralentir chaque recherche.
$results = null;
$photonFlag = sys_get_temp_dir() . '/hh_photon_down';
$photonDown = is_file($photonFlag) && (time() - filemtime($photonFlag)) < 600;
$data = $photonDown ? null : hol_geocode_get('https://photon.komoot.io/api/?' . http_build_query(['q' => $q, 'limit' => $limit, 'lang' => 'fr'], '', '&', PHP_QUERY_RFC3986), 4);
if ($data === null && !$photonDown) @touch($photonFlag);
if ($data !== null && isset($data['features'])) {
    $results = [];
    foreach ($data['features'] as $feature) {
        $prop = $feature['properties'];
        $coords = $feature['geometry']['coordinates']; // [lng, lat]

        // Construction propre du nom (Nom du lieu + Rue + Ville + Pays)
        $nameParts = [];
        if (!empty($prop['name'])) $nameParts[] = $prop['name'];

        if (!empty($prop['housenumber']) && !empty($prop['street'])) {
            $nameParts[] = $prop['housenumber'] . ' ' . $prop['street'];
        } elseif (!empty($prop['street'])) {
            $nameParts[] = $prop['street'];
        }

        if (!empty($prop['city'])) $nameParts[] = $prop['city'];
        elseif (!empty($prop['town'])) $nameParts[] = $prop['town'];
        elseif (!empty($prop['village'])) $nameParts[] = $prop['village'];

        if (!empty($prop['country'])) $nameParts[] = $prop['country'];

        $results[] = [
            'lat' => round((float)$coords[1], 6),
            'lng' => round((float)$coords[0], 6),
            'display_name' => implode(', ', array_unique($nameParts))
        ];
    }
}

// 5. Secours : Nominatim (OpenStreetMap) si Photon est injoignable
if ($results === null) {
    $nominatimUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query(['q' => $q, 'format' => 'jsonv2', 'limit' => $limit, 'accept-language' => 'fr'], '', '&', PHP_QUERY_RFC3986);
    $data = hol_geocode_get($nominatimUrl, 8);
    if ($data === null) {
        // Nominatim limite à une requête par seconde : on patiente puis on retente une fois
        usleep(1500000);
        $data = hol_geocode_get($nominatimUrl, 8);
    }
    if ($data !== null) {
        $results = [];
        foreach ($data as $row) {
            if (!isset($row['lat'], $row['lon'])) continue;
            $results[] = [
                'lat' => round((float)$row['lat'], 6),
                'lng' => round((float)$row['lon'], 6),
                'display_name' => (string)($row['display_name'] ?? $q)
            ];
        }
    }
}

if ($results === null) {
    http_response_code(502);
    echo json_encode(['error' => 'geocode_failed']);
    exit;
}
if (empty($results)) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

// 6. Mise en cache du premier résultat
if (isset($results[0])) {
    try {
        $st = $pdo->prepare("
            INSERT INTO pf_geocode_cache (q_hash, q, lat, lng, display_name, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), display_name=VALUES(display_name), updated_at=NOW()
        ");
        $st->execute([$qHash, $q, $results[0]['lat'], $results[0]['lng'], $results[0]['display_name']]);
    } catch (Throwable $e) {}
}

// 7. Retour JSON pour ton Javascript
if ($limit === 1) {
    echo json_encode($results[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}