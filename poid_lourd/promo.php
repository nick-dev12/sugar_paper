<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$produits = get_produits_en_promo($offset, $limit);
if (!empty($produits) && is_array($produits)) {
    if (function_exists('random_int')) {
        mt_srand((int) (microtime(true) * 1000000) + random_int(0, 9999));
    } else {
        mt_srand((int) (microtime(true) * 1000000));
    }
    shuffle($produits);
}
if (!empty($produits) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}
$total_produits = count_produits_en_promo();
$total_pages = $total_produits > 0 ? (int) ceil($total_produits / $limit) : 1;

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Promotions & bonnes affaires — ' . SITE_BRAND_NAME;
$seo_description = 'Promotions et produits en réduction sur ' . SITE_BRAND_NAME . ', marketplace multi-boutiques au Sénégal. Économisez sur des milliers d’articles.';
$seo_keywords = site_brand_seo_keywords_default() . ', promotions Sénégal, soldes en ligne, bonnes affaires Dakar';
$seo_canonical = $base . '/promo.php';

$return_url_list = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/promo.php';
$card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include __DIR__ . '/nav_bar.php'; ?>

    <div class="mp-catalog-hero mp-catalog-hero--promo">
        <h1><i class="fas fa-percent" aria-hidden="true"></i> Promotions</h1>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
    <div class="cat-page-alert cat-page-alert--ok mp-shell">
        <i class="fas fa-check-circle" aria-hidden="true"></i> Produit ajouté au panier avec succès.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="cat-page-alert cat-page-alert--err mp-shell">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $_GET['error']); ?>
    </div>
    <?php endif; ?>

    <main class="mp-main mp-main--promo">
        <div class="mp-shell mp-shell--promo">
            <section class="mp-block" aria-labelledby="promo-heading">
                <header class="mp-block-head">
                    <h2 id="promo-heading">Offres du moment</h2>
                    <span style="font-size:14px;color:var(--texte-mute);"><?php echo (int) count($produits); ?> sur cette page</span>
                </header>
                <div class="mp-grid" id="produits-container">
                    <?php if (empty($produits)): ?>
                    <div class="mp-empty">
                        <p style="margin:0 0 12px;"><i class="fas fa-tags" style="font-size:40px;opacity:.45;" aria-hidden="true"></i></p>
                        <p style="margin:0 0 20px;">Aucun produit en promotion pour le moment.</p>
                        <a href="produits.php" class="cat-page-back"><i class="fas fa-th" aria-hidden="true"></i> Voir le catalogue</a>
                    </div>
                    <?php else: ?>
                    <?php
                    foreach ($produits as $produit) {
                        $return_url = $return_url_list;
                        require $card_partial;
                    }
                    ?>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="mp-pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <?php if ($i === $page): ?>
                    <span class="is-current" aria-current="page"><?php echo $i; ?></span>
                        <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>
