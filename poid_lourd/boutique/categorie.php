<?php
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/_init.php';

// Inclusion des modèles
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_genres.php';

$generale_id = isset($_GET['generale']) ? (int) $_GET['generale'] : 0;
$categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$filter_genre_id = isset($_GET['genre']) ? (int) $_GET['genre'] : 0;
$filter_sous_categorie_id = 0;

unset($categorie);
$categorie = null;
$generale_row = null;
$categorie_nom = 'Catégorie';
$produits = [];
$genres_pour_filtre_rayon = [];

if ($generale_id > 0) {
    $filter_sous_categorie_id = isset($_GET['sous_categorie']) ? (int) $_GET['sous_categorie'] : 0;
    $generale_row = get_categorie_generale_by_id($generale_id);
    if (!$generale_row || empty($generale_row['nom'])) {
        header('Location: ' . boutique_url('index.php', BOUTIQUE_SLUG));
        exit;
    }
    $categorie_nom = (string) $generale_row['nom'];

    if (function_exists('count_genres_linked_to_categorie_generale')
        && count_genres_linked_to_categorie_generale($generale_id) > 0) {
        $genres_pour_filtre_rayon = get_genres_linked_to_categorie_generale($generale_id);
        if ($filter_genre_id > 0) {
            $genre_ok = false;
            foreach ($genres_pour_filtre_rayon as $grow) {
                if ((int) ($grow['id'] ?? 0) === $filter_genre_id) {
                    $genre_ok = true;
                    break;
                }
            }
            if (!$genre_ok) {
                $filter_genre_id = 0;
            }
        }
    } else {
        $filter_genre_id = 0;
    }

    if ($filter_sous_categorie_id > 0) {
        if (!function_exists('categorie_plateforme_liee_au_rayon')
            || !categorie_plateforme_liee_au_rayon($filter_sous_categorie_id, $generale_id)) {
            $filter_sous_categorie_id = 0;
        }
    }
    $produits = get_produits_by_categorie_generale(
        $generale_id,
        BOUTIQUE_ADMIN_ID,
        $filter_genre_id > 0 ? $filter_genre_id : null,
        $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : null
    );
} elseif ($categorie_id > 0) {
    $filter_genre_id = 0;
    $filter_sous_categorie_id = 0;
    $categorie = get_categorie_by_id($categorie_id);
    if (!$categorie || !is_array($categorie) || empty($categorie['nom'])) {
        header('Location: ' . boutique_url('index.php', BOUTIQUE_SLUG));
        exit;
    }
    $categorie_nom = (string) $categorie['nom'];
    $produits = get_produits_by_categorie($categorie_id, BOUTIQUE_ADMIN_ID);
    if ($produits === false) {
        $produits = [];
    }
} else {
    header('Location: ' . boutique_url('index.php', BOUTIQUE_SLUG));
    exit;
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/../controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/../controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';
$base = get_site_base_url();
$seo_title = $categorie_nom . ' - ' . BOUTIQUE_NOM;
if ($generale_row) {
    $desc_cat = !empty($generale_row['description']) ? strip_tags((string) $generale_row['description']) : 'Catalogue « ' . $categorie_nom . ' » — ' . BOUTIQUE_NOM . '.';
    $seo_q = 'categorie.php?generale=' . (int) $generale_id;
    if (!empty($filter_genre_id)) {
        $seo_q .= '&genre=' . (int) $filter_genre_id;
    }
    if (!empty($filter_sous_categorie_id)) {
        $seo_q .= '&sous_categorie=' . (int) $filter_sous_categorie_id;
    }
    $seo_canonical = $base . boutique_url($seo_q, BOUTIQUE_SLUG);
} else {
    $desc_cat = !empty($categorie['description'])
        ? strip_tags((string) $categorie['description'])
        : 'Catégorie « ' . $categorie_nom . ' » chez ' . BOUTIQUE_NOM . ' sur COLObanes, marketplace des boutiques du Sénégal.';
    $seo_canonical = $base . boutique_url('categorie.php?id=' . (int) $categorie_id, BOUTIQUE_SLUG);
}
$seo_description = mb_substr($desc_cat, 0, 160);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/../includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutique-vitrine-products.css<?php echo asset_version_query(); ?>">
    <style>
        /* Styles personnalisés pour les cartes produits */
    </style>
</head>

<body class="boutique-vitrine">

    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
    <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--success-bg); border-left: 4px solid var(--bleu); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--error-bg); border-left: 4px solid var(--error-border); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>
    <section class="section00">
        <section class="produit_vedetes">
            <div class="box1">
                <h1><?php echo htmlspecialchars($categorie_nom); ?></h1>
            </div>

            <?php if ($generale_id > 0 && !empty($genres_pour_filtre_rayon) && function_exists('nav_categorie_generale_genre_href')): ?>
            <div class="boutique-cat-genre-filtres" style="max-width: 1200px; margin: 0 auto 18px; padding: 0 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; justify-content: center;">
                <span style="font-size: 12px; font-weight: 700; color: var(--texte-mute); width: 100%; text-align: center;">Par genre</span>
                <?php
                $g_h0 = function_exists('nav_categorie_generale_filtre_href')
                    ? nav_categorie_generale_filtre_href($generale_id, 0, $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : 0)
                    : nav_categorie_generale_genre_href($generale_id, 0);
                ?>
                <a href="<?php echo htmlspecialchars($g_h0); ?>"
                    style="padding: 8px 14px; border-radius: 999px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid var(--border-input, #ddd);
                    <?php echo $filter_genre_id === 0 ? 'background: var(--couleur-dominante); color: #fff; border-color: transparent;' : 'background: var(--fond-secondaire, #f5f5f5); color: var(--titres);'; ?>">Tous</a>
                <?php foreach ($genres_pour_filtre_rayon as $grow): ?>
                <?php
                $gpid = (int) ($grow['id'] ?? 0);
                if ($gpid <= 0) {
                    continue;
                }
                $active = $filter_genre_id === $gpid;
                $g_h = function_exists('nav_categorie_generale_filtre_href')
                    ? nav_categorie_generale_filtre_href(
                        $generale_id,
                        $gpid,
                        $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : 0
                    )
                    : nav_categorie_generale_genre_href($generale_id, $gpid);
                ?>
                <a href="<?php echo htmlspecialchars($g_h); ?>"
                    style="padding: 8px 14px; border-radius: 999px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid var(--border-input, #ddd);
                    <?php echo $active ? 'background: var(--couleur-dominante); color: #fff; border-color: transparent;' : 'background: var(--fond-secondaire, #f5f5f5); color: var(--titres);'; ?>">
                    <?php echo htmlspecialchars((string) ($grow['nom'] ?? 'Genre')); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($produits)): ?>
                <div style="text-align: center; padding: 40px; color: var(--gris-moyen);">
                    <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                    <a href="<?php echo htmlspecialchars(boutique_url('index.php', BOUTIQUE_SLUG)); ?>"
                        style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: var(--couleur-dominante); color: var(--texte-clair); text-decoration: none; border-radius: 5px; transition: background 0.3s ease;">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
            <?php else: ?>
                <div class="mp-grid" data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000"
                    data-aos-once="false" style="padding: 0 12px;">
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $has_promo = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                        $prix_affichage = $has_promo ? $produit['prix_promotion'] : $produit['prix'];
                        $pourcentage_reduction = $has_promo ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                        ?>
                        <article class="mp-card">
                            <?php require __DIR__ . '/../includes/partials/product_share_button.php'; ?>
                            <a href="/produit.php?id=<?php echo (int)$produit['id']; ?>" class="mp-card-link">
                                    <div class="mp-card-img">
                                    <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                                        alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                                        loading="lazy" onerror="this.src='/image/produit1.jpg'">
                                </div>
                                <div class="mp-card-body">
                                    <p class="mp-card-title"><?php echo htmlspecialchars($produit['nom']); ?></p>
                                    <div class="mp-card-price-row">
                                        <?php if ($has_promo): ?>
                                        <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                                        <span class="mp-card-price-old"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</span>
                                        <span class="mp-card-badge mp-card-badge--nouveau mp-card-badge--inline">-<?php echo $pourcentage_reduction; ?>%</span>
                                        <?php else: ?>
                                        <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <div class="mp-card-cart">
                                <form method="POST" action="/add-to-panier.php">
                                    <?php boutique_add_to_panier_hidden_fields(); ?>
                                    <input type="hidden" name="produit_id" value="<?php echo (int)$produit['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/categorie.php'); ?>">
                                    <button type="submit" class="mp-card-btn">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>

    <?php include __DIR__ . '/../footer.php'; ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="/js/owl.carousel.min.js"></script>
    <script src="/js/owl.carousel.js"></script>
    <script src="/js/owl.animate.js"></script>
    <script src="/js/owl.autoplay.js"></script>

    <script>
        $(document).ready(function () {
            AOS.init();
        });
    </script>

    <script>
        // ..
        AOS.init();
    </script>

</body>

</html>