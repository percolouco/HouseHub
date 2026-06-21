<?php
// modules/holidays/includes/api/upload_attachment.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$holiday_id = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;
$item_id = (isset($_POST['item_id']) && (int)$_POST['item_id'] > 0) ? (int)$_POST['item_id'] : null;

if ($holiday_id === 0 || empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes ou fichier invalide']);
    exit;
}

$file = $_FILES['file'];

// On range les fichiers dans un sous-dossier par voyage pour rester propre sur le NAS
$uploadDir = dirname(__DIR__, 4) . '/uploads/holidays/' . $holiday_id . '/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Sécurisation du nom de fichier (retrait des accents et caractères spéciaux)
$fileName = basename($file['name']);
$safeFileName = preg_replace("/[^a-zA-Z0-9.-]/", "_", $fileName);
$uniqueFileName = time() . '_' . $safeFileName;
$destination = $uploadDir . $uniqueFileName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Enregistrement en base de données
    $stmt = $pdo->prepare("INSERT INTO pf_holidays_attachments (holiday_id, item_id, file_name, file_path) VALUES (?, ?, ?, ?)");
    $stmt->execute([$holiday_id, $item_id, $fileName, 'uploads/holidays/' . $holiday_id . '/' . $uniqueFileName]);
    
    $attachmentId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $attachmentId, 'file_name' => $fileName]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'écriture du fichier sur le NAS']);
}