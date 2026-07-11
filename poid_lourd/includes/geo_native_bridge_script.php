<?php
/**
 * Script pont géolocalisation native (app Flutter COLObanes).
 */
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}
?>
<script src="/js/geo-native-bridge.js<?php echo asset_version_query(); ?>"></script>
