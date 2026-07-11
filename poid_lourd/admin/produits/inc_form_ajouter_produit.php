<?php
/**
 * Formulaire ajout / modification produit — ajouter.php, modifier.php, index.php (modal).
 * Variables attendues :
 *   $add_produit_modal, $add_produit_form_action, $categories, $categorie_id_prefill
 *   Hiérarchie vendeur : $fap_use_category_hierarchy, $vcat_prefill_sub, $vcat_prefill_generale
 *   $add_produit_error_message (optionnel)
 * Édition : $fap_is_edit = true, $fap_edit_produit (tableau), $PM prérempli par modifier.php
 *   $fap_edit_variantes_js (optionnel) — variantes initiales pour le script
 */
$add_produit_modal = !empty($add_produit_modal);
$fap_is_edit = !empty($fap_is_edit);
$add_produit_form_action = isset($add_produit_form_action) ? (string) $add_produit_form_action : '';
$fap_edit_modal = !empty($fap_edit_modal);
$form_el_id = $fap_is_edit
    ? ($fap_edit_modal ? 'form-edit-produit-modal' : 'form-edit-produit-page')
    : ($add_produit_modal ? 'form-add-produit-modal' : 'form-add-produit-page');
if (!isset($PM) || !is_array($PM)) {
    $PM = $_POST;
}
$fap_edit_produit = (isset($fap_edit_produit) && is_array($fap_edit_produit)) ? $fap_edit_produit : null;
if (!isset($fap_edit_variantes_js) || !is_array($fap_edit_variantes_js)) {
    $fap_edit_variantes_js = [];
}
$categorie_id_prefill = isset($categorie_id_prefill) ? (int) $categorie_id_prefill : 0;
if (!isset($categories) || !is_array($categories)) {
    $categories = [];
}

$fap_existing_image_paths = [];
if ($fap_is_edit && $fap_edit_produit) {
    $fe = $fap_edit_produit;
    if (!empty($fe['images'])) {
        $dec = json_decode((string) $fe['images'], true);
        if (is_array($dec)) {
            $fap_existing_image_paths = $dec;
        }
    }
    if (empty($fap_existing_image_paths) && !empty($fe['image_principale'])) {
        $fap_existing_image_paths = [(string) $fe['image_principale']];
    }
}
if ($fap_is_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['images_to_keep']) && is_array($_POST['images_to_keep'])) {
    $fap_existing_image_paths = array_values(array_filter(array_map('trim', $_POST['images_to_keep'])));
}
$fap_use_category_hierarchy = !empty($fap_use_category_hierarchy);
if (!isset($vcat_prefill_sub)) {
    $vcat_prefill_sub = 0;
}
if (!isset($vcat_prefill_generale)) {
    $vcat_prefill_generale = 0;
}
if ($fap_use_category_hierarchy && $categorie_id_prefill > 0 && (int) $vcat_prefill_sub === 0) {
    require_once __DIR__ . '/../../models/model_categories.php';
    $cp = get_categorie_by_id((int) $categorie_id_prefill);
    if ($cp && isset($_SESSION['admin_id']) && function_exists('categorie_est_utilisable_par_vendeur')
        && categorie_est_utilisable_par_vendeur((int) $cp['id'], (int) $_SESSION['admin_id'])) {
        $vcat_prefill_sub = (int) $cp['id'];
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($cp['categorie_generale_id'] ?? 0);
        }
    }
}
$fap_cg_attr_map = [];
if (function_exists('get_categorie_generale_attributs_map_for_js')) {
    require_once __DIR__ . '/../../models/model_categories.php';
    $fap_cg_attr_map = get_categorie_generale_attributs_map_for_js();
}
$fap_attr_conditional = !empty($fap_use_category_hierarchy);
$pm_unite = isset($PM['unite']) ? (string) $PM['unite'] : 'unité';
$pm_mesure = isset($PM['mesure']) ? (string) $PM['mesure'] : '';
$pm_prix_negociable = 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prix_negociable'])) {
    $pm_prix_negociable = ((string) $_POST['prix_negociable'] === '0') ? 0 : 1;
} elseif ($fap_is_edit && $fap_edit_produit && function_exists('produits_has_column') && produits_has_column('prix_negociable')) {
    $pm_prix_negociable = (int) ($fap_edit_produit['prix_negociable'] ?? 1) ? 1 : 0;
}
$fap_attrs_card_hidden = $fap_use_category_hierarchy && (int) $vcat_prefill_generale <= 0;
?>
<form method="POST" action="<?php echo htmlspecialchars($add_produit_form_action); ?>"
    enctype="multipart/form-data"
    class="form-add form-add-produit-v2 <?php echo htmlspecialchars($form_el_id); ?>"
    id="<?php echo htmlspecialchars($form_el_id); ?>"<?php echo $fap_edit_modal ? ' target="_top"' : ''; ?>>
    <?php if ($add_produit_modal): ?>
    <input type="hidden" name="admin_add_produit" value="1">
    <?php endif; ?>
    <?php if ($fap_edit_modal && $fap_is_edit && $fap_edit_produit): ?>
    <input type="hidden" name="admin_edit_produit" value="1">
    <input type="hidden" name="produit_id" value="<?php echo (int) ($fap_edit_produit['id'] ?? 0); ?>">
    <?php endif; ?>

    <?php if (!empty($add_produit_error_message)): ?>
    <div class="fap-alert fap-alert-error" role="alert">
        <i class="fas fa-exclamation-circle"></i>
        <div class="fap-alert-msg"><?php echo $add_produit_error_message; ?></div>
    </div>
    <?php endif; ?>

    <div class="fap-layout">
        <div class="fap-column fap-column-main">
            <div class="fap-card">
                <div class="fap-card-head">
                    <i class="fas fa-info-circle"></i>
                    <h3>Informations principales</h3>
                </div>
                <div class="fap-field">
                    <label for="nom">Nom du produit <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" required placeholder="Ex. Kit frein arrière complet"
                        value="<?php echo isset($PM['nom']) ? htmlspecialchars($PM['nom']) : ''; ?>">
                </div>
                <div class="fap-field">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" required placeholder="Décrivez le produit, compatibilité, état…" rows="5"><?php echo isset($PM['description']) ? htmlspecialchars($PM['description']) : ''; ?></textarea>
                </div>
                <?php if ($fap_is_edit && $fap_edit_produit): ?>
                <?php
                $ident_ro = trim((string) ($fap_edit_produit['identifiant_interne'] ?? ''));
                ?>
                <div class="fap-field">
                    <label for="fap_ident_interne_ro">Identifiant interne</label>
                    <input type="text" id="fap_ident_interne_ro" readonly
                        value="<?php echo $ident_ro !== '' ? htmlspecialchars($ident_ro) : '—'; ?>"
                        style="background:var(--blanc-neige,#f5f5f5);cursor:default;">
                </div>
                <?php endif; ?>
                <?php if ($fap_use_category_hierarchy): ?>
                    <?php require __DIR__ . '/inc_vendeur_category_fields.php'; ?>
                <?php else: ?>
                <div class="fap-field">
                    <label for="categorie_id">Catégorie <span class="required">*</span></label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ((isset($PM['categorie_id']) && (string) $PM['categorie_id'] === (string) $c['id']) || ($categorie_id_prefill > 0 && (int) $c['id'] === $categorie_id_prefill)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Aucune catégorie disponible</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$categories || count($categories) == 0): ?>
                    <small class="fap-hint fap-hint-warn">
                        <i class="fas fa-exclamation-triangle"></i>
                        <a href="../categories/ajouter.php" class="link-accent">Créer une catégorie</a>
                    </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="fap-card">
                <div class="fap-card-head">
                    <i class="fas fa-tag"></i>
                    <h3>Prix &amp; stock</h3>
                </div>
                <div class="fap-row-3">
                    <div class="fap-field">
                        <label for="prix">Prix (FCFA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" step="0.01" min="0" required placeholder="0"
                            value="<?php echo isset($PM['prix']) ? htmlspecialchars($PM['prix']) : ''; ?>">
                    </div>
                    <div class="fap-field">
                        <label for="prix_promotion">Prix promo (FCFA)</label>
                        <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0" placeholder="Optionnel"
                            value="<?php echo isset($PM['prix_promotion']) ? htmlspecialchars($PM['prix_promotion']) : ''; ?>">
                    </div>
                    <div class="fap-field">
                        <label for="stock">Stock <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="0" required placeholder="0"
                            value="<?php echo isset($PM['stock']) ? htmlspecialchars($PM['stock']) : '0'; ?>">
                    </div>
                </div>
                <div class="fap-field">
                    <label for="prix_negociable">Prix n&eacute;gociable</label>
                    <select id="prix_negociable" name="prix_negociable">
                        <option value="1" <?php echo $pm_prix_negociable === 1 ? 'selected' : ''; ?>>Oui</option>
                        <option value="0" <?php echo $pm_prix_negociable === 0 ? 'selected' : ''; ?>>Non</option>
                    </select>
                </div>
            </div>

            <div class="fap-card" id="fap-attrs-card"<?php echo $fap_attrs_card_hidden ? ' hidden' : ''; ?>>
                <div class="fap-card-head">
                    <i class="fas fa-sliders-h"></i>
                    <h3>Poids, taille, mesures &amp; couleurs</h3>
                </div>
                <div class="fap-field" data-fap-attr="poids">
                    <label>Poids disponibles</label>
                    <div class="options-add-block options-with-surcharge">
                        <div class="options-add-row">
                            <input type="text" id="poids-input" placeholder="Ex. 500g, 1kg" class="options-input">
                            <input type="number" id="poids-surcharge" placeholder="+ FCFA" min="0" step="1" class="options-surcharge" title="Montant à ajouter au prix">
                            <button type="button" class="btn-add-option" id="btn-add-poids">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                        <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                        <input type="hidden" name="poids" id="poids-hidden"
                            value="<?php echo isset($PM['poids']) ? htmlspecialchars($PM['poids']) : ''; ?>">
                    </div>
                </div>

                <div class="fap-field" data-fap-attr="taille">
                    <label>Tailles disponibles</label>
                    <div class="options-add-block options-with-surcharge">
                        <div class="options-add-row">
                            <input type="text" id="taille-input" placeholder="Ex. S, M, L, XL" class="options-input">
                            <input type="number" id="taille-surcharge" placeholder="+ FCFA" min="0" step="1" class="options-surcharge" title="Montant à ajouter au prix">
                            <button type="button" class="btn-add-option" id="btn-add-taille">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                        <div id="taille-list" class="options-tags-list options-tags-with-surcharge"></div>
                        <input type="hidden" name="taille" id="taille-hidden"
                            value="<?php echo isset($PM['taille']) ? htmlspecialchars($PM['taille']) : ''; ?>">
                    </div>
                </div>

                <div class="fap-field" data-fap-attr="mesure">
                    <div class="fap-row-2">
                        <div class="fap-field" style="margin-bottom:0;">
                            <label for="unite">Unité par défaut</label>
                            <select id="unite" name="unite">
                                <option value="unité" <?php echo ($pm_unite === 'unité') ? 'selected' : ''; ?>>Unité</option>
                                <option value="kg" <?php echo ($pm_unite === 'kg') ? 'selected' : ''; ?>>Kilogramme</option>
                                <option value="g" <?php echo ($pm_unite === 'g') ? 'selected' : ''; ?>>Gramme</option>
                                <option value="L" <?php echo ($pm_unite === 'L') ? 'selected' : ''; ?>>Litre</option>
                            </select>
                        </div>
                        <div class="fap-field" style="margin-bottom:0;">
                            <label for="mesure">Mesures / dimensions</label>
                            <input type="text" id="mesure" name="mesure" maxlength="500" placeholder="Ex. 30 × 40 cm, 2 L…"
                                value="<?php echo htmlspecialchars($pm_mesure, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>

                <div class="fap-field" data-fap-attr="couleur">
                    <label>Couleurs (optionnel)</label>
                    <div class="couleurs-picker-block">
                        <div class="couleurs-add-row">
                            <input type="color" id="couleur-input" value="#3564a6" title="Choisir une couleur">
                            <button type="button" class="btn-add-couleur" id="btn-add-couleur">
                                <i class="fas fa-plus"></i> Ajouter cette couleur
                            </button>
                        </div>
                        <div id="couleurs-list" class="couleurs-swatches"></div>
                        <input type="hidden" name="couleurs" id="couleurs-hidden"
                            value="<?php echo isset($PM['couleurs']) ? htmlspecialchars($PM['couleurs']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="fap-column fap-column-side">
            <div class="fap-card fap-card-images">
                <div class="fap-card-head">
                    <i class="fas fa-images"></i>
                    <h3>Visuels</h3>
                </div>
                <?php if (!$fap_is_edit): ?>
                <div class="fap-field">
                    <label>Images <span class="required">*</span></label>
                    <div class="fap-dropzone file-input-wrapper file-input-single" data-trigger="images_produit">
                        <input type="file" id="images_produit" name="images_produit[]" accept="image/*" multiple required class="file-input fap-file-native">
                        <div class="fap-dropzone-inner">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Glissez-déposez ou cliquez pour choisir</span>
                            <small>JPG, PNG, WEBP, GIF — plusieurs fichiers possibles</small>
                        </div>
                    </div>
                    <div id="preview-images" class="image-preview-accumulator"></div>
                </div>
                <?php else: ?>
                <div class="fap-field">
                    <label>Images <span class="required">*</span></label>
                    <p class="fap-hint">Conservez au moins une image. La 1<sup>ère</sup> reste la photo principale. Utilisez &times; pour retirer une image existante avant enregistrement.</p>
                    <div id="fap-existing-gallery" class="fap-existing-gallery image-preview-accumulator">
                        <?php foreach ($fap_existing_image_paths as $idx => $img_path): ?>
                        <div class="preview-item fap-thumb-existing" data-path="<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="images_to_keep[]" value="<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="preview-badge"><?php echo (int) $idx === 0 ? 'Principale' : (string) ((int) $idx + 1); ?></span>
                            <button type="button" class="preview-remove fap-remove-existing-img" title="Retirer">&times;</button>
                            <img src="/upload/<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                onerror="this.src='/image/produit1.jpg'">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="fap-hint" style="margin-top:8px;">Ajouter des images à la galerie</p>
                    <div class="fap-dropzone file-input-wrapper file-input-single" data-trigger="images_supplementaires">
                        <input type="file" id="images_supplementaires" name="images_supplementaires[]" accept="image/*" multiple class="file-input fap-file-native">
                        <div class="fap-dropzone-inner">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Glissez-déposez ou cliquez pour ajouter</span>
                            <small>JPG, PNG, WEBP, GIF — plusieurs fichiers possibles</small>
                        </div>
                    </div>
                    <div id="preview-images-supp" class="image-preview-accumulator"></div>
                </div>
                <?php endif; ?>
                <?php if ($add_produit_modal): ?>
                <input type="hidden" name="statut" value="actif">
                <?php elseif (isset($PM['statut']) && $PM['statut'] === 'bloque'): ?>
                <input type="hidden" name="statut" value="bloque">
                <div class="fap-field">
                    <label>Visibilité</label>
                    <p class="fap-hint" style="color:#b45309;margin:0;"><i class="fas fa-ban"></i> Bloqué par la plateforme — modifiez le nom ou l’image indiqué(s) pour rétablir la visibilité.</p>
                </div>
                <?php else: ?>
                <div class="fap-field">
                    <label for="statut">Visibilité</label>
                    <select id="statut" name="statut">
                        <option value="actif" <?php echo (!isset($PM['statut']) || $PM['statut'] === 'actif') ? 'selected' : ''; ?>>Actif (visible en boutique)</option>
                        <option value="inactif" <?php echo (isset($PM['statut']) && $PM['statut'] === 'inactif') ? 'selected' : ''; ?>>Inactif (masqué)</option>
                        <?php if ($fap_is_edit): ?>
                        <option value="rupture_stock" <?php echo (isset($PM['statut']) && $PM['statut'] === 'rupture_stock') ? 'selected' : ''; ?>>Rupture de stock</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="fap-card fap-card-variantes">
                <div class="fap-card-head">
                    <i class="fas fa-layer-group"></i>
                    <div class="fap-card-head-text">
                        <h3>Variantes</h3>
                    </div>
                </div>
                <div id="fap-variantes-list-wrap" class="fap-variantes-list-wrap" hidden>
                    <p class="fap-variantes-list-label"><i class="fas fa-list-ul"></i> Variantes ajoutées</p>
                    <div id="fap-variantes-cards" class="fap-variantes-cards" aria-live="polite"></div>
                </div>
                <button type="button" class="btn-fap-add-variante" id="btn-fap-open-variante-modal">
                    <span class="btn-fap-add-variante-icon"><i class="fas fa-plus"></i></span>
                    <span class="btn-fap-add-variante-text">
                        <strong>Ajouter une variante</strong>
                        <small>Ouvre le formulaire dans une fenêtre</small>
                    </span>
                </button>
                <div id="fap-variantes-mount" class="fap-variantes-mount" aria-hidden="true"></div>
            </div>
        </div>
    </div>

    <div class="fap-actions">
        <button type="submit" class="btn-fap-submit">
            <i class="fas fa-check"></i> <?php echo $fap_is_edit ? 'Enregistrer les modifications' : 'Enregistrer le produit'; ?>
        </button>
        <?php if ($fap_is_edit && $fap_edit_modal): ?>
        <button type="button" class="btn-fap-cancel" id="btn-fap-cancel-edit-modal" data-close-edit-modal>Annuler</button>
        <?php elseif ($fap_is_edit): ?>
        <a href="index.php" class="btn-fap-cancel">Annuler</a>
        <?php elseif ($add_produit_modal): ?>
        <button type="button" class="btn-fap-cancel" id="btn-fap-cancel-modal" data-close-modal>Annuler</button>
        <?php elseif ($categorie_id_prefill > 0): ?>
        <a href="../categories/produits.php?id=<?php echo (int) $categorie_id_prefill; ?>" class="btn-fap-cancel">Annuler</a>
        <?php else: ?>
        <a href="../stock/index.php" class="btn-fap-cancel">Annuler</a>
        <?php endif; ?>
    </div>
</form>

<div class="fap-variante-modal" id="fapVarianteModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fapVarianteModalTitle">
    <div class="fap-variante-modal-backdrop" data-close-variante-modal tabindex="-1"></div>
    <div class="fap-variante-modal-panel">
        <div class="fap-variante-modal-head">
            <h3 id="fapVarianteModalTitle">Nouvelle variante</h3>
            <button type="button" class="fap-icon-close" data-close-variante-modal aria-label="Fermer">&times;</button>
        </div>
        <div class="fap-variante-modal-body">
            <div class="fap-field">
                <label for="fap-vm-nom">Nom <span class="required">*</span></label>
                <input type="text" id="fap-vm-nom" placeholder="Ex. Lot de 4 pièces" autocomplete="off">
            </div>
            <div class="fap-row-2">
                <div class="fap-field">
                    <label for="fap-vm-prix">Prix (FCFA) <span class="required">*</span></label>
                    <input type="number" id="fap-vm-prix" min="0" step="0.01" placeholder="0">
                </div>
                <div class="fap-field">
                    <label for="fap-vm-promo">Prix promo</label>
                    <input type="number" id="fap-vm-promo" min="0" step="0.01" placeholder="Optionnel">
                </div>
            </div>
            <div class="fap-field">
                <label for="fap-vm-image">Image (optionnel)</label>
                <input type="file" id="fap-vm-image" accept="image/*" class="fap-vm-file">
            </div>
            <p class="fap-hint">La variante n’est acceptée qu’avec un nom et un prix &gt; 0.</p>
        </div>
        <div class="fap-variante-modal-foot">
            <button type="button" class="btn-fap-cancel" data-close-variante-modal>Annuler</button>
            <button type="button" class="btn-fap-submit" id="fap-vm-valider"><i class="fas fa-check"></i> Valider</button>
        </div>
    </div>
</div>

<style>
.form-add-produit-v2 { margin: 0; }
.fap-alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
    font-size: 14px; line-height: 1.45;
}
.fap-alert-error {
    background: linear-gradient(135deg, rgba(194, 102, 56, 0.12), rgba(107, 47, 32, 0.08));
    border: 1px solid rgba(194, 102, 56, 0.45);
    color: #4a2418;
}
.fap-alert-msg { flex: 1; min-width: 0; }
.fap-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 22px;
}
@media (min-width: 1100px) {
    .fap-layout { grid-template-columns: 1.15fr 0.85fr; align-items: start; }
}
.fap-card {
    background: var(--blanc, #fff);
    border: 1px solid rgba(53, 100, 166, 0.16);
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: var(--ombre-douce, 0 6px 28px rgba(53, 100, 166, 0.07));
    margin-bottom: 20px;
}
.fap-card-head {
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 2px solid rgba(53, 100, 166, 0.15);
}
.fap-card-head i { color: var(--couleur-dominante, #3564a6); font-size: 1.2rem; margin-top: 2px; }
.fap-card-head-text { flex: 1; min-width: 0; }
.fap-card-head h3 { margin: 0; font-size: 1.08rem; font-weight: 700; color: var(--titres, #0d0d0d); }
.fap-card-sub { display: block; font-size: 12px; color: var(--gris-moyen, #737373); font-weight: 500; margin-top: 4px; line-height: 1.35; }
.fap-hint { font-size: 13px; color: #5a5a5a; margin: 0 0 14px; line-height: 1.5; }
.fap-hint-warn { color: #8a4a16; }
.fap-field { margin-bottom: 18px; }
.fap-field label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 7px; color: #333; }
.fap-field input[type=text],
.fap-field input[type=number],
.fap-field input[type=file],
.fap-field select,
.fap-field textarea {
    width: 100%; padding: 12px 14px; border: 1.5px solid rgba(0,0,0,0.12);
    border-radius: 10px; font-size: 15px; font-family: inherit;
    background: #fafbfc; transition: border-color .2s, box-shadow .2s;
}
.fap-field textarea { min-height: 120px; resize: vertical; }
.fap-field input:focus, .fap-field select:focus, .fap-field textarea:focus {
    outline: none;
    border-color: var(--couleur-dominante, #3564a6);
    box-shadow: 0 0 0 3px var(--focus-ring, rgba(53, 100, 166, 0.15));
    background: var(--blanc, #fff);
}
.fap-row-3 { display: grid; grid-template-columns: 1fr; gap: 14px; }
@media (min-width: 640px) { .fap-row-3 { grid-template-columns: repeat(3, 1fr); } }
.fap-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.fap-dropzone {
    border: 2px dashed rgba(53, 100, 166, 0.35);
    border-radius: 14px;
    background: linear-gradient(180deg, #f8faff 0%, #fff 100%);
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    overflow: hidden;
}
.fap-dropzone:hover { border-color: #918a44; background: #fffef8; }
.fap-file-native {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; font-size: 0; width: 100%; height: 100%;
}
.fap-dropzone-inner {
    pointer-events: none;
    text-align: center; padding: 28px 16px;
}
.fap-dropzone-inner i { font-size: 2rem; color: #3564a6; margin-bottom: 8px; display: block; }
.fap-dropzone-inner span { font-weight: 600; color: #333; display: block; margin-bottom: 4px; }
.fap-dropzone-inner small { color: #777; font-size: 12px; }
.fap-variantes-list-wrap { margin-bottom: 16px; }
.fap-variantes-list-wrap[hidden] { display: none !important; }
.fap-variantes-list-label {
    font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--couleur-dominante, #3564a6); margin: 0 0 10px; display: flex; align-items: center; gap: 8px;
}
.fap-variantes-cards { display: flex; flex-direction: column; gap: 12px; min-height: 0; margin-bottom: 0; }
.fap-variante-chip {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(145, 138, 68, 0.35);
    background: linear-gradient(135deg, #fffef9, #fff);
}
.fap-variante-chip-thumb {
    width: 56px; height: 56px; border-radius: 10px; object-fit: cover;
    background: #eee; flex-shrink: 0;
}
.fap-variante-chip-body { flex: 1; min-width: 0; }
.fap-variante-chip-title { font-weight: 700; color: var(--titres, #0d0d0d); margin: 0 0 4px; font-size: 15px; }
.fap-variante-chip-meta { margin: 0; font-size: 13px; color: #555; }
.fap-variante-chip-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.fap-variante-chip-actions button {
    padding: 8px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 12px; font-weight: 600;
}
.fap-btn-mod-variante { background: linear-gradient(135deg, var(--couleur-dominante, #3564a6), var(--bleu-fonce, #2d5690)); color: var(--texte-clair, #fff); }
.fap-btn-del-variante { background: rgba(255, 107, 53, 0.12); color: var(--orange-fonce, #E85A2A); border: 1px solid rgba(255, 107, 53, 0.35); }
.btn-fap-add-variante {
    width: 100%;
    display: inline-flex; align-items: center; justify-content: flex-start; gap: 16px;
    padding: 18px 20px;
    border: 2px solid rgba(53, 100, 166, 0.22);
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(53, 100, 166, 0.08) 0%, rgba(255, 255, 255, 0.95) 55%, rgba(145, 138, 68, 0.06) 100%);
    color: var(--titres, #0d0d0d);
    font-weight: 600; font-size: 15px; cursor: pointer;
    transition: transform .15s, border-color .2s, box-shadow .2s, background .2s;
    text-align: left;
}
.btn-fap-add-variante-icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: var(--couleur-dominante, #3564a6);
    color: var(--texte-clair, #fff);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(53, 100, 166, 0.35);
}
.btn-fap-add-variante-text { display: flex; flex-direction: column; gap: 2px; }
.btn-fap-add-variante-text strong { font-size: 1.02rem; color: var(--titres, #0d0d0d); }
.btn-fap-add-variante-text small { font-size: 12px; font-weight: 500; color: var(--gris-moyen, #737373); }
.btn-fap-add-variante:hover {
    border-color: var(--couleur-dominante, #3564a6);
    box-shadow: var(--ombre-promo, 0 8px 28px rgba(53, 100, 166, 0.2));
    transform: translateY(-2px);
}
.btn-fap-add-variante:focus {
    outline: none;
    box-shadow: 0 0 0 3px var(--focus-ring, rgba(53, 100, 166, 0.15));
}
.fap-variantes-mount {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); border: 0;
}
.fap-actions {
    display: flex; flex-wrap: wrap; align-items: center; gap: 14px;
    margin-top: 28px; padding-top: 22px;
    border-top: 1px solid rgba(0,0,0,0.08);
}
.form-add-produit-modal .fap-actions,
.form-edit-produit-modal .fap-actions {
    padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
}
@media (max-width: 1024px) {
    .form-add-produit-modal .fap-actions,
    .form-edit-produit-modal .fap-actions {
        position: sticky;
        bottom: 0;
        z-index: 5;
        margin-bottom: 0;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, #fff 28%);
        box-shadow: 0 -12px 28px rgba(13, 13, 13, 0.1);
        padding-bottom: calc(14px + env(safe-area-inset-bottom, 0px));
    }
}
.btn-fap-submit {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 14px 28px; border: none; border-radius: 12px;
    background: linear-gradient(135deg, var(--couleur-dominante, #3564a6), var(--bleu-fonce, #2d5690));
    color: var(--texte-clair, #fff); font-weight: 700; font-size: 16px; cursor: pointer;
    box-shadow: 0 4px 18px rgba(53, 100, 166, 0.35);
}
.btn-fap-submit:hover { filter: brightness(1.06); }
.btn-fap-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 22px; border-radius: 12px;
    background: var(--blanc, #fff); border: 2px solid rgba(53, 100, 166, 0.28);
    color: var(--couleur-dominante, #3564a6); font-weight: 600; text-decoration: none; cursor: pointer; font-size: 15px;
}
.fap-variante-modal {
    position: fixed; inset: 0; z-index: 10050;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.fap-variante-modal[hidden] { display: none !important; }
.fap-variante-modal-backdrop { position: absolute; inset: 0; background: rgba(13, 13, 13, 0.45); backdrop-filter: blur(3px); }
.fap-variante-modal-panel {
    position: relative; z-index: 1;
    width: 100%; max-width: 440px;
    background: #fff; border-radius: 18px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    border: 1px solid rgba(145, 138, 68, 0.25);
    overflow: hidden;
}
.fap-variante-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; background: linear-gradient(135deg, rgba(53,100,166,0.08), rgba(145,138,68,0.08));
    border-bottom: 1px solid rgba(0,0,0,0.06);
}
.fap-variante-modal-head h3 { margin: 0; font-size: 1.1rem; color: var(--titres, #0d0d0d); font-family: var(--font-titres, inherit); }
.fap-icon-close {
    width: 36px; height: 36px; border: none; border-radius: 10px;
    background: rgba(0,0,0,0.06); font-size: 22px; line-height: 1; cursor: pointer; color: #333;
}
.fap-variante-modal-body { padding: 22px; }
.fap-variante-modal-foot {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 16px 22px; border-top: 1px solid #eee; background: #fafafa;
}
.fap-card-variantes { position: relative; }
/* réutiliser styles options / couleurs / preview de l'ancienne page */
.file-input-single { cursor: pointer; }
.image-preview-accumulator { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 15px; }
.image-preview-accumulator .preview-item { position: relative; }
.image-preview-accumulator .preview-item img {
    width: 90px; height: 90px; object-fit: cover; border-radius: 8px;
    border: 2px solid rgba(53, 100, 166, 0.3); display: block;
}
.image-preview-accumulator .preview-item .preview-badge {
    position: absolute; top: 4px; left: 4px;
    background: var(--couleur-dominante, #3564a6); color: #fff; font-size: 10px;
    padding: 2px 6px; border-radius: 4px;
}
.image-preview-accumulator .preview-item .preview-remove {
    position: absolute; top: 4px; right: 4px; width: 22px; height: 22px;
    border: none; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%;
    cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; padding: 0;
}
.image-preview-accumulator .preview-item .preview-remove:hover { background: #c00; }
.fap-existing-gallery .fap-thumb-existing { position: relative; }
.couleurs-picker-block { margin-top: 8px; }
.couleurs-add-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.couleurs-add-row input[type="color"] { width: 50px; height: 40px; padding: 2px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; }
.btn-add-couleur {
    padding: 12px 18px; background: #918a44; color: #fff; border: none; border-radius: 8px;
    cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;
}
.btn-add-couleur:hover { background: #7a7340; }
.couleurs-swatches { display: flex; flex-wrap: wrap; gap: 10px; padding: 10px 0; }
.couleur-swatch {
    display: flex; align-items: center; gap: 6px; padding: 6px 10px;
    background: #f5f5f5; border-radius: 20px; border: 2px solid #ddd;
}
.couleur-swatch .swatch-preview { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #333; }
.couleur-swatch .swatch-hex { font-size: 12px; color: #333; }
.couleur-swatch .swatch-remove {
    width: 24px; height: 24px; border: none; background: #c00; color: #fff;
    border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; padding: 0;
}
.options-add-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.options-input { flex: 1; min-width: 150px; padding: 10px 14px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; }
.btn-add-option {
    padding: 10px 16px; background: #918a44; color: #fff; border: none; border-radius: 8px;
    cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;
}
.options-tags-list { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 0; }
.option-tag {
    display: flex; align-items: center; gap: 6px; padding: 6px 12px;
    background: #f5f5f5; border-radius: 20px; border: 2px solid #ddd; font-size: 13px;
}
.option-tag .tag-remove {
    width: 22px; height: 22px; border: none; background: #c00; color: #fff;
    border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; padding: 0;
}
.options-surcharge { width: 90px; padding: 8px 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px; }
.option-tag .tag-surcharge { font-size: 11px; color: #666; margin-left: 4px; }

/* ---- Responsive formulaire produit ---- */
@media (max-width: 1024px) {
    .fap-layout { gap: 14px; }
    .fap-card {
        padding: 16px 14px;
        margin-bottom: 14px;
        border-radius: 12px;
    }
    .fap-card-head { margin-bottom: 12px; padding-bottom: 10px; gap: 8px; }
    .fap-card-head i { font-size: 1rem; }
    .fap-card-head h3 { font-size: 0.95rem; }
    .fap-card-sub { font-size: 11px; }
    .fap-hint { font-size: 12px; margin-bottom: 10px; }
    .fap-field { margin-bottom: 14px; }
    .fap-field label { font-size: 12px; margin-bottom: 5px; }
    .fap-field input[type=text],
    .fap-field input[type=number],
    .fap-field input[type=file],
    .fap-field select,
    .fap-field textarea {
        padding: 10px 11px;
        font-size: 14px;
        border-radius: 8px;
    }
    .fap-field textarea { min-height: 100px; }
    .fap-row-3 { gap: 10px; }
    .fap-dropzone-inner { padding: 20px 12px; }
    .fap-dropzone-inner i { font-size: 1.5rem; margin-bottom: 6px; }
    .fap-dropzone-inner span { font-size: 0.88rem; }
    .fap-dropzone-inner small { font-size: 11px; }
    .btn-fap-add-variante { padding: 12px 14px; gap: 10px; border-radius: 12px; }
    .btn-fap-add-variante-icon { width: 40px; height: 40px; font-size: 1rem; border-radius: 10px; }
    .btn-fap-add-variante-text strong { font-size: 0.9rem; }
    .btn-fap-add-variante-text small { font-size: 11px; }
    .btn-fap-add-variante { font-size: 13px; }
    .fap-actions { margin-top: 18px; padding-top: 14px; gap: 10px; }
    .btn-fap-submit { padding: 11px 20px; font-size: 14px; border-radius: 10px; gap: 7px; }
    .btn-fap-cancel { padding: 9px 16px; font-size: 13px; border-radius: 10px; }
    .fap-variante-chip { padding: 10px 12px; gap: 10px; }
    .fap-variante-chip-thumb { width: 44px; height: 44px; }
    .fap-variante-chip-title { font-size: 13px; }
    .fap-variante-chip-meta { font-size: 12px; }
    .options-input { padding: 8px 10px; font-size: 13px; min-width: 0; }
    .btn-add-option, .btn-add-couleur { padding: 8px 12px; font-size: 12px; }
    .options-surcharge { width: 76px; padding: 6px 8px; font-size: 12px; }
    .option-tag { padding: 4px 9px; font-size: 12px; }
    .couleurs-add-row input[type="color"] { width: 42px; height: 34px; }
    .fap-alert { padding: 10px 12px; font-size: 12px; margin-bottom: 14px; }
    .image-preview-accumulator .preview-item img { width: 72px; height: 72px; }
    .fap-variante-modal { padding: 12px; }
    .fap-variante-modal-panel { max-width: 100%; border-radius: 14px; }
    .fap-variante-modal-head { padding: 12px 14px; }
    .fap-variante-modal-head h3 { font-size: 0.95rem; }
    .fap-variante-modal-body { padding: 14px; }
    .fap-variante-modal-foot { padding: 12px 14px; }
    .fap-icon-close { width: 32px; height: 32px; font-size: 18px; }
}

@media (max-width: 640px) {
    .fap-layout { gap: 10px; }
    .fap-card {
        padding: 11px 10px;
        margin-bottom: 10px;
        border-radius: 10px;
        box-shadow: 0 3px 14px rgba(53, 100, 166, 0.06);
    }
    .fap-card-head { margin-bottom: 8px; padding-bottom: 8px; gap: 6px; }
    .fap-card-head i { font-size: 0.9rem; margin-top: 1px; }
    .fap-card-head h3 { font-size: 0.84rem; }
    .fap-card-sub { font-size: 10px; margin-top: 2px; }
    .fap-hint { font-size: 11px; margin-bottom: 8px; line-height: 1.4; }
    .fap-field { margin-bottom: 10px; }
    .fap-field label { font-size: 11px; margin-bottom: 4px; }
    .fap-field input[type=text],
    .fap-field input[type=number],
    .fap-field input[type=file],
    .fap-field select,
    .fap-field textarea {
        padding: 7px 9px;
        font-size: 13px;
        border-radius: 7px;
        border-width: 1px;
    }
    .fap-field textarea { min-height: 76px; }
    .fap-row-2 { grid-template-columns: 1fr; gap: 8px; }
    .fap-row-3 { gap: 8px; }
    .fap-dropzone { border-radius: 10px; }
    .fap-dropzone-inner { padding: 14px 10px; }
    .fap-dropzone-inner i { font-size: 1.25rem; margin-bottom: 4px; }
    .fap-dropzone-inner span { font-size: 0.8rem; }
    .fap-dropzone-inner small { font-size: 10px; }
    .btn-fap-add-variante {
        padding: 10px 12px;
        gap: 8px;
        font-size: 12px;
        border-radius: 10px;
        border-width: 1.5px;
    }
    .btn-fap-add-variante-icon {
        width: 34px;
        height: 34px;
        font-size: 0.9rem;
        border-radius: 8px;
    }
    .btn-fap-add-variante-text strong { font-size: 0.82rem; }
    .btn-fap-add-variante-text small { font-size: 10px; }
    .btn-fap-add-variante:hover { transform: none; }
    .fap-actions {
        flex-direction: column;
        align-items: stretch;
        margin-top: 14px;
        padding-top: 12px;
        gap: 8px;
    }
    .btn-fap-submit,
    .btn-fap-cancel {
        width: 100%;
        justify-content: center;
        padding: 9px 14px;
        font-size: 13px;
        border-radius: 8px;
    }
    .fap-variantes-list-label { font-size: 10px; margin-bottom: 6px; }
    .fap-variante-chip { padding: 8px 10px; gap: 8px; border-radius: 8px; }
    .fap-variante-chip-thumb { width: 38px; height: 38px; border-radius: 7px; }
    .fap-variante-chip-title { font-size: 12px; }
    .fap-variante-chip-meta { font-size: 11px; }
    .fap-variante-chip-actions button { padding: 6px 9px; font-size: 11px; }
    .options-add-row, .couleurs-add-row { gap: 8px; margin-bottom: 8px; }
    .options-input { padding: 7px 9px; font-size: 12px; flex: 1 1 100%; }
    .btn-add-option, .btn-add-couleur {
        padding: 7px 10px;
        font-size: 11px;
        gap: 5px;
        border-radius: 7px;
    }
    .options-surcharge { width: 100%; max-width: 120px; padding: 6px 8px; font-size: 11px; }
    .option-tag { padding: 4px 8px; font-size: 11px; gap: 4px; }
    .option-tag .tag-remove, .couleur-swatch .swatch-remove { width: 18px; height: 18px; font-size: 11px; }
    .couleur-swatch { padding: 4px 8px; gap: 4px; }
    .couleur-swatch .swatch-preview { width: 18px; height: 18px; }
    .couleur-swatch .swatch-hex { font-size: 10px; }
    .couleurs-add-row input[type="color"] { width: 36px; height: 30px; }
    .fap-alert { padding: 8px 10px; font-size: 11px; border-radius: 8px; margin-bottom: 10px; }
    .image-preview-accumulator { gap: 8px; margin-top: 10px; }
    .image-preview-accumulator .preview-item img { width: 60px; height: 60px; border-radius: 6px; }
    .fap-variante-modal { padding: 8px; align-items: flex-end; }
    .fap-variante-modal-panel { border-radius: 12px 12px 0 0; max-width: 100%; }
    .fap-variante-modal-head { padding: 10px 12px; }
    .fap-variante-modal-head h3 { font-size: 0.88rem; }
    .fap-variante-modal-body { padding: 12px; }
    .fap-variante-modal-foot {
        flex-direction: column-reverse;
        align-items: stretch;
        padding: 10px 12px;
        gap: 8px;
    }
    .fap-variante-modal-foot .btn-fap-submit,
    .fap-variante-modal-foot .btn-fap-cancel { width: 100%; }
}

@media (max-width: 380px) {
    .fap-card { padding: 9px 8px; }
    .fap-card-head h3 { font-size: 0.8rem; }
    .fap-field input[type=text],
    .fap-field input[type=number],
    .fap-field select,
    .fap-field textarea { font-size: 12px; padding: 6px 8px; }
    .btn-fap-submit, .btn-fap-cancel { font-size: 12px; padding: 8px 12px; }
}
</style>

<script>
(function () {
    var formId = <?php echo json_encode($form_el_id); ?>;
    var form = document.getElementById(formId);
    if (!form) return;

    var fapIsEdit = <?php echo $fap_is_edit ? 'true' : 'false'; ?>;

    if (fapIsEdit) {
        var suppInput = document.getElementById('images_supplementaires');
        var suppContainer = document.getElementById('preview-images-supp');
        var existGal = document.getElementById('fap-existing-gallery');
        var accumulatedSupp = [];
        function countKept() {
            return existGal ? existGal.querySelectorAll('input[name="images_to_keep[]"]').length : 0;
        }
        function updateSuppFiles() {
            if (!suppInput) return;
            var dt = new DataTransfer();
            for (var s = 0; s < accumulatedSupp.length; s++) { dt.items.add(accumulatedSupp[s]); }
            suppInput.files = dt.files;
        }
        function addSuppPreviews(newFiles) {
            if (!suppContainer || !suppInput) return;
            for (var i = 0; i < newFiles.length; i++) {
                (function (file) {
                    if (!file.type.match('image.*')) return;
                    var pos = accumulatedSupp.length;
                    accumulatedSupp.push(file);
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var div = document.createElement('div');
                        div.className = 'preview-item';
                        div.dataset.suppIndex = String(pos);
                        var badge = document.createElement('span');
                        badge.className = 'preview-badge';
                        badge.textContent = 'Nouvelle';
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Aperçu';
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'preview-remove';
                        btn.innerHTML = '&times;';
                        btn.title = 'Retirer';
                        btn.onclick = function (ev) {
                            ev.preventDefault(); ev.stopPropagation();
                            var idx = parseInt(div.dataset.suppIndex, 10);
                            accumulatedSupp.splice(idx, 1);
                            div.remove();
                            for (var j = 0; j < suppContainer.children.length; j++) {
                                suppContainer.children[j].dataset.suppIndex = String(j);
                            }
                            updateSuppFiles();
                        };
                        div.appendChild(badge);
                        div.appendChild(img);
                        div.appendChild(btn);
                        suppContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                })(newFiles[i]);
            }
            updateSuppFiles();
        }
        if (suppInput && suppContainer) {
            suppInput.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    var nf = [];
                    for (var i = 0; i < this.files.length; i++) nf.push(this.files[i]);
                    addSuppPreviews(nf);
                }
            });
        }
        if (existGal) {
            existGal.addEventListener('click', function (e) {
                var r = e.target.closest('.fap-remove-existing-img');
                if (r) {
                    e.preventDefault();
                    var thumb = r.closest('.fap-thumb-existing');
                    if (thumb) thumb.remove();
                }
            });
        }
        form.addEventListener('submit', function (e) {
            if (countKept() + accumulatedSupp.length < 1) {
                e.preventDefault();
                alert('Au moins une image est obligatoire (conservez une image existante ou ajoutez-en une nouvelle).');
                return false;
            }
        });
    } else {
        var input = document.getElementById('images_produit');
        var container = document.getElementById('preview-images');
        var accumulatedFiles = [];
        if (input && container) {
            function updateInputFiles() {
                var dt = new DataTransfer();
                for (var i = 0; i < accumulatedFiles.length; i++) { dt.items.add(accumulatedFiles[i]); }
                input.files = dt.files;
            }
            function addPreviews(newFiles) {
                for (var i = 0; i < newFiles.length; i++) {
                    (function (file) {
                        if (!file.type.match('image.*')) return;
                        var pos = accumulatedFiles.length;
                        accumulatedFiles.push(file);
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            var div = document.createElement('div');
                            div.className = 'preview-item';
                            div.dataset.index = pos;
                            var badge = document.createElement('span');
                            badge.className = 'preview-badge';
                            badge.textContent = pos === 0 ? 'Principale' : String(pos + 1);
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Aperçu';
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'preview-remove';
                            btn.innerHTML = '&times;';
                            btn.title = 'Retirer';
                            btn.onclick = function (ev) {
                                ev.preventDefault(); ev.stopPropagation();
                                var idx = parseInt(div.dataset.index, 10);
                                accumulatedFiles.splice(idx, 1);
                                div.remove();
                                for (var j = 0; j < container.children.length; j++) {
                                    container.children[j].dataset.index = j;
                                    container.children[j].querySelector('.preview-badge').textContent =
                                        j === 0 ? 'Principale' : String(j + 1);
                                }
                                updateInputFiles();
                            };
                            div.appendChild(badge);
                            div.appendChild(img);
                            div.appendChild(btn);
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    })(newFiles[i]);
                }
                updateInputFiles();
            }
            input.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    var nf = []; for (var ii = 0; ii < this.files.length; ii++) nf.push(this.files[ii]);
                    addPreviews(nf);
                }
            });
            form.addEventListener('submit', function (e) {
                if (accumulatedFiles.length === 0) {
                    e.preventDefault();
                    alert('Veuillez ajouter au moins une image.');
                    return false;
                }
            });
        }
    }

    (function couleurs() {
        var couleurInput = document.getElementById('couleur-input');
        var btnAdd = document.getElementById('btn-add-couleur');
        var list = document.getElementById('couleurs-list');
        var hidden = document.getElementById('couleurs-hidden');
        var couleurs = [];
        try {
            if (hidden && hidden.value) {
                var parsed = JSON.parse(hidden.value);
                if (Array.isArray(parsed)) couleurs = parsed;
            }
        } catch (e) {}
        function updateHidden() { if (hidden) hidden.value = JSON.stringify(couleurs); }
        function render() {
            if (!list) return;
            list.innerHTML = '';
            couleurs.forEach(function (hex, i) {
                var div = document.createElement('div');
                div.className = 'couleur-swatch';
                div.innerHTML = '<span class="swatch-preview" style="background:' + hex + '"></span><span class="swatch-hex">' + hex +
                    '</span><button type="button" class="swatch-remove" data-i="' + i + '">&times;</button>';
                list.appendChild(div);
            });
            updateHidden();
        }
        if (btnAdd && couleurInput) {
            btnAdd.addEventListener('click', function () {
                var hex = couleurInput.value;
                if (hex && couleurs.indexOf(hex) === -1) { couleurs.push(hex); render(); }
            });
        }
        if (list) {
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('.swatch-remove');
                if (btn) { couleurs.splice(parseInt(btn.dataset.i, 10), 1); render(); }
            });
        }
        render();
    })();

    (function poids() {
        function initOptionsWithSurcharge(idInput, idSurcharge, idList, idHidden, btnId) {
            var inp = document.getElementById(idInput);
            var surchargeInput = document.getElementById(idSurcharge);
            var list = document.getElementById(idList);
            var hidden = document.getElementById(idHidden);
            var btn = document.getElementById(btnId);
            var values = [];
            try {
                if (hidden && hidden.value && hidden.value !== '[]') {
                    var parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed)) values = parsed;
                }
            } catch (e) {
                if (hidden && hidden.value && hidden.value !== '[]') {
                    values = hidden.value.split(',').map(function (s) { return { v: s.trim(), s: 0 }; })
                        .filter(function (x) { return x.v && x.v !== '[]'; });
                }
            }
            values = values.filter(function (item) {
                var v = typeof item === 'object' ? item.v : item;
                return v && v !== '[]' && String(v).trim() !== '';
            });
            function updateHidden() { if (hidden) hidden.value = JSON.stringify(values); }
            function renderL() {
                if (!list) return;
                list.innerHTML = '';
                values.forEach(function (item, i) {
                    var v = typeof item === 'object' ? item.v : item;
                    var s = typeof item === 'object' ? (item.s || 0) : 0;
                    var surc = s > 0 ? ' <span class="tag-surcharge">+' + s + ' FCFA</span>' : '';
                    var div = document.createElement('div');
                    div.className = 'option-tag';
                    div.innerHTML = '<span>' + String(v).replace(/</g, '&lt;').replace(/>/g, '&gt;') + surc +
                        '</span><button type="button" class="tag-remove" data-i="' + i + '">&times;</button>';
                    list.appendChild(div);
                });
                updateHidden();
            }
            if (btn && inp) {
                btn.addEventListener('click', function () {
                    var val = (inp.value || '').trim();
                    var surc = surchargeInput ? (parseInt(surchargeInput.value, 10) || 0) : 0;
                    if (val) {
                        var exists = values.some(function (x) { return (typeof x === 'object' ? x.v : x) === val; });
                        if (!exists) {
                            values.push({ v: val, s: surc });
                            inp.value = '';
                            if (surchargeInput) surchargeInput.value = '';
                            renderL();
                        }
                    }
                });
                inp.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); btn.click(); }
                });
            }
            if (list) {
                list.addEventListener('click', function (e) {
                    var b = e.target.closest('.tag-remove');
                    if (b) { values.splice(parseInt(b.dataset.i, 10), 1); renderL(); }
                });
            }
            renderL();
        }
        initOptionsWithSurcharge('poids-input', 'poids-surcharge', 'poids-list', 'poids-hidden', 'btn-add-poids');
        initOptionsWithSurcharge('taille-input', 'taille-surcharge', 'taille-list', 'taille-hidden', 'btn-add-taille');
    })();

    <?php if (!empty($fap_attr_conditional)): ?>
    (function () {
        var map = <?php echo json_encode($fap_cg_attr_map, JSON_UNESCAPED_UNICODE); ?>;
        var sel = document.getElementById('categorie_generale_id');
        var attrsCard = document.getElementById('fap-attrs-card');
        function syncAttrsCardVisibility() {
            if (!attrsCard || !sel) return;
            var id = parseInt(sel.value, 10) || 0;
            attrsCard.hidden = id <= 0;
        }
        function syncFapAttrs() {
            var els = document.querySelectorAll('[data-fap-attr]');
            if (!sel) {
                for (var i = 0; i < els.length; i++) els[i].style.display = '';
                return;
            }
            var id = String(parseInt(sel.value, 10) || 0);
            var attr = map[id] || { p: 1, t: 1, m: 1, c: 1 };
            for (var j = 0; j < els.length; j++) {
                var el = els[j];
                var k = el.getAttribute('data-fap-attr');
                var show = true;
                if (k === 'poids') show = !!attr.p;
                else if (k === 'taille') show = !!attr.t;
                else if (k === 'mesure') show = !!attr.m;
                else if (k === 'couleur') show = !!attr.c;
                el.style.display = show ? '' : 'none';
            }
        }
        function syncCategoryAttrs() {
            syncAttrsCardVisibility();
            syncFapAttrs();
        }
        if (sel) sel.addEventListener('change', syncCategoryAttrs);
        syncCategoryAttrs();
    })();
    <?php endif; ?>

    /* Variantes : mémoire + mount pour POST */
    (function variantes() {
        var mount = document.getElementById('fap-variantes-mount');
        var cardsEl = document.getElementById('fap-variantes-cards');
        var listWrap = document.getElementById('fap-variantes-list-wrap');
        var modal = document.getElementById('fapVarianteModal');
        var btnOpen = document.getElementById('btn-fap-open-variante-modal');
        var btnValider = document.getElementById('fap-vm-valider');
        var vNom = document.getElementById('fap-vm-nom');
        var vPrix = document.getElementById('fap-vm-prix');
        var vPromo = document.getElementById('fap-vm-promo');
        var vFile = document.getElementById('fap-vm-image');
        var titleModal = document.getElementById('fapVarianteModalTitle');
        var variantesData = [];
        var editIndex = null;

        function rebuildMount() {
            if (!mount) return;
            mount.innerHTML = '';
            variantesData.forEach(function (v) {
                var w = document.createElement('div');
                w.className = 'fap-v-chunk';
                var hidId = document.createElement('input');
                hidId.type = 'hidden';
                hidId.name = 'variantes_id[]';
                hidId.value = (v.id != null && String(v.id) !== '' && String(v.id) !== '0') ? String(v.id) : '';
                hidId.tabIndex = -1;
                var nom = document.createElement('input');
                nom.type = 'text';
                nom.name = 'variantes_nom[]';
                nom.value = v.nom;
                nom.tabIndex = -1;
                var pr = document.createElement('input');
                pr.type = 'number';
                pr.name = 'variantes_prix[]';
                pr.step = '0.01';
                pr.value = v.prix;
                pr.tabIndex = -1;
                var prm = document.createElement('input');
                prm.type = 'number';
                prm.name = 'variantes_prix_promo[]';
                prm.step = '0.01';
                prm.value = v.promo != null && v.promo !== '' ? v.promo : '';
                prm.tabIndex = -1;
                var fi = document.createElement('input');
                fi.type = 'file';
                fi.name = 'variantes_image[]';
                fi.accept = 'image/*';
                fi.tabIndex = -1;
                if (v.file) {
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(v.file);
                        fi.files = dt.files;
                    } catch (e) {}
                }
                w.appendChild(hidId);
                w.appendChild(nom);
                w.appendChild(pr);
                w.appendChild(prm);
                w.appendChild(fi);
                mount.appendChild(w);
            });
        }

        function renderCards() {
            if (!cardsEl) return;
            cardsEl.innerHTML = '';
            if (listWrap) {
                listWrap.hidden = variantesData.length === 0;
            }
            variantesData.forEach(function (v, idx) {
                var chip = document.createElement('div');
                chip.className = 'fap-variante-chip';
                chip.dataset.index = String(idx);
                var thumb = document.createElement('img');
                thumb.className = 'fap-variante-chip-thumb';
                thumb.alt = '';
                if (v.file) {
                    var r = new FileReader();
                    r.onload = function (e) { thumb.src = e.target.result; };
                    r.readAsDataURL(v.file);
                } else if (v.existingImage) {
                    thumb.src = v.existingImage;
                } else {
                    thumb.style.display = 'none';
                }
                var body = document.createElement('div');
                body.className = 'fap-variante-chip-body';
                var h = document.createElement('p');
                h.className = 'fap-variante-chip-title';
                h.textContent = v.nom;
                var meta = document.createElement('p');
                meta.className = 'fap-variante-chip-meta';
                var promoTxt = (v.promo && parseFloat(v.promo) > 0) ? ' — Promo : ' + v.promo + ' FCFA' : '';
                var imgNote = v.file ? '' : (v.existingImage ? ' — image enregistrée' : ' — sans image');
                meta.textContent = 'Prix : ' + v.prix + ' FCFA' + promoTxt + imgNote;
                body.appendChild(h);
                body.appendChild(meta);
                var actions = document.createElement('div');
                actions.className = 'fap-variante-chip-actions';
                var bMod = document.createElement('button');
                bMod.type = 'button';
                bMod.className = 'fap-btn-mod-variante';
                bMod.textContent = 'Modifier';
                bMod.dataset.idx = String(idx);
                var bDel = document.createElement('button');
                bDel.type = 'button';
                bDel.className = 'fap-btn-del-variante';
                bDel.textContent = 'Supprimer';
                bDel.dataset.idx = String(idx);
                actions.appendChild(bMod);
                actions.appendChild(bDel);
                chip.appendChild(thumb);
                chip.appendChild(body);
                chip.appendChild(actions);
                cardsEl.appendChild(chip);
            });
        }

        function openModal(isEdit) {
            if (!modal) return;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            if (titleModal) titleModal.textContent = isEdit ? 'Modifier la variante' : 'Nouvelle variante';
            if (!isEdit && vNom) { vNom.value = ''; vPrix.value = ''; vPromo.value = ''; if (vFile) vFile.value = ''; }
        }

        function closeModal() {
            if (!modal) return;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            editIndex = null;
        }

        if (btnOpen) btnOpen.addEventListener('click', function () { editIndex = null; openModal(false); });

        modal && modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-close-variante-modal]')) closeModal();
        });

        if (btnValider) {
            btnValider.addEventListener('click', function () {
                var nom = (vNom && vNom.value || '').trim();
                var prix = parseFloat(vPrix && vPrix.value || '0');
                if (!nom || prix <= 0) {
                    alert('Indiquez un nom et un prix valide pour la variante.');
                    return;
                }
                var promoRaw = (vPromo && vPromo.value || '').trim();
                var promo = promoRaw !== '' ? promoRaw : '';
                var f = (vFile && vFile.files && vFile.files[0]) ? vFile.files[0] : null;
                if (f === null && editIndex !== null && editIndex >= 0 && variantesData[editIndex] && variantesData[editIndex].file) {
                    f = variantesData[editIndex].file;
                }
                var prev = (editIndex !== null && editIndex >= 0) ? variantesData[editIndex] : null;
                var entry = {
                    id: prev && prev.id != null ? prev.id : null,
                    nom: nom,
                    prix: String(prix),
                    promo: promo,
                    file: f,
                    existingImage: null
                };
                if (f) {
                    entry.existingImage = null;
                } else if (prev && prev.existingImage) {
                    entry.existingImage = prev.existingImage;
                }
                if (editIndex !== null && editIndex >= 0) {
                    variantesData[editIndex] = entry;
                } else {
                    entry.id = null;
                    variantesData.push(entry);
                }
                renderCards();
                rebuildMount();
                closeModal();
            });
        }

        if (cardsEl) {
            cardsEl.addEventListener('click', function (e) {
                var del = e.target.closest('.fap-btn-del-variante');
                if (del) {
                    var i = parseInt(del.dataset.idx, 10);
                    if (!isNaN(i)) { variantesData.splice(i, 1); renderCards(); rebuildMount(); }
                    return;
                }
                var mod = e.target.closest('.fap-btn-mod-variante');
                if (mod) {
                    var j = parseInt(mod.dataset.idx, 10);
                    if (isNaN(j)) return;
                    editIndex = j;
                    var v = variantesData[j];
                    if (vNom) vNom.value = v.nom;
                    if (vPrix) vPrix.value = v.prix;
                    if (vPromo) vPromo.value = v.promo || '';
                    if (vFile) vFile.value = '';
                    openModal(true);
                }
            });
        }

        <?php
        $repop_v = [];
        if (!empty($PM['variantes_nom']) && is_array($PM['variantes_nom'])) {
            $noms = array_values($PM['variantes_nom']);
            $prixs = isset($PM['variantes_prix']) && is_array($PM['variantes_prix']) ? array_values($PM['variantes_prix']) : [];
            $promos = isset($PM['variantes_prix_promo']) && is_array($PM['variantes_prix_promo']) ? array_values($PM['variantes_prix_promo']) : [];
            $ids_row = isset($PM['variantes_id']) && is_array($PM['variantes_id']) ? array_values($PM['variantes_id']) : [];
            if ($fap_is_edit && !function_exists('get_variante_by_id')) {
                require_once __DIR__ . '/../../models/model_variantes.php';
            }
            foreach ($noms as $ki => $vn) {
                $vn = trim((string) $vn);
                $vp = isset($prixs[$ki]) && is_numeric($prixs[$ki]) ? (float) $prixs[$ki] : 0;
                if ($vn !== '' && $vp > 0) {
                    $vid = isset($ids_row[$ki]) ? (int) $ids_row[$ki] : 0;
                    $exImg = null;
                    if ($fap_is_edit && $vid > 0 && function_exists('get_variante_by_id')) {
                        $ov = get_variante_by_id($vid);
                        if ($ov && !empty($ov['image'])) {
                            $exImg = '/upload/' . ltrim((string) $ov['image'], '/');
                        }
                    }
                    $repop_v[] = [
                        'id' => $vid > 0 ? $vid : null,
                        'nom' => $vn,
                        'prix' => (string) $vp,
                        'promo' => isset($promos[$ki]) && (float) $promos[$ki] > 0 ? (string) $promos[$ki] : '',
                        'existingImage' => $exImg,
                    ];
                }
            }
        }
        if (!empty($repop_v)):
        ?>
        try {
            var repop = <?php echo json_encode($repop_v, JSON_UNESCAPED_UNICODE); ?>;
            if (Array.isArray(repop)) repop.forEach(function (r) {
                variantesData.push({
                    id: r.id != null && r.id !== 0 ? r.id : null,
                    nom: r.nom,
                    prix: r.prix,
                    promo: r.promo || '',
                    file: null,
                    existingImage: r.existingImage || null
                });
            });
            renderCards();
            rebuildMount();
        } catch (e) {}
        <?php elseif ($fap_is_edit && !empty($fap_edit_variantes_js)): ?>
        try {
            var ini = <?php echo json_encode($fap_edit_variantes_js, JSON_UNESCAPED_UNICODE); ?>;
            if (Array.isArray(ini)) ini.forEach(function (r) {
                variantesData.push({
                    id: r.id != null ? r.id : null,
                    nom: r.nom,
                    prix: r.prix,
                    promo: r.promo || '',
                    file: null,
                    existingImage: r.existingImage || null
                });
            });
            renderCards();
            rebuildMount();
        } catch (e) {}
        <?php endif; ?>
    })();
})();
</script>
