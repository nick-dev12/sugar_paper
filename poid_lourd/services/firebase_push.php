<?php
/**
 * Service d'envoi de notifications push via Firebase Cloud Messaging (FCM)
 * Utilise kreait/firebase-php si disponible (composer install), sinon implémentation native
 */

/**
 * Retourne la configuration Firebase serveur
 */
function _firebase_get_config() {
    $config_path = __DIR__ . '/../config/firebase_server.php';
    if (file_exists($config_path)) {
        $config = require $config_path;
        return $config;
    }
    return ['credentials_path' => __DIR__ . '/../config/colobanes-firebase-adminsdk-fbsvc-b4db241730.json'];
}

/**
 * URL publique du site pour les liens web push (icône, clic notification)
 */
function firebase_public_site_url() {
    static $url = null;
    if ($url !== null) {
        return $url;
    }

    $config = _firebase_get_config();
    if (!empty($config['public_site_url'])) {
        $url = rtrim((string) $config['public_site_url'], '/');
        return $url;
    }

    $site_path = __DIR__ . '/../includes/site_url.php';
    if (file_exists($site_path)) {
        require_once $site_path;
        $base = rtrim(get_site_base_url(), '/');
        if ($base !== '' && $base !== 'http://localhost') {
            $url = $base;
            return $url;
        }
    }

    $url = 'http://localhost:5000';
    return $url;
}

/**
 * Convertit un chemin relatif en URL absolue
 */
function firebase_absolute_url($path) {
    $path = trim((string) $path);
    if ($path === '') {
        return firebase_public_site_url() . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = firebase_public_site_url();
    return $base . (strpos($path, '/') === 0 ? $path : '/' . $path);
}

/**
 * Prépare les données push (liens absolus, titre/corps dans data pour le web)
 */
function firebase_prepare_push_data($title, $body, $data = []) {
    $payload = is_array($data) ? $data : [];
    if (!empty($payload['link'])) {
        $payload['link'] = firebase_absolute_url($payload['link']);
    }
    $payload['title'] = (string) $title;
    $payload['body'] = (string) $body;

    foreach ($payload as $k => $v) {
        $payload[$k] = (string) $v;
    }

    return $payload;
}

/**
 * Configuration webpush FCM (sans clé notification globale — meilleur support navigateur)
 */
function _firebase_build_webpush_config($title, $body, $dataPayload) {
    $link = $dataPayload['link'] ?? firebase_public_site_url() . '/';
    $icon = firebase_absolute_url('/image/logo_market.jpeg');

    return [
        'headers' => [
            'Urgency' => 'high',
        ],
        'notification' => [
            'title' => (string) $title,
            'body' => (string) $body,
            'icon' => $icon,
        ],
        'fcm_options' => [
            'link' => (string) $link,
        ],
    ];
}

/**
 * Supprime les tokens invalides signalés par FCM
 */
function _firebase_purge_invalid_tokens($tokens, $errors) {
    if (empty($errors) || empty($tokens)) {
        return;
    }

    $purge = false;
    foreach ($errors as $err) {
        $msg = strtolower((string) $err);
        if (strpos($msg, 'unregistered') !== false
            || strpos($msg, 'not found') !== false
            || strpos($msg, 'invalid registration') !== false) {
            $purge = true;
            break;
        }
    }
    if (!$purge) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    foreach ($tokens as $token) {
        delete_fcm_token_by_value((string) $token);
    }
}

/**
 * Configure les certificats SSL pour corriger l'erreur cURL 60 (Windows/WAMP)
 */
function _firebase_configure_ssl() {
    $config = _firebase_get_config();
    $cacert = $config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
    if (file_exists($cacert)) {
        $path = realpath($cacert);
        putenv('SSL_CERT_FILE=' . $path);
        putenv('CURL_CA_BUNDLE=' . $path);
    }
}

/**
 * Vérifie que les dépendances Composer requises par kreait/firebase-php sont présentes.
 * Évite les warnings PHP (psr/cache manquant) qui cassent les redirections HTTP.
 */
function _firebase_library_dependencies_ready() {
    $root = __DIR__ . '/../vendor';
    $autoload = $root . '/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }
    $required = [
        $root . '/psr/cache/src/CacheItemPoolInterface.php',
        $root . '/kreait/firebase-php/src/Firebase/Factory.php',
    ];
    foreach ($required as $path) {
        if (!is_file($path)) {
            return false;
        }
    }
    return true;
}

/**
 * Envoie une notification push FCM via kreait/firebase-php (si installé)
 * Retourne null en cas d'erreur de dépendances (ex: PSR Cache) pour déclencher le fallback natif
 */
function _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data) {
    _firebase_configure_ssl();
    if (!_firebase_library_dependencies_ready()) {
        return null;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    try {
        require_once $autoload;
    } catch (\Throwable $e) {
        return null;
    }
    if (!class_exists('Kreait\Firebase\Factory')) {
        return null;
    }

    try {
        $config = _firebase_get_config();
        $cacert = $config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($credentials_path);
        if (file_exists($cacert)) {
            $httpOptions = \Kreait\Firebase\Http\HttpClientOptions::default()
                ->withGuzzleConfigOption('verify', realpath($cacert));
            $factory = $factory->withHttpClientOptions($httpOptions);
        }
        $messaging = $factory->createMessaging();

        $dataPayload = firebase_prepare_push_data($title, $body, $data);
        $webPush = _firebase_build_webpush_config($title, $body, $dataPayload);

        $success = 0;
        $errors = [];
        $failed_tokens = [];

        $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);

        foreach ($tokens as $token) {
            try {
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($dataPayload)
                    ->withWebPushConfig(\Kreait\Firebase\Messaging\WebPushConfig::fromArray($webPush));
                $messaging->send($message);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $failed_tokens[] = $token;
            }
        }

        if (!empty($failed_tokens)) {
            _firebase_purge_invalid_tokens($failed_tokens, $errors);
        }

        return [
            'success' => $success,
            'failed' => count($tokens) - $success,
            'errors' => $errors
        ];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'CacheItemPoolInterface') !== false
            || stripos($msg, 'Interface') !== false && stripos($msg, 'not found') !== false
            || stripos($msg, 'Class') !== false && stripos($msg, 'not found') !== false) {
            return null;
        }
        return ['success' => 0, 'failed' => count($tokens), 'errors' => [$msg]];
    }
}

/**
 * Implémentation native (fallback sans Composer)
 */
function _fcm_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _firebase_get_project_id($credentials_path) {
    if (!file_exists($credentials_path)) {
        return 'colobanes';
    }
    $credentials = json_decode(file_get_contents($credentials_path), true);
    return $credentials['project_id'] ?? 'colobanes';
}

function firebase_get_access_token($credentials_path) {
    if (!file_exists($credentials_path)) {
        return null;
    }
    $credentials = json_decode(file_get_contents($credentials_path), true);
    if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
        return null;
    }
    $now = time();
    $payload = [
        'iss' => $credentials['client_email'],
        'sub' => $credentials['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ];
    $header = _fcm_base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payloadEnc = _fcm_base64url_encode(json_encode($payload));
    $signatureInput = $header . '.' . $payloadEnc;
    $privateKey = openssl_pkey_get_private($credentials['private_key']);
    if (!$privateKey) {
        return null;
    }
    openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $signature = _fcm_base64url_encode($signature);
    $jwt = $signatureInput . '.' . $signature;
    $data = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);
    $opts = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => $data
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if (!$result) {
        return null;
    }
    $response = json_decode($result, true);
    return $response['access_token'] ?? null;
}

function _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data) {
    $access_token = firebase_get_access_token($credentials_path);
    if (!$access_token) {
        return ['success' => 0, 'failed' => count($tokens), 'errors' => ['Impossible d\'obtenir le token d\'accès']];
    }
    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $dataPayload = firebase_prepare_push_data($title, $body, $data);
    $webPush = _firebase_build_webpush_config($title, $body, $dataPayload);

    $success = 0;
    $errors = [];
    $failed_tokens = [];

    foreach ($tokens as $token) {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                ],
                'data' => $dataPayload,
                'webpush' => $webPush,
            ]
        ];
        $opts = [
            'http' => [
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$access_token}\r\n",
                'method' => 'POST',
                'content' => json_encode($message)
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) {
            $response = json_decode($result, true);
            if (isset($response['name'])) {
                $success++;
            } else {
                $errors[] = $response['error']['message'] ?? 'Erreur inconnue';
                $failed_tokens[] = $token;
            }
        } else {
            $errors[] = 'Échec de la requête HTTP';
            $failed_tokens[] = $token;
        }
    }

    if (!empty($failed_tokens)) {
        _firebase_purge_invalid_tokens($failed_tokens, $errors);
    }

    return [
        'success' => $success,
        'failed' => count($tokens) - $success,
        'errors' => $errors
    ];
}

/**
 * Envoie une notification push FCM à un ou plusieurs tokens
 * @param array $tokens Liste des tokens FCM
 * @param string $title Titre de la notification
 * @param string $body Corps du message
 * @param array $data Données additionnelles (optionnel)
 * @return array ['success' => int, 'failed' => int, 'errors' => array]
 */
function firebase_send_notification($tokens, $title, $body, $data = []) {
    if (empty($tokens)) {
        return ['success' => 0, 'failed' => 0, 'errors' => []];
    }
    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $project_id = _firebase_get_project_id($credentials_path);

    $result = _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data);
    if ($result !== null) {
        return $result;
    }
    return _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data);
}
