<?php
/**
 * Retourne l'URL de base du site pour les liens (emails, notifications, etc.)
 * Priorité : config/site.php > config/email.php > déduction depuis $_SERVER
 * 
 * @return string URL sans slash final (ex: https://sugar-paper.com)
 */
function get_site_base_url() {
    $site_url = '';

    if (file_exists(__DIR__ . '/../config/site.php')) {
        $config = require __DIR__ . '/../config/site.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (empty($site_url) && file_exists(__DIR__ . '/../config/email.php')) {
        $config = require __DIR__ . '/../config/email.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (!empty($site_url)) {
        return rtrim($site_url, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Segment d'URL entre l'hôte et /admin/ (ex. '' en racine, '/site_gateau' en sous-dossier WAMP).
 */
function get_public_root_uri_path() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && preg_match('#^(.+?)/admin/#', $script, $m)) {
        $cached = $m[1];
        return $cached;
    }
    $cached = '';
    return $cached;
}

/**
 * URL de base pour la requête HTTP en cours : schéma + hôte + racine publique.
 */
function get_request_origin_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = get_public_root_uri_path();
    return rtrim($protocol . '://' . $host . $root, '/');
}

/**
 * Chemin fichier du logo vitrine présent sous /image/ ou à la racine.
 *
 * @return string Nom de fichier relatif (ex. logo.svg ou logo.png), ou chaîne vide
 */
function get_site_logo_relative_filename() {
    $image_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR;
    $root_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $candidates_image = ['sugar_paper.jpg', 'logo.png', 'logo.jpg', 'logo-fpl.png', 'logo fpl_stock.png'];
    foreach ($candidates_image as $name) {
        if (is_file($image_dir . $name)) {
            return $name;
        }
    }
    if (is_file($root_dir . 'logo.svg')) {
        return '../logo.svg';
    }
    if (is_file($image_dir . 'logo.svg')) {
        return 'logo.svg';
    }
    return '';
}

/**
 * Segment d'URI du logo sous la racine web (ex. /image/logo.png).
 */
function get_site_logo_uri_path_relative_to_webroot() {
    $name = get_site_logo_relative_filename();
    if ($name === '') {
        return '';
    }
    if (strpos($name, '../') === 0) {
        return '/' . ltrim(substr($name, 3), '/');
    }
    $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $name))));
    return '/image/' . $encoded;
}

/**
 * URL absolue du logo pour les balises img (admin local / sous-dossier WAMP OK).
 */
function get_site_logo_url_for_current_request() {
    $name = get_site_logo_relative_filename();
    if ($name === '') {
        return '';
    }
    if (strpos($name, '../') === 0) {
        $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', ltrim($name, './')))));
        return rtrim(get_request_origin_base_url(), '/') . '/' . $encoded;
    }
    $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $name))));
    return rtrim(get_request_origin_base_url(), '/') . '/image/' . $encoded;
}
