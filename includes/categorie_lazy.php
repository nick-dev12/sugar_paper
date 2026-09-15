<?php
/**
 * Page catégorie — chargement progressif (lazy)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/produit_prix_display.php';
require_once __DIR__ . '/produit_share.php';

/**
 * @return array<int, string>
 */
function get_categorie_lazy_part_keys()
{
    return [
        'product_grid',
    ];
}

/**
 * @param string $part_key
 * @return bool
 */
function is_valid_categorie_lazy_part($part_key)
{
    return in_array($part_key, get_categorie_lazy_part_keys(), true);
}

/**
 * @return array<string, string>
 */
function get_categorie_lazy_part_labels()
{
    return [
        'product_grid' => 'Produits de la catégorie',
    ];
}

/**
 * @param int $categorie_id
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_categorie_product_grid($categorie_id, $return_url, $limit = 20)
{
    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0) {
        return;
    }

    $categorie = get_categorie_by_id($categorie_id);
    if (!$categorie || !is_array($categorie) || empty($categorie['nom'])) {
        return;
    }

    $limit = max(1, min(50, (int) $limit));
    $rand_seed = produits_listing_rand_seed();
    $produits = search_produits_with_filters('', null, null, $categorie_id, 'rand', 0, $limit, $rand_seed);
    $total_produits = count_search_produits_with_filters('', null, null, $categorie_id);
    $offset_actuel = min($limit, max(count($produits), 0));
    $api_query = http_build_query(['categorie' => $categorie_id, 'limit' => $limit, 'rand_seed' => $rand_seed]);

    $return_url = trim((string) $return_url);
    if ($return_url === '') {
        $return_url = '/categorie.php?id=' . $categorie_id;
    }

    ?>
    <div class="categorie-catalogue-grid produits-catalogue-grid produits-reveal is-visible"
         data-limit="<?php echo $limit; ?>"
         data-offset="<?php echo (int) $offset_actuel; ?>"
         data-total="<?php echo (int) $total_produits; ?>"
         data-api-query="<?php echo htmlspecialchars($api_query, ENT_QUOTES, 'UTF-8'); ?>"
         data-return-url="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
            <article class="articles carousel11" id="produits-container">
                <?php if (empty($produits)): ?>
                    <div style="text-align: center; padding: 40px; color: #666; width: 100%;">
                        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                        <a href="index.php"
                            style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #918a44; color: white; text-decoration: none; border-radius: 5px;">
                            <i class="fas fa-arrow-left"></i> Retour à l'accueil
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                        <div class="carousel" data-produit-id="<?php echo (int) $produit['id']; ?>">
                            <?php echo produit_share_button_html($produit); ?>
                            <a href="produit.php?id=<?php echo (int) $produit['id']; ?>" class="product-card-link">
                                <div class="image-wrapper">
                                    <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                                        alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.src='/image/produit1.jpg'">
                                </div>
                                <div class="produit-content">
                                    <p id="nom"><?php echo htmlspecialchars($produit['nom']); ?></p>
                                    <?php echo produit_render_listing_prix_html($produit, ['show_promo_badge' => true]); ?>
                                    <?php if (!empty($produit['stock'])): ?>
                                        <p class="produit-card-stock-info">
                                            <strong>Stock:</strong> <?php echo (int) $produit['stock']; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
                                <input type="hidden" name="quantite" value="1">
                                <input type="hidden" name="return_url"
                                    value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn-add-cart">
                                    <i class="fa-solid fa-cart-shopping"></i> Commander
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <?php if (!empty($produits) && $total_produits > $limit): ?>
                <div style="text-align: center; margin-top: 40px; padding: 20px;">
                    <button id="btn-voir-plus" class="btn-voir-plus" type="button">
                        <i class="fas fa-chevron-down"></i> Voir plus
                    </button>
                    <p id="produits-count" class="produits-count">
                        Affichés : <span id="count-actuel"><?php echo (int) $offset_actuel; ?></span> /
                        <?php echo (int) $total_produits; ?> produits
                    </p>
                </div>
            <?php endif; ?>
    </div>
    <?php
}

/**
 * @param string $part_key
 * @param int $categorie_id
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_categorie_lazy_part($part_key, $categorie_id, $return_url = '', $limit = 20)
{
    if (!is_valid_categorie_lazy_part($part_key)) {
        return;
    }

    switch ($part_key) {
        case 'product_grid':
            render_categorie_product_grid($categorie_id, $return_url, $limit);
            break;
    }
}

/**
 * @param string $part_key
 * @param int $categorie_id
 * @return void
 */
function render_categorie_lazy_placeholder($part_key, $categorie_id)
{
    if (!is_valid_categorie_lazy_part($part_key)) {
        return;
    }

    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0 || !get_categorie_by_id($categorie_id)) {
        return;
    }

    $labels = get_categorie_lazy_part_labels();
    $label = $labels[$part_key] ?? 'Contenu';
    ?>
    <div class="categorie-lazy-section"
         data-categorie-lazy="<?php echo htmlspecialchars($part_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-categorie-id="<?php echo $categorie_id; ?>"
         data-loaded="0"
         aria-busy="true"
         aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="categorie-lazy-skeleton" aria-hidden="true">
            <span class="categorie-lazy-skeleton__bar categorie-lazy-skeleton__bar--sm"></span>
            <span class="categorie-lazy-skeleton__bar categorie-lazy-skeleton__bar--lg"></span>
            <span class="categorie-lazy-skeleton__grid"></span>
        </div>
    </div>
    <?php
}
