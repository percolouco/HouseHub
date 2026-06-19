<?php
/**
 * Script de migration global HouseHub OS (Multi-tenant)
 * Auto-Sync dynamique basé sur schema_family.sql + Migration des données
 * À exécuter via le navigateur : http://localhost:8083/migrate.php
 */

require_once __DIR__ . '/includes/meta_db.php';

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'househub';
$db_pass = getenv('DB_PASS') ?: 'househub_dev';

echo "<h1>🚀 Début de la migration Multi-Tenant (Auto-Sync)</h1>";

// ---------------------------------------------------------
// 1. LECTURE ET PARSING DU FICHIER SCHEMA_FAMILY.SQL
// ---------------------------------------------------------
$schemaPath = __DIR__ . '/schema_family.sql'; // Modifie si rangé dans /docker/
if (!file_exists($schemaPath)) {
    $schemaPath = __DIR__ . '/docker/schema_family.sql';
}
if (!file_exists($schemaPath)) {
    die("❌ Impossible de trouver le fichier schema_family.sql");
}

$sqlContent = file_get_contents($schemaPath);

/**
 * Analyse le code SQL pour en extraire la structure [Table => [Colonnes]]
 */
function parseExpectedSchema($sql) {
    $schema = [];
    // Récupère tout ce qui se trouve entre CREATE TABLE (...) ENGINE
    preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/si', $sql, $tableMatches, PREG_SET_ORDER);
    
    foreach($tableMatches as $match) {
        $tableName = $match[1];
        $body = $match[2];
        
        // Sépare les lignes par virgule, en ignorant les virgules entre parenthèses (ex: DECIMAL(10,2) ou ENUM('a','b'))
        $lines = preg_split('/,(?![^\(]*\))/', $body);
        
        $columns = [];
        foreach($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // On ignore la déclaration des clés, contraintes et index
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|FOREIGN KEY|KEY|INDEX|FULLTEXT KEY|CONSTRAINT)\b/i', $line)) {
                continue;
            }
            
            // Extrait le nom de la colonne et sa définition SQL
            if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.*)$/i', $line, $colMatch)) {
                $colName = $colMatch[1];
                $colDef = rtrim($colMatch[2], ',');
                $columns[$colName] = $colDef;
            }
        }
        $schema[$tableName] = $columns;
    }
    return $schema;
}

$expectedSchema = parseExpectedSchema($sqlContent);
echo "ℹ️ Modèle SQL chargé et analysé : <b>" . count($expectedSchema) . " tables</b> détectées.<hr>";

try {
    $stmt = $meta_pdo->query("SELECT db_name, name FROM families WHERE is_active = 1");
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($families as $family) {
        $dbName = $family['db_name'];
        echo "<h2>🏡 Famille : {$family['name']} ($dbName)</h2>";

        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$dbName;charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // ---------------------------------------------------------
            // 2. EXÉCUTION DU SQL (CRÉATION TABLES MANQUANTES + DONNÉES PAR DÉFAUT)
            // ---------------------------------------------------------
            try {
                $pdo->exec($sqlContent);
                echo "✅ Structure de base validée (les tables manquantes ont été créées).<br>";
            } catch (PDOException $e) {
                echo "⚠️ Avertissement SQL brut : " . $e->getMessage() . "<br>";
            }

            // ---------------------------------------------------------
            // 3. DIFFING (AJOUT DYNAMIQUE DES COLONNES MANQUANTES)
            // ---------------------------------------------------------
            $colsAdded = 0;
            foreach ($expectedSchema as $table => $expectedCols) {
                $stmtCol = $pdo->query("SHOW COLUMNS FROM `$table`");
                $existingCols = $stmtCol->fetchAll(PDO::FETCH_COLUMN);

                foreach ($expectedCols as $colName => $colDef) {
                    if (!in_array($colName, $existingCols)) {
                        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$colName` $colDef");
                        echo "➕ Nouvelle colonne ajoutée : <b>$table.$colName</b><br>";
                        $colsAdded++;
                    }
                }
            }
            if ($colsAdded === 0) {
                echo "✅ Toutes les colonnes de toutes les tables sont déjà à jour.<br>";
            }

            // ---------------------------------------------------------
            // 4. MIGRATION DES DONNÉES (TRANSFORMATIONS HISTORIQUES)
            // ---------------------------------------------------------
            echo "<i>🔄 Exécution des transformations de données héritées...</i><br>";
            
            // A. Changement de types spécifiques (Car le parseur n'ajoute que les colonnes manquantes)
            $pdo->exec("ALTER TABLE pf_budget_items MODIFY category VARCHAR(100) DEFAULT NULL");

            // B. Migration de l'historique calendrier (Enfants & Helpers)
            $helperId = $pdo->query("SELECT id FROM pf_people WHERE role = 'helper' LIMIT 1")->fetchColumn();
            if ($helperId) {
                $pdo->exec("UPDATE pf_events SET event_type = 'HELPER_OFF', person_id = $helperId WHERE event_type = 'OFF_CAROLE'");
                $pdo->exec("UPDATE pf_events SET event_type = 'HELPER_EXTRA', person_id = $helperId WHERE event_type = 'EXTRA_OFF_CAROLE'");
            }

            $kidId = $pdo->query("SELECT id FROM pf_people WHERE role IN ('child', 'enfant') ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($kidId) {
                $pdo->exec("UPDATE pf_events SET event_type = 'CHILD_SICK', person_id = $kidId WHERE event_type = 'PEP_SICK'");
            }

            // C. Mapping Intelligent du Budget (is_estimate)
            $itemsMigres = 0;
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FIXED' WHERE category = 'expense' AND is_estimate = 0");
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FUEL' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%ESSENCE%' OR name LIKE '%gasolina%')");
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'SCHOOL' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%ESCOLA%' OR mapping_keywords LIKE '%PARASCOL%' OR name LIKE '%escola%')");
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'FMCG' WHERE category = 'expense' AND is_estimate = 1 AND (mapping_keywords LIKE '%FMCG%' OR name LIKE '%F&B%')");
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'AUTRES' WHERE category = 'expense'");
            $itemsMigres += $pdo->exec("UPDATE pf_budget_items SET category = 'INCOME' WHERE category = 'income'");

            // D. Nettoyage des virgules orphelines dans mapping_keywords
            $budgetCodes = ['INCOME', 'FMCG', 'FUEL', 'SCHOOL', 'HEALTH', 'FIXED', 'SAVINGS', 'AUTRES'];
            foreach ($budgetCodes as $code) {
                $pdo->exec("UPDATE pf_budget_items SET mapping_keywords = REPLACE(mapping_keywords, '$code', '') WHERE mapping_keywords LIKE '%$code%'");
            }
            $pdo->exec("UPDATE pf_budget_items SET mapping_keywords = TRIM(BOTH ',' FROM REPLACE(REPLACE(mapping_keywords, ' ', ''), ',,', ','))");

            // E. Migration de l'historique des dépenses et des règles d'import
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

            foreach ($budgetMapping as $old => $newCode) {
                $stmtExp->execute([$newCode, $old]);
                $stmtRules->execute([$newCode, $old]);
            }

            echo "✅ Transformations Data terminées ($itemsMigres prévisions remappées).<hr>";

        } catch (PDOException $e) {
            echo "❌ Erreur sur la base $dbName : " . $e->getMessage() . "<hr>";
        }
    } // FIN DE LA BOUCLE FOREACH FAMILLES

    echo "<h1>🎉 Synchronisation et Migration terminées avec succès !</h1>";

} catch (Exception $e) {
    die("❌ Erreur fatale Meta DB : " . $e->getMessage());
}
?>