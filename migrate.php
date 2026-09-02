<?php
require __DIR__ . '/includes/db.php';

try {
    // 1. On s'assure que 'id' est bien défini comme clé primaire (corrige l'erreur 1075)
    $pdo->exec("ALTER TABLE pf_leave_snapshots ADD PRIMARY KEY (id);");
    echo "Clé primaire ajoutée avec succès sur pf_leave_snapshots.<br>";
} catch (PDOException $e) {
    // Si la clé primaire existe déjà d'une façon ou d'une autre, on ignore l'erreur
    echo "Info (Clé primaire) : " . $e->getMessage() . "<br>";
}

try {
    // 2. Maintenant que la clé est garantie, on peut ajouter l'AUTO_INCREMENT en toute sécurité
    $pdo->exec("ALTER TABLE pf_leave_snapshots MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT;");
    echo "AUTO_INCREMENT rétabli avec succès sur pf_leave_snapshots. 🚀<br>";
} catch (PDOException $e) {
    echo "Erreur (Auto-increment) : " . $e->getMessage() . "<br>";
}