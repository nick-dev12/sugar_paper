<?php
if (defined('PLATFORM_SHARE_HEAD_LOADED')) {
    return;
}
define('PLATFORM_SHARE_HEAD_LOADED', true);

if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}
?>
<link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
