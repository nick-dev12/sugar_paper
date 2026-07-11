<?php
/**
 * Géocodage adresse → meilleure correspondance (Nominatim OSM).
 * Usage : GET ?q=adresse
 */
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => false, 'error' => 'query_too_short'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/geo_geocode_suggest.php';

$best = geo_geocode_best_match($q, 'sn');
if ($best !== null) {
    echo json_encode([
        'ok' => true,
        'lat' => (float) $best['lat'],
        'lng' => (float) $best['lng'],
        'label' => $best['label'] ?? $q,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
