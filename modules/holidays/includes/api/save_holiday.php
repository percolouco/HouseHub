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

    // 1. Détection d'un changement de nom pour refaire l'appel API
    $image_url = null;
    $old_title = null;
    if ($id) {
        $stmtOld = $pdo->prepare("SELECT title, image_url FROM pf_holidays WHERE id = ?");
        $stmtOld->execute([$id]);
        if ($row = $stmtOld->fetch()) {
            $old_title = $row['title'];
            $image_url = $row['image_url'];
        }
    }

    // 2. Appel à Pixabay si nouveau voyage ou si le titre a changé
    if (!$id || strcasecmp($old_title ?? '', $title) !== 0) {
        $apiKey = '56931941-a86fbea4e14b88712cc1e9ed9'; 
        $query = urlencode($title);
        // On force des photos horizontales HD
        $url = "https://pixabay.com/api/?key={$apiKey}&q={$query}&image_type=photo&orientation=horizontal&min_width=1280&per_page=3";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout de 3s max pour ne pas bloquer l'UI
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['hits'][0]['largeImageURL'])) {
                $image_url = $data['hits'][0]['largeImageURL'];
            }
        }
    }

    // 3. Sauvegarde en Base
    if ($id) {
        // UPDATE
        $sql = "UPDATE pf_holidays SET title=?, period_hint=?, start_date=?, end_date=?, status=?, budget_food=?, budget_extra=?, notes=?, vehicle_id=?, image_url=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $vehicle_id, $image_url, $id]);
    } else {
        // INSERT
        $sql = "INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes, vehicle_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $vehicle_id, $image_url]);
        $id = $pdo->lastInsertId();
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur base de données : " . $e->getMessage());
}

header("Location: /holidays.php");
exit;