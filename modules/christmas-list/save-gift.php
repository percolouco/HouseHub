<?php
require __DIR__ . '/../../includes/auth.php';
require_login('/christmas-list.php');

require __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $gift_id = (int)($_POST['gift_id'] ?? 0);
        if ($gift_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM pf_christmas_gifts WHERE id = :id");
            $stmt->execute(['id' => $gift_id]);
        }

        header('Location: /christmas-list.php');
        exit;
    }

    // Champs communs
    $year         = (int)($_POST['year'] ?? date('Y'));
    $adult_name   = trim($_POST['adult_name'] ?? '');
    $payer_name   = trim($_POST['payer_name'] ?? ''); // nouveau champ
    $child_name   = trim($_POST['child_name'] ?? '');
    $occasion     = trim($_POST['occasion'] ?? '');
    $gift_desc    = trim($_POST['gift_description'] ?? '');
    $product_link = trim($_POST['product_link'] ?? '');
    $amount       = $_POST['amount'] !== '' ? (float)$_POST['amount'] : 0.0;

    if ($payer_name === '') {
        $payer_name = $adult_name;
    }

    if ($action === 'update') {
        $gift_id = (int)($_POST['gift_id'] ?? 0);
        if ($gift_id > 0 && $adult_name && $payer_name && $child_name && $occasion && $gift_desc) {
            $stmt = $pdo->prepare("
                UPDATE pf_christmas_gifts
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
                'product_link'     => $product_link ?: null,
                'amount'           => $amount,
            ]);
        }

        header('Location: /christmas-list.php');
        exit;
    }

    // create (par défaut)
    if ($adult_name && $payer_name && $child_name && $occasion && $gift_desc) {
        $stmt = $pdo->prepare("
            INSERT INTO pf_christmas_gifts
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
            'product_link'     => $product_link ?: null,
            'amount'           => $amount,
        ]);
    }

    header('Location: /christmas-list.php');
    exit;
}
