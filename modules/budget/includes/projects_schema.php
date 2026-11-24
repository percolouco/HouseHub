<?php
// Tables de l'onglet « Chantiers » du budget (créées au besoin dans la base de la famille courante)
function projectsEnsureSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if ($pdo->query("SHOW TABLES LIKE 'pf_projects'")->fetch()) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        icon VARCHAR(16) DEFAULT '🔨',
        status VARCHAR(20) NOT NULL DEFAULT 'idea',
        target_budget DECIMAL(12,2) DEFAULT NULL,
        dim_w DECIMAL(10,3) DEFAULT NULL,
        dim_l DECIMAL(10,3) DEFAULT NULL,
        dim_h DECIMAL(10,3) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        provision_id INT DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_project_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        kind VARCHAR(10) NOT NULL DEFAULT 'material',
        lot VARCHAR(100) DEFAULT '',
        name VARCHAR(200) NOT NULL DEFAULT '',
        store VARCHAR(100) DEFAULT '',
        url VARCHAR(500) DEFAULT '',
        qty DECIMAL(12,3) NOT NULL DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        unit_ttc DECIMAL(12,4) NOT NULL DEFAULT 0,
        unit_ht DECIMAL(12,4) NOT NULL DEFAULT 0,
        vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20,
        entered_ht TINYINT(1) NOT NULL DEFAULT 0,
        note VARCHAR(255) DEFAULT '',
        is_bought TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_project_tools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(150) NOT NULL DEFAULT '',
        lender VARCHAR(100) DEFAULT '',
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_project_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(200) NOT NULL DEFAULT '',
        url VARCHAR(500) NOT NULL DEFAULT '',
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Totaux d'un chantier : TTC / HT, dont main d'œuvre, déjà acheté, nombre de lignes
function projectTotals(PDO $pdo, int $projectId): array {
    $st = $pdo->prepare("SELECT kind, is_bought, SUM(qty * unit_ttc) AS ttc, SUM(qty * unit_ht) AS ht, COUNT(*) AS n FROM pf_project_items WHERE project_id = ? GROUP BY kind, is_bought");
    $st->execute([$projectId]);
    $t = ['ttc' => 0.0, 'ht' => 0.0, 'labor_ttc' => 0.0, 'bought_ttc' => 0.0, 'lines' => 0, 'bought_lines' => 0];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $t['ttc'] += (float)$r['ttc']; $t['ht'] += (float)$r['ht']; $t['lines'] += (int)$r['n'];
        if ($r['kind'] === 'labor') $t['labor_ttc'] += (float)$r['ttc'];
        if ((int)$r['is_bought'] === 1) { $t['bought_ttc'] += (float)$r['ttc']; $t['bought_lines'] += (int)$r['n']; }
    }
    foreach (['ttc', 'ht', 'labor_ttc', 'bought_ttc'] as $k) $t[$k] = round($t[$k], 2);
    return $t;
}

// Ligne d'achat : prix saisi (TTC ou HT) + TVA -> les deux prix unitaires
function projectPrices(float $price, bool $enteredHt, float $vat): array {
    $f = 1 + max(0, $vat) / 100;
    return $enteredHt ? ['ht' => $price, 'ttc' => $price * $f] : ['ht' => $price / $f, 'ttc' => $price];
}
