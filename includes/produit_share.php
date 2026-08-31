<?php
/**
 * Partage de produit — données, SEO Open Graph et bouton HTML réutilisable.
 */

if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}

function absolute_public_url($relative_uri)
{
    $relative_uri = trim((string) $relative_uri);
    if ($relative_uri === '') {
        return rtrim(get_site_base_url(), '/');
    }
    if (preg_match('#^https?://#i', $relative_uri)) {
        return $relative_uri;
    }
    $base = rtrim(get_site_base_url(), '/');
    return $base . '/' . ltrim($relative_uri, '/');
}

function produit_share_resolve_upload_relative_path($produit)
{
    if (!function_exists('upload_image_url')) {
        require_once __DIR__ . '/image_optimizer.php';
    }

    $img = trim(str_replace('\\', '/', (string) ($produit['image_principale'] ?? '')));
    if ($img === '') {
        return '';
    }

    $uri = upload_image_url($img, 'original');
    return ltrim(str_replace('\\', '/', $uri), '/');
}

function produit_share_og_image_url($produit)
{
    $relative = produit_share_resolve_upload_relative_path($produit);
    if ($relative === '') {
        return absolute_public_url('/image/sugar_paper.jpg');
    }

    // Original JPG/PNG : meilleure compatibilité WhatsApp / Facebook que le WebP.
    return absolute_public_url('/' . $relative);
}

function produit_share_og_image_dimensions($produit)
{
    $relative = produit_share_resolve_upload_relative_path($produit);
    if ($relative === '') {
        return null;
    }

    $upload_root = dirname(__DIR__) . '/upload/';
    $file = $upload_root . str_replace('/', DIRECTORY_SEPARATOR, preg_replace('#^upload/#', '', $relative));
    if (!is_file($file)) {
        $file = $upload_root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
    if (!is_file($file)) {
        return null;
    }

    $info = @getimagesize($file);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }

    return [
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'mime' => isset($info['mime']) ? (string) $info['mime'] : '',
    ];
}

function produit_share_get_display_price($produit)
{
    $prix = (float) ($produit['prix'] ?? $produit['prix_unitaire'] ?? 0);
    $prix_promo = isset($produit['prix_promotion']) ? (float) $produit['prix_promotion'] : 0;
    if ($prix_promo > 0 && ($prix <= 0 || $prix_promo < $prix)) {
        return $prix_promo;
    }
    return $prix;
}

function produit_share_seo_vars($produit, $prix_affichage = null)
{
    $base = rtrim(get_site_base_url(), '/');
    $nom = trim((string) ($produit['nom'] ?? 'Produit'));
    if ($nom === '') {
        $nom = 'Produit';
    }

    if ($prix_affichage === null) {
        $prix_affichage = produit_share_get_display_price($produit);
    }
    $prix_fmt = number_format((float) $prix_affichage, 0, ',', ' ') . ' FCFA';

    $desc_raw = !empty($produit['description']) ? trim(strip_tags((string) $produit['description'])) : '';
    if ($desc_raw === '') {
        $desc_raw = 'Produit décoratif pour gâteau — Sugar Paper.';
    }
    $desc_raw = preg_replace('/\s+/u', ' ', $desc_raw);

    $seo_description = 'Découvrez « ' . $nom . ' » à ' . $prix_fmt . ' sur Sugar Paper. ' . $desc_raw;
    if (function_exists('mb_substr')) {
        $seo_description = mb_substr($seo_description, 0, 200);
    } else {
        $seo_description = substr($seo_description, 0, 200);
    }

    $dims = produit_share_og_image_dimensions($produit);

    $categorie = trim((string) ($produit['categorie_nom'] ?? ''));
    $seo_title = $nom;
    if ($categorie !== '') {
        $seo_title .= ' — ' . $categorie;
    }
    $seo_title .= ' | Sugar Paper Dakar';

    $keywords = $nom . ', décoration gâteau, Sugar Paper Dakar';
    if ($categorie !== '') {
        $keywords = $nom . ', ' . $categorie . ', cake topper, impression comestible, Sugar Paper Sénégal';
    }

    return [
        'seo_title' => $seo_title,
        'seo_description' => $seo_description,
        'seo_keywords' => $keywords,
        'seo_canonical' => $base . '/produit.php?id=' . (int) ($produit['id'] ?? 0),
        'seo_og_type' => 'product',
        'seo_image' => produit_share_og_image_url($produit),
        'seo_image_alt' => $nom,
        'seo_image_width' => isset($dims['width']) ? (int) $dims['width'] : 0,
        'seo_image_height' => isset($dims['height']) ? (int) $dims['height'] : 0,
        'seo_image_type' => isset($dims['mime']) ? (string) $dims['mime'] : '',
        'seo_product_price' => number_format((float) $prix_affichage, 2, '.', ''),
        'seo_product_currency' => 'XOF',
    ];
}

function produit_share_build_data($produit)
{
    $id = (int) ($produit['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $base = rtrim(get_site_base_url(), '/');
    $share_title = trim((string) ($produit['nom'] ?? 'Produit'));
    if ($share_title === '') {
        $share_title = 'Produit';
    }

    $share_price_value = produit_share_get_display_price($produit);
    $share_price = number_format($share_price_value, 0, ',', ' ') . ' FCFA';

    return [
        'share_url' => $base . '/produit.php?id=' . $id,
        'share_title' => $share_title,
        'share_text' => 'Découvrez « ' . $share_title . ' » à ' . $share_price . ' sur Sugar Paper.',
        'share_modal_title' => 'Partager le produit',
        'share_hint' => 'Partagez ce lien pour que vos clients consultent le produit avec photo et prix.',
        'share_image' => produit_share_og_image_url($produit),
    ];
}

function produit_share_button_html($produit)
{
    $data = produit_share_build_data($produit);
    if ($data === null) {
        return '';
    }

    $title_esc = htmlspecialchars($data['share_title'], ENT_QUOTES, 'UTF-8');

    return '<button type="button"'
        . ' class="produit-card-share js-platform-share"'
        . ' aria-label="Partager ' . $title_esc . '"'
        . ' data-share-modal-title="' . htmlspecialchars($data['share_modal_title'], ENT_QUOTES, 'UTF-8') . '"'
        . ' data-share-title="' . $title_esc . '"'
        . ' data-share-url="' . htmlspecialchars($data['share_url'], ENT_QUOTES, 'UTF-8') . '"'
        . ' data-share-text="' . htmlspecialchars($data['share_text'], ENT_QUOTES, 'UTF-8') . '"'
        . ' data-share-hint="' . htmlspecialchars($data['share_hint'], ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="fa-solid fa-share-nodes" aria-hidden="true"></i>'
        . '</button>';
}
