<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_prix_display.php';
require_once __DIR__ . '/includes/produit_share.php';
require_once __DIR__ . '/includes/home_sections.php';

$section_key = isset($_GET['section']) ? normalize_produit_section_accueil($_GET['section']) : null;
$section_config = $section_key ? get_home_section_config($section_key) : null;

if (!$section_key || !$section_config) {
    header('Location: produits.php');
    exit;
}

$limit = 20;
$produits = get_produits_by_home_section($section_key, 0, $limit);
$total_produits = count_produits_by_home_section($section_key);
$return_url = 'section-produits.php?section=' . rawurlencode($section_key);

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = $section_config['title'] . ' - Sugar Paper';
$seo_description = $section_config['desc'];
$seo_canonical = $base . '/section-produits.php?section=' . rawurlencode($section_key);
$page_icon = $section_config['page_icon'] ?? 'fa-box-open';
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
    <?php include __DIR__ . '/includes/platform_share_head.php'; ?>
    <style>
        .page-header {
            background: var(--couleur-dominante);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
            margin-bottom: 40px;
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

<body>
    <?php include 'nav_bar.php'; ?>

    <div class="page-header">
        <h1><i class="fas <?php echo htmlspecialchars($page_icon); ?>"></i> <?php echo htmlspecialchars($section_config['title']); ?></h1>
        <p><?php echo htmlspecialchars($section_config['desc']); ?></p>
        <a href="index.php#<?php echo htmlspecialchars($section_config['id']); ?>" class="page-header-back">
            <i class="fas fa-arrow-left"></i> Retour à l'accueil
        </a>
    </div>

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

    <div class="produits-container-wrapper">
        <section class="section00">
            <section class="produit_vedetes">
                <article class="articles carousel11" id="produits-container">
                    <?php if (empty($produits)): ?>
                    <div class="empty-state" style="width:100%;">
                        <i class="fas fa-box-open" style="font-size:48px;opacity:0.4;margin-bottom:16px;"></i>
                        <p>Aucun produit dans cette section pour le moment.</p>
                        <a href="index.php" style="display:inline-block;margin-top:16px;color:var(--couleur-dominante);">Retour à l'accueil</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                    <div class="carousel" data-produit-id="<?php echo (int) $produit['id']; ?>">
                        <?php echo produit_share_button_html($produit); ?>
                        <a href="produit.php?id=<?php echo (int) $produit['id']; ?>" class="product-card-link">
                            <div class="image-wrapper">
                                <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
                                    alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
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
                        <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                            <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
                            <input type="hidden" name="quantite" value="1">
                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                            <button type="submit" class="btn-add-cart">
                                <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                            </button>
                        </form>
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
                        Affichés : <span id="count-actuel"><?php echo min($limit, $total_produits); ?></span> / <?php echo (int) $total_produits; ?> produits
                    </p>
                </div>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <?php include 'footer.php'; ?>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>
    <script src="/js/produit-card-share.js<?php echo asset_version_query(); ?>"></script>
    <script>
        const sectionKey = <?php echo json_encode($section_key); ?>;
        let offsetActuel = <?php echo (int) min($limit, max(count($produits), 0)); ?>;
        const limit = <?php echo (int) $limit; ?>;
        const totalProduits = <?php echo (int) $total_produits; ?>;
        const returnUrl = <?php echo json_encode($return_url); ?>;

        function formatNumber(n) {
            return Number(n).toLocaleString('fr-FR');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function chargerPlusProduits() {
            const btn = document.getElementById('btn-voir-plus');
            const container = document.getElementById('produits-container');
            const countActuel = document.getElementById('count-actuel');
            if (!btn || !container) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';

            fetch('api/get_produits_section.php?section=' + encodeURIComponent(sectionKey) + '&offset=' + offsetActuel + '&limit=' + limit)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.produits.length > 0) {
                        data.produits.forEach(function(produit) {
                            const div = document.createElement('div');
                            div.className = 'carousel';
                            div.setAttribute('data-produit-id', produit.id);

                            const prixClass = produit.show_price_from ? 'prix prix--from' : 'prix';
                            const fromLabel = produit.show_price_from
                                ? '<span class="prix-from-label">À partir de</span>'
                                : '';
                            let prixHTML = '';
                            if (produit.has_promotion) {
                                prixHTML = fromLabel + '<span class="span2">' + formatNumber(produit.prix) + ' FCFA</span>'
                                    + '<span class="prix-promo">' + formatNumber(produit.prix_affichage) + ' FCFA</span>'
                                    + '<span class="span3">-' + produit.pourcentage_promo + '%</span>';
                            } else {
                                prixHTML = fromLabel + formatNumber(produit.prix_affichage) + '<span class="span1"> FCFA</span>';
                            }

                            const shareBtnHtml = (typeof buildProduitShareButtonHtml === 'function')
                                ? buildProduitShareButtonHtml(produit)
                                : '';

                            div.innerHTML = shareBtnHtml
                                + '<a href="produit.php?id=' + produit.id + '" class="product-card-link">'
                                + '<div class="image-wrapper"><img src="' + (produit.image_url || '/image/produit1.jpg') + '" alt="' + escapeHtml(produit.nom) + '" onerror="this.src=\'/image/produit1.jpg\'"></div>'
                                + '<div class="produit-content"><p id="nom">' + escapeHtml(produit.nom) + '</p>'
                                + (produit.categorie_nom ? '<p id="ville">' + escapeHtml(produit.categorie_nom) + '</p>' : '')
                                + '<p class="' + prixClass + '">' + prixHTML + '</p></div></a>'
                                + '<form method="POST" action="/add-to-panier.php" class="add-to-cart-form">'
                                + '<input type="hidden" name="produit_id" value="' + produit.id + '">'
                                + '<input type="hidden" name="quantite" value="1">'
                                + '<input type="hidden" name="return_url" value="' + escapeHtml(returnUrl) + '">'
                                + '<button type="submit" class="btn-add-cart"><i class="fa-solid fa-cart-shopping"></i> Ajouter au panier</button>'
                                + '</form>';

                            container.appendChild(div);
                        });

                        offsetActuel += data.produits.length;
                        if (countActuel) countActuel.textContent = offsetActuel;

                        if (offsetActuel >= totalProduits) {
                            btn.style.display = 'none';
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
                        }
                    } else {
                        btn.style.display = 'none';
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
                });
        }

        const btnVoirPlus = document.getElementById('btn-voir-plus');
        if (btnVoirPlus) {
            btnVoirPlus.addEventListener('click', chargerPlusProduits);
        }
    </script>
</body>

</html>
