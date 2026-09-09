<?php
/**
 * Modal personnalisation contours de gâteau — 3 bandes sur feuille A3/A4
 */
?>
<div class="perso-modal perso-modal--contours" id="modal-personnalisation-contours" aria-hidden="true" role="dialog" aria-labelledby="contours-modal-title">
    <div class="perso-modal-backdrop" aria-hidden="true"></div>
    <div class="perso-modal-dialog">
        <button type="button" class="perso-modal-close" id="contours-modal-close" aria-label="Fermer">&times;</button>
        <h2 class="perso-modal-title" id="contours-modal-title">Personnalisez vos contours</h2>

        <div class="perso-modal-layout">
            <aside class="perso-toolbar" aria-label="Outils contours">
                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-image" aria-hidden="true"></i> Images</h3>
                    <div class="perso-image-mode-picker" role="group" aria-label="Mode d’ajout des images">
                        <button type="button" class="perso-image-mode-btn is-active" data-mode="shared" aria-pressed="true">
                            <strong>Une image</strong>
                            <span>Appliquée aux 3 contours</span>
                        </button>
                        <button type="button" class="perso-image-mode-btn" data-mode="per_contour" aria-pressed="false">
                            <strong>Par contour</strong>
                            <span>Une image différente chacun</span>
                        </button>
                    </div>
                    <label class="perso-upload-compact" id="contours-upload-label" tabindex="0">
                        <input type="file" id="contours-image-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="perso-upload-compact-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
                        <span class="perso-upload-compact-text" id="contours-upload-text">Importer une image</span>
                        <span class="perso-upload-compact-hint">JPG, PNG, WebP — ou cliquez un contour</span>
                        <span class="perso-upload-filename" id="contours-upload-filename"></span>
                    </label>
                    <p class="perso-image-hint" id="contours-image-hint" hidden>Glissez l’image pour la repositionner. Molette ou poignées pour zoomer.</p>
                    <button type="button" class="perso-image-reset-btn" id="contours-image-reset" hidden>Réinitialiser le cadrage</button>
                    <p class="perso-field-hint" id="contours-active-hint">Contour sélectionné : <strong id="contours-active-label">—</strong> / 3</p>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-file" aria-hidden="true"></i> Format de feuille</h3>
                    <div class="perso-paper-picker" role="group" aria-label="Choisir le format de feuille">
                        <button type="button" class="perso-paper-btn is-active" data-paper="a4" aria-pressed="true" title="A4 paysage">
                            <span class="perso-paper-btn__label">A4</span>
                            <span class="perso-paper-btn__size">29,7 × 21 cm paysage</span>
                        </button>
                        <button type="button" class="perso-paper-btn" data-paper="a3" aria-pressed="false" title="A3 paysage">
                            <span class="perso-paper-btn__label">A3</span>
                            <span class="perso-paper-btn__size">42 × 29,7 cm paysage</span>
                        </button>
                    </div>
                    <p class="perso-paper-info" id="contours-paper-info">Feuille A4 paysage — 29,7 × 21 cm · hauteur max. contour : 5,9 cm</p>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Dimensions</h3>
                    <div class="perso-dimension-field">
                        <label for="contours-dim-height">Hauteur des contours <span id="contours-dim-height-val">5</span> cm</label>
                        <input type="range" id="contours-dim-height" min="2" max="8" step="0.1" value="5">
                    </div>
                    <p class="perso-field-hint">Les 3 bandes ont la même hauteur et s’adaptent à la feuille en paysage. La largeur utilise presque toute la feuille ; la hauteur est limitée pour tenir dans le format choisi.</p>
                </section>

                <?php
                $perso_text_prefix = 'contours';
                include __DIR__ . '/produit_personnalisation_text_section.php';
                ?>
            </aside>

            <div class="perso-preview-panel">
                <p class="perso-preview-label">Aperçu en direct — cliquez un contour pour le sélectionner</p>
                <div class="perso-preview-stage">
                    <div class="perso-preview-viewport" id="contours-preview-viewport">
                        <canvas id="contours-preview-canvas" width="842" height="595" aria-label="Aperçu contours"></canvas>
                        <div class="perso-image-manipulator" id="contours-image-manipulator" hidden aria-hidden="true">
                            <div class="perso-image-manip-box" id="contours-image-manip-box">
                                <button type="button" class="perso-manip-delete" id="contours-image-delete" aria-label="Supprimer l'image">&times;</button>
                                <span class="perso-image-handle perso-image-handle--nw" data-handle="nw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--ne" data-handle="ne" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--sw" data-handle="sw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--se" data-handle="se" aria-hidden="true"></span>
                            </div>
                        </div>
                        <?php
                        $perso_text_prefix = 'contours';
                        include __DIR__ . '/produit_personnalisation_text_manipulator.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <p class="perso-loading" id="contours-loading" hidden>Préparation de l'image…</p>

        <div class="perso-modal-actions">
            <button type="button" class="perso-btn perso-btn-secondary" id="contours-cancel">Annuler</button>
            <button type="button" class="perso-btn perso-btn-primary" id="contours-validate" disabled>Valider et ajouter au panier</button>
        </div>
    </div>
</div>
