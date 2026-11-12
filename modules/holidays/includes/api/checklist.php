<?php
// modules/holidays/includes/api/checklist.php
// Checklist d'un voyage : « à faire avant le départ » (todo) et « bagages » (pack).
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$action     = $_POST['action'] ?? '';
$holiday_id = (int)($_POST['holiday_id'] ?? 0);
$kind       = ($_POST['kind'] ?? 'todo') === 'pack' ? 'pack' : 'todo';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_holidays_checklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_id INT NOT NULL,
        kind VARCHAR(10) NOT NULL DEFAULT 'todo',
        label VARCHAR(255) NOT NULL,
        is_done TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_hol_check (holiday_id),
        FOREIGN KEY (holiday_id) REFERENCES pf_holidays(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $chk = $pdo->prepare("SELECT id FROM pf_holidays WHERE id = ?");
    $chk->execute([$holiday_id]);
    if (!$chk->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Voyage introuvable']);
        exit;
    }

    if ($action === 'add') {
        // Plusieurs éléments d'un coup : un par ligne (utilisé par les suggestions)
        $labels = array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['label'] ?? ''))), fn($l) => $l !== '');
        $labels = array_slice(array_values($labels), 0, 40);
        if (empty($labels)) {
            echo json_encode(['success' => false, 'error' => 'Intitulé manquant']);
            exit;
        }
        $exists = $pdo->prepare("SELECT 1 FROM pf_holidays_checklist WHERE holiday_id = ? AND kind = ? AND label = ?");
        $ins = $pdo->prepare("INSERT INTO pf_holidays_checklist (holiday_id, kind, label) VALUES (?, ?, ?)");
        $created = [];
        foreach ($labels as $label) {
            $label = mb_substr($label, 0, 255);
            $exists->execute([$holiday_id, $kind, $label]);
            if ($exists->fetchColumn()) continue; // pas de doublon dans une même liste
            $ins->execute([$holiday_id, $kind, $label]);
            $created[] = ['id' => (int)$pdo->lastInsertId(), 'label' => $label, 'kind' => $kind, 'is_done' => 0];
        }
        echo json_encode(['success' => true, 'items' => $created], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle') {
        $st = $pdo->prepare("UPDATE pf_holidays_checklist SET is_done = 1 - is_done WHERE id = ? AND holiday_id = ?");
        $st->execute([$id, $holiday_id]);
        echo json_encode(['success' => $st->rowCount() > 0]);
        exit;
    }
    if ($action === 'delete') {
        $st = $pdo->prepare("DELETE FROM pf_holidays_checklist WHERE id = ? AND holiday_id = ?");
        $st->execute([$id, $holiday_id]);
        echo json_encode(['success' => $st->rowCount() > 0]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
