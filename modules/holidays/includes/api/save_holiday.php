<?php
// modules/holidays/includes/api/save_holiday.php

// On remonte de 4 niveaux pour atteindre la racine
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login(); 

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
// 🔥 NOUVEAU : On récupère le véhicule optionnel
$vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;

try {
    $pdo->beginTransaction();

    if ($id) {
        // UPDATE (avec vehicle_id)
        $sql = "UPDATE pf_holidays SET title=?, period_hint=?, start_date=?, end_date=?, status=?, budget_food=?, budget_extra=?, notes=?, vehicle_id=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $vehicle_id, $id]);
    } else {
        // INSERT (avec vehicle_id)
        $sql = "INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes, vehicle_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $vehicle_id]);
        $id = $pdo->lastInsertId();
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur base de données : " . $e->getMessage());
}

header("Location: /holidays.php");
exit;