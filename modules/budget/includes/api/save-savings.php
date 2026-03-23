<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

// =================================================================
// MISE À JOUR D'UNE CELLULE EN DIRECT (AJAX)
// =================================================================
if ($action === 'update_single_entry') {
    header('Content-Type: application/json');
    $month = $_POST['month_date'];
    $cat = $_POST['category'];
    $owner = $_POST['owner'];
    $amount = (float)$_POST['amount'];

    try {
        if ($amount == 0 && $cat !== 'TOTAL_BANQUE') {
            // Si on met à 0 une ligne (autre que le total), on supprime l'entrée pour garder la base propre
            $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE month_date=? AND owner=? AND category=?");
            $stmt->execute([$month, $owner, $cat]);
        } else {
            // Sinon on insère ou on met à jour
            $stmt = $pdo->prepare("INSERT INTO pf_savings (month_date, owner, category, amount) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            $stmt->execute([$month, $owner, $cat, $amount]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SUPPRESSION D'UNE ENTRÉE UNIQUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_entry') {
    $owner = $_POST['owner'];
    $date = $_POST['month_date'];
    $cat = $_POST['category'];

    $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
    $stmt->execute([$owner, $date, $cat]);
    
    echo json_encode(['success' => true]);
    exit;
}

// --- ACTION : DUPLICATION DE MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate_month') {
    $owner = $_POST['owner'];
    $sourceDate = $_POST['source_date'];
    $targetDate = $_POST['target_date'];
    $newTotal = floatval($_POST['new_total']);

    try {
        $pdo->beginTransaction();

        // 1. Vérifier si le mois cible existe déjà
        $check = $pdo->prepare("SELECT id FROM pf_savings WHERE owner = ? AND month_date = ? LIMIT 1");
        $check->execute([$owner, $targetDate]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ce mois existe déjà !']);
            exit;
        }

        // 2. Insérer le nouveau TOTAL BANQUE
        $stmtIns = $pdo->prepare("INSERT INTO pf_savings (owner, month_date, category, amount) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$owner, $targetDate, 'TOTAL_BANQUE', $newTotal]);

        // 3. Copier toutes les catégories (sauf TOTAL_BANQUE) du mois source
        $sqlCopy = "INSERT INTO pf_savings (owner, month_date, category, amount)
                    SELECT owner, ?, category, amount 
                    FROM pf_savings 
                    WHERE owner = ? AND month_date = ? AND category != 'TOTAL_BANQUE'";
        
        $stmtCopy = $pdo->prepare($sqlCopy);
        $stmtCopy->execute([$targetDate, $owner, $sourceDate]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SUPPRESSION GLOBALE D'UN MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_month_global') {
    $owner = $_POST['owner'];
    $date = $_POST['month_date'];

    try {
        $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ?");
        $stmt->execute([$owner, $date]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SAUVEGARDE CLASSIQUE (MODALE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner = $_POST['owner'];
    
    // CORRECTION ICI : On récupère l'onglet de redirection (Nens, Alex ou Laia)
    $redirectTab = $_POST['redirect_tab'] ?? $owner; 
    
    $dateInput = $_POST['month_date']; 
    $dateObj = new DateTime($dateInput);
    $monthDate = $dateObj->format('Y-m-01');
    $values = $_POST['values'] ?? []; // Tableau généré par nos champs JS: values[Catégorie]

    try {
        $pdo->beginTransaction();
        
        // On supprime d'abord les anciennes données du mois pour cet utilisateur
        $stmtDel = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ?");
        $stmtDel->execute([$owner, $monthDate]);
        
        // On réinsère les nouvelles données
        $stmtIns = $pdo->prepare("INSERT INTO pf_savings (owner, month_date, category, amount) VALUES (?, ?, ?, ?)");
        foreach ($values as $category => $amount) {
            $amount = floatval($amount);
            // On enregistre si c'est positif OU si c'est le total banque
            if ($amount > 0 || $category === 'TOTAL_BANQUE') {
                $stmtIns->execute([$owner, $monthDate, $category, $amount]);
            }
        }
        $pdo->commit();
        
        // Redirection vers le bon onglet
        header("Location: /budget.php?tab=epargne&owner=" . urlencode($redirectTab));        
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur : " . $e->getMessage());
    }
}