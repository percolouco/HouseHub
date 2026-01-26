<?php
// modules/holidays/geocode.php
require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  http_response_code(400);
  echo json_encode(['error' => 'missing q']);
  exit;
}

// borne la limite entre 1 et 5 (usage perso)
$limit = (int)($_GET['limit'] ?? 1);
if ($limit < 1) $limit = 1;
if ($limit > 5) $limit = 5;

// Cache local (silencieux si table absente)
$qNorm = mb_strtolower($q);
$qHash = hash('sha256', $qNorm);
try {
  if ($limit === 1) {
    $st = $pdo->prepare("SELECT lat, lng, display_name FROM pf_geocode_cache WHERE q_hash = ?");
    $st->execute([$qHash]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      echo json_encode([
        'lat' => (float)$row['lat'],
        'lng' => (float)$row['lng'],
        'display_name' => $row['display_name'],
        'cached' => true
      ]);
      exit;
    }
  }
} catch (Throwable $e) {
  // pas bloquant
}

// Appel Nominatim (respect des règles d'usage)
$endpoint = 'https://nominatim.openstreetmap.org/search';
$params = http_build_query([
  'format'          => 'jsonv2',
  'addressdetails'  => 1,
  'limit'           => $limit,
  'q'               => $q,
], '', '&', PHP_QUERY_RFC3986);

$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 5,
  CURLOPT_TIMEOUT        => 10,
  CURLOPT_HTTPHEADER     => [
    // Contact fourni: ferlan.alexandre@gmail.com
    'User-Agent: PachaFamily-Holidays/1.0 (+contact: ferlan.alexandre@gmail.com)'
  ],
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $http !== 200) {
  http_response_code(502);
  echo json_encode(['error' => 'geocode_failed', 'details' => $err ?: ('HTTP '.$http)]);
  exit;
}

$data = json_decode($body, true);
if (!is_array($data) || empty($data)) {
  http_response_code(404);
  echo json_encode(['error' => 'not_found']);
  exit;
}

if ($limit === 1) {
  $r   = $data[0];
  $lat = round((float)$r['lat'], 6);
  $lng = round((float)$r['lon'], 6);
  $display = $r['display_name'] ?? null;

  try {
    $st = $pdo->prepare("REPLACE INTO pf_geocode_cache (q_hash, q, lat, lng, display_name) VALUES (?, ?, ?, ?, ?)");
    $st->execute([$qHash, $q, $lat, $lng, $display]);
  } catch (Throwable $e) {}

  echo json_encode(['lat' => $lat, 'lng' => $lng, 'display_name' => $display]);
  exit;
}

// Multi-résultats
$results = array_map(function ($r) {
  return [
    'lat'          => round((float)$r['lat'], 6),
    'lng'          => round((float)$r['lon'], 6),
    'display_name' => (string)($r['display_name'] ?? ''),
  ];
}, $data);

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
