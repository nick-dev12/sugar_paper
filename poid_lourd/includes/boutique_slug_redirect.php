<?php
/**
 * Anciens slugs boutique → redirection vers le slug actuel du vendeur.
 */

if (!function_exists('boutique_slug_redirects_table_exists')) {
    function boutique_slug_redirects_table_exists()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        global $db;
        $cached = false;
        if (!$db) {
            return false;
        }
        try {
            $st = $db->query("
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boutique_slug_redirects'
            ");
            $cached = ((int) $st->fetchColumn()) > 0;
        } catch (PDOException $e) {
            $cached = false;
        }
        return $cached;
    }
}

if (!function_exists('boutique_slug_redirect_save')) {
    /**
     * Enregistre un ancien slug pour redirection (renommage boutique).
     */
    function boutique_slug_redirect_save($old_slug, $admin_id)
    {
        global $db;

        $old_slug = trim((string) $old_slug, '/');
        $admin_id = (int) $admin_id;
        if ($old_slug === '' || $admin_id <= 0 || !boutique_slug_redirects_table_exists()) {
            return false;
        }

        if (!function_exists('marketplace_slugify')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }
        $old_slug = marketplace_slugify($old_slug);
        if ($old_slug === '' || $old_slug === 'boutique') {
            return false;
        }

        try {
            $st = $db->prepare('
                INSERT INTO boutique_slug_redirects (old_slug, admin_id, date_creation)
                VALUES (:old_slug, :admin_id, NOW())
                ON DUPLICATE KEY UPDATE admin_id = :admin_id2, date_creation = NOW()
            ');
            return $st->execute([
                'old_slug' => $old_slug,
                'admin_id' => $admin_id,
                'admin_id2' => $admin_id,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('get_admin_by_old_boutique_slug')) {
    /**
     * Résout un vendeur via un ancien slug enregistré.
     *
     * @return array<string, mixed>|false
     */
    function get_admin_by_old_boutique_slug($slug)
    {
        global $db;

        $slug = trim((string) $slug, '/');
        if ($slug === '' || !boutique_slug_redirects_table_exists()) {
            return false;
        }

        if (!function_exists('marketplace_slugify')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }
        $slug = marketplace_slugify($slug);

        try {
            $st = $db->prepare('
                SELECT a.*
                FROM boutique_slug_redirects r
                INNER JOIN admin a ON a.id = r.admin_id
                WHERE r.old_slug = :slug
                LIMIT 1
            ');
            $st->execute(['slug' => $slug]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('boutique_slug_redirect_target_url')) {
    /**
     * URL de destination en conservant la page courante (query/path).
     */
    function boutique_slug_redirect_target_url($requested_slug, $new_slug)
    {
        if (!function_exists('boutique_url')) {
            require_once __DIR__ . '/marketplace_helpers.php';
        }

        $requested_slug = trim((string) $requested_slug, '/');
        $new_slug = trim((string) $new_slug, '/');
        if ($new_slug === '') {
            return boutique_url('index.php', $requested_slug);
        }

        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($request_uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($request_uri, PHP_URL_QUERY);
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }

        if (array_key_exists('boutique', $params)) {
            $params['boutique'] = $new_slug;
            $qs = http_build_query($params);
            return $path . ($qs !== '' ? '?' . $qs : '');
        }

        if ($requested_slug !== '' && preg_match('#^/' . preg_quote($requested_slug, '#') . '(/.*)?$#', $path, $m)) {
            $rest = $m[1] ?? '';
            return '/' . rawurlencode($new_slug) . $rest . (is_string($query) && $query !== '' ? '?' . $query : '');
        }

        if (strpos($path, '/boutique/') === 0) {
            $params['boutique'] = $new_slug;
            return $path . '?' . http_build_query($params);
        }

        return boutique_url('index.php', $new_slug);
    }
}

if (!function_exists('boutique_perform_slug_redirect')) {
    /**
     * Redirection HTTP 301 vers le slug actuel.
     */
    function boutique_perform_slug_redirect($requested_slug, $new_slug)
    {
        $target = boutique_slug_redirect_target_url($requested_slug, $new_slug);
        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }
        if ($target !== '' && $target[0] === '/') {
            $target = rtrim(get_site_base_url(), '/') . $target;
        }
        header('Location: ' . $target, true, 301);
        exit;
    }
}

if (!function_exists('resolve_vendeur_by_boutique_slug')) {
    /**
     * Slug actuel ou ancien — sans redirection HTTP (API, panier, etc.).
     *
     * @return array<string, mixed>|false
     */
    function resolve_vendeur_by_boutique_slug($slug)
    {
        $slug = trim((string) $slug, '/');
        if ($slug === '') {
            return false;
        }

        if (!function_exists('get_admin_by_boutique_slug')) {
            require_once dirname(__DIR__) . '/models/model_admin.php';
        }

        $row = get_admin_by_boutique_slug($slug);
        if ($row && ($row['statut'] ?? '') === 'actif' && ($row['role'] ?? '') === 'vendeur') {
            return $row;
        }

        $row = get_admin_by_old_boutique_slug($slug);
        if ($row && ($row['statut'] ?? '') === 'actif' && ($row['role'] ?? '') === 'vendeur') {
            return $row;
        }

        return false;
    }
}

if (!function_exists('boutique_resolve_vendeur_web')) {
    /**
     * Slug actuel ou ancien — redirection 301 si ancien slug (pages HTML).
     *
     * @return array<string, mixed>|false
     */
    function boutique_resolve_vendeur_web($slug)
    {
        $slug = trim((string) $slug, '/');
        if ($slug === '') {
            return false;
        }

        if (!function_exists('get_admin_by_boutique_slug')) {
            require_once dirname(__DIR__) . '/models/model_admin.php';
        }

        $row = get_admin_by_boutique_slug($slug);
        if ($row && ($row['statut'] ?? '') === 'actif' && ($row['role'] ?? '') === 'vendeur') {
            return $row;
        }

        $row = get_admin_by_old_boutique_slug($slug);
        if ($row && ($row['statut'] ?? '') === 'actif' && ($row['role'] ?? '') === 'vendeur') {
            $new_slug = trim((string) ($row['boutique_slug'] ?? ''));
            if ($new_slug !== '' && $new_slug !== $slug) {
                boutique_perform_slug_redirect($slug, $new_slug);
            }
            return $row;
        }

        return false;
    }
}
