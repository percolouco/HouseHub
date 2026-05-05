<?php
/**
 * PachaFamily - CSS Auditor 🦙
 * Génère un rapport complet de tous les styles (fichiers, balises, inline)
 */

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$report = "=== 🦙 RAPPORT CSS COMPLET PACHAFAMILY ===\n";
$report .= "Généré le : " . date('Y-m-d H:i:s') . "\n\n";

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $filePath = $file->getPathname();
    $relativePath = str_replace($root, '', $filePath);
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    // 1. EXTRACTION DES FICHIERS .css
    if ($ext === 'css') {
        $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n";
        $report .= file_get_contents($filePath) . "\n";
        $report .= str_repeat("-", 40) . "\n\n";
    }

    // 2. EXTRACTION DES BALISES <style> ET ATTRIBUTS style="" DANS LES .php
    if ($ext === 'php') {
        $content = file_get_contents($filePath);
        $foundInFile = false;

        // Extraction des blocs <style>
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $content, $matches)) {
            if (!$foundInFile) { $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n"; $foundInFile = true; }
            foreach ($matches[1] as $idx => $styleBlock) {
                $report .= "/* Bloc <style> #$idx */\n";
                $report .= trim($styleBlock) . "\n";
            }
        }

        // Extraction des styles inline style="..."
        // On capture le tag pour donner du contexte au style inline
        if (preg_match_all('/<([a-z0-9]+)[^>]*?\sstyle=["\']([^"]*?)["\'][^>]*>/i', $content, $matches)) {
            if (!$foundInFile) { $report .= "/* 📄 FICHIER SOURCE : $relativePath */\n"; $foundInFile = true; }
            foreach ($matches[0] as $idx => $fullTag) {
                // On nettoie un peu pour ne garder que l'essentiel du tag et son style
                $report .= "/* Style Inline #$idx */\n";
                $report .= trim($fullTag) . "\n";
            }
        }

        if ($foundInFile) {
            $report .= str_repeat("-", 40) . "\n\n";
        }
    }
}

echo $report;