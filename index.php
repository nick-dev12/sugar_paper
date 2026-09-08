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
require_once __DIR__ . '/includes/seo_config.php';
$base = get_site_base_url();
$home_seo = get_seo_home_meta();
$seo_title = $home_seo['title'];
$seo_description = $home_seo['description'];
$seo_keywords = $home_seo['keywords'];
$seo_canonical = $base . '/';
?>




<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,550;9..144,700&family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/catalogue-responsive.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/home-redesign.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/produit-personnalisation.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/home-perf.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-personnalisee.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-loader-overlay.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/platform_share_head.php'; ?>

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
        <div class="commande-perso-showcase home-reveal">
            <div class="commande-perso-showcase-inner">
                <div class="commande-perso-content">
                    <div class="commande-perso-main">
                        <span class="commande-perso-badge"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Création sur mesure</span>
                        <h2 class="commande-perso-title">Vous l’imaginez.<br><em>Nous le créons.</em></h2>
                        <ul class="commande-perso-promises">
                            <li><i class="fas fa-pen-nib" aria-hidden="true"></i> Votre idée, sans limite</li>
                            <li><i class="fas fa-image" aria-hidden="true"></i> Photo d’inspiration</li>
                            <li><i class="fas fa-bolt" aria-hidden="true"></i> Réponse rapide</li>
                        </ul>
                        <a href="commande-personnalisee.php" class="btn-commande-perso">
                            <span>Faire une commande personnalisée</span>
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    <a href="commande-personnalisee.php" class="commande-perso-visual" aria-label="Faire une commande personnalisée">
                        <div class="commande-perso-atelier" aria-hidden="true">
                            <div class="commande-perso-cake">
                                <i class="fas fa-cake-candles"></i>
                            </div>
                            <span class="commande-perso-float commande-perso-float--1"><i class="fas fa-palette"></i></span>
                            <span class="commande-perso-float commande-perso-float--2"><i class="fas fa-camera"></i></span>
                            <span class="commande-perso-float commande-perso-float--3"><i class="fas fa-heart"></i></span>
                            <span class="commande-perso-stamp">100&nbsp;% à votre image</span>
                        </div>
                        <ol class="commande-perso-steps" aria-label="Comment ça marche">
                            <li class="commande-perso-step">
                                <span class="commande-perso-step-number">1</span>
                                <span class="commande-perso-step-copy">
                                    <span class="commande-perso-step-label">Décrivez</span>
                                    <span class="commande-perso-step-hint">Votre idée exacte</span>
                                </span>
                            </li>
                            <li class="commande-perso-step">
                                <span class="commande-perso-step-number">2</span>
                                <span class="commande-perso-step-copy">
                                    <span class="commande-perso-step-label">Inspirez</span>
                                    <span class="commande-perso-step-hint">Une photo suffit</span>
                                </span>
                            </li>
                            <li class="commande-perso-step">
                                <span class="commande-perso-step-number">3</span>
                                <span class="commande-perso-step-copy">
                                    <span class="commande-perso-step-label">Recevez</span>
                                    <span class="commande-perso-step-hint">Une création unique</span>
                                </span>
                            </li>
                        </ol>
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
    require_once __DIR__ . '/includes/home_sections.php';
    $home_return_url = $_SERVER['REQUEST_URI'] ?? '/index.php';
    render_home_all_products_section(40, $home_return_url, 10);
    render_home_product_section('cake_topper', 20, $home_return_url);
    ?>

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

    // Chemin de l'image de fond (fallback si aucune vidéo bannière)
    $image_fond_path = '/image/market.png';
    if (!empty($section4_config['image_fond'])) {
        $resolved_fond = upload_subdir_image_url('section4', $section4_config['image_fond'], 'original');
        $fond_relative = ltrim(str_replace('/upload/', '', $resolved_fond), '/');
        if (is_file(__DIR__ . '/upload/' . $fond_relative)) {
            $image_fond_path = $resolved_fond;
        }
    }

    $hero_banner_video = null;
    $hero_banner_video_url = '';
    $hero_banner_video_type = 'video/mp4';
    $hero_banner_poster = '';
    if (file_exists(__DIR__ . '/models/model_videos.php')) {
        require_once __DIR__ . '/models/model_videos.php';
        $hero_banner_video = get_hero_banner_video();
        if ($hero_banner_video && !empty($hero_banner_video['fichier_video'])) {
            $hero_disk = __DIR__ . '/upload/videos/' . $hero_banner_video['fichier_video'];
            if (is_file($hero_disk)) {
                $hero_banner_video_url = '/upload/videos/' . rawurlencode($hero_banner_video['fichier_video']);
                $hero_ext = strtolower(pathinfo($hero_banner_video['fichier_video'], PATHINFO_EXTENSION));
                $hero_mimes = [
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'ogv' => 'video/ogg',
                    'mov' => 'video/quicktime',
                ];
                $hero_banner_video_type = $hero_mimes[$hero_ext] ?? 'video/mp4';
                $hero_banner_poster = resolve_video_poster_url($hero_banner_video);
            } else {
                $hero_banner_video = null;
            }
        }
    }
    ?>
    <?php if ($section4_actif): ?>
    <section class="section4 home-hero-banner home-reveal" aria-label="Bannière d'accueil">
        <div class="home-hero-banner__media">
            <?php if ($hero_banner_video_url !== ''): ?>
            <video class="home-hero-banner__video"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                <?php if ($hero_banner_poster !== ''): ?>
                poster="<?php echo htmlspecialchars($hero_banner_poster, ENT_QUOTES, 'UTF-8'); ?>"
                <?php endif; ?>
                aria-label="<?php echo htmlspecialchars($section4_titre !== '' ? $section4_titre : 'Sugar Paper', ENT_QUOTES, 'UTF-8'); ?>">
                <source src="<?php echo htmlspecialchars($hero_banner_video_url, ENT_QUOTES, 'UTF-8'); ?>"
                    type="<?php echo htmlspecialchars($hero_banner_video_type, ENT_QUOTES, 'UTF-8'); ?>">
            </video>
            <?php else: ?>
            <img class="home-hero-banner__img"
                src="<?php echo htmlspecialchars($image_fond_path, ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($section4_titre !== '' ? $section4_titre : 'Sugar Paper', ENT_QUOTES, 'UTF-8'); ?>"
                width="1400"
                height="420"
                fetchpriority="high"
                decoding="async">
            <?php endif; ?>
            <div class="home-hero-banner__overlay" aria-hidden="true"></div>
            <div class="home-hero-banner__content">
                <span class="home-hero-banner__kicker"><i class="fa-solid fa-cake-candles" aria-hidden="true"></i> Sugar Paper</span>
                <?php if ($section4_titre !== ''): ?>
                <h2 class="home-hero-banner__title"><?php echo htmlspecialchars($section4_titre); ?></h2>
                <?php endif; ?>
                <?php if ($section4_texte !== ''): ?>
                <p class="home-hero-banner__tagline"><?php echo htmlspecialchars($section4_texte); ?></p>
                <?php endif; ?>
            </div>
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
                    $poster_url = resolve_video_poster_url($video);
                    $video_src = '/upload/videos/' . rawurlencode($video['fichier_video']);
                    $needs_poster = $poster_url === '' ? '1' : '0';
                ?>
                <article class="galerie-item">
                    <div class="galerie-card">
                        <div class="galerie-video-wrapper"
                            data-video-loaded="0"
                            data-needs-poster="<?php echo $needs_poster; ?>"
                            data-video-title="<?php echo htmlspecialchars($video['titre'] ?? 'Vidéo création', ENT_QUOTES, 'UTF-8'); ?>">
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

    <?php
    render_home_product_section('photo_impression', 20, $home_return_url);
    ?>

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
    render_home_product_section('outils_patisserie', 20, $home_return_url);
    render_home_product_section('decoration_gateau', 20, $home_return_url);
    ?>

    </main>

    <?php
    require_once __DIR__ . '/includes/cake_topper_cp.php';
    render_cp_form_modal_assets();
    render_produit_personnalisation_modal();
    ?>

    <?php include('footer.php') ?>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>

    <script src="/js/owl.carousel.min.js"></script>
    <script src="/js/owl.carousel.js"></script>
    <script src="/js/owl.autoplay.js"></script>

    <script>
    $(document).ready(function() {
        var owlDefaults = {
            loop: true,
            dots: true,
            nav: true,
            navText: [
                '<i class="fa-solid fa-chevron-left"></i>',
                '<i class="fa-solid fa-chevron-right"></i>'
            ],
            smartSpeed: 450,
            autoplayHoverPause: true
        };

        $('.slider-area').owlCarousel($.extend({}, owlDefaults, {
            items: 1,
            autoplay: true,
            autoplayTimeout: 6000,
            lazyLoad: true
        }));

        $('.categorie').owlCarousel($.extend({}, owlDefaults, {
            items: 5,
            autoplay: true,
            autoplayTimeout: 4500,
            stagePadding: 20,
            margin: 15,
            responsive: {
                0: { items: 1, stagePadding: 10, margin: 10 },
                350: { items: 2, stagePadding: 10, margin: 12 },
                576: { items: 2, stagePadding: 15, margin: 15 },
                768: { items: 3, stagePadding: 15, margin: 15 },
                992: { items: 4, stagePadding: 20, margin: 15 },
                1200: { items: 4, stagePadding: 20, margin: 15 }
            }
        }));
    });
    </script>

    <script src="/js/home-galerie-video.js<?php echo asset_version_query(); ?>" defer></script>
    <?php if (produit_personnalisation_enabled()): ?>
    <script src="/js/produit-personnalisation.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>

</body>

</html>