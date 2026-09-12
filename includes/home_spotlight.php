<?php
/**
 * Section produit vedette (mise en avant) — page d'accueil
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_trending.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/home_sections.php';

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
 * Normalise le lien du bouton CTA (legacy)
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
 * Prépare les slides spotlight pour l'accueil
 * @return array<int, array<string, mixed>>
 */
function home_spotlight_get_prepared_slides()
{
    $slides = get_trending_slides();
    if (!empty($slides)) {
        $prepared = [];
        foreach ($slides as $slide) {
            $label = trim((string) ($slide['label'] ?? ''));
            $titre = trim((string) ($slide['titre'] ?? ''));
            if ($label === '' && $titre === '') {
                continue;
            }
            if ($label === '') {
                $label = 'Nouveauté';
            }
            if (strtolower($label) === 'categories') {
                $label = 'Nouveauté';
            }

            $title_parts = home_spotlight_parse_title($titre);
            $bouton_texte = trim((string) ($slide['bouton_texte'] ?? 'Découvrir'));
            if ($bouton_texte === '' || strtolower($bouton_texte) === 'buy now!' || strtolower($bouton_texte) === 'buy now') {
                $bouton_texte = 'Découvrir';
            }

            $description = trim((string) ($slide['description'] ?? ''));
            if ($description === '') {
                $description = 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.';
            }

            $section_key = trending_normalize_section_key($slide['section_key'] ?? 'kit_impression');
            $image_name = trim((string) ($slide['image'] ?? ''));
            $image_alt = $title_parts['line2'] !== '' ? $title_parts['line2'] : $title_parts['line1'];

            $prepared[] = [
                'id' => (int) ($slide['id'] ?? 0),
                'label' => $label,
                'title_parts' => $title_parts,
                'description' => $description,
                'bouton_texte' => $bouton_texte,
                'bouton_lien' => trending_slide_section_url($section_key),
                'section_key' => $section_key,
                'image_url' => home_spotlight_image_url($image_name),
                'image_alt' => $image_alt,
            ];
        }
        return $prepared;
    }

    $config = get_trending_config();
    if (!is_array($config)) {
        return [];
    }

    $label = trim((string) ($config['label'] ?? ''));
    $titre = trim((string) ($config['titre'] ?? ''));
    if ($label === '' && $titre === '') {
        return [];
    }

    if ($label === '') {
        $label = 'Nouveauté';
    }

    $title_parts = home_spotlight_parse_title($titre);
    $description = trim((string) ($config['description'] ?? ''));
    if ($description === '') {
        $description = 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.';
    }

    $bouton_texte = trim((string) ($config['bouton_texte'] ?? 'Découvrir'));
    if ($bouton_texte === '') {
        $bouton_texte = 'Découvrir';
    }

    $rows = get_trending_spotlight_images();
    $image_name = '';
    if (!empty($rows)) {
        $image_name = trim((string) ($rows[0]['image'] ?? ''));
    }
    if ($image_name === '') {
        $image_name = trim((string) ($config['image'] ?? ''));
    }

    $image_alt = $title_parts['line2'] !== '' ? $title_parts['line2'] : $title_parts['line1'];

    return [[
        'id' => 0,
        'label' => $label,
        'title_parts' => $title_parts,
        'description' => $description,
        'bouton_texte' => $bouton_texte,
        'bouton_lien' => home_spotlight_normalize_link($config['bouton_lien'] ?? ''),
        'section_key' => 'kit_impression',
        'image_url' => home_spotlight_image_url($image_name),
        'image_alt' => $image_alt,
    ]];
}

/**
 * Affiche la section produit vedette si configurée
 * @return void
 */
function render_home_spotlight_section()
{
    $slides = home_spotlight_get_prepared_slides();
    if (empty($slides)) {
        return;
    }

    $has_slider = count($slides) > 1;
    ?>
    <section class="home-spotlight home-reveal<?php echo $has_slider ? ' home-spotlight--slider' : ''; ?>" aria-label="Produit en vedette">
        <div class="home-spotlight__inner">
            <span class="home-spotlight__streak home-spotlight__streak--1" aria-hidden="true"></span>
            <span class="home-spotlight__streak home-spotlight__streak--2" aria-hidden="true"></span>
            <div class="home-spotlight__slider">
                <?php foreach ($slides as $index => $slide): ?>
                <?php $title_parts = $slide['title_parts']; ?>
                <div class="home-spotlight__slide<?php echo $index === 0 ? ' is-active' : ''; ?>" data-slide-index="<?php echo (int) $index; ?>">
                    <div class="home-spotlight__grid">
                        <div class="home-spotlight__content">
                            <p class="home-spotlight__badge"><?php echo htmlspecialchars($slide['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <h2 class="home-spotlight__title">
                                <span class="home-spotlight__title-line"><?php echo htmlspecialchars($title_parts['line1'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="home-spotlight__title-line home-spotlight__title-line--accent"><?php echo htmlspecialchars($title_parts['line2'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </h2>
                            <p class="home-spotlight__desc"><?php echo htmlspecialchars($slide['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <a href="<?php echo htmlspecialchars($slide['bouton_lien'], ENT_QUOTES, 'UTF-8'); ?>" class="home-spotlight__cta">
                                <span><?php echo htmlspecialchars($slide['bouton_texte'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="home-spotlight__cta-icon" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                            </a>
                        </div>
                        <div class="home-spotlight__visual">
                            <div class="home-spotlight__pedestal">
                                <img class="home-spotlight__img"
                                    src="<?php echo htmlspecialchars($slide['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($slide['image_alt'], ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
                                    decoding="async"
                                    onerror="this.src='/image/produit1.jpg'">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($has_slider): ?>
            <div class="home-spotlight__dots" role="tablist" aria-label="Slides produit vedette">
                <?php foreach ($slides as $index => $slide): ?>
                <button type="button"
                    class="home-spotlight__dot<?php echo $index === 0 ? ' home-spotlight__dot--active' : ''; ?>"
                    data-slide-index="<?php echo (int) $index; ?>"
                    aria-label="Slide <?php echo (int) ($index + 1); ?>"
                    aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
