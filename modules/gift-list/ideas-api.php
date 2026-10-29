<?php
// modules/gift-list/ideas-api.php
// Bloc-notes d'idées en vrac par enfant : ajout, modification, suppression (réponses JSON).
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/db.php';
require_login();
require __DIR__ . '/ensure-schema.php';
gift_ensure_schema($pdo);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST requis']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $child = trim($_POST['child_name'] ?? '');
        $idea  = trim($_POST['idea'] ?? '');
        if ($child === '' || $idea === '') throw new Exception('Enfant et idée obligatoires.');
        $stmt = $pdo->prepare("INSERT INTO pf_gift_ideas (child_name, idea) VALUES (?, ?)");
        $stmt->execute([mb_substr($child, 0, 100), mb_substr($idea, 0, 500)]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $idea = trim($_POST['idea'] ?? '');
        if ($id <= 0 || $idea === '') throw new Exception('Idée invalide.');
        $stmt = $pdo->prepare("UPDATE pf_gift_ideas SET idea = ? WHERE id = ?");
        $stmt->execute([mb_substr($idea, 0, 500), $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Idée invalide.');
        $stmt = $pdo->prepare("DELETE FROM pf_gift_ideas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Action inconnue.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
