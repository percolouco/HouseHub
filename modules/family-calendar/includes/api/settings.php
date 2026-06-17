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
        // On retire le fallback "Nounou" codé en dur, on part sur vide si rien n'est configuré
        $foyer = $stmtFoyer->fetch(PDO::FETCH_ASSOC) ?: ['zone_scolaire' => 'C', 'care_modes' => '[]'];
        
        // B. Liste des membres de la famille (Triés par rôle pour grouper Parents, Enfants, Helpers)
        // 🟢 CORRECTION : Ajout de la colonne `care_modes` et `color` pour le Javascript !
        $stmtPeople = $pdo->query("SELECT id, name, role, care_modes, color FROM pf_people WHERE is_active = 1 ORDER BY role ASC, name ASC");
        $people = $stmtPeople->fetchAll(PDO::FETCH_ASSOC);

        // C. Matrice des congés par personne
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

        // D. Paramètres dynamiques du calendrier (pf_settings)
        $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM pf_settings WHERE module = 'calendar'");
        $calendarSettingsRaw = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR); // Crée un tableau [key => value]
        
        // Paramètres par défaut si la table est vide
        $calendarSettings = [
            'calendar_default_view'  => $calendarSettingsRaw['calendar_default_view'] ?? 'month',
            'calendar_first_day'     => $calendarSettingsRaw['calendar_first_day'] ?? '1',
            'calendar_working_hours' => $calendarSettingsRaw['calendar_working_hours'] ?? '08:00-19:00'
        ];

        // E. NOUVEAU : Récupération du catalogue des types de congés de la famille
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
                'leave_types' => $leaveTypes // On envoie le catalogue au JS !
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

    // ─── 3. SAUVEGARDE DES CONGÉS D'UN MEMBRE (ONGLET 2) ───
    if ($action === 'save_member_leaves') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");

        $personId = (int)($_POST['person_id'] ?? 0);
        $leavesData = json_decode($_POST['leaves'] ?? '[]', true);

        if ($personId <= 0) throw new Exception("Identifiant de membre invalide.");

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("DELETE FROM pf_person_leave_meta WHERE person_id = ?");
        $stmtDelete->execute([$personId]);

        if (!empty($leavesData)) {
            $stmtInsert = $pdo->prepare("INSERT INTO pf_person_leave_meta (person_id, leave_type, method, allowance, anniversary_date) VALUES (?, ?, ?, ?, ?)");
            foreach ($leavesData as $leave) {
                $type = strtoupper(trim($leave['type']));
                $method = in_array($leave['method'] ?? '', ['FIXED', 'ACCUMULATED']) ? $leave['method'] : 'FIXED';
                $allowance = (float)($leave['allowance'] ?? 0);
                $date = trim($leave['date']);
                
                // Si le JS n'envoie que "MM-DD" (Renouvellement perpétuel), on ajoute l'année bissextile 2000 pour satisfaire le format DATE de MySQL
                if (preg_match('/^\d{2}-\d{2}$/', $date)) {
                    $date = "2000-" . $date;
                }
                
                if (!empty($type) && !empty($date)) {
                    $stmtInsert->execute([$personId, $type, $method, $allowance, $date]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ─── 4. SAUVEGARDE DES PARAMÈTRES D'AFFICHAGE DU CALENDRIER (ONGLET 3) ───
    if ($action === 'save_calendar_settings') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Méthode non autorisée");

        $allowed_keys = ['calendar_default_view', 'calendar_first_day', 'calendar_working_hours'];
        
        $stmt = $pdo->prepare("
            INSERT INTO pf_settings (setting_key, setting_value, module) 
            VALUES (:key, :val, 'calendar') 
            ON DUPLICATE KEY UPDATE setting_value = :val2
        ");

        $pdo->beginTransaction();
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $stmt->execute([
                    'key'  => $key, 
                    'val'  => $value,
                    'val2' => $value
                ]);
            }
        }
        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;
    }

    // ─── 5. SAUVEGARDE DES MODES DE GARDE D'UN ENFANT ───
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

    throw new Exception("Action inconnue");

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}