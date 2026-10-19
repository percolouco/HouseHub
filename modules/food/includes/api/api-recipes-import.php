<?php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = trim($_POST['import_data'] ?? '');
if (empty($input)) {
    echo json_encode(['success' => false, 'error' => 'Aucune donnée fournie.']);
    exit;
}

// 1. TENTATIVE : Extraction JSON-LD si c'est une URL
if (filter_var($input, FILTER_VALIDATE_URL)) {
    $html = @file_get_contents($input);
    if ($html) {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches);
        foreach ($matches[1] as $match) {
            $jsonld = json_decode($match, true);
            // Parfois le JSON-LD est un tableau d'objets, parfois un objet direct
            if (isset($jsonld['@graph'])) $jsonld = $jsonld['@graph'];
            if (!is_array($jsonld)) $jsonld = [$jsonld];

            foreach ($jsonld as $item) {
                if (isset($item['@type']) && (is_array($item['@type']) ? in_array('Recipe', $item['@type']) : $item['@type'] === 'Recipe')) {
                    // On a trouvé une recette standardisée ! On la formate pour notre frontend.
                    $recipe = [
                        'title' => $item['name'] ?? '',
                        'image_url' => is_array($item['image']) ? ($item['image'][0] ?? '') : ($item['image'] ?? ''),
                        'prep_time' => parseISO8601Duration($item['prepTime'] ?? ''),
                        'cook_time' => parseISO8601Duration($item['cookTime'] ?? ''),
                        'base_servings' => (int)preg_replace('/\D/', '', $item['recipeYield'] ?? '4') ?: 4,
                        'ingredients' => [],
                        'steps' => []
                    ];

                    if (!empty($item['recipeIngredient'])) {
                        foreach ($item['recipeIngredient'] as $ing) {
                            $recipe['ingredients'][] = ['qty' => '', 'unit' => '', 'name' => trim($ing)];
                        }
                    }

                    if (!empty($item['recipeInstructions'])) {
                        foreach ($item['recipeInstructions'] as $step) {
                            $text = is_array($step) ? ($step['text'] ?? '') : $step;
                            if ($text) $recipe['steps'][] = ['title' => '', 'instruction' => strip_tags(trim($text))];
                        }
                    }

                    echo json_encode(['success' => true, 'data' => $recipe]);
                    exit;
                }
            }
        }
    }
    // Si l'URL n'avait pas de JSON-LD, on passe la requête à l'IA en tant que texte.
}

// 2. EXTRACTION LLM (Texte brut ou URL sans JSON-LD)
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pf_settings WHERE module = 'ai'");
$aiSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$apiUrl = $aiSettings['ai_base_url'] ?? '';
$apiKey = $aiSettings['ai_api_key'] ?? '';
$model = $aiSettings['ai_model'] ?? 'gpt-4o-mini';

if (empty($apiUrl) || empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'Veuillez configurer la clé API dans les paramètres pour utiliser l\'import textuel.']);
    exit;
}

$systemPrompt = 'Tu es un extracteur de recettes de cuisine. Analyse le texte fourni et extrais les informations dans CE FORMAT JSON EXACT ET RIEN D\'AUTRE. 
{
  "title": "Titre",
  "prep_time": 15, 
  "cook_time": 30, 
  "base_servings": 4, 
  "difficulty": 1, 
  "ingredients": [
    {"qty": 200, "unit": "g", "name": "Farine"}
  ],
  "steps": [
    {"title": "Optionnel", "instruction": "Faire chauffer..."}
  ],
  "chef_tip": "Astuce si présente"
}
Sépare bien les quantités, unités et noms pour les ingrédients. Si une quantité est introuvable, mets "". Si le texte contient une URL, ignore le formatage web et trouve le sens.';

// Détection du fournisseur selon l'URL
$isGemini = strpos($apiUrl, 'generativelanguage.googleapis.com') !== false;

if ($isGemini) {
    // --- FORMAT GOOGLE GEMINI ---
    $headers = [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ];
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $systemPrompt . "\n\nTexte à analyser :\n" . $input]
                ]
            ]
        ],
        "generationConfig" => [
            "responseMimeType" => "application/json"
        ]
    ];
} else {
    // --- FORMAT STANDARD (OpenAI, Groq, Ollama...) ---
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    $data = [
        'model' => $model,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Extrais cette recette :\n\n" . $input]
        ]
    ];
}

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['success' => false, 'error' => 'Erreur de l\'API IA : HTTP ' . $httpCode . ' - ' . $response]);
    exit;
}

$decoded = json_decode($response, true);

// L'emplacement de la réponse diffère entre Gemini et OpenAI
if ($isGemini) {
    $aiContent = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
} else {
    $aiContent = $decoded['choices'][0]['message']['content'] ?? '{}';
}

// Nettoyage éventuel si Gemini encapsule dans des balises Markdown (ex: ```json ... ```)
$aiContent = str_replace(['```json', '```'], '', $aiContent);
$recipeJson = json_decode(trim($aiContent), true);

if (!$recipeJson) {
    echo json_encode(['success' => false, 'error' => 'Le modèle IA n\'a pas renvoyé un JSON valide.']);
    exit;
}

echo json_encode(['success' => true, 'data' => $recipeJson]);

// Helper for ISO8601 durations (PT30M -> 30)
function parseISO8601Duration($iso) {
    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $matches);
    $h = isset($matches[1]) ? (int)$matches[1] : 0;
    $m = isset($matches[2]) ? (int)$matches[2] : 0;
    return ($h * 60) + $m;
}