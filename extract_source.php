<?php
/**
 * HouseHub OS - Source Extractor 🦙🚀
 * Génère un fichier Markdown contenant tout le code source du projet.
 * Idéal pour alimenter une GEM ou un LLM.
 */

// 1. 🚨 SÉCURITÉ : Ne jamais laisser ce fichier accessible publiquement sans protection !
// Appelle le script via : https://househub.nas.../extract_source.php?key=TON_MOT_DE_PASSE
$secretKey = 'mR83s7MmXbP$jer$9C4HnGkry6xhGL6'; 

if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die("⛔ Accès refusé. Veuillez fournir la clé de sécurité.");
}

// Forcer l'affichage en texte brut dans le navigateur pour un copier-coller facile
header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;

// 2. CONFIGURATION DU FILTRAGE
// Dossiers à exclure (ne mets pas de slash au début)
$excludeDirs = [
    '.git', 
    '.gitea', 
    '.devtools', 
    'vendor', 
    'node_modules',
    'assets/img',
    'modules/holidays/assets/img',
    'modules/family-calendar/assets/img',
    'modules/gift-list/assets/img'
];

// Fichiers spécifiques à exclure (Sécurité & Bruit)
$excludeFiles = [
    '.env', 
    '.env.example',
    'extract_source.php', // On s'exclut soi-même
    'pachafamily_source.md',
    'docker-compose.override.yml'
];

// Extensions autorisées (on ignore les images, pdf, zip, etc.)
$allowedExtensions = ['php', 'js', 'css', 'sql', 'json', 'yml', 'yaml', 'md', 'sh'];

// 3. INITIALISATION DU PARCOURS
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$report = "# 🦙 Source Code HouseHub OS\n\n";
$report .= "> *Généré le " . date('Y-m-d H:i:s') . "*\n\n";
$report .= "---\n\n";

// 4. BOUCLE D'EXTRACTION
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $filePath = $file->getPathname();
    $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
    $relativePath = str_replace('\\', '/', $relativePath); // Uniformisation Windows/Linux
    
    // Extraction de l'extension
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // A. Filtrage par dossier
    $skip = false;
    foreach ($excludeDirs as $dir) {
        // Si le chemin commence par ce dossier, ou contient /ce_dossier/
        if (strpos($relativePath, $dir . '/') === 0 || strpos($relativePath, '/' . $dir . '/') !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    // B. Filtrage par nom de fichier
    if (in_array(basename($filePath), $excludeFiles)) continue;

    // C. Filtrage par extension
    if (!in_array($ext, $allowedExtensions)) continue;

    // 5. LECTURE ET FORMATAGE
    $content = file_get_contents($filePath);
    
    if ($content === false) {
        $content = "// Erreur lors de la lecture de ce fichier.";
    }

    // Ajustement du tag de langage pour le Markdown
    $lang = $ext;
    if ($ext === 'js') $lang = 'javascript';
    if ($ext === 'yml') $lang = 'yaml';

    $report .= "### 📄 Fichier : `$relativePath`\n";
    $report .= "```$lang\n";
    $report .= trim($content) . "\n";
    $report .= "```\n\n---\n\n";
}

// 6. SORTIE
echo $report;