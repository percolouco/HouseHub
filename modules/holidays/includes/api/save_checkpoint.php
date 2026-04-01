<?php
// modules/holidays/includes/api/save_checkpoint.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

$holiday_id = (int)$_POST['holiday_id'];
$location_name = trim($_POST['location_name']);
$lat = (float)$_POST['lat'];
$lng = (float)$_POST['lng'];
$old_sort_order = isset($_POST['old_sort_order']) && $_POST['old_sort_order'] !== '' ? (int)$_POST['old_sort_order'] : null;

// 1. SUPPRESSION
if (isset($_POST['action_delete']) && $_POST['action_delete'] === '1') {
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
    header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
    exit;
}

if ($holiday_id > 0 && !empty($location_name)) {
    try {
        $pdo->beginTransaction();

        // 2. DÉTERMINER L'ORDRE (Identifiant de l'étape)
        if ($old_sort_order !== null) {
            // Modification : on supprime l'ancien contenu de cette étape précise
            $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
            $target_order = $old_sort_order;
        } else {
            // Ajout : on place à la fin (Max + 1)
            $stmtMax = $pdo->prepare("SELECT MAX(sort_order) FROM pf_holidays_items WHERE holiday_id = ?");
            $stmtMax->execute([$holiday_id]);
            $max = $stmtMax->fetchColumn();
            $target_order = ($max !== null) ? (int)$max + 1 : 0;
        }

        // 3. INSERTION DES LIGNES
        $stmt = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");        $validItemsCount = 0;

        if (isset($_POST['items']['name'])) {
            foreach ($_POST['items']['name'] as $i => $raw_name) {
                $name = trim($raw_name);
                $amount_raw = $_POST['items']['amount'][$i];
                if ($name !== '' || $amount_raw !== '') {
                    $cat = $_POST['items']['cat'][$i] ?? 'activity';
                    $amount = (float)$amount_raw;
                    $paid = (isset($_POST['items']['paid'][$i]) && (int)$_POST['items']['paid'][$i] === 1) ? 1 : 0;                    
                    $note = trim($_POST['items']['notes'][$i] ?? '');
                    $stmt->execute([$holiday_id, $cat, $name ?: 'Dépense', $amount, $paid, $location_name, $lat, $lng, $target_order, $note]);                    $validItemsCount++;
                }
            }
        }

        if ($validItemsCount === 0) {
            $stmt->execute([$holiday_id, 'activity', 'PF_TECHNICAL_POINT', 0, 1, $location_name, $lat, $lng, $target_order, '']);
        }

        // 4. GESTION DES FAVORIS
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

        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
exit;