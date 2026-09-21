<?php
/**
 * Suggestions d'adresses — Photon (rapide) + Nominatim OSM (secours).
 */

if (!function_exists('geo_geocode_suggest')) {

    define('GEO_SUGGEST_USER_AGENT', 'SugarPaper-Livreurs/1.0 (https://sugar-paper.com; livraison@sugar-paper.com)');
    define('GEO_SUGGEST_HTTP_TIMEOUT', 6);
    /** Bbox Sénégal (ouest, sud, est, nord) pour prioriser les résultats locaux. */
    define('GEO_SUGGEST_SN_BBOX', '-17.8,12.4,-11.3,16.7');

    /** @var string|null Dernière erreur réseau (debug léger côté API). */
    $GLOBALS['geo_geocode_suggest_last_error'] = null;

    function geo_geocode_suggest_last_error() {
        return isset($GLOBALS['geo_geocode_suggest_last_error'])
            ? $GLOBALS['geo_geocode_suggest_last_error']
            : null;
    }

    function geo_geocode_suggest_set_error($message) {
        $GLOBALS['geo_geocode_suggest_last_error'] = $message !== null && $message !== ''
            ? (string) $message
            : null;
    }

    function geo_geocode_suggest_parse_coord($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $n = is_numeric($value) ? (float) $value : null;
        return ($n !== null && is_finite($n)) ? $n : null;
    }

    function geo_geocode_suggest_coords_valid($lat, $lng) {
        return $lat !== null && $lng !== null
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }

    /**
     * Degrés minutes secondes → décimal (N/E positif, S/W négatif).
     */
    function geo_geocode_dms_to_decimal($deg, $min, $sec, $hemisphere) {
        $deg = (float) str_replace(',', '.', (string) $deg);
        $min = (float) str_replace(',', '.', (string) $min);
        $sec = (float) str_replace(',', '.', (string) $sec);
        $dec = abs($deg) + (abs($min) / 60.0) + (abs($sec) / 3600.0);
        $h = strtoupper(trim((string) $hemisphere));
        if ($h === 'S' || $h === 'W') {
            $dec = -$dec;
        }
        return $dec;
    }

    /**
     * Interprète une paire décimale (ordre lat,lng ou lng,lat).
     *
     * @return array{lat: float, lng: float}|null
     */
    function geo_geocode_assign_decimal_pair($a, $b) {
        $a = geo_geocode_suggest_parse_coord($a);
        $b = geo_geocode_suggest_parse_coord($b);
        if ($a === null || $b === null) {
            return null;
        }

        $abs_a = abs($a);
        $abs_b = abs($b);

        if ($abs_a <= 90 && $abs_b <= 180) {
            if ($abs_a <= 17 && $abs_b >= 10 && $abs_b > $abs_a) {
                return ['lat' => $a, 'lng' => $b];
            }
            if ($abs_b <= 17 && $abs_a >= 10 && $abs_a > $abs_b) {
                return ['lat' => $b, 'lng' => $a];
            }
            return ['lat' => $a, 'lng' => $b];
        }

        return null;
    }

    function geo_geocode_is_likely_maps_url($query) {
        $query = trim((string) $query);
        if ($query === '') {
            return false;
        }
        return (bool) preg_match(
            '#^(?:https?://)?(?:maps\.(?:google|app\.goo\.gl)|www\.google\.(?:com|[a-z]{2}(?:\.[a-z]{2})?)/maps|goo\.gl/maps|geo:)#i',
            $query
        ) || (bool) preg_match('#^https?://maps\.app\.goo\.gl/#i', $query);
    }

    function geo_geocode_is_short_maps_url($url) {
        return (bool) preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps|goo\.gl/|bit\.ly/)#i', trim((string) $url));
    }

    /**
     * Suit les redirections des liens courts Google Maps (WhatsApp, SMS…).
     */
    function geo_geocode_resolve_maps_short_url($url) {
        $url = trim((string) $url);
        if ($url === '' || !geo_geocode_is_short_maps_url($url)) {
            return $url;
        }

        if (!function_exists('curl_init')) {
            return $url;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return $url;
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => GEO_SUGGEST_HTTP_TIMEOUT,
            CURLOPT_TIMEOUT => GEO_SUGGEST_HTTP_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . GEO_SUGGEST_USER_AGENT,
                'Accept: text/html,application/xhtml+xml',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_exec($ch);
        $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($final !== '' && ($code === 0 || ($code >= 200 && $code < 400))) {
            return $final;
        }

        return $url;
    }

    /**
     * Extrait lat/lng depuis une URL Google Maps / geo: (sans requête HTTP).
     *
     * @return array{lat: float, lng: float}|null
     */
    function geo_geocode_extract_coords_from_maps_url($url) {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }

        $decoded = rawurldecode($url);

        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        if (preg_match('/[?&](?:q|query)=loc:(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        if (preg_match('/[?&](?:q|query)=(-?\d+(?:\.\d+)?)[,%20\s+]+(-?\d+(?:\.\d+)?)(?:[&]|$)/i', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        if (preg_match('/[?&](?:ll|center|destination|daddr)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        if (preg_match('/^geo:(?:-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?\?q=)?(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i', $decoded, $m)) {
            return geo_geocode_assign_decimal_pair($m[1], $m[2]);
        }

        return null;
    }

    /**
     * Résout un lien Google Maps (court ou long) en coordonnées.
     *
     * @return array{lat: float, lng: float, label: string}|null
     */
    function geo_geocode_try_parse_maps_url($query) {
        $query = trim((string) $query);
        if ($query === '' || !geo_geocode_is_likely_maps_url($query)) {
            return null;
        }

        if (!preg_match('#^https?://#i', $query) && !preg_match('#^geo:#i', $query)) {
            $query = 'https://' . ltrim($query, '/');
        }

        $candidates = [$query];
        if (geo_geocode_is_short_maps_url($query)) {
            $resolved = geo_geocode_resolve_maps_short_url($query);
            if ($resolved !== '' && $resolved !== $query) {
                $candidates[] = $resolved;
            }
        }

        foreach ($candidates as $candidate) {
            $pair = geo_geocode_extract_coords_from_maps_url($candidate);
            if ($pair !== null && geo_geocode_suggest_coords_valid($pair['lat'], $pair['lng'])) {
                return [
                    'lat' => $pair['lat'],
                    'lng' => $pair['lng'],
                    'label' => sprintf('%.6f, %.6f', $pair['lat'], $pair['lng']),
                ];
            }
        }

        return null;
    }

    /**
     * Extrait lat/lng depuis coordonnées DMS, décimales ou lien Google Maps.
     *
     * @return array{lat: float, lng: float, label: string}|null
     */
    function geo_geocode_try_parse_coordinates($query) {
        $query = trim((string) $query);
        if ($query === '') {
            return null;
        }

        $from_maps = geo_geocode_try_parse_maps_url($query);
        if ($from_maps !== null) {
            return $from_maps;
        }

        $normalized = preg_replace('/\s+/u', ' ', $query);

        if (preg_match(
            '/(?P<lat_deg>\d{1,2})\s*[°º˚]\s*(?P<lat_min>\d{1,2})\s*[\'′]?\s*(?P<lat_sec>\d+(?:[.,]\d+)?)\s*["″]?\s*(?P<lat_h>[NnSs])'
            . '.*?'
            . '(?P<lng_deg>\d{1,3})\s*[°º˚]\s*(?P<lng_min>\d{1,2})\s*[\'′]?\s*(?P<lng_sec>\d+(?:[.,]\d+)?)\s*["″]?\s*(?P<lng_h>[EeWw])/u',
            $normalized,
            $m
        )) {
            $lat = geo_geocode_dms_to_decimal($m['lat_deg'], $m['lat_min'], $m['lat_sec'], $m['lat_h']);
            $lng = geo_geocode_dms_to_decimal($m['lng_deg'], $m['lng_min'], $m['lng_sec'], $m['lng_h']);
            if (geo_geocode_suggest_coords_valid($lat, $lng)) {
                return [
                    'lat' => $lat,
                    'lng' => $lng,
                    'label' => sprintf('%.6f, %.6f', $lat, $lng),
                ];
            }
        }

        $decimal_token = '(?:-?\d+(?:[.,]\d+)?)';
        if (preg_match(
            '/^\s*(' . $decimal_token . ')\s*[,;]\s*(' . $decimal_token . ')\s*$/u',
            $normalized,
            $m
        ) || preg_match(
            '/^\s*(' . $decimal_token . ')\s+(' . $decimal_token . ')\s*$/u',
            $normalized,
            $m
        )) {
            $a = str_replace(',', '.', $m[1]);
            $b = str_replace(',', '.', $m[2]);
            $pair = geo_geocode_assign_decimal_pair($a, $b);
            if ($pair !== null && geo_geocode_suggest_coords_valid($pair['lat'], $pair['lng'])) {
                return [
                    'lat' => $pair['lat'],
                    'lng' => $pair['lng'],
                    'label' => sprintf('%.6f, %.6f', $pair['lat'], $pair['lng']),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float, label: string, full: string, score: int}
     */
    function geo_geocode_coordinate_suggestion_row($query, array $coords) {
        $lat = (float) $coords['lat'];
        $lng = (float) $coords['lng'];
        $label = trim((string) ($coords['label'] ?? ''));
        if ($label === '') {
            $label = sprintf('%.6f, %.6f', $lat, $lng);
        }

        $reverse = geo_geocode_reverse($lat, $lng);
        if ($reverse !== '') {
            $label = $reverse;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'label' => $label,
            'full' => trim((string) $query),
            'score' => 1000,
        ];
    }

    /**
     * Limite Nominatim : 1 requête / seconde (verrou fichier inter-processus).
     */
    function geo_geocode_suggest_nominatim_throttle() {
        $lock_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sugar_paper_nominatim.lock';
        $fp = @fopen($lock_file, 'c+');
        if ($fp === false) {
            usleep(1100000);
            return;
        }
        flock($fp, LOCK_EX);
        $last = (float) trim((string) @stream_get_contents($fp));
        $elapsed = microtime(true) - $last;
        if ($last > 0 && $elapsed < 1.1) {
            usleep((int) ((1.1 - $elapsed) * 1000000));
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) microtime(true));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * GET HTTP — cURL en priorité (VPS), file_get_contents en secours.
     *
     * @return string|null
     */
    function geo_geocode_suggest_http_get($url, array $headers = []) {
        $header_lines = array_merge(
            ['Accept: application/json', 'Accept-Language: fr'],
            $headers
        );
        if (strpos(implode("\n", $header_lines), 'User-Agent:') === false) {
            $header_lines[] = 'User-Agent: ' . GEO_SUGGEST_USER_AGENT;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                $curl_opts = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => GEO_SUGGEST_HTTP_TIMEOUT,
                    CURLOPT_TIMEOUT => GEO_SUGGEST_HTTP_TIMEOUT,
                    CURLOPT_HTTPHEADER => $header_lines,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ];
                curl_setopt_array($ch, $curl_opts);
                $raw = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                if (($raw === false || $raw === '') && stripos($err, 'ssl') !== false) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    $raw = curl_exec($ch);
                    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                }
                curl_close($ch);
                if ($raw !== false && $raw !== '' && ($code === 0 || ($code >= 200 && $code < 300))) {
                    geo_geocode_suggest_set_error(null);
                    return $raw;
                }
                if ($err !== '') {
                    geo_geocode_suggest_set_error('curl: ' . $err);
                }
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => GEO_SUGGEST_HTTP_TIMEOUT,
                'ignore_errors' => true,
                'header' => implode("\r\n", $header_lines) . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => GEO_SUGGEST_HTTP_TIMEOUT,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $header_lines) . "\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
        }

        if ($raw === false || $raw === '') {
            if (geo_geocode_suggest_last_error() === null) {
                geo_geocode_suggest_set_error('http_get_failed');
            }
            return null;
        }

        geo_geocode_suggest_set_error(null);
        return $raw;
    }

    function geo_geocode_suggest_nominatim(array $query) {
        geo_geocode_suggest_nominatim_throttle();

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($query);
        $raw = geo_geocode_suggest_http_get($url);
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Autocomplétion via Photon (Komoot) — rapide, sans limite 1 req/s OSM.
     *
     * @return list<array{lat: float, lng: float, label: string, full: string}>
     */
    function geo_geocode_suggest_photon($query, $limit = 6) {
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }

        $params = [
            'q' => $query,
            'limit' => max(1, min(10, (int) $limit)),
            'lang' => 'fr',
            'bbox' => GEO_SUGGEST_SN_BBOX,
        ];

        $url = 'https://photon.komoot.io/api/?' . http_build_query($params);
        $raw = geo_geocode_suggest_http_get($url);
        if ($raw === null) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['features']) || !is_array($data['features'])) {
            return [];
        }

        $out = [];
        foreach ($data['features'] as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }
            $lng = geo_geocode_suggest_parse_coord($coords[0]);
            $lat = geo_geocode_suggest_parse_coord($coords[1]);
            if (!geo_geocode_suggest_coords_valid($lat, $lng)) {
                continue;
            }

            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $full = geo_geocode_suggest_photon_full_label($props);
            $label = geo_geocode_suggest_short_label($full);
            if ($label === '') {
                $label = $full;
            }
            if ($label === '') {
                continue;
            }

            $out[] = [
                'lat' => $lat,
                'lng' => $lng,
                'label' => $label,
                'full' => $full !== '' ? $full : $label,
            ];
        }

        return $out;
    }

    function geo_geocode_suggest_photon_full_label(array $props) {
        $parts = [];
        foreach (['name', 'housenumber', 'street', 'district', 'city', 'state', 'country'] as $key) {
            if (!empty($props[$key])) {
                $val = trim((string) $props[$key]);
                if ($val !== '' && !in_array($val, $parts, true)) {
                    $parts[] = $val;
                }
            }
        }
        if ($parts === [] && !empty($props['country'])) {
            $parts[] = trim((string) $props['country']);
        }
        return implode(', ', $parts);
    }

    function geo_geocode_suggest_short_label($display_name) {
        $display_name = trim((string) $display_name);
        if ($display_name === '') {
            return '';
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $display_name))));
        if (count($parts) <= 4) {
            return implode(', ', $parts);
        }
        return implode(', ', array_slice($parts, 0, 4));
    }

    /**
     * Normalise pour comparaison (minuscules, sans accents, espaces).
     */
    function geo_geocode_suggest_normalize($text) {
        $text = mb_strtolower(trim((string) $text), 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($text === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($ascii !== false && $ascii !== '') {
                $text = mb_strtolower($ascii, 'UTF-8');
            }
        }
        $text = str_replace(["'", '`', '’'], ' ', $text);
        $text = preg_replace('/[^a-z0-9\s\-]/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Requête principale enrichie (une seule variante pour limiter la latence).
     */
    function geo_geocode_suggest_primary_query($query) {
        $query = trim((string) $query);
        if ($query === '') {
            return '';
        }
        $has_country = (bool) preg_match(
            '/sen[eé]gal|dakar|thi[eè]s|pikine|guediawaye|almadies|parcelles|mermoz|yoff|keur|mbour|rufisque|touba|kaolack|ziguinchor|saint[- ]louis|s[eé]n[eé]gal/i',
            $query
        );
        if (!$has_country && mb_strlen($query) >= 2) {
            return $query . ', Sénégal';
        }
        return $query;
    }

    /**
     * Score de pertinence (insensible casse / accents).
     */
    function geo_geocode_suggest_relevance_score($query, $label, $full) {
        $q = geo_geocode_suggest_normalize($query);
        if ($q === '') {
            return 0;
        }

        $l = geo_geocode_suggest_normalize($label);
        $f = geo_geocode_suggest_normalize($full);

        if ($l === $q || $f === $q) {
            return 1000;
        }
        if (strpos($l, $q) === 0 || strpos($f, $q) === 0) {
            return 850;
        }
        if (strpos($l, $q) !== false || strpos($f, $q) !== false) {
            return 700;
        }

        $score = 0;
        $words = array_filter(explode(' ', $q), static function ($w) {
            return mb_strlen($w) >= 2;
        });
        foreach ($words as $word) {
            if (strpos($l, $word) !== false || strpos($f, $word) !== false) {
                $score += 120;
            } elseif (strlen($word) >= 4) {
                similar_text($word, $l, $pctL);
                similar_text($word, $f, $pctF);
                $best = max($pctL, $pctF);
                if ($best >= 72) {
                    $score += (int) round($best);
                }
            }
        }

        return $score;
    }

    /**
     * Fusionne des lignes brutes avec dédoublonnage et score.
     *
     * @param list<array{lat: float, lng: float, label: string, full: string}> $rows
     * @return list<array{lat: float, lng: float, label: string, full: string, score: int}>
     */
    function geo_geocode_suggest_merge_scored($query, array $rows, $limit) {
        $merged = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lat = geo_geocode_suggest_parse_coord($row['lat'] ?? null);
            $lng = geo_geocode_suggest_parse_coord($row['lng'] ?? null);
            if (!geo_geocode_suggest_coords_valid($lat, $lng)) {
                continue;
            }
            $full = isset($row['full']) ? trim((string) $row['full']) : '';
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            if ($label === '') {
                $label = geo_geocode_suggest_short_label($full);
            }
            if ($label === '') {
                continue;
            }
            if ($full === '') {
                $full = $label;
            }
            $key = round($lat, 5) . ',' . round($lng, 5);
            if (isset($merged[$key])) {
                continue;
            }
            $merged[$key] = [
                'lat' => $lat,
                'lng' => $lng,
                'label' => $label,
                'full' => $full,
                'score' => geo_geocode_suggest_relevance_score($query, $label, $full),
            ];
        }

        $out = array_values($merged);
        usort($out, static function ($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcmp($a['label'], $b['label']);
        });

        return array_slice($out, 0, max(1, min(10, (int) $limit)));
    }

    /**
     * @return list<array{lat: float, lng: float, label: string, full: string, score: int}>
     */
    function geo_geocode_suggest_raw($query, $country = null, $limit = 6) {
        geo_geocode_suggest_set_error(null);
        $limit = max(1, min(10, (int) $limit));
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }

        $parsed_coords = geo_geocode_try_parse_coordinates($query);
        if ($parsed_coords !== null) {
            return [geo_geocode_coordinate_suggestion_row($query, $parsed_coords)];
        }

        $rows = [];
        $primary = geo_geocode_suggest_primary_query($query);

        /* 1) Photon — rapide, adapté à l'autocomplétion */
        $photon = geo_geocode_suggest_photon($primary !== '' ? $primary : $query, $limit);
        if ($photon === [] && $primary !== $query && $query !== '') {
            $photon = geo_geocode_suggest_photon($query, $limit);
        }
        foreach ($photon as $row) {
            $rows[] = $row;
        }

        $scored = geo_geocode_suggest_merge_scored($query, $rows, $limit);
        if ($scored !== []) {
            return $scored;
        }

        /* 2) Nominatim — uniquement si Photon n'a rien trouvé */
        $params = [
            'format' => 'jsonv2',
            'q' => $primary !== '' ? $primary : $query,
            'limit' => $limit,
            'addressdetails' => 0,
        ];
        if ($country !== null && preg_match('/^[A-Za-z]{2}$/', (string) $country)) {
            $params['countrycodes'] = strtolower((string) $country);
        }

        $data = geo_geocode_suggest_nominatim($params);
        if (is_array($data)) {
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lat = geo_geocode_suggest_parse_coord($row['lat'] ?? null);
                $lng = geo_geocode_suggest_parse_coord($row['lon'] ?? null);
                if (!geo_geocode_suggest_coords_valid($lat, $lng)) {
                    continue;
                }
                $full = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
                $label = geo_geocode_suggest_short_label($full);
                if ($label === '') {
                    $label = $full;
                }
                $rows[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'label' => $label,
                    'full' => $full !== '' ? $full : $label,
                ];
            }
        }

        return geo_geocode_suggest_merge_scored($query, $rows, $limit);
    }

    /**
     * @return list<array{lat: float, lng: float, label: string, full: string}>
     */
    function geo_geocode_suggest($query, $country = null, $limit = 6) {
        $query = trim((string) $query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $raw = geo_geocode_suggest_raw($query, $country, $limit);
        $out = [];
        foreach ($raw as $row) {
            unset($row['score']);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Meilleure correspondance pour une saisie (Entrée / validation).
     *
     * @return array{lat: float, lng: float, label: string, full: string}|null
     */
    function geo_geocode_best_match($query, $country = null) {
        $parsed_coords = geo_geocode_try_parse_coordinates($query);
        if ($parsed_coords !== null) {
            $row = geo_geocode_coordinate_suggestion_row($query, $parsed_coords);
            unset($row['score']);
            return $row;
        }

        $raw = geo_geocode_suggest_raw($query, $country, 8);
        if ($raw !== [] && ($raw[0]['score'] ?? 0) >= 120) {
            $best = $raw[0];
            unset($best['score']);
            return $best;
        }

        /* Secours Nominatim si Photon vide ou peu pertinent (Entrée / validation) */
        $primary = geo_geocode_suggest_primary_query(trim((string) $query));
        $params = [
            'format' => 'jsonv2',
            'q' => $primary !== '' ? $primary : trim((string) $query),
            'limit' => 5,
            'addressdetails' => 0,
        ];
        if ($country !== null && preg_match('/^[A-Za-z]{2}$/', (string) $country)) {
            $params['countrycodes'] = strtolower((string) $country);
        }
        $data = geo_geocode_suggest_nominatim($params);
        $rows = [];
        if (is_array($data)) {
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lat = geo_geocode_suggest_parse_coord($row['lat'] ?? null);
                $lng = geo_geocode_suggest_parse_coord($row['lon'] ?? null);
                if (!geo_geocode_suggest_coords_valid($lat, $lng)) {
                    continue;
                }
                $full = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
                $label = geo_geocode_suggest_short_label($full);
                if ($label === '') {
                    $label = $full;
                }
                $rows[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'label' => $label,
                    'full' => $full !== '' ? $full : $label,
                ];
            }
        }
        foreach ($rows as $row) {
            $raw[] = array_merge($row, [
                'score' => geo_geocode_suggest_relevance_score($query, $row['label'], $row['full']),
            ]);
        }

        if ($raw === []) {
            return null;
        }
        usort($raw, static function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });
        $best = $raw[0];
        unset($best['score']);
        return $best;
    }

    /**
     * Géocodage inverse — libellé court pour affichage livraison.
     *
     * @return string
     */
    function geo_geocode_reverse($lat, $lng) {
        $lat = geo_geocode_suggest_parse_coord($lat);
        $lng = geo_geocode_suggest_parse_coord($lng);
        if (!geo_geocode_suggest_coords_valid($lat, $lng)) {
            return '';
        }

        geo_geocode_suggest_nominatim_throttle();
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'format' => 'jsonv2',
            'lat' => (string) $lat,
            'lon' => (string) $lng,
            'zoom' => 17,
            'addressdetails' => 1,
        ]);
        $raw = geo_geocode_suggest_http_get($url);
        if ($raw === null) {
            return '';
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return '';
        }

        $full = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
        if ($full === '') {
            return '';
        }

        $label = geo_geocode_suggest_short_label($full);
        return $label !== '' ? $label : $full;
    }
}
