<?php
/**
 * Widget GTranslate (offre gratuite) — https://gtranslate.io
 * Traduction automatique de la page via le moteur Google Translate (cookie googtrans).
 * Langues : français (défaut), anglais, espagnol.
 */
if (!empty($GLOBALS['_gtranslate_nav_widget_loaded'])) {
    return;
}
$GLOBALS['_gtranslate_nav_widget_loaded'] = true;
?>
<div class="nav-gtranslate-wrapper notranslate" aria-label="Choisir la langue du site"></div>
<script>
window.gtranslateSettings = {
    default_language: 'fr',
    languages: ['fr', 'en', 'es'],
    wrapper_selector: '.nav-gtranslate-wrapper',
    switcher_horizontal_position: 'inline',
    float_switcher_open_direction: 'bottom',
    flag_style: '3d',
    native_language_names: true,
    url_structure: 'none'
};
</script>
<script src="https://cdn.gtranslate.net/widgets/latest/float.js" defer></script>
