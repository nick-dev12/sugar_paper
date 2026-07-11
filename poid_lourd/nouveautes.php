<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/includes/catalogue_shuffle.php';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$seed_param = isset($_GET['seed']) ? (int) $_GET['seed'] : null;
$catalogue_seed = catalogue_seed_pagination('nouveautes', $seed_param, $seed_param === null);

$produits = get_all_produits_paginated($offset, $limit, null, $catalogue_seed);
$total_produits = count_all_produits_actifs();
if (!empty($produits) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}
$total_pages = $total_produits > 0 ? (int) ceil($total_produits / $limit) : 1;

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Nouveautés — ' . SITE_BRAND_NAME . ' | Produits des boutiques Sénégal';
$seo_description = 'Derniers produits ajoutés sur ' . SITE_BRAND_NAME . ' : découvrez les nouveautés des vendeurs du marketplace, toutes catégories, achat en ligne.';
$seo_keywords = site_brand_seo_keywords_default() . ', nouveautés produits, nouveautés e-commerce Sénégal';
$seo_canonical = $base . '/nouveautes.php';

$return_url_list = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/nouveautes.php';
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

    <div class="mp-catalog-hero mp-catalog-hero--bleu">
        <h1><i class="fas fa-gift" aria-hidden="true"></i> Nouveautés</h1>
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

    <main class="mp-main">
        <div class="mp-shell">
            <section class="mp-block" aria-labelledby="nouveautes-heading">
                <header class="mp-block-head">
                    <h2 id="nouveautes-heading">Derniers arrivages</h2>
                    <span style="font-size:14px;color:var(--texte-mute);"><?php echo (int) count($produits); ?> sur cette page</span>
                </header>
                <div class="mp-grid" id="produits-container">
                    <?php if (empty($produits)): ?>
                    <div class="mp-empty">
                        <p style="margin:0 0 12px;"><i class="fas fa-box-open" style="font-size:40px;opacity:.45;" aria-hidden="true"></i></p>
                        <p style="margin:0 0 20px;">Aucun produit pour le moment.</p>
                        <a href="index.php" class="cat-page-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à l’accueil</a>
                    </div>
                    <?php else: ?>
                    <?php
                    $show_nouveau_badge = true;
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
                    <a href="?page=<?php echo $page - 1; ?>&seed=<?php echo (int) $catalogue_seed; ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <?php if ($i === $page): ?>
                    <span class="is-current" aria-current="page"><?php echo $i; ?></span>
                        <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&seed=<?php echo (int) $catalogue_seed; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&seed=<?php echo (int) $catalogue_seed; ?>">Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>

</html>
