<?php
// modules/holidays/includes/api/upload_attachment.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();
require dirname(__DIR__) . '/hol_files.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$holiday_id = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;
// L'étape est repérée par sa position, et la première étape est en position 0 : 0 est une valeur valide
$item_id = (isset($_POST['item_id']) && is_numeric($_POST['item_id']) && (int)$_POST['item_id'] >= 0) ? (int)$_POST['item_id'] : null;

if ($holiday_id === 0 || empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes ou fichier invalide']);
    exit;
}

$file = $_FILES['file'];

// Contrôle côté serveur : uniquement des PDF et des images, 10 Mo maximum
$allowed = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Envoi du fichier incomplet']);
    exit;
}
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Fichier trop lourd (10 Mo maximum)']);
    exit;
}
$mime = function_exists('finfo_open') ? (string)finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']) : ($allowed[$ext] ?? '');
if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
    echo json_encode(['success' => false, 'error' => 'Format non accepté (PDF, JPG, PNG ou WEBP uniquement)']);
    exit;
}

// On range les fichiers dans un sous-dossier par voyage pour rester propre sur le NAS
$uploadDir = hol_storage_dir((string)$holiday_id) . '/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Sécurisation du nom de fichier (retrait des accents et caractères spéciaux)
$fileName = basename($file['name']);
$safeFileName = preg_replace("/[^a-zA-Z0-9.-]/", "_", $fileName);
$uniqueFileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeFileName;
$destination = $uploadDir . $uniqueFileName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Enregistrement en base de données
    $stmt = $pdo->prepare("INSERT INTO pf_holidays_attachments (holiday_id, item_id, file_name, file_path) VALUES (?, ?, ?, ?)");
    $stmt->execute([$holiday_id, $item_id, $fileName, $holiday_id . '/' . $uniqueFileName]);
    
    $attachmentId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $attachmentId, 'file_name' => $fileName]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'écriture du fichier sur le NAS']);
}