<?php
// migrate.php

// 1. Initialisation autonome de la connexion Meta
require __DIR__ . '/includes/meta_db.php';

try {
    // Ajout de la colonne amount
    $pdo->exec("ALTER TABLE pf_import_rules ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) DEFAULT NULL AFTER keyword");
    
    // Suppression de la contrainte d'unicité sur le keyword si elle existe
    $hasIndex = $pdo->query("SHOW INDEX FROM pf_import_rules WHERE Key_name = 'keyword'")->fetch();
    if ($hasIndex) {
        $pdo->exec("ALTER TABLE pf_import_rules DROP INDEX keyword");
    }
} catch (Exception $e) {
    // Log l'erreur si besoin
}

// 3. Chargement sécurisé de l'authentification (qui fonctionne maintenant)
require __DIR__ . '/includes/auth.php';
require_login();

// 4. Sécurisation de la page
if (empty($_SESSION['user']['is_admin'])) {
    die("Accès refusé. Droits administrateur requis.");
}

echo "<h1>Migration Meta</h1>";
echo "🎉 L'infrastructure est à jour et la session a pu démarrer correctement.";