<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php'; 
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
        
        $keywords = $_POST['mapping_keywords'] ?? ''; 
        
        // NOUVEAU : Récupération de l'ID des vacances
        $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE pf_budget_items SET name=?, amount=?, category=?, type=?, payment_day=?, is_estimate=?, reg_month=?, mapping_keywords=?, holiday_id=? WHERE id=?");
            $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id, $id]);
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO pf_budget_items (name, amount, category, type, payment_day, is_estimate, reg_month, mapping_keywords, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id]);
        }
        header('Location: /budget.php?tab=recap');
        exit;
    }

    // --- ACTION : COCHER/DÉCOCHER RAPIDE (VIA JS FETCH) ---
    if ($action === 'toggle-check') {
        $id     = $_POST['id'];
        $status = $_POST['status']; 
        
        $sql = "UPDATE pf_budget_items SET is_checked = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTION : SUPPRIMER ---
    if ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM pf_budget_items WHERE id = ?")->execute([$id]);
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Location: /budget.php?tab=recap');
        }
        exit;
    }
}