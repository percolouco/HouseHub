<?php
require __DIR__ . '/includes/auth.php';
require_login();

$family_id = $_SESSION['user']['family_id'];
if (!$family_id) { http_response_code(404); exit; }

$file = null;
foreach (glob('/uploads/home_bg_' . $family_id . '.*') as $f) { $file = $f; break; }

if (!$file || !file_exists($file)) { http_response_code(404); exit; }

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext] ?? 'image/jpeg';

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
readfile($file);
