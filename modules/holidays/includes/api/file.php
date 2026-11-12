<?php
// modules/holidays/includes/api/file.php
// Sert les fichiers du module Voyages après vérification de la connexion :
//   ?cover=<id voyage>   photo de couverture enregistrée sur le serveur
//   ?id=<id pièce jointe> document joint à une étape
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();
require dirname(__DIR__) . '/hol_files.php';

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];

if (isset($_GET['cover'])) {
    $path = hol_cover_path((int)$_GET['cover']);
    if (!is_file($path)) { http_response_code(404); exit; }
    $info = @getimagesize($path);
    header('Content-Type: ' . ($info['mime'] ?? 'image/jpeg'));
    header('Cache-Control: private, max-age=604800');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT file_name, file_path FROM pf_holidays_attachments WHERE id = ?");
$st->execute([$id]);
$f = $st->fetch(PDO::FETCH_ASSOC);
$path = $f ? hol_attachment_path($f['file_path']) : null;
if (!$path || !is_file($path)) { http_response_code(404); exit; }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (!isset($mimes[$ext])) { http_response_code(404); exit; }
header('Content-Type: ' . $mimes[$ext]);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['file_name']) . '"');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($path));
readfile($path);
