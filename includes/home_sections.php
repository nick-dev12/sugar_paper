<?php
/**
 * Sections produits de la page d'accueil
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/produit_share.php';
require_once __DIR__ . '/produit_prix_display.php';
require_once __DIR__ . '/produit_personnalisation.php';

/**
 * @return array<string, array<string, string>>
 */
function get_home_sections_config()
{
    return [
        'cake_topper' => [
            'id' => 'home-cake-topper',
            'kicker' => 'Décoration personnaliser',
            'nav_label' => 'Décoration perso',
            'title' => 'Cake toppers',
            'desc' => 'Topper et décorations pour personnaliser vos gâteaux.',
            'cta_href' => 'section-produits.php?section=cake_topper',
            'cta_label' => 'Tous les cake toppers',
            'page_icon' => 'fa-cake-candles',
        ],
        'photo_impression' => [
            'id' => 'home-photo-impression',
            'kicker' => 'Photo comestible',
            'nav_label' => 'Photo comestible',
            'title' => 'Photo et impression comestible',
            'desc' => 'Impressions alimentaires et décors photo pour gâteaux uniques.',
            'cta_href' => 'section-produits.php?section=photo_impression',
            'cta_label' => 'Voir la sélection',
            'page_icon' => 'fa-image',
        ],
        'outils_patisserie' => [
            'id' => 'home-outils-patisserie',
            'kicker' => 'Outils de pâtisserie',
            'nav_label' => 'Outils pâtisserie',
            'title' => 'Outils de pâtisserie',
            'desc' => 'Le matériel indispensable pour vos créations.',
            'cta_href' => 'section-produits.php?section=outils_patisserie',
            'cta_label' => 'Tous les outils',
            'page_icon' => 'fa-utensils',
        ],
        'decoration_gateau' => [
            'id' => 'home-decoration-gateau',
            'kicker' => 'Décoration de gâteau',
            'nav_label' => 'Décoration gâteau',
            'title' => 'Décoration de gâteau',
            'desc' => 'Paillettes, sprays, rubans et accessoires pour sublimer vos gâteaux.',
            'cta_href' => 'section-produits.php?section=decoration_gateau',
            'cta_label' => 'Voir la sélection',
            'page_icon' => 'fa-star',
        ],
    ];
}

/**
 * @param string $section_key
 * @return string|null
 */
function get_home_section_config($section_key)
{
    $sections = get_home_sections_config();
    return $sections[$section_key] ?? null;
}

/**
 * @param string $section_key
 * @return string
 */
function get_home_section_page_url($section_key)
{
    $section = normalize_produit_section_accueil($section_key);
    if (!$section) {
        return 'produits.php';
    }
    return 'section-produits.php?section=' . rawurlencode($section);
}

/**
 * @return array<string, string>
 */
function get_home_section_accueil_options()
{
    $options = [];
    foreach (get_home_sections_config() as $key => $config) {
        $options[$key] = $config['title'];
    }
    return $options;
}

/**
 * @param string $section_key
 * @return string
 */
function home_build_voir_plus_url($section_key)
{
    return get_home_section_page_url($section_key);
}

/**
 * Liens rapides vers les sections produits (barre MENU)
 * @return void
 */
function render_section1_sections_nav()
{
    ?>
    <nav class="section1-sections-nav" aria-label="Sections produits">
        <?php foreach (get_home_sections_config() as $config): ?>
        <a href="/index.php#<?php echo htmlspecialchars($config['id']); ?>" class="section1-section-link">
            <i class="fa-solid <?php echo htmlspecialchars($config['page_icon']); ?>" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($config['nav_label'] ?? $config['kicker']); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * @param array $produit
 * @param string $return_url
 * @return void
 */
function render_home_product_card($produit, $return_url = '/index.php')
{
    ?>
    <div class="carousel" data-produit-id="<?php echo (int) $produit['id']; ?>">
        <?php echo produit_share_button_html($produit); ?>
        <a href="produit.php?id=<?php echo (int) $produit['id']; ?>" class="product-card-link">
            <div class="image-wrapper">
                <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                    alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                    loading="lazy"
                    onerror="this.src='/image/produit1.jpg'">
            </div>
            <div class="produit-content">
                <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                <?php if (!empty($produit['categorie_nom'])): ?>
                <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                <?php endif; ?>
                <?php echo produit_render_listing_prix_html($produit); ?>
            </div>
        </a>
        <?php render_produit_listing_actions($produit, $return_url); ?>
    </div>
    <?php
}

/**
 * Section accueil : tous les produits (priorité cake topper + photo comestible, mélangés aléatoirement)
 * @param int $limit
 * @param string $return_url
 * @param int $min_per_priority_section Minimum aléatoire par section cake topper et photo/impression
 * @return void
 */
function render_home_all_products_section($limit = 30, $return_url = '/index.php', $min_per_priority_section = 10)
{
    $produits = get_home_catalog_produits_mixed($limit, $min_per_priority_section);
    $total = count_all_produits_actifs();
    $has_more = $total > count($produits);
    ?>
    <section class="produit_vedete home-section-products home-reveal" id="home-all-products">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Catalogue</span>
                <h2 class="home-section-title">Tous nos produits</h2>
                <p class="home-section-desc">Découvrez l'ensemble de notre sélection, toutes catégories confondues.</p>
            </div>
            <a href="produits.php" class="home-section-cta">
                Voir le catalogue <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <article class="articles carousel11 home-section-grid" id="grid-all-products">
            <?php if (empty($produits)): ?>
            <div class="carousel message-vide home-section-empty">
                <p>Aucun produit disponible pour le moment.</p>
            </div>
            <?php else: ?>
            <?php foreach ($produits as $produit): ?>
            <?php render_home_product_card($produit, $return_url); ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </article>

        <?php if ($has_more): ?>
        <div class="home-voir-plus-wrap">
            <a href="produits.php" class="btn-home-voir-plus">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                Voir plus (<?php echo (int) $total; ?> produits)
            </a>
        </div>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * @param string $section_key
 * @param int $limit
 * @param string $return_url
 * @return void
 */
function render_home_product_section($section_key, $limit = 20, $return_url = '/index.php')
{
    $sections = get_home_sections_config();
    if (!isset($sections[$section_key])) {
        return;
    }

    $config = $sections[$section_key];
    $produits = get_produits_by_home_section($section_key, 0, $limit);
    $total = count_produits_by_home_section($section_key);
    $has_more = $total > count($produits);
    ?>
    <section class="produit_vedete home-section-products home-reveal" id="<?php echo htmlspecialchars($config['id']); ?>">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker"><?php echo htmlspecialchars($config['kicker']); ?></span>
                <h2 class="home-section-title"><?php echo htmlspecialchars($config['title']); ?></h2>
                <p class="home-section-desc"><?php echo htmlspecialchars($config['desc']); ?></p>
            </div>
            <a href="<?php echo htmlspecialchars($config['cta_href']); ?>" class="home-section-cta">
                <?php echo htmlspecialchars($config['cta_label']); ?> <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <article class="articles carousel11 home-section-grid" id="grid-<?php echo htmlspecialchars($section_key); ?>">
            <?php if (empty($produits)): ?>
            <div class="carousel message-vide home-section-empty">
                <p>Aucun produit dans cette section pour le moment.</p>
            </div>
            <?php else: ?>
            <?php foreach ($produits as $produit): ?>
            <?php render_home_product_card($produit, $return_url); ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </article>

        <?php if ($has_more): ?>
        <div class="home-voir-plus-wrap">
            <a href="<?php echo htmlspecialchars(home_build_voir_plus_url($section_key)); ?>" class="btn-home-voir-plus">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                Voir plus (<?php echo (int) $total; ?> produits)
            </a>
        </div>
        <?php endif; ?>
    </section>
    <?php
}
