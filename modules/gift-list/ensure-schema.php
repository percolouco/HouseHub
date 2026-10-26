<?php
// modules/gift-list/ensure-schema.php
// Ajoute la colonne « acheté » aux bases existantes (une base par famille).

function gift_ensure_schema(PDO $pdo): void
{
    try {
        if ($pdo->query("SHOW COLUMNS FROM pf_gifts LIKE 'is_bought'")->fetch()) {
            return;
        }
        $pdo->exec("ALTER TABLE pf_gifts ADD COLUMN is_bought TINYINT(1) NOT NULL DEFAULT 0");
        // Les cadeaux déjà saisis avec un montant étaient des achats réalisés
        $pdo->exec("UPDATE pf_gifts SET is_bought = 1 WHERE amount > 0");
    } catch (Throwable $e) {
        // table absente ou droits insuffisants : la page fonctionnera sans la case « acheté »
    }
}
