<?php
if (defined('PLATFORM_SHARE_FOOTER_LOADED')) {
    return;
}
define('PLATFORM_SHARE_FOOTER_LOADED', true);

if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}

include __DIR__ . '/partials/platform_share_modal.php';
?>
<script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
