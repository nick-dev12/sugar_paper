<?php
/**
 * Scripts tiers en chargement différé (Jotform, GTranslate) — inclus une fois via footer.php
 */
if (defined('COLOBANES_PERF_LAZY_LOADED')) {
    return;
}
define('COLOBANES_PERF_LAZY_LOADED', true);

if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}

$lazy_jotform = '';
$assistance_path = __DIR__ . '/../config/assistance.php';
if (file_exists($assistance_path)) {
    $assistance_cfg = require $assistance_path;
    if (is_array($assistance_cfg)) {
        $lazy_jotform = trim((string) ($assistance_cfg['jotform_embed_js'] ?? ''));
    }
}

$lazy_gtranslate = 'https://cdn.gtranslate.net/widgets/latest/dropdown.js';
?>
<script>
window.__COLOBANES_LAZY = {
    jotform: <?php echo json_encode($lazy_jotform, JSON_UNESCAPED_SLASHES); ?>,
    gtranslate: <?php echo json_encode($lazy_gtranslate, JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="/js/deferred-third-party.js<?php echo asset_version_query(); ?>" defer></script>
