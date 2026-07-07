<?php
header('Content-Type: application/json');
// On charge l'authentification et l'init PDO de l'espace familial courant automatiquement via tes includes globaux
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$expected_date = $_POST['expected_date'] ?? '';

if (empty($title) || $amount <= 0 || empty($expected_date)) {
    echo json_encode(['success' => false, 'message' => tr('error_generic')]);
    exit;
}

try {
    // Grâce au multi-tenant par BDD, $pdo pointe déjà sur la base de la famille connectée
    $stmt = $pdo->prepare("INSERT INTO pf_expected_expenses (title, amount, expected_date) VALUES (?, ?, ?)");
    $stmt->execute([$title, $amount, $expected_date]);

    echo json_encode(['success' => true, 'message' => tr('success_add_provision')]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}