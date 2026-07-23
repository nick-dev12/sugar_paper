<?php
/**
 * Bouton flottant « Retour » — haut à droite, history.back()
 * Programmation procédurale uniquement.
 */

if (empty($skip_jotform_ai_assistant) && !defined('JOTFORM_AI_ASSISTANT_INCLUDED')) {
    include __DIR__ . '/jotform_ai_assistant.php';
}

if (defined('FLOATING_BACK_BUTTON_INCLUDED')) {
    return;
}

if (!empty($skip_floating_back_button)) {
    return;
}

define('FLOATING_BACK_BUTTON_INCLUDED', true);

if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}

if (!function_exists('get_public_root_uri_path')) {
    require_once __DIR__ . '/site_url.php';
}

$floating_back_home = rtrim(get_public_root_uri_path(), '/') . '/index.php';
?>
<link rel="stylesheet" href="/css/floating-back-button.css<?php echo asset_version_query(); ?>">
<button type="button"
    class="floating-back-btn"
    id="floatingBackBtn"
    aria-label="Revenir en arrière"
    title="Revenir en arrière"
    data-fallback-home="<?php echo htmlspecialchars($floating_back_home, ENT_QUOTES, 'UTF-8'); ?>">
    <i class="fas fa-arrow-left" aria-hidden="true"></i>
</button>
<script src="/js/floating-back-button.js<?php echo asset_version_query(); ?>"></script>
