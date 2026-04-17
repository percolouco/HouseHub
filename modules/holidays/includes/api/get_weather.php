<?php
// modules/holidays/includes/api/get_weather.php
ini_set('display_errors', 0);
error_reporting(0); 

$basePath = '../../../../';
require_once $basePath . 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $basePath . 'includes/auth.php';

header('Content-Type: application/json');

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$date = $_GET['date'] ?? null;

if (!$lat || !$lng || !$date) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']); 
    exit;
}

$date = substr($date, 0, 10);
$today = new DateTime();
$targetDate = new DateTime($date);
$interval = $today->diff($targetDate);
$daysDiff = (int)$interval->format('%R%a');

// --- 🧠 GESTION INTELLIGENTE DU TEMPS (Prévisions vs Historique) ---
if ($daysDiff > 16) {
    // 1. VOYAGE LOINTAIN : Calcul de la moyenne sur 3 ans
    $currentYear = (int)$today->format('Y');
    $monthDay = $targetDate->format('m-d');
    if ($monthDay === '02-29') $monthDay = '02-28'; // Sécurité année bissextile

    $tempMaxSum = 0;
    $tempMinSum = 0;
    $validYearsCount = 0;
    $representativeCode = 0; // On gardera le code de l'année N-1

    for ($i = 1; $i <= 3; $i++) {
        $pastYear = $currentYear - $i;
        $searchDate = $pastYear . '-' . $monthDay;
        
        $url = "https://archive-api.open-meteo.com/v1/archive?latitude=$lat&longitude=$lng&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=$searchDate&end_date=$searchDate";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Timeout court pour ne pas ralentir le serveur
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $data = json_decode($res, true);
            if (isset($data['daily']['temperature_2m_max'][0])) {
                $tempMaxSum += $data['daily']['temperature_2m_max'][0];
                $tempMinSum += $data['daily']['temperature_2m_min'][0];
                
                if ($i === 1) { // On prend le temps qu'il a fait à N-1 pour l'icône
                    $representativeCode = $data['daily']['weather_code'][0];
                }
                $validYearsCount++;
            }
        }
    }

    // Si on a pu récupérer au moins une année de données
    if ($validYearsCount > 0) {
        echo json_encode([
            'success' => true,
            'data' => [
                'code' => $representativeCode,
                'temp_max' => round($tempMaxSum / $validYearsCount, 1), // Moyenne arrondie à 1 décimale
                'temp_min' => round($tempMinSum / $validYearsCount, 1),
                'is_historical' => true
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Archives indisponibles']);
    }
    exit; // On arrête l'exécution ici pour les voyages lointains

} elseif ($daysDiff < -2) {
    // 2. VOYAGE PASSÉ : On interroge les archives pour cette date précise
    $baseUrl = "https://archive-api.open-meteo.com/v1/archive";
    $searchDate = $date;
} else {
    // 3. FUTUR PROCHE (Prévisions fiables) : On interroge les prévisions
    $baseUrl = "https://api.open-meteo.com/v1/forecast";
    $searchDate = $date;
}

// --- APPEL API CLASSIQUE (Pour les prévisions et les voyages passés) ---
$url = "$baseUrl?latitude=$lat&longitude=$lng&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=$searchDate&end_date=$searchDate";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
curl_close($ch);

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Erreur API Open-Meteo']); 
    exit;
}

$data = json_decode($res, true);

if (isset($data['daily']['weather_code'][0])) {
    echo json_encode([
        'success' => true,
        'data' => [
            'code' => $data['daily']['weather_code'][0],
            'temp_max' => $data['daily']['temperature_2m_max'][0],
            'temp_min' => $data['daily']['temperature_2m_min'][0],
            'is_historical' => false
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée pour cette date']);
}