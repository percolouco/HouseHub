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

    // GESTION INTELLIGENTE DES ITEMS
    if (!empty($_POST['items']['name'])) {
        $count = count($_POST['items']['name']);
        // On prépare une requête qui met à jour si l'ID existe, sinon insère
        $stmtItem = $pdo->prepare("
            INSERT INTO pf_holidays_items (id, holiday_id, category, name, amount, is_paid, location_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                category = VALUES(category),
                name = VALUES(name),
                amount = VALUES(amount),
                is_paid = VALUES(is_paid)
        ");
        
        $keepIds = [];
        for ($i = 0; $i < $count; $i++) {
            $itemId = !empty($_POST['items']['id'][$i]) ? (int)$_POST['items']['id'][$i] : null;
            $cat = $_POST['items']['cat'][$i] ?? 'activity';
            $name = trim($_POST['items']['name'][$i] ?? '');
            $amount = floatval($_POST['items']['amount'][$i] ?? 0);
            $paid = (int)($_POST['items']['paid'][$i] ?? 0);
            $loc = !empty($_POST['items']['location'][$i]) ? $_POST['items']['location'][$i] : null;

            if (!empty($name)) {
                $stmtItem->execute([$itemId, $id, $cat, $name, $amount, $paid, $loc]);
                $keepIds[] = $itemId ?: $pdo->lastInsertId();
            }
        }

        // Nettoyage : On supprime les items qui ont été retirés de la modale
        // (Attention : uniquement ceux du voyage actuel qui ne sont plus dans la liste envoyée)
        if (!empty($keepIds)) {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $sqlDel = "DELETE FROM pf_holidays_items WHERE holiday_id = ? AND id NOT IN ($placeholders)";
            $pdo->prepare($sqlDel)->execute(array_merge([$id], $keepIds));
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