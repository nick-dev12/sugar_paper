<?php
/**
 * Meta tags et liens pour l'installation PWA (Progressive Web App)
 * À inclure dans le <head> des pages client
 */
?>
<meta name="theme-color" content="#E5488A">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Sugar Paper">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<script>
(function() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function() {});
        });
    }
})();
</script>
