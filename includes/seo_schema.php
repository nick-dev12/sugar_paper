<?php
/**
 * Schémas JSON-LD Schema.org pour le référencement.
 */

require_once __DIR__ . '/seo_config.php';
require_once __DIR__ . '/produit_share.php';

/**
 * @param mixed $data
 * @return string
 */
function seo_schema_json_encode($data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false ? $json : '{}';
}

/**
 * @param array<int, array<string, mixed>> $graphs
 * @return void
 */
function seo_schema_render_scripts($graphs)
{
    if (empty($graphs)) {
        return;
    }

    $clean = [];
    foreach ($graphs as $graph) {
        if (is_array($graph) && !empty($graph)) {
            $clean[] = $graph;
        }
    }
    if (empty($clean)) {
        return;
    }

    if (count($clean) === 1) {
        $payload = $clean[0];
        if (!isset($payload['@context'])) {
            $payload['@context'] = 'https://schema.org';
        }
    } else {
        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => $clean,
        ];
    }

    echo '<script type="application/ld+json">' . seo_schema_json_encode($payload) . '</script>' . "\n";
}

/**
 * @return array<string, mixed>
 */
function seo_schema_build_organization()
{
    $org = get_seo_site_organization();

    return [
        '@type' => ['LocalBusiness', 'Store'],
        '@id' => $org['url'] . '/#organization',
        'name' => $org['name'],
        'url' => $org['url'],
        'logo' => $org['logo'],
        'image' => $org['logo'],
        'email' => $org['email'],
        'telephone' => $org['telephone'],
        'priceRange' => $org['priceRange'],
        'areaServed' => $org['areaServed'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $org['streetAddress'],
            'addressLocality' => $org['addressLocality'],
            'addressRegion' => $org['addressRegion'],
            'postalCode' => $org['postalCode'],
            'addressCountry' => $org['addressCountry'],
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => $org['geo']['latitude'],
            'longitude' => $org['geo']['longitude'],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function seo_schema_build_website()
{
    $org = get_seo_site_organization();

    return [
        '@type' => 'WebSite',
        '@id' => $org['url'] . '/#website',
        'url' => $org['url'],
        'name' => $org['name'],
        'publisher' => ['@id' => $org['url'] . '/#organization'],
        'inLanguage' => 'fr-SN',
    ];
}

/**
 * @param array<int, array{name: string, url: string}> $items
 * @return array<string, mixed>
 */
function seo_schema_build_breadcrumb($items)
{
    $list = [];
    $position = 1;
    foreach ($items as $item) {
        if (empty($item['name']) || empty($item['url'])) {
            continue;
        }
        $list[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $item['name'],
            'item' => $item['url'],
        ];
        $position++;
    }

    if (empty($list)) {
        return [];
    }

    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

/**
 * @param string $name
 * @param string $description
 * @param string $url
 * @return array<string, mixed>
 */
function seo_schema_build_collection_page($name, $description, $url)
{
    $org = get_seo_site_organization();

    return [
        '@type' => 'CollectionPage',
        'name' => $name,
        'description' => $description,
        'url' => $url,
        'isPartOf' => ['@id' => $org['url'] . '/#website'],
        'about' => ['@id' => $org['url'] . '/#organization'],
    ];
}

/**
 * @param array $produit
 * @param float|null $prix_affichage
 * @return array<string, mixed>
 */
function seo_schema_build_product($produit, $prix_affichage = null)
{
    if (empty($produit['id'])) {
        return [];
    }

    $org = get_seo_site_organization();
    $base = rtrim($org['url'], '/');
    $id = (int) $produit['id'];
    $url = $base . '/produit.php?id=' . $id;
    $nom = trim((string) ($produit['nom'] ?? 'Produit'));
    if ($nom === '') {
        $nom = 'Produit';
    }

    if ($prix_affichage === null) {
        $prix = (float) ($produit['prix'] ?? 0);
        $prix_promo = isset($produit['prix_promotion']) ? (float) $produit['prix_promotion'] : 0;
        if ($prix_promo > 0 && ($prix <= 0 || $prix_promo < $prix)) {
            $prix_affichage = $prix_promo;
        } else {
            $prix_affichage = $prix;
        }
    }

    $image = produit_share_og_image_url($produit);
    $desc = !empty($produit['description']) ? trim(strip_tags((string) $produit['description'])) : '';
    if ($desc === '') {
        $desc = 'Produit de décoration pour gâteau — Sugar Paper, Dakar.';
    }
    $desc = preg_replace('/\s+/u', ' ', $desc);
    if (function_exists('mb_substr')) {
        $desc = mb_substr($desc, 0, 500);
    } else {
        $desc = substr($desc, 0, 500);
    }

    $schema = [
        '@type' => 'Product',
        '@id' => $url . '#product',
        'name' => $nom,
        'description' => $desc,
        'url' => $url,
        'image' => $image,
        'brand' => [
            '@type' => 'Brand',
            'name' => 'Sugar Paper',
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'XOF',
            'price' => number_format((float) $prix_affichage, 2, '.', ''),
            'availability' => (!empty($produit['statut']) && $produit['statut'] === 'rupture_stock')
                ? 'https://schema.org/OutOfStock'
                : 'https://schema.org/InStock',
            'seller' => ['@id' => $org['url'] . '/#organization'],
        ],
    ];

    if (!empty($produit['categorie_nom'])) {
        $schema['category'] = (string) $produit['categorie_nom'];
    }

    return $schema;
}

/**
 * Schémas par défaut (Organization + WebSite) pour les pages publiques.
 *
 * @return array<int, array<string, mixed>>
 */
function seo_schema_default_graphs()
{
    return [
        seo_schema_build_organization(),
        seo_schema_build_website(),
    ];
}
