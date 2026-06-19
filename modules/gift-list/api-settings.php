<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/db.php';
require_login();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'get_all') {
        $occ = $pdo->query("SELECT * FROM pf_gift_occasions ORDER BY sort_order ASC, id ASC")->fetchAll();
        $proches = $pdo->query("SELECT * FROM pf_people WHERE role IN ('proche_adulte', 'proche_enfant') ORDER BY name ASC")->fetchAll();
        echo json_encode(['success' => true, 'data' => ['occasions' => $occ, 'proches' => $proches]]);
        exit;
    }

    if ($action === 'save_occasion') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name']);
        $icon = trim($_POST['icon']) ?: '🎁';
        $month_date = trim($_POST['month_date']) ?: null;
        
        if (empty($name)) throw new Exception("Le nom est obligatoire.");

        if ($id) {
            $stmt = $pdo->prepare("UPDATE pf_gift_occasions SET name=?, icon=?, month_date=? WHERE id=?");
            $stmt->execute([$name, $icon, $month_date, $id]);
        } else {
            $code = 'CUST_' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4)) . '_' . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO pf_gift_occasions (code, name, icon, month_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $name, $icon, $month_date]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_occasion') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM pf_gift_occasions WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle_occasion') {
        $id = (int)$_POST['id'];
        $state = (int)$_POST['state'];
        $pdo->prepare("UPDATE pf_gift_occasions SET is_active=? WHERE id=?")->execute([$state, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_proche') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name']);
        $role = $_POST['role'];
        
        if (empty($name)) throw new Exception("Le nom est obligatoire.");
        if (!in_array($role, ['proche_adulte', 'proche_enfant'])) throw new Exception("Rôle invalide.");

        if ($id) {
            // Sécurité : on s'assure qu'on modifie bien un proche et pas un parent
            $stmt = $pdo->prepare("UPDATE pf_people SET name=?, role=? WHERE id=? AND role IN ('proche_adulte', 'proche_enfant')");
            $stmt->execute([$name, $role, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pf_people (name, role, color) VALUES (?, ?, '#94a3b8')");
            $stmt->execute([$name, $role]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_proche') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM pf_people WHERE id=? AND role IN ('proche_adulte', 'proche_enfant')");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}