<?php
ob_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

header('Content-Type: application/json');

set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit;
});

require_once dirname(__DIR__, 2) . '/includes/db.php';

// ── Auto-create tables ────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS pf_lists (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL DEFAULT 'Ma liste',
  color VARCHAR(20) DEFAULT NULL, list_type VARCHAR(50) DEFAULT NULL,
  position INT NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_grocery_items (
  id INT AUTO_INCREMENT PRIMARY KEY, list_id INT NOT NULL DEFAULT 1,
  category_id INT DEFAULT NULL, label VARCHAR(500) NOT NULL,
  in_cart TINYINT(1) NOT NULL DEFAULT 0, position INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_items_list (list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_grocery_history (
  id INT AUTO_INCREMENT PRIMARY KEY, label_hash CHAR(64) NOT NULL,
  label_display VARCHAR(500) NOT NULL,
  last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grocery_hist_hash (label_hash), KEY idx_hist_last (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_list_categories (
  id INT AUTO_INCREMENT PRIMARY KEY, icon VARCHAR(10) NOT NULL DEFAULT '🏷️',
  name VARCHAR(100) NOT NULL, position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pf_item_category_rules (
  id INT AUTO_INCREMENT PRIMARY KEY, keyword VARCHAR(255) NOT NULL,
  category_id INT NOT NULL, UNIQUE KEY uq_keyword (keyword)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migrations
try { $pdo->exec("ALTER TABLE pf_grocery_items ADD COLUMN list_id INT NOT NULL DEFAULT 1 AFTER id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE pf_grocery_items ADD COLUMN category_id INT DEFAULT NULL AFTER list_id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE pf_grocery_items ADD KEY idx_items_list (list_id)"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE pf_lists ADD COLUMN color VARCHAR(20) DEFAULT NULL AFTER name"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE pf_lists ADD COLUMN list_type VARCHAR(50) DEFAULT NULL AFTER color"); } catch (\Exception $e) {}

// ── Seed categories if empty ──────────────────────────────────────────────────
if ((int)$pdo->query("SELECT COUNT(*) FROM pf_list_categories")->fetchColumn() === 0) {
    $cats = [
        ['🍎','Fruits & Légumes'],['🍖','Viande'],['🐟','Poissonnerie'],
        ['🍞','Boulangerie'],['🥛','Frais'],['❄️','Surgelés'],
        ['🧃','Boissons'],['🌾','Pâtes, Riz, Féculents'],['🥜','Épicerie salée'],
        ['🥫','Conserves'],['🍛','Plats cuisinés'],['🧂','Sauces & Condiments'],
        ['🥐','Petit déjeuner'],['🍪','Biscuits & Gâteaux'],['🍫','Confiserie'],
        ['🍦','Dessert'],['🧴','Beauté & Hygiène'],['🍼','Bébé'],
        ['🧼','Entretien'],['🐶','Animaux'],['🏡','Maison & Jardin'],['💊','Pharmacie'],
    ];
    $ins = $pdo->prepare("INSERT INTO pf_list_categories (icon, name, position) VALUES (?,?,?)");
    foreach ($cats as $i => $c) $ins->execute([$c[0], $c[1], $i * 10]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function liste_normalize(string $s): string {
    return mb_strtolower(trim(mb_substr($s, 0, 500)));
}

function liste_normalize_detect(string $s): string {
    $s = mb_strtolower(trim($s));
    $from = ['é','è','ê','ë','à','â','ä','ù','û','ü','î','ï','ô','ö','ç','œ','æ'];
    $to   = ['e','e','e','e','a','a','a','u','u','u','i','i','o','o','c','oe','ae'];
    $s = str_replace($from, $to, $s);
    return trim(preg_replace('/[^a-z0-9 ]/', '', $s));
}

function liste_touch_history(PDO $pdo, string $label): void {
    $hash = hash('sha256', liste_normalize($label));
    $pdo->prepare("INSERT INTO pf_grocery_history (label_hash, label_display) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE label_display=VALUES(label_display), last_used_at=NOW()")
        ->execute([$hash, trim($label)]);
}

function liste_ensure_default(PDO $pdo): int {
    if ((int)$pdo->query("SELECT COUNT(*) FROM pf_lists")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO pf_lists (name, position) VALUES ('Ma liste', 0)");
        return (int)$pdo->lastInsertId();
    }
    return (int)$pdo->query("SELECT id FROM pf_lists ORDER BY position, id LIMIT 1")->fetchColumn();
}

// Built-in keyword → category position mapping (position maps to seeded order above)
function liste_builtin_detect(string $normalized): ?int {
    static $map = null;
    if ($map === null) $map = [
        // 1 = Fruits & Légumes
        'pomme'=>1,'poire'=>1,'banane'=>1,'orange'=>1,'citron'=>1,'raisin'=>1,
        'fraise'=>1,'framboise'=>1,'myrtille'=>1,'cerise'=>1,'peche'=>1,'abricot'=>1,
        'mangue'=>1,'kiwi'=>1,'ananas'=>1,'melon'=>1,'pasteque'=>1,'prune'=>1,
        'tomate'=>1,'concombre'=>1,'carotte'=>1,'oignon'=>1,'poireau'=>1,
        'courgette'=>1,'aubergine'=>1,'poivron'=>1,'salade'=>1,'laitue'=>1,
        'epinard'=>1,'brocoli'=>1,'chou'=>1,'choux'=>1,'haricot'=>1,'pois'=>1,
        'champignon'=>1,'ail'=>1,'echalote'=>1,'pomme de terre'=>1,'patate'=>1,
        'navet'=>1,'radis'=>1,'betterave'=>1,'fenouil'=>1,'asperge'=>1,
        'artichaut'=>1,'celeri'=>1,'butternut'=>1,'courge'=>1,'potiron'=>1,
        'gingembre'=>1,'avocat'=>1,'endive'=>1,'ciboulette'=>1,'persil'=>1,
        'basilic'=>1,'menthe'=>1,'thym'=>1,'romarin'=>1,'coriandre'=>1,
        'girofle'=>1,'citron vert'=>1,'lime'=>1,'pamplemousse'=>1,'litchi'=>1,
        // 2 = Viande
        'boeuf'=>2,'veau'=>2,'poulet'=>2,'porc'=>2,'agneau'=>2,'dinde'=>2,
        'lapin'=>2,'jambon'=>2,'lardon'=>2,'saucisse'=>2,'saucisson'=>2,
        'merguez'=>2,'chipolata'=>2,'steak'=>2,'escalope'=>2,'roti'=>2,
        'gigot'=>2,'filet'=>2,'cuisses'=>2,'viande hachee'=>2,'andouille'=>2,
        'magret'=>2,'canard'=>2,'foie'=>2,'boudin'=>2,'paupiette'=>2,
        'entrecote'=>2,'cote de boeuf'=>2,'onglet'=>2,'bavette'=>2,
        // 3 = Poissonnerie
        'saumon'=>3,'thon'=>3,'dorade'=>3,'bar'=>3,'cabillaud'=>3,'sole'=>3,
        'truite'=>3,'lieu'=>3,'maquereau'=>3,'sardine'=>3,'anchois'=>3,
        'crevette'=>3,'moule'=>3,'huitre'=>3,'coquille'=>3,'poulpe'=>3,
        'seiche'=>3,'langoustine'=>3,'lotte'=>3,'merlu'=>3,'colin'=>3,
        'tilapia'=>3,'daurade'=>3,'aiglefin'=>3,'homard'=>3,'crabe'=>3,
        // 4 = Boulangerie
        'pain'=>4,'baguette'=>4,'brioche'=>4,'croissant'=>4,'pain de mie'=>4,
        'ficelle'=>4,'fougasse'=>4,'ciabatta'=>4,'biscotte'=>4,'naan'=>4,
        'pita'=>4,'toast'=>4,'bagel'=>4,'pain burger'=>4,'wrap'=>4,
        // 5 = Frais
        'lait'=>5,'creme'=>5,'yaourt'=>5,'yourt'=>5,'beurre'=>5,
        'fromage'=>5,'camembert'=>5,'brie'=>5,'comte'=>5,'gruyere'=>5,
        'emmental'=>5,'mozzarella'=>5,'ricotta'=>5,'mascarpone'=>5,
        'feta'=>5,'chevre'=>5,'oeuf'=>5,'oeufs'=>5,'creme fraiche'=>5,
        'fromage blanc'=>5,'petit suisse'=>5,'kefir'=>5,
        // 6 = Surgelés
        'surgele'=>6,'pizza surgelee'=>6,'frite surgelee'=>6,'poisson pane'=>6,
        'legume surgele'=>6,'glace'=>6,'sorbet'=>6,'plat surgele'=>6,
        'nugget'=>6,'edamame'=>6,'petits pois'=>6,'epinards'=>6,
        // 7 = Boissons
        'eau'=>7,'coca'=>7,'cola'=>7,'limonade'=>7,'sirop'=>7,
        'biere'=>7,'vin'=>7,'champagne'=>7,'cafe'=>7,'the'=>7,
        'tisane'=>7,'soda'=>7,'jus'=>7,'kombucha'=>7,'cidre'=>7,
        'whisky'=>7,'rhum'=>7,'vodka'=>7,'prosecco'=>7,'rose'=>7,
        // 8 = Pâtes, Riz, Féculents
        'pate'=>8,'spaghetti'=>8,'tagliatelle'=>8,'penne'=>8,'fusilli'=>8,
        'macaroni'=>8,'riz'=>8,'couscous'=>8,'quinoa'=>8,'lentille'=>8,
        'pois chiche'=>8,'farine'=>8,'semoule'=>8,'polenta'=>8,'boulgour'=>8,
        'orge'=>8,'feculent'=>8,'vermicelle'=>8,'nouille'=>8,'gnocchi'=>8,
        // 9 = Épicerie salée
        'chips'=>9,'crackers'=>9,'olives'=>9,'cornichon'=>9,'capres'=>9,
        'tapenade'=>9,'houmous'=>9,'tzatziki'=>9,'sel'=>9,'poivre'=>9,
        'epice'=>9,'cube bouillon'=>9,'bouillon'=>9,'noix de cajou'=>9,
        'amande'=>9,'pistache'=>9,'noix'=>9,'cacahuete'=>9,
        // 10 = Conserves
        'conserve'=>10,'boite de tomate'=>10,'concentre de tomate'=>10,
        'mais'=>10,'ratatouille'=>10,'cassoulet'=>10,'pate de foie'=>10,
        'sardine boite'=>10,'thon boite'=>10,'maquereau boite'=>10,
        'soupe boite'=>10,'haricot boite'=>10,
        // 11 = Plats cuisinés
        'quiche'=>11,'lasagne'=>11,'gratin'=>11,'pizza'=>11,'tartiflette'=>11,
        'croque'=>11,'hachis parmentier'=>11,'paella'=>11,'moussaka'=>11,
        'tarte'=>11,'flamiche'=>11,'pissaladiere'=>11,
        // 12 = Sauces & Condiments
        'ketchup'=>12,'mayonnaise'=>12,'moutarde'=>12,'vinaigrette'=>12,
        'sauce soja'=>12,'tabasco'=>12,'huile'=>12,'vinaigre'=>12,
        'sauce tomate'=>12,'pesto'=>12,'sriracha'=>12,'worcester'=>12,
        'nuoc mam'=>12,'sauce'=>12,'condiment'=>12,'worcestershire'=>12,
        // 13 = Petit déjeuner
        'cereale'=>13,'muesli'=>13,'granola'=>13,'corn flakes'=>13,
        'miel'=>13,'confiture'=>13,'nutella'=>13,'beurre de cacahuete'=>13,
        'sirop d erable'=>13,'sirop erable'=>13,'chocolat en poudre'=>13,
        // 14 = Biscuits & Gâteaux
        'biscuit'=>14,'gateau'=>14,'cookie'=>14,'madeleine'=>14,
        'financier'=>14,'quatre quarts'=>14,'brownie'=>14,'sable'=>14,
        'speculoos'=>14,'oreo'=>14,'lu'=>14,'petit beurre'=>14,'galette'=>14,
        'palmier'=>14,'macaron'=>14,'eclair'=>14,
        // 15 = Confiserie
        'chocolat'=>15,'bonbon'=>15,'reglisse'=>15,'caramel'=>15,
        'nougat'=>15,'marshmallow'=>15,'guimauve'=>15,'sucette'=>15,
        'chewing gum'=>15,'pastille'=>15,'calisson'=>15,
        // 16 = Dessert
        'yaourt dessert'=>16,'creme dessert'=>16,'mousse au chocolat'=>16,
        'tiramisu'=>16,'creme brulee'=>16,'flan'=>16,'crepe'=>16,
        'madeleine'=>16,'profiterole'=>16,'eclair'=>16,
        // 17 = Beauté & Hygiène
        'shampoing'=>17,'gel douche'=>17,'savon'=>17,'dentifrice'=>17,
        'deodorant'=>17,'crème visage'=>17,'crème corps'=>17,'rasoir'=>17,
        'mousse a raser'=>17,'coton'=>17,'lingette'=>17,'maquillage'=>17,
        'parfum'=>17,'brosse a dents'=>17,'fil dentaire'=>17,'serum'=>17,
        'hydratant'=>17,'demaquillant'=>17,
        // 18 = Bébé
        'couche'=>18,'biberon'=>18,'lait infantile'=>18,'compote bebe'=>18,
        'pot bebe'=>18,'puree bebe'=>18,'lingette bebe'=>18,'savon bebe'=>18,
        'creme bebe'=>18,'sucette bebe'=>18,
        // 19 = Entretien
        'lessive'=>19,'liquide vaisselle'=>19,'nettoyant'=>19,'degraissant'=>19,
        'deboucheur'=>19,'anticalcaire'=>19,'eponge'=>19,'serpillere'=>19,
        'sac poubelle'=>19,'papier toilette'=>19,'sopalin'=>19,'essuie tout'=>19,
        'aluminium'=>19,'film plastique'=>19,'sac congelation'=>19,
        'nettoyant wc'=>19,'desinfectant'=>19,'vitre'=>19,'javel'=>19,
        // 20 = Animaux
        'croquette'=>20,'patee'=>20,'litiere'=>20,'os'=>20,
        'friandise animale'=>20,'nourriture chat'=>20,'nourriture chien'=>20,
        // 21 = Maison & Jardin
        'ampoule'=>21,'pile'=>21,'bougie'=>21,'allumette'=>21,
        'ruban adhesif'=>21,'colle'=>21,'vis'=>21,'terreau'=>21,
        'engrais'=>21,'pot de fleur'=>21,'arrosoir'=>21,'graine'=>21,
        'tournevis'=>21,'marteau'=>21,'clou'=>21,'cle'=>21,
        // 22 = Pharmacie
        'doliprane'=>22,'ibuprofene'=>22,'paracetamol'=>22,'aspirine'=>22,
        'bandage'=>22,'pansement'=>22,'thermometre'=>22,'serum physiologique'=>22,
        'vitamine'=>22,'complement'=>22,'masque'=>22,'gant medical'=>22,
        'sirop'=>22,'antihistaminique'=>22,'antidouleur'=>22,
    ];

    // Check full phrase
    if (isset($map[$normalized])) return $map[$normalized];

    // Check individual words
    $words = explode(' ', $normalized);
    foreach ($words as $w) {
        if (strlen($w) >= 3 && isset($map[$w])) return $map[$w];
    }

    // Partial match (contains)
    foreach ($map as $kw => $catPos) {
        if (str_contains($normalized, $kw)) return $catPos;
    }

    return null;
}

function liste_detect_category(PDO $pdo, string $label): ?int {
    $norm = liste_normalize_detect($label);

    // 1. Learned rules (exact normalized label)
    $r = $pdo->prepare("SELECT category_id FROM pf_item_category_rules WHERE keyword=? LIMIT 1");
    $r->execute([$norm]);
    if ($row = $r->fetch()) return (int)$row['category_id'];

    // 2. Learned rules (individual words)
    foreach (explode(' ', $norm) as $w) {
        if (strlen($w) < 3) continue;
        $r = $pdo->prepare("SELECT category_id FROM pf_item_category_rules WHERE keyword=? LIMIT 1");
        $r->execute([$w]);
        if ($row = $r->fetch()) return (int)$row['category_id'];
    }

    // 3. Built-in dictionary → map position to real category id
    $pos = liste_builtin_detect($norm);
    if ($pos === null) return null;

    // Get category id by position order
    $r = $pdo->query("SELECT id FROM pf_list_categories ORDER BY position, id");
    $ids = $r->fetchAll(PDO::FETCH_COLUMN);
    return $ids[$pos - 1] ?? null;
}

function liste_learn(PDO $pdo, string $label, int $category_id): void {
    $norm = liste_normalize_detect($label);
    if (!$norm) return;
    $pdo->prepare("INSERT INTO pf_item_category_rules (keyword, category_id) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE category_id=VALUES(category_id)")
        ->execute([$norm, $category_id]);
    // Also learn individual meaningful words
    foreach (explode(' ', $norm) as $w) {
        if (strlen($w) >= 4) {
            $pdo->prepare("INSERT IGNORE INTO pf_item_category_rules (keyword, category_id) VALUES (?,?)")
                ->execute([$w, $category_id]);
        }
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
if ($method === 'PUT' || $method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) $body = json_decode($raw, true) ?? [];
    foreach ($_POST as $k => $v) if (!isset($body[$k])) $body[$k] = $v;
}

// ── CATEGORIES ────────────────────────────────────────────────────────────────
if ($action === 'categories' && $method === 'GET') {
    $rows = $pdo->query("SELECT id, icon, name, position FROM pf_list_categories ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['categories' => $rows]);
    exit;
}

// ── SET CATEGORY (+ learn) ────────────────────────────────────────────────────
if ($action === 'set_category' && $method === 'POST') {
    $item_id    = (int)($body['item_id'] ?? 0);
    $cat_id_raw = $body['category_id'] ?? null;
    $category_id = ($cat_id_raw === null || $cat_id_raw === '' || $cat_id_raw === '0') ? null : (int)$cat_id_raw;

    if (!$item_id) { http_response_code(400); echo json_encode(['error' => 'item_id required']); exit; }

    $pdo->prepare("UPDATE pf_grocery_items SET category_id=? WHERE id=?")->execute([$category_id, $item_id]);

    // Learn from manual assignment
    if ($category_id !== null) {
        $row = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE id=?");
        $row->execute([$item_id]);
        if ($r = $row->fetch()) liste_learn($pdo, $r['label'], $category_id);
    }

    echo json_encode(['ok' => true]);
    exit;
}

// ── LISTS ─────────────────────────────────────────────────────────────────────
if ($action === 'lists') {
    if ($method === 'GET') {
        $firstId = liste_ensure_default($pdo);
        $rows = $pdo->query("SELECT id, name, color, list_type, position FROM pf_lists ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['lists' => $rows, 'default_id' => $firstId]);
        exit;
    }
    if ($method === 'POST') {
        $name      = mb_substr(trim($body['name'] ?? ''), 0, 100);
        $color     = mb_substr(trim($body['color'] ?? ''), 0, 20) ?: null;
        $list_type = mb_substr(trim($body['list_type'] ?? ''), 0, 50) ?: null;
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'name required']); exit; }
        $pos = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM pf_lists")->fetchColumn();
        $pdo->prepare("INSERT INTO pf_lists (name, color, list_type, position) VALUES (?,?,?,?)")->execute([$name, $color, $list_type, $pos]);
        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'color' => $color, 'list_type' => $list_type, 'position' => $pos]);
        exit;
    }
    if ($method === 'PUT') {
        $id        = (int)($_GET['id'] ?? 0);
        $name      = mb_substr(trim($body['name'] ?? ''), 0, 100);
        $color     = array_key_exists('color', $body) ? (mb_substr(trim($body['color'] ?? ''), 0, 20) ?: null) : false;
        $list_type = array_key_exists('list_type', $body) ? (mb_substr(trim($body['list_type'] ?? ''), 0, 50) ?: null) : false;
        if (!$id || !$name) { http_response_code(400); echo json_encode(['error' => 'invalid']); exit; }
        $sets = ['name=?'];
        $vals = [$name];
        if ($color !== false)     { $sets[] = 'color=?';     $vals[] = $color; }
        if ($list_type !== false) { $sets[] = 'list_type=?'; $vals[] = $list_type; }
        $vals[] = $id;
        $pdo->prepare("UPDATE pf_lists SET " . implode(', ', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok' => true]); exit;
    }
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ((int)$pdo->query("SELECT COUNT(*) FROM pf_lists")->fetchColumn() <= 1) {
            http_response_code(400); echo json_encode(['error' => 'cannot delete last list']); exit;
        }
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM pf_lists WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }
}

// ── ITEMS ─────────────────────────────────────────────────────────────────────
if ($action === 'items') {
    $list_id = (int)($_GET['list_id'] ?? $body['list_id'] ?? 0);
    if ($method === 'GET') {
        if (!$list_id) { http_response_code(400); echo json_encode(['error' => 'list_id required']); exit; }
        $s = $pdo->prepare("SELECT id, list_id, category_id, label, in_cart, position FROM pf_grocery_items WHERE list_id=? ORDER BY in_cart, position, id");
        $s->execute([$list_id]);
        echo json_encode(['items' => $s->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }
    if ($method === 'POST') {
        $label = trim($body['label'] ?? '');
        if (!$list_id || !$label) { http_response_code(400); echo json_encode(['error' => 'missing fields']); exit; }
        $norm = liste_normalize($label);
        $dup = $pdo->prepare("SELECT COUNT(*) FROM pf_grocery_items WHERE list_id=? AND LOWER(TRIM(label))=?");
        $dup->execute([$list_id, $norm]);
        if ((int)$dup->fetchColumn() > 0) { echo json_encode(['duplicate' => true]); exit; }
        $q = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 FROM pf_grocery_items WHERE list_id=?");
        $q->execute([$list_id]);
        $pos = (int)$q->fetchColumn();
        $category_id = liste_detect_category($pdo, $label);
        $pdo->prepare("INSERT INTO pf_grocery_items (list_id, category_id, label, in_cart, position) VALUES (?,?,?,0,?)")->execute([$list_id, $category_id, $label, $pos]);
        $id = (int)$pdo->lastInsertId();
        liste_touch_history($pdo, $label);
        echo json_encode(['id' => $id, 'list_id' => $list_id, 'category_id' => $category_id, 'label' => $label, 'in_cart' => 0, 'position' => $pos]); exit;
    }
    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
        if (isset($body['in_cart'])) $pdo->prepare("UPDATE pf_grocery_items SET in_cart=? WHERE id=?")->execute([(int)$body['in_cart'], $id]);
        if (isset($body['label'])) {
            $label = trim($body['label']);
            if ($label) {
                $pdo->prepare("UPDATE pf_grocery_items SET label=? WHERE id=?")->execute([$label, $id]);
                liste_touch_history($pdo, $label);
            }
        }
        echo json_encode(['ok' => true]); exit;
    }
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
        $r = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE id=?"); $r->execute([$id]);
        if ($row = $r->fetch()) liste_touch_history($pdo, $row['label']);
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }
}

// ── BULK ──────────────────────────────────────────────────────────────────────
if ($action === 'uncheck_all' && $method === 'POST') {
    $lid = (int)($body['list_id'] ?? 0);
    if ($lid) $pdo->prepare("UPDATE pf_grocery_items SET in_cart=0 WHERE list_id=?")->execute([$lid]);
    echo json_encode(['ok' => true]); exit;
}
if ($action === 'delete_picked' && $method === 'POST') {
    $lid = (int)($body['list_id'] ?? 0);
    if ($lid) {
        $rows = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE list_id=? AND in_cart=1"); $rows->execute([$lid]);
        foreach ($rows->fetchAll() as $r) liste_touch_history($pdo, $r['label']);
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=? AND in_cart=1")->execute([$lid]);
    }
    echo json_encode(['ok' => true]); exit;
}
if ($action === 'clear_all' && $method === 'POST') {
    $lid = (int)($body['list_id'] ?? 0);
    if ($lid) {
        $rows = $pdo->prepare("SELECT label FROM pf_grocery_items WHERE list_id=?"); $rows->execute([$lid]);
        foreach ($rows->fetchAll() as $r) liste_touch_history($pdo, $r['label']);
        $pdo->prepare("DELETE FROM pf_grocery_items WHERE list_id=?")->execute([$lid]);
    }
    echo json_encode(['ok' => true]); exit;
}

// ── HISTORY ───────────────────────────────────────────────────────────────────
if ($action === 'history' && $method === 'GET') {
    $max = 20;
    $n = $pdo->prepare("SELECT content FROM pf_notes WHERE note_type='setting' AND reference_id='liste_history_max'"); $n->execute();
    if ($r = $n->fetch()) $max = max(1, min(50, (int)$r['content']));
    $s = $pdo->query("SELECT label_display FROM pf_grocery_history ORDER BY last_used_at DESC LIMIT $max");
    echo json_encode(['history' => $s->fetchAll(PDO::FETCH_COLUMN)]); exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action: ' . $action]);
