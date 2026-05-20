<?php
// modules/voyage/api.php
// Toll cost estimator API for HouseHub

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action !== 'estimate') {
    echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
    exit;
}

// --- Read POST body ---
$body = file_get_contents('php://input');
$params = json_decode($body, true);
if (!$params || empty($params['stops']) || count($params['stops']) < 2) {
    echo json_encode(['ok' => false, 'error' => 'Paramètres invalides (stops requis, min 2)']);
    exit;
}

$stops    = $params['stops'];
$fuelL100 = (float)($params['fuel_l100'] ?? 7);
$fuelPrice= (float)($params['fuel_price'] ?? 1.85);

// --- Load databases ---
$tollsPath  = __DIR__ . '/data/tolls.json';
$gpsPath    = __DIR__ . '/data/toll_gps.json';

if (!file_exists($tollsPath)) {
    echo json_encode(['ok' => false, 'error' => 'Base de péages introuvable']);
    exit;
}

$tollsRaw = json_decode(file_get_contents($tollsPath), true);
$tollData = $tollsRaw['data'] ?? [];

$gpsData = [];
if (file_exists($gpsPath)) {
    $gpsData = json_decode(file_get_contents($gpsPath), true) ?? [];
}

// --- Build lookup index: [op][entry][exit] => c1_price ---
$tollIndex = [];
foreach ($tollData as $row) {
    $op = $row['op'];
    $e  = $row['e'];
    $x  = $row['x'];
    $c1 = (float)$row['c1'];
    $tollIndex[$op][$e][$x] = $c1;
}

// --- Helper: great-circle distance (km) ---
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R  = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// --- Find plazas near a coordinate (within $maxKm) ---
// Returns array of [plaza_name => distance_km]
function plazasNear(array $gpsData, float $lat, float $lng, float $maxKm): array {
    $result = [];
    foreach ($gpsData as $name => $info) {
        $d = haversine($lat, $lng, $info['lat'], $info['lng']);
        if ($d <= $maxKm) {
            $result[$name] = $d;
        }
    }
    asort($result);
    return $result;
}

// --- Look up toll price for (op, entry, exit) with fallback ---
function lookupToll(array $tollIndex, string $op, string $entry, string $exit): ?float {
    // Direct lookup
    if (isset($tollIndex[$op][$entry][$exit])) {
        return $tollIndex[$op][$entry][$exit];
    }
    // Reversed
    if (isset($tollIndex[$op][$exit][$entry])) {
        return $tollIndex[$op][$exit][$entry];
    }
    return null;
}

// --- OSRM route distance ---
function getRouteDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float {
    $url = sprintf(
        'https://router.project-osrm.org/route/v1/driving/%f,%f;%f,%f?overview=false',
        $lng1, $lat1, $lng2, $lat2
    );
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => "User-Agent: HouseHub/1.0\r\n"
        ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!empty($data['routes'][0]['distance'])) {
        return (float)$data['routes'][0]['distance'] / 1000.0;
    }
    return null;
}

// --- Main: process each consecutive stop pair ---
$segments   = [];
$totalKm    = 0.0;
$totalToll  = 0.0;

for ($i = 0; $i < count($stops) - 1; $i++) {
    $orig = $stops[$i];
    $dest = $stops[$i + 1];

    $oLat = (float)$orig['lat'];
    $oLng = (float)$orig['lng'];
    $dLat = (float)$dest['lat'];
    $dLng = (float)$dest['lng'];
    $fromName = $orig['name'] ?? "Étape " . ($i + 1);
    $toName   = $dest['name'] ?? "Étape " . ($i + 2);

    // Distance via OSRM
    $distKm = getRouteDistanceKm($oLat, $oLng, $dLat, $dLng);
    if ($distKm === null) {
        // Fallback: straight-line distance * 1.25
        $distKm = haversine($oLat, $oLng, $dLat, $dLng) * 1.25;
    }
    $totalKm += $distKm;

    // --- Find toll ---
    $segToll      = 0.0;
    $entryPlaza   = null;
    $exitPlaza    = null;
    $tollNote     = null;

    if (!empty($gpsData)) {
        // Find plazas near origin (80km) and destination (80km)
        $nearOrig = plazasNear($gpsData, $oLat, $oLng, 80.0);
        $nearDest = plazasNear($gpsData, $dLat, $dLng, 80.0);

        $bestToll    = null;
        $bestDistSum = PHP_FLOAT_MAX;
        $bestEntry   = null;
        $bestExit    = null;
        $bestOp      = null;

        // Try each operator
        foreach ($tollIndex as $op => $opEntries) {
            // Find candidate entry plazas (near origin, present in this operator)
            $candidateEntries = [];
            foreach ($nearOrig as $pName => $dist) {
                if (isset($opEntries[$pName]) || isset(array_flip(array_keys($opEntries))[$pName])) {
                    // Plaza exists as entry in this operator
                    if (isset($opEntries[$pName])) {
                        $candidateEntries[$pName] = $dist;
                    }
                }
                // Also check if it's an exit plaza (reversed lookup later)
                // Include any plaza from this operator that is within range
            }

            // Build set of all plazas for this operator
            $opPlazas = [];
            foreach ($opEntries as $entry => $exits) {
                $opPlazas[$entry] = true;
                foreach ($exits as $exit => $price) {
                    $opPlazas[$exit] = true;
                }
            }

            // Filter nearOrig and nearDest to only plazas in this operator
            $opNearOrig = array_intersect_key($nearOrig, $opPlazas);
            $opNearDest = array_intersect_key($nearDest, $opPlazas);

            if (empty($opNearOrig) || empty($opNearDest)) continue;

            // Try best 5 entry and best 5 exit candidates
            $topEntries = array_slice($opNearOrig, 0, 5, true);
            $topExits   = array_slice($opNearDest, 0, 5, true);

            foreach ($topEntries as $ePlaza => $eDist) {
                foreach ($topExits as $xPlaza => $xDist) {
                    if ($ePlaza === $xPlaza) continue;
                    $price = lookupToll($tollIndex, $op, $ePlaza, $xPlaza);
                    if ($price !== null) {
                        $distSum = $eDist + $xDist;
                        if ($distSum < $bestDistSum) {
                            $bestDistSum = $distSum;
                            $bestToll    = $price;
                            $bestEntry   = $ePlaza;
                            $bestExit    = $xPlaza;
                            $bestOp      = $op;
                        }
                    }
                }
            }
        }

        if ($bestToll !== null) {
            $segToll    = $bestToll;
            $entryPlaza = $bestEntry;
            $exitPlaza  = $bestExit;
        } else {
            $tollNote = 'péages non estimés pour ce trajet';
        }
    } else {
        $tollNote = 'base GPS des péages non disponible';
    }

    $totalToll += $segToll;

    $seg = [
        'from'        => $fromName,
        'to'          => $toName,
        'distance_km' => round($distKm, 1),
        'toll'        => round($segToll, 2),
    ];
    if ($entryPlaza) $seg['entry_plaza'] = $entryPlaza;
    if ($exitPlaza)  $seg['exit_plaza']  = $exitPlaza;
    if ($tollNote)   $seg['note']        = $tollNote;

    $segments[] = $seg;
}

$fuelCost   = round(($totalKm / 100.0) * $fuelL100 * $fuelPrice, 2);
$grandTotal = round($totalToll + $fuelCost, 2);

echo json_encode([
    'ok'          => true,
    'segments'    => $segments,
    'total_km'    => round($totalKm, 1),
    'total_toll'  => round($totalToll, 2),
    'fuel_cost'   => $fuelCost,
    'grand_total' => $grandTotal,
], JSON_UNESCAPED_UNICODE);
