<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

// 1. Déclaration propre des variables d'entrée
$action = $_POST['action'] ?? '';
$isAjax = !empty($_POST['ajax']);

// Si c'est de l'AJAX, on force les en-têtes HTTP pour garantir du JSON
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

// =================================================================
// MISE À JOUR D'UNE CELLULE EN DIRECT (AJAX)
// =================================================================
if ($action === 'update_single_entry') {
    $month = $_POST['month_date'] ?? '';
    $cat = $_POST['category'] ?? '';
    $owner = $_POST['owner'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_entry') {
    $owner = $_POST['owner'] ?? '';
    $date = $_POST['month_date'] ?? '';
    $cat = $_POST['category'] ?? '';

    $stmt = $pdo->prepare("DELETE FROM pf_savings WHERE owner = ? AND month_date = ? AND category = ?");
    $stmt->execute([$owner, $date, $cat]);
    
    echo json_encode(['success' => true]);
    exit;
}

// --- ACTION : DUPLICATION DE MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'duplicate_month') {
    $owner = $_POST['owner'] ?? '';
    $sourceDate = $_POST['source_date'] ?? '';
    $targetDate = $_POST['target_date'] ?? '';
    $newTotal = floatval($_POST['new_total'] ?? 0);

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
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION : SUPPRESSION GLOBALE D'UN MOIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_month_global') {
    $owner = $_POST['owner'] ?? '';
    $date = $_POST['month_date'] ?? '';

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
// Cette action s'exécute si c'est un POST général sans action précise (le form HTML) ou l'action explicitement nommée.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save_modal', ''])) {
    $owner = $_POST['owner'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? $owner; 
    
    $dateInput = $_POST['month_date'] ?? ''; 
    $values = $_POST['values'] ?? []; // Tableau généré par nos champs JS: values[Catégorie]

    try {
        $dateObj = new DateTime($dateInput);
        $monthDate = $dateObj->format('Y-m-01');

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
        
        // On répond proprement selon le contexte (AJAX ou Fallback classique)
        if ($isAjax) {
            echo json_encode(['success' => true]);
        } else {
            header("Location: /budget.php?tab=epargne&owner=" . urlencode($redirectTab));        
        }
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } else {
            die("Erreur : " . $e->getMessage());
        }
        exit;
    }
}

// Sécurité finale : Si l'action n'est pas reconnue
if ($isAjax) {
    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}