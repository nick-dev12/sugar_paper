<?php
/**
 * Enregistrement position livreur — appel interne Node.js
 * POST JSON : livreur_id, latitude, longitude, commande_id, bl_id, accuracy, speed, heading
 */
require_once __DIR__ . '/../../includes/tracking_config.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tracking_json_response(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (!tracking_verify_internal_request()) {
    tracking_json_response(['success' => false, 'error' => 'unauthorized'], 403);
}

$input = $_POST;
$content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$livreur_id = (int) ($input['livreur_id'] ?? 0);
$commande_id = isset($input['commande_id']) ? (int) $input['commande_id'] : null;
$bl_id = isset($input['bl_id']) ? (int) $input['bl_id'] : null;
$cp_id = isset($input['cp_id']) ? (int) $input['cp_id'] : null;
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

if ($livreur_id < 1 || $latitude === null || $longitude === null) {
    tracking_json_response(['success' => false, 'error' => 'invalid_params'], 400);
}

if ($cp_id !== null && $cp_id > 0) {
    $cp = livreur_get_cp_tracking($cp_id);
    if (!$cp || (int) ($cp['livreur_id'] ?? 0) !== $livreur_id) {
        tracking_json_response(['success' => false, 'error' => 'cp_mismatch'], 403);
    }
    if ((int) ($cp['tracking_active'] ?? 0) !== 1) {
        tracking_json_response(['success' => false, 'error' => 'tracking_inactive'], 403);
    }
    $commande_id = null;
    $bl_id = null;
} elseif ($bl_id !== null && $bl_id > 0) {
    $facture = livreur_get_facture_tracking($bl_id);
    if (!$facture || (int) ($facture['livreur_id'] ?? 0) !== $livreur_id) {
        tracking_json_response(['success' => false, 'error' => 'facture_mismatch'], 403);
    }
    if ((int) ($facture['tracking_active'] ?? 0) !== 1) {
        tracking_json_response(['success' => false, 'error' => 'tracking_inactive'], 403);
    }
    $commande_id = null;
    $cp_id = null;
} elseif ($commande_id !== null && $commande_id > 0) {
    $commande = livreur_get_commande_tracking($commande_id);
    if (!$commande || (int) ($commande['livreur_id'] ?? 0) !== $livreur_id) {
        tracking_json_response(['success' => false, 'error' => 'commande_mismatch'], 403);
    }
    if ((int) ($commande['tracking_active'] ?? 0) !== 1) {
        tracking_json_response(['success' => false, 'error' => 'tracking_inactive'], 403);
    }
    $bl_id = null;
    $cp_id = null;
} else {
    $commande_id = null;
    $bl_id = null;
    $cp_id = null;
}

$ok = livreur_save_position(
    $livreur_id,
    $latitude,
    $longitude,
    $commande_id,
    isset($input['accuracy']) ? $input['accuracy'] : null,
    isset($input['speed']) ? $input['speed'] : null,
    isset($input['heading']) ? $input['heading'] : null,
    $bl_id,
    $cp_id
);

tracking_json_response(['success' => $ok]);
