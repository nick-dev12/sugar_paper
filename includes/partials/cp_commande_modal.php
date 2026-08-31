<?php
/**
 * Modal formulaire commande personnalisée (partagé page + cartes cake topper)
 *
 * Variables attendues via $cp_modal (array) ou variables individuelles legacy.
 */
if (!isset($cp_modal) || !is_array($cp_modal)) {
    require_once __DIR__ . '/../cake_topper_cp.php';
    $cp_modal = cp_form_modal_prepare_state();
}

$cp_csrf = (string) ($cp_modal['csrf'] ?? '');
$user_logged_in = !empty($cp_modal['user_logged_in']);
$prefill = isset($cp_modal['prefill']) && is_array($cp_modal['prefill']) ? $cp_modal['prefill'] : [];
$form_action = isset($cp_modal['form_action']) ? (string) $cp_modal['form_action'] : '/commande-personnalisee.php';
$show_order_form = !empty($cp_modal['show_order_form']);
$selected_catalogue_id = (int) ($cp_modal['selected_catalogue_id'] ?? 0);
$selected_catalogue_nom = (string) ($cp_modal['selected_catalogue_nom'] ?? ($prefill['type_produit'] ?? ''));
$selected_catalogue_image = (string) ($cp_modal['selected_catalogue_image'] ?? '');
$selected_prix_min = (float) ($cp_modal['selected_prix_min'] ?? 0);
$selected_prix_max = (float) ($cp_modal['selected_prix_max'] ?? 0);
$selected_boutique_id = (int) ($cp_modal['selected_boutique_id'] ?? ($prefill['boutique_produit_id'] ?? 0));
?>
<div class="cp-modal-overlay<?php echo $show_order_form ? ' is-visible' : ''; ?>" id="cp-modal-overlay"<?php echo !$show_order_form ? ' hidden' : ''; ?> aria-hidden="<?php echo $show_order_form ? 'false' : 'true'; ?>">
    <div class="perso-modal-backdrop cp-modal-backdrop" id="cp-modal-backdrop" aria-hidden="true"></div>
    <div class="cp-form-wrap cp-form-wrap--modal perso-modal-dialog cp-perso-dialog" id="cp-form-wrap" role="dialog" aria-modal="true" aria-labelledby="cp-modal-title">
        <button type="button" class="perso-modal-close" id="cp-clear-selection" aria-label="Fermer le formulaire">&times;</button>
        <h2 class="perso-modal-title" id="cp-modal-title">Personnalisez votre création</h2>

        <form method="POST" action="<?php echo htmlspecialchars($form_action); ?>" class="form-commande-perso form-commande-perso--modal cp-perso-form" id="form-commande-perso" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($cp_csrf); ?>">
            <input type="hidden" name="catalogue_produit_id" id="catalogue_produit_id" value="<?php echo $selected_catalogue_id > 0 ? (int) $selected_catalogue_id : ''; ?>">
            <input type="hidden" name="boutique_produit_id" id="boutique_produit_id" value="<?php echo $selected_boutique_id > 0 ? (int) $selected_boutique_id : ''; ?>">
            <input type="hidden" name="type_produit" id="type_produit" value="<?php echo htmlspecialchars($selected_catalogue_nom); ?>">

            <div class="cp-form-product-head perso-tool-section" id="cp-form-product-head">
                <?php if ($selected_catalogue_image !== ''): ?>
                <img src="<?php echo htmlspecialchars($selected_catalogue_image); ?>" alt="" class="cp-form-product-head__img" id="cp-form-product-image">
                <?php else: ?>
                <img src="" alt="" class="cp-form-product-head__img" id="cp-form-product-image" hidden>
                <?php endif; ?>
                <div class="cp-form-product-head__body">
                    <p class="cp-form-product-head__label">Produit sélectionné</p>
                    <p class="cp-form-product-head__name" id="cp-form-product-name"><?php echo htmlspecialchars($selected_catalogue_nom); ?></p>
                    <p class="cp-form-product-head__range" id="cp-form-product-range">
                        <?php if ($selected_prix_max > 0): ?>
                        Fourchette : <?php echo number_format($selected_prix_min, 0, ',', ' '); ?> — <?php echo number_format($selected_prix_max, 0, ',', ' '); ?> FCFA
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!$user_logged_in): ?>
            <section class="cp-form-section perso-tool-section">
                <h3 class="perso-tool-title"><i class="fas fa-user-circle" aria-hidden="true"></i> Vos coordonnées</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required autocomplete="name"
                            value="<?php echo htmlspecialchars($prefill['nom'] ?? ''); ?>" placeholder="Votre nom">
                    </div>
                    <div class="form-group form-group--tel">
                        <label for="telephone">Téléphone *</label>
                        <div class="input-wrapper input-wrapper--intl-tel cp-tel-intl">
                            <input type="tel" id="telephone" name="telephone" required autocomplete="tel"
                                value="<?php echo htmlspecialchars($prefill['telephone'] ?? ''); ?>"
                                placeholder="77 123 45 67">
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="cp-form-section perso-tool-section">
                <h3 class="perso-tool-title"><i class="fas fa-tag" aria-hidden="true"></i> Votre prix</h3>
                <div class="form-group">
                    <label for="prix_propose">Prix proposé (FCFA) *</label>
                    <input type="number" id="prix_propose" name="prix_propose" required min="0" step="1"
                        value="<?php echo htmlspecialchars($prefill['prix_propose'] ?? ''); ?>"
                        placeholder="Indiquez votre budget">
                </div>
            </section>

            <section class="cp-form-section perso-tool-section">
                <h3 class="perso-tool-title"><i class="fas fa-pen-fancy" aria-hidden="true"></i> Personnalisez</h3>
                <div class="form-group">
                    <label for="description_creation">Description de votre création</label>
                    <textarea id="description_creation" name="description_creation" rows="4" maxlength="2000"
                        placeholder="Décrivez votre idée : thème, texte, prénom, couleurs, date de l'événement..."><?php echo htmlspecialchars($prefill['description_creation'] ?? ''); ?></textarea>
                    <p class="cp-field-help">Précisez ce que vous souhaitez pour votre création sur mesure.</p>
                </div>
            </section>

            <section class="cp-form-section perso-tool-section">
                <h3 class="perso-tool-title"><i class="fas fa-microphone" aria-hidden="true"></i> Message vocal <span class="cp-optional">(optionnel)</span></h3>
                <div class="cp-voice-note" id="cp-voice-note">
                    <input type="file" id="note_vocale" name="note_vocale" class="cp-voice-note__input" accept="audio/*,.webm,.ogg,.mp4,.m4a,.mp3" hidden>
                    <div class="cp-voice-note__panel cp-voice-note__panel--idle" id="cp-voice-idle">
                        <button type="button" class="cp-voice-note__mic" id="cp-voice-record-btn" aria-label="Enregistrer un message vocal">
                            <i class="fas fa-microphone" aria-hidden="true"></i>
                        </button>
                        <div class="cp-voice-note__hint">
                            <strong>Appuyez pour enregistrer</strong>
                            <span>Max 2 min</span>
                        </div>
                    </div>
                    <div class="cp-voice-note__panel cp-voice-note__panel--recording" id="cp-voice-recording" hidden>
                        <span class="cp-voice-note__rec-dot" aria-hidden="true"></span>
                        <span class="cp-voice-note__timer" id="cp-voice-timer">0:00</span>
                        <div class="cp-voice-note__wave cp-voice-note__wave--live" id="cp-voice-wave-live" aria-hidden="true">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <button type="button" class="cp-voice-note__stop" id="cp-voice-stop">
                            <i class="fas fa-stop" aria-hidden="true"></i> Arrêter
                        </button>
                    </div>
                    <div class="cp-voice-note__panel cp-voice-note__panel--preview" id="cp-voice-preview" hidden>
                        <button type="button" class="cp-voice-note__play" id="cp-voice-play" aria-label="Écouter le message">
                            <i class="fas fa-play" aria-hidden="true"></i>
                        </button>
                        <div class="cp-voice-note__preview-body">
                            <div class="cp-voice-note__wave cp-voice-note__wave--preview" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                            </div>
                            <span class="cp-voice-note__duration" id="cp-voice-duration">0:00</span>
                        </div>
                        <button type="button" class="cp-voice-note__delete" id="cp-voice-delete" aria-label="Supprimer le message">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="cp-voice-note__error" id="cp-voice-error" hidden role="alert"></p>
                </div>
            </section>

            <section class="cp-form-section perso-tool-section">
                <h3 class="perso-tool-title"><i class="fas fa-image" aria-hidden="true"></i> Images d'inspiration <span class="cp-optional">(optionnel)</span></h3>
                <div class="form-group">
                    <div class="upload-reference-box cp-upload-perso" id="upload-reference-box">
                        <input type="file" id="images_reference" name="images_reference[]" class="upload-reference-input"
                            accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                        <button type="button" class="upload-reference-trigger" id="upload-reference-trigger">
                            <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                            <strong>Ajouter des photos d'inspiration</strong>
                            <span>Glissez-déposez ou cliquez — jusqu'à 6 images</span>
                        </button>
                        <p class="upload-help">
                            <strong>Formats :</strong> JPG, PNG, WEBP, GIF — 5&nbsp;Mo max par image.
                        </p>
                        <p class="upload-counter" id="upload-counter">0 / 6 image(s) sélectionnée(s)</p>
                        <div class="preview-reference-grid" id="preview-reference-grid" aria-live="polite"></div>
                    </div>
                </div>
            </section>

            <p class="cp-legal">
                En envoyant, vous acceptez les
                <a href="/conditions-utilisation.php" target="_blank" rel="noopener">conditions d'utilisation</a>.
                <?php if (!$user_logged_in): ?>
                Un compte sera créé avec votre numéro pour suivre la demande.
                <?php endif; ?>
            </p>

            <div class="perso-modal-actions">
                <button type="submit" class="perso-btn perso-btn-primary btn-submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer ma demande
                </button>
            </div>
        </form>
    </div>
</div>
