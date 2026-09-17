<?php
// migrate.php

// 1. Initialisation autonome de la connexion Meta
require __DIR__ . '/includes/meta_db.php';

// 2. FIX CHICKEN & EGG 🛑 : Création de la colonne AVANT de charger l'authentification
try {
    $checkMetaCol = $meta_pdo->query("SHOW COLUMNS FROM families LIKE 'enabled_views'");
    if ($checkMetaCol->rowCount() == 0) {
        $meta_pdo->exec("ALTER TABLE families ADD COLUMN enabled_views JSON DEFAULT NULL AFTER enabled_modules");
        echo "✅ Colonne 'enabled_views' ajoutée à la table families (Bypass Auth).<br><br>";
    }
} catch (Exception $e) {
    die("❌ Erreur critique lors de la mise à jour structurelle : " . $e->getMessage());
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