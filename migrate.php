<?php
// migrate.php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/meta_db.php';
require_login();

// Sécurité : Accès limité aux administrateurs
if (empty($_SESSION['user']['is_admin'])) {
    die("Accès refusé. Droits administrateur requis.");
}

try {
    echo "<h1>Migration de la base Meta (househub_meta)</h1>";
    echo "Vérification de la structure de la table 'families'...<br>";

    // Vérification de l'existence de la colonne pour rendre le script idempotent
    $checkMetaCol = $meta_pdo->query("SHOW COLUMNS FROM families LIKE 'enabled_views'");
    
    if ($checkMetaCol->rowCount() == 0) {
        // Ajout de la colonne JSON
        $meta_pdo->exec("ALTER TABLE families ADD COLUMN enabled_views JSON DEFAULT NULL AFTER enabled_modules");
        echo "✅ <strong>Succès :</strong> Colonne 'enabled_views' ajoutée à househub_meta.families.<br><br>";
    } else {
        echo "ℹ️ <strong>Info :</strong> La colonne 'enabled_views' est déjà présente. Aucune modification requise.<br><br>";
    }

    echo "🎉 Migration Meta terminée avec succès.";

} catch (Exception $e) {
    die("❌ <strong>Erreur lors de la migration :</strong> " . $e->getMessage());
}