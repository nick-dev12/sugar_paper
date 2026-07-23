<?php
/**
 * Géocodage inverse JSON — lat/lng → libellé adresse.
 * GET/POST : lat + lng (ou geo_lat + geo_lng)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/geo_location.php';
require_once __DIR__ . '/../includes/geo_geocode_suggest.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$lat = geo_parse_coord($input['geo_lat'] ?? $input['lat'] ?? null);
$lng = geo_parse_coord($input['geo_lng'] ?? $input['lng'] ?? null);

if (!geo_coords_valid($lat, $lng)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_coords'], JSON_UNESCAPED_UNICODE);
    exit;
}

$label = geo_reverse_geocode_label($lat, $lng);

echo json_encode([
    'ok' => true,
    'label' => $label,
    'address' => $label,
    'lat' => $lat,
    'lng' => $lng,
], JSON_UNESCAPED_UNICODE);
