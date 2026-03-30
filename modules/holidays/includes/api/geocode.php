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

// Borner la limite
$limit = (int)($_GET['limit'] ?? 1);
if ($limit < 1) $limit = 1;
if ($limit > 10) $limit = 10; 

// 2. Normalisation pour le cache
$qNorm = mb_strtolower($q);
$qHash = hash('sha256', $qNorm);

// 3. Création automatique de la table cache
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

// 4. Vérification du cache
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

// 5. Appel Nominatim 
$endpoint = 'https://nominatim.openstreetmap.org/search';
$params = http_build_query([
    'format'         => 'jsonv2',
    'addressdetails' => 1,
    'limit'          => $limit,
    'q'              => $q,
], '', '&', PHP_QUERY_RFC3986);

$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'User-Agent: PachaFamily-Holidays/1.0 (+contact: ferlan.alexandre@gmail.com)'
    ],
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $http !== 200) {
    http_response_code(502); 
    echo json_encode([
        'error' => 'geocode_failed', 
        'details' => $err ?: ('HTTP ' . $http)
    ]);
    exit;
}

$data = json_decode($body, true);

if (!is_array($data) || empty($data)) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

// 6. Mise en cache
if (isset($data[0])) {
    $r = $data[0];
    $lat = round((float)$r['lat'], 6);
    $lng = round((float)$r['lon'], 6);
    $display = $r['display_name'] ?? '';

    try {
        $st = $pdo->prepare("
            INSERT INTO pf_geocode_cache (q_hash, q, lat, lng, display_name, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), display_name=VALUES(display_name), updated_at=NOW()
        ");
        $st->execute([$qHash, $q, $lat, $lng, $display]);
    } catch (Throwable $e) {}
}

// 7. Retour
if ($limit === 1) {
    $r = $data[0];
    echo json_encode([
        'lat' => round((float)$r['lat'], 6), 
        'lng' => round((float)$r['lon'], 6), 
        'display_name' => $r['display_name'] ?? ''
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    $results = array_map(function ($r) {
        return [
            'lat'          => round((float)$r['lat'], 6),
            'lng'          => round((float)$r['lon'], 6),
            'display_name' => (string)($r['display_name'] ?? ''),
        ];
    }, $data);

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}