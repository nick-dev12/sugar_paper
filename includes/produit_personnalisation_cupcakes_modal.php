<?php
/**
 * Modal personnalisation cupcakes — 12 cercles sur feuille
 */
?>
<div class="perso-modal perso-modal--cupcakes" id="modal-personnalisation-cupcakes" aria-hidden="true" role="dialog" aria-labelledby="cupcakes-modal-title">
    <div class="perso-modal-backdrop" aria-hidden="true"></div>
    <div class="perso-modal-dialog">
        <button type="button" class="perso-modal-close" id="cupcakes-modal-close" aria-label="Fermer">&times;</button>
        <h2 class="perso-modal-title" id="cupcakes-modal-title">Personnalisez vos cupcakes</h2>

        <div class="perso-modal-layout">
            <aside class="perso-toolbar" aria-label="Outils cupcakes">
                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-image" aria-hidden="true"></i> Images</h3>
                    <div class="perso-image-mode-picker" role="group" aria-label="Mode d’ajout des images">
                        <button type="button" class="perso-image-mode-btn is-active" data-mode="shared" aria-pressed="true">
                            <strong>Une image</strong>
                            <span>Appliquée aux 12 cercles</span>
                        </button>
                        <button type="button" class="perso-image-mode-btn" data-mode="per_circle" aria-pressed="false">
                            <strong>Par cercle</strong>
                            <span>Une image différente chacun</span>
                        </button>
                    </div>
                    <label class="perso-upload-compact" id="cupcakes-upload-label" tabindex="0">
                        <input type="file" id="cupcakes-image-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="perso-upload-compact-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
                        <span class="perso-upload-compact-text" id="cupcakes-upload-text">Importer une image</span>
                        <span class="perso-upload-compact-hint">JPG, PNG, WebP — ou cliquez un cercle</span>
                        <span class="perso-upload-filename" id="cupcakes-upload-filename"></span>
                    </label>
                    <p class="perso-image-hint" id="cupcakes-image-hint" hidden>Glissez l’image pour la repositionner. Molette ou poignées pour zoomer.</p>
                    <button type="button" class="perso-image-reset-btn" id="cupcakes-image-reset" hidden>Réinitialiser le cadrage</button>
                    <p class="perso-field-hint" id="cupcakes-active-circle-hint">Cercle sélectionné : <strong id="cupcakes-active-circle-label">—</strong> / 12</p>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-file" aria-hidden="true"></i> Format de feuille</h3>
                    <p class="perso-paper-info" id="cupcakes-paper-info">Feuille A4 — 21 × 29,7 cm · 12 emplacements</p>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-shapes" aria-hidden="true"></i> Forme</h3>
                    <div class="perso-shape-picker" role="group" aria-label="Choisir une forme">
                        <button type="button" class="perso-shape-btn is-active" data-shape="circle" aria-pressed="true" title="Cercle">
                            <span class="perso-shape-icon perso-shape-icon--circle"></span>
                            <span>Cercle</span>
                        </button>
                        <button type="button" class="perso-shape-btn" data-shape="heart" aria-pressed="false" title="Cœur">
                            <span class="perso-shape-icon perso-shape-icon--heart"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
                            <span>Cœur</span>
                        </button>
                    </div>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Dimensions</h3>
                    <div class="perso-dimension-field">
                        <label for="cupcakes-dim-diameter">Diamètre global <span id="cupcakes-dim-diameter-val">5</span> cm</label>
                        <input type="range" id="cupcakes-dim-diameter" min="1" max="5" step="0.1" value="5">
                    </div>
                    <p class="perso-field-hint">Les 12 formes sont redimensionnées ensemble (maximum 5 cm), avec un espacement régulier.</p>
                </section>

                <?php
                $perso_text_prefix = 'cupcakes';
                include __DIR__ . '/produit_personnalisation_text_section.php';
                ?>
            </aside>

            <div class="perso-preview-panel">
                <p class="perso-preview-label">Aperçu en direct — cliquez un cercle pour le sélectionner</p>
                <div class="perso-preview-stage">
                    <div class="perso-preview-viewport" id="cupcakes-preview-viewport">
                        <canvas id="cupcakes-preview-canvas" width="595" height="842" aria-label="Aperçu cupcakes"></canvas>
                        <div class="perso-image-manipulator" id="cupcakes-image-manipulator" hidden aria-hidden="true">
                            <div class="perso-image-manip-box" id="cupcakes-image-manip-box">
                                <button type="button" class="perso-manip-delete" id="cupcakes-image-delete" aria-label="Supprimer l'image">&times;</button>
                                <span class="perso-image-handle perso-image-handle--nw" data-handle="nw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--ne" data-handle="ne" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--sw" data-handle="sw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--se" data-handle="se" aria-hidden="true"></span>
                            </div>
                        </div>
                        <?php
                        $perso_text_prefix = 'cupcakes';
                        include __DIR__ . '/produit_personnalisation_text_manipulator.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <p class="perso-loading" id="cupcakes-loading" hidden>Préparation de l'image…</p>

        <div class="perso-modal-actions">
            <button type="button" class="perso-btn perso-btn-secondary" id="cupcakes-cancel">Annuler</button>
            <button type="button" class="perso-btn perso-btn-primary" id="cupcakes-validate" disabled>Valider et ajouter au panier</button>
        </div>
    </div>
</div>
