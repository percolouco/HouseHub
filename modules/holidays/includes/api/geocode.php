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

// 3. Vérification du cache
try {
    $st = $pdo->prepare("SELECT lat, lng, display_name FROM pf_geocode_cache WHERE q_hash = ?");
    $st->execute([$qHash]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if ($limit === 1) {
            echo json_encode([
                'lat'          => (float)$row['lat'],
                'lng'          => (float)$row['lng'],
                'display_name' => $row['display_name'],
                'cached'       => true
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['results' => [[
                'lat'          => (float)$row['lat'],
                'lng'          => (float)$row['lng'],
                'display_name' => $row['display_name'],
                'cached'       => true
            ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
} catch (Throwable $e) {}

// 4. NOUVELLE API : Appel à Photon (Komoot) - Beaucoup plus tolérant pour les adresses !
$endpoint = 'https://photon.komoot.io/api/';
$params = http_build_query([
    'q'     => $q,
    'limit' => $limit,
    'lang'  => 'fr' // On force les résultats en français
], '', '&', PHP_QUERY_RFC3986);

$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'PachaFamily-Holidays/1.0',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $http !== 200) {
    http_response_code(502); 
    echo json_encode(['error' => 'geocode_failed']);
    exit;
}

$data = json_decode($body, true);

if (!isset($data['features']) || empty($data['features'])) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

// 5. Formatage des résultats (Photon renvoie les données différemment de Nominatim)
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
    
    $displayName = implode(', ', array_unique($nameParts));
    
    $results[] = [
        'lat' => round((float)$coords[1], 6),
        'lng' => round((float)$coords[0], 6),
        'display_name' => $displayName
    ];
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