<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php'; 
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- ACTION : SAUVEGARDER (AJOUT OU MODIF DU BUDGET) ---
    if ($action === 'save') {
        try {
            // 1. Sécurisation des données entrantes
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $category = $_POST['category'] ?? '';
            $type = $_POST['type'] ?? 'Mensuel';
            $payment_day = !empty($_POST['payment_day']) ? (int)$_POST['payment_day'] : null;
            $is_estimate = isset($_POST['is_estimate']) ? (int)$_POST['is_estimate'] : 0;
            $reg_month = !empty($_POST['reg_month']) ? trim($_POST['reg_month']) : null;
            $keywords = trim($_POST['mapping_keywords'] ?? ''); 
            $holiday_id = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

            // 2. Gestion du montant (toujours stocker les dépenses en négatif)
            $amount = abs((float)($_POST['amount'] ?? 0)); 
            $amount = -$amount; // On force le négatif car le modal Recap ne gère que des charges

            // 3. Exécution de la requête
            if ($id) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE pf_budget_items SET name=?, amount=?, category=?, type=?, payment_day=?, is_estimate=?, reg_month=?, mapping_keywords=?, holiday_id=? WHERE id=?");
                $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id, $id]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO pf_budget_items (name, amount, category, type, payment_day, is_estimate, reg_month, mapping_keywords, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $amount, $category, $type, $payment_day, $is_estimate, $reg_month, $keywords, $holiday_id]);
            }
            
            // 4. Réponse appropriée (JSON si appel via JS fetch, Redirection sinon)
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                header('Location: /budget.php?tab=recap');
                exit;
            }

        } catch (PDOException $e) {
            // En cas d'erreur BDD (ex: contrainte de clé étrangère, colonne manquante)
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erreur BDD : ' . $e->getMessage()]);
                exit;
            }
            die("Erreur fatale : " . $e->getMessage());
        }
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

    // --- ACTION : SUPPRIMER UNE LIGNE DU BUDGET ---
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

    // --- ACTION : SUPPRIMER UNE DÉPENSE RÉELLE (Depuis l'onglet Suivi) ---
    if ($action === 'delete_expense') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM pf_expenses WHERE id = ?")->execute([$id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTION : SAUVEGARDER UNE DÉPENSE MANUELLE (FETCH) ---
    if ($action === 'save_expense_manual') {
        header('Content-Type: application/json'); 
        
        try {
            $id = !empty($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;
            $cat = $_POST['category'] ?? ''; 
            $amount = floatval($_POST['amount'] ?? 0); 
            $date = $_POST['date'] ?? date('Y-m-d');
            
            // Format attendu : YYYY-MM
            $gestionMonthRaw = $_POST['gestion_month'] ?? '';
            
            // Si la valeur est vide ou invalide
            if (empty($gestionMonthRaw) || strpos($gestionMonthRaw, '0000') !== false) {
                $gestionMonth = date('Y-m-01', strtotime($date));
            } else {
                $gestionMonth = (strlen($gestionMonthRaw) === 7) ? $gestionMonthRaw . '-01' : $gestionMonthRaw;
            }
            
            $label = trim($_POST['label'] ?? '');
            $budgetItemId = null;
            $holidayId = !empty($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : null;

            if ($cat === 'School' && !empty($_POST['label_select'])) {
                $label = trim($_POST['label_select']);
            } elseif (($cat === 'Frais' || $cat === 'Income') && !empty($_POST['budget_item_id'])) {
                $budgetItemId = (int)$_POST['budget_item_id'];
            }

            // Vérification de sécurité
            if (empty($label) || $amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'Le label et un montant supérieur à 0 sont obligatoires.']);
                exit;
            }

            $is_credit = isset($_POST['is_credit']) ? (int)$_POST['is_credit'] : 0;
            $finalAmount = $is_credit ? abs($amount) : -abs($amount);
            
            if ($id) {
                // UPDATE
                $pdo->prepare("UPDATE pf_expenses SET date_exp=?, gestion_month=?, category=?, label=?, amount=?, budget_item_id=?, holiday_id=? WHERE id=?")
                    ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $budgetItemId, $holidayId, $id]);
            } else {
                // INSERT
                $uniqueRef = "MANUAL_" . uniqid();
                $pdo->prepare("INSERT INTO pf_expenses (date_exp, gestion_month, category, label, amount, import_ref, budget_item_id, holiday_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$date, $gestionMonth, $cat, $label, $finalAmount, $uniqueRef, $budgetItemId, $holidayId]);
            }
            
            echo json_encode(['success' => true]);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
            exit;
        }
    }
}