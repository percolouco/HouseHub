<?php
// Script de migration : Refonte globale Multi-Tenant & Synchronisation des schémas
require_once __DIR__ . '/includes/meta_db.php';

echo "<h1>🚀 Début de la migration Globale ...</h1>";

// ==========================================
// 0. MISE À JOUR DE LA META DB
// ==========================================
echo "<h3>Mise à jour Meta DB (househub_meta)</h3><ul>";
try {
    $meta_pdo->exec("ALTER TABLE user_calendar_integrations ADD COLUMN calendar_prefs_json TEXT DEFAULT NULL");
    echo "<li><span style='color:green'>Colonne 'calendar_prefs_json' ajoutée à user_calendar_integrations.</span></li>";
} catch (\PDOException $e) {
    if ($e->getCode() == '42S21' || strpos($e->getMessage(), '1060') !== false) {
        echo "<li><span style='color:gray'>Colonne 'calendar_prefs_json' déjà présente.</span></li>";
    } else { throw $e; }
}
echo "</ul>";

// ==========================================
// MIGRATIONS PAR FAMILLE
// ==========================================
$stmt = $meta_pdo->query("SELECT id, name, db_name FROM families WHERE db_name != ''");
$families = $stmt->fetchAll();

$host = getenv('DB_HOST') ?: 'househub-db';
$user = getenv('DB_USER') ?: 'househub';
$pass = getenv('DB_PASS') ?: 'changeme';

foreach ($families as $f) {
    $db_name = $f['db_name'];
    $family_id = $f['id'];
    echo "<h3>Mise à jour de <strong>{$f['name']}</strong> ($db_name)</h3><ul>";

    $p1_name = null;
    $p2_name = null;

    try {
        $fam_pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 1. Table pf_foyer_settings
        $fam_pdo->exec("CREATE TABLE IF NOT EXISTS pf_foyer_settings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          currency VARCHAR(10) NOT NULL DEFAULT '€',
          zone_scolaire VARCHAR(5) NOT NULL DEFAULT 'C',
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $fam_pdo->exec("INSERT IGNORE INTO pf_foyer_settings (id, currency, zone_scolaire) VALUES (1, '€', 'C');");
        echo "<li><span style='color:green'>Table pf_foyer_settings vérifiée/créée.</span></li>";

        // 2. Colonne external_href pour le Calendrier iOS
        try {
            $fam_pdo->exec("ALTER TABLE pf_calendar_event_links ADD COLUMN external_href VARCHAR(2048) DEFAULT NULL");
            echo "<li><span style='color:green'>Colonne 'external_href' ajoutée à pf_calendar_event_links.</span></li>";
        } catch (\PDOException $e) {
            if ($e->getCode() != '42S21' && strpos($e->getMessage(), '1060') === false) throw $e;
        }

        // 3. Colonne budget_item_id pour les règles d'import
        try {
            $fam_pdo->exec("ALTER TABLE pf_import_rules ADD COLUMN budget_item_id INT(11) DEFAULT NULL");
            echo "<li><span style='color:green'>Colonne 'budget_item_id' ajoutée à pf_import_rules.</span></li>";
        } catch (\PDOException $e) {
            if ($e->getCode() != '42S21' && strpos($e->getMessage(), '1060') === false) throw $e;
        }

        // 4. Uniformisation des VARCHAR de dates pour le budget (YYYY-MM-01 = 10 chars)
        $fam_pdo->exec("ALTER TABLE pf_expenses MODIFY gestion_month VARCHAR(10) NOT NULL");
        $fam_pdo->exec("ALTER TABLE pf_alloc_values MODIFY month_date VARCHAR(10) NOT NULL");
        $fam_pdo->exec("ALTER TABLE pf_savings MODIFY month_date VARCHAR(10) NOT NULL");
        echo "<li><span style='color:green'>Formats de dates (VARCHAR 10) uniformisés pour le budget.</span></li>";

        // 5. GESTION DE PF_PEOPLE (user_id, role, color)
        echo "<li><strong>Mise à jour pf_people :</strong> ";
        try { $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN user_id INT NULL DEFAULT NULL"); } catch (\Exception $e) {}
        try { $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN role VARCHAR(50) DEFAULT NULL"); } catch (\Exception $e) {}
        try { $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN color VARCHAR(7) DEFAULT '#0891b2'"); } catch (\Exception $e) {}

        $stmtUsers = $meta_pdo->prepare("SELECT id, username, display_name FROM users WHERE family_id = ? ORDER BY id ASC");
        $stmtUsers->execute([$family_id]);
        $users = $stmtUsers->fetchAll();

        foreach ($users as $u) {
            $stmtCheck = $fam_pdo->prepare("SELECT id FROM pf_people WHERE user_id = ? OR LOWER(name) = LOWER(?)");
            $stmtCheck->execute([$u['id'], $u['username']]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists) {
                $fam_pdo->prepare("UPDATE pf_people SET user_id = ? WHERE id = ?")->execute([$u['id'], $exists]);
            } else {
                $fam_pdo->prepare("INSERT INTO pf_people (name, user_id) VALUES (?, ?)")->execute([$u['display_name'] ?: $u['username'], $u['id']]);
            }
        }
        $fam_pdo->exec("UPDATE pf_people SET role = 'parent' WHERE user_id IS NOT NULL AND role IS NULL;");
        $fam_pdo->exec("UPDATE pf_people SET role = 'nounou' WHERE LOWER(name) = 'carole';");

        $pIds = $fam_pdo->query("SELECT id FROM pf_people WHERE role = 'parent' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (isset($pIds[0])) $fam_pdo->exec("UPDATE pf_people SET color = '#0891b2' WHERE id = " . (int)$pIds[0] . " AND color IS NULL");
        if (isset($pIds[1])) $fam_pdo->exec("UPDATE pf_people SET color = '#f59e0b' WHERE id = " . (int)$pIds[1] . " AND color IS NULL");
        echo "<span style='color:blue'>OK</span></li>";

        // ==========================================
        // 6. REFONTE RELATIONNELLE : pf_alloc_values (Colonnes -> Lignes)
        // ==========================================
        echo "<li><strong>Table pf_alloc_values (Normalisation) :</strong> ";

        // Vérifier si la table est déjà convertie
        $checkNewFormat = $fam_pdo->query("SHOW COLUMNS FROM pf_alloc_values LIKE 'person_id'")->rowCount();
        
        if ($checkNewFormat > 0) {
            echo "<span style='color:gray'>Déjà convertie au format relationnel.</span></li>";
        } else {
            // A. Récupérer l'ordre des parents réels pour faire le mapping d'index
            $stmtParents = $fam_pdo->query("SELECT id, name FROM pf_people WHERE role = 'parent' ORDER BY id ASC");
            $orderedParents = $stmtParents->fetchAll();
            
            // B. Sauvegarder les anciennes données à migrer
            $oldAllocations = $fam_pdo->query("SELECT * FROM pf_alloc_values")->fetchAll(PDO::FETCH_ASSOC);
            
            // C. Supprimer l'ancienne table
            $fam_pdo->exec("DROP TABLE IF EXISTS pf_alloc_values");
            
            // D. Créer la nouvelle table normalisée
            $fam_pdo->exec("CREATE TABLE pf_alloc_values (
              id           INT AUTO_INCREMENT PRIMARY KEY,
              month_date   VARCHAR(10) NOT NULL,
              cat_id       INT NOT NULL,
              person_id    INT NOT NULL,
              amount       DECIMAL(10,2) DEFAULT 0.00,
              UNIQUE KEY uq_alloc_person (month_date, cat_id, person_id),
              FOREIGN KEY (person_id) REFERENCES pf_people(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // E. Migration des données
            $stmtInsert = $fam_pdo->prepare("INSERT INTO pf_alloc_values (month_date, cat_id, person_id, amount) VALUES (?, ?, ?, ?)");
            $migratedRows = 0;

            foreach ($oldAllocations as $oldRow) {
                // Mapping des anciennes colonnes potentielles vers les index de parents
                $possibleColumns = [
                    0 => ['amount_p1', 'amount_alex'],
                    1 => ['amount_p2', 'amount_laia'],
                    2 => ['amount_p3'],
                    3 => ['amount_p4']
                ];

                foreach ($possibleColumns as $parentIndex => $colNames) {
                    if (!isset($orderedParents[$parentIndex])) continue;
                    $targetParentId = $orderedParents[$parentIndex]['id'];

                    foreach ($colNames as $col) {
                        if (isset($oldRow[$col]) && (float)$oldRow[$col] > 0) {
                            $stmtInsert->execute([
                                $oldRow['month_date'],
                                $oldRow['cat_id'],
                                $targetParentId,
                                (float)$oldRow[$col]
                            ]);
                            $migratedRows++;
                            break; // Passer à l'index parent suivant dès qu'on a trouvé une valeur
                        }
                    }
                }
            }
            echo "<span style='color:green'>Succès ! Nouvelle table créée, $migratedRows lignes migrées.</span></li>";
        }

        // 7. GESTION DU BUDGET (pf_alloc_categories & pf_salary_config)
        $fam_pdo->exec("UPDATE pf_alloc_categories SET name = 'Eco P1' WHERE name LIKE 'Eco Alex%'");
        $fam_pdo->exec("UPDATE pf_alloc_categories SET name = 'Eco P2' WHERE name LIKE 'Eco Laia%'");

        $stmtParents = $fam_pdo->query("SELECT name FROM pf_people WHERE role = 'parent' ORDER BY id ASC");
        $parents = $stmtParents->fetchAll();
        if (count($parents) >= 2) {
            $fam_pdo->prepare("UPDATE pf_salary_config SET person = ? WHERE person = 'Alex'")->execute([$parents[0]['name']]);
            $fam_pdo->prepare("UPDATE pf_salary_config SET person = ? WHERE person = 'Laia'")->execute([$parents[1]['name']]);
        }

    } catch (\PDOException $e) {
        echo "<li style='color:red'>❌ Erreur : " . $e->getMessage() . "</li>";
    }
    echo "</ul>";

        // ==========================================
        // 8. SEPARATION CIBLE BUDGET / DESTINATION VIREMENT
        // ==========================================
        echo "<li><strong>Table pf_alloc_categories (Fix Collision) :</strong> ";
        try {
            $fam_pdo->exec("ALTER TABLE pf_alloc_categories ADD COLUMN transfer_dest VARCHAR(50) DEFAULT NULL AFTER target");
            echo "<span style='color:green'>Succès ! Colonne 'transfer_dest' ajoutée pour préserver vos objectifs chiffrés.</span></li>";
        } catch (\PDOException $e) {
            echo "<span style='color:gray'>La colonne transfer_dest existe déjà.</span></li>";
        }

        // ==========================================
        // 9. TRANSFERT DES DONNÉES (Cible -> Destination)
        // ==========================================
        echo "<li><strong>Table pf_alloc_categories (Récupération des données) :</strong> ";
        try {
            $updated1 = $fam_pdo->exec("UPDATE pf_alloc_categories SET transfer_dest = target, target = '0' WHERE target LIKE 'vers %'");
            
            $updated2 = $fam_pdo->exec("UPDATE pf_alloc_categories SET transfer_dest = 'SYSTEM', target = '0' WHERE target = 'SYSTEM'");
            
            $fam_pdo->exec("UPDATE pf_alloc_categories SET target = '0' WHERE target NOT REGEXP '^[0-9]+(\.[0-9]+)?$'");

            echo "<span style='color:green'>" . ($updated1 + $updated2) . " destinations récupérées et déplacées.</span></li>";
            
            $fam_pdo->exec("ALTER TABLE pf_alloc_categories MODIFY target DECIMAL(10,2) DEFAULT 0.00");
            echo "<li><span style='color:green'>Colonne 'target' re-sécurisée en format monétaire DECIMAL(10,2).</span></li>";
        } catch (\PDOException $e) {
            echo "<span style='color:red'>Erreur : " . $e->getMessage() . "</span></li>";
        }
}



echo "<h2>🎉 Migration terminée avec succès !</h2>";
?>