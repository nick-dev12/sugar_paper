<?php
/**
 * Variables d'affichage — localisation boutique vendeur (partial UI).
 */
function vendeur_boutique_localisation_prepare_ui_vars(array $vbl_admin, array $options = [])
{
    require_once __DIR__ . '/../../includes/marketplace_countries.php';
    require_once __DIR__ . '/../../includes/geo_regions.php';
    require_once __DIR__ . '/../../includes/geo_location_service.php';
    require_once __DIR__ . '/../../includes/geo_geocoder.php';

    $admin_id = (int) ($options['admin_id'] ?? 0);
    $allow_reverse = !empty($options['allow_reverse_geocode_upgrade']);

    $vbl_country_val = strtoupper(trim((string) ($vbl_admin['boutique_country'] ?? 'SN')));
    if (!marketplace_country_is_valid($vbl_country_val)) {
        $vbl_country_val = marketplace_country_default_code();
    }
    $vbl_region_raw = trim((string) ($vbl_admin['boutique_region'] ?? ''));
    $vbl_region_val = htmlspecialchars($vbl_region_raw, ENT_QUOTES, 'UTF-8');

    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    $vbl_csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

    $vbl_geo_lat = geo_parse_coord($vbl_admin['boutique_latitude'] ?? null);
    $vbl_geo_lng = geo_parse_coord($vbl_admin['boutique_longitude'] ?? null);
    $vbl_geo_set = geo_coords_valid($vbl_geo_lat, $vbl_geo_lng);

    if ($allow_reverse && $vbl_geo_set && $admin_id > 0) {
        require_once __DIR__ . '/../../models/model_admin.php';
        $stored_adresse = trim((string) ($vbl_admin['boutique_adresse'] ?? ''));
        $enriched_adresse = geo_reverse_geocode($vbl_geo_lat, $vbl_geo_lng);
        if ($enriched_adresse !== null && $enriched_adresse !== ''
            && geo_address_should_upgrade_label($stored_adresse, $enriched_adresse)) {
            update_admin_boutique_branding($admin_id, ['boutique_adresse' => $enriched_adresse]);
            $vbl_admin['boutique_adresse'] = $enriched_adresse;
        }
    }

    $vbl_position_text = geo_address_concise_normalize(trim((string) ($vbl_admin['boutique_adresse'] ?? '')));
    if ($vbl_geo_set) {
        $segments = geo_address_segments_from_coords($vbl_geo_lat, $vbl_geo_lng);
        if ($segments !== []) {
            $vbl_position_text = geo_address_merge_parts($segments, 5);
        }
    }
    if ($vbl_position_text === '' && $vbl_geo_set) {
        $rev = geo_reverse_geocode($vbl_geo_lat, $vbl_geo_lng);
        if ($rev !== null && $rev !== '') {
            $vbl_position_text = $rev;
        }
    }
    $vbl_position_text_html = htmlspecialchars($vbl_position_text, ENT_QUOTES, 'UTF-8');

    return [
        'vbl_country_val' => $vbl_country_val,
        'vbl_region_raw' => $vbl_region_raw,
        'vbl_region_val' => $vbl_region_val,
        'vbl_csrf' => $vbl_csrf,
        'vbl_geo_lat' => $vbl_geo_lat,
        'vbl_geo_lng' => $vbl_geo_lng,
        'vbl_geo_set' => $vbl_geo_set,
        'vbl_position_text' => $vbl_position_text,
        'vbl_position_text_html' => $vbl_position_text_html,
    ];
}
