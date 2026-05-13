<?php

/**
 * Matériau de clé pour chiffrer les secrets (mot de passe d’app iCloud, etc.).
 * Ordre de priorité :
 * 1. Variable d’environnement APP_SECRET_KEY (recommandé en production)
 * 2. Fichier data/.hh_app_secret (généré automatiquement au premier besoin si le dossier est inscriptible)
 * 3. Dérivation stable depuis DB_PASS + DB_HOST (évite l’erreur si rien n’est configuré ; change si le mot de passe DB change)
 */
function hh_secret_key_material(): string
{
    $fromEnv = trim((string) getenv('APP_SECRET_KEY'));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $keyFile = dirname(__DIR__) . '/data/.hh_app_secret';
    if (is_readable($keyFile)) {
        $content = trim((string) file_get_contents($keyFile));
        if ($content !== '' && strlen($content) >= 32) {
            return $content;
        }
    }

    $dir = dirname($keyFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        try {
            $material = bin2hex(random_bytes(32));
            if (@file_put_contents($keyFile, $material, LOCK_EX) !== false) {
                @chmod($keyFile, 0600);
                return $material;
            }
        } catch (\Throwable $e) {
            // continuer vers la dérivation
        }
    }

    $dbPass = (string) (getenv('DB_PASS') ?: '');
    $dbHost = (string) (getenv('DB_HOST') ?: '');
    return 'hh-derived|' . $dbPass . '|' . $dbHost . '|HouseHub-calendar-ios-v1';
}

function hh_encryption_key(): string
{
    return hash('sha256', hh_secret_key_material(), true);
}

function hh_encrypt_secret(string $plain): string
{
    $key = hh_encryption_key();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Chiffrement impossible');
    }
    return base64_encode($iv . $cipher);
}

function hh_decrypt_secret(string $encrypted): string
{
    $key = hh_encryption_key();
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) <= 16) {
        throw new RuntimeException('Secret invalide');
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('Déchiffrement impossible');
    }
    return $plain;
}
