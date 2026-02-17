<?php
// modules/budget/includes/api/save-budget.php

require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

// Note : On ne force pas le header JSON partout car add/delete/update utilisent des redirections
// header('Content-Type: application/json'); 

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
    header('Content-Type: application/json'); // On précise JSON ici
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
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO pf_alloc_categories (name, target) VALUES (?, ?)");
        $stmt->execute([$name, $target]);
    }
    // Redirection vers la page précédente
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 4. MODIFICATION D'UNE CATEGORIE (Nom / Cible) - NOUVEAU BLOC
if ($action === 'update_category') {
    $id = (int)$_POST['cat_id'];
    $name = trim($_POST['name']);
    $target = trim($_POST['target']);

    if ($id > 0 && !empty($name)) {
        $stmt = $pdo->prepare("UPDATE pf_alloc_categories SET name = ?, target = ? WHERE id = ?");
        $stmt->execute([$name, $target, $id]);
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 5. SUPPRESSION CATEGORIE
if ($action === 'delete_category') {
    $id = (int)$_GET['id'] ?? (int)$_POST['id']; // Peut venir du GET (lien) ou POST
    
    if ($id > 0) {
        $pdo->prepare("DELETE FROM pf_alloc_categories WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_alloc_values WHERE cat_id = ?")->execute([$id]); // Nettoyage valeurs
    }
    // Redirection vers la page précédente
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 6. VALIDATION DES VIREMENTS (Complex Business Logic)
if ($action === 'validate_transfers') {
    header('Content-Type: application/json');
    
    $person = $_POST['person']; // Alex ou Laia
    $monthDate = $_POST['month_date'];

    try {
        $pdo->beginTransaction();

        // 1. Récupérer tous les virements prévus pour ce mois/personne dans le BUDGET
        // On joint avec les catégories pour avoir le nom et la cible
        $stmt = $pdo->prepare("
            SELECT v.*, c.name as cat_name, c.target 
            FROM pf_alloc_values v 
            JOIN pf_alloc_categories c ON v.cat_id = c.id
            WHERE v.month_date = ?
        ");
        $stmt->execute([$monthDate]);
        $budgetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // On prépare les totaux à transférer par Cible
        // Structure : ['Alex' => ['total'=>100, 'cats'=>['Noel'=>50, 'Eco'=>50]], 'Pol' => ...]
        $transfersToDo = [];

        foreach ($budgetLines as $line) {
            $amount = ($person === 'Alex') ? $line['amount_alex'] : $line['amount_laia'];
            if ($amount <= 0) continue; // Rien à virer

            $target = trim($line['target']);
            $catName = trim($line['cat_name']);

            // MAPPING DES PROPRIÉTAIRES CIBLES
            $targetOwner = null;
            if ($target === 'vers L.Perso') {
                $targetOwner = $person; // Alex -> Alex, Laia -> Laia
            } elseif ($target === 'vers L.Pol') {
                $targetOwner = 'Pol';
            } elseif ($target === 'vers L.Pep') {
                $targetOwner = 'Pep';
            } elseif ($target === 'vers commune') {
                continue; // On ignore (Business Rule)
            }

            if ($targetOwner) {
                if (!isset($transfersToDo[$targetOwner])) {
                    $transfersToDo[$targetOwner] = ['total_add' => 0, 'cats' => []];
                }
                $transfersToDo[$targetOwner]['total_add'] += $amount;
                
                if (!isset($transfersToDo[$targetOwner]['cats'][$catName])) {
                    $transfersToDo[$targetOwner]['cats'][$catName] = 0;
                }
                $transfersToDo[$targetOwner]['cats'][$catName] += $amount;
            }
        }

        // 2. Traiter chaque Propriétaire Cible (Alex, Laia, Pol, Pep)
        foreach ($transfersToDo as $owner => $data) {
            
            // A. VÉRIFIER SI LE MOIS EXISTE EN EPARGNE
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pf_savings WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtCheck->execute([$owner, $monthDate]);
            $exists = $stmtCheck->fetchColumn() > 0;

            if (!$exists) {
                // SCENARIO : LE MOIS N'EXISTE PAS -> DUPLICATION DEPUIS M-1
                $prevDate = date('Y-m-d', strtotime($monthDate . ' -1 month'));
                
                // Récup M-1
                $stmtPrev = $pdo->prepare("SELECT category, amount FROM pf_savings WHERE owner = ? AND month_date = ?");
                $stmtPrev->execute([$owner, $prevDate]);
                $prevLines = $stmtPrev->fetchAll(PDO::FETCH_KEY_PAIR); // [Cat => Montant]

                if (empty($prevLines)) {
                    // Si M-1 n'existe pas non plus, on initialise à 0 (ou on lève une erreur selon préférence)
                    $prevLines = ['TOTAL_BANQUE' => 0];
                }

                // Insertion M (Copie de M-1)
                $stmtInsert = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount) VALUES (?, ?, ?, ?)");
                foreach ($prevLines as $cat => $amt) {
                    $stmtInsert->execute([$monthDate, $owner, $cat, $amt]);
                }
            }

            // B. MISE À JOUR DU TOTAL_BANQUE
            // On ajoute le montant du virement au montant existant (qu'il vienne d'être créé ou non)
            $stmtUpdTotal = $pdo->prepare("UPDATE pf_savings SET amount = amount + ? WHERE owner = ? AND month_date = ? AND category = 'TOTAL_BANQUE'");
            $stmtUpdTotal->execute([$data['total_add'], $owner, $monthDate]);

            // C. MISE À JOUR / CRÉATION DES CATÉGORIES
            foreach ($data['cats'] as $catName => $catAmount) {
                // On utilise ON DUPLICATE KEY UPDATE pour gérer "Créer ou Sommer" en une seule requête
                // Note: category est-il unique par (month, owner)? Si ta table pf_savings n'a pas de clé unique là-dessus, il faut faire un SELECT avant.
                // Supposons qu'il n'y a pas de contrainte UNIQUE stricte, faisons le check manuel PHP pour être sûr.
                
                $stmtCheckCat = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
                $stmtCheckCat->execute([$owner, $monthDate, $catName]);
                $catId = $stmtCheckCat->fetchColumn();

                if ($catId) {
                    // Update : Sommer
                    $stmtUpdateCat = $pdo->prepare("UPDATE pf_savings SET amount = amount + ? WHERE id = ?");
                    $stmtUpdateCat->execute([$catAmount, $catId]);
                } else {
                    // Insert : Créer
                    $stmtInsertCat = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount) VALUES (?, ?, ?, ?)");
                    $stmtInsertCat->execute([$monthDate, $owner, $catName, $catAmount]);
                }
            }
        }

        // 3. ENREGISTRER LA VALIDATION (Mise à jour table existante)
        
        // a. Trouver l'ID de la catégorie système
        $stmtSys = $pdo->prepare("SELECT id FROM pf_alloc_categories WHERE name = 'SYSTEM_VALIDATION' LIMIT 1");
        $stmtSys->execute();
        $sysCatId = $stmtSys->fetchColumn();

        if ($sysCatId) {
            // b. Mettre à jour la valeur (1 = Validé)
            // On utilise une astuce SQL : on met à jour uniquement la colonne de la personne concernée
            // Si la ligne n'existe pas, on l'insère avec 1 pour la personne et 0 pour l'autre.
            
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