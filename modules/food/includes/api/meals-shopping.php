<?php
// modules/food/includes/api/meals-shopping.php
// Aperçu « liste de courses » d'une période du planning des repas.
// GET ?from=YYYY-MM-DD&to=YYYY-MM-DD  ->  aliments à acheter (cantine exclue), repas ignorés, repas non reconnus, listes disponibles.
// L'ajout à la liste se fait ensuite via api-liste.php (action=items), aliment par aliment.
ini_set('display_errors', 0);
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require dirname(__DIR__, 4) . '/includes/i18n.php';
require dirname(__DIR__) . '/meals_lib.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    meals_ensure_schema($pdo);
    $from = $_GET['from'] ?? date('Y-m-d');
    $to   = $_GET['to'] ?? date('Y-m-d', strtotime('+13 days'));
    foreach ([$from, $to] as $d) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) { echo json_encode(['success' => false, 'error' => 'Dates invalides']); exit; }
    }
    if ($to < $from || (strtotime($to) - strtotime($from)) > 62 * 86400) { echo json_encode(['success' => false, 'error' => 'Période invalide (62 jours max)']); exit; }

    // Recettes de l'utilisateur (titre -> ingrédients)
    $recipes = [];
    try {
        $rs = $pdo->query("SELECT id, title FROM pf_recipes")->fetchAll(PDO::FETCH_ASSOC);
        if ($rs) {
            $ing = [];
            foreach ($pdo->query("SELECT recipe_id, name FROM pf_recipe_ingredients ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC) as $i) {
                $n = trim(preg_replace('/\s+/', ' ', $i['name']));
                if ($n !== '') $ing[$i['recipe_id']][] = mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
            }
            foreach ($rs as $r) if (!empty($ing[$r['id']])) $recipes[] = ['norm' => meals_norm($r['title']), 'ingredients' => $ing[$r['id']]];
            usort($recipes, fn($a, $b) => strlen($b['norm']) <=> strlen($a['norm']));
        }
    } catch (Throwable $e) { $recipes = []; }

    $st = $pdo->prepare("SELECT plan_date, service, person_id, meal_name, is_canteen FROM pf_meals_plan WHERE plan_date BETWEEN ? AND ? AND meal_name IS NOT NULL AND TRIM(meal_name) <> '' ORDER BY plan_date, service, person_id");
    $st->execute([$from, $to]);

    $dayTr = ['mon' => 'day_mon', 'tue' => 'day_tue', 'wed' => 'day_wed', 'thu' => 'day_thu', 'fri' => 'day_fri', 'sat' => 'day_sat', 'sun' => 'day_sun'];
    $slotLabel = function (string $date, string $svc) use ($dayTr) {
        $ts = strtotime($date);
        return tr($dayTr[strtolower(date('D', $ts))]) . ' ' . date('d/m', $ts) . ' ' . ($svc === 'lunch' ? tr('meal_lunch') : tr('meal_dinner'));
    };

    $items = [];      // clé normalisée => ['label', 'count', 'meals' => []]
    $skipped = [];    // cantine / restes / restaurant
    $unknown = [];    // repas sans aliment reconnu
    $seen = [];       // un même repas saisi pour plusieurs personnes ne compte qu'une fois
    $canteenSeen = [];

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $txt = trim($row['meal_name']);
        $key = $row['plan_date'] . '|' . $row['service'] . '|' . meals_norm($txt);
        $label = $slotLabel($row['plan_date'], $row['service']);

        if ((int)$row['is_canteen'] === 1) {
            if (!isset($canteenSeen[$key])) { $canteenSeen[$key] = 1; $skipped[] = ['when' => $label, 'meal' => $txt, 'why' => 'canteen']; }
            continue;
        }
        if (isset($seen[$key])) continue;
        $seen[$key] = 1;

        $x = meals_extract($txt, $recipes);
        if ($x['skip']) { if ($x['skip'] === 'out') $skipped[] = ['when' => $label, 'meal' => $txt, 'why' => 'out']; continue; }
        foreach ($x['foods'] as $f) {
            $k = meals_norm($f);
            if (!isset($items[$k])) $items[$k] = ['label' => $f, 'count' => 0, 'meals' => [], 'detail' => []];
            $items[$k]['count']++;
            $items[$k]['meals'][] = $label;
            $items[$k]['detail'][] = $label . ' — ' . $txt;
        }
        if (!$x['foods'] || $x['unknown']) $unknown[] = ['when' => $label, 'meal' => $txt, 'partial' => (bool)$x['foods']];
    }

    uasort($items, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

    // Listes de courses existantes (la liste par défaut est la première)
    $lists = [];
    try { $lists = $pdo->query("SELECT id, name FROM pf_lists ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

    echo json_encode(['success' => true, 'from' => $from, 'to' => $to, 'items' => array_values($items), 'skipped' => $skipped, 'unknown' => $unknown, 'lists' => $lists], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
