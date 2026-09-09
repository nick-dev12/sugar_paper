<?php
/**
 * Section texte réutilisable pour modals personnalisation
 * @var string $perso_text_prefix ex. perso, cupcakes, contours
 */
$perso_text_prefix = isset($perso_text_prefix) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $perso_text_prefix) : 'perso';
?>
<section class="perso-tool-section">
    <h3 class="perso-tool-title"><i class="fa-solid fa-font" aria-hidden="true"></i> Texte</h3>
    <div class="perso-text-list" id="<?php echo $perso_text_prefix; ?>-text-list" role="list" aria-label="Textes ajoutés"></div>
    <button type="button" class="perso-text-add-btn" id="<?php echo $perso_text_prefix; ?>-text-add"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un texte</button>
    <textarea class="perso-text-input" id="<?php echo $perso_text_prefix; ?>-text-input" maxlength="500" rows="3" placeholder="Votre texte ici… (Entrée = retour à la ligne)" autocomplete="off"></textarea>

    <div class="perso-text-controls">
        <div class="perso-dimension-field">
            <label for="<?php echo $perso_text_prefix; ?>-text-size"><span class="perso-dim-label-text">Taille du texte</span> <span id="<?php echo $perso_text_prefix; ?>-text-size-val">50</span>%</label>
            <input type="range" id="<?php echo $perso_text_prefix; ?>-text-size" min="20" max="100" value="50" step="1">
        </div>
        <div class="perso-dimension-field">
            <label for="<?php echo $perso_text_prefix; ?>-text-pos-x"><span class="perso-dim-label-text">Position horizontale</span> <span id="<?php echo $perso_text_prefix; ?>-text-pos-x-val">50</span>%</label>
            <input type="range" id="<?php echo $perso_text_prefix; ?>-text-pos-x" min="0" max="100" value="50" step="1">
        </div>
        <div class="perso-dimension-field">
            <label for="<?php echo $perso_text_prefix; ?>-text-pos-y"><span class="perso-dim-label-text">Position verticale</span> <span id="<?php echo $perso_text_prefix; ?>-text-pos-y-val">50</span>%</label>
            <input type="range" id="<?php echo $perso_text_prefix; ?>-text-pos-y" min="0" max="100" value="50" step="1">
        </div>
        <div class="perso-dimension-field" id="<?php echo $perso_text_prefix; ?>-text-rotation-wrap">
            <label for="<?php echo $perso_text_prefix; ?>-text-rotation"><span class="perso-dim-label-text">Orientation</span> <span id="<?php echo $perso_text_prefix; ?>-text-rotation-val">0</span>°</label>
            <input type="range" id="<?php echo $perso_text_prefix; ?>-text-rotation" min="-180" max="180" value="0" step="1">
        </div>
        <div class="perso-color-field">
            <span class="perso-color-label">Couleur du texte</span>
            <div class="perso-color-picker" role="group" aria-label="Couleur du texte">
                <button type="button" class="perso-color-swatch is-active" data-color="#E5488A" style="--swatch:#E5488A" title="Rose Sugar Paper"></button>
                <button type="button" class="perso-color-swatch" data-color="#ffffff" style="--swatch:#ffffff" title="Blanc"></button>
                <button type="button" class="perso-color-swatch" data-color="#000000" style="--swatch:#000000" title="Noir"></button>
                <button type="button" class="perso-color-swatch" data-color="#918a44" style="--swatch:#918a44" title="Doré"></button>
                <button type="button" class="perso-color-swatch" data-color="#c26638" style="--swatch:#c26638" title="Terre"></button>
                <button type="button" class="perso-color-swatch" data-color="#6b2f20" style="--swatch:#6b2f20" title="Marron"></button>
                <label class="perso-color-custom" title="Couleur personnalisée">
                    <input type="color" id="<?php echo $perso_text_prefix; ?>-text-color" value="#E5488A">
                    <span><i class="fa-solid fa-eyedropper" aria-hidden="true"></i></span>
                </label>
            </div>
        </div>
        <div class="perso-wrap-toggle">
            <input type="checkbox" id="<?php echo $perso_text_prefix; ?>-text-wrap-circle" class="perso-wrap-checkbox">
            <button type="button" class="perso-wrap-btn" id="<?php echo $perso_text_prefix; ?>-wrap-circle-btn" aria-pressed="false">
                <span class="perso-checkbox-box" aria-hidden="true"></span>
                <span class="perso-checkbox-text">Épouser la forme du cercle</span>
            </button>
        </div>
        <div class="perso-wrap-position" id="<?php echo $perso_text_prefix; ?>-wrap-position" role="group" aria-label="Position du texte sur le cercle">
            <span class="perso-wrap-position-label">Position sur le cercle</span>
            <div class="perso-wrap-position-btns">
                <button type="button" class="perso-wrap-pos-btn is-active" data-wrap-pos="top" aria-pressed="true">
                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> En haut
                </button>
                <button type="button" class="perso-wrap-pos-btn" data-wrap-pos="bottom" aria-pressed="false">
                    <i class="fa-solid fa-arrow-down" aria-hidden="true"></i> En bas
                </button>
            </div>
        </div>
        <p class="perso-field-hint" id="<?php echo $perso_text_prefix; ?>-wrap-circle-hint">Le texte suit le contour intérieur du cercle.</p>
    </div>

    <div class="perso-font-picker" role="group" aria-label="Choisir une police">
        <button type="button" class="perso-font-btn is-active" data-font="Outfit" style="font-family:'Outfit',sans-serif">Outfit</button>
        <button type="button" class="perso-font-btn" data-font="Fraunces" style="font-family:'Fraunces',serif">Fraunces</button>
        <button type="button" class="perso-font-btn" data-font="Pacifico" style="font-family:'Pacifico',cursive">Pacifico</button>
        <button type="button" class="perso-font-btn" data-font="Bebas Neue" style="font-family:'Bebas Neue',sans-serif">Bebas</button>
        <button type="button" class="perso-font-btn" data-font="Dancing Script" style="font-family:'Dancing Script',cursive">Dancing</button>
        <button type="button" class="perso-font-btn" data-font="Playfair Display" style="font-family:'Playfair Display',serif">Playfair</button>
        <button type="button" class="perso-font-btn" data-font="Lobster" style="font-family:'Lobster',cursive">Lobster</button>
    </div>
</section>
