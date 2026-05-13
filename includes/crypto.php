<?php
function hh_encrypt_secret(string $plain): string
{
    $keyMaterial = getenv('APP_SECRET_KEY') ?: '';
    if ($keyMaterial === '') {
        throw new RuntimeException('APP_SECRET_KEY manquant');
    }
    $key = hash('sha256', $keyMaterial, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Chiffrement impossible');
    }
    return base64_encode($iv . $cipher);
}

function hh_decrypt_secret(string $encrypted): string
{
    $keyMaterial = getenv('APP_SECRET_KEY') ?: '';
    if ($keyMaterial === '') {
        throw new RuntimeException('APP_SECRET_KEY manquant');
    }
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) <= 16) {
        throw new RuntimeException('Secret invalide');
    }
    $key = hash('sha256', $keyMaterial, true);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('Déchiffrement impossible');
    }
    return $plain;
}
