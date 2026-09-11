<?php
/**
 * Sections page section-produits chargées progressivement (lazy)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/produit_prix_display.php';
require_once __DIR__ . '/produit_share.php';
require_once __DIR__ . '/home_sections.php';
require_once __DIR__ . '/produit_personnalisation.php';

/**
 * @return array<int, string>
 */
function get_section_produits_lazy_part_keys()
{
    return [
        'product_grid',
    ];
}

/**
 * @param string $part_key
 * @return bool
 */
function is_valid_section_produits_lazy_part($part_key)
{
    return in_array($part_key, get_section_produits_lazy_part_keys(), true);
}

/**
 * @return array<string, string>
 */
function get_section_produits_lazy_part_labels()
{
    return [
        'product_grid' => 'Catalogue section',
    ];
}

/**
 * @param string $section_key
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_section_produits_product_grid($section_key, $return_url, $limit = 20)
{
    $section_key = normalize_produit_section_accueil($section_key);
    $section_config = $section_key ? get_home_section_config($section_key) : null;
    if (!$section_key || !$section_config) {
        return;
    }

    $limit = max(1, min(50, (int) $limit));
    $produits = get_produits_by_home_section($section_key, 0, $limit);
    $total_produits = count_produits_by_home_section($section_key);
    $offset_actuel = min($limit, max(count($produits), 0));
    $section_uses_perso = ($section_key === 'photo_impression');
    $section_uses_cp = ($section_key === 'cake_topper');

    $return_url = trim((string) $return_url);
    if ($return_url === '') {
        $return_url = 'section-produits.php?section=' . rawurlencode($section_key);
    }
    ?>
    <section class="section00 section-produits-grid section-produits-reveal is-visible"
             data-section-key="<?php echo htmlspecialchars($section_key, ENT_QUOTES, 'UTF-8'); ?>"
             data-limit="<?php echo $limit; ?>"
             data-offset="<?php echo (int) $offset_actuel; ?>"
             data-total="<?php echo (int) $total_produits; ?>"
             data-return-url="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>"
             data-uses-perso="<?php echo $section_uses_perso ? '1' : '0'; ?>"
             data-uses-cp="<?php echo $section_uses_cp ? '1' : '0'; ?>">
        <section class="produit_vedetes">
            <article class="articles carousel11" id="produits-container">
                <?php if (empty($produits)): ?>
                    <div class="empty-state" style="width:100%;">
                        <i class="fas fa-box-open" style="font-size:48px;opacity:0.4;margin-bottom:16px;"></i>
                        <p>Aucun produit dans cette section pour le moment.</p>
                        <a href="index.php"
                            style="display:inline-block;margin-top:16px;color:var(--couleur-dominante);">Retour à
                            l'accueil</a>
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
                            <?php render_produit_listing_actions($produit, $return_url); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <?php if (!empty($produits) && $total_produits > $limit): ?>
                <div style="text-align:center;margin-top:40px;padding:20px;">
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
 * @param string $section_key
 * @param string $return_url
 * @param int $limit
 * @return void
 */
function render_section_produits_lazy_part($part_key, $section_key, $return_url = '', $limit = 20)
{
    if (!is_valid_section_produits_lazy_part($part_key)) {
        return;
    }

    switch ($part_key) {
        case 'product_grid':
            render_section_produits_product_grid($section_key, $return_url, $limit);
            break;
    }
}

/**
 * @param string $part_key
 * @param string $section_key
 * @return void
 */
function render_section_produits_lazy_placeholder($part_key, $section_key)
{
    if (!is_valid_section_produits_lazy_part($part_key)) {
        return;
    }

    $section_key = normalize_produit_section_accueil($section_key);
    if (!$section_key || !get_home_section_config($section_key)) {
        return;
    }

    $labels = get_section_produits_lazy_part_labels();
    $label = $labels[$part_key] ?? 'Contenu';
    ?>
    <div class="section-produits-lazy-section"
         data-section-produits-lazy="<?php echo htmlspecialchars($part_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-section-key="<?php echo htmlspecialchars($section_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-loaded="0"
         aria-busy="true"
         aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="section-produits-lazy-skeleton" aria-hidden="true">
            <span class="section-produits-lazy-skeleton__bar section-produits-lazy-skeleton__bar--sm"></span>
            <span class="section-produits-lazy-skeleton__bar section-produits-lazy-skeleton__bar--lg"></span>
            <span class="section-produits-lazy-skeleton__grid"></span>
        </div>
    </div>
    <?php
}
