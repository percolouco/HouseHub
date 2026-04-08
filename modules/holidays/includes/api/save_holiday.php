<?php
// modules/holidays/includes/api/save_holiday.php

// On remonte de 4 niveaux pour atteindre la racine (api -> includes -> holidays -> modules -> racine)
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login(); // Si cette fonction nécessite une redirection, gère-la dans auth.php

if (isset($_POST['action_delete']) && $_POST['action_delete'] == '1') {
    $stmt = $pdo->prepare("DELETE FROM pf_holidays WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header("Location: /holidays.php");
    exit;
}

$id = $_POST['id'] ?? '';
$title = $_POST['title'];
$period = $_POST['period_hint'];
$start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
$status = $_POST['status'];
$food = !empty($_POST['budget_food']) ? $_POST['budget_food'] : 0;
$extra = !empty($_POST['budget_extra']) ? $_POST['budget_extra'] : 0;
$notes = $_POST['notes'];

try {
    $pdo->beginTransaction();

    if ($id) {
        // UPDATE
        $sql = "UPDATE pf_holidays SET title=?, period_hint=?, start_date=?, end_date=?, status=?, budget_food=?, budget_extra=?, notes=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $id]);
    } else {
        // INSERT
        $sql = "INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes]);
        $id = $pdo->lastInsertId();
    }

    // GESTION DES ITEMS GLOBAUX (On ne supprime QUE les items qui n'ont pas de lieu défini !)
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND (location_name IS NULL OR location_name = '')")->execute([$id]);
    
    if (!empty($_POST['items']['name'])) {
        $stmtItem = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid) VALUES (?, ?, ?, ?, ?)");
        
        $count = count($_POST['items']['name']);
        for ($i = 0; $i < $count; $i++) {
            // Utilisation de "?? ''" pour sécuriser si la donnée n'est pas envoyée
            $cat = $_POST['items']['cat'][$i] ?? '';
            $name = trim($_POST['items']['name'][$i] ?? '');
            $amount = floatval($_POST['items']['amount'][$i] ?? 0);
            $paid = isset($_POST['items']['paid'][$i]) ? $_POST['items']['paid'][$i] : 0;

            if (!empty($name)) {
                $stmtItem->execute([$id, $cat, $name, $amount, $paid]);
            }
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur base de données : " . $e->getMessage());
}

// Redirection vers la page principale
header("Location: /holidays.php");
exit;