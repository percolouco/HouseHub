<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();
header('Content-Type: application/json');
set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
});
require_once dirname(__DIR__, 2) . '/includes/db.php';

define('PV_MODEL_DIR', '/uploads/printvault/models/');
define('PV_THUMB_DIR', '/uploads/printvault/thumbs/');

function pvOk($d)       { ob_end_clean(); echo json_encode(['ok'=>true,'data'=>$d], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function pvErr($m,$c=400){ ob_end_clean(); http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function pvBody()       { return json_decode(file_get_contents('php://input'), true) ?? []; }

// ── STL parser ─────────────────────────────────────────────────────────────────
function parseSTL(string $path): array {
    $data = file_get_contents($path, false, null, 0, 204);
    if ($data === false || strlen($data) < 6) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $isAscii = strtolower(substr(ltrim($data), 0, 5)) === 'solid';

    $size = filesize($path);
    if ($size > 30 * 1024 * 1024) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0]; // skip >30MB

    $data = file_get_contents($path);
    if ($isAscii) return parseSTLAscii($data);
    return parseSTLBinary($data);
}

function parseSTLBinary(string $data): array {
    if (strlen($data) < 84) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $n = unpack('V', substr($data, 80, 4))[1];
    if (strlen($data) < 84 + $n * 50 || $n === 0) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];

    $mins = [PHP_FLOAT_MAX, PHP_FLOAT_MAX, PHP_FLOAT_MAX];
    $maxs = [-PHP_FLOAT_MAX, -PHP_FLOAT_MAX, -PHP_FLOAT_MAX];
    $vol = 0.0;

    for ($i = 0; $i < $n; $i++) {
        $off = 84 + $i * 50;
        $v1 = array_values(unpack('f3', substr($data, $off+12, 12)));
        $v2 = array_values(unpack('f3', substr($data, $off+24, 12)));
        $v3 = array_values(unpack('f3', substr($data, $off+36, 12)));
        for ($j = 0; $j < 3; $j++) {
            $vals = [$v1[$j], $v2[$j], $v3[$j]];
            $mins[$j] = min($mins[$j], ...$vals);
            $maxs[$j] = max($maxs[$j], ...$vals);
        }
        $vol += ($v1[0]*($v2[1]*$v3[2]-$v2[2]*$v3[1])
                -$v1[1]*($v2[0]*$v3[2]-$v2[2]*$v3[0])
                +$v1[2]*($v2[0]*$v3[1]-$v2[1]*$v3[0])) / 6.0;
    }
    return [
        'dim_x' => round($maxs[0]-$mins[0], 2),
        'dim_y' => round($maxs[1]-$mins[1], 2),
        'dim_z' => round($maxs[2]-$mins[2], 2),
        'volume'=> round(abs($vol)/1000.0, 3),
    ];
}

function parseSTLAscii(string $data): array {
    preg_match_all('/vertex\s+([-\d.eE+]+)\s+([-\d.eE+]+)\s+([-\d.eE+]+)/', $data, $m);
    if (empty($m[1])) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $xs = array_map('floatval', $m[1]);
    $ys = array_map('floatval', $m[2]);
    $zs = array_map('floatval', $m[3]);
    return [
        'dim_x' => round(max($xs)-min($xs), 2),
        'dim_y' => round(max($ys)-min($ys), 2),
        'dim_z' => round(max($zs)-min($zs), 2),
        'volume'=> 0,
    ];
}

// ── 3MF parser ─────────────────────────────────────────────────────────────────
function parse3MF(string $path): array {
    if (!class_exists('ZipArchive')) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    if (filesize($path) > 50 * 1024 * 1024) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $z = new ZipArchive();
    if ($z->open($path) !== true) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $xml = null;
    for ($i = 0; $i < $z->numFiles; $i++) {
        if (str_ends_with($z->getNameIndex($i), '.model')) {
            $xml = $z->getFromIndex($i); break;
        }
    }
    $z->close();
    if (!$xml) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];

    libxml_use_internal_errors(true);
    $dom = simplexml_load_string($xml);
    if (!$dom) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];

    preg_match_all('/x="([-\d.eE+]+)"/', $xml, $mx);
    preg_match_all('/y="([-\d.eE+]+)"/', $xml, $my);
    preg_match_all('/z="([-\d.eE+]+)"/', $xml, $mz);
    if (empty($mx[1])) return ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
    $xs = array_map('floatval', $mx[1]); $ys = array_map('floatval', $my[1]); $zs = array_map('floatval', $mz[1]);
    return [
        'dim_x' => round(max($xs)-min($xs), 2),
        'dim_y' => round(max($ys)-min($ys), 2),
        'dim_z' => round(max($zs)-min($zs), 2),
        'volume'=> 0,
    ];
}

// ── GCode parser ───────────────────────────────────────────────────────────────
function parseGCode(string $path): array {
    $time = $filament = $nozzle = $bed = '';
    $handle = fopen($path, 'r');
    $lines = 0;
    while (!feof($handle) && $lines < 300) {
        $line = fgets($handle); $lines++;
        if (preg_match('/;TIME:(\d+)/', $line, $m)) {
            $s = (int)$m[1]; $time = ($s>=3600 ? floor($s/3600).'h':'').sprintf('%02d', ($s%3600)/60).'m';
        } elseif (preg_match('/;estimated printing time[^=]*=\s*(.+)/i', $line, $m)) {
            $time = trim($m[1]);
        } elseif (preg_match('/;Filament used:\s*([\d.]+)m/i', $line, $m)) {
            $filament = $m[1].'m';
        } elseif (preg_match('/;filament used \[mm\]\s*=\s*([\d.]+)/i', $line, $m)) {
            $filament = round((float)$m[1]/1000, 2).'m';
        } elseif (preg_match('/M104\s+S(\d+)/i', $line, $m) && !$nozzle) { $nozzle = $m[1].'°C'; }
        elseif  (preg_match('/M109\s+S(\d+)/i', $line, $m) && !$nozzle) { $nozzle = $m[1].'°C'; }
        elseif  (preg_match('/M140\s+S(\d+)/i', $line, $m) && !$bed)    { $bed = $m[1].'°C'; }
        elseif  (preg_match('/M190\s+S(\d+)/i', $line, $m) && !$bed)    { $bed = $m[1].'°C'; }
    }
    // Scan tail for slicer comments
    fseek($handle, -min(8192, filesize($path)), SEEK_END);
    while (!feof($handle)) {
        $line = fgets($handle);
        if (!$time  && preg_match('/;TIME:(\d+)/', $line, $m)) { $s=(int)$m[1]; $time=($s>=3600?floor($s/3600).'h':'').sprintf('%02d',($s%3600)/60).'m'; }
        if (!$filament && preg_match('/;Filament used:\s*([\d.]+)m/i', $line, $m)) $filament = $m[1].'m';
        if (!$filament && preg_match('/;filament used \[mm\]\s*=\s*([\d.]+)/i', $line, $m)) $filament = round((float)$m[1]/1000,2).'m';
    }
    fclose($handle);
    return compact('time', 'filament', 'nozzle', 'bed');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── CATEGORIES ─────────────────────────────────────────────────────────────────
if ($action === 'categories') {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT c.*, COUNT(m.id) as count FROM pf_pv_categories c LEFT JOIN pf_pv_models m ON m.category=c.name GROUP BY c.id ORDER BY c.name")->fetchAll();
        pvOk($rows);
    }
    if ($method === 'POST') {
        $d = pvBody();
        $name = trim($d['name'] ?? ''); if (!$name) pvErr('Nom requis');
        $pdo->prepare("INSERT IGNORE INTO pf_pv_categories (name,color) VALUES (?,?)")->execute([$name, $d['color'] ?? '#8b5cf6']);
        pvOk(['id' => (int)$pdo->lastInsertId()]);
    }
}

// ── MODELS ─────────────────────────────────────────────────────────────────────
if ($action === 'models') {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $s = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
            $r = $s->fetch(); if (!$r) pvErr('Introuvable', 404);
            pvOk($r);
        }
        $where = []; $params = [];
        if ($cat = ($_GET['category'] ?? '')) { $where[] = 'category=?'; $params[] = $cat; }
        if ($ft  = ($_GET['file_type'] ?? '')) {
            if ($ft === 'gcode') { $where[] = "file_type IN ('gcode','gco','g')"; }
            else { $where[] = 'file_type=?'; $params[] = $ft; }
        }
        if ($q = ($_GET['search'] ?? '')) {
            $like = '%'.$q.'%';
            $where[] = '(name LIKE ? OR description LIKE ? OR tags LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sql = "SELECT * FROM pf_pv_models" . ($where ? ' WHERE '.implode(' AND ',$where) : '') . " ORDER BY created_at DESC";
        $s = $pdo->prepare($sql); $s->execute($params);
        pvOk($s->fetchAll());
    }

    if ($method === 'POST') {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) pvErr('Fichier manquant');
        $f   = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['stl','3mf','gcode','gco','g'];
        if (!in_array($ext, $allowed)) pvErr('Format non supporté');

        $fname = uniqid('pv', true) . '.' . $ext;
        $dest  = PV_MODEL_DIR . $fname;
        if (!move_uploaded_file($f['tmp_name'], $dest)) pvErr('Erreur déplacement fichier');

        // Parse geometry / metadata
        $geo   = ['dim_x'=>0,'dim_y'=>0,'dim_z'=>0,'volume'=>0];
        $gcode = ['time'=>'','filament'=>'','nozzle'=>'','bed'=>''];
        if ($ext === 'stl')                      $geo   = parseSTL($dest);
        elseif ($ext === '3mf')                  $geo   = parse3MF($dest);
        elseif (in_array($ext, ['gcode','gco','g'])) $gcode = parseGCode($dest);

        $name = trim($_POST['name'] ?? pathinfo($f['name'], PATHINFO_FILENAME));
        $pdo->prepare("INSERT INTO pf_pv_models (name,description,category,tags,file_type,filename,original_name,file_size,dim_x,dim_y,dim_z,volume,gcode_time,gcode_filament,gcode_nozzle,gcode_bed) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $name,
                $_POST['description'] ?? '',
                $_POST['category'] ?? 'Non classé',
                $_POST['tags'] ?? '',
                $ext,
                $fname,
                $f['name'],
                filesize($dest),
                $geo['dim_x'], $geo['dim_y'], $geo['dim_z'], $geo['volume'],
                $gcode['time'], $gcode['filament'], $gcode['nozzle'], $gcode['bed'],
            ]);
        $id  = (int)$pdo->lastInsertId();
        $row = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?");
        $row->execute([$id]); pvOk($row->fetch());
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0); if (!$id) pvErr('ID manquant');
        $d  = pvBody();
        $name = trim($d['name'] ?? ''); if (!$name) pvErr('Nom requis');
        $pdo->prepare("UPDATE pf_pv_models SET name=?,description=?,category=?,tags=?,updated_at=NOW() WHERE id=?")
            ->execute([$name, $d['description']??'', $d['category']??'Non classé', $d['tags']??'', $id]);
        pvOk(['updated'=>true]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0); if (!$id) pvErr('ID manquant');
        $s = $pdo->prepare("SELECT filename, thumb FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
        $row = $s->fetch(); if (!$row) pvErr('Introuvable', 404);
        @unlink(PV_MODEL_DIR . $row['filename']);
        if ($row['thumb']) @unlink(PV_THUMB_DIR . $row['thumb']);
        $pdo->prepare("DELETE FROM pf_pv_models WHERE id=?")->execute([$id]);
        pvOk(['deleted'=>true]);
    }
}

// ── GCODE PATHS ────────────────────────────────────────────────────────────────
if ($action === 'gcode_paths') {
    $id     = (int)($_GET['id'] ?? 0);
    $max    = min((int)($_GET['max'] ?? 30000), 80000);
    $s = $pdo->prepare("SELECT filename, file_type FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
    $row = $s->fetch(); if (!$row) pvErr('Introuvable', 404);
    $ext = strtolower($row['file_type']);
    if (!in_array($ext, ['gcode','gco','g'])) pvErr('Pas un fichier GCode');
    $path = PV_MODEL_DIR . $row['filename'];
    if (!file_exists($path)) pvErr('Fichier introuvable', 404);

    $segments = [];
    $x=0.0; $y=0.0; $z=0.0; $e=0.0; $lastE=0.0;
    $minZ=PHP_FLOAT_MAX; $maxZ=0.0;

    $handle = fopen($path, 'r');
    while (!feof($handle) && count($segments) < $max) {
        $line = trim(fgets($handle));
        if (($p = strpos($line, ';')) !== false) $line = substr($line, 0, $p);
        $line = trim($line);
        if (!$line) continue;
        $cmd = strtoupper(strtok($line, ' '));
        if ($cmd === 'G92') {
            if (preg_match('/E([-\d.]+)/i', $line, $m)) $lastE = (float)$m[1];
            continue;
        }
        if ($cmd !== 'G0' && $cmd !== 'G1') continue;
        $nx=$x; $ny=$y; $nz=$z; $ne=$e;
        if (preg_match('/X([-\d.]+)/i', $line, $m)) $nx = (float)$m[1];
        if (preg_match('/Y([-\d.]+)/i', $line, $m)) $ny = (float)$m[1];
        if (preg_match('/Z([-\d.]+)/i', $line, $m)) $nz = (float)$m[1];
        if (preg_match('/E([-\d.]+)/i', $line, $m)) $ne = (float)$m[1];
        $extruding = ($cmd === 'G1' && $ne > $lastE) ? 1 : 0;
        if ($nx !== $x || $ny !== $y || $nz !== $z) {
            $segments[] = [$x, $y, $z, $nx, $ny, $nz, $extruding];
            $minZ = min($minZ, $nz);
            $maxZ = max($maxZ, $nz);
        }
        $x=$nx; $y=$ny; $z=$nz;
        if ($ne !== $e) { $lastE = $e = $ne; }
    }
    fclose($handle);
    pvOk(['segments' => $segments, 'min_z' => $minZ === PHP_FLOAT_MAX ? 0 : $minZ, 'max_z' => $maxZ, 'total' => count($segments)]);
}

// ── FILE SERVE ─────────────────────────────────────────────────────────────────
if ($action === 'file') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
    $row = $s->fetch(); if (!$row) { http_response_code(404); exit; }
    $path = PV_MODEL_DIR . $row['filename'];
    if (!file_exists($path)) { http_response_code(404); exit; }
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ['stl'=>'model/stl','3mf'=>'model/3mf','gcode'=>'text/plain','gco'=>'text/plain','g'=>'text/plain'][$ext] ?? 'application/octet-stream';
    ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($row['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path); exit;
}

// ── MODEL FILE FOR VIEWER (inline, no download) ────────────────────────────────
if ($action === 'model_data') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $pdo->prepare("SELECT * FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
    $row = $s->fetch(); if (!$row) { http_response_code(404); exit; }
    $path = PV_MODEL_DIR . $row['filename'];
    if (!file_exists($path)) { http_response_code(404); exit; }
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ['stl'=>'model/stl','3mf'=>'model/3mf'][$ext] ?? 'application/octet-stream';
    ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . rawurlencode($row['filename']) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($path); exit;
}

// ── THUMBNAIL SAVE ──────────────────────────────────────────────────────────────
if ($action === 'thumb' && $method === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $pdo->prepare("SELECT id, thumb FROM pf_pv_models WHERE id=?"); $s->execute([$id]);
    $row = $s->fetch(); if (!$row) pvErr('Introuvable', 404);

    if (empty($_FILES['thumb']) || $_FILES['thumb']['error'] !== UPLOAD_ERR_OK) pvErr('Pas de fichier');
    if ($row['thumb']) @unlink(PV_THUMB_DIR . $row['thumb']);

    $thumbName = 'thumb_' . uniqid('', true) . '.png';
    if (!move_uploaded_file($_FILES['thumb']['tmp_name'], PV_THUMB_DIR . $thumbName)) pvErr('Erreur sauvegarde');
    $pdo->prepare("UPDATE pf_pv_models SET thumb=? WHERE id=?")->execute([$thumbName, $id]);
    pvOk(['thumb' => $thumbName]);
}

pvErr('Action inconnue', 404);
