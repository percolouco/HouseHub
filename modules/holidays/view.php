<?php
require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'bad id']);
  exit;
}

$st = $pdo->prepare("SELECT * FROM pf_holidays_ideas WHERE id = ?");
$st->execute([$id]);
$it = $st->fetch(PDO::FETCH_ASSOC);

if (!$it) {
  http_response_code(404);
  echo json_encode(['error' => 'not found']);
  exit;
}

echo json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
