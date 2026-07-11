<?php
/**
 * Carte commande client complète (mes-commandes, mon-compte).
 * Variables préparées par client_commande_card_render().
 */
if (empty($commande) || !is_array($commande)) {
    return;
}
?>
<article class="uc-v2-card<?php echo $link_to_tracking ? ' uc-v2-card--nav-suivi' : ''; ?>"
    <?php if ($suivi_href !== ''): ?>
        data-suivi-href="<?php echo htmlspecialchars($suivi_href, ENT_QUOTES, 'UTF-8'); ?>"
    <?php endif; ?>>
    <div class="uc-v2-card__top">
        <div class="uc-v2-card__ref">
            <div class="uc-v2-card__ref-head">
                <?php if ($is_urgent): ?><span class="uc-urgence" title="Action possible"></span><?php endif; ?>
                <span class="uc-v2-card__boutique"><?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <span class="uc-badge <?php echo cmd_user_badge($st); ?>">
            <i class="fas <?php echo cmd_user_icon($st); ?>" style="font-size:.7em;margin-right:3px;"></i>
            <?php echo cmd_user_label($st); ?>
        </span>
    </div>

    <div class="uc-v2-card__body">
        <?php if (!empty($cmd_galerie_urls)): ?>
            <button type="button"
                class="uc-v2-card__thumb uc-btn-open-gallery"
                data-gallery="<?php echo htmlspecialchars(json_encode($cmd_galerie_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                data-gallery-title="<?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="Voir les photos du produit <?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($cmd_thumb_src, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    onerror="this.src='/image/produit1.jpg'">
                <?php if (count($cmd_galerie_urls) > 1): ?>
                    <span class="uc-v2-card__thumb-count">+<?php echo count($cmd_galerie_urls) - 1; ?></span>
                <?php endif; ?>
                <span class="uc-v2-card__thumb-zoom" aria-hidden="true"><i class="fas fa-expand"></i></span>
            </button>
        <?php endif; ?>
        <div class="uc-v2-card__body-inner">
            <div class="uc-v2-card__info">
                <div class="uc-v2-card__amount">
                    <?php echo number_format((float) ($commande['montant_total'] ?? 0), 0, ',', ' '); ?><small>FCFA</small>
                </div>
                <?php if ($boutique_tel !== ''): ?>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $boutique_tel), ENT_QUOTES, 'UTF-8'); ?>"
                        class="uc-v2-card__tel uc-v2-card__tel--boutique">
                        <i class="fas fa-store"></i>
                        <?php echo htmlspecialchars($boutique_tel, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($timeline !== null): ?>
                <div class="uc-v2-timeline" aria-label="Avancement de la commande">
                    <?php foreach ($timeline as $step): ?>
                        <div class="uc-tl-step uc-tl-step--<?php echo htmlspecialchars($step['state'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="uc-tl-dot">
                                <i class="fas <?php echo htmlspecialchars($step['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </div>
                            <span class="uc-tl-label"><?php echo $step['label']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="uc-v2-card__meta-bar">
        <span class="uc-v2-card__meta-line">
            <span class="uc-v2-card__ref-num">#<?php echo htmlspecialchars((string) ($commande['numero_commande'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="uc-v2-card__sep" aria-hidden="true">&middot;</span>
            <span class="uc-v2-card__date"><?php echo $date_cmd; ?></span>
        </span>
    </div>

    <?php if ($show_footer): ?>
    <div class="uc-v2-card__footer">
        <?php if ($boutique_maps_url !== '' || $boutique_geo_share_url !== ''): ?>
            <button type="button"
                class="uc-card-btn uc-card-btn--gmaps js-geo-open-maps"
                title="Ouvrir avec une application de navigation"
                data-lat="<?php echo $boutique_geo_lat !== null ? htmlspecialchars((string) $boutique_geo_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                data-lng="<?php echo $boutique_geo_lng !== null ? htmlspecialchars((string) $boutique_geo_lng, ENT_QUOTES, 'UTF-8') : ''; ?>"
                data-label="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                data-maps-url="<?php echo htmlspecialchars($boutique_maps_url, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fab fa-google" aria-hidden="true"></i> Ouvrir avec Google Maps
            </button>
        <?php endif; ?>
        <?php if ($boutique_geo_share_url !== ''): ?>
            <button type="button"
                class="uc-card-btn uc-card-btn--wa-share js-geo-share-location"
                title="Partager la position de la boutique"
                data-lat="<?php echo $boutique_geo_lat !== null ? htmlspecialchars((string) $boutique_geo_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                data-lng="<?php echo $boutique_geo_lng !== null ? htmlspecialchars((string) $boutique_geo_lng, ENT_QUOTES, 'UTF-8') : ''; ?>"
                data-label="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                data-share-title="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                data-share-url="<?php echo htmlspecialchars($boutique_geo_share_url, ENT_QUOTES, 'UTF-8'); ?>"
                data-share-text="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                data-share-modal-title="Partager la position de la boutique"
                data-share-hint="Partagez le point de retrait de la boutique avec vos proches.">
                <i class="fab fa-whatsapp" aria-hidden="true"></i> Partager la position de la boutique
            </button>
        <?php endif; ?>

        <a href="commande-categorie.php?commande_id=<?php echo $cmd_id; ?>"
            class="uc-card-btn uc-card-btn--track">
            <i class="fas fa-route"></i> Suivre ma commande
        </a>

        <?php if ($can_noter): ?>
            <button type="button"
                class="uc-card-btn uc-card-btn--rate uc-btn-open-rating"
                data-commande-id="<?php echo $cmd_id; ?>"
                aria-label="Noter les produits de cette commande">
                <i class="fas fa-star"></i> Noter
            </button>
        <?php endif; ?>

        <?php if ($can_confirm): ?>
            <form method="post" action="<?php echo htmlspecialchars($card_form_action, ENT_QUOTES, 'UTF-8'); ?>" style="display:inline;">
                <input type="hidden" name="commande_id" value="<?php echo $cmd_id; ?>">
                <button type="submit" name="confirmer_livraison" class="uc-card-btn uc-card-btn--confirm"
                    onclick="return confirm('Confirmez-vous la r&eacute;ception de votre colis ?');">
                    <i class="fas fa-check-circle"></i> Colis re&ccedil;u
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_cancel): ?>
            <form method="post" action="<?php echo htmlspecialchars($card_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="uc-cancel-form" style="display:inline;">
                <input type="hidden" name="commande_id" value="<?php echo $cmd_id; ?>">
                <button type="button" class="uc-card-btn uc-card-btn--cancel uc-btn-open-cancel"
                    data-commande-id="<?php echo $cmd_id; ?>">
                    <i class="fas fa-times-circle"></i> Annuler
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_reorder): ?>
            <form method="post" action="<?php echo htmlspecialchars($card_form_action, ENT_QUOTES, 'UTF-8'); ?>" style="display:inline;">
                <input type="hidden" name="commande_id" value="<?php echo $cmd_id; ?>">
                <button type="submit" name="recommander" class="uc-card-btn uc-card-btn--reorder">
                    <i class="fas fa-rotate-right"></i> Recommander
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</article>
