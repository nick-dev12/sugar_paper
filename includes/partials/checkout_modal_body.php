<?php
/**
 * Corps de la modale commande.
 */
$user = $user ?? false;
$panier_items = isset($panier_items) && is_array($panier_items) ? $panier_items : [];
$panier_total = isset($panier_total) ? (float) $panier_total : 0;
$nombre_total_articles = isset($nombre_total_articles) ? (int) $nombre_total_articles : 0;
$zones_livraison = isset($zones_livraison) && is_array($zones_livraison) ? $zones_livraison : [];
$zone_retrait = $zone_retrait ?? null;
$zones_livraison_delivery = isset($zones_livraison_delivery) && is_array($zones_livraison_delivery) ? $zones_livraison_delivery : [];
$commande_mode_selected = $commande_mode_selected ?? 'livraison';
$ckm_message = isset($ckm_message) ? (string) $ckm_message : '';
$ckm_message_type = isset($ckm_message_type) ? (string) $ckm_message_type : '';
?>
<div class="ckm-checkout" data-panier-total="<?php echo (int) round($panier_total); ?>">
    <?php if ($ckm_message !== ''): ?>
        <div class="ckm-flash ckm-flash--<?php echo $ckm_message_type === 'error' ? 'error' : 'success'; ?>">
            <i class="fas fa-<?php echo $ckm_message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo htmlspecialchars($ckm_message); ?>
        </div>
    <?php endif; ?>

    <div class="ckm-checkout__grid">
        <div class="ckm-checkout__form-wrap">
            <form method="POST" action="/api/modals/checkout-submit.php" id="form-commande" class="ckm-checkout__form">
                <input type="hidden" name="action" value="create_commande">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="mode_livraison" id="mode_livraison"
                    value="<?php echo htmlspecialchars($commande_mode_selected, ENT_QUOTES, 'UTF-8'); ?>">

                <?php if (empty($zones_livraison)): ?>
                    <div class="ckm-flash ckm-flash--error">
                        <i class="fas fa-exclamation-triangle"></i>
                        Aucune zone de livraison n'est configurée.
                    </div>
                <?php else: ?>
                    <div class="cmd-mode-switch" role="tablist" aria-label="Mode de réception">
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'livraison' ? ' is-active' : ''; ?>"
                            data-mode="livraison" id="cmd-mode-btn-livraison" role="tab">
                            <i class="fas fa-truck" aria-hidden="true"></i> Livraison
                        </button>
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'retrait' ? ' is-active' : ''; ?>"
                            data-mode="retrait" id="cmd-mode-btn-retrait" role="tab">
                            <i class="fas fa-store" aria-hidden="true"></i> Sur place
                        </button>
                    </div>

                    <?php if ($zone_retrait): ?>
                        <input type="hidden" name="zone_livraison_id" id="zone_retrait_id"
                            value="<?php echo (int) $zone_retrait['id']; ?>"
                            <?php echo $commande_mode_selected === 'retrait' ? '' : 'disabled'; ?>>
                    <?php endif; ?>

                    <div id="panel-livraison"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'livraison' ? ' is-visible' : ''; ?>">
                        <?php if (!empty($zones_livraison_delivery)): ?>
                            <div class="ckm-field">
                                <label for="zone_livraison_id">Zone de livraison *</label>
                                <select id="zone_livraison_id" name="zone_livraison_id"
                                    <?php echo $commande_mode_selected === 'livraison' ? 'required' : 'disabled'; ?>>
                                    <option value="">Sélectionnez votre zone</option>
                                    <?php foreach ($zones_livraison_delivery as $zone): ?>
                                        <option value="<?php echo (int) $zone['id']; ?>"
                                            data-prix="<?php echo (float) $zone['prix_livraison']; ?>">
                                            <?php echo htmlspecialchars($zone['ville'] . ' - ' . $zone['quartier']); ?>
                                            (<?php echo number_format($zone['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="ckm-flash ckm-flash--error">Aucune zone de livraison à domicile n'est configurée.</div>
                        <?php endif; ?>

                        <div class="ckm-field">
                            <label>Votre position exacte *</label>
                            <div class="commande-geo-box">
                                <div id="commande-geo-map" class="commande-geo-map" aria-label="Carte de votre position"></div>
                                <button type="button" class="btn-commande-geo-refresh" id="btn-commande-geo-refresh">
                                    <i class="fas fa-crosshairs" aria-hidden="true"></i> Actualiser ma position
                                </button>
                                <div id="commande-geo-status" class="commande-geo-status" aria-live="polite"></div>
                            </div>
                            <input type="hidden" name="geo_lat" id="geo_lat" value="">
                            <input type="hidden" name="geo_lng" id="geo_lng" value="">
                            <input type="hidden" name="geo_precision" id="geo_precision" value="">
                            <input type="hidden" name="geo_source" id="geo_source" value="">
                            <input type="hidden" name="geo_address" id="geo_address" value="">
                        </div>
                    </div>

                    <div id="panel-retrait"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'retrait' ? ' is-visible' : ''; ?>">
                        <div class="cmd-retrait-info">
                            <i class="fas fa-store" aria-hidden="true"></i>
                            Vous récupérez votre commande en boutique
                            <?php if ($zone_retrait): ?>
                                — <strong><?php echo htmlspecialchars($zone_retrait['ville'] . ' - ' . $zone_retrait['quartier']); ?></strong>
                            <?php else: ?>
                                — <strong>Sugar Paper, Hann Mariste 2</strong>
                            <?php endif; ?>
                            (gratuit).
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ckm-field">
                    <label for="telephone_livraison">
                        <span id="tel-label-text"><?php echo $commande_mode_selected === 'retrait' ? 'Téléphone de contact' : 'Téléphone de livraison'; ?></span> *
                    </label>
                    <input type="tel" id="telephone_livraison" name="telephone_livraison" required
                        placeholder="+221 XX XXX XX XX"
                        value="<?php echo htmlspecialchars((string) ($user['telephone'] ?? '')); ?>">
                </div>

                <button type="submit" class="ckm-btn ckm-btn--primary btn-submit-commande"
                    <?php echo empty($zones_livraison) ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-circle"></i> Confirmer la commande
                </button>
            </form>
        </div>

        <aside class="ckm-checkout__recap">
            <h3 class="ckm-checkout__recap-title">Résumé</h3>
            <?php foreach ($panier_items as $item): ?>
                <?php
                $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                    ? (float) $item['panier_prix_unitaire']
                    : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                $prix_total_item = $prix_unitaire * $item['quantite'];
                $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : ($item['image_principale'] ?? '');
                $item_nom = !empty($item['panier_variante_nom']) ? $item['nom'] . ' - ' . $item['panier_variante_nom'] : ($item['nom'] ?? 'Produit');
                ?>
                <div class="ckm-recap-item">
                    <img src="<?php echo htmlspecialchars(upload_image_url($item_img, 'sm')); ?>" alt=""
                        onerror="this.src='/image/produit1.jpg'">
                    <div>
                        <strong><?php echo htmlspecialchars($item_nom); ?></strong>
                        <span><?php echo (int) $item['quantite']; ?> × <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <em><?php echo number_format($prix_total_item, 0, ',', ' '); ?></em>
                </div>
            <?php endforeach; ?>
            <div class="ckm-summary__row">
                <span>Sous-total</span>
                <span><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</span>
            </div>
            <div class="ckm-summary__row">
                <span>Livraison</span>
                <span id="summary-livraison">0 FCFA</span>
            </div>
            <div class="ckm-summary__total">
                <span>Total</span>
                <strong id="summary-total"><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</strong>
            </div>
        </aside>
    </div>
</div>
