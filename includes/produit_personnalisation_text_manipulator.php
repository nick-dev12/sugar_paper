<?php
/**
 * Manipulateur texte pour aperçu modal personnalisation
 * @var string $perso_text_prefix
 */
$perso_text_prefix = isset($perso_text_prefix) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $perso_text_prefix) : 'perso';
?>
<div class="perso-text-manipulator" id="<?php echo $perso_text_prefix; ?>-text-manipulator" hidden aria-hidden="true">
    <div class="perso-text-manip-box" id="<?php echo $perso_text_prefix; ?>-text-manip-box">
        <button type="button" class="perso-manip-delete" id="<?php echo $perso_text_prefix; ?>-text-delete" aria-label="Supprimer ce texte">&times;</button>
        <span class="perso-text-handle perso-text-handle--nw" data-handle="nw" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--ne" data-handle="ne" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--sw" data-handle="sw" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--se" data-handle="se" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--n" data-handle="n" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--s" data-handle="s" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--e" data-handle="e" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--w" data-handle="w" aria-hidden="true"></span>
        <span class="perso-text-handle perso-text-handle--rotate" data-handle="rotate" aria-label="Pivoter le texte"></span>
    </div>
</div>
