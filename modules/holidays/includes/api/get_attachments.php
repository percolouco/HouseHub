<?php
// modules/holidays/includes/api/get_attachments.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

$holiday_id = isset($_GET['holiday_id']) ? (int)$_GET['holiday_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($holiday_id === 0) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']);
    exit;
}

// On récupère les documents de cette étape spécifique
$stmt = $pdo->prepare("SELECT id, file_name, file_path FROM pf_holidays_attachments WHERE holiday_id = ? AND item_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$holiday_id, $item_id]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'files' => $files]);