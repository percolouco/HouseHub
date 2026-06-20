<?php
// modules/holidays/includes/api/save_checkpoint.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

// ---------------------------------------------------------------------------
// INTERCEPTIONS AJAX (Exécution ultra rapide sans rechargement lourd)
// ---------------------------------------------------------------------------
if (isset($_POST['action']) && in_array($_POST['action'], ['update_item_datetime', 'update_item_duration', 'add_single_item'])) {
    header('Content-Type: application/json');
    session_write_close(); // 🚀 LIBÈRE LA SESSION : Permet d'autres requêtes simultanées sans bloquer le navigateur
    
    try {
        if ($_POST['action'] === 'add_single_item') {
            $holiday_id = (int)$_POST['holiday_id'];
            $sort_order = (int)$_POST['sort_order'];
            
            $stmt = $pdo->prepare("SELECT location_name, lat, lng, step_start_date, step_end_date, step_type FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ? LIMIT 1");
            $stmt->execute([$holiday_id, $sort_order]);
            $stepInfo = $stmt->fetch();

            if ($stepInfo) {
                $dur  = isset($_POST['duration']) ? (int)$_POST['duration'] : 1;
                $date = !empty($_POST['item_date']) ? $_POST['item_date'] : null;
                $time = !empty($_POST['item_time']) ? $_POST['item_time'] : null;

                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, step_start_date, step_end_date, step_type, expense_context, duration, item_date, item_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $holiday_id, $_POST['category'], $_POST['name'], (float)$_POST['amount'], 0,
                    $stepInfo['location_name'], $stepInfo['lat'], $stepInfo['lng'],
                    $sort_order, $stepInfo['step_start_date'], $stepInfo['step_end_date'],
                    $stepInfo['step_type'], $_POST['context'], $dur, $date, $time
                ]);
                $pdo->commit();
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Etape introuvable']);
            exit;
        }

        // Drag & Drop planning : Date et Heure
        if ($_POST['action'] === 'update_item_datetime') {
            $itemId = (int)$_POST['item_id'];
            $itemDate = !empty($_POST['item_date']) ? $_POST['item_date'] : null;
            $itemTime = !empty($_POST['item_time']) ? $_POST['item_time'] : null;
            $stmt = $pdo->prepare("UPDATE pf_holidays_items SET item_date = ?, item_time = ? WHERE id = ?");
            $stmt->execute([$itemDate, $itemTime, $itemId]);
            echo json_encode(['success' => true]);
            exit;
        } 
        
        // Changement de durée (+/-)
        if ($_POST['action'] === 'update_item_duration') {
            $itemId = (int)$_POST['item_id'];
            $duration = (int)$_POST['duration'];
            $stmt = $pdo->prepare("UPDATE pf_holidays_items SET duration = ? WHERE id = ?");
            $stmt->execute([$duration, $itemId]);
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// --------------------------------===========================================
// FORMULAIRE TRADITIONNEL (Soumission de la modale d'étape)
// --------------------------------===========================================
$holiday_id = (int)$_POST['holiday_id'];
$location_name = trim($_POST['location_name']);
$lat = (float)$_POST['lat'];
$lng = (float)$_POST['lng'];
$old_sort_order = isset($_POST['old_sort_order']) && $_POST['old_sort_order'] !== '' ? (int)$_POST['old_sort_order'] : null;

// SUPPRESSION DE L'ÉTAPE
if (isset($_POST['action_delete']) && $_POST['action_delete'] === '1') {
    $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
    header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
    exit;
}

if ($holiday_id > 0 && !empty($location_name)) {
    try {
        $pdo->beginTransaction();

        if ($old_sort_order !== null) {
            $pdo->prepare("DELETE FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ?")->execute([$holiday_id, $old_sort_order]);
            $target_order = $old_sort_order;
        } else {
            $stmtMax = $pdo->prepare("SELECT MAX(sort_order) FROM pf_holidays_items WHERE holiday_id = ?");
            $stmtMax->execute([$holiday_id]);
            $max = $stmtMax->fetchColumn();
            $target_order = ($max !== null) ? (int)$max + 1 : 0;
        }

        $step_start = !empty($_POST['step_start_date']) ? $_POST['step_start_date'] : null;
        $step_end = !empty($_POST['step_end_date']) ? $_POST['step_end_date'] : null;
        $step_type = $_POST['step_type'] ?? 'stop';

        if ($step_type === 'origin' || $step_type === 'destination') {
            $step_end = null; 
        }

        $stmt = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, notes, item_date, item_time, step_start_date, step_end_date, duration, step_type, expense_context) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $validItemsCount = 0;

        if (isset($_POST['items']['name'])) {
            foreach ($_POST['items']['name'] as $i => $raw_name) {
                $name = trim($raw_name);
                $amount_raw = $_POST['items']['amount'][$i];
                if ($name !== '' || $amount_raw !== '') {
                    $cat = $_POST['items']['cat'][$i] ?? 'activity';
                    $amount = (float)$amount_raw;
                    $paid = (isset($_POST['items']['paid'][$i]) && (int)$_POST['items']['paid'][$i] === 1) ? 1 : 0;
                    $note = trim($_POST['items']['notes'][$i] ?? '');
                    
                    $date = !empty($_POST['items']['date'][$i]) ? $_POST['items']['date'][$i] : null;
                    $time = !empty($_POST['items']['time'][$i]) ? $_POST['items']['time'][$i] : null;
                    $dur  = !empty($_POST['items']['duration'][$i]) ? (int)$_POST['items']['duration'][$i] : 1;
                    $context = !empty($_POST['items']['context'][$i]) ? $_POST['items']['context'][$i] : 'local';

                    $stmt->execute([$holiday_id, $cat, $name, $amount, $paid, $location_name, $lat, $lng, $target_order, $note, $date, $time, $step_start, $step_end, $dur, $step_type, $context]);
                    $validItemsCount++;
                }
            }
        }

        if ($validItemsCount === 0) {
            $stmt->execute([$holiday_id, 'activity', 'PF_TECHNICAL_POINT', 0, 1, $location_name, $lat, $lng, $target_order, '', null, null, $step_start, $step_end, 1, $step_type, 'local']);
        }

        if (isset($_POST['set_as_return']) && $_POST['set_as_return'] == '1') {
             $pdo->prepare("UPDATE pf_holidays SET return_step_id = ? WHERE id = ?")->execute([$target_order, $holiday_id]);
        }

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
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        die($e->getMessage()); 
    }
}

header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
exit;