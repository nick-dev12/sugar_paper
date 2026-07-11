<?php
/**
 * Géocodage inverse JSON — libellé zone (lieu + quartier + arrondissement + ville + pays).
 * GET/POST : geo_lat ou lat, geo_lng ou lng
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/geo_location_service.php';
require_once __DIR__ . '/../includes/geo_geocoder.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$lat = geo_parse_coord($input['geo_lat'] ?? $input['lat'] ?? null);
$lng = geo_parse_coord($input['geo_lng'] ?? $input['lng'] ?? null);

if (!geo_coords_valid($lat, $lng)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Coordonnées invalides']);
    exit;
}

$segments = geo_address_segments_from_coords($lat, $lng);
$address = $segments !== [] ? geo_address_merge_parts($segments, 5) : (geo_reverse_geocode($lat, $lng) ?? '');

echo json_encode([
    'success' => true,
    'address' => $address,
    'segments' => $segments,
    'lat' => $lat,
    'lng' => $lng,
], JSON_UNESCAPED_UNICODE);
