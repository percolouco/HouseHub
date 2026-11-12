<?php
// modules/holidays/includes/hol_files.php
// Fichiers du module Voyages (photos de couverture, documents joints).
// Ils sont rangés dans le volume /uploads, hors du site, par famille, et servis par file.php
// après vérification de la connexion : une famille ne peut pas ouvrir les fichiers d'une autre.

function hol_family_id(): int {
    return (int)($_SESSION['user']['family_id'] ?? 0);
}

function hol_storage_dir(string $sub = ''): string {
    return '/uploads/holidays/' . hol_family_id() . ($sub !== '' ? '/' . trim($sub, '/') : '');
}

// Chemin absolu d'une pièce jointe à partir de la valeur stockée en base.
// Nouveau format : « <id voyage>/<fichier> » (dans le dossier de la famille).
// Ancien format  : « uploads/holidays/<id voyage>/<fichier> » (dans le dossier du site).
function hol_attachment_path(string $stored): ?string {
    if ($stored === '' || strpos($stored, '..') !== false) return null;
    if (strpos($stored, 'uploads/holidays/') === 0) return dirname(__DIR__, 3) . '/' . $stored;
    return hol_storage_dir() . '/' . ltrim($stored, '/');
}

function hol_cover_path(int $holidayId): string {
    return hol_storage_dir('covers') . '/' . $holidayId . '.jpg';
}

function hol_cover_url(int $holidayId): string {
    return '/modules/holidays/includes/api/file.php?cover=' . $holidayId . '&v=' . time();
}

function hol_http_get(string $url, int $timeout = 4, int $maxBytes = 0): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'HouseHub-Holidays/1.0',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http !== 200) return null;
    if ($maxBytes > 0 && strlen($body) > $maxBytes) return null;
    return $body;
}

// Cherche une photo de paysage pour une destination. Retourne l'URL distante ou null.
function hol_find_cover_url(string $title): ?string {
    // Mots qui décrivent le voyage et pas le lieu : retirés pour une 2e tentative
    $clean = trim(preg_replace('/\s+/', ' ', preg_replace('/\b(road ?trip|voyage|vacances|week-?end|séjour|sejour|été|ete|hiver|printemps|automne|noël|noel|toussaint|pâques|paques|famille|\d{2,4})\b/iu', ' ', $title)));
    $queries = array_values(array_unique(array_filter([$title, $clean], fn($q) => mb_strlen($q) >= 3)));

    // 1. Pixabay (photos libres de droits, meilleure qualité) si une clé est disponible
    $key = getenv('PIXABAY_API_KEY') ?: (defined('HOL_PIXABAY_KEY') ? HOL_PIXABAY_KEY : '');
    if ($key !== '') {
        foreach ($queries as $q) {
            $json = hol_http_get('https://pixabay.com/api/?' . http_build_query(['key' => $key, 'q' => $q, 'image_type' => 'photo', 'orientation' => 'horizontal', 'min_width' => 1280, 'per_page' => 3, 'safesearch' => 'true']), 4);
            $data = $json ? json_decode($json, true) : null;
            if (!empty($data['hits'][0]['largeImageURL'])) return $data['hits'][0]['largeImageURL'];
        }
    }

    // 2. Secours sans clé : photo principale de l'article Wikipédia (on écarte drapeaux, logos et cartes)
    foreach ($queries as $q) {
        $json = hol_http_get('https://fr.wikipedia.org/w/api.php?' . http_build_query(['action' => 'query', 'generator' => 'search', 'gsrsearch' => $q, 'gsrlimit' => 5, 'prop' => 'pageimages', 'piprop' => 'thumbnail', 'pithumbsize' => 1600, 'format' => 'json']), 5);
        $data = $json ? json_decode($json, true) : null;
        $pages = array_values($data['query']['pages'] ?? []);
        usort($pages, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        foreach ($pages as $p) {
            $t = $p['thumbnail'] ?? null;
            if (!$t || empty($t['source'])) continue;
            if (preg_match('/\.svg|flag|drapeau|logo|blason|coat_of_arms|map|carte|locator/i', $t['source'])) continue;
            if (($t['width'] ?? 0) < 800 || ($t['width'] ?? 0) < ($t['height'] ?? 0)) continue; // paysage uniquement
            return $t['source'];
        }
    }
    return null;
}

// Télécharge la photo et la garde sur le serveur : elle ne dépend plus d'un lien qui expire.
function hol_store_cover(int $holidayId, string $remoteUrl): bool {
    if (hol_family_id() <= 0 || $holidayId <= 0) return false;
    $bin = hol_http_get($remoteUrl, 10, 8 * 1024 * 1024);
    if ($bin === null) return false;
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return false;
    $dir = hol_storage_dir('covers');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents(hol_cover_path($holidayId), $bin) !== false;
}

// Cherche + enregistre la photo d'un voyage. Retourne l'URL locale, ou null si rien trouvé.
function hol_refresh_cover(int $holidayId, string $title): ?string {
    $remote = hol_find_cover_url($title);
    if ($remote && hol_store_cover($holidayId, $remote)) return hol_cover_url($holidayId);
    return null;
}
