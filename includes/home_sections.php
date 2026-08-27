<?php
/**
 * Sections produits de la page d'accueil
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/produit_share.php';
require_once __DIR__ . '/produit_prix_display.php';

/**
 * @return array<string, array<string, string>>
 */
function get_home_sections_config()
{
    return [
        'cake_topper' => [
            'id' => 'home-cake-topper',
            'kicker' => 'Décoration',
            'title' => 'Cake toppers',
            'desc' => 'Topper et décorations pour personnaliser vos gâteaux.',
            'cta_href' => 'section-produits.php?section=cake_topper',
            'cta_label' => 'Tous les cake toppers',
            'page_icon' => 'fa-cake-candles',
        ],
        'photo_impression' => [
            'id' => 'home-photo-impression',
            'kicker' => 'Personnalisation',
            'title' => 'Photo et impression comestible',
            'desc' => 'Impressions alimentaires et décors photo pour gâteaux uniques.',
            'cta_href' => 'section-produits.php?section=photo_impression',
            'cta_label' => 'Voir la sélection',
            'page_icon' => 'fa-image',
        ],
        'outils_patisserie' => [
            'id' => 'home-outils-patisserie',
            'kicker' => 'Équipement',
            'title' => 'Outils de pâtisserie',
            'desc' => 'Le matériel indispensable pour vos créations.',
            'cta_href' => 'section-produits.php?section=outils_patisserie',
            'cta_label' => 'Tous les outils',
            'page_icon' => 'fa-utensils',
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
        <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
            <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
            <input type="hidden" name="quantite" value="1">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
            <button type="submit" class="btn-add-cart">
                <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
            </button>
        </form>
    </div>
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
