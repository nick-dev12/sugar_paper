<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Itinéraire livreur — évite les autoroutes à péage.
 * GET : from_lat, from_lng, to_lat, to_lng
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_livreur_gps()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/livreur_routing.php';

$from_lat = isset($_GET['from_lat']) ? (float) $_GET['from_lat'] : 0.0;
$from_lng = isset($_GET['from_lng']) ? (float) $_GET['from_lng'] : 0.0;
$to_lat = isset($_GET['to_lat']) ? (float) $_GET['to_lat'] : 0.0;
$to_lng = isset($_GET['to_lng']) ? (float) $_GET['to_lng'] : 0.0;

$result = livreur_get_route_avoid_tolls($from_lat, $from_lng, $to_lat, $to_lng);

if (empty($result['ok'])) {
    http_response_code(404);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
