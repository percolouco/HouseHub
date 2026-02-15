<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php'; // On suppose que $pdo est défini ici
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- ACTION : SAUVEGARDER (AJOUT OU MODIF) ---
    if ($action === 'save') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $type = $_POST['type'];
    $payment_day = $_POST['payment_day'];
    $is_estimate = $_POST['is_estimate'];
    $reg_month = $_POST['reg_month'];
    
    // NOUVEAU : Récupération des mots-clés
    $keywords = $_POST['mapping_keywords'] ?? ''; 

    if ($id) {
        // UPDATE
        $stmt = $pdo->prepare("UPDATE pf_budget_items SET name=?, amount=?, category=?, type=?, payment_day=?, is_estimate=?, reg_month=?, mapping_keywords=? WHERE id=?");
        $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $id]);
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO pf_budget_items (name, amount, category, type, payment_day, is_estimate, reg_month, mapping_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords]);
    }
    header('Location: /budget.php?tab=recap'); // Ou ta redirection habituelle
    exit;
}

    // --- ACTION : COCHER/DÉCOCHER RAPIDE (VIA JS FETCH) ---
    if ($action === 'toggle-check') {
        $id     = $_POST['id'];
        $status = $_POST['status']; // 1 ou 0
        
        $sql = "UPDATE pf_budget_items SET is_checked = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $id]);
        
        // On renvoie du JSON car c'est un appel JS (pas de redirection)
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTION : SUPPRIMER ---
    if ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM pf_budget_items WHERE id = ?")->execute([$id]);
        
        // On peut répondre en JSON pour le JS ou rediriger
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Location: /budget.php?tab=recap');
        }
        exit;
    }
}