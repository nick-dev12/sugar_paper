<?php
session_start();


// Inclusion du fichier de connexion à la BDD

// Récupérez l'ID du commerçant à partir de la session
// Récupérez l'ID de l'utilisateur depuis la variable de session
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Sugar Paper - Produits naturels de qualité';
$seo_description = 'Sugar Paper, boutique en ligne de produits naturels : noix, feuilles, fruits, huiles, céréales, racines. Produits 100% naturels issus de notre production.';
$seo_canonical = $base . '/';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai&family=Rozha+One&family=Playfair+Display:wght@400;600;700&family=Quicksand:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <style>
    .btn-commande-perso:hover {
        background: rgba(229, 72, 138, 0.9) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(229, 72, 138, 0.4);
    }

    /* Nouveaux produits et Produits populaires : flex-wrap, Owl désactivé, 6 produits max */
    .carousel-produits-outer {
        position: relative;
        width: 100%;
    }

    .carousel-produits-outer .carousel1.carousel1-flex-mode {
        display: flex !important;
        flex-wrap: wrap;
        justify-content: space-around;
        align-items: flex-start;
        gap: 15px;
        padding: 15px;
    }

    .carousel-produits-outer .carousel1.carousel1-flex-mode .carousel {
        width: 320px;
        min-width: 170px;
        max-width: 320px;
        flex: 0 0 320px;
    }

    .carousel-produits-outer .carousel1.carousel1-flex-mode .carousel:nth-child(n+7) {
        display: none !important;
    }

    @media (max-width: 650px) {
        .carousel-produits-outer .carousel1.carousel1-flex-mode {
            gap: 12px;
            padding: 12px;
        }
    }

    @media (max-width: 400px) {
        .carousel-produits-outer .carousel1.carousel1-flex-mode {
            gap: 10px;
            padding: 10px;
        }
    }
    </style>

</head>


<body>

    <?php include('nav_bar.php') ?>


    <?php
    // Récupérer les slides depuis la base de données
    $slides = [];
    if (file_exists(__DIR__ . '/models/model_slider.php')) {
        require_once __DIR__ . '/models/model_slider.php';
        $slides_result = get_all_slides('actif'); // Récupérer uniquement les slides actifs
        $slides = is_array($slides_result) ? $slides_result : [];
    }
    ?>

    <div class="slider-area owl-carousel">
        <?php if (empty($slides)): ?>

        <?php else: ?>
        <?php foreach ($slides as $slide): ?>
        <div class="slider-item">
            <img src="/upload/slider/<?php echo htmlspecialchars($slide['image']); ?>"
                alt="<?php echo htmlspecialchars($slide['titre']); ?>" onerror="this.src='/image/produit1.jpg'">

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
    <div class="commande-perso-success"
        style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(32, 197, 199, 0.15); border-left: 4px solid var(--turquoise); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="commande-perso-success"
        style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(229, 72, 138, 0.15); border-left: 4px solid var(--couleur-dominante); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['commande_perso_success'])): ?>
    <div class="commande-perso-success"
        style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(32, 197, 199, 0.15); border-left: 4px solid var(--turquoise); border-radius: 8px; color: var(--titres);">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['commande_perso_success']); unset($_SESSION['commande_perso_success']); ?>
    </div>
    <?php endif; ?>

    <section class="services-banner">
        <div class="services-banner-inner">
            <div class="service-item">
                <div class="service-icon"><i class="fa-solid fa-headset"></i></div>
                <h3>Service</h3>
                <p>Une équipe à votre écoute</p>
            </div>
            <div class="service-divider"></div>
            <div class="service-item">
                <div class="service-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h3>Satisfaction garantie</h3>
                <p>Produits de qualité certifiée</p>
            </div>
            <div class="service-divider"></div>
            <div class="service-item">
                <div class="service-icon"><i class="fa-solid fa-rotate-left"></i></div>
                <h3>Service après vente</h3>
                <p>Accompagnement personnalisé</p>
            </div>
            <div class="service-divider"></div>
            <div class="service-item">
                <div class="service-icon"><i class="fa-solid fa-clock"></i></div>
                <h3>Disponible 7 jours sur 7</h3>
                <p>Commandez quand vous voulez</p>
            </div>
            <div class="service-divider"></div>
            <div class="service-item">
                <div class="service-icon"><i class="fa-solid fa-truck-fast"></i></div>
                <h3>Livraison rapide</h3>
                <p>Réception en temps record</p>
            </div>
        </div>
        <div class="commande-perso-cta" style="margin-top: 25px; text-align: center;">
            <a href="commande-personnalisee.php" class="btn-commande-perso"
                style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; background: var(--couleur-dominante); color: #fff !important; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 15px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(229, 72, 138, 0.3);">
                <i class="fas fa-palette"></i> Commande personnalisée
            </a>
            <p style="margin-top: 10px; font-size: 13px; color: var(--texte-fonce); opacity: 0.9;">Des produits sur
                mesure ? Décrivez-nous vos besoins</p>
        </div>
    </section>



    <?php
    // Récupérer les catégories depuis la base de données
    $categories = [];
    if (file_exists(__DIR__ . '/models/model_categories.php')) {
        require_once __DIR__ . '/models/model_categories.php';
        $categories_result = get_all_categories_with_count();
        $categories = is_array($categories_result) ? $categories_result : [];
    }
    ?>

    <section class="categorie owl-carousel">
        <?php if (empty($categories)): ?>
        <!-- Message si aucune catégorie -->
        <div class="message-vide" style="text-align: center; padding: 40px; color: var(--texte-fonce); width: 100%;">
            <p style="font-size: 16px;">Aucune catégorie disponible pour le moment.</p>
        </div>
        <?php else: ?>
        <?php foreach ($categories as $categorie): ?>
        <a href="categorie.php?id=<?php echo $categorie['id']; ?>" style="text-decoration: none; color: inherit;">
            <div class="item">
                <?php if ($categorie['image']): ?>
                <img class="img" src="/upload/<?php echo htmlspecialchars($categorie['image']); ?>"
                    alt="<?php echo htmlspecialchars($categorie['nom']); ?>" onerror="this.src='/image/produit1.jpg'">
                <?php else: ?>
                <img class="img" src="/image/produit1.jpg" alt="<?php echo htmlspecialchars($categorie['nom']); ?>">
                <?php endif; ?>
                <p><?php echo htmlspecialchars($categorie['nom']); ?></p>
                <span><?php echo (int) $categorie['nb_produits']; ?>
                    element<?php echo (int) $categorie['nb_produits'] > 1 ? 's' : ''; ?></span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>




    <?php
    // Récupérer les 10 derniers produits publiés (nouveautés)
    $produits_nouveaux = [];
    if (file_exists(__DIR__ . '/models/model_produits.php')) {
        require_once __DIR__ . '/models/model_produits.php';
        $produits_nouveaux = get_all_produits_paginated(0, 10);
    }
    ?>

    <section class="produit_vedete">
        <div class="box1">
            <span></span>
            <h1>NOUVEAUX PRODUITS</h1>
            <span></span>
        </div>



        <div class="carousel-produits-outer">
            <article data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-easing="ease-in-out"
                data-aos-mirror="true" data-aos-once="true" data-aos-anchor-placement="top-bottom"
                class="articles carousel1 carousel1-flex-mode" id="carousel-nouveaux">
                <?php if (empty($produits_nouveaux)): ?>
                <!-- Message si aucun produit -->
                <div class="carousel message-vide" style="text-align: center; padding: 40px; width: 100%;">
                    <p style="color: var(--texte-fonce); font-size: 16px;">Aucun produit publié pour le moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($produits_nouveaux as $produit): ?>
                <?php
                    // Calculer le prix à afficher
                    $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                        ? $produit['prix_promotion']
                        : $produit['prix'];
                    $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                    $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                    ?>
                <div class="carousel">
                    <a href="produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
                        <div class="image-wrapper">
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? 'produit1.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                onerror="this.src='/image/produit1.jpg'">
                        </div>
                        <div class="produit-content">
                            <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                            <?php if (!empty($produit['categorie_nom'])): ?>
                            <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                            <?php endif; ?>
                            <p class="prix">
                                <?php if ($has_promotion): ?>
                                <span class="span2"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                    FCFA</span>
                                <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                    FCFA</span>
                                <?php else: ?>
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?><span class="span1">
                                    FCFA</span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($produit['stock'])): ?>
                            <p class="produit-card-stock-info">
                                <strong>Stock:</strong> <?php echo $produit['stock']; ?>

                            </p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                        <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                        <input type="hidden" name="quantite" value="1">
                        <input type="hidden" name="return_url"
                            value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/index.php'); ?>">
                        <button type="submit" class="btn-add-cart">
                            <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </div>
    </section>


    <?php
    // Récupérer la configuration de la section4
    $section4_config = [
        'titre' => 'Bienvenue au Sugar Paper',
        'texte' => 'Tous les produits a petit prix',
        'image_fond' => 'market.png',
        'statut' => 'actif'
    ];

    if (file_exists(__DIR__ . '/models/model_section4.php')) {
        require_once __DIR__ . '/models/model_section4.php';
        $config_result = get_section4_config();
        if ($config_result) {
            $section4_config = $config_result;
        }
    }

    // Afficher la section4 uniquement si statut = actif
    $section4_actif = ($section4_config['statut'] ?? 'actif') === 'actif';
    $section4_titre = trim($section4_config['titre'] ?? '');
    $section4_texte = trim($section4_config['texte'] ?? '');

    // Chemin de l'image de fond
    $image_fond_path = '/image/market.png';
    if (!empty($section4_config['image_fond'])) {
        $upload_path = '/upload/section4/' . htmlspecialchars($section4_config['image_fond']);
        $file_path = __DIR__ . '/upload/section4/' . $section4_config['image_fond'];
        if (file_exists($file_path)) {
            $image_fond_path = $upload_path;
        }
    }
    ?>
    <?php if ($section4_actif): ?>
    <section class="section4">
        <div class="slider" style="background-image: url('<?php echo $image_fond_path; ?>');">
            <?php if ($section4_titre !== ''): ?>
            <div class="box">
                <div class="text">
                    <h1><?php echo htmlspecialchars($section4_titre); ?></h1>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($section4_texte !== ''): ?>
            <p><?php echo htmlspecialchars($section4_texte); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php
    // Récupérer les vidéos pour le carrousel
    $videos = [];
    if (file_exists(__DIR__ . '/models/model_videos.php')) {
        require_once __DIR__ . '/models/model_videos.php';
        $videos = get_all_videos('actif');
    }
    
    // Afficher la section seulement s'il y a des vidéos
    if (!empty($videos)):
    ?>
    <section class="galerie-creations">
        <div class="galerie-creations-container">
            <header class="galerie-header">
                <span class="galerie-surtitre">Découvrez</span>
                <h2 class="galerie-titre">Nos créations</h2>
                <p class="galerie-sous-titre">Une sélection de nos réalisations en vidéo</p>
            </header>

            <div class="galerie-grid" id="videosSlider">
                <?php foreach ($videos as $index => $video): ?>
                <article class="galerie-item">
                    <div class="galerie-card">
                        <div class="galerie-video-wrapper">
                            <video class="galerie-video" controls preload="metadata" playsinline
                                <?php if (!empty($video['image_preview'])): ?>
                                poster="/upload/videos/thumbnails/<?php echo htmlspecialchars($video['image_preview']); ?>"
                                <?php endif; ?>>
                                <source src="/upload/videos/<?php echo htmlspecialchars($video['fichier_video']); ?>">
                                Votre navigateur ne supporte pas la lecture de vidéos.
                            </video>
                            <div class="galerie-play-overlay">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <?php if (!empty($video['titre'])): ?>
                        <div class="galerie-caption">
                            <h3><?php echo htmlspecialchars($video['titre']); ?></h3>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.galerie-video').forEach(function(video) {
            var overlay = video.nextElementSibling;
            if (overlay && overlay.classList.contains('galerie-play-overlay')) {
                video.addEventListener('play', function() {
                    video.classList.add('playing');
                    overlay.style.opacity = '0';
                });
                video.addEventListener('pause', function() {
                    video.classList.remove('playing');
                    overlay.style.opacity = '1';
                });
            }
        });
    });
    </script>

    <?php
    // Récupérer les produits les plus visités
    $produits_populaires = [];
    if (file_exists(__DIR__ . '/models/model_visites.php')) {
        require_once __DIR__ . '/models/model_visites.php';
        $produits_populaires = get_produits_plus_visites(10);
    }
    ?>

    <section class="produit_vedete">
        <div class="box1">
            <span></span>
            <h1>PRODUITS POPULAIRES</h1>
            <span></span>
        </div>



        <div class="carousel-produits-outer">
            <article data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-easing="ease-in-out"
                data-aos-mirror="true" data-aos-once="true" data-aos-anchor-placement="top-bottom"
                class="articles carousel1 carousel1-flex-mode" id="carousel-populaires">
                <?php if (empty($produits_populaires)): ?>
                <!-- Message si aucun produit -->
                <div class="carousel message-vide" style="text-align: center; padding: 40px; width: 100%;">
                    <p style="color: var(--texte-fonce); font-size: 16px;">Aucun produit publié pour le moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($produits_populaires as $produit): ?>
                <?php
                    // Calculer le prix à afficher
                    $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                        ? $produit['prix_promotion']
                        : $produit['prix'];
                    $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                    $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                    ?>
                <div class="carousel">
                    <a href="produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
                        <div class="image-wrapper">
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? 'produit1.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                onerror="this.src='/image/produit1.jpg'">
                        </div>
                        <div class="produit-content">
                            <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                            <?php if (!empty($produit['categorie_nom'])): ?>
                            <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                            <?php endif; ?>
                            <p class="prix">
                                <?php if ($has_promotion): ?>
                                <span class="span2"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                    FCFA</span>
                                <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                    FCFA</span>

                                <?php else: ?>
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?><span class="span1">
                                    FCFA</span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($produit['stock'])): ?>
                            <p class="produit-card-stock-info">
                                <strong>Stock:</strong> <?php echo $produit['stock']; ?>

                            </p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                        <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                        <input type="hidden" name="quantite" value="1">
                        <input type="hidden" name="return_url"
                            value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/index.php'); ?>">
                        <button type="submit" class="btn-add-cart">
                            <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </div>
    </section>




    <?php
    // Récupérer les catégories les plus populaires (visites + commandes) - Maximum 2
    $top_categories = [];
    if (file_exists(__DIR__ . '/models/model_categories.php')) {
        require_once __DIR__ . '/models/model_categories.php';
        $top_categories = get_top_categories(2);
    }
    ?>

    <section class="section5">
        <h1>Top Categorie</h1>
        <div class="container">
            <?php if (empty($top_categories)): ?>
            <!-- Message si aucune catégorie -->
            <div class="message-vide" style="text-align: center; padding: 40px; color: var(--texte-fonce);">
                <p>Aucune catégorie disponible pour le moment.</p>
            </div>
            <?php else: ?>
            <?php foreach ($top_categories as $categorie): ?>
            <?php
                    // Déterminer le chemin de l'image
                    $categorie_image_path = '/image/produit1.jpg'; // Par défaut
                    if (!empty($categorie['image'])) {
                        $upload_path = '/upload/' . htmlspecialchars($categorie['image']);
                        $file_path = __DIR__ . '/upload/' . $categorie['image'];
                        if (file_exists($file_path)) {
                            $categorie_image_path = $upload_path;
                        }
                    }
                    ?>
            <div class="slider">
                <img src="<?php echo $categorie_image_path; ?>" alt="<?php echo htmlspecialchars($categorie['nom']); ?>"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="box">
                    <h4><?php echo htmlspecialchars(strtoupper($categorie['nom'])); ?></h4>
                    <a href="categorie.php?id=<?php echo $categorie['id']; ?>">Voir cette categorie ></a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>



    <?php
    // Récupérer les 20 premiers produits
    $produits_tous = [];
    $total_produits = 0;
    if (file_exists(__DIR__ . '/models/model_produits.php')) {
        require_once __DIR__ . '/models/model_produits.php';
        $produits_tous = get_all_produits_paginated(0, 20);
        $total_produits = count_all_produits_actifs();
    }
    ?>

    <section class="section00">
        <section class="produit_vedetes">
            <div class="box1">
                <h1>Tous nos produits</h1>
            </div>

            <article data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-easing="ease-in-out"
                data-aos-mirror="true" data-aos-once="true" data-aos-anchor-placement="top-bottom"
                class="articles carousel11" id="produits-container">
                <?php if (empty($produits_tous)): ?>
                <!-- Message si aucun produit -->
                <div class="message-vide"
                    style="text-align: center; padding: 40px; color: var(--texte-fonce); width: 100%;">
                    <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($produits_tous as $produit): ?>
                <?php
                        // Calculer le prix à afficher
                        $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                            ? $produit['prix_promotion']
                            : $produit['prix'];
                        $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                        $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                        ?>
                <div class="carousel" data-produit-id="<?php echo $produit['id']; ?>">
                    <a href="produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
                        <div class="image-wrapper">
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? 'produit1.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                onerror="this.src='/image/produit1.jpg'">
                        </div>
                        <div class="produit-content">
                            <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                            <?php if (!empty($produit['categorie_nom'])): ?>
                            <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                            <?php endif; ?>
                            <p class="prix">
                                <?php if ($has_promotion): ?>
                                <span class="span2"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                    FCFA</span>
                                <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                    FCFA</span>

                                <?php else: ?>
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?><span class="span1">
                                    FCFA</span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($produit['stock'])): ?>
                            <p class="produit-card-stock-info">
                                <strong>Stock:</strong> <?php echo $produit['stock']; ?>

                            </p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                        <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                        <input type="hidden" name="quantite" value="1">
                        <input type="hidden" name="return_url"
                            value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/index.php'); ?>">
                        <button type="submit" class="btn-add-cart">
                            <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <?php if (!empty($produits_tous) && $total_produits > 20): ?>
            <div class="voir-tous-produits-wrapper">
                <a href="produits.php" class="btn-voir-tous-produits">
                    <i class="fas fa-arrow-right"></i> Voir tous les produits (<?php echo $total_produits; ?>)
                </a>
            </div>
            <?php endif; ?>
        </section>
    </section>




    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="/js/owl.carousel.min.js"></script>
    <script src="/js/owl.carousel.js"></script>
    <script src="/js/owl.animate.js"></script>
    <script src="/js/owl.autoplay.js"></script>

    <script>
    $(document).ready(function() {

        $('.slider1').owlCarousel({
            items: 2,
            loop: true,
            dots: true,
            autoplay: true,
            autoplayTimeout: 4000,
            animateOut: 'slideOutDown',
            animateIn: 'flipInX',
            smartSpeed: 400,
            stagePadding: 0,
            nav: true,
            navText: ['<i class="fa-solid fa-chevron-left"></i>',
                '<i class="fa-solid fa-chevron-right"></i>'
            ]
        });
        var carousel2 = $('.slider1').owlCarousel();
        $('.owl-next2').click(function() {
            carousel2.trigger('next.owl.carousel');
        })
        $('.owl-prev2').click(function() {
            carousel2.trigger('prev.owl.carousel');
        })

        // Nouveaux produits et Produits populaires : Owl désactivé, toujours en mode flex-wrap


        $('.slider-area').owlCarousel({
            items: 1,
            loop: true,
            dots: true,
            autoplay: true,
            autoplayTimeout: 6000,
            animateOut: 'slideOutDown',
            animateIn: 'flipInX',
            smartSpeed: 800,
            stagePadding: 1,
            nav: true,
            navText: ['<i class="fa-solid fa-chevron-left"></i>',
                '<i class="fa-solid fa-chevron-right"></i>'
            ]
        });
        var carousel2 = $('.carousel2').owlCarousel();
        $('.owl-next2').click(function() {
            carousel2.trigger('next.owl.carousel');
        })
        $('.owl-prev2').click(function() {
            carousel2.trigger('prev.owl.carousel');
        })


        // Carrousel catégories : 1 item < 350px, 2 items >= 350px sur mobile
        $('.categorie').owlCarousel({
            items: 5,
            loop: true,
            dots: true,
            autoplay: true,
            autoplayTimeout: 2000,
            autoplaySpeed: 3000,
            animateOut: 'slideOutDown',
            animateIn: 'flipInX',
            smartSpeed: 1200,
            stagePadding: 20,
            margin: 15,
            nav: true,
            navText: ['<i class="fa-solid fa-chevron-left"></i>',
                '<i class="fa-solid fa-chevron-right"></i>'
            ],
            responsive: {
                0: {
                    items: 1,
                    stagePadding: 10,
                    margin: 10,
                    nav: true,
                    dots: true
                },
                350: {
                    items: 2,
                    stagePadding: 10,
                    margin: 12,
                    nav: true,
                    dots: true
                },
                576: {
                    items: 2,
                    stagePadding: 15,
                    margin: 15,
                    nav: true,
                    dots: true
                },
                768: {
                    items: 3,
                    stagePadding: 15,
                    margin: 15,
                    nav: true,
                    dots: true
                },
                992: {
                    items: 4,
                    stagePadding: 20,
                    margin: 15,
                    nav: true,
                    dots: true
                },
                1200: {
                    items: 4,
                    stagePadding: 20,
                    margin: 15,
                    nav: true,
                    dots: true
                }
            }
        });
        var carousel2 = $('.carousel2').owlCarousel();
        $('.owl-next2').click(function() {
            carousel2.trigger('next.owl.carousel');
        })
        $('.owl-prev2').click(function() {
            carousel2.trigger('prev.owl.carousel');
        })


    });
    </script>

    <script>
    // ..
    AOS.init();
    </script>

    <script>
    // Slider vidéo simple en JavaScript vanilla
    document.addEventListener('DOMContentLoaded', function() {
        var slider = document.getElementById('videosSlider');
        var prevBtn = document.getElementById('videosPrev');
        var nextBtn = document.getElementById('videosNext');
        var dotsContainer = document.getElementById('videosDots');
        var autoplayInterval;
        var autoplayDelay = 8000; // 8 secondes

        if (!slider) {
            return; // Pas de slider, ne rien faire
        }

        var cards = slider.querySelectorAll('.video-card');
        if (cards.length === 0) {
            return; // Pas de vidéos
        }

        var currentIndex = 0;
        var itemsPerView = 1; // Par défaut mobile
        var dots = [];

        // Fonction pour déterminer le nombre d'éléments visibles
        function getItemsPerView() {
            var width = window.innerWidth;
            if (width >= 992) {
                return 3; // Grand écran : 3 vidéos
            } else if (width >= 768) {
                return 2; // Tablette : 2 vidéos
            }
            return 1; // Mobile : 1 vidéo
        }

        // Fonction pour créer les dots
        function createDots() {
            if (!dotsContainer) return;

            itemsPerView = getItemsPerView();
            var totalPages = Math.ceil(cards.length / itemsPerView);

            dotsContainer.innerHTML = '';
            dots = [];

            for (var i = 0; i < totalPages; i++) {
                var dot = document.createElement('span');
                dot.className = 'dot';
                if (i === 0) {
                    dot.classList.add('active');
                }
                dot.setAttribute('data-page', i);
                dot.addEventListener('click', function() {
                    var page = parseInt(this.getAttribute('data-page'));
                    currentIndex = page * itemsPerView;
                    updateSlider();
                    stopAutoplay();
                    startAutoplay();
                });
                dotsContainer.appendChild(dot);
                dots.push(dot);
            }
        }

        // Fonction pour calculer le nombre de slides possibles
        function getMaxIndex() {
            itemsPerView = getItemsPerView();
            return Math.max(0, cards.length - itemsPerView);
        }

        // Fonction pour mettre à jour la position du slider
        function updateSlider() {
            var maxIndex = getMaxIndex();
            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }

            if (cards.length === 0) return;

            // Calculer la translation en fonction de la largeur des cartes
            var cardWidth = cards[0].offsetWidth;
            var gap = 20;
            var translateX = -(currentIndex * (cardWidth + gap));

            slider.style.transform = 'translateX(' + translateX + 'px)';

            // Mettre à jour les dots
            var dotIndex = Math.floor(currentIndex / itemsPerView);
            dots.forEach(function(dot, index) {
                dot.classList.remove('active');
                if (index === dotIndex) {
                    dot.classList.add('active');
                }
            });

            // Afficher/masquer les boutons selon la position
            if (prevBtn) {
                prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
            }
            if (nextBtn) {
                nextBtn.style.display = currentIndex >= maxIndex ? 'none' : 'flex';
            }
        }

        function nextSlide() {
            var maxIndex = getMaxIndex();
            if (currentIndex < maxIndex) {
                currentIndex += itemsPerView;
            } else {
                currentIndex = 0; // Retour au début
            }
            updateSlider();
        }

        function prevSlide() {
            var maxIndex = getMaxIndex();
            if (currentIndex > 0) {
                currentIndex -= itemsPerView;
                if (currentIndex < 0) {
                    currentIndex = maxIndex; // Aller à la fin
                }
            } else {
                currentIndex = maxIndex; // Aller à la fin
            }
            updateSlider();
        }

        function startAutoplay() {
            autoplayInterval = setInterval(function() {
                nextSlide();
            }, autoplayDelay);
        }

        function stopAutoplay() {
            if (autoplayInterval) {
                clearInterval(autoplayInterval);
            }
        }

        // Événements pour les boutons
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                nextSlide();
                stopAutoplay();
                startAutoplay();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                prevSlide();
                stopAutoplay();
                startAutoplay();
            });
        }

        // Pause autoplay au survol
        var sliderWrapper = document.querySelector('.videos-slider-wrapper');
        if (sliderWrapper) {
            sliderWrapper.addEventListener('mouseenter', stopAutoplay);
            sliderWrapper.addEventListener('mouseleave', startAutoplay);
        }

        // Gérer le redimensionnement de la fenêtre
        var resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                currentIndex = 0;
                createDots();
                updateSlider();
            }, 250);
        });

        // Initialiser
        createDots();
        updateSlider();
        if (cards.length > getItemsPerView()) {
            startAutoplay();
        }
    });
    </script>

</body>

</html>