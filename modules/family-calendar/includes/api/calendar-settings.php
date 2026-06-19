<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

try {
    // ─── 1. RECUPÉRATION COMPLÈTE DES PARAMÈTRES (INITIALISATION) ───
    if ($action === 'get_all') {
        $stmtFoyer = $pdo->query("SELECT zone_scolaire, care_modes FROM pf_foyer_settings LIMIT 1");
        $foyer = $stmtFoyer->fetch(PDO::FETCH_ASSOC) ?: ['zone_scolaire' => 'C', 'care_modes' => '[]'];
        
        $stmtPeople = $pdo->query("SELECT id, name, role, care_modes, color FROM pf_people WHERE is_active = 1 ORDER BY role ASC, name ASC");
        $people = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

        $stmtLeaves = $pdo->query("SELECT person_id, leave_type, anniversary_date, method, allowance FROM pf_person_leave_meta");
        $leavesRaw = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
        
        $leavesMap = [];
        foreach ($leavesRaw as $leave) {
            $leavesMap[$leave['person_id']][] = [
                'type'      => $leave['leave_type'],
                'method'    => $leave['method'] ?? 'FIXED',       
                'allowance' => $leave['allowance'] ?? 0,          
                'date'      => $leave['anniversary_date']
            ];
        }

        $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM pf_settings WHERE module = 'calendar'");
        $calendarSettingsRaw = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $calendarSettings = [
            'calendar_default_view'  => $calendarSettingsRaw['calendar_default_view'] ?? 'month',
            'calendar_first_day'     => $calendarSettingsRaw['calendar_first_day'] ?? '1',
            'calendar_working_hours' => $calendarSettingsRaw['calendar_working_hours'] ?? '08:00-19:00'
        ];

        // Catalogue des congés
        $stmtLeaveTypes = $pdo->query("SELECT code, label, default_allowance, reset_month, allow_carry_over FROM pf_leave_types ORDER BY label ASC");
        $leaveTypes = $stmtLeaveTypes->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'foyer' => [
                    'zone_scolaire' => $foyer['zone_scolaire'],
                    'care_modes'    => json_decode($foyer['care_modes'] ?? '[]', true)
                ],
                'people' => $people,
                'leaves' => $leavesMap,
                'calendar_settings' => $calendarSettings,
                'leave_types' => $leaveTypes
            ]
        ]);
        exit;
    }

    // ─── 2. SAUVEGARDE DU FOYER (ONGLET 1) ───
    if ($action === 'save_foyer') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");

        $zone = trim($_POST['zone_scolaire'] ?? 'C');
        $modesRaw = $_POST['care_modes'] ?? '[]';
        $modesArray = json_encode(array_values(array_unique(array_filter(json_decode($modesRaw, true)))));

        $stmt = $pdo->prepare("UPDATE pf_foyer_settings SET zone_scolaire = ?, care_modes = ? WHERE id = 1");
        $stmt->execute([$zone, $modesArray]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ─── 3. SAUVEGARDE DES MODES DE GARDE D'UN ENFANT ───
    if ($action === 'save_child_care_modes') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");

        $person_id = (int)($_POST['person_id'] ?? 0);
        $care_modes = $_POST['care_modes'] ?? '[]';
        
        if ($person_id > 0) {
            $stmt = $pdo->prepare("UPDATE pf_people SET care_modes = ? WHERE id = ?");
            $stmt->execute([$care_modes, $person_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de l\'enfant manquant.']);
        }
        exit;
    }

    // ─── 4. GESTION DU CATALOGUE DE CONGÉS (FOYER) ───
    if ($action === 'get_leave_types') {
        $stmt = $pdo->query("SELECT * FROM pf_leave_types ORDER BY label ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save_leave_type') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");
        
        $mode = $_POST['mode'] ?? 'add';
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $label = trim($_POST['label'] ?? '');

        if (empty($code) || empty($label)) throw new Exception("Le code et le label sont obligatoires.");

        if ($mode === 'add') {
            $stmt = $pdo->prepare("INSERT INTO pf_leave_types (code, label) VALUES (?, ?)");
            $stmt->execute([$code, $label]);
        } else {
            $stmt = $pdo->prepare("UPDATE pf_leave_types SET label = ? WHERE code = ?");
            $stmt->execute([$label, $code]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_leave_type') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");
        
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (empty($code)) throw new Exception("Code du congé manquant.");

        $pdo->beginTransaction();

        // 1. On supprime d'abord ce congé chez tous les membres à qui on l'avait attribué
        $stmtMeta = $pdo->prepare("DELETE FROM pf_person_leave_meta WHERE leave_type = ?");
        $stmtMeta->execute([$code]);

        // 2. Ensuite, on supprime le congé du catalogue global
        $stmtCat = $pdo->prepare("DELETE FROM pf_leave_types WHERE code = ?");
        $stmtCat->execute([$code]);

        $pdo->commit();
        
        echo json_encode(['success' => true]);
        exit;
    }

    // ─── 5. GESTION DES CONGÉS INDIVIDUELS (MEMBRES) ───
    if ($action === 'get_person_leaves') {
        $personId = (int)($_GET['person_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, leave_type, allowance, anniversary_date FROM pf_person_leave_meta WHERE person_id = ? ORDER BY leave_type ASC");
        $stmt->execute([$personId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'add_person_leave') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");

        $personId = (int)($_POST['person_id'] ?? 0);
        $type = strtoupper(trim($_POST['leave_type'] ?? ''));
        $allowance = (float)($_POST['allowance'] ?? 0);
        $resetMonth = (int)($_POST['reset_month'] ?? 1);
        
        $method = in_array($_POST['method'] ?? '', ['FIXED', 'ACCUMULATED']) ? $_POST['method'] : 'FIXED';

        if ($personId <= 0 || empty($type)) throw new Exception("Données invalides.");

        // Vérification des doublons
        $check = $pdo->prepare("SELECT id FROM pf_person_leave_meta WHERE person_id = ? AND leave_type = ?");
        $check->execute([$personId, $type]);
        if ($check->rowCount() > 0) throw new Exception("Ce congé est déjà attribué à cette personne.");

        // On construit la date anniversaire avec le mois choisi par l'utilisateur
        $anniversaryDate = "2000-" . str_pad((string)$resetMonth, 2, "0", STR_PAD_LEFT) . "-01";

        $stmt = $pdo->prepare("INSERT INTO pf_person_leave_meta (person_id, leave_type, allowance, method, anniversary_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$personId, $type, $allowance, $method, $anniversaryDate]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_person_leave') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");
        
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM pf_person_leave_meta WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Action inconnue : " . htmlspecialchars($action));

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}