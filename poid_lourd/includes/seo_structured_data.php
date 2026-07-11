<?php
/**
 * Données structurées JSON-LD (schema.org) pour Google et autres moteurs.
 * Inclure via $seo_json_ld_blocks dans seo_meta.php.
 */
require_once __DIR__ . '/site_brand.php';
if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}

if (!function_exists('site_brand_alternate_names')) {
    /**
     * Variantes orthographiques de la marque (singular/plural, casse).
     *
     * @return string[]
     */
    function site_brand_alternate_names() {
        return [
            'Colobane',
            'Colobanes',
            'COLOBANE',
            'COLOBANES',
            'colobane',
            'colobanes',
            'Colobane marketplace',
            'Colobane marché en ligne',
            'Colobane Dakar',
            'Marché Colobane',
            'Marché de Colobane',
            'Colobane Sénégal',
            'colobanes.com',
            'Colobanes.com',
        ];
    }
}

if (!function_exists('seo_structured_data_homepage_graph')) {
    /** Bloc unique @graph pour l'accueil (léger, une seule balise script). */
    function seo_structured_data_homepage_graph() {
        $base = get_site_base_url();
        $names = site_brand_alternate_names();
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $base . '/#organization',
                    'name' => SITE_BRAND_NAME,
                    'alternateName' => $names,
                    'url' => $base . '/',
                    'logo' => $base . '/icons/icon-512.png',
                    'email' => SITE_BRAND_CONTACT_EMAIL,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $base . '/#website',
                    'name' => SITE_BRAND_NAME,
                    'alternateName' => $names,
                    'url' => $base . '/',
                    'publisher' => ['@id' => $base . '/#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => $base . '/produits.php?recherche={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'OnlineStore',
                    'name' => SITE_BRAND_NAME,
                    'url' => $base . '/',
                    'image' => $base . '/image/logo_market.png',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => 'Dakar',
                        'addressCountry' => 'SN',
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('seo_structured_data_organization')) {
    function seo_structured_data_organization() {
        $base = get_site_base_url();
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SITE_BRAND_NAME,
            'alternateName' => site_brand_alternate_names(),
            'url' => $base . '/',
            'logo' => $base . '/icons/icon-512.png',
            'image' => $base . '/image/logo_market.png',
            'description' => site_brand_seo_description_default(),
            'email' => SITE_BRAND_CONTACT_EMAIL,
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'Sénégal'],
                ['@type' => 'City', 'name' => 'Dakar'],
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Marché de Colobane',
                'addressLocality' => 'Dakar',
                'addressRegion' => 'Dakar',
                'addressCountry' => 'SN',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'email' => SITE_BRAND_CONTACT_EMAIL,
                'areaServed' => 'SN',
                'availableLanguage' => ['French', 'Wolof'],
            ],
        ];
    }
}

if (!function_exists('seo_structured_data_website')) {
    function seo_structured_data_website() {
        $base = get_site_base_url();
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => SITE_BRAND_NAME,
            'alternateName' => site_brand_alternate_names(),
            'url' => $base . '/',
            'description' => site_brand_seo_description_default(),
            'inLanguage' => 'fr-SN',
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_BRAND_NAME,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $base . '/icons/icon-512.png',
                ],
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $base . '/produits.php?recherche={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}

if (!function_exists('seo_structured_data_local_business')) {
    function seo_structured_data_local_business() {
        $base = get_site_base_url();
        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => SITE_BRAND_NAME . ' — Marché en ligne Colobane',
            'alternateName' => site_brand_alternate_names(),
            'url' => $base . '/',
            'image' => $base . '/image/logo_market.png',
            'logo' => $base . '/icons/icon-512.png',
            'description' => site_brand_seo_description_default(),
            'email' => SITE_BRAND_CONTACT_EMAIL,
            'priceRange' => 'FCFA',
            'currenciesAccepted' => 'XOF',
            'paymentAccepted' => 'Cash, Mobile Money',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Marché de Colobane',
                'addressLocality' => 'Dakar',
                'addressRegion' => 'Dakar',
                'addressCountry' => 'SN',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => 14.6937,
                'longitude' => -17.4441,
            ],
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Sénégal',
            ],
        ];
    }
}

if (!function_exists('seo_structured_data_product')) {
    /**
     * @param array<string,mixed> $produit
     * @param string $boutique_nom
     */
    function seo_structured_data_product(array $produit, $boutique_nom = '') {
        $base = get_site_base_url();
        $id = (int) ($produit['id'] ?? 0);
        $nom = trim((string) ($produit['nom'] ?? ''));
        $desc = trim(strip_tags((string) ($produit['description'] ?? '')));
        if ($desc === '') {
            $desc = $nom;
        }
        $prix = isset($produit['prix_promotion']) && $produit['prix_promotion'] !== null && $produit['prix_promotion'] !== ''
            ? (float) $produit['prix_promotion']
            : (float) ($produit['prix'] ?? 0);
        $stock = (int) ($produit['stock'] ?? 0);
        $statut = (string) ($produit['statut'] ?? 'actif');
        $availability = ($statut === 'actif' && $stock > 0)
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $image = $base . '/icons/icon-512.png';
        $img_raw = trim((string) ($produit['image_principale'] ?? ''));
        if ($img_raw !== '' && strpos($img_raw, '..') === false) {
            $image = $base . '/upload/' . str_replace('\\', '/', $img_raw);
        }

        $brand = $boutique_nom !== '' ? $boutique_nom : SITE_BRAND_NAME;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $nom,
            'description' => mb_substr($desc, 0, 5000),
            'image' => $image,
            'sku' => (string) ($produit['identifiant_interne'] ?? $id),
            'url' => $base . '/produit.php?id=' . $id,
            'brand' => [
                '@type' => 'Brand',
                'name' => $brand,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $base . '/produit.php?id=' . $id,
                'priceCurrency' => 'XOF',
                'price' => number_format($prix, 2, '.', ''),
                'availability' => $availability,
                'seller' => [
                    '@type' => 'Organization',
                    'name' => SITE_BRAND_NAME,
                ],
            ],
        ];
    }
}

if (!function_exists('seo_meta_echo_json_ld')) {
    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    function seo_meta_echo_json_ld(array $blocks) {
        $blocks = array_values(array_filter($blocks, function ($b) {
            return is_array($b) && !empty($b);
        }));
        if ($blocks === []) {
            return;
        }
        $payload = count($blocks) === 1 ? $blocks[0] : $blocks;
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "\n</script>\n";
    }
}
