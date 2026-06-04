<?php
// modules/budget/includes/api/save-budget.php

require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

$action = $_POST['action'] ?? '';

// =================================================================
// 7. SAUVEGARDE D'UNE NOTE GÉNÉRIQUE (pf_notes)
// =================================================================
if ($action === 'save_note') {
    // On affiche les erreurs s'il y a un souci SQL pour pouvoir déboguer
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    header('Content-Type: application/json');
    
    try {
        $noteType = $_POST['note_type'] ?? '';
        $refId = $_POST['reference_id'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($noteType) || empty($refId)) {
            throw new Exception("Le type et la référence de la note sont requis.");
        }

        // Insère ou met à jour la note si elle existe déjà
        $stmt = $pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE content = VALUES(content)");
        $stmt->execute([$noteType, $refId, $content]);
        
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $e->getMessage()]);
    }
    exit;
}
// =================================================================

// 1. MISE A JOUR TABLEAU SALAIRES (AJAX)
if ($action === 'update_salary_config') {
    header('Content-Type: application/json');
    $year = $_POST['year'];
    $person = $_POST['person'];
    $field = $_POST['field']; // salary, mensualite, etc.
    $value = floatval($_POST['value']);

    // Liste des champs autorisés pour éviter les injections
    $allowed = ['salary', 'mensualite', 'frais_func', 'eco_perso', 'eco_family'];
    if (!in_array($field, $allowed)) { 
        echo json_encode(['success' => false, 'error' => 'Champ invalide']); 
        exit; 
    }

    $stmt = $pdo->prepare("INSERT INTO pf_salary_config (year, person, $field) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)");
    $stmt->execute([$year, $person, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// 2. MISE A JOUR TABLEAU REPARTITION (AJAX)
if ($action === 'update_allocation') {
    header('Content-Type: application/json');
    $date     = $_POST['month_date'];
    $catId    = (int)$_POST['cat_id'];
    $personId = (int)$_POST['person_id']; 
    $value    = floatval($_POST['value']);

    if ($catId <= 0 || $personId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Données invalides.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO pf_alloc_values (month_date, cat_id, person_id, amount) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
    $stmt->execute([$date, $catId, $personId, $value]);
    
    echo json_encode(['success' => true]);
    exit;
}

// 3. GESTION DES CATEGORIES (Ajout)
if ($action === 'add_category') {
    try {
        $name = trim($_POST['name']);
        $transfer_dest = trim($_POST['transfer_dest'] ?? '');
        $target = floatval($_POST['target'] ?? 0);
        $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO pf_alloc_categories (name, target, transfer_dest, holiday_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $target, $transfer_dest, $holiday_id]);
        }

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        die($e->getMessage());
    }
}

// 4. MODIFICATION D'UNE CATEGORIE
if ($action === 'update_category') {
    try {
        $id = (int)$_POST['cat_id'];
        $name = trim($_POST['name']);
        $transfer_dest = trim($_POST['transfer_dest'] ?? '');
        $target = floatval($_POST['target'] ?? 0);
        $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

        if ($id > 0 && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE pf_alloc_categories SET name = ?, target = ?, transfer_dest = ?, holiday_id = ? WHERE id = ?");
            $stmt->execute([$name, $target, $transfer_dest, $holiday_id, $id]);
        }

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        die($e->getMessage());
    }
}

// 5. SUPPRESSION CATEGORIE
if ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0); 
    
    if ($id > 0) {
        $pdo->prepare("DELETE FROM pf_alloc_categories WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_alloc_values WHERE cat_id = ?")->execute([$id]); 
    }
    
    if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/budget.php')); 
    exit;
}

// 6. VALIDATION DES VIREMENTS (Complex Business Logic)
if ($action === 'validate_transfers') {
    header('Content-Type: application/json');
    $personId  = (int)$_POST['person_id']; // Reçoit l'ID parent
    $monthDate = $_POST['month_date'];

    try {
        $pdo->beginTransaction();

        $stmtP = $pdo->prepare("SELECT name FROM pf_people WHERE id = ?");
        $stmtP->execute([$personId]);
        $dbPersonName = $stmtP->fetchColumn();
        if (!$dbPersonName) throw new Exception("Parent introuvable.");

        $stmt = $pdo->prepare("
            SELECT v.amount, c.name as cat_name, c.transfer_dest, c.holiday_id
            FROM pf_alloc_values v
            JOIN pf_alloc_categories c ON v.cat_id = c.id
            WHERE v.month_date = ? AND v.person_id = ?
        ");
        $stmt->execute([$monthDate, $personId]);
        $budgetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transfersToDo = [];

        foreach ($budgetLines as $line) {
            $amount = (float)$line['amount'];
            if ($amount <= 0) continue;

            $dest = trim($line['transfer_dest']);
            $catName = trim($line['cat_name']);
            $holidayId = $line['holiday_id'];

            $targetOwner = null;
            if ($dest === 'vers L.Perso') { $targetOwner = $dbPersonName; }
            elseif ($dest === 'vers L.Pol') { $targetOwner = 'Pol'; }
            elseif ($dest === 'vers L.Pep') { $targetOwner = 'Pep'; }
            elseif ($dest === 'vers commune') { continue; }

            if ($targetOwner) {
                if (!isset($transfersToDo[$targetOwner])) {
                    $transfersToDo[$targetOwner] = ['total_add' => 0, 'cats' => []];
                }
                $transfersToDo[$targetOwner]['total_add'] += $amount;

                if (!isset($transfersToDo[$targetOwner]['cats'][$catName])) {
                    $transfersToDo[$targetOwner]['cats'][$catName] = ['amount' => 0, 'holiday_id' => $holidayId];
                }
                $transfersToDo[$targetOwner]['cats'][$catName]['amount'] += $amount;
            }
        }

        foreach ($transfersToDo as $owner => $data) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pf_savings WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtCheck->execute([$owner, $monthDate]);
            $exists = $stmtCheck->fetchColumn() > 0;

            if (!$exists) {
                $prevDate = date('Y-m-d', strtotime($monthDate . ' -1 month'));
                $stmtPrev = $pdo->prepare("SELECT category, amount, holiday_id FROM pf_savings WHERE owner = ? AND month_date = ?");
                $stmtPrev->execute([$owner, $prevDate]);
                $prevLines = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

                if (empty($prevLines)) { $prevLines = [['category' => 'TOTAL_BANQUE', 'amount' => 0, 'holiday_id' => null]]; }

                $stmtInsert = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount, holiday_id) VALUES (?, ?, ?, ?, ?)");
                foreach ($prevLines as $row) {
                    $stmtInsert->execute([$monthDate, $owner, $row['category'], $row['amount'], $row['holiday_id']]);
                }
            }

            $stmtUpdTotal = $pdo->prepare("UPDATE pf_savings SET amount = amount + ? WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtUpdTotal->execute([$data['total_add'], $owner, $monthDate]);

            foreach ($data['cats'] as $catName => $catInfo) {
                $catAmount = $catInfo['amount'];
                $catHolidayId = $catInfo['holiday_id'];

                if (strpos($catName, 'Eco P') === 0) { continue; }

                $stmtCheckCat = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
                $stmtCheckCat->execute([$owner, $monthDate, $catName]);
                $catId = $stmtCheckCat->fetchColumn();

                if ($catId) {
                    $stmtUpdateCat = $pdo->prepare("UPDATE pf_savings SET amount = amount + ?, holiday_id = ? WHERE id = ?");
                    $stmtUpdateCat->execute([$catAmount, $catHolidayId, $catId]);
                } else {
                    $stmtInsertCat = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount, holiday_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsertCat->execute([$monthDate, $owner, $catName, $catAmount, $catHolidayId]);
                }
            }
        }

        $stmtSys = $pdo->prepare("SELECT id FROM pf_alloc_categories WHERE name = 'SYSTEM_VALIDATION' LIMIT 1");
        $stmtSys->execute();
        $sysCatId = $stmtSys->fetchColumn();

        if ($sysCatId) {
            $stmtVal = $pdo->prepare("INSERT INTO pf_alloc_values (month_date, cat_id, person_id, amount)
                                      VALUES (?, ?, ?, 1)
                                      ON DUPLICATE KEY UPDATE amount = 1");
            $stmtVal->execute([$monthDate, $sysCatId, $personId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =================================================================
// 7. GESTION DES AVANCES / TRICOUNT (pf_advances)
// =================================================================

if ($action === 'save_advance') {
    header('Content-Type: application/json');
    try {
        $payer        = trim($_POST['payer'] ?? '');
        $advance_date = $_POST['advance_date'] ?? date('Y-m-d');
        $description  = trim($_POST['description'] ?? '');
        $amount       = abs((float)($_POST['amount'] ?? 0));
        $from_savings = isset($_POST['from_savings']) ? 1 : 0;

        if (empty($payer) || empty($description) || $amount <= 0) {
            throw new Exception("Champs obligatoires manquants ou invalides.");
        }

        $stmt = $pdo->prepare("INSERT INTO pf_advances (advance_date, payer, description, amount, from_savings) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$advance_date, $payer, $description, $amount, $from_savings]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_advance') {
    header('Content-Type: application/json');
    try {
        $id           = (int)($_POST['id'] ?? 0);
        $payer        = trim($_POST['payer'] ?? '');
        $advance_date = $_POST['advance_date'] ?? date('Y-m-d');
        $description  = trim($_POST['description'] ?? '');
        $amount       = abs((float)($_POST['amount'] ?? 0));
        $from_savings = isset($_POST['from_savings']) ? 1 : 0;

        if ($id <= 0 || empty($payer) || empty($description) || $amount <= 0) {
            throw new Exception("Paramètres invalides fournis.");
        }

        $stmt = $pdo->prepare("UPDATE pf_advances SET advance_date = ?, payer = ?, description = ?, amount = ?, from_savings = ? WHERE id = ?");
        $stmt->execute([$advance_date, $payer, $description, $amount, $from_savings, $id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'resolve_advance') {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception("ID invalide fourni.");

        $stmt = $pdo->prepare("UPDATE pf_advances SET is_resolved = 1 WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_advance') {
    header('Content-Type: application/json');
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception("ID invalide.");

        $stmt = $pdo->prepare("DELETE FROM pf_advances WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}