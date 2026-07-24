<?php
/**
 * Service d'envoi de notifications push via Firebase Cloud Messaging (FCM)
 * Utilise kreait/firebase-php si disponible (composer install), sinon implémentation native
 *
 * Important : les envois multi-appareils doivent être parallèles (multicast / curl_multi).
 * Un envoi séquentiel token-par-token dépasse souvent max_execution_time et ne notifie
 * que le premier compte (souvent l'admin principal avec plusieurs tokens).
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
 * Journalise un envoi FCM (diagnostic multi-admins)
 */
function _firebase_log_send($context, array $result) {
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('c') . ' [' . $context . '] '
        . 'success=' . (int) ($result['success'] ?? 0)
        . ' failed=' . (int) ($result['failed'] ?? 0)
        . ' admins=' . (int) ($result['admins_notified'] ?? 0) . '/' . (int) ($result['admins_total'] ?? 0);
    if (!empty($result['errors'])) {
        $line .= ' errors=' . implode(' | ', array_slice($result['errors'], 0, 8));
    }
    $line .= "\n";
    @file_put_contents($dir . '/fcm_send.log', $line, FILE_APPEND | LOCK_EX);
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
    return $base . '/icons/icon-192.png';
}

/**
 * Configuration Android / iOS pour l'app Flutter Sugar Paper
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
    $tag = isset($dataPayload['tag']) ? (string) $dataPayload['tag'] : ('sugar-paper-' . time());
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
            'tag' => $tag,
            'requireInteraction' => false,
            'silent' => false,
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
 * Instance Messaging Kreait réutilisable (évite de recréer le client à chaque groupe)
 * @return mixed|null
 */
function _firebase_get_messaging($credentials_path) {
    static $cache = [];
    $key = realpath($credentials_path) ?: $credentials_path;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

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
                ->withGuzzleConfigOption('verify', realpath($cacert))
                ->withGuzzleConfigOption('timeout', 20)
                ->withGuzzleConfigOption('connect_timeout', 8);
            $factory = $factory->withHttpClientOptions($httpOptions);
        }
        $messaging = $factory->createMessaging();
        $cache[$key] = $messaging;
        return $messaging;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Indique si un message d'erreur FCM signifie un token à supprimer
 */
function _firebase_error_is_invalid_token($message) {
    $msg = (string) $message;
    return stripos($msg, 'UNREGISTERED') !== false
        || stripos($msg, 'NOT_FOUND') !== false
        || stripos($msg, 'InvalidRegistration') !== false
        || stripos($msg, 'invalid registration') !== false
        || stripos($msg, 'Requested entity was not found') !== false
        || stripos($msg, 'registration-token-not-registered') !== false;
}

/**
 * Envoie via kreait (multicast parallèle) — retourne null pour basculer en natif
 */
function _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data) {
    $messaging = _firebase_get_messaging($credentials_path);
    if ($messaging === null) {
        return null;
    }

    try {
        $dataPayload = firebase_prepare_push_data($title, $body, $data);
        $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);
        $mobile = _firebase_build_mobile_config($title, $body, $dataPayload);
        $webpush = _firebase_build_webpush_config($title, $body, $dataPayload);

        $message = \Kreait\Firebase\Messaging\CloudMessage::new()
            ->withNotification($notification)
            ->withData($dataPayload)
            ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray($mobile['android']))
            ->withApnsConfig(\Kreait\Firebase\Messaging\ApnsConfig::fromArray($mobile['apns']))
            ->withWebPushConfig(\Kreait\Firebase\Messaging\WebPushConfig::fromArray($webpush));

        $success = 0;
        $errors = [];
        $token_results = [];
        $invalid = [];

        // Multicast = sendAll concurrent (tous les appareils en parallèle)
        $report = $messaging->sendMulticast($message, $tokens);

        foreach ($report->getItems() as $item) {
            $target = $item->target();
            $token = method_exists($target, 'value') ? (string) $target->value() : '';
            if ($item->isSuccess()) {
                $success++;
                if ($token !== '') {
                    $token_results[$token] = true;
                }
                continue;
            }
            $err = $item->error();
            $errMsg = $err ? $err->getMessage() : 'Erreur FCM';
            $errors[] = $errMsg;
            if ($token !== '') {
                $token_results[$token] = false;
            }
            if ($token !== '' && (
                $item->messageWasSentToUnknownToken()
                || $item->messageTargetWasInvalid()
                || _firebase_error_is_invalid_token($errMsg)
            )) {
                $invalid[] = $token;
            }
        }

        if (!empty($invalid)) {
            if (!function_exists('fcm_delete_invalid_tokens')) {
                require_once __DIR__ . '/../models/model_fcm.php';
            }
            fcm_delete_invalid_tokens($invalid);
        }

        return [
            'success' => $success,
            'failed' => count($tokens) - $success,
            'errors' => $errors,
            'token_results' => $token_results,
            'invalid_tokens' => $invalid,
        ];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'CacheItemPoolInterface') !== false
            || (stripos($msg, 'Interface') !== false && stripos($msg, 'not found') !== false)
            || (stripos($msg, 'Class') !== false && stripos($msg, 'not found') !== false)) {
            return null;
        }
        return [
            'success' => 0,
            'failed' => count($tokens),
            'errors' => [$msg],
            'token_results' => [],
            'invalid_tokens' => [],
        ];
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
    static $cache = [];
    $cacheKey = realpath($credentials_path) ?: $credentials_path;
    if (isset($cache[$cacheKey]) && ($cache[$cacheKey]['expires'] ?? 0) > time() + 60) {
        return $cache[$cacheKey]['token'];
    }

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

    $token = null;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        _firebase_apply_curl_ssl($ch);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $response = json_decode($result, true);
            $token = $response['access_token'] ?? null;
        }
    } else {
        $opts = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => $data,
                'timeout' => 15,
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
        if ($result) {
            $response = json_decode($result, true);
            $token = $response['access_token'] ?? null;
        }
    }

    if ($token) {
        $cache[$cacheKey] = ['token' => $token, 'expires' => $now + 3500];
    }
    return $token;
}

/**
 * Applique le CA bundle à une ressource cURL
 */
function _firebase_apply_curl_ssl($ch) {
    $config = _firebase_get_config();
    $cacert = $config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
    if (file_exists($cacert)) {
        curl_setopt($ch, CURLOPT_CAINFO, realpath($cacert));
    }
}

/**
 * Envoi natif parallèle (curl_multi) — tous les tokens en même temps
 */
function _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data) {
    $access_token = firebase_get_access_token($credentials_path);
    if (!$access_token) {
        return [
            'success' => 0,
            'failed' => count($tokens),
            'errors' => ['Impossible d\'obtenir le token d\'accès'],
            'token_results' => [],
            'invalid_tokens' => [],
        ];
    }

    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $dataPayload = firebase_prepare_push_data($title, $body, $data);
    $mobile = _firebase_build_mobile_config($title, $body, $dataPayload);
    $webpush = _firebase_build_webpush_config($title, $body, $dataPayload);

    $success = 0;
    $errors = [];
    $token_results = [];
    $invalid = [];

    if (!function_exists('curl_multi_init')) {
        // Fallback ultra-simple séquentiel (rare)
        foreach ($tokens as $token) {
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
                    'content' => json_encode($message),
                    'timeout' => 12,
                ]
            ];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
            if ($result !== false) {
                $response = json_decode($result, true);
                if (isset($response['name'])) {
                    $success++;
                    $token_results[$token] = true;
                } else {
                    $errMsg = $response['error']['message'] ?? 'Erreur inconnue';
                    $errors[] = $errMsg;
                    $token_results[$token] = false;
                    if (_firebase_error_is_invalid_token($errMsg)) {
                        $invalid[] = $token;
                    }
                }
            } else {
                $errors[] = 'Échec de la requête HTTP';
                $token_results[$token] = false;
            }
        }
    } else {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($tokens as $idx => $token) {
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
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($message),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
            ]);
            _firebase_apply_curl_ssl($ch);
            curl_multi_add_handle($mh, $ch);
            $handles[$idx] = ['ch' => $ch, 'token' => $token];
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $item) {
            $ch = $item['ch'];
            $token = $item['token'];
            $result = curl_multi_getcontent($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $response = is_string($result) ? json_decode($result, true) : null;
            if (is_array($response) && isset($response['name'])) {
                $success++;
                $token_results[$token] = true;
            } else {
                $errMsg = is_array($response)
                    ? ($response['error']['message'] ?? ('HTTP ' . $httpCode))
                    : ('Échec HTTP ' . $httpCode);
                $errors[] = $errMsg;
                $token_results[$token] = false;
                if (_firebase_error_is_invalid_token($errMsg)) {
                    $invalid[] = $token;
                }
            }
        }
        curl_multi_close($mh);
    }

    if (!empty($invalid)) {
        if (!function_exists('fcm_delete_invalid_tokens')) {
            require_once __DIR__ . '/../models/model_fcm.php';
        }
        fcm_delete_invalid_tokens($invalid);
    }

    return [
        'success' => $success,
        'failed' => count($tokens) - $success,
        'errors' => $errors,
        'token_results' => $token_results,
        'invalid_tokens' => $invalid,
    ];
}

/**
 * Envoie une notification push FCM à un ou plusieurs tokens (en parallèle)
 * @param array $tokens Liste des tokens FCM
 * @param string $title Titre de la notification
 * @param string $body Corps du message
 * @param array $data Données additionnelles (optionnel)
 * @return array ['success' => int, 'failed' => int, 'errors' => array, 'token_results' => array]
 */
function firebase_send_notification($tokens, $title, $body, $data = []) {
    @set_time_limit(180);
    @ignore_user_abort(true);

    if (empty($tokens)) {
        return ['success' => 0, 'failed' => 0, 'errors' => [], 'token_results' => [], 'invalid_tokens' => []];
    }
    if (!is_array($tokens)) {
        $tokens = [$tokens];
    }
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
    if (empty($tokens)) {
        return ['success' => 0, 'failed' => 0, 'errors' => [], 'token_results' => [], 'invalid_tokens' => []];
    }

    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $project_id = _firebase_get_project_id($credentials_path);

    $result = _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data);
    if ($result === null) {
        $result = _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data);
    }

    // 2e tentative sur les tokens en échec HTTP / temporaire
    $token_results = is_array($result['token_results'] ?? null) ? $result['token_results'] : [];
    $retry_tokens = [];
    foreach ($tokens as $t) {
        $tok_ok = $token_results[$t] ?? null;
        if ($tok_ok === true) {
            continue;
        }
        if (in_array($t, $result['invalid_tokens'] ?? [], true)) {
            continue;
        }
        $err_blob = implode(' ', $result['errors'] ?? []);
        if ($tok_ok === false
            || $tok_ok === null
            || stripos($err_blob, 'HTTP') !== false
            || stripos($err_blob, 'timeout') !== false
            || stripos($err_blob, 'cURL') !== false
        ) {
            $retry_tokens[] = $t;
        }
    }
    $retry_tokens = array_values(array_unique($retry_tokens));
    if (!empty($retry_tokens)) {
        usleep(400000);
        $retry = _firebase_send_via_library($credentials_path, $retry_tokens, $title, $body, $data);
        if ($retry === null) {
            $retry = _firebase_send_native($credentials_path, $project_id, $retry_tokens, $title, $body, $data);
        }
        foreach (($retry['token_results'] ?? []) as $tok => $ok) {
            if ($ok) {
                $token_results[$tok] = true;
            } elseif (!isset($token_results[$tok])) {
                $token_results[$tok] = false;
            }
        }
        $result['errors'] = array_merge($result['errors'] ?? [], $retry['errors'] ?? []);
        $result['invalid_tokens'] = array_values(array_unique(array_merge(
            $result['invalid_tokens'] ?? [],
            $retry['invalid_tokens'] ?? []
        )));
        $success = 0;
        foreach ($tokens as $t) {
            if (!empty($token_results[$t])) {
                $success++;
            }
        }
        $result['success'] = $success;
        $result['failed'] = count($tokens) - $success;
        $result['token_results'] = $token_results;
    }

    return $result;
}

/**
 * Nom du topic FCM pour les alertes admin / utilisateur
 */
function firebase_fcm_admin_topic_name() {
    return 'sugar_paper_admin_alerts';
}

/**
 * Abonne des tokens au topic alertes admin
 * @param array $tokens
 * @return array{ok:bool,subscribed:int,errors:array}
 */
function firebase_fcm_subscribe_admin_topic(array $tokens) {
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
    if (empty($tokens)) {
        return ['ok' => true, 'subscribed' => 0, 'errors' => []];
    }

    $topic = firebase_fcm_admin_topic_name();
    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $messaging = _firebase_get_messaging($credentials_path);

    if ($messaging !== null) {
        try {
            $messaging->subscribeToTopic($topic, $tokens);
            return ['ok' => true, 'subscribed' => count($tokens), 'errors' => []];
        } catch (\Throwable $e) {
            // fallback natif ci-dessous
            $lib_err = $e->getMessage();
        }
    }

    $access = firebase_get_access_token($credentials_path);
    if (!$access) {
        return ['ok' => false, 'subscribed' => 0, 'errors' => [isset($lib_err) ? $lib_err : 'Token OAuth indisponible']];
    }

    $payload = json_encode([
        'to' => '/topics/' . $topic,
        'registration_tokens' => $tokens,
    ]);
    $ch = curl_init('https://iid.googleapis.com/iid/v1:batchAdd');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access,
            'access_token_auth: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    _firebase_apply_curl_ssl($ch);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        return ['ok' => false, 'subscribed' => 0, 'errors' => [$cerr !== '' ? $cerr : ('HTTP ' . $code . ' ' . (string) $body)]];
    }
    return ['ok' => true, 'subscribed' => count($tokens), 'errors' => []];
}

/**
 * Désabonne des tokens du topic alertes admin
 */
function firebase_fcm_unsubscribe_admin_topic(array $tokens) {
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
    if (empty($tokens)) {
        return true;
    }
    $topic = firebase_fcm_admin_topic_name();
    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $messaging = _firebase_get_messaging($credentials_path);
    if ($messaging !== null) {
        try {
            $messaging->unsubscribeFromTopic($topic, $tokens);
            return true;
        } catch (\Throwable $e) {
            // continue natif
        }
    }
    $access = firebase_get_access_token($credentials_path);
    if (!$access || !function_exists('curl_init')) {
        return false;
    }
    $payload = json_encode([
        'to' => '/topics/' . $topic,
        'registration_tokens' => $tokens,
    ]);
    $ch = curl_init('https://iid.googleapis.com/iid/v1:batchRemove');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access,
            'access_token_auth: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    _firebase_apply_curl_ssl($ch);
    curl_exec($ch);
    curl_close($ch);
    return true;
}

/**
 * Synchronise tous les tokens admin éligibles vers le topic
 */
function firebase_fcm_sync_admin_topic() {
    if (!function_exists('get_all_fcm_tokens_admin')) {
        require_once __DIR__ . '/../models/model_fcm.php';
    }
    $tokens = get_all_fcm_tokens_admin();
    return firebase_fcm_subscribe_admin_topic($tokens);
}

/**
 * Envoi FCM vers un topic
 */
function firebase_send_to_topic($topic, $title, $body, $data = []) {
    $topic = trim((string) $topic);
    if ($topic === '') {
        return ['success' => 0, 'failed' => 1, 'errors' => ['Topic vide']];
    }

    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $dataPayload = firebase_prepare_push_data($title, $body, $data);
    $mobile = _firebase_build_mobile_config($title, $body, $dataPayload);
    $webpush = _firebase_build_webpush_config($title, $body, $dataPayload);

    $messaging = _firebase_get_messaging($credentials_path);
    if ($messaging !== null) {
        try {
            $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($dataPayload)
                ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray($mobile['android']))
                ->withApnsConfig(\Kreait\Firebase\Messaging\ApnsConfig::fromArray($mobile['apns']))
                ->withWebPushConfig(\Kreait\Firebase\Messaging\WebPushConfig::fromArray($webpush));
            $messaging->send($message);
            return ['success' => 1, 'failed' => 0, 'errors' => [], 'channel' => 'topic'];
        } catch (\Throwable $e) {
            $lib_err = $e->getMessage();
        }
    }

    $project_id = _firebase_get_project_id($credentials_path);
    $access = firebase_get_access_token($credentials_path);
    if (!$access) {
        return ['success' => 0, 'failed' => 1, 'errors' => [isset($lib_err) ? $lib_err : 'OAuth indisponible']];
    }
    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $message = [
        'message' => [
            'topic' => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $dataPayload,
            'android' => $mobile['android'],
            'apns' => $mobile['apns'],
            'webpush' => $webpush,
        ],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($message),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    _firebase_apply_curl_ssl($ch);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $resp = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($resp) && isset($resp['name'])) {
        return ['success' => 1, 'failed' => 0, 'errors' => [], 'channel' => 'topic'];
    }
    $err = $cerr !== '' ? $cerr : (is_array($resp) ? ($resp['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code));
    if (isset($lib_err)) {
        $err = $lib_err . ' | ' . $err;
    }
    return ['success' => 0, 'failed' => 1, 'errors' => [$err], 'channel' => 'topic'];
}

/**
 * Envoie une notification push à TOUS les admins éligibles
 * 1) Synchronise le topic FCM (tous les tokens admin/utilisateur)
 * 2) Envoie via le topic (un message → tous les appareils abonnés)
 * 3) Si le topic échoue : envoi token-par-token (secours)
 *
 * @return array
 */
function firebase_send_notification_to_all_admins($title, $body, $data = []) {
    @set_time_limit(180);
    @ignore_user_abort(true);

    if (!function_exists('get_fcm_admin_token_groups')) {
        require_once __DIR__ . '/../models/model_fcm.php';
    }

    $groups = get_fcm_admin_token_groups();
    if (empty($groups)) {
        $empty = [
            'success' => 0,
            'failed' => 0,
            'admins_notified' => 0,
            'admins_total' => 0,
            'errors' => ['Aucun token admin éligible — chaque compte doit activer Notifications sur SON appareil'],
            'details' => [],
            'channel' => 'none',
        ];
        _firebase_log_send('all_admins', $empty);
        return $empty;
    }

    $all_tokens = [];
    foreach ($groups as $group) {
        foreach ($group['tokens'] as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                $all_tokens[] = $token;
            }
        }
    }
    $all_tokens = array_values(array_unique($all_tokens));

    $payload = is_array($data) ? $data : [];
    $base_tag = isset($payload['tag']) ? (string) $payload['tag'] : ('admin-alert-' . time());
    $payload['tag'] = $base_tag . '-' . time();

    $errors = [];
    $sync = firebase_fcm_subscribe_admin_topic($all_tokens);
    if (!empty($sync['errors'])) {
        foreach ($sync['errors'] as $e) {
            $errors[] = 'topic-sync: ' . $e;
        }
    }

    $topic_name = firebase_fcm_admin_topic_name();
    $topic_res = firebase_send_to_topic($topic_name, $title, $body, $payload);
    if (!empty($topic_res['errors'])) {
        foreach ($topic_res['errors'] as $e) {
            $errors[] = 'topic-send: ' . $e;
        }
    }

    $details = [];
    $total_success = 0;
    $total_failed = 0;
    $admins_notified = 0;
    $channel = 'topic';

    if (!empty($topic_res['success'])) {
        // Topic OK : tous les appareils abonnés reçoivent 1 notification (pas de double envoi)
        foreach ($groups as $group) {
            $n = count($group['tokens']);
            $total_success += $n;
            $admins_notified++;
            $details[] = [
                'admin_id' => (int) $group['admin_id'],
                'email' => $group['email'] ?? '',
                'role' => $group['role'] ?? '',
                'tokens' => $n,
                'success' => $n,
                'failed' => 0,
                'topic_ok' => true,
            ];
        }
    } else {
        // Secours : envoi individuel par compte
        $channel = 'tokens';
        foreach ($groups as $group) {
            $admin_id = (int) $group['admin_id'];
            $tokens = $group['tokens'];
            if (empty($tokens)) {
                continue;
            }
            $p = $payload;
            $p['tag'] = $base_tag . '-a' . $admin_id;
            $p['admin_id'] = (string) $admin_id;

            $result = firebase_send_notification($tokens, $title, $body, $p);
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
                'role' => $group['role'] ?? '',
                'tokens' => count($tokens),
                'success' => $ok,
                'failed' => $ko,
                'topic_ok' => false,
            ];
        }
    }

    $out = [
        'success' => $total_success,
        'failed' => $total_failed,
        'admins_notified' => $admins_notified,
        'admins_total' => count($groups),
        'errors' => $errors,
        'details' => $details,
        'topic_success' => !empty($topic_res['success']),
        'topic_synced' => (int) ($sync['subscribed'] ?? 0),
        'channel' => $channel,
    ];
    _firebase_log_send('all_admins', $out);
    return $out;
}

/**
 * Envoi de test / alerte à UN seul compte admin (ses tokens uniquement)
 */
function firebase_send_notification_to_admin_id($admin_id, $title, $body, $data = []) {
    if (!function_exists('get_fcm_tokens_by_admin')) {
        require_once __DIR__ . '/../models/model_fcm.php';
    }
    $admin_id = (int) $admin_id;
    $tokens = get_fcm_tokens_by_admin($admin_id);
    if (empty($tokens)) {
        return ['success' => 0, 'failed' => 0, 'errors' => ['Aucun token pour ce compte'], 'admins_notified' => 0];
    }
    // S'assurer que ces tokens sont bien sur le topic
    firebase_fcm_subscribe_admin_topic($tokens);
    $payload = is_array($data) ? $data : [];
    $payload['tag'] = ($payload['tag'] ?? 'admin-one') . '-a' . $admin_id;
    $payload['admin_id'] = (string) $admin_id;
    $result = firebase_send_notification($tokens, $title, $body, $payload);
    $result['admins_notified'] = (($result['success'] ?? 0) > 0) ? 1 : 0;
    return $result;
}
