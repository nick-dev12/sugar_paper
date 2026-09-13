<?php
/**
 * Sections accueil chargées progressivement (lazy)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/home_sections.php';
require_once __DIR__ . '/image_optimizer.php';

/**
 * @return array<int, string>
 */
function get_home_lazy_section_keys()
{
    return [
        'all_products',
        'hero_banner',
        'cake_topper',
        'galerie_creations',
        'photo_impression',
        'top_categories',
        'outils_patisserie',
        'decoration_gateau',
    ];
}

/**
 * @param string $section_key
 * @return bool
 */
function is_valid_home_lazy_section_key($section_key)
{
    return in_array($section_key, get_home_lazy_section_keys(), true);
}

/**
 * @return array<string, string>
 */
function get_home_lazy_section_labels()
{
    return [
        'all_products' => 'Catalogue produits',
        'hero_banner' => 'Bannière d\'accueil',
        'cake_topper' => 'Cake toppers',
        'galerie_creations' => 'Nos créations',
        'photo_impression' => 'Photo comestible',
        'top_categories' => 'Top catégories',
        'outils_patisserie' => 'Outils pâtisserie',
        'decoration_gateau' => 'Décoration gâteau',
    ];
}

/**
 * @param string $section_key
 * @param string $home_return_url
 * @return void
 */
function render_home_lazy_section($section_key, $home_return_url = '/index.php')
{
    if (!is_valid_home_lazy_section_key($section_key)) {
        return;
    }

    switch ($section_key) {
        case 'all_products':
            render_home_all_products_section(40, $home_return_url, 10);
            break;

        case 'hero_banner':
            render_home_lazy_hero_banner_section();
            break;

        case 'cake_topper':
            render_home_product_section('cake_topper', 20, $home_return_url);
            break;

        case 'galerie_creations':
            render_home_lazy_galerie_section();
            break;

        case 'photo_impression':
            render_home_product_section('photo_impression', 20, $home_return_url);
            break;

        case 'top_categories':
            render_home_lazy_top_categories_section();
            break;

        case 'outils_patisserie':
            render_home_product_section('outils_patisserie', 20, $home_return_url);
            break;

        case 'decoration_gateau':
            render_home_product_section('decoration_gateau', 20, $home_return_url);
            break;
    }
}

/**
 * @return void
 */
function render_home_lazy_hero_banner_section()
{
    $section4_config = [
        'titre' => 'Bienvenue au Sugar Paper',
        'texte' => 'Tous les produits a petit prix',
        'image_fond' => 'market.png',
        'statut' => 'actif',
    ];

    if (file_exists(__DIR__ . '/../models/model_section4.php')) {
        require_once __DIR__ . '/../models/model_section4.php';
        $config_result = get_section4_config();
        if ($config_result) {
            $section4_config = $config_result;
        }
    }

    if (($section4_config['statut'] ?? 'actif') !== 'actif') {
        return;
    }

    $section4_titre = trim($section4_config['titre'] ?? '');
    $section4_texte = trim($section4_config['texte'] ?? '');

    $image_fond_path = '/image/market.png';
    if (!empty($section4_config['image_fond'])) {
        $resolved_fond = upload_subdir_image_url('section4', $section4_config['image_fond'], 'original');
        $fond_relative = ltrim(str_replace('/upload/', '', $resolved_fond), '/');
        if (is_file(__DIR__ . '/../upload/' . $fond_relative)) {
            $image_fond_path = $resolved_fond;
        }
    }

    $hero_banner_video_url = '';
    $hero_banner_video_type = 'video/mp4';
    $hero_banner_poster = '';

    if (file_exists(__DIR__ . '/../models/model_videos.php')) {
        require_once __DIR__ . '/../models/model_videos.php';
        $hero_banner_video = get_hero_banner_video();
        if ($hero_banner_video && !empty($hero_banner_video['fichier_video'])) {
            $hero_disk = __DIR__ . '/../upload/videos/' . $hero_banner_video['fichier_video'];
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
            }
        }
    }
    ?>
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
                loading="lazy"
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
    <?php
}

/**
 * @return void
 */
function render_home_lazy_galerie_section()
{
    $videos = [];
    if (file_exists(__DIR__ . '/../models/model_videos.php')) {
        require_once __DIR__ . '/../models/model_videos.php';
        $videos = get_all_videos('actif');
    }

    if (empty($videos)) {
        return;
    }
    ?>
    <section class="galerie-creations home-reveal" id="home-creations">
        <div class="galerie-creations-container">
            <header class="galerie-header">
                <span class="galerie-surtitre">Découvrez</span>
                <h2 class="galerie-titre">Nos créations</h2>
                <p class="galerie-sous-titre">Une sélection de nos réalisations en vidéo</p>
            </header>

            <div class="galerie-grid" id="videosSlider">
                <?php foreach ($videos as $video): ?>
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
    <?php
}

/**
 * @return void
 */
function render_home_lazy_top_categories_section()
{
    $top_categories = [];
    if (file_exists(__DIR__ . '/../models/model_categories.php')) {
        require_once __DIR__ . '/../models/model_categories.php';
        $top_categories = get_top_categories(2);
    }
    ?>
    <section class="section5 home-reveal" id="home-top-categories">
        <div class="home-section-head">
            <div>
                <span class="home-section-kicker">Sélection</span>
                <h2 class="home-section-title">Top catégories</h2>
            </div>
        </div>
        <h1>Top Categorie</h1>
        <div class="container">
            <?php if (empty($top_categories)): ?>
            <div class="message-vide" style="text-align: center; padding: 40px; color: var(--texte-fonce);">
                <p>Aucune catégorie disponible pour le moment.</p>
            </div>
            <?php else: ?>
            <?php foreach ($top_categories as $categorie): ?>
            <?php
                $categorie_image_path = '/image/produit1.jpg';
                if (!empty($categorie['image'])) {
                    $resolved_cat = upload_image_url($categorie['image'], 'md');
                    $cat_relative = ltrim(str_replace('/upload/', '', $resolved_cat), '/');
                    if (is_file(__DIR__ . '/../upload/' . $cat_relative)) {
                        $categorie_image_path = $resolved_cat;
                    }
                }
            ?>
            <div class="slider">
                <img src="<?php echo htmlspecialchars($categorie_image_path, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($categorie['nom']); ?>"
                    loading="lazy"
                    decoding="async"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="box">
                    <h4><?php echo htmlspecialchars($categorie['nom']); ?></h4>
                    <a href="categorie.php?id=<?php echo (int) $categorie['id']; ?>">Découvrir <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/**
 * @param string $section_key
 * @return void
 */
function render_home_lazy_placeholder($section_key)
{
    if (!is_valid_home_lazy_section_key($section_key)) {
        return;
    }

    $labels = get_home_lazy_section_labels();
    $label = $labels[$section_key] ?? 'Contenu';
    ?>
    <div class="home-lazy-section"
         data-home-lazy="<?php echo htmlspecialchars($section_key, ENT_QUOTES, 'UTF-8'); ?>"
         data-loaded="0"
         aria-busy="true"
         aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="home-lazy-skeleton" aria-hidden="true">
            <span class="home-lazy-skeleton__bar home-lazy-skeleton__bar--sm"></span>
            <span class="home-lazy-skeleton__bar home-lazy-skeleton__bar--lg"></span>
            <span class="home-lazy-skeleton__grid"></span>
        </div>
    </div>
    <?php
}
