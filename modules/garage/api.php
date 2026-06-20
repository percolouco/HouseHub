<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
});

require_once dirname(__DIR__, 2) . '/includes/db.php';

$UPLOAD_DIR = '/uploads/garage/';
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0755, true); }

$pdo->exec("
CREATE TABLE IF NOT EXISTS pf_garage_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  maintenance_id INT DEFAULT NULL,
  label VARCHAR(255) DEFAULT NULL,
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(120) DEFAULT NULL,
  size INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gd_vehicle (vehicle_id),
  KEY idx_gd_maint (maintenance_id),
  FOREIGN KEY (vehicle_id) REFERENCES pf_vehicles(id) ON DELETE CASCADE,
  FOREIGN KEY (maintenance_id) REFERENCES pf_maintenances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function gOk($d) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode(['ok' => true, 'data' => $d], $flags);
    if ($json === false) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Erreur encodage JSON : ' . json_last_error_msg()]); exit; }
    echo $json; exit;
}
function gErr($m, $c = 400) { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }
function gBody()  { return json_decode(file_get_contents('php://input'), true) ?? []; }

function handleUpload(string $field, string $dir): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== 0) return null;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;
    $fname = uniqid('g', true) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $fname)) return $fname;
    return null;
}

function handleGarageDocumentUpload(string $field, string $dir): ?array {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($_FILES[$field]['size'] > 12 * 1024 * 1024) {
        gErr('Fichier trop volumineux (max 12 Mo).', 400);
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','odt','txt'];
    if (!in_array($ext, $allowed, true)) {
        gErr('Format non autorisé.', 400);
    }
    $fname = 'd_' . uniqid('', true) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $fname)) {
        return null;
    }
    $mimeMap = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt' => 'application/vnd.oasis.opendocument.text', 'txt' => 'text/plain',
    ];
    return [
        'filename' => $fname,
        'original_name' => $_FILES[$field]['name'],
        'mime' => $mimeMap[$ext] ?? 'application/octet-stream',
        'size' => (int) $_FILES[$field]['size'],
    ];
}

// ─── Vehicles ─────────────────────────────────────────────────────────────────
if ($action === 'vehicles') {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $v = $pdo->prepare("SELECT * FROM pf_vehicles WHERE id = ?"); $v->execute([$id]);
            $vehicle = $v->fetch(); if (!$vehicle) gErr('Véhicule introuvable', 404);
            $s = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(cost),0) as total FROM pf_maintenances WHERE vehicle_id = ?"); $s->execute([$id]); $vehicle['stats'] = $s->fetch();
            $p = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(price*quantity),0) as total FROM pf_parts WHERE vehicle_id = ?"); $p->execute([$id]); $vehicle['parts_stats'] = $p->fetch();
            $dc = $pdo->prepare("SELECT COUNT(*) FROM pf_garage_documents WHERE vehicle_id = ? AND maintenance_id IS NULL");
            $dc->execute([$id]);
            $vehicle['vehicle_docs_count'] = (int) $dc->fetchColumn();
            $dm = $pdo->prepare("SELECT COUNT(*) FROM pf_garage_documents WHERE vehicle_id = ? AND maintenance_id IS NOT NULL");
            $dm->execute([$id]);
            $vehicle['maint_docs_count'] = (int) $dm->fetchColumn();
            gOk($vehicle);
        }
        $stmt = $pdo->query("SELECT v.*, (SELECT COUNT(*) FROM pf_maintenances m WHERE m.vehicle_id = v.id) as maintenance_count, (SELECT COALESCE(SUM(cost),0) FROM pf_maintenances m WHERE m.vehicle_id = v.id) as total_cost, (SELECT date FROM pf_maintenances m WHERE m.vehicle_id = v.id ORDER BY date DESC LIMIT 1) as last_maintenance FROM pf_vehicles v ORDER BY v.created_at DESC");
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $photo = handleUpload('photo', $UPLOAD_DIR); 
        $d = $_POST ?: gBody();
        
        // 🔥 LE FIX EST ICI : Fonctions boucliers pour MySQL Strict Mode
        $nullInt   = fn($v) => ($v === '' || $v === null) ? null : (int)$v;
        $nullFloat = fn($v) => ($v === '' || $v === null) ? null : (float)$v;
        $nullStr   = fn($v) => ($v === '' || $v === null) ? null : $v;

        $stmt = $pdo->prepare("INSERT INTO pf_vehicles (name,brand,model,year,license_plate,vin,fuel_type,consumption,color,purchase_date,purchase_price,current_km,photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['name'] ?? '', 
            $d['brand'] ?? '', 
            $d['model'] ?? '', 
            $nullInt($d['year'] ?? null), 
            $nullStr($d['license_plate'] ?? null), 
            $nullStr($d['vin'] ?? null), 
            $d['fuel_type'] ?? 'Essence', 
            $nullFloat($d['consumption'] ?? null), 
            $nullStr($d['color'] ?? null), 
            $nullStr($d['purchase_date'] ?? null), 
            $nullFloat($d['purchase_price'] ?? null), 
            $nullInt($d['current_km'] ?? null) ?? 0, 
            $photo, 
            $nullStr($d['notes'] ?? null)
        ]);
        gOk(['id' => $pdo->lastInsertId()]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant'); $d = gBody();
        $int_fields = ['year','current_km']; 
        $float_fields = ['purchase_price', 'consumption'];
        $fields = ['name','brand','model','year','license_plate','vin','fuel_type','consumption','color','purchase_date','purchase_price','current_km','notes']; 
        $sets = array_map(fn($f) => "$f = ?", $fields);
        $vals = array_map(function($f) use ($d, $int_fields, $float_fields) {
            $v = $d[$f] ?? null;
            if ($v === '' || $v === null) return null;
            if (in_array($f, $int_fields)) return (int)$v;
            if (in_array($f, $float_fields)) return (float)$v;
            return $v;
        }, $fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE pf_vehicles SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?")->execute($vals);
        gOk(['updated' => true]);
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant');
        $docs = $pdo->prepare('SELECT filename FROM pf_garage_documents WHERE vehicle_id = ?');
        $docs->execute([$id]);
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $fn) {
            if ($fn) {
                @unlink($UPLOAD_DIR . basename($fn));
            }
        }
        $v = $pdo->prepare("SELECT photo FROM pf_vehicles WHERE id=?"); $v->execute([$id]); $row = $v->fetch(); if ($row['photo']) @unlink($UPLOAD_DIR . $row['photo']);
        $pdo->prepare("DELETE FROM pf_vehicles WHERE id = ?")->execute([$id]); gOk(['deleted' => true]);
    }
}

// ─── Documents (véhicule / entretien — factures, PDF, etc.) ───────────────────
if ($action === 'garage_documents') {
    if ($method === 'GET') {
        $vid = $_GET['vehicle_id'] ?? null;
        $mid = $_GET['maintenance_id'] ?? null;
        $vehicleOnly = ($_GET['vehicle_only'] ?? '') === '1';
        if ($mid) {
            $stmt = $pdo->prepare("SELECT d.*, v.name as vehicle_name FROM pf_garage_documents d JOIN pf_vehicles v ON v.id = d.vehicle_id WHERE d.maintenance_id = ? ORDER BY d.created_at DESC");
            $stmt->execute([$mid]);
            gOk($stmt->fetchAll());
        }
        if (!$vid) {
            gErr('vehicle_id ou maintenance_id requis');
        }
        if ($vehicleOnly) {
            $stmt = $pdo->prepare("SELECT * FROM pf_garage_documents WHERE vehicle_id = ? AND maintenance_id IS NULL ORDER BY created_at DESC");
            $stmt->execute([$vid]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM pf_garage_documents WHERE vehicle_id = ? ORDER BY created_at DESC");
            $stmt->execute([$vid]);
        }
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $d = $_POST;
        $vid = (int) ($d['vehicle_id'] ?? 0);
        if ($vid < 1) {
            gErr('vehicle_id manquant');
        }
        $mid = isset($d['maintenance_id']) && $d['maintenance_id'] !== '' ? (int) $d['maintenance_id'] : null;
        if ($mid) {
            $chk = $pdo->prepare("SELECT id FROM pf_maintenances WHERE id = ? AND vehicle_id = ?");
            $chk->execute([$mid, $vid]);
            if (!$chk->fetch()) {
                gErr('Entretien invalide pour ce véhicule');
            }
        }
        $up = handleGarageDocumentUpload('file', $UPLOAD_DIR);
        if (!$up) {
            gErr('Upload échoué ou fichier manquant');
        }
        $label = trim($d['label'] ?? '');
        $pdo->prepare("INSERT INTO pf_garage_documents (vehicle_id, maintenance_id, label, filename, original_name, mime, size) VALUES (?,?,?,?,?,?,?)")
            ->execute([$vid, $mid, $label ?: null, $up['filename'], $up['original_name'], $up['mime'], $up['size']]);
        gOk(['id' => (int) $pdo->lastInsertId()]);
    }
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            gErr('ID manquant');
        }
        $r = $pdo->prepare("SELECT filename FROM pf_garage_documents WHERE id = ?");
        $r->execute([$id]);
        $row = $r->fetch();
        if (!$row) {
            gErr('Document introuvable', 404);
        }
        if (!empty($row['filename'])) {
            @unlink($UPLOAD_DIR . $row['filename']);
        }
        $pdo->prepare("DELETE FROM pf_garage_documents WHERE id = ?")->execute([$id]);
        gOk(['deleted' => true]);
    }
}

if ($action === 'garage_document') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        gErr('ID manquant');
    }
    $stmt = $pdo->prepare('SELECT * FROM pf_garage_documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc || empty($doc['filename'])) {
        http_response_code(404);
        exit;
    }
    $f = basename($doc['filename']);
    $path = $UPLOAD_DIR . $f;
    if (!preg_match('/^d_[a-zA-Z0-9._-]+$/', $f) || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = $doc['mime'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    $disp = $doc['original_name'] ?: $f;
    header('Content-Disposition: inline; filename="' . rawurlencode($disp) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

// ─── Maintenances ─────────────────────────────────────────────────────────────
if ($action === 'maintenances') {
    $vid = $_GET['vehicle_id'] ?? null;
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("SELECT m.*, v.name as vehicle_name, v.license_plate, v.current_km FROM pf_maintenances m JOIN pf_vehicles v ON v.id = m.vehicle_id WHERE m.id = ?");
            $stmt->execute([$id]); $m = $stmt->fetch(); if (!$m) gErr('Entretien introuvable', 404);
            $dc = $pdo->prepare("SELECT COUNT(*) FROM pf_garage_documents WHERE maintenance_id = ?");
            $dc->execute([$id]);
            $m['documents_count'] = (int) $dc->fetchColumn();
            gOk($m);
        }
        if ($vid) {
            $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(p.name SEPARATOR ', ') as parts_names, COUNT(p.id) as parts_count, COALESCE(SUM(p.price*p.quantity),0) as parts_cost, (SELECT COUNT(*) FROM pf_garage_documents d WHERE d.maintenance_id = m.id) as documents_count FROM pf_maintenances m LEFT JOIN pf_parts p ON p.maintenance_id = m.id WHERE m.vehicle_id = ? GROUP BY m.id ORDER BY m.date DESC, m.created_at DESC");
            $stmt->execute([$vid]); gOk($stmt->fetchAll());
        }
        $stmt = $pdo->query("SELECT m.*, v.name as vehicle_name, v.license_plate, v.current_km FROM pf_maintenances m JOIN pf_vehicles v ON v.id = m.vehicle_id WHERE m.next_date IS NOT NULL OR m.next_km IS NOT NULL ORDER BY m.next_date ASC");
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $photo = handleUpload('invoice_photo', $UPLOAD_DIR); $d = $_POST ?: gBody();
        if (!($d['vehicle_id'] ?? null)) gErr('vehicle_id manquant');
        $stmt = $pdo->prepare("INSERT INTO pf_maintenances (vehicle_id,type,description,date,km,cost,mechanic,garage_name,next_km,next_date,invoice_photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $nullInt   = fn($v) => ($v === '' || $v === null) ? null : (int)$v;
        $nullFloat = fn($v) => ($v === '' || $v === null) ? null : (float)$v;
        $nullStr   = fn($v) => ($v === '' || $v === null) ? null : $v;
        $stmt->execute([
            $d['vehicle_id'],
            $d['type'] ?? '',
            $nullStr($d['description'] ?? null),
            $d['date'] ?? date('Y-m-d'),
            $nullInt($d['km'] ?? null),
            $nullFloat($d['cost'] ?? null),
            $nullStr($d['mechanic'] ?? null),
            $nullStr($d['garage_name'] ?? null),
            $nullInt($d['next_km'] ?? null),
            $nullStr($d['next_date'] ?? null),
            $photo,
            $nullStr($d['notes'] ?? null),
        ]);
        $mid = $pdo->lastInsertId();
        if (!empty($d['km'])) { $pdo->prepare("UPDATE pf_vehicles SET current_km = GREATEST(current_km, ?), updated_at = NOW() WHERE id = ?")->execute([$d['km'], $d['vehicle_id']]); }
        gOk(['id' => $mid]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant'); $d = gBody();
        if (empty($d['type'])) gErr('Le type est requis');
        if (empty($d['date'])) gErr('La date est requise');
        $int_f = ['km','next_km']; $float_f = ['cost'];
        $fields = ['type','description','date','km','cost','mechanic','garage_name','next_km','next_date','notes'];
        $sets = array_map(fn($f) => "$f = ?", $fields);
        $vals = array_map(function($f) use ($d, $int_f, $float_f) {
            $v = $d[$f] ?? null;
            if ($v === '' || $v === null) return null;
            if (in_array($f, $int_f)) return (int)$v;
            if (in_array($f, $float_f)) return (float)$v;
            return $v;
        }, $fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE pf_maintenances SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals); gOk(['updated' => true]);
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant');
        $docs = $pdo->prepare('SELECT filename FROM pf_garage_documents WHERE maintenance_id = ?');
        $docs->execute([$id]);
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $fn) {
            if ($fn) {
                @unlink($UPLOAD_DIR . basename($fn));
            }
        }
        $pdo->prepare("DELETE FROM pf_maintenances WHERE id = ?")->execute([$id]); gOk(['deleted' => true]);
    }
}

// ─── Parts ────────────────────────────────────────────────────────────────────
if ($action === 'parts') {
    if ($method === 'GET') {
        $vid = $_GET['vehicle_id'] ?? null; $mid = $_GET['maintenance_id'] ?? null;
        $id = $_GET['id'] ?? null;
        if ($id) { $stmt = $pdo->prepare("SELECT * FROM pf_parts WHERE id = ?"); $stmt->execute([$id]); $p = $stmt->fetch(); if (!$p) gErr('Pièce introuvable', 404); gOk($p); }
        if ($mid) { $stmt = $pdo->prepare("SELECT * FROM pf_parts WHERE maintenance_id = ? ORDER BY created_at DESC"); $stmt->execute([$mid]); gOk($stmt->fetchAll()); }
        if ($vid) { $stmt = $pdo->prepare("SELECT p.*, m.type as maintenance_type, m.date as maintenance_date FROM pf_parts p LEFT JOIN pf_maintenances m ON m.id = p.maintenance_id WHERE p.vehicle_id = ? ORDER BY p.created_at DESC"); $stmt->execute([$vid]); gOk($stmt->fetchAll()); }
        $stmt = $pdo->query("SELECT p.*, v.name as vehicle_name, m.type as maintenance_type, m.date as maintenance_date FROM pf_parts p LEFT JOIN pf_vehicles v ON v.id = p.vehicle_id LEFT JOIN pf_maintenances m ON m.id = p.maintenance_id ORDER BY p.created_at DESC");
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $photo = handleUpload('photo', $UPLOAD_DIR); $d = $_POST ?: gBody();
        $nullInt   = fn($v) => ($v === '' || $v === null) ? null : (int)$v;
        $nullFloat = fn($v) => ($v === '' || $v === null) ? null : (float)$v;
        $nullStr   = fn($v) => ($v === '' || $v === null) ? null : $v;

        $stmt = $pdo->prepare("INSERT INTO pf_parts (vehicle_id,maintenance_id,brand,reference,name,category,price,quantity,unit,supplier,purchase_date,photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $nullInt($d['vehicle_id'] ?? null), 
            $nullInt($d['maintenance_id'] ?? null), 
            $nullStr($d['brand'] ?? null), 
            $nullStr($d['reference'] ?? null), 
            $d['name'] ?? '', 
            $d['category'] ?? 'Autre', 
            $nullFloat($d['price'] ?? null) ?? 0, 
            $nullInt($d['quantity'] ?? null) ?? 1, 
            $d['unit'] ?? 'pièce', 
            $nullStr($d['supplier'] ?? null), 
            $nullStr($d['purchase_date'] ?? null), 
            $photo, 
            $nullStr($d['notes'] ?? null)
        ]);
        gOk(['id' => $pdo->lastInsertId()]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant'); $d = gBody();
        $int_f = ['quantity']; $float_f = ['price'];
        $fields = ['brand','reference','name','category','price','quantity','unit','supplier','purchase_date','notes'];
        $sets = array_map(fn($f) => "$f = ?", $fields);
        $vals = array_map(function($f) use ($d, $int_f, $float_f) {
            $v = $d[$f] ?? null;
            if ($v === '' || $v === null) return null;
            if (in_array($f, $int_f)) return (int)$v;
            if (in_array($f, $float_f)) return (float)$v;
            return $v;
        }, $fields);
        $vals[] = $id;
        $pdo->prepare("UPDATE pf_parts SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals); gOk(['updated' => true]);
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant');
        $r = $pdo->prepare("SELECT photo FROM pf_parts WHERE id=?"); $r->execute([$id]); $row = $r->fetch(); if ($row['photo']) @unlink($UPLOAD_DIR . $row['photo']);
        $pdo->prepare("DELETE FROM pf_parts WHERE id = ?")->execute([$id]); gOk(['deleted' => true]);
    }
}

// ─── Stats ────────────────────────────────────────────────────────────────────
if ($action === 'stats') {
    gOk([
        'vehicles'          => $pdo->query("SELECT COUNT(*) FROM pf_vehicles")->fetchColumn(),
        'maintenances'      => $pdo->query("SELECT COUNT(*) FROM pf_maintenances")->fetchColumn(),
        'parts'             => $pdo->query("SELECT COUNT(*) FROM pf_parts")->fetchColumn(),
        'total_cost'        => $pdo->query("SELECT COALESCE(SUM(cost),0) FROM pf_maintenances")->fetchColumn(),
        'total_parts_cost'  => $pdo->query("SELECT COALESCE(SUM(price*quantity),0) FROM pf_parts")->fetchColumn(),
        'upcoming_reminders'=> $pdo->query("SELECT COUNT(*) FROM pf_maintenances WHERE next_date >= CURDATE() OR next_km IS NOT NULL")->fetchColumn(),
    ]);
}

// ─── Photo upload ──────────────────────────────────────────────────────────────
if ($action === 'upload_vehicle_photo') {
    $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant');
    $photo = handleUpload('photo', $UPLOAD_DIR); if (!$photo) gErr('Upload échoué');
    $old = $pdo->prepare("SELECT photo FROM pf_vehicles WHERE id=?"); $old->execute([$id]); $row = $old->fetch(); if ($row['photo']) @unlink($UPLOAD_DIR . $row['photo']);
    $pdo->prepare("UPDATE pf_vehicles SET photo = ?, updated_at = NOW() WHERE id = ?")->execute([$photo, $id]); gOk(['photo' => $photo]);
}

if ($action === 'photo') {
    $f = basename($_GET['file'] ?? '');
    if ($f && preg_match('/^[a-zA-Z0-9._-]+$/', $f) && file_exists($UPLOAD_DIR . $f)) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/jpeg';
        header('Content-Type: ' . $mime);
        readfile($UPLOAD_DIR . $f);
        exit;
    }
    http_response_code(404); exit;
}

if ($action === 'upload_part_photo') {
    $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant');
    $photo = handleUpload('photo', $UPLOAD_DIR); if (!$photo) gErr('Upload échoué');
    $pdo->prepare("UPDATE pf_parts SET photo = ? WHERE id = ?")->execute([$photo, $id]); gOk(['photo' => $photo]);
}
gErr('Action inconnue', 404);