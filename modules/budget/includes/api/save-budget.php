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