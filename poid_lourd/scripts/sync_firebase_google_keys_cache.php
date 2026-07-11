<?php
/**
 * Télécharge les clés publiques Firebase/Google et met à jour le cache local.
 *
 * À lancer sur une machine qui accède à googleapis.com (ex. WAMP local),
 * puis déployer config/firebase_google_public_keys_cache.json sur le VPS.
 *
 * CLI : php scripts/sync_firebase_google_keys_cache.php
 * Web (admin connecté) : /scripts/sync_firebase_google_keys_cache.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    require_once $root . '/includes/session_admin.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Accès refusé — connectez-vous en tant qu'administrateur.\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function sync_keys_out(string $line): void
{
    echo $line . (PHP_SAPI === 'cli' ? "\n" : "\n");
}

$cfg_path = $root . '/config/firebase_server.php';
if (!file_exists($cfg_path)) {
    sync_keys_out('Erreur : config/firebase_server.php manquant.');
    exit(1);
}

$cfg = require $cfg_path;
$cacert = $cfg['cacert_path'] ?? $root . '/config/cacert.pem';
if ($cacert !== '' && file_exists($cacert)) {
    $real = realpath($cacert);
    if ($real !== false) {
        putenv('SSL_CERT_FILE=' . $real);
        putenv('CURL_CA_BUNDLE=' . $real);
    }
}

require_once $root . '/includes/firebase_auth_keys_file_handler.php';

$client = new GuzzleHttp\Client([
    'http_errors' => false,
    'verify' => file_exists($cacert) ? $cacert : true,
    'timeout' => 25,
]);

$clock = \Beste\Clock\SystemClock::create();
$network = new \Kreait\Firebase\JWT\Action\FetchGooglePublicKeys\WithGuzzle($client, $clock);
$handler = new ColobanesFirebaseGoogleKeysFileHandler($network, $clock);

sync_keys_out('Synchronisation des clés Google (Firebase Auth)…');

try {
    $keys = $handler->handle(\Kreait\Firebase\JWT\Action\FetchGooglePublicKeys::fromGoogle());
    $count = count($keys->all());
    sync_keys_out('OK — ' . $count . ' clé(s) enregistrée(s) dans config/firebase_google_public_keys_cache.json');
    sync_keys_out('Déployez ce fichier sur le serveur de production si le VPS bloque googleapis.com.');
} catch (Throwable $e) {
    sync_keys_out('Erreur : ' . $e->getMessage());
    exit(1);
}
