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

            // ---------------------------------------------------------
            // 1. UPDATE DE pf_people (Juste la date de naissance et l'état actif)
            // ---------------------------------------------------------
            $colCheck = $pdo->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pf_people' AND COLUMN_NAME = 'birthdate'");
            $colCheck->execute([$dbName]);
            if ($colCheck->rowCount() === 0) {
                $pdo->exec("ALTER TABLE pf_people 
                    ADD COLUMN birthdate DATE DEFAULT NULL,
                    ADD COLUMN is_active TINYINT(1) DEFAULT 1
                ");
                echo "✅ pf_people mis à jour (birthdate ajouté).<br>";
            } else {
                echo "➖ pf_people déjà à jour.<br>";
            }

            // ---------------------------------------------------------
            // 2. CRÉATION DES NOUVELLES TABLES (IF NOT EXISTS)
            // ---------------------------------------------------------
            
            // Comptes bancaires
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_bank_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                owner_person_id INT DEFAULT NULL,
                account_type VARCHAR(50) DEFAULT 'savings',
                is_default TINYINT(1) DEFAULT 0,
                FOREIGN KEY (owner_person_id) REFERENCES pf_people(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Catégories de budget
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_budget_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                label VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                color VARCHAR(20) DEFAULT '#ccc',
                icon VARCHAR(20) DEFAULT '💰',
                UNIQUE KEY (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Types de congés
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_leave_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(20) NOT NULL,
                label VARCHAR(100) NOT NULL,
                default_allowance DECIMAL(5,2) DEFAULT 0,
                reset_month INT DEFAULT 1,
                allow_carry_over TINYINT(1) DEFAULT 0,
                UNIQUE KEY (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Fêtes (Cadeaux)
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_gift_occasions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(100) NOT NULL,
                month_date VARCHAR(5) DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1,
                UNIQUE KEY (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Règles Cadeaux (Qui paie pour quel enfant à quelle occasion)
            // L'ERREUR DE SYNTAXE ÉTAIT ICI : UNIQUE KEY adult_child_occ
            $pdo->exec("CREATE TABLE IF NOT EXISTS pf_gift_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                adult_person_id INT NOT NULL,
                child_person_id INT NOT NULL,
                occasion_id INT NOT NULL,
                FOREIGN KEY (adult_person_id) REFERENCES pf_people(id) ON DELETE CASCADE,
                FOREIGN KEY (child_person_id) REFERENCES pf_people(id) ON DELETE CASCADE,
                FOREIGN KEY (occasion_id) REFERENCES pf_gift_occasions(id) ON DELETE CASCADE,
                UNIQUE KEY adult_child_occ (adult_person_id, child_person_id, occasion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            echo "✅ Nouvelles tables de configuration créées ou vérifiées.<br>";

            // ---------------------------------------------------------
            // 3. INJECTION DE DONNÉES PAR DÉFAUT (Pour ne rien casser)
            // ---------------------------------------------------------
            
            if ($pdo->query("SELECT COUNT(*) FROM pf_bank_accounts")->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO pf_bank_accounts (name, account_type, is_default) VALUES 
                    ('Compte Commun', 'checking', 1),
                    ('Livret A Alex', 'savings', 0),
                    ('Livret A Laia', 'savings', 0),
                    ('Livret A Pol', 'savings', 0),
                    ('Livret A Pep', 'savings', 0)
                ");
            }

            if ($pdo->query("SELECT COUNT(*) FROM pf_budget_categories")->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO pf_budget_categories (code, label, type, color, icon) VALUES 
                    ('INCOME', 'Revenus', 'Income', '#22c55e', '💵'),
                    ('FMCG', 'Alimentation', 'Expense', '#3b82f6', '🛒'),
                    ('FUEL', 'Carburant', 'Expense', '#f59e0b', '⛽'),
                    ('SCHOOL', 'École / Garde', 'Expense', '#a855f7', '🎒'),
                    ('HEALTH', 'Santé', 'Expense', '#ef4444', '⚕️')
                ");
            }

            if ($pdo->query("SELECT COUNT(*) FROM pf_leave_types")->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO pf_leave_types (code, label, default_allowance, reset_month, allow_carry_over) VALUES 
                    ('CA', 'Congés Annuels', 25, 6, 1),
                    ('JRA', 'Jours de Repos', 10, 1, 0),
                    ('JA', 'Jour Anniversaire', 1, 1, 0)
                ");
            }

            if ($pdo->query("SELECT COUNT(*) FROM pf_gift_occasions")->fetchColumn() == 0) {
                $pdo->exec("INSERT INTO pf_gift_occasions (code, name, month_date) VALUES 
                    ('NOEL', 'Noël', '12-25'),
                    ('ROIS', 'Les Rois', '01-06'),
                    ('ANNIV', 'Anniversaire', NULL)
                ");
            }

            echo "✅ Données de base injectées (si nécessaire).<br>";

        } catch (PDOException $e) {
            echo "❌ Erreur sur la base $dbName : " . $e->getMessage() . "<br>";
        }
    }

    echo "<h1>🎉 Migration terminée avec succès !</h1>";

} catch (Exception $e) {
    die("❌ Erreur fatale Meta DB : " . $e->getMessage());
}
?>