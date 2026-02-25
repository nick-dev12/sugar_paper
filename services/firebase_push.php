<?php
/**
 * Service d'envoi de notifications push via Firebase Cloud Messaging (FCM) HTTP v1
 * Programmation procédurale uniquement - Sans dépendances externes
 */

/**
 * Encode en base64 URL-safe
 */
function _fcm_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Obtient un token d'accès OAuth2 à partir du compte de service Firebase
 * @param string $credentials_path Chemin vers le fichier JSON du compte de service
 * @return string|null Token d'accès ou null en cas d'échec
 */
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

/**
 * Envoie une notification push FCM à un ou plusieurs tokens
 * @param array $tokens Liste des tokens FCM
 * @param string $title Titre de la notification
 * @param string $body Corps du message
 * @param array $data Données additionnelles (optionnel)
 * @return array ['success' => int, 'failed' => int, 'errors' => array]
 */
function firebase_send_notification($tokens, $title, $body, $data = []) {
    $credentials_path = __DIR__ . '/../sugar-paper-d34851eeca5a.json';
    $project_id = 'sugar-paper';
    
    $access_token = firebase_get_access_token($credentials_path);
    if (!$access_token) {
        return ['success' => 0, 'failed' => count($tokens), 'errors' => ['Impossible d\'obtenir le token d\'accès']];
    }
    
    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $success = 0;
    $errors = [];
    
    foreach ($tokens as $token) {
        $dataPayload = [];
        foreach (array_merge($data, ['title' => $title, 'body' => $body]) as $k => $v) {
            $dataPayload[$k] = (string) $v;
        }
        
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $dataPayload,
                'webpush' => [
                    'fcm_options' => [
                        'link' => isset($data['link']) ? (string) $data['link'] : '/'
                    ]
                ]
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
