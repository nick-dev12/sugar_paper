<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Itinéraire livreur — évite les autoroutes à péage.
 * GET : from_lat, from_lng, to_lat, to_lng
 */
session_start_persistent();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/livreur_routing.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

$authorized = false;

if (isset($_SESSION['admin_id'])) {
    if (admin_can_livreur_gps() || admin_can_watch_livraison()) {
        $authorized = true;
    }
}

if (!$authorized) {
    $token = trim((string) ($_GET['token'] ?? ''));
    $commande_id = (int) ($_GET['commande_id'] ?? 0);
    $bl_id = (int) ($_GET['bl_id'] ?? 0);
    $cp_id = (int) ($_GET['cp_id'] ?? 0);
    if ($token !== '' && ($commande_id > 0 || $bl_id > 0 || $cp_id > 0)) {
        $row = livreur_get_watch_token_row(
            $token,
            $commande_id > 0 ? $commande_id : null,
            $bl_id > 0 ? $bl_id : null,
            $cp_id > 0 ? $cp_id : null
        );
        if ($row) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$from_lat = isset($_GET['from_lat']) ? (float) $_GET['from_lat'] : 0.0;
$from_lng = isset($_GET['from_lng']) ? (float) $_GET['from_lng'] : 0.0;
$to_lat = isset($_GET['to_lat']) ? (float) $_GET['to_lat'] : 0.0;
$to_lng = isset($_GET['to_lng']) ? (float) $_GET['to_lng'] : 0.0;

$result = livreur_get_route_avoid_tolls($from_lat, $from_lng, $to_lat, $to_lng);

if (empty($result['ok'])) {
    http_response_code(404);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
