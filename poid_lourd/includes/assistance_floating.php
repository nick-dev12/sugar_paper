<?php
/**
 * Assistant plateforme (Jotform Agent) — widget injecté par le script CDN (aucun bouton HTML personnalisé).
 */
if (defined('ASSISTANCE_FLOATING_LOADED')) {
    return;
}
define('ASSISTANCE_FLOATING_LOADED', true);

$assistance_config = file_exists(__DIR__ . '/../config/assistance.php')
    ? require __DIR__ . '/../config/assistance.php'
    : [];
$assistance_config = is_array($assistance_config) ? $assistance_config : [];

$jotform_embed = isset($assistance_config['jotform_embed_js']) ? trim((string) $assistance_config['jotform_embed_js']) : '';

if ($jotform_embed === '') {
    return;
}

require_once __DIR__ . '/asset_version.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(
    '/css/jotform-agent-stack.css' . asset_version_query(),
    ENT_QUOTES,
    'UTF-8'
); ?>">
<?php /* Script Jotform chargé en différé via includes/perf_lazy_assets.php (footer) */ ?>
