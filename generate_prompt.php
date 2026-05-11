<?php
// generate_prompt.php - Script pour extraire le code de HouseHub
// À lancer via le navigateur (http://localhost:8082/generate_prompt.php) ou en ligne de commande.

$rootDir = __DIR__;
$outputFile = $rootDir . '/househub_source.md';

// Dossiers à ignorer (pour ne pas polluer le cerveau de l'IA avec des choses inutiles)
$ignoredDirs = ['.git', '.devtools', '.gitea', 'assets'];

// Extensions autorisées (on ne veut que du code, pas d'images)
$allowedExtensions = ['php', 'css', 'js', 'html', 'sh'];

$output = "# 🦙 Source Code HouseHub\n\n";
$output .= "> *Généré le " . date('Y-m-d H:i:s') . "*\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fileCount = 0;

foreach ($iterator as $file) {
    if ($file->isFile()) {
        // Chemin relatif propre
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath); // Uniformiser pour Windows

        // Vérification des dossiers ignorés
        $skip = false;
        foreach ($ignoredDirs as $ignored) {
            if (strpos($relativePath, $ignored) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        // Vérification de l'extension
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $allowedExtensions)) continue;

        // On ignore ce script lui-même
        if ($file->getFilename() === 'generate_prompt.php') continue;

        // Lecture du contenu
        $content = file_get_contents($file->getPathname());
        
        // Détermination du langage pour le bloc Markdown
        $lang = $ext === 'php' ? 'php' : ($ext === 'js' ? 'javascript' : $ext);

        // Formatage pour l'IA
        $output .= "### 📄 Fichier : `" . $relativePath . "`\n";
        $output .= "```" . $lang . "\n";
        $output .= $content . "\n";
        $output .= "```\n\n";
        $output .= "---\n\n";
        
        $fileCount++;
    }
}

file_put_contents($outputFile, $output);

echo "<div style='font-family:sans-serif; padding:20px; background:#f0fdf4; color:#16a34a; border-radius:8px; border:1px solid #bbf7d0;'>";
echo "<h2>✅ Succès !</h2>";
echo "<p>Le fichier <strong>househub_source.md</strong> a été généré à la racine de ton projet.</p>";
echo "<p><strong>$fileCount fichiers</strong> de code ont été fusionnés.</p>";
echo "<p><em>Tu peux maintenant donner ce fichier à ta Gem HouseHub !</em></p>";
echo "</div>";
?>