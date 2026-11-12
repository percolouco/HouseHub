<?php
// modules/holidays/includes/api/save_holiday.php

// On remonte de 4 niveaux pour atteindre la racine
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();
// Clé du service de photos : variable d'environnement PIXABAY_API_KEY si elle existe, sinon la valeur historique
define('HOL_PIXABAY_KEY', '56931941-a86fbea4e14b88712cc1e9ed9');
require dirname(__DIR__) . '/hol_files.php';

// Rafraîchit en arrière-plan la photo d'un voyage dont le lien distant a expiré (appelé par la page de détail)
if (($_POST['action'] ?? '') === 'refresh_cover') {
    header('Content-Type: application/json');
    $hid = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("SELECT title FROM pf_holidays WHERE id = ?");
    $st->execute([$hid]);
    $t = $st->fetchColumn();
    session_write_close();
    $url = $t ? hol_refresh_cover($hid, (string)$t) : null;
    if ($url) $pdo->prepare("UPDATE pf_holidays SET image_url = ? WHERE id = ?")->execute([$url, $hid]);
    echo json_encode(['success' => (bool)$url, 'url' => $url]);
    exit;
}

if (isset($_POST['action_delete']) && $_POST['action_delete'] == '1') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        // Étapes, dépenses et pièces jointes du voyage (au cas où la base n'a pas les suppressions en cascade)
        try {
            $stmtFiles = $pdo->prepare("SELECT file_path FROM pf_holidays_attachments WHERE holiday_id = ?");
            $stmtFiles->execute([$delId]);
            foreach ($stmtFiles->fetchAll(PDO::FETCH_COLUMN) as $relPath) {
                $abs = hol_attachment_path((string)$relPath);
                if ($abs && is_file($abs)) @unlink($abs);
            }
            @unlink(hol_cover_path($delId));
            $pdo->prepare("DELETE FROM pf_holidays_attachments WHERE holiday_id = ?")->execute([$delId]);
        } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ?")->execute([$delId]);
        $pdo->prepare("DELETE FROM pf_holidays WHERE id = ?")->execute([$delId]);
    }
    header("Location: /holidays.php");
    exit;
}

// Duplication : copie le voyage (en brouillon) avec ses étapes et dépenses remises « à payer »
if (isset($_POST['action_duplicate']) && $_POST['action_duplicate'] == '1') {
    $srcId = (int)($_POST['id'] ?? 0);
    $newId = 0;
    try {
        $pdo->beginTransaction();
        $src = $pdo->prepare("SELECT * FROM pf_holidays WHERE id = ?");
        $src->execute([$srcId]);
        if ($h = $src->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes, vehicle_id, return_step_id, image_url) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)")
                ->execute([mb_substr($h['title'] . ' (copie)', 0, 255), $h['period_hint'], $h['start_date'], $h['end_date'], $h['budget_food'], $h['budget_extra'], $h['notes'], $h['vehicle_id'], $h['return_step_id'], $h['image_url']]);
            $newId = (int)$pdo->lastInsertId();
            if (is_file(hol_cover_path($srcId)) && @copy(hol_cover_path($srcId), hol_cover_path($newId))) {
                $pdo->prepare("UPDATE pf_holidays SET image_url = ? WHERE id = ?")->execute([hol_cover_url($newId), $newId]);
            }
            $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, notes, item_date, item_time, step_type, step_start_date, step_end_date, duration, expense_context)
                           SELECT ?, category, name, amount, 0, location_name, lat, lng, sort_order, notes, item_date, item_time, step_type, step_start_date, step_end_date, duration, expense_context
                           FROM pf_holidays_items WHERE holiday_id = ?")->execute([$newId, $srcId]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur base de données : " . $e->getMessage());
    }
    header("Location: /holidays.php" . ($newId ? "?tab=holiday_detail&id=" . $newId : ""));
    exit;
}

$id = $_POST['id'] ?? '';
$title = trim($_POST['title'] ?? '');
if ($title === '') { header("Location: /holidays.php"); exit; }
$period = $_POST['period_hint'] ?? '';
$start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
$status = in_array($_POST['status'] ?? '', ['draft', 'planned', 'booked', 'passed', 'archived'], true) ? $_POST['status'] : 'draft';
if ($start && $end && $end < $start) { [$start, $end] = [$end, $start]; }
$food = !empty($_POST['budget_food']) ? $_POST['budget_food'] : 0;
$extra = !empty($_POST['budget_extra']) ? $_POST['budget_extra'] : 0;
// Les notes ne sont modifiées que si le formulaire les envoie (la modale « Modifier » ne les contient pas)
$hasNotes = array_key_exists('notes', $_POST);
$notes = $hasNotes ? $_POST['notes'] : null;
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

    // 2. Photo de couverture : à chercher si nouveau voyage, titre modifié, ou photo encore hébergée ailleurs (lien qui expire)
    $needCover = !$id || strcasecmp($old_title ?? '', $title) !== 0 || empty($image_url) || strpos((string)$image_url, '/modules/holidays/') !== 0;

    // 3. Sauvegarde en Base
    if ($id) {
        // UPDATE
        $sql = "UPDATE pf_holidays SET title=?, period_hint=?, start_date=?, end_date=?, status=?, budget_food=?, budget_extra=?, vehicle_id=?, image_url=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $vehicle_id, $image_url, $id]);
        if ($hasNotes) {
            $pdo->prepare("UPDATE pf_holidays SET notes=? WHERE id=?")->execute([$notes, $id]);
        }
    } else {
        // INSERT
        $sql = "INSERT INTO pf_holidays (title, period_hint, start_date, end_date, status, budget_food, budget_extra, notes, vehicle_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $period, $start, $end, $status, $food, $extra, $notes, $vehicle_id, $image_url]);
        $id = $pdo->lastInsertId();
    }

    $pdo->commit();

    // La photo est téléchargée et gardée sur le serveur (hors transaction : un service lent ne bloque pas l'enregistrement)
    if ($needCover) {
        $localUrl = hol_refresh_cover((int)$id, $title);
        if ($localUrl) $pdo->prepare("UPDATE pf_holidays SET image_url = ? WHERE id = ?")->execute([$localUrl, $id]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erreur base de données : " . $e->getMessage());
}

header("Location: /holidays.php");
exit;