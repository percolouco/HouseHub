<?php
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'get_all') {
        // 1. Comptes bancaires avec le nom du propriétaire lié (pf_people)
        $accounts = $pdo->query("
            SELECT a.*, p.name as owner_name 
            FROM pf_bank_accounts a 
            LEFT JOIN pf_people p ON a.owner_person_id = p.id 
            ORDER BY a.is_default DESC, a.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Catégories
        $categories = $pdo->query("SELECT * FROM pf_budget_categories ORDER BY type ASC, label ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Règles d'import
        $rules = $pdo->query("
            SELECT r.*, c.label as cat_label 
            FROM pf_import_rules r 
            LEFT JOIN pf_budget_categories c ON r.category COLLATE utf8mb4_unicode_ci = c.code COLLATE utf8mb4_unicode_ci
            ORDER BY r.keyword ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Salaires (Année en cours)
        $currentYear = (int)date('Y');
        $salaries = $pdo->query("SELECT * FROM pf_salary_config WHERE year = $currentYear ORDER BY person ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 5. Paramètres Foyer (Devise + Mapping CSV)
        $foyerData = $pdo->query("SELECT currency, csv_mapping FROM pf_foyer_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $currencySetting = $foyerData['currency'] ?? '€';
        $csvMapping = !empty($foyerData['csv_mapping']) ? json_decode($foyerData['csv_mapping'], true) : null;

        echo json_encode([
            'success' => true,
            'data' => [
                'accounts'    => $accounts,
                'categories'  => $categories,
                'rules'       => $rules,
                'salaries'    => $salaries,
                'year'        => $currentYear,
                'currency'    => $currencySetting,
                'csv_mapping' => $csvMapping
            ]
        ]);
        exit;
    }

    // --- GESTION DES COMPTES BANCAIRES ---
    if ($action === 'add_account') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'checking';
        if (empty($name)) throw new Exception("Le nom du compte est obligatoire.");
        $stmt = $pdo->prepare("INSERT INTO pf_bank_accounts (name, account_type, is_default) VALUES (?, ?, 0)");
        $stmt->execute([$name, $type]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_account') {
        $id = (int)($_POST['id'] ?? 0);
        $count = $pdo->query("SELECT COUNT(*) FROM pf_bank_accounts")->fetchColumn();
        if ($count <= 1) throw new Exception("Impossible de supprimer le dernier compte.");
        $stmt = $pdo->prepare("DELETE FROM pf_bank_accounts WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- GESTION DES CATÉGORIES ---
    if ($action === 'add_category') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $label = trim($_POST['label'] ?? '');
        $type = $_POST['type'] ?? 'Expense';
        $color = $_POST['color'] ?? '#cccccc';
        $icon = trim($_POST['icon'] ?? '📌');
        if (empty($code) || empty($label)) throw new Exception("Le code et le libellé sont obligatoires.");
        $check = $pdo->prepare("SELECT COUNT(*) FROM pf_budget_categories WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetchColumn() > 0) throw new Exception("Ce code de catégorie existe déjà.");
        $stmt = $pdo->prepare("INSERT INTO pf_budget_categories (code, label, type, color, icon) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $label, $type, $color, $icon]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM pf_budget_categories WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- GESTION DES RÈGLES D'IMPORT ---
    if ($action === 'add_rule') {
        $keyword = strtoupper(trim($_POST['keyword'] ?? ''));
        $category = trim($_POST['category'] ?? '');
        if (empty($keyword) || empty($category)) throw new Exception("Le mot-clé et la catégorie sont obligatoires.");
        $check = $pdo->prepare("SELECT COUNT(*) FROM pf_import_rules WHERE keyword = ?");
        $check->execute([$keyword]);
        if ($check->fetchColumn() > 0) throw new Exception("Une règle pour ce mot-clé existe déjà.");
        $stmt = $pdo->prepare("INSERT INTO pf_import_rules (keyword, category) VALUES (?, ?)");
        $stmt->execute([$keyword, $category]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_rule') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM pf_import_rules WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- GESTION DES SALAIRES ---
    if ($action === 'save_salary') {
        $id = (int)($_POST['id'] ?? 0);
        $salary = (float)($_POST['salary'] ?? 0);
        $mensualite = (float)($_POST['mensualite'] ?? 0);
        if ($id <= 0) throw new Exception("ID de configuration de salaire invalide.");
        $stmt = $pdo->prepare("UPDATE pf_salary_config SET salary = ?, mensualite = ? WHERE id = ?");
        $stmt->execute([$salary, $mensualite, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- GESTION DE LA DEVISE GLOBALE ---
    if ($action === 'save_currency') {
        $currency = trim($_POST['currency'] ?? '€');
        $stmt = $pdo->prepare("UPDATE pf_foyer_settings SET currency = ?");
        $stmt->execute([$currency]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- GESTION DU FORMAT CSV ---
    if ($action === 'save_csv_mapping') {
        $mapping = [
            'delimiter'   => $_POST['csv_delimiter'] ?? ';',
            'date_format' => $_POST['csv_date_format'] ?? 'd/m/Y',
            'col_date'    => (int)($_POST['csv_col_date'] ?? 0),
            'col_label'   => (int)($_POST['csv_col_label'] ?? 1),
            'amount_type' => $_POST['csv_amount_type'] ?? 'single',
            'col_debit'   => (int)($_POST['csv_col_debit'] ?? 8),
            'col_credit'  => (int)($_POST['csv_col_credit'] ?? 9),
            'col_ref'     => (int)($_POST['csv_col_ref'] ?? 3)
        ];
        $jsonContent = json_encode($mapping);
        $stmt = $pdo->prepare("UPDATE pf_foyer_settings SET csv_mapping = ?");
        $stmt->execute([$jsonContent]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Action API inconnue.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>