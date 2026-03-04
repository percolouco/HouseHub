<?php
// modules/budget/includes/api/save-budget.php

require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();


$action = $_POST['action'] ?? '';

// 1. MISE A JOUR TABLEAU SALAIRES (AJAX)
if ($action === 'update_salary_config') {
    header('Content-Type: application/json'); // On précise JSON ici
    $year = $_POST['year'];
    $person = $_POST['person'];
    $field = $_POST['field']; // salary, mensualite, etc.
    $value = floatval($_POST['value']);

    // Liste des champs autorisés pour éviter les injections
    $allowed = ['salary', 'mensualite', 'frais_func', 'eco_perso', 'eco_family'];
    if (!in_array($field, $allowed)) { echo json_encode(['error'=>'Champ invalide']); exit; }

    $stmt = $pdo->prepare("INSERT INTO pf_salary_config (year, person, $field) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)");
    $stmt->execute([$year, $person, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// 2. MISE A JOUR TABLEAU REPARTITION (AJAX)
if ($action === 'update_allocation') {
    header('Content-Type: application/json'); 
    $date = $_POST['month_date'];
    $catId = $_POST['cat_id'];
    $person = $_POST['person']; // 'amount_alex' ou 'amount_laia'
    $value = floatval($_POST['value']);

    $stmt = $pdo->prepare("INSERT INTO pf_alloc_values (month_date, cat_id, $person) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $person = VALUES($person)");
    $stmt->execute([$date, $catId, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. GESTION DES CATEGORIES (Ajout)
if ($action === 'add_category') {
    $name = trim($_POST['name']);
    $target = trim($_POST['target']);
    $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO pf_alloc_categories (name, target, holiday_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $target, $holiday_id]);
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 4. MODIFICATION D'UNE CATEGORIE
if ($action === 'update_category') {
    $id = (int)$_POST['cat_id'];
    $name = trim($_POST['name']);
    $target = trim($_POST['target']);
    $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

    if ($id > 0 && !empty($name)) {
        $stmt = $pdo->prepare("UPDATE pf_alloc_categories SET name = ?, target = ?, holiday_id = ? WHERE id = ?");
        $stmt->execute([$name, $target, $holiday_id, $id]);
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 5. SUPPRESSION CATEGORIE
if ($action === 'delete_category') {
    $id = (int)$_GET['id'] ?? (int)$_POST['id']; 
    
    if ($id > 0) {
        $pdo->prepare("DELETE FROM pf_alloc_categories WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_alloc_values WHERE cat_id = ?")->execute([$id]); 
    }
    // Redirection vers la page précédente
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 6. VALIDATION DES VIREMENTS (Complex Business Logic)
if ($action === 'validate_transfers') {
    header('Content-Type: application/json');
    $person = $_POST['person'];
    $monthDate = $_POST['month_date'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT v.*, c.name as cat_name, c.target, c.holiday_id 
            FROM pf_alloc_values v 
            JOIN pf_alloc_categories c ON v.cat_id = c.id
            WHERE v.month_date = ?
        ");
        $stmt->execute([$monthDate]);
        $budgetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transfersToDo = [];

        foreach ($budgetLines as $line) {
            $amount = ($person === 'Alex') ? $line['amount_alex'] : $line['amount_laia'];
            if ($amount <= 0) continue; 

            $target = trim($line['target']);
            $catName = trim($line['cat_name']);
            $holidayId = $line['holiday_id']; 

            $targetOwner = null;
            if ($target === 'vers L.Perso') { $targetOwner = $person; } 
            elseif ($target === 'vers L.Pol') { $targetOwner = 'Pol'; } 
            elseif ($target === 'vers L.Pep') { $targetOwner = 'Pep'; } 
            elseif ($target === 'vers commune') { continue; }

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
            // A. VERIFIER EXISTENCE (Inchangé)
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

            // B. UPDATE TOTAL (Inchangé)
            $stmtUpdTotal = $pdo->prepare("UPDATE pf_savings SET amount = amount + ? WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtUpdTotal->execute([$data['total_add'], $owner, $monthDate]);

            // C. UPDATE CATÉGORIES (Modifié pour gérer le holiday_id)
            foreach ($data['cats'] as $catName => $catInfo) {
                $catAmount = $catInfo['amount'];
                $catHolidayId = $catInfo['holiday_id']; // NOUVEAU

                if ($catName === 'Eco Alex' || $catName === 'Eco Laia') { continue; }

                $stmtCheckCat = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
                $stmtCheckCat->execute([$owner, $monthDate, $catName]);
                $catId = $stmtCheckCat->fetchColumn();

                if ($catId) {
                    // Update : On actualise aussi le holiday_id au cas où il aurait changé
                    $stmtUpdateCat = $pdo->prepare("UPDATE pf_savings SET amount = amount + ?, holiday_id = ? WHERE id = ?");
                    $stmtUpdateCat->execute([$catAmount, $catHolidayId, $catId]);
                } else {
                    // Insert
                    $stmtInsertCat = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount, holiday_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsertCat->execute([$monthDate, $owner, $catName, $catAmount, $catHolidayId]);
                }
            }
        }

        // 3. ENREGISTRER LA VALIDATION (Mise à jour table existante)
        
        // a. Trouver l'ID de la catégorie système
        $stmtSys = $pdo->prepare("SELECT id FROM pf_alloc_categories WHERE name = 'SYSTEM_VALIDATION' LIMIT 1");
        $stmtSys->execute();
        $sysCatId = $stmtSys->fetchColumn();

        if ($sysCatId) {
            
            if ($person === 'Alex') {
                $sql = "INSERT INTO pf_alloc_values (month_date, cat_id, amount_alex, amount_laia) 
                        VALUES (?, ?, 1, 0) 
                        ON DUPLICATE KEY UPDATE amount_alex = 1";
            } else {
                $sql = "INSERT INTO pf_alloc_values (month_date, cat_id, amount_alex, amount_laia) 
                        VALUES (?, ?, 0, 1) 
                        ON DUPLICATE KEY UPDATE amount_laia = 1";
            }
            
            $stmtVal = $pdo->prepare($sql);
            $stmtVal->execute([$monthDate, $sysCatId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}