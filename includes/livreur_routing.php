<?php
/**
 * Calcul d'itinéraires livreurs — évitement des autoroutes à péage.
 * Programmation procédurale uniquement.
 */

if (!function_exists('livreur_routing_load_config')) {

    function livreur_routing_load_config() {
        static $config = null;
        if ($config !== null) {
            return $config;
        }
        $path = __DIR__ . '/../config/routing.php';
        if (!is_file($path)) {
            $path = __DIR__ . '/../config/routing.example.php';
        }
        $loaded = require $path;
        $config = is_array($loaded) ? $loaded : [];
        return $config;
    }

    function livreur_routing_config_get($key, $default = null) {
        $config = livreur_routing_load_config();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    function livreur_decode_polyline($encoded, $precision = 6) {
        $coordinates = [];
        if ($encoded === '' || $encoded === null) {
            return $coordinates;
        }

        $index = 0;
        $lat = 0;
        $lng = 0;
        $factor = pow(10, (int) $precision);
        $len = strlen($encoded);

        while ($index < $len) {
            $result = 1;
            $shift = 0;
            do {
                if ($index >= $len) {
                    break 2;
                }
                $b = ord($encoded[$index++]) - 63 - 1;
                $result += $b << $shift;
                $shift += 5;
            } while ($b >= 0x1f);
            $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lat += $dlat;

            $result = 1;
            $shift = 0;
            do {
                if ($index >= $len) {
                    break 2;
                }
                $b = ord($encoded[$index++]) - 63 - 1;
                $result += $b << $shift;
                $shift += 5;
            } while ($b >= 0x1f);
            $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lng += $dlng;

            $coordinates[] = [$lat / $factor, $lng / $factor];
        }

        return $coordinates;
    }

    function livreur_routing_http_post_json($url, $body, $extra_headers = []) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return null;
        }

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: SugarPaper-Livreurs/1.0',
        ], $extra_headers);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 12,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    function livreur_routing_normalize_coords($coords) {
        $normalized = [];
        if (!is_array($coords)) {
            return $normalized;
        }
        foreach ($coords as $pt) {
            if (!is_array($pt) || count($pt) < 2) {
                continue;
            }
            $lat = (float) $pt[0];
            $lng = (float) $pt[1];
            if (!is_finite($lat) || !is_finite($lng)) {
                continue;
            }
            $normalized[] = [$lat, $lng];
        }
        return $normalized;
    }

    function livreur_routing_via_openrouteservice($from_lat, $from_lng, $to_lat, $to_lng, $api_key) {
        $api_key = trim((string) $api_key);
        if ($api_key === '') {
            return null;
        }

        $data = livreur_routing_http_post_json(
            'https://api.openrouteservice.org/v2/directions/driving-car/geojson',
            [
                'coordinates' => [
                    [(float) $from_lng, (float) $from_lat],
                    [(float) $to_lng, (float) $to_lat],
                ],
                'options' => [
                    'avoid_features' => ['tollways'],
                ],
            ],
            ['Authorization: ' . $api_key]
        );

        if (!$data || empty($data['features'][0]['geometry']['coordinates'])) {
            return null;
        }

        $coords = [];
        foreach ($data['features'][0]['geometry']['coordinates'] as $pt) {
            if (!is_array($pt) || count($pt) < 2) {
                continue;
            }
            $coords[] = [(float) $pt[1], (float) $pt[0]];
        }
        $coords = livreur_routing_normalize_coords($coords);
        if (count($coords) < 2) {
            return null;
        }

        $props = $data['features'][0]['properties'] ?? [];
        $summary = $props['summary'] ?? [];
        $distance_m = isset($summary['distance']) ? (float) $summary['distance'] : 0.0;
        $duration_s = isset($summary['duration']) ? (float) $summary['duration'] : 0.0;

        return [
            'coords' => $coords,
            'distance_m' => $distance_m,
            'duration_s' => $duration_s,
            'provider' => 'openrouteservice',
            'avoid_tolls' => true,
        ];
    }

    function livreur_routing_via_valhalla($from_lat, $from_lng, $to_lat, $to_lng, $base_url) {
        $base_url = trim((string) $base_url);
        if ($base_url === '') {
            $base_url = 'https://valhalla1.openstreetmap.de/route';
        }

        $body = [
            'locations' => [
                ['lat' => (float) $from_lat, 'lon' => (float) $from_lng],
                ['lat' => (float) $to_lat, 'lon' => (float) $to_lng],
            ],
            'costing' => 'auto',
            'costing_options' => [
                'auto' => [
                    'use_tolls' => 0,
                ],
            ],
            'directions_options' => [
                'units' => 'kilometers',
            ],
            'format' => 'osrm',
        ];

        $data = livreur_routing_http_post_json($base_url, $body);
        if (!$data || empty($data['routes'][0])) {
            return null;
        }

        $route = $data['routes'][0];
        $coords = [];

        if (!empty($route['geometry']['coordinates']) && is_array($route['geometry']['coordinates'])) {
            foreach ($route['geometry']['coordinates'] as $pt) {
                if (!is_array($pt) || count($pt) < 2) {
                    continue;
                }
                $coords[] = [(float) $pt[1], (float) $pt[0]];
            }
        } elseif (!empty($route['geometry']) && is_string($route['geometry'])) {
            $coords = livreur_decode_polyline($route['geometry'], 6);
        }

        $coords = livreur_routing_normalize_coords($coords);
        if (count($coords) < 2) {
            return null;
        }

        return [
            'coords' => $coords,
            'distance_m' => isset($route['distance']) ? (float) $route['distance'] : 0.0,
            'duration_s' => isset($route['duration']) ? (float) $route['duration'] : 0.0,
            'provider' => 'valhalla',
            'avoid_tolls' => true,
            'has_toll' => !empty($route['has_toll']),
        ];
    }

    /**
     * Itinéraire routier évitant les péages autant que possible.
     */
    function livreur_get_route_avoid_tolls($from_lat, $from_lng, $to_lat, $to_lng) {
        $from_lat = (float) $from_lat;
        $from_lng = (float) $from_lng;
        $to_lat = (float) $to_lat;
        $to_lng = (float) $to_lng;

        if (!is_finite($from_lat) || !is_finite($from_lng) || !is_finite($to_lat) || !is_finite($to_lng)) {
            return ['ok' => false, 'error' => 'invalid_coords'];
        }

        $avoid_tolls = (bool) livreur_routing_config_get('avoid_tolls', true);
        if (!$avoid_tolls) {
            return ['ok' => false, 'error' => 'toll_avoidance_disabled'];
        }

        $ors_key = livreur_routing_config_get('openrouteservice_api_key', '');
        $result = livreur_routing_via_openrouteservice($from_lat, $from_lng, $to_lat, $to_lng, $ors_key);

        if ($result === null) {
            $valhalla_url = livreur_routing_config_get('valhalla_url', 'https://valhalla1.openstreetmap.de/route');
            $result = livreur_routing_via_valhalla($from_lat, $from_lng, $to_lat, $to_lng, $valhalla_url);
        }

        if ($result === null || empty($result['coords'])) {
            return ['ok' => false, 'error' => 'route_not_found'];
        }

        return [
            'ok' => true,
            'coords' => $result['coords'],
            'distance_m' => $result['distance_m'],
            'duration_s' => $result['duration_s'],
            'provider' => $result['provider'],
            'avoid_tolls' => true,
            'has_toll' => !empty($result['has_toll']),
        ];
    }
}
