<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit;
});

define('PV_BASE', 'http://192.168.1.29:9500');

function pvOk($d)  { echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function pvErr($m, $c = 400) { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }

function pvProxy(string $url, string $method = 'GET', ?string $body = null, string $contentType = 'application/json'): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . $contentType]);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $resp, 'code' => $code];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── MODELS ────────────────────────────────────────────────────────────────────
if ($action === 'models') {
    if ($method === 'GET') {
        $qs = http_build_query(array_intersect_key($_GET, array_flip(['category','file_type','search','limit','offset'])));
        $r = pvProxy(PV_BASE . '/api/models?' . $qs);
        if ($r['code'] !== 200) pvErr('PrintVault inaccessible', 502);
        pvOk(json_decode($r['body'], true));
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) pvErr('ID manquant');
        $r = pvProxy(PV_BASE . '/api/models/' . intval($id), 'DELETE');
        if ($r['code'] === 200) pvOk(['deleted' => true]);
        pvErr('Erreur suppression', 500);
    }
    if ($method === 'POST') {
        // Upload: forward multipart to PrintVault
        if (empty($_FILES['file'])) pvErr('Fichier manquant');
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) pvErr('Erreur upload: ' . $f['error']);

        $postFields = [
            'file'        => new CURLFile($f['tmp_name'], $f['type'] ?: 'application/octet-stream', $f['name']),
            'name'        => $_POST['name'] ?? pathinfo($f['name'], PATHINFO_FILENAME),
            'description' => $_POST['description'] ?? '',
            'category'    => $_POST['category'] ?? 'Non classé',
            'tags'        => $_POST['tags'] ?? '',
        ];

        $ch = curl_init(PV_BASE . '/api/models');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POSTFIELDS     => $postFields,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) pvOk(json_decode($resp, true));
        pvErr('Upload échoué (' . $code . '): ' . substr($resp, 0, 200), 500);
    }
}

// ── CATEGORIES ────────────────────────────────────────────────────────────────
if ($action === 'categories') {
    $r = pvProxy(PV_BASE . '/api/categories');
    if ($r['code'] !== 200) pvErr('PrintVault inaccessible', 502);
    pvOk(json_decode($r['body'], true));
}

// ── FILE DOWNLOAD (proxy) ─────────────────────────────────────────────────────
if ($action === 'file') {
    $id = intval($_GET['id'] ?? 0); if (!$id) pvErr('ID manquant');
    $info = pvProxy(PV_BASE . '/api/models/' . $id);
    if ($info['code'] !== 200) pvErr('Modèle introuvable', 404);
    $m = json_decode($info['body'], true);

    $ch = curl_init(PV_BASE . '/api/models/' . $id . '/file');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $data = curl_exec($ch);
    $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    curl_close($ch);

    ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($m['original_name'] ?? 'model') . '"');
    header('Cache-Control: no-cache');
    echo $data;
    exit;
}

pvErr('Action inconnue', 404);
