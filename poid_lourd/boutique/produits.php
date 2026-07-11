<?php
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/_init.php';

// Inclusion des modèles
require_once __DIR__ . '/../models/model_produits.php';

// Récupérer les produits (recherche + filtres ou tous)
$produits_tous = [];
$total_produits = 0;
$recherche_actuelle = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$prix_min = isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float) $_GET['prix_min'] : null;
$prix_max = isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float) $_GET['prix_max'] : null;
$categorie_id = isset($_GET['categorie']) && $_GET['categorie'] !== '' ? (int) $_GET['categorie'] : null;
$tri = isset($_GET['tri']) && in_array($_GET['tri'], ['date', 'prix_asc', 'prix_desc', 'nom']) ? $_GET['tri'] : 'date';
$has_filters = !empty($recherche_actuelle) || $prix_min !== null || $prix_max !== null || $categorie_id !== null || $tri !== 'date';

if (file_exists(__DIR__ . '/../models/model_produits.php')) {
    if ($has_filters) {
        $produits_tous = search_produits_with_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id, $tri, 0, 20, BOUTIQUE_ADMIN_ID);
        $total_produits = count_search_produits_with_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id, BOUTIQUE_ADMIN_ID);
    } else {
        $produits_tous = get_all_produits_paginated(0, 20, BOUTIQUE_ADMIN_ID);
        $total_produits = count_all_produits_actifs(BOUTIQUE_ADMIN_ID);
    }
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/../controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/../controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Catalogue — ' . BOUTIQUE_NOM . ' | ' . SITE_BRAND_NAME;
$seo_description = 'Catalogue produits de la boutique ' . BOUTIQUE_NOM . ' sur ' . SITE_BRAND_NAME . '. Achat en ligne, vendeur au Sénégal, marketplace multi-boutiques.';
$seo_keywords = site_brand_seo_keywords_default() . ', ' . BOUTIQUE_NOM;
$seo_canonical = $base . boutique_url('produits.php', BOUTIQUE_SLUG);
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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutique-vitrine-products.css<?php echo asset_version_query(); ?>">
    <style>
        @keyframes produitsFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        #produits-container { animation: produitsFadeIn 0.25s ease-out both; }
        .mp-card { animation: produitsFadeIn 0.22s ease-out both; }
        .mp-card:nth-child(1)  { animation-delay: 0.00s; }
        .mp-card:nth-child(2)  { animation-delay: 0.03s; }
        .mp-card:nth-child(3)  { animation-delay: 0.06s; }
        .mp-card:nth-child(4)  { animation-delay: 0.09s; }
        .mp-card:nth-child(n+5){ animation-delay: 0.00s; }
        .mp-card--loading-anim { animation: produitsFadeIn 0.22s ease-out both; }
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
            color: var(--texte-clair);
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
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
        }

        .btn-voir-plus:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .produits-count {
            text-align: center;
            margin-top: 15px;
            color: var(--gris-moyen);
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

<body class="boutique-vitrine">
    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <div class="produits-page-header">
        <h1><i class="fas fa-box"></i>
            <?php echo !empty($recherche_actuelle) ? 'Résultats pour "' . htmlspecialchars($recherche_actuelle) . '"' : 'Tous nos produits'; ?>
        </h1>
        <p><?php echo $has_filters ? $total_produits . ' produit(s) trouvé(s)' : 'Découvrez notre sélection complète de produits naturels'; ?>
        </p>
        <?php if ($has_filters): ?>
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
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--success-bg); border-left: 4px solid var(--bleu); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--error-bg); border-left: 4px solid var(--error-border); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <div class="produits-container-wrapper">
        <section class="section00">
            <section class="produit_vedetes">
                <div class="mp-grid" id="produits-container" style="padding: 0 12px;">
                    <?php if (empty($produits_tous)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--gris-moyen); width: 100%; grid-column: 1/-1;">
                            <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                            <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($produits_tous as $produit): ?>
                            <?php
                            $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                                ? $produit['prix_promotion'] : $produit['prix'];
                            $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                            $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                            ?>
                            <article class="mp-card" data-produit-id="<?php echo (int)$produit['id']; ?>">
                                <?php require __DIR__ . '/../includes/partials/product_share_button.php'; ?>
                                <a href="/produit.php?id=<?php echo (int)$produit['id']; ?>" class="mp-card-link">
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
                                        <input type="hidden" name="produit_id" value="<?php echo (int)$produit['id']; ?>">
                                        <input type="hidden" name="quantite" value="1">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/produits.php'); ?>">
                                        <button type="submit" class="mp-card-btn">
                                            <i class="fa-solid fa-cart-shopping"></i> Ajouter
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($produits_tous) && $total_produits > 20): ?>
                    <div id="infinite-sentinel" style="height:1px;"></div>
                    <div id="loading-indicator" style="display:none;text-align:center;padding:24px;">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.6rem;color:var(--couleur-dominante);"></i>
                    </div>
                    <p id="produits-count" class="produits-count">
                        Affichés : <span id="count-actuel">20</span> / <?php echo $total_produits; ?> produits
                    </p>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <?php include __DIR__ . '/../footer.php'; ?>

    <script>
        (function () {
            let offsetActuel = 20;
            const totalProduits = <?php echo $total_produits; ?>;
            let loading = false;
            const container = document.getElementById('produits-container');
            const sentinel  = document.getElementById('infinite-sentinel');
            const loader    = document.getElementById('loading-indicator');
            const countEl   = document.getElementById('count-actuel');
            const boutiqueSlugForPanier = <?php echo json_encode((string) BOUTIQUE_SLUG, JSON_UNESCAPED_UNICODE); ?>;

            const apiBaseParams = '<?php
            $p = ['offset' => 0, 'limit' => 20, 'boutique' => BOUTIQUE_SLUG];
            if ($has_filters) {
                if (!empty($recherche_actuelle)) $p['recherche'] = $recherche_actuelle;
                if ($prix_min !== null)           $p['prix_min']  = $prix_min;
                if ($prix_max !== null)           $p['prix_max']  = $prix_max;
                if ($categorie_id !== null)       $p['categorie'] = $categorie_id;
                $p['tri'] = $tri;
            }
            echo http_build_query($p);
            ?>';

            function getApiUrl() {
                const params = new URLSearchParams(apiBaseParams);
                params.set('offset', offsetActuel);
                return '/api/get_produits.php?' + params.toString();
            }

            function formatNumber(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0'); }

            function escapeHtml(t) {
                const d = document.createElement('div');
                d.textContent = t == null ? '' : t;
                return d.innerHTML;
            }

            function buildCard(produit) {
                const article = document.createElement('article');
                article.className = 'mp-card mp-card--loading-anim';
                article.setAttribute('data-produit-id', produit.id);
                let badgeHTML = produit.has_promotion && produit.pourcentage_promo
                    ? `<span class="mp-card-badge mp-card-badge--nouveau mp-card-badge--inline">-${produit.pourcentage_promo}%</span>` : '';
                let prixHTML = produit.has_promotion
                    ? `<span class="mp-card-price">${formatNumber(produit.prix_affichage)} FCFA</span><span class="mp-card-price-old">${formatNumber(produit.prix)} FCFA</span>${badgeHTML}`
                    : `<span class="mp-card-price">${formatNumber(produit.prix_affichage)} FCFA</span>`;
                const returnUrl = (window.location.pathname + window.location.search).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                const boutiqueField = boutiqueSlugForPanier
                    ? `<input type="hidden" name="boutique_slug" value="${escapeHtml(boutiqueSlugForPanier)}">` : '';
                const shareUrl = window.location.origin + '/produit.php?id=' + produit.id;
                const shareText = 'Découvrez « ' + (produit.nom || 'Produit') + ' » à ' + formatNumber(produit.prix_affichage) + ' FCFA sur Colobanes :\n' + shareUrl;
                const shareHtml = (typeof window.buildProductShareHtml === 'function')
                    ? window.buildProductShareHtml({ url: shareUrl, text: shareText, title: produit.nom || 'Produit' })
                    : '';
                article.innerHTML = `
                    ${shareHtml}
                    <a href="/produit.php?id=${produit.id}" class="mp-card-link">
                        <div class="mp-card-img"><img src="${escapeHtml(produit.image_url || ('/upload/' + (produit.image_principale || 'produit1.jpg')))}" alt="${escapeHtml(produit.nom)}" loading="lazy" onerror="this.src='/image/produit1.jpg'"></div>
                        <div class="mp-card-body"><p class="mp-card-title">${escapeHtml(produit.nom)}</p><div class="mp-card-price-row">${prixHTML}</div></div>
                    </a>
                    <div class="mp-card-cart"><form method="POST" action="/add-to-panier.php">
                        ${boutiqueField}
                        <input type="hidden" name="produit_id" value="${produit.id}">
                        <input type="hidden" name="quantite" value="1">
                        <input type="hidden" name="return_url" value="${returnUrl}">
                        <button type="submit" class="mp-card-btn"><i class="fa-solid fa-cart-shopping"></i> Ajouter</button>
                    </form></div>`;
                return article;
            }

            function charger() {
                if (loading || offsetActuel >= totalProduits || !sentinel) return;
                loading = true;
                if (loader) loader.style.display = 'block';

                fetch(getApiUrl())
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.produits.length > 0) {
                            data.produits.forEach(p => container.appendChild(buildCard(p)));
                            offsetActuel += data.produits.length;
                            if (countEl) countEl.textContent = offsetActuel;
                        }
                        if (offsetActuel >= totalProduits && sentinel) sentinel.remove();
                    })
                    .catch(() => {})
                    .finally(() => {
                        loading = false;
                        if (loader) loader.style.display = 'none';
                    });
            }

            if (sentinel && 'IntersectionObserver' in window) {
                const obs = new IntersectionObserver(entries => {
                    if (entries[0].isIntersecting) charger();
                }, { rootMargin: '200px' });
                obs.observe(sentinel);
            }
        })();
    </script>
</body>

</html>