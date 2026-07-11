<?php
/**
 * Géocodage adresse → coordonnées (Nominatim OSM).
 * Usage : GET ?q=adresse
 */
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 3) {
    echo json_encode(['ok' => false, 'error' => 'query_too_short']);
    exit;
}

$geo_file = dirname(__DIR__) . '/poid_lourd/includes/geo_geocoder.php';
if (is_file($geo_file)) {
    require_once $geo_file;
    $result = geo_geocode_address($q, 'sn');
    if ($result && isset($result['lat'], $result['lng'])) {
        echo json_encode([
            'ok' => true,
            'lat' => (float) $result['lat'],
            'lng' => (float) $result['lng'],
            'label' => $result['display_name'] ?? $q,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Repli minimal sans dépendance poid_lourd
usleep(1100000);
$url = 'https://nominatim.openstreetmap.org/search?'
    . http_build_query([
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'sn',
        'q' => $q,
    ]);

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'header' => "User-Agent: SugarPaper-Livreurs/1.0\r\nAccept: application/json\r\n",
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
$data = $raw ? json_decode($raw, true) : null;

if (is_array($data) && !empty($data[0]['lat']) && !empty($data[0]['lon'])) {
    echo json_encode([
        'ok' => true,
        'lat' => (float) $data[0]['lat'],
        'lng' => (float) $data[0]['lon'],
        'label' => $data[0]['display_name'] ?? $q,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
