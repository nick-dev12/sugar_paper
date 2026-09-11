<?php
/**
 * Sections page produit chargées progressivement (lazy)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/produit_prix_display.php';
require_once __DIR__ . '/produit_share.php';

/**
 * @return array<int, string>
 */
function get_produit_lazy_section_keys()
{
    return [
        'similar_products',
    ];
}

/**
 * @param string $section_key
 * @return bool
 */
function is_valid_produit_lazy_section_key($section_key)
{
    return in_array($section_key, get_produit_lazy_section_keys(), true);
}

/**
 * @return array<string, string>
 */
function get_produit_lazy_section_labels()
{
    return [
        'similar_products' => 'Produits similaires',
    ];
}

/**
 * @param int $produit_id
 * @param string $return_url
 * @return void
 */
function render_produit_lazy_similar_products($produit_id, $return_url = '/produit.php')
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return;
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit || ($produit['statut'] ?? '') !== 'actif') {
        return;
    }

    $categorie_produit_id = (int) ($produit['categorie_id'] ?? 0);
    if ($categorie_produit_id <= 0) {
        return;
    }

    $produits_similaires = get_produits_similaires($produit_id, $categorie_produit_id, 4);
    if (empty($produits_similaires)) {
        return;
    }

    $return_url = trim((string) $return_url);
    if ($return_url === '') {
        $return_url = '/produit.php?id=' . $produit_id;
    }
    ?>
    <div class="produits-similaires produit-reveal is-visible">
        <div class="produits-similaires-header">
            <h2>Produits similaires</h2>
            <a href="categorie.php?id=<?php echo $categorie_produit_id; ?>" class="btn-produit-voir-plus">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                Voir plus
            </a>
        </div>
        <section class="produit_vedetes">
            <article class="articles carousel11">
                <?php foreach ($produits_similaires as $similaire): ?>
                    <div class="carousel">
                        <?php echo produit_share_button_html($similaire); ?>
                        <a href="produit.php?id=<?php echo (int) $similaire['id']; ?>" class="product-card-link">
                            <div class="image-wrapper">
                                <img src="<?php echo htmlspecialchars(upload_image_url($similaire['image_principale'] ?? '', 'md')); ?>"
                                    alt="<?php echo htmlspecialchars($similaire['nom']); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.src='/image/produit1.jpg'">
                            </div>
                            <div class="produit-content">
                                <p id="nom"><?php echo htmlspecialchars($similaire['nom']); ?></p>
                                <?php echo produit_render_listing_prix_html($similaire); ?>
                                <p id="ville"><?php echo htmlspecialchars($similaire['categorie_nom']); ?></p>
                            </div>
                        </a>
                        <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                            <input type="hidden" name="produit_id" value="<?php echo (int) $similaire['id']; ?>">
                            <input type="hidden" name="quantite" value="1">
                            <input type="hidden" name="return_url"
                                value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn-add-cart">
                                <i class="fa-solid fa-cart-shopping"></i> Commander
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </article>
        </section>
    </div>
    <?php
}

/**
 * @param string $section_key
 * @param int $produit_id
 * @param string $return_url
 * @return void
 */
function render_produit_lazy_section($section_key, $produit_id, $return_url = '/produit.php')
{
    if (!is_valid_produit_lazy_section_key($section_key)) {
        return;
    }

    switch ($section_key) {
        case 'similar_products':
            render_produit_lazy_similar_products($produit_id, $return_url);
            break;
    }
}

/**
 * @param string $section_key
 * @param int $produit_id
 * @return void
 */
function render_produit_lazy_placeholder($section_key, $produit_id)
{
    if (!is_valid_produit_lazy_section_key($section_key)) {
        return;
    }

    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return;
    }

    $labels = get_produit_lazy_section_labels();
    $label = $labels[$section_key] ?? 'Contenu';
    ?>
    <div class="produit-lazy-section"
         data-produit-lazy="<?php echo htmlspecialchars($section_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-produit-id="<?php echo $produit_id; ?>"
         data-loaded="0"
         aria-busy="true"
         aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="produit-lazy-skeleton" aria-hidden="true">
            <span class="produit-lazy-skeleton__bar produit-lazy-skeleton__bar--sm"></span>
            <span class="produit-lazy-skeleton__bar produit-lazy-skeleton__bar--lg"></span>
            <span class="produit-lazy-skeleton__grid"></span>
        </div>
    </div>
    <?php
}
