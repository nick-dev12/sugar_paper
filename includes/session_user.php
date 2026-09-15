<?php
/**
 * Configuration de session persistante pour les utilisateurs connectés
 * Durée : 30 jours (cookie + données serveur)
 * À inclure AVANT session_start() sur les pages utilisateur
 */

if (!function_exists('session_persistent_lifetime')) {
    function session_persistent_lifetime(): int
    {
        return 30 * 24 * 3600;
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

        if ($has_user || $has_admin) {
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

if (!function_exists('request_is_native_app')) {
    function request_is_native_app(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return stripos($ua, 'SugarPaperApp') !== false;
    }
}

if (!function_exists('request_should_skip_native_home_redirect')) {
    function request_should_skip_native_home_redirect(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return true;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script);
        if (strpos($script, '/api/') !== false || strpos($base, 'ajax_') === 0) {
            return true;
        }

        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($xrw === 'xmlhttprequest') {
            return true;
        }

        return false;
    }
}

if (!function_exists('native_app_redirect_guest_to_home_if_needed')) {
    /**
     * App native : si la session/cookie est perdue sur une page protégée,
     * renvoyer à l'accueil au lieu d'un login/dashboard qui laisse un écran vide.
     */
    function native_app_redirect_guest_to_home_if_needed(): void
    {
        if (!request_is_native_app() || request_should_skip_native_home_redirect()) {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script);

        $admin_public = ['login.php', 'logout.php', 'mot-de-passe-oublie.php'];
        if (preg_match('#/admin/#', $script)) {
            if (in_array($base, $admin_public, true)) {
                return;
            }
            $has_admin = !empty($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0
                && !empty($_SESSION['admin_email']);
            if (!$has_admin) {
                header('Location: /index.php');
                exit;
            }
            return;
        }

        $user_public = ['connexion.php', 'inscription.php', 'deconnexion.php'];
        if (preg_match('#/user/#', $script)) {
            if (in_array($base, $user_public, true)) {
                return;
            }
            $has_user = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
            if (!$has_user) {
                header('Location: /index.php');
                exit;
            }
        }
    }
}

if (!function_exists('session_start_persistent')) {
    function session_start_persistent(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_touch_persistent_if_authenticated();
            native_app_redirect_guest_to_home_if_needed();
            return true;
        }

        session_configure_persistent();
        $started = session_start();
        if ($started) {
            session_touch_persistent_if_authenticated();
            native_app_redirect_guest_to_home_if_needed();
        }

        return $started;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_configure_persistent();
}
