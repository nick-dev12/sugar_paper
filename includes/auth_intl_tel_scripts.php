<?php
/**
 * Scripts intl-tel-input + init partagé.
 */
if (defined('AUTH_INTL_TEL_SCRIPTS_LOADED')) {
    return;
}
define('AUTH_INTL_TEL_SCRIPTS_LOADED', true);

require_once __DIR__ . '/asset_version.php';
$intl_base = 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.11/build';
?>
<script src="<?php echo htmlspecialchars($intl_base, ENT_QUOTES, 'UTF-8'); ?>/js/intlTelInputWithUtils.min.js" crossorigin="anonymous" defer></script>
<script src="/js/auth-intl-tel.js<?php echo asset_version_query(); ?>" defer></script>
