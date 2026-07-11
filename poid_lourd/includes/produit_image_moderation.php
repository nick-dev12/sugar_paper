<?php
/**
 * Modération automatique et manuelle des images produits (contenu sensible).
 */

require_once __DIR__ . '/image_optimizer.php';

/**
 * @return array<string, mixed>
 */
function produit_image_moderation_config()
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = __DIR__ . '/../config/image_moderation.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/../config/image_moderation.example.php';
    }
    $loaded = is_file($path) ? require $path : [];
    $cfg = is_array($loaded) ? $loaded : [];
    return $cfg;
}

function produit_image_moderation_last_error()
{
    return (string) ($GLOBALS['produit_image_moderation_last_error'] ?? '');
}

function produit_image_moderation_set_last_error($message)
{
    $GLOBALS['produit_image_moderation_last_error'] = (string) $message;
}

function produit_image_moderation_should_scan_role($role)
{
    $cfg = produit_image_moderation_config();
    if (empty($cfg['enabled'])) {
        return false;
    }
    $roles = isset($cfg['roles']) && is_array($cfg['roles']) ? $cfg['roles'] : ['vendeur'];
    return in_array((string) $role, $roles, true);
}

/**
 * @return array{action:string, reason:string, provider:string, scores:array, hash:string, needs_hold:bool}
 */
function produit_image_moderation_scan_path($absolute_path)
{
    $absolute_path = (string) $absolute_path;
    $default = [
        'action' => 'allow',
        'reason' => '',
        'provider' => 'none',
        'scores' => [],
        'hash' => is_file($absolute_path) ? hash_file('sha256', $absolute_path) : '',
        'needs_hold' => false,
    ];
    if (!is_file($absolute_path)) {
        $default['action'] = 'reject';
        $default['reason'] = 'Fichier image introuvable.';
        return $default;
    }

    require_once __DIR__ . '/../models/model_produit_image_moderation.php';
    if ($default['hash'] !== '' && produit_image_moderation_hash_is_blocked($default['hash'])) {
        $default['action'] = 'reject';
        $default['reason'] = 'Cette image a déjà été refusée pour non-conformité.';
        $default['provider'] = 'blocklist';
        return $default;
    }

    $heuristic = produit_image_moderation_heuristic_check($absolute_path);
    if ($heuristic['action'] === 'reject') {
        return array_merge($default, $heuristic);
    }

    $cfg = produit_image_moderation_config();
    $provider = isset($cfg['provider']) ? trim((string) $cfg['provider']) : 'none';

    if ($provider === 'sightengine') {
        $api = produit_image_moderation_sightengine_scan($absolute_path, $cfg);
        if ($api !== null) {
            return array_merge($default, $api);
        }
    } elseif ($provider === 'google_vision') {
        $api = produit_image_moderation_google_vision_scan($absolute_path, $cfg);
        if ($api !== null) {
            return array_merge($default, $api);
        }
    }

    return $default;
}

/**
 * Vérifications techniques (fichier valide, pas de polyglot évident).
 *
 * @return array{action:string, reason:string, provider:string, scores:array}
 */
function produit_image_moderation_heuristic_check($absolute_path)
{
    $out = ['action' => 'allow', 'reason' => '', 'provider' => 'heuristic', 'scores' => []];
    $info = @getimagesize($absolute_path);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        $out['action'] = 'reject';
        $out['reason'] = 'Le fichier n\'est pas une image valide.';
        return $out;
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w < 80 || $h < 80) {
        $out['action'] = 'reject';
        $out['reason'] = 'Image trop petite (minimum 80×80 px).';
        return $out;
    }
    if ($w > 12000 || $h > 12000) {
        $out['action'] = 'reject';
        $out['reason'] = 'Dimensions d\'image excessives.';
        return $out;
    }
    $mime = image_optimizer_detect_mime($absolute_path);
    if ($mime !== '' && !in_array($mime, image_optimizer_allowed_mimes(), true)) {
        $out['action'] = 'reject';
        $out['reason'] = 'Format d\'image non autorisé.';
        return $out;
    }
    return $out;
}

/**
 * @return array{action:string, reason:string, provider:string, scores:array, needs_hold:bool}|null
 */
function produit_image_moderation_sightengine_scan($absolute_path, array $cfg)
{
    $se = isset($cfg['sightengine']) && is_array($cfg['sightengine']) ? $cfg['sightengine'] : [];
    $user = trim((string) ($se['api_user'] ?? ''));
    $secret = trim((string) ($se['api_secret'] ?? ''));
    if ($user === '' || $secret === '') {
        return null;
    }
    if (!function_exists('curl_init')) {
        return null;
    }
    $models = trim((string) ($se['models'] ?? 'nudity-2.0,offensive,gore-2.0'));
    $threshold = (float) ($cfg['reject_score'] ?? 0.55);
    $threshold = max(0.1, min(0.95, $threshold));

    $ch = curl_init('https://api.sightengine.com/1.0/check.json');
    $post = [
        'media' => new CURLFile($absolute_path),
        'models' => $models,
        'api_user' => $user,
        'api_secret' => $secret,
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http < 200 || $http >= 300) {
        return [
            'action' => 'review',
            'reason' => 'Analyse automatique indisponible — validation manuelle requise.',
            'provider' => 'sightengine',
            'scores' => ['http' => $http],
            'needs_hold' => true,
        ];
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        return null;
    }

    $scores = [];
    $max_score = 0.0;
    $labels = [];
    if (isset($json['nudity']) && is_array($json['nudity'])) {
        foreach ($json['nudity'] as $k => $v) {
            if (!is_numeric($v)) {
                continue;
            }
            $fv = (float) $v;
            $scores['nudity.' . $k] = $fv;
            if ($fv > $max_score) {
                $max_score = $fv;
                $labels[] = 'nudité';
            }
        }
    }
    foreach (['offensive', 'gore', 'weapon', 'recreational_drug'] as $bucket) {
        if (!isset($json[$bucket])) {
            continue;
        }
        if (is_array($json[$bucket])) {
            foreach ($json[$bucket] as $k => $v) {
                if (!is_numeric($v)) {
                    continue;
                }
                $fv = (float) $v;
                $scores[$bucket . '.' . $k] = $fv;
                if ($fv > $max_score) {
                    $max_score = $fv;
                    $labels[] = $bucket;
                }
            }
        } elseif (is_numeric($json[$bucket])) {
            $fv = (float) $json[$bucket];
            $scores[$bucket] = $fv;
            if ($fv > $max_score) {
                $max_score = $fv;
                $labels[] = $bucket;
            }
        }
    }

    if ($max_score >= $threshold) {
        return [
            'action' => 'reject',
            'reason' => 'Image refusée : contenu potentiellement inapproprié détecté.',
            'provider' => 'sightengine',
            'scores' => $scores,
            'needs_hold' => false,
        ];
    }
    if ($max_score >= ($threshold * 0.65)) {
        return [
            'action' => 'review',
            'reason' => 'Image signalée pour vérification manuelle.',
            'provider' => 'sightengine',
            'scores' => $scores,
            'needs_hold' => true,
        ];
    }

    $policy = (string) ($cfg['policy'] ?? 'strict');
    if (!empty($cfg['hold_product_until_approved']) && ($policy === 'hold' || !empty($cfg['hold_product_until_approved']))) {
        return [
            'action' => 'review',
            'reason' => 'Image conforme au scan automatique — validation plateforme en cours.',
            'provider' => 'sightengine',
            'scores' => $scores,
            'needs_hold' => true,
        ];
    }

    return [
        'action' => 'allow',
        'reason' => '',
        'provider' => 'sightengine',
        'scores' => $scores,
        'needs_hold' => false,
    ];
}

/**
 * @return array{action:string, reason:string, provider:string, scores:array, needs_hold:bool}|null
 */
function produit_image_moderation_google_vision_scan($absolute_path, array $cfg)
{
    $gv = isset($cfg['google_vision']) && is_array($cfg['google_vision']) ? $cfg['google_vision'] : [];
    $api_key = trim((string) ($gv['api_key'] ?? ''));
    if ($api_key === '') {
        return null;
    }
    $blob = @file_get_contents($absolute_path);
    if ($blob === false || $blob === '') {
        return null;
    }
    $payload = json_encode([
        'requests' => [[
            'image' => ['content' => base64_encode($blob)],
            'features' => [['type' => 'SAFE_SEARCH_DETECTION']],
        ]],
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return null;
    }
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . rawurlencode($api_key);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $http < 200 || $http >= 300) {
        return [
            'action' => 'review',
            'reason' => 'Analyse Google Vision indisponible — validation manuelle requise.',
            'provider' => 'google_vision',
            'scores' => ['http' => $http],
            'needs_hold' => true,
        ];
    }
    $json = json_decode((string) $body, true);
    $safe = $json['responses'][0]['safeSearchAnnotation'] ?? null;
    if (!is_array($safe)) {
        return null;
    }
    $scores = [];
    $bad = ['LIKELY', 'VERY_LIKELY'];
    $watch = ['adult', 'violence', 'racy', 'medical'];
    foreach ($watch as $key) {
        $val = strtoupper((string) ($safe[$key] ?? 'UNKNOWN'));
        $scores[$key] = $val;
        if (in_array($val, $bad, true)) {
            return [
                'action' => 'reject',
                'reason' => 'Image refusée : contenu potentiellement inapproprié (Safe Search).',
                'provider' => 'google_vision',
                'scores' => $scores,
                'needs_hold' => false,
            ];
        }
        if ($val === 'POSSIBLE') {
            return [
                'action' => 'review',
                'reason' => 'Image à vérifier manuellement (doute Safe Search).',
                'provider' => 'google_vision',
                'scores' => $scores,
                'needs_hold' => true,
            ];
        }
    }
    if (!empty($cfg['hold_product_until_approved'])) {
        return [
            'action' => 'review',
            'reason' => 'Image analysée — validation plateforme en cours.',
            'provider' => 'google_vision',
            'scores' => $scores,
            'needs_hold' => true,
        ];
    }
    return [
        'action' => 'allow',
        'reason' => '',
        'provider' => 'google_vision',
        'scores' => $scores,
        'needs_hold' => false,
    ];
}

/**
 * Scan après upload ; supprime le fichier si rejeté.
 *
 * @return array{ok:bool, needs_hold:bool, scan:array}
 */
function produit_image_moderation_after_upload($relative_path, $role, $admin_id = 0)
{
    produit_image_moderation_set_last_error('');
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return ['ok' => false, 'needs_hold' => false, 'scan' => []];
    }
    if (!produit_image_moderation_should_scan_role($role)) {
        return ['ok' => true, 'needs_hold' => false, 'scan' => ['action' => 'allow']];
    }

    $abs = dirname(__DIR__) . '/upload/' . $relative_path;
    $scan = produit_image_moderation_scan_path($abs);

    require_once __DIR__ . '/../models/model_produit_image_moderation.php';
    if (($scan['action'] ?? '') === 'review' && empty(produit_image_moderation_config()['hold_product_until_approved'])) {
        $scan['action'] = 'allow';
        $scan['needs_hold'] = false;
        $scan['reason'] = '';
    }
    produit_image_moderation_log_entry((int) $admin_id, $relative_path, $scan);

    if (($scan['action'] ?? '') === 'reject') {
        image_optimizer_delete_with_variants($relative_path);
        $msg = (string) ($scan['reason'] ?? 'Image refusée pour non-conformité.');
        produit_image_moderation_set_last_error($msg);
        return ['ok' => false, 'needs_hold' => false, 'scan' => $scan];
    }

    return [
        'ok' => true,
        'needs_hold' => !empty($scan['needs_hold']) || ($scan['action'] ?? '') === 'review',
        'scan' => $scan,
    ];
}

/**
 * Applique la politique de publication vendeur après enregistrement produit.
 */
function produit_image_moderation_finalize_vendor_product($produit_id, $admin_id, array $image_paths, $needs_hold, $requested_statut)
{
    require_once __DIR__ . '/../models/model_produit_image_moderation.php';
    produit_image_moderation_attach_produit((int) $produit_id, (int) $admin_id, $image_paths);

    $cfg = produit_image_moderation_config();
    $must_hold = $needs_hold || !empty($cfg['hold_product_until_approved']);
    if (!$must_hold || (int) $produit_id <= 0) {
        return ['held' => false, 'message' => ''];
    }

    global $db;
    if (!isset($db) || !($db instanceof PDO)) {
        require_once __DIR__ . '/../conn/conn.php';
    }
    try {
        $stmt = $db->prepare('UPDATE produits SET statut = \'inactif\', date_modification = NOW() WHERE id = :id');
        $stmt->execute(['id' => (int) $produit_id]);
    } catch (PDOException $e) {
        return ['held' => false, 'message' => ''];
    }

    return [
        'held' => true,
        'message' => ' Produit enregistré en mode masqué : les images seront vérifiées par la plateforme avant publication.',
    ];
}
