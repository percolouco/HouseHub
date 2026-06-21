<?php
// modules/holidays/includes/api/delete_attachment.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
$holiday_id = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;

if ($file_id === 0 || $holiday_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
    exit;
}

// 1. On récupère le chemin exact du fichier pour le supprimer du NAS
$stmt = $pdo->prepare("SELECT file_path FROM pf_holidays_attachments WHERE id = ? AND holiday_id = ?");
$stmt->execute([$file_id, $holiday_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if ($file) {
    $absolutePath = dirname(__DIR__, 4) . '/' . $file['file_path'];
    
    // 2. On supprime physiquement le fichier s'il existe
    if (file_exists($absolutePath)) {
        unlink($absolutePath);
    }
    
    // 3. On nettoie la base de données
    $del = $pdo->prepare("DELETE FROM pf_holidays_attachments WHERE id = ?");
    $del->execute([$file_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Fichier introuvable ou non autorisé']);
}