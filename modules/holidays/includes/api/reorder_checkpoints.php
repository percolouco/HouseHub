<?php
// modules/holidays/includes/api/reorder_checkpoints.php
// Réordonne les étapes d'un voyage. Chaque étape est repérée par sa position actuelle
// (sort_order) et non par son nom : un roadtrip peut passer deux fois par la même ville.
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

$holiday_id = (int)($_POST['holiday_id'] ?? 0);
$order = json_decode($_POST['order'] ?? '[]', true);

if ($holiday_id <= 0 || !is_array($order) || empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

$order = array_values(array_map('intval', $order));

try {
    // La liste envoyée doit correspondre exactement aux étapes existantes
    $st = $pdo->prepare("SELECT DISTINCT sort_order FROM pf_holidays_items WHERE holiday_id = ? AND location_name IS NOT NULL");
    $st->execute([$holiday_id]);
    $existing = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $a = $order; $b = $existing;
    sort($a); sort($b);
    if ($a !== $b || count($order) !== count(array_unique($order))) {
        echo json_encode(['success' => false, 'error' => 'Liste des étapes obsolète, rechargez la page']);
        exit;
    }

    $OFFSET = 100000; // passage par une plage temporaire pour éviter les collisions
    $pdo->beginTransaction();

    $stRet = $pdo->prepare("SELECT return_step_id FROM pf_holidays WHERE id = ?");
    $stRet->execute([$holiday_id]);
    $returnStep = $stRet->fetchColumn();

    $pdo->prepare("UPDATE pf_holidays_items SET sort_order = sort_order + $OFFSET WHERE holiday_id = ? AND location_name IS NOT NULL")->execute([$holiday_id]);
    $pdo->prepare("UPDATE pf_holidays_attachments SET item_id = item_id + $OFFSET WHERE holiday_id = ? AND item_id IS NOT NULL")->execute([$holiday_id]);

    $upItems = $pdo->prepare("UPDATE pf_holidays_items SET sort_order = ? WHERE holiday_id = ? AND sort_order = ?");
    $upFiles = $pdo->prepare("UPDATE pf_holidays_attachments SET item_id = ? WHERE holiday_id = ? AND item_id = ?");
    $newReturn = null;
    foreach ($order as $newPos => $oldPos) {
        $upItems->execute([$newPos, $holiday_id, $oldPos + $OFFSET]);
        $upFiles->execute([$newPos, $holiday_id, $oldPos + $OFFSET]);
        if ($returnStep !== null && $returnStep !== false && (int)$returnStep === $oldPos) $newReturn = $newPos;
    }
    // Documents d'étapes disparues : on les remet à leur position d'origine plutôt que de les perdre
    $pdo->prepare("UPDATE pf_holidays_attachments SET item_id = item_id - $OFFSET WHERE holiday_id = ? AND item_id >= $OFFSET")->execute([$holiday_id]);
    $pdo->prepare("UPDATE pf_holidays SET return_step_id = ? WHERE id = ?")->execute([$newReturn, $holiday_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
