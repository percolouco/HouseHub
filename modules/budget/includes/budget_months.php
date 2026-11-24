<?php
// Mois concernés par une ligne du budget (colonne pf_budget_items.active_months : « 1,2,...,10 », NULL = tous les mois)
function budgetItemActiveMonths(array $item): array {
    $v = trim((string)($item['active_months'] ?? ''));
    if ($v === '') return range(1, 12);
    $m = array_values(array_unique(array_filter(array_map('intval', explode(',', $v)), fn($x) => $x >= 1 && $x <= 12)));
    return $m ?: range(1, 12);
}
function budgetItemActiveIn(array $item, int $month): bool {
    return in_array($month, budgetItemActiveMonths($item), true);
}
