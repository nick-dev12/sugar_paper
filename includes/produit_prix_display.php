<?php
/**
 * Affichage des prix produits (listings publics)
 */

/**
 * @param array $produit
 * @return array{prix_affichage: float, has_promotion: bool, pourcentage_promo: int}
 */
function produit_compute_display_price($produit)
{
    $has_promotion = !empty($produit['prix_promotion'])
        && (float) $produit['prix_promotion'] < (float) $produit['prix'];
    $prix_affichage = $has_promotion
        ? (float) $produit['prix_promotion']
        : (float) $produit['prix'];
    $pourcentage_promo = 0;
    if ($has_promotion && (float) $produit['prix'] > 0) {
        $pourcentage_promo = (int) round((((float) $produit['prix'] - (float) $produit['prix_promotion']) / (float) $produit['prix']) * 100);
    }

    return [
        'prix_affichage' => $prix_affichage,
        'has_promotion' => $has_promotion,
        'pourcentage_promo' => $pourcentage_promo,
    ];
}

/**
 * @return string
 */
function produit_price_from_label_html()
{
    return '<span class="prix-from-label">À partir de</span>';
}

/**
 * @param array $produit
 * @param array{show_promo_badge?: bool, wrapper?: bool} $options
 * @return string
 */
function produit_render_listing_prix_html($produit, $options = [])
{
    $show_promo_badge = !empty($options['show_promo_badge']);
    $wrapper = !array_key_exists('wrapper', $options) || !empty($options['wrapper']);

    $price = produit_compute_display_price($produit);
    $show_from = produit_uses_price_from_label($produit);
    $prix_class = 'prix' . ($show_from ? ' prix--from' : '');

    ob_start();
    if ($wrapper) {
        echo '<p class="' . htmlspecialchars($prix_class) . '">';
    }
    if ($show_from) {
        echo produit_price_from_label_html();
    }
    if ($price['has_promotion']) {
        echo '<span class="span2">' . number_format((float) $produit['prix'], 0, ',', ' ') . ' FCFA</span>';
        echo '<span class="prix-promo">' . number_format($price['prix_affichage'], 0, ',', ' ') . ' FCFA</span>';
        if ($show_promo_badge && $price['pourcentage_promo'] > 0) {
            echo '<span class="span3">-' . (int) $price['pourcentage_promo'] . '%</span>';
        }
    } else {
        echo number_format($price['prix_affichage'], 0, ',', ' ');
        echo '<span class="span1"> FCFA</span>';
    }
    if ($wrapper) {
        echo '</p>';
    }

    return (string) ob_get_clean();
}

/**
 * @param array $produit
 * @param float|null $prix_affichage
 * @param bool|null $has_promotion
 * @param int|null $pourcentage_reduction
 * @return string
 */
function produit_render_detail_prix_html($produit, $prix_affichage = null, $has_promotion = null, $pourcentage_reduction = null)
{
    if ($prix_affichage === null || $has_promotion === null) {
        $computed = produit_compute_display_price($produit);
        $prix_affichage = $computed['prix_affichage'];
        $has_promotion = $computed['has_promotion'];
        if ($pourcentage_reduction === null) {
            $pourcentage_reduction = $computed['pourcentage_promo'];
        }
    }

    $show_from = produit_uses_price_from_label($produit);
    ob_start();
    if ($show_from) {
        echo produit_price_from_label_html();
    }
    if ($has_promotion) {
        echo '<span class="prix-original">' . number_format((float) $produit['prix'], 0, ',', ' ') . ' FCFA</span>';
        echo '<span class="prix-promo">' . number_format((float) $prix_affichage, 0, ',', ' ') . ' FCFA</span>';
        if ((int) $pourcentage_reduction > 0) {
            echo '<span class="promo-badge">-' . (int) $pourcentage_reduction . '%</span>';
        }
    } else {
        echo number_format((float) $prix_affichage, 0, ',', ' ') . ' FCFA';
    }

    return (string) ob_get_clean();
}
