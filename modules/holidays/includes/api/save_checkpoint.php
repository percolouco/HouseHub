<?php
// modules/holidays/includes/api/save_checkpoint.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

// INTERCEPTION AJAX : Sauvegarde du planning (Drag & Drop / Durée) ET Dépense Rapide
if (isset($_POST['action']) && in_array($_POST['action'], ['update_item_datetime', 'update_item_duration', 'add_single_item'])) {
    
        // 🔥 NOUVEAU : Ajout sécurisé d'une dépense unique (Essence OSRM)
        if ($_POST['action'] === 'add_single_item') {
            $holiday_id = (int)$_POST['holiday_id'];
            $sort_order = (int)$_POST['sort_order'];
            
            // On récupère les infos de l'étape existante pour ne rien casser (lat, lng, dates...)
            $stmt = $pdo->prepare("SELECT location_name, lat, lng, step_start_date, step_end_date, step_type FROM pf_holidays_items WHERE holiday_id = ? AND sort_order = ? LIMIT 1");
            $stmt->execute([$holiday_id, $sort_order]);
            $stepInfo = $stmt->fetch();

            if ($stepInfo) {
                $ins = $pdo->prepare("INSERT INTO pf_holidays_items (holiday_id, category, name, amount, is_paid, location_name, lat, lng, sort_order, step_start_date, step_end_date, step_type, expense_context, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $holiday_id, $_POST['category'], $_POST['name'], (float)$_POST['amount'], 0,
                    $stepInfo['location_name'], $stepInfo['lat'], $stepInfo['lng'],
                    $sort_order, $stepInfo['step_start_date'], $stepInfo['step_end_date'],
                    $stepInfo['step_type'], $_POST['context'], 1
                ]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

    // --- Ancien code Drag&Drop conservé ---
    $itemId = (int)$_POST['item_id'];
    if ($_POST['action'] === 'update_item_datetime') {
        $itemDate = !empty($_POST['item_date']) ? $_POST['item_date'] : null;
        $itemTime = !empty($_POST['item_time']) ? $_POST['item_time'] : null;
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET item_date = ?, item_time = ? WHERE id = ?");
        $stmt->execute([$itemDate, $itemTime, $itemId]);
    } else if ($_POST['action'] === 'update_item_duration') {
        $duration = (int)$_POST['duration'];
        $stmt = $pdo->prepare("UPDATE pf_holidays_items SET duration = ? WHERE id = ?");
        $stmt->execute([$duration, $itemId]);
    }
    
    echo json_encode(['success' => true]);
    exit; 
}

$holiday_id = (int)$_POST['holiday_id'];
// ... (LE RESTE DE TON FICHIER NE CHANGE PAS)
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

        // Récupération des dates de l'étape globale et du type
        $step_start = !empty($_POST['step_start_date']) ? $_POST['step_start_date'] : null;
        $step_end = !empty($_POST['step_end_date']) ? $_POST['step_end_date'] : null;
        $step_type = $_POST['step_type'] ?? 'stop';

        // Nettoyage des dates selon le type d'étape
        if ($step_type === 'origin') $step_end = null; // Un départ n'a pas de date de fin
        if ($step_type === 'destination') $step_end = null; // Une arrivée finale n'a pas de date de départ

        // 3. INSERTION DES LIGNES
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

                    $stmt->execute([$holiday_id, $cat, $name ?: tr('hdl_default_exp_name'), $amount, $paid, $location_name, $lat, $lng, $target_order, $note, $date, $time, $step_start, $step_end, $dur, $step_type, $context]);
                    $validItemsCount++;
                }
            }
        }

        if ($validItemsCount === 0) {
            $stmt->execute([$holiday_id, 'activity', 'PF_TECHNICAL_POINT', 0, 1, $location_name, $lat, $lng, $target_order, '', null, null, $step_start, $step_end, 1, $step_type, 'local']);
        }

        // 4. GESTION DU RETOUR (Si l'utilisateur définit cette étape comme point de retour)
        if (isset($_POST['set_as_return']) && $_POST['set_as_return'] == '1') {
             // On enregistre l'ID de cette étape technique comme point de retour global du voyage
             $pdo->prepare("UPDATE pf_holidays SET return_step_id = ? WHERE id = ?")->execute([$target_order, $holiday_id]);
        }

        // 5. GESTION DES FAVORIS ... (Garde ton code existant ici)

        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

header("Location: /holidays.php?tab=holiday_detail&id=" . $holiday_id);
exit;