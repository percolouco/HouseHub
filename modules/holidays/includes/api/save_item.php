<?php
// modules/holidays/includes/api/save_item.php
// Actions rapides sur une dépense d'un voyage, sans passer par la modale d'étape :
//  - toggle_paid    : bascule « payé / à payer » (dépense d'étape ou dépense générale)
//  - save_general   : ajoute ou modifie une dépense générale (vol, location, assurance… sans étape)
//  - delete_general : supprime une dépense générale
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
$item_id    = (int)($_POST['item_id'] ?? 0);

if ($holiday_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Voyage manquant']);
    exit;
}

try {
    if ($action === 'toggle_paid') {
        $st = $pdo->prepare("UPDATE pf_holidays_items SET is_paid = 1 - COALESCE(is_paid, 0) WHERE id = ? AND holiday_id = ? AND name <> 'PF_TECHNICAL_POINT'");
        $st->execute([$item_id, $holiday_id]);
        if ($st->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Dépense introuvable']);
            exit;
        }
        $st = $pdo->prepare("SELECT is_paid FROM pf_holidays_items WHERE id = ?");
        $st->execute([$item_id]);
        echo json_encode(['success' => true, 'is_paid' => (int)$st->fetchColumn()]);
        exit;
    }

    if ($action === 'save_general') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || $name === 'PF_TECHNICAL_POINT') {
            echo json_encode(['success' => false, 'error' => 'Nom manquant']);
            exit;
        }
        $category = in_array($_POST['category'] ?? '', ['transport', 'accommodation', 'activity', 'other'], true) ? $_POST['category'] : 'other';
        $amount   = round((float)str_replace(',', '.', $_POST['amount'] ?? '0'), 2);
        $paid     = !empty($_POST['is_paid']) ? 1 : 0;
        $notes    = trim($_POST['notes'] ?? '');

        if ($item_id > 0) {
            // On ne modifie ici que les dépenses générales (sans étape)
            $st = $pdo->prepare("UPDATE pf_holidays_items SET category = ?, name = ?, amount = ?, is_paid = ?, notes = ? WHERE id = ? AND holiday_id = ? AND location_name IS NULL");
            $st->execute([$category, $name, $amount, $paid, $notes, $item_id, $holiday_id]);
            echo json_encode(['success' => true, 'id' => $item_id]);
            exit;
        }

        $chk = $pdo->prepare("SELECT id FROM pf_holidays WHERE id = ?");
        $chk->execute([$holiday_id]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Voyage introuvable']);
            exit;
        }
        $st = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, notes, expense_context) VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, 0, ?, 'general')");
        $st->execute([$holiday_id, $category, $name, $amount, $paid, $notes]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'delete_general') {
        $st = $pdo->prepare("DELETE FROM pf_holidays_items WHERE id = ? AND holiday_id = ? AND location_name IS NULL");
        $st->execute([$item_id, $holiday_id]);
        echo json_encode(['success' => $st->rowCount() > 0, 'error' => $st->rowCount() > 0 ? null : 'Dépense introuvable']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
