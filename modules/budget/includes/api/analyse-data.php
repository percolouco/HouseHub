<?php
// Données brutes de l'onglet « Analyse » du Budget : toutes les opérations de la famille courante (base tenant),
// agrégées côté navigateur (filtres, regroupements, graphiques). Lecture seule.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../../../includes/auth.php';
require __DIR__ . '/../../../../includes/db.php';
require_login();

try {
    $cats = $pdo->query("SELECT code, label, icon, type FROM pf_budget_categories ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
    $items = $pdo->query("SELECT id, name, category, is_estimate FROM pf_budget_items")->fetchAll(PDO::FETCH_ASSOC);
    // clés courtes (gain de poids) : d = date réelle, m = mois de gestion, c = catégorie, l = libellé, a = montant (dépense < 0), i = poste du budget, s = salaire
    $st = $pdo->query("SELECT date_exp AS d, SUBSTRING(gestion_month, 1, 7) AS m, category AS c, label AS l, amount AS a, budget_item_id AS i, salary_id AS s
                       FROM pf_expenses ORDER BY date_exp ASC, id ASC");
    $rows = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [$r['d'], $r['m'], (string)$r['c'], (string)$r['l'], (float)$r['a'], $r['i'] !== null ? (int)$r['i'] : null, $r['s'] !== null ? 1 : 0];
    }
    echo json_encode(['categories' => $cats, 'items' => $items, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de lecture']);
}
