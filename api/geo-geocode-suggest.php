<?php
/**
 * Suggestions d'adresses (autocomplétion) — Nominatim OSM.
 * Usage : GET ?q=adresse&limit=6
 */
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 6);

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/geo_geocode_suggest.php';

$suggestions = geo_geocode_suggest($q, 'sn', $limit);
$payload = ['ok' => true, 'suggestions' => $suggestions];
$err = geo_geocode_suggest_last_error();
if ($suggestions === [] && $err !== null) {
    $payload['hint'] = 'geo_unavailable';
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
