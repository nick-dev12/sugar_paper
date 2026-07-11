<?php
/**
 * Bannière « Faire mes achats sur COLObanes » (style mon-compte).
 * Variables optionnelles :
 * - $mc_v2_shop_promo_href (string) : lien CTA, défaut /index.php
 * - $mc_v2_shop_promo_id (string) : id du titre pour aria-labelledby
 */
if (!defined('SITE_BRAND_NAME')) {
    require_once __DIR__ . '/site_brand.php';
}

$mc_v2_shop_promo_href = isset($mc_v2_shop_promo_href) ? trim((string) $mc_v2_shop_promo_href) : '/index.php';
if ($mc_v2_shop_promo_href === '' || strpos($mc_v2_shop_promo_href, '//') !== false) {
    $mc_v2_shop_promo_href = '/index.php';
}
$mc_v2_shop_promo_id = isset($mc_v2_shop_promo_id) ? trim((string) $mc_v2_shop_promo_id) : 'mc-v2-shop-promo-title';

$mc_promo_images = [];
if (!function_exists('get_produits_nouveautes')) {
    require_once __DIR__ . '/../models/model_produits.php';
}
$mc_promo_produits = get_produits_nouveautes(12);
if (is_array($mc_promo_produits)) {
    foreach ($mc_promo_produits as $mc_p) {
        $mc_img = trim((string) ($mc_p['image_principale'] ?? ''));
        if ($mc_img !== '') {
            $mc_promo_images[] = $mc_img;
        }
        if (count($mc_promo_images) >= 10) {
            break;
        }
    }
}
if (count($mc_promo_images) < 4) {
    foreach (['produit1.jpg', 'produit2.jpg', 'produit3.jpg', 'produit4.jpg', 'produit5.jpg', 'produit6.jpg'] as $mc_fallback) {
        if (!in_array($mc_fallback, $mc_promo_images, true)) {
            $mc_promo_images[] = $mc_fallback;
        }
        if (count($mc_promo_images) >= 6) {
            break;
        }
    }
}
$mc_promo_track = array_merge($mc_promo_images, $mc_promo_images);
$mc_brand_name = htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8');
?>
<section class="mc-v2-shop-promo" aria-labelledby="<?php echo htmlspecialchars($mc_v2_shop_promo_id, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="mc-v2-shop-promo__icons" aria-hidden="true">
        <div class="mc-v2-shop-promo__track">
            <?php foreach ($mc_promo_track as $mc_promo_file) : ?>
            <img class="mc-v2-shop-promo__thumb"
                src="/upload/<?php echo htmlspecialchars($mc_promo_file, ENT_QUOTES, 'UTF-8'); ?>"
                alt="" loading="lazy" decoding="async" width="36" height="36">
            <?php endforeach; ?>
        </div>
    </div>
    <div class="mc-v2-shop-promo__veil" aria-hidden="true"></div>
    <div class="mc-v2-shop-promo__content">
        <div class="mc-v2-shop-promo__copy">
            <p class="mc-v2-shop-promo__eyebrow">Globale Marketplace</p>
            <h2 class="mc-v2-shop-promo__title" id="<?php echo htmlspecialchars($mc_v2_shop_promo_id, ENT_QUOTES, 'UTF-8'); ?>">
                Faites vos courses sur <span><?php echo $mc_brand_name; ?></span>
            </h2>
        </div>
        <a href="<?php echo htmlspecialchars($mc_v2_shop_promo_href, ENT_QUOTES, 'UTF-8'); ?>" class="mc-v2-shop-promo__cta" title="Retour au marché COLObanes">
            <i class="fas fa-cart-shopping" aria-hidden="true"></i>
            Faire mes achats sur <?php echo $mc_brand_name; ?>
        </a>
    </div>
</section>
