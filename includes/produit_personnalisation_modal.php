<?php
/**
 * Fragment HTML du modal personnalisation (inclus par render_produit_personnalisation_modal)
 */
?>
<div class="perso-modal" id="modal-personnalisation" aria-hidden="true" role="dialog" aria-labelledby="perso-modal-title">
    <div class="perso-modal-backdrop" aria-hidden="true"></div>
    <div class="perso-modal-dialog">
        <button type="button" class="perso-modal-close" id="perso-modal-close" aria-label="Fermer">&times;</button>
        <h2 class="perso-modal-title" id="perso-modal-title">Personnalisez votre création</h2>

        <div class="perso-modal-layout">
            <aside class="perso-toolbar" aria-label="Outils de personnalisation">
                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-image" aria-hidden="true"></i> Images</h3>
                    <label class="perso-upload-compact" id="perso-upload-label" tabindex="0">
                        <input type="file" id="perso-image-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="perso-upload-compact-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
                        <span class="perso-upload-compact-text">Importer une image</span>
                        <span class="perso-upload-compact-hint">JPG, PNG, WebP — chaque import ajoute une image superposée</span>
                        <span class="perso-upload-filename" id="perso-upload-filename"></span>
                    </label>
                    <p class="perso-image-hint" id="perso-image-hint" hidden>Glissez l'image sur l'aperçu pour la repositionner. Molette ou pincement pour zoomer.</p>
                    <button type="button" class="perso-image-reset-btn" id="perso-image-reset" hidden>Réinitialiser le cadrage</button>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-file" aria-hidden="true"></i> Format de feuille</h3>
                    <div class="perso-paper-picker" role="group" aria-label="Choisir le format de feuille">
                        <button type="button" class="perso-paper-btn is-active" data-paper="a4" aria-pressed="true" title="A4">
                            <span class="perso-paper-btn__label">A4</span>
                            <span class="perso-paper-btn__size">21 × 29,7 cm</span>
                        </button>
                        <button type="button" class="perso-paper-btn" data-paper="a3" aria-pressed="false" title="A3">
                            <span class="perso-paper-btn__label">A3</span>
                            <span class="perso-paper-btn__size">29,7 × 42 cm</span>
                        </button>
                    </div>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-shapes" aria-hidden="true"></i> Forme</h3>
                    <div class="perso-shape-picker" role="group" aria-label="Choisir une forme">
                        <button type="button" class="perso-shape-btn is-active" data-shape="circle" aria-pressed="true" title="Cercle">
                            <span class="perso-shape-icon perso-shape-icon--circle"></span>
                            <span>Cercle</span>
                        </button>
                        <button type="button" class="perso-shape-btn" data-shape="square" aria-pressed="false" title="Carré">
                            <span class="perso-shape-icon perso-shape-icon--square"></span>
                            <span>Carré</span>
                        </button>
                        <button type="button" class="perso-shape-btn" data-shape="heart" aria-pressed="false" title="Cœur">
                            <span class="perso-shape-icon perso-shape-icon--heart"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
                            <span>Cœur</span>
                        </button>
                    </div>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Dimensions</h3>
                    <p class="perso-paper-info" id="perso-paper-info">Feuille A4 — 21 × 29,7 cm</p>
                    <div class="perso-dimension-field" id="perso-dim-width-wrap">
                        <label for="perso-dim-width">Largeur <span id="perso-dim-width-val">15</span> cm</label>
                        <input type="range" id="perso-dim-width" min="5" max="19" step="0.5" value="15">
                    </div>
                    <div class="perso-dimension-field" id="perso-dim-height-wrap">
                        <label for="perso-dim-height">Hauteur <span id="perso-dim-height-val">15</span> cm</label>
                        <input type="range" id="perso-dim-height" min="5" max="27.7" step="0.5" value="15">
                    </div>
                    <div class="perso-dimension-field" id="perso-dim-diameter-wrap" hidden>
                        <label for="perso-dim-diameter">Diamètre <span id="perso-dim-diameter-val">15</span> cm</label>
                        <input type="range" id="perso-dim-diameter" min="5" max="19" step="0.5" value="15">
                    </div>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-font" aria-hidden="true"></i> Texte</h3>
                    <div class="perso-text-list" id="perso-text-list" role="list" aria-label="Textes ajoutés"></div>
                    <button type="button" class="perso-text-add-btn" id="perso-text-add"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un texte</button>
                    <textarea class="perso-text-input" id="perso-text-input" maxlength="500" rows="3" placeholder="Votre texte ici… (Entrée = retour à la ligne)" autocomplete="off"></textarea>

                    <div class="perso-text-controls">
                        <div class="perso-dimension-field">
                            <label for="perso-text-size"><span class="perso-dim-label-text">Taille du texte</span> <span id="perso-text-size-val">50</span>%</label>
                            <input type="range" id="perso-text-size" min="20" max="100" value="50" step="1">
                        </div>
                        <div class="perso-dimension-field">
                            <label for="perso-text-pos-x"><span class="perso-dim-label-text">Position horizontale</span> <span id="perso-text-pos-x-val">50</span>%</label>
                            <input type="range" id="perso-text-pos-x" min="0" max="100" value="50" step="1">
                        </div>
                        <div class="perso-dimension-field">
                            <label for="perso-text-pos-y"><span class="perso-dim-label-text">Position verticale</span> <span id="perso-text-pos-y-val">50</span>%</label>
                            <input type="range" id="perso-text-pos-y" min="0" max="100" value="50" step="1">
                        </div>
                        <div class="perso-dimension-field" id="perso-text-rotation-wrap">
                            <label for="perso-text-rotation"><span class="perso-dim-label-text">Orientation</span> <span id="perso-text-rotation-val">0</span>°</label>
                            <input type="range" id="perso-text-rotation" min="-180" max="180" value="0" step="1">
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
                                    <input type="color" id="perso-text-color" value="#E5488A">
                                    <span><i class="fa-solid fa-eyedropper" aria-hidden="true"></i></span>
                                </label>
                            </div>
                        </div>
                        <div class="perso-wrap-toggle">
                            <input type="checkbox" id="perso-text-wrap-circle" class="perso-wrap-checkbox">
                            <button type="button" class="perso-wrap-btn" id="perso-wrap-circle-btn" aria-pressed="false">
                                <span class="perso-checkbox-box" aria-hidden="true"></span>
                                <span class="perso-checkbox-text">Épouser la forme du cercle</span>
                            </button>
                        </div>
                        <div class="perso-wrap-position" id="perso-wrap-position" role="group" aria-label="Position du texte sur le cercle">
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
                        <p class="perso-field-hint" id="perso-wrap-circle-hint">Le texte suit le contour intérieur du cercle.</p>
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
            </aside>

            <div class="perso-preview-panel">
                <p class="perso-preview-label">Aperçu en direct</p>
                <div class="perso-preview-stage">
                    <div class="perso-preview-viewport" id="perso-preview-viewport">
                        <canvas id="perso-preview-canvas" width="640" height="640" aria-label="Aperçu de la personnalisation"></canvas>
                        <div class="perso-image-manipulator" id="perso-image-manipulator" hidden aria-hidden="true">
                            <div class="perso-image-manip-box" id="perso-image-manip-box">
                                <button type="button" class="perso-manip-delete" id="perso-image-delete" aria-label="Supprimer l'image">&times;</button>
                                <span class="perso-image-handle perso-image-handle--nw" data-handle="nw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--ne" data-handle="ne" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--sw" data-handle="sw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--se" data-handle="se" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="perso-text-manipulator" id="perso-text-manipulator" hidden aria-hidden="true">
                            <div class="perso-text-manip-box" id="perso-text-manip-box">
                                <button type="button" class="perso-manip-delete" id="perso-text-delete" aria-label="Supprimer ce texte">&times;</button>
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
                    </div>
                </div>
            </div>
        </div>

        <p class="perso-loading" id="perso-loading" hidden>Préparation de l'image…</p>

        <div class="perso-modal-actions">
            <button type="button" class="perso-btn perso-btn-secondary" id="perso-cancel">Annuler</button>
            <button type="button" class="perso-btn perso-btn-primary" id="perso-validate" disabled>Valider et ajouter au panier</button>
        </div>
    </div>
</div>
