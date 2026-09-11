<?php
/**
 * Page catalogue produits — chargement progressif (lazy)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/produit_prix_display.php';
require_once __DIR__ . '/produit_share.php';

/**
 * @return array<int, string>
 */
function get_produits_lazy_part_keys()
{
    return [
        'product_grid',
    ];
}

/**
 * @param string $part_key
 * @return bool
 */
function is_valid_produits_lazy_part($part_key)
{
    return in_array($part_key, get_produits_lazy_part_keys(), true);
}

/**
 * @return array<string, string>
 */
function get_produits_lazy_part_labels()
{
    return [
        'product_grid' => 'Catalogue produits',
    ];
}

/**
 * @param string $recherche
 * @param float|null $prix_min
 * @param float|null $prix_max
 * @param int|null $categorie_id
 * @param string $tri
 * @return bool
 */
function produits_catalogue_has_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri)
{
    return !empty($recherche)
        || $prix_min !== null
        || $prix_max !== null
        || $categorie_id !== null
        || $tri !== 'date';
}

/**
 * @param string $recherche
 * @param float|null $prix_min
 * @param float|null $prix_max
 * @param int|null $categorie_id
 * @param string $tri
 * @return array<string, scalar>
 */
function produits_catalogue_filter_params($recherche, $prix_min, $prix_max, $categorie_id, $tri)
{
    $params = [];
    if (!empty($recherche)) {
        $params['recherche'] = $recherche;
    }
    if ($prix_min !== null) {
        $params['prix_min'] = $prix_min;
    }
    if ($prix_max !== null) {
        $params['prix_max'] = $prix_max;
    }
    if ($categorie_id !== null) {
        $params['categorie'] = $categorie_id;
    }
    if ($tri !== 'date') {
        $params['tri'] = $tri;
    }
    return $params;
}

/**
 * @param array<string, scalar> $filter_params
 * @return array{recherche:string,prix_min:float|null,prix_max:float|null,categorie_id:int|null,tri:string,has_filters:bool}
 */
function produits_catalogue_parse_filter_params($filter_params)
{
    $recherche = isset($filter_params['recherche']) ? trim((string) $filter_params['recherche']) : '';
    $prix_min = isset($filter_params['prix_min']) && $filter_params['prix_min'] !== ''
        ? (float) $filter_params['prix_min']
        : null;
    $prix_max = isset($filter_params['prix_max']) && $filter_params['prix_max'] !== ''
        ? (float) $filter_params['prix_max']
        : null;
    $categorie_id = isset($filter_params['categorie']) && $filter_params['categorie'] !== ''
        ? (int) $filter_params['categorie']
        : null;
    $tri = isset($filter_params['tri']) && in_array($filter_params['tri'], ['date', 'prix_asc', 'prix_desc', 'nom'], true)
        ? (string) $filter_params['tri']
        : 'date';

    return [
        'recherche' => $recherche,
        'prix_min' => $prix_min,
        'prix_max' => $prix_max,
        'categorie_id' => $categorie_id,
        'tri' => $tri,
        'has_filters' => produits_catalogue_has_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri),
    ];
}

/**
 * @param string $recherche
 * @param float|null $prix_min
 * @param float|null $prix_max
 * @param int|null $categorie_id
 * @param string $tri
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_produits_product_grid($recherche, $prix_min, $prix_max, $categorie_id, $tri, $return_url, $limit = 20)
{
    $limit = max(1, min(50, (int) $limit));
    $has_filters = produits_catalogue_has_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri);

    if ($has_filters) {
        $produits = search_produits_with_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri, 0, $limit);
        $total_produits = count_search_produits_with_filters($recherche, $prix_min, $prix_max, $categorie_id);
    } else {
        $produits = get_all_produits_paginated(0, $limit);
        $total_produits = count_all_produits_actifs();
    }

    $offset_actuel = min($limit, max(count($produits), 0));
    $filter_params = produits_catalogue_filter_params($recherche, $prix_min, $prix_max, $categorie_id, $tri);
    $api_query = http_build_query(array_merge($filter_params, ['limit' => $limit]));

    $return_url = trim((string) $return_url);
    if ($return_url === '') {
        $return_url = '/produits.php';
    }
    ?>
    <section class="section00 produits-catalogue-grid produits-reveal is-visible"
             data-limit="<?php echo $limit; ?>"
             data-offset="<?php echo (int) $offset_actuel; ?>"
             data-total="<?php echo (int) $total_produits; ?>"
             data-api-query="<?php echo htmlspecialchars($api_query, ENT_QUOTES, 'UTF-8'); ?>"
             data-return-url="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
        <section class="produit_vedetes">
            <article class="articles carousel11" id="produits-container">
                <?php if (empty($produits)): ?>
                    <div style="text-align: center; padding: 40px; color: #666; width: 100%;">
                        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p style="font-size: 16px;">
                            <?php echo $has_filters ? 'Aucun produit ne correspond à votre recherche.' : 'Aucun produit publié pour le moment.'; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                        <div class="carousel" data-produit-id="<?php echo (int) $produit['id']; ?>">
                            <?php echo produit_share_button_html($produit); ?>
                            <a href="produit.php?id=<?php echo (int) $produit['id']; ?>" class="product-card-link">
                                <div class="image-wrapper">
                                    <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                                        alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.src='/image/produit1.jpg'">
                                </div>
                                <div class="produit-content">
                                    <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                                    <?php if (!empty($produit['categorie_nom'])): ?>
                                        <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                                    <?php endif; ?>
                                    <?php echo produit_render_listing_prix_html($produit, ['show_promo_badge' => true]); ?>
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
        </section>
    </section>
    <?php
}

/**
 * @param string $part_key
 * @param array<string, scalar> $filter_params
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_produits_lazy_part($part_key, $filter_params, $return_url = '/produits.php', $limit = 20)
{
    if (!is_valid_produits_lazy_part($part_key)) {
        return;
    }

    $filters = produits_catalogue_parse_filter_params($filter_params);

    switch ($part_key) {
        case 'product_grid':
            render_produits_product_grid(
                $filters['recherche'],
                $filters['prix_min'],
                $filters['prix_max'],
                $filters['categorie_id'],
                $filters['tri'],
                $return_url,
                $limit
            );
            break;
    }
}

/**
 * @param string $part_key
 * @param array<string, scalar> $filter_params
 * @return void
 */
function render_produits_lazy_placeholder($part_key, $filter_params = [])
{
    if (!is_valid_produits_lazy_part($part_key)) {
        return;
    }

    $labels = get_produits_lazy_part_labels();
    $label = $labels[$part_key] ?? 'Contenu';
    $query = http_build_query($filter_params);
    ?>
    <div class="produits-lazy-section"
         data-produits-lazy="<?php echo htmlspecialchars($part_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-query="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
         data-loaded="0"
         aria-busy="true"
         aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="produits-lazy-skeleton" aria-hidden="true">
            <span class="produits-lazy-skeleton__bar produits-lazy-skeleton__bar--sm"></span>
            <span class="produits-lazy-skeleton__bar produits-lazy-skeleton__bar--lg"></span>
            <span class="produits-lazy-skeleton__grid"></span>
        </div>
    </div>
    <?php
}
