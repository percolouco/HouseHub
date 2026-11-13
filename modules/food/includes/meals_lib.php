<?php
// modules/food/includes/meals_lib.php
// Outils du planning des repas : colonne « cantine » + extraction des aliments d'un menu pour la liste de courses.

/** Ajoute la colonne is_canteen (repas importés d'un PDF de cantine : à ne pas préparer). Idempotent. */
function meals_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $has = $pdo->query("SHOW COLUMNS FROM pf_meals_plan LIKE 'is_canteen'")->fetch();
        if (!$has) $pdo->exec("ALTER TABLE pf_meals_plan ADD COLUMN is_canteen TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) { /* base sans la table : rien à faire */ }
}

/** minuscules, sans accents, ponctuation -> espaces */
function meals_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a','ù'=>'u','û'=>'u','ü'=>'u','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ç'=>'c','œ'=>'oe','æ'=>'ae','’'=>"'"]);
    $s = preg_replace("/[^a-z0-9+,;\/&']+/", ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Repas « on ne prépare rien » : restes, restaurant, chez des amis… */
function meals_is_skip(string $norm): ?string {
    if (preg_match('/^(x|rien|aucun|non|pas de repas|\?)$/', $norm)) return 'empty';
    if (preg_match('/\b(reste|restes|resto|restaurant|mcdo|macdo|mc do|kfc|burger king|quick|traiteur|cantine|chez|invite|invites|pique nique|piquenique|sortie|jeune|livraison|commande)\b/', $norm)) return 'out';
    return null;
}

/**
 * Dictionnaire : mot / expression (normalisé) => liste d'aliments à acheter.
 * Les expressions les plus longues sont testées d'abord (« pomme de terre » avant « pomme »).
 */
function meals_dictionary(): array {
    static $d = null;
    if ($d !== null) return $d;
    $foods = [
        // légumes
        'carotte'=>'Carottes','courgette'=>'Courgettes','aubergine'=>'Aubergines','poivron'=>'Poivrons','tomate'=>'Tomates','tomates cerises'=>'Tomates cerises',
        'concombre'=>'Concombre','salade'=>'Salade','laitue'=>'Salade','mache'=>'Mâche','epinard'=>'Épinards','brocoli'=>'Brocolis','chou fleur'=>'Chou-fleur',
        'chou'=>'Chou','choux de bruxelles'=>'Choux de Bruxelles','haricot vert'=>'Haricots verts','haricots verts'=>'Haricots verts','haricot blanc'=>'Haricots blancs',
        'haricot rouge'=>'Haricots rouges','flageolet'=>'Flageolets','petit pois'=>'Petits pois','pois chiche'=>'Pois chiches','lentille'=>'Lentilles',
        'champignon'=>'Champignons','poireau'=>'Poireaux','oignon'=>'Oignons','echalote'=>'Échalotes','ail'=>'Ail','pomme de terre'=>'Pommes de terre',
        'patate douce'=>'Patates douces','patate'=>'Pommes de terre','puree'=>'Pommes de terre','navet'=>'Navets','radis'=>'Radis','betterave'=>'Betteraves',
        'fenouil'=>'Fenouil','endive'=>'Endives','celeri'=>'Céleri','butternut'=>'Butternut','courge'=>'Courge','potiron'=>'Potiron','avocat'=>'Avocats',
        'mais'=>'Maïs','legumes'=>'Légumes','legume'=>'Légumes','crudites'=>'Crudités','asperge'=>'Asperges','artichaut'=>'Artichauts',
        // fruits
        'pomme'=>'Pommes','poire'=>'Poires','banane'=>'Bananes','orange'=>'Oranges','clementine'=>'Clémentines','mandarine'=>'Mandarines','kiwi'=>'Kiwis',
        'fraise'=>'Fraises','framboise'=>'Framboises','raisin'=>'Raisin','ananas'=>'Ananas','melon'=>'Melon','pasteque'=>'Pastèque','peche'=>'Pêches',
        'abricot'=>'Abricots','cerise'=>'Cerises','prune'=>'Prunes','citron'=>'Citrons','fruit'=>'Fruits','fruits'=>'Fruits','salade de fruits'=>'Fruits','compote'=>'Compote',
        // viandes
        'poulet'=>'Poulet','dinde'=>'Dinde','canard'=>'Canard','magret'=>'Magret de canard','steak hache'=>'Steaks hachés','steak'=>'Steaks','viande hachee'=>'Viande hachée',
        'hache'=>'Viande hachée','boeuf'=>'Bœuf','veau'=>'Veau','porc'=>'Porc','agneau'=>'Agneau','jambon'=>'Jambon','lardon'=>'Lardons','saucisse'=>'Saucisses',
        'merguez'=>'Merguez','chipolata'=>'Chipolatas','saucisson'=>'Saucisson','cordon bleu'=>'Cordons bleus','nugget'=>'Nuggets','escalope'=>'Escalopes',
        'poulet roti'=>'Poulet','roti de porc'=>'Rôti de porc','roti de boeuf'=>'Rôti de bœuf','roti de veau'=>'Rôti de veau','roti'=>'Rôti','filet mignon'=>'Filet mignon','cote de porc'=>'Côtes de porc','gigot'=>'Gigot','lapin'=>'Lapin','boudin'=>'Boudin','paupiette'=>'Paupiettes',
        'brochette'=>'Brochettes','knacki'=>'Knacki','cuisse de poulet'=>'Cuisses de poulet','blanc de poulet'=>'Blancs de poulet','viande'=>'Viande',
        // poissons
        'poisson pane'=>'Poisson pané','poisson'=>'Poisson','saumon'=>'Saumon','cabillaud'=>'Cabillaud','colin'=>'Colin','merlu'=>'Merlu','thon'=>'Thon',
        'truite'=>'Truite','dorade'=>'Dorade','sardine'=>'Sardines','crevette'=>'Crevettes','moule'=>'Moules','lieu'=>'Lieu',
        // féculents
        'pates'=>'Pâtes','spaghetti'=>'Spaghettis','tagliatelle'=>'Tagliatelles','coquillette'=>'Coquillettes','macaroni'=>'Macaronis','penne'=>'Penne',
        'fusilli'=>'Fusilli','riz'=>'Riz','semoule'=>'Semoule','ble'=>'Blé','quinoa'=>'Quinoa','boulgour'=>'Boulgour','pain'=>'Pain','baguette'=>'Baguette',
        'frites'=>'Frites','gnocchi'=>'Gnocchis','polenta'=>'Polenta','wrap'=>'Wraps','tortilla'=>'Tortillas','galette'=>'Galettes','pain de mie'=>'Pain de mie',
        // frais / épicerie
        'oeuf'=>'Œufs','fromage blanc'=>'Fromage blanc','fromage'=>'Fromage','yaourt'=>'Yaourts','mozzarella'=>'Mozzarella','feta'=>'Feta','chevre'=>'Fromage de chèvre',
        'camembert'=>'Camembert','emmental'=>'Emmental','comte'=>'Comté','parmesan'=>'Parmesan','gruyere'=>'Gruyère','beurre'=>'Beurre','lait'=>'Lait',
        'creme fraiche'=>'Crème fraîche','pesto'=>'Pesto','coulis de tomate'=>'Coulis de tomate','sauce tomate'=>'Sauce tomate','ketchup'=>'Ketchup',
        'mayonnaise'=>'Mayonnaise','moutarde'=>'Moutarde','soupe'=>'Soupe','potage'=>'Potage','veloute'=>'Velouté','pizza'=>'Pizza','sandwich'=>'Pain de mie',
        'glace'=>'Glace','mousse au chocolat'=>'Mousse au chocolat','flan'=>'Flan','creme dessert'=>'Crèmes dessert','chocolat'=>'Chocolat','biscuit'=>'Biscuits',
        'brioche'=>'Brioche','croissant'=>'Croissants','confiture'=>'Confiture','cereales'=>'Céréales','miel'=>'Miel',
    ];
    $d = [];
    foreach ($foods as $k => $v) $d[$k] = [$v];
    // plats : une préparation = plusieurs ingrédients
    $dishes = [
        'lasagne' => ['Pâtes à lasagnes', 'Viande hachée', 'Coulis de tomate', 'Béchamel', 'Fromage râpé'],
        'hachis parmentier' => ['Viande hachée', 'Pommes de terre', 'Fromage râpé'],
        'carbonara' => ['Pâtes', 'Lardons', 'Crème fraîche', 'Œufs'],
        'bolognaise' => ['Pâtes', 'Viande hachée', 'Coulis de tomate', 'Oignons'],
        'quiche' => ['Pâte brisée', 'Œufs', 'Crème fraîche', 'Lardons', 'Fromage râpé'],
        'croque monsieur' => ['Pain de mie', 'Jambon', 'Fromage râpé'],
        'gratin dauphinois' => ['Pommes de terre', 'Crème fraîche', 'Fromage râpé'],
        'crepe' => ['Farine', 'Œufs', 'Lait'],
        'omelette' => ['Œufs'],
        'tartiflette' => ['Pommes de terre', 'Reblochon', 'Lardons', 'Oignons'],
        'raclette' => ['Fromage à raclette', 'Pommes de terre', 'Charcuterie'],
        'fondue savoyarde' => ['Fromage à fondue', 'Pain'],
        'pot au feu' => ['Bœuf à pot-au-feu', 'Carottes', 'Poireaux', 'Navets', 'Pommes de terre'],
        'blanquette' => ['Veau', 'Carottes', 'Champignons', 'Riz', 'Crème fraîche'],
        'bourguignon' => ['Bœuf bourguignon', 'Carottes', 'Oignons', 'Lardons'],
        'couscous' => ['Semoule', 'Merguez', 'Courgettes', 'Carottes', 'Pois chiches'],
        'chili con carne' => ['Viande hachée', 'Haricots rouges', 'Tomates concassées', 'Riz'],
        'chili' => ['Viande hachée', 'Haricots rouges', 'Tomates concassées', 'Riz'],
        'tacos' => ['Galettes tortillas', 'Poulet', 'Poivrons', 'Fromage râpé'],
        'fajitas' => ['Galettes tortillas', 'Poulet', 'Poivrons', 'Fromage râpé'],
        'burger' => ['Pains à burger', 'Steaks hachés', 'Fromage à burger', 'Salade', 'Tomates'],
        'hot dog' => ['Pains à hot-dog', 'Saucisses'],
        'salade nicoise' => ['Salade', 'Tomates', 'Thon', 'Œufs', 'Olives'],
        'ratatouille' => ['Courgettes', 'Aubergines', 'Poivrons', 'Tomates', 'Oignons'],
        'risotto' => ['Riz à risotto', 'Bouillon', 'Parmesan', 'Oignons'],
        'pizza maison' => ['Pâte à pizza', 'Coulis de tomate', 'Mozzarella', 'Jambon'],
        'gratin de pates' => ['Pâtes', 'Fromage râpé', 'Crème fraîche'],
        'croque' => ['Pain de mie', 'Jambon', 'Fromage râpé'],
        'cake sale' => ['Farine', 'Œufs', 'Lardons', 'Fromage râpé'],
    ];
    foreach ($dishes as $k => $v) $d[$k] = $v;
    uksort($d, fn($a, $b) => strlen($b) <=> strlen($a));
    return $d;
}

/** Mots qui, seuls, ne méritent pas d'être signalés comme « non reconnu » */
const MEALS_FILLER = ['de','du','des','la','le','les','au','aux','a','en','et','sauce','maison','nature','frais','fraiche','bio','chaud','chaude','froid','cuit','grille','grillee','rotie','four','vapeur','poele','poelee','fondant','tendre','petit','petits','petite','petites','gratin','gratine','gratinee','legumes','dessert','plat','entree','avec','sans','un','une','d','l'];

/**
 * Extrait les aliments d'un texte de repas.
 * @return array{foods: string[], unknown: string[], skip: ?string, recipe: bool}
 */
function meals_extract(string $text, array $recipes = []): array {
    $norm = meals_norm($text);
    $res = ['foods' => [], 'unknown' => [], 'skip' => null, 'recipe' => false];
    if ($norm === '') return $res;
    if ($skip = meals_is_skip($norm)) { $res['skip'] = $skip; return $res; }

    // 1. Recettes de l'utilisateur : le titre apparaît dans le repas (ou l'inverse)
    foreach ($recipes as $r) {
        if (strlen($r['norm']) >= 4 && (str_contains($norm, $r['norm']) || (strlen($norm) >= 5 && str_contains($r['norm'], $norm)))) {
            foreach ($r['ingredients'] as $ing) $res['foods'][] = $ing;
            $res['recipe'] = true;
            return $res;
        }
    }

    // 2. Dictionnaire, par segments (« poulet + riz, salade »)
    $segments = preg_split('/\s*(?:\+|,|;|\/|&|\bet\b|\bavec\b)\s*/u', $norm) ?: [$norm];
    $dict = meals_dictionary();
    foreach ($segments as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        $found = false;
        $segFoods = [];
        foreach ($dict as $key => $labels) {
            $re = '/(?<![a-z])' . implode("[ ']?", array_map(fn($w) => preg_quote($w, '/') . '(?:s|x)?', explode(' ', $key))) . '(?![a-z])/';
            if (preg_match($re, $seg)) {
                foreach ($labels as $l) $segFoods[] = $l;
                $seg = trim(preg_replace($re, ' ', $seg)); // consommé : « pomme de terre » ne redonne pas « pomme »
                $found = true;
            }
        }
        // « soupe de courgettes » : la courgette suffit, pas besoin de « soupe » en plus
        if (count($segFoods) > 1) $segFoods = array_values(array_diff($segFoods, ['Soupe', 'Potage', 'Velouté']));
        foreach ($segFoods as $l) $res['foods'][] = $l;
        $left = array_filter(preg_split('/[\s\']+/', $seg), fn($w) => strlen($w) >= 3 && !in_array($w, MEALS_FILLER, true));
        if (!$found && $left) $res['unknown'][] = trim($seg);
    }
    $res['foods'] = array_values(array_unique($res['foods']));
    return $res;
}
