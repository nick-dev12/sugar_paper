<?php
/**
 * Données agrégées pour le sitemap (pages marketplace + vitrines vendeur).
 * Utilisé par sitemap.php (XML dynamique) et generate_sitemap.php (export statique).
 */

require_once __DIR__ . '/site_url.php';
require_once __DIR__ . '/site_brand.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_admin.php';

/**
 * URL absolue d’image produit pour le namespace image:sitemap (ou null si absente/invalide).
 *
 * @param string $base URL du site sans slash final
 * @param array<string,mixed> $p Ligne produit (image_principale, nom…)
 */
function sitemap_abs_url_image_produit($base, array $p) {
    $raw = isset($p['image_principale']) ? trim((string) $p['image_principale']) : '';
    if ($raw === '') {
        return null;
    }
    if (strpos($raw, '..') !== false || strpbrk($raw, "\0<>") !== false) {
        return null;
    }
    $nom = isset($p['nom']) ? trim((string) $p['nom']) : '';
    $title = $nom !== '' ? SITE_BRAND_NAME . ' — ' . $nom : SITE_BRAND_NAME . ' — produit';
    return [
        'image' => rtrim((string) $base, '/') . '/upload/' . str_replace('\\', '/', $raw),
        'image_title' => $title,
    ];
}

/**
 * Construit la liste des entrées sitemap (<url>), chemins relatifs depuis la racine web.
 *
 * @return array<int, array<string, mixed>>
 */
function build_sitemap_url_rows() {
    $base = get_site_base_url();
    $logo_url = $base . '/image/logo_market.png';
    $home_image_title = SITE_BRAND_NAME . ' — Colobane marketplace Sénégal, marché en ligne Dakar, boutiques & produits';

    $rows = [];

    $static_pages = [
        ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily', 'image' => $logo_url, 'image_title' => $home_image_title],
        ['loc' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/produits.php', 'priority' => '0.95', 'changefreq' => 'daily'],
        ['loc' => '/nouveautes.php', 'priority' => '0.9', 'changefreq' => 'daily'],
        ['loc' => '/promo.php', 'priority' => '0.9', 'changefreq' => 'daily'],
        ['loc' => '/commande-personnalisee.php', 'priority' => '0.65', 'changefreq' => 'monthly'],
        ['loc' => '/politique-confidentialite.php', 'priority' => '0.35', 'changefreq' => 'yearly'],
        ['loc' => '/politique-suppression-compte.php', 'priority' => '0.35', 'changefreq' => 'yearly'],
        ['loc' => '/conditions-utilisation.php', 'priority' => '0.35', 'changefreq' => 'yearly'],
        ['loc' => '/boutiques-proches.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => '/choix-inscription.php', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => '/choix-connexion.php', 'priority' => '0.45', 'changefreq' => 'monthly'],
    ];
    $rows = array_merge($rows, $static_pages);

    $cats = [];
    if (function_exists('get_all_categories')) {
        $cats = get_all_categories();
    }
    if (!is_array($cats)) {
        $cats = [];
    }
    foreach ($cats as $cat) {
        if (empty($cat['nom']) || empty($cat['id'])) {
            continue;
        }
        $rows[] = [
            'loc' => '/categorie.php?id=' . (int) $cat['id'],
            'priority' => '0.85',
            'changefreq' => 'weekly',
            'lastmod' => $cat['date_modification'] ?? $cat['date_creation'] ?? null,
        ];
    }

    $produits = function_exists('get_all_produits') ? get_all_produits('actif') : [];
    if (!is_array($produits)) {
        $produits = [];
    }
    foreach ($produits as $p) {
        if (empty($p['id'])) {
            continue;
        }
        $entry = [
            'loc' => '/produit.php?id=' . (int) $p['id'],
            'priority' => '0.8',
            'changefreq' => 'weekly',
            'lastmod' => isset($p['date_modification']) ? $p['date_modification'] : ($p['date_creation'] ?? null),
        ];
        $img = sitemap_abs_url_image_produit($base, $p);
        if ($img !== null) {
            $entry['image'] = $img['image'];
            $entry['image_title'] = $img['image_title'];
        }
        $rows[] = $entry;
    }

    $boutiques = function_exists('get_actifs_vendeurs_pour_sitemap') ? get_actifs_vendeurs_pour_sitemap() : [];
    if (!is_array($boutiques)) {
        $boutiques = [];
    }
    foreach ($boutiques as $boutique) {
        $slug = $boutique['slug'] ?? '';
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            continue;
        }
        $boutique_nom = isset($boutique['boutique_nom']) ? trim((string) $boutique['boutique_nom']) : '';
        $lastmod_b = isset($boutique['date_creation']) ? $boutique['date_creation'] : null;

        $shop_prefix = [
            '/' . rawurlencode($slug) . '/',
            '/' . rawurlencode($slug) . '/produits.php',
            '/' . rawurlencode($slug) . '/nouveautes.php',
            '/' . rawurlencode($slug) . '/promo.php',
            '/' . rawurlencode($slug) . '/contact.php',
        ];

        foreach ($shop_prefix as $i => $path) {
            $r = [
                'loc' => $path,
                'priority' => $i === 0 ? '0.75' : '0.7',
                'changefreq' => $i === 0 ? 'daily' : 'weekly',
                'lastmod' => $lastmod_b,
            ];
            if ($i === 0 && $boutique_nom !== '') {
                if (!empty($boutique['logo_rel'])) {
                    $lr = str_replace('\\', '/', (string) $boutique['logo_rel']);
                    if (strpos($lr, '..') === false && trim($lr) !== '') {
                        $lr = ltrim(trim($lr), '/');
                        if ($lr !== '' && !preg_match('#^https?://#i', $lr)) {
                            if (strpos($lr, 'upload/') !== 0) {
                                $lr = 'upload/' . $lr;
                            }
                            $r['image'] = rtrim($base, '/') . '/' . $lr;
                            $r['image_title'] = $boutique_nom . ' — boutique sur ' . SITE_BRAND_NAME;
                        }
                    }
                }
            }
            $rows[] = $r;
        }

        $vid = isset($boutique['id']) ? (int) $boutique['id'] : 0;
        if ($vid > 0 && function_exists('produits_has_column') && produits_has_column('admin_id')) {
            $cats_vendeur = [];
            if (function_exists('get_all_categories_for_vendeur')) {
                $cats_vendeur = get_all_categories_for_vendeur($vid);
            }
            if (!is_array($cats_vendeur)) {
                $cats_vendeur = [];
            }
            foreach ($cats_vendeur as $cv) {
                if (empty($cv['id'])) {
                    continue;
                }
                $rows[] = [
                    'loc' => '/' . rawurlencode($slug) . '/categorie.php?id=' . (int) $cv['id'],
                    'priority' => '0.65',
                    'changefreq' => 'weekly',
                    'lastmod' => $cv['date_modification'] ?? $cv['date_creation'] ?? $lastmod_b,
                ];
            }
        }
    }

    return $rows;
}
