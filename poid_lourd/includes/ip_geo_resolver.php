<?php
/**
 * Détection du pays visiteur à partir de l'adresse IP (sans duplication par page).
 */

require_once __DIR__ . '/marketplace_countries.php';

function ip_geo_client_address(): string
{
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $candidates[] = trim($part);
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = (string) $_SERVER['REMOTE_ADDR'];
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

function ip_geo_is_local_address(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '' || $ip === '::1') {
        return true;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function ip_geo_country_from_headers(): ?string
{
    $keys = [
        'HTTP_CF_IPCOUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'GEOIP_COUNTRY_CODE',
    ];
    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $code = strtoupper(trim((string) $_SERVER[$key]));
        if ($code === 'XX' || $code === 'T1') {
            continue;
        }
        if (marketplace_country_is_valid($code)) {
            return $code;
        }
    }
    return null;
}

function ip_geo_lookup_country_api(string $ip): ?string
{
    $ip = trim($ip);
    if ($ip === '' || ip_geo_is_local_address($ip)) {
        return null;
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return null;
    }
    $code = strtoupper(trim((string) ($data['countryCode'] ?? '')));
    return marketplace_country_is_valid($code) ? $code : null;
}

/**
 * Détecte le code pays ISO (parmi les pays marketplace) pour l'utilisateur courant.
 */
function ip_geo_detect_country_code(): string
{
    $from_header = ip_geo_country_from_headers();
    if ($from_header !== null) {
        return $from_header;
    }

    $ip = ip_geo_client_address();
    if (ip_geo_is_local_address($ip)) {
        return marketplace_country_default_code();
    }

    static $api_cache = [];
    if (isset($api_cache[$ip])) {
        return $api_cache[$ip];
    }

    $from_api = ip_geo_lookup_country_api($ip);
    $api_cache[$ip] = $from_api !== null ? $from_api : marketplace_country_default_code();
    return $api_cache[$ip];
}
