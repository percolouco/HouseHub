<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php'; // On suppose que $pdo est défini ici
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- ACTION : SAUVEGARDER (AJOUT OU MODIF) ---
    if ($action === 'save') {
        $id          = !empty($_POST['id']) ? $_POST['id'] : null;
        $name        = $_POST['name'];
        $amount      = str_replace(',', '.', $_POST['amount']); 
        $type        = $_POST['type'];
        $day         = !empty($_POST['payment_day']) ? $_POST['payment_day'] : null;
        $month       = $_POST['reg_month'] ?: null;
        $cat         = $_POST['category'];
        $is_estimate = isset($_POST['is_estimate']) ? (int)$_POST['is_estimate'] : 0;

        if ($id) {
            // Update : on ajoute is_estimate
            $sql = "UPDATE pf_budget_items SET name=?, amount=?, type=?, payment_day=?, reg_month=?, category=?, is_estimate=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $amount, $type, $day, $month, $cat, $is_estimate, $id]);
        } else {
            // Insert : on ajoute is_estimate
            $sql = "INSERT INTO pf_budget_items (name, amount, type, payment_day, reg_month, category, is_estimate) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $amount, $type, $day, $month, $cat, $is_estimate]);
        }
        
        // Redirection après sauvegarde classique
        header('Location: /budget.php?tab=recap');
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