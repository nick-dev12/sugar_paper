<?php
/**
 * Ping interne Node.js → PHP (secret partagé).
 */
require_once __DIR__ . '/../../includes/tracking_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tracking_json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!tracking_verify_internal_request()) {
    tracking_json_response(['ok' => false, 'error' => 'unauthorized'], 403);
}

tracking_json_response([
    'ok' => true,
    'service' => 'tracking-php',
    'realtime' => tracking_realtime_available(),
]);
