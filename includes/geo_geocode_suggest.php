<?php
/**
 * Suggestions d'adresses (Nominatim) — autonome, sans dépendance poid_lourd.
 */

if (!function_exists('geo_geocode_suggest')) {

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

    function geo_geocode_suggest_throttle() {
        static $last = 0.0;
        $elapsed = microtime(true) - $last;
        if ($last > 0 && $elapsed < 1.1) {
            usleep((int) ((1.1 - $elapsed) * 1000000));
        }
        $last = microtime(true);
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

    function geo_geocode_suggest_nominatim(array $query) {
        geo_geocode_suggest_throttle();

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($query);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "User-Agent: SugarPaper-Livreurs/1.0\r\nAccept: application/json\r\nAccept-Language: fr\r\n",
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
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "User-Agent: SugarPaper-Livreurs/1.0\r\nAccept: application/json\r\nAccept-Language: fr\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
        }

        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    function geo_geocode_suggest_short_label($display_name) {
        $display_name = trim((string) $display_name);
        if ($display_name === '') {
            return '';
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $display_name))));
        if (count($parts) <= 4) {
            return implode(' ', $parts);
        }
        return implode(' ', array_slice($parts, 0, 4));
    }

    /**
     * Variantes de recherche (tolère fautes légères, contexte Sénégal).
     *
     * @return list<string>
     */
    function geo_geocode_suggest_query_variants($query) {
        $query = trim((string) $query);
        $variants = [];
        if ($query !== '') {
            $variants[] = $query;
        }

        $norm = geo_geocode_suggest_normalize($query);
        if ($norm !== '' && $norm !== geo_geocode_suggest_normalize(mb_strtolower($query, 'UTF-8'))) {
            $variants[] = $norm;
        }

        $lower = mb_strtolower($query, 'UTF-8');
        if ($lower !== $query) {
            $variants[] = $lower;
        }

        $has_country = (bool) preg_match('/sen[eé]gal|dakar|thi[eè]s|pikine|guediawaye|almadies|parcelles|mermoz|yoff|keur/i', $query);
        if (!$has_country && mb_strlen($query) >= 2) {
            $variants[] = $query . ', Sénégal';
            if ($norm !== '') {
                $variants[] = $norm . ', senegal';
            }
        }

        $unique = [];
        foreach ($variants as $v) {
            $v = trim($v);
            if ($v !== '' && !in_array($v, $unique, true)) {
                $unique[] = $v;
            }
        }
        return $unique;
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
     * @return list<array{lat: float, lng: float, label: string, full: string, score: int}>
     */
    function geo_geocode_suggest_raw($query, $country = null, $limit = 6) {
        $limit = max(1, min(10, (int) $limit));
        $params = [
            'format' => 'jsonv2',
            'limit' => $limit,
            'addressdetails' => 0,
        ];
        if ($country !== null && preg_match('/^[A-Za-z]{2}$/', (string) $country)) {
            $params['countrycodes'] = strtolower((string) $country);
        }

        $merged = [];
        foreach (geo_geocode_suggest_query_variants($query) as $variant) {
            $params['q'] = $variant;
            $data = geo_geocode_suggest_nominatim($params);
            if (empty($data) || !is_array($data)) {
                continue;
            }
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
                $key = round($lat, 5) . ',' . round($lng, 5);
                if (isset($merged[$key])) {
                    continue;
                }
                $merged[$key] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'label' => $label,
                    'full' => $full !== '' ? $full : $label,
                    'score' => geo_geocode_suggest_relevance_score($query, $label, $full),
                ];
            }
            if (count($merged) >= $limit) {
                break;
            }
        }

        $out = array_values($merged);
        usort($out, static function ($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcmp($a['label'], $b['label']);
        });

        return array_slice($out, 0, $limit);
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
        $raw = geo_geocode_suggest_raw($query, $country, 8);
        if ($raw === []) {
            return null;
        }
        $best = $raw[0];
        unset($best['score']);
        return $best;
    }
}
