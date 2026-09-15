<?php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_cell') {
        $date = $_POST['plan_date'];
        $service = $_POST['service'];
        $person_id = (int)$_POST['person_id'];
        $meal_name = trim($_POST['meal_name']);

        if (empty($meal_name)) {
            $stmt = $pdo->prepare("DELETE FROM pf_meals_plan WHERE plan_date=? AND service=? AND person_id=?");
            $stmt->execute([$date, $service, $person_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pf_meals_plan (plan_date, service, person_id, meal_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE meal_name = VALUES(meal_name)");
            $stmt->execute([$date, $service, $person_id, $meal_name]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_bulk') {
        $updates = json_decode($_POST['updates'], true);
        if (is_array($updates)) {
            $pdo->beginTransaction();
            $stmtDel = $pdo->prepare("DELETE FROM pf_meals_plan WHERE plan_date=? AND service=? AND person_id=?");
            $stmtIns = $pdo->prepare("INSERT INTO pf_meals_plan (plan_date, service, person_id, meal_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE meal_name = VALUES(meal_name)");
            
            foreach ($updates as $u) {
                if (empty(trim($u['meal_name']))) {
                    $stmtDel->execute([$u['plan_date'], $u['service'], $u['person_id']]);
                } else {
                    $stmtIns->execute([$u['plan_date'], $u['service'], $u['person_id'], trim($u['meal_name'])]);
                }
            }
            $pdo->commit();
        }
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}