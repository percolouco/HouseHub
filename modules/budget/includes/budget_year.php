<?php
// modules/budget/includes/budget_year.php
// Passage d'une année à l'autre : les salaires / mensualités sont rangés par année (pf_salary_config).
// Si l'année demandée n'a encore aucune ligne, on reprend celles de la dernière année configurée avant elle,
// pour que le budget ne retombe pas à zéro le 1er janvier. Les années futures et passées ne sont pas touchées.
function budgetEnsureSalaryYear(PDO $pdo, int $year): void {
    if ($year > (int)date('Y')) return;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM pf_salary_config WHERE year = ?");
        $st->execute([$year]);
        if ((int)$st->fetchColumn() > 0) return;
        $st = $pdo->prepare("SELECT MAX(year) FROM pf_salary_config WHERE year < ?");
        $st->execute([$year]);
        $src = $st->fetchColumn();
        if (!$src) return;
        $pdo->prepare("INSERT IGNORE INTO pf_salary_config (year, person, salary, mensualite, frais_func, eco_perso, eco_family)
                       SELECT ?, person, salary, mensualite, frais_func, eco_perso, eco_family FROM pf_salary_config WHERE year = ?")
            ->execute([$year, (int)$src]);
    } catch (Throwable $e) { /* lecture seule ou création concurrente : sans gravité */ }
}
