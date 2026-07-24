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
        $config['credentials_path'] = $config['credentials_path'] ?? __DIR__ . '/../sugar-paper-d34851eeca5a.json';
        return $config;
    }
    return ['credentials_path' => __DIR__ . '/../sugar-paper-d34851eeca5a.json'];
}

/**
 * Prépare le payload data (liens absolus + titre/corps pour le web et l'app native)
 */
function firebase_prepare_push_data($title, $body, $data = []) {
    require_once __DIR__ . '/../includes/site_url.php';
    $payload = is_array($data) ? $data : [];
    if (!empty($payload['link'])) {
        $link = (string) $payload['link'];
        if (!preg_match('#^https?://#i', $link)) {
            $payload['link'] = rtrim(get_site_base_url(), '/') . (strpos($link, '/') === 0 ? $link : '/' . $link);
        }
    }
    $payload['title'] = (string) $title;
    $payload['body'] = (string) $body;
    foreach ($payload as $k => $v) {
        $payload[$k] = (string) $v;
    }
    return $payload;
}

/**
 * URL absolue de l'icône / logo pour les notifications push (web + SW)
 * @return string
 */
function firebase_get_notification_icon_url() {
    require_once __DIR__ . '/../includes/site_url.php';
    $base = rtrim(get_site_base_url(), '/');
    // Logo PWA officiel (carré, adapté aux toasts Windows / Chrome / Edge)
    return $base . '/icons/icon-192.png';
}

/**
 * Configuration Android / iOS pour l'app Flutter Sugar Paper
 * Force une alerte native (bannière + son), pas une notification silencieuse
 */
function _firebase_build_mobile_config($title, $body, $dataPayload) {
    $link = $dataPayload['link'] ?? '/';
    return [
        'android' => [
            'priority' => 'high',
            'notification' => [
                'channel_id' => 'sugar_paper_alerts',
                'title' => (string) $title,
                'body' => (string) $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default',
                'default_sound' => true,
                'default_vibrate_timings' => true,
                'notification_priority' => 'PRIORITY_MAX',
                'visibility' => 'PUBLIC',
            ],
        ],
        'apns' => [
            'headers' => [
                'apns-priority' => '10',
                'apns-push-type' => 'alert',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => (string) $title,
                        'body' => (string) $body,
                    ],
                    'sound' => 'default',
                    'badge' => 1,
                    'interruption-level' => 'time-sensitive',
                ],
                'link' => (string) $link,
            ],
        ],
    ];
}

/**
 * Configuration Web Push (navigateur / PWA) avec logo du site
 */
function _firebase_build_webpush_config($title, $body, $dataPayload) {
    $iconUrl = firebase_get_notification_icon_url();
    return [
        'headers' => [
            'Urgency' => 'high',
            'TTL' => '86400',
        ],
        'notification' => [
            'title' => (string) $title,
            'body' => (string) $body,
            'icon' => $iconUrl,
            'badge' => $iconUrl,
            'image' => $iconUrl,
            'requireInteraction' => false,
            'silent' => false,
            'vibrate' => [200, 100, 200],
        ],
        'fcm_options' => [
            'link' => $dataPayload['link'] ?? '/',
        ],
    ];
}

/**
 * Vérifie que les dépendances Composer requises par kreait/firebase-php sont présentes.
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
 * Envoie une notification push FCM via kreait/firebase-php (si installé)
 * Retourne null en cas d'erreur de dépendances (ex: PSR Cache) pour déclencher le fallback natif
 */
function _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data) {
    _firebase_configure_ssl();
    if (!_firebase_library_dependencies_ready()) {
        return null;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }

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
        $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);
        $mobile = _firebase_build_mobile_config($title, $body, $dataPayload);

        $success = 0;
        $errors = [];

        foreach ($tokens as $token) {
            try {
                $webpush = _firebase_build_webpush_config($title, $body, $dataPayload);
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($dataPayload)
                    ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray($mobile['android']))
                    ->withApnsConfig(\Kreait\Firebase\Messaging\ApnsConfig::fromArray($mobile['apns']))
                    ->withWebPushConfig(\Kreait\Firebase\Messaging\WebPushConfig::fromArray($webpush));
                $messaging->send($message);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => count($tokens) - $success,
            'errors' => $errors
        ];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        // Erreurs de dépendances (PSR Cache, etc.) : basculer vers l'implémentation native
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
        return 'sugar-paper';
    }
    $credentials = json_decode(file_get_contents($credentials_path), true);
    return $credentials['project_id'] ?? 'sugar-paper';
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
    $success = 0;
    $errors = [];
    foreach ($tokens as $token) {
        $dataPayload = firebase_prepare_push_data($title, $body, $data);
        $mobile = _firebase_build_mobile_config($title, $body, $dataPayload);
        $webpush = _firebase_build_webpush_config($title, $body, $dataPayload);
        $message = [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $dataPayload,
                'android' => $mobile['android'],
                'apns' => $mobile['apns'],
                'webpush' => $webpush,
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
                $errMsg = $response['error']['message'] ?? 'Erreur inconnue';
                $errors[] = $errMsg;
                if (stripos($errMsg, 'UNREGISTERED') !== false
                    || stripos($errMsg, 'NOT_FOUND') !== false
                    || stripos($errMsg, 'InvalidRegistration') !== false) {
                    if (!function_exists('fcm_delete_invalid_tokens')) {
                        require_once __DIR__ . '/../models/model_fcm.php';
                    }
                    fcm_delete_invalid_tokens([$token]);
                }
            }
        } else {
            $errors[] = 'Échec de la requête HTTP';
        }
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
    if (!is_array($tokens)) {
        $tokens = [$tokens];
    }
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
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

/**
 * Envoie une notification push à chaque admin éligible individuellement
 * (un envoi par compte, sur tous ses appareils)
 *
 * @param string $title
 * @param string $body
 * @param array $data
 * @return array ['success'=>int,'failed'=>int,'admins_notified'=>int,'admins_total'=>int,'errors'=>array,'details'=>array]
 */
function firebase_send_notification_to_all_admins($title, $body, $data = []) {
    if (!function_exists('get_fcm_admin_token_groups')) {
        require_once __DIR__ . '/../models/model_fcm.php';
    }

    $groups = get_fcm_admin_token_groups();
    $total_success = 0;
    $total_failed = 0;
    $admins_notified = 0;
    $errors = [];
    $details = [];

    foreach ($groups as $group) {
        $admin_id = (int) $group['admin_id'];
        $tokens = $group['tokens'];
        if (empty($tokens)) {
            continue;
        }

        // Tag unique par admin pour éviter qu'un navigateur ne fusionne les alertes multi-comptes
        $payload = is_array($data) ? $data : [];
        $base_tag = isset($payload['tag']) ? (string) $payload['tag'] : ('admin-alert-' . time());
        $payload['tag'] = $base_tag . '-a' . $admin_id;
        $payload['admin_id'] = (string) $admin_id;

        $result = firebase_send_notification($tokens, $title, $body, $payload);
        $ok = (int) ($result['success'] ?? 0);
        $ko = (int) ($result['failed'] ?? 0);
        $total_success += $ok;
        $total_failed += $ko;
        if ($ok > 0) {
            $admins_notified++;
        }
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $errors[] = 'admin#' . $admin_id . ': ' . $err;
            }
        }
        $details[] = [
            'admin_id' => $admin_id,
            'email' => $group['email'] ?? '',
            'tokens' => count($tokens),
            'success' => $ok,
            'failed' => $ko,
        ];
    }

    return [
        'success' => $total_success,
        'failed' => $total_failed,
        'admins_notified' => $admins_notified,
        'admins_total' => count($groups),
        'errors' => $errors,
        'details' => $details,
    ];
}
