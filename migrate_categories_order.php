<?php
// migrate_categories_order.php

// ============================================================================
// CONFIGURATION DE CONNEXION (À ADAPTER)
// ============================================================================
require __DIR__ . '/includes/db.php'; 


echo "<h2>🚀 Migration : Ajout de l'ordre des catégories</h2>";

try {
    // Connexion au serveur MySQL (sans spécifier de base de données particulière)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Trouver toutes les bases de données de la famille HouseHub
    $stmt = $pdo->query("SHOW DATABASES LIKE 'househub_f%'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($databases)) {
        echo "<p style='color: #ef4444;'>❌ Aucune base de données correspondant à 'househub_f*' n'a été trouvée.</p>";
        exit;
    }

    echo "<p>🔍 <strong>" . count($databases) . "</strong> base(s) de données trouvée(s).</p>";

    // 2. Boucler sur chaque base de données
    foreach ($databases as $db) {
        echo "<hr><div style='padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px;'>";
        echo "<h4 style='margin-top: 0;'>📂 Traitement de la base : <strong>$db</strong></h4>";

        // Sélectionner la base de données active
        $pdo->exec("USE `$db`");

        // Vérifier si la table pf_budget_categories existe dans cette DB
        $tableExists = $pdo->query("SHOW TABLES LIKE 'pf_budget_categories'")->rowCount() > 0;

        if ($tableExists) {
            // Vérifier si la colonne sort_order existe déjà (sécurité)
            $columnExists = $pdo->query("SHOW COLUMNS FROM pf_budget_categories LIKE 'sort_order'")->rowCount() > 0;

            if (!$columnExists) {
                // La colonne n'existe pas, on l'ajoute !
                $pdo->exec("ALTER TABLE pf_budget_categories ADD sort_order INT DEFAULT 0");
                echo "<span style='color: #10b981;'>✅ Colonne 'sort_order' ajoutée avec succès !</span>";
            } else {
                // La colonne est déjà là
                echo "<span style='color: #f59e0b;'>ℹ️ La colonne 'sort_order' existe déjà (Ignoré).</span>";
            }
        } else {
            echo "<span style='color: #ef4444;'>⚠️ La table 'pf_budget_categories' n'existe pas ici.</span>";
        }
        echo "</div>";
    }

    echo "<h3>🎉 Migration terminée avec succès !</h3>";
    echo "<p style='color: #64748b;'><em>Tu peux maintenant supprimer ce fichier par mesure de sécurité.</em></p>";

} catch (PDOException $e) {
    echo "<h3 style='color: #ef4444;'>❌ Erreur fatale MySQL :</h3>";
    echo "<pre style='background: #fee2e2; padding: 15px; border-radius: 8px;'>" . $e->getMessage() . "</pre>";
}
?>