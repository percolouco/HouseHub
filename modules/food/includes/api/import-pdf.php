<?php
// modules/meals/includes/api/import-pdf.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require dirname(__DIR__, 4) . '/vendor/autoload.php';
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
// PARSEUR PDF SPATIAL (via smalot/pdfparser pour un décodage
// fiable des polices Type0/CID + tables ToUnicode)
// ---------------------------------------------------------
class NativePdfParser {
    // strtoupper() seul ne gère pas les accents (ex: "Déclinaison" -> "DCLINAISON"
    // au lieu de "DECLINAISON"), ce qui empêchait ce label d'être reconnu.
    private static function cleanLabel($text) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return strtoupper(preg_replace('/[^A-Z]/i', '', $ascii ?: $text));
    }

    public static function getMealData($filename) {
        $texts = [];

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filename);

        foreach ($pdf->getPages() as $page) {
            foreach ($page->getDataTm() as $entry) {
                [$tm, $rawText] = $entry;
                $textVal = trim((string) $rawText);
                if ($textVal === '') continue;
                self::addText($texts, $textVal, (float) $tm[4], (float) $tm[5]);
            }
        }

        if (empty($texts)) throw new Exception("Impossible de lire le contenu du PDF. Le fichier est peut-être crypté ou corrompu.");

        // 2. Définition des Colonnes Verticles (X) via les En-têtes de Jour
        $dayLabels = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI'];
        $colXs = [];
        foreach ($texts as $t) {
            $clean = self::cleanLabel($t['text']);
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
            $clean = self::cleanLabel($t['text']);
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
            $cleanAlpha = self::cleanLabel($t['text']);
            
            // Rejet des labels génériques et de la fameuse ligne "Déclinaison"
            if (in_array($cleanAlpha, ['DECLINAISON', 'MENUVEGETARIEN', 'PLATCHAUD', 'GARNITURE', 'PRODUITLAITIER', 'HORSDEUVRE', 'LESGOUTERS', 'DESSERT'])) continue;

            // Retrait des labels BIO/HVE et mentions superflues
            $textVal = trim(preg_replace('/\b(AB|HVE|MSC|Label Rouge)\b/i', '', $t['text']));
            $textVal = str_replace(['(plat', 'complet)'], '', $textVal);
            $textVal = trim(preg_replace('/[^a-zA-ZÀ-ÿ0-9\s&\-]/u', '', $textVal));

            if (strlen($textVal) < 3) continue;

            // Filtre Vertical : Est-on dans la tranche Plat Chaud ou Garniture ? (Tolérance +/- 5 unités)
            // Le texte d'une rangée déborde souvent au-dessus de son propre label (lignes
            // empilées vers le haut), y compris pour le label de la rangée suivante qui
            // sert de limite basse : on applique donc la même marge des deux côtés.
            $inPlat = ($t['y'] <= $yPlatTop + 20 && $t['y'] >= $yPlatBot + 20);
            $inGar  = ($t['y'] <= $yGarTop + 20 && $t['y'] >= $yGarBot + 20);

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
        // smalot/pdfparser décode déjà correctement les polices Type0/CID + ToUnicode :
        // il ne reste plus qu'à nettoyer les caractères parasites résiduels.
        $m = str_replace("\0", "", $rawText);
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