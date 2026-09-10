<?php
/**
 * Modal personnalisation disques à cocktail — 6 cercles sur feuille
 */
?>
<div class="perso-modal perso-modal--disques-cocktail" id="modal-personnalisation-disques-cocktail" aria-hidden="true" role="dialog" aria-labelledby="disques-cocktail-modal-title">
    <div class="perso-modal-backdrop" aria-hidden="true"></div>
    <div class="perso-modal-dialog">
        <button type="button" class="perso-modal-close" id="disques-cocktail-modal-close" aria-label="Fermer">&times;</button>
        <h2 class="perso-modal-title" id="disques-cocktail-modal-title">Personnalisez vos disques à cocktail</h2>

        <div class="perso-modal-layout">
            <aside class="perso-toolbar" aria-label="Outils disques à cocktail">
                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-image" aria-hidden="true"></i> Images</h3>
                    <div class="perso-image-mode-picker" role="group" aria-label="Mode d’ajout des images">
                        <button type="button" class="perso-image-mode-btn is-active" data-mode="shared" aria-pressed="true">
                            <strong>Une image</strong>
                            <span>Appliquée aux 6 disques</span>
                        </button>
                        <button type="button" class="perso-image-mode-btn" data-mode="per_circle" aria-pressed="false">
                            <strong>Par disque</strong>
                            <span>Une image différente chacun</span>
                        </button>
                    </div>
                    <label class="perso-upload-compact" id="disques-cocktail-upload-label" tabindex="0">
                        <input type="file" id="disques-cocktail-image-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="perso-upload-compact-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
                        <span class="perso-upload-compact-text" id="disques-cocktail-upload-text">Importer une image</span>
                        <span class="perso-upload-compact-hint">JPG, PNG, WebP — ou cliquez un disque</span>
                        <span class="perso-upload-filename" id="disques-cocktail-upload-filename"></span>
                    </label>
                    <p class="perso-image-hint" id="disques-cocktail-image-hint" hidden>Glissez l’image pour la repositionner. Molette ou poignées pour zoomer.</p>
                    <button type="button" class="perso-image-reset-btn" id="disques-cocktail-image-reset" hidden>Réinitialiser le cadrage</button>
                    <p class="perso-field-hint" id="disques-cocktail-active-circle-hint">Disque sélectionné : <strong id="disques-cocktail-active-circle-label">—</strong> / 6</p>
                </section>

                <section class="perso-tool-section">
                    <h3 class="perso-tool-title"><i class="fa-solid fa-file" aria-hidden="true"></i> Format de feuille</h3>
                    <p class="perso-paper-info" id="disques-cocktail-paper-info">Feuille A4 — 21 × 29,7 cm · 6 emplacements</p>
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
                        <label for="disques-cocktail-dim-diameter">Diamètre global <span id="disques-cocktail-dim-diameter-val">8</span> cm</label>
                        <input type="range" id="disques-cocktail-dim-diameter" min="1" max="8" step="0.1" value="8">
                    </div>
                    <p class="perso-field-hint">Les 6 disques (2 en haut, 2 au centre, 2 en bas) suivent ce diamètre (maximum 8 cm par défaut).</p>
                </section>

                <?php
                $perso_text_prefix = 'disques-cocktail';
                include __DIR__ . '/produit_personnalisation_text_section.php';
                ?>
            </aside>

            <div class="perso-preview-panel">
                <p class="perso-preview-label">Aperçu en direct — cliquez un disque pour le sélectionner</p>
                <div class="perso-preview-stage">
                    <div class="perso-preview-viewport" id="disques-cocktail-preview-viewport">
                        <canvas id="disques-cocktail-preview-canvas" width="595" height="842" aria-label="Aperçu disques à cocktail"></canvas>
                        <div class="perso-image-manipulator" id="disques-cocktail-image-manipulator" hidden aria-hidden="true">
                            <div class="perso-image-manip-box" id="disques-cocktail-image-manip-box">
                                <button type="button" class="perso-manip-delete" id="disques-cocktail-image-delete" aria-label="Supprimer l'image">&times;</button>
                                <span class="perso-image-handle perso-image-handle--nw" data-handle="nw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--ne" data-handle="ne" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--sw" data-handle="sw" aria-hidden="true"></span>
                                <span class="perso-image-handle perso-image-handle--se" data-handle="se" aria-hidden="true"></span>
                            </div>
                        </div>
                        <?php
                        $perso_text_prefix = 'disques-cocktail';
                        include __DIR__ . '/produit_personnalisation_text_manipulator.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <p class="perso-loading" id="disques-cocktail-loading" hidden>Préparation de l'image…</p>

        <div class="perso-modal-actions">
            <button type="button" class="perso-btn perso-btn-secondary" id="disques-cocktail-cancel">Annuler</button>
            <button type="button" class="perso-btn perso-btn-primary" id="disques-cocktail-validate" disabled>Valider et ajouter au panier</button>
        </div>
    </div>
</div>
