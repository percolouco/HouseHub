<?php
// migrate_attachments.php

// 1. Inclusion de ta connexion BDD principale (Ajuste le chemin si besoin)
require __DIR__ . '/includes/db.php'; 

echo "<pre>🚀 Lancement de la migration multi-bases...\n\n";

try {
    // 2. Récupérer toutes les bases de données qui commencent par "househub_f"
    $stmt = $pdo->query("SHOW DATABASES LIKE 'househub_f%'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($databases)) {
        die("❌ Aucune base de données familiale trouvée (Format househub_f1, househub_f2...).\n");
    }

    $successCount = 0;

    foreach ($databases as $dbName) {
        // Sécurité : on vérifie le format exact (househub_f suivi de chiffres)
        if (preg_match('/^househub_f\d+$/', $dbName)) {
            echo "⚙️ Mise à jour de la base : <strong>$dbName</strong>... ";
            
            // On bascule la connexion sur la base de la famille
            $pdo->exec("USE `$dbName`");
            
            // 3. Création de la table
            $sql = "CREATE TABLE IF NOT EXISTS pf_holidays_attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                holiday_id INT NOT NULL,
                item_id INT DEFAULT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_holiday_attachment FOREIGN KEY (holiday_id) REFERENCES pf_holidays(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $pdo->exec($sql);
            echo "✅ OK\n";
            $successCount++;
        }
    }
    
    echo "\n🎉 Migration terminée avec succès sur $successCount base(s) familiale(s) !</pre>";
    
} catch (PDOException $e) {
    die("\n❌ Erreur SQL pendant la migration : " . $e->getMessage() . "</pre>");
}