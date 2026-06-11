<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

try {
    // ─── 1. RECUPÉRATION COMPLÈTE DES PARAMÈTRES ───
    if ($action === 'get_all') {
        // A. Options globales du foyer
        $stmtFoyer = $pdo->query("SELECT zone_scolaire, care_modes FROM pf_foyer_settings LIMIT 1");
        $foyer = $stmtFoyer->fetch(PDO::FETCH_ASSOC) ?: ['zone_scolaire' => 'C', 'care_modes' => '["Nounou","Centre"]'];
        
        // B. Liste des membres de la famille
        $stmtPeople = $pdo->query("SELECT id, name, role FROM pf_people WHERE is_active = 1 ORDER BY role DESC, name ASC");
        $people = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

        // C. Matrice des congés par personne
        $stmtLeaves = $pdo->query("SELECT person_id, leave_type, anniversary_date FROM pf_person_leave_meta");
        $leavesRaw = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
        
        // On organise les congés par ID de personne pour faciliter le traitement côté JS
        $leavesMap = [];
        foreach ($leavesRaw as $leave) {
            $leavesMap[$leave['person_id']][] = [
                'type'      => $leave['leave_type'],
                'method'    => $leave['method'] ?? 'FIXED',       
                'allowance' => $leave['allowance'] ?? 0,          
                'date'      => $leave['anniversary_date']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'foyer' => [
                    'zone_scolaire' => $foyer['zone_scolaire'],
                    'care_modes'    => json_decode($foyer['care_modes'] ?? '[]', true)
                ],
                'people' => $people,
                'leaves' => $leavesMap
            ]
        ]);
        exit;
    }

    // ─── 2. SAUVEGARDE DU FOYER (ONGLET 1) ───
    if ($action === 'save_foyer') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Error("Méthode non autorisée");

        $zone = trim($_POST['zone_scolaire'] ?? 'C');
        $modesRaw = $_POST['care_modes'] ?? '[]';
        
        $modesArray = json_encode(array_values(array_unique(array_filter(json_decode($modesRaw, true)))));

        $stmt = $pdo->prepare("UPDATE pf_foyer_settings SET zone_scolaire = ?, care_modes = ? WHERE id = 1");
        $stmt->execute([$zone, $modesArray]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ─── 3. SAUVEGARDE DES CONGÉS D'UN MEMBRE (ONGLET 2) ───
    if ($action === 'save_member_leaves') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Error("Méthode non autorisée");

        $personId = (int)($_POST['person_id'] ?? 0);
        $leavesData = json_decode($_POST['leaves'] ?? '[]', true);

        if ($personId <= 0) throw new Exception("Identifiant de membre invalide.");

        $pdo->beginTransaction();

        // On nettoie les anciennes configurations de congés de cette personne
        $stmtDelete = $pdo->prepare("DELETE FROM pf_person_leave_meta WHERE person_id = ?");
        $stmtDelete->execute([$personId]);

        // On réinsère la nouvelle matrice propre
        if (!empty($leavesData)) {
            $stmtInsert = $pdo->prepare("INSERT INTO pf_person_leave_meta (person_id, leave_type, method, allowance, anniversary_date) VALUES (?, ?, ?, ?, ?)");
            foreach ($leavesData as $leave) {
                $type = strtoupper(trim($leave['type']));
                $method = in_array($leave['method'], ['FIXED', 'ACCUMULATED']) ? $leave['method'] : 'FIXED';
                $allowance = (float)($leave['allowance'] ?? 0);
                $date = trim($leave['date']);
                
                if (!empty($type) && !empty($date)) {
                    $stmtInsert->execute([$personId, $type, $method, $allowance, $date]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Action inconnue");

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}