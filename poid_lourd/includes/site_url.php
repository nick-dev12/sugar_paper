<?php
/**
 * Retourne l'URL de base du site pour les liens (emails, notifications, etc.)
 * Priorité : config/site.php > config/email.php > déduction depuis $_SERVER
 * 
 * @return string URL sans slash final (ex: https://colobanes.com)
 */
function normalize_colobanes_site_base_url($url) {
    $url = rtrim(trim((string) $url), '/');
    if ($url === '') {
        return '';
    }
    return preg_replace('#^https?://www\.colobanes\.com#i', 'https://colobanes.com', $url);
}

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
        return normalize_colobanes_site_base_url($site_url);
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strcasecmp($host, 'www.colobanes.com') === 0) {
        $host = 'colobanes.com';
    }
    return normalize_colobanes_site_base_url($protocol . '://' . $host);
}
