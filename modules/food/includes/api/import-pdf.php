<?php
// modules/meals/includes/api/import-pdf.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdf_file'])) {
    echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu.']);
    exit;
}

$personId = (int)$_POST['person_id'];
if ($personId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Enfant non spécifié.']);
    exit;
}

// ---------------------------------------------------------
// PARSEUR PDF SPATIAL NATIF (100% PHP SANS LIBRAIRIE)
// ---------------------------------------------------------
class NativePdfParser {
    public static function getMealData($filename) {
        $content = file_get_contents($filename);
        $texts = [];
        
        // 1. Décompression et Tokenization Mathématique
        if (preg_match_all('/stream(.*?)endstream/is', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $stream = ltrim($stream, "\r\n");
                $decoded = @gzuncompress($stream);
                if (!$decoded) $decoded = $stream;
                
                // On éclate le flux par opérateurs PDF clés (Position et Texte)
                $parts = preg_split('/(Tm|Td|Tj|TJ)/', $decoded, -1, PREG_SPLIT_DELIM_CAPTURE);
                $x = 0; $y = 0;
                
                for ($i = 0; $i < count($parts); $i++) {
                    $token = $parts[$i];
                    if ($token === 'Tm' || $token === 'Td') {
                        $prev = $parts[$i-1];
                        preg_match_all('/[0-9.-]+/', $prev, $nums);
                        if (count($nums[0]) >= 2) {
                            $yRaw = array_pop($nums[0]);
                            $xRaw = array_pop($nums[0]);
                            if ($token === 'Tm') {
                                $x = (float)$xRaw; $y = (float)$yRaw;
                            } else {
                                $x += (float)$xRaw; $y += (float)$yRaw;
                            }
                        }
                    } elseif ($token === 'Tj') {
                        $prev = $parts[$i-1];
                        if (preg_match('/\((.*?)\)$/', trim($prev), $m)) {
                            self::addText($texts, $m[1], $x, $y);
                        }
                    } elseif ($token === 'TJ') {
                        $prev = $parts[$i-1];
                        if (preg_match('/\[(.*?)\]$/', trim($prev), $m)) {
                            preg_match_all('/\((.*?)\)/', $m[1], $tjs);
                            $tjX = $x;
                            foreach ($tjs[1] as $tj) {
                                self::addText($texts, $tj, $tjX, $y);
                                $tjX += 10; // Décalage estimé pour les mots suivants du tableau
                            }
                        }
                    }
                }
            }
        }

        if (empty($texts)) throw new Exception("Impossible de lire le contenu du PDF. Le fichier est peut-être crypté ou corrompu.");

        // 2. Définition des Colonnes Verticles (X) via les En-têtes de Jour
        $dayLabels = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI'];
        $colXs = [];
        foreach ($texts as $t) {
            $clean = strtoupper(preg_replace('/[^A-Z]/', '', $t['text']));
            if (in_array($clean, $dayLabels)) {
                $colXs[$clean] = $t['x'];
            }
        }
        
        // Sécurité si les jours exacts ne sont pas détectés à cause de la police
        if (count($colXs) < 5) {
            $allXs = array_column($texts, 'x');
            sort($allXs);
            $minX = $allXs[0] ?? 0;
            $maxX = end($allXs) ?? 500;
            $step = ($maxX - $minX) / 5;
            $colXs = [
                'LUNDI' => $minX + $step * 0.5,
                'MARDI' => $minX + $step * 1.5,
                'MERCREDI' => $minX + $step * 2.5,
                'JEUDI' => $minX + $step * 3.5,
                'VENDREDI' => $minX + $step * 4.5,
            ];
        }
        $sortedXs = array_values($colXs);
        sort($sortedXs);

        // 3. Définition des Tranches Horizontales (Y) via les Titres de Rangée (Zone Extrême Gauche)
        $rowHeaders = [];
        foreach ($texts as $t) {
            $clean = strtoupper(preg_replace('/[^A-Z]/', '', $t['text']));
            if (in_array($clean, ['HORSDEUVRE', 'DECLINAISON', 'PLATCHAUD', 'GARNITURE', 'PRODUITLAITIER', 'DESSERT', 'LESGOUTERS'])) {
                // On s'assure de ne capter que la colonne tout à gauche
                if ($t['x'] < ($sortedXs[0] - 20)) {
                    $rowHeaders[] = ['label' => $clean, 'y' => $t['y']];
                }
            }
        }
        
        // Tri de haut en bas (Y du PDF est décroissant du haut vers le bas)
        usort($rowHeaders, fn($a, $b) => $b['y'] <=> $a['y']);

        $yPlatTop = null; $yPlatBot = null;
        $yGarTop = null; $yGarBot = null;

        for ($i = 0; $i < count($rowHeaders); $i++) {
            if ($rowHeaders[$i]['label'] === 'PLATCHAUD') {
                $yPlatTop = $rowHeaders[$i]['y'];
                // La limite basse est le label suivant, ou -50 unités
                $yPlatBot = $rowHeaders[$i+1]['y'] ?? ($yPlatTop - 50);
            }
            if ($rowHeaders[$i]['label'] === 'GARNITURE') {
                $yGarTop = $rowHeaders[$i]['y'];
                $yGarBot = $rowHeaders[$i+1]['y'] ?? ($yGarTop - 50);
            }
        }

        if (!$yPlatTop || !$yGarTop) {
            throw new Exception("Structure du menu non reconnue (Mots-clés 'PLAT CHAUD' et 'GARNITURE' introuvables).");
        }

        // 4. Affectation Chirurgicale par Intersection Grille (X, Y)
        $days = [[], [], [], [], []];
        foreach ($texts as $t) {
            $cleanAlpha = strtoupper(preg_replace('/[^A-Z]/', '', $t['text']));
            
            // Rejet des labels génériques et de la fameuse ligne "Déclinaison"
            if (in_array($cleanAlpha, ['DECLINAISON', 'MENUVEGETARIEN', 'PLATCHAUD', 'GARNITURE', 'PRODUITLAITIER', 'HORSDEUVRE', 'LESGOUTERS', 'DESSERT'])) continue;

            // Retrait des labels BIO/HVE et mentions superflues
            $textVal = trim(preg_replace('/\b(AB|HVE|MSC|Label Rouge)\b/i', '', $t['text']));
            $textVal = str_replace(['(plat', 'complet)'], '', $textVal);
            $textVal = trim(preg_replace('/[^a-zA-ZÀ-ÿ0-9\s&\-]/u', '', $textVal));

            if (strlen($textVal) < 3) continue;

            // Filtre Vertical : Est-on dans la tranche Plat Chaud ou Garniture ? (Tolérance +/- 5 unités)
            $inPlat = ($t['y'] <= $yPlatTop + 5 && $t['y'] >= $yPlatBot + 5);
            $inGar  = ($t['y'] <= $yGarTop + 5 && $t['y'] >= $yGarBot + 5);

            if ($inPlat || $inGar) {
                // Filtre Horizontal : Quel est le jour le plus proche ?
                $bestCol = 0;
                $minDiff = PHP_FLOAT_MAX;
                foreach ($sortedXs as $idx => $cx) {
                    $diff = abs($t['x'] - $cx);
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $bestCol = $idx;
                    }
                }
                $days[$bestCol][] = ['text' => $textVal, 'y' => $t['y'], 'type' => $inPlat ? 'plat' : 'gar'];
            }
        }

        // 5. Concaténation Finale
        $finalDays = [];
        for ($i = 0; $i < 5; $i++) {
            $plats = array_filter($days[$i], fn($item) => $item['type'] === 'plat');
            $gars = array_filter($days[$i], fn($item) => $item['type'] === 'gar');
            
            usort($plats, fn($a, $b) => $b['y'] <=> $a['y']); 
            usort($gars, fn($a, $b) => $b['y'] <=> $a['y']); 

            $platText = self::concatLines($plats);
            $garText = self::concatLines($gars);

            $full = trim("$platText + $garText", " +");
            $finalDays[] = preg_replace('/\s+/', ' ', $full);
        }

        return $finalDays;
    }

    private static function concatLines($items) {
        $lines = [];
        $currentLine = "";
        $lastY = null;
        foreach ($items as $item) {
            // Si le décalage Y est trop fort, on considère que c'est une nouvelle ligne
            if ($lastY !== null && abs($lastY - $item['y']) > 8) {
                if ($currentLine) $lines[] = trim($currentLine);
                $currentLine = $item['text'];
            } else {
                $currentLine .= " " . $item['text'];
            }
            $lastY = $item['y'];
        }
        if ($currentLine) $lines[] = trim($currentLine);
        return implode(' ', array_filter($lines, fn($l) => strlen(trim($l)) > 2));
    }

    private static function addText(&$texts, $rawText, $x, $y) {
        // Décodage de l'octal \123
        $m = preg_replace_callback('/\\\\([0-7]{1,3})/', function($cb) { return chr(octdec($cb[1])); }, $rawText);
        
        // Détection de l'UTF-16BE (Le BOM \xFE\xFF indique que le texte est double encodé)
        if (str_starts_with($m, "\xFE\xFF")) {
            $m = mb_convert_encoding(substr($m, 2), 'UTF-8', 'UTF-16BE');
        } else {
            // Fallback Latin-1 si pas de BOM
            $m = mb_convert_encoding($m, 'UTF-8', 'ISO-8859-1');
        }
        
        // Suppression radicale des octets nuls responsables de l'espacement forcé (E s c a l o p e)
        $m = str_replace("\0", "", $m);
        $m = trim(preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\+&\'’\-]/u', '', $m));
        
        if ($m !== '') {
            $texts[] = ['text' => $m, 'x' => $x, 'y' => $y];
        }
    }
}

try {
    $filename = $_FILES['pdf_file']['name'];
    
    // 1. Extraction robuste de la date depuis le nom de fichier (Menus-du-14-au-20-septembre-2026.pdf)
    $mondayDate = null;
    if (preg_match('/(\d{1,2})[\s-]+au[\s-]+\d{1,2}[\s-]+([a-zèéû]+)[\s-]+(\d{4})/i', $filename, $m)) {
        $monthsMap = ['janvier'=>'01','fevrier'=>'02','février'=>'02','mars'=>'03','avril'=>'04','mai'=>'05','juin'=>'06','juillet'=>'07','aout'=>'08','août'=>'08','septembre'=>'09','octobre'=>'10','novembre'=>'11','decembre'=>'12','décembre'=>'12'];
        $month = $monthsMap[strtolower($m[2])] ?? '09';
        $mondayDate = $m[3] . '-' . $month . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }

    if (!$mondayDate) {
        echo json_encode(['success' => false, 'error' => 'Veuillez nommer le fichier "Menus-du-14-au-20-septembre-2026.pdf".']);
        exit;
    }

    // 2. Lancement de l'Analyse Spatiale
    $plats = NativePdfParser::getMealData($_FILES['pdf_file']['tmp_name']);

    if (empty($plats) || count($plats) !== 5) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors du découpage des 5 colonnes.']);
        exit;
    }

    // 3. Insertion en BDD (Lundi au Vendredi)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO pf_meals_plan (plan_date, service, person_id, meal_name) 
        VALUES (?, 'lunch', ?, ?) 
        ON DUPLICATE KEY UPDATE meal_name = VALUES(meal_name)
    ");

    $currentDate = new DateTime($mondayDate);
    for ($i = 0; $i < 5; $i++) {
        $fullMeal = $plats[$i];
        if (!empty($fullMeal) && $fullMeal !== '+') {
            $stmt->execute([$currentDate->format('Y-m-d'), $personId, $fullMeal]);
        }
        $currentDate->modify('+1 day');
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}