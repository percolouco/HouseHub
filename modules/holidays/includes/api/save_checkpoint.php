<?php
// modules/holidays/includes/api/save_checkpoint.php

require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

$holiday_id = (int)$_POST['holiday_id'];

// Suppression complète d'une étape
if (isset($_POST['action_delete']) && $_POST['action_delete'] === '1') {
    $loc = $_POST['old_location_name'];
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND location_name = ?")->execute([$holiday_id, $loc]);
    header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
    exit;
}

// Ajout / Modification d'une étape
$location_name = trim($_POST['location_name']);
$old_location = trim($_POST['old_location_name'] ?? '');
$lat = (float)$_POST['lat'];
$lng = (float)$_POST['lng'];

if ($holiday_id > 0 && !empty($location_name)) {
    try {
        $pdo->beginTransaction();

        // 1. GESTION DES FAVORIS
        if (isset($_POST['save_favorite']) && $_POST['save_favorite'] == '1') {
            $stmtFav = $pdo->query("SELECT content FROM pf_notes WHERE note_type = 'holiday_favorites'");
            $favs = json_decode($stmtFav->fetchColumn() ?: '[]', true);
            $exists = false;
            foreach ($favs as $f) { if ($f['name'] === $location_name) $exists = true; }
            if (!$exists) {
                $favs[] = ['name' => $location_name, 'lat' => $lat, 'lng' => $lng];
                $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('holiday_favorites', 'GLOBAL', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")->execute([json_encode($favs)]);
            }
        }

        // 2. GESTION DES DÉPENSES
        if (!empty($old_location)) {
            $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND location_name = ?")->execute([$holiday_id, $old_location]);
        }

        $stmt = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $validItemsCount = 0;

        if (isset($_POST['items']['name'])) {
            $count = count($_POST['items']['name']);
            for ($i = 0; $i < $count; $i++) {
                $name = trim($_POST['items']['name'][$i]);
                $amount_raw = $_POST['items']['amount'][$i];
                
                // Si la ligne n'est pas totalement vide
                if ($name !== '' || $amount_raw !== '') {
                    $cat = $_POST['items']['cat'][$i] ?? 'activity';
                    $amount = (float)$amount_raw;
                    if ($name === '') $name = 'Dépense liée';
                    $paid = isset($_POST['items']['paid'][$i]) ? 1 : 0;

                    $stmt->execute([$holiday_id, $cat, $name, $amount, $paid, $location_name, $lat, $lng]);
                    $validItemsCount++;
                }
            }
        }

        // 3. ÉTAPE SANS DÉPENSE (Point de passage)
        // Si l'utilisateur n'a saisi aucune dépense, on crée une ligne technique invisible pour forcer l'affichage du point GPS
        if ($validItemsCount === 0) {
            $stmt->execute([$holiday_id, 'activity', 'PF_TECHNICAL_POINT', 0, 1, $location_name, $lat, $lng]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur de sauvegarde : " . $e->getMessage());
    }
}

header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
exit;