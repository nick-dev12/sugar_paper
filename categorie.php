<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/models/model_categories.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_prix_display.php';
require_once __DIR__ . '/includes/categorie_lazy.php';

// Récupérer l'ID de la catégorie depuis l'URL
$categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Réinitialiser la variable pour éviter tout problème de cache
unset($categorie);
$categorie = null;

// Récupérer les informations de la catégorie
if ($categorie_id > 0) {
    $categorie = get_categorie_by_id($categorie_id);
}

// Si la catégorie n'existe pas, rediriger vers l'accueil
if (!$categorie || !is_array($categorie) || empty($categorie['nom'])) {
    header('Location: index.php');
    exit;
}

// Forcer la récupération du nom de la catégorie depuis le tableau
$categorie_nom = isset($categorie['nom']) ? $categorie['nom'] : 'Catégorie';

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/seo_config.php';
require_once __DIR__ . '/includes/seo_schema.php';
$base = get_site_base_url();
$categorie_nom = isset($categorie['nom']) ? $categorie['nom'] : 'Catégorie';
$seo_title = $categorie_nom . ' — Décoration gâteau | Sugar Paper Dakar';
$desc_cat = !empty($categorie['description']) ? strip_tags($categorie['description']) : 'Découvrez nos produits ' . $categorie_nom . ' pour la décoration de gâteaux à Dakar : cake topper, impression comestible et accessoires pâtisserie au Sénégal.';
$seo_description = function_exists('mb_substr') ? mb_substr($desc_cat, 0, 160) : substr($desc_cat, 0, 160);
$seo_keywords = $categorie_nom . ', décoration gâteau, cake topper Dakar, Sugar Paper Sénégal';
$seo_canonical = $base . '/categorie.php?id=' . (int) $categorie_id;
$seo_schema_graphs = array_merge(
    seo_schema_default_graphs(),
    [
        seo_schema_build_breadcrumb([
            ['name' => 'Accueil', 'url' => $base . '/'],
            ['name' => 'Produits', 'url' => $base . '/produits.php'],
            ['name' => $categorie_nom, 'url' => $seo_canonical],
        ]),
    ]
);
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
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/catalogue-responsive.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/categorie-perf.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/platform_share_head.php'; ?>
</head>

<body class="page-categorie">
    <?php
    if (!defined('NAV_SKIP_HEAD_ASSETS')) {
        define('NAV_SKIP_HEAD_ASSETS', true);
    }
    include('nav_bar.php');
    ?>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
    <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(32, 197, 199, 0.15); border-left: 4px solid var(--turquoise); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(229, 72, 138, 0.15); border-left: 4px solid var(--couleur-dominante); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>

    <section class="section00">
        <section class="produit_vedetes">
            <div class="box1">
                <h1><?php echo htmlspecialchars($categorie_nom); ?></h1>
            </div>
            <?php render_categorie_lazy_placeholder('product_grid', $categorie_id); ?>
        </section>
    </section>

    <?php include('footer.php') ?>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>

    <script src="/js/produit-card-share.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/produits-catalogue.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/home-progressive.js<?php echo asset_version_query(); ?>" defer></script>

</body>

</html>
