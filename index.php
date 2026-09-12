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
    <link rel="stylesheet" href="/css/home-spotlight.css<?php echo asset_version_query(); ?>">
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

    $slider_carousel_videos = [];
    if (file_exists(__DIR__ . '/models/model_videos.php')) {
        require_once __DIR__ . '/models/model_videos.php';
        $slider_carousel_videos = get_slider_carousel_videos();
    }

    $slider_carousel_videos_valid = [];
    foreach ($slider_carousel_videos as $slider_video_row) {
        if (empty($slider_video_row['fichier_video'])) {
            continue;
        }
        $slider_video_disk = __DIR__ . '/upload/videos/' . $slider_video_row['fichier_video'];
        if (!is_file($slider_video_disk)) {
            continue;
        }
        $slider_carousel_videos_valid[] = $slider_video_row;
    }
    $use_video_slider = count($slider_carousel_videos_valid) > 0;
    ?>

    <div class="slider-area owl-carousel<?php echo $use_video_slider ? ' slider-area--video-mode slider-area--video-triple' : ' slider-area--image-mode'; ?>">
        <?php if ($use_video_slider): ?>
        <?php foreach ($slider_carousel_videos_valid as $slider_video_index => $slider_video): ?>
        <?php
        $slider_video_url = '/upload/videos/' . rawurlencode($slider_video['fichier_video']);
        $slider_video_type = video_file_mime_type($slider_video['fichier_video']);
        $slider_video_poster = resolve_video_poster_url($slider_video);
        $slider_video_label = trim((string) ($slider_video['titre'] ?? ''));
        if ($slider_video_label === '') {
            $slider_video_label = 'Vidéo Sugar Paper';
        }
        $slider_video_preload = ($slider_video_index === 0) ? 'metadata' : 'none';
        ?>
        <div class="slider-item slider-item--video slider-item--video-fullscreen">
            <video class="slider-item__video"
                muted
                loop
                playsinline
                webkit-playsinline
                preload="<?php echo $slider_video_preload; ?>"
                tabindex="0"
                title="Cliquer pour agrandir la vidéo"
                <?php if ($slider_video_poster !== ''): ?>
                poster="<?php echo htmlspecialchars($slider_video_poster, ENT_QUOTES, 'UTF-8'); ?>"
                <?php endif; ?>
                aria-label="<?php echo htmlspecialchars($slider_video_label, ENT_QUOTES, 'UTF-8'); ?>">
                <source <?php echo $slider_video_index === 0 ? 'src' : 'data-src'; ?>="<?php echo htmlspecialchars($slider_video_url, ENT_QUOTES, 'UTF-8'); ?>"
                    type="<?php echo htmlspecialchars($slider_video_type, ENT_QUOTES, 'UTF-8'); ?>">
            </video>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <?php foreach ($slides as $slide_index => $slide): ?>
        <div class="slider-item slider-item--image">
            <img src="<?php echo htmlspecialchars(upload_subdir_image_url('slider', $slide['image'] ?? '', 'original')); ?>"
                alt="<?php echo htmlspecialchars($slide['titre']); ?>"
                <?php echo $slide_index === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                decoding="async"
                onerror="this.src='/image/produit1.jpg'">
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
        <div class="services-banner-inner-scale-wrap">
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
                <h3>Livraison en temps réel</h3>
                <p>Suivez votre commande en direct</p>
            </div>
        </div>
        </div>

        <div class="services-banner-scale-wrap">
        <div class="services-banner-scaler">
        <?php
        require_once __DIR__ . '/includes/home_spotlight.php';
        render_home_spotlight_section();
        ?>
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
                    alt="<?php echo htmlspecialchars($categorie['nom']); ?>"
                    loading="lazy"
                    decoding="async"
                    onerror="this.src='/image/produit1.jpg'">
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
    require_once __DIR__ . '/includes/home_lazy_sections.php';
    foreach (get_home_lazy_section_keys() as $lazy_section_key) {
        render_home_lazy_placeholder($lazy_section_key);
    }
    ?>

    </main>

    <?php
    require_once __DIR__ . '/includes/cake_topper_cp.php';
    render_cp_form_modal_assets();
    render_produit_personnalisation_modal();
    ?>

    <?php include('footer.php') ?>
    <?php include __DIR__ . '/includes/platform_share_footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
    <script src="/js/owl.carousel.min.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/owl.carousel.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/owl.autoplay.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/home-progressive.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/home-galerie-video.js<?php echo asset_version_query(); ?>" defer></script>

    <script defer>
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

        function ensureSliderVideoLoaded(videoEl) {
            if (!videoEl) {
                return;
            }
            var source = videoEl.querySelector('source[data-src]');
            if (!source) {
                return;
            }
            var dataSrc = source.getAttribute('data-src');
            if (!dataSrc) {
                return;
            }
            source.setAttribute('src', dataSrc);
            source.removeAttribute('data-src');
            videoEl.load();
        }

        function syncSliderVideos() {
            var $slider = $('.slider-area');
            var modalOpen = $('#home-video-modal').hasClass('is-open');
            $slider.find('.slider-item__video').each(function() {
                this.pause();
            });
            if (modalOpen) {
                return;
            }
            $slider.find('.owl-item.active .slider-item__video').each(function() {
                ensureSliderVideoLoaded(this);
                var playPromise = this.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function() { /* autoplay bloqué */ });
                }
            });
        }

        function updateVideoModalPlayButton(isPlaying) {
            var $btn = $('.home-video-modal__play');
            var $icon = $btn.find('i');
            if (isPlaying) {
                $icon.removeClass('fa-play').addClass('fa-pause');
                $btn.attr('aria-label', 'Pause');
            } else {
                $icon.removeClass('fa-pause').addClass('fa-play');
                $btn.attr('aria-label', 'Lecture');
            }
        }

        function closeHomeVideoModal() {
            var $modal = $('#home-video-modal');
            var modalVideo = $modal.find('.home-video-modal__video').get(0);
            if (modalVideo) {
                modalVideo.pause();
                modalVideo.removeAttribute('src');
                while (modalVideo.firstChild) {
                    modalVideo.removeChild(modalVideo.firstChild);
                }
                modalVideo.load();
            }
            $modal.removeClass('is-open').attr('hidden', true);
            $('body').removeClass('home-video-modal-open');
            syncSliderVideos();
        }

        function openHomeVideoModal(sourceVideo) {
            if (!sourceVideo) {
                return;
            }
            var $modal = $('#home-video-modal');
            var modalVideo = $modal.find('.home-video-modal__video').get(0);
            var $source = $(sourceVideo).find('source').first();
            var src = $source.attr('src') || sourceVideo.currentSrc || sourceVideo.src;
            var type = $source.attr('type') || '';
            var title = $(sourceVideo).attr('aria-label') || 'Vidéo Sugar Paper';

            if (!src || !modalVideo) {
                return;
            }

            $('#home-video-modal-title').text(title);
            while (modalVideo.firstChild) {
                modalVideo.removeChild(modalVideo.firstChild);
            }
            if (type) {
                var sourceEl = document.createElement('source');
                sourceEl.src = src;
                sourceEl.type = type;
                modalVideo.appendChild(sourceEl);
            } else {
                modalVideo.src = src;
            }
            modalVideo.muted = true;
            modalVideo.loop = true;
            modalVideo.controls = false;
            modalVideo.currentTime = 0;
            modalVideo.load();

            $modal.addClass('is-open').removeAttr('hidden');
            $('body').addClass('home-video-modal-open');
            $('.slider-area .slider-item__video').each(function() {
                this.pause();
            });

            var playPromise = modalVideo.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function() {
                    updateVideoModalPlayButton(false);
                });
            }
            updateVideoModalPlayButton(true);
        }

        $('.slider-area').on('click', '.slider-item__video', function(event) {
            event.preventDefault();
            event.stopPropagation();
            openHomeVideoModal(this);
        });

        $('.slider-area').on('keydown', '.slider-item__video', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openHomeVideoModal(this);
            }
        });

        $(document).on('click', '[data-video-modal-close]', function() {
            closeHomeVideoModal();
        });

        $('.home-video-modal__play').on('click', function() {
            var modalVideo = $('.home-video-modal__video').get(0);
            if (!modalVideo) {
                return;
            }
            if (modalVideo.paused) {
                var playPromise = modalVideo.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function() { /* lecture bloquée */ });
                }
                updateVideoModalPlayButton(true);
            } else {
                modalVideo.pause();
                updateVideoModalPlayButton(false);
            }
        });

        $(document).on('keydown', function(event) {
            if (event.key === 'Escape' && $('#home-video-modal').hasClass('is-open')) {
                closeHomeVideoModal();
            }
        });

        function scaleServicesBannerInner() {
            var wrap = document.querySelector('.services-banner-inner-scale-wrap');
            var inner = document.querySelector('.services-banner-inner');
            if (!wrap || !inner) {
                return;
            }
            if (window.innerWidth >= 993) {
                inner.style.transform = 'none';
                inner.style.width = '100%';
                wrap.style.height = 'auto';
                return;
            }
            var designWidth = 820;
            var available = wrap.clientWidth;
            var scale = available / designWidth;
            if (!isFinite(scale) || scale <= 0) {
                scale = 1;
            }
            if (scale > 1) {
                scale = 1;
            }
            inner.style.width = designWidth + 'px';
            inner.style.transform = 'scale(' + scale + ')';
            wrap.style.height = (inner.offsetHeight * scale) + 'px';
        }

        function scaleHomeSpotlight() {
            var wrap = document.querySelector('.services-banner-scale-wrap');
            var scaler = document.querySelector('.services-banner-scaler');
            if (!wrap || !scaler) {
                return;
            }
            var designWidth = 1200;
            if (window.innerWidth <= 576) {
                designWidth = 760;
            } else if (window.innerWidth <= 992) {
                designWidth = 900;
            }
            var available = wrap.clientWidth;
            if (available >= designWidth) {
                scaler.style.width = '100%';
                scaler.style.transform = 'none';
                wrap.style.height = 'auto';
                return;
            }
            var scale = available / designWidth;
            if (!isFinite(scale) || scale <= 0) {
                scale = 1;
            }
            if (scale > 1) {
                scale = 1;
            }
            scaler.style.width = designWidth + 'px';
            scaler.style.transform = 'scale(' + scale + ')';
            wrap.style.height = (scaler.offsetHeight * scale) + 'px';
        }

        function initHomeSpotlightSlider() {
            var $spotlight = $('.home-spotlight--slider');
            if (!$spotlight.length) {
                return;
            }

            $spotlight.each(function() {
                var $root = $(this).find('.home-spotlight__inner');
                var $slides = $root.find('.home-spotlight__slide');
                var $dots = $root.find('.home-spotlight__dot');
                var current = 0;
                var timer = null;

                function showSlide(index) {
                    if (!$slides.length) {
                        return;
                    }
                    current = (index + $slides.length) % $slides.length;
                    $slides.removeClass('is-active');
                    $slides.eq(current).addClass('is-active');
                    $dots.removeClass('home-spotlight__dot--active').attr('aria-selected', 'false');
                    $dots.eq(current).addClass('home-spotlight__dot--active').attr('aria-selected', 'true');
                }

                function startAutoPlay() {
                    if (timer) {
                        clearInterval(timer);
                    }
                    if ($slides.length < 2) {
                        return;
                    }
                    timer = setInterval(function() {
                        showSlide(current + 1);
                    }, 5000);
                }

                $dots.on('click', function() {
                    var index = parseInt($(this).attr('data-slide-index'), 10);
                    if (isNaN(index)) {
                        return;
                    }
                    showSlide(index);
                    startAutoPlay();
                });

                showSlide(0);
                startAutoPlay();
            });
        }

        scaleServicesBannerInner();
        scaleHomeSpotlight();
        initHomeSpotlightSlider();
        $(window).on('resize', function() {
            scaleServicesBannerInner();
            scaleHomeSpotlight();
        });
        $('.home-spotlight__img').on('load', scaleHomeSpotlight);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function() {
                scaleServicesBannerInner();
                scaleHomeSpotlight();
            });
        }

        var $homeSlider = $('.slider-area');
        if ($homeSlider.find('.slider-item').length) {
            var sliderIsVideoMode = $homeSlider.hasClass('slider-area--video-mode');
            var videoSlideCount = $homeSlider.find('.slider-item--video').length;

            if (sliderIsVideoMode) {
                var videoVisibleCount = Math.min(3, videoSlideCount);
                var videoTripleOptions = {
                    items: videoVisibleCount,
                    margin: 0,
                    stagePadding: 0,
                    autoplay: videoSlideCount > videoVisibleCount,
                    autoplayTimeout: 12000,
                    loop: videoSlideCount > videoVisibleCount,
                    lazyLoad: false,
                    nav: videoSlideCount > videoVisibleCount,
                    dots: videoSlideCount > videoVisibleCount,
                    responsive: {
                        0: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        480: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        768: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        992: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        }
                    }
                };
                $homeSlider.owlCarousel($.extend({}, owlDefaults, videoTripleOptions));

                $homeSlider.on('initialized.owl.carousel changed.owl.carousel translated.owl.carousel', function() {
                    syncSliderVideos();
                });
                syncSliderVideos();
            } else {
                $homeSlider.owlCarousel($.extend({}, owlDefaults, {
            items: 1,
            autoplay: true,
            autoplayTimeout: 6000,
            lazyLoad: true
        }));
            }
        }

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

    <div id="home-video-modal" class="home-video-modal" hidden>
        <div class="home-video-modal__backdrop" data-video-modal-close></div>
        <div class="home-video-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="home-video-modal-title">
            <p id="home-video-modal-title" class="home-video-modal__title">Vidéo Sugar Paper</p>
            <button type="button" class="home-video-modal__close" data-video-modal-close aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <video class="home-video-modal__video" muted loop playsinline webkit-playsinline></video>
            <div class="home-video-modal__controls">
                <button type="button" class="home-video-modal__play" aria-label="Pause">
                    <i class="fas fa-pause" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <?php if (produit_personnalisation_enabled()): ?>
    <script src="/js/produit-personnalisation.js<?php echo asset_version_query(); ?>" defer></script>
    <?php endif; ?>

</body>

</html>