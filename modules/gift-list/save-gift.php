<?php
// modules/gift-list/save-gift.php

require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gift-list.php');
    exit;
}

// Configuration
$tableName = 'pf_gifts'; // Harmonisé avec gift-list.php

// Récupération des données
$action = $_POST['action'] ?? 'create';
$gift_id = (int)($_POST['gift_id'] ?? 0);

// Logique de redirection (pour rester sur la bonne vue)
// Par défaut on renvoie vers le referer ou vers la page principale
$redirectUrl = '/gift-list.php';
$occasionForView = $_POST['occasion'] ?? '';
if (in_array($occasionForView, ['ANNIV', 'SANT'])) {
    $redirectUrl .= '?view=anniversary';
} else {
    $redirectUrl .= '?view=nadal';
}

try {
    // --- SUPPRESSION ---
    if ($action === 'delete') {
        if ($gift_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = :id");
            $stmt->execute(['id' => $gift_id]);
        }
        header("Location: $redirectUrl");
        exit;
    }

    // Champs communs
    $year       = (int)($_POST['year'] ?? date('Y'));
    $adult_name = trim($_POST['adult_name'] ?? '');
    $payer_name = trim($_POST['payer_name'] ?? '');
    $child_name = trim($_POST['child_name'] ?? '');
    $occasion   = trim($_POST['occasion'] ?? '');
    $gift_desc  = trim($_POST['gift_description'] ?? '');
    $prod_link  = trim($_POST['product_link'] ?? '');
    $amount     = ($_POST['amount'] !== '') ? (float)$_POST['amount'] : 0.0;

    // Si payeur vide, c'est l'adulte responsable qui paye
    if ($payer_name === '') {
        $payer_name = $adult_name;
    }

    // Validation minimale
    if (!$adult_name || !$child_name || !$occasion || !$gift_desc) {
        // En cas d'erreur, on redirige sans rien faire (ou on pourrait gérer une erreur)
        header("Location: $redirectUrl");
        exit;
    }

    // --- UPDATE ---
    if ($action === 'update' && $gift_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE {$tableName}
            SET year = :year,
                adult_name = :adult_name,
                payer_name = :payer_name,
                child_name = :child_name,
                occasion = :occasion,
                gift_description = :gift_description,
                product_link = :product_link,
                amount = :amount
            WHERE id = :id
        ");
        $stmt->execute([
            'id'               => $gift_id,
            'year'             => $year,
            'adult_name'       => $adult_name,
            'payer_name'       => $payer_name,
            'child_name'       => $child_name,
            'occasion'         => $occasion,
            'gift_description' => $gift_desc,
            'product_link'     => $prod_link ?: null,
            'amount'           => $amount,
        ]);
    } 
    // --- CREATE ---
    else {
        $stmt = $pdo->prepare("
            INSERT INTO {$tableName}
              (year, adult_name, payer_name, child_name, occasion, gift_description, product_link, amount)
            VALUES
              (:year, :adult_name, :payer_name, :child_name, :occasion, :gift_description, :product_link, :amount)
        ");
        $stmt->execute([
            'year'             => $year,
            'adult_name'       => $adult_name,
            'payer_name'       => $payer_name,
            'child_name'       => $child_name,
            'occasion'         => $occasion,
            'gift_description' => $gift_desc,
            'product_link'     => $prod_link ?: null,
            'amount'           => $amount,
        ]);
    }

} catch (PDOException $e) {
    // Log l'erreur si besoin
}

header("Location: $redirectUrl");
exit;