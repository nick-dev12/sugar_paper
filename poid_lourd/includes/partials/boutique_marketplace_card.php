<?php
/**
 * Carte boutique style « sac shopping » (icône marketplace).
 * Variable : $boutique (array admin) ou $bt_card (array préparé)
 */
if (!function_exists('marketplace_boutique_prepare_card')) {
    require_once dirname(__DIR__) . '/marketplace_boutique_card_helpers.php';
}

$card = null;
if (isset($bt_card) && is_array($bt_card) && !empty($bt_card['nom'])) {
    $card = $bt_card;
} elseif (!empty($boutique) && is_array($boutique)) {
    $card = marketplace_boutique_prepare_card($boutique);
} else {
    return;
}

$theme = $card['theme'];
$style_vars = '--bt-main:' . htmlspecialchars($theme['main'], ENT_QUOTES, 'UTF-8')
    . ';--bt-dark:' . htmlspecialchars($theme['dark'], ENT_QUOTES, 'UTF-8')
    . ';--bt-accent:' . htmlspecialchars($theme['accent'], ENT_QUOTES, 'UTF-8')
    . ';--bt-band:' . htmlspecialchars($theme['band'], ENT_QUOTES, 'UTF-8');

$nb_produits = (int) ($card['nb_produits'] ?? 0);
$produits_label = $nb_produits . ' produit' . ($nb_produits > 1 ? 's' : '');
$has_geo = !empty($card['has_geo']);
$bt_geo_label = 'Boutique — ' . $card['nom'];
?>
<article class="mp-bt-card<?php echo !empty($theme['has_custom']) ? ' mp-bt-card--themed' : ''; ?>"
    style="<?php echo $style_vars; ?>" role="listitem">
    <div class="mp-bt-card__topbar">
        <span class="mp-bt-card__products"><?php echo htmlspecialchars($produits_label, ENT_QUOTES, 'UTF-8'); ?></span>
        <button type="button"
            class="mp-bt-card__share js-platform-share"
            title="Partager cette boutique"
            aria-haspopup="dialog"
            aria-controls="platformShareModal"
            data-share-modal-title="<?php echo htmlspecialchars($card['share_modal_title'] ?? 'Partager cette boutique', ENT_QUOTES, 'UTF-8'); ?>"
            data-share-title="<?php echo htmlspecialchars($card['share_title'], ENT_QUOTES, 'UTF-8'); ?>"
            data-share-url="<?php echo htmlspecialchars($card['share_url'], ENT_QUOTES, 'UTF-8'); ?>"
            data-share-text="<?php echo htmlspecialchars($card['share_text'], ENT_QUOTES, 'UTF-8'); ?>"
            data-share-hint="<?php echo htmlspecialchars($card['share_hint'] ?? 'Le lien ouvre la boutique publique sur COLObanes.', ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-share-nodes" aria-hidden="true"></i>
            <span>Partager</span>
        </button>
    </div>
    <div class="mp-bt-bag">
        <div class="mp-bt-bag__handle"></div>
        <div class="mp-bt-bag__shell">
            <a class="mp-bt-bag__logo mp-bt-bag__logo-link"
                href="<?php echo htmlspecialchars($card['vitrine_href'], ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="Voir la boutique <?php echo htmlspecialchars($card['nom'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($card['logo_url'] !== ''): ?>
                    <img src="<?php echo htmlspecialchars($card['logo_url'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        onerror="this.remove();">
                <?php endif; ?>
                <svg class="mp-bt-bag__fallback" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M18 24c0-7.7 6.3-14 14-14s14 6.3 14 14" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <rect x="10" y="24" width="44" height="32" rx="8" fill="currentColor" opacity="0.18"/>
                </svg>
            </a>
            <div class="mp-bt-bag__band"></div>
        </div>
    </div>

    <div class="mp-bt-card__body">
        <h3 class="mp-bt-card__title"><?php echo htmlspecialchars($card['nom'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <?php
        $produits_vignettes = isset($card['produits_vignettes']) && is_array($card['produits_vignettes'])
            ? array_slice($card['produits_vignettes'], 0, 10)
            : [];
        ?>
        <?php if (!empty($produits_vignettes)):
            $nb_vignettes = count($produits_vignettes);
            $marquee_animate = $nb_vignettes >= 4;
            $marquee_class = 'mp-bt-card__products-marquee' . ($marquee_animate ? '' : ' mp-bt-card__products-marquee--static');
        ?>
            <div class="<?php echo $marquee_class; ?>" aria-label="Aperçu des produits de la boutique">
                <div class="mp-bt-card__products-track">
                    <?php foreach ($produits_vignettes as $pv): ?>
                        <a class="mp-bt-card__product-bubble"
                            href="<?php echo htmlspecialchars((string) ($pv['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                            title="<?php echo htmlspecialchars((string) ($pv['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars((string) ($pv['image_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                onerror="this.src='/image/produit1.jpg'">
                        </a>
                    <?php endforeach; ?>
                    <?php if ($marquee_animate): ?>
                        <?php foreach ($produits_vignettes as $pv): ?>
                            <a class="mp-bt-card__product-bubble"
                                href="<?php echo htmlspecialchars((string) ($pv['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                                title="<?php echo htmlspecialchars((string) ($pv['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8'); ?>"
                                tabindex="-1"
                                aria-hidden="true">
                                <img src="<?php echo htmlspecialchars((string) ($pv['image_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.src='/image/produit1.jpg'">
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($card['distance_km'] !== null && function_exists('geo_format_distance')): ?>
            <p class="mp-bt-card__dist">
                <i class="fas fa-route" aria-hidden="true"></i>
                <?php echo htmlspecialchars(geo_format_distance((float) $card['distance_km']), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <div class="mp-bt-card__actions">
            <a href="<?php echo htmlspecialchars($card['vitrine_href'], ENT_QUOTES, 'UTF-8'); ?>"
                class="mp-bt-card__btn mp-bt-card__btn--primary">
                <i class="fas fa-store" aria-hidden="true"></i> Voir la boutique
            </a>
            <?php if ($has_geo): ?>
                <button type="button"
                    class="mp-bt-card__btn mp-bt-card__btn--ghost js-geo-open-maps"
                    title="Ouvrir avec une application de navigation"
                    data-lat="<?php echo htmlspecialchars((string) $card['lat'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-lng="<?php echo htmlspecialchars((string) $card['lng'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-label="<?php echo htmlspecialchars($bt_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                    data-maps-url="<?php echo htmlspecialchars($card['maps_url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-location-dot" aria-hidden="true"></i> Localisation
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
