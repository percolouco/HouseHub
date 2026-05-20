<?php
// Script de migration : Refonte globale Multi-Tenant (pf_people & Budget)
require_once __DIR__ . '/includes/meta_db.php';

echo "<h1>🚀 Début de la migration Globale ...</h1>";

$stmt = $meta_pdo->query("SELECT id, name, db_name FROM families WHERE db_name != ''");
$families = $stmt->fetchAll();

$host = getenv('DB_HOST') ?: 'househub-db';
$user = getenv('DB_USER') ?: 'househub';
$pass = getenv('DB_PASS') ?: 'changeme';

foreach ($families as $f) {
    $db_name = $f['db_name'];
    $family_id = $f['id'];
    echo "<h3>Mise à jour de <strong>{$f['name']}</strong> ($db_name)</h3><ul>";
    
    try {
        $fam_pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // ==========================================
        // 1. GESTION DE PF_PEOPLE (user_id, role, color)
        // ==========================================
        echo "<li><strong>Table pf_people :</strong> ";
        
        // user_id
        try {
            $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN user_id INT NULL DEFAULT NULL");
            echo "<span style='color:green'>user_id OK</span> - ";
        } catch (\PDOException $e) {
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), '1060') !== false) {
                echo "<span style='color:gray'>user_id déjà présent</span> - ";
            } else { throw $e; }
        }

        // Auto-mapping
        $stmtUsers = $meta_pdo->prepare("SELECT id, username FROM users WHERE family_id = ?");
        $stmtUsers->execute([$family_id]);
        $users = $stmtUsers->fetchAll();
        $mappedCount = 0;
        foreach ($users as $u) {
            $stmtUpdate = $fam_pdo->prepare("UPDATE pf_people SET user_id = ? WHERE LOWER(name) = LOWER(?) AND user_id IS NULL");
            $stmtUpdate->execute([$u['id'], $u['username']]);
            $mappedCount += $stmtUpdate->rowCount();
        }
        echo "<span style='color:blue'>$mappedCount profils liés</span> - ";
        
        // role
        try {
            $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN role VARCHAR(50) DEFAULT NULL");
            echo "<span style='color:green'>role OK</span> - ";
        } catch (\PDOException $e) {
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), '1060') !== false) {
                echo "<span style='color:gray'>role déjà présent</span> - ";
            } else { throw $e; }
        }

        // 🟢 NOUVEAU : Colonne color
        try {
            $fam_pdo->exec("ALTER TABLE pf_people ADD COLUMN color VARCHAR(7) DEFAULT '#0891b2'");
            echo "<span style='color:green'>colonne 'color' ajoutée</span> - ";
        } catch (\PDOException $e) {
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), '1060') !== false) {
                echo "<span style='color:gray'>colonne 'color' déjà présente</span> - ";
            } else { throw $e; }
        }

        // Configuration des valeurs par défaut et des rôles
        $fam_pdo->exec("
            UPDATE pf_people SET role = 'parent' WHERE user_id IS NOT NULL;
            UPDATE pf_people SET role = 'nounou' WHERE LOWER(name) = 'carole';
        ");

        // On donne des couleurs distinctes par défaut aux deux parents (P1 = Cyan, P2 = Orange)
        $stmtP = $fam_pdo->query("SELECT id FROM pf_people WHERE role = 'parent' ORDER BY id ASC");
        $pIds = $stmtP->fetchAll(PDO::FETCH_COLUMN);
        if (isset($pIds[0])) {
            $fam_pdo->exec("UPDATE pf_people SET color = '#0891b2' WHERE id = " . (int)$pIds[0]);
        }
        if (isset($pIds[1])) {
            $fam_pdo->exec("UPDATE pf_people SET color = '#f59e0b' WHERE id = " . (int)$pIds[1]);
        }

        echo "<span style='color:blue'>Rôles et Couleurs initialisés.</span></li>";

        // ==========================================
        // 2. GESTION DU BUDGET (pf_alloc_values)
        // ==========================================
        echo "<li><strong>Table pf_alloc_values :</strong> ";
        
        // Vérification et renommage de amount_alex
        $checkAlex = $fam_pdo->query("SHOW COLUMNS FROM pf_alloc_values LIKE 'amount_alex'")->rowCount();
        if ($checkAlex > 0) {
            // Utilisation de CHANGE pour compatibilité maximale avec les anciennes versions MySQL
            $fam_pdo->exec("ALTER TABLE pf_alloc_values CHANGE amount_alex amount_p1 FLOAT DEFAULT 0");
            echo "<span style='color:green'>amount_alex -> amount_p1</span> - ";
        } else {
            echo "<span style='color:gray'>amount_p1 OK</span> - ";
        }

        // Vérification et renommage de amount_laia
        $checkLaia = $fam_pdo->query("SHOW COLUMNS FROM pf_alloc_values LIKE 'amount_laia'")->rowCount();
        if ($checkLaia > 0) {
            $fam_pdo->exec("ALTER TABLE pf_alloc_values CHANGE amount_laia amount_p2 FLOAT DEFAULT 0");
            echo "<span style='color:green'>amount_laia -> amount_p2</span></li>";
        } else {
            echo "<span style='color:gray'>amount_p2 OK</span></li>";
        }

        // ==========================================
        // 3. GESTION DU BUDGET (pf_alloc_categories)
        // ==========================================
        echo "<li><strong>Table pf_alloc_categories :</strong> ";
        $stmtCats1 = $fam_pdo->exec("UPDATE pf_alloc_categories SET name = 'Eco P1' WHERE name LIKE 'Eco Alex%'");
        $stmtCats2 = $fam_pdo->exec("UPDATE pf_alloc_categories SET name = 'Eco P2' WHERE name LIKE 'Eco Laia%'");
        echo "<span style='color:green'>Catégories 'Eco' génériques mises à jour (" . ($stmtCats1 + $stmtCats2) . " lignes).</span></li>";

        // ==========================================
        // 4. GESTION DU BUDGET (pf_salary_config)
        // ==========================================
        echo "<li><strong>Table pf_salary_config :</strong> ";
        // On récupère les vrais prénoms pour les remplacer dans les configs de salaires
        $stmtParents = $fam_pdo->query("SELECT name FROM pf_people WHERE role = 'parent' ORDER BY id ASC");
        $parents = $stmtParents->fetchAll();
        
        if (count($parents) >= 2) {
            $p1_name = $parents[0]['name'];
            $p2_name = $parents[1]['name'];
            
            $stmtSal1 = $fam_pdo->prepare("UPDATE pf_salary_config SET person = ? WHERE person = 'Alex'");
            $stmtSal1->execute([$p1_name]);
            
            $stmtSal2 = $fam_pdo->prepare("UPDATE pf_salary_config SET person = ? WHERE person = 'Laia'");
            $stmtSal2->execute([$p2_name]);
            
            echo "<span style='color:green'>Salaires mappés vers $p1_name et $p2_name.</span></li>";
        } else {
            echo "<span style='color:orange'>Pas assez de parents trouvés pour mapper les salaires.</span></li>";
        }

    } catch (\PDOException $e) {
        echo "<li style='color:red'>❌ Erreur : " . $e->getMessage() . "</li>";
    }
    echo "</ul>";
}

echo "<h2>🎉 Migration terminée avec succès !</h2>";
?>