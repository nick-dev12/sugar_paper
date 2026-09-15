<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_prix_display.php';
require_once __DIR__ . '/includes/produit_share.php';
require_once __DIR__ . '/includes/home_sections.php';
require_once __DIR__ . '/includes/produit_personnalisation.php';

$section_key = isset($_GET['section']) ? normalize_produit_section_accueil($_GET['section']) : null;
if ($section_key === 'cupcakes' || $section_key === 'contours_gateau' || $section_key === 'disques_cocktail' || $section_key === 'habillage_papier_azyme') {
    header('Location: section-produits.php?section=photo_impression');
    exit;
}
$section_config = $section_key ? get_home_section_config($section_key) : null;

if (!$section_key || !$section_config) {
    header('Location: produits.php');
    exit;
}

require_once __DIR__ . '/includes/section_produits_lazy.php';

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/seo_config.php';
require_once __DIR__ . '/includes/seo_schema.php';
$base = get_site_base_url();
$section_seo = get_seo_section_meta($section_key);
$seo_title = $section_seo ? $section_seo['title'] : ($section_config['title'] . ' | Sugar Paper Dakar');
$seo_description = $section_seo ? $section_seo['description'] : $section_config['desc'];
$seo_keywords = $section_seo ? $section_seo['keywords'] : get_seo_default_keywords();
$seo_canonical = $base . '/section-produits.php?section=' . rawurlencode($section_key);
$seo_schema_graphs = array_merge(
    seo_schema_default_graphs(),
    [
        seo_schema_build_collection_page(
            $section_config['title'],
            $seo_description,
            $seo_canonical
        ),
        seo_schema_build_breadcrumb([
            ['name' => 'Accueil', 'url' => $base . '/'],
            ['name' => 'Produits', 'url' => $base . '/produits.php'],
            ['name' => $section_config['title'], 'url' => $seo_canonical],
        ]),
    ]
);
$page_icon = $section_config['page_icon'] ?? 'fa-box-open';
$section_uses_perso = ($section_key === 'photo_impression');
$section_uses_cp = ($section_key === 'cake_topper');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/catalogue-responsive.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/seo-content.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/section-produits-perf.css<?php echo asset_version_query(); ?>">
    <?php if ($section_uses_perso): ?>
        <link rel="stylesheet" href="/css/produit-personnalisation.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <?php if ($section_uses_cp): ?>
        <link rel="stylesheet" href="/css/produit-personnalisation.css<?php echo asset_version_query(); ?>">
        <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
        <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
        <?php if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0): ?>
            <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/platform_share_head.php'; ?>
    <style>
        .page-header {
            background: var(--couleur-dominante);
            padding: 40px 20px 32px;
            text-align: center;
            color: #ffffff;
            margin-bottom: 0;
        }

        .page-header h1 {
            font-size: clamp(1.6rem, 4vw, 2rem);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 16px;
            opacity: 0.92;
            max-width: 640px;
            margin: 0 auto;
        }

        .page-header-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
        }

        .produits-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 100px;
        }

        .page-section-produits .produits-container-wrapper .section00 {
            padding-top: 8px;
            padding-bottom: 40px;
        }

        @media (max-width: 799px) {
            .page-section-produits .produits-container-wrapper .section00 {
                padding-top: 4px;
                padding-bottom: 32px;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
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
    </style>
</head>

<body class="page-section-produits">
    <?php
    if (!defined('NAV_SKIP_HEAD_ASSETS')) {
        define('NAV_SKIP_HEAD_ASSETS', true);
    }
    include 'nav_bar.php';
    ?>

    <div class="page-header">
        <h1><i class="fas <?php echo htmlspecialchars($page_icon); ?>"></i>
            <?php echo htmlspecialchars($section_config['title']); ?></h1>
        <p><?php echo htmlspecialchars($section_config['desc']); ?></p>
        <a href="index.php#<?php echo htmlspecialchars($section_config['id']); ?>" class="page-header-back">
            <i class="fas fa-arrow-left"></i> Retour à l'accueil
        </a>
    </div>

    <?php echo render_section_seo_intro_header_html($section_key); ?>

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
        <?php render_section_produits_lazy_placeholder('product_grid', $section_key); ?>
    </div>

    <?php if ($section_uses_cp): ?>
        <?php render_cp_form_modal_assets(); ?>
    <?php endif; ?>

    <?php if ($section_uses_perso): ?>
        <?php render_produit_personnalisation_modal(); ?>
    <?php endif; ?>

    <?php include 'footer.php'; ?>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>
    <script src="/js/produit-card-share.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/section-produits.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/home-progressive.js<?php echo asset_version_query(); ?>" defer></script>
    <?php if ($section_uses_perso): ?>
        <script src="/js/produit-personnalisation.js<?php echo asset_version_query(); ?>" defer></script>
    <?php endif; ?>
</body>

</html>