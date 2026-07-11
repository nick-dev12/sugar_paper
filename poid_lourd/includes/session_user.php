<?php
/**
 * Sessions persistantes (client + admin) — 30 jours.
 * Inclure AVANT session_start(), ou utiliser session_start_persistent().
 */

if (!function_exists('session_persistent_lifetime')) {
    function session_persistent_lifetime(): int
    {
        // 10 ans — renouvelé à chaque visite connectée (déconnexion = bouton uniquement en usage normal).
        return 10 * 365 * 24 * 3600;
    }
}

if (!function_exists('session_request_is_https')) {
    function session_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }

        return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
    }
}

if (!function_exists('session_configure_persistent')) {
    /**
     * À appeler avant le premier session_start() de la requête.
     */
    function session_configure_persistent(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $lifetime = session_persistent_lifetime();

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => session_request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }
}

if (!function_exists('session_refresh_persistent_cookie')) {
    /**
     * Prolonge le cookie de session (évite déconnexion à la fermeture du navigateur / WebView).
     */
    function session_refresh_persistent_cookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = session_persistent_lifetime();
        $params = session_get_cookie_params();
        $expires = time() + $lifetime;

        setcookie(session_name(), session_id(), [
            'expires' => $expires,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? session_request_is_https()),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}

if (!function_exists('session_touch_persistent_if_authenticated')) {
    function session_touch_persistent_if_authenticated(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $has_user = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
        $has_admin = !empty($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
        $has_super = !empty($_SESSION['super_admin_id']) && (int) $_SESSION['super_admin_id'] > 0;

        if ($has_user || $has_admin || $has_super) {
            session_refresh_persistent_cookie();
        }
    }
}

if (!function_exists('session_regenerate_persistent')) {
    function session_regenerate_persistent(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_regenerate_id(true);
        session_refresh_persistent_cookie();
    }
}

if (!function_exists('session_start_persistent')) {
    function session_start_persistent(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_touch_persistent_if_authenticated();
            return true;
        }

        session_configure_persistent();
        $started = session_start();
        if ($started) {
            session_touch_persistent_if_authenticated();
        }

        return $started;
    }
}
