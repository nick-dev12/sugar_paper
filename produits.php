<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_prix_display.php';

require_once __DIR__ . '/includes/produits_lazy.php';

$recherche_actuelle = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$prix_min = isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float) $_GET['prix_min'] : null;
$prix_max = isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float) $_GET['prix_max'] : null;
$categorie_id = isset($_GET['categorie']) && $_GET['categorie'] !== '' ? (int) $_GET['categorie'] : null;
$tri = isset($_GET['tri']) && in_array($_GET['tri'], ['rand', 'date', 'prix_asc', 'prix_desc', 'nom']) ? $_GET['tri'] : 'rand';
$has_filters = produits_catalogue_has_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id, $tri);
$filter_params = produits_catalogue_filter_params($recherche_actuelle, $prix_min, $prix_max, $categorie_id, $tri);

$total_produits = 0;
if ($has_filters) {
    $total_produits = count_search_produits_with_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id);
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/seo_config.php';
$base = get_site_base_url();
$catalogue_seo = get_seo_produits_meta();
$seo_title = $catalogue_seo['title'];
$seo_description = $catalogue_seo['description'];
$seo_keywords = $catalogue_seo['keywords'];
$seo_canonical = $base . '/produits.php';
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
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/catalogue-responsive.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/produits-perf.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/platform_share_head.php'; ?>
    <style>
        .produits-page-header {
            background: var(--couleur-dominante);
            padding: 40px 20px;
            text-align: center;
            color: var(--texte-clair);
            margin-bottom: 40px;
        }

        .produits-page-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .produits-page-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .produits-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;

        }

        .btn-voir-plus {
            padding: 15px 40px;
            background: var(--couleur-dominante);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto;
        }

        .btn-voir-plus:hover {
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(229, 72, 138, 0.3);
        }

        .btn-voir-plus:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .produits-count {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }

        /* Assurer que le contenu principal a un espacement suffisant pour le footer */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .produits-container-wrapper {
            flex: 1;
            padding-bottom: 100px;
            margin-bottom: 0;
        }

        /* S'assurer que le footer est bien positionné et ne se superpose pas */
        .footer {
            margin-top: 80px;
            position: relative;
            width: 100%;
            clear: both;
            flex-shrink: 0;
        }

        /* Espacement supplémentaire pour la section des produits */
        .section00 {
            margin-bottom: 60px;
        }

        /* S'assurer que le wrapper principal a un espacement suffisant */
        .produits-page-header {
            margin-bottom: 40px;
        }

        .filtres-actifs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-top: 12px;
            font-size: 14px;
            opacity: 0.95;
        }

        .filtres-actifs span {
            background: rgba(255, 255, 255, 0.25);
            padding: 6px 12px;
            border-radius: 20px;
        }
    </style>
</head>

<body class="page-produits">
    <?php
    if (!defined('NAV_SKIP_HEAD_ASSETS')) {
        define('NAV_SKIP_HEAD_ASSETS', true);
    }
    include('nav_bar.php');
    ?>

    <div class="produits-page-header">
        <h1><i class="fas fa-box"></i>
            <?php echo !empty($recherche_actuelle) ? 'Résultats pour "' . htmlspecialchars($recherche_actuelle) . '"' : 'Tous nos produits'; ?>
        </h1>
        <?php if ($has_filters): ?>
        <p><?php echo $total_produits . ' produit(s) trouvé(s)'; ?></p>
            <p class="filtres-actifs">
                <?php if (!empty($recherche_actuelle)): ?><span><i class="fas fa-search"></i>
                        <?php echo htmlspecialchars($recherche_actuelle); ?></span><?php endif; ?>
                <?php if ($prix_min !== null): ?><span><i class="fas fa-coins"></i> Min
                        <?php echo number_format($prix_min, 0, ',', ' '); ?> FCFA</span><?php endif; ?>
                <?php if ($prix_max !== null): ?><span><i class="fas fa-coins"></i> Max
                        <?php echo number_format($prix_max, 0, ',', ' '); ?> FCFA</span><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
        <div
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(32, 197, 199, 0.15); border-left: 4px solid var(--turquoise); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(229, 72, 138, 0.15); border-left: 4px solid var(--couleur-dominante); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <div class="produits-container-wrapper">
        <?php render_produits_lazy_placeholder('product_grid', $filter_params); ?>
    </div>

    <?php include('footer.php'); ?>
    <script src="/js/produit-card-share.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/produits-catalogue.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/home-progressive.js<?php echo asset_version_query(); ?>" defer></script>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>
</body>

</html>