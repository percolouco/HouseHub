<?php
/**
 * Script de migration global HouseHub OS (Multi-tenant)
 * À exécuter via le navigateur : http://localhost:8083/migrate.php
 */

require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🚀 Début de la migration Multi-Tenant</h1>";

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        echo "<h2>Famille : {$family['name']} ($dbName)</h2>";

        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$dbName;charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 1. UPDATE DE pf_people
            $colCheck = $pdo->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pf_people' AND COLUMN_NAME = 'birthdate'");
            $colCheck->execute([$dbName]);
            if ($colCheck->rowCount() === 0) {
                $pdo->exec("ALTER TABLE pf_people ADD COLUMN birthdate DATE DEFAULT NULL, ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                echo "✅ pf_people mis à jour.<br>";
            }

            // 2. CRÉATION DES NOUVELLES TABLES
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_bank_accounts (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, owner_person_id INT DEFAULT NULL, account_type VARCHAR(50) DEFAULT 'savings', is_default TINYINT(1) DEFAULT 0, FOREIGN KEY (owner_person_id) REFERENCES pf_people(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_budget_categories (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, color VARCHAR(20) DEFAULT '#ccc', icon VARCHAR(20) DEFAULT '💰', UNIQUE KEY (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_leave_types (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, default_allowance DECIMAL(5,2) DEFAULT 0, reset_month INT DEFAULT 1, allow_carry_over TINYINT(1) DEFAULT 0, UNIQUE KEY (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_gift_occasions (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL, month_date VARCHAR(5) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1, UNIQUE KEY (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_gift_rules (id INT AUTO_INCREMENT PRIMARY KEY, adult_person_id INT NOT NULL, child_person_id INT NOT NULL, occasion_id INT NOT NULL, FOREIGN KEY (adult_person_id) REFERENCES pf_people(id) ON DELETE CASCADE, FOREIGN KEY (child_person_id) REFERENCES pf_people(id) ON DELETE CASCADE, FOREIGN KEY (occasion_id) REFERENCES pf_gift_occasions(id) ON DELETE CASCADE, UNIQUE KEY adult_child_occ (adult_person_id, child_person_id, occasion_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // 3. INJECTION DONNÉES PAR DÉFAUT
            if ($pdo->query("SELECT COUNT(*) FROM pf_bank_accounts")->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO pf_bank_accounts (name, account_type, is_default) VALUES ('Compte Commun', 'checking', 1), ('Livret A Alex', 'savings', 0), ('Livret A Laia', 'savings', 0), ('Livret A Pol', 'savings', 0), ('Livret A Pep', 'savings', 0)");
            }
            $pdo->exec("INSERT IGNORE INTO pf_budget_categories (code, label, type, color, icon) VALUES ('INCOME', 'Revenus', 'Income', '#22c55e', '💵'), ('FMCG', 'Alimentation', 'Expense', '#3b82f6', '🛒'), ('FUEL', 'Carburant', 'Expense', '#f59e0b', '⛽'), ('SCHOOL', 'École / Garde', 'Expense', '#a855f7', '🎒'), ('HEALTH', 'Santé', 'Expense', '#ef4444', '⚕️'), ('FIXED', 'Charges Fixes', 'Expense', '#ef4444', '🏢'), ('SAVINGS', 'Épargne & Projets', 'Expense', '#8b5cf6', '🐷'), ('AUTRES', 'Autres / Divers', 'Expense', '#64748b', '📁')");

            // 4. MIGRATION MODULE CALENDRIER
            $tableExists = $pdo->query("SHOW TABLES LIKE 'pf_person_leave_meta'")->rowCount() > 0;
            if (!$tableExists) {
                $pdo->exec("CREATE TABLE pf_person_leave_meta (id INT AUTO_INCREMENT PRIMARY KEY, person_id INT NOT NULL, leave_type VARCHAR(50) NOT NULL, anniversary_date DATE NOT NULL, UNIQUE KEY uq_person_leave (person_id, leave_type), FOREIGN KEY (person_id) REFERENCES pf_people(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            // 5. MIGRATION PARAMÈTRES DYNAMIQUES
            $pdo->exec("CREATE TABLE IF NOT EXISTS `pf_settings` (`setting_key` varchar(50) NOT NULL, `setting_value` text DEFAULT NULL, `module` varchar(50) NOT NULL, `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("INSERT IGNORE INTO `pf_settings` (`setting_key`, `setting_value`, `module`) VALUES ('calendar_default_view', 'month', 'calendar'), ('calendar_first_day', '1', 'calendar'), ('calendar_working_hours', '08:00-19:00', 'calendar'), ('budget_start_day', '1', 'budget'), ('budget_default_tab', 'dépenses', 'budget'), ('travel_default_transport', 'car', 'voyage'), ('travel_default_fuel_price', '1.85', 'voyage'), ('gifts_hide_purchased', '0', 'cadeaux'), ('gifts_default_sort', 'person', 'cadeaux'), ('gifts_budget_alert', '500', 'cadeaux')");

            // 6. MIGRATION CSV MAPPING (La partie ajoutée)
            $checkCol = $pdo->query("SHOW COLUMNS FROM pf_foyer_settings LIKE 'csv_mapping'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE pf_foyer_settings ADD COLUMN csv_mapping TEXT NULL");
                echo "✅ Colonne 'csv_mapping' ajoutée.<br>";
            }

            // 7. MIGRATION STRUCTURE BUDGET & HISTORIQUE
            $pdo->exec("ALTER TABLE pf_budget_items MODIFY category VARCHAR(100) DEFAULT NULL");
            $pdo->exec("UPDATE pf_budget_items SET category = 'FIXED' WHERE category = 'expense' AND is_estimate = 0");
            $pdo->exec("UPDATE pf_budget_items SET category = 'AUTRES' WHERE category = 'expense'");
            $pdo->exec("UPDATE pf_budget_items SET category = 'INCOME' WHERE category = 'income'");
            
            echo "✅ Migration terminée pour {$family['name']}.<br>";

        } catch (PDOException $e) {
            echo "❌ Erreur sur la base $dbName : " . $e->getMessage() . "<br>";
        }
    }
    echo "<h1>🎉 Migration terminée avec succès !</h1>";
} catch (Exception $e) {
    die("❌ Erreur fatale Meta DB : " . $e->getMessage());
}
?>