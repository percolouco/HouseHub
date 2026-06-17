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



            // Règles Cadeaux

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



            // Utilisation de INSERT IGNORE pour éviter les doublons tout en injectant les catégories manquantes

            $pdo->exec("INSERT IGNORE INTO pf_budget_categories (code, label, type, color, icon) VALUES

                ('INCOME', 'Revenus', 'Income', '#22c55e', '💵'),

                ('FMCG', 'Alimentation', 'Expense', '#3b82f6', '🛒'),

                ('FUEL', 'Carburant', 'Expense', '#f59e0b', '⛽'),

                ('SCHOOL', 'École / Garde', 'Expense', '#a855f7', '🎒'),

                ('HEALTH', 'Santé', 'Expense', '#ef4444', '⚕️'),

                ('FIXED', 'Charges Fixes', 'Expense', '#ef4444', '🏢'),

                ('SAVINGS', 'Épargne & Projets', 'Expense', '#8b5cf6', '🐷'),

                ('AUTRES', 'Autres / Divers', 'Expense', '#64748b', '📁')

            ");



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



            // ---------------------------------------------------------

            // 4. MIGRATION SPECIFIQUE : MODULE CALENDRIER (CONGÉS GRANULAIRES)

            // ---------------------------------------------------------

            $tableExists = $pdo->query("SHOW TABLES LIKE 'pf_person_leave_meta'")->rowCount() > 0;



            if (!$tableExists) {

                $pdo->exec("

                    CREATE TABLE pf_person_leave_meta (

                        id INT AUTO_INCREMENT PRIMARY KEY,

                        person_id INT NOT NULL,

                        leave_type VARCHAR(50) NOT NULL,

                        anniversary_date DATE NOT NULL,

                        UNIQUE KEY uq_person_leave (person_id, leave_type),

                        FOREIGN KEY (person_id) REFERENCES pf_people(id) ON DELETE CASCADE

                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                ");

                echo "✅ Table pf_person_leave_meta créée (Nouvelle installation).<br>";

            } else {

                $checkColumn = $pdo->query("SHOW COLUMNS FROM pf_person_leave_meta LIKE 'leave_type'")->rowCount();

                if ($checkColumn === 0) {

                    $pdo->exec("ALTER TABLE pf_person_leave_meta ADD COLUMN leave_type VARCHAR(50) NULL AFTER person_id");

                    $pdo->exec("UPDATE pf_person_leave_meta SET leave_type = 'CP' WHERE leave_type IS NULL");

                    $pdo->exec("ALTER TABLE pf_person_leave_meta MODIFY COLUMN leave_type VARCHAR(50) NOT NULL");

                   

                    try {

                        $pdo->exec("ALTER TABLE pf_person_leave_meta DROP INDEX person_id");

                    } catch (\Throwable $e) {}

                   

                    $pdo->exec("ALTER TABLE pf_person_leave_meta ADD UNIQUE KEY uq_person_leave (person_id, leave_type)");

                    echo "✅ Table pf_person_leave_meta mise à jour avec le type granulaire.<br>";

                } else {

                    echo "➖ Table pf_person_leave_meta déjà à jour.<br>";

                }

            }



            // ---------------------------------------------------------

            // 5. MIGRATION DES PARAMÈTRES DYNAMIQUES (pf_settings)

            // ---------------------------------------------------------

            $pdo->exec("

                CREATE TABLE IF NOT EXISTS `pf_settings` (

                  `setting_key` varchar(50) NOT NULL,

                  `setting_value` text DEFAULT NULL,

                  `module` varchar(50) NOT NULL,

                  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),

                  PRIMARY KEY (`setting_key`)

                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            ");



            $pdo->exec("

                INSERT IGNORE INTO `pf_settings` (`setting_key`, `setting_value`, `module`) VALUES

                ('calendar_default_view', 'month', 'calendar'),

                ('calendar_first_day', '1', 'calendar'),

                ('calendar_working_hours', '08:00-19:00', 'calendar'),

                ('budget_start_day', '1', 'budget'),

                ('budget_default_tab', 'dépenses', 'budget'),

                ('travel_default_transport', 'car', 'voyage'),

                ('travel_default_fuel_price', '1.85', 'voyage'),

                ('gifts_hide_purchased', '0', 'cadeaux'),

                ('gifts_default_sort', 'person', 'cadeaux'),

                ('gifts_budget_alert', '500', 'cadeaux');

            ");

            echo "✅ Table pf_settings créée et paramètres de base injectés.<br>";



            try {

                $pdo->exec("ALTER TABLE `pf_vehicles` ADD COLUMN `consumption` decimal(4,2) DEFAULT NULL COMMENT 'Consommation L/100km' AFTER `fuel_type`");

                echo "✅ Colonne consumption ajoutée au Garage.<br>";

            } catch (PDOException $e) {

                echo "➖ pf_vehicles déjà à jour.<br>";

            }



            // ---------------------------------------------------------

            // 6. MIGRATION DE L'HISTORIQUE CALENDRIER (FINI LE CODÉ EN DUR)

            // ---------------------------------------------------------

            $stmtHelper = $pdo->query("SELECT id FROM pf_people WHERE role = 'helper' LIMIT 1");

            $helperId = $stmtHelper->fetchColumn();



            if ($helperId) {

                $pdo->exec("UPDATE pf_events SET event_type = 'HELPER_OFF', person_id = $helperId WHERE event_type = 'OFF_CAROLE'");

                $pdo->exec("UPDATE pf_events SET event_type = 'HELPER_EXTRA', person_id = $helperId WHERE event_type = 'EXTRA_OFF_CAROLE'");

                echo "✅ Historique des absences Nounou migré dynamiquement.<br>";

            }



            $stmtKid = $pdo->query("SELECT id FROM pf_people WHERE role IN ('child', 'enfant') ORDER BY id ASC LIMIT 1");

            $kidId = $stmtKid->fetchColumn();



            if ($kidId) {

                $pdo->exec("UPDATE pf_events SET event_type = 'CHILD_SICK', person_id = $kidId WHERE event_type = 'PEP_SICK'");

                echo "✅ Historique des maladies Enfant migré dynamiquement.<br>";

            }



            // ---------------------------------------------------------

            // 7. MIGRATION DÉFINITIVE : STRUCTURE DU BUDGET

            // ---------------------------------------------------------

           

            // A. Changement du type de la colonne (ENUM -> VARCHAR)

            $pdo->exec("ALTER TABLE pf_budget_items MODIFY category VARCHAR(100) DEFAULT NULL");

           

            // B. MAPPING INTELLIGENT DES DONNEES EXISTANTES (Nettoyage de l'ancienne logique)

            $itemsMigres = 0;

           

            // 1. Les charges fixes (is_estimate = 0) deviennent FIXED

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FIXED' WHERE category = 'expense' AND is_estimate = 0");

           

            // 2. Les estimations (is_estimate = 1) sont mappées selon leur nom ou mots clés (hack historique)

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FUEL' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%ESSENCE%' OR name LIKE '%gasolina%')");

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'SCHOOL' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%ESCOLA%' OR mapping_keywords LIKE '%PARASCOL%' OR name LIKE '%escola%')");

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FMCG' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%FMCG%' OR name LIKE '%F&B%')");

           

            // 3. On sécurise les éventuelles estimations restantes dans AUTRES

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'AUTRES' WHERE category = 'expense'");

           

            // 4. On sécurise les anciens revenus

            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'INCOME' WHERE category = 'income'");

           

            // C. Nettoyage des virgules orphelines dans mapping_keywords (suppression des vieux hacks)

            $budgetCodes = ['INCOME', 'FMCG', 'FUEL', 'SCHOOL', 'HEALTH', 'FIXED', 'SAVINGS', 'AUTRES'];

            foreach ($budgetCodes as $code) {

                $pdo->exec("UPDATE pf_budget_items SET mapping_keywords = REPLACE(mapping_keywords, '$code', '') WHERE mapping_keywords LIKE '%$code%'");

            }

            $pdo->exec("UPDATE pf_budget_items SET mapping_keywords = TRIM(BOTH ',' FROM REPLACE(REPLACE(mapping_keywords, ' ', ''), ',,', ','))");

           

            // D. Migration de l'historique des dépenses et des règles (Anciens libellés FR/EN -> Nouveaux Codes Dynamiques)

            $budgetMapping = [

                'Income'   => 'INCOME',

                'FMCG'     => 'FMCG',

                'Essence'  => 'FUEL',

                'School'   => 'SCHOOL',

                'Frais'    => 'FIXED',

                'LivretA'  => 'SAVINGS',

                'Autres'   => 'AUTRES'

            ];



            $stmtExp = $pdo->prepare("UPDATE pf_expenses SET category = ? WHERE category = ?");

            $stmtRules = $pdo->prepare("UPDATE pf_import_rules SET category = ? WHERE category = ?");



            $expCount = 0;

            $rulesCount = 0;



            foreach ($budgetMapping as $old => $newCode) {

                $stmtExp->execute([$newCode, $old]);

                $expCount += $stmtExp->rowCount();



                $stmtRules->execute([$newCode, $old]);

                $rulesCount += $stmtRules->rowCount();

            }



            echo "✅ Budget Structure : Table pf_budget_items passée en VARCHAR. $itemsMigres prévisions recatégorisées. Historique migré ($expCount dépenses et $rulesCount règles mises à jour).<br>";



        } catch (PDOException $e) {

            echo "❌ Erreur sur la base $dbName : " . $e->getMessage() . "<br>";

        }

    } // FIN DE LA BOUCLE FOREACH



    try {

    // Vérification si la colonne csv_mapping existe déjà dans pf_foyer_settings

    $checkCol = $pdo->query("SHOW COLUMNS FROM pf_foyer_settings LIKE 'csv_mapping'")->fetch();



    if (!$checkCol) {

        $pdo->exec("ALTER TABLE pf_foyer_settings ADD COLUMN csv_mapping TEXT NULL");

        echo "✅ Migration : Colonne 'csv_mapping' ajoutée à la table 'pf_foyer_settings'.\n";

    } else {

        echo "ℹ️ Migration : La colonne 'csv_mapping' existe déjà.\n";

    }



} catch (PDOException $e) {

    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "\n";

}



    echo "<h1>🎉 Migration terminée avec succès !</h1>";



} catch (Exception $e) {

    die("❌ Erreur fatale Meta DB : " . $e->getMessage());

}

?> 

