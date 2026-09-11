<?php
/**
 * Section produit vedette (mise en avant) — page d'accueil
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_trending.php';
require_once __DIR__ . '/image_optimizer.php';

/**
 * URL de l'image trending pour la section spotlight
 * @param string $image_name
 * @return string
 */
function home_spotlight_image_url($image_name)
{
    $image_name = trim((string) $image_name);
    if ($image_name === '' || $image_name === 'speaker.png') {
        return '/image/produit1.jpg';
    }

    $disk = __DIR__ . '/../upload/trending/' . $image_name;
    if (!is_file($disk)) {
        return '/image/produit1.jpg';
    }

    return upload_subdir_image_url('trending', $image_name, 'lg');
}

/**
 * Images du carrousel spotlight (table + fallback legacy)
 * @return array<int, array{url: string, alt: string}>
 */
function home_spotlight_get_images($image_alt)
{
    $images = [];
    $rows = get_trending_spotlight_images();
    foreach ($rows as $row) {
        $filename = trim((string) ($row['image'] ?? ''));
        if ($filename === '') {
            continue;
        }
        $images[] = [
            'url' => home_spotlight_image_url($filename),
            'alt' => $image_alt,
        ];
    }

    if (!empty($images)) {
        return $images;
    }

    $config = get_trending_config();
    $legacy = trim((string) ($config['image'] ?? ''));
    if ($legacy !== '') {
        $images[] = [
            'url' => home_spotlight_image_url($legacy),
            'alt' => $image_alt,
        ];
    }

    if (empty($images)) {
        $images[] = [
            'url' => '/image/produit1.jpg',
            'alt' => $image_alt,
        ];
    }

    return $images;
}

/**
 * Découpe le titre en deux lignes (séparateur | ou retour ligne)
 * @param string $titre
 * @return array{line1: string, line2: string}
 */
function home_spotlight_parse_title($titre)
{
    $titre = trim((string) $titre);
    if ($titre === '') {
        return ['line1' => 'Sugar Paper', 'line2' => 'Kit impression comestible'];
    }

    $parts = preg_split('/\||\r\n|\n|\r/u', $titre, 2);
    $line1 = trim((string) ($parts[0] ?? ''));
    $line2 = trim((string) ($parts[1] ?? ''));

    if ($line2 === '') {
        $line2 = $line1;
        $line1 = 'Sugar Paper';
    }

    return ['line1' => $line1, 'line2' => $line2];
}

/**
 * Normalise le lien du bouton CTA
 * @param string $lien
 * @return string
 */
function home_spotlight_normalize_link($lien)
{
    $lien = trim((string) $lien);
    if ($lien === '' || $lien === '#' || $lien === 'produits.php') {
        return 'section-produits.php?section=kit_impression';
    }
    return $lien;
}

/**
 * Affiche la section produit vedette si configurée
 * @return void
 */
function render_home_spotlight_section()
{
    $config = get_trending_config();
    if (!is_array($config)) {
        return;
    }

    $label = trim((string) ($config['label'] ?? ''));
    $titre = trim((string) ($config['titre'] ?? ''));
    $description = trim((string) ($config['description'] ?? ''));
    $bouton_texte = trim((string) ($config['bouton_texte'] ?? ''));
    $bouton_lien = home_spotlight_normalize_link($config['bouton_lien'] ?? '');

    if ($label === '' && $titre === '') {
        return;
    }

    if ($label === '') {
        $label = 'Nouveauté';
    }
    if ($bouton_texte === '') {
        $bouton_texte = 'Découvrir';
    }
    if ($description === '') {
        $description = 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.';
    }

    if (strtolower($label) === 'categories') {
        $label = 'Nouveauté';
    }
    if (strtolower($bouton_texte) === 'buy now!' || strtolower($bouton_texte) === 'buy now') {
        $bouton_texte = 'Découvrir';
    }

    $title_parts = home_spotlight_parse_title($titre);
    $image_alt = $title_parts['line2'] !== '' ? $title_parts['line2'] : $title_parts['line1'];
    $spotlight_images = home_spotlight_get_images($image_alt);
    $has_slider = count($spotlight_images) > 1;
    ?>
    <section class="home-spotlight home-reveal" aria-label="Produit en vedette">
        <div class="home-spotlight__inner">
            <span class="home-spotlight__streak home-spotlight__streak--1" aria-hidden="true"></span>
            <span class="home-spotlight__streak home-spotlight__streak--2" aria-hidden="true"></span>
            <div class="home-spotlight__grid">
                <div class="home-spotlight__content">
                    <p class="home-spotlight__badge"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="home-spotlight__title">
                        <span class="home-spotlight__title-line"><?php echo htmlspecialchars($title_parts['line1'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="home-spotlight__title-line home-spotlight__title-line--accent"><?php echo htmlspecialchars($title_parts['line2'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </h2>
                    <p class="home-spotlight__desc"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
                    <a href="<?php echo htmlspecialchars($bouton_lien, ENT_QUOTES, 'UTF-8'); ?>" class="home-spotlight__cta">
                        <span><?php echo htmlspecialchars($bouton_texte, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="home-spotlight__cta-icon" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>
                <div class="home-spotlight__visual">
                    <div class="home-spotlight__pedestal<?php echo $has_slider ? ' home-spotlight__pedestal--slider' : ''; ?>">
                        <div class="home-spotlight__slides">
                            <?php foreach ($spotlight_images as $index => $spotlight_image): ?>
                            <img class="home-spotlight__img<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                src="<?php echo htmlspecialchars($spotlight_image['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($spotlight_image['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                                loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
                                decoding="async"
                                data-slide-index="<?php echo (int) $index; ?>"
                                onerror="this.src='/image/produit1.jpg'">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($has_slider): ?>
            <div class="home-spotlight__dots" role="tablist" aria-label="Images du produit vedette">
                <?php foreach ($spotlight_images as $index => $spotlight_image): ?>
                <button type="button"
                    class="home-spotlight__dot<?php echo $index === 0 ? ' home-spotlight__dot--active' : ''; ?>"
                    data-slide-index="<?php echo (int) $index; ?>"
                    aria-label="Image <?php echo (int) ($index + 1); ?>"
                    aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
