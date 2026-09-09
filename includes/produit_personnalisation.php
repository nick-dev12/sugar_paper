<?php
/**
 * Personnalisation photo / impression comestible sur fiche produit
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/cake_topper_cp.php';

/**
 * Personnalisation produit activée (désactivée temporairement)
 * @return bool
 */
function produit_personnalisation_enabled()
{
    return true;
}

function produit_supports_photo_personnalisation($produit)
{
    if (!is_array($produit)) {
        return false;
    }

    return normalize_produit_section_accueil($produit['section_accueil'] ?? '') === 'photo_impression';
}

/**
 * Personnalisation cupcakes (12 cercles sur feuille)
 * @param array|null $produit
 * @return bool
 */
function produit_supports_cupcakes_personnalisation($produit)
{
    if (!is_array($produit)) {
        return false;
    }

    return normalize_produit_section_accueil($produit['section_accueil'] ?? '') === 'cupcakes';
}

/**
 * Personnalisation contours de gâteau (3 bandes sur feuille A3/A4)
 * @param array|null $produit
 * @return bool
 */
function produit_supports_contours_personnalisation($produit)
{
    if (!is_array($produit)) {
        return false;
    }

    return normalize_produit_section_accueil($produit['section_accueil'] ?? '') === 'contours_gateau';
}

/**
 * Personnalisation feuille (photo OU cupcakes OU contours)
 * @param array|null $produit
 * @return bool
 */
function produit_supports_sheet_personnalisation($produit)
{
    return produit_supports_photo_personnalisation($produit)
        || produit_supports_cupcakes_personnalisation($produit)
        || produit_supports_contours_personnalisation($produit);
}

/**
 * Bouton personnalisation sur les cartes catalogue / accueil (section photo_impression uniquement)
 * @param array|string|null $produit
 * @return bool
 */
function produit_listing_uses_personnalisation($produit)
{
    if (!produit_personnalisation_enabled() || !is_array($produit)) {
        return false;
    }

    return produit_supports_sheet_personnalisation($produit);
}

/**
 * Chemin relatif du modèle de gâteau (depuis la racine web)
 * @return string
 */
function produit_personnalisation_modele_url()
{
    return '/image/gateau-personnalisation-modele.jpg';
}

/**
 * Dossier d'upload des visuels personnalisés
 * @return string
 */
function produit_personnalisation_upload_dir()
{
    return __DIR__ . '/../upload/produits-personnalises/';
}

/**
 * Sous-dossier relatif pour image_optimizer
 * @return string
 */
function produit_personnalisation_upload_subdir()
{
    return 'produits-personnalises';
}

/**
 * Valide qu'un chemin relatif appartient bien au dossier personnalisation
 * @param string|null $relative_path
 * @return bool
 */
function produit_personnalisation_path_is_valid($relative_path)
{
    $relative_path = trim((string) $relative_path);
    if ($relative_path === '' || strpos($relative_path, '..') !== false) {
        return false;
    }

    $prefix = produit_personnalisation_upload_subdir() . '/';
    if (strpos($relative_path, $prefix) !== 0) {
        return false;
    }

    $disk = __DIR__ . '/../upload/' . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    return is_file($disk);
}

/**
 * URL publique d'une image personnalisée enregistrée
 * @param string|null $relative_path
 * @return string
 */
function produit_personnalisation_public_url($relative_path)
{
    if (!produit_personnalisation_path_is_valid($relative_path)) {
        return '';
    }

    require_once __DIR__ . '/image_optimizer.php';
    return upload_image_url($relative_path, 'md');
}

/**
 * Badge promo personnalisation (-10 %)
 * @return void
 */
function render_produit_perso_promo_badge()
{
    ?>
    <div class="produit-perso-promo-badge" role="note">
        <span class="produit-perso-promo-icon" aria-hidden="true"><i class="fa-solid fa-gift"></i></span>
        <span class="produit-perso-promo-text"><strong>-10&nbsp;%</strong> de réduction si vous personnalisez votre création</span>
    </div>
    <?php
}

/**
 * Actions carte produit : personnalisation ou ajout panier classique
 * @param array $produit
 * @param string $return_url
 * @return void
 */
function render_produit_listing_actions($produit, $return_url = '/index.php')
{
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        return;
    }

    $return_url = htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8');

    if (produit_listing_uses_cake_topper_cp($produit)) {
        ?>
        <div class="add-to-cart-form">
            <a href="produit.php?id=<?php echo $produit_id; ?>" class="btn-add-cart btn-personnaliser-card">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Personnaliser
            </a>
        </div>
        <?php
        return;
    }

    if (produit_listing_uses_personnalisation($produit)) {
        ?>
        <div class="add-to-cart-form">
            <a href="produit.php?id=<?php echo $produit_id; ?>" class="btn-add-cart btn-personnaliser-card">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Personnalisation
            </a>
        </div>
        <?php
        return;
    }
    ?>
    <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
        <input type="hidden" name="produit_id" value="<?php echo $produit_id; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
        <button type="submit" class="btn-add-cart">
            <i class="fa-solid fa-cart-shopping"></i> Commander
        </button>
    </form>
    <?php
}

/**
 * Modal de prévisualisation personnalisation (une instance par page)
 * @return void
 */
function render_produit_personnalisation_modal()
{
    if (!produit_personnalisation_enabled()) {
        return;
    }

    include __DIR__ . '/produit_personnalisation_modal.php';
}

/**
 * Modal personnalisation cupcakes
 * @return void
 */
function render_produit_personnalisation_cupcakes_modal()
{
    if (!produit_personnalisation_enabled()) {
        return;
    }

    include __DIR__ . '/produit_personnalisation_cupcakes_modal.php';
}

/**
 * Modal personnalisation contours de gâteau
 * @return void
 */
function render_produit_personnalisation_contours_modal()
{
    if (!produit_personnalisation_enabled()) {
        return;
    }

    include __DIR__ . '/produit_personnalisation_contours_modal.php';
}

/**
 * Chemin relatif de personnalisation sur une ligne panier ou commande
 * @param array $ligne
 * @return string
 */
function commande_ligne_personnalisation_path(array $ligne)
{
    $candidates = [
        $ligne['image_personnalisation'] ?? null,
        $ligne['panier_image_personnalisation'] ?? null,
    ];
    foreach ($candidates as $path) {
        $path = trim((string) $path);
        if ($path !== '' && produit_personnalisation_path_is_valid($path)) {
            return $path;
        }
    }
    return '';
}

/**
 * URL miniature pour une ligne panier / commande (priorité au visuel personnalisé)
 * @param array $ligne
 * @param string $size
 * @return string
 */
function commande_ligne_image_url(array $ligne, $size = 'sm')
{
    $perso = commande_ligne_personnalisation_path($ligne);
    if ($perso !== '') {
        return produit_personnalisation_public_url($perso);
    }

    require_once __DIR__ . '/image_optimizer.php';
    $img = $ligne['image_afficher'] ?? $ligne['panier_variante_image'] ?? $ligne['image_principale'] ?? '';
    return upload_image_url($img, $size);
}

/**
 * Affiche l'aperçu personnalisation pour une ligne de commande
 * @param array|string $ligne_or_path Ligne commande ou chemin relatif
 * @param array $options show_download (bool), compact (bool)
 * @return void
 */
function render_commande_personnalisation_preview($ligne_or_path, array $options = [])
{
    $path = is_array($ligne_or_path)
        ? commande_ligne_personnalisation_path($ligne_or_path)
        : trim((string) $ligne_or_path);

    if ($path === '' || !produit_personnalisation_path_is_valid($path)) {
        return;
    }

    $show_download = !isset($options['show_download']) || $options['show_download'];
    $compact = !empty($options['compact']);
    $url = produit_personnalisation_public_url($path);
    $download_url = '/upload/' . ltrim(str_replace('\\', '/', $path), '/');
    $class = 'commande-perso-preview' . ($compact ? ' commande-perso-preview--compact' : '');
    ?>
    <div class="<?php echo htmlspecialchars($class); ?>">
        <div class="commande-perso-preview__head">
            <span class="commande-perso-preview__badge"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Aperçu composé</span>
            <?php if ($show_download): ?>
                <a class="commande-perso-preview__download" href="<?php echo htmlspecialchars($download_url); ?>" download target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-download" aria-hidden="true"></i> Télécharger
                </a>
            <?php endif; ?>
        </div>
        <a class="commande-perso-preview__link" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" title="Voir en grand">
            <img class="commande-perso-preview__img" src="<?php echo htmlspecialchars($url); ?>" alt="Aperçu personnalisation">
        </a>
    </div>
    <?php
}

/**
 * Formats de feuille pour impression comestible
 * @return array<string, array{label:string,width_cm:float,height_cm:float}>
 */
function get_produit_personnalisation_paper_formats()
{
    return [
        'a4' => ['label' => 'A4', 'width_cm' => 21.0, 'height_cm' => 29.7],
        'a3' => ['label' => 'A3', 'width_cm' => 29.7, 'height_cm' => 42.0],
    ];
}

/**
 * Dimensions feuille contours (orientation paysage)
 * @param string $format a4|a3
 * @return array{label:string,width_cm:float,height_cm:float}
 */
function get_produit_personnalisation_contours_paper($format)
{
    $formats = get_produit_personnalisation_paper_formats();
    $format = strtolower(trim((string) $format));
    if (!isset($formats[$format])) {
        $format = 'a4';
    }
    $portrait = $formats[$format];

    return [
        'label' => $portrait['label'],
        'width_cm' => (float) $portrait['height_cm'],
        'height_cm' => (float) $portrait['width_cm'],
    ];
}

/**
 * Chemin relatif de l'image source importée (fichier client, distinct de l'aperçu composé).
 *
 * @param array $ligne
 * @return string
 */
function commande_ligne_personnalisation_source_path(array $ligne)
{
    $candidates = [
        $ligne['image_personnalisation_source'] ?? null,
        $ligne['panier_image_personnalisation_source'] ?? null,
    ];
    foreach ($candidates as $path) {
        $path = trim((string) $path);
        if ($path !== '' && produit_personnalisation_path_is_valid($path)) {
            return $path;
        }
    }
    return '';
}

/**
 * @param array<int, mixed>|null $list
 * @return array<int, array<string, mixed>>
 */
function produit_personnalisation_texts_normalize_list($list)
{
    if (!is_array($list)) {
        return [];
    }
    $texts = [];
    foreach ($list as $text_row) {
        if (!is_array($text_row)) {
            continue;
        }
        $normalized = produit_personnalisation_text_normalize($text_row);
        if (trim((string) ($normalized['text'] ?? '')) === '') {
            continue;
        }
        $texts[] = $normalized;
    }
    return array_values($texts);
}

/**
 * @param array|string|null $raw
 * @return array<string, mixed>|null
 */
function produit_personnalisation_text_normalize(array $text)
{
    $allowed_fonts = ['Outfit', 'Fraunces', 'Pacifico', 'Bebas Neue', 'Dancing Script', 'Playfair Display', 'Lobster'];
    $font = isset($text['font']) ? trim((string) $text['font']) : 'Outfit';
    if (!in_array($font, $allowed_fonts, true)) {
        $font = 'Outfit';
    }
    $color = isset($text['textColor']) ? trim((string) $text['textColor']) : (isset($text['text_color']) ? trim((string) $text['text_color']) : '#E5488A');
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#E5488A';
    }

    return [
        'id' => isset($text['id']) ? substr(trim((string) $text['id']), 0, 40) : '',
        'text' => isset($text['text']) ? mb_substr((string) $text['text'], 0, 500) : '',
        'font' => $font,
        'textSizePct' => max(20, min(100, (int) ($text['textSizePct'] ?? 50))),
        'textPosX' => max(0, min(100, (int) ($text['textPosX'] ?? 50))),
        'textPosY' => max(0, min(100, (int) ($text['textPosY'] ?? 50))),
        'textRotation' => max(-180, min(180, (int) ($text['textRotation'] ?? 0))),
        'wrapOnCircle' => !empty($text['wrapOnCircle']),
        'wrapArcPosition' => (isset($text['wrapArcPosition']) && $text['wrapArcPosition'] === 'bottom') ? 'bottom' : 'top',
        'textColor' => $color,
    ];
}

/**
 * @param array $ligne_or_path
 * @param array $options label (string)
 * @return void
 */
function render_commande_personnalisation_source_preview($ligne_or_path, array $options = [])
{
    $path = is_array($ligne_or_path)
        ? commande_ligne_personnalisation_source_path($ligne_or_path)
        : trim((string) $ligne_or_path);

    if ($path === '' || !produit_personnalisation_path_is_valid($path)) {
        return;
    }

    $label = isset($options['label']) ? (string) $options['label'] : 'Image importée';
    $compact = !empty($options['compact']);
    $url = produit_personnalisation_public_url($path);
    $download_url = '/upload/' . ltrim(str_replace('\\', '/', $path), '/');
    $class = 'commande-perso-preview commande-perso-preview--source' . ($compact ? ' commande-perso-preview--compact' : '');
    ?>
    <div class="<?php echo htmlspecialchars($class); ?>">
        <div class="commande-perso-preview__head">
            <span class="commande-perso-preview__badge"><i class="fa-solid fa-file-image" aria-hidden="true"></i> <?php echo htmlspecialchars($label); ?></span>
            <a class="commande-perso-preview__download" href="<?php echo htmlspecialchars($download_url); ?>" download target="_blank" rel="noopener noreferrer">
                <i class="fas fa-download" aria-hidden="true"></i> Télécharger
            </a>
        </div>
        <a class="commande-perso-preview__link" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" title="Voir en grand">
            <img class="commande-perso-preview__img" src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($label); ?>">
        </a>
    </div>
    <?php
}

/**
 * @param array|string|null $raw
 * @return array{format:string,shape:string,width_cm:float,height_cm:float,texts?:array<int,array<string,mixed>>}|null
 */
function produit_personnalisation_meta_decode($raw)
{
    if (is_array($raw)) {
        return produit_personnalisation_meta_normalize($raw);
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return null;
    }
    return produit_personnalisation_meta_normalize($dec);
}

function produit_personnalisation_image_normalize(array $data)
{
    $offset_x = isset($data['offset_x']) ? (int) $data['offset_x'] : (isset($data['offsetX']) ? (int) $data['offsetX'] : 50);
    $offset_y = isset($data['offset_y']) ? (int) $data['offset_y'] : (isset($data['offsetY']) ? (int) $data['offsetY'] : 50);
    $scale_pct = isset($data['scale_pct']) ? (int) $data['scale_pct'] : (isset($data['scalePct']) ? (int) $data['scalePct'] : 100);

    return [
        'offset_x' => max(-100, min(200, $offset_x)),
        'offset_y' => max(-100, min(200, $offset_y)),
        'scale_pct' => max(10, min(800, $scale_pct)),
    ];
}

/**
 * @param array<int, mixed>|null $layers
 * @return array<int, array<string, int>>
 */
function produit_personnalisation_image_layers_normalize_list($layers)
{
    if (!is_array($layers)) {
        return [];
    }
    $out = [];
    foreach ($layers as $layer_row) {
        if (!is_array($layer_row)) {
            continue;
        }
        $out[] = produit_personnalisation_image_normalize($layer_row);
    }
    return $out;
}

/**
 * @param array $data
 * @return array{format:string,shape:string,width_cm:float,height_cm:float}
 */
function produit_personnalisation_meta_normalize(array $data)
{
    $type = isset($data['type']) ? strtolower(trim((string) $data['type'])) : '';
    if ($type === 'cupcakes') {
        return produit_personnalisation_cupcakes_meta_normalize($data);
    }
    if ($type === 'contours_gateau') {
        return produit_personnalisation_contours_meta_normalize($data);
    }

    $formats = get_produit_personnalisation_paper_formats();
    $format = isset($data['format']) ? strtolower(trim((string) $data['format'])) : 'a4';
    if (!isset($formats[$format])) {
        $format = 'a4';
    }
    $paper = $formats[$format];
    $shape = isset($data['shape']) ? strtolower(trim((string) $data['shape'])) : 'circle';
    if (!in_array($shape, ['circle', 'square', 'heart'], true)) {
        $shape = 'circle';
    }

    $max_w = max(5, (float) $paper['width_cm'] - 2);
    $max_h = max(5, (float) $paper['height_cm'] - 2);
    $max_d = min($max_w, $max_h);

    if ($shape === 'circle') {
        $d = isset($data['width_cm']) ? (float) $data['width_cm'] : 15.0;
        if (isset($data['height_cm']) && !isset($data['width_cm'])) {
            $d = (float) $data['height_cm'];
        }
        $d = max(5, min($d, $max_d));
        $width = $height = $d;
    } else {
        $width = max(5, min(isset($data['width_cm']) ? (float) $data['width_cm'] : 15.0, $max_w));
        $height = max(5, min(isset($data['height_cm']) ? (float) $data['height_cm'] : 15.0, $max_h));
    }

    return [
        'format' => $format,
        'shape' => $shape,
        'width_cm' => round($width, 1),
        'height_cm' => round($height, 1),
    ] + (function ($data) {
        if (!isset($data['texts']) || !is_array($data['texts'])) {
            return [];
        }
        $texts = [];
        foreach ($data['texts'] as $text_row) {
            if (!is_array($text_row)) {
                continue;
            }
            $normalized = produit_personnalisation_text_normalize($text_row);
            if (trim((string) ($normalized['text'] ?? '')) === '') {
                continue;
            }
            $texts[] = $normalized;
        }
        return $texts !== [] ? ['texts' => array_values($texts)] : [];
    })($data) + (function ($data) {
        $layers = produit_personnalisation_image_layers_normalize_list(isset($data['layers']) ? $data['layers'] : []);
        if ($layers === [] && isset($data['image']) && is_array($data['image'])) {
            $image = produit_personnalisation_image_normalize($data['image']);
            if (!($image['offset_x'] === 50 && $image['offset_y'] === 50 && $image['scale_pct'] === 100)) {
                $layers = [$image];
            }
        }
        if ($layers === []) {
            return [];
        }
        return [
            'layers' => $layers,
            'image' => $layers[0],
        ];
    })($data);
}

/**
 * Meta cupcakes : 12 cercles, diamètre global max 5 cm
 * @param array $data
 * @return array
 */
function produit_personnalisation_cupcakes_meta_normalize(array $data)
{
    $formats = get_produit_personnalisation_paper_formats();
    $format = isset($data['format']) ? strtolower(trim((string) $data['format'])) : 'a4';
    if (!isset($formats[$format])) {
        $format = 'a4';
    }
    $shape = isset($data['shape']) ? strtolower(trim((string) $data['shape'])) : 'circle';
    if (!in_array($shape, ['circle', 'heart'], true)) {
        $shape = 'circle';
    }
    $image_mode = isset($data['image_mode']) ? strtolower(trim((string) $data['image_mode'])) : 'shared';
    if (!in_array($image_mode, ['shared', 'per_circle'], true)) {
        $image_mode = 'shared';
    }

    $diameter = isset($data['diameter_cm'])
        ? (float) $data['diameter_cm']
        : (isset($data['width_cm']) ? (float) $data['width_cm'] : 5.0);
    $diameter = max(1, min(5, $diameter));

    $circles = [];
    $raw_circles = isset($data['circles']) && is_array($data['circles']) ? $data['circles'] : [];
    for ($i = 0; $i < 12; $i++) {
        $row = isset($raw_circles[$i]) && is_array($raw_circles[$i]) ? $raw_circles[$i] : [];
        $circle_out = produit_personnalisation_image_normalize($row);
        if ($image_mode === 'per_circle') {
            $texts = produit_personnalisation_texts_normalize_list(isset($row['texts']) ? $row['texts'] : []);
            if ($texts !== []) {
                $circle_out['texts'] = $texts;
            }
        }
        $circles[] = $circle_out;
    }

    $out = [
        'type' => 'cupcakes',
        'format' => $format,
        'shape' => $shape,
        'diameter_cm' => round($diameter, 1),
        'width_cm' => round($diameter, 1),
        'height_cm' => round($diameter, 1),
        'image_mode' => $image_mode,
        'circles' => $circles,
    ];

    if ($image_mode === 'shared' && isset($data['image']) && is_array($data['image'])) {
        $out['image'] = produit_personnalisation_image_normalize($data['image']);
    }

    if ($image_mode === 'shared') {
        $texts = produit_personnalisation_texts_normalize_list(isset($data['texts']) ? $data['texts'] : []);
        if ($texts !== []) {
            $out['texts'] = $texts;
        }
    }

    return $out;
}

/**
 * Meta contours : 3 bandes sur feuille A3/A4
 * @param array $data
 * @return array
 */
function produit_personnalisation_contours_meta_normalize(array $data)
{
    $format = isset($data['format']) ? strtolower(trim((string) $data['format'])) : 'a4';
    $paper = get_produit_personnalisation_contours_paper($format);

    $image_mode = isset($data['image_mode']) ? strtolower(trim((string) $data['image_mode'])) : 'shared';
    if (!in_array($image_mode, ['shared', 'per_contour'], true)) {
        $image_mode = 'shared';
    }

    $edge = 0.7;
    $gap = 0.9;
    $usable_h = max(1.0, (float) $paper['height_cm'] - $edge * 2);
    $max_h = max(2.0, min(6.0, ($usable_h - 2 * $gap) / 3));
    $height = isset($data['height_cm']) ? (float) $data['height_cm'] : 5.0;
    $height = max(2.0, min($height, round($max_h, 1)));

    $usable_w = max(1.0, (float) $paper['width_cm'] - $edge * 2);
    $width = isset($data['width_cm']) ? (float) $data['width_cm'] : $usable_w;
    $width = max(5.0, min($width, $usable_w));

    $contours = [];
    $raw_contours = isset($data['contours']) && is_array($data['contours']) ? $data['contours'] : [];
    for ($i = 0; $i < 3; $i++) {
        $row = isset($raw_contours[$i]) && is_array($raw_contours[$i]) ? $raw_contours[$i] : [];
        $contour_out = [];
        $layers = produit_personnalisation_image_layers_normalize_list(isset($row['layers']) ? $row['layers'] : []);
        if ($layers === [] && (isset($row['offset_x']) || isset($row['offsetX']) || isset($row['scale_pct']) || isset($row['scalePct']))) {
            $layers = [produit_personnalisation_image_normalize($row)];
        }
        if ($layers !== []) {
            $contour_out['layers'] = $layers;
        }
        if ($image_mode === 'per_contour') {
            $texts = produit_personnalisation_texts_normalize_list(isset($row['texts']) ? $row['texts'] : []);
            if ($texts !== []) {
                $contour_out['texts'] = $texts;
            }
        }
        $contours[] = $contour_out;
    }

    $out = [
        'type' => 'contours_gateau',
        'format' => $format,
        'orientation' => 'landscape',
        'height_cm' => round($height, 1),
        'width_cm' => round($width, 1),
        'image_mode' => $image_mode,
        'contours' => $contours,
    ];

    if ($image_mode === 'shared') {
        $shared_layers = produit_personnalisation_image_layers_normalize_list(isset($data['layers']) ? $data['layers'] : []);
        if ($shared_layers === [] && isset($data['image']) && is_array($data['image'])) {
            $shared_layers = [produit_personnalisation_image_normalize($data['image'])];
        }
        if ($shared_layers !== []) {
            $out['layers'] = $shared_layers;
            $out['image'] = $shared_layers[0];
        }
    }

    if ($image_mode === 'shared') {
        $texts = produit_personnalisation_texts_normalize_list(isset($data['texts']) ? $data['texts'] : []);
        if ($texts !== []) {
            $out['texts'] = $texts;
        }
    }

    return $out;
}

/**
 * @param array $meta
 * @return string
 */
function produit_personnalisation_meta_encode(array $meta)
{
    return json_encode(produit_personnalisation_meta_normalize($meta), JSON_UNESCAPED_UNICODE);
}

/**
 * @param array $meta
 * @return string
 */
function produit_personnalisation_meta_label(array $meta)
{
    $n = produit_personnalisation_meta_normalize($meta);
    $formats = get_produit_personnalisation_paper_formats();
    $paper = $formats[$n['format']];

    if (($n['type'] ?? '') === 'cupcakes') {
        $shape_labels = ['circle' => 'Cercle', 'heart' => 'Cœur'];
        $shape_label = $shape_labels[$n['shape']] ?? 'Cercle';
        $mode = ($n['image_mode'] ?? 'shared') === 'per_circle' ? 'image par cercle' : 'image unique';
        return $paper['label'] . ' · Cupcakes · 12 × ' . $shape_label
            . ' Ø ' . number_format((float) ($n['diameter_cm'] ?? $n['width_cm']), 1, ',', ' ') . ' cm · ' . $mode;
    }

    if (($n['type'] ?? '') === 'contours_gateau') {
        $contours_paper = get_produit_personnalisation_contours_paper($n['format'] ?? 'a4');
        $mode = ($n['image_mode'] ?? 'shared') === 'per_contour' ? 'image par contour' : 'image unique';
        return $contours_paper['label'] . ' paysage · Contours · 3 × '
            . number_format((float) ($n['height_cm'] ?? 5), 1, ',', ' ') . ' cm de haut · ' . $mode;
    }

    $shape_labels = [
        'circle' => 'Cercle',
        'square' => 'Carré',
        'heart' => 'Cœur',
    ];
    $shape_label = $shape_labels[$n['shape']] ?? 'Cercle';
    if ($n['shape'] === 'circle') {
        $dim = 'Ø ' . number_format($n['width_cm'], 1, ',', ' ') . ' cm';
    } else {
        $dim = number_format($n['width_cm'], 1, ',', ' ') . ' × ' . number_format($n['height_cm'], 1, ',', ' ') . ' cm';
    }
    return $paper['label'] . ' (' . number_format($paper['width_cm'], 1, ',', ' ') . ' × ' . number_format($paper['height_cm'], 1, ',', ' ') . ' cm) · ' . $shape_label . ' · ' . $dim;
}

/**
 * @param array $ligne
 * @return array{format:string,shape:string,width_cm:float,height_cm:float}|null
 */
function commande_ligne_personnalisation_meta(array $ligne)
{
    $raw = $ligne['personnalisation_meta'] ?? $ligne['panier_personnalisation_meta'] ?? null;
    return produit_personnalisation_meta_decode($raw);
}

/**
 * Affiche les specs format / forme / dimensions cm
 * @param array $ligne
 * @param array $options
 * @return void
 */
function render_commande_personnalisation_specs(array $ligne, array $options = [])
{
    $meta = commande_ligne_personnalisation_meta($ligne);
    if (!$meta) {
        return;
    }
    $compact = !empty($options['compact']);
    $class = 'commande-perso-specs' . ($compact ? ' commande-perso-specs--compact' : '');
    ?>
    <p class="<?php echo htmlspecialchars($class); ?>">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        <?php echo htmlspecialchars(produit_personnalisation_meta_label($meta)); ?>
    </p>
    <?php
}
