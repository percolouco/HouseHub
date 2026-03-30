<?php
// modules/holidays/includes/api/reorder_checkpoints.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

$holiday_id = (int)$_POST['holiday_id'];
$locations = json_decode($_POST['locations'] ?? '[]', true);

if ($holiday_id > 0 && is_array($locations)) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET sort_order = ? WHERE holiday_id = ? AND location_name = ?");
        
        foreach ($locations as $index => $loc) {
            // On met à jour toutes les dépenses de ce lieu avec son nouveau rang (0, 1, 2...)
            $stmt->execute([$index, $holiday_id, $loc]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}