<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/includes/image_optimizer.php';


// Inclusion du fichier de connexion à la BDD

// Récupérez l'ID du commerçant à partir de la session
// Récupérez l'ID de l'utilisateur depuis la variable de session
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Sugar Paper - Décoration de gâteaux personnalisée';
$seo_description = 'Sugar Paper : produits décoratifs pour gâteaux d\'anniversaire, mariage et cérémonies. Décoration comestible et non comestible à grande échelle. Personnalisez vos gâteaux !';
$seo_canonical = $base . '/';
?>




<!DOCTYPE html>
<html lang="fr" class="aos-not-ready">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <style>
    /* AOS masque [data-aos] avant init JS — affichage immédiat au chargement */
    html.aos-not-ready [data-aos] {
        opacity: 1 !important;
        transform: none !important;
        filter: none !important;
        pointer-events: auto !important;
    }
    </style>
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/catalogue-responsive.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/home-redesign.css<?php echo asset_version_query(); ?>">
    <style>
    /* Nouveaux produits et Produits populaires : flex-wrap, Owl désactivé, 6 produits max */
    .carousel-produits-outer {
        position: relative;
        width: 100%;
    }

    .carousel-produits-outer .carousel1.carousel1-flex-mode .carousel:nth-child(n+7) {
        display: none !important;
    }
    </style>

</head>


<body class="page-home">

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
            <img src="<?php echo htmlspecialchars(upload_subdir_image_url('slider', $slide['image'] ?? '', 'original')); ?>"
                alt="<?php echo htmlspecialchars($slide['titre']); ?>" onerror="this.src='/image/produit1.jpg'">

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
    <div class="home-alert home-alert--success">
        <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['compte_supprime']) && $_GET['compte_supprime'] == '1'): ?>
    <div class="home-alert home-alert--info">
        <i class="fas fa-check-circle"></i> Votre compte a été supprimé définitivement. Merci d'avoir utilisé Sugar Paper.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="home-alert home-alert--error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['commande_perso_success'])): ?>
    <div class="home-alert home-alert--success">
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
        <div class="commande-perso-showcase home-reveal" data-aos="fade-up" data-aos-duration="700">
            <div class="commande-perso-showcase-inner">
                <div class="commande-perso-content">
                    <div class="commande-perso-main">
                        <span class="commande-perso-badge"><i class="fas fa-palette"></i> Sur mesure</span>
                        <h2 class="commande-perso-title">Commande personnalisée</h2>
                        <p class="commande-perso-text">Décrivez votre besoin et joignez une image d'inspiration. Réponse rapide.</p>
                    </div>
                    <ol class="commande-perso-steps" aria-label="Étapes">
                        <li class="commande-perso-step">
                            <span class="commande-perso-step-number">1</span>
                            <span class="commande-perso-step-label">Décrivez</span>
                        </li>
                        <li class="commande-perso-step">
                            <span class="commande-perso-step-number">2</span>
                            <span class="commande-perso-step-label">Inspirez</span>
                        </li>
                        <li class="commande-perso-step">
                            <span class="commande-perso-step-number">3</span>
                            <span class="commande-perso-step-label">Recevez</span>
                        </li>
                    </ol>
                    <a href="commande-personnalisee.php" class="btn-commande-perso">
                        <i class="fas fa-palette"></i> Faire une demande
                    </a>
                </div>
            </div>
        </div>
    </section>

    <main class="home-main">

    <?php
    // Récupérer les catégories depuis la base de données
    $categories = [];
    if (file_exists(__DIR__ . '/models/model_categories.php')) {
        require_once __DIR__ . '/models/model_categories.php';
        $categories_result = get_all_categories_with_count();
        $categories = is_array($categories_result) ? $categories_result : [];
    }
    ?>

    <section class="home-section home-reveal" id="home-categories">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Explorer</span>
                <h2 class="home-section-title">Nos catégories</h2>
                <p class="home-section-desc">Trouvez rapidement le matériel idéal pour sublimer vos gâteaux.</p>
            </div>
            <a href="produits.php" class="home-section-cta">Voir le catalogue <i class="fas fa-arrow-right"></i></a>
        </div>

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
                <img class="img" src="<?php echo htmlspecialchars(upload_image_url($categorie['image'], 'sm')); ?>"
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
    </section>




    <?php
    // Récupérer les 10 derniers produits publiés (nouveautés)
    $produits_nouveaux = [];
    if (file_exists(__DIR__ . '/models/model_produits.php')) {
        require_once __DIR__ . '/models/model_produits.php';
        $produits_nouveaux = get_all_produits_paginated(0, 10);
    }
    ?>

    <section class="produit_vedete home-reveal" id="home-nouveautes">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Juste arrivés</span>
                <h2 class="home-section-title">Nouveaux produits</h2>
                <p class="home-section-desc">Les dernières pépites pour vos créations pâtissières.</p>
            </div>
            <a href="nouveautes.php" class="home-section-cta">Toutes les nouveautés <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="box1" hidden aria-hidden="true">
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
                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
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
        $resolved_fond = upload_subdir_image_url('section4', $section4_config['image_fond'], 'original');
        $fond_relative = ltrim(str_replace('/upload/', '', $resolved_fond), '/');
        if (is_file(__DIR__ . '/upload/' . $fond_relative)) {
            $image_fond_path = $resolved_fond;
        }
    }
    ?>
    <?php if ($section4_actif): ?>
    <section class="section4 home-reveal">
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
    <section class="galerie-creations home-reveal" id="home-creations">
        <div class="galerie-creations-container">
            <header class="galerie-header">
                <span class="galerie-surtitre">Découvrez</span>
                <h2 class="galerie-titre">Nos créations</h2>
                <p class="galerie-sous-titre">Une sélection de nos réalisations en vidéo</p>
            </header>

            <div class="galerie-grid" id="videosSlider">
                <?php foreach ($videos as $index => $video): ?>
                <?php
                    $preview_file = video_ensure_preview_image($video);
                    $poster_url = $preview_file
                        ? '/upload/videos/thumbnails/' . rawurlencode($preview_file)
                        : '';
                    $video_src = '/upload/videos/' . rawurlencode($video['fichier_video']);
                ?>
                <article class="galerie-item">
                    <div class="galerie-card">
                        <div class="galerie-video-wrapper" data-video-loaded="0">
                            <?php if ($poster_url !== ''): ?>
                            <img class="galerie-poster"
                                src="<?php echo htmlspecialchars($poster_url, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($video['titre'] ?? 'Vidéo création', ENT_QUOTES, 'UTF-8'); ?>"
                                loading="lazy"
                                decoding="async">
                            <?php else: ?>
                            <div class="galerie-poster galerie-poster--placeholder" aria-hidden="true">
                                <i class="fa-solid fa-film"></i>
                            </div>
                            <?php endif; ?>
                            <video class="galerie-video" controls preload="none" playsinline
                                <?php if ($poster_url !== ''): ?>
                                poster="<?php echo htmlspecialchars($poster_url, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php endif; ?>
                                data-src="<?php echo htmlspecialchars($video_src, ENT_QUOTES, 'UTF-8'); ?>">
                                <source data-src="<?php echo htmlspecialchars($video_src, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                Votre navigateur ne supporte pas la lecture de vidéos.
                            </video>
                            <button type="button" class="galerie-play-overlay" aria-label="Lire la vidéo">
                                <span class="galerie-play-ring" aria-hidden="true"></span>
                                <i class="fa-solid fa-play" aria-hidden="true"></i>
                            </button>
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
        document.querySelectorAll('.galerie-video-wrapper').forEach(function(wrapper) {
            var poster = wrapper.querySelector('.galerie-poster');
            var video = wrapper.querySelector('.galerie-video');
            var playBtn = wrapper.querySelector('.galerie-play-overlay');
            if (!video || !playBtn) {
                return;
            }

            function loadVideoSource() {
                if (video.dataset.loaded === '1') {
                    return;
                }
                var src = video.getAttribute('data-src');
                var source = video.querySelector('source');
                if (src && source && !source.getAttribute('src')) {
                    source.setAttribute('src', src);
                    video.load();
                }
                video.dataset.loaded = '1';
                wrapper.setAttribute('data-video-loaded', '1');
            }

            function hidePoster() {
                if (poster) {
                    poster.style.display = 'none';
                }
                video.classList.add('is-active');
                playBtn.style.opacity = '0';
                playBtn.style.pointerEvents = 'none';
            }

            function showPoster() {
                if (poster) {
                    poster.style.display = '';
                }
                video.classList.remove('is-active');
                playBtn.style.opacity = '';
                playBtn.style.pointerEvents = '';
            }

            playBtn.addEventListener('click', function() {
                loadVideoSource();
                hidePoster();
                video.play().catch(function() {});
            });

            video.addEventListener('play', hidePoster);
            video.addEventListener('pause', function() {
                if (video.currentTime === 0 || video.ended) {
                    showPoster();
                }
            });
            video.addEventListener('ended', showPoster);
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

    <section class="produit_vedete home-reveal" id="home-populaires">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Coups de cœur</span>
                <h2 class="home-section-title">Produits populaires</h2>
                <p class="home-section-desc">Les indispensables plébiscités par nos clients.</p>
            </div>
            <a href="produits.php" class="home-section-cta">Voir plus <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="box1" hidden aria-hidden="true">
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
                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
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

    <section class="section5 home-reveal" id="home-top-categories">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Sélection</span>
                <h2 class="home-section-title">Top catégories</h2>
                <p class="home-section-desc">Les univers les plus consultés du moment.</p>
            </div>
        </div>
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
                        $resolved_cat = upload_image_url($categorie['image'], 'md');
                        $cat_relative = ltrim(str_replace('/upload/', '', $resolved_cat), '/');
                        if (is_file(__DIR__ . '/upload/' . $cat_relative)) {
                            $categorie_image_path = $resolved_cat;
                        }
                    }
                    ?>
            <div class="slider">
                <img src="<?php echo $categorie_image_path; ?>" alt="<?php echo htmlspecialchars($categorie['nom']); ?>"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="box">
                    <h4><?php echo htmlspecialchars($categorie['nom']); ?></h4>
                    <a href="categorie.php?id=<?php echo $categorie['id']; ?>">Découvrir <i class="fas fa-arrow-right"></i></a>
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

    <section class="section00 home-reveal" id="home-catalogue">
        <section class="produit_vedetes">
            <div class="home-section-head">
                <div>
                    <span class="home-section-kicker">Boutique</span>
                    <h2 class="home-section-title">Tous nos produits</h2>
                    <p class="home-section-desc">Parcourez une sélection de notre catalogue Sugar Paper.</p>
                </div>
                <?php if (!empty($produits_tous) && $total_produits > 20): ?>
                <a href="produits.php" class="home-section-cta">Catalogue complet <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
            <div class="box1" hidden aria-hidden="true">
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
                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'md')); ?>"
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

    </main>

    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js" defer></script>
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
    (function () {
        function finishAos() {
            document.documentElement.classList.remove('aos-not-ready');
        }

        function runAos() {
            if (typeof AOS === 'undefined') {
                finishAos();
                return;
            }
            AOS.init({
                duration: 650,
                once: true,
                offset: 20,
                easing: 'ease-out-cubic',
                disable: function () {
                    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                }
            });
            requestAnimationFrame(function () {
                requestAnimationFrame(finishAos);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runAos);
        } else {
            runAos();
        }
    })();
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