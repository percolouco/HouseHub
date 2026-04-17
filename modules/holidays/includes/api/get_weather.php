<?php
// modules/holidays/includes/api/get_weather.php

// 1. Désactiver les erreurs HTML pour ne pas corrompre le JSON
ini_set('display_errors', 0);
error_reporting(0); 

// 2. Inclusion de tes fichiers vitaux (C'est auth.php qui sécurise l'accès !)
$basePath = '../../../../';
require_once $basePath . 'includes/db.php';
require_once $basePath . 'includes/auth.php';

// Si on arrive à cette ligne, c'est que auth.php a validé la session.
header('Content-Type: application/json');

// 3. Récupération des paramètres GPS et Date
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

// 4. Choix du bon modèle météo (Prévisions vs Historique)
if ($daysDiff < -2) {
    $baseUrl = "https://archive-api.open-meteo.com/v1/archive";
} else {
    $baseUrl = "https://api.open-meteo.com/v1/forecast";
}

$url = "$baseUrl?latitude=$lat&longitude=$lng&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=$date&end_date=$date";

// 5. Interrogation d'Open-Meteo via cURL (plus robuste)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 secondes max
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Contourne les erreurs SSL en local

$res = curl_exec($ch);
$curlError = curl_error($ch); // On capture l'erreur exacte au cas où
curl_close($ch);

if (!$res) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur API Open-Meteo',
        'debug' => $curlError, // L'inspecteur JS nous donnera la vraie raison !
        'url' => $url
    ]); 
    exit;
}
// 6. Formatage et envoi au Javascript
if (isset($data['daily']['weather_code'][0])) {
    echo json_encode([
        'success' => true,
        'data' => [
            'code' => $data['daily']['weather_code'][0],
            'temp_max' => $data['daily']['temperature_2m_max'][0],
            'temp_min' => $data['daily']['temperature_2m_min'][0]
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée pour cette date']);
}