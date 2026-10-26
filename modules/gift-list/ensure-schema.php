<?php
// modules/gift-list/ensure-schema.php
// Met la table pf_gifts à niveau (une base par famille) : colonne « acheté » et structure
// des anciennes bases (id sans AUTO_INCREMENT, occasion limitée à une liste fixe, champs trop courts).

function gift_ensure_schema(PDO $pdo): void
{
    try {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM pf_gifts")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[$c['Field']] = $c;
        }
        if (!$cols) return;

        if (isset($cols['id']) && stripos($cols['id']['Extra'], 'auto_increment') === false) {
            $pk = $cols['id']['Key'] === 'PRI' ? '' : ', ADD PRIMARY KEY (id)';
            $pdo->exec("ALTER TABLE pf_gifts MODIFY id INT NOT NULL AUTO_INCREMENT" . $pk);
        }
        if (isset($cols['occasion']) && stripos($cols['occasion']['Type'], 'enum') === 0) {
            $pdo->exec("ALTER TABLE pf_gifts MODIFY occasion VARCHAR(50) NOT NULL");
        }
        foreach (['adult_name' => 'VARCHAR(100) NOT NULL', 'child_name' => 'VARCHAR(100) NOT NULL'] as $f => $def) {
            if (isset($cols[$f]) && preg_match('/varchar\((\d+)\)/i', $cols[$f]['Type'], $m) && (int)$m[1] < 100) {
                $pdo->exec("ALTER TABLE pf_gifts MODIFY $f $def");
            }
        }
        if (isset($cols['gift_description']) && stripos($cols['gift_description']['Type'], 'varchar') === 0) {
            $pdo->exec("ALTER TABLE pf_gifts MODIFY gift_description TEXT NOT NULL");
        }
        if (isset($cols['product_link']) && preg_match('/varchar\((\d+)\)/i', $cols['product_link']['Type'], $m) && (int)$m[1] < 500) {
            $pdo->exec("ALTER TABLE pf_gifts MODIFY product_link VARCHAR(500) DEFAULT NULL");
        }

        if (!isset($cols['is_bought'])) {
            $pdo->exec("ALTER TABLE pf_gifts ADD COLUMN is_bought TINYINT(1) NOT NULL DEFAULT 0");
            // Les cadeaux déjà saisis avec un montant étaient des achats réalisés
            $pdo->exec("UPDATE pf_gifts SET is_bought = 1 WHERE amount > 0");
        }
    } catch (Throwable $e) {
        // table absente ou droits insuffisants : la page continue avec la structure existante
    }
}
