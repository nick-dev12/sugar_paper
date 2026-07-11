<?php
/**
 * Contexte boutique : slug → vendeur (admin role vendeur, actif)
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/marketplace_helpers.php';
require_once __DIR__ . '/boutique_vendeur_display.php';
require_once __DIR__ . '/boutique_slug_redirect.php';
require_once __DIR__ . '/../models/model_admin.php';

/**
 * Résout le slug depuis GET, POST ou REQUEST_URI
 */
function boutique_resolve_slug_from_request() {
    if (!empty($_GET['boutique'])) {
        return trim((string) $_GET['boutique'], '/');
    }
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if ($path !== '' && preg_match('#^/boutique/([a-z0-9-]+)#', $path, $mm)) {
        return trim($mm[1], '/');
    }
    if ($path !== '' && preg_match('#^/([a-z0-9-]+)(?:/|$)#', $path, $mm)) {
        $seg = $mm[1];
        if (function_exists('marketplace_is_reserved_public_slug') && marketplace_is_reserved_public_slug($seg)) {
            return '';
        }
        return $seg;
    }
    return '';
}

/**
 * Charge la boutique ou envoie 404
 * @return array Ligne admin (vendeur)
 */
function boutique_bootstrap_or_404() {
    global $db;
    if (!$db) {
        http_response_code(503);
        exit('Service indisponible');
    }
    $slug = boutique_resolve_slug_from_request();
    if ($slug === '') {
        http_response_code(404);
        exit('Boutique introuvable');
    }
    $row = boutique_resolve_vendeur_web($slug);
    if (!$row) {
        http_response_code(404);
        exit('Boutique introuvable');
    }
    if (!defined('BOUTIQUE_ADMIN_ID')) {
        define('BOUTIQUE_ADMIN_ID', (int) $row['id']);
    }
    if (!defined('BOUTIQUE_SLUG')) {
        define('BOUTIQUE_SLUG', (string) $row['boutique_slug']);
    }
    if (!defined('BOUTIQUE_NOM')) {
        define('BOUTIQUE_NOM', (string) ($row['boutique_nom'] ?: $row['boutique_slug']));
    }
    $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'] = boutique_vendeur_display_from_row($row);
    return $row;
}
