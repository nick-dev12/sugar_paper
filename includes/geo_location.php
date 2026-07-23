<?php
/**
 * Localisation GPS client — commandes Sugar Paper.
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/../conn/conn.php';

define('GEO_SOURCES_COMMANDE', ['gps', 'map_pin', 'adresse', 'ip']);

/**
 * @param mixed $value
 */
function geo_parse_coord($value) {
    if ($value === null || $value === '' || is_array($value)) {
        return null;
    }
    $value = str_replace(',', '.', trim((string) $value));
    if (!is_numeric($value)) {
        return null;
    }
    $f = (float) $value;
    return is_finite($f) ? $f : null;
}

/**
 * @param float|null $lat
 * @param float|null $lng
 */
function geo_coords_valid($lat, $lng) {
    if ($lat === null || $lng === null) {
        return false;
    }
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return false;
    }
    if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
        return false;
    }
    return true;
}

/**
 * @param mixed $value
 */
function geo_parse_precision($value) {
    $p = geo_parse_coord($value);
    if ($p === null || $p < 0) {
        return null;
    }
    return min($p, 999999.0);
}

function geo_commandes_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    global $db;
    try {
        $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'delivery_latitude'");
        $ready = $r && $r->rowCount() > 0;
    } catch (PDOException $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * @param string|null $mode
 */
function commande_mode_livraison_normalize($mode) {
    $m = strtolower(trim((string) $mode));
    if (in_array($m, ['retrait', 'sur_place', 'pickup', 'recuperer'], true)) {
        return 'retrait';
    }
    return 'livraison';
}

/**
 * Zone « Récupérer sur place ».
 *
 * @param array<int, array<string, mixed>> $zones
 * @return array<string, mixed>|null
 */
function zones_livraison_find_retrait(array $zones) {
    foreach ($zones as $zone) {
        $ville = mb_strtolower(trim((string) ($zone['ville'] ?? '')));
        $quartier = mb_strtolower(trim((string) ($zone['quartier'] ?? '')));
        if (strpos($ville, 'récupérer') !== false || strpos($quartier, 'récupérer') !== false
            || strpos($ville, 'recuperer') !== false || strpos($quartier, 'recuperer') !== false) {
            return $zone;
        }
    }
    foreach ($zones as $zone) {
        if ((float) ($zone['prix_livraison'] ?? -1) <= 0) {
            return $zone;
        }
    }
    return null;
}

/**
 * @param array<int, array<string, mixed>> $zones
 * @param array<string, mixed>|null $zone_retrait
 * @return array<int, array<string, mixed>>
 */
function zones_livraison_filter_delivery(array $zones, $zone_retrait) {
    if (!$zone_retrait || empty($zone_retrait['id'])) {
        return $zones;
    }
    $retrait_id = (int) $zone_retrait['id'];
    $filtered = [];
    foreach ($zones as $zone) {
        if ((int) ($zone['id'] ?? 0) === $retrait_id) {
            continue;
        }
        $filtered[] = $zone;
    }
    return $filtered;
}

/**
 * Libellé d'adresse à partir de lat/lng (Nominatim).
 */
function geo_reverse_geocode_label($lat, $lng) {
    require_once __DIR__ . '/geo_geocode_suggest.php';
    if (!geo_coords_valid($lat, $lng)) {
        return '';
    }
    if (function_exists('geo_geocode_reverse')) {
        $label = geo_geocode_reverse($lat, $lng);
        if ($label !== '') {
            return $label;
        }
    }
    return sprintf('Position GPS : %.6f, %.6f', $lat, $lng);
}

/**
 * Enregistre la position exacte du client sur une commande.
 */
function geo_save_commande_location($commande_id, $lat, $lng, $precision = null, $source = 'gps') {
    global $db;

    $commande_id = (int) $commande_id;
    $lat = geo_parse_coord($lat);
    $lng = geo_parse_coord($lng);
    $precision = geo_parse_precision($precision);

    if ($commande_id <= 0 || !geo_coords_valid($lat, $lng) || !geo_commandes_ready()) {
        return false;
    }
    if (!in_array($source, GEO_SOURCES_COMMANDE, true)) {
        $source = 'gps';
    }

    try {
        $has_precision = false;
        try {
            $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'delivery_geo_precision'");
            $has_precision = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has_precision = false;
        }

        if ($has_precision) {
            $stmt = $db->prepare("
                UPDATE commandes SET
                    delivery_latitude = :lat,
                    delivery_longitude = :lng,
                    delivery_geo_precision = :precision,
                    delivery_geo_source = :source,
                    delivery_geo_date = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $commande_id,
                'lat' => $lat,
                'lng' => $lng,
                'precision' => $precision,
                'source' => $source,
            ]);
        }

        $stmt = $db->prepare("
            UPDATE commandes SET
                delivery_latitude = :lat,
                delivery_longitude = :lng
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $commande_id,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    } catch (PDOException $e) {
        error_log('[geo_save_commande_location] ' . $e->getMessage());
        return false;
    }
}
