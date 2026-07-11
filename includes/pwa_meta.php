<?php
/**
 * Meta tags PWA (sans enregistrement de Service Worker).
 *
 * IMPORTANT:
 * Le SW PWA (/sw.js) est volontairement désactivé globalement pour éviter
 * tout conflit avec Firebase Messaging (/firebase-messaging-sw.js).
 */
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
$asset_version = get_asset_version();
?>
<?php include __DIR__ . '/favicon.php'; ?>
<meta name="theme-color" content="#E5488A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Sugar Paper">
<meta name="application-name" content="Sugar Paper">
<link rel="manifest" href="/manifest.json">
