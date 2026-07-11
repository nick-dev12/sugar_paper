<?php
/**
 * Vérification des tokens Firebase Auth (Google, Apple, etc.).
 */

function firebase_auth_token_error($message)
{
    return ['success' => false, 'message' => $message, 'claims' => null, 'provider' => ''];
}

function firebase_auth_get_project_id($credentials_path)
{
    $firebase_config_path = __DIR__ . '/../config/firebase_config.php';
    if (file_exists($firebase_config_path)) {
        $cfg = require $firebase_config_path;
        if (!empty($cfg['projectId'])) {
            return (string) $cfg['projectId'];
        }
    }

    if ($credentials_path !== '' && file_exists($credentials_path)) {
        $json = json_decode((string) file_get_contents($credentials_path), true);
        if (is_array($json) && !empty($json['project_id'])) {
            return (string) $json['project_id'];
        }
    }

    return '';
}

function firebase_auth_configure_ssl($cacert_path)
{
    if ($cacert_path === '' || !file_exists($cacert_path)) {
        return false;
    }

    $real_cacert = realpath($cacert_path);
    if ($real_cacert === false) {
        return false;
    }

    putenv('SSL_CERT_FILE=' . $real_cacert);
    putenv('CURL_CA_BUNDLE=' . $real_cacert);
    ini_set('openssl.cafile', $real_cacert);
    ini_set('curl.cainfo', $real_cacert);

    return $real_cacert;
}

function firebase_auth_create_verifier($project_id, $cacert_real)
{
    $client = new \GuzzleHttp\Client([
        'http_errors' => false,
        'verify' => $cacert_real,
        'timeout' => 5,
        'connect_timeout' => 3,
    ]);

    $clock = \Beste\Clock\SystemClock::create();
    $network_handler = new \Kreait\Firebase\JWT\Action\FetchGooglePublicKeys\WithGuzzle($client, $clock);
    require_once __DIR__ . '/firebase_auth_keys_file_handler.php';
    $key_handler = new ColobanesFirebaseGoogleKeysFileHandler($network_handler, $clock);
    $keys = new \Kreait\Firebase\JWT\GooglePublicKeys($key_handler, $clock);
    $handler = new \Kreait\Firebase\JWT\Action\VerifyIdToken\WithLcobucciJWT($project_id, $keys, $clock);

    return new \Kreait\Firebase\JWT\IdTokenVerifier($handler);
}

function firebase_auth_provider_label($provider)
{
    if ($provider === 'apple.com') {
        return 'Apple';
    }
    if ($provider === 'google.com') {
        return 'Google';
    }
    return 'Firebase';
}

function firebase_auth_normalize_provider($provider)
{
    $provider = trim((string) $provider);
    if ($provider === 'apple.com') {
        return 'apple';
    }
    if ($provider === 'google.com') {
        return 'google';
    }
    return $provider;
}

/**
 * @param string|null $expected_provider google.com | apple.com | null (les deux)
 */
function firebase_auth_get_server_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $server_config_path = __DIR__ . '/../config/firebase_server.php';
    if (!file_exists($server_config_path)) {
        return null;
    }

    $loaded = require $server_config_path;
    $config = is_array($loaded) ? $loaded : null;

    return $config;
}

function firebase_auth_get_verifier($project_id, $cacert_real)
{
    static $verifiers = [];

    $cache_key = $project_id . '|' . $cacert_real;
    if (!isset($verifiers[$cache_key])) {
        $verifiers[$cache_key] = firebase_auth_create_verifier($project_id, $cacert_real);
    }

    return $verifiers[$cache_key];
}

function firebase_auth_verify_id_token($id_token, $expected_provider = null)
{
    $id_token = trim((string) $id_token);
    if ($id_token === '') {
        return firebase_auth_token_error('Token d’authentification manquant.');
    }

    static $autoloaded = false;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return firebase_auth_token_error('Dépendances Firebase absentes. Exécutez composer install.');
    }
    if (!$autoloaded) {
        require_once $autoload;
        $autoloaded = true;
    }

    if (!class_exists('\Kreait\Firebase\JWT\IdTokenVerifier')) {
        return firebase_auth_token_error('Librairie Firebase PHP indisponible.');
    }

    $server_config = firebase_auth_get_server_config();
    if ($server_config === null) {
        return firebase_auth_token_error('Configuration serveur Firebase manquante.');
    }

    $credentials_path = $server_config['credentials_path'] ?? '';
    if ($credentials_path === '' || !file_exists($credentials_path)) {
        return firebase_auth_token_error('Clé de service Firebase introuvable.');
    }

    $project_id = firebase_auth_get_project_id($credentials_path);
    if ($project_id === '') {
        return firebase_auth_token_error('Project ID Firebase introuvable.');
    }

    $cacert = $server_config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
    static $cacert_real_cache = null;
    if ($cacert_real_cache === null) {
        $cacert_real_cache = firebase_auth_configure_ssl($cacert);
    }
    $cacert_real = $cacert_real_cache;
    if ($cacert_real === false) {
        return firebase_auth_token_error(
            'Certificat SSL local manquant (config/cacert.pem). Téléchargez le fichier CA depuis https://curl.se/ca/cacert.pem'
        );
    }

    try {
        $verifier = firebase_auth_get_verifier($project_id, $cacert_real);
        $leeway_seconds = 300;
        $verified_token = $verifier->verifyIdTokenWithLeeway($id_token, $leeway_seconds);
        $claims = $verified_token->payload();

        $provider = '';
        if (!empty($claims['firebase']['sign_in_provider'])) {
            $provider = (string) $claims['firebase']['sign_in_provider'];
        }

        $allowed = ['google.com', 'apple.com'];
        if ($expected_provider !== null && $expected_provider !== '') {
            $allowed = [(string) $expected_provider];
        }

        if (!in_array($provider, $allowed, true)) {
            $label = firebase_auth_provider_label($expected_provider ?: $provider);
            return firebase_auth_token_error('Ce token ne provient pas de ' . $label . '.');
        }

        return [
            'success' => true,
            'message' => '',
            'claims' => $claims,
            'provider' => $provider,
        ];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $label = firebase_auth_provider_label($expected_provider ?: '');

        if (stripos($msg, 'cURL error 60') !== false || stripos($msg, 'SSL certificate') !== false) {
            return firebase_auth_token_error(
                'Erreur SSL locale (WAMP) : vérifiez que config/cacert.pem existe et que curl.cainfo est configuré dans php.ini.'
            );
        }
        if (stripos($msg, 'issued in the future') !== false || stripos($msg, 'expired') !== false) {
            return firebase_auth_token_error(
                'Horloge du serveur incorrecte. Vérifiez la date/heure du serveur (synchronisation NTP), puis réessayez.'
            );
        }
        if (stripos($msg, 'Connection refused') !== false
            || stripos($msg, 'failed: Connection') !== false
            || stripos($msg, 'Could not resolve host') !== false
            || stripos($msg, 'FetchingGooglePublicKeysFailed') !== false) {
            return firebase_auth_token_error(
                'Vérification Google indisponible depuis le serveur (accès googleapis.com bloqué). '
                . 'Sur votre PC : php scripts/sync_firebase_google_keys_cache.php puis uploadez '
                . 'config/firebase_google_public_keys_cache.json sur le VPS. '
                . 'Contactez l’hébergeur pour autoriser les connexions HTTPS sortantes vers *.googleapis.com.'
            );
        }

        $prefix = $label !== '' && $label !== 'Firebase'
            ? 'Connexion ' . $label . ' impossible : '
            : 'Connexion impossible : ';

        return firebase_auth_token_error($prefix . $msg);
    }
}

function firebase_auth_profile_from_claims(array $claims)
{
    $uid = isset($claims['sub']) ? trim((string) $claims['sub']) : '';
    $email = isset($claims['email']) ? trim((string) $claims['email']) : '';
    $name = isset($claims['name']) ? trim((string) $claims['name']) : '';
    $picture = isset($claims['picture']) ? trim((string) $claims['picture']) : '';

    $firebase = (isset($claims['firebase']) && is_array($claims['firebase'])) ? $claims['firebase'] : [];
    $identities = (isset($firebase['identities']) && is_array($firebase['identities'])) ? $firebase['identities'] : [];

    if ($name === '' && !empty($identities['apple.com'])) {
        $name = 'Utilisateur Apple';
    }

    $provider = '';
    if (!empty($firebase['sign_in_provider'])) {
        $provider = (string) $firebase['sign_in_provider'];
    }

    return [
        'uid' => $uid,
        'email' => $email,
        'name' => $name,
        'picture' => $picture,
        'provider' => $provider,
        'provider_key' => firebase_auth_normalize_provider($provider),
    ];
}