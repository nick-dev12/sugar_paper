<?php
/**
 * Géocodage via Nominatim (OpenStreetMap) — open source, gratuit.
 * Programmation procédurale uniquement.
 *
 * Règles d'utilisation Nominatim (https://operations.osmfoundation.org/policies/nominatim/) :
 * - Maximum 1 requête par seconde
 * - User-Agent identifiant l'application obligatoire
 * - Pas d'usage massif : pour du volume, héberger sa propre instance Nominatim/Photon
 */

require_once __DIR__ . '/geo_location_service.php';

define('GEO_NOMINATIM_BASE', 'https://nominatim.openstreetmap.org');
define('GEO_NOMINATIM_USER_AGENT', 'COLObanes-Marketplace/1.0 (contact@colobanes.local)');
define('GEO_NOMINATIM_TIMEOUT', 6);

/**
 * Respecte la limite de 1 requête/seconde (verrou fichier partagé entre processus).
 */
function geo_nominatim_throttle(): void
{
    $lock_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'colobanes_nominatim.lock';
    $fp = @fopen($lock_file, 'c+');
    if ($fp === false) {
        usleep(1100000);
        return;
    }
    flock($fp, LOCK_EX);
    $last = (float) trim((string) @stream_get_contents($fp));
    $elapsed = microtime(true) - $last;
    if ($elapsed < 1.1) {
        usleep((int) ((1.1 - $elapsed) * 1000000));
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) microtime(true));
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Requête HTTP GET vers Nominatim, JSON décodé ou null.
 */
function geo_nominatim_request(string $path, array $query): ?array
{
    geo_nominatim_throttle();

    $url = GEO_NOMINATIM_BASE . $path . '?' . http_build_query($query);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => GEO_NOMINATIM_TIMEOUT,
            'ignore_errors' => true,
            'header' => "User-Agent: " . GEO_NOMINATIM_USER_AGENT . "\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        // Fallback local WAMP : certificats SSL parfois absents en CLI Windows
        $ctx_no_ssl = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => GEO_NOMINATIM_TIMEOUT,
                'ignore_errors' => true,
                'header' => "User-Agent: " . GEO_NOMINATIM_USER_AGENT . "\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx_no_ssl);
    }
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Géocode une adresse texte en coordonnées GPS.
 *
 * @param string $address Adresse libre (ex: "Marché Colobane, Dakar")
 * @param string|null $country Code pays ISO-2 pour restreindre (ex: 'SN')
 * @return array{lat: float, lng: float, display_name: string}|null
 */
function geo_geocode_address(string $address, ?string $country = null): ?array
{
    $address = trim($address);
    if ($address === '') {
        return null;
    }
    $query = [
        'q' => $address,
        'format' => 'jsonv2',
        'limit' => 1,
        'addressdetails' => 0,
    ];
    if ($country !== null && preg_match('/^[A-Za-z]{2}$/', $country)) {
        $query['countrycodes'] = strtolower($country);
    }
    $data = geo_nominatim_request('/search', $query);
    if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
        return null;
    }
    $lat = geo_parse_coord($data[0]['lat']);
    $lng = geo_parse_coord($data[0]['lon']);
    if (!geo_coords_valid($lat, $lng)) {
        return null;
    }
    return [
        'lat' => $lat,
        'lng' => $lng,
        'display_name' => isset($data[0]['display_name'])
            ? geo_address_concise_from_display_name((string) $data[0]['display_name'])
            : '',
    ];
}

/**
 * Géocodage inverse : coordonnées GPS -> adresse lisible concise.
 */
function geo_reverse_geocode(float $lat, float $lng): ?string
{
    if (!geo_coords_valid($lat, $lng)) {
        return null;
    }
    $data = geo_nominatim_request('/reverse', [
        'lat' => $lat,
        'lon' => $lng,
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'zoom' => 17,
    ]);
    if (empty($data)) {
        return null;
    }
    return geo_address_concise_from_nominatim($data);
}

/**
 * Raccourcit une adresse Nominatim : lieu + quartier + arrondissement + ville + pays.
 */
function geo_address_concise_from_nominatim(array $data): ?string
{
    $segments = geo_address_collect_segments_from_nominatim($data);
    if ($segments === []) {
        return null;
    }
    return geo_address_merge_parts($segments, 5);
}

/**
 * Segments d'adresse depuis coordonnées GPS (géocodage inverse).
 *
 * @return list<string>
 */
function geo_address_segments_from_coords(float $lat, float $lng): array
{
    if (!geo_coords_valid($lat, $lng)) {
        return [];
    }
    $data = geo_nominatim_request('/reverse', [
        'lat' => $lat,
        'lon' => $lng,
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'zoom' => 17,
    ]);
    if (empty($data)) {
        return [];
    }
    return geo_address_collect_segments_from_nominatim($data);
}

/**
 * Extrait le quartier depuis « Commune de Jaxaay - Parcelles ».
 */
function geo_address_extract_quartier_from_commune(string $segment): string
{
    $segment = trim($segment);
    if ($segment === '' || !preg_match('/^commune\s+de\s+(.+)$/iu', $segment, $m)) {
        return '';
    }
    $inner = trim($m[1]);
    if (preg_match('/^(.+?)\s*-\s*(.+)$/', $inner, $m2)) {
        $right = trim($m2[2]);
        return $right !== '' ? $right : trim($m2[1]);
    }
    return $inner;
}

/**
 * Extrait le nom après « Arrondissement de … » ou « Département de … ».
 */
function geo_address_extract_admin_level(string $segment, string $level): string
{
    $segment = trim($segment);
    if ($segment === '') {
        return '';
    }
    $pattern = '/^' . preg_quote($level, '/') . '\s+de\s+(.+)$/iu';
    if (preg_match($pattern, $segment, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * Extrait la ville depuis « Région de Dakar ».
 */
function geo_address_extract_ville_from_region(string $segment): string
{
    return geo_address_extract_admin_level($segment, 'région')
        ?: geo_address_extract_admin_level($segment, 'region');
}

/**
 * @deprecated Utiliser les extracteurs spécifiques par niveau administratif.
 */
function geo_address_admin_extract_label(string $segment): string
{
    $q = geo_address_extract_quartier_from_commune($segment);
    if ($q !== '') {
        return $q;
    }
    $v = geo_address_extract_ville_from_region($segment);
    if ($v !== '') {
        return $v;
    }
    return geo_address_extract_admin_level($segment, 'arrondissement');
}

/**
 * Segments administratifs ou bruit à ignorer dans display_name.
 */
function geo_address_part_is_noise(string $part): bool
{
    $part = trim($part);
    return $part === '' || preg_match('/^\d+$/', $part) || preg_match('/^\d{4,6}$/', $part);
}

/**
 * Séparateur entre parties d'adresse (pas de virgule).
 */
function geo_address_separator(): string
{
    return ' ';
}

/**
 * Découpe un libellé d'adresse en segments (legacy virgule / point médian / espaces).
 */
function geo_address_split_parts(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    if (str_contains($text, ' · ')) {
        return array_values(array_filter(array_map('trim', explode(' · ', $text))));
    }
    if (str_contains($text, ',')) {
        return array_values(array_filter(array_map('trim', explode(',', $text))));
    }
    return preg_split('/\s{2,}/u', $text) ?: [$text];
}

/**
 * Fusionne des segments d'adresse sans doublons (lieu → pays, séparateur espace).
 */
function geo_address_merge_parts(array $parts, int $max = 5): string
{
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string) $p);
        if ($p === '' || in_array($p, $out, true)) {
            continue;
        }
        $out[] = $p;
        if (count($out) >= $max) {
            break;
        }
    }
    return implode(geo_address_separator(), $out);
}

/**
 * Segments « lieu précis » (plan, rue…) — repère en tête du libellé.
 */
function geo_address_part_is_lieu(string $part): bool
{
    $part = trim($part);
    if ($part === '') {
        return true;
    }
    if (preg_match('/^plan\s/i', $part)) {
        return true;
    }
    if (preg_match('/^\d+[\s,.-]/', $part) || preg_match('/^\d+[a-z]?\s/i', $part)) {
        return true;
    }
    if (preg_match('/^(rue|route|avenue|av\.|bd|boulevard|impasse|allée|allee|lot|lotissement|cité|cite|carrefour|marché|marche)\s/iu', $part)) {
        return true;
    }
    return false;
}

/**
 * Collecte les segments : lieu, quartier, arrondissement, ville, pays (sans département).
 *
 * @return list<string>
 */
function geo_address_collect_segments_from_nominatim(array $data): array
{
    if (!empty($data['address']) && is_array($data['address'])) {
        $from_parts = geo_address_collect_segments_from_parts($data['address']);
        if ($from_parts !== []) {
            return $from_parts;
        }
    }
    if (!empty($data['display_name'])) {
        return geo_address_collect_segments_from_display_name((string) $data['display_name']);
    }
    return [];
}

/**
 * @return list<string>
 */
function geo_address_collect_segments_from_parts(array $addr): array
{
    $pick = static function (array $keys) use ($addr): string {
        foreach ($keys as $k) {
            if (!empty($addr[$k]) && is_string($addr[$k])) {
                $v = trim($addr[$k]);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        return '';
    };

    $add = static function (array &$segments, string $value): void {
        $value = trim($value);
        if ($value !== '' && !in_array($value, $segments, true)) {
            $segments[] = $value;
        }
    };

    $segments = [];

    $house = $pick(['house_number']);
    $road = $pick(['road', 'pedestrian', 'footway', 'path', 'residential', 'cycleway']);
    if ($road !== '') {
        $add($segments, $house !== '' ? ($house . ' ' . $road) : $road);
    } else {
        $add($segments, $pick(['amenity', 'shop', 'building', 'tourism', 'leisure', 'place', 'landuse']));
    }

    $quartier = $pick(['suburb', 'quarter', 'neighbourhood', 'hamlet', 'city_district']);
    if ($quartier === '') {
        $municipality = $pick(['municipality']);
        $quartier = geo_address_extract_quartier_from_commune($municipality);
    }
    $add($segments, $quartier);

    foreach (['county', 'state_district', 'district'] as $key) {
        if (!empty($addr[$key]) && is_string($addr[$key])) {
            $raw = trim($addr[$key]);
            $arr = geo_address_extract_admin_level($raw, 'arrondissement');
            $add($segments, $arr !== '' ? $arr : (stripos($raw, 'arrondissement') !== false ? '' : $raw));
            break;
        }
    }

    foreach (['city', 'town', 'village'] as $key) {
        if (!empty($addr[$key])) {
            $add($segments, (string) $addr[$key]);
            break;
        }
    }
    if (count($segments) < 4) {
        foreach (['state', 'region'] as $key) {
            if (!empty($addr[$key]) && is_string($addr[$key])) {
                $ville = geo_address_extract_ville_from_region($addr[$key]);
                $add($segments, $ville !== '' ? $ville : trim($addr[$key]));
                break;
            }
        }
    }

    $add($segments, $pick(['country']));

    return $segments;
}

/**
 * @return list<string>
 */
function geo_address_collect_segments_from_display_name(string $display_name): array
{
    $parts = array_map('trim', explode(',', $display_name));
    $lieu = '';
    $quartier = '';
    $arrondissement = '';
    $ville = '';
    $pays = '';

    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        if (preg_match('/^(senegal|sénégal)$/iu', $p)) {
            $pays = $p;
            continue;
        }
        if (geo_address_part_is_noise($p)) {
            continue;
        }
        if (preg_match('/^(département|departement)\s+de\b/iu', $p)) {
            continue;
        }

        $q = geo_address_extract_quartier_from_commune($p);
        if ($q !== '') {
            $quartier = $q;
            continue;
        }
        $arr = geo_address_extract_admin_level($p, 'arrondissement');
        if ($arr !== '') {
            $arrondissement = $arr;
            continue;
        }
        $reg = geo_address_extract_ville_from_region($p);
        if ($reg !== '') {
            $ville = $reg;
            continue;
        }
        if (preg_match('/^(commune|arrondissement|région|region)\s+de\b/iu', $p)) {
            continue;
        }
        if ($lieu === '') {
            $lieu = $p;
        }
    }

    return array_values(array_filter([$lieu, $quartier, $arrondissement, $ville, $pays], static function ($v) {
        return trim((string) $v) !== '';
    }));
}

/**
 * Construit le libellé : lieu, quartier, arrondissement, ville, pays.
 */
function geo_address_concise_from_parts(array $addr): string
{
    return geo_address_merge_parts(geo_address_collect_segments_from_parts($addr), 5);
}

/**
 * Réduit un display_name Nominatim en libellé zone.
 */
function geo_address_concise_from_display_name(string $display_name): string
{
    return geo_address_merge_parts(geo_address_collect_segments_from_display_name($display_name), 5);
}

/**
 * Indique si le libellé en base mérite d'être mis à jour (ex. ancien format avec lieu précis).
 */
function geo_address_should_upgrade_label(string $stored, string $fresh): bool
{
    $stored = trim($stored);
    $fresh = trim($fresh);
    if ($fresh === '') {
        return false;
    }
    if ($stored === '') {
        return true;
    }
    if ($stored === $fresh) {
        return false;
    }
    if (str_contains($stored, ',') || str_contains($stored, ' · ')) {
        return true;
    }
    $stored_rebuilt = geo_address_concise_from_display_name(str_replace(geo_address_separator(), ', ', $stored));
    if ($stored_rebuilt !== '' && $stored_rebuilt !== $stored) {
        return true;
    }
    $count = static function (string $s): int {
        return count(geo_address_split_parts($s));
    };
    return $count($fresh) >= $count($stored_rebuilt !== '' ? $stored_rebuilt : $stored);
}

/**
 * Normalise un texte d'adresse saisi ou généré (concis, une ligne).
 */
function geo_address_concise_normalize(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $text)));
    if ($text === '') {
        return '';
    }
    if (str_contains($text, ',')) {
        $normalized = geo_address_concise_from_display_name($text);
        return $normalized !== '' ? $normalized : $text;
    }
    $parts = geo_address_split_parts($text);
    if (count($parts) > 1) {
        return geo_address_merge_parts($parts, 5);
    }
    return $text;
}

/**
 * Géocode l'adresse d'une boutique et enregistre ses coordonnées.
 * Utilise boutique_adresse + région + pays pour maximiser la précision.
 */
function geo_geocode_boutique(int $admin_id): bool
{
    global $db;

    if ($admin_id <= 0 || !geo_boutiques_ready()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT boutique_adresse, boutique_region,
                   " . (geo_column_exists('admin', 'boutique_country') ? 'boutique_country' : "'SN' AS boutique_country") . "
            FROM admin WHERE id = :id AND role = 'vendeur'
        ");
        $stmt->execute(['id' => $admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
    if (!$row) {
        return false;
    }

    $parts = [];
    if (!empty($row['boutique_adresse'])) {
        $parts[] = trim((string) $row['boutique_adresse']);
    }
    if (!empty($row['boutique_region'])) {
        $parts[] = str_replace('-', ' ', trim((string) $row['boutique_region']));
    }
    if (empty($parts)) {
        return false;
    }
    $country = !empty($row['boutique_country']) ? strtoupper(trim((string) $row['boutique_country'])) : null;

    $result = geo_geocode_address(implode(', ', $parts), $country);
    if ($result === null) {
        return false;
    }
    return geo_save_boutique_location($admin_id, $result['lat'], $result['lng'], 'adresse');
}
