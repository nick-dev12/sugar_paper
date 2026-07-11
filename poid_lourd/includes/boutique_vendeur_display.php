<?php
/**
 * Données publiques vendeur (vitrine) + surcharge CSS variables
 * Ne jamais exposer le hash mot de passe : utiliser boutique_vendeur_display_from_row uniquement sur champs métier.
 */

if (!function_exists('boutique_vendeur_display_from_row')) {
    /**
     * @param array $row Ligne admin (vendeur)
     * @return array<string, string>
     */
    function boutique_vendeur_display_from_row(array $row) {
        return [
            'boutique_nom' => trim((string) ($row['boutique_nom'] ?? '')),
            'nom' => trim((string) ($row['nom'] ?? '')),
            'prenom' => trim((string) ($row['prenom'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'telephone' => trim((string) ($row['telephone'] ?? '')),
            'boutique_logo' => trim((string) ($row['boutique_logo'] ?? '')),
            'boutique_couleur_principale' => trim((string) ($row['boutique_couleur_principale'] ?? '')),
            'boutique_couleur_accent' => trim((string) ($row['boutique_couleur_accent'] ?? '')),
            'boutique_adresse' => trim((string) ($row['boutique_adresse'] ?? '')),
            'boutique_region' => trim((string) ($row['boutique_region'] ?? '')),
            'boutique_country' => strtoupper(trim((string) ($row['boutique_country'] ?? ''))),
            'boutique_latitude' => $row['boutique_latitude'] ?? null,
            'boutique_longitude' => $row['boutique_longitude'] ?? null,
        ];
    }
}

if (!function_exists('boutique_adresse_publique')) {
    /**
     * Adresse affichée publiquement (footer, retrait sur site).
     */
    function boutique_adresse_publique(array $row): string
    {
        $adresse = trim((string) ($row['boutique_adresse'] ?? ''));
        if ($adresse !== '') {
            if (!function_exists('geo_address_concise_normalize')) {
                require_once __DIR__ . '/geo_geocoder.php';
            }
            return geo_address_concise_normalize($adresse);
        }
        $region = trim((string) ($row['boutique_region'] ?? ''));
        $country = strtoupper(trim((string) ($row['boutique_country'] ?? '')));
        if ($region !== '') {
            if (!function_exists('marketplace_country_label')) {
                require_once __DIR__ . '/marketplace_countries.php';
            }
            $country_label = ($country !== '' && function_exists('marketplace_country_is_valid') && marketplace_country_is_valid($country))
                ? marketplace_country_label($country)
                : '';
            return $country_label !== '' ? $region . ', ' . $country_label : $region;
        }
        if (!function_exists('geo_parse_coord')) {
            require_once __DIR__ . '/geo_location_service.php';
        }
        $lat = geo_parse_coord($row['boutique_latitude'] ?? null);
        $lng = geo_parse_coord($row['boutique_longitude'] ?? null);
        if (geo_coords_valid($lat, $lng)) {
            return 'Position GPS : ' . number_format($lat, 5, '.', '') . ', ' . number_format($lng, 5, '.', '');
        }
        return '';
    }
}

if (!function_exists('boutique_pickup_info_from_admin')) {
    /**
     * Infos point de retrait pour affichage client.
     *
     * @return array{nom: string, adresse: string, region: string, telephone: string, lat: ?float, lng: ?float, adresse_ligne: string, maps_url: string, whatsapp_url: string}
     */
    function boutique_pickup_info_from_admin(?array $adm, string $fallback_nom = 'Boutique'): array
    {
        if (!function_exists('geo_parse_coord')) {
            require_once __DIR__ . '/geo_location_service.php';
        }
        $nom = $fallback_nom;
        $adresse = '';
        $region = '';
        $telephone = '';
        $lat = null;
        $lng = null;
        if ($adm && is_array($adm)) {
            $nom = trim((string) ($adm['boutique_nom'] ?? '')) ?: $fallback_nom;
            $adresse = trim((string) ($adm['boutique_adresse'] ?? ''));
            $region = trim((string) ($adm['boutique_region'] ?? ''));
            $telephone = trim((string) ($adm['telephone'] ?? ''));
            $lat = geo_parse_coord($adm['boutique_latitude'] ?? null);
            $lng = geo_parse_coord($adm['boutique_longitude'] ?? null);
        }
        $adresse_ligne = boutique_adresse_publique($adm && is_array($adm) ? $adm : []);
        $maps_url = '';
        $whatsapp_url = '';
        $wa_label = 'Point de retrait — ' . $nom;

        if ($lat !== null && $lng !== null && geo_coords_valid($lat, $lng)) {
            $maps_url = geo_nav_google_maps_dir((float) $lat, (float) $lng);
            $whatsapp_url = geo_share_whatsapp((float) $lat, (float) $lng, $wa_label);
        } elseif ($adresse_ligne !== '') {
            $maps_url = geo_gmaps_search_link($adresse_ligne);
            $whatsapp_url = 'https://wa.me/?text=' . rawurlencode($wa_label . ' : ' . $adresse_ligne . ' ' . $maps_url);
        }

        return [
            'nom' => $nom,
            'adresse' => $adresse,
            'region' => $region,
            'telephone' => $telephone,
            'lat' => geo_coords_valid($lat, $lng) ? (float) $lat : null,
            'lng' => geo_coords_valid($lat, $lng) ? (float) $lng : null,
            'adresse_ligne' => $adresse_ligne,
            'maps_url' => $maps_url,
            'whatsapp_url' => $whatsapp_url,
        ];
    }
}

if (!function_exists('boutique_normalize_hex_color')) {
    /**
     * Valide et normalise #RRGGBB
     */
    function boutique_normalize_hex_color($hex) {
        $h = trim((string) $hex);
        if ($h === '') {
            return '';
        }
        if ($h[0] !== '#') {
            $h = '#' . $h;
        }
        if (strlen($h) !== 7 || !ctype_xdigit(substr($h, 1))) {
            return '';
        }
        return '#' . strtolower(substr($h, 1));
    }
}

if (!function_exists('boutique_echo_theme_style_override')) {
    /**
     * À inclure dans &lt;head&gt; (ex. via seo_meta.php) après _init boutique.
     */
    function boutique_echo_theme_style_override() {
        if (!defined('BOUTIQUE_ADMIN_ID')) {
            return;
        }
        $d = $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'] ?? null;
        if (!is_array($d)) {
            return;
        }
        $c1 = boutique_normalize_hex_color($d['boutique_couleur_principale'] ?? '');
        $c2 = boutique_normalize_hex_color($d['boutique_couleur_accent'] ?? '');
        if ($c1 === '' && $c2 === '') {
            return;
        }
        echo '<style id="boutique-vendeur-theme">' . "\n";
        echo ":root {\n";
        if ($c1 !== '') {
            echo "  --couleur-dominante: {$c1};\n";
            echo "  --bleu-principal: {$c1};\n";
            echo "  --bleu: {$c1};\n";
            echo "  --boutons-secondaires: {$c1};\n";
            echo "  --couleur-dominante-hover: color-mix(in srgb, {$c1} 78%, black);\n";
            echo "  --bleu-fonce: color-mix(in srgb, {$c1} 62%, black);\n";
            echo "  --boutons-secondaires-hover: color-mix(in srgb, {$c1} 78%, black);\n";
        }
        if ($c2 !== '') {
            echo "  --accent-promo: {$c2};\n";
            echo "  --orange: {$c2};\n";
        }
        echo "}\n</style>\n";
    }
}
