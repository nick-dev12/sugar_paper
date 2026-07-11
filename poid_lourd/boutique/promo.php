<?php
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/_init.php';

require_once __DIR__ . '/../models/model_produits.php';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$produits = get_produits_en_promo($offset, $limit, BOUTIQUE_ADMIN_ID);
$total_produits = count_produits_en_promo(BOUTIQUE_ADMIN_ID);
$total_pages = $total_produits > 0 ? ceil($total_produits / $limit) : 1;

if (file_exists(__DIR__ . '/../controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/../controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Promotions — ' . BOUTIQUE_NOM . ' | ' . SITE_BRAND_NAME;
$seo_description = 'Promotions et offres de la boutique ' . BOUTIQUE_NOM . ' sur ' . SITE_BRAND_NAME . ', marketplace au Sénégal.';
$seo_keywords = site_brand_seo_keywords_default() . ', promotions, ' . BOUTIQUE_NOM;
$seo_canonical = $base . boutique_url('promo.php', BOUTIQUE_SLUG);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/../includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutique-vitrine-products.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--accent-promo) 0%, var(--orange-fonce) 100%);
            padding: 40px 20px;
            text-align: center;
            color: var(--texte-clair);
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 0;
            font-weight: 700;
        }

        .produits-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 80px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gris-moyen);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .empty-state a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: var(--accent-promo);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .empty-state a:hover {
            background: var(--boutons-secondaires-hover);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            background: var(--blanc);
            border: 1px solid var(--glass-border);
            color: var(--texte-fonce);
            font-weight: 600;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: var(--accent-promo);
            color: var(--texte-clair);
            border-color: var(--accent-promo);
        }

        .pagination .current {
            background: var(--accent-promo);
            color: var(--texte-clair);
            border-color: var(--accent-promo);
        }
    </style>
</head>

<body class="boutique-vitrine">
    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <div class="page-header">
        <h1><i class="fas fa-percent"></i> Promotions</h1>
    </div>

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
    <div class="produits-container-wrapper">
        <section class="section00">
            <section class="produit_vedetes">
                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <p>Aucun produit en promotion pour le moment.</p>
                        <a href="<?php echo htmlspecialchars(boutique_url('produits.php', BOUTIQUE_SLUG)); ?>"><i class="fas fa-box"></i> Voir tous les produits</a>
                    </div>
                <?php else: ?>
                    <div class="carousel-produits-outer">
                        <div class="mp-grid" id="promo-grid"
                            data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-once="true">
                            <?php foreach ($produits as $produit): ?>
                            <?php
                            $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                                ? $produit['prix_promotion'] : $produit['prix'];
                            $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                            $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                            ?>
                            <article class="mp-card" data-produit-id="<?php echo (int) $produit['id']; ?>">
                                <?php require __DIR__ . '/../includes/partials/product_share_button.php'; ?>
                                <a href="/produit.php?id=<?php echo (int) $produit['id']; ?>" class="mp-card-link">
                                    <div class="mp-card-img">
                                        <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                                            alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                            loading="lazy" onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="mp-card-body">
                                        <p class="mp-card-title"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                                        <div class="mp-card-price-row">
                                            <?php if ($has_promotion): ?>
                                            <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                                            <span class="mp-card-price-old"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</span>
                                            <span class="mp-card-badge mp-card-badge--nouveau mp-card-badge--inline">-<?php echo $pourcentage_promo; ?>%</span>
                                            <?php else: ?>
                                            <span class="mp-card-price"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <div class="mp-card-cart">
                                    <form method="POST" action="/add-to-panier.php">
                                        <?php boutique_add_to_panier_hidden_fields(); ?>
                                        <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
                                        <input type="hidden" name="quantite" value="1">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/promo.php'); ?>">
                                        <button type="submit" class="mp-card-btn">
                                            <i class="fa-solid fa-cart-shopping"></i> Ajouter
                                        </button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i> Précédent</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>">Suivant <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>AOS.init();</script>
</body>

</html>