<?php
/**
 * Génère colobane/lib/config/firebase_auth_config.dart depuis config/firebase_config.php
 *
 * Usage CLI (recommandé) :
 *   php scripts/sync_colobane_auth_config.php
 *
 * Usage navigateur (localhost uniquement) :
 *   http://localhost:5000/scripts/sync_colobane_auth_config.php
 */
declare(strict_types=1);

function sync_auth_message(string $message, bool $isError = false): void
{
    if (PHP_SAPI === 'cli') {
        $stream = $isError ? STDERR : STDOUT;
        fwrite($stream, $message . PHP_EOL);
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        if ($isError) {
            http_response_code(500);
        }
    }

    echo $message . PHP_EOL;
}

function sync_auth_abort(string $message): void
{
    sync_auth_message($message, true);
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed = in_array($remote, ['127.0.0.1', '::1'], true);
    if (!$allowed) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Accès refusé. Exécutez en CLI : php scripts/sync_colobane_auth_config.php\n";
        exit(1);
    }
}

$root = dirname(__DIR__);
$config_path = $root . '/config/firebase_config.php';
$out_path = $root . '/colobane/lib/config/firebase_auth_config.dart';

if (!is_file($config_path)) {
    sync_auth_abort("Fichier introuvable : {$config_path}");
}

$cfg = require $config_path;
$auth = is_array($cfg['auth'] ?? null) ? $cfg['auth'] : [];

$required = [
    'webClientId',
    'iosClientId',
    'appleServicesId',
    'appleAndroidRedirectUri',
];
foreach ($required as $key) {
    if (empty($auth[$key]) || !is_string($auth[$key])) {
        sync_auth_abort("Clé auth manquante dans firebase_config.php : {$key}");
    }
}

$dart = <<<'HEADER'
/// Configuration auth sociale — app native COLObanes.
/// Généré depuis config/firebase_config.php — ne pas éditer à la main.
/// Regénérer : php scripts/sync_colobane_auth_config.php

HEADER;

$lines = [
    "const String kFirebaseWebClientId =\n    '" . $auth['webClientId'] . "';",
    "const String kFirebaseIosClientId =\n    '" . $auth['iosClientId'] . "';",
    "const String kAppleServicesClientId = '" . $auth['appleServicesId'] . "';",
    "const String kAppleAndroidRedirectUri =\n    '" . $auth['appleAndroidRedirectUri'] . "';",
];

if (!empty($auth['appleOAuthRedirectUri'])) {
    $lines[] = "/// Site web uniquement (Firebase JS) — ne pas utiliser sur Android.";
    $lines[] = "const String kAppleWebOAuthRedirectUri =\n    '" . $auth['appleOAuthRedirectUri'] . "';";
}

if (!empty($cfg['authDomain'])) {
    $lines[] = "const String kFirebaseAuthDomain = '" . $cfg['authDomain'] . "';";
}

$dart .= implode("\n\n", $lines) . "\n";

if (file_put_contents($out_path, $dart) === false) {
    sync_auth_abort("Échec écriture : {$out_path}");
}

sync_auth_message("OK — {$out_path} mis à jour.");
