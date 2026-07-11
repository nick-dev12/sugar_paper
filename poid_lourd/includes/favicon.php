<?php
/**
 * Favicon / icônes pour Google, navigateurs et PWA.
 * Inclure une seule fois dans le <head> (via pwa_meta.php).
 */
if (defined('FAVICON_META_INCLUDED')) {
    return;
}
define('FAVICON_META_INCLUDED', true);

if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}
$favicon_base = rtrim(get_site_base_url(), '/');
?>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="/icons/favicon-48.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="shortcut icon" type="image/png" href="/icons/favicon-48.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<?php
require_once __DIR__ . '/skeleton_shimmer.php';
skeleton_shimmer_include_head();
?>
