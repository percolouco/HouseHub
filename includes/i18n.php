<?php
// Démarrer la session si ce n'est pas déjà fait (souvent fait dans auth.php, mais on sécurise)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Changement de langue si l'utilisateur a cliqué sur un drapeau
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ca', 'en'])) {
    $_SESSION['app_lang'] = $_GET['lang'];
}

// 2. Langue par défaut = Français
$current_lang = $_SESSION['app_lang'] ?? 'fr';

// 3. Chargement du bon dictionnaire
$lang_file = __DIR__ . '/lang/' . $current_lang . '.php';
if (file_exists($lang_file)) {
    $GLOBALS['translations'] = include $lang_file;
} else {
    $GLOBALS['translations'] = [];
}

// 4. La fonction magique de traduction
// Si le mot existe dans le dictionnaire on l'affiche, sinon on affiche la clé par défaut
if (!function_exists('tr')) {
    function tr($key) {
        return $GLOBALS['translations'][$key] ?? $key;
    }
}