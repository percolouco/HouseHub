<?php
require __DIR__ . '/includes/db.php';

try {
    // Rétablit l'auto-incrémentation sur la colonne id
    $pdo->exec("ALTER TABLE pf_leave_snapshots MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT;");
    echo "AUTO_INCREMENT rétabli avec succès sur pf_leave_snapshots. 🚀<br>";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}