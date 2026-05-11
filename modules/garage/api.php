<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once dirname(__DIR__, 2) . '/includes/db.php';

$UPLOAD_DIR = '/uploads/garage/';
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0755, true); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function gOk($d)  { echo json_encode(['ok' => true,  'data' => $d]); exit; }
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

// ─── Vehicles ─────────────────────────────────────────────────────────────────
if ($action === 'vehicles') {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $v = $pdo->prepare("SELECT * FROM pf_vehicles WHERE id = ?"); $v->execute([$id]);
            $vehicle = $v->fetch(); if (!$vehicle) gErr('Véhicule introuvable', 404);
            $s = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(cost),0) as total FROM pf_maintenances WHERE vehicle_id = ?"); $s->execute([$id]); $vehicle['stats'] = $s->fetch();
            $p = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(price*quantity),0) as total FROM pf_parts WHERE vehicle_id = ?"); $p->execute([$id]); $vehicle['parts_stats'] = $p->fetch();
            gOk($vehicle);
        }
        $stmt = $pdo->query("SELECT v.*, (SELECT COUNT(*) FROM pf_maintenances m WHERE m.vehicle_id = v.id) as maintenance_count, (SELECT COALESCE(SUM(cost),0) FROM pf_maintenances m WHERE m.vehicle_id = v.id) as total_cost, (SELECT date FROM pf_maintenances m WHERE m.vehicle_id = v.id ORDER BY date DESC LIMIT 1) as last_maintenance FROM pf_vehicles v ORDER BY v.created_at DESC");
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $photo = handleUpload('photo', $UPLOAD_DIR); $d = $_POST ?: gBody();
        $stmt = $pdo->prepare("INSERT INTO pf_vehicles (name,brand,model,year,license_plate,vin,fuel_type,color,purchase_date,purchase_price,current_km,photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['name']??'', $d['brand']??'', $d['model']??'', $d['year']??null, $d['license_plate']??null, $d['vin']??null, $d['fuel_type']??'Essence', $d['color']??null, $d['purchase_date']??null, $d['purchase_price']??null, $d['current_km']??0, $photo, $d['notes']??null]);
        gOk(['id' => $pdo->lastInsertId()]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant'); $d = gBody();
        $int_fields = ['year','current_km']; $float_fields = ['purchase_price'];
        $fields = ['name','brand','model','year','license_plate','vin','fuel_type','color','purchase_date','purchase_price','current_km','notes'];
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
        $v = $pdo->prepare("SELECT photo FROM pf_vehicles WHERE id=?"); $v->execute([$id]); $row = $v->fetch(); if ($row['photo']) @unlink($UPLOAD_DIR . $row['photo']);
        $pdo->prepare("DELETE FROM pf_vehicles WHERE id = ?")->execute([$id]); gOk(['deleted' => true]);
    }
}

// ─── Maintenances ─────────────────────────────────────────────────────────────
if ($action === 'maintenances') {
    $vid = $_GET['vehicle_id'] ?? null;
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("SELECT m.*, v.name as vehicle_name, v.license_plate, v.current_km FROM pf_maintenances m JOIN pf_vehicles v ON v.id = m.vehicle_id WHERE m.id = ?");
            $stmt->execute([$id]); $m = $stmt->fetch(); if (!$m) gErr('Entretien introuvable', 404);
            gOk($m);
        }
        if ($vid) {
            $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(p.name SEPARATOR ', ') as parts_names, COUNT(p.id) as parts_count, COALESCE(SUM(p.price*p.quantity),0) as parts_cost FROM pf_maintenances m LEFT JOIN pf_parts p ON p.maintenance_id = m.id WHERE m.vehicle_id = ? GROUP BY m.id ORDER BY m.date DESC, m.created_at DESC");
            $stmt->execute([$vid]); gOk($stmt->fetchAll());
        }
        $stmt = $pdo->query("SELECT m.*, v.name as vehicle_name, v.license_plate, v.current_km FROM pf_maintenances m JOIN pf_vehicles v ON v.id = m.vehicle_id WHERE m.next_date IS NOT NULL OR m.next_km IS NOT NULL ORDER BY m.next_date ASC");
        gOk($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $photo = handleUpload('invoice_photo', $UPLOAD_DIR); $d = $_POST ?: gBody();
        if (!($d['vehicle_id'] ?? null)) gErr('vehicle_id manquant');
        $stmt = $pdo->prepare("INSERT INTO pf_maintenances (vehicle_id,type,description,date,km,cost,mechanic,garage_name,next_km,next_date,invoice_photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['vehicle_id'], $d['type']??'', $d['description']??null, $d['date']??date('Y-m-d'), $d['km']??null, $d['cost']??0, $d['mechanic']??null, $d['garage_name']??null, $d['next_km']??null, $d['next_date']??null, $photo, $d['notes']??null]);
        $mid = $pdo->lastInsertId();
        if (!empty($d['km'])) { $pdo->prepare("UPDATE pf_vehicles SET current_km = GREATEST(current_km, ?), updated_at = NOW() WHERE id = ?")->execute([$d['km'], $d['vehicle_id']]); }
        gOk(['id' => $mid]);
    }
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null; if (!$id) gErr('ID manquant'); $d = gBody();
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
        $stmt = $pdo->prepare("INSERT INTO pf_parts (vehicle_id,maintenance_id,brand,reference,name,category,price,quantity,unit,supplier,purchase_date,photo,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['vehicle_id']??null, $d['maintenance_id']??null, $d['brand']??null, $d['reference']??null, $d['name']??'', $d['category']??'Autre', $d['price']??0, $d['quantity']??1, $d['unit']??'pièce', $d['supplier']??null, $d['purchase_date']??null, $photo, $d['notes']??null]);
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
