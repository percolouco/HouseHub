<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // 1. Lire toutes les fêtes
    if ($action === 'get_occasions') {
        $stmt = $pdo->query("SELECT * FROM pf_gift_occasions ORDER BY month_date ASC, id ASC");
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. Ajouter une nouvelle fête (Renvoie l'ID pour rafraîchir la modale)
    if ($action === 'add_occasion') {
        $name = trim($_POST['name'] ?? '');
        $month_date = trim($_POST['month_date'] ?? '');
        
        if (empty($name)) throw new Exception("Name required");
        
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $code = substr($code, 0, 20);

        $stmt = $pdo->prepare("INSERT INTO pf_gift_occasions (code, name, month_date, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$code, $name, $month_date ?: null]);
        
        echo json_encode(['ok' => true]);
        exit;
    }

    // 3. Sauvegarde globale des cases à cocher
    if ($action === 'save_toggles') {
        $states = json_decode($_POST['states'] ?? '[]', true);
        if (is_array($states)) {
            $stmt = $pdo->prepare("UPDATE pf_gift_occasions SET is_active = ? WHERE id = ?");
            foreach ($states as $item) {
                $stmt->execute([(int)$item['state'], (int)$item['id']]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new Exception("Action inconnue.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>